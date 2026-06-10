<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\TaxRate;

/**
 * Giao diện repository cho thuế suất (Tax Rate).
 *
 * Cung cấp các phương thức truy xuất và thao tác với các mức thuế suất
 * áp dụng (GTGT, TNCN, TNDN, ...).
 */
interface TaxRateRepositoryInterface
{
    /**
     * Tìm thuế suất theo ID.
     *
     * @param string $id ID của thuế suất
     * @return TaxRate|null Đối tượng TaxRate nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?TaxRate;

    /**
     * Tìm thuế suất theo mã.
     *
     * @param string $code Mã thuế suất
     * @return TaxRate|null Đối tượng TaxRate nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?TaxRate;

    /**
     * Lấy tất cả thuế suất.
     *
     * @return TaxRate[] Danh sách tất cả thuế suất
     */
    public function findAll(): array;

    /**
     * Lưu thuế suất (thêm mới hoặc cập nhật).
     *
     * @param TaxRate $taxRate Đối tượng TaxRate cần lưu
     * @return void
     */
    public function save(TaxRate $taxRate): void;

    /**
     * Xóa thuế suất theo ID.
     *
     * @param string $id ID của thuế suất cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
