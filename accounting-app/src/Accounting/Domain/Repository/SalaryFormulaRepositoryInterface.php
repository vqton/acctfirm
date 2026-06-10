<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\SalaryFormula;

/**
 * Giao diện repository cho công thức tính lương (Salary Formula).
 *
 * Cung cấp các phương thức truy xuất và thao tác với công thức tính lương,
 * cho phép định nghĩa cách tính các thành phần lương theo từng loại.
 */
interface SalaryFormulaRepositoryInterface
{
    /**
     * Tìm công thức tính lương theo ID.
     *
     * @param string $id ID của công thức
     * @return SalaryFormula|null Đối tượng SalaryFormula nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?SalaryFormula;

    /**
     * Tìm công thức tính lương theo mã.
     *
     * @param string $code Mã công thức
     * @return SalaryFormula|null Đối tượng SalaryFormula nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?SalaryFormula;

    /**
     * Lấy tất cả công thức tính lương.
     *
     * @return SalaryFormula[] Danh sách tất cả công thức
     */
    public function findAll(): array;

    /**
     * Tìm công thức theo loại.
     *
     * @param string $type Loại công thức
     * @return SalaryFormula[] Danh sách công thức
     */
    public function findByType(string $type): array;

    /**
     * Lưu công thức tính lương (thêm mới hoặc cập nhật).
     *
     * @param SalaryFormula $f Đối tượng SalaryFormula cần lưu
     * @return void
     */
    public function save(SalaryFormula $f): void;

    /**
     * Xóa công thức tính lương theo ID.
     *
     * @param string $id ID của công thức cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
