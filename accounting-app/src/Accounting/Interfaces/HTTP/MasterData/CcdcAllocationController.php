<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Repository\CcdcAllocationRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Phân bổ CCDC (Công cụ dụng cụ)
 *
 * Mục đích nghiệp vụ:
 *   - Phân bổ giá trị CCDC vào nhiều kỳ
 *   - Theo dõi giá trị hao mòn CCDC
 *
 * API endpoints:
 *   GET    /api/ccdc-allocations — Danh sách
 *   POST   /api/ccdc-allocations — Tạo mới
 *   PUT    /api/ccdc-allocations/{id} — Cập nhật
 *   DELETE /api/ccdc-allocations/{id} — Xoá
 *
 * Tích hợp:
 *   - CcdcController quản lý CCDC đầu vào
 */
class CcdcAllocationController
{
    use CrudControllerTrait;

    /**
     * @param CcdcAllocationRepositoryInterface $repository
     */
    public function __construct(CcdcAllocationRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'ccdc_allocations';
    }
}
