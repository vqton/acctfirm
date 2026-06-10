<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Model\Uom;
use Accounting\Domain\Repository\UomRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Đơn vị tính (UoM)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách đơn vị tính (cái, kg, thùng...)
 *   - Quản lý tỷ lệ quy đổi giữa các đơn vị
 *
 * API endpoints:
 *   GET    /api/uoms — Danh sách
 *   POST   /api/uoms — Tạo mới
 *   GET    /api/uoms/{id} — Chi tiết
 *   PUT    /api/uoms/{id} — Cập nhật
 *   DELETE /api/uoms/{id} — Xoá
 *
 * Tích hợp:
 *   - ItemController gán UoM cho hàng hoá
 */
class UomController
{
    use CrudControllerTrait;

    /**
     * @param UomRepositoryInterface $repository
     */
    public function __construct(UomRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'uoms';
    }

    protected function repo()
    {
        return $this->repository;
    }

    protected function idPrefix(): string
    {
        return 'uom_';
    }

    protected function createEntity(array $data): object
    {
        return new Uom(
            id: $data['id'] ?? uniqid('uom_'),
            code: $data['code'] ?? '',
            name: $data['name'] ?? ''
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}
