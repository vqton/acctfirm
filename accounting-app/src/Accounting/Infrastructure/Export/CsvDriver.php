<?php
namespace Accounting\Infrastructure\Export;

use Accounting\Domain\Contract\ExportDriverInterface;
use Accounting\Domain\Model\ExportResult;

// Driver xuất CSV — dùng fputcsv để đảm bảo escaping đúng chuẩn
// Thêm UTF-8 BOM để Excel nhận diện tiếng Việt
// Hỗ trợ delimiter tùy chọn (mặc định dấu phẩy)
class CsvDriver implements ExportDriverInterface
{
    public function supports(string $format): bool
    {
        return in_array($format, ['csv', 'CSV'], true);
    }

    public function export(string $title, array $headers, array $rows, array $options = []): ExportResult
    {
        $delimiter = $options['delimiter'] ?? ',';
        $useBom = $options['bom'] ?? true;

        // UTF-8 BOM để Excel nhận diện tiếng Việt
        $content = '';
        if ($useBom) {
            $content .= "\xEF\xBB\xBF";
        }

        // Mở bộ nhớ đệm để dùng fputcsv
        $fh = fopen('php://temp', 'r+');

        // Ghi header
        fputcsv($fh, $headers, $delimiter);

        // Ghi từng dòng dữ liệu
        foreach ($rows as $row) {
            $converted = [];
            foreach ($row as $cell) {
                $converted[] = (string)$cell;
            }
            fputcsv($fh, $converted, $delimiter);
        }

        rewind($fh);
        $content .= stream_get_contents($fh);
        fclose($fh);

        $filename = $options['filename'] ?? $this->sanitizeFilename($title) . '.csv';

        return new ExportResult(
            content: $content,
            mimeType: 'text/csv; charset=utf-8',
            filename: $filename,
            size: strlen($content)
        );
    }

    private function sanitizeFilename(string $title): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_\-\p{L}]/u', '_', $title);
        $clean = preg_replace('/_+/', '_', $clean);
        return trim($clean, '_') ?: 'export';
    }
}
