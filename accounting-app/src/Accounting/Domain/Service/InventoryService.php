<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\InventoryServiceInterface;
use Accounting\Domain\Contract\JournalServiceInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use Accounting\Domain\Repository\TransactionRepositoryInterface;
use Accounting\Domain\Repository\WarehouseRepositoryInterface;

/**
 * NGHIỆP VỤ KHO — Service xử lý toàn bộ nghiệp vụ nhập/xuất/tồn kho theo Thông tư 99/2025/TT-BTC.
 *
 * Nguyên tắc hạch toán:
 * - Mọi biến động hàng tồn kho đều ghi nhận bút toán kép qua JournalService
 * - Giá trị tồn kho theo dõi chi tiết qua cost layer (FIFO/Bình quân gia quyền/Specific ID)
 * - Xuất kho → ghi nhận giá vốn (TK 632) → ảnh hưởng BC02 chỉ tiêu 24
 *
 * RỦI RO: Sai giá vốn → BC02 sai → Thuế TNDN sai → Phạt thuế
 * RỦI RO: Mất dữ liệu cost layer → không trace được giá gốc xuất kho
 * RỦI RO: Âm kho sai lệch số liệu kiểm kê → BC01 sai
 */
class InventoryService implements InventoryServiceInterface
{
    private AccountRepositoryInterface $accountRepo;
    private TransactionRepositoryInterface $txnRepo;
    private ItemRepositoryInterface $itemRepo;
    private WarehouseRepositoryInterface $warehouseRepo;
    private \PDO $pdo;
    private JournalServiceInterface $journal;

    // BẢNG ÁNH XẠ: Loại hàng → Tài khoản tồn kho tương ứng
    // - material (152): Nguyên liệu vật liệu
    // - tool (153): Công cụ dụng cụ
    // - product (155): Thành phẩm sản xuất
    // - merchandise (156): Hàng hóa mua về bán
    // - other (152): Mặc định vào Nguyên liệu
    //
    // Chú ý: Các TK này KHÔNG phải control account trong nghiệp vụ kho
    private array $inventoryAccountMap = [
        'material' => '152', 'tool' => '153',
        'product' => '155', 'merchandise' => '156',
        'other' => '152',
    ];

    public function __construct(
        AccountRepositoryInterface $accountRepo,
        TransactionRepositoryInterface $txnRepo,
        ItemRepositoryInterface $itemRepo,
        WarehouseRepositoryInterface $warehouseRepo,
        JournalServiceInterface $journal,
        \PDO $pdo
    ) {
        $this->accountRepo = $accountRepo;
        $this->txnRepo = $txnRepo;
        $this->itemRepo = $itemRepo;
        $this->warehouseRepo = $warehouseRepo;
        $this->journal = $journal;
        $this->pdo = $pdo;
    }

    /**
     * Wrap nghiệp vụ kho trong DB transaction để đảm bảo toàn vẹn dữ liệu.
     *
     * BẢO VỆ TOÀN VẸN: Mọi nghiệp vụ kho đều chạy trong DB transaction.
     * Nếu một bước thất bại (ví dụ: trừ kho nhưng post bút toán lỗi),
     * toàn bộ thay đổi về số lượng lẫn giá trị đều được rollback.
     *
     * RỦI RO: Nếu không dùng transaction, trường hợp lỗi giữa chừng sẽ
     * dẫn đến lệch số lượng kho với số dư tài khoản (không trace được).
     *
     * @param callable $fn Hàm nghiệp vụ cần wrap
     * @return mixed Kết quả từ $fn
     * @throws \Exception Rollback và ném lại lỗi
     */
    private function wrapInTransaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn();
            $this->pdo->commit();
            return $result;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Kiểm soát kỳ: Không cho phép nhập/xuất vào kỳ kế toán đã đóng.
     *
     * Nếu kỳ đã đóng mà vẫn cho nhập/xuất → số dư đầu kỳ sau sai,
     * dẫn đến BC01/BC02/BC03 sai toàn bộ.
     * LƯU Ý: Nếu date = null → dùng ngày hiện tại.
     *
     * @param string|null $date Ngày giao dịch (null = current date)
     * @return void
     * @throws \InvalidArgumentException Nếu kỳ đã đóng
     */
    private function assertPeriodOpen(?string $date = null): void
    {
        $date ??= date('Y-m-d');
        if (!PeriodService::isPeriodOpen($date, $this->pdo)) {
            throw new \InvalidArgumentException("Không thể thay đổi tồn kho trong kỳ đã khóa. Ngày: {$date}. Vui lòng kiểm tra lại kỳ kế toán.");
        }
    }

    /**
     * NHẬP KHO (Mua hàng) — Nợ 15x / Có 331.
     *
     * Hạch toán:
     *   Nợ 15x (Giá trị hàng = SL × ĐG + Chi phí mua)
     *   Có 331 (Phải trả người bán)
     *
     * Chi phí mua (vận chuyển, bốc xếp, bảo hiểm) được phân bổ vào giá gốc hàng nhập
     * và ghi nhận vào addon_per_unit để tính giá xuất sau này.
     * Cost layer được tạo để theo dõi giá gốc riêng cho từng lô nhập (FIFO).
     *
     * ẢNH HƯỞNG BCTC: Tăng giá trị hàng tồn kho (BC01), chưa ảnh hưởng BC02
     *
     * RỦI RO: Nếu không phân bổ chi phí mua → giá vốn sau này thấp hơn thực tế → lợi nhuận ảo
     *
     * @param string $itemId ID mặt hàng
     * @param float $qty Số lượng nhập
     * @param float $unitPrice Đơn giá chưa bao gồm chi phí mua
     * @param array $addonCosts Chi phí mua kèm [['description'=>'','amount'=>float],...]
     * @param string $reference Số chứng từ (PNK)
     * @param string $createdBy ID người tạo
     * @param string|null $batchCode Mã lô (FIFO tracking)
     * @param string|null $expiryDate Hạn sử dụng (nếu có)
     * @return array
     */
    public function receiveGoods(string $itemId, float $qty, float $unitPrice,
        array $addonCosts, string $reference, string $createdBy,
        ?string $batchCode = null, ?string $expiryDate = null): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $qty, $unitPrice, $addonCosts, $reference, $createdBy, $batchCode, $expiryDate) {
            $this->assertPeriodOpen();
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Không tìm thấy mặt hàng mã {$itemId}. Vui lòng kiểm tra lại mã vật tư/hàng hóa.");

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';
            $itemCost = $qty * $unitPrice;
            $totalAddon = array_sum(array_column($addonCosts, 'amount'));
            $totalCost = $itemCost + $totalAddon;

            $lines = [
                ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => '331', 'amount' => $totalCost, 'is_debit' => false],
            ];

            $txn = $this->journal->postEntry("Goods receipt: {$item->getName()}", $reference, $lines, $createdBy);

            // CONCURRENCY: update stock_qty và save cost layer không dùng SELECT FOR UPDATE.
            // Dưới concurrent cao, 2 request nhập kho cùng lúc có thể đọc cùng stock_qty cũ,
            // dẫn đến mất 1 lần cập nhật (lost update).
            // Biện pháp: DB transaction + WHERE stock_qty = old_value (optimistic locking).
            // Hiện tại chưa có optimistic lock — cần bổ sung nếu scale > 10 request/giây.
            // RỦI RO THẤP: Với nghiệp vụ nhập kho, tần suất thấp (vài lần/ngày), lost update
            // hiếm xảy ra. Với xuất kho bán lẻ (hàng trăm lần/ngày), cần bổ sung lock.
            $item->setStockQty($item->getStockQty() + $qty);
            $this->itemRepo->save($item);

            $this->saveCostLayer($itemId, $qty, $unitPrice, $totalAddon / max($qty, 1), null, $batchCode, $expiryDate);
            $this->calculateAndUpdateUnitCost($itemId);

            return ['transaction_id' => $txn->getId(), 'total_cost' => $totalCost];
        });
    }

    /**
     * XUẤT KHO — Nợ 154/241/632 / Có 15x.
     *
     * Hạch toán: Nợ TK chi phí (theo issueType) / Có TK tồn kho.
     *
     * issueType xác định bản chất xuất:
     *   'production' (154): Xuất cho sản xuất → CPSXKD dở dang
     *   'construction' (241): Xuất cho XDCB → XDCB dở dang
     *   'sale' (632): Xuất bán → Giá vốn hàng bán (ảnh hưởng trực tiếp BC02 chỉ tiêu 24)
     *
     * Đơn giá xuất được tính từ consumeCostLayers() theo phương pháp FIFO/bình quân.
     *
     * RỦI RO: Sai phương pháp tính giá → sai giá vốn → BC02 sai → Thuế TNDN sai
     * RỦI RO: Xuất nhầm loại (sale vs production) → sai chỉ tiêu BC01 và BC02
     *
     * @param string $itemId ID mặt hàng
     * @param float $qty Số lượng xuất
     * @param string $issueType Loại xuất: production|construction|sale
     * @param string $reference Số chứng từ (PXK)
     * @param string $createdBy ID người tạo
     * @return array
     */
    public function issueGoods(string $itemId, float $qty, string $issueType,
        string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $qty, $issueType, $reference, $createdBy) {
            $this->assertPeriodOpen();
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Không tìm thấy mặt hàng mã {$itemId}. Vui lòng kiểm tra lại mã vật tư/hàng hóa.");
            if (!$item->getAllowNegativeStock() && $item->getStockQty() < $qty) {
                throw new \InvalidArgumentException(
                    "Tồn kho không đủ. Hiện có {$item->getStockQty()}, cần {$qty}."
                );
            }

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';
            $costResult = $this->consumeCostLayers($itemId, $qty, null);
            $totalCost = $costResult['total_cost'];
            $expenseCode = match($issueType) {
                'production' => '154',
                'construction' => '241',
                'sale' => '632',
                default => throw new \InvalidArgumentException("Loại xuất kho không hợp lệ: {$issueType}. Vui lòng kiểm tra lại."),
            };

            $lines = [
                ['account_code' => $expenseCode, 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => false],
            ];

            // allowControl=true cho construction (TK 241 là control account):
            // TK 241 (XDCB dở dang) có thể là control account với TK con 2411, 2412, 2413.
            // Xuất kho cho XDCB thường ghi nhận vào TK 241 tổng hợp do chưa phân bổ được
            // ngay vào TK con. Cho phép bypass control account check cho trường hợp này.
            // RỦI RO: Nếu lạm dụng allowControl=true cho mục đích khác → mất kiểm soát
            // chi tiết tài khoản → số dư chi tiết không khớp tổng hợp.
            $txn = $this->journal->postEntry("Goods issue: {$item->getName()}", $reference, $lines, $createdBy, $issueType === 'construction');

            $item->setStockQty($item->getStockQty() - $qty);
            $this->itemRepo->save($item);

            return ['transaction_id' => $txn->getId(), 'total_cost' => $totalCost];
        });
    }

    /**
     * NGHIỆP VỤ: CHUYỂN KHO NỘI BỘ
     *
     * Hạch toán:
     *   Nợ 15x (Kho đích)
     *   Có 15x (Kho nguồn)
     *
     * Đây là bút toán nội bảng — không ảnh hưởng đến BC02 (không ghi nhận giá vốn).
     * Cost layer được chuyển từ kho nguồn sang kho đích giữ nguyên đơn giá gốc,
     * đảm bảo trace được giá nhập ban đầu.
     *
     * RỦI RO: Nếu không giữ nguyên đơn giá layer → sai số dư kho đích → sai giá vốn khi xuất sau này
     *
     * @param string $itemId ID mặt hàng
     * @param float $qty Số lượng chuyển
     * @param string|null $fromWarehouseId ID kho nguồn (null = kho tổng hợp)
     * @param string $toWarehouseId ID kho đích
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @return array
     * @throws \InvalidArgumentException Nếu không tìm thấy mặt hàng/kho, hoặc tồn kho không đủ
     */
    public function transferGoods(string $itemId, float $qty, ?string $fromWarehouseId, string $toWarehouseId, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $qty, $fromWarehouseId, $toWarehouseId, $reference, $createdBy) {
            $this->assertPeriodOpen();
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Không tìm thấy mặt hàng mã {$itemId}. Vui lòng kiểm tra lại mã vật tư/hàng hóa.");

            if ($fromWarehouseId !== null) {
                $from = $this->warehouseRepo->findById($fromWarehouseId);
                if (!$from) throw new \InvalidArgumentException("Không tìm thấy kho xuất mã {$fromWarehouseId}.");
            }
            $to = $this->warehouseRepo->findById($toWarehouseId);
            if (!$to) throw new \InvalidArgumentException("Không tìm thấy kho nhập mã {$toWarehouseId}.");

            $pdo = $this->getPdo();
            if ($fromWarehouseId !== null) {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty), 0) FROM inventory_cost_layers WHERE item_id = ? AND warehouse_id = ? AND qty > 0");
                $stmt->execute([$itemId, $fromWarehouseId]);
            } else {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty), 0) FROM inventory_cost_layers WHERE item_id = ? AND warehouse_id IS NULL AND qty > 0");
                $stmt->execute([$itemId]);
            }
            $sourceStock = (float)$stmt->fetchColumn();
            if (!$item->getAllowNegativeStock() && $sourceStock < $qty) {
                throw new \InvalidArgumentException("Tồn kho không đủ tại kho xuất. Hiện có {$sourceStock}, cần {$qty}.");
            }

            if ($fromWarehouseId !== null) {
                $stmt = $pdo->prepare("SELECT id, qty, unit_cost, addon_per_unit FROM inventory_cost_layers WHERE item_id = ? AND warehouse_id = ? AND qty > 0 ORDER BY created_at ASC");
                $stmt->execute([$itemId, $fromWarehouseId]);
            } else {
                $stmt = $pdo->prepare("SELECT id, qty, unit_cost, addon_per_unit FROM inventory_cost_layers WHERE item_id = ? AND warehouse_id IS NULL AND qty > 0 ORDER BY created_at ASC");
                $stmt->execute([$itemId]);
            }

            $remaining = $qty;
            $totalCost = 0;
            $transferLayers = [];
            while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) && $remaining > 0) {
                $consume = min($row['qty'], $remaining);
                $layerCost = $consume * ((float)$row['unit_cost'] + (float)$row['addon_per_unit']);
                $totalCost += $layerCost;
                $transferLayers[] = [
                    'id' => $row['id'],
                    'consume' => $consume,
                    'unit_cost' => $row['unit_cost'],
                    'addon_per_unit' => $row['addon_per_unit'],
                ];
                $remaining -= $consume;
            }

            foreach ($transferLayers as $tl) {
                $update = $pdo->prepare("UPDATE inventory_cost_layers SET qty = qty - ? WHERE id = ?");
                $update->execute([$tl['consume'], $tl['id']]);
            }

            foreach ($transferLayers as $tl) {
                $insert = $pdo->prepare("INSERT INTO inventory_cost_layers (id, item_id, warehouse_id, qty, unit_cost, addon_per_unit, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $insert->execute([uniqid('cst_'), $itemId, $toWarehouseId, $tl['consume'], $tl['unit_cost'], $tl['addon_per_unit']]);
            }

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';

            $fromName = $fromWarehouseId ? $from->getName() : 'General';
            $txn = $this->journal->postEntry(
                "Transfer: {$item->getName()} ({$fromName} → {$to->getName()})",
                $reference,
                [
                    ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => true],
                    ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => false],
                ],
                $createdBy
            );

            return ['transaction_id' => $txn->getId(), 'total_cost' => $totalCost, 'qty' => $qty];
        });
    }

    /**
     * NGHIỆP VỤ: HÀNG ĐANG ĐI ĐƯỜNG (TK 151)
     *
     * Hạch toán:
     *   Nợ 151 (Hàng mua đang đi đường)
     *   Có 331 (Phải trả người bán)
     *
     * Sử dụng khi hàng đã mua nhưng chưa về đến kho.
     * Hàng đi đường vẫn thuộc sở hữu của DN — phải ghi nhận để đảm bảo BC01 phản ánh đúng.
     *
     * ẢNH HƯỞNG BCTC: Tăng TK 151 (BC01), chưa ghi nhận vào tồn kho thực tế
     *
     * RỦI RO: Nếu không ghi nhận → thiếu hàng trên BC01 → sai tỷ lệ thanh toán với NCC
     *
     * @param string $itemId ID mặt hàng
     * @param float $qty Số lượng nhập đi đường
     * @param float $unitPrice Đơn giá mua
     * @param array $addonCosts Chi phí mua kèm
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @return array
     * @throws \InvalidArgumentException Nếu không tìm thấy mặt hàng
     */
    public function recordInTransit(string $itemId, float $qty, float $unitPrice,
        array $addonCosts, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $qty, $unitPrice, $addonCosts, $reference, $createdBy) {
            $this->assertPeriodOpen();
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Không tìm thấy mặt hàng mã {$itemId}. Vui lòng kiểm tra lại mã vật tư/hàng hóa.");

            $itemCost = $qty * $unitPrice;
            $totalAddon = array_sum(array_column($addonCosts, 'amount'));
            $totalCost = $itemCost + $totalAddon;

            $lines = [
                ['account_code' => '151', 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => '331', 'amount' => $totalCost, 'is_debit' => false],
            ];

            $txn = $this->journal->postEntry("In transit: {$item->getName()}", $reference, $lines, $createdBy);

            $pdo = $this->getPdo();
            $transitId = uniqid('trn_');
            $addonPerUnit = $totalAddon / max($qty, 1);
            $stmt = $pdo->prepare(
                "INSERT INTO inventory_in_transit (id, item_id, qty, unit_cost, addon_per_unit, reference) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$transitId, $itemId, $qty, $unitPrice, $addonPerUnit, $reference]);

            return ['transit_id' => $transitId, 'total_cost' => $totalCost, 'qty' => $qty];
        });
    }

    /**
     * NGHIỆP VỤ: NHẬN HÀNG TỪ ĐI ĐƯỜNG VỀ KHO
     *
     * Hạch toán:
     *   Nợ 15x (Hàng tồn kho)
     *   Có 151 (Hàng mua đang đi đường)
     *
     * Khi hàng về đến kho, chuyển từ TK 151 sang TK tồn kho tương ứng.
     * Cost layer được tạo với giá gốc giống hồi ghi nhận đi đường.
     *
     * ẢNH HƯỞNG BCTC: Giảm TK 151, tăng TK 15x (BC01), không ảnh hưởng BC02
     *
     * @param string $transitId ID lô hàng đi đường
     * @param float $qty Số lượng nhận về kho
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @return array
     * @throws \InvalidArgumentException Nếu không tìm thấy lô hàng đi đường hoặc số lượng không đủ
     */
    public function receiveFromTransit(string $transitId, float $qty, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($transitId, $qty, $reference, $createdBy) {
            $this->assertPeriodOpen();
            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("SELECT * FROM inventory_in_transit WHERE id = ?");
            $stmt->execute([$transitId]);
            $transit = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$transit) throw new \InvalidArgumentException("Không tìm thấy lô hàng đi đường mã {$transitId}.");
            if ($transit['qty'] < $qty) {
                throw new \InvalidArgumentException("Hàng đi đường không đủ. Hiện có {$transit['qty']}, cần {$qty}.");
            }

            $item = $this->itemRepo->findById($transit['item_id']);
            if (!$item) throw new \InvalidArgumentException("Không tìm thấy mặt hàng mã {$transit['item_id']}.");

            $unitCost = (float)$transit['unit_cost'];
            $addonPerUnit = (float)$transit['addon_per_unit'];
            $totalCost = $qty * ($unitCost + $addonPerUnit);
            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';

            $lines = [
                ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => '151', 'amount' => $totalCost, 'is_debit' => false],
            ];

            $txn = $this->journal->postEntry("Receive from transit: {$item->getName()}", $reference, $lines, $createdBy);

            $item->setStockQty($item->getStockQty() + $qty);
            $this->itemRepo->save($item);

            $this->saveCostLayer($transit['item_id'], $qty, $unitCost, $addonPerUnit, null);

            $newQty = $transit['qty'] - $qty;
            if ($newQty <= 0) {
                $pdo->prepare("DELETE FROM inventory_in_transit WHERE id = ?")->execute([$transitId]);
            } else {
                $pdo->prepare("UPDATE inventory_in_transit SET qty = ? WHERE id = ?")->execute([$newQty, $transitId]);
            }

            return ['transaction_id' => $txn->getId(), 'total_cost' => $totalCost, 'qty' => $qty];
        });
    }

    /**
     * NGHIỆP VỤ: GỬI BÁN ĐẠI LÝ/KÝ GỬI (TK 157)
     *
     * Hạch toán:
     *   Nợ 157 (Hàng gửi đi bán)
     *   Có 15x (Hàng tồn kho)
     *
     * Hàng gửi đi bán vẫn thuộc sở hữu DN đến khi bên nhận bán được.
     * Chuyển từ tồn kho trong kho sang tồn kho gửi bán — không ghi nhận doanh thu hay giá vốn.
     *
     * ẢNH HƯỞNG BCTC: Giảm TK 15x, tăng TK 157 (BC01), chưa ảnh hưởng BC02
     *
     * RỦI RO: Nếu ghi nhận doanh thu khi gửi bán → doanh thu ảo → sai BC02 + Thuế GTGT
     *
     * @param string $itemId ID mặt hàng
     * @param float $qty Số lượng gửi bán
     * @param string $consignee Tên bên nhận ký gửi
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @return array
     * @throws \InvalidArgumentException Nếu không tìm thấy mặt hàng hoặc tồn kho không đủ
     */
    public function consignGoods(string $itemId, float $qty, string $consignee,
        string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $qty, $consignee, $reference, $createdBy) {
            $this->assertPeriodOpen();
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Không tìm thấy mặt hàng mã {$itemId}. Vui lòng kiểm tra lại mã vật tư/hàng hóa.");
            if (!$item->getAllowNegativeStock() && $item->getStockQty() < $qty) {
                throw new \InvalidArgumentException("Tồn kho không đủ. Hiện có {$item->getStockQty()}, cần {$qty}.");
            }

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';
            $costResult = $this->consumeCostLayers($itemId, $qty, null);
            $totalCost = $costResult['total_cost'];
            $avgUnitCost = $qty > 0 ? $totalCost / $qty : 0;

            $lines = [
                ['account_code' => '157', 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => false],
            ];

            $txn = $this->journal->postEntry("Consignment: {$item->getName()} → {$consignee}", $reference, $lines, $createdBy);

            $item->setStockQty($item->getStockQty() - $qty);
            $this->itemRepo->save($item);

            $pdo = $this->getPdo();
            $cId = uniqid('csn_');
            $pdo->prepare("INSERT INTO inventory_consignment (id, item_id, qty, unit_cost, addon_per_unit, consignee, reference) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$cId, $itemId, $qty, $avgUnitCost, 0, $consignee, $reference]);

            return ['consignment_id' => $cId, 'total_cost' => $totalCost, 'qty' => $qty];
        });
    }

    /**
     * NGHIỆP VỤ: BÁN HÀNG KÝ GỬI
     *
     * Hạch toán:
     *   Nợ 632 (Giá vốn hàng bán)
     *   Có 157 (Hàng gửi đi bán)
     *
     * Khi bên nhận ký gửi thông báo đã bán được hàng, ghi nhận giá vốn.
     * Lúc này hàng không còn thuộc sở hữu DN → xóa khỏi TK 157.
     *
     * ẢNH HƯỞNG BCTC: Tăng giá vốn (BC02 chỉ tiêu 24), giảm TK 157 (BC01)
     *
     * @param string $consignmentId ID phiếu ký gửi
     * @param float $qty Số lượng đã bán
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @return array
     * @throws \InvalidArgumentException Nếu không tìm thấy phiếu ký gửi hoặc số lượng không đủ
     */
    public function sellConsigned(string $consignmentId, float $qty, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($consignmentId, $qty, $reference, $createdBy) {
            $this->assertPeriodOpen();
            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("SELECT * FROM inventory_consignment WHERE id = ?");
            $stmt->execute([$consignmentId]);
            $record = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$record) throw new \InvalidArgumentException("Không tìm thấy phiếu ký gửi mã {$consignmentId}.");
            if ($record['qty'] < $qty) {
                throw new \InvalidArgumentException("Hàng ký gửi không đủ. Hiện có {$record['qty']}, cần {$qty}.");
            }

            $unitCost = (float)$record['unit_cost'] + (float)$record['addon_per_unit'];
            $totalCost = $qty * $unitCost;

            $txn = $this->journal->postEntry("Consignment sale", $reference, [
                ['account_code' => '632', 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => '157', 'amount' => $totalCost, 'is_debit' => false],
            ], $createdBy);

            $newQty = $record['qty'] - $qty;
            if ($newQty <= 0) {
                $pdo->prepare("DELETE FROM inventory_consignment WHERE id = ?")->execute([$consignmentId]);
            } else {
                $pdo->prepare("UPDATE inventory_consignment SET qty = ? WHERE id = ?")->execute([$newQty, $consignmentId]);
            }

            return ['transaction_id' => $txn->getId(), 'total_cost' => $totalCost, 'qty' => $qty];
        });
    }

    /**
     * NGHIỆP VỤ: NHẬN LẠI HÀNG KÝ GỬI (KHÔNG BÁN ĐƯỢC)
     *
     * Hạch toán:
     *   Nợ 15x (Nhập lại kho)
     *   Có 157 (Hàng gửi đi bán)
     *
     * Khi bên nhận ký gửi trả lại, hàng về kho với giá gốc ban đầu.
     * Cost layer được tạo lại để phục hồi dấu vết giá gốc.
     *
     * ẢNH HƯỞNG BCTC: Tăng TK 15x, giảm TK 157 (BC01), không ảnh hưởng BC02
     *
     * @param string $consignmentId ID phiếu ký gửi
     * @param float $qty Số lượng trả lại
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @return array
     * @throws \InvalidArgumentException Nếu không tìm thấy phiếu ký gửi hoặc số lượng không đủ
     */
    public function returnConsigned(string $consignmentId, float $qty, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($consignmentId, $qty, $reference, $createdBy) {
            $this->assertPeriodOpen();
            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("SELECT * FROM inventory_consignment WHERE id = ?");
            $stmt->execute([$consignmentId]);
            $record = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$record) throw new \InvalidArgumentException("Không tìm thấy phiếu ký gửi mã {$consignmentId}.");
            if ($record['qty'] < $qty) {
                throw new \InvalidArgumentException("Hàng ký gửi không đủ để trả lại. Hiện có {$record['qty']}, cần {$qty}.");
            }

            $item = $this->itemRepo->findById($record['item_id']);
            if (!$item) throw new \InvalidArgumentException("Không tìm thấy mặt hàng mã {$record['item_id']}.");

            $unitCost = (float)$record['unit_cost'] + (float)$record['addon_per_unit'];
            $totalCost = $qty * $unitCost;
            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';

            $txn = $this->journal->postEntry("Consignment return", $reference, [
                ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => '157', 'amount' => $totalCost, 'is_debit' => false],
            ], $createdBy);

            $item->setStockQty($item->getStockQty() + $qty);
            $this->itemRepo->save($item);

            $this->saveCostLayer($record['item_id'], $qty, (float)$record['unit_cost'], (float)$record['addon_per_unit'], null);

            $newQty = $record['qty'] - $qty;
            if ($newQty <= 0) {
                $pdo->prepare("DELETE FROM inventory_consignment WHERE id = ?")->execute([$consignmentId]);
            } else {
                $pdo->prepare("UPDATE inventory_consignment SET qty = ? WHERE id = ?")->execute([$newQty, $consignmentId]);
            }

            return ['transaction_id' => $txn->getId(), 'total_cost' => $totalCost, 'qty' => $qty];
        });
    }

    /**
     * NGHIỆP VỤ: ĐIỀU CHỈNH TỒN KHO THEO KIỂM KÊ THỰC TẾ
     *
     * Thừa (actualQty > systemQty):
     *   Nợ 15x, Có 711 (Thu nhập khác) — ghi nhận hàng thừa chờ xử lý
     *
     * Thiếu (actualQty < systemQty):
     *   Nợ 632 (Giá vốn), Có 15x — ghi nhận hao hụt vào giá vốn
     *
     * Kiểm kê định kỳ là yêu cầu bắt buộc theo chuẩn mực kế toán.
     * Chênh lệch > 10% phải có giải trình với cơ quan thuế.
     *
     * RỦI RO: Nếu không điều chỉnh kịp thời → BC01 sai số dư hàng tồn kho
     * RỦI RO: Thiếu kho không ghi nhận → lãi ảo (giá vốn thấp hơn thực tế)
     *
     * @param string $itemId ID mặt hàng
     * @param float $actualQty Số lượng thực tế kiểm kê
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @return array
     * @throws \InvalidArgumentException Nếu không tìm thấy mặt hàng
     */
    public function adjustPhysicalCount(string $itemId, float $actualQty, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $actualQty, $reference, $createdBy) {
            $this->assertPeriodOpen();
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Không tìm thấy mặt hàng mã {$itemId}. Vui lòng kiểm tra lại mã vật tư/hàng hóa.");

            $systemQty = $item->getStockQty();
            $diff = $actualQty - $systemQty;
            if (abs($diff) < 0.001) {
                return ['message' => 'No adjustment needed', 'diff' => 0];
            }

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';

            // KIỂM KÊ: Xử lý chênh lệch theo 2 hướng:
            // THỪA (diff > 0): Ghi nhận vào 711 (Thu nhập khác).
            //   → Ảnh hưởng BC02: Tăng LN trước thuế (chỉ tiêu 40) → Tăng Thuế TNDN
            //   → RỦI RO: Nếu thừa do nhập sai (không phải thu nhập thực), khai thuế có thể bị truy thu.
            //   → Xử lý đúng: Phải chờ xử lý (TK 3381 - Tài sản thừa chờ giải quyết).
            //
            // THIẾU (diff < 0): Ghi nhận vào 632 (Giá vốn hàng bán).
            //   → Ảnh hưởng BC02: Tăng giá vốn (chỉ tiêu 24) → Giảm LN trước thuế → Giảm Thuế TNDN
            //   → RỦI RO: Nếu thiếu do mất cắp (không phải hao hụt tự nhiên), cơ quan thuế
            //     có thể loại chi phí này khi tính thuế TNDN (cần Biên bản xử lý của cơ quan công an).
            //
            // THAM CHIẾU: Chuẩn mực kế toán VAS 02 — Hàng tồn kho và Thông tư 48/2019/TT-BTC.
            if ($diff > 0) {
                $unitCost = $item->getPurchasePrice() ?: 0;
                $diffValue = abs($diff) * $unitCost;

                $txn = $this->journal->postEntry("Count surplus: {$item->getName()}", $reference, [
                    ['account_code' => $inventoryCode, 'amount' => $diffValue, 'is_debit' => true],
                    ['account_code' => '711', 'amount' => $diffValue, 'is_debit' => false],
                ], $createdBy);

                $this->saveCostLayer($itemId, $diff, $unitCost, 0, null);

                $item->setStockQty($actualQty);
                $this->itemRepo->save($item);

                return [
                    'transaction_id' => $txn->getId(), 'diff' => $diff,
                    'diff_value' => $diffValue, 'adjusted' => true,
                ];
            } else {
                $costResult = $this->consumeCostLayers($itemId, abs($diff), null);
                $diffValue = $costResult['total_cost'];

                $txn = $this->journal->postEntry("Count shortage: {$item->getName()}", $reference, [
                    ['account_code' => '632', 'amount' => $diffValue, 'is_debit' => true],
                    ['account_code' => $inventoryCode, 'amount' => $diffValue, 'is_debit' => false],
                ], $createdBy);

                $item->setStockQty($actualQty);
                $this->itemRepo->save($item);

                return [
                    'transaction_id' => $txn->getId(), 'diff' => $diff,
                    'diff_value' => $diffValue, 'adjusted' => true,
                ];
            }
        });
    }

    /**
     * NGHIỆP VỤ: TẠO PHIÊN KIỂM KÊ (DRAFT)
     *
     * Ghi nhận danh sách kiểm kê ở trạng thái 'draft' để đối chiếu sau.
     * Mỗi dòng ghi nhận: số lượng hệ thống, số lượng thực tế, chênh lệch, giá trị chênh lệch.
     *
     * Sau khi kiểm kê xong, gọi adjustPhysicalCount() cho từng item có chênh lệch.
     *
     * Audit trail: Lưu toàn bộ phiên kiểm kê — ai kiểm, ngày nào, lệch bao nhiêu.
     *
     * @param array $lines Danh sách các dòng kiểm kê [['item_id'=>'','actual_qty'=>float],...]
     * @param string $reference Số chứng từ
     * @param string $notes Ghi chú phiên kiểm kê
     * @param string $createdBy ID người tạo
     * @return array
     */
    public function createCountSession(array $lines, string $reference, string $notes, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($lines, $reference, $notes, $createdBy) {
            $this->assertPeriodOpen();
            $pdo = $this->getPdo();
            $sessionId = uniqid('cnt_');
            $totalDiff = 0;
            $count = 0;

            $pdo->prepare("INSERT INTO inventory_count_sessions (id, session_date, reference, notes, status, created_by) VALUES (?, CURDATE(), ?, ?, 'draft', ?)")
                ->execute([$sessionId, $reference, $notes, $createdBy]);

            foreach ($lines as $line) {
                $item = $this->itemRepo->findById($line['item_id']);
                if (!$item) continue;

                $systemQty = $item->getStockQty();
                $actualQty = (float)$line['actual_qty'];
                $diffQty = $actualQty - $systemQty;
                $unitCost = $item->getPurchasePrice();
                $diffValue = $diffQty * $unitCost;
                $totalDiff += $diffValue;
                $count++;

                $pdo->prepare("INSERT INTO inventory_count_lines (id, session_id, item_id, system_qty, actual_qty, diff_qty, unit_cost, diff_value) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([uniqid('ctl_'), $sessionId, $line['item_id'], $systemQty, $actualQty, $diffQty, $unitCost, $diffValue]);
            }

            $pdo->prepare("UPDATE inventory_count_sessions SET total_items = ?, total_diff = ? WHERE id = ?")
                ->execute([$count, $totalDiff, $sessionId]);

            return ['session_id' => $sessionId, 'total_items' => $count, 'total_diff' => $totalDiff];
        });
    }

    /**
     * NGHIỆP VỤ: TRÍCH LẬP DỰ PHÒNG GIẢM GIÁ HÀNG TỒN KHO
     *
     * Hạch toán:
     *   Nợ 632 (Giá vốn hàng bán)
     *   Có 2294 (Dự phòng giảm giá hàng tồn kho)
     *
     * Khi giá trị thuần có thể thực hiện được (NRV) thấp hơn giá gốc,
     * DN phải trích lập dự phòng theo chuẩn mực kế toán VAS 02 và TT 48/2019/TT-BTC.
     *
     * ẢNH HƯỞNG BCTC: Tăng giá vốn (giảm lợi nhuận), số dư TK 2294 tăng (BC01)
     *
     * RỦI RO: Nếu không trích lập → tài sản và lợi nhuận cao hơn thực tế → sai BC01/BC02
     *
     * @param string $itemId ID mặt hàng
     * @param float $amount Số tiền dự phòng
     * @param string $reference Số chứng từ
     * @param string $notes Ghi chú lý do trích lập
     * @param string $createdBy ID người tạo
     * @return array
     * @throws \InvalidArgumentException Nếu không tìm thấy mặt hàng hoặc số tiền không hợp lệ
     */
    public function recordImpairment(string $itemId, float $amount, string $reference, string $notes, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $amount, $reference, $notes, $createdBy) {
            $this->assertPeriodOpen();
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Không tìm thấy mặt hàng mã {$itemId}. Vui lòng kiểm tra lại mã vật tư/hàng hóa.");
            if ($amount <= 0) throw new \InvalidArgumentException("Số tiền dự phòng giảm giá phải lớn hơn 0.");

            $txn = $this->journal->postEntry("Impairment: {$item->getName()}", $reference, [
                ['account_code' => '632', 'amount' => $amount, 'is_debit' => true],
                ['account_code' => '2294', 'amount' => $amount, 'is_debit' => false],
            ], $createdBy);

            $pdo = $this->getPdo();
            $impairId = uniqid('imp_');
            $pdo->prepare("INSERT INTO inventory_impairment (id, item_id, provision_amount, remaining_amount, reference, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$impairId, $itemId, $amount, $amount, $reference, $notes, $createdBy]);

            return ['impairment_id' => $impairId, 'transaction_id' => $txn->getId(), 'amount' => $amount];
        });
    }

    /**
     * NGHIỆP VỤ: HOÀN NHẬP DỰ PHÒNG GIẢM GIÁ HÀNG TỒN KHO
     *
     * Hạch toán:
     *   Nợ 2294 (Dự phòng giảm giá hàng tồn kho)
     *   Có 632 (Giá vốn hàng bán)
     *
     * Khi giá trị thị trường phục hồi hoặc hàng đã được bán/xuất khỏi kho,
     * phần dự phòng tương ứng được hoàn nhập để phản ánh đúng thực tế.
     *
     * ẢNH HƯỞNG BCTC: Giảm giá vốn (tăng lợi nhuận), giảm số dư TK 2294 (BC01)
     *
     * RỦI RO: Hoàn nhập quá mức → lãi ảo → sai BC02 + Thuế TNDN
     *
     * @param string $impairmentId ID phiếu dự phòng
     * @param float $amount Số tiền hoàn nhập
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @return array
     * @throws \InvalidArgumentException Nếu không tìm thấy phiếu dự phòng hoặc số dư không đủ
     */
    public function reverseImpairment(string $impairmentId, float $amount, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($impairmentId, $amount, $reference, $createdBy) {
            $this->assertPeriodOpen();
            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("SELECT * FROM inventory_impairment WHERE id = ?");
            $stmt->execute([$impairmentId]);
            $record = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$record) throw new \InvalidArgumentException("Không tìm thấy phiếu dự phòng mã {$impairmentId}.");
            if ($record['remaining_amount'] < $amount) {
                throw new \InvalidArgumentException("Dự phòng còn lại không đủ. Hiện còn {$record['remaining_amount']}, cần {$amount}.");
            }

            $item = $this->itemRepo->findById($record['item_id']);
            $itemName = $item ? $item->getName() : $record['item_id'];

            $txn = $this->journal->postEntry("Impairment reversal: {$itemName}", $reference, [
                ['account_code' => '2294', 'amount' => $amount, 'is_debit' => true],
                ['account_code' => '632', 'amount' => $amount, 'is_debit' => false],
            ], $createdBy);

            $newRemaining = $record['remaining_amount'] - $amount;
            $pdo->prepare("UPDATE inventory_impairment SET remaining_amount = ? WHERE id = ?")
                ->execute([$newRemaining, $impairmentId]);

            return ['transaction_id' => $txn->getId(), 'amount' => $amount, 'remaining' => $newRemaining];
        });
    }

    /**
     * NGHIỆP VỤ: XUẤT HÀNG KHUYẾN MẠI (tặng kèm, khuyến mãi)
     *
     * Hạch toán:
     *   Nợ 641 (Chi phí bán hàng) — giá vốn hàng khuyến mại
     *   Có 15x (Hàng tồn kho)
     *
     * Nếu có deemedSaleValue (giá tính thuế TTĐB hoặc GTGT):
     *   Nợ 641, Có 33311 (Thuế GTGT đầu ra phải nộp) — theo VAS 02
     *
     * Hàng khuyến mại không ghi nhận doanh thu nhưng vẫn phải nộp thuế GTGT
     * trên giá trị tính thuế theo quy định.
     *
     * ẢNH HƯỞNG BCTC: Tăng chi phí bán hàng (BC02), giảm tồn kho (BC01)
     *
     * RỦI RO: Quên thuế GTGT đầu ra cho hàng KM → bị phạt thuế
     *
     * @param string $itemId ID mặt hàng
     * @param float $qty Số lượng xuất khuyến mại
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @param float|null $deemedSaleValue Giá tính thuế GTGT (nếu có)
     * @param float $vatRate Thuế suất GTGT (%)
     * @return array
     * @throws \InvalidArgumentException Nếu không tìm thấy mặt hàng hoặc tồn kho không đủ
     */
    public function issuePromotional(string $itemId, float $qty, string $reference, string $createdBy,
        ?float $deemedSaleValue = null, float $vatRate = 0): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $qty, $reference, $createdBy, $deemedSaleValue, $vatRate) {
            $this->assertPeriodOpen();
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Không tìm thấy mặt hàng mã {$itemId}. Vui lòng kiểm tra lại mã vật tư/hàng hóa.");
            if (!$item->getAllowNegativeStock() && $item->getStockQty() < $qty) {
                throw new \InvalidArgumentException("Tồn kho không đủ. Hiện có {$item->getStockQty()}, cần {$qty}.");
            }

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';
            $costResult = $this->consumeCostLayers($itemId, $qty, null);
            $totalCost = $costResult['total_cost'];

            $lines = [
                ['account_code' => '641', 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => false],
            ];

            // Output VAT on deemed sale value for promotional goods (VAS 02)
            if ($deemedSaleValue !== null && $deemedSaleValue > 0 && $vatRate > 0) {
                $vatAmount = $deemedSaleValue * $vatRate / 100;
                $lines[] = ['account_code' => '641', 'amount' => $vatAmount, 'is_debit' => true];
                $lines[] = ['account_code' => '33311', 'amount' => $vatAmount, 'is_debit' => false];
            }

            $txn = $this->journal->postEntry("Promotional: {$item->getName()}", $reference, $lines, $createdBy);

            $item->setStockQty($item->getStockQty() - $qty);
            $this->itemRepo->save($item);

            return ['transaction_id' => $txn->getId(), 'total_cost' => $totalCost, 'qty' => $qty, 'vat_amount' => $vatAmount ?? 0];
        });
    }

    /**
     * NGHIỆP VỤ: XUẤT KHO THEO LÔ (Batch)
     *
     * Giống issueGoods() nhưng bắt buộc xuất từ một lô cụ thể.
     * Sử dụng khi hàng hóa yêu cầu trace theo lô (dược phẩm, thực phẩm, hóa chất).
     *
     * Phương pháp tính giá: Specific ID — lấy đúng đơn giá của lô đó.
     *
     * RỦI RO: Nếu không theo dõi lô → không trace được hàng hư hỏng/ thu hồi → rủi ro pháp lý
     *
     * @param string $itemId ID mặt hàng
     * @param float $qty Số lượng xuất
     * @param string $batchCode Mã lô cần xuất
     * @param string $issueType Loại xuất: production|construction|sale
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @return array
     * @throws \InvalidArgumentException Nếu không tìm thấy mặt hàng hoặc tồn kho lô không đủ
     */
    public function issueFromBatch(string $itemId, float $qty, string $batchCode, string $issueType,
        string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $qty, $batchCode, $issueType, $reference, $createdBy) {
            $this->assertPeriodOpen();
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Không tìm thấy mặt hàng mã {$itemId}. Vui lòng kiểm tra lại mã vật tư/hàng hóa.");

            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM inventory_cost_layers WHERE item_id = ? AND batch_code = ? AND qty > 0");
            $stmt->execute([$itemId, $batchCode]);
            $available = (float)$stmt->fetchColumn();
            if (!$item->getAllowNegativeStock() && $available < $qty) {
                throw new \InvalidArgumentException("Tồn kho không đủ trong lô {$batchCode}. Hiện có {$available}, cần {$qty}.");
            }

            $stmt = $pdo->prepare("SELECT id, qty, unit_cost, addon_per_unit FROM inventory_cost_layers WHERE item_id = ? AND batch_code = ? AND qty > 0 ORDER BY created_at ASC");
            $stmt->execute([$itemId, $batchCode]);
            $remaining = $qty;
            $totalCost = 0.0;
            while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) && $remaining > 0) {
                $consume = min($row['qty'], $remaining);
                $layerUnitCost = (float)$row['unit_cost'] + (float)$row['addon_per_unit'];
                $totalCost += $consume * $layerUnitCost;
                $update = $pdo->prepare("UPDATE inventory_cost_layers SET qty = qty - ? WHERE id = ?");
                $update->execute([$consume, $row['id']]);
                $remaining -= $consume;
            }

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';
            $expenseCode = match($issueType) {
                'production' => '154',
                'construction' => '241',
                'sale' => '632',
                default => throw new \InvalidArgumentException("Loại xuất kho không hợp lệ: {$issueType}. Vui lòng kiểm tra lại."),
            };

            $txn = $this->journal->postEntry("Goods issue: {$item->getName()}", $reference, [
                ['account_code' => $expenseCode, 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => false],
            ], $createdBy, $issueType === 'construction');

            $item->setStockQty($item->getStockQty() - $qty);
            $this->itemRepo->save($item);

            return ['transaction_id' => $txn->getId(), 'total_cost' => $totalCost, 'qty' => $qty, 'batch_code' => $batchCode];
        });
    }

    /**
     * LẤY TỶ GIÁ NGOẠI TỆ: Tra cứu tỷ giá mới nhất cho giao dịch nhập kho ngoại tệ.
     *
     * Tỷ giá sử dụng là tỷ giá ghi sổ (theo Thông tư 200/2014/TT-BTC).
     *
     * RỦI RO: Nếu dùng sai tỷ giá → sai giá gốc hàng nhập → sai giá vốn khi xuất
     *
     * @param string $currencyCode Mã ngoại tệ (ví dụ: USD, EUR)
     * @return float Tỷ giá mới nhất
     * @throws \InvalidArgumentException Nếu không tìm thấy tỷ giá
     */
    public function getExchangeRate(string $currencyCode): float
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT rate FROM exchange_rates WHERE currency_code = ? ORDER BY rate_date DESC LIMIT 1");
        $stmt->execute([$currencyCode]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) throw new \InvalidArgumentException("Không tìm thấy tỷ giá cho ngoại tệ: {$currencyCode}. Vui lòng cập nhật tỷ giá.");
        return (float)$row['rate'];
    }

    /**
     * NGHIỆP VỤ: NHẬP KHO NGOẠI TỆ
     *
     * Hạch toán giống receiveGoods() nhưng quy đổi từ ngoại tệ sang VND theo tỷ giá tại ngày nhập.
     * Ghi nhận song song:
     *   - Giá trị VND hạch toán vào TK 15x
     *   - Thông tin ngoại tệ ghi vào fc_transactions để quản lý chênh lệch tỷ giá
     *
     * RỦI RO: Chênh lệch tỷ giá sau này nếu chưa thanh toán → ảnh hưởng BC02 (chi phí tài chính TK 635)
     *
     * @param string $itemId ID mặt hàng
     * @param float $qty Số lượng nhập
     * @param float $unitPriceFC Đơn giá ngoại tệ
     * @param array $addonCosts Chi phí mua kèm (ngoại tệ)
     * @param string $currencyCode Mã ngoại tệ
     * @param float|null $exchangeRate Tỷ giá (null = tự tra cứu)
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @return array
     * @throws \InvalidArgumentException Nếu không tìm thấy mặt hàng hoặc tỷ giá
     */
    public function receiveGoodsFC(string $itemId, float $qty, float $unitPriceFC,
        array $addonCosts, string $currencyCode, ?float $exchangeRate,
        string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $qty, $unitPriceFC, $addonCosts, $currencyCode, $exchangeRate, $reference, $createdBy) {
            $this->assertPeriodOpen();
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Không tìm thấy mặt hàng mã {$itemId}. Vui lòng kiểm tra lại mã vật tư/hàng hóa.");

            $rate = $exchangeRate ?? $this->getExchangeRate($currencyCode);
            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';

            $itemCostFC = $qty * $unitPriceFC;
            $totalAddonFC = array_sum(array_column($addonCosts, 'amount'));
            $totalFC = $itemCostFC + $totalAddonFC;
            $totalVND = $totalFC * $rate;
            $unitPriceVND = $unitPriceFC * $rate;

            $lines = [
                ['account_code' => $inventoryCode, 'amount' => $totalVND, 'is_debit' => true],
                ['account_code' => '331', 'amount' => $totalVND, 'is_debit' => false],
            ];

            $txn = $this->journal->postEntry("FC receipt: {$item->getName()}", $reference, $lines, $createdBy);

            $pdo = $this->getPdo();
            $pdo->prepare("INSERT INTO fc_transactions (transaction_id, account_code, currency_code, fc_amount, exchange_rate, vnd_amount, type, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$txn->getId(), $inventoryCode, $currencyCode, $totalFC, $rate, $totalVND, 'receipt', "FC purchase {$reference}"]);

            $item->setStockQty($item->getStockQty() + $qty);
            $this->itemRepo->save($item);

            $this->saveCostLayer($itemId, $qty, $unitPriceVND, 0, null);
            $this->calculateAndUpdateUnitCost($itemId);

            return ['transaction_id' => $txn->getId(), 'total_cost' => $totalVND, 'fc_total' => $totalFC, 'rate' => $rate];
        });
    }

    /**
     * NGHIỆP VỤ: KHÁCH HÀNG TRẢ LẠI HÀNG (NHẬP LẠI KHO)
     *
     * Hạch toán:
     *   Nợ 15x (Nhập lại kho)
     *   Có 632 (Giá vốn hàng bán) — giảm giá vốn tương ứng
     *
     * Giá nhập lại = giá bình quân hiện tại trong kho tại thời điểm trả lại.
     * Cost layer được tạo với đơn giá đó để theo dõi sau này.
     *
     * ẢNH HƯỞNG BCTC: Tăng tồn kho, giảm giá vốn (tăng lợi nhuận BC02)
     *
     * RỦI RO: Nếu dùng sai giá nhập lại → sai giá vốn kỳ sau → sai BC02
     *
     * @param string $itemId ID mặt hàng
     * @param float $qty Số lượng trả lại
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @return array
     * @throws \InvalidArgumentException Nếu không tìm thấy mặt hàng hoặc số lượng không hợp lệ
     */
    public function returnFromCustomer(string $itemId, float $qty, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $qty, $reference, $createdBy) {
            $this->assertPeriodOpen();
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Không tìm thấy mặt hàng mã {$itemId}. Vui lòng kiểm tra lại mã vật tư/hàng hóa.");
            if ($qty <= 0) throw new \InvalidArgumentException("Số lượng phải lớn hơn 0.");

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';

            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty),0) as qty, COALESCE(SUM(qty * unit_cost + qty * addon_per_unit),0) as val FROM inventory_cost_layers WHERE item_id = ?");
            $stmt->execute([$itemId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $avgUnitCost = ($row['qty'] > 0) ? $row['val'] / $row['qty'] : ($item->getPurchasePrice() ?: 0);
            $totalCost = $qty * $avgUnitCost;

            $txn = $this->journal->postEntry("Customer return: {$item->getName()}", $reference, [
                ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => '632', 'amount' => $totalCost, 'is_debit' => false],
            ], $createdBy);

            $item->setStockQty($item->getStockQty() + $qty);
            $this->itemRepo->save($item);

            $this->saveCostLayer($itemId, $qty, $avgUnitCost, 0, null);

            return ['transaction_id' => $txn->getId(), 'total_cost' => $totalCost, 'qty' => $qty];
        });
    }

    /**
     * NGHIỆP VỤ: TRẢ LẠI HÀNG CHO NHÀ CUNG CẤP
     *
     * Hạch toán:
     *   Nợ 331 (Phải trả người bán — giảm công nợ)
     *   Có 15x (Hàng tồn kho — xuất trả)
     *
     * Cost layer được xuất theo FIFO để giảm số lượng tồn kho tương ứng.
     * Giá trị hàng trả = giá bình quân hiện tại (không nhất thiết bằng giá mua ban đầu).
     *
     * ẢNH HƯỞNG BCTC: Giảm hàng tồn kho (BC01), giảm công nợ phải trả (BC01)
     *
     * RỦI RO: Nếu giá trả khác giá mua → chênh lệch ảnh hưởng giá vốn sau này
     *
     * @param string $itemId ID mặt hàng
     * @param float $qty Số lượng trả lại
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @return array
     * @throws \InvalidArgumentException Nếu không tìm thấy mặt hàng hoặc tồn kho không đủ
     */
    public function returnToSupplier(string $itemId, float $qty, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $qty, $reference, $createdBy) {
            $this->assertPeriodOpen();
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Không tìm thấy mặt hàng mã {$itemId}. Vui lòng kiểm tra lại mã vật tư/hàng hóa.");
            if ($qty <= 0) throw new \InvalidArgumentException("Số lượng phải lớn hơn 0.");
            if ($item->getStockQty() < $qty) {
                throw new \InvalidArgumentException("Tồn kho không đủ để nhập hàng trả lại. Hiện có {$item->getStockQty()}, cần {$qty}.");
            }

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';
            $pdo = $this->getPdo();

            // Get weighted average cost for the return
            $aggStmt = $pdo->prepare("SELECT SUM(qty) as total_qty, SUM(qty * (unit_cost + addon_per_unit)) as total_value FROM inventory_cost_layers WHERE item_id = ? AND qty > 0");
            $aggStmt->execute([$itemId]);
            $aggRow = $aggStmt->fetch(\PDO::FETCH_ASSOC);
            $avgUnitCost = ($aggRow['total_qty'] > 0) ? $aggRow['total_value'] / $aggRow['total_qty'] : $item->getPurchasePrice();
            $totalCost = $qty * $avgUnitCost;

            // Consume layers FIFO for physical tracking
            $stmt = $pdo->prepare("SELECT id, qty FROM inventory_cost_layers WHERE item_id = ? AND qty > 0 ORDER BY created_at ASC");
            $stmt->execute([$itemId]);
            $remaining = $qty;
            while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) && $remaining > 0) {
                $consume = min($row['qty'], $remaining);
                $pdo->prepare("UPDATE inventory_cost_layers SET qty = qty - ? WHERE id = ?")->execute([$consume, $row['id']]);
                $remaining -= $consume;
            }

            $txn = $this->journal->postEntry("Supplier return: {$item->getName()}", $reference, [
                ['account_code' => '331', 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => false],
            ], $createdBy);

            $item->setStockQty($item->getStockQty() - $qty);
            $this->itemRepo->save($item);
            $this->calculateAndUpdateUnitCost($itemId);

            $returnId = uniqid('sret_');
            $pdo->prepare("INSERT INTO supplier_returns (id, item_id, qty, unit_cost, total_cost, reference, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$returnId, $itemId, $qty, $avgUnitCost, $totalCost, $reference, $createdBy]);

            return ['return_id' => $returnId, 'transaction_id' => $txn->getId(), 'total_cost' => $totalCost, 'qty' => $qty];
        });
    }

    /**
     * NGHIỆP VỤ: XÓA SỔ HÀNG TỒN KHO (hỏng, hết hạn, lỗi thời, mất, khác)
     *
     * Hạch toán:
     *   Nợ TK chi phí (theo expenseAccount)
     *   Có 15x (Hàng tồn kho)
     *
     * Lý do xóa sổ được kiểm soát chặt (validReasons) để đảm bảo audit trail.
     * Chi phí xóa sổ có thể hạch toán vào 632 (giá vốn), 641 (bán hàng), 642 (QLDN) tùy bản chất.
     *
     * ẢNH HƯỞNG BCTC: Tăng chi phí (BC02), giảm hàng tồn kho (BC01)
     *
     * RỦI RO: Xóa sổ không đúng lý do → thuế không chấp nhận chi phí → tăng thuế TNDN
     * RỦI RO: Cần hóa đơn/chứng từ cho hàng hỏng/hết hạn để được khấu trừ thuế
     *
     * @param string $itemId ID mặt hàng
     * @param float $qty Số lượng xóa sổ
     * @param string $reason Lý do: damaged|expired|obsolete|lost|other
     * @param string $expenseAccount Tài khoản chi phí (632, 641, 642)
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @param string $notes Ghi chú bổ sung
     * @return array
     * @throws \InvalidArgumentException Nếu lý do không hợp lệ, không tìm thấy mặt hàng, hoặc tồn kho không đủ
     */
    public function writeOffGoods(string $itemId, float $qty, string $reason, string $expenseAccount, string $reference, string $createdBy, string $notes = ''): array
    {
        $validReasons = ['damaged', 'expired', 'obsolete', 'lost', 'other'];
        if (!in_array($reason, $validReasons)) {
            throw new \InvalidArgumentException("Lý do xuất hủy không hợp lệ: {$reason}. Lý do hợp lệ: " . implode(', ', $validReasons));
        }

        return $this->wrapInTransaction(function () use ($itemId, $qty, $reason, $expenseAccount, $reference, $createdBy, $notes) {
            $this->assertPeriodOpen();
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Không tìm thấy mặt hàng mã {$itemId}. Vui lòng kiểm tra lại mã vật tư/hàng hóa.");
            if ($qty <= 0) throw new \InvalidArgumentException("Số lượng phải lớn hơn 0.");
            if ($item->getStockQty() < $qty) {
                throw new \InvalidArgumentException("Tồn kho không đủ. Hiện có {$item->getStockQty()}, cần {$qty}.");
            }

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';
            $costResult = $this->consumeCostLayers($itemId, $qty, null);
            $totalCost = $costResult['total_cost'];

            $txn = $this->journal->postEntry("Write-off ({$reason}): {$item->getName()}", $reference, [
                ['account_code' => $expenseAccount, 'amount' => $totalCost, 'is_debit' => true],
                ['account_code' => $inventoryCode, 'amount' => $totalCost, 'is_debit' => false],
            ], $createdBy);

            $item->setStockQty($item->getStockQty() - $qty);
            $this->itemRepo->save($item);

            $woId = uniqid('wo_');
            $pdo = $this->getPdo();
            $pdo->prepare("INSERT INTO inventory_write_offs (id, item_id, qty, unit_cost, total_cost, reason, expense_account, reference, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$woId, $itemId, $qty, $totalCost / max($qty, 1), $totalCost, $reason, $expenseAccount, $reference, $notes, $createdBy]);

            return ['write_off_id' => $woId, 'transaction_id' => $txn->getId(), 'total_cost' => $totalCost, 'qty' => $qty];
        });
    }

    /**
     * NGHIỆP VỤ: KẾT CHUYỂN TỒN KHO CUỐI KỲ (Phương pháp kiểm kê định kỳ)
     *
     * Với phương pháp kiểm kê định kỳ, giá trị hàng tồn cuối kỳ được xác định bằng kiểm kê thực tế.
     * Giá vốn = Giá trị tồn đầu kỳ + Nhập trong kỳ - Giá trị tồn cuối kỳ (theo kiểm kê).
     *
     * Hạch toán:
     *   Nợ 632 (Giá vốn hàng bán), Có 15x (Giá trị hàng đã tiêu thụ)
     *
     * Xóa toàn bộ cost layer cũ và tạo layer mới với số lượng tồn cuối kỳ.
     *
     * ẢNH HƯỞNG BCTC: Ghi nhận giá vốn toàn bộ hàng đã bán trong kỳ (BC02 chỉ tiêu 24)
     *
     * RỦI RO: Kiểm kê sai → giá vốn sai → BC02 sai → Thuế TNDN sai
     *
     * @param string $itemId ID mặt hàng
     * @param float $closingQty Số lượng tồn cuối kỳ (theo kiểm kê)
     * @param float $closingUnitCost Đơn giá tồn cuối kỳ
     * @param string $reference Số chứng từ
     * @param string $createdBy ID người tạo
     * @return array
     * @throws \InvalidArgumentException Nếu không tìm thấy mặt hàng
     */
    public function closePeriodicInventory(string $itemId, float $closingQty, float $closingUnitCost, string $reference, string $createdBy): array
    {
        return $this->wrapInTransaction(function () use ($itemId, $closingQty, $closingUnitCost, $reference, $createdBy) {
            $this->assertPeriodOpen();
            $item = $this->itemRepo->findById($itemId);
            if (!$item) throw new \InvalidArgumentException("Không tìm thấy mặt hàng mã {$itemId}. Vui lòng kiểm tra lại mã vật tư/hàng hóa.");

            $pdo = $this->getPdo();
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty), 0) as total_qty, COALESCE(SUM(qty * unit_cost + qty * addon_per_unit), 0) as total_value FROM inventory_cost_layers WHERE item_id = ?");
            $stmt->execute([$itemId]);
            $layers = $stmt->fetch(\PDO::FETCH_ASSOC);
            $totalAvailableValue = (float)$layers['total_value'];

            $closingValue = $closingQty * $closingUnitCost;
            $cogsValue = max(0, $totalAvailableValue - $closingValue);

            $inventoryCode = $this->inventoryAccountMap[$item->getItemType()] ?? '152';

            if ($cogsValue > 0.01) {
                $txn = $this->journal->postEntry("Periodic close: {$item->getName()}", $reference, [
                    ['account_code' => '632', 'amount' => $cogsValue, 'is_debit' => true],
                    ['account_code' => $inventoryCode, 'amount' => $cogsValue, 'is_debit' => false],
                ], $createdBy);
                $txnId = $txn->getId();
            } else {
                $txnId = null;
            }

            // RỦI RO NGHIỆM TRỌNG: DELETE toàn bộ cost layer của item — nếu INSERT
            // saveCostLayer() phía sau thất bại (ví dụ: lỗi kết nối DB giữa chừng),
            // toàn bộ track record giá gốc của item này bị mất vĩnh viễn.
            // Biện pháp: wrapInTransaction() ở method cha đã bao bọc — rollback toàn bộ
            // nếu bất kỳ bước nào thất bại. Nhưng nếu DELETE thành công mà transaction
            // commit thất bại (network split), cost layer vẫn có thể bị mất.
            // TODO: Sử dụng soft-delete (status flag) thay vì DELETE để phục hồi khi cần.
            $pdo->prepare("DELETE FROM inventory_cost_layers WHERE item_id = ?")->execute([$itemId]);
            $this->saveCostLayer($itemId, $closingQty, $closingUnitCost, 0, null);

            $item->setStockQty($closingQty);
            $item->setPurchasePrice($closingUnitCost);
            $this->itemRepo->save($item);

            $periodId = uniqid('prd_');
            $periodStart = date('Y-m-01');
            $periodEnd = date('Y-m-t');
            $pdo->prepare("INSERT INTO periodic_inventory (id, item_id, period_start, period_end, opening_qty, opening_value, purchases_qty, purchases_value, closing_qty, closing_value, cogs, reference, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$periodId, $itemId, $periodStart, $periodEnd, 0, 0, 0, 0, $closingQty, $closingValue, $cogsValue, $reference, $createdBy]);

            return [
                'periodic_id' => $periodId,
                'total_available' => $totalAvailableValue,
                'closing_value' => $closingValue, 'cogs' => $cogsValue,
                'transaction_id' => $txnId,
            ];
        });
    }

    /**
     * NGHIỆP VỤ: CHỐT TỒN KHO CUỐI KỲ + ĐỐI CHIẾU SỔ CHI TIẾT VỚI TỔNG HỢP
     *
     * Bước 1: Snapshot toàn bộ cost layer để lưu trữ (audit trail không thể xóa).
     * Bước 2: Đối chiếu số dư sub-ledger (tổng giá trị cost layer) với số dư GL (ledger_entries).
     *
     * Nếu sub-ledger ≠ GL và chênh lệch > 10 VND → cảnh báo trong báo cáo reconciliation.
     * Chênh lệch thường do:
     *   - Bút toán tay sửa trực tiếp vào GL mà không qua InventoryService
     *   - Lỗi trong quá trình nhập/xuất trước đó
     *
     * RỦI RO: Chênh lệch SL ≠ GL không được phát hiện → sai BC01 và BC02
     *
     * @param int $periodId ID kỳ kế toán
     * @param string $periodCode Mã kỳ kế toán
     * @param string $startDate Ngày bắt đầu kỳ
     * @param string $endDate Ngày kết thúc kỳ
     * @param string $closedBy ID người chốt
     * @return array
     */
    public function closeInventoryForPeriod(int $periodId, string $periodCode, string $startDate, string $endDate, string $closedBy): array
    {
        $pdo = $this->getPdo();

        // Snapshot all cost layers
        $stmt = $pdo->query("SELECT cl.*, i.code as item_code, i.name as item_name, i.stock_qty
            FROM inventory_cost_layers cl JOIN items i ON i.id = cl.item_id WHERE cl.qty > 0");
        $layers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Verify sub-ledger = GL for inventory accounts
        $invAccounts = array_unique(array_values($this->inventoryAccountMap));
        $reconResults = [];
        $itemTypeMap = ['152' => 'material', '153' => 'tool', '155' => 'product', '156' => 'merchandise'];
        foreach ($invAccounts as $accCode) {
            $itemType = $itemTypeMap[$accCode] ?? 'other';
            $slStmt = $pdo->prepare("SELECT COALESCE(SUM(cl.qty * (cl.unit_cost + cl.addon_per_unit)), 0)
                FROM inventory_cost_layers cl JOIN items i ON i.id = cl.item_id
                WHERE i.item_type = ? AND cl.qty > 0");
            $slStmt->execute([$itemType]);
            $subLedgerVal = (float)$slStmt->fetchColumn();

            $glStmt = $pdo->prepare("SELECT COALESCE(SUM(le.amount * IF(le.is_debit, 1, -1)), 0)
                FROM ledger_entries le JOIN accounts a ON a.id = le.account_id
                WHERE a.code = ? AND le.created_at <= ?");
            $glStmt->execute([$accCode, $endDate . ' 23:59:59']);
            $glVal = (float)$glStmt->fetchColumn();

            $diff = abs($subLedgerVal - $glVal);
            $reconResults[$accCode] = ['sub_ledger' => $subLedgerVal, 'gl' => $glVal, 'diff' => $diff > 10];
        }

        // Store snapshot
        $snapshotId = uniqid('snap_');
        $pdo->prepare("INSERT INTO period_inventory_snapshots (id, period_id, period_code, data, created_by)
            VALUES (?, ?, ?, ?, ?)")
            ->execute([$snapshotId, $periodId, $periodCode, json_encode($layers), $closedBy]);

        return [
            'snapshot_id' => $snapshotId,
            'items_count' => count($layers),
            'reconciliation' => $reconResults,
        ];
    }

    /**
     * NGHIỆP VỤ: KHÔI PHỤC TỒN KHO TỪ SNAPSHOT (Rollback)
     *
     * Xóa toàn bộ cost layer hiện tại và khôi phục từ snapshot đã lưu khi chốt kỳ.
     * Việc này chỉ nên thực hiện khi phát hiện sai sót nghiêm trọng trong kỳ hiện tại.
     *
     * RỦI RO: Rollback sẽ mất toàn bộ thay đổi tồn kho sau snapshot
     * Biện pháp: Chỉ kế toán trưởng mới được thực hiện, phải có audit trail
     *
     * @param int $periodId ID kỳ kế toán cần rollback
     * @param string $rolledBackBy ID người thực hiện rollback
     * @return array
     * @throws \InvalidArgumentException Nếu không tìm thấy snapshot hoặc dữ liệu snapshot lỗi
     */
    public function rollbackInventoryForPeriod(int $periodId, string $rolledBackBy): array
    {
        $pdo = $this->getPdo();

        $stmt = $pdo->prepare("SELECT * FROM period_inventory_snapshots WHERE period_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$periodId]);
        $snapshot = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$snapshot) throw new \InvalidArgumentException("Không tìm thấy dữ liệu kiểm kê tồn kho cho kỳ {$periodId}. Vui lòng thực hiện kiểm kê cuối kỳ.");

        $layers = json_decode($snapshot['data'], true);
        if (!is_array($layers)) throw new \InvalidArgumentException("Dữ liệu kiểm kê tồn kho bị lỗi. Vui lòng kiểm tra lại.");

        // CẢNH BÁO RỦI RO CỰC KỲ NGHIÊM TRỌNG — CHỈ SỬ DỤNG KHI THẬT SỰ CẦN:
        // Thao tác này XÓA TOÀN BỘ cost layer hiện tại và khôi phục từ snapshot cũ.
        // Hậu quả:
        // 1. Mất tất cả giao dịch nhập/xuất kho đã thực hiện SAU snapshot
        // 2. Số dư tài khoản GL (ledger_entries) KHÔNG được rollback → lệch sub-ledger vs GL
        // 3. Báo cáo tài chính BC01/BC02 không khớp → phải làm bút toán điều chỉnh thủ công
        // 4. Audit trail bị gián đoạn — kiểm toán viên sẽ đặt câu hỏi
        //
        // Biện pháp kiểm soát: Chỉ Kế toán trưởng được gọi. AuditLogger ghi nhận mỗi lần.
        // Sau rollback, bắt buộc kiểm tra: sub-ledger vs GL, trial balance, BC01 số dư đầu kỳ.
        $this->wrapInTransaction(function () use ($pdo, $layers, $periodId, $rolledBackBy, $snapshot) {
            // Delete current cost layers
            $pdo->exec("DELETE FROM inventory_cost_layers");

            // Restore from snapshot
            $insert = $pdo->prepare("INSERT INTO inventory_cost_layers (id, item_id, warehouse_id, batch_code, expiry_date, qty, unit_cost, addon_per_unit, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($layers as $l) {
                $insert->execute([
                    $l['id'], $l['item_id'], $l['warehouse_id'], $l['batch_code'], $l['expiry_date'],
                    $l['qty'], $l['unit_cost'], $l['addon_per_unit'], $l['created_at']
                ]);
            }

            // Restore stock quantities
            $updateStock = $pdo->prepare("UPDATE items SET stock_qty = ? WHERE id = ?");
            foreach ($layers as $l) {
                $updateStock->execute([$l['stock_qty'], $l['item_id']]);
            }
        });

        return ['message' => 'Inventory rolled back', 'items_restored' => count($layers)];
    }

    /**
     * TÍNH GIÁ BÌNH QUÂN GIA QUYỀN: Cập nhật đơn giá cho item sau mỗi lần nhập.
     *
     * Công thức: ĐGBQ = (Tổng giá trị cost layer) / (Tổng số lượng cost layer)
     *
     * Nếu phương pháp định giá là 'weighted_avg', toàn bộ cost layer của item
     * được cập nhật về cùng một đơn giá bình quân (revalue remaining layers).
     * Với phương pháp FIFO, chỉ cập nhật purchasePrice item — không thay đổi layer.
     *
     * RỦI RO: Nếu tính sai giá bình quân → sai định giá tồn kho → sai BC01
     *
     * @param string $itemId ID mặt hàng
     * @return void
     */
    public function calculateAndUpdateUnitCost(string $itemId): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("SELECT SUM(qty) as total_qty, SUM(qty * unit_cost + addon_per_unit * qty) as total_cost FROM inventory_cost_layers WHERE item_id = ?");
        $stmt->execute([$itemId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row && $row['total_qty'] > 0) {
            $avg = $row['total_cost'] / $row['total_qty'];
            $item = $this->itemRepo->findById($itemId);
            if ($item) {
                $item->setPurchasePrice($avg);
                $this->itemRepo->save($item);
            }

            // For weighted average items, revalue remaining layers to the new avg
            $methodStmt = $pdo->prepare("SELECT COALESCE(vm.code, 'fifo') FROM items i LEFT JOIN valuation_methods vm ON vm.id = i.valuation_method_id WHERE i.id = ?");
            $methodStmt->execute([$itemId]);
            if ($methodStmt->fetchColumn() === 'weighted_avg') {
                $pdo->prepare("UPDATE inventory_cost_layers SET unit_cost = ?, addon_per_unit = 0 WHERE item_id = ? AND qty > 0")
                    ->execute([$avg, $itemId]);
            }
        }
    }

    /**
     * CẬP NHẬT TỒN KHO + COST LAYER CHO MỘT MẶT HÀNG
     *
     * Được gọi từ GoodsReceiptService khi ghi sổ phiếu nhập kho.
     * Không wrap transaction — caller đảm bảo transaction.
     *
     * @param string $itemId ID mặt hàng
     * @param float $qty Số lượng nhập
     * @param float $unitPrice Đơn giá nhập
     * @param string|null $batchCode Mã lô (FIFO tracking)
     * @param string|null $expiryDate Hạn sử dụng
     * @return void
     * @throws \InvalidArgumentException Nếu không tìm thấy mặt hàng
     */
    public function updateStockAndCostLayer(string $itemId, float $qty, float $unitPrice, ?string $batchCode = null, ?string $expiryDate = null): void
    {
        $item = $this->itemRepo->findById($itemId);
        if (!$item) {
            throw new \InvalidArgumentException("Không tìm thấy mặt hàng: {$itemId}");
        }
        $item->setStockQty(($item->getStockQty() ?? 0) + $qty);
        $this->itemRepo->save($item);
        $this->saveCostLayer($itemId, $qty, $unitPrice, 0, null, $batchCode, $expiryDate);
        $this->calculateAndUpdateUnitCost($itemId);
    }

    /**
     * BÁO CÁO: PHÂN TÍCH TUỔI TỒN KHO
     *
     * Phân loại hàng tồn kho theo thời gian lưu kho (0-30, 31-60, 61-90, 91-180, 180+ ngày).
     * Hữu ích để xác định hàng chậm luân chuyển, hàng có nguy cơ giảm giá hoặc hết hạn.
     *
     * Kế toán quản trị sử dụng báo cáo này để:
     *   - Đánh giá hiệu quả quản lý kho
     *   - Trích lập dự phòng cho hàng tồn lâu
     *   - Đề xuất thanh lý hàng chậm luân chuyển
     *
     * @param string|null $itemId Lọc theo mặt hàng (null = tất cả)
     * @param string|null $warehouseId Lọc theo kho (null = tất cả)
     * @return array
     */
    public function getAgingReport(?string $itemId = null, ?string $warehouseId = null): array
    {
        $pdo = $this->getPdo();
        $sql = "SELECT i.id, i.code, i.name, i.unit,
            cl.qty, cl.unit_cost, cl.addon_per_unit,
            DATEDIFF(NOW(), cl.created_at) as age_days
            FROM inventory_cost_layers cl
            JOIN items i ON i.id = cl.item_id
            WHERE cl.qty > 0";
        $params = [];
        if ($itemId) { $sql .= " AND cl.item_id = ?"; $params[] = $itemId; }
        if ($warehouseId) { $sql .= " AND cl.warehouse_id = ?"; $params[] = $warehouseId; }
        $sql .= " ORDER BY i.code, cl.created_at ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $buckets = ['0-30' => 0, '31-60' => 0, '61-90' => 0, '91-180' => 0, '180+' => 0];
        $report = [];
        foreach ($rows as $r) {
            $key = $r['id'];
            if (!isset($report[$key])) {
                $report[$key] = ['code' => $r['code'], 'name' => $r['name'], 'unit' => $r['unit'],
                    'total_qty' => 0, 'total_value' => 0] + $buckets;
            }
            $val = (float)$r['qty'] * ((float)$r['unit_cost'] + (float)$r['addon_per_unit']);
            $age = (int)$r['age_days'];
            $bucket = match(true) { $age <= 30 => '0-30', $age <= 60 => '31-60', $age <= 90 => '61-90', $age <= 180 => '91-180', default => '180+' };
            $report[$key][$bucket] += $val;
            $report[$key]['total_qty'] += (float)$r['qty'];
            $report[$key]['total_value'] += $val;
        }
        return ['buckets' => array_keys($buckets), 'items' => array_values($report)];
    }

    /**
     * BÁO CÁO: VÒNG QUAY HÀNG TỒN KHO (Inventory Turnover Ratio)
     *
     * Công thức: Vòng quay HTK = Giá vốn hàng bán / Giá trị HTK bình quân
     * Số ngày tồn kho = 365 / Vòng quay HTK
     *
     * Giá vốn (COGS) lấy từ TK 632 trong kỳ.
     * Giá trị HTK bình quân = (Đầu kỳ + Cuối kỳ) / 2 từ cost layer.
     *
     * Chỉ số này giúp đánh giá hiệu quả quản lý kho:
     *   - Cao: Hàng bán nhanh, quản lý tốt
     *   - Thấp: Hàng tồn đọng, cần xem xét trích lập dự phòng
     *
     * RỦI RO: Nếu tính sai COGS → vòng quay sai → quyết định quản trị sai
     *
     * @param string $periodStart Ngày bắt đầu kỳ (Y-m-d)
     * @param string $periodEnd Ngày kết thúc kỳ (Y-m-d)
     * @param string|null $itemId Lọc theo mặt hàng (null = tất cả)
     * @return array
     */
    public function getTurnoverRatio(string $periodStart, string $periodEnd, ?string $itemId = null): array
    {
        $pdo = $this->getPdo();

        // COGS from transactions in period
        $cogsSql = "SELECT COALESCE(SUM(le.amount), 0) FROM ledger_entries le
            JOIN transactions t ON t.id = le.transaction_id
            JOIN accounts a ON a.id = le.account_id
            WHERE a.code = '632' AND le.is_debit = 1
            AND t.created_at BETWEEN ? AND ?";
        $cogsParams = [$periodStart, $periodEnd];
        if ($itemId) {
            $item = $this->itemRepo->findById($itemId);
            $itemName = $item ? $item->getName() : $itemId;
            $cogsSql .= " AND t.description LIKE ?";
            $cogsParams[] = "%{$itemName}%";
        }
        $stmt = $pdo->prepare($cogsSql);
        $stmt->execute($cogsParams);
        $cogs = (float)$stmt->fetchColumn();

        // Opening inventory value from periodic_inventory or cost layers at period start
        $openStmt = $pdo->prepare("SELECT COALESCE(SUM(cl.qty * (cl.unit_cost + cl.addon_per_unit)), 0)
            FROM inventory_cost_layers cl WHERE cl.created_at < ? AND cl.qty > 0");
        $openStmt->execute([$periodStart]);
        $openingValue = (float)$openStmt->fetchColumn();

        // Closing inventory value
        $closeStmt = $pdo->prepare("SELECT COALESCE(SUM(cl.qty * (cl.unit_cost + cl.addon_per_unit)), 0)
            FROM inventory_cost_layers cl WHERE cl.created_at <= ? AND cl.qty > 0");
        $closeStmt->execute([$periodEnd]);
        $closingValue = (float)$closeStmt->fetchColumn();

        $avgInventory = ($openingValue + $closingValue) / 2;
        $turnover = $avgInventory > 0 ? round($cogs / $avgInventory, 2) : 0;
        $daysOutstanding = $turnover > 0 ? round(365 / $turnover, 1) : 0;

        return [
            'period_start' => $periodStart, 'period_end' => $periodEnd,
            'total_cogs' => $cogs,
            'opening_inventory' => $openingValue,
            'closing_inventory' => $closingValue,
            'avg_inventory' => $avgInventory,
            'turnover_ratio' => $turnover,
            'days_outstanding' => $daysOutstanding,
        ];
    }

    /**
     * BÁO CÁO: ĐỊNH GIÁ HÀNG TỒN KHO THEO PHƯƠNG PHÁP TÍNH GIÁ
     *
     * Hiển thị chi tiết tồn kho theo:
     *   - Phương pháp định giá (FIFO, Bình quân, Specific ID)
     *   - Số lượng và giá trị đầu kỳ, nhập trong kỳ, cuối kỳ
     *
     * Dữ liệu lấy từ cost layer, phân kỳ dựa trên created_at của layer.
     *
     * Hữu ích cho kiểm toán viên đối chiếu số dư tồn kho cuối kỳ (BC01)
     * và kiểm tra tính nhất quán của phương pháp tính giá.
     *
     * @param string|null $itemId Lọc theo mặt hàng (null = tất cả)
     * @param string|null $warehouseId Lọc theo kho (null = tất cả)
     * @param string|null $periodStart Ngày bắt đầu kỳ (Y-m-d)
     * @param string|null $periodEnd Ngày kết thúc kỳ (Y-m-d)
     * @return array
     */
    public function getValuationReport(?string $itemId = null, ?string $warehouseId = null,
        ?string $periodStart = null, ?string $periodEnd = null): array
    {
        $pdo = $this->getPdo();
        $periodStart ??= date('Y-m-01');
        $periodEnd ??= date('Y-m-t');

        $sql = "SELECT i.id, i.code, i.name, i.unit, COALESCE(vm.code, 'fifo') as method,
            cl.id as layer_id, cl.qty, cl.unit_cost, cl.addon_per_unit, cl.created_at
            FROM inventory_cost_layers cl
            JOIN items i ON i.id = cl.item_id
            LEFT JOIN valuation_methods vm ON vm.id = i.valuation_method_id
            WHERE cl.qty > 0";
        $params = [];
        if ($itemId) { $sql .= " AND cl.item_id = ?"; $params[] = $itemId; }
        if ($warehouseId) { $sql .= " AND cl.warehouse_id = ?"; $params[] = $warehouseId; }
        $sql .= " ORDER BY i.code, cl.created_at ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $items = [];
        foreach ($rows as $r) {
            $key = $r['id'];
            if (!isset($items[$key])) {
                $items[$key] = ['code' => $r['code'], 'name' => $r['name'], 'unit' => $r['unit'],
                    'method' => $r['method'], 'opening_qty' => 0, 'opening_value' => 0,
                    'receipts_qty' => 0, 'receipts_value' => 0,
                    'issues_qty' => 0, 'issues_value' => 0,
                    'closing_qty' => 0, 'closing_value' => 0];
            }
            $val = (float)$r['qty'] * ((float)$r['unit_cost'] + (float)$r['addon_per_unit']);
            $created = $r['created_at'];
            if ($created < $periodStart) {
                $items[$key]['opening_qty'] += (float)$r['qty'];
                $items[$key]['opening_value'] += $val;
            } elseif ($created <= $periodEnd) {
                $items[$key]['receipts_qty'] += (float)$r['qty'];
                $items[$key]['receipts_value'] += $val;
            }
            $items[$key]['closing_qty'] += (float)$r['qty'];
            $items[$key]['closing_value'] += $val;
        }

        return ['period_start' => $periodStart, 'period_end' => $periodEnd,
            'items' => array_values($items)];
    }

    /**
     * GHI NHẬN COST LAYER: Lưu một lớp giá trị cho lô hàng nhập kho.
     *
     * Mỗi lần nhập kho → tạo một cost layer mới với đơn giá riêng.
     * Khi xuất kho, consumeCostLayers() sẽ lấy từ các layer cũ nhất trước (FIFO).
     *
     * Thông số lưu trữ:
     *   - unit_cost: Đơn giá mua (chưa gồm chi phí mua)
     *   - addon_per_unit: Chi phí mua phân bổ cho mỗi đơn vị
     *   - batch_code / expiry_date: Theo dõi lô và hạn dùng (nếu có)
     *
     * RỦI RO: Nếu không lưu chi phí mua riêng → giá vốn xuất kho thấp hơn thực tế
     *
     * @param string $itemId ID mặt hàng
     * @param float $qty Số lượng nhập
     * @param float $unitCost Đơn giá mua (chưa gồm chi phí mua)
     * @param float $addonPerUnit Chi phí mua phân bổ cho mỗi đơn vị
     * @param string|null $warehouseId ID kho (null = kho tổng hợp)
     * @param string|null $batchCode Mã lô (FIFO tracking)
     * @param string|null $expiryDate Hạn sử dụng
     * @return void
     */
    private function saveCostLayer(string $itemId, float $qty, float $unitCost, float $addonPerUnit, ?string $warehouseId,
        ?string $batchCode = null, ?string $expiryDate = null): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare(
            "INSERT INTO inventory_cost_layers (id, item_id, warehouse_id, batch_code, expiry_date, qty, unit_cost, addon_per_unit, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([uniqid('cst_'), $itemId, $warehouseId, $batchCode, $expiryDate, $qty, $unitCost, $addonPerUnit]);
    }

    /**
     * XUẤT COST LAYER: Xác định giá vốn khi xuất kho theo phương pháp định giá.
     *
     * Hỗ trợ 3 phương pháp:
     *   1. Specific ID: Xuất đúng lô chỉ định (batchCode bắt buộc)
     *   2. Weighted Average: Tính tổng giá trị / tổng số lượng → đơn giá bình quân
     *   3. FIFO (mặc định): Xuất từ layer cũ nhất trước — created_at ASC
     *
     * Với FIFO, mỗi layer giảm dần số lượng cho đến hết, giữ nguyên đơn giá gốc của layer đó.
     * Với Weighted Average, chỉ giảm số lượng layer, giá trị = số lượng × giá bình quân.
     *
     * RỦI RO: Nếu âm kho (qty > available) → sai giá vốn do không đủ layer để xuất
     * RỦI RO: Đổi phương pháp tính giá giữa kỳ → sai số liệu so sánh BC02
     *
     * @param string $itemId ID mặt hàng
     * @param float $qty Số lượng xuất
     * @param string|null $warehouseId ID kho (null = kho tổng hợp)
     * @param string|null $batchCode Mã lô (cho Specific ID)
     * @return array ['total_cost' => float, 'remaining' => float]
     * @throws \InvalidArgumentException Nếu Specific ID yêu cầu batchCode nhưng không có
     */
    private function consumeCostLayers(string $itemId, float $qty, ?string $warehouseId, ?string $batchCode = null): array
    {
        $pdo = $this->getPdo();

        $methodStmt = $pdo->prepare("SELECT COALESCE(vm.code, 'fifo') FROM items i LEFT JOIN valuation_methods vm ON vm.id = i.valuation_method_id WHERE i.id = ?");
        $methodStmt->execute([$itemId]);
        $methodCode = $methodStmt->fetchColumn();

        // PHƯƠNG PHÁP TÍNH GIÁ XUẤT KHO:
        //   1. Specific ID: Lấy đơn giá từ lô cụ thể — dùng cho hàng yêu cầu trace (dược, thực phẩm)
        //   2. Weighted Average: Tính đơn giá bình quân = Tổng giá trị / Tổng số lượng
        //   3. FIFO (mặc định): Xuất từ layer cũ nhất — đúng bản chất dòng chảy vật tư
        //
        // RỦI RO CONCURRENCY: Dưới concurrent, 2 request xuất kho cùng lúc có thể đọc cùng
        // một cost layer, dẫn đến double-consumption (cùng layer bị trừ 2 lần).
        // Biện pháp: SELECT ... FOR UPDATE trên cost layer bị ảnh hưởng (cần bổ sung).
        // Hậu quả nếu double-consumption: giá vốn (632) ghi nhận sai (cao hơn thực tế),
        // số lượng tồn kho âm oan, BC02 chỉ tiêu 24 sai.
        if ($methodCode === 'specific_id') {
            if (!$batchCode) {
                throw new \InvalidArgumentException("Phương pháp tính giá theo từng lô yêu cầu nhập mã lô.");
            }
            $stmt = $pdo->prepare("SELECT id, qty, unit_cost, addon_per_unit FROM inventory_cost_layers WHERE item_id = ? AND batch_code = ? AND qty > 0 ORDER BY created_at ASC");
            $stmt->execute([$itemId, $batchCode]);
            $remaining = $qty;
            $totalCost = 0.0;
            while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) && $remaining > 0) {
                $consume = min($row['qty'], $remaining);
                $layerUnitCost = (float)$row['unit_cost'] + (float)$row['addon_per_unit'];
                $totalCost += $consume * $layerUnitCost;
                $update = $pdo->prepare("UPDATE inventory_cost_layers SET qty = qty - ? WHERE id = ?");
                $update->execute([$consume, $row['id']]);
                $remaining -= $consume;
            }
            return ['total_cost' => $totalCost, 'remaining' => $remaining];
        }

        if ($methodCode === 'weighted_avg') {
            $aggSql = "SELECT SUM(qty) as total_qty, SUM(qty * (unit_cost + addon_per_unit)) as total_value FROM inventory_cost_layers WHERE item_id = ? AND qty > 0";
            $aggParams = [$itemId];
            if ($warehouseId !== null) {
                $aggSql .= " AND warehouse_id = ?";
                $aggParams[] = $warehouseId;
            } else {
                $aggSql .= " AND warehouse_id IS NULL";
            }
            $aggStmt = $pdo->prepare($aggSql);
            $aggStmt->execute($aggParams);
            $aggRow = $aggStmt->fetch(\PDO::FETCH_ASSOC);
            $totalQty = (float)$aggRow['total_qty'];
            $waUnitCost = $totalQty > 0 ? $aggRow['total_value'] / $totalQty : 0;
            $consumeQty = min($qty, $totalQty);
            $remaining = $qty;
            $whereClause = $warehouseId ? "warehouse_id = ? AND" : "warehouse_id IS NULL AND";
            $params = $warehouseId ? [$itemId, $warehouseId] : [$itemId];
            $stmt = $pdo->prepare("SELECT id, qty FROM inventory_cost_layers WHERE item_id = ? AND {$whereClause} qty > 0 ORDER BY created_at ASC");
            $stmt->execute($params);
            while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) && $remaining > 0) {
                $consume = min($row['qty'], $remaining);
                $update = $pdo->prepare("UPDATE inventory_cost_layers SET qty = qty - ? WHERE id = ?");
                $update->execute([$consume, $row['id']]);
                $remaining -= $consume;
            }
            $totalCost = $consumeQty * $waUnitCost;
            return ['total_cost' => $totalCost, 'remaining' => $remaining];
        }

        // Default: FIFO
        if ($warehouseId !== null) {
            $stmt = $pdo->prepare("SELECT id, qty, unit_cost, addon_per_unit FROM inventory_cost_layers WHERE item_id = ? AND warehouse_id = ? AND qty > 0 ORDER BY created_at ASC");
            $stmt->execute([$itemId, $warehouseId]);
        } else {
            $stmt = $pdo->prepare("SELECT id, qty, unit_cost, addon_per_unit FROM inventory_cost_layers WHERE item_id = ? AND warehouse_id IS NULL AND qty > 0 ORDER BY created_at ASC");
            $stmt->execute([$itemId]);
        }
        $remaining = $qty;
        $totalCost = 0.0;
        while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) && $remaining > 0) {
            $consume = min($row['qty'], $remaining);
            $layerUnitCost = (float)$row['unit_cost'] + (float)$row['addon_per_unit'];
            $totalCost += $consume * $layerUnitCost;
            $update = $pdo->prepare("UPDATE inventory_cost_layers SET qty = qty - ? WHERE id = ?");
            $update->execute([$consume, $row['id']]);
            $remaining -= $consume;
        }
        return ['total_cost' => $totalCost, 'remaining' => $remaining];
    }

    /**
     * Lấy PDO connection dùng trong class.
     *
     * @return \PDO
     */
    private function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
