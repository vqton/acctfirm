<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Project;

/**
 * Giao diện repository cho dự án.
 *
 * Cung cấp các phương thức truy xuất và thao tác với dự án,
 * bao gồm tổng hợp chi phí, giao dịch, nghiệm thu và ngân sách.
 */
interface ProjectRepositoryInterface
{
    /**
     * Tìm dự án theo ID.
     *
     * @param string $id ID của dự án
     * @return Project|null Đối tượng Project nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?Project;

    /**
     * Tìm dự án theo mã.
     *
     * @param string $code Mã dự án
     * @return Project|null Đối tượng Project nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?Project;

    /**
     * Lấy tất cả dự án.
     *
     * @return Project[] Danh sách tất cả dự án
     */
    public function findAll(): array;

    /**
     * Lưu dự án (thêm mới hoặc cập nhật).
     *
     * @param Project $project Đối tượng Project cần lưu
     * @return void
     */
    public function save(Project $project): void;

    /**
     * Xóa dự án theo ID.
     *
     * @param string $id ID của dự án cần xóa
     * @return void
     */
    public function delete(string $id): void;

    /**
     * Lấy tổng hợp chi phí của dự án.
     *
     * @param string $projectId ID của dự án
     * @return array Mảng tổng hợp chi phí
     */
    public function getCostSummary(string $projectId): array;

    /**
     * Lấy danh sách giao dịch của dự án trong khoảng thời gian.
     *
     * @param string $projectId ID của dự án
     * @param string|null $fromDate Từ ngày (nullable)
     * @param string|null $toDate Đến ngày (nullable)
     * @return array Danh sách giao dịch
     */
    public function getProjectTransactions(string $projectId, ?string $fromDate = null, ?string $toDate = null): array;

    /**
     * Lấy danh sách nghiệm thu của dự án.
     *
     * @param string $projectId ID của dự án
     * @return array Danh sách nghiệm thu
     */
    public function getProgressBillings(string $projectId): array;

    /**
     * Lấy danh sách ngân sách của dự án.
     *
     * @param string $projectId ID của dự án
     * @return array Danh sách ngân sách
     */
    public function getProjectBudgets(string $projectId): array;
}
