<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
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
}
