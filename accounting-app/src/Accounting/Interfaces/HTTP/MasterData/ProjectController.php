<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Repository\ProjectRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Dự án (Master Data)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách dự án
 *   - Theo dõi chi phí, doanh thu từng dự án
 *
 * API endpoints:
 *   GET    /api/projects — Danh sách
 *   POST   /api/projects — Tạo mới
 *   GET    /api/projects/{id} — Chi tiết
 *   PUT    /api/projects/{id} — Cập nhật
 *   DELETE /api/projects/{id} — Xoá
 *
 * Tích hợp:
 *   - ProjectAccountingController xử lý phân bổ chi phí
 */
class ProjectController
{
    use CrudControllerTrait;

    /**
     * @param ProjectRepositoryInterface $repository
     */
    public function __construct(ProjectRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'projects';
    }
}
