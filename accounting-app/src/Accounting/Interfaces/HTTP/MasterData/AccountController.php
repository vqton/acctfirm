<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Domain\Model\Account;
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

    protected function repo()
    {
        return $this->repository;
    }

    protected function idPrefix(): string
    {
        return 'acct_';
    }

    protected function createEntity(array $data): object
    {
        return new Account(
            id: $data['id'] ?? uniqid('acct_'),
            code: $data['code'] ?? '',
            name: $data['name'] ?? '',
            type: $data['type'] ?? '',
            parentId: $data['parent_id'] ?? null,
            normalBalance: $data['normal_balance'] ?? 'D',
            accountClass: $data['account_class'] ?? null,
            description: $data['description'] ?? null,
            fsMappingCode: $data['fs_mapping_code'] ?? null,
            fsMappingType: $data['fs_mapping_type'] ?? null,
            alternativeCode: $data['alternative_code'] ?? null,
            detailBy: $data['detail_by'] ?? null
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['type'])) $entity->setType($data['type']);
        if (isset($data['parent_id'])) $entity->setParentId($data['parent_id']);
        if (isset($data['normal_balance'])) $entity->setNormalBalance($data['normal_balance']);
        if (isset($data['account_class'])) $entity->setAccountClass($data['account_class']);
        if (isset($data['description'])) $entity->setDescription($data['description']);
        if (isset($data['fs_mapping_code'])) $entity->setFsMappingCode($data['fs_mapping_code']);
        if (isset($data['fs_mapping_type'])) $entity->setFsMappingType($data['fs_mapping_type']);
        if (isset($data['alternative_code'])) $entity->setAlternativeCode($data['alternative_code']);
        if (isset($data['detail_by'])) $entity->setDetailBy($data['detail_by']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}
