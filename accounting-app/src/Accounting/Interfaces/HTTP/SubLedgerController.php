<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\SubLedgerService;
use Accounting\Domain\Service\ReportExportService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Sổ Chi Tiết (Subsidiary Ledger)
 *
 * Mục đích nghiệp vụ:
 *   - Báo cáo sổ chi tiết cho tất cả các loại (sổ cái, sổ quỹ, sổ kho...)
 *   - Xuất CSV/HTML
 *   - Hỗ trợ lọc theo tài khoản, khách hàng, NCC, hàng hóa
 *
 * API endpoints:
 *   GET  /api/reports/sub-ledger — Báo cáo (type=general_ledger, account_code=...)
 *   POST /api/reports/sub-ledger/export — Xuất
 *   GET  /api/reports/sub-ledger/parameters — Tham số
 *   GET  /api/reports/sub-ledger/supported — Danh sách báo cáo
 *   GET  /so-chi-tiet — View HTML
 *
 * Tích hợp:
 *   - SubLedgerService xử lý mọi loại báo cáo
 *   - ReportExportService format output
 */
class SubLedgerController
{
    private SubLedgerService $subLedgerService;
    private ReportExportService $exportService;

    public function __construct(SubLedgerService $subLedgerService, ReportExportService $exportService)
    {
        $this->subLedgerService = $subLedgerService;
        $this->exportService = $exportService;
    }

    /**
     * Lấy báo cáo sổ chi tiết
     *
     * @return void
     */
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

    /**
     * Xuất báo cáo CSV/HTML
     *
     * @return void
     */
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

    /**
     * View HTML sổ chi tiết
     *
     * @return void
     */
    public function viewIndex(): void
    {
        Auth::requirePermission('report', 'read');

        $reports = $this->subLedgerService->getSupportedReports();
        $accounts = $this->subLedgerService->getAccounts();

        $title = 'Sổ chi tiết';
        $activeMenu = 'so_chi_tiet';
        require __DIR__ . '/../../../../public/views/sub-ledger.php';
    }

    /**
     * Tham số cho một loại báo cáo
     *
     * @return void
     */
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

    /**
     * Danh sách báo cáo được hỗ trợ
     *
     * @return void
     */
    public function getSupportedReports(): void
    {
        Auth::requirePermission('report', 'read');
        $reports = $this->subLedgerService->getSupportedReports();
        JsonResponse::ok(['data' => $reports]);
    }

    /**
     * Xây dựng params từ $_GET
     *
     * @return array
     */
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
