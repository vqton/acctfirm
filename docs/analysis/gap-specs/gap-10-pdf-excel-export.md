# Gap 10: PDF/Excel Export — Parity Specification

## 1. Business Context

**Vấn đề:** Bookwise hiện chỉ trả JSON — kế toán không thể in chứng từ, xuất báo cáo Excel để gửi cơ quan thuế hoặc phục vụ kiểm toán.

**Tại sao cần export:**
- Mọi phần mềm kế toán đối thủ đều có in ấn và xuất Excel — đây là basic feature
- Kiểm toán viên yêu cầu báo cáo có chữ ký, đóng dấu bản cứng
- Nộp tờ khai thuế (GTGT, TNDN, TNCN) yêu cầu định dạng PDF/XML theo GDT
- Kế toán nội bộ cần Excel để phân tích, soát xét, pivot
- Gửi báo cáo tài chính cho ban giám đốc qua email cần PDF/Excel
- Đối chiếu và ký duyệt chứng từ cần bản in (phiếu thu, phiếu chi, hóa đơn)

**Ràng buộc kỹ thuật:** Không Composer, không thư viện bên ngoài (TCPDF, DomPDF, PhpSpreadsheet). Toàn bộ export phải dùng PHP built-in hoặc system tool.

---

## 2. Technology Decision

### 2.1 Export Format Matrix

| Format | Approach | Pros | Cons |
|---|---|---|---|
| **CSV** | `fputcsv()` + UTF-8 BOM (`\xEF\xBB\xBF`) | Native PHP, zero deps, 30 LOC | Không formatting, 1 sheet, không merge cell |
| **HTML Excel** | HTML table → `.xls` extension với XML namespace | Giữ được formatting, mở được trong Excel | Cảnh báo "File format mismatch" khi mở |
| **HTML Print** | HTML + `@media print` CSS → browser Print-to-PDF | Zero deps, đẹp, CSS đầy đủ | Phải dùng browser, không server-side |
| **PDF (wkhtmltopdf)** | `proc_open('wkhtmltopdf ...')` | Chất lượng cao, CSS styled, font Unicode | Yêu cầu cài đặt system tool |
| **PDF (pure PHP)** | Tự viết PDF primitives (header/table/footer) | Zero deps, portable | Chỉ đủ cho báo cáo đơn giản, không hỗ trợ đồ thị |

### 2.2 Recommended Phased Approach

```
Phase 1 (Week 1-2, quick win):
  └─ CSV (fputcsv + BOM)          → có ngay, export sổ cái, trial balance
  └─ HTML Excel (table → .xls)    → có ngay, export BC01/02/03
  └─ HTML Print (window.print())  → có ngay, in chứng từ, sổ sách

Phase 2 (Week 3-4):
  └─ PDF via wkhtmltopdf           → nếu detect có system tool
  └─ Pure PHP PDF (fallback)       → cho báo cáo đơn giản
  └─ Mass export (chọn nhiều kỳ, multi-file)

Phase 3 (Week 5-6):
  └─ PDF templates (chứng từ mẫu: phiếu thu/chi, hóa đơn)
  └─ Batch export (hàng loạt chứng từ)
  └─ Export scheduler (gửi mail tự động)
```

### 2.3 Existing Code Assessment

Bookwise **đã có** `ReportExportService` (`accounting-app/src/Accounting/Domain/Service/ReportExportService.php`) với:

- `exportCsv()` — fputcsv + UTF-8 BOM
- `exportHtml()` — HTML table với `<style>` inline + nút `window.print()`

**Đã có routes:**
- `GET /api/export/csv/ledger` — sổ cái CSV
- `GET /api/export/html/ledger` — sổ cái HTML (in/PDF)
- `GET /api/export/csv/trial-balance` — BCĐPS CSV
- `GET /api/export/csv/bc03` — BC03 CSV

**Đã có controllers:**
- `ReportExportController` với 4 methods: `exportCsvLedger`, `exportHtmlLedger`, `exportCsvTrialBalance`, `exportCsvBC03`

Đây là khởi đầu tốt. Gap 10 spec bổ sung architecture cho all reports, all formats, unified endpoint.

---

## 3. Export Architecture

### 3.1 Interface Design

```php
namespace Accounting\Domain\Contract\Export;

interface ExportDriverInterface
{
    public function export(array $data, array $options): ExportResult;
    public function format(): string; // 'csv', 'xls', 'pdf', 'html'
}

interface LayoutRendererInterface
{
    public function render(string $reportType, array $data, array $options): array;
    public function supports(string $reportType): bool;
}

class ExportResult
{
    public function __construct(
        public readonly string $content,      // Nội dung file binary
        public readonly string $mimeType,     // application/pdf, text/csv, ...
        public readonly string $filename,     // Tên file gợi ý
        public readonly int $size,            // Content-Length
    ) {}
}
```

### 3.2 ExportService — Unified Entry Point

```php
namespace Accounting\Domain\Service;

class ExportService
{
    /** @var ExportDriverInterface[] */
    private array $drivers = [];

    /** @var LayoutRendererInterface[] */
    private array $renderers = [];

    public function registerDriver(ExportDriverInterface $driver): void
    {
        $this->drivers[$driver->format()] = $driver;
    }

    public function registerLayout(LayoutRendererInterface $renderer): void
    {
        $this->renderers[] = $renderer;
    }

    // NGHIỆP VỤ: Xuất báo cáo — là entry point duy nhất cho mọi export
    // reportType: 'bc01', 'bc02', 'bc03', 'ledger', 'subsidiary', 'trial_balance', ...
    // format: 'csv', 'xls', 'pdf', 'html'
    // data: dữ liệu từ service (array)
    // options: 'orientation', 'page_size', 'show_signature', 'title', ...
    //
    // Flow:
    //   1. Tìm layout renderer phù hợp → render HTML từ data
    //   2. Tìm driver phù hợp → chuyển HTML/dữ liệu sang format đích
    //   3. Trả về ExportResult
    public function export(
        string $reportType,
        string $format,
        array $data,
        array $options = [],
    ): ExportResult {
        // Tìm layout renderer
        $renderer = $this->findRenderer($reportType);
        if (!$renderer) {
            throw new \InvalidArgumentException("Không tìm thấy layout cho báo cáo: $reportType");
        }

        // Render layout — output là array (CSV rows) hoặc string (HTML)
        $rendered = $renderer->render($reportType, $data, $options);

        // Tìm driver
        $driver = $this->drivers[$format] ?? null;
        if (!$driver) {
            throw new \InvalidArgumentException("Định dạng không được hỗ trợ: $format");
        }

        return $driver->export($rendered, $options);
    }

    public function getSupportedFormats(): array
    {
        return array_keys($this->drivers);
    }

    public function getSupportedReports(): array
    {
        $reports = [];
        foreach ($this->renderers as $r) {
            // Có thể inspect qua reflection hoặc convention
        }
        return $reports;
    }

    private function findRenderer(string $reportType): ?LayoutRendererInterface
    {
        foreach ($this->renderers as $r) {
            if ($r->supports($reportType)) return $r;
        }
        return null;
    }
}
```

### 3.3 Unified Export Endpoint

```
POST /api/export/{reportType}

Request body (JSON):
{
    "format": "pdf",
    "period_id": 12,
    "filters": {
        "account": "111",
        "from": "2026-01-01",
        "to": "2026-03-31",
        "customer_id": 42,
        "supplier_id": null
    },
    "options": {
        "orientation": "landscape",
        "page_size": "A4",
        "show_signature": true,
        "title": "Sổ quỹ tiền mặt tháng 3/2026"
    }
}

Response:
    Content-Type: application/pdf (hoặc text/csv, application/vnd.ms-excel)
    Content-Disposition: attachment; filename="so_quy_tien_mat_202603_20260331.pdf"
    Content-Length: 123456
    [binary content]
```

---

## 4. Driver Implementations

### 4.1 CsvDriver — Existing, Complete

```php
class CsvDriver implements ExportDriverInterface
{
    // Dùng fputcsv() với UTF-8 BOM
    // Input: ['headers' => [...], 'rows' => [[...], ...]]
    // Options: delimiter (mặc định ','), enclosure (mặc định '"')
    public function export(array $data, array $options): ExportResult
    {
        $delimiter = $options['delimiter'] ?? ',';
        $lines = [];
        $lines[] = "\xEF\xBB\xBF"; // UTF-8 BOM

        // Header row
        $lines[] = $this->csvLine($data['headers'] ?? [], $delimiter);

        // Data rows
        foreach (($data['rows'] ?? []) as $row) {
            $lines[] = $this->csvLine($row, $delimiter);
        }

        $content = implode("\r\n", $lines);
        $filename = $options['filename'] ?? 'export.csv';

        return new ExportResult(
            content: $content,
            mimeType: 'text/csv; charset=utf-8',
            filename: $filename,
            size: strlen($content),
        );
    }

    public function format(): string { return 'csv'; }

    private function csvLine(array $fields, string $delimiter): string
    {
        $parts = [];
        foreach ($fields as $f) {
            $str = (string)$f;
            if (str_contains($str, $delimiter) || str_contains($str, '"') || str_contains($str, "\n")) {
                $str = '"' . str_replace('"', '""', $str) . '"';
            }
            $parts[] = $str;
        }
        return implode($delimiter, $parts);
    }
}
```

### 4.2 HtmlExcelDriver — HTML Table → .xls

```php
class HtmlExcelDriver implements ExportDriverInterface
{
    // NGHIỆP VỤ: Excel mở HTML table natively — không cần thư viện
    // Khi mở file .xls, Excel hiện warning "The file format differs..."
    // nhưng vẫn hiển thị đúng dữ liệu và formatting.
    // Hạn chế: 1 sheet, không formula, không chart
    //
    // Format: XHTML với XML namespace cho Excel
    //   <html xmlns:o="urn:schemas-microsoft-com:office:office"
    //         xmlns:x="urn:schemas-microsoft-com:office:excel"
    //         xmlns="http://www.w3.org/TR/REC-html40">
    //   <head>
    //     <meta charset="UTF-8">
    //     <!--[if gte mso 9]><xml><x:ExcelWorkbook>...</x:ExcelWorkbook></xml><![endif]-->
    //     <style> ... </style>
    //   </head>
    //   <body><table>...</table></body>
    //   </html>
    //
    // Input: ['headers' => [...], 'rows' => [[...], ...], 'title' => '...']
    // Output: .xls file với application/vnd.ms-excel
    public function export(array $data, array $options): ExportResult
    {
        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" ';
        $html .= 'xmlns:x="urn:schemas-microsoft-com:office:excel" ';
        $html .= 'xmlns="http://www.w3.org/TR/REC-html40">';
        $html .= '<head><meta charset="UTF-8">';
        $html .= '<!--[if gte mso 9]><xml><x:ExcelWorkbook>';
        $html .= '<x:ExcelWorksheets><x:ExcelWorksheet>';
        $html .= '<x:Name>Sheet1</x:Name>';
        $html .= '<x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>';
        $html .= '</x:ExcelWorksheet></x:ExcelWorksheets>';
        $html .= '</x:ExcelWorkbook></xml><![endif]-->';
        $html .= '<style>';
        $html .= 'table{border-collapse:collapse;font-family:"Times New Roman",serif;font-size:11pt;}';
        $html .= 'th{background:#d9e1f2;border:1px solid #000;padding:4px 8px;text-align:center;font-weight:bold;}';
        $html .= 'td{border:1px solid #000;padding:3px 6px;mso-number-format:"\#\#\#0";}';
        $html .= '.text-end{text-align:right;}';
        $html .= '.text-center{text-align:center;}';
        $html .= '.date-cell{mso-number-format:"dd/mm/yyyy";}';
        $html .= '.amount-cell{mso-number-format:"\#\#\#0";text-align:right;}';
        $html .= '.header-title{font-size:14pt;font-weight:bold;text-align:center;border:none;}';
        $html .= '.signature-row td{border:none;text-align:center;padding-top:30px;}';
        $html .= '</style></head><body>';

        // Title
        $title = $data['title'] ?? $options['title'] ?? 'Báo cáo';
        $html .= '<table><tr><td class="header-title" colspan="' . count($data['headers'] ?? [1]) . '">';
        $html .= htmlspecialchars($title) . '</td></tr>';

        // Header row
        $html .= '<tr>';
        foreach (($data['headers'] ?? []) as $h) {
            $html .= '<th>' . htmlspecialchars((string)$h) . '</th>';
        }
        $html .= '</tr>';

        // Data rows
        foreach (($data['rows'] ?? []) as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $class = is_numeric($cell) && $cell !== '' ? ' class="amount-cell"' : '';
                $html .= '<td' . $class . '>' . htmlspecialchars((string)$cell) . '</td>';
            }
            $html .= '</tr>';
        }

        // Signature blocks
        if ($options['show_signature'] ?? true) {
            $html .= '<tr><td class="signature-row" colspan="' . count($data['headers'] ?? [1]) . '">';
            $html .= '<table style="width:100%;border:none;"><tr>';
            $html .= '<td style="border:none;text-align:center;width:33%;">Người lập<br>(Ký, họ tên)</td>';
            $html .= '<td style="border:none;text-align:center;width:33%;">Kế toán trưởng<br>(Ký, họ tên)</td>';
            $html .= '<td style="border:none;text-align:center;width:33%;">Giám đốc<br>(Ký, họ tên)</td>';
            $html .= '</tr></table></td></tr>';
        }

        $html .= '</table></body></html>';

        $filename = $options['filename'] ?? 'export.xls';

        return new ExportResult(
            content: $html,
            mimeType: 'application/vnd.ms-excel',
            filename: $filename,
            size: strlen($html),
        );
    }

    public function format(): string { return 'xls'; }
}
```

### 4.3 WkhtmltopdfDriver — System Tool

```php
class WkhtmltopdfDriver implements ExportDriverInterface
{
    private ?string $binaryPath;

    public function __construct(?string $binaryPath = null)
    {
        $this->binaryPath = $binaryPath ?? $this->findBinary();
    }

    // Kiểm tra wkhtmltopdf availability:
    //   - Tìm trong $PATH, /usr/local/bin, /usr/bin
    //   - Test run: `wkhtmltopdf --version`
    //   - Nếu không có → disable driver (không throw)
    public function isAvailable(): bool
    {
        return $this->binaryPath !== null;
    }

    public function export(array $data, array $options): ExportResult
    {
        if (!$this->binaryPath) {
            throw new \RuntimeException('wkhtmltopdf không khả dụng. Vui lòng cài đặt hoặc dùng HTML Print.');
        }

        // HTML content from layout renderer
        $html = $data['html'] ?? '';
        if (empty($html)) {
            throw new \InvalidArgumentException('Không có nội dung HTML để xuất PDF');
        }

        $orientation = $options['orientation'] ?? 'portrait';
        $pageSize = $options['page_size'] ?? 'A4';
        $marginTop = $options['margin_top'] ?? '15mm';
        $marginBottom = $options['margin_bottom'] ?? '15mm';
        $marginLeft = $options['margin_left'] ?? '20mm';
        $marginRight = $options['margin_right'] ?? '20mm';
        $filename = $options['filename'] ?? 'export.pdf';

        // Build command
        // --enable-local-file-access: cho phép CSS/image local
        // --no-stop-slow-scripts: đợi JS chạy xong
        // --encoding UTF-8: hỗ trợ tiếng Việt
        // - -: đọc HTML từ stdin, xuất PDF ra stdout
        $cmd = sprintf(
            '%s --page-size %s --orientation %s'
            . ' --margin-top %s --margin-bottom %s'
            . ' --margin-left %s --margin-right %s'
            . ' --encoding UTF-8 --enable-local-file-access --no-stop-slow-scripts'
            . ' - - 2>/dev/null',
            escapeshellcmd($this->binaryPath),
            escapeshellarg($pageSize),
            escapeshellarg($orientation),
            escapeshellarg($marginTop),
            escapeshellarg($marginBottom),
            escapeshellarg($marginLeft),
            escapeshellarg($marginRight),
        );

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout (PDF)
            2 => ['pipe', 'w'],  // stderr (error log)
        ];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('Không thể khởi tạo wkhtmltopdf process');
        }

        // Gửi HTML vào stdin
        fwrite($pipes[0], $html);
        fclose($pipes[0]);

        // Đọc PDF từ stdout
        $pdf = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        // Đọc lỗi từ stderr (log, không throw)
        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0 || $pdf === false || strlen($pdf) === 0) {
            throw new \RuntimeException(
                'wkhtmltopdf thất bại (exit code: ' . $exitCode . '): ' . ($errorOutput ?: 'No error output')
            );
        }

        return new ExportResult(
            content: $pdf,
            mimeType: 'application/pdf',
            filename: $filename,
            size: strlen($pdf),
        );
    }

    public function format(): string { return 'pdf'; }

    public function getBinaryVersion(): ?string
    {
        if (!$this->binaryPath) return null;
        $output = `{$this->binaryPath} --version 2>&1`;
        return $output ?: null;
    }

    private function findBinary(): ?string
    {
        $candidates = ['wkhtmltopdf', '/usr/local/bin/wkhtmltopdf', '/usr/bin/wkhtmltopdf'];
        foreach ($candidates as $bin) {
            $result = `which "$bin" 2>/dev/null || echo ""`;
            // which trả về path nếu tìm thấy, rỗng nếu không
        }
        // Fallback: check common paths
        $check = trim(`command -v wkhtmltopdf 2>/dev/null`);
        return $check ?: null;
    }
}
```

### 4.4 PurePhpPdfDriver — Zero-dependency Fallback

```php
class PurePhpPdfDriver implements ExportDriverInterface
{
    // Minimal PDF builder for tabular reports
    // Hỗ trợ: header, table, footer, page numbering
    // Không hỗ trợ: images, graphs, complex layouts
    //
    // PDF Structure:
    //   %PDF-1.4
    //   1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj
    //   2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj
    //   3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842]
    //                  /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj
    //   ... (content stream with PDF operators)
    //
    // Constraints:
    //   - Chỉ Unicode font DejaVu (built-in subset)
    //   - Tối đa 10 columns
    //   - Tối đa 200 dòng/trang
    //   - A4 only (portrait: 595×842 pt, landscape: 842×595 pt)
    //   - Font size 8-14pt
    //
    // RỦI RO: PDF spec phức tạp. Chỉ dùng cho báo cáo đơn giản
    // (trial balance, sổ cái ít dòng). Với báo cáo lớn, ưu tiên wkhtmltopdf.
    public function export(array $data, array $options): ExportResult
    {
        // Pure PHP PDF generation
        // Uses PDFlib-like primitives:
        //   $this->pdfAddPage()
        //   $this->pdfSetFont()
        //   $this->pdfCell()
        //   $this->pdfTable()
        //   $this->pdfFooter()
        //
        // Implementation detail dài ~300-400 LOC
        // Xem design doc riêng: docs/analysis/pure-php-pdf-design.md

        $pdf = $this->generatePdf($data, $options);
        $filename = $options['filename'] ?? 'export.pdf';

        return new ExportResult(
            content: $pdf,
            mimeType: 'application/pdf',
            filename: $filename,
            size: strlen($pdf),
        );
    }

    public function format(): string { return 'pdf'; }

    private function generatePdf(array $data, array $options): string
    {
        // STUB: Implementation chi tiết sẽ được phát triển sau
        throw new \RuntimeException('Pure PHP PDF driver chưa được implement. Dùng HTML Print hoặc wkhtmltopdf.');
    }
}
```

### 4.5 HtmlDriver — for Browser Print

```php
class HtmlDriver implements ExportDriverInterface
{
    // Trả về HTML đầy đủ (DOCTYPE + CSS + body) cho browser render
    // User dùng Ctrl+P → Print-to-PDF hoặc in trực tiếp
    // MIME type: text/html → mở trong tab mới
    public function export(array $data, array $options): ExportResult
    {
        $html = $data['html'] ?? '';
        $content = $this->wrapHtml($html, $options);
        $filename = $options['filename'] ?? 'export.html';

        return new ExportResult(
            content: $content,
            mimeType: 'text/html; charset=utf-8',
            filename: $filename,
            size: strlen($content),
        );
    }

    private function wrapHtml(string $body, array $options): string
    {
        $orientation = $options['orientation'] ?? 'portrait';
        $pageSize = $options['page_size'] ?? 'A4';

        return '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">'
            . '<title>' . htmlspecialchars($options['title'] ?? 'Báo cáo') . '</title>'
            . '<style>'
            . '@page { size: ' . $pageSize . ' ' . $orientation . '; '
            . 'margin: 15mm 20mm; }'
            . 'body { font-family: "Times New Roman", serif; font-size: 11pt; line-height: 1.3; }'
            . 'table { width: 100%; border-collapse: collapse; }'
            . 'th, td { border: 1px solid #000; padding: 4px 6px; }'
            . 'th { background: #eee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }'
            . '@media print { .no-print { display: none !important; } '
            . '.page-break { page-break-before: always; } }'
            . '.signature-block { margin-top: 40px; width: 100%; }'
            . '.signature-block td { border: none; text-align: center; width: 33%; }'
            . '</style></head><body>'
            . $body
            . '</body></html>';
    }

    public function format(): string { return 'html'; }
}
```

---

## 5. Layout Renderers

### 5.1 Standard Layout Renderer

```php
namespace Accounting\Infrastructure\Export\Layout;

class StandardLayoutRenderer implements LayoutRendererInterface
{
    // Layout chung cho mọi báo cáo — tuân thủ TT 99/2025:
    //   - Header: Tên công ty + địa chỉ + MST + điện thoại
    //   - Title: Tên báo cáo (16pt, bold, center)
    //   - Period: "Từ ngày ... đến ngày ..."
    //   - Unit: "Đơn vị tính: VNĐ" (right-aligned)
    //   - Body: HTML table
    //   - Footer: 3 signature blocks + date + page number

    private array $supported = [
        'bc01', 'bc02', 'bc03', 'bc09',
        'ledger', 'subsidiary', 'trial_balance',
        'cash_book', 'bank_book', 'journal',
        'ar_aging', 'ap_aging',
        'vat', 'cit', 'pit',
        'transactions', 'inventory_ledger',
    ];

    public function supports(string $reportType): bool
    {
        return in_array($reportType, $this->supported, true);
    }

    // Trả về: ['headers' => [...], 'rows' => [[...], ...], 'title' => '...']
    // cho CSV driver, hoặc ['html' => '...'] cho PDF/HTML driver
    public function render(string $reportType, array $data, array $options): array
    {
        // 1. Company info từ business_config (nếu có)
        $companyName = $data['company_name'] ?? 'CÔNG TY TNHH ABC';
        $companyAddress = $data['company_address'] ?? '';
        $taxCode = $data['tax_code'] ?? '';
        $phone = $data['phone'] ?? '';

        // 2. Report title
        $title = $options['title'] ?? $this->getDefaultTitle($reportType);
        $periodLabel = $options['period_label'] ?? '';

        // 3. Build HTML
        $html = '';

        // Company header
        $html .= '<div style="text-align:center;margin-bottom:20px;">';
        $html .= '<h2 style="text-transform:uppercase;font-weight:bold;font-size:16pt;margin:0 0 4px;">'
            . htmlspecialchars($companyName) . '</h2>';
        if ($companyAddress) {
            $html .= '<p style="margin:0;font-size:10pt;">Địa chỉ: ' . htmlspecialchars($companyAddress) . '</p>';
        }
        if ($taxCode) {
            $html .= '<p style="margin:0;font-size:10pt;">MST: ' . htmlspecialchars($taxCode)
                . ($phone ? ' | ĐT: ' . htmlspecialchars($phone) : '') . '</p>';
        }
        $html .= '</div>';

        // Report title
        $html .= '<h3 style="text-align:center;font-size:14pt;font-weight:bold;margin:16px 0 8px;">'
            . htmlspecialchars($title) . '</h3>';

        // Period
        if ($periodLabel) {
            $html .= '<p style="text-align:center;font-size:11pt;margin:0 0 16px;">'
                . htmlspecialchars($periodLabel) . '</p>';
        }

        // Unit
        $html .= '<p style="text-align:right;font-size:10pt;margin:0 0 12px;">Đơn vị tính: VNĐ</p>';

        // Table
        $html .= '<table>';
        $html .= '<thead><tr>';
        foreach (($data['headers'] ?? []) as $h) {
            $html .= '<th>' . htmlspecialchars((string)$h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach (($data['rows'] ?? []) as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $class = is_numeric($cell) ? ' style="text-align:right;font-family:monospace;"' : '';
                $html .= '<td' . $class . '>' . htmlspecialchars((string)$cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        // Summary row
        if (!empty($data['summary'])) {
            foreach ($data['summary'] as $label => $value) {
                $html .= '<p style="font-weight:bold;margin-top:8px;">'
                    . htmlspecialchars($label) . ': ' . htmlspecialchars((string)$value) . '</p>';
            }
        }

        // Date
        $today = date('d \t\h\á\n\g m \n\ă\m Y');
        $html .= '<p style="text-align:right;margin-top:24px;font-size:11pt;">..., ngày ' . $today . '</p>';

        // Signature blocks
        $html .= '<table class="signature-block"><tr>';
        $html .= '<td>Người lập biểu<br><br><br><br>(Ký, họ tên)</td>';
        $html .= '<td>Kế toán trưởng<br><br><br><br>(Ký, họ tên)</td>';
        $html .= '<td>Giám đốc<br><br><br><br>(Ký, họ tên)</td>';
        $html .= '</tr></table>';

        // Return both: rows for CSV, html for PDF/HTML
        return [
            'headers' => $data['headers'] ?? [],
            'rows' => $data['rows'] ?? [],
            'title' => $title,
            'summary' => $data['summary'] ?? [],
            'html' => $html,
        ];
    }

    private function getDefaultTitle(string $reportType): string
    {
        return match ($reportType) {
            'bc01' => 'BẢNG CÂN ĐỐI KẾ TOÁN',
            'bc02' => 'BÁO CÁO KẾT QUẢ HOẠT ĐỘNG KINH DOANH',
            'bc03' => 'BÁO CÁO LƯU CHUYỂN TIỀN TỆ',
            'bc09' => 'THUYẾT MINH BÁO CÁO TÀI CHÍNH',
            'ledger' => 'SỔ CÁI',
            'subsidiary' => 'SỔ CHI TIẾT',
            'trial_balance' => 'BẢNG CÂN ĐỐI SỐ PHÁT SINH',
            'cash_book' => 'SỔ QUỸ TIỀN MẶT',
            'bank_book' => 'SỔ TIỀN GỬI NGÂN HÀNG',
            'journal' => 'SỔ NHẬT KÝ CHUNG',
            'ar_aging' => 'BẢNG PHÂN TÍCH TUỔI NỢ PHẢI THU',
            'ap_aging' => 'BẢNG PHÂN TÍCH TUỔI NỢ PHẢI TRẢ',
            'vat' => 'TỜ KHAI THUẾ GIÁ TRỊ GIA TĂNG',
            'cit' => 'TỜ KHAI QUYẾT TOÁN THUẾ TNDN',
            'pit' => 'TỜ KHAI QUYẾT TOÁN THUẾ TNCN',
            'transactions' => 'DANH SÁCH GIAO DỊCH',
            'inventory_ledger' => 'SỔ CHI TIẾT HÀNG TỒN KHO',
            default => 'BÁO CÁO',
        };
    }
}
```

### 5.2 Report Data Providers

Mỗi report cần 1 service method trả về dữ liệu đã format sẵn cho export:

| Report Type | Service | Method |
|---|---|---|
| `bc01` | FsService | `generateBC01($period)` |
| `bc02` | FsService | `generateBC02($period)` |
| `bc03` | FsService | `generateBC03($period)` hoặc `generateBC03Direct($period)` |
| `ledger` | GlService | `getGeneralLedger($account, $from, $to)` |
| `subsidiary` | GlService | `getSubsidiaryLedger($account, $from, $to, $groupBy)` |
| `trial_balance` | TrialBalanceService | `getTrialBalance($period)` |
| `cash_book` | CashService | `getCashBook($from, $to)` |
| `journal` | JournalBookService | `getJournalBook($from, $to)` |
| `ar_aging` | ArService | `getAging($customerId?)` |
| `ap_aging` | ApService | `getAging($supplierId?)` |
| `vat` | VatService | `getDeclaration($period)` |
| `cit` | CitService | `getCalculation($year)` |

**Chuẩn hóa output:** Mỗi method trên cần 1 wrapper method cho export trả về cấu trúc:

```php
[
    'headers' => ['Cột 1', 'Cột 2', ...],  // Tên cột
    'rows' => [                           // Dữ liệu
        ['value1', 'value2', ...],
        ...
    ],
    'summary' => [                        // Dòng tổng cộng (optional)
        'Tổng cộng' => '100,000,000',
        'Số dư cuối kỳ' => '200,000,000',
    ],
    'meta' => [                           // Thông tin báo cáo
        'title' => 'Sổ cái TK 111',
        'period' => 'Từ 01/01/2026 đến 31/03/2026',
        'orientation' => 'landscape',
    ],
]
```

---

## 6. ExportController — Unified Endpoint

```php
class ExportController
{
    public function __construct(
        private ExportService $exportService,
        private AccountService $accountService,
        private ApService $apService,
        private ArService $arService,
        private CashService $cashService,
        private FsService $fsService,
        private GlService $glService,
        private InventoryService $inventoryService,
        private JournalBookService $journalBookService,
        private TrialBalanceService $trialBalanceService,
        private VatService $vatService,
        private CitService $citService,
    ) {}

    // NGHIỆP VỤ: Xuất báo cáo — unified endpoint cho tất cả report types
    // POST /api/export/{reportType}
    // Input: { format: 'pdf'|'xls'|'csv'|'html', period_id, filters, options }
    // Output: file download với Content-Type phù hợp
    //
    // Flow:
    //   1. Parse reportType → xác định service method
    //   2. Gọi service method → lấy dữ liệu
    //   3. Gọi ExportService → render layout + chuyển đổi format
    //   4. Trả về file
    //
    // Ràng buộc:
    //   - Export cần permission 'report', 'export'
    //   - Max 365 ngày cho filter date range
    //   - Max 10,000 rows cho CSV
    //   - Max 100 pages cho PDF
    public function export(string $reportType): void
    {
        Auth::requirePermission('report', 'export');

        $input = json_decode(file_get_contents('php://input'), true)
            ?? $_POST;

        $format = $input['format'] ?? 'pdf';
        $options = $input['options'] ?? [];
        $filters = $input['filters'] ?? [];

        // Validate format
        if (!in_array($format, $this->exportService->getSupportedFormats(), true)) {
            JsonResponse::error("Định dạng '$format' không được hỗ trợ", 400);
            return;
        }

        try {
            // 1. Fetch data từ service phù hợp
            $data = $this->fetchReportData($reportType, $filters, $options);

            // 2. Validate date range (max 365 days)
            if (isset($filters['from'], $filters['to'])) {
                $from = new \DateTime($filters['from']);
                $to = new \DateTime($filters['to']);
                $diff = $from->diff($to)->days;
                if ($diff > 365) {
                    JsonResponse::error('Khoảng thời gian xuất tối đa 365 ngày', 400);
                    return;
                }
            }

            // 3. Export
            $result = $this->exportService->export($reportType, $format, $data, $options);

            // 4. Set response headers
            header('Content-Type: ' . $result->mimeType);
            header('Content-Disposition: attachment; filename="' . $result->filename . '"');
            header('Content-Length: ' . $result->size);
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');

            echo $result->content;

        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 500);
        }
    }

    // Xác định service method và fetch dữ liệu theo reportType
    // Mỗi report type map đến 1 service method
    private function fetchReportData(string $reportType, array $filters, array &$options): array
    {
        $period = $filters['period_id'] ?? null;
        $options['filename'] ??= $reportType . '_' . date('Ymd') . '.' . ($options['format'] ?? 'pdf');

        return match ($reportType) {
            // Báo cáo tài chính
            'bc01' => $this->prepareFsData(
                $this->fsService->generateBC01($period), 'BC01', $period, $options
            ),
            'bc02' => $this->prepareFsData(
                $this->fsService->generateBC02($period), 'BC02', $period, $options
            ),
            'bc03' => $this->prepareFsData(
                $this->fsService->generateBC03($period), 'BC03', $period, $options
            ),
            'bc03_direct' => $this->prepareFsData(
                $this->fsService->generateBC03Direct($period), 'BC03D', $period, $options
            ),

            // Sổ kế toán
            'ledger' => $this->prepareLedgerData(
                $this->glService->getGeneralLedger(
                    $filters['account'] ?? '111',
                    $filters['from'] ?? null,
                    $filters['to'] ?? null
                ),
                $options
            ),
            'subsidiary' => $this->prepareSubsidiaryData(
                $this->glService->getSubsidiaryLedger(
                    $filters['account'] ?? '131',
                    $filters['from'] ?? null,
                    $filters['to'] ?? null,
                    $filters['group_by'] ?? null
                ),
                $options
            ),
            'trial_balance' => $this->prepareTbData(
                $this->trialBalanceService->getTrialBalance($filters['period'] ?? date('Y-m')),
                $options
            ),
            'journal' => $this->prepareJournalData(
                $this->journalBookService->getJournalBook(
                    $filters['from'] ?? null,
                    $filters['to'] ?? null
                ),
                $options
            ),

            // Vốn bằng tiền
            'cash_book' => $this->prepareCashBookData(
                $this->cashService->getCashBook(
                    $filters['account_code'] ?? '1111',
                    $filters['from'] ?? null,
                    $filters['to'] ?? null
                ),
                $options
            ),

            // Công nợ
            'ar_aging' => $this->prepareAgingData(
                $this->arService->getAging($filters['customer_id'] ?? null),
                'ar', $options
            ),
            'ap_aging' => $this->prepareAgingData(
                $this->apService->getAging($filters['supplier_id'] ?? null),
                'ap', $options
            ),

            // Thuế
            'vat' => $this->prepareTaxData(
                $this->vatService->getDeclaration($filters['period'] ?? date('Y-m')),
                'vat', $options
            ),
            'cit' => $this->prepareTaxData(
                $this->citService->getCalculation($filters['year'] ?? date('Y')),
                'cit', $options
            ),

            // Tồn kho
            'inventory_ledger' => $this->prepareInventoryData(
                $filters['item_id'] ?? null,
                $filters['from'] ?? null,
                $filters['to'] ?? null,
                $options
            ),

            default => throw new \InvalidArgumentException("Loại báo cáo '$reportType' không tồn tại"),
        };
    }

    // ── Data preparation helpers ──

    private function prepareFsData(array $fsData, string $statement, ?string $period, array &$options): array
    {
        $options['orientation'] ??= 'portrait';
        $options['filename'] ??= strtolower($statement) . '_' . ($period ?? date('Y')) . '.pdf';

        $headers = ['Mã số', 'Chỉ tiêu', 'Giá trị'];
        $rows = [];
        foreach ($fsData as $item) {
            $indent = $item['is_control'] || $item['is_total'] ? 'font-weight:bold;' : '';
            $rows[] = [
                $item['ma_so'],
                $item['name_vi'],
                number_format((float)$item['value'], 0, ',', '.'),
            ];
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'title' => $options['title'] ?? "Báo cáo $statement",
        ];
    }

    private function prepareLedgerData(array $glData, array &$options): array
    {
        $options['orientation'] ??= 'landscape';
        $options['filename'] ??= 'so_cai_' . ($glData['account_code'] ?? '000') . '_' . date('Ymd') . '.pdf';

        $headers = ['Ngày', 'Số CT', 'Diễn giải', 'TK ĐƯ', 'Nợ', 'Có', 'Số dư'];
        $rows = [];
        foreach (($glData['entries'] ?? []) as $e) {
            $rows[] = [
                $e['date'] ?? '',
                $e['reference'] ?? '',
                $e['description'] ?? '',
                $e['contra_account'] ?? '',
                number_format((float)($e['debit'] ?? 0), 0, ',', '.'),
                number_format((float)($e['credit'] ?? 0), 0, ',', '.'),
                number_format((float)($e['running_balance'] ?? 0), 0, ',', '.'),
            ];
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'summary' => [
                'Số dư đầu kỳ' => number_format((float)($glData['opening_balance'] ?? 0), 0, ',', '.'),
                'Số dư cuối kỳ' => number_format((float)($glData['closing_balance'] ?? 0), 0, ',', '.'),
            ],
            'title' => $options['title'] ?? 'Sổ cái TK ' . ($glData['account_code'] ?? ''),
        ];
    }

    private function prepareSubsidiaryData(array $subData, array &$options): array
    {
        // Tương tự ledgerData nhưng grouped by đối tượng
        // Implementation sẽ chi tiết hóa sau
        $options['orientation'] ??= 'landscape';
        $options['filename'] ??= 'so_chi_tiet_' . date('Ymd') . '.pdf';
        return $this->prepareLedgerData($subData, $options);
    }

    private function prepareTbData(array $tbData, array &$options): array
    {
        $options['orientation'] ??= 'landscape';
        $options['filename'] ??= 'bang_can_doi_so_phat_sinh_' . date('Ymd') . '.pdf';

        $headers = ['TK', 'Tên TK', 'SD ĐK Nợ', 'SD ĐK Có', 'PS Nợ', 'PS Có', 'SD CK Nợ', 'SD CK Có'];
        $rows = [];
        foreach (($tbData['accounts'] ?? []) as $a) {
            $rows[] = [
                $a['code'],
                $a['name'],
                number_format((float)($a['opening_debit'] ?? 0), 0, ',', '.'),
                number_format((float)($a['opening_credit'] ?? 0), 0, ',', '.'),
                number_format((float)($a['debit'] ?? 0), 0, ',', '.'),
                number_format((float)($a['credit'] ?? 0), 0, ',', '.'),
                number_format((float)($a['closing_debit'] ?? 0), 0, ',', '.'),
                number_format((float)($a['closing_credit'] ?? 0), 0, ',', '.'),
            ];
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'summary' => [
                'Tổng Nợ' => number_format((float)($tbData['total_debit'] ?? 0), 0, ',', '.'),
                'Tổng Có' => number_format((float)($tbData['total_credit'] ?? 0), 0, ',', '.'),
                'Cân đối' => ($tbData['balanced'] ?? false) ? 'Cân bằng' : 'Mất cân đối!',
            ],
            'title' => $options['title'] ?? 'Bảng cân đối số phát sinh',
        ];
    }

    private function prepareCashBookData(array $cbData, array &$options): array
    {
        // Tương tự ledgerData nhưng format cho sổ quỹ
        $options['orientation'] ??= 'landscape';
        $options['filename'] ??= 'so_quy_tien_mat_' . date('Ymd') . '.pdf';
        // placeholder
        return $cbData;
    }

    private function prepareAgingData(array $agingData, string $type, array &$options): array
    {
        $prefix = $type === 'ar' ? 'ar' : 'ap';
        $options['orientation'] ??= 'landscape';
        $options['filename'] ??= $prefix . '_aging_' . date('Ymd') . '.pdf';

        $headers = ['Đối tượng', 'Tổng dư nợ', '0-30 ngày', '31-60 ngày', '61-90 ngày', 'Trên 90 ngày', 'Dự phòng'];
        $rows = [];
        foreach (($agingData['items'] ?? []) as $item) {
            $rows[] = [
                $item['name'] ?? '',
                number_format((float)($item['total'] ?? 0), 0, ',', '.'),
                number_format((float)($item['range_0_30'] ?? 0), 0, ',', '.'),
                number_format((float)($item['range_31_60'] ?? 0), 0, ',', '.'),
                number_format((float)($item['range_61_90'] ?? 0), 0, ',', '.'),
                number_format((float)($item['range_90_plus'] ?? 0), 0, ',', '.'),
                number_format((float)($item['provision'] ?? 0), 0, ',', '.'),
            ];
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'summary' => [
                'Tổng cộng' => number_format((float)($agingData['total'] ?? 0), 0, ',', '.'),
                'Dự phòng' => number_format((float)($agingData['total_provision'] ?? 0), 0, ',', '.'),
            ],
            'title' => $options['title'] ?? ($type === 'ar'
                ? 'Bảng phân tích tuổi nợ phải thu' : 'Bảng phân tích tuổi nợ phải trả'),
        ];
    }

    private function prepareTaxData(array $taxData, string $type, array &$options): array
    {
        $options['orientation'] ??= 'portrait';
        $options['filename'] ??= $type . '_declaration_' . date('Ymd') . '.pdf';
        // placeholder — sẽ được implement chi tiết khi có tax export spec
        return $taxData;
    }

    private function prepareInventoryData(?int $itemId, ?string $from, ?string $to, array &$options): array
    {
        $options['orientation'] ??= 'landscape';
        $options['filename'] ??= 'inventory_ledger_' . date('Ymd') . '.pdf';
        // placeholder
        return [
            'headers' => ['Ngày', 'Số CT', 'Diễn giải', 'Nhập SL', 'Nhập GT', 'Xuất SL', 'Xuất GT', 'Tồn SL', 'Tồn GT'],
            'rows' => [],
            'title' => $options['title'] ?? 'Sổ chi tiết hàng tồn kho',
        ];
    }
}
```

---

## 7. UI/UX — User Interface

### 7.1 Export Button on Every Report View

Thêm export dropdown vào toolbar của mọi view có dữ liệu dạng bảng:

```html
<div class="btn-group">
    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
        <i class="bi bi-download"></i> Xuất
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><h6 class="dropdown-header">Định dạng</h6></li>
        <li><a class="dropdown-item" href="#" onclick="exportReport('pdf')">
            <i class="bi bi-file-pdf text-danger"></i> PDF</a></li>
        <li><a class="dropdown-item" href="#" onclick="exportReport('xls')">
            <i class="bi bi-file-earmark-excel text-success"></i> Excel (.xls)</a></li>
        <li><a class="dropdown-item" href="#" onclick="exportReport('csv')">
            <i class="bi bi-file-text text-primary"></i> CSV</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="#" onclick="window.print()">
            <i class="bi bi-printer"></i> In</a></li>
    </ul>
</div>
```

### 7.2 JavaScript Export Helper

```javascript
// Hàm xuất dữ liệu — gọi API /api/export/{reportType}
// Hiển thị loading spinner, trigger download, xử lý lỗi
// Được load trong layout.php hoặc mỗi view riêng
var exportInProgress = false;

function exportReport(format) {
    if (exportInProgress) {
        showToast('Đang xử lý yêu cầu xuất khác. Vui lòng đợi.', 'warning');
        return;
    }

    // Thu thập params từ view hiện tại
    var params = {
        format: format,
        filters: getExportFilters(),
        options: {
            orientation: getExportOrientation(),
            title: document.title || 'Báo cáo',
        }
    };

    exportInProgress = true;
    showLoading('Đang xuất file...');

    fetch('/api/export/' + reportType, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrf
        },
        body: JSON.stringify(params)
    })
    .then(function(response) {
        if (!response.ok) {
            return response.json().then(function(err) {
                throw new Error(err.error || 'Xuất file thất bại');
            });
        }
        return response.blob();
    })
    .then(function(blob) {
        // Tạo filename từ Content-Disposition header hoặc default
        var disposition = response.headers.get('Content-Disposition');
        var filename = 'export.pdf';
        if (disposition) {
            var match = disposition.match(/filename="?(.+?)"?$/);
            if (match) filename = match[1];
        }

        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        showToast('Xuất file thành công: ' + filename, 'success');
    })
    .catch(function(err) {
        showToast(err.message || 'Có lỗi xảy ra khi xuất file', 'error');
    })
    .finally(function() {
        exportInProgress = false;
        hideLoading();
    });
}

// Mỗi view override các hàm này để cung cấp thông tin riêng
function getExportFilters() {
    // Default: lấy từ các input filter trong view
    var filters = {};
    $('#fromDate').val() && (filters.from = $('#fromDate').val());
    $('#toDate').val() && (filters.to = $('#toDate').val());
    $('#accountSelect').val() && (filters.account = $('#accountSelect').val());
    $('#periodSelect').val() && (filters.period_id = $('#periodSelect').val());
    return filters;
}

function getExportOrientation() {
    // Landscape cho wide tables, portrait cho narrow
    var wideTables = ['ledger', 'subsidiary', 'trial_balance', 'cash_book', 'inventory'];
    return wideTables.includes(reportType) ? 'landscape' : 'portrait';
}

function showLoading(msg) {
    var overlay = $('<div class="loading-overlay"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">' + msg + '</p></div>');
    overlay.css({
        position: 'fixed', top: 0, left: 0, width: '100%', height: '100%',
        background: 'rgba(255,255,255,0.8)', zIndex: 99999,
        display: 'flex', flexDirection: 'column', alignItems: 'center',
        justifyContent: 'center'
    });
    overlay.attr('id', 'loadingOverlay');
    $('body').append(overlay);
}

function hideLoading() {
    $('#loadingOverlay').remove();
}
```

### 7.3 Insert Export Buttons into Existing Views

| View | reportType | Cần sửa? |
|---|---|---|
| `so_cai.php` | `ledger` | ✅ Đã có nút In + CSV. Thêm dropdown. |
| `trial_balance.php` | `trial_balance` | ✅ Cần thêm nút export. |
| `cash_book.php` | `cash_book` | Cần thêm nút export. |
| `fs_bc01.php` | `bc01` | Cần thêm nút export. |
| `fs_bc02.php` | `bc02` | Cần thêm nút export. |
| `fs_bc03.php` | `bc03` | Cần thêm nút export. |
| `so_chi_tiet.php` | `subsidiary` | Cần thêm nút export. |
| `so_nhat_ky_chung.php` | `journal` | Cần thêm nút export. |
| `ar_aging.php` | `ar_aging` | Cần thêm nút export. |
| `ap_aging.php` | `ap_aging` | Cần thêm nút export. |
| `vat_declarations.php` | `vat` | Cần thêm nút export. |
| `cit_calculations.php` | `cit` | Cần thêm nút export. |

---

## 8. Business Rules & Constraints

| Rule | Enforcement | Error Message |
|---|---|---|
| Export cần permission `report.export` | `Auth::requirePermission('report', 'export')` | "Bạn không có quyền xuất báo cáo" |
| Max date range: 365 consecutive days | Controller validation | "Khoảng thời gian xuất tối đa 365 ngày" |
| CSV max rows: 10,000 | Controller check trước khi render | "Dữ liệu vượt quá 10,000 dòng. Vui lòng xuất Excel." |
| PDF max pages: 100 | wkhtmltopdf `--stop-at-100-pages` hoặc split | "Báo cáo quá dài. Vui lòng thu hẹp phạm vi." |
| Filename: `{report}_{period}_{YYYYMMDD}.{ext}` | Auto-generated bởi ExportService | — |
| UTF-8 encoding for Vietnamese | BOM trong CSV, `<meta charset="UTF-8">` trong HTML | — |
| No PII in filename | Chỉ dùng period/date, không tên user | — |
| Number format: `#,##0` | `number_format($val, 0, ',', '.')` | — |
| Negative numbers: parentheses | `$val < 0 ? '(' . number_format(abs($val)) . ')' : number_format($val)` | — |
| Export dữ liệu từ kỳ đã đóng | Cho phép (read-only), không cần kiểm tra period open | — |

---

## 9. Edge Cases & Error Handling

| Scenario | Behavior |
|---|---|
| User chọn định dạng không hỗ trợ | 400 error: "Định dạng 'docx' không được hỗ trợ" |
| wkhtmltopdf chưa cài | Hide PDF option từ dropdown; nếu gọi → 500 error + hướng dẫn cài |
| Dữ liệu rỗng | Vẫn tạo file với header + "(Không có dữ liệu)" |
| Export concurrent (2 user cùng lúc) | Mỗi request sinh file riêng, không conflict |
| File quá lớn (>50MB) | Stream output thay vì build full content trong memory |
| Kết nối mất giữa chừng | Client retry button, server không lưu state |
| Font Unicode không có | wkhtmltopdf fallback → DejaVu font; pure PHP → multi-byte string handling |
| BC03 export cả 2 methods | Phân biệt `bc03` (indirect) và `bc03_direct` (direct) |

---

## 10. Implementation Checklist

### Phase 1 — Core Infrastructure (2 days)
```
[ ] Interface: ExportDriverInterface (Domain/Contract/Export/)
[ ] Interface: LayoutRendererInterface (Domain/Contract/Export/)
[ ] Class: ExportResult (Domain/Model/Export/)
[ ] Service: ExportService (Domain/Service/) — phối hợp driver + renderer
[ ] DI: config/services.php — register drivers + renderers
[ ] Routes: POST /api/export/{reportType} trong config/routes/api_export.php
[ ] Controller: ExportController (Interfaces/HTTP/Export/) — unified endpoint
```

### Phase 2 — Drivers (1 day)
```
[ ] Driver: CsvDriver (Infrastructure/Export/) — fputcsv + UTF-8 BOM
[ ] Driver: HtmlExcelDriver (Infrastructure/Export/) — HTML table → .xls
[ ] Driver: HtmlDriver (Infrastructure/Export/) — full HTML for browser print
[ ] Driver: WkhtmltopdfDriver (Infrastructure/Export/) — proc_open
[ ] Driver availability check — WkhtmltopdfDriver::isAvailable()
```

### Phase 3 — Layout Renderers (1.5 days)
```
[ ] Layout: StandardLayoutRenderer (Infrastructure/Export/Layout/) — company header + title + table + signatures
[ ] Layout: FsLayoutRenderer (optional, nếu BC01/02/03 cần format khác)
[ ] Layout: LedgerLayoutRenderer (optional, nếu sổ cái cần running balance highlight)
[ ] Layout: AgingLayoutRenderer (optional, nếu aging cần color coding)
[ ] Data preparers: ExportController::fetchReportData() match cho 15+ report types
```

### Phase 4 — UI Integration (1 day)
```
[ ] JS: export helper function trong layout.php hoặc file riêng
[ ] CSS: @media print styles trong layout.php
[ ] CSS: Print-specific layout (TT 99 compliant)
[ ] View: Thêm export dropdown cho so_cai.php
[ ] View: Thêm export dropdown cho trial_balance.php
[ ] View: Thêm export dropdown cho fs_bc01/02/03.php
[ ] View: Thêm export dropdown cho cash_book.php
[ ] View: Thêm export dropdown cho so_chi_tiet.php
[ ] View: Thêm export dropdown cho so_nhat_ky_chung.php
[ ] View: Thêm export dropdown cho ar/ap_aging.php
[ ] View: Thêm export dropdown cho vat/cit declarations
```

### Phase 5 — Polish & Testing (1 day)
```
[ ] Tests: ExportDriverTest — CsvDriver (happy + edge cases)
[ ] Tests: ExportDriverTest — HtmlExcelDriver (UTF-8, number format)
[ ] Tests: ExportDriverTest — HtmlDriver (print layout)
[ ] Tests: ExportDriverTest — WkhtmltopdfDriver (mock proc_open)
[ ] Tests: ExportServiceTest — driver routing, error handling
[ ] Tests: ExportControllerTest — permission, validation, file download
[ ] Tests: Integration — full export flow with real data
[ ] Tests: Print CSS — verify @media print rendering
[ ] Permissions: Thêm 'export' action cho module 'report'
[ ] Cleanup: Xóa hoặc deprecate ReportExportController cũ nếu không cần
```

---

## 11. Effort Estimate

| Phase | Days | Details |
|---|---|---|
| Phase 1: Core | 2 | Interfaces, ExportService, DI, Controller, Routes |
| Phase 2: Drivers | 1 | CSV, HTML Excel, HTML, wkhtmltopdf |
| Phase 3: Layouts | 1.5 | Standard layout + data preparers cho 15+ reports |
| Phase 4: UI | 1 | 12 views thêm nút export, JS helper, print CSS |
| Phase 5: Testing | 1 | 8+ test files, edge cases, integration |
| **Total** | **6.5** | |

### Quick Win (Day 1)
CsvDriver + HtmlExcelDriver + HtmlDriver đã có sẵn nền tảng từ `ReportExportService`. Ngày 1 có thể export được:
- Sổ cái CSV (đã có route)
- BC03 CSV (đã có route)
- Trial Balance CSV (đã có route)
- BC01/BC02 CSV
- AR/AP Aging CSV

### Risk Mitigation
- **wkhtmltopdf không khả dụng:** Ẩn nút PDF, fallback sang HTML Print + hướng dẫn user dùng browser Print-to-PDF
- **Dữ liệu lớn:** Split thành nhiều file, stream output
- **Font Unicode thiếu:** Bundle DejaVu font subset trong project

---

## 12. Comparison with Existing Export

| Aspect | Current (ReportExportService) | Proposed (ExportService) |
|---|---|---|
| Format support | CSV, HTML | CSV, XLS, HTML, PDF (via wkhtmltopdf) |
| Report types | 3 (ledger, trial_balance, bc03) | 15+ (all reports) |
| Endpoint | Multiple per report | Unified POST /api/export/{type} |
| Architecture | Procedural, direct echo | Interface-based, driver pattern |
| Layout | Minimal (title + table + print button) | TT 99 compliant (company header + signatures) |
| Error handling | Basic try-catch | Structured validation + error codes |
| Testing | None | 8+ test files |

---

## 13. Future Extensions (Beyond Scope)

1. **PDF với chữ ký số:** Ký số PDF bằng OpenSSL (hỗ trợ nộp thuế GDT)
2. **Excel (.xlsx) thực:** Nếu sau này có thể dùng PhpSpreadsheet → xlsx real
3. **Batch export:** Chọn nhiều kỳ, nhiều báo cáo → zip file
4. **Scheduled export:** Gửi báo cáo định kỳ qua email
5. **Export API token:** Cho phép API key export (integration với hệ thống khác)
6. **Multi-language:** Tiếng Anh cho các công ty FDI
7. **Custom template:** User tự thiết kế mẫu in (drag-drop)
