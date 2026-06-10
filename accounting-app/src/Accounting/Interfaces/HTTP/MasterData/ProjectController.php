<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Model\Project;
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

    protected function repo()
    {
        return $this->repository;
    }

    protected function idPrefix(): string
    {
        return 'proj_';
    }

    protected function createEntity(array $data): object
    {
        return new Project(
            id: $data['id'] ?? uniqid('proj_'),
            code: $data['code'] ?? '',
            name: $data['name'] ?? '',
            customerId: $data['customer_id'] ?? '',
            startDate: $data['start_date'] ?? date('Y-m-d'),
            endDate: $data['end_date'] ?? null,
            budget: (float)($data['budget'] ?? 0),
            notes: $data['notes'] ?? null,
            managerId: $data['manager_id'] ?? null
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['customer_id'])) $entity->setCustomerId($data['customer_id']);
        if (isset($data['manager_id'])) $entity->setManagerId($data['manager_id']);
        if (isset($data['start_date'])) $entity->setStartDate($data['start_date']);
        if (isset($data['end_date'])) $entity->setEndDate($data['end_date']);
        if (isset($data['budget'])) $entity->setBudget((float)$data['budget']);
        if (isset($data['notes'])) $entity->setNotes($data['notes']);
        if (isset($data['status'])) $entity->setStatus($data['status']);
    }
}
