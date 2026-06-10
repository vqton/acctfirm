<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\MenuItem;

/**
 * Giao diện repository cho menu điều hướng.
 *
 * Cung cấp các phương thức truy xuất và thao tác với các mục menu
 * trong giao diện người dùng.
 */
interface MenuRepositoryInterface
{
    /**
     * Lấy tất cả menu đang hoạt động.
     *
     * @return MenuItem[] Danh sách menu đang hoạt động
     */
    public function findAllActive(): array;

    /**
     * Tìm menu theo section (khu vực).
     *
     * @param string $section Mã section
     * @return MenuItem[] Danh sách menu thuộc section
     */
    public function findBySection(string $section): array;

    /**
     * Tìm menu theo ID.
     *
     * @param int $id ID của menu
     * @return MenuItem|null Đối tượng MenuItem nếu tìm thấy, null nếu không
     */
    public function findById(int $id): ?MenuItem;

    /**
     * Tìm kiếm menu theo từ khóa.
     *
     * @param string $keyword Từ khóa tìm kiếm
     * @return MenuItem[] Danh sách menu phù hợp
     */
    public function search(string $keyword): array;

    /**
     * Lưu menu mới.
     *
     * @param MenuItem $item Đối tượng MenuItem cần lưu
     * @return void
     */
    public function save(MenuItem $item): void;

    /**
     * Cập nhật menu.
     *
     * @param MenuItem $item Đối tượng MenuItem cần cập nhật
     * @return void
     */
    public function update(MenuItem $item): void;

    /**
     * Vô hiệu hóa menu (soft delete).
     *
     * @param int $id ID của menu cần vô hiệu hóa
     * @return void
     */
    public function deactivate(int $id): void;
}
