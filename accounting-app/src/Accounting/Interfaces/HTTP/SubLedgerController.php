<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\SubLedgerService;
use Accounting\Domain\Service\ReportExportService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

// Controller cho Sổ Chi Tiết (Subsidiary Ledger)
//
// Nghiệp vụ: Cung cấp API endpoints cho tất cả báo cáo sổ chi tiết:
//   - GET /api/reports/sub-ledger?type=general_ledger&account_code=1111
//   - POST /api/reports/sub-ledger/export (CSV hoặc HTML)
//   - GET /so-chi-tiet (View trang chính)
//
// Controller chỉ validate input và format response — không chứa business logic.
// Mọi nghiệp vụ báo cáo được xử lý bởi SubLedgerService và các Report implementations.
//
class SubLedgerController
{
    private SubLedgerService $subLedgerService;
    private ReportExportService $exportService;

    public function __construct(SubLedgerService $subLedgerService, ReportExportService $exportService)
    {
        $this->subLedgerService = $subLedgerService;
        $this->exportService = $exportService;
    }

    // GET /api/reports/sub-ledger
    // Query params: type (required), account_code, from_date, to_date, customer_id, supplier_id, item_id
    //
    public function getReport(): void
    {
        Auth::requirePermission('report', 'read');

        $reportType = $_GET['type'] ?? '';
        if (!$reportType) {
            JsonResponse::error('Vui lòng chọn loại báo cáo sổ chi tiết.', 400);
            return;
        }

        try {
            $params = $this->buildParamsFromGet();
            $data = $this->subLedgerService->getReport($reportType, $params);
            JsonResponse::ok(['data' => $data]);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            JsonResponse::error('Có lỗi xảy ra khi tải báo cáo: ' . $e->getMessage(), 500);
        }
    }

    // POST /api/reports/sub-ledger/export
    // Body JSON: { type: string, format: 'csv'|'html', params: {...} }
    //
    public function exportReport(): void
    {
        Auth::requirePermission('report', 'export');

        $input = json_decode(file_get_contents('php://input'), true);
        $reportType = $input['type'] ?? '';
        $format = $input['format'] ?? 'csv';
        $params = $input['params'] ?? [];

        if (!$reportType) {
            JsonResponse::error('Vui lòng chọn loại báo cáo sổ chi tiết.', 400);
            return;
        }

        try {
            if ($format === 'html') {
                $result = $this->subLedgerService->exportHtml($reportType, $params);
            } else {
                $result = $this->subLedgerService->exportCsv($reportType, $params);
            }

            header('Content-Type: ' . $result['mime']);
            header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
            echo $result['content'];
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            JsonResponse::error('Có lỗi xảy ra khi xuất báo cáo: ' . $e->getMessage(), 500);
        }
    }

    // GET /so-chi-tiet — Hiển thị view sổ chi tiết
    //
    public function viewIndex(): void
    {
        Auth::requirePermission('report', 'read');

        $reports = $this->subLedgerService->getSupportedReports();
        $accounts = $this->subLedgerService->getAccounts();

        $title = 'Sổ chi tiết';
        $activeMenu = 'so_chi_tiet';
        require __DIR__ . '/../../../../public/views/sub-ledger.php';
    }

    // GET /api/reports/sub-ledger/parameters?type=general_ledger
    //
    public function getParameters(): void
    {
        Auth::requirePermission('report', 'read');

        $reportType = $_GET['type'] ?? '';
        if (!$reportType) {
            JsonResponse::error('Vui lòng chọn loại báo cáo.', 400);
            return;
        }

        try {
            $params = $this->subLedgerService->getReportParameters($reportType);
            JsonResponse::ok(['data' => ['type' => $reportType, 'parameters' => $params]]);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    // GET /api/reports/sub-ledger/supported
    //
    public function getSupportedReports(): void
    {
        Auth::requirePermission('report', 'read');
        $reports = $this->subLedgerService->getSupportedReports();
        JsonResponse::ok(['data' => $reports]);
    }

    // Xây dựng params từ $_GET
    private function buildParamsFromGet(): array
    {
        $params = [];
        foreach (['account_code', 'from_date', 'to_date', 'customer_id', 'supplier_id', 'item_id'] as $key) {
            if (!empty($_GET[$key])) {
                $params[$key] = $_GET[$key];
            }
        }
        return $params;
    }
}
