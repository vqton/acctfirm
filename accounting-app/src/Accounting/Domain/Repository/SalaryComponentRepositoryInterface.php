<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\SalaryComponent;

/**
 * Giao diện repository cho thành phần lương (Salary Component).
 *
 * Cung cấp các phương thức truy xuất và thao tác với các thành phần
 * cấu tạo nên bảng lương (lương cơ bản, phụ cấp, thưởng, khấu trừ...).
 */
interface SalaryComponentRepositoryInterface
{
    /**
     * Tìm thành phần lương theo ID.
     *
     * @param string $id ID của thành phần lương
     * @return SalaryComponent|null Đối tượng SalaryComponent nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?SalaryComponent;

    /**
     * Tìm thành phần lương theo mã.
     *
     * @param string $code Mã thành phần lương
     * @return SalaryComponent|null Đối tượng SalaryComponent nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?SalaryComponent;

    /**
     * Lấy tất cả thành phần lương.
     *
     * @return SalaryComponent[] Danh sách tất cả thành phần lương
     */
    public function findAll(): array;

    /**
     * Tìm thành phần lương theo loại (thu nhập, khấu trừ, bảo hiểm...).
     *
     * @param string $type Loại thành phần lương
     * @return SalaryComponent[] Danh sách thành phần lương
     */
    public function findByType(string $type): array;

    /**
     * Lưu thành phần lương (thêm mới hoặc cập nhật).
     *
     * @param SalaryComponent $c Đối tượng SalaryComponent cần lưu
     * @return void
     */
    public function save(SalaryComponent $c): void;

    /**
     * Xóa thành phần lương theo ID.
     *
     * @param string $id ID của thành phần lương cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
