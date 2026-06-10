<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Repository\CcdcRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý CCDC (Công cụ dụng cụ)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách công cụ dụng cụ
 *   - Nhập kho CCDC (TK 153)
 *   - Xuất kho + phân bổ CCDC
 *
 * API endpoints:
 *   GET    /api/ccdc — Danh sách
 *   POST   /api/ccdc — Tạo mới
 *   GET    /api/ccdc/{id} — Chi tiết
 *   PUT    /api/ccdc/{id} — Cập nhật
 *   DELETE /api/ccdc/{id} — Xoá
 *
 * Tích hợp:
 *   - InventoryService xử lý tồn kho CCDC
 *   - CcdcAllocationController xử lý phân bổ
 */
class CcdcController
{
    use CrudControllerTrait;

    /**
     * @param CcdcRepositoryInterface $repository
     */
    public function __construct(CcdcRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'ccdc';
    }
}
