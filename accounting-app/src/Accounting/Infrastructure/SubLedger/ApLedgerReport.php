<?php
namespace Accounting\Infrastructure\SubLedger;

use Accounting\Domain\Contract\SubLedgerReportInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;

// SỔ CHI TIẾT CÔNG NỢ PHẢI TRẢ (S13-DN): Theo dõi công nợ theo từng nhà cung cấp
//
// Nghiệp vụ: Báo cáo chi tiết các khoản phải trả (TK 331) theo từng nhà cung cấp.
// Dữ liệu lấy từ bảng ap_invoices và ap_payments.
//
// Cấu trúc:
//   - Mỗi NCC một bảng riêng hoặc lọc theo NCC
//   - Cột: Ngày, Số CT, Diễn giải, Phát sinh Nợ (giảm), Phát sinh Có (tăng), Số dư
//
// LƯU Ý VỀ DẤU: Công nợ phải trả có số dư bên Có (dư Có 331).
// - Phát sinh Có 331: Tăng công nợ (mua hàng, dịch vụ)
// - Phát sinh Nợ 331: Giảm công nợ (thanh toán, trả lại, chiết khấu)
//
class ApLedgerReport implements SubLedgerReportInterface
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
        return 'ap_ledger';
    }

    public function getParameters(): array
    {
        return [
            ['name' => 'supplier_id', 'label' => 'Nhà cung cấp', 'type' => 'supplier_select', 'required' => false],
            ['name' => 'from_date', 'label' => 'Từ ngày', 'type' => 'date', 'required' => false],
            ['name' => 'to_date', 'label' => 'Đến ngày', 'type' => 'date', 'required' => false],
        ];
    }

    public function getData(array $params): array
    {
        $supplierId = $params['supplier_id'] ?? null;
        $fromDate = $params['from_date'] ?? null;
        $toDate = $params['to_date'] ?? null;

        if ($supplierId) {
            return $this->getSupplierDetail($supplierId, $fromDate, $toDate);
        }

        return $this->getAllSuppliersSummary($fromDate, $toDate);
    }

    private function getSupplierDetail(string $supplierId, ?string $fromDate, ?string $toDate): array
    {
        $supplier = $this->getSupplier($supplierId);
        $supplierName = $supplier ? ($supplier['name'] ?? $supplier['code'] ?? $supplierId) : $supplierId;

        // Lấy số dư đầu kỳ từ các hóa đơn trước fromDate
        $openingBalance = 0;
        if ($fromDate) {
            $openStmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(balance), 0) as total_balance
                 FROM ap_invoices
                 WHERE supplier_id = ? AND created_at < ? AND status != 'prepayment'"
            );
            $openStmt->execute([$supplierId, $fromDate]);
            $openingBalance = (float)$openStmt->fetchColumn();
        }

        // Lấy hóa đơn và thanh toán trong kỳ
        $rows = $this->fetchSupplierTransactions($supplierId, $fromDate, $toDate);

        $headers = ['Ngày', 'Số CT', 'Diễn giải', 'Phát sinh Nợ (giảm)', 'Phát sinh Có (tăng)', 'Số dư'];
        $running = $openingBalance;

        $resultRows = [];
        foreach ($rows as $r) {
            $cr = (float)($r['cr_amount'] ?? 0);
            $dr = (float)($r['dr_amount'] ?? 0);
            // For liability: Cr increases balance, Dr decreases balance
            $running += $cr - $dr;
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
            'report_type' => 'ap_ledger',
            'title' => 'Sổ chi tiết công nợ phải trả - ' . $supplierName,
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
                'id' => $supplierId,
                'name' => $supplierName,
                'type' => 'supplier',
            ],
        ];
    }

    private function getAllSuppliersSummary(?string $fromDate, ?string $toDate): array
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
            "SELECT s.id, s.code, s.name,
                    COALESCE(SUM(CASE WHEN i.balance > 0 THEN i.balance ELSE 0 END), 0) as total_balance,
                    COALESCE(SUM(CASE WHEN i.status NOT IN ('paid','written_off') THEN i.gross_amount ELSE 0 END), 0) as total_gross,
                    COALESCE(SUM(CASE WHEN i.status NOT IN ('paid','written_off') THEN i.gross_amount - i.balance ELSE 0 END), 0) as total_paid
             FROM suppliers s
             LEFT JOIN ap_invoices i ON i.supplier_id = s.id
             WHERE 1=1{$dateWhere}
             GROUP BY s.id, s.code, s.name
             ORDER BY s.name ASC"
        );
        $stmt->execute($params);
        $suppliers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $headers = ['Mã NCC', 'Tên nhà cung cấp', 'Tổng phải trả', 'Đã trả', 'Số dư cuối'];
        $rows = [];
        foreach ($suppliers as $s) {
            $rows[] = [
                'code' => $s['code'] ?? '',
                'name' => $s['name'] ?? '',
                'total' => round((float)$s['total_gross'], 2),
                'paid' => round((float)$s['total_paid'], 2),
                'balance' => round((float)$s['total_balance'], 2),
            ];
        }

        return [
            'report_type' => 'ap_ledger',
            'title' => 'Sổ chi tiết công nợ phải trả - Tổng hợp theo nhà cung cấp',
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

    private function fetchSupplierTransactions(string $supplierId, ?string $fromDate, ?string $toDate): array
    {
        $transactions = [];

        // Hóa đơn = phát sinh Có 331 (tăng công nợ)
        $params = [$supplierId];
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
                    0 as dr_amount, i.gross_amount as cr_amount
             FROM ap_invoices i
             WHERE i.supplier_id = ?{$dateWhere} AND i.status != 'prepayment'
             ORDER BY i.invoice_date ASC"
        );
        $invStmt->execute($params);
        foreach ($invStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $r['description'] = 'Hóa đơn: ' . ($r['description'] ?? '');
            $transactions[] = $r;
        }

        // Thanh toán = phát sinh Nợ 331 (giảm công nợ)
        $params = [$supplierId];
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
                    p.amount as dr_amount, 0 as cr_amount
             FROM ap_payments p
             JOIN ap_invoices i ON i.id = p.ap_invoice_id
             JOIN transactions t ON t.id = p.transaction_id
             WHERE i.supplier_id = ?{$dateWhere}
             ORDER BY p.created_at ASC"
        );
        $payStmt->execute($params);
        foreach ($payStmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $r['description'] = 'Thanh toán: ' . ($r['description'] ?? '');
            $transactions[] = $r;
        }

        usort($transactions, function ($a, $b) {
            return strcmp($a['date'] ?? '', $b['date'] ?? '');
        });

        return $transactions;
    }

    private function getSupplier(string $supplierId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
            $stmt->execute([$supplierId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
