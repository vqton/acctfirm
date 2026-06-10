<?php
namespace Accounting\Interfaces\HTTP\Purchase;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Repository\ProcurementRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Mua hàng (Procurement)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý quy trình mua hàng: đề nghị mua -> đặt hàng -> nhận hàng
 *   - Theo dõi trạng thái đơn hàng, tiến độ giao hàng
 *
 * API endpoints:
 *   GET    /api/procurements — Danh sách
 *   POST   /api/procurements — Tạo mới
 *   GET    /api/procurements/{id} — Chi tiết
 *   PUT    /api/procurements/{id} — Cập nhật
 *   DELETE /api/procurements/{id} — Xoá
 *
 * Tích hợp:
 *   - SupplierController quản lý NCC
 *   - ApController xử lý công nợ
 */
class ProcurementController
{
    use CrudControllerTrait;

    /**
     * @param ProcurementRepositoryInterface $repository
     */
    public function __construct(ProcurementRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'procurements';
    }
}
