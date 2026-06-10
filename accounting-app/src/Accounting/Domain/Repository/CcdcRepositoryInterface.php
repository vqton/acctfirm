<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Ccdc;

/**
 * Giao diện repository cho công cụ dụng cụ (CCDC).
 *
 * Cung cấp các phương thức truy xuất và thao tác với công cụ dụng cụ
 * (TK 153), bao gồm tra cứu và phân bổ giá trị.
 */
interface CcdcRepositoryInterface
{
    /**
     * Tìm CCDC theo ID.
     *
     * @param string $id ID của CCDC
     * @return Ccdc|null Đối tượng Ccdc nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?Ccdc;

    /**
     * Tìm CCDC theo mã.
     *
     * @param string $code Mã CCDC
     * @return Ccdc|null Đối tượng Ccdc nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?Ccdc;

    /**
     * Lấy tất cả CCDC.
     *
     * @return Ccdc[] Danh sách tất cả CCDC
     */
    public function findAll(): array;

    /**
     * Tìm các CCDC đang chờ phân bổ.
     *
     * @param int $limit Số lượng tối đa (mặc định 100)
     * @return Ccdc[] Danh sách CCDC chờ phân bổ
     */
    public function findPendingAllocation(int $limit = 100): array;

    /**
     * Lưu CCDC (thêm mới hoặc cập nhật).
     *
     * @param Ccdc $ccdc Đối tượng Ccdc cần lưu
     * @return void
     */
    public function save(Ccdc $ccdc): void;

    /**
     * Xóa CCDC theo ID.
     *
     * @param string $id ID của CCDC cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
