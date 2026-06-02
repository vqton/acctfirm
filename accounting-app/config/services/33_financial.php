<?php
// TỰ ĐỘNG SINH TỪ services.php — không sửa trực tiếp. Sửa services.php và chạy split script.

use Accounting\Domain\Service\PeriodService;
use Accounting\Domain\Service\FsService;
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
$apService = new ApService($pdo, $supplierRepository, $accountRepository, $journalService, $auditLogger);
$arService = new ArService($pdo, $accountRepository, $journalService, $auditLogger, $customerRepository);
$glService = new GlService($pdo, $accountRepository);
$journalBookService = new JournalBookService($pdo);
$fixedAssetService = new FixedAssetService($fixedAssetRepository, $accountRepository, $transactionRepository, $journalService, $pdo, $auditLogger);
$vatService = new VatService($pdo, $auditLogger);
$citService = new CitService($pdo, $auditLogger);
$fctService = new FctService($pdo, $journalService, $auditLogger);
$openingBalanceService = new OpeningBalanceService($pdo, $accountRepository);
$reportExportService = new ReportExportService();
