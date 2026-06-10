<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Repository\FixedAssetRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý TSCĐ (Master Data)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách tài sản cố định
 *   - Ghi nhận nguyên giá, khấu hao, giá trị còn lại
 *
 * API endpoints:
 *   GET    /api/fixed-assets — Danh sách
 *   POST   /api/fixed-assets — Tạo mới
 *   GET    /api/fixed-assets/{id} — Chi tiết
 *   PUT    /api/fixed-assets/{id} — Cập nhật
 *   DELETE /api/fixed-assets/{id} — Xoá
 *
 * Tích hợp:
 *   - FixedAsset/LifecycleController xử lý mua/sang nhượng/thanh lý
 *   - FixedAsset/DepreciationReportController
 */
class FixedAssetController
{
    use CrudControllerTrait;

    /**
     * @param FixedAssetRepositoryInterface $repository
     */
    public function __construct(FixedAssetRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'fixed_assets';
    }
}
