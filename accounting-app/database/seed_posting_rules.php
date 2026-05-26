<?php
// Seed common posting rules for Vietnamese enterprise (Circular 99)
return function (PDO $pdo, string $createdBy = 'system') {
    $insert = $pdo->prepare(
        'INSERT IGNORE INTO posting_rules (debit_account_code, credit_account_code, module, severity, max_amount, created_by)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $rules = [
        // Cash
        ['111', '511', 'sales', 'block', null],
        ['111', '33311', 'sales', 'block', null],
        ['111', '131', 'ar', 'block', null],
        ['112', '511', 'sales', 'block', null],
        ['112', '33311', 'sales', 'block', null],
        ['112', '131', 'ar', 'block', null],

        // Sales on credit
        ['131', '511', 'sales', 'block', null],
        ['131', '33311', 'sales', 'block', null],

        // Purchase
        ['152', '331', 'purchase', 'block', null],
        ['153', '331', 'purchase', 'block', null],
        ['156', '331', 'purchase', 'block', null],
        ['133', '331', 'purchase', 'block', null],
        ['152', '111', 'purchase', 'block', null],
        ['153', '111', 'purchase', 'block', null],
        ['156', '111', 'purchase', 'block', null],
        ['133', '111', 'purchase', 'block', null],
        ['152', '112', 'purchase', 'block', null],
        ['153', '112', 'purchase', 'block', null],
        ['156', '112', 'purchase', 'block', null],
        ['133', '112', 'purchase', 'block', null],

        // Supplier payment
        ['331', '111', 'ap', 'block', null],
        ['331', '112', 'ap', 'block', null],

        // Customer receipt
        ['111', '131', 'ar', 'block', null],
        ['112', '131', 'ar', 'block', null],

        // COGS
        ['632', '152', 'inventory', 'block', null],
        ['632', '153', 'inventory', 'block', null],
        ['632', '155', 'inventory', 'block', null],
        ['632', '156', 'inventory', 'block', null],
        ['632', '157', 'inventory', 'block', null],

        // Finished goods
        ['155', '154', 'manufacturing', 'block', null],

        // WIP
        ['154', '152', 'manufacturing', 'block', null],
        ['154', '621', 'manufacturing', 'block', null],
        ['154', '622', 'manufacturing', 'block', null],
        ['154', '627', 'manufacturing', 'block', null],

        // Depreciation
        ['641', '214', 'fa', 'block', null],
        ['642', '214', 'fa', 'block', null],
        ['627', '214', 'fa', 'block', null],
        ['154', '214', 'fa', 'block', null],

        // Salary
        ['641', '334', 'payroll', 'block', null],
        ['642', '334', 'payroll', 'block', null],
        ['622', '334', 'payroll', 'block', null],
        ['334', '111', 'payroll', 'block', null],
        ['334', '112', 'payroll', 'block', null],

        // Social insurance
        ['641', '3383', 'payroll', 'block', null],
        ['642', '3383', 'payroll', 'block', null],
        ['622', '3383', 'payroll', 'block', null],
        ['334', '3383', 'payroll', 'block', null],

        // VAT
        ['3331', '133', 'vat', 'block', null],

        // Revenue closing
        ['511', '911', 'close', 'block', null],
        ['515', '911', 'close', 'block', null],
        ['711', '911', 'close', 'block', null],

        // Expense closing
        ['911', '632', 'close', 'block', null],
        ['911', '635', 'close', 'block', null],
        ['911', '641', 'close', 'block', null],
        ['911', '642', 'close', 'block', null],
        ['911', '811', 'close', 'block', null],
        ['911', '821', 'close', 'block', null],

        // P&L transfer
        ['911', '421', 'close', 'block', null],
        ['421', '911', 'close', 'block', null],

        // FA acquisition
        ['211', '331', 'fa', 'block', null],
        ['211', '111', 'fa', 'block', null],
        ['211', '112', 'fa', 'block', null],
        ['133', '331', 'fa', 'block', null],
        ['133', '111', 'fa', 'block', null],
        ['133', '112', 'fa', 'block', null],

        // Prepayment
        ['242', '111', 'general', 'block', null],
        ['242', '112', 'general', 'block', null],
        ['641', '242', 'general', 'block', null],
        ['642', '242', 'general', 'block', null],
        ['627', '242', 'general', 'block', null],

        // Accruals
        ['641', '335', 'general', 'block', null],
        ['642', '335', 'general', 'block', null],
        ['335', '331', 'general', 'block', null],
        ['335', '111', 'general', 'block', null],
        ['335', '112', 'general', 'block', null],

        // Inventory transfer
        ['152', '152', 'inventory', 'block', null],
        ['156', '156', 'inventory', 'block', null],
    ];

    foreach ($rules as $r) {
        $insert->execute([$r[0], $r[1], $r[2] ?? null, $r[3] ?? 'warn', $r[4] ?? null, $createdBy]);
    }
};
