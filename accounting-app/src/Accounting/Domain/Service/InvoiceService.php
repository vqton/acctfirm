<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\EInvoiceGatewayInterface;
use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Infrastructure\EInvoice\XmlInvoiceBuilder;
use Accounting\Infrastructure\EInvoice\DigitalSignatureService;

//
// DỊCH VỤ HÓA ĐƠN ĐIỆN TỬ
//
// Cầu nối giữa giao dịch kế toán (AR/Doanh thu) và hệ thống hóa đơn điện tử.
// Quản lý vòng đời hóa đơn: draft → signed → published → (adjust|replace|cancel)
//
// NGHIỆP VỤ:
//   1. Nhận giao dịch bán hàng (AR invoice hoặc Transaction)
//   2. Xây dựng XML hóa đơn theo chuẩn TT 32/2025
//   3. Ký số bằng USB Token (PKCS#7)
//   4. Gửi lên T-VAN qua gateway
//   5. Lưu kết quả + audit trail
//
// LUỒNG:
//   createFromTransaction(transactionId)
//     → Xây dựng XML (XmlInvoiceBuilder)
//     → Ký số (DigitalSignatureService)
//     → Phát hành (EInvoiceGatewayInterface::publish)
//     → Lưu vào e_invoices table
//     → Audit log
//
// RỦI RO:
//   - Phát hành thất bại sau khi ký: XML đã ký lưu trong DB, cần retry queue
//   - Gateway timeout: Refund/cancel phải do người dùng kích hoạt
//   - Trùng số hóa đơn: VoucherService đảm bảo uniqueness qua SELECT FOR UPDATE
//
class InvoiceService
{
    private \PDO $pdo;
    private XmlInvoiceBuilder $xmlBuilder;
    private DigitalSignatureService $signer;
    private EInvoiceGatewayInterface $gateway;
    private ?AuditLoggerInterface $auditLogger;
    private ?VoucherService $voucherService;
    private ?AccountService $accountService;

    public function __construct(
        \PDO $pdo,
        XmlInvoiceBuilder $xmlBuilder,
        DigitalSignatureService $signer,
        EInvoiceGatewayInterface $gateway,
        ?AuditLoggerInterface $auditLogger = null,
        ?VoucherService $voucherService = null,
        ?AccountService $accountService = null,
    ) {
        $this->pdo = $pdo;
        $this->xmlBuilder = $xmlBuilder;
        $this->signer = $signer;
        $this->gateway = $gateway;
        $this->auditLogger = $auditLogger;
        $this->voucherService = $voucherService;
        $this->accountService = $accountService;
    }

    //
    // TẠO HÓA ĐƠN TỪ GIAO DỊCH KẾ TOÁN
    //
    // Input: transaction_id từ giao dịch bán hàng (Dr 131, 111 / Cr 511, 33311)
    // Output: array — thông tin hóa đơn đã phát hành
    //
    // 1. Đọc giao dịch từ DB
    // 2. Tách dòng (SKU từ chi tiết hoặc tự động)
    // 3. Xây dựng XML
    // 4. Ký số
    // 5. Phát hành qua gateway
    // 6. Lưu kết quả
    //
    public function createFromTransaction(string $transactionId, string $providerId = 'tvan_vnpt'): array
    {
        // Đọc giao dịch + khách hàng
        $txn = $this->loadTransaction($transactionId);
        if (!$txn) {
            throw new \RuntimeException("Không tìm thấy giao dịch: {$transactionId}");
        }
        if ($txn['status'] !== 'posted') {
            throw new \RuntimeException("Giao dịch chưa được post. Trạng thái hiện tại: {$txn['status']}");
        }

        // Kiểm tra đã có hóa đơn chưa (idempotent)
        $existing = $this->pdo->prepare("SELECT id FROM e_invoices WHERE transaction_id = ? AND status NOT IN ('cancelled', 'replaced')");
        $existing->execute([$transactionId]);
        if ($existing->fetch()) {
            throw new \RuntimeException("Hóa đơn đã được tạo từ giao dịch này.");
        }

        // Lấy thông tin seller (từ account/company config)
        $seller = $this->getSellerInfo();
        // Lấy thông tin buyer
        $buyer = $this->getBuyerInfo($txn);

        // Tách dòng từ giao dịch
        $items = $this->extractLineItems($txn);
        if (empty($items)) {
            throw new \RuntimeException('Không có dòng hàng hóa để xuất hóa đơn.');
        }

        // Tính tổng
        $totals = $this->calculateTotals($items);

        // Lấy thông tin provider (template code, serial)
        $providerConfig = $this->getProviderConfig($providerId);

        // Sinh số hóa đơn
        $issueDate = date('Y-m-d');
        $invoiceNumber = $this->generateInvoiceNumber($providerConfig['serial'], $issueDate);

        // Xây dựng XML chưa ký
        $xmlData = [
            'seller' => $seller,
            'buyer' => $buyer,
            'items' => $items,
            'totals' => $totals,
            'templateCode' => $providerConfig['pattern'],
            'templateSymbol' => $providerConfig['serial'],
            'invoiceNumber' => $invoiceNumber,
            'issueDate' => $issueDate,
        ];
        $unsignedXml = $this->xmlBuilder->buildGtgt($xmlData);

        // Ký số
        $signedXml = $this->signer->sign($unsignedXml);

        // Lưu XML trước khi gửi (phòng khi gateway lỗi)
        $id = uniqid('einv_');
        $this->pdo->prepare(
            "INSERT INTO e_invoices (id, transaction_id, invoice_type, template_code, template_symbol, invoice_number,
             xml_unsigned, xml_signed, status, issue_date, customer_name, customer_tax_code,
             total_amount, total_vat, grand_total, currency, created_by)
             VALUES (?, ?, '01GTKT', ?, ?, ?, ?, ?, 'signing', ?, ?, ?, ?, ?, ?, 'VND', ?)"
        )->execute([
            $id, $transactionId,
            $providerConfig['pattern'], $providerConfig['serial'], $invoiceNumber,
            $unsignedXml, $signedXml,
            $issueDate, $buyer['name'] ?? '', $buyer['taxCode'] ?? '',
            $totals['totalBeforeVat'], $totals['totalVat'], $totals['grandTotal'],
            $txn['created_by'] ?? 'system',
        ]);

        // Đồng bộ khách hàng lên T-VAN
        $this->gateway->syncCustomer(
            $buyer['code'] ?? $buyer['taxCode'] ?? 'KH' . uniqid(),
            $buyer['name'] ?? '',
            $buyer['taxCode'] ?? '',
            $buyer['address'] ?? '',
            $buyer['email'] ?? '',
        );

        // Phát hành qua gateway
        try {
            $result = $this->gateway->publish(
                $signedXml,
                $providerConfig['pattern'],
                $providerConfig['serial'],
            );

            $status = $result->success ? 'published' : 'publish_failed';

            $this->pdo->prepare(
                "UPDATE e_invoices SET status = ?, fkey = ?, inv_token = ?, cqt_code = ?,
                 error_code = ?, error_message = ?, submitted_at = NOW()
                 WHERE id = ?"
            )->execute([
                $status,
                $result->fkey,
                $result->invToken,
                $result->cqtCode,
                $result->success ? null : 'PUBLISH_ERR',
                $result->success ? null : $result->error,
                $id,
            ]);

            $this->auditLogger?->log(
                'einvoice.publish',
                'e_invoice',
                $id,
                null,
                ['transaction_id' => $transactionId, 'fkey' => $result->fkey, 'status' => $status],
                $txn['created_by'] ?? 'system',
            );

            return $this->getInvoice($id);
        } catch (\Throwable $e) {
            $this->pdo->prepare(
                "UPDATE e_invoices SET status = 'publish_failed', error_message = ? WHERE id = ?"
            )->execute([$e->getMessage(), $id]);

            throw new \RuntimeException("Phát hành hóa đơn thất bại: {$e->getMessage()}");
        }
    }

    //
    // ĐIỀU CHỈNH HÓA ĐƠN
    //
    public function adjustInvoice(string $einvoiceId, array $newData): array
    {
        $einvoice = $this->getInvoice($einvoiceId);
        if (!$einvoice) throw new \RuntimeException("Không tìm thấy hóa đơn.");
        if ($einvoice['status'] !== 'published') {
            throw new \RuntimeException("Chỉ được điều chỉnh hóa đơn đã phát hành.");
        }

        $seller = $newData['seller'] ?? $this->getSellerInfo();
        $buyer = $newData['buyer'] ?? $this->getBuyerInfo(['customer_id' => $einvoice['customer_tax_code'] ?? '']);
        $items = $newData['items'] ?? json_decode($einvoice['items_json'] ?? '[]', true) ?: [];
        $totals = $this->calculateTotals($items);

        $xmlData = [
            'seller' => $seller,
            'buyer' => $buyer,
            'items' => $items,
            'totals' => $totals,
            'templateCode' => $einvoice['template_code'],
            'templateSymbol' => $einvoice['template_symbol'],
            'invoiceNumber' => $einvoice['invoice_number'],
            'issueDate' => date('Y-m-d'),
        ];
        $unsignedXml = $this->xmlBuilder->buildAdjustment($einvoice['fkey'], $xmlData);
        $signedXml = $this->signer->sign($unsignedXml);

        $result = $this->gateway->adjust($einvoice['fkey'], $signedXml);
        if (!$result->success) {
            throw new \RuntimeException("Điều chỉnh hóa đơn thất bại: {$result->error}");
        }

        $adjId = uniqid('einv_');
        $this->pdo->prepare(
            "INSERT INTO e_invoices (id, transaction_id, invoice_type, template_code, template_symbol, invoice_number,
             fkey, inv_token, xml_unsigned, xml_signed, status, issue_date,
             total_amount, total_vat, grand_total, adjustment_type, original_fkey, created_by)
             VALUES (?, ?, '01GTKT', ?, ?, ?, ?, ?, ?, ?, 'adjusted', ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $adjId, $einvoice['transaction_id'],
            $einvoice['template_code'], $einvoice['template_symbol'], $einvoice['invoice_number'],
            $result->newFkey, $einvoice['inv_token'],
            $unsignedXml, $signedXml,
            date('Y-m-d'),
            $totals['totalBeforeVat'], $totals['totalVat'], $totals['grandTotal'],
            'adjust', $einvoice['fkey'],
            $newData['created_by'] ?? 'system',
        ]);

        $this->auditLogger?->log('einvoice.adjust', 'e_invoice', $adjId,
            $einvoice, ['new_fkey' => $result->newFkey], $newData['created_by'] ?? 'system');

        return $this->getInvoice($adjId);
    }

    //
    // THAY THẾ HÓA ĐƠN (hủy + tạo mới)
    //
    public function replaceInvoice(string $einvoiceId, array $newData): array
    {
        $einvoice = $this->getInvoice($einvoiceId);
        if (!$einvoice) throw new \RuntimeException("Không tìm thấy hóa đơn.");
        if ($einvoice['status'] !== 'published') {
            throw new \RuntimeException("Chỉ được thay thế hóa đơn đã phát hành.");
        }

        $seller = $newData['seller'] ?? $this->getSellerInfo();
        $buyer = $newData['buyer'] ?? $this->getBuyerInfo(['customer_id' => $einvoice['customer_tax_code'] ?? '']);
        $items = $newData['items'] ?? json_decode($einvoice['items_json'] ?? '[]', true) ?: [];
        $totals = $this->calculateTotals($items);

        $invoiceNumber = $newData['invoiceNumber'] ?? $this->generateInvoiceNumber($einvoice['template_symbol'], date('Y-m-d'));

        $xmlData = [
            'seller' => $seller,
            'buyer' => $buyer,
            'items' => $items,
            'totals' => $totals,
            'templateCode' => $einvoice['template_code'],
            'templateSymbol' => $einvoice['template_symbol'],
            'invoiceNumber' => $invoiceNumber,
            'issueDate' => date('Y-m-d'),
        ];
        $unsignedXml = $this->xmlBuilder->buildGtgt($xmlData);
        $signedXml = $this->signer->sign($unsignedXml);

        $result = $this->gateway->replace($einvoice['fkey'], $signedXml);
        if (!$result->success) {
            throw new \RuntimeException("Thay thế hóa đơn thất bại: {$result->error}");
        }

        // Đánh dấu hóa đơn cũ là replaced
        $this->pdo->prepare("UPDATE e_invoices SET status = 'replaced' WHERE id = ?")->execute([$einvoiceId]);

        $replId = uniqid('einv_');
        $this->pdo->prepare(
            "INSERT INTO e_invoices (id, transaction_id, invoice_type, template_code, template_symbol, invoice_number,
             fkey, inv_token, xml_unsigned, xml_signed, status, issue_date,
             total_amount, total_vat, grand_total, adjustment_type, original_fkey, created_by)
             VALUES (?, ?, '01GTKT', ?, ?, ?, ?, ?, ?, ?, 'published', ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $replId, $einvoice['transaction_id'],
            $einvoice['template_code'], $einvoice['template_symbol'], $invoiceNumber,
            $result->newFkey, $result->newInvToken,
            $unsignedXml, $signedXml,
            date('Y-m-d'),
            $totals['totalBeforeVat'], $totals['totalVat'], $totals['grandTotal'],
            'replace', $einvoice['fkey'],
            $newData['created_by'] ?? 'system',
        ]);

        $this->auditLogger?->log('einvoice.replace', 'e_invoice', $replId,
            $einvoice, ['new_fkey' => $result->newFkey], $newData['created_by'] ?? 'system');

        return $this->getInvoice($replId);
    }

    //
    // HỦY HÓA ĐƠN
    //
    public function cancelInvoice(string $einvoiceId, string $reason): array
    {
        $einvoice = $this->getInvoice($einvoiceId);
        if (!$einvoice) throw new \RuntimeException("Không tìm thấy hóa đơn.");
        if ($einvoice['status'] !== 'published') {
            throw new \RuntimeException("Chỉ được hủy hóa đơn đã phát hành.");
        }

        $result = $this->gateway->cancel($einvoice['fkey'], $reason);
        if (!$result->success) {
            throw new \RuntimeException("Hủy hóa đơn thất bại: {$result->error}");
        }

        $this->pdo->prepare(
            "UPDATE e_invoices SET status = 'cancelled', error_message = ? WHERE id = ?"
        )->execute([$reason, $einvoiceId]);

        $this->auditLogger?->log('einvoice.cancel', 'e_invoice', $einvoiceId,
            $einvoice, ['reason' => $reason], 'system');

        return $this->getInvoice($einvoiceId);
    }

    //
    // HÀM HỖ TRỢ
    //

    public function getInvoice(string $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM e_invoices WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return null;
        return $this->castInvoice($row);
    }

    public function listInvoices(string $status = '', string $from = '', string $to = '', int $limit = 50): array
    {
        $sql = "SELECT * FROM e_invoices WHERE 1=1";
        $params = [];

        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        if ($from) {
            $sql .= " AND issue_date >= ?";
            $params[] = $from;
        }
        if ($to) {
            $sql .= " AND issue_date <= ?";
            $params[] = $to;
        }

        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(fn($r) => $this->castInvoice($r), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function retryPublish(string $einvoiceId): array
    {
        $einvoice = $this->getInvoice($einvoiceId);
        if (!$einvoice) throw new \RuntimeException("Không tìm thấy hóa đơn.");
        if ($einvoice['status'] !== 'publish_failed') {
            throw new \RuntimeException("Chỉ retry hóa đơn ở trạng thái publish_failed.");
        }

        $providerConfig = $this->getProviderConfig('tvan_vnpt');

        try {
            $result = $this->gateway->publish(
                $einvoice['xml_signed'],
                $providerConfig['pattern'],
                $providerConfig['serial'],
            );

            $status = $result->success ? 'published' : 'publish_failed';
            $this->pdo->prepare(
                "UPDATE e_invoices SET status = ?, fkey = ?, inv_token = ?, cqt_code = ?,
                 error_code = ?, error_message = ?, submitted_at = NOW()
                 WHERE id = ?"
            )->execute([
                $status,
                $result->fkey,
                $result->invToken,
                $result->cqtCode,
                $result->success ? null : 'RETRY_ERR',
                $result->success ? null : $result->error,
                $einvoiceId,
            ]);

            return $this->getInvoice($einvoiceId);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Retry phát hành thất bại: {$e->getMessage()}");
        }
    }

    private function loadTransaction(string $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT t.*, ari.customer_id, ari.invoice_number as ref_invoice,
                    ari.net_amount, ari.vat_amount, ari.vat_rate,
                    c.name as customer_name, c.tax_code as customer_tax_code,
                    c.address as customer_address, c.email as customer_email
             FROM transactions t
             LEFT JOIN ar_invoices ari ON ari.transaction_id = t.id
             LEFT JOIN customers c ON c.id = ari.customer_id
             WHERE t.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private function getSellerInfo(): array
    {
        $stmt = $this->pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'company_info' LIMIT 1");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            $info = json_decode($row['setting_value'], true);
            if ($info) return $info;
        }

        // Fallback: lấy từ tài khoản 511 (doanh thu)
        return [
            'name' => 'CÔNG TY ABC',
            'taxCode' => '0000000000',
            'address' => 'Hà Nội, Việt Nam',
        ];
    }

    private function getBuyerInfo(array $txn): array
    {
        if (!empty($txn['customer_name'])) {
            return [
                'code' => $txn['customer_id'] ?? '',
                'name' => $txn['customer_name'],
                'taxCode' => $txn['customer_tax_code'] ?? '',
                'address' => $txn['customer_address'] ?? '',
                'email' => $txn['customer_email'] ?? '',
            ];
        }

        // Fallback: buyer thông thường (không xuất HĐ)
        return [
            'code' => 'KH-LE',
            'name' => 'Khách hàng lẻ',
            'taxCode' => '',
            'address' => '',
            'email' => '',
        ];
    }

    private function extractLineItems(array $txn): array
    {
        // Thử lấy từ chi tiết giao dịch (nếu có bảng transaction_items)
        $stmt = $this->pdo->prepare(
            "SELECT * FROM transaction_items WHERE transaction_id = ?"
        );
        $stmt->execute([$txn['id']]);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($items)) {
            return array_map(fn($item) => [
                'productCode' => $item['item_code'] ?? '',
                'productName' => $item['item_name'] ?? 'Hàng hóa',
                'unit' => $item['unit'] ?? 'Cái',
                'quantity' => (float)($item['quantity'] ?? 1),
                'unitPrice' => (float)($item['unit_price'] ?? 0),
                'discountRate' => (float)($item['discount_rate'] ?? 0),
                'discountAmount' => (float)($item['discount_amount'] ?? 0),
                'totalBeforeVat' => (float)($item['total_before_vat'] ?? 0),
                'vatRate' => (int)($item['vat_rate'] ?? ($txn['vat_rate'] ?? 10)),
                'vatAmount' => (float)($item['vat_amount'] ?? ($txn['vat_amount'] ?? 0)),
                'isService' => (bool)($item['is_service'] ?? false),
            ], $items);
        }

        // Fallback: tạo 1 dòng từ thông tin giao dịch
        $amount = (float)($txn['net_amount'] ?? 0);
        $vatAmount = (float)($txn['vat_amount'] ?? 0);
        $vatRate = (int)($txn['vat_rate'] ?? 10);

        if ($amount <= 0) {
            // Lấy từ ledger_entries (Dr 131/Cr 511)
            $leStmt = $this->pdo->prepare(
                "SELECT a.code, SUM(le.amount) as total
                 FROM ledger_entries le
                 JOIN accounts a ON a.id = le.account_id
                 WHERE le.transaction_id = ? AND le.is_debit = 1
                 AND a.code LIKE '131%'
                 GROUP BY a.code"
            );
            $leStmt->execute([$txn['id']]);
            $leRow = $leStmt->fetch(\PDO::FETCH_ASSOC);
            $amount = (float)($leRow['total'] ?? 0);

            // Nếu không có 131, lấy từ 511
            if ($amount <= 0) {
                $leStmt = $this->pdo->prepare(
                    "SELECT a.code, SUM(le.amount) as total
                     FROM ledger_entries le
                     JOIN accounts a ON a.id = le.account_id
                     WHERE le.transaction_id = ? AND le.is_debit = 0
                     AND a.code LIKE '511%'
                     GROUP BY a.code"
                );
                $leStmt->execute([$txn['id']]);
                $leRow = $leStmt->fetch(\PDO::FETCH_ASSOC);
                $amount = (float)($leRow['total'] ?? 0);
            }

            // Suy ra VAT
            if ($vatRate > 0 && $amount > 0) {
                $vatAmount = $amount * $vatRate / (100 + $vatRate);
                $amount -= $vatAmount;
            }
        }

        return [
            [
                'productCode' => $txn['reference'] ?? 'HD',
                'productName' => $txn['description'] ?? 'Hàng hóa/dịch vụ',
                'unit' => 'Dịch vụ',
                'quantity' => 1,
                'unitPrice' => $amount,
                'discountRate' => 0,
                'discountAmount' => 0,
                'totalBeforeVat' => $amount,
                'vatRate' => $vatRate,
                'vatAmount' => $vatAmount,
                'isService' => true,
            ],
        ];
    }

    private function calculateTotals(array $items): array
    {
        $totalBeforeVat = 0;
        $totalVat = 0;
        foreach ($items as $item) {
            $totalBeforeVat += (float)($item['totalBeforeVat'] ?? 0);
            $totalVat += (float)($item['vatAmount'] ?? 0);
        }
        return [
            'totalBeforeVat' => $totalBeforeVat,
            'totalVat' => $totalVat,
            'grandTotal' => $totalBeforeVat + $totalVat,
        ];
    }

    private function getProviderConfig(string $providerId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tvan_providers WHERE id = ? AND is_active = 1"
        );
        $stmt->execute([$providerId]);
        $config = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$config) {
            // Fallback: lấy provider mặc định
            $stmt = $this->pdo->query("SELECT * FROM tvan_providers WHERE is_default = 1 AND is_active = 1 LIMIT 1");
            $config = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        if (!$config) {
            throw new \RuntimeException('Không có T-VAN provider nào được cấu hình.');
        }
        return $config;
    }

    private function generateInvoiceNumber(string $serial, string $date): string
    {
        // Format: {prefix}{year}-{6-digit sequence}
        // Ví dụ: 01GTKT0/001-TT25-000001
        $year = date('y', strtotime($date));

        // SELECT FOR UPDATE để đảm bảo uniqueness
        $this->pdo->exec("CREATE TEMPORARY TABLE IF NOT EXISTS _seq_lock (id INT PRIMARY KEY) ENGINE=InnoDB");
        $this->pdo->exec("INSERT IGNORE INTO _seq_lock VALUES (1)");

        $lockStmt = $this->pdo->prepare("SELECT id FROM _seq_lock WHERE id = 1 FOR UPDATE");
        $lockStmt->execute();

        // Lấy số lớn nhất hiện tại
        $seqStmt = $this->pdo->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '-', -1) AS UNSIGNED)) as max_seq
             FROM e_invoices
             WHERE template_symbol = ? AND invoice_number LIKE CONCAT(?, '-%')"
        );
        $prefix = explode('-', $serial)[0] ?? $serial;
        $seqStmt->execute([$serial, $prefix . $year]);
        $maxSeq = (int)$seqStmt->fetchColumn();

        $nextSeq = str_pad($maxSeq + 1, 6, '0', STR_PAD_LEFT);
        return "{$prefix}{$year}-{$nextSeq}";
    }

    private function castInvoice(array $row): array
    {
        return [
            'id' => $row['id'],
            'transaction_id' => $row['transaction_id'],
            'invoice_type' => $row['invoice_type'],
            'template_code' => $row['template_code'],
            'template_symbol' => $row['template_symbol'],
            'invoice_number' => $row['invoice_number'],
            'fkey' => $row['fkey'],
            'inv_token' => $row['inv_token'],
            'cqt_code' => $row['cqt_code'],
            'status' => $row['status'],
            'issue_date' => $row['issue_date'],
            'customer_name' => $row['customer_name'],
            'customer_tax_code' => $row['customer_tax_code'],
            'total_amount' => (float)$row['total_amount'],
            'total_vat' => (float)$row['total_vat'],
            'grand_total' => (float)$row['grand_total'],
            'currency' => $row['currency'],
            'adjustment_type' => $row['adjustment_type'],
            'original_fkey' => $row['original_fkey'],
            'error_message' => $row['error_message'],
            'created_at' => $row['created_at'],
        ];
    }
}
