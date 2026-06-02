<?php
// TỰ ĐỘNG SINH TỪ services.php — không sửa trực tiếp. Sửa services.php và chạy split script.

use Accounting\Domain\Service\AccountService;

// === COA SERVICE: Business logic cho Hệ thống Tài khoản ===
$accountService = new AccountService($accountRepository, $auditLogger, $journalService);
