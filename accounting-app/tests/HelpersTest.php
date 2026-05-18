<?php
require __DIR__ . '/bootstrap.php';

use Accounting\Infrastructure\Helpers;

echo "\n=== toVnWords Tests ===\n";
assertEq('Không đồng', Helpers::toVnWords(0), 'Zero');
assertEq('Một đồng', Helpers::toVnWords(1), 'One');
assertEq('Mười đồng', Helpers::toVnWords(10), 'Ten');
assertEq('Mười một đồng', Helpers::toVnWords(11), 'Eleven');
assertEq('Mười lăm đồng', Helpers::toVnWords(15), 'Fifteen');
assertEq('Hai mươi đồng', Helpers::toVnWords(20), 'Twenty');
assertEq('Hai mươi mốt đồng', Helpers::toVnWords(21), 'Twenty one');
assertEq('Hai mươi tư đồng', Helpers::toVnWords(24), 'Twenty four');
assertEq('Hai mươi lăm đồng', Helpers::toVnWords(25), 'Twenty five');
assertEq('Một trăm đồng', Helpers::toVnWords(100), 'One hundred');
assertEq('Một trăm linh một đồng', Helpers::toVnWords(101), 'One hundred one');
assertEq('Một trăm mười đồng', Helpers::toVnWords(110), 'One hundred ten');
assertEq('Một trăm mười một đồng', Helpers::toVnWords(111), 'One hundred eleven');
assertEq('Một nghìn đồng', Helpers::toVnWords(1000), 'One thousand');
assertEq('Một nghìn một trăm đồng', Helpers::toVnWords(1100), 'One thousand one hundred');
assertEq('Một triệu đồng', Helpers::toVnWords(1000000), 'One million');
assertEq('Một tỷ đồng', Helpers::toVnWords(1000000000), 'One billion');
assertEq('Một tỷ một trăm triệu đồng', Helpers::toVnWords(1100000000), '1.1 billion');
assertEq('Mười hai triệu ba trăm bốn mươi lăm nghìn sáu trăm bảy mươi tám đồng', Helpers::toVnWords(12345678), '12,345,678');
assertEq('Một trăm linh năm nghìn đồng', Helpers::toVnWords(105000), '105,000');
assertEq('Âm một triệu đồng', Helpers::toVnWords(-1000000), 'Negative one million');
assertEq('Một nghìn hai trăm ba mươi tư phẩy năm mươi sáu đồng', Helpers::toVnWords(1234.56), 'With decimals');

echo "\n=== fmt Tests ===\n";
assertEq('1.000.000', Helpers::fmt(1000000), 'Format millions');
assertEq('1.234.567', Helpers::fmt(1234567), 'Format 1,234,567');
assertEq('0', Helpers::fmt(0), 'Format zero');
assertEq('1.000', Helpers::fmt(1000), 'Format thousands');
assertEq('1.234,57', Helpers::fmt(1234.567, 2), 'Format with 2 decimals');

echo "\n=== e (XSS escape) Tests ===\n";
assertEq('&lt;script&gt;', Helpers::e('<script>'), 'Escape script tag');
assertEq('&amp; &amp;', Helpers::e('& &'), 'Escape ampersand');
assertEq('&quot;hello&quot;', Helpers::e('"hello"'), 'Escape quotes');
assertEq('', Helpers::e(null), 'Null returns empty');
assertEq('abc', Helpers::e('abc'), 'Plain text unchanged');

echo "\n=== isValidAccountCode Tests ===\n";
assertTrue(Helpers::isValidAccountCode('111'), '3-digit code valid');
assertTrue(Helpers::isValidAccountCode('3331'), '4-digit (level 2) valid');
assertTrue(Helpers::isValidAccountCode('33311'), '5-digit valid');
assertTrue(Helpers::isValidAccountCode('333111'), '6-digit valid');
assertTrue(Helpers::isValidAccountCode('41111'), 'Sub-sub account valid');
assertTrue(!Helpers::isValidAccountCode('11'), '2-digit invalid');
assertTrue(!Helpers::isValidAccountCode(''), 'Empty invalid');
assertTrue(!Helpers::isValidAccountCode('abc'), 'Letters invalid');
assertTrue(!Helpers::isValidAccountCode('11111111'), '8-digit invalid (too long)');
assertTrue(!Helpers::isValidAccountCode('11a'), 'Mixed invalid');

results();
