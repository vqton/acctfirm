<?php
//
// Test: CurrencyDisplayService + CurrencyController (R-11 Multi-Currency Display)
// Cover: listCurrencies, getRate, convertFromVnd, convertToVnd,
//        user preference get/set, format, formatDual, failure cases
//
require __DIR__ . '/bootstrap.php';

use Accounting\Domain\Service\CurrencyDisplayService;

$pdo = new PDO("mysql:host=127.0.0.1;dbname=accounting_db;charset=utf8mb4", "dev", "123456",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Self-seed: đảm bảo rates tồn tại (test khác có thể đã xóa)
$today = date('Y-m-d');
$seedRates = [
    ['USD', 'US Dollar', 25480.00], ['EUR', 'Euro', 27700.00],
    ['JPY', 'Japanese Yen', 162.50], ['GBP', 'British Pound', 32200.00],
    ['CNY', 'Chinese Yuan', 3510.00], ['SGD', 'Singapore Dollar', 18900.00],
    ['AUD', 'Australian Dollar', 16800.00],
];
foreach ($seedRates as $r) {
    $check = $pdo->prepare("SELECT id FROM exchange_rates WHERE currency_code = ?");
    $check->execute([$r[0]]);
    if (!$check->fetchColumn()) {
        $pdo->prepare("INSERT INTO exchange_rates (id, currency_code, currency_name, rate, rate_date) VALUES (?, ?, ?, ?, ?)")
            ->execute(['fx_' . $r[0] . '_testseed', $r[0], $r[1], $r[2], $today]);
    }
}

$svc = new CurrencyDisplayService($pdo);

// Helper: ensure user exists
function ensureUser(PDO $pdo, string $userId): void {
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
    $pdo->prepare("INSERT INTO users (id, username, password_hash, full_name, email, status, display_currency) VALUES (?, ?, '', 'Test User', 'test@test.com', 'active', 'VND')")
        ->execute([$userId, $userId]);
}

// === TEST 1: listCurrencies trả về ≥ 7 currencies (VND + 6 seeded) ===
$currencies = $svc->listCurrencies();
assertTrue(count($currencies) >= 7, 'Có ≥ 7 currencies');
$codes = array_column($currencies, 'code');
assertTrue(in_array('VND', $codes), 'Có VND (base)');
assertTrue(in_array('USD', $codes), 'Có USD');
assertTrue(in_array('EUR', $codes), 'Có EUR');
assertTrue(in_array('JPY', $codes), 'Có JPY');

// === TEST 2: VND luôn là base với rate = 1 ===
$vnd = $currencies[0]; // VND ở đầu
assertEq($vnd['code'], 'VND', 'VND là base currency');
assertEq($vnd['rate'], 1.0, 'VND rate = 1.0');

// === TEST 3: getRate USD trả về rate hợp lệ ===
$usdRate = $svc->getRate('USD');
assertTrue($usdRate !== null, 'USD rate tồn tại');
assertTrue($usdRate['rate'] > 20000 && $usdRate['rate'] < 30000,
    'USD rate trong khoảng 20K-30K (vd 25,480)');
assertTrue(isset($usdRate['rate_date']), 'Có rate_date');

// === TEST 4: getRate VND trả về rate = 1 ===
$vndRate = $svc->getRate('VND');
assertEq($vndRate['rate'], 1.0, 'VND rate = 1');
assertEq($vndRate['code'], 'VND', 'VND code');

// === TEST 5: getRate case-insensitive ===
$usdUpper = $svc->getRate('USD');
$usdLower = $svc->getRate('usd');
assertEq($usdUpper['rate'], $usdLower['rate'], 'getRate case-insensitive');

// === TEST 6: getRate currency không tồn tại → null ===
$xxxRate = $svc->getRate('XYZ_NONEXISTENT');
assertEq($xxxRate, null, 'Currency không tồn tại → null');

// === TEST 7: getRate với date cụ thể ===
$usdAtDate = $svc->getRate('USD', date('Y-m-d'));
assertTrue($usdAtDate !== null, 'USD @ today OK');

// === TEST 8: convertFromVnd VND → USD ===
$conv = $svc->convertFromVnd(1000000, 'USD');
assertTrue($conv !== null, 'Convert 1M VND → USD OK');
assertEq($conv['currency'], 'USD', 'Target = USD');
$expectedUsd = 1000000 / $usdRate['rate'];
assertNear($conv['amount'], $expectedUsd, 'Amount = 1M / rate');

// === TEST 9: convertFromVnd VND → VND = same ===
$convVnd = $svc->convertFromVnd(1000000, 'VND');
assertEq($convVnd['amount'], 1000000.0, 'VND → VND = same');
assertEq($convVnd['rate'], 1.0, 'rate = 1');

// === TEST 10: convertFromVnd → currency không có rate → null ===
$convXyz = $svc->convertFromVnd(1000000, 'XYZ_NONEXISTENT');
assertEq($convXyz, null, 'Currency không tồn tại → null');

// === TEST 11: convertToVnd USD → VND ===
$back = $svc->convertToVnd(100, 'USD');
assertEq($back['currency'], 'VND', 'Target = VND');
$expectedVnd = 100 * $usdRate['rate'];
assertNear($back['amount'], $expectedVnd, '100 USD = expected VND');

// === TEST 12: convertToVnd VND → VND = same ===
$backVnd = $svc->convertToVnd(1000000, 'VND');
assertEq($backVnd['amount'], 1000000.0, 'VND → VND = same');

// === TEST 13: convertToVnd currency không tồn tại → null ===
$backXyz = $svc->convertToVnd(100, 'XYZ_NONEXISTENT');
assertEq($backXyz, null, 'Currency không tồn tại → null');

// === TEST 14: setUserDisplayCurrency + getUserDisplayCurrency ===
ensureUser($pdo, 'test_cd_user_1');
$svc->setUserDisplayCurrency('test_cd_user_1', 'USD');
assertEq($svc->getUserDisplayCurrency('test_cd_user_1'), 'USD', 'Set USD → get USD');

// === TEST 15: setUserDisplayCurrency về VND ===
$svc->setUserDisplayCurrency('test_cd_user_1', 'VND');
assertEq($svc->getUserDisplayCurrency('test_cd_user_1'), 'VND', 'Set VND → get VND');

// === TEST 16: setUserDisplayCurrency case-insensitive ===
$svc->setUserDisplayCurrency('test_cd_user_1', 'eur');
assertEq($svc->getUserDisplayCurrency('test_cd_user_1'), 'EUR', 'Set eur → get EUR');

// === TEST 17: setUserDisplayCurrency currency không tồn tại → throw ===
try {
    $svc->setUserDisplayCurrency('test_cd_user_1', 'XYZ_NONEXISTENT');
    assertFalse(true, 'Phải throw cho currency không tồn tại');
} catch (\InvalidArgumentException $e) {
    assertTrue(str_contains($e->getMessage(), 'XYZ_NONEXISTENT') || str_contains($e->getMessage(), 'tỷ giá'),
        'Exception message: ' . $e->getMessage());
}

// === TEST 18: getUserDisplayCurrency user không tồn tại → default VND ===
$default = $svc->getUserDisplayCurrency('nonexistent_user_xyz');
assertEq($default, 'VND', 'User không tồn tại → default VND');

// === TEST 19: format cơ bản ===
$f1 = $svc->format(1000000, 'VND');
assertTrue(str_contains($f1, 'VND'), 'Format có VND');
assertTrue(str_contains($f1, '1'), 'Format có số');

$f2 = $svc->format(39.25, 'USD');
assertTrue(str_contains($f2, 'USD'), 'Format USD');
assertTrue(str_contains($f2, '39'), 'Format có số 39');

// === TEST 20: format JPY không có decimal ===
$fJpy = $svc->format(1234.56, 'JPY');
assertTrue(str_contains($fJpy, 'JPY'), 'JPY có ký hiệu');
// JPY sẽ làm tròn thành 1235
assertTrue(str_contains($fJpy, '1,235'), 'JPY không có decimal');

// === TEST 21: formatDual VND only (display = VND) ===
$dualVnd = $svc->formatDual(1000000, 'VND');
assertTrue(str_contains($dualVnd, '1,000,000'), 'Dual VND có số gốc');
assertTrue(!str_contains($dualVnd, '~'), 'Dual VND không có (~) vì display = VND');

// === TEST 22: formatDual với USD ===
$dualUsd = $svc->formatDual(1000000, 'USD');
assertTrue(str_contains($dualUsd, '1,000,000'), 'Dual USD có VND gốc');
assertTrue(str_contains($dualUsd, '~'), 'Dual USD có (~)');
assertTrue(str_contains($dualUsd, 'USD'), 'Dual USD có USD');

// === TEST 23: formatDual với currency không tồn tại → fallback VND only ===
$dualXyz = $svc->formatDual(1000000, 'XYZ_NONEXISTENT');
assertTrue(str_contains($dualXyz, '1,000,000'), 'Dual XYZ fallback có VND');
assertTrue(!str_contains($dualXyz, '~'), 'Dual XYZ không có (~) vì no rate');

// === TEST 24: listCurrencies có cấu trúc chuẩn ===
foreach ($currencies as $c) {
    assertTrue(isset($c['code']), "Currency có code");
    assertTrue(isset($c['name']), "Currency có name");
    assertTrue(isset($c['rate']), "Currency có rate");
    assertTrue(is_numeric($c['rate']), "Rate là số");
}

// === TEST 25: convertFromVnd với date cụ thể ===
$convDate = $svc->convertFromVnd(1000000, 'USD', date('Y-m-d'));
assertTrue($convDate !== null, 'Convert với date OK');
assertTrue(isset($convDate['rate_date']), 'Có rate_date trong result');

// === TEST 26: setUserDisplayCurrency nhiều lần (overwrite) ===
$svc->setUserDisplayCurrency('test_cd_user_1', 'JPY');
$svc->setUserDisplayCurrency('test_cd_user_1', 'EUR');
assertEq($svc->getUserDisplayCurrency('test_cd_user_1'), 'EUR', 'Overwrite OK');

// === TEST 27: convertToVnd với date cụ thể ===
$toVndDate = $svc->convertToVnd(100, 'USD', date('Y-m-d'));
assertTrue($toVndDate !== null, 'toVnd với date OK');

// === TEST 28: format số 0 ===
$fZero = $svc->format(0, 'USD');
assertTrue(str_contains($fZero, '0'), 'Format 0');
assertTrue(str_contains($fZero, 'USD'), 'Format 0 USD');

// === Cleanup ===
$pdo->prepare("DELETE FROM users WHERE id = 'test_cd_user_1'")->execute();

results();
