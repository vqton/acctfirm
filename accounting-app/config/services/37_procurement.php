<?php
// TỰ ĐỘNG SINH TỪ services.php — không sửa trực tiếp. Sửa services.php và chạy split script.

use Accounting\Domain\Service\ProcurementService;
use Accounting\Domain\Service\ThreeWayMatchService;
use Accounting\Domain\Service\BudgetControlService;

$procurementService = new ProcurementService($purchaseRequisitionRepo, $purchaseOrderRepo, $goodsReceiptRepo, $itemRepository, $supplierRepository, $journalService, $inventoryService, $auditLogger, $approvalRoutingService, $pdo);
$threeWayMatchService = new ThreeWayMatchService($pdo, $auditLogger);
$budgetControlService = new BudgetControlService($pdo, $auditLogger);
