<?php
namespace Accounting\Infrastructure\SubLedger;

use Accounting\Domain\Contract\SubLedgerReportInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;

// SỔ QUỸ TIỀN MẶT (S03a-DN): Chi tiết thu/chi tiền mặt theo TK 1111/1112/1113
//
// Nghiệp vụ: Sổ quỹ tiền mặt theo dõi toàn bộ dòng tiền vào/ra khỏi quỹ tiền mặt.
// Áp dụng cho TK 1111 (Tiền mặt VNĐ), 1112 (Tiền mặt ngoại tệ), 1113 (Vàng bạc, đá quý).
//
// Cấu trúc:
//   - Dòng đầu: Số dư đầu kỳ
//   - Các dòng phát sinh: Cột Thu (Dr 111), Cột Chi (Cr 111), Số dư cuối
//   - Dòng cuối: Số dư cuối kỳ
//
// RỦI RO: Sổ quỹ là chứng từ kiểm kê quỹ bắt buộc. Nếu không khớp với kiểm kê thực tế
// → phải lập biên bản kiểm kê quỹ và điều chỉnh (Nợ/Có 111 và 1381/3381).
//
class CashBookReport implements SubLedgerReportInterface
{
    private \PDO $pdo;
    private AccountRepositoryInterface $accountRepo;

    public function __construct(\PDO $pdo, AccountRepositoryInterface $accountRepo)
    {
        $this->pdo = $pdo;
        $this->accountRepo = $accountRepo;
    }

    public function getReportType(): string
    {
        return 'cash_book';
    }

    public function getParameters(): array
    {
        return [
            ['name' => 'account_code', 'label' => 'TK tiền mặt', 'type' => 'account_select', 'default' => '1111', 'required' => false],
            ['name' => 'from_date', 'label' => 'Từ ngày', 'type' => 'date', 'required' => false],
            ['name' => 'to_date', 'label' => 'Đến ngày', 'type' => 'date', 'required' => false],
        ];
    }

    public function getData(array $params): array
    {
        $accountCode = $params['account_code'] ?? '1111';
        $fromDate = $params['from_date'] ?? null;
        $toDate = $params['to_date'] ?? null;

        // Kiểm tra tài khoản tồn tại
        $account = $this->accountRepo->findByCode($accountCode);
        if (!$account) {
            throw new \InvalidArgumentException("Không tìm thấy tài khoản tiền mặt mã {$accountCode}.");
        }

        // Lấy account_id của TK được chọn và các TK con nếu là TK tổng hợp
        $accountIds = $this->getAccountIds($accountCode);

        $params = [];
        $dateWhere = '';
        if ($fromDate) {
            $dateWhere .= ' AND t.created_at >= ?';
            $params[] = $fromDate;
        }
        if ($toDate) {
            $dateWhere .= ' AND t.created_at <= ?';
            $params[] = $toDate . ' 23:59:59';
        }

        // Đếm số dư đầu kỳ
        $openBalance = 0;
        if ($fromDate) {
            $openStmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(CASE WHEN le.is_debit = 1 THEN le.amount ELSE 0 END), 0) as total_dr,
                        COALESCE(SUM(CASE WHEN le.is_debit = 0 THEN le.amount ELSE 0 END), 0) as total_cr
                 FROM ledger_entries le
                 JOIN transactions t ON t.id = le.transaction_id
                 WHERE le.account_id IN (" . implode(',', $accountIds) . ") AND t.created_at < ?"
            );
            $openStmt->execute([$fromDate]);
            $openRow = $openStmt->fetch(\PDO::FETCH_ASSOC);
            $openBalance = (float)$openRow['total_dr'] - (float)$openRow['total_cr'];
        }

        // Lấy phát sinh trong kỳ
        $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
        $sql = "SELECT t.id, t.description, t.reference, t.created_at as date,
                       le.amount, le.is_debit,
                       a2.code as contra_account_code, a2.name as contra_account_name
                FROM ledger_entries le
                JOIN transactions t ON t.id = le.transaction_id
                JOIN accounts a ON a.id = le.account_id
                LEFT JOIN ledger_entries le2 ON le2.transaction_id = le.transaction_id AND le2.id != le.id
                LEFT JOIN accounts a2 ON a2.id = le2.account_id
                WHERE le.account_id IN ({$placeholders}){$dateWhere}
                ORDER BY t.created_at ASC, le.id ASC";
        $stmt = $this->pdo->prepare($sql);

        $execParams = array_merge($accountIds, $params);
        $stmt->execute($execParams);
        $txRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Deduplicate and group by transaction
        $grouped = [];
        foreach ($txRows as $r) {
            $tid = $r['id'];
            if (!isset($grouped[$tid])) {
                $grouped[$tid] = [
                    'id' => $tid,
                    'date' => $r['date'],
                    'reference' => $r['reference'],
                    'description' => $r['description'],
                    'receipt' => 0,
                    'payment' => 0,
                    'contra_account' => '',
                ];
            }
            if ($r['is_debit']) {
                $grouped[$tid]['receipt'] += (float)$r['amount'];
            } else {
                $grouped[$tid]['payment'] += (float)$r['amount'];
            }
            if ($r['contra_account_code']) {
                $ca = $r['contra_account_code'];
                if (strpos($grouped[$tid]['contra_account'], $ca) === false) {
                    $grouped[$tid]['contra_account'] .= ($grouped[$tid]['contra_account'] ? ', ' : '') . $ca;
                }
            }
        }

        // Build result with running balance
        $rows = [];
        $running = $openBalance;
        foreach ($grouped as $g) {
            $running += $g['receipt'] - $g['payment'];
            $rows[] = [
                'date' => substr($g['date'], 0, 10),
                'reference' => $g['reference'],
                'description' => $g['description'],
                'receipt' => $g['receipt'],
                'payment' => $g['payment'],
                'contra_account' => $g['contra_account'],
                'balance' => round($running, 2),
            ];
        }

        $totalReceipt = array_sum(array_column($rows, 'receipt'));
        $totalPayment = array_sum(array_column($rows, 'payment'));

        $headers = ['Ngày', 'Số CT', 'Diễn giải', 'Thu', 'Chi', 'TK Đối ứng', 'Số dư'];

        return [
            'report_type' => 'cash_book',
            'title' => 'Sổ quỹ tiền mặt - ' . $account->getName() . ' (' . $accountCode . ')',
            'period' => ($fromDate ?? 'Đầu kỳ') . ' → ' . ($toDate ?? 'Cuối kỳ'),
            'opening_balance' => round($openBalance, 2),
            'closing_balance' => round($running, 2),
            'headers' => $headers,
            'rows' => $rows,
            'totals' => [
                'total_receipt' => round($totalReceipt, 2),
                'total_payment' => round($totalPayment, 2),
            ],
            'account_info' => [
                'code' => $accountCode,
                'name' => $account->getName(),
                'type' => $account->getType(),
            ],
        ];
    }

    private function getAccountIds(string $accountCode): array
    {
        // Lấy tất cả ID của TK chính và TK con
        $stmt = $this->pdo->prepare(
            "SELECT id FROM accounts WHERE code = ? OR code LIKE ?"
        );
        $stmt->execute([$accountCode, $accountCode . '%']);
        $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        if (empty($ids)) {
            throw new \InvalidArgumentException("Không tìm thấy tài khoản mã {$accountCode}.");
        }
        return $ids;
    }
}
