<?php
namespace Accounting\Domain\Service;

// Dịch vụ xuất báo cáo — hỗ trợ xuất CSV (Excel) và HTML (in/PDF)
//
// Hạn chế: Không sử dụng thư viện PDF bên ngoài (no Composer).
// PDF được xử lý qua browser print (window.print()) ở frontend.
// CSV có thể mở bằng Excel với encoding UTF-8 BOM.
//
class ReportExportService
{
    // Xuất dữ liệu dạng CSV (mở được bằng Excel)
    // Input: headers (mảng tên cột), rows (mảng các mảng dữ liệu), filename
    // Output: trả về array để controller set header + echo
    public function exportCsv(array $headers, array $rows, string $filename = 'export.csv'): array
    {
        $lines = [];
        // UTF-8 BOM để Excel nhận diện tiếng Việt
        $bom = "\xEF\xBB\xBF";
        $lines[] = $this->csvLine($headers);

        foreach ($rows as $row) {
            $lines[] = $this->csvLine($row);
        }

        return [
            'content' => $bom . implode("\r\n", $lines),
            'filename' => $filename,
            'mime' => 'text/csv; charset=utf-8',
        ];
    }

    // Xuất dữ liệu dạng HTML table (dùng cho in ấn / PDF browser)
    public function exportHtml(string $title, array $headers, array $rows, array $summary = []): array
    {
        $html = '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">';
        $html .= '<title>' . htmlspecialchars($title) . '</title>';
        $html .= '<style>
            body { font-family: "Times New Roman", serif; font-size: 12pt; margin: 20px; }
            h2 { text-align: center; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
            th, td { border: 1px solid #333; padding: 4px 8px; text-align: left; }
            th { background: #eee; font-weight: bold; }
            .text-end { text-align: right; }
            .font-monospace { font-family: monospace; }
            .summary { margin-top: 10px; font-weight: bold; }
            @media print { .no-print { display: none; } }
        </style></head><body>';
        $html .= '<h2>' . htmlspecialchars($title) . '</h2>';
        $html .= '<table><thead><tr>';
        foreach ($headers as $h) {
            $html .= '<th>' . htmlspecialchars($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        foreach ($summary as $label => $value) {
            $html .= '<div class="summary">' . htmlspecialchars($label) . ': ' . htmlspecialchars((string)$value) . '</div>';
        }
        $html .= '<div class="no-print" style="margin-top:20px;"><button onclick="window.print()">In / Xuất PDF</button></div>';
        $html .= '</body></html>';

        return [
            'content' => $html,
            'filename' => $title . '.html',
            'mime' => 'text/html; charset=utf-8',
        ];
    }

    private function csvLine(array $fields): string
    {
        $parts = [];
        foreach ($fields as $f) {
            $str = (string)$f;
            if (str_contains($str, ',') || str_contains($str, '"') || str_contains($str, "\n")) {
                $str = '"' . str_replace('"', '""', $str) . '"';
            }
            $parts[] = $str;
        }
        return implode(',', $parts);
    }
}
