<?php

// === TIỀN LƯƠNG (TK 334, 338) ===
// Kỳ lương
$router->get('/api/payroll/periods', function() use ($c) { $c['PayrollController']->listPeriods(); });
$router->post('/api/payroll/periods', function() use ($c) { $c['PayrollController']->createPeriod(); });
$router->get('/api/payroll/periods/open', function() use ($c) { $c['PayrollController']->listOpenPeriods(); });
$router->get('/api/payroll/periods/:id', function($id) use ($c) { $c['PayrollController']->getPeriod($id); });
$router->post('/api/payroll/periods/:id/close', function($id) use ($c) { $c['PayrollController']->closePeriod($id); });

// Bảng lương
$router->get('/api/payroll/entries', function() use ($c) { $c['PayrollController']->listEntries(); });
$router->get('/api/payroll/entries/pending', function() use ($c) { $c['PayrollController']->listPendingEntries(); });
$router->get('/api/payroll/entries/:id', function($id) use ($c) { $c['PayrollController']->getEntry($id); });
$router->get('/api/payroll/entries/:id/details', function($id) use ($c) { $c['PayrollController']->getEntryDetails($id); });

// Tính lương
$router->post('/api/payroll/process', function() use ($c) { $c['PayrollController']->processPayroll(); });
$router->get('/api/payroll/calculate/insurance', function() use ($c) { $c['PayrollController']->calculateInsurance(); });
$router->get('/api/payroll/calculate/tax', function() use ($c) { $c['PayrollController']->calculateTax(); });
$router->get('/api/payroll/calculate/employee-pay', function() use ($c) { $c['PayrollController']->calculateEmployeePay(); });

// Duyệt/Post/Điều chỉnh
$router->post('/api/payroll/entries/:id/approve', function($id) use ($c) { $c['PayrollController']->approveEntry($id); });
$router->post('/api/payroll/entries/:id/post', function($id) use ($c) { $c['PayrollController']->postEntry($id); });
$router->post('/api/payroll/entries/:id/adjust', function($id) use ($c) { $c['PayrollController']->adjustEntry($id); });

// Nhân viên
$router->get('/api/payroll/employees', function() use ($c) { $c['PayrollController']->listPayrollEmployees(); });

// Views API
$router->get('/api/payroll/views/employees', function() { require __DIR__ . '/../public/views/payroll_employees.php'; });
$router->get('/api/payroll/views/periods', function() { require __DIR__ . '/../public/views/payroll_periods.php'; });
$router->get('/api/payroll/views/entries', function() { require __DIR__ . '/../public/views/payroll_entries.php'; });
$router->get('/api/payroll/views/entries/:id', function($id) { require __DIR__ . '/../public/views/payroll_entry_detail.php'; });
