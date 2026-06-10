<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Model\DepreciationPolicy;
use Accounting\Domain\Repository\DepreciationPolicyRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Chính sách Khấu hao
 *
 * Mục đích nghiệp vụ:
 *   - CRUD phương pháp và tỷ lệ khấu hao TSCĐ
 *   - Tuân thủ TT 45/2013/TT-BTC
 *
 * API endpoints:
 *   GET    /api/depreciation-policies — Danh sách
 *   POST   /api/depreciation-policies — Tạo mới
 *   GET    /api/depreciation-policies/{id} — Chi tiết
 *   PUT    /api/depreciation-policies/{id} — Cập nhật
 *   DELETE /api/depreciation-policies/{id} — Xoá
 *
 * Tích hợp:
 *   - FixedAssetController gán policy cho từng TSCĐ
 */
class DepreciationPolicyController
{
    use CrudControllerTrait;

    /**
     * @param DepreciationPolicyRepositoryInterface $repository
     */
    public function __construct(DepreciationPolicyRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'depreciation_policies';
    }

    protected function repo()
    {
        return $this->repository;
    }

    protected function idPrefix(): string
    {
        return 'depr_';
    }

    protected function createEntity(array $data): object
    {
        return new DepreciationPolicy(
            id: $data['id'] ?? uniqid('depr_'),
            code: $data['code'] ?? '',
            name: $data['name'] ?? '',
            method: $data['method'] ?? 'straight_line',
            defaultLife: (int)($data['default_life'] ?? 0),
            defaultSalvageRate: (float)($data['default_salvage_rate'] ?? 0)
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['method'])) $entity->setMethod($data['method']);
        if (isset($data['default_life'])) $entity->setDefaultLife((int)$data['default_life']);
        if (isset($data['default_salvage_rate'])) $entity->setDefaultSalvageRate((float)$data['default_salvage_rate']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}
