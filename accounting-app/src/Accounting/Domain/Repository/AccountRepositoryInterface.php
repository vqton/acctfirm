<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Account;

/**
 * Giao diện repository cho tài khoản kế toán (Hệ thống tài khoản Circular 99).
 *
 * Cung cấp các phương thức truy xuất và thao tác với tài khoản kế toán,
 * bao gồm tra cứu theo mã, tìm kiếm, và tính số dư theo cấp.
 */
interface AccountRepositoryInterface
{
    /**
     * Tìm tài khoản theo ID.
     *
     * @param string $id ID của tài khoản
     * @return Account|null Đối tượng Account nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?Account;

    /**
     * Tìm tài khoản theo mã số (ví dụ: "1111", "156").
     *
     * @param string $code Mã số tài khoản
     * @return Account|null Đối tượng Account nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?Account;

    /**
     * Lấy tất cả tài khoản.
     *
     * @return Account[] Danh sách tất cả tài khoản
     */
    public function findAll(): array;

    /**
     * Lưu tài khoản (thêm mới hoặc cập nhật).
     *
     * @param Account $account Đối tượng Account cần lưu
     * @return void
     */
    public function save(Account $account): void;

    /**
     * Xóa tài khoản theo ID.
     *
     * @param string $id ID của tài khoản cần xóa
     * @return void
     */
    public function delete(string $id): void;

    /**
     * Tìm tài khoản theo mã ánh xạ báo cáo tài chính.
     *
     * @param string $fsMappingCode Mã ánh xạ báo cáo tài chính
     * @return Account[] Danh sách tài khoản phù hợp
     */
    public function findByFsMapping(string $fsMappingCode): array;

    /**
     * Tìm các tài khoản tổng hợp (control accounts).
     *
     * @return Account[] Danh sách tài khoản tổng hợp
     */
    public function findControlAccounts(): array;

    /**
     * Tìm các tài khoản bị khóa.
     *
     * @return Account[] Danh sách tài khoản bị khóa
     */
    public function findLocked(): array;

    /**
     * Tìm tài khoản theo loại (asset, liability, equity, revenue, expense).
     *
     * @param string $type Loại tài khoản
     * @return Account[] Danh sách tài khoản thuộc loại
     */
    public function findByType(string $type): array;

    /**
     * Tìm kiếm tài khoản theo từ khóa.
     *
     * @param string $query Từ khóa tìm kiếm
     * @return Account[] Danh sách tài khoản phù hợp
     */
    public function search(string $query): array;

    /**
     * Đếm tổng số tài khoản.
     *
     * @return int Số lượng tài khoản
     */
    public function count(): int;

    /**
     * TÍNH SỐ DƯ THEO CẤP: Trả về tổng số dư của tài khoản và tất cả tài khoản con.
     * Giải pháp cho vấn đề "control account balance = 0" mà không cần liệt kê từng TK con.
     * Composite pattern: parent delegat∑∑∑es to children recursively.
     * RỦI RO: Nếu cây tài khoản có chu trình (A→B→A), đệ quy sẽ không bao giờ dừng.
     * Biện pháp: accounts.parent_id được kiểm soát chặt (không cho set parent_id tạo cycle).
     *
     * @param string $code Mã tài khoản cần tính số dư
     * @return float Tổng số dư của tài khoản và các tài khoản con
     */
    public function getTreeBalance(string $code): float;
}
