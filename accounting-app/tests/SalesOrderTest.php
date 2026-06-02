<?php
require __DIR__ . '/bootstrap.php';

use Accounting\Domain\Model\SalesOrder;
use Accounting\Domain\Model\SalesOrderLine;

// === Model Tests ===

// Test 1: Create SalesOrder with minimal params
$so = new SalesOrder('so_test1', 'SO2026-000001', 1, '2026-06-01');
assertEq('draft', $so->getStatus(), 'Default status is draft');
assertEq('SO2026-000001', $so->getReference(), 'Reference set correctly');
assertEq(1, $so->getCustomerId(), 'Customer ID set correctly');

// Test 2: Status transitions
assertTrue($so->canTransitionTo('confirmed'), 'Draft can go to confirmed');
assertTrue($so->canTransitionTo('cancelled'), 'Draft can be cancelled');
assertFalse($so->canTransitionTo('shipped'), 'Draft cannot go to shipped directly');

$so->setStatus('confirmed');
assertTrue($so->canTransitionTo('cancelled'), 'Confirmed can be cancelled');
assertTrue($so->canTransitionTo('partially_shipped'), 'Confirmed can be shipped');
assertFalse($so->canTransitionTo('draft'), 'Cannot go back to draft');

$so->setStatus('cancelled');
assertFalse($so->canTransitionTo('confirmed'), 'Cannot transition from cancelled');

// Test 3: Create SO with lines
$so2 = new SalesOrder('so_test2', 'SO2026-000002', 2, '2026-06-02');
$line1 = new SalesOrderLine(
    null, null, 1, 100, 'SP001', 'Sản phẩm A',
    'cái', 10.0, 0, 0, 100000, 0, 0, 10, 100000, 1000000, false, 1
);
$line2 = new SalesOrderLine(
    null, null, 2, 101, 'SP002', 'Sản phẩm B',
    'cái', 5.0, 0, 0, 200000, 0, 0, 10, 100000, 1000000, false, 2
);
$so2->setLines([$line1, $line2]);
$so2->updateAmounts();
assertEq(2000000.0, $so2->getTotalAmount(), 'Total = 2M');
assertEq(200000.0, $so2->getTaxAmount(), 'Tax = 200k');
assertEq(2200000.0, $so2->getGrandTotal(), 'Grand total = 2.2M');

// Test 4: SalesOrder toArray
$arr = $so2->toArray();
assertEq('SO2026-000002', $arr['reference'], 'toArray reference');
assertEq(2, count($arr['lines']), 'toArray includes lines');
assertEq('Sản phẩm A', $arr['lines'][0]['item_name'], 'Line item name in array');

// Test 5: Validate invalid status
$threw = false;
try {
    $so2->setStatus('invalid_status');
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
assertTrue($threw, 'Invalid status throws exception');

// === SalesOrderLine Tests ===

$line = new SalesOrderLine('line1', 'so1', 1, 100, 'SP001', 'Test Item', 'kg', 15, 3, 2, 50000, 10, 75000, 10, 7500, 675000, false, 1);
assertEq('line1', $line->getId(), 'Line ID');
assertEq(15.0, $line->getQtyOrdered(), 'Qty ordered');
assertEq(3.0, $line->getQtyShipped(), 'Qty shipped');
assertEq(50000.0, $line->getUnitPrice(), 'Unit price');
assertEq(10.0, $line->getDiscountPct(), 'Discount %');
assertEq(675000.0, $line->getLineTotal(), 'Line total');

$line->setQtyShipped(10.0);
assertEq(10.0, $line->getQtyShipped(), 'Qty shipped updated');
$line->setSalesOrderId('so1');
assertEq('so1', $line->getSalesOrderId(), 'SalesOrder ID set');

// Test toArray
$arr = $line->toArray();
assertEq('SP001', $arr['item_code'], 'Item code in array');
assertTrue($arr['is_service'] === false, 'is_service boolean');
assertEq(15.0, $arr['qty_ordered'], 'Qty ordered in array');

results();
