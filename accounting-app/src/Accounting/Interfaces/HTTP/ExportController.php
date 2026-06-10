<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\ExportService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Xuất file thống nhất (CSV/XLS/PDF Export)
 *
 * Mục đích nghiệp vụ:
 *   - Xuất dữ liệu từ các báo cáo ra file
 *   - Hỗ trợ CSV, XLS (HTML table), PDF
 *   - Endpoint duy nhất POST /api/export
 *
 * API endpoints:
 *   POST /api/export — Xuất file
 *
 * Yêu cầu quyền:
 *   - report.export
 *
 * Tích hợp:
 *   - ExportService xử lý render file
 *   - Gọi từ ReportExportController, SubLedgerController
 */
class ExportController
{
    public function __construct(
        private ExportService $exportService
    ) {}

    /**
     * Xuất file CSV/XLS/PDF
     *
     * @return void
     */
    public function export(): void
    {
        Auth::requirePermission('report', 'export');

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            JsonResponse::error('Dữ liệu JSON không hợp lệ', 400);
            return;
        }

        $format = $input['format'] ?? '';
        $title = $input['title'] ?? 'Báo cáo';
        $headers = $input['headers'] ?? [];
        $rows = $input['rows'] ?? [];
        $options = $input['options'] ?? [];

        if (!$format) {
            JsonResponse::error('Thiếu định dạng xuất (format: csv/xls/pdf)', 400);
            return;
        }

        if (empty($headers) || empty($rows)) {
            JsonResponse::error('Thiếu dữ liệu xuất (headers và rows là bắt buộc)', 400);
            return;
        }

        try {
            $result = $this->exportService->export($format, $title, $headers, $rows, $options);

            header('Content-Type: ' . $result->getMimeType());
            header('Content-Disposition: attachment; filename="' . $result->getFilename() . '"');
            header('Content-Length: ' . $result->getSize());
            header('Pragma: no-cache');
            header('Expires: 0');

            echo $result->getContent();
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            JsonResponse::error('Lỗi xuất file: ' . $e->getMessage(), 500);
        }
    }
}
