<?php
declare(strict_types=1);

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\GoodsReceipt;

/**
 * Giao diện repository cho phiếu nhập kho (Goods Receipt).
 *
 * Cung cấp các phương thức truy xuất và thao tác với phiếu nhập kho,
 * bao gồm tra cứu theo số chứng từ và theo đơn đặt hàng.
 */
interface GoodsReceiptRepositoryInterface
{
    /**
     * Tìm phiếu nhập kho theo ID.
     *
     * @param string $id ID của phiếu nhập kho
     * @return GoodsReceipt|null Đối tượng GoodsReceipt nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?GoodsReceipt;

    /**
     * Tìm phiếu nhập kho theo số chứng từ.
     *
     * @param string $grNumber Số phiếu nhập kho
     * @return GoodsReceipt|null Đối tượng GoodsReceipt nếu tìm thấy, null nếu không
     */
    public function findOneByGrNumber(string $grNumber): ?GoodsReceipt;

    /**
     * Tìm danh sách phiếu nhập kho theo đơn đặt hàng.
     *
     * @param string $poId ID của đơn đặt hàng
     * @return GoodsReceipt[] Danh sách phiếu nhập kho
     */
    public function findByPoId(string $poId): array;

    /**
     * Lấy tất cả phiếu nhập kho, có thể lọc theo trạng thái.
     *
     * @param string|null $status Trạng thái phiếu nhập (null để lấy tất cả)
     * @param int $limit Số lượng tối đa (mặc định 50)
     * @return GoodsReceipt[] Danh sách phiếu nhập kho
     */
    public function findAll(?string $status = null, int $limit = 50): array;

    /**
     * Lưu phiếu nhập kho (thêm mới hoặc cập nhật).
     *
     * @param GoodsReceipt $receipt Đối tượng GoodsReceipt cần lưu
     * @return void
     */
    public function save(GoodsReceipt $receipt): void;

    /**
     * Xóa phiếu nhập kho theo ID.
     *
     * @param string $id ID của phiếu nhập kho cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
