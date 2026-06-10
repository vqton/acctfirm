<?php
declare(strict_types=1);

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\GoodsReceiptLine;

/**
 * Giao diện repository cho dòng chi tiết phiếu nhập kho.
 *
 * Cung cấp các phương thức truy xuất và thao tác với từng dòng hàng hóa
 * trong phiếu nhập kho (Goods Receipt).
 */
interface GoodsReceiptLineRepositoryInterface
{
    /**
     * Tìm danh sách dòng nhập kho theo mã phiếu nhập.
     *
     * @param string $grId ID của phiếu nhập kho
     * @return GoodsReceiptLine[] Danh sách dòng nhập kho
     */
    public function findByGrId(string $grId): array;

    /**
     * Lưu dòng nhập kho (thêm mới hoặc cập nhật).
     *
     * @param GoodsReceiptLine $line Đối tượng GoodsReceiptLine cần lưu
     * @return void
     */
    public function save(GoodsReceiptLine $line): void;

    /**
     * Xóa tất cả dòng nhập kho theo mã phiếu nhập.
     *
     * @param string $grId ID của phiếu nhập kho
     * @return void
     */
    public function deleteByGrId(string $grId): void;
}
