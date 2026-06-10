<?php

declare(strict_types=1);

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\PurchaseInvoiceMatch;

/**
 * Giao diện repository cho đối chiếu hóa đơn mua hàng (3-way matching).
 *
 * Cung cấp các phương thức truy xuất và thao tác với việc đối chiếu
 * giữa hóa đơn, đơn đặt hàng và phiếu nhập kho.
 */
interface PurchaseInvoiceMatchRepositoryInterface
{
    /**
     * Tìm đối chiếu theo ID.
     *
     * @param string $id ID của đối chiếu
     * @return PurchaseInvoiceMatch|null Đối tượng PurchaseInvoiceMatch nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?PurchaseInvoiceMatch;

    /**
     * Tìm đối chiếu theo đơn đặt hàng.
     *
     * @param string $poId ID của đơn đặt hàng
     * @return PurchaseInvoiceMatch[] Danh sách đối chiếu
     */
    public function findByPoId(string $poId): array;

    /**
     * Tìm đối chiếu theo trạng thái.
     *
     * @param string $status Trạng thái đối chiếu
     * @return PurchaseInvoiceMatch[] Danh sách đối chiếu
     */
    public function findByStatus(string $status): array;

    /**
     * Lấy tất cả đối chiếu.
     *
     * @return PurchaseInvoiceMatch[] Danh sách tất cả đối chiếu
     */
    public function findAll(): array;

    /**
     * Lưu đối chiếu (thêm mới hoặc cập nhật).
     *
     * @param PurchaseInvoiceMatch $match Đối tượng PurchaseInvoiceMatch cần lưu
     * @return void
     */
    public function save(PurchaseInvoiceMatch $match): void;

    /**
     * Xóa đối chiếu theo ID.
     *
     * @param string $id ID của đối chiếu cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
