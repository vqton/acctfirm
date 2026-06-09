<?php
declare(strict_types=1);
// NGHIEP VU: PHIEU NHAP KHO — Mẫu số 01-VT theo Thông tư 99/2025/TT-BTC
//
// Quy trình Lifecycle:
//   draft → posted → cancelled
//
// Hạch toán (khi ghi sổ):
//   Nợ 15x (Giá trị hàng = SL × ĐG) / Có 331 (Phải trả người bán)
//
// Ảnh hưởng BCTC:
//   - BC01: Tăng hàng tồn kho (Tài sản ngắn hạn)
//   - BC01: Tăng phải trả người bán (Nợ phải trả)
//   - Chưa ảnh hưởng BC02/KQKD (chỉ ảnh hưởng khi xuất kho ghi nhận giá vốn)
//
// Audit trail: Ghi log toàn bộ thay đổi trạng thái
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Contract\JournalServiceInterface;
use Accounting\Domain\Model\GoodsReceipt;
use Accounting\Domain\Model\GoodsReceiptLine;
use Accounting\Domain\Repository\GoodsReceiptLineRepositoryInterface;
use Accounting\Domain\Repository\GoodsReceiptRepositoryInterface;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Domain\Repository\WarehouseRepositoryInterface;
use Accounting\Domain\ValueObject\VnWords;

class GoodsReceiptService
{
    private \PDO $pdo;
    private VoucherService $voucherService;
    private JournalServiceInterface $journalService;
    private GoodsReceiptRepositoryInterface $grRepo;
    private GoodsReceiptLineRepositoryInterface $grLineRepo;
    private ItemRepositoryInterface $itemRepo;
    private WarehouseRepositoryInterface $warehouseRepo;
    private AuditLoggerInterface $auditLogger;
    private InventoryService $inventoryService;

    // Bảng ánh xạ: Loại hàng → Tài khoản tồn kho
    private array $inventoryAccountMap = [
        'material' => '152', 'tool' => '153',
        'product' => '155', 'merchandise' => '156',
        'other' => '152',
    ];

    public function __construct(
        \PDO $pdo,
        VoucherService $voucherService,
        JournalServiceInterface $journalService,
        GoodsReceiptRepositoryInterface $grRepo,
        GoodsReceiptLineRepositoryInterface $grLineRepo,
        ItemRepositoryInterface $itemRepo,
        WarehouseRepositoryInterface $warehouseRepo,
        AuditLoggerInterface $auditLogger,
        InventoryService $inventoryService
    ) {
        $this->pdo = $pdo;
        $this->voucherService = $voucherService;
        $this->journalService = $journalService;
        $this->grRepo = $grRepo;
        $this->grLineRepo = $grLineRepo;
        $this->itemRepo = $itemRepo;
        $this->warehouseRepo = $warehouseRepo;
        $this->auditLogger = $auditLogger;
        $this->inventoryService = $inventoryService;
    }

    // KIEM SOAT KY: Khong cho nhap kho trong ky da dong
    private function assertPeriodOpen(?string $date = null): void
    {
        $date ??= date('Y-m-d');
        if (!PeriodService::isPeriodOpen($date, $this->pdo)) {
            throw new \InvalidArgumentException(
                "Không thể nhập kho trong kỳ đã khóa. Ngày: {$date}."
            );
        }
    }

    // TAO MOI PHIEU NHAP KHO (draft)
    // Input: Thông tin header + danh sách dòng hàng
    // Output: goods_receipt với status = draft
    // THÔNG BÁO KHI SỐ LƯỢNG THEO CT KHÁC SỐ LƯỢNG THỰC NHẬP
    private function buildQtyWarning(array $lines): ?string
    {
        $warnings = [];
        foreach ($lines as $i => $line) {
            $doc = (float)($line['qty_in_document'] ?? 0);
            $actual = (float)($line['qty_received'] ?? 0);
            if ($doc > 0 && $doc !== $actual) {
                $name = $line['item_name'] ?? "dòng " . ($i + 1);
                $warnings[] = "{$name}: CT={$doc}, thực nhập={$actual} (" . ($doc > $actual ? 'thiếu' : 'thừa') . " {$actual})";
            }
        }
        return $warnings ? 'Chênh lệch số lượng: ' . implode('; ', $warnings) : null;
    }

    public function createDraft(
        ?string $poId,
        ?string $supplierName,
        ?string $supplierAddress,
        string $receiptType,
        ?string $warehouseId,
        string $receivedDate,
        ?string $department,
        ?string $note,
        array $lines,
        string $createdBy,
        ?string $invoiceRef = null,
        ?string $invoiceDate = null,
        ?string $delivererName = null,
        ?string $warehouseLocation = null,
        ?string $attachDoc = null
    ): array {
        $this->assertPeriodOpen($receivedDate);

        // Validate lines
        if (empty($lines)) {
            throw new \InvalidArgumentException('Phiếu nhập kho phải có ít nhất một dòng hàng');
        }

        $id = uniqid('gr_');
        $grNumber = $this->voucherService->nextNumber('PNK');
        $totalAmount = 0;

        foreach ($lines as $i => $line) {
            $qty = (float)($line['qty_received'] ?? 0);
            $price = (float)($line['unit_price'] ?? 0);
            if ($qty <= 0) {
                throw new \InvalidArgumentException("Số lượng nhập dòng " . ($i + 1) . " phải lớn hơn 0");
            }
            if ($price < 0) {
                throw new \InvalidArgumentException("Đơn giá dòng " . ($i + 1) . " không được âm");
            }
            $totalAmount += $qty * $price;
        }

        $amountInWords = VnWords::toWords($totalAmount);
        $qtyWarning = $this->buildQtyWarning($lines);

        $receipt = new GoodsReceipt(
            $id, $grNumber, $poId,
            $supplierName, $supplierAddress,
            $receiptType, 'draft',
            $warehouseId, $receivedDate,
            $department, $note,
            $totalAmount, $amountInWords,
            $createdBy, date('Y-m-d H:i:s'),
            null,
            $invoiceRef, $invoiceDate,
            $delivererName, $warehouseLocation, $attachDoc
        );

        $this->pdo->beginTransaction();
        try {
            $this->grRepo->save($receipt);

            foreach ($lines as $i => $line) {
                $lineId = uniqid('grl_');
                $itemId = $line['item_id'] ?? null;
                $qty = (float)($line['qty_received'] ?? 0);
                $price = (float)($line['unit_price'] ?? 0);
                $total = $qty * $price;

                $grLine = new GoodsReceiptLine(
                    $lineId, $id,
                    $line['po_line_id'] ?? null,
                    $itemId,
                    $line['item_name'] ?? null,
                    $line['item_code'] ?? null,
                    $line['uom'] ?? null,
                    $qty,
                    isset($line['qty_rejected']) ? (float)$line['qty_rejected'] : null,
                    $line['batch_no'] ?? null,
                    $line['expiry_date'] ?? null,
                    $price, $total,
                    $i + 1,
                    isset($line['qty_in_document']) ? (float)$line['qty_in_document'] : null
                );
                $this->grLineRepo->save($grLine);
            }

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $auditData = ['gr_number' => $grNumber, 'total_amount' => $totalAmount, 'lines' => count($lines)];
        if ($qtyWarning) {
            $auditData['qty_warning'] = $qtyWarning;
        }
        $this->auditLogger->log(
            'goods_receipt.create_draft', 'goods_receipts', $id,
            null,
            $auditData,
            $createdBy
        );

        return $this->getReceipt($id);
    }

    // GHI SO PHIEU NHAP KHO (draft → posted)
    // Hạch toán: Nợ 15x / Có 331 (hoặc Có 111/112 nếu trả tiền ngay)
    // Cập nhật: tồn kho, cost layer, đơn giá bình quân
    public function postReceipt(string $id, string $postedBy): array
    {
        $receipt = $this->grRepo->findById($id);
        if (!$receipt) {
            throw new \InvalidArgumentException('Không tìm thấy phiếu nhập kho');
        }
        if ($receipt->getStatus() !== 'draft') {
            throw new \InvalidArgumentException(
                "Chỉ có thể ghi sổ phiếu nhập kho ở trạng thái nháp. Trạng thái hiện tại: {$receipt->getStatus()}"
            );
        }

        $this->assertPeriodOpen($receipt->getReceivedDate());

        $lines = $this->grLineRepo->findByGrId($id);

        $this->pdo->beginTransaction();
        try {
            // Xây dựng bút toán: gom theo tài khoản tồn kho
            $accountLines = []; // [account_code => amount]
            foreach ($lines as $line) {
                if (!$line->getItemId()) {
                    throw new \InvalidArgumentException('Dòng hàng thiếu thông tin mặt hàng');
                }
                $item = $this->itemRepo->findById($line->getItemId());
                if (!$item) {
                    throw new \InvalidArgumentException("Không tìm thấy mặt hàng: {$line->getItemId()}");
                }
                $invCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';
                $amount = $line->getTotal() ?? ($line->getQtyReceived() * $line->getUnitPrice());
                $accountLines[$invCode] = ($accountLines[$invCode] ?? 0) + $amount;
            }

            // Tạo journal lines
            $journalLines = [];
            foreach ($accountLines as $code => $amount) {
                $journalLines[] = ['account_code' => $code, 'amount' => $amount, 'is_debit' => true];
            }
            // Credit: 331 (nếu có supplier) hoặc ghi theo receipt_type
            $creditAccount = $receipt->getSupplierName() ? '331' : '1111';
            $journalLines[] = [
                'account_code' => $creditAccount,
                'amount' => $receipt->getTotalAmount(),
                'is_debit' => false,
            ];

            $txn = $this->journalService->postEntry(
                "Nhập kho: {$receipt->getGrNumber()} - {$receipt->getSupplierName()}",
                $receipt->getGrNumber(),
                $journalLines,
                $postedBy, false, 'inventory',
                $receipt->getReceivedDate(),
                'PNK', 'goods_receipt'
            );

            // Cập nhật tồn kho + cost layer cho từng dòng
            foreach ($lines as $line) {
                $item = $this->itemRepo->findById($line->getItemId());
                if ($item) {
                    $qty = $line->getQtyReceived() ?? 0;
                    $price = $line->getUnitPrice() ?? 0;

                    // Sử dụng InventoryService để cập nhật cost layer
                    $this->inventoryService->updateStockAndCostLayer(
                        $line->getItemId(), $qty, $price
                    );
                }
            }

            // Cập nhật trạng thái
            $receipt->setStatus('posted');
            $receipt->setUpdatedAt(date('Y-m-d H:i:s'));
            $this->grRepo->save($receipt);

            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->auditLogger->log(
            'goods_receipt.post', 'goods_receipts', $id,
            ['status' => 'draft'],
            ['status' => 'posted', 'transaction_id' => $txn->getId()],
            $postedBy
        );

        return $this->getReceipt($id);
    }

    // HUY PHIEU NHAP KHO (draft → cancelled)
    public function cancelReceipt(string $id, string $cancelledBy): array
    {
        $receipt = $this->grRepo->findById($id);
        if (!$receipt) {
            throw new \InvalidArgumentException('Không tìm thấy phiếu nhập kho');
        }
        if ($receipt->getStatus() !== 'draft') {
            throw new \InvalidArgumentException(
                "Chỉ có thể hủy phiếu nhập kho ở trạng thái nháp. Trạng thái hiện tại: {$receipt->getStatus()}"
            );
        }

        $this->pdo->beginTransaction();
        try {
            $receipt->setStatus('cancelled');
            $receipt->setUpdatedAt(date('Y-m-d H:i:s'));
            $this->grRepo->save($receipt);
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->auditLogger->log(
            'goods_receipt.cancel', 'goods_receipts', $id,
            ['status' => 'draft'],
            ['status' => 'cancelled'],
            $cancelledBy
        );

        return $this->getReceipt($id);
    }

    // LAY CHI TIET PHIEU NHAP KHO (header + lines)
    public function getReceipt(string $id): array
    {
        $receipt = $this->grRepo->findById($id);
        if (!$receipt) {
            throw new \InvalidArgumentException('Không tìm thấy phiếu nhập kho');
        }
        $result = $receipt->toArray();
        $result['lines'] = array_map(
            fn($l) => $l->toArray(),
            $this->grLineRepo->findByGrId($id)
        );
        return $result;
    }

    // LAY DU LIEU IN PHIEU NHAP KHO (Mẫu 01-VT)
    // Trả về: thông tin in + danh sách dòng 8 cột A-D + 1-4
    public function getPrintData(string $id): array
    {
        $data = $this->getReceipt($id);
        // Xác định TK Nợ dựa trên item_type của từng line
        $debitAccounts = [];
        $lines = $data['lines'] ?? [];
        foreach ($lines as $line) {
            $item = $line['item_id'] ? $this->itemRepo->findById($line['item_id']) : null;
            $itemType = $item ? $item->getItemType() : 'other';
            $invCode = $this->inventoryAccountMap[$itemType] ?? '152';
            $debitAccounts[$invCode] = ($debitAccounts[$invCode] ?? 0) + ($line['total'] ?? 0);
        }

        // Xác định TK Có
        $supplierName = $data['supplier_name'] ?? null;
        $creditAccount = $supplierName ? '331' : '1111';

        $data['debit_accounts'] = $debitAccounts;
        $data['credit_account'] = $creditAccount;
        $data['qty_warning'] = null;
        $hasDiff = false;
        foreach ($lines as $line) {
            $doc = (float)($line['qty_in_document'] ?? 0);
            $act = (float)($line['qty_received'] ?? 0);
            if ($doc > 0 && $doc !== $act) { $hasDiff = true; break; }
        }
        if ($hasDiff) {
            $diffLines = array_filter($lines, fn($l) => (float)($l['qty_in_document'] ?? 0) !== (float)($l['qty_received'] ?? 0));
            $names = array_map(fn($l) => $l['item_name'] ?? $l['item_code'] ?? '', array_slice($diffLines, 0, 3));
            $data['qty_warning'] = 'Chênh lệch số lượng: ' . implode(', ', $names) . (count($diffLines) > 3 ? '...' : '');
        }
        return $data;
    }

    // DANH SACH PHIEU NHAP KHO
    public function listReceipts(?string $status = null, int $limit = 50): array
    {
        return array_map(
            fn(GoodsReceipt $r) => $r->toArray(),
            $this->grRepo->findAll($status, $limit)
        );
    }
}
