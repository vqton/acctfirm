<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\SubLedgerReportInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Infrastructure\SubLedger\GeneralLedgerReport;
use Accounting\Infrastructure\SubLedger\CashBookReport;
use Accounting\Infrastructure\SubLedger\BankBookReport;
use Accounting\Infrastructure\SubLedger\InventoryLedgerReport;
use Accounting\Infrastructure\SubLedger\ArLedgerReport;
use Accounting\Infrastructure\SubLedger\ApLedgerReport;

// Dịch vụ Sổ Chi Tiết (Subsidiary Ledger): Factory + orchestrator cho tất cả báo cáo sổ chi tiết
//
// Nghiệp vụ: SubLedgerService là đầu vào duy nhất cho tất cả báo cáo sổ chi tiết.
// Controller không cần biết implementation cụ thể của từng loại sổ.
// Dựa vào reportType để dispatch đến implementation tương ứng.
//
// Các loại báo cáo hỗ trợ:
//   - general_ledger: Sổ cái (S05-DN) — qua GlService
//   - cash_book: Sổ quỹ tiền mặt (S03a-DN)
//   - bank_book: Sổ tiền gửi NH (S03b-DN)
//   - inventory_ledger: Sổ kho (S12-DN)
//   - ar_ledger: Sổ chi tiết công nợ phải thu (S13-DN)
//   - ap_ledger: Sổ chi tiết công nợ phải trả (S13-DN)
//
// Running Balance Utility: Cung cấp phương thức tính running balance dùng chung
// cho tất cả report — asset/expense (debit-normal) và liability/equity/revenue (credit-normal).
//
class SubLedgerService
{
    private \PDO $pdo;
    private AccountRepositoryInterface $accountRepo;
    private GlService $glService;
    private PeriodService $periodService;
    private ReportExportService $exportService;

    /** @var array<string, SubLedgerReportInterface> */
    private array $reports = [];

    public function __construct(
        \PDO $pdo,
        AccountRepositoryInterface $accountRepo,
        GlService $glService,
        PeriodService $periodService,
        ReportExportService $exportService
    ) {
        $this->pdo = $pdo;
        $this->accountRepo = $accountRepo;
        $this->glService = $glService;
        $this->periodService = $periodService;
        $this->exportService = $exportService;
    }

    // Lấy báo cáo theo loại — dispatch đến implementation tương ứng
    //
    // Input:
    //   reportType: general_ledger | cash_book | bank_book | inventory_ledger | ar_ledger | ap_ledger
    //   params: Mảng tham số phụ thuộc vào từng loại báo cáo
    //
    // Output: Mảng chuẩn hóa theo SubLedgerReportInterface::getData()
    //
    // RỦI RO: Nếu reportType không hợp lệ → throw InvalidArgumentException
    //   → View hiển thị lỗi "Loại báo cáo không hợp lệ" thay vì response undefined
    //
    public function getReport(string $reportType, array $params): array
    {
        $report = $this->resolveReport($reportType);
        return $report->getData($params);
    }

    // Lấy danh sách tất cả loại báo cáo hỗ trợ
    // Dùng cho view filter dropdown
    //
    public function getSupportedReports(): array
    {
        return [
            [
                'type' => 'general_ledger',
                'label' => 'Sổ cái (S05-DN)',
                'description' => 'Chi tiết phát sinh theo tài khoản',
                'icon' => 'bi-journal-text',
            ],
            [
                'type' => 'cash_book',
                'label' => 'Sổ quỹ tiền mặt (S03a-DN)',
                'description' => 'Thu/chi tiền mặt',
                'icon' => 'bi-cash',
            ],
            [
                'type' => 'bank_book',
                'label' => 'Sổ tiền gửi NH (S03b-DN)',
                'description' => 'Nhận/chi qua ngân hàng',
                'icon' => 'bi-bank',
            ],
            [
                'type' => 'inventory_ledger',
                'label' => 'Sổ kho (S12-DN)',
                'description' => 'Nhập/xuất/tồn theo mặt hàng',
                'icon' => 'bi-box',
            ],
            [
                'type' => 'ar_ledger',
                'label' => 'Sổ chi tiết phải thu (S13-DN)',
                'description' => 'Công nợ phải thu theo khách hàng',
                'icon' => 'bi-person-up',
            ],
            [
                'type' => 'ap_ledger',
                'label' => 'Sổ chi tiết phải trả (S13-DN)',
                'description' => 'Công nợ phải trả theo nhà cung cấp',
                'icon' => 'bi-person-down',
            ],
        ];
    }

    // Lấy tham số cho một loại báo cáo — dùng cho view dynamic filter
    //
    public function getReportParameters(string $reportType): array
    {
        $report = $this->resolveReport($reportType);
        return $report->getParameters();
    }

    // Xuất báo cáo ra CSV
    //
    public function exportCsv(string $reportType, array $params, string $filename = ''): array
    {
        $data = $this->getReport($reportType, $params);
        if (!$filename) {
            $filename = $data['report_type'] . '_' . date('Ymd') . '.csv';
        }
        return $this->exportService->exportCsv($data['headers'], $data['rows'], $filename);
    }

    // Xuất báo cáo ra HTML (in/PDF)
    //
    public function exportHtml(string $reportType, array $params): array
    {
        $data = $this->getReport($reportType, $params);

        $summary = [
            'Số dư đầu kỳ' => number_format($data['opening_balance'], 0, ',', '.'),
            'Số dư cuối kỳ' => number_format($data['closing_balance'], 0, ',', '.'),
        ];
        if (isset($data['totals'])) {
            foreach ($data['totals'] as $key => $val) {
                $label = match ($key) {
                    'total_debit' => 'Tổng phát sinh Nợ',
                    'total_credit' => 'Tổng phát sinh Có',
                    'total_receipt' => 'Tổng thu',
                    'total_payment' => 'Tổng chi',
                    default => $key,
                };
                $summary[$label] = number_format((float)$val, 0, ',', '.');
            }
        }

        return $this->exportService->exportHtml($data['title'], $data['headers'], $data['rows'], $summary);
    }

    // Phương thức tiện ích: Tính running balance cho một tài khoản
    //
    // Input:
    //   accountType: asset | expense | liability | equity | revenue
    //   openingBalance: Số dư đầu kỳ
    //   items: Mảng các mục với 'debit' và 'credit'
    //
    // Output: Mảng items với 'running_balance' được tính
    //
    // Công thức:
    //   Asset/Expense: Số dư = Đầu kỳ + Dr - Cr
    //   Liability/Equity/Revenue: Số dư = Đầu kỳ + Cr - Dr
    //
    // Sử dụng: Các report implementation có thể dùng method này thay vì tự tính
    //
    public function calculateRunningBalances(string $accountType, float $openingBalance, array $items): array
    {
        $isDebitNormal = in_array($accountType, ['asset', 'expense']);
        $running = $openingBalance;

        foreach ($items as &$item) {
            $dr = (float)($item['debit'] ?? 0);
            $cr = (float)($item['credit'] ?? 0);
            if ($isDebitNormal) {
                $running += $dr - $cr;
            } else {
                $running += $cr - $dr;
            }
            $item['running_balance'] = round($running, 2);
        }

        return $items;
    }

    // Lấy danh sách tài khoản cho filter dropdown
    // Chỉ lấy tài khoản chi tiết (không phải control account)
    //
    public function getAccounts(): array
    {
        return $this->glService->getAccounts();
    }

    private function resolveReport(string $reportType): SubLedgerReportInterface
    {
        if (!isset($this->reports[$reportType])) {
            $this->reports[$reportType] = $this->createReport($reportType);
        }
        return $this->reports[$reportType];
    }

    private function createReport(string $reportType): SubLedgerReportInterface
    {
        return match ($reportType) {
            'general_ledger' => new GeneralLedgerReport($this->glService, $this->accountRepo, $this->periodService),
            'cash_book' => new CashBookReport($this->pdo, $this->accountRepo),
            'bank_book' => new BankBookReport($this->pdo, $this->accountRepo),
            'inventory_ledger' => new InventoryLedgerReport($this->pdo, $this->accountRepo),
            'ar_ledger' => new ArLedgerReport($this->pdo, $this->accountRepo),
            'ap_ledger' => new ApLedgerReport($this->pdo, $this->accountRepo),
            default => throw new \InvalidArgumentException("Loại báo cáo không hợp lệ: {$reportType}. Các loại hỗ trợ: general_ledger, cash_book, bank_book, inventory_ledger, ar_ledger, ap_ledger."),
        };
    }
}
