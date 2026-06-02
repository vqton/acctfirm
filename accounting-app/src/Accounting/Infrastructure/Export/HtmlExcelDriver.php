<?php
namespace Accounting\Infrastructure\Export;

use Accounting\Domain\Contract\ExportDriverInterface;
use Accounting\Domain\Model\ExportResult;

// Driver xuất Excel dạng HTML table — Excel mở được file .xls chứa HTML table
// Không cần thư viện — render table với mso styles cho Excel hiển thị đúng
// Hỗ trợ: header lặp lại khi in, số định dạng tiền tệ, màu sắc cơ bản, signature block
class HtmlExcelDriver implements ExportDriverInterface
{
    public function supports(string $format): bool
    {
        return in_array($format, ['xls', 'xlsx', 'excel', 'XLS', 'XLSX'], true);
    }

    public function export(string $title, array $headers, array $rows, array $options = []): ExportResult
    {
        $orientation = $options['orientation'] ?? 'portrait';
        $pageSize = $options['page_size'] ?? 'A4';
        $showSignature = $options['signature'] ?? false;
        $footerText = $options['footer'] ?? '';
        $summary = $options['summary'] ?? [];

        // Xác định hướng giấy cho @page
        $pageOrientation = ($orientation === 'landscape') ? 'landscape' : 'portrait';

        $html = '<!DOCTYPE html>';
        $html .= '<html xmlns:o="urn:schemas-microsoft-com:office:office" ';
        $html .= 'xmlns:x="urn:schemas-microsoft-com:office:excel" ';
        $html .= 'xmlns="http://www.w3.org/TR/REC-html40">';
        $html .= '<head><meta charset="UTF-8">';
        $html .= '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets>';
        $html .= '<x:ExcelWorksheet><x:Name>' . htmlspecialchars($title) . '</x:Name>';
        $html .= '<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>';
        $html .= '</x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
        $html .= '<style>';
        $html .= '@page { size: ' . $pageSize . ' ' . $pageOrientation . '; margin: 1.5cm; }';
        $html .= 'body { font-family: "Times New Roman", serif; font-size: 11pt; margin: 20px; }';
        $html .= 'h2 { text-align: center; margin-bottom: 5px; font-size: 14pt; }';
        $html .= '.subtitle { text-align: center; margin-bottom: 15px; font-style: italic; }';
        $html .= 'table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }';
        $html .= 'th, td { border: 1px solid #222; padding: 4px 6px; text-align: left; ';
        $html .= 'vertical-align: top; font-size: 10pt; }';
        $html .= 'th { background: #e8e8e8; font-weight: bold; text-align: center; }';
        $html .= '.text-end { text-align: right; }';
        $html .= '.text-center { text-align: center; }';
        $html .= '.font-monospace { font-family: "Courier New", monospace; }';
        $html .= '.summary-table { width: auto; margin-left: auto; margin-right: 0; }';
        $html .= '.summary-table td { border: none; padding: 2px 8px; }';
        $html .= '.signature { margin-top: 40px; width: 100%; }';
        $html .= '.signature td { border: none; text-align: center; padding: 10px 20px; }';
        $html .= '.footer { text-align: center; font-size: 9pt; color: #666; ';
        $html .= 'margin-top: 20px; border-top: 1px solid #ccc; padding-top: 5px; }';
        $html .= 'tr { mso-height-source: auto; }';
        $html .= 'col { mso-width-source: auto; }';
        $html .= '@media print { .no-print { display: none; } }';
        $html .= '</style></head><body>';

        // Tiêu đề
        $html .= '<h2>' . htmlspecialchars($title) . '</h2>';
        if (!empty($options['subtitle'])) {
            $html .= '<div class="subtitle">' . htmlspecialchars($options['subtitle']) . '</div>';
        }

        // Bảng dữ liệu
        $html .= '<table>';
        $html .= '<thead><tr>';
        foreach ($headers as $h) {
            $html .= '<th>' . htmlspecialchars((string)$h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $class = '';
                $val = (string)$cell;
                // Phát hiện số — canh phải nếu là số
                if (is_numeric(str_replace([',', '.'], '', $val))) {
                    $class = ' class="text-end"';
                }
                $html .= '<td' . $class . '>' . htmlspecialchars($val) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        // Tổng hợp
        if (!empty($summary)) {
            $html .= '<table class="summary-table">';
            foreach ($summary as $label => $value) {
                $html .= '<tr><td><strong>' . htmlspecialchars((string)$label) . ':</strong></td>';
                $html .= '<td class="text-end">' . htmlspecialchars((string)$value) . '</td></tr>';
            }
            $html .= '</table>';
        }

        // Chữ ký — mẫu Việt Nam
        if ($showSignature) {
            $html .= '<table class="signature">';
            $html .= '<tr>';
            $html .= '<td><strong>Người lập biểu</strong><br><em>(Ký, họ tên)</em></td>';
            $html .= '<td><strong>Kế toán trưởng</strong><br><em>(Ký, họ tên)</em></td>';
            $html .= '<td><strong>Giám đốc</strong><br><em>(Ký, họ tên)</em></td>';
            $html .= '</tr>';
            $html .= '</table>';
        }

        // Footer
        if ($footerText) {
            $html .= '<div class="footer">' . htmlspecialchars($footerText) . '</div>';
        }

        $html .= '<div class="no-print" style="margin-top:20px;">';
        $html .= '<button onclick="window.print()">In / Xuất PDF</button></div>';
        $html .= '</body></html>';

        $filename = $options['filename'] ?? $this->sanitizeFilename($title) . '.xls';

        return new ExportResult(
            content: $html,
            mimeType: 'application/vnd.ms-excel; charset=utf-8',
            filename: $filename,
            size: strlen($html)
        );
    }

    private function sanitizeFilename(string $title): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_\-\p{L}]/u', '_', $title);
        $clean = preg_replace('/_+/', '_', $clean);
        return trim($clean, '_') ?: 'export';
    }
}
