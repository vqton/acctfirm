<?php
// TỰ ĐỘNG SINH TỪ services.php — không sửa trực tiếp. Sửa services.php và chạy split script.

use Accounting\Infrastructure\Database\AuditLogger;
use Accounting\Domain\Service\PostingRuleService;
use Accounting\Domain\Service\VoucherService;
use Accounting\Domain\Service\ApprovalRoutingService;
use Accounting\Domain\Service\ReconciliationService;

// === LỚP INFRASTRUCTURE SERVICE: Audit, Posting Rule, Voucher ===
// Các service không chứa nghiệp vụ kế toán cụ thể — hỗ trợ kỹ thuật
$auditLogger = new AuditLogger($pdo);
$postingRuleService = new PostingRuleService($pdo);
$voucherService = new VoucherService($pdo);
$approvalRoutingService = new ApprovalRoutingService($pdo);
$reconciliationService = new ReconciliationService($pdo);

// === SUB-LEDGER SERVICE ===
use Accounting\Domain\Service\SubLedgerService;
$subLedgerService = new SubLedgerService($pdo, $accountRepository, $glService, $periodService, $reportExportService);

// === EXPORT DRIVERS (Gap 10 — PDF/Excel/CSV) ===
// Strategy pattern — mỗi driver xử lý một định dạng xuất
// CsvDriver: UTF-8 BOM + fputcsv
// HtmlExcelDriver: HTML table với MSO namespace cho Excel
// PurePhpPdfDriver: PDF 1.4 thuần PHP (không thư viện)
// ExportService: unified interface — registerDriver + export() dispatch
$exportCsvDriver = new CsvDriver();
$exportXlsDriver = new HtmlExcelDriver();
$exportPdfDriver = new PurePhpPdfDriver();
$exportService = new ExportService($reportExportService);
$exportService->registerDriver('csv', $exportCsvDriver);
$exportService->registerDriver('xls', $exportXlsDriver);
$exportService->registerDriver('pdf', $exportPdfDriver);
