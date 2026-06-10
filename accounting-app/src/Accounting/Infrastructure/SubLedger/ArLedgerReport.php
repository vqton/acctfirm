<?php
namespace Accounting\Infrastructure\SubLedger;

use Accounting\Domain\Contract\SubLedgerReportInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;

/**
 * SỔ CHI TIẾT CÔNG NỢ PHẢI THU (S13-DN): Theo dõi công nợ theo từng khách hàng.
 *
 * Nghiệp vụ: Báo cáo chi tiết các khoản phải thu (TK 131) theo từng khách hàng.
 * Dữ liệu lấy từ bảng ar_invoices và ar_payments.
 *
 * ĐỐI SOÁT: Tổng số dư tất cả KH phải khớp với số dư TK 131 trên sổ cái.
 */
class ArLedgerReport implements SubLedgerReportInterface
{
    private \PDO $pdo;
    private AccountRepositoryInterface $accountRepo;

    /**
     * @param \PDO $pdo Kết nối PDO.
     * @param AccountRepositoryInterface $accountRepo Repository tài khoản.
     */
    public function __construct(\PDO $pdo, AccountRepositoryInterface $accountRepo)
    {
        $this->pdo = $pdo;
        $this->accountRepo = $accountRepo;
    }

    /**
     * Lấy loại báo cáo.
     *
     * @return string 'ar_ledger'.
     */
    public function getReportType(): string
    {
        return 'ar_ledger';
    }

    /**
     * Lấy tham số báo cáo.
     *
     * @return array Mảng tham số với name, label, type, required.
     */
    public function getParameters(): array
    {
        return [
            ['name' => 'customer_id', 'label' => 'Khách hàng', 'type' => 'customer_select', 'required' => false],
            ['name' => 'from_date', 'label' => 'Từ ngày', 'type' => 'date', 'required' => false],
            ['name' => 'to_date', 'label' => 'Đến ngày', 'type' => 'date', 'required' => false],
        ];
    }

    /**
     * Lấy dữ liệu sổ chi tiết công nợ phải thu.
     *
     * Nếu có customer_id, trả về chi tiết theo KH; nếu không, trả về tổng hợp.
     *
     * @param array $params Tham số: customer_id, from_date, to_date.
     * @return array Dữ liệu báo cáo.
     */
    public function getData(array $params): array
    {
        $customerId = $params['customer_id'] ?? null;
        $fromDate = $params['from_date'] ?? null;
        $toDate = $params['to_date'] ?? null;

        if ($customerId) {
            return $this->getCustomerDetail($customerId, $fromDate, $toDate);
        }

        return $this->getAllCustomersSummary($fromDate, $toDate);
    }

    /**
     * Lấy chi tiết công nợ theo khách hàng.
     *
     * @param string $customerId ID khách hàng.
     * @param string|null $fromDate Từ ngày.
     * @param string|null $toDate Đến ngày.
     * @return array Dữ liệu chi tiết công nợ.
     */
    private function getCustomerDetail(string $customerId, ?string $fromDate, ?string $toDate): array
    {
        $customer = $this->getCustomer($customerId);
        $customerName = $customer ? ($customer['name'] ?? $customer['code'] ?? $customerId) : $customerId;

        // Lấy số dư đầu kỳ từ các hóa đơn trước fromDate
        $openingBalance = 0;
        if ($fromDate) {
            $openStmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(balance), 0) as total_balance
                 FROM ar_invoices
                 WHERE customer_id = ? AND created_at < ? AND status != 'prepayment'"
            );
            $openStmt->execute([$customerId, $fromDate]);
            $openingBalance = (float)$openStmt->fetchColumn();
        }

        // Lấy hóa đơn và thanh toán trong kỳ
        $rows = $this->fetchCustomerTransactions($customerId, $fromDate, $toDate);

        $headers = ['Ngày', 'Số CT', 'Diễn giải', 'Phát sinh Nợ', 'Phát sinh Có', 'Số dư'];
        $running = $openingBalance;

        $resultRows = [];
        foreach ($rows as $r) {
            $dr = (float)($r['dr_amount'] ?? 0);
            $cr = (float)($r['cr_amount'] ?? 0);
            $running += $dr - $cr;
            $resultRows[] = [
                'date' => $r['date'],
                'reference' => $r['reference'],
                'description' => $r['description'],
                'debit' => $dr,
                'credit' => $cr,
                'balance' => round($running, 2),
            ];
        }

        return [
            'report_type' => 'ar_ledger',
            'title' => 'Sổ chi tiết công nợ phải thu - ' . $customerName,
            'period' => ($fromDate ?? 'Đầu kỳ') . ' → ' . ($toDate ?? 'Cuối kỳ'),
            'opening_balance' => round($openingBalance, 2),
            'closing_balance' => round($running, 2),
            'headers' => $headers,
            'rows' => $resultRows,
            'totals' => [
                'total_debit' => round(array_sum(array_column($resultRows, 'debit')), 2),
                'total_credit' => round(array_sum(array_column($resultRows, 'credit')), 2),
            ],
            'entity_info' => [
                'id' => $customerId,
                'name' => $customerName,
                'type' => 'customer',
            ],
        ];
    }

    /**
     * Lấy tổng hợp công nợ theo tất cả khách hàng.
     *
     * @param string|null $fromDate Từ ngày.
     * @param string|null $toDate Đến ngày.
     * @return array Dữ liệu tổng hợp.
     */
    private function getAllCustomersSummary(?string $fromDate, ?string $toDate): array
    {
        $dateWhere = '';
        $params = [];
        if ($fromDate) {
            $dateWhere .= ' AND i.created_at >= ?';
            $params[] = $fromDate;
        }
        if ($toDate) {
            $dateWhere .= ' AND i.created_at <= ?';
            $params[] = $toDate . ' 23:59:59';
        }

        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.code, c.name,
                    COALESCE(SUM(CASE WHEN i.balance > 0 THEN i.balance ELSE 0 END), 0) as total_balance,
                    COALESCE(SUM(CASE WHEN i.status NOT IN ('paid','written_off') THEN i.gross_amount ELSE 0 END), 0) as total_gross,
                    COALESCE(SUM(CASE WHEN i.status NOT IN ('paid','written_off') THEN i.gross_amount - i.balance ELSE 0 END), 0) as total_paid
             FROM customers c
             LEFT JOIN ar_invoices i ON i.customer_id = c.id
             WHERE 1=1{$dateWhere}
             GROUP BY c.id, c.code, c.name
             ORDER BY c.name ASC"
        );
        $stmt->execute($params);
        $customers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $headers = ['Mã KH', 'Tên khách hàng', 'Tổng phải thu', 'Đã thu', 'Số dư cuối'];
        $rows = [];
        foreach ($customers as $c) {
            $rows[] = [
                'code' => $c['code'] ?? '',
                'name' => $c['name'] ?? '',
                'total' => round((float)$c['total_gross'], 2),
                'paid' => round((float)$c['total_paid'], 2),
                'balance' => round((float)$c['total_balance'], 2),
            ];
        }

        return [
            'report_type' => 'ar_ledger',
            'title' => 'Sổ chi tiết công nợ phải thu - Tổng hợp theo khách hàng',
            'period' => ($fromDate ?? 'Đầu kỳ') . ' → ' . ($toDate ?? 'Cuối kỳ'),
            'opening_balance' => 0,
            'closing_balance' => round(array_sum(array_column($rows, 'balance')), 2),
            'headers' => $headers,
            'rows' => $rows,
            'totals' => [
                'total_balance' => round(array_sum(array_column($rows, 'balance')), 2),
            ],
        ];
    }

    /**
     * Lấy danh sách giao dịch của khách hàng (hóa đơn + thanh toán).
     *
     * @param string $customerId ID khách hàng.
     * @param string|null $fromDate Từ ngày.
     * @param string|null $toDate Đến ngày.
     * @return array Mảng giao dịch đã sắp xếp theo ngày.
     */
    private function fetchCustomerTransactions(string $customerId, ?string $fromDate, ?string $toDate): array
    {
        $transactions = [];

        // Hóa đơn = phát sinh Nợ 131
        $params = [$customerId];
        $dateWhere = '';
        if ($fromDate) {
            $dateWhere .= ' AND i.created_at >= ?';
            $params[] = $fromDate;
        }
        if ($toDate) {
            $dateWhere .= ' AND i.created_at <= ?';
            $params[] = $toDate . ' 23:59:59';
        }

        $invStmt = $this->pdo->prepare(
            "SELECT i.invoice_date as date, i.invoice_number as reference, i.description,
                    i.gross_amount as dr_amount, 0 as cr_amount
             FROM ar_invoices i
             WHERE i.customer_id = ?{$dateWhere} AND i.status != 'prepayment'
             ORDER BY i.invoice_date ASC"
        );
        $invStmt->execute($params);
        foreach ($invStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $r['description'] = 'Hóa đơn: ' . ($r['description'] ?? '');
            $transactions[] = $r;
        }
        unset($params);

        // Thanh toán = phát sinh Có 131
        $params = [$customerId];
        $dateWhere = '';
        if ($fromDate) {
            $dateWhere .= ' AND p.created_at >= ?';
            $params[] = $fromDate;
        }
        if ($toDate) {
            $dateWhere .= ' AND p.created_at <= ?';
            $params[] = $toDate . ' 23:59:59';
        }

        $payStmt = $this->pdo->prepare(
            "SELECT p.created_at as date, t.reference, t.description,
                    0 as dr_amount, p.amount as cr_amount
             FROM ar_payments p
             JOIN ar_invoices i ON i.id = p.ar_invoice_id
             JOIN transactions t ON t.id = p.transaction_id
             WHERE i.customer_id = ?{$dateWhere}
             ORDER BY p.created_at ASC"
        );
        $payStmt->execute($params);
        foreach ($payStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $r['description'] = 'Thanh toán: ' . ($r['description'] ?? '');
            $transactions[] = $r;
        }

        // Sắp xếp theo ngày
        usort($transactions, function ($a, $b) {
            return strcmp($a['date'] ?? '', $b['date'] ?? '');
        });

        return $transactions;
    }

    /**
     * Lấy thông tin khách hàng.
     *
     * @param string $customerId ID khách hàng.
     * @return array|null Thông tin khách hàng hoặc null.
     */
    private function getCustomer(string $customerId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$customerId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
