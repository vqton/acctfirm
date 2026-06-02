<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\ExportDriverInterface;
use Accounting\Domain\Model\ExportResult;

// Dịch vụ xuất file thống nhất — dùng strategy pattern với các driver
// Hỗ trợ CSV, XLS (HTML table), PDF (pure PHP)
// Cho phép đăng ký driver mới mà không sửa code ExportService
//
// Ngoài ra giữ backward compatibility với ReportExportService cũ
// (exportCsv, exportHtml) qua legacyExport property
class ExportService
{
    /** @var array<string, ExportDriverInterface> */
    private array $drivers = [];

    public function __construct(
        private ReportExportService $legacyExport
    ) {}

    // Đăng ký driver cho một định dạng — format là key (csv, xls, pdf)
    // Cho phép ghi đè driver nếu đã tồn tại
    public function registerDriver(string $format, ExportDriverInterface $driver): void
    {
        $this->drivers[$format] = $driver;
    }

    // Xuất dữ liệu với driver tương ứng — tự động tìm driver theo format
    // Throw \InvalidArgumentException nếu format không được hỗ trợ
    public function export(string $format, string $title, array $headers, array $rows, array $options = []): ExportResult
    {
        foreach ($this->drivers as $key => $driver) {
            if ($driver->supports($format)) {
                return $driver->export($title, $headers, $rows, $options);
            }
        }

        throw new \InvalidArgumentException(
            "Định dạng xuất không được hỗ trợ: {$format}. "
            . "Các định dạng hiện có: " . implode(', ', array_keys($this->drivers))
        );
    }

    // Lấy danh sách định dạng được hỗ trợ
    public function getSupportedFormats(): array
    {
        return array_keys($this->drivers);
    }

    // Backward compatibility: delegate sang ReportExportService
    public function getLegacyExport(): ReportExportService
    {
        return $this->legacyExport;
    }
}
