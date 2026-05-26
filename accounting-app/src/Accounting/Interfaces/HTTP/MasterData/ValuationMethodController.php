<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\ValuationMethod;
use Accounting\Domain\Repository\ValuationMethodRepositoryInterface;

use \Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Phương pháp Tính giá Hàng tồn kho (Valuation Method)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD phương pháp tính giá xuất kho
 *   - Các phương pháp: FIFO (nhập trước xuất trước), Bình quân gia quyền
 *   - Gán cho từng item để tính giá vốn (632)
 *   - Tuân thủ VAS 02 — Hàng tồn kho
 *
 * API endpoints:
 *   (Sử dụng CrudControllerTrait — CRUD chuẩn)
 *
 * Rủi ro:
 *   - Thay đổi phương pháp tính giá giữa kỳ → sai giá vốn
 *   - FIFO cho hàng hóa có thời hạn (thực phẩm, dược phẩm)
 *   - Bình quân gia quyền phù hợp cho hàng đồng nhất
 *   - Xác định sai phương pháp → sai BC02 chỉ tiêu 24 (giá vốn)
 *
 * Tích hợp:
 *   - ItemController gán valuation_method_id
 *   - InventoryService tính giá xuất dựa trên phương pháp
 *   - InventoryReportController báo cáo định giá
 */
class ValuationMethodController
{
    use CrudControllerTrait;

    private ValuationMethodRepositoryInterface $repo;
    public function __construct(ValuationMethodRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'vm_'; }

    protected function createEntity(array $data): object
    {
        return new ValuationMethod($data['id'], $data['code'], $data['name'], $data['description'] ?? null);
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['description'])) $entity->setDescription($data['description']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}
