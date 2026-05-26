<?php
// NGHIEP VU: Bo sung cac truong tinh luong cho bang nhan vien
//
// Cac truong bo sung:
//   - insurance_salary: Muc luong tham gia BHXH (co the khac luong co ban)
//   - bank_account: So tai khoan ngan hang nhan luong
//   - bank_name: Ten ngan hang
//   - tax_code: Ma so thue TNCN ca nhan
//   - dependent_count: So nguoi phu thuoc (giam tru gia canh)
//   - region: Vung luong toi thieu (I, II, III, IV) — theo Nghi dinh 293/2025/ND-CP
//   - contract_type: Loai hop dong lao dong
//     (indefinite: Khong xac dinh thoi han, definite_12: Xac dinh >=12 thang,
//      definite_under_12: Xac dinh <12 thang, seasonal: Thoi vu, trial: Thu viec)
//
// Anh huong:
//   - Tinh BHXH/BHYT/BHTN: insurance_salary quyet dinh muc dong BH
//   - Tinh thue TNCN: tax_code + dependent_count -> giam tru gia canh
//   - Chi luong qua NH: bank_account + bank_name
//   - Luong toi thieu vung: region quyet dinh san BHXH toi thieu
return function (PDO $pdo) {
    $pdo->exec('ALTER TABLE employees ADD COLUMN IF NOT EXISTS insurance_salary DECIMAL(15,2) DEFAULT NULL AFTER email');
    $pdo->exec('ALTER TABLE employees ADD COLUMN IF NOT EXISTS bank_account VARCHAR(50) DEFAULT NULL AFTER insurance_salary');
    $pdo->exec('ALTER TABLE employees ADD COLUMN IF NOT EXISTS bank_name VARCHAR(255) DEFAULT NULL AFTER bank_account');
    $pdo->exec('ALTER TABLE employees ADD COLUMN IF NOT EXISTS tax_code VARCHAR(20) DEFAULT NULL AFTER bank_name');
    $pdo->exec('ALTER TABLE employees ADD COLUMN IF NOT EXISTS dependent_count INT NOT NULL DEFAULT 0 AFTER tax_code');
    $pdo->exec('ALTER TABLE employees ADD COLUMN IF NOT EXISTS region VARCHAR(10) DEFAULT NULL AFTER dependent_count');
    $pdo->exec('ALTER TABLE employees ADD COLUMN IF NOT EXISTS contract_type VARCHAR(30) DEFAULT "indefinite" AFTER region');
};
