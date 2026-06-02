<?php
namespace Accounting\Infrastructure\SubLedger;

use Accounting\Domain\Contract\SubLedgerReportInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;

// SỔ TIỀN GỬI NGÂN HÀNG (S03b-DN): Chi tiết thu/chi tiền gửi NH theo TK 1121/1122/1123
//
// Nghiệp vụ: Tương tự CashBookReport nhưng cho TK 112 (Tiền gửi ngân hàng).
// Phản ánh toàn bộ giao dịch qua tài khoản ngân hàng: thu tiền KH chuyển khoản,
// chi trả NCC, lãi NH, phí NH, chuyển tiền giữa các NH.
//
// Cấu trúc:
//   - Số dư đầu kỳ
//   - Các giao dịch: Nhận (Dr 112), Chi (Cr 112), Số dư lũy kế
//   - Số dư cuối kỳ
//
// ĐỐI CHIẾU: Số dư cuối kỳ trên sổ quỹ NH phải khớp với sao kê NH (bank statement).
// Nếu lệch → thực hiện đối chiếu NH (BankReconciliationService) để xác định chênh lệch
// và điều chỉnh (Nợ/Có 112 và 3387/242).
//
// RỦI RO: Số dư NH trên sổ kế toán không khớp sao kê NH → sai BC01 chỉ tiêu Tiền gửi NH
// → ảnh hưởng đánh giá khả năng thanh toán.
//
class BankBookReport implements SubLedgerReportInterface
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
        return 'bank_book';
    }

    public function getParameters(): array
    {
        return [
            ['name' => 'account_code', 'label' => 'TK ngân hàng', 'type' => 'account_select', 'default' => '1121', 'required' => false],
            ['name' => 'from_date', 'label' => 'Từ ngày', 'type' => 'date', 'required' => false],
            ['name' => 'to_date', 'label' => 'Đến ngày', 'type' => 'date', 'required' => false],
        ];
    }

    public function getData(array $params): array
    {
        $accountCode = $params['account_code'] ?? '1121';
        $fromDate = $params['from_date'] ?? null;
        $toDate = $params['to_date'] ?? null;

        $account = $this->accountRepo->findByCode($accountCode);
        if (!$account) {
            throw new \InvalidArgumentException("Không tìm thấy tài khoản ngân hàng mã {$accountCode}.");
        }

        $accountIds = $this->getAccountIds($accountCode);

        $sqlParams = [];
        $dateWhere = '';
        if ($fromDate) {
            $dateWhere .= ' AND t.created_at >= ?';
            $sqlParams[] = $fromDate;
        }
        if ($toDate) {
            $dateWhere .= ' AND t.created_at <= ?';
            $sqlParams[] = $toDate . ' 23:59:59';
        }

        // Tính số dư đầu kỳ
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
        $execParams = array_merge($accountIds, $sqlParams);
        $stmt->execute($execParams);
        $txRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Deduplicate
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

        $headers = ['Ngày', 'Số CT', 'Diễn giải', 'Nhận', 'Chi', 'TK Đối ứng', 'Số dư'];

        return [
            'report_type' => 'bank_book',
            'title' => 'Sổ tiền gửi ngân hàng - ' . $account->getName() . ' (' . $accountCode . ')',
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
