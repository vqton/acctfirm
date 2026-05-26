<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Model\Item;
use Accounting\Domain\Repository\ItemRepositoryInterface;

use \Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Danh mục Hàng hóa - Vật tư (Item Master)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh mục hàng hóa, nguyên vật liệu, thành phẩm, công cụ
 *   - Quản lý thông tin: mã, tên, loại, đơn vị tính, giá mua/bán
 *   - Theo dõi tồn kho tối thiểu (min_stock) để cảnh báo tái đặt hàng
 *   - Gán phương pháp tính giá (valuation_method_id)
 *
 * API endpoints:
 *   (Sử dụng CrudControllerTrait — CRUD chuẩn)
 *   GET    /api/items       — Danh sách items
 *   GET    /api/items/{id}  — Chi tiết item
 *   POST   /api/items       — Tạo item mới
 *   PUT    /api/items/{id}  — Cập nhật item
 *   DELETE /api/items/{id}  — Xóa item
 *
 * Rủi ro:
 *   - Xóa item đã có giao dịch → mất dữ liệu lịch sử (chỉ soft delete)
 *   - Mã item trùng lặp → nhầm lẫn trong nhập/xuất kho
 *   - Sai phương pháp tính giá → sai giá vốn hàng bán
 *
 * Tích hợp:
 *   - ItemRepository được dùng bởi tất cả Inventory controllers
 *   - ReceiptController, IssueController tham chiếu item_id
 *   - ValuationMethodController xác định phương pháp tính giá
 */
class ItemController
{
    use CrudControllerTrait;

    private ItemRepositoryInterface $repo;
    public function __construct(ItemRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'itm_'; }

    protected function createEntity(array $data): object
    {
        return new Item(
            $data['id'], $data['code'], $data['name'],
            $data['item_type'] ?? 'material', $data['unit'] ?? 'cai',
            (float)($data['purchase_price'] ?? 0), (float)($data['sale_price'] ?? 0),
            (float)($data['stock_qty'] ?? 0), (float)($data['min_stock'] ?? 0),
            $data['description'] ?? null, $data['valuation_method_id'] ?? null
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['item_type'])) $entity->setItemType($data['item_type']);
        if (isset($data['unit'])) $entity->setUnit($data['unit']);
        if (isset($data['purchase_price'])) $entity->setPurchasePrice((float)$data['purchase_price']);
        if (isset($data['sale_price'])) $entity->setSalePrice((float)$data['sale_price']);
        if (isset($data['stock_qty'])) $entity->setStockQty((float)$data['stock_qty']);
        if (isset($data['min_stock'])) $entity->setMinStock((float)$data['min_stock']);
        if (isset($data['description'])) $entity->setDescription($data['description']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}
