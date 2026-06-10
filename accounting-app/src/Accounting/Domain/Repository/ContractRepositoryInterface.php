<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Contract;

/**
 * Giao diện repository cho hợp đồng.
 *
 * Cung cấp các phương thức truy xuất và thao tác với hợp đồng kinh tế,
 * bao gồm tra cứu theo mã hợp đồng và quản lý vòng đời hợp đồng.
 */
interface ContractRepositoryInterface
{
    /**
     * Tìm hợp đồng theo ID.
     *
     * @param string $id ID của hợp đồng
     * @return Contract|null Đối tượng Contract nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?Contract;

    /**
     * Tìm hợp đồng theo mã số.
     *
     * @param string $code Mã số hợp đồng
     * @return Contract|null Đối tượng Contract nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?Contract;

    /**
     * Lấy tất cả hợp đồng.
     *
     * @return Contract[] Danh sách tất cả hợp đồng
     */
    public function findAll(): array;

    /**
     * Lưu hợp đồng (thêm mới hoặc cập nhật).
     *
     * @param Contract $contract Đối tượng Contract cần lưu
     * @return void
     */
    public function save(Contract $contract): void;

    /**
     * Xóa hợp đồng theo ID.
     *
     * @param string $id ID của hợp đồng cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
