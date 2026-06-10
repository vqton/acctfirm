<?php

declare(strict_types=1);

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\PurchaseBudget;

/**
 * Giao diện repository cho ngân sách mua hàng.
 *
 * Cung cấp các phương thức truy xuất và thao tác với ngân sách mua hàng
 * theo phòng ban và kỳ kế toán.
 */
interface PurchaseBudgetRepositoryInterface
{
    /**
     * Tìm ngân sách theo ID.
     *
     * @param string $id ID của ngân sách
     * @return PurchaseBudget|null Đối tượng PurchaseBudget nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?PurchaseBudget;

    /**
     * Tìm ngân sách theo phòng ban.
     *
     * @param string $departmentId ID của phòng ban
     * @return PurchaseBudget[] Danh sách ngân sách
     */
    public function findByDepartment(string $departmentId): array;

    /**
     * Tìm ngân sách theo phòng ban và kỳ.
     *
     * @param string $departmentId ID của phòng ban
     * @param string $period Mã kỳ
     * @return PurchaseBudget|null Đối tượng PurchaseBudget nếu tìm thấy, null nếu không
     */
    public function findOneByDeptPeriod(string $departmentId, string $period): ?PurchaseBudget;

    /**
     * Lấy tất cả ngân sách.
     *
     * @return PurchaseBudget[] Danh sách tất cả ngân sách
     */
    public function findAll(): array;

    /**
     * Lưu ngân sách (thêm mới hoặc cập nhật).
     *
     * @param PurchaseBudget $budget Đối tượng PurchaseBudget cần lưu
     * @return void
     */
    public function save(PurchaseBudget $budget): void;

    /**
     * Xóa ngân sách theo ID.
     *
     * @param string $id ID của ngân sách cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
