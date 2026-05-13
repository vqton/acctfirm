<?php
// Test 1: ValuationService Weighted Average calculation
// RED phase — this file asserts the expected behavior before implementation

require_once __DIR__ . '/../src/Accounting/Domain/Service/ValuationService.php';

use Accounting\Domain\Service\ValuationService;

$failed = 0;
$total = 0;

function assertEq($expected, $actual, $msg) {
    global $total, $failed;
    $total++;
    $expected = round($expected, 4);
    $actual = round($actual, 4);
    if (abs($expected - $actual) > 0.0001) {
        echo "FAIL: {$msg} — expected {$expected}, got {$actual}\n";
        $failed++;
    } else {
        echo "PASS: {$msg}\n";
    }
}

// Test 1: Weighted average with simple 3-batch receipt
echo "\n--- Test: Basic Weighted Average ---\n";
$svc = new ValuationService();
$batches = [
    ['qty' => 10, 'unit_cost' => 1000],  // batch 1: 10 x 1,000 = 10,000
    ['qty' => 20, 'unit_cost' => 1200],  // batch 2: 20 x 1,200 = 24,000
    ['qty' => 30, 'unit_cost' => 1100],  // batch 3: 30 x 1,100 = 33,000
];
// Total cost = 10,000 + 24,000 + 33,000 = 67,000
// Total qty  = 10 + 20 + 30 = 60
// Avg       = 67,000 / 60 = 1,116.6667
$avg = $svc->calculateWeightedAverage($batches);
assertEq(1116.6667, $avg, 'Weighted average of 3 batches');

// Test 2: Single batch — average = unit cost
echo "\n--- Test: Single Batch ---\n";
$avg = $svc->calculateWeightedAverage([['qty' => 5, 'unit_cost' => 500]]);
assertEq(500, $avg, 'Single batch average equals unit cost');

// Test 3: Zero quantity should return 0
echo "\n--- Test: Zero Quantity ---\n";
$avg = $svc->calculateWeightedAverage([['qty' => 0, 'unit_cost' => 100]]);
assertEq(0, $avg, 'Zero quantity returns zero');

// Test 4: Zero batches should return 0
echo "\n--- Test: Empty Batches ---\n";
$avg = $svc->calculateWeightedAverage([]);
assertEq(0, $avg, 'Empty batches returns zero');

// Summary
echo "\n=== Results: {$total} tests, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);