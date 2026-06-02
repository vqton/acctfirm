<?php
// Test: Export drivers (CSV, Excel HTML, PDF) + ExportService unified
//
// Kiểm tra:
// - CsvDriver: BOM, escaping, delimiter
// - HtmlExcelDriver: HTML table structure, MSO namespace, signature block
// - PurePhpPdfDriver: PDF header, structure, page count
// - ExportService: driver registration, dispatch, error handling
// - ExportService: unsupported format throws InvalidArgumentException
require __DIR__ . '/bootstrap.php';

use Accounting\Domain\Service\ExportService;
use Accounting\Domain\Service\ReportExportService;
use Accounting\Infrastructure\Export\CsvDriver;
use Accounting\Infrastructure\Export\HtmlExcelDriver;
use Accounting\Infrastructure\Export\PurePhpPdfDriver;

$headers = ['STT', 'Tên tài khoản', 'Số dư', 'Ghi chú'];
$rows = [
    ['1', 'Tiền mặt', '100000', '1111'],
    ['2', 'Tiền gửi NH', '500000', '1121'],
    ['3', 'Phải thu KH', '200000', '1311'],
];

// === CSV DRIVER ===
$csv = new CsvDriver();
assertTrue($csv->supports('csv'), 'CSV driver supports csv');
assertTrue($csv->supports('CSV'), 'CSV driver supports CSV');
assertFalse($csv->supports('pdf'), 'CSV driver does not support pdf');

$result = $csv->export('Báo cáo', $headers, $rows);
assertTrue(str_starts_with($result->getContent(), "\xEF\xBB\xBF"), 'CSV has UTF-8 BOM');
assertTrue(str_contains($result->getContent(), 'STT'), 'CSV contains header');
assertTrue(str_contains($result->getContent(), 'Tiền mặt'), 'CSV contains data');
assertEq($result->getMimeType(), 'text/csv; charset=utf-8', 'CSV MIME type');
assertTrue(str_ends_with($result->getFilename(), '.csv'), 'CSV filename extension');
assertTrue($result->getSize() > 0, 'CSV size > 0');

// CSV with custom delimiter — cần 2 cột để thấy delimiter
$result2 = $csv->export('Test', ['A', 'B'], [['1', '2']], ['delimiter' => ';']);
assertTrue(str_contains($result2->getContent(), '1;2'), 'CSV semicolon delimiter');

// CSV with escaping
$result3 = $csv->export('Test', ['A'], [['Hello, "World"']]);
assertTrue(str_contains($result3->getContent(), '"Hello, ""World"""'), 'CSV escaping');

// === HTML EXCEL DRIVER ===
$xls = new HtmlExcelDriver();
assertTrue($xls->supports('xls'), 'XLS driver supports xls');
assertTrue($xls->supports('xlsx'), 'XLS driver supports xlsx');
assertFalse($xls->supports('pdf'), 'XLS driver does not support pdf');

$resultXls = $xls->export('Bảng cân đối', $headers, $rows, [
    'subtitle' => 'Kỳ 06/2026',
    'orientation' => 'landscape',
]);

assertTrue(str_contains($resultXls->getContent(), 'Bảng cân đối'), 'XLS contains title');
assertTrue(str_contains($resultXls->getContent(), '<table>'), 'XLS contains table tag');
assertTrue(str_contains($resultXls->getContent(), '<th>'), 'XLS contains header cells');
assertTrue(str_contains($resultXls->getContent(), '<td'), 'XLS contains data cells');
assertTrue(str_contains($resultXls->getContent(), 'Kỳ 06/2026'), 'XLS contains subtitle');
assertTrue(str_contains($resultXls->getContent(), 'urn:schemas-microsoft-com:office:excel'), 'XLS contains MSO namespace');
assertEq($resultXls->getMimeType(), 'application/vnd.ms-excel; charset=utf-8', 'XLS MIME type');
assertTrue(str_ends_with($resultXls->getFilename(), '.xls'), 'XLS filename extension');

// XLS with signature block
$resultXlsSig = $xls->export('Test', ['A'], [['1']], ['signature' => true]);
assertTrue(str_contains($resultXlsSig->getContent(), 'Người lập biểu'), 'XLS has signature block');
assertTrue(str_contains($resultXlsSig->getContent(), 'Kế toán trưởng'), 'XLS has accountant signature');
assertTrue(str_contains($resultXlsSig->getContent(), 'Giám đốc'), 'XLS has director signature');

// XLS with summary
$resultXlsSum = $xls->export('Test', ['A'], [['1']], ['summary' => ['Tổng cộng' => '800000']]);
assertTrue(str_contains($resultXlsSum->getContent(), 'Tổng cộng'), 'XLS contains summary');
assertTrue(str_contains($resultXlsSum->getContent(), '800000'), 'XLS contains summary value');

// === PDF DRIVER ===
$pdf = new PurePhpPdfDriver();
assertTrue($pdf->supports('pdf'), 'PDF driver supports pdf');
assertFalse($pdf->supports('csv'), 'PDF driver does not support csv');

$resultPdf = $pdf->export('Báo cáo tài chính', $headers, $rows, [
    'orientation' => 'landscape',
    'footer' => 'Ngày xuất: 01/06/2026',
]);

assertTrue(str_starts_with($resultPdf->getContent(), '%PDF-1.4'), 'PDF starts with PDF header');
assertTrue(str_contains($resultPdf->getContent(), 'endobj'), 'PDF contains endobj markers');
assertTrue(str_contains($resultPdf->getContent(), '/Type /Catalog'), 'PDF has Catalog');
assertTrue(str_contains($resultPdf->getContent(), '/Type /Pages'), 'PDF has Pages');
assertTrue(str_contains($resultPdf->getContent(), '/Type /Page'), 'PDF has Page objects');
assertTrue(str_contains($resultPdf->getContent(), '%%EOF'), 'PDF ends with EOF marker');
assertEq($resultPdf->getMimeType(), 'application/pdf', 'PDF MIME type');
assertTrue(str_ends_with($resultPdf->getFilename(), '.pdf'), 'PDF filename extension');
assertTrue($resultPdf->getSize() > 0, 'PDF size > 0');

// PDF portrait
$resultPdfPortrait = $pdf->export('Báo cáo', $headers, $rows);
assertTrue(str_starts_with($resultPdfPortrait->getContent(), '%PDF-1.4'), 'PDF portrait valid');

// PDF with signature
$resultPdfSig = $pdf->export('Test', ['A'], [['1']], ['signature' => true]);
assertTrue(str_starts_with($resultPdfSig->getContent(), '%PDF-1.4'), 'PDF with signature valid');

// === EXPORT SERVICE ===
$legacyExport = new ReportExportService();
$exportService = new ExportService($legacyExport);

// Chưa đăng ký driver — phải đăng ký trước
try {
    $exportService->export('csv', 'Test', $headers, $rows);
    assertTrue(false, 'ExportService without drivers should throw');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'ExportService throws without drivers');
}

// Đăng ký driver
$csvDriver = new CsvDriver();
$xlsDriver = new HtmlExcelDriver();
$pdfDriver = new PurePhpPdfDriver();

$exportService->registerDriver('csv', $csvDriver);
$exportService->registerDriver('xls', $xlsDriver);
$exportService->registerDriver('pdf', $pdfDriver);

assertEq($exportService->getSupportedFormats(), ['csv', 'xls', 'pdf'], 'ExportService supported formats');

// Export CSV qua service
$resultSvc = $exportService->export('csv', 'Test', $headers, $rows);
assertTrue(str_starts_with($resultSvc->getContent(), "\xEF\xBB\xBF"), 'ExportService CSV has BOM');
assertEq($resultSvc->getMimeType(), 'text/csv; charset=utf-8', 'ExportService CSV MIME');

// Export XLS qua service
$resultSvcXls = $exportService->export('xls', 'Test', $headers, $rows);
assertTrue(str_contains($resultSvcXls->getContent(), '<table>'), 'ExportService XLS has table');

// Export PDF qua service
$resultSvcPdf = $exportService->export('pdf', 'Test', $headers, $rows);
assertTrue(str_starts_with($resultSvcPdf->getContent(), '%PDF-1.4'), 'ExportService PDF valid');

// Unsupported format throws
try {
    $exportService->export('doc', 'Test', $headers, $rows);
    assertTrue(false, 'Unsupported format should throw');
} catch (\InvalidArgumentException $e) {
    assertTrue(true, 'Unsupported format throws error');
}

// Backward compatibility
assertTrue($exportService->getLegacyExport() instanceof ReportExportService, 'Legacy export accessible');

results();
