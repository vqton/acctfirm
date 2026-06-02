<?php
// Shared test bootstrap: autoloader + assert helpers

spl_autoload_register(function ($class) {
    $prefix = 'Accounting\\';
    $baseDir = __DIR__ . '/../src/Accounting/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    require $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});

$failed = 0;
$total = 0;

function assertEq(mixed $a, mixed $b, string $msg = ''): void
{
    global $total, $failed;
    $total++;
    if ($a === $b) {
        echo "PASS: {$msg}\n";
    } else {
        echo "FAIL: {$msg} — expected " . var_export($b, true) . ", got " . var_export($a, true) . "\n";
        $failed++;
    }
}

function assertTrue(bool $cond, string $msg = ''): void
{
    global $total, $failed;
    $total++;
    if ($cond) {
        echo "PASS: {$msg}\n";
    } else {
        echo "FAIL: {$msg} — expected true, got false\n";
        $failed++;
    }
}

function assertFalse(bool $cond, string $msg = ''): void
{
    global $total, $failed;
    $total++;
    if (!$cond) {
        echo "PASS: {$msg}\n";
    } else {
        echo "FAIL: {$msg} — expected false, got true\n";
        $failed++;
    }
}

function assertNear(float $a, float $b, string $msg = ''): void
{
    global $total, $failed;
    $total++;
    if (abs($a - $b) < 0.01) {
        echo "PASS: {$msg}\n";
    } else {
        echo "FAIL: {$msg} — expected {$b}, got {$a}\n";
        $failed++;
    }
}

function results(): void
{
    global $total, $failed;
    echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
    exit($failed > 0 ? 1 : 0);
}
