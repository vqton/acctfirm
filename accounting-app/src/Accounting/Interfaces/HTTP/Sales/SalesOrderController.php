<?php
namespace Accounting\Interfaces\HTTP\Sales;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Repository\SalesOrderRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Đơn bán hàng (Sales Orders)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý đơn bán hàng, báo giá
 *   - Theo dõi trạng thái đơn hàng, xác nhận, giao hàng
 *   - Tích hợp với xuất kho, ghi nhận doanh thu
 *
 * API endpoints:
 *   GET    /api/sales-orders — Danh sách
 *   POST   /api/sales-orders — Tạo mới
 *   GET    /api/sales-orders/{id} — Chi tiết
 *   PUT    /api/sales-orders/{id} — Cập nhật
 *   DELETE /api/sales-orders/{id} — Xoá
 *
 * Rủi ro:
 *   - Ghi nhận doanh thu sai thời điểm
 *
 * Tích hợp:
 *   - ArController xử lý công nợ
 *   - InventoryController xuất kho
 */
class SalesOrderController
{
    use CrudControllerTrait;

    /**
     * @param SalesOrderRepositoryInterface $repository
     */
    public function __construct(SalesOrderRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'sales_orders';
    }
}
