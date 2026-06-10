<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Warehouse;

/**
 * Giao diện repository cho kho (Warehouse).
 *
 * Cung cấp các phương thức truy xuất và thao tác với danh sách kho,
 * phục vụ quản lý hàng tồn kho và xuất nhập kho.
 */
interface WarehouseRepositoryInterface
{
    /**
     * Tìm kho theo ID.
     *
     * @param string $id ID của kho
     * @return Warehouse|null Đối tượng Warehouse nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?Warehouse;

    /**
     * Tìm kho theo mã.
     *
     * @param string $code Mã kho
     * @return Warehouse|null Đối tượng Warehouse nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?Warehouse;

    /**
     * Lấy tất cả kho.
     *
     * @return Warehouse[] Danh sách tất cả kho
     */
    public function findAll(): array;

    /**
     * Lưu kho (thêm mới hoặc cập nhật).
     *
     * @param Warehouse $w Đối tượng Warehouse cần lưu
     * @return void
     */
    public function save(Warehouse $w): void;

    /**
     * Xóa kho theo ID.
     *
     * @param string $id ID của kho cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
