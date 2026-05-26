<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\AccountRepositoryInterface;

// Dịch vụ báo cáo tiền mặt và tiền gửi ngân hàng
//
// Nghiệp vụ: Cung cấp các báo cáo phục vụ quản lý dòng tiền cho Ban Giám đốc
// và Kế toán trưởng, bao gồm:
//   - getCashPosition: Tổng quan số dư tiền mặt (111), tiền gửi (112), tiền đang chuyển (113)
//   - getBankLedger: Sổ chi tiết tài khoản ngân hàng theo ngày
//   - getDailyCashFlow: Báo cáo thu/chi theo ngày phục vụ quản lý dòng tiền
//   - getCashConcentration: Cơ cấu phân bổ tiền giữa các tài khoản ngân hàng
//   - getKPIs: Các chỉ số quản lý tiền (số dư, thu/chi hôm nay, đối chiếu tồn đọng)
//
// Ảnh hưởng: Báo cáo này ảnh hưởng trực tiếp đến quyết định của Ban Giám đốc
// về đầu tư ngắn hạn, vay vốn lưu động, và quản trị rủi ro thanh khoản.
class CashReportService
{
    private \PDO $pdo;
    private AccountRepositoryInterface $accountRepo;

    public function __construct(\PDO $pdo, AccountRepositoryInterface $accountRepo)
    {
        $this->pdo = $pdo;
        $this->accountRepo = $accountRepo;
    }

    // Báo cáo tổng quan vị thế tiền mặt tại thời điểm hiện tại
    //
    // Output: { cash_balance, bank_balance, transit_balance, total, bank_accounts[] }
    //
    // Nghiệp vụ:
    //   - cash_balance: TK 111 (Tiền mặt) — số dư quỹ tiền mặt
    //   - bank_balance: TK 112 (Tiền gửi NH) — tổng số dư tất cả TK ngân hàng
    //   - transit_balance: TK 113 (Tiền đang chuyển) — tiền chưa về tài khoản
    //   - bank_accounts: Chi tiết từng tài khoản ngân hàng (1121, 1122...)
    //
    // Ảnh hưởng BC01: Chỉ tiêu 111 (Tiền) — phải khớp với số liệu BC01
    // Ảnh hưởNG QUẢN TRỊ: Quyết định vay/vốn lưu động dựa trên số dư này
    //
    // GIỚI HẠN: Số dư là REAL-TIME (từ AccountRepository::getBalance)
    //   Không bao gồm giao dịch chưa ghi nhận (cut-off time)
    //   Không bao gồm dự báo dòng tiền
    public function getCashPosition(): array
    {
        $cash = $this->accountRepo->findByCode('111');
        $bank = $this->accountRepo->findByCode('112');
        $transit = $this->accountRepo->findByCode('113');

        $cashBal = $cash ? $cash->getBalance() : 0;
        $bankBal = $bank ? $bank->getBalance() : 0;
        $transitBal = $transit ? $transit->getBalance() : 0;

        $bankAccounts = $this->pdo->query(
            "SELECT a.code, a.name, a.balance FROM accounts a WHERE a.code LIKE '112%' AND a.code != '112' AND LENGTH(a.code) = 4 ORDER BY a.code"
        )->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'cash_balance' => $cashBal,
            'bank_balance' => $bankBal,
            'transit_balance' => $transitBal,
            'total' => $cashBal + $bankBal + $transitBal,
            'bank_accounts' => $bankAccounts,
            'as_at' => date('Y-m-d H:i:s'),
        ];
    }

    // Sổ chi tiết tài khoản ngân hàng (Bank Ledger)
    //
    // Input: fromDate, toDate, bankAccount (mặc định 112)
    // Output: Mảng giao dịch với running_balance (số dư lũy kế)
    //
    // Quy trình:
    //   1. Lấy account theo bankAccount code (hỗ trợ TK con: 1121, 1122...)
    //   2. Query ledger_entries JOIN transactions + accounts
    //   3. Tính running_balance: receipt (+) / payment (-)
    //
    // KHÔNG DÙNG prepared statement cho WHERE clause động
    //   → String concatenation (nhưng có (int) cast cho account_id)
    //   → date params dùng quote() — an toàn nhưng không phải prepared statement
    //
    // RỦI RO TRÌNH TỰ:
    //   running_balance tính từ giao dịch đầu tiên trong kết quả
    //   Nếu fromDate không phải là ngày đầu kỳ → số dư đầu không chính xác
    //   Cần lấy số dư đầu kỳ (opening balance) trước khi tính running
    //
    // RỦI RO DỮ LIỆU LỚN: Nếu khoảng thời gian dài → nhiều giao dịch
    //   → PHP memory issue với mảng lớn
    public function getBankLedger(string $fromDate = null, string $toDate = null, string $bankAccount = '112'): array
    {
        $bank = $this->accountRepo->findByCode($bankAccount);
        if (!$bank) return [];

        $where = "le.account_id = " . (int)$bank->getId();
        if ($fromDate) $where .= " AND t.created_at >= " . $this->pdo->quote($fromDate);
        if ($toDate) $where .= " AND t.created_at <= " . $this->pdo->quote($toDate . ' 23:59:59');

        $rows = $this->pdo->query(
            "SELECT t.id, t.description, t.reference, t.created_at as date, le.amount, le.is_debit,
                    a.code as account_code, a.name as account_name
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN accounts a ON a.id = le.account_id
             WHERE {$where}
             ORDER BY t.created_at ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $running = 0;
        foreach ($rows as &$r) {
            $amt = (float)$r['amount'];
            if ($r['is_debit']) { $running += $amt; $r['type'] = 'receipt'; }
            else { $running -= $amt; $r['type'] = 'payment'; }
            $r['running_balance'] = round($running, 2);
        }

        return $rows;
    }

    // Báo cáo dòng tiền thu/chi theo ngày
    //
    // Input: fromDate, toDate
    // Output: Mảng { date, receipts, payments, transaction_count }
    //
    // Nghiệp vụ:
    //   - receipts: Tổng Nợ TK 111/112 (tiền vào)
    //   - payments: Tổng Có TK 111/112 (tiền ra)
    //   - transaction_count: Số lượng giao dịch trong ngày
    //
    // Ảnh hưởng QUẢN TRỊ:
    //   - Ban Giám đốc dùng báo cáo này để đánh giá dòng tiền hàng ngày
    //   - Phát hiện biến động bất thường (thu/chi đột biến)
    //   - Hỗ trợ quyết định vay ngắn hạn hoặc đầu tư tạm thời
    //
    // KHÔNG phải BC03 (Báo cáo LCTT) — chỉ là báo cáo quản trị nội bộ
    // BC03 phân loại theo hoạt động (KD/ĐT/TC), không theo ngày
    //
    // GIỚI HẠN: Chỉ tính trên TK 111 và 112 (tiền mặt và tiền gửi)
    //   Không bao gồm TK 113 (tiền đang chuyển) — tiền chưa thực sự về
    public function getDailyCashFlow(string $fromDate, string $toDate): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DATE(t.created_at) as date,
                    SUM(CASE WHEN le.is_debit = 1 AND a.code IN ('111','112') THEN le.amount ELSE 0 END) as receipts,
                    SUM(CASE WHEN le.is_debit = 0 AND a.code IN ('111','112') THEN le.amount ELSE 0 END) as payments,
                    COUNT(DISTINCT t.id) as transaction_count
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN accounts a ON a.id = le.account_id
             WHERE a.code IN ('111','112')
               AND DATE(t.created_at) >= ? AND DATE(t.created_at) <= ?
             GROUP BY DATE(t.created_at)
             ORDER BY date ASC"
        );
        $stmt->execute([$fromDate, $toDate]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCashConcentration(): array
    {
        $rows = $this->pdo->query(
            "SELECT a.code, a.name, a.balance
             FROM accounts a
             WHERE (a.code = '111' OR a.code LIKE '112%')
               AND LENGTH(a.code) <= 4
               AND a.code NOT IN ('112')
             ORDER BY a.code"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $total = array_sum(array_column($rows, 'balance'));
        foreach ($rows as &$r) {
            $r['pct'] = $total > 0 ? round($r['balance'] / $total * 100, 1) : 0;
        }

        return ['accounts' => $rows, 'total' => $total];
    }

    // Dashboard KPIs cho quản lý tiền mặt
    //
    // Output: {
    //   cash_balance, bank_balance, total_cash_bank,    — số dư hiện tại
    //   today_receipts, today_payments,                  — giao dịch hôm nay
    //   pending_recon_count,                             — phiên đối chiếu chưa hoàn tất
    //   trend                                            — dòng tiền 7 ngày
    // }
    //
    // Nghiệp vụ:
    //   - pending_recon_count > 0 → cảnh báo: có phiên đối chiếu NH chưa hoàn tất
    //   - today_receipts/payments → biết ngay dòng tiền hôm nay
    //   - trend (7 ngày) → xu hướng tăng/giảm
    //
    // RỦI RO QUYẾT ĐỊNH SAI:
    //   Nếu pending_recon_count sai (do không tạo phiên đối chiếu)
    //   → lãnh đạo nghĩ rằng mọi thứ đã được đối chiếu
    //   → thực tế có thể có sai lệch NH chưa phát hiện
    //
    // LƯU Ý: cash, bank variables ở đầu method chỉ dùng để tính số dư
    //   Thực tế dùng pos array từ getCashPosition() cho hầu hết dữ liệu
    //   → cash và bank có vẻ là dead code (dư thừa)
    public function getKPIs(): array
    {
        $cash = $this->accountRepo->findByCode('111');
        $bank = $this->accountRepo->findByCode('112');
        $pos = $this->getCashPosition();

        // Today's cash receipts and payments
        $stmt = $this->pdo->prepare(
            "SELECT
                SUM(CASE WHEN le.is_debit = 1 AND a.code = '111' THEN le.amount ELSE 0 END) as receipts,
                SUM(CASE WHEN le.is_debit = 0 AND a.code = '111' THEN le.amount ELSE 0 END) as payments
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN accounts a ON a.id = le.account_id
             WHERE a.code = '111' AND DATE(t.created_at) = CURDATE()"
        );
        $stmt->execute();
        $today = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Pending bank reconciliation sessions
        $pendingRecon = $this->pdo->query(
            "SELECT COUNT(*) FROM bank_reconciliation_sessions WHERE status = 'in_progress'"
        )->fetchColumn();

        // 7-day trend
        $trend = $this->getCashFlowTrend(7);

        return [
            'cash_balance' => $pos['cash_balance'],
            'bank_balance' => $pos['bank_balance'],
            'total_cash_bank' => $pos['total'],
            'today_receipts' => (float)($today['receipts'] ?? 0),
            'today_payments' => (float)($today['payments'] ?? 0),
            'pending_recon_count' => (int)$pendingRecon,
            'trend' => $trend,
        ];
    }

    public function getCashFlowTrend(int $days = 7): array
    {
        $to = date('Y-m-d');
        $from = date('Y-m-d', strtotime("-{$days} days"));

        return $this->getDailyCashFlow($from, $to);
    }
}
