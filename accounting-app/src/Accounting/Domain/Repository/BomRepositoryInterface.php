<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Bom;

/**
 * Giao diện repository cho định mức nguyên vật liệu (Bill of Materials).
 *
 * Cung cấp các phương thức truy xuất và thao tác với định mức sản xuất,
 * bao gồm tra cứu theo sản phẩm và quản lý định mức đang hoạt động.
 */
interface BomRepositoryInterface
{
    /**
     * Tìm định mức theo ID.
     *
     * @param string $id ID của định mức
     * @return Bom|null Đối tượng Bom nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?Bom;

    /**
     * Tìm tất cả định mức của một sản phẩm.
     *
     * @param string $productId ID của sản phẩm
     * @return Bom[] Danh sách định mức của sản phẩm
     */
    public function findByProduct(string $productId): array;

    /**
     * Tìm định mức đang hoạt động của một sản phẩm.
     *
     * @param string $productId ID của sản phẩm
     * @return Bom|null Định mức đang hoạt động, null nếu không có
     */
    public function findActiveByProduct(string $productId): ?Bom;

    /**
     * Lấy tất cả định mức.
     *
     * @return Bom[] Danh sách tất cả định mức
     */
    public function findAll(): array;

    /**
     * Lưu định mức (thêm mới hoặc cập nhật).
     *
     * @param Bom $bom Đối tượng Bom cần lưu
     * @return void
     */
    public function save(Bom $bom): void;

    /**
     * Xóa định mức theo ID.
     *
     * @param string $id ID của định mức cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
