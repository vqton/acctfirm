<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\SupplierRepositoryInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Contract\AuditLoggerInterface;
use PDO;
use Accounting\Domain\Service\GoodsReceiptService;

class EInvoiceImportService
{
    private const TCT_NS = 'http://www.gdt.gov.vn/2025/invoice';

    private PDO $pdo;
    private SupplierRepositoryInterface $supplierRepo;
    private AccountRepositoryInterface $accountRepo;
    private JournalService $journalService;
    private AuditLoggerInterface $auditLogger;
    private ?GoodsReceiptService $goodsReceiptService = null;

    public function __construct(
        PDO $pdo,
        SupplierRepositoryInterface $supplierRepo,
        AccountRepositoryInterface $accountRepo,
        JournalService $journalService,
        AuditLoggerInterface $auditLogger
    ) {
        $this->pdo = $pdo;
        $this->supplierRepo = $supplierRepo;
        $this->accountRepo = $accountRepo;
        $this->journalService = $journalService;
        $this->auditLogger = $auditLogger;
    }

    // Gắn GoodsReceiptService để tự động tạo phiếu nhập kho khi import
    public function setGoodsReceiptService(GoodsReceiptService $service): void
    {
        $this->goodsReceiptService = $service;
    }

    // Parse XML hóa đơn → cấu trúc PHP
    public function parseXml(string $xmlContent): array
    {
        $xml = simplexml_load_string($xmlContent);
        if ($xml === false) {
            throw new \InvalidArgumentException('XML không đúng định dạng.');
        }

        $xml->registerXPathNamespace('inv', self::TCT_NS);

        // Lấy thông tin chung
        $ttChung = $xml->xpath('//inv:TTChung');
        if (empty($ttChung)) {
            throw new \InvalidArgumentException('Không tìm thấy thông tin chung (TTChung) trong XML.');
        }
        $ttChung = $ttChung[0];

        $templateCode = (string)$ttChung->KHMSHDon;
        $templateSymbol = (string)$ttChung->KHHDon;
        $invoiceNumber = (string)$ttChung->SHDon;
        $issueDate = (string)$ttChung->NLap;
        $currency = (string)$ttChung->DVTTe ?: 'VND';

        // Tạo FKey duy nhất để chống trùng
        $fkey = $templateSymbol . '_' . $invoiceNumber;

        // Lấy thông tin người bán
        $nBan = $xml->xpath('//inv:NBan');
        $supplier = [];
        if (!empty($nBan)) {
            $supplier = [
                'tax_code' => (string)$nBan[0]->MST,
                'name' => (string)$nBan[0]->Ten,
                'address' => (string)$nBan[0]->DChi,
            ];
        }

        if (empty($supplier['tax_code'])) {
            throw new \InvalidArgumentException('XML không có mã số thuế người bán.');
        }

        // Lấy thông tin người mua
        $nMua = $xml->xpath('//inv:NMua');
        $buyer = [];
        if (!empty($nMua)) {
            $buyer = [
                'tax_code' => (string)$nMua[0]->MST,
                'name' => (string)$nMua[0]->Ten,
                'address' => (string)$nMua[0]->DChi,
            ];
        }

        // Lấy danh sách hàng hóa
        $items = [];
        $hhDVuList = $xml->xpath('//inv:HHDVu');
        foreach ($hhDVuList as $hh) {
            $vatRateStr = (string)$hh->TSuat;
            $vatRate = $vatRateStr === '0%' ? 0 : (int)$vatRateStr;
            $items[] = [
                'line_number' => (int)$hh->STT,
                'product_code' => (string)$hh->MHHDVu,
                'product_name' => (string)$hh->THHDVu,
                'unit' => (string)$hh->DVTinh,
                'quantity' => (float)$hh->SLuong,
                'unit_price' => (float)$hh->DGia,
                'discount_rate' => (float)$hh->TLCKhau,
                'discount_amount' => (float)$hh->STCKhau,
                'total_before_vat' => (float)$hh->ThTien,
                'vat_rate' => $vatRate,
            ];
        }

        // Lấy tổng tiền
        $tToan = $xml->xpath('//inv:TToan');
        $totals = ['total_before_vat' => 0, 'total_vat' => 0, 'grand_total' => 0];
        if (!empty($tToan)) {
            $totals = [
                'total_before_vat' => (float)$tToan[0]->TgTCThue,
                'total_vat' => (float)$tToan[0]->TgTThue,
                'grand_total' => (float)$tToan[0]->TgTTTBSo,
            ];
        }

        return [
            'fkey' => $fkey,
            'template_code' => $templateCode,
            'template_symbol' => $templateSymbol,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $issueDate,
            'currency' => $currency,
            'supplier' => $supplier,
            'buyer' => $buyer,
            'items' => $items,
            'totals' => $totals,
        ];
    }

    // Import XML → tạo chứng từ mua hàng
    // Options:
    //   auto_goods_receipt (bool): Tự động tạo phiếu nhập kho
    //   warehouse_id (string|null): Kho nhập (mặc định: kho đầu tiên)
    //   receipt_type (string): Loại nhập (purchase|transfer|return|other) mặc định: purchase
    public function importXml(string $xmlContent, string $createdBy, array $options = []): array
    {
        $parsed = $this->parseXml($xmlContent);

        // Kiểm tra trùng FKey
        $existing = $this->findImportByFkey($parsed['fkey']);
        if ($existing) {
            throw new \InvalidArgumentException('Hóa đơn này đã được import. FKey: ' . $parsed['fkey']);
        }

        $warehouseId = $options['warehouse_id'] ?? $this->getDefaultWarehouseId();
        $receiptType = $options['receipt_type'] ?? 'purchase';
        $autoGoodsReceipt = $options['auto_goods_receipt'] ?? ($this->goodsReceiptService !== null);

        $this->pdo->beginTransaction();
        try {
            // 1. Tạo/cập nhật nhà cung cấp
            $supplierId = $this->ensureSupplier(
                $parsed['supplier']['tax_code'],
                $parsed['supplier']['name'],
                $parsed['supplier']['address'] ?? ''
            );

            // 2. Tạo items (nếu chưa có)
            $itemIds = [];
            foreach ($parsed['items'] as &$item) {
                $itemId = $this->ensureItem(
                    $item['product_name'],
                    $item['unit'] ?? 'Cái'
                );
                $itemIds[] = $itemId;
                $item['item_id'] = $itemId;
            }
            unset($item);

            // 3. Tạo journal entries qua JournalService
            $description = 'Mua hàng theo HĐĐT: ' . $parsed['invoice_number']
                . ' - ' . ($parsed['supplier']['name'] ?? '')
                . ' ngày ' . ($parsed['invoice_date'] ?? '');

            $lines = [];

            // Nợ 156 (Hàng hóa) — tổng tiền trước thuế
            $lines[] = [
                'account_code' => '156',
                'is_debit' => true,
                'amount' => $parsed['totals']['total_before_vat'],
            ];

            // Nợ 1331 (Thuế GTGT được khấu trừ)
            if ($parsed['totals']['total_vat'] > 0) {
                $lines[] = [
                    'account_code' => '1331',
                    'is_debit' => true,
                    'amount' => $parsed['totals']['total_vat'],
                ];
            }

            // Có 331 (Phải trả người bán)
            $lines[] = [
                'account_code' => '331',
                'is_debit' => false,
                'amount' => $parsed['totals']['grand_total'],
            ];

            // Post entry
            $txn = $this->journalService->postEntry(
                $description,
                'einvoice_import',
                $lines,
                $createdBy,
                true, // allowControl — allow 156/1331/331 (not sub-accounts)
                'ap_invoice',
                $parsed['invoice_date'] ?: date('Y-m-d')
            );

            $goodsReceiptId = null;

            // 4. Lưu record import
            $importId = uniqid('eimp_');
            $stmt = $this->pdo->prepare(
                "INSERT INTO einvoice_imports
                 (id, original_xml, invoice_number, invoice_date, template_code, template_symbol,
                  supplier_tax_code, supplier_name, supplier_address,
                  buyer_tax_code, buyer_name,
                  total_before_vat, total_vat, grand_total, currency, items,
                  status, fkey, transaction_id, goods_receipt_id, supplier_id, warehouse_id, receipt_type, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'processed', ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $importId,
                $xmlContent,
                $parsed['invoice_number'],
                $parsed['invoice_date'] ?: null,
                $parsed['template_code'],
                $parsed['template_symbol'],
                $parsed['supplier']['tax_code'],
                $parsed['supplier']['name'],
                $parsed['supplier']['address'] ?? null,
                $parsed['buyer']['tax_code'] ?? null,
                $parsed['buyer']['name'] ?? null,
                $parsed['totals']['total_before_vat'],
                $parsed['totals']['total_vat'],
                $parsed['totals']['grand_total'],
                $parsed['currency'],
                json_encode($parsed['items']),
                $parsed['fkey'],
                $txn->getId(),
                $goodsReceiptId,
                $supplierId,
                $warehouseId,
                $receiptType,
                $createdBy,
            ]);

            $this->pdo->commit();

            // 3b. Tự động tạo phiếu nhập kho (sau commit để tránh nested transaction)
            if ($autoGoodsReceipt && $this->goodsReceiptService && $warehouseId) {
                try {
                    $grLines = [];
                    foreach ($parsed['items'] as $item) {
                        $grLines[] = [
                            'item_id' => $item['item_id'],
                            'qty_received' => $item['quantity'],
                            'unit_price' => $item['unit_price'],
                        ];
                    }
                    $gr = $this->goodsReceiptService->createDraft(
                        null,
                        $parsed['supplier']['name'],
                        $parsed['supplier']['address'] ?? null,
                        $receiptType,
                        $warehouseId,
                        $parsed['invoice_date'] ?: date('Y-m-d'),
                        null,
                        'Tự động từ HĐĐT: ' . $parsed['invoice_number'],
                        $grLines,
                        $createdBy
                    );
                    $postedGr = $this->goodsReceiptService->postReceipt($gr['id'], $createdBy);
                    $goodsReceiptId = $postedGr['id'];
                    // Cập nhật goods_receipt_id vào import record
                    $this->pdo->prepare("UPDATE einvoice_imports SET goods_receipt_id = ? WHERE id = ?")
                        ->execute([$goodsReceiptId, $importId]);
                } catch (\Throwable $grE) {
                    // GR failed but import succeeded — log warning, không rollback import
                    $this->auditLogger->log(
                        'einvoice.import.gr_failed',
                        'einvoice_import',
                        $importId,
                        null,
                        ['error' => $grE->getMessage()],
                        $createdBy
                    );
                }
            }

            $this->auditLogger->log(
                'einvoice.import',
                'einvoice_import',
                $importId,
                null,
                [
                    'fkey' => $parsed['fkey'],
                    'supplier' => $parsed['supplier']['name'],
                    'total' => $parsed['totals']['grand_total'],
                    'transaction_id' => $txn->getId(),
                    'goods_receipt_id' => $goodsReceiptId,
                ],
                $createdBy
            );

            $result = [
                'import_id' => $importId,
                'fkey' => $parsed['fkey'],
                'transaction_id' => $txn->getId(),
                'description' => $description,
                'supplier_name' => $parsed['supplier']['name'],
                'total_before_vat' => $parsed['totals']['total_before_vat'],
                'total_vat' => $parsed['totals']['total_vat'],
                'grand_total' => $parsed['totals']['grand_total'],
                'items_count' => count($parsed['items']),
            ];
            if ($goodsReceiptId) {
                $result['goods_receipt_id'] = $goodsReceiptId;
            }
            return $result;

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Tổng hợp số liệu VAT từ hóa đơn đã import theo kỳ
    public function getVatSummary(string $periodCode): array
    {
        // Xác định start_date/end_date từ period_code (YYYY-MM)
        if (preg_match('/^(\d{4})-(\d{2})$/', $periodCode, $m)) {
            $startDate = $m[1] . '-' . $m[2] . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));
        } else {
            $startDate = $periodCode . '-01-01';
            $endDate = $periodCode . '-12-31';
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                COUNT(*) as total_invoices,
                COALESCE(SUM(total_before_vat), 0) as total_purchases,
                COALESCE(SUM(total_vat), 0) as total_vat_input,
                COALESCE(SUM(grand_total), 0) as total_payable,
                COUNT(DISTINCT supplier_tax_code) as supplier_count,
                COUNT(DISTINCT CASE WHEN goods_receipt_id IS NOT NULL THEN id END) as with_goods_receipt,
                COUNT(DISTINCT CASE WHEN payment_status = 'paid' THEN id END) as paid_count,
                COALESCE(SUM(paid_amount), 0) as total_paid
             FROM einvoice_imports
             WHERE status = 'processed'
               AND invoice_date >= ? AND invoice_date <= ?"
        );
        $stmt->execute([$startDate, $endDate]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        $summary['total_purchases'] = (float)$summary['total_purchases'];
        $summary['total_vat_input'] = (float)$summary['total_vat_input'];
        $summary['total_payable'] = (float)$summary['total_payable'];
        $summary['total_invoices'] = (int)$summary['total_invoices'];
        $summary['supplier_count'] = (int)$summary['supplier_count'];
        $summary['with_goods_receipt'] = (int)$summary['with_goods_receipt'];
        $summary['paid_count'] = (int)$summary['paid_count'];
        $summary['total_paid'] = (float)$summary['total_paid'];
        return $summary;
    }

    // Ghi nhận thanh toán cho hóa đơn đã import
    public function recordPayment(string $importId, float $amount, string $paidBy, ?string $bankTransactionId = null): array
    {
        $import = $this->getImport($importId);
        if (!$import) {
            throw new \InvalidArgumentException('Không tìm thấy import: ' . $importId);
        }
        if ($import['status'] !== 'processed') {
            throw new \InvalidArgumentException('Chỉ có thể thanh toán cho hóa đơn đã import thành công.');
        }

        $grandTotal = (float)$import['grand_total'];
        $currentPaid = (float)$import['paid_amount'];
        $newPaid = $currentPaid + $amount;

        if ($newPaid > $grandTotal + 10) {
            throw new \InvalidArgumentException(
                "Số tiền thanh toán ($amount) vượt quá số còn phải trả (" 
                . ($grandTotal - $currentPaid) . ")."
            );
        }

        $paymentStatus = abs($newPaid - $grandTotal) <= 10 ? 'paid' : 'partial';

        $stmt = $this->pdo->prepare(
            "UPDATE einvoice_imports SET payment_status = ?, paid_amount = ? WHERE id = ?"
        );
        $stmt->execute([$paymentStatus, $newPaid, $importId]);

        $this->auditLogger->log(
            'einvoice.payment',
            'einvoice_import',
            $importId,
            ['paid_amount' => $currentPaid, 'payment_status' => $import['payment_status'] ?? 'unpaid'],
            ['paid_amount' => $newPaid, 'payment_status' => $paymentStatus],
            $paidBy
        );

        return [
            'import_id' => $importId,
            'payment_status' => $paymentStatus,
            'paid_amount' => $newPaid,
            'remaining' => $grandTotal - $newPaid,
        ];
    }

    // Ghi nhận tạm ứng cho hóa đơn đã import
    public function recordPrepay(string $importId, float $amount, string $transactionId, string $createdBy): array
    {
        $import = $this->getImport($importId);
        if (!$import) {
            throw new \InvalidArgumentException('Không tìm thấy import: ' . $importId);
        }
        $stmt = $this->pdo->prepare(
            "UPDATE einvoice_imports SET prepay_amount = prepay_amount + ?, prepay_transaction_id = ? WHERE id = ?"
        );
        $stmt->execute([$amount, $transactionId, $importId]);

        $this->auditLogger->log(
            'einvoice.prepay',
            'einvoice_import',
            $importId,
            null,
            ['prepay_amount' => $amount, 'transaction_id' => $transactionId],
            $createdBy
        );

        return [
            'import_id' => $importId,
            'prepay_amount' => $amount,
            'prepay_transaction_id' => $transactionId,
        ];
    }

    // Liên kết hóa đơn import với lệnh sản xuất
    public function allocateToProduction(string $importId, string $productionOrderId, string $category = 'raw_material', string $createdBy = 'system'): array
    {
        $import = $this->getImport($importId);
        if (!$import) {
            throw new \InvalidArgumentException('Không tìm thấy import: ' . $importId);
        }
        $stmt = $this->pdo->prepare(
            "UPDATE einvoice_imports SET production_order_id = ?, cost_category = ? WHERE id = ?"
        );
        $stmt->execute([$productionOrderId, $category, $importId]);

        $this->auditLogger->log(
            'einvoice.allocate',
            'einvoice_import',
            $importId,
            null,
            ['production_order_id' => $productionOrderId, 'cost_category' => $category],
            $createdBy
        );

        return [
            'import_id' => $importId,
            'production_order_id' => $productionOrderId,
            'cost_category' => $category,
        ];
    }

    // Kiểm tra trùng lặp
    public function checkDuplicate(string $fkey): bool
    {
        return $this->findImportByFkey($fkey) !== null;
    }

    // Danh sách import
    public function listImports(int $limit = 50): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, invoice_number, invoice_date, supplier_tax_code, supplier_name,
                    total_before_vat, total_vat, grand_total, status, transaction_id,
                    payment_status, paid_amount, prepay_amount, prepay_transaction_id,
                    production_order_id, cost_category, goods_receipt_id,
                    created_by, created_at, processed_at
             FROM einvoice_imports
             ORDER BY created_at DESC LIMIT $limit"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['total_before_vat'] = (float)$r['total_before_vat'];
            $r['total_vat'] = (float)$r['total_vat'];
            $r['grand_total'] = (float)$r['grand_total'];
            $r['paid_amount'] = (float)$r['paid_amount'];
            $r['prepay_amount'] = (float)($r['prepay_amount'] ?? 0);
        }
        return $rows;
    }

    // Chi tiết import
    public function getImport(string $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM einvoice_imports WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        if ($row['items']) {
            $row['items'] = json_decode($row['items'], true);
        }
        $row['total_before_vat'] = (float)$row['total_before_vat'];
        $row['total_vat'] = (float)$row['total_vat'];
        $row['grand_total'] = (float)$row['grand_total'];
        $row['paid_amount'] = (float)($row['paid_amount'] ?? 0);
        $row['prepay_amount'] = (float)($row['prepay_amount'] ?? 0);
        return $row;
    }

    private function findImportByFkey(string $fkey): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id FROM einvoice_imports WHERE fkey = ?");
        $stmt->execute([$fkey]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function ensureSupplier(string $taxCode, string $name, string $address): string
    {
        // Tìm supplier theo MST
        $stmt = $this->pdo->prepare("SELECT id FROM suppliers WHERE tax_code = ?");
        $stmt->execute([$taxCode]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            // Cập nhật tên nếu khác
            $this->pdo->prepare("UPDATE suppliers SET name = ?, address = COALESCE(NULLIF(?, ''), address) WHERE id = ?")
                ->execute([$name, $address, $existing]);
            return $existing;
        }

        // Tạo mới supplier
        $id = uniqid('sup_');
        $this->pdo->prepare(
            "INSERT INTO suppliers (id, code, name, tax_code, address, status, created_at)
             VALUES (?, ?, ?, ?, ?, 1, NOW())"
        )->execute([$id, 'NCC-' . $taxCode, $name, $taxCode, $address]);

        return $id;
    }

    private function ensureItem(string $productName, string $unit): string
    {
        // Tìm item theo tên
        $stmt = $this->pdo->prepare("SELECT id FROM items WHERE name = ? LIMIT 1");
        $stmt->execute([$productName]);
        $existing = $stmt->fetchColumn();

        if ($existing) return $existing;

        // Tạo mới item
        $id = uniqid('item_');
        $code = 'HH-' . strtoupper(substr(md5($productName), 0, 8));
        $this->pdo->prepare(
            "INSERT INTO items (id, code, name, unit, item_type, status, created_at)
             VALUES (?, ?, ?, ?, 'product', 1, NOW())"
        )->execute([$id, $code, $productName, $unit]);

        return $id;
    }

    private function getDefaultWarehouseId(): ?string
    {
        $stmt = $this->pdo->query("SELECT id FROM warehouses WHERE status = 1 ORDER BY created_at ASC LIMIT 1");
        return $stmt->fetchColumn() ?: null;
    }
}
