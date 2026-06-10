<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Repository\SupplierRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Nhà cung cấp
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách nhà cung cấp
 *   - Theo dõi công nợ, lịch sử mua hàng
 *   - Liên kết với TK 331 (Phải trả người bán)
 *
 * API endpoints:
 *   GET    /api/suppliers — Danh sách
 *   POST   /api/suppliers — Tạo mới
 *   GET    /api/suppliers/{id} — Chi tiết
 *   PUT    /api/suppliers/{id} — Cập nhật
 *   DELETE /api/suppliers/{id} — Xoá
 *
 * Rủi ro:
 *   - R005: Xoá NCC đang có công nợ
 *
 * Tích hợp:
 *   - ApService theo dõi công nợ NCC
 *   - ApController xử lý giao dịch
 */
class SupplierController
{
    use CrudControllerTrait;

    /**
     * @param SupplierRepositoryInterface $repository
     */
    public function __construct(SupplierRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'suppliers';
    }
}
