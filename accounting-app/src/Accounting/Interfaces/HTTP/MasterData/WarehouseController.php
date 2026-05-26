<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\Warehouse;
use Accounting\Domain\Repository\WarehouseRepositoryInterface;

use \Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Danh mục Kho (Warehouse Master)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD kho hàng, địa điểm lưu trữ hàng hóa
 *   - Quản lý thông tin: mã kho, tên kho, địa chỉ
 *   - Cơ sở để quản lý tồn kho chi tiết theo kho
 *
 * API endpoints:
 *   (Sử dụng CrudControllerTrait — CRUD chuẩn)
 *
 * Rủi ro:
 *   - Xóa kho đang có hàng → mất thông tin tồn kho chi tiết
 *   - Sai thông tin kho → nhập/xuất sai vị trí
 *
 * Tích hợp:
 *   - TransferController chuyển hàng giữa các kho
 *   - ReceiptController và IssueController tham chiếu warehouse_id
 *   - InventoryReportController báo cáo tồn kho theo kho
 */
class WarehouseController
{
    use CrudControllerTrait;

    private WarehouseRepositoryInterface $repo;
    public function __construct(WarehouseRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'wh_'; }

    protected function createEntity(array $data): object
    {
        return new Warehouse($data['id'], $data['code'], $data['name'], $data['address'] ?? null);
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['address'])) $entity->setAddress($data['address']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}
