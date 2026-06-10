<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\PayrollPeriod;

/**
 * Giao diện repository cho kỳ lương.
 *
 * Cung cấp các phương thức truy xuất và thao tác với kỳ tính lương,
 * bao gồm tìm kỳ đang mở để xử lý lương.
 */
interface PayrollPeriodRepositoryInterface
{
    /**
     * Tìm kỳ lương theo ID.
     *
     * @param string $id ID của kỳ lương
     * @return PayrollPeriod|null Đối tượng PayrollPeriod nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?PayrollPeriod;

    /**
     * Tìm kỳ lương theo mã.
     *
     * @param string $code Mã kỳ lương
     * @return PayrollPeriod|null Đối tượng PayrollPeriod nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?PayrollPeriod;

    /**
     * Lấy tất cả kỳ lương.
     *
     * @return PayrollPeriod[] Danh sách tất cả kỳ lương
     */
    public function findAll(): array;

    /**
     * Lấy danh sách kỳ lương đang mở.
     *
     * @return PayrollPeriod[] Danh sách kỳ lương đang mở
     */
    public function findOpen(): array;

    /**
     * Lưu kỳ lương (thêm mới hoặc cập nhật).
     *
     * @param PayrollPeriod $p Đối tượng PayrollPeriod cần lưu
     * @return void
     */
    public function save(PayrollPeriod $p): void;

    /**
     * Xóa kỳ lương theo ID.
     *
     * @param string $id ID của kỳ lương cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
