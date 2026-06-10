<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Tài khoản kế toán (Chart of Accounts - COA)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách hệ thống tài khoản kế toán
 *   - Tuân thủ Circular 99/2025/TT-BTC
 *   - Import/Export COA
 *
 * API endpoints:
 *   GET    /api/accounts — Danh sách
 *   POST   /api/accounts — Tạo mới
 *   GET    /api/accounts/{id} — Chi tiết
 *   PUT    /api/accounts/{id} — Cập nhật
 *   DELETE /api/accounts/{id} — Xoá
 *
 * Rủi ro:
 *   - R005: Sai account code -> sai BC
 *   - R007: Xoá tài khoản đang giao dịch
 *
 * Tích hợp:
 *   - AccountRepositoryInterface
 *   - Mọi module đều dùng account code
 */
class AccountController
{
    use CrudControllerTrait;

    /**
     * @param AccountRepositoryInterface $repository
     */
    public function __construct(AccountRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'coa';
    }

    /**
     * Tìm kiếm tài khoản theo từ khoá
     *
     * @return void
     */
    public function search(): void
    {
        Auth::requirePermission($this->module, 'read');
        $q = $_GET['q'] ?? '';
        if (!$q) { JsonResponse::ok([]); return; }
        JsonResponse::ok($this->repository->searchByKeyword($q));
    }
}
