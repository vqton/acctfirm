<?php
// Seed dữ liệu thuế suất mặc định vào bảng tax_rates
// Mức thuế GTGT: 0%, 5%, 8%, 10% (Luật VAT 48/2024 + NQ 204/2025)
// Bao gồm các loại thuế khác: TTĐB, nhập khẩu, bảo vệ môi trường, tài nguyên
return function (PDO $pdo) {
    $pdo->exec("INSERT IGNORE INTO tax_rates (id, code, name, rate, tax_type, status) VALUES
        ('vat_0',      'VAT00', 'Thuế GTGT 0% (xuất khẩu)',                      0,  'vat',               1),
        ('vat_5',      'VAT05', 'Thuế GTGT 5% (hàng thiết yếu)',                  5,  'vat',               1),
        ('vat_8',      'VAT08', 'Thuế GTGT 8% (giảm từ 10% - NQ 204/2025)',       8,  'vat',               1),
        ('vat_10',     'VAT10', 'Thuế GTGT 10% (tiêu chuẩn)',                    10,  'vat',               1),
        ('excise_50',  'TTDB50', 'Thuế TTĐB 50% (rượu, bia, thuốc lá)',         50,  'excise',            1),
        ('excise_65',  'TTDB65', 'Thuế TTĐB 65%',                               65,  'excise',            1),
        ('import_std', 'NK_STD', 'Thuế nhập khẩu tiêu chuẩn',                   10,  'import_duty',       1),
        ('env_std',    'BVMT',   'Thuế bảo vệ môi trường',                      10,  'environment',       1),
        ('resource',   'TN',     'Thuế tài nguyên',                             5,  'natural_resource',  1)");
};