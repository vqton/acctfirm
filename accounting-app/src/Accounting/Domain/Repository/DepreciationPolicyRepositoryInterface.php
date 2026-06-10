<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\DepreciationPolicy;

/**
 * Giao diện repository cho chính sách khấu hao tài sản cố định.
 *
 * Cung cấp các phương thức truy xuất và thao tác với chính sách khấu hao
 * (đường thẳng, số dư giảm dần, ...) áp dụng cho tài sản cố định.
 */
interface DepreciationPolicyRepositoryInterface
{
    /**
     * Tìm chính sách khấu hao theo ID.
     *
     * @param string $id ID của chính sách
     * @return DepreciationPolicy|null Đối tượng DepreciationPolicy nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?DepreciationPolicy;

    /**
     * Tìm chính sách khấu hao theo mã.
     *
     * @param string $code Mã chính sách
     * @return DepreciationPolicy|null Đối tượng DepreciationPolicy nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?DepreciationPolicy;

    /**
     * Lấy tất cả chính sách khấu hao.
     *
     * @return DepreciationPolicy[] Danh sách tất cả chính sách
     */
    public function findAll(): array;

    /**
     * Lưu chính sách khấu hao (thêm mới hoặc cập nhật).
     *
     * @param DepreciationPolicy $policy Đối tượng DepreciationPolicy cần lưu
     * @return void
     */
    public function save(DepreciationPolicy $policy): void;

    /**
     * Xóa chính sách khấu hao theo ID.
     *
     * @param string $id ID của chính sách cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
