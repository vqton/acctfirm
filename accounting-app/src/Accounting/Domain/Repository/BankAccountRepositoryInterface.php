<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\BankAccount;

/**
 * Giao diện repository cho tài khoản ngân hàng.
 *
 * Cung cấp các phương thức truy xuất và thao tác với tài khoản ngân hàng
 * của doanh nghiệp (TK 112 - Tiền gửi ngân hàng).
 */
interface BankAccountRepositoryInterface
{
    /**
     * Tìm tài khoản ngân hàng theo ID.
     *
     * @param string $id ID của tài khoản ngân hàng
     * @return BankAccount|null Đối tượng BankAccount nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?BankAccount;

    /**
     * Tìm tài khoản ngân hàng theo mã số.
     *
     * @param string $code Mã số tài khoản ngân hàng
     * @return BankAccount|null Đối tượng BankAccount nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?BankAccount;

    /**
     * Lấy tất cả tài khoản ngân hàng.
     *
     * @return BankAccount[] Danh sách tất cả tài khoản ngân hàng
     */
    public function findAll(): array;

    /**
     * Lưu tài khoản ngân hàng (thêm mới hoặc cập nhật).
     *
     * @param BankAccount $account Đối tượng BankAccount cần lưu
     * @return void
     */
    public function save(BankAccount $account): void;

    /**
     * Xóa tài khoản ngân hàng theo ID.
     *
     * @param string $id ID của tài khoản ngân hàng cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
