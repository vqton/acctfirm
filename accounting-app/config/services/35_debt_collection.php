<?php
// TỰ ĐỘNG SINH TỪ services.php — không sửa trực tiếp. Sửa services.php và chạy split script.

use Accounting\Infrastructure\Repository\PDODebtCollectionRepository;
use Accounting\Domain\Service\DebtCollectionService;
use Accounting\Interfaces\HTTP\Financial\DebtCollectionController;

// === DEBT COLLECTION: Repository + Service ===
$debtCollectionRepo = new PDODebtCollectionRepository($pdo);
$debtCollectionService = new DebtCollectionService($pdo, $debtCollectionRepo, $arService, $auditLogger);
$debtCollectionController = new DebtCollectionController($debtCollectionService);
