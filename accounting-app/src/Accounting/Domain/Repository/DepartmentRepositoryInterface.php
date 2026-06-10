<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Department;

/**
 * Giao diện repository cho phòng ban.
 *
 * Cung cấp các phương thức truy xuất và thao tác với danh sách phòng ban
 * trong doanh nghiệp.
 */
interface DepartmentRepositoryInterface
{
    /**
     * Tìm phòng ban theo ID.
     *
     * @param string $id ID của phòng ban
     * @return Department|null Đối tượng Department nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?Department;

    /**
     * Tìm phòng ban theo mã.
     *
     * @param string $code Mã phòng ban
     * @return Department|null Đối tượng Department nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?Department;

    /**
     * Lấy tất cả phòng ban.
     *
     * @return Department[] Danh sách tất cả phòng ban
     */
    public function findAll(): array;

    /**
     * Lưu phòng ban (thêm mới hoặc cập nhật).
     *
     * @param Department $d Đối tượng Department cần lưu
     * @return void
     */
    public function save(Department $d): void;

    /**
     * Xóa phòng ban theo ID.
     *
     * @param string $id ID của phòng ban cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
