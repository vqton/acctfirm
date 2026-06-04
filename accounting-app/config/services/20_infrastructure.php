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


