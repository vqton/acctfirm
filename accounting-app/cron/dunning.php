<?php
// Cron thu hồi công nợ — chạy hàng ngày 08:00
// Usage: php cron/dunning.php
// Crontab: 0 8 * * * php /path/to/cron/dunning.php >> logs/dunning.log 2>&1

require __DIR__ . '/../config/services.php';

$dcs = $GLOBALS['container']['debtCollectionService'];
$logger = $GLOBALS['container']['auditLogger'] ?? null;
$start = microtime(true);

echo "[" . date('Y-m-d H:i:s') . "] Dunning cron started\n";

try {
    // 1. Sinh queue entries cho hóa đơn quá hạn mới
    $generated = $dcs->generateQueueEntries('cron');
    echo "  Queue entries generated: " . count($generated) . "\n";

    // 2. Kiểm tra cam kết đến hạn
    $promiseResults = $dcs->checkPromisesDue('cron');
    echo "  Promises checked: kept={$promiseResults['kept']}, broken={$promiseResults['broken']}\n";

    // 3. Tự động leo thang
    $escalated = $dcs->autoEscalate('cron');
    echo "  Auto-escalated: {$escalated['escalated']}\n";

    // 4. Tự động release hold hết hạn
    $released = $dcs->autoReleaseHolds('cron');
    echo "  Holds auto-released: {$released['released']}\n";

    $elapsed = round((microtime(true) - $start) * 1000, 0);
    echo "[" . date('Y-m-d H:i:s') . "] Dunning cron completed in {$elapsed}ms\n";
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
