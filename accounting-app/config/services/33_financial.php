<?php
// TỰ ĐỘNG SINH TỪ services.php — không sửa trực tiếp. Sửa services.php và chạy split script.

use Accounting\Domain\Service\PeriodService;
use Accounting\Domain\Service\FsService;
use Accounting\Domain\Service\XbrlGenerator;
use Accounting\Domain\Service\ImportService;
use Accounting\Domain\Service\CurrencyDisplayService;
use Accounting\Domain\Service\NotificationService;
use Accounting\Domain\Service\PrintTemplateService;
use Accounting\Domain\Service\ApService;
use Accounting\Domain\Service\ArService;
use Accounting\Domain\Service\GlService;
use Accounting\Domain\Service\JournalBookService;
use Accounting\Domain\Service\FixedAssetService;
use Accounting\Domain\Service\VatService;
use Accounting\Domain\Service\CitService;
use Accounting\Domain\Service\FctService;
use Accounting\Domain\Service\OpeningBalanceService;
use Accounting\Domain\Service\ReportExportService;

// PeriodService phụ thuộc InventoryService để kiểm tra tồn kho trước khi đóng kỳ
$periodService = new PeriodService($pdo, $accountRepository, $transactionRepository, $journalService, $auditLogger, $inventoryService, $reconciliationService);
$fsService = new FsService($pdo, $accountRepository, $auditLogger);
$xbrlGenerator = new XbrlGenerator($pdo, $auditLogger);
$importService = new ImportService($pdo, $accountRepository, $auditLogger);
$currencyDisplayService = new CurrencyDisplayService($pdo);
$notificationService = new NotificationService($pdo, $auditLogger);
$printTemplateService = new PrintTemplateService($pdo);
$apService = new ApService($pdo, $supplierRepository, $accountRepository, $journalService, $auditLogger);
$arService = new ArService($pdo, $accountRepository, $journalService, $auditLogger, $customerRepository);
$glService = new GlService($pdo, $accountRepository);
$journalBookService = new JournalBookService($pdo);
$fixedAssetService = new FixedAssetService($fixedAssetRepository, $accountRepository, $transactionRepository, $journalService, $pdo, $auditLogger);
use Accounting\Domain\Service\DepreciationBatchService;
$depreciationBatchService = new DepreciationBatchService($pdo, $fixedAssetRepository);
$vatService = new VatService($pdo, $auditLogger);
$vatRateService = new \Accounting\Domain\Service\VatRateService($pdo);
$citService = new CitService($pdo, $auditLogger);
$citDeclarationEngine = new \Accounting\Domain\Service\CitDeclarationEngine($pdo);
$pitDeclarationService = new \Accounting\Domain\Service\PitDeclarationService($pdo, $auditLogger);
$fctService = new FctService($pdo, $journalService, $auditLogger);
$openingBalanceService = new OpeningBalanceService($pdo, $accountRepository);
$reportExportService = new ReportExportService();

// === EXPORT DRIVERS (Gap 10 — PDF/Excel/CSV) ===
// Strategy pattern — mỗi driver xử lý một định dạng xuất
// Phụ thuộc reportExportService đã khởi tạo ở trên
$exportCsvDriver = new \Accounting\Infrastructure\Export\CsvDriver();
$exportXlsDriver = new \Accounting\Infrastructure\Export\HtmlExcelDriver();
$exportPdfDriver = new \Accounting\Infrastructure\Export\PurePhpPdfDriver();
$exportService = new \Accounting\Domain\Service\ExportService($reportExportService);
$exportService->registerDriver('csv', $exportCsvDriver);
$exportService->registerDriver('xls', $exportXlsDriver);
$exportService->registerDriver('pdf', $exportPdfDriver);

// === BC09: Thuyết minh BCTC ===
use Accounting\Domain\Repository\Bc09RepositoryInterface;
use Accounting\Infrastructure\Repository\PDOBc09Repository;
use Accounting\Domain\Service\FsNotesService;

$bc09Repository = new PDOBc09Repository($pdo);
$fsNotesService = new FsNotesService($bc09Repository, $accountRepository, $periodService, $pdo);

// === SUB-LEDGER SERVICE — phụ thuộc GlService đã khởi tạo ở trên ===
use Accounting\Domain\Service\SubLedgerService;
$subLedgerService = new SubLedgerService($pdo, $accountRepository, $glService, $periodService, $reportExportService);

// === SALES ORDER SERVICE — phụ thuộc journalService, inventoryService, reportExportService đã khởi tạo ===
$salesOrderRepository = new \Accounting\Infrastructure\Repository\PDOSalesOrderRepository($pdo);
$salesOrderService = new \Accounting\Domain\Service\SalesOrderService($salesOrderRepository, $journalService, $voucherService, $inventoryService, $pdo, $reportExportService);
