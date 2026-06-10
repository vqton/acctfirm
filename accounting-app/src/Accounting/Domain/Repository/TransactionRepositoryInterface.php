<?php
// src/Accounting/Domain/Repository/TransactionRepositoryInterface.php

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\Transaction;

/**
 * Giao diện repository cho giao dịch kế toán (Transaction).
 *
 * Cung cấp các phương thức truy xuất và thao tác với bút toán,
 * bao gồm tra cứu theo tham chiếu, khoảng ngày, kỳ kế toán và bút toán điều chỉnh.
 */
interface TransactionRepositoryInterface
{
    /**
     * Tìm giao dịch theo ID.
     *
     * @param string $id ID của giao dịch
     * @return Transaction|null Đối tượng Transaction nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?Transaction;

    /**
     * Tìm giao dịch theo số tham chiếu.
     *
     * @param string $reference Số tham chiếu chứng từ
     * @return Transaction|null Đối tượng Transaction nếu tìm thấy, null nếu không
     */
    public function findByReference(string $reference): ?Transaction;

    /**
     * Lưu giao dịch (thêm mới hoặc cập nhật).
     *
     * @param Transaction $transaction Đối tượng Transaction cần lưu
     * @return void
     */
    public function save(Transaction $transaction): void;

    /**
     * Lấy tất cả giao dịch.
     *
     * @return Transaction[] Danh sách tất cả giao dịch
     */
    public function getAll(): array;

    /**
     * Lấy giao dịch trong khoảng ngày.
     *
     * @param \DateTimeInterface $start Ngày bắt đầu
     * @param \DateTimeInterface $end Ngày kết thúc
     * @return Transaction[] Danh sách giao dịch trong khoảng
     */
    public function getTransactionsByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array;

    /**
     * Lấy giao dịch theo kỳ kế toán.
     *
     * @param string $periodCode Mã kỳ kế toán
     * @return Transaction[] Danh sách giao dịch thuộc kỳ
     */
    public function getTransactionsByPeriod(string $periodCode): array;

    /**
     * Lấy các bút toán điều chỉnh theo ID bút toán gốc.
     *
     * @param string $originalId ID của bút toán gốc
     * @return Transaction[] Danh sách bút toán điều chỉnh
     */
    public function getCorrectionsByOriginalId(string $originalId): array;
}
