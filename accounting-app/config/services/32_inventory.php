<?php
// TỰ ĐỘNG SINH TỪ services.php — không sửa trực tiếp. Sửa services.php và chạy split script.

use Accounting\Domain\Service\InventoryService;
use Accounting\Domain\Service\CcdcAllocationService;
use Accounting\Domain\Service\GoodsIssueService;

$inventoryService = new InventoryService($accountRepository, $transactionRepository, $itemRepository, $warehouseRepository, $journalService, $pdo);
$ccdcAllocationService = new CcdcAllocationService($ccdcRepository, $journalService, $pdo, $auditLogger);
$goodsIssueService = new GoodsIssueService($pdo, $inventoryService, $voucherService, $itemRepository, $auditLogger);
