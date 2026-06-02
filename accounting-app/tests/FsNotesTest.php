<?php
require __DIR__ . '/bootstrap.php';

use Accounting\Domain\Model\Bc09Config;
use Accounting\Domain\Model\Bc09Data;
use Accounting\Domain\Repository\Bc09RepositoryInterface;
use Accounting\Domain\Service\FsNotesService;
use Accounting\Infrastructure\Repository\PDOBc09Repository;

// Khởi tạo PDO từ DI container nếu có
$pdo = $GLOBALS['container']['pdo'] ?? null;
if (!$pdo) {
    // Tạo PDO từ config
    $dbConfig = require __DIR__ . '/../config/database.php';
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'], $dbConfig['password'], $dbConfig['options']
    );
}

// Đảm bảo tables tồn tại
$pdo->exec("CREATE TABLE IF NOT EXISTS bc09_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_code VARCHAR(10) NOT NULL,
    indicator_code VARCHAR(30) NOT NULL,
    indicator_name VARCHAR(255) NOT NULL,
    formula_expression TEXT,
    account_codes VARCHAR(500),
    is_auto_calc BOOLEAN DEFAULT TRUE,
    is_required BOOLEAN DEFAULT TRUE,
    parent_code VARCHAR(30) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    UNIQUE KEY uq_section_indicator (section_code, indicator_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS bc09_data (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_id INT UNSIGNED NOT NULL,
    section_code VARCHAR(10) NOT NULL,
    indicator_code VARCHAR(30) NOT NULL,
    year_start DECIMAL(15,2) DEFAULT 0,
    year_end DECIMAL(15,2) DEFAULT 0,
    note_text TEXT,
    is_manual BOOLEAN DEFAULT FALSE,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_period_indicator (period_id, indicator_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Clean test data
$pdo->exec("DELETE FROM bc09_config");
$pdo->exec("DELETE FROM bc09_data");

// Test 1: Bc09Config model
$config = new Bc09Config(1, 'V', 'V.01', 'Tiền và tương đương tiền', '', '1111,1112,1113', true, true, null, 10);
assertEq($config->getSectionCode(), 'V', 'Bc09Config sectionCode');
assertEq($config->getIndicatorCode(), 'V.01', 'Bc09Config indicatorCode');
assertEq($config->isAutoCalc(), true, 'Bc09Config isAutoCalc');
assertEq($config->isRequired(), true, 'Bc09Config isRequired');
assertTrue(count($config->getAccountCodeList()) === 3, 'Bc09Config getAccountCodeList count');

// Test 2: Bc09Data model
$data = new Bc09Data(1, 1, 'V', 'V.01', 100000, 200000, 'Ghi chú', false, null);
assertEq($data->getPeriodId(), 1, 'Bc09Data periodId');
assertEq($data->getYearStart(), 100000.0, 'Bc09Data yearStart');
assertEq($data->getYearEnd(), 200000.0, 'Bc09Data yearEnd');
assertEq($data->getNoteText(), 'Ghi chú', 'Bc09Data noteText');

// Test 3: PDOBc09Repository — save and read config
$repo = new PDOBc09Repository($pdo);

// Seed one config row
$pdo->prepare(
    'INSERT INTO bc09_config (section_code, indicator_code, indicator_name, account_codes, is_auto_calc, sort_order)
     VALUES (?, ?, ?, ?, ?, ?)'
)->execute(['V', 'V.01', 'Tiền và tương đương tiền', '1111,1112,1113', 1, 10]);

$configs = $repo->getConfig();
assertTrue(count($configs) >= 1, 'PDOBc09Repository getConfig returns array');
assertTrue($configs[0] instanceof Bc09Config, 'getConfig returns Bc09Config objects');

// Test 4: getSection
$sectionV = $repo->getSection('V');
assertTrue(count($sectionV) >= 1, 'getSection V returns items');

// Test 5: getConfigByIndicator
$found = $repo->getConfigByIndicator('V.01');
assertTrue($found instanceof Bc09Config, 'getConfigByIndicator V.01 returns Bc09Config');
assertEq($found->getIndicatorName(), 'Tiền và tương đương tiền', 'getConfigByIndicator name');

// Test 6: saveData and getData
$repo->saveData(1, 'V', 'V.01', 100000, 200000, null, false, null);
$savedData = $repo->getData(1);
assertTrue(count($savedData) >= 1, 'getData returns array');
assertTrue($savedData[0] instanceof Bc09Data, 'getData returns Bc09Data objects');
assertNear($savedData[0]->getYearStart(), 100000, 'Saved yearStart');
assertNear($savedData[0]->getYearEnd(), 200000, 'Saved yearEnd');

// Test 7: deleteDataForPeriod
$repo->deleteDataForPeriod(1);
$afterDelete = $repo->getData(1);
assertEq(count($afterDelete), 0, 'deleteDataForPeriod clears data');

// Test 8: FsNotesService constructor
$accountRepo = $GLOBALS['container']['accountRepository'] ?? null;
$periodService = $GLOBALS['container']['periodService'] ?? null;

if ($accountRepo && $periodService) {
    $fsNotes = new FsNotesService($repo, $accountRepo, $periodService, $pdo);
    assertTrue($fsNotes instanceof FsNotesService, 'FsNotesService constructor');

    // Test 9: getPolicyTemplates
    $policies = $fsNotes->getPolicyTemplates();
    assertTrue(count($policies) >= 22, 'getPolicyTemplates returns 22+ policies');
    assertEq($policies[0]['code'], 'IV.01', 'First policy code is IV.01');
    assertEq($policies[0]['name'], 'Cơ sở lập báo cáo tài chính', 'First policy name');

    // Test 10: generate (if periods exist)
    $periods = $periodService->getPeriods();
    if (count($periods) > 0) {
        $periodId = $periods[0]['id'];
        $result = $fsNotes->generate($periodId);
        assertTrue(is_array($result), 'generate returns array');
    }

    // Test 11: getReport
    if (count($periods) > 0) {
        $periodId = $periods[0]['id'];
        $report = $fsNotes->getReport($periodId);
        assertTrue(isset($report['sections']), 'getReport has sections');
        assertTrue(isset($report['period_id']), 'getReport has period_id');
    }

    // Test 12: validate
    if (count($periods) > 0) {
        $periodId = $periods[0]['id'];
        $validation = $fsNotes->validate($periodId);
        assertTrue(isset($validation['errors']), 'validate has errors key');
        assertTrue(isset($validation['warnings']), 'validate has warnings key');
        assertTrue(isset($validation['total_checks']), 'validate has total_checks key');
    }
} else {
    echo "SKIP: FsNotesService integration tests (no accountRepo/periodService in container)\n";
}

results();
