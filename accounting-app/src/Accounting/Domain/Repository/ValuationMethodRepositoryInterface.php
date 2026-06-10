<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\ValuationMethod;

/**
 * Giao diện repository cho phương pháp định giá hàng tồn kho.
 *
 * Cung cấp các phương thức truy xuất và thao tác với phương pháp định giá
 * (FIFO, bình quân gia quyền, thực tế đích danh, ...).
 */
interface ValuationMethodRepositoryInterface
{
    /**
     * Tìm phương pháp định giá theo ID.
     *
     * @param string $id ID của phương pháp
     * @return ValuationMethod|null Đối tượng ValuationMethod nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?ValuationMethod;

    /**
     * Tìm phương pháp định giá theo mã.
     *
     * @param string $code Mã phương pháp
     * @return ValuationMethod|null Đối tượng ValuationMethod nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?ValuationMethod;

    /**
     * Lấy tất cả phương pháp định giá.
     *
     * @return ValuationMethod[] Danh sách tất cả phương pháp
     */
    public function findAll(): array;

    /**
     * Lưu phương pháp định giá (thêm mới hoặc cập nhật).
     *
     * @param ValuationMethod $method Đối tượng ValuationMethod cần lưu
     * @return void
     */
    public function save(ValuationMethod $method): void;

    /**
     * Xóa phương pháp định giá theo ID.
     *
     * @param string $id ID của phương pháp cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
