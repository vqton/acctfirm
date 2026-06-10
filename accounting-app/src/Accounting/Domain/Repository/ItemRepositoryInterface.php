<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Item;

/**
 * Giao diện repository cho hàng hóa, vật tư (Item).
 *
 * Cung cấp các phương thức truy xuất và thao tác với danh mục hàng hóa,
 * vật tư, nguyên vật liệu, thành phẩm trong kho.
 */
interface ItemRepositoryInterface
{
    /**
     * Tìm hàng hóa theo ID.
     *
     * @param string $id ID của hàng hóa
     * @return Item|null Đối tượng Item nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?Item;

    /**
     * Tìm hàng hóa theo mã.
     *
     * @param string $code Mã hàng hóa
     * @return Item|null Đối tượng Item nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?Item;

    /**
     * Lấy tất cả hàng hóa.
     *
     * @return Item[] Danh sách tất cả hàng hóa
     */
    public function findAll(): array;

    /**
     * Lưu hàng hóa (thêm mới hoặc cập nhật).
     *
     * @param Item $item Đối tượng Item cần lưu
     * @return void
     */
    public function save(Item $item): void;

    /**
     * Xóa hàng hóa theo ID.
     *
     * @param string $id ID của hàng hóa cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
