<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Uom;

/**
 * Giao diện repository cho đơn vị tính (Unit of Measure).
 *
 * Cung cấp các phương thức truy xuất và thao tác với danh mục đơn vị tính
 * sử dụng trong quản lý hàng hóa, vật tư.
 */
interface UomRepositoryInterface
{
    /**
     * Tìm đơn vị tính theo ID.
     *
     * @param string $id ID của đơn vị tính
     * @return Uom|null Đối tượng Uom nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?Uom;

    /**
     * Tìm đơn vị tính theo mã.
     *
     * @param string $code Mã đơn vị tính
     * @return Uom|null Đối tượng Uom nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?Uom;

    /**
     * Lấy tất cả đơn vị tính.
     *
     * @return Uom[] Danh sách tất cả đơn vị tính
     */
    public function findAll(): array;

    /**
     * Lưu đơn vị tính (thêm mới hoặc cập nhật).
     *
     * @param Uom $uom Đối tượng Uom cần lưu
     * @return void
     */
    public function save(Uom $uom): void;

    /**
     * Xóa đơn vị tính theo ID.
     *
     * @param string $id ID của đơn vị tính cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
