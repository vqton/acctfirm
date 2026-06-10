<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Repository\WarehouseRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Kho
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách kho
 *   - Mỗi kho có địa chỉ, người phụ trách
 *
 * API endpoints:
 *   GET    /api/warehouses — Danh sách
 *   POST   /api/warehouses — Tạo mới
 *   GET    /api/warehouses/{id} — Chi tiết
 *   PUT    /api/warehouses/{id} — Cập nhật
 *   DELETE /api/warehouses/{id} — Xoá
 *
 * Tích hợp:
 *   - Mọi nghiệp vụ nhập/xuất/chuyển kho
 */
class WarehouseController
{
    use CrudControllerTrait;

    /**
     * @param WarehouseRepositoryInterface $repository
     */
    public function __construct(WarehouseRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'warehouses';
    }
}
