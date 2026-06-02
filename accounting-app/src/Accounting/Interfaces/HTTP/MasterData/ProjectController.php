<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\Project;
use Accounting\Domain\Repository\ProjectRepositoryInterface;

use \Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Danh mục Dự án (Project Master)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD dự án/ công trình theo dõi riêng
 *   - Quản lý ngân sách, thời gian, khách hàng liên quan
 *   - Cơ sở để tập hợp chi phí (TK 154 — CPSXKD dở dang) theo dự án
 *   - Tính giá thành sản phẩm/dịch vụ theo dự án
 *
 * API endpoints:
 *   (Sử dụng CrudControllerTrait — CRUD chuẩn)
 *
 * Rủi ro:
 *   - Chi phí vượt ngân sách dự án → lỗ dự án
 *   - Dự án kết thúc không kết chuyển chi phí → sai số dư 154
 *   - Nhầm lẫn chi phí giữa các dự án
 *
 * Tích hợp:
 *   - Cost allocation module (tương lai) tính giá thành theo dự án
 *   - CustomerController cung cấp thông tin khách hàng
 *   - Báo cáo quản trị theo dự án
 */
class ProjectController
{
    use CrudControllerTrait;

    private ProjectRepositoryInterface $repo;
    public function __construct(ProjectRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'proj_'; }
    protected function requiredFields(): array { return ['code', 'name', 'customer_id', 'start_date']; }

    protected function createEntity(array $data): object
    {
        return new Project(
            $data['id'], $data['code'], $data['name'], $data['customer_id'],
            $data['start_date'], $data['end_date'] ?? null, (float)($data['budget'] ?? 0),
            $data['notes'] ?? null, $data['manager_id'] ?? null
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['customer_id'])) $entity->setCustomerId($data['customer_id']);
        if (isset($data['start_date'])) $entity->setStartDate($data['start_date']);
        if (isset($data['end_date'])) $entity->setEndDate($data['end_date']);
        if (isset($data['budget'])) $entity->setBudget((float)$data['budget']);
        if (isset($data['status'])) $entity->setStatus($data['status']);
        if (isset($data['manager_id'])) $entity->setManagerId($data['manager_id']);
        if (isset($data['estimated_completion_pct'])) $entity->setEstimatedCompletionPct((float)$data['estimated_completion_pct']);
        if (isset($data['notes'])) $entity->setNotes($data['notes']);
    }
}
