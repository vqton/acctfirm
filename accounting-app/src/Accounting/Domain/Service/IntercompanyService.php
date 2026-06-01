<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\JournalServiceInterface;

// Dịch vụ đối chiếu và loại trừ giao dịch nội bộ (Intercompany Matching & Elimination)
//
// Nghiệp vụ: Theo Thông tư 99 và Chuẩn mực VAS 11 (Hợp nhất Kinh doanh),
// doanh nghiệp có nhiều đơn vị trực thuộc (chi nhánh, nhà máy) phải thực hiện
// đối chiếu và loại trừ các giao dịch nội bộ khi lập BCTC hợp nhất:
//   - TK 136 (Phải thu nội bộ) ↔ TK 336 (Phải trả nội bộ)
//   - Loại trừ doanh thu/chi phí nội bộ (511, 632, 641, 642...)
//   - Loại trừ lãi/lỗ nội bộ chưa thực hiện trong hàng tồn kho
//
// Process flow:
//   1. Đối chiếu (matchBalances): So sánh số dư 136/336 giữa các cặp entity
//   2. Kiểm tra chênh lệch: matched nếu |difference| < 10 VND
//   3. Loại trừ (eliminate): Tạo bút toán loại trừ cho các cặp đã matched
//   4. Báo cáo hợp nhất (consolidatedReport): Tổng hợp tất cả cặp entity
//
// RỦI RO:
//   - Không loại trừ → BCTC hợp nhất sai doanh thu, công nợ và KQKD
//   - Loại trừ khi chưa matched → tạo chênh lệch mới, audit trail phức tạp
//   - Chỉ loại trừ 136/336, thiếu loại trừ doanh thu/chi phí nội bộ → BC02 sai
//   - Lãi/lỗ nội bộ chưa thực hiện (trong hàng tồn kho, TSCĐ) cần loại trừ riêng
class IntercompanyService
{
    private \PDO $pdo;
    private JournalServiceInterface $journal;

    // Tài khoản công nợ nội bộ
    private const IC_RECEIVABLE = '136';
    private const IC_PAYABLE = '336';

    public function __construct(\PDO $pdo, JournalServiceInterface $journal)
    {
        $this->pdo = $pdo;
        $this->journal = $journal;
    }

    // Lấy danh sách các entity
    public function getEntities(): array
    {
        return $this->pdo->query("SELECT * FROM accounting_entities WHERE is_active = 1 ORDER BY code")->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Đối chiếu số dư IC giữa các cặp entity
    //
    // Process flow:
    //   1. Xác định entity_id và period_code (nếu có) để lọc
    //   2. Query 1: Lấy tổng số dư 136/336 của entity hiện tại với từng counterparty
    //   3. Query 2 (per counterparty): Lấy số dư đối ứng từ phía counterparty
    //   4. So sánh: matched nếu |receivable_balance + contra_balance| < 10 VND
    //
    // Logic đối chiếu:
    //   Số dư của entity A (136 - 336) + Số dư của entity B (336 - 136) ≈ 0
    //   Nghĩa là: A ghi Nợ 136 / Có 336 → B ghi Nợ 336 / Có 136
    //
    // FORBIDDEN: SQL injection risk tại WHERE (a.code = '" . self::IC_RECEIVABLE . "')
    // Mặc dù IC_RECEIVABLE và IC_PAYABLE là const (an toàn), pattern này là tiền lệ xấu.
    // Nên dùng prepared statement với placeholder cho account code.
    //
    // Edge cases:
    //   - periodCode = null: đối chiếu tất cả các kỳ (cả năm)
    //   - Không có counterparty nào: trả về rỗng
    //   - Chênh lệch > 10 VND: đánh dấu unmatched (cần điều tra)
    //
    // Performance: Với N counterparty, cần N+1 queries (N+1 problem).
    // Nếu entity có nhiều counterparty (> 20), cân nhắc tối ưu bằng bulk query.
    public function matchBalances(int $entityId, ?string $periodCode = null): array
    {
        $where = '';
        $params = [$entityId];
        if ($periodCode) {
            $where = 'AND t.date >= (SELECT start_date FROM accounting_periods WHERE period_code = ?)
                      AND t.date <= (SELECT end_date FROM accounting_periods WHERE period_code = ?)';
            $params[] = $periodCode;
            $params[] = $periodCode;
        }

        // Bước 1: Lấy tất cả số dư IC (136 + 336) của entity hiện tại, gộp theo counterparty
        //
        // Lưu ý: a.code = '136' OR a.code = '336' lọc các giao dịch nội bộ.
        // receivable_balance có thể dương (phải thu) hoặc âm (phải trả) tùy theo bản chất.
        // Nếu receivable_balance > 0: entity này đang phải thu counterparty
        // Nếu receivable_balance < 0: entity này đang phải trả counterparty
        $sql = "
            SELECT t.related_entity_id AS counterparty_id,
                   e.code AS counterparty_code,
                   e.name AS counterparty_name,
                   SUM(CASE WHEN le.is_debit = 1 THEN le.amount ELSE -le.amount END) AS receivable_balance,
                   COUNT(*) AS txn_count,
                   MIN(t.date) AS oldest_date,
                   MAX(t.date) AS newest_date
            FROM ledger_entries le
            JOIN transactions t ON t.id = le.transaction_id
            LEFT JOIN accounting_entities e ON e.id = t.related_entity_id
            JOIN accounts a ON a.id = le.account_id
            WHERE (a.code = '" . self::IC_RECEIVABLE . "' OR a.code = '" . self::IC_PAYABLE . "')
              AND t.entity_id = ?
              AND t.related_entity_id IS NOT NULL
              {$where}
            GROUP BY t.related_entity_id
            ORDER BY e.code
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Bước 2: Với mỗi counterparty, lấy số dư đối ứng để kiểm tra khớp
        $items = [];
        foreach ($rows as $r) {
            // Contra balance: số dư từ phía counterparty nhìn về entity hiện tại
            // Nếu entity ghi 136 (phải thu) → counterparty phải ghi 336 (phải trả)
            // → receivable_balance + contra_balance ≈ 0
            $counterparties = [$r['counterparty_id'], $entityId];
            $stmt2 = $this->pdo->prepare("
                SELECT SUM(CASE WHEN le.is_debit = 1 THEN le.amount ELSE -le.amount END) AS contra_balance
                FROM ledger_entries le
                JOIN transactions t ON t.id = le.transaction_id
                JOIN accounts a ON a.id = le.account_id
                WHERE (a.code = '" . self::IC_RECEIVABLE . "' OR a.code = '" . self::IC_PAYABLE . "')
                  AND t.entity_id = ?
                  AND t.related_entity_id = ?
            ");
            $stmt2->execute($counterparties);
            $contra = (float)$stmt2->fetchColumn();
            $receivable = (float)$r['receivable_balance'];
            $difference = abs($receivable + $contra); // Lý tưởng = 0

            $items[] = [
                'counterparty_id' => (int)$r['counterparty_id'],
                'counterparty_code' => $r['counterparty_code'] ?? 'N/A',
                'counterparty_name' => $r['counterparty_name'] ?? 'Unknown',
                'receivable_balance' => $receivable,
                'contra_balance' => $contra,
                'difference' => $difference,
                // Ngưỡng 10 VND: dung sai cho sai số làm tròn và chênh lệch ngân hàng nhỏ
                'status' => $difference < 10 ? 'matched' : 'unmatched',
                'txn_count' => (int)$r['txn_count'],
                'oldest_date' => $r['oldest_date'],
                'newest_date' => $r['newest_date'],
            ];
        }

        return [
            'entity_id' => $entityId,
            'period_code' => $periodCode ?? 'ALL',
            'items' => $items,
            'total_unmatched' => count(array_filter($items, fn($i) => $i['status'] === 'unmatched')),
        ];
    }

    // Loại trừ giao dịch nội bộ (Intercompany Elimination)
    //
    // Nghiệp vụ: Khi lập BCTC hợp nhất, các khoản công nợ nội bộ phải được loại trừ:
    //   - Nếu entity A có phải thu entity B → Nợ 336 (Phải trả nội bộ) / Có 136 (Phải thu nội bộ)
    //   - Nếu entity A có phải trả entity B → Nợ 136 / Có 336
    //
    // Process flow:
    //   1. Gọi matchBalances() để lấy danh sách cặp đã đối chiếu
    //   2. Chỉ xử lý các cặp có status = 'matched'
    //   3. Với mỗi cặp, xác định hướng Dr/Cr dựa trên dấu của receivable_balance
    //   4. Tạo bút toán loại trừ qua JournalService
    //
    // RỦI RO:
    //   - ELIMINATE CHỈ KHI MATCHED: Nếu thực hiện eliminate khi chưa đối chiếu đầy đủ
    //     → tạo bút toán sai → số dư 136/336 sau loại trừ không về 0 → BC01 sai
    //   - Bút toán loại trừ KHÔNG được post vào kỳ kế toán thông thường — phải post vào
    //     kỳ hợp nhất riêng (consolidation period) hoặc đánh dấu là consolidation entry
    //   - $allowControl = true: bỏ qua kiểm tra TK tổng hợp vì 136/336 là TK chi tiết
    //   - Loại trừ 2 chiều (Dr/Cr ngược) dễ nhầm lẫn — cần kiểm tra kỹ dấu của balance
    public function eliminate(int $entityId, string $createdBy, ?string $periodCode = null): array
    {
        $matchResult = $this->matchBalances($entityId, $periodCode);
        $eliminations = [];

        foreach ($matchResult['items'] as $item) {
            if ($item['status'] !== 'matched') continue;
            $balance = $item['receivable_balance'];
            if (abs($balance) < 10) continue;

            // Tạo bút toán loại trừ
            $description = "IC elimination: entity {$matchResult['entity_id']} ↔ {$item['counterparty_id']} ({$item['counterparty_code']})";
            $reference = 'IC-ELIM-' . date('Ymd') . '-' . $item['counterparty_id'];

            // Xác định hướng bút toán loại trừ dựa trên dấu của receivable_balance:
            //
            // Nếu balance > 0: Entity hiện tại đang có phải thu counterparty
            //   → Bút toán loại trừ: Dr 336 (giảm phải trả nội bộ) / Cr 136 (giảm phải thu nội bộ)
            //   Giải thích: Từ góc nhìn BCTC hợp nhất, 136 và 336 tự triệt tiêu.
            //   Khi ghi Nợ 336 (giảm nợ phải trả) và Có 136 (giảm nợ phải thu),
            //   cả hai đều về 0 → không còn giao dịch nội bộ trên BC01 hợp nhất.
            //
            // Nếu balance < 0: Entity hiện tại đang có phải trả counterparty
            //   → Bút toán loại trừ ngược lại: Dr 136 / Cr 336
            //
            // Lưu ý: Voucher type = 'IC_ELIM', source = 'intercompany' để dễ truy xuất
            // $allowControl = true: 136 và 336 có thể là TK tổng hợp
            // TRANSACTION: JournalService tự quản lý
            if ($balance > 0) {
                $this->journal->postEntry(
                    $description,
                    $reference,
                    [
                        ['account_code' => self::IC_PAYABLE, 'amount' => $balance, 'is_debit' => true],
                        ['account_code' => self::IC_RECEIVABLE, 'amount' => $balance, 'is_debit' => false],
                    ],
                    $createdBy,
                    true,
                    'intercompany_elimination',
                    null,
                    'IC_ELIM',
                    'intercompany'
                );
            } else {
                $loss = abs($balance);
                $this->journal->postEntry(
                    $description,
                    $reference,
                    [
                        ['account_code' => self::IC_RECEIVABLE, 'amount' => $loss, 'is_debit' => true],
                        ['account_code' => self::IC_PAYABLE, 'amount' => $loss, 'is_debit' => false],
                    ],
                    $createdBy,
                    true,
                    'intercompany_elimination',
                    null,
                    'IC_ELIM',
                    'intercompany'
                );
            }

            $eliminations[] = [
                'counterparty_id' => $item['counterparty_id'],
                'counterparty_code' => $item['counterparty_code'],
                'amount' => $balance,
                'reference' => $reference,
            ];
        }

        return [
            'entity_id' => $entityId,
            'eliminations_count' => count($eliminations),
            'eliminations' => $eliminations,
        ];
    }

    // Báo cáo tổng hợp IC: tất cả entity, tất cả cặp
    //
    // Process: Lấy tất cả entity → gọi matchBalances() cho mỗi entity
    // Performance: O(n * m) queries với n = số entity, m = số counterparty mỗi entity
    //   Nếu có 10 entity, mỗi entity có 5 counterparty → ~60 queries
    //   Cân nhắc tối ưu nếu hệ thống có > 20 entity
    //
    // Công dụng: Kế toán trưởng dùng báo cáo này để đánh giá tổng quan trước khi
    // thực hiện loại trừ. Nếu còn unmatched nhiều, cần đối chiếu bổ sung trước
    // khi khóa sổ hợp nhất.
    public function consolidatedReport(): array
    {
        $entities = $this->getEntities();
        $allPairs = [];

        foreach ($entities as $e) {
            $match = $this->matchBalances((int)$e['id']);
            foreach ($match['items'] as $item) {
                $allPairs[] = [
                    'entity_id' => $match['entity_id'],
                    'entity_code' => $e['code'],
                    'entity_name' => $e['name'],
                ] + $item;
            }
        }

        return [
            'entity_count' => count($entities),
            'pair_count' => count($allPairs),
            'unmatched_count' => count(array_filter($allPairs, fn($p) => $p['status'] === 'unmatched')),
            'pairs' => $allPairs,
        ];
    }
}
