<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Employee;

/**
 * Giao diện repository cho nhân viên.
 *
 * Cung cấp các phương thức truy xuất và thao tác với danh sách nhân viên
 * phục vụ tính lương và quản lý nhân sự.
 */
interface EmployeeRepositoryInterface
{
    /**
     * Tìm nhân viên theo ID.
     *
     * @param string $id ID của nhân viên
     * @return Employee|null Đối tượng Employee nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?Employee;

    /**
     * Tìm nhân viên theo mã.
     *
     * @param string $code Mã nhân viên
     * @return Employee|null Đối tượng Employee nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?Employee;

    /**
     * Lấy tất cả nhân viên.
     *
     * @return Employee[] Danh sách tất cả nhân viên
     */
    public function findAll(): array;

    /**
     * Lưu nhân viên (thêm mới hoặc cập nhật).
     *
     * @param Employee $e Đối tượng Employee cần lưu
     * @return void
     */
    public function save(Employee $e): void;

    /**
     * Xóa nhân viên theo ID.
     *
     * @param string $id ID của nhân viên cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
