<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Supplier;

/**
 * Giao diện repository cho nhà cung cấp.
 *
 * Cung cấp các phương thức truy xuất và thao tác với danh sách nhà cung cấp
 * (TK 331 - Phải trả người bán).
 */
interface SupplierRepositoryInterface
{
    /**
     * Tìm nhà cung cấp theo ID.
     *
     * @param string $id ID của nhà cung cấp
     * @return Supplier|null Đối tượng Supplier nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?Supplier;

    /**
     * Tìm nhà cung cấp theo mã.
     *
     * @param string $code Mã nhà cung cấp
     * @return Supplier|null Đối tượng Supplier nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?Supplier;

    /**
     * Lấy tất cả nhà cung cấp.
     *
     * @return Supplier[] Danh sách tất cả nhà cung cấp
     */
    public function findAll(): array;

    /**
     * Lưu nhà cung cấp (thêm mới hoặc cập nhật).
     *
     * @param Supplier $supplier Đối tượng Supplier cần lưu
     * @return void
     */
    public function save(Supplier $supplier): void;

    /**
     * Xóa nhà cung cấp theo ID.
     *
     * @param string $id ID của nhà cung cấp cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
