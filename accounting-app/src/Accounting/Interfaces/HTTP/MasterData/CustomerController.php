<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Repository\CustomerRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Khách hàng
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách khách hàng
 *   - Theo dõi công nợ, hạn mức tín dụng, lịch sử giao dịch
 *   - Liên kết với TK 131 (Phải thu khách hàng)
 *
 * API endpoints:
 *   GET    /api/customers — Danh sách
 *   POST   /api/customers — Tạo mới
 *   GET    /api/customers/{id} — Chi tiết
 *   PUT    /api/customers/{id} — Cập nhật
 *   DELETE /api/customers/{id} — Xoá
 *
 * Rủi ro:
 *   - R005: Xoá khách hàng đang có công nợ
 *
 * Tích hợp:
 *   - ArService theo dõi công nợ
 *   - ArController xử lý giao dịch
 */
class CustomerController
{
    use CrudControllerTrait;

    /**
     * @param CustomerRepositoryInterface $repository
     */
    public function __construct(CustomerRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'customers';
    }
}
