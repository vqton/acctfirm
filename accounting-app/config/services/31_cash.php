<?php
// TỰ ĐỘNG SINH TỪ services.php — không sửa trực tiếp. Sửa services.php và chạy split script.

use Accounting\Domain\Service\FxRevaluationService;
use Accounting\Domain\Service\IntercompanyService;
use Accounting\Domain\Service\CashService;
use Accounting\Domain\Service\PettyCashService;
use Accounting\Domain\Service\BankReconciliationService;
use Accounting\Domain\Service\CashReportService;

$fxRevaluationService = new FxRevaluationService($pdo, $accountRepository, $journalService);
$intercompanyService = new IntercompanyService($pdo, $journalService);
$cashService = new CashService($accountRepository, $transactionRepository, $journalService, $pdo);
$pettyCashService = new PettyCashService($accountRepository, $transactionRepository, $journalService, $pdo);
$bankReconciliationService = new BankReconciliationService($accountRepository, $transactionRepository, $journalService, $pdo, $auditLogger);
$cashReportService = new CashReportService($pdo, $accountRepository);
