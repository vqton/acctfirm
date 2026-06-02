<?php
namespace Accounting\Domain\Service;

// Dịch vụ hỗ trợ kê khai thuế GTGT
//
// Nghiệp vụ: Cuối kỳ, kế toán tổng hợp VAT đầu vào (TK 1331) và đầu ra (TK 33311)
// để lập tờ khai thuế GTGT (mẫu 01/GTGT). Dịch vụ này hỗ trợ chuẩn bị số liệu.
//
// Quy trình: prepareDeclaration → review → (finalise → export/submit)
//   - prepareDeclaration: Tự động tổng hợp VAT từ AP (đầu vào) và AR (đầu ra)
//   - finalise: Khóa tờ khai, không cho sửa
//   - getDetail: Chi tiết từng hóa đơn đầu vào/ra trong kỳ
//
// NGUỒN DỮ LIỆU:
//   - VAT đầu vào: ap_invoices (vat_amount), ledger_entries (1331)
//   - VAT đầu ra: ar_invoices (vat_amount), ledger_entries (33311)
//
// RỦI RO: Số liệu VAT trên AP/AR có thể khác với ledger_entries nếu
// bút toán post không đúng (không ghi nhận 1331/33311 đúng mức).
// Cần đối chiếu: SUM(vat_amount on invoices) vs SUM(ledger_entries 1331/33311)
//
class VatService
{
    private \PDO $pdo;
    private ?\Accounting\Domain\Contract\AuditLoggerInterface $auditLogger;

    public function __construct(\PDO $pdo, ?\Accounting\Domain\Contract\AuditLoggerInterface $auditLogger = null)
    {
        $this->pdo = $pdo;
        $this->auditLogger = $auditLogger;
    }

    //
    // QUÉT VAT KHÔNG ĐƯỢC KHẤU TRỪ: Kiểm tra hóa đơn đầu vào ≥ 5 triệu thanh toán bằng tiền mặt
    // Tuân thủ TT 69/2025 — khoản chi ≥ 5 triệu không qua ngân hàng thì VAT không được khấu trừ
    //
    // RỦI RO: Nếu kế toán khấu trừ VAT cho hóa đơn ≥ 5 triệu trả bằng tiền mặt,
    // cơ quan thuế sẽ truy thu + phạt. Cần phát hiện trước khi nộp tờ khai.
    //
    public function scanNonDeductibleVat(string $period): array
    {
        $periodStart = $period . '-01';
        $nextPeriod = date('Y-m', strtotime('+1 month', strtotime($periodStart)));
        $periodEnd = date('Y-m-d', strtotime('-1 day', strtotime($nextPeriod . '-01')));

        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT ai.id, ai.invoice_number, ai.invoice_date,
                    ai.net_amount, ai.vat_amount, ai.vat_rate,
                    (ai.net_amount + ai.vat_amount) as total_amount,
                    t.id as transaction_id, t.reference,
                    a.code as cash_account_code, a.name as cash_account_name
             FROM ap_invoices ai
             JOIN payment_allocations pa ON pa.invoice_id = ai.id AND pa.payment_type = 'ap'
             JOIN transactions t ON t.id = pa.transaction_id
             JOIN ledger_entries le ON le.transaction_id = t.id
             JOIN accounts a ON a.id = le.account_id
             WHERE ai.vat_amount > 0
               AND (ai.net_amount + ai.vat_amount) >= 5000000
               AND ai.invoice_date BETWEEN ? AND ?
               AND a.code LIKE '111%'
               AND le.is_debit = 0
             ORDER BY ai.invoice_date"
        );
        $stmt->execute([$periodStart, $periodEnd]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    //
    // ĐỐI CHIẾU TỜ KHAI VAT VỚI SỔ KẾ TOÁN: So sánh số liệu VAT giữa 3 nguồn
    // 1. Tờ khai VAT (vat_declarations) — từ AP/AR invoices
    // 2. Sổ kế toán (ledger_entries) — TK 1331 (đầu vào) và TK 33311 (đầu ra)
    // 3. Chênh lệch — cần điều chỉnh nếu > tolerance
    //
    // RỦI RO: Nếu chênh lệch > 500,000 VND, tờ khai có thể sai.
    // Cần kiểm tra bút toán ghi nhận VAT đã đúng chưa.
    //
    public function reconcileVatDeclaration(string $period): array
    {
        $periodStart = $period . '-01';
        $nextPeriod = date('Y-m', strtotime('+1 month', strtotime($periodStart)));
        $periodEnd = date('Y-m-d', strtotime('-1 day', strtotime($nextPeriod . '-01')));

        // Số liệu từ tờ khai
        $declStmt = $this->pdo->prepare(
            "SELECT total_vat_input, total_vat_output, vat_payable
             FROM vat_declarations WHERE period = ? ORDER BY created_at DESC LIMIT 1"
        );
        $declStmt->execute([$period]);
        $declData = $declStmt->fetch(\PDO::FETCH_ASSOC);

        // Số liệu từ ledger_entries — TK 1331 (VAT đầu vào được khấu trừ)
        $glInput = $this->pdo->prepare(
            "SELECT COALESCE(SUM(le.amount), 0)
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN accounts a ON a.id = le.account_id
             WHERE a.code = '1331' AND t.status = 'posted'
             AND t.transaction_date BETWEEN ? AND ?
             AND le.is_debit = 1"
        );
        $glInput->execute([$periodStart, $periodEnd]);
        $totalGlInput = (float)$glInput->fetchColumn();

        // Số liệu từ ledger_entries — TK 33311 (VAT đầu ra phải nộp)
        $glOutput = $this->pdo->prepare(
            "SELECT COALESCE(SUM(le.amount), 0)
             FROM ledger_entries le
             JOIN transactions t ON t.id = le.transaction_id
             JOIN accounts a ON a.id = le.account_id
             WHERE a.code = '33311' AND t.status = 'posted'
             AND t.transaction_date BETWEEN ? AND ?
             AND le.is_debit = 0"
        );
        $glOutput->execute([$periodStart, $periodEnd]);
        $totalGlOutput = (float)$glOutput->fetchColumn();

        $declInput = $declData ? (float)$declData['total_vat_input'] : 0;
        $declOutput = $declData ? (float)$declData['total_vat_output'] : 0;

        return [
            'period' => $period,
            'declaration' => [
                'vat_input' => $declInput,
                'vat_output' => $declOutput,
                'vat_payable' => $declData ? (float)$declData['vat_payable'] : 0,
            ],
            'general_ledger' => [
                'vat_input_1331' => $totalGlInput,
                'vat_output_33311' => $totalGlOutput,
                'vat_payable' => $totalGlOutput - $totalGlInput,
            ],
            'difference' => [
                'vat_input' => round($declInput - $totalGlInput, 0),
                'vat_output' => round($declOutput - $totalGlOutput, 0),
                'vat_payable' => round(($declOutput - $declInput) - ($totalGlOutput - $totalGlInput), 0),
            ],
            'has_mismatch' => abs($declInput - $totalGlInput) > 500
                || abs($declOutput - $totalGlOutput) > 500,
            'tolerance' => 500,
        ];
    }

    // Chuẩn bị tờ khai VAT — tổng hợp số liệu từ AP/AR invoices và ledger_entries
    public function prepareDeclaration(string $period, string $createdBy): array
    {
        $periodStart = $period . '-01';
        $nextPeriod = date('Y-m', strtotime('+1 month', strtotime($periodStart)));
        $periodEnd = date('Y-m-d', strtotime('-1 day', strtotime($nextPeriod . '-01')));

        // VAT đầu vào (1331) từ AP
        $inputStmt = $this->pdo->prepare(
            "SELECT COUNT(*) as invoice_count, COALESCE(SUM(vat_amount), 0) as total_vat,
                    GROUP_CONCAT(CONCAT(ai.invoice_number, '|', ai.vat_amount, '|', ai.vat_rate, '|', COALESCE(s.name, ai.supplier_id), '|', ai.invoice_date) SEPARATOR ';') as detail
             FROM ap_invoices ai
             LEFT JOIN suppliers s ON s.id = ai.supplier_id
             WHERE ai.invoice_date BETWEEN ? AND ? AND ai.vat_amount > 0"
        );
        $inputStmt->execute([$periodStart, $periodEnd]);
        $inputData = $inputStmt->fetch(\PDO::FETCH_ASSOC);

        // VAT đầu ra (33311) từ AR
        $outputStmt = $this->pdo->prepare(
            "SELECT COUNT(*) as invoice_count, COALESCE(SUM(vat_amount), 0) as total_vat,
                    GROUP_CONCAT(CONCAT(ari.invoice_number, '|', ari.vat_amount, '|', ari.vat_rate, '|', COALESCE(c.name, ari.customer_id), '|', ari.invoice_date) SEPARATOR ';') as detail
             FROM ar_invoices ari
             LEFT JOIN customers c ON c.id = ari.customer_id
             WHERE ari.invoice_date BETWEEN ? AND ? AND ari.vat_amount > 0"
        );
        $outputStmt->execute([$periodStart, $periodEnd]);
        $outputData = $outputStmt->fetch(\PDO::FETCH_ASSOC);

        $totalInput = (float)$inputData['total_vat'];
        $totalOutput = (float)$outputData['total_vat'];
        $payable = $totalOutput - $totalInput;

        // Resolve actual declaration ID before INSERT (handle ON DUPLICATE KEY UPDATE)
        $stmt = $this->pdo->prepare("SELECT id FROM vat_declarations WHERE period = ?");
        $stmt->execute([$period]);
        $existingId = $stmt->fetchColumn();
        $id = $existingId ?: uniqid('vat_');

        $this->pdo->prepare(
            "INSERT INTO vat_declarations (id, period, total_vat_input, total_vat_output, vat_payable, invoice_count_input, invoice_count_output, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', ?)
             ON DUPLICATE KEY UPDATE total_vat_input=VALUES(total_vat_input), total_vat_output=VALUES(total_vat_output),
             vat_payable=VALUES(vat_payable), invoice_count_input=VALUES(invoice_count_input), invoice_count_output=VALUES(invoice_count_output),
             status=IF(status='finalised', status, 'draft')"
        )->execute([$id, $period, $totalInput, $totalOutput, $payable,
            (int)$inputData['invoice_count'], (int)$outputData['invoice_count'], $createdBy]);

        $this->auditLogger?->log('vat.prepare', 'vat_declaration', $id,
            null, ['period' => $period, 'vat_payable' => $payable], $createdBy);

        // Lưu chi tiết (xóa cũ rồi insert lại)
        $this->pdo->prepare("DELETE FROM vat_declaration_details WHERE declaration_id = ?")->execute([$id]);
        $detailStmt = $this->pdo->prepare(
            "INSERT INTO vat_declaration_details (id, declaration_id, line_type, invoice_ref, supplier_or_customer, invoice_date, gross_amount, vat_amount, vat_rate, source_table, source_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if ($inputData['detail']) {
            foreach (explode(';', $inputData['detail']) as $part) {
                $fields = explode('|', $part);
                if (count($fields) >= 5) {
                    $detailStmt->execute([uniqid('vdet_'), $id, 'input', $fields[0], $fields[3], $fields[4], 0, $fields[1], $fields[2], 'ap_invoices', $fields[0]]);
                }
            }
        }
        if ($outputData['detail']) {
            foreach (explode(';', $outputData['detail']) as $part) {
                $fields = explode('|', $part);
                if (count($fields) >= 5) {
                    $detailStmt->execute([uniqid('vdet_'), $id, 'output', $fields[0], $fields[3], $fields[4], 0, $fields[1], $fields[2], 'ar_invoices', $fields[0]]);
                }
            }
        }

        return $this->getDeclaration($id);
    }

    public function getDeclaration(string $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM vat_declarations WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['total_vat_input'] = (float)$row['total_vat_input'];
        $row['total_vat_output'] = (float)$row['total_vat_output'];
        $row['vat_payable'] = (float)$row['vat_payable'];
        $row['details'] = $this->getDetails($id);
        return $row;
    }

    public function getDeclarations(): array
    {
        $rows = $this->pdo->query("SELECT * FROM vat_declarations ORDER BY period DESC LIMIT 50")->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => [
            'id' => $r['id'], 'period' => $r['period'], 'status' => $r['status'],
            'total_vat_input' => (float)$r['total_vat_input'],
            'total_vat_output' => (float)$r['total_vat_output'],
            'vat_payable' => (float)$r['vat_payable'],
            'invoice_count_input' => (int)$r['invoice_count_input'],
            'invoice_count_output' => (int)$r['invoice_count_output'],
            'created_at' => $r['created_at'],
        ], $rows);
    }

    public function getDetails(string $declarationId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM vat_declaration_details WHERE declaration_id = ? ORDER BY line_type, invoice_date");
        $stmt->execute([$declarationId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function finalise(string $id): array
    {
        // Load declaration first to get period info
        $decl = $this->getDeclaration($id);
        if (!$decl) throw new \RuntimeException('Không tìm thấy tờ khai VAT.');

        // Kiểm tra kỳ kế toán đang mở
        $period = $decl['period'] ?? '';
        if ($period && !PeriodService::isPeriodOpen($period . '-15', $this->pdo)) {
            throw new \RuntimeException("Kỳ kế toán {$period} đã đóng. Không thể khóa tờ khai.");
        }

        $stmt = $this->pdo->prepare("UPDATE vat_declarations SET status = 'finalised' WHERE id = ? AND status IN ('draft','approved')");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            throw new \RuntimeException('Không thể khóa tờ khai. Tờ khai đã được khóa trước đó.');
        }
        $this->auditLogger?->log('vat.finalise', 'vat_declaration', $id,
            $decl, ['status' => 'finalised'], 'system');
        return $this->getDeclaration($id);
    }

    //
    // 4-EYES APPROVAL: Tax Accountant prepare → Chief Accountant approve
    //
    public function approveDeclaration(string $id, string $approvedBy): array
    {
        $decl = $this->getDeclaration($id);
        if (!$decl) throw new \RuntimeException('Không tìm thấy tờ khai VAT.');
        if ($decl['status'] !== 'draft') {
            throw new \RuntimeException("Tờ khai ở trạng thái '{$decl['status']}', không thể phê duyệt.");
        }

        $this->pdo->prepare(
            "UPDATE vat_declarations SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'draft'"
        )->execute([$approvedBy, $id]);

        $this->auditLogger?->log('vat.approve', 'vat_declaration', $id,
            $decl, ['status' => 'approved', 'approved_by' => $approvedBy], $approvedBy);

        return $this->getDeclaration($id);
    }

    public function rejectDeclaration(string $id, string $reason, string $rejectedBy): array
    {
        $decl = $this->getDeclaration($id);
        if (!$decl) throw new \RuntimeException('Không tìm thấy tờ khai VAT.');
        if ($decl['status'] !== 'draft') {
            throw new \RuntimeException("Tờ khai ở trạng thái '{$decl['status']}', không thể từ chối.");
        }

        $this->pdo->prepare(
            "UPDATE vat_declarations SET status = 'draft', rejection_reason = ? WHERE id = ? AND status = 'draft'"
        )->execute([$reason, $id]);

        $this->auditLogger?->log('vat.reject', 'vat_declaration', $id,
            $decl, ['reason' => $reason, 'rejected_by' => $rejectedBy], $rejectedBy);

        return $this->getDeclaration($id);
    }

    //
    // 03/KHBS — KHAI BỔ SUNG
    //
    public function createAdjustment(string $originalPeriod, array $adjustedData, string $createdBy): array
    {
        // Validate original declaration
        $origStmt = $this->pdo->prepare(
            "SELECT * FROM vat_declarations WHERE period = ? ORDER BY created_at DESC LIMIT 1"
        );
        $origStmt->execute([$originalPeriod]);
        $original = $origStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$original) throw new \RuntimeException("Không tìm thấy tờ khai gốc cho kỳ {$originalPeriod}.");

        $id = uniqid('khbs_');

        $this->pdo->prepare(
            "INSERT INTO vat_declarations
             (id, period, total_vat_input, total_vat_output, vat_payable,
              invoice_count_input, invoice_count_output, status, created_by,
              original_declaration_id, adjustment_type, rejection_reason)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'draft_adjustment', ?, ?, '03/KHBS', ?)"
        )->execute([
            $id, $originalPeriod,
            $adjustedData['total_vat_input'] ?? $original['total_vat_input'],
            $adjustedData['total_vat_output'] ?? $original['total_vat_output'],
            $adjustedData['vat_payable'] ?? ($original['total_vat_output'] - $original['total_vat_input']),
            $adjustedData['invoice_count_input'] ?? $original['invoice_count_input'],
            $adjustedData['invoice_count_output'] ?? $original['invoice_count_output'],
            $createdBy,
            $original['id'],
            $adjustedData['reason'] ?? 'Điều chỉnh bổ sung',
        ]);

        $this->auditLogger?->log('vat.adjustment', 'vat_declaration', $id,
            $original, ['period' => $originalPeriod, 'type' => '03/KHBS'], $createdBy);

        return $this->getDeclaration($id);
    }

    //
    // HTKK XML EXPORT — Xuất XML tờ khai 01/GTGT theo chuẩn cổng TĐT
    //
    public function exportHtkkXml(string $id): string
    {
        $engine = new VatDeclarationEngine($this->pdo);
        return $engine->exportToXml($id);
    }

    //
    // E-INVOICE VS DECLARATION RECONCILIATION REPORT
    //
    public function reconcileWithEInvoice(string $period): array
    {
        $periodStart = $period . '-01';
        $nextPeriod = date('Y-m', strtotime('+1 month', strtotime($periodStart)));
        $periodEnd = date('Y-m-d', strtotime('-1 day', strtotime($nextPeriod . '-01')));

        // Tổng VAT đầu ra từ e_invoices
        $einvOut = $this->pdo->prepare(
            "SELECT COALESCE(SUM(grand_total), 0) as total, COALESCE(SUM(total_vat), 0) as vat,
                    COUNT(*) as count FROM e_invoices
             WHERE status = 'published' AND issue_date BETWEEN ? AND ?"
        );
        $einvOut->execute([$periodStart, $periodEnd]);
        $einvData = $einvOut->fetch(\PDO::FETCH_ASSOC);

        // Tổng VAT đầu ra từ declaration
        $declStmt = $this->pdo->prepare(
            "SELECT total_vat_output, vat_payable, status
             FROM vat_declarations WHERE period = ? ORDER BY created_at DESC LIMIT 1"
        );
        $declStmt->execute([$period]);
        $declData = $declStmt->fetch(\PDO::FETCH_ASSOC);

        $diffOutput = ($declData ? (float)$declData['total_vat_output'] : 0) - (float)$einvData['vat'];

        return [
            'period' => $period,
            'e_invoice' => [
                'count' => (int)$einvData['count'],
                'total' => (float)$einvData['total'],
                'vat_output' => (float)$einvData['vat'],
            ],
            'declaration' => [
                'vat_output' => $declData ? (float)$declData['total_vat_output'] : 0,
                'status' => $declData['status'] ?? 'none',
            ],
            'difference' => $diffOutput,
            'has_mismatch' => abs($diffOutput) > 500,
            'note' => abs($diffOutput) > 500
                ? 'Chênh lệch > 500 — cần kiểm tra hóa đơn đã phát hành nhưng chưa hạch toán'
                : 'Khớp',
        ];
    }

    //
    // NON-DEDUCTIBLE VAT FLAGGED INVOICE LIST (có UI review)
    //
    public function getNonDeductibleInvoices(string $period): array
    {
        $rows = $this->scanNonDeductibleVat($period);
        return array_map(fn($r) => [
            'id' => $r['id'],
            'invoice_number' => $r['invoice_number'],
            'invoice_date' => $r['invoice_date'],
            'supplier' => $r['cash_account_name'],
            'net_amount' => (float)$r['net_amount'],
            'vat_amount' => (float)$r['vat_amount'],
            'total_amount' => (float)$r['total_amount'],
            'transaction_reference' => $r['reference'],
            'reason' => 'Thanh toán ≥ 5M không qua ngân hàng (TT 69/2025)',
            'status' => 'flagged',
        ], $rows);
    }

    //
    // INPUT VAT DEDUCTION CHECKLIST — 4 conditions per invoice
    //
    public function getInputVatChecklist(string $period): array
    {
        $invoices = [];
        $stmt = $this->pdo->prepare(
            "SELECT ai.id, ai.invoice_number, ai.invoice_date, ai.net_amount, ai.vat_amount,
                    ai.vat_rate, s.name as supplier_name, s.tax_code as supplier_tax_code
             FROM ap_invoices ai
             LEFT JOIN suppliers s ON s.id = ai.supplier_id
             WHERE ai.invoice_date BETWEEN ? AND ?
               AND (SELECT DATE_FORMAT(?, '%Y-%m-01')) <= ai.invoice_date
               AND (SELECT LAST_DAY(?) ) >= ai.invoice_date
               AND ai.vat_amount > 0
             LIMIT 200"
        );
        $periodStart = $period . '-01';
        $nextPeriod = date('Y-m', strtotime('+1 month', strtotime($periodStart)));
        $periodEnd = date('Y-m-d', strtotime('-1 day', strtotime($nextPeriod . '-01')));
        $stmt->execute([$periodStart, $periodEnd, $periodStart, $periodStart]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $id = $row['id'];
            // Condition 1: Valid invoice (has tax code)
            $cond1 = !empty($row['supplier_tax_code']);
            // Condition 2: >=5M has non-cash payment
            $total = (float)$row['net_amount'] + (float)$row['vat_amount'];
            $cond2 = $total < 5000000 || $this->hasNonCashPayment($id);
            // Condition 3: Used for taxable activity (default true — no contra evidence in ERP)
            $cond3 = true;
            // Condition 4: Valid tax code format (10 or 13 digits)
            $cond4 = preg_match('/^\d{10}(-\d{3})?$/', $row['supplier_tax_code'] ?? '');

            $deductible = $cond1 && $cond2 && $cond3 && $cond4;
            $invoices[] = [
                'id' => $id,
                'invoice_number' => $row['invoice_number'],
                'invoice_date' => $row['invoice_date'],
                'supplier' => $row['supplier_name'],
                'supplier_tax_code' => $row['supplier_tax_code'],
                'net_amount' => (float)$row['net_amount'],
                'vat_amount' => (float)$row['vat_amount'],
                'conditions' => [
                    'valid_invoice' => $cond1,
                    'non_cash_payment' => $cond2,
                    'taxable_activity' => $cond3,
                    'valid_tax_code' => $cond4,
                ],
                'deductible' => $deductible,
                'status' => $deductible ? 'OK' : 'FLAGGED',
            ];
        }
        return $invoices;
    }

    private function hasNonCashPayment(int $invoiceId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM payment_allocations pa
             JOIN transactions t ON t.id = pa.transaction_id
             JOIN ledger_entries le ON le.transaction_id = t.id
             JOIN accounts a ON a.id = le.account_id
             WHERE pa.invoice_id = ? AND pa.payment_type = 'ap'
             AND a.code NOT LIKE '111%'
             AND le.is_debit = 1"
        );
        $stmt->execute([$invoiceId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
