<?php
// TỰ ĐỘNG SINH TỪ services.php — không sửa trực tiếp. Sửa services.php và chạy split script.

use Accounting\Domain\Service\JournalService;

// JournalService — CORE: mọi bút toán đều qua service này
// Đảm bảo Dr = Cr, kiểm tra posting rules, sinh số chứng từ, ghi audit trail
// Tất cả module khác đều phụ thuộc vào JournalService để ghi sổ
$journalService = new JournalService($accountRepository, $transactionRepository, $pdo, $auditLogger, $postingRuleService, $voucherService, $approvalRoutingService);
