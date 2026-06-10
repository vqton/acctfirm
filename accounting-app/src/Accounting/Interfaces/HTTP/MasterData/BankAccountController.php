<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Repository\BankAccountRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Tài khoản Ngân hàng
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách tài khoản ngân hàng của doanh nghiệp
 *   - Liên kết với TK 112 (Tiền gửi ngân hàng)
 *
 * API endpoints:
 *   GET    /api/bank-accounts — Danh sách
 *   POST   /api/bank-accounts — Tạo mới
 *   GET    /api/bank-accounts/{id} — Chi tiết
 *   PUT    /api/bank-accounts/{id} — Cập nhật
 *   DELETE /api/bank-accounts/{id} — Xoá
 *
 * Tích hợp:
 *   - CashService dùng để ghi nhận giao dịch ngân hàng
 */
class BankAccountController
{
    use CrudControllerTrait;

    /**
     * @param BankAccountRepositoryInterface $repository
     */
    public function __construct(BankAccountRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'bank_accounts';
    }
}
