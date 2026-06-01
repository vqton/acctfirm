<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Account;

interface AccountRepositoryInterface
{
    public function findById(string $id): ?Account;
    public function findByCode(string $code): ?Account;
    public function findAll(): array;
    public function save(Account $account): void;
    public function delete(string $id): void;

    // COA mở rộng — Phase 0
    /** @return Account[] */
    public function findByFsMapping(string $fsMappingCode): array;

    /** @return Account[] */
    public function findControlAccounts(): array;

    /** @return Account[] */
    public function findLocked(): array;

    /** @return Account[] */
    public function findByType(string $type): array;

    /** @return Account[] */
    public function search(string $query): array;

    public function count(): int;

    // TÍNH SỐ DƯ THEO CẤP: Trả về tổng số dư của tài khoản và tất cả tài khoản con
    // Giải pháp cho vấn đề "control account balance = 0" mà không cần liệt kê từng TK con
    // Composite pattern: parent delegat∑∑∑es to children recursively
    // RỦI RO: Nếu cây tài khoản có chu trình (A→B→A), đệ quy sẽ không bao giờ dừng.
    // Biện pháp: accounts.parent_id được kiểm soát chặt (không cho set parent_id tạo cycle).
    public function getTreeBalance(string $code): float;
}
