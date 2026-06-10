<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\ExchangeRate;

/**
 * Giao diện repository cho tỷ giá hối đoái.
 *
 * Cung cấp các phương thức truy xuất và thao tác với tỷ giá ngoại tệ
 * phục vụ quy đổi tiền tệ trong hạch toán kế toán.
 */
interface ExchangeRateRepositoryInterface
{
    /**
     * Tìm tỷ giá theo ID.
     *
     * @param string $id ID của tỷ giá
     * @return ExchangeRate|null Đối tượng ExchangeRate nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?ExchangeRate;

    /**
     * Tìm tỷ giá theo mã.
     *
     * @param string $code Mã tỷ giá
     * @return ExchangeRate|null Đối tượng ExchangeRate nếu tìm thấy, null nếu không
     */
    public function findByCode(string $code): ?ExchangeRate;

    /**
     * Lấy tất cả tỷ giá.
     *
     * @return ExchangeRate[] Danh sách tất cả tỷ giá
     */
    public function findAll(): array;

    /**
     * Lưu tỷ giá (thêm mới hoặc cập nhật).
     *
     * @param ExchangeRate $rate Đối tượng ExchangeRate cần lưu
     * @return void
     */
    public function save(ExchangeRate $rate): void;

    /**
     * Xóa tỷ giá theo ID.
     *
     * @param string $id ID của tỷ giá cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
