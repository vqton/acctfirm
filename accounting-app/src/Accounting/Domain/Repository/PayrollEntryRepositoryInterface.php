<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\PayrollEntry;
use Accounting\Domain\Model\PayrollDetail;

/**
 * Giao diện repository cho bảng lương (Payroll Entry).
 *
 * Cung cấp các phương thức truy xuất và thao tác với bảng lương
 * và chi tiết bảng lương theo từng kỳ.
 */
interface PayrollEntryRepositoryInterface
{
    /**
     * Tìm bảng lương theo ID.
     *
     * @param string $id ID của bảng lương
     * @return PayrollEntry|null Đối tượng PayrollEntry nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?PayrollEntry;

    /**
     * Tìm bảng lương theo kỳ.
     *
     * @param string $periodId ID của kỳ lương
     * @return PayrollEntry[] Danh sách bảng lương thuộc kỳ
     */
    public function findByPeriod(string $periodId): array;

    /**
     * Lấy tất cả bảng lương.
     *
     * @return PayrollEntry[] Danh sách tất cả bảng lương
     */
    public function findAll(): array;

    /**
     * Lưu bảng lương (thêm mới hoặc cập nhật).
     *
     * @param PayrollEntry $e Đối tượng PayrollEntry cần lưu
     * @return void
     */
    public function save(PayrollEntry $e): void;

    /**
     * Xóa bảng lương theo ID.
     *
     * @param string $id ID của bảng lương cần xóa
     * @return void
     */
    public function delete(string $id): void;

    /**
     * Lấy chi tiết bảng lương theo bảng lương.
     *
     * @param string $entryId ID của bảng lương
     * @return PayrollDetail[] Danh sách chi tiết bảng lương
     */
    public function findDetailsByEntry(string $entryId): array;

    /**
     * Tìm chi tiết bảng lương theo ID.
     *
     * @param string $id ID của chi tiết bảng lương
     * @return PayrollDetail|null Đối tượng PayrollDetail nếu tìm thấy, null nếu không
     */
    public function findDetailById(string $id): ?PayrollDetail;

    /**
     * Lưu chi tiết bảng lương.
     *
     * @param PayrollDetail $d Đối tượng PayrollDetail cần lưu
     * @return void
     */
    public function saveDetail(PayrollDetail $d): void;

    /**
     * Xóa chi tiết bảng lương theo ID.
     *
     * @param string $id ID của chi tiết cần xóa
     * @return void
     */
    public function deleteDetail(string $id): void;

    /**
     * Xóa tất cả chi tiết bảng lương theo bảng lương.
     *
     * @param string $entryId ID của bảng lương
     * @return void
     */
    public function deleteDetailsByEntry(string $entryId): void;
}
