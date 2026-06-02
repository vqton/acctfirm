<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\ExportService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

// Controller xuất file thống nhất (PDF/Excel/CSV)
// Endpoint duy nhất POST /api/export — client gửi format, title, headers, rows, options
// Hỗ trợ các định dạng: csv, xls (HTML table), pdf (pure PHP)
// Yêu cầu quyền report.export
class ExportController
{
    public function __construct(
        private ExportService $exportService
    ) {}

    // POST /api/export
    // Body JSON: { format, title, headers, rows, options }
    //   format: "csv" | "xls" | "pdf"
    //   title: string — tiêu đề báo cáo
    //   headers: string[] — tên các cột
    //   rows: array[] — mảng các dòng dữ liệu
    //   options: { orientation?, signature?, footer?, filename?, subtitle?, summary? }
    // Response: file download với Content-Type và Content-Disposition phù hợp
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

            // Set header cho file download
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
