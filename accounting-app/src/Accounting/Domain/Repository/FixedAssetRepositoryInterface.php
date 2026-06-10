<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\FixedAsset;

/**
 * Giao diện repository cho tài sản cố định (TSCĐ).
 *
 * Cung cấp các phương thức truy xuất và thao tác với tài sản cố định
 * (TK 211, 213, 214), bao gồm tra cứu tài sản đang hoạt động.
 */
interface FixedAssetRepositoryInterface
{
    /**
     * Tìm TSCĐ theo ID.
     *
     * @param string $id ID của tài sản
     * @return FixedAsset|null Đối tượng FixedAsset nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?FixedAsset;

    /**
     * Tìm TSCĐ theo mã.
     *
     * @param string $code Mã tài sản
     * @return FixedAsset|null Đối tượng FixedAsset nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?FixedAsset;

    /**
     * Lấy tất cả TSCĐ.
     *
     * @return FixedAsset[] Danh sách tất cả tài sản
     */
    public function findAll(): array;

    /**
     * Lấy danh sách TSCĐ đang hoạt động.
     *
     * @return FixedAsset[] Danh sách tài sản đang hoạt động
     */
    public function findActive(): array;

    /**
     * Lưu TSCĐ (thêm mới hoặc cập nhật).
     *
     * @param FixedAsset $asset Đối tượng FixedAsset cần lưu
     * @return void
     */
    public function save(FixedAsset $asset): void;

    /**
     * Xóa TSCĐ theo ID.
     *
     * @param string $id ID của tài sản cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
