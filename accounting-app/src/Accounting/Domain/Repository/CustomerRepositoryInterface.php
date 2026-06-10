<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Customer;

/**
 * Giao diện repository cho khách hàng.
 *
 * Cung cấp các phương thức truy xuất và thao tác với danh sách khách hàng
 * (TK 131 - Phải thu khách hàng).
 */
interface CustomerRepositoryInterface
{
    /**
     * Tìm khách hàng theo ID.
     *
     * @param string $id ID của khách hàng
     * @return Customer|null Đối tượng Customer nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?Customer;

    /**
     * Tìm khách hàng theo mã.
     *
     * @param string $code Mã khách hàng
     * @return Customer|null Đối tượng Customer nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?Customer;

    /**
     * Lấy tất cả khách hàng.
     *
     * @return Customer[] Danh sách tất cả khách hàng
     */
    public function findAll(): array;

    /**
     * Lưu khách hàng (thêm mới hoặc cập nhật).
     *
     * @param Customer $customer Đối tượng Customer cần lưu
     * @return void
     */
    public function save(Customer $customer): void;

    /**
     * Xóa khách hàng theo ID.
     *
     * @param string $id ID của khách hàng cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
