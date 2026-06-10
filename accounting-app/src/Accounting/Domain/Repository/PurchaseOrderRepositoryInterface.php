<?php

declare(strict_types=1);

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\PurchaseOrder;

/**
 * Giao diện repository cho đơn đặt hàng (Purchase Order).
 *
 * Cung cấp các phương thức truy xuất và thao tác với đơn đặt hàng
 * nhà cung cấp, bao gồm tra cứu theo số PO và nhà cung cấp.
 */
interface PurchaseOrderRepositoryInterface
{
    /**
     * Tìm đơn đặt hàng theo ID.
     *
     * @param string $id ID của đơn đặt hàng
     * @return PurchaseOrder|null Đối tượng PurchaseOrder nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?PurchaseOrder;

    /**
     * Tìm đơn đặt hàng theo số PO.
     *
     * @param string $poNumber Số đơn đặt hàng
     * @return PurchaseOrder|null Đối tượng PurchaseOrder nếu tìm thấy, null nếu không
     */
    public function findOneByPoNumber(string $poNumber): ?PurchaseOrder;

    /**
     * Tìm đơn đặt hàng theo nhà cung cấp.
     *
     * @param string $supplierId ID của nhà cung cấp
     * @return PurchaseOrder[] Danh sách đơn đặt hàng
     */
    public function findBySupplier(string $supplierId): array;

    /**
     * Tìm đơn đặt hàng theo trạng thái.
     *
     * @param string $status Trạng thái đơn đặt hàng
     * @return PurchaseOrder[] Danh sách đơn đặt hàng
     */
    public function findByStatus(string $status): array;

    /**
     * Lấy tất cả đơn đặt hàng.
     *
     * @return PurchaseOrder[] Danh sách tất cả đơn đặt hàng
     */
    public function findAll(): array;

    /**
     * Lưu đơn đặt hàng (thêm mới hoặc cập nhật).
     *
     * @param PurchaseOrder $order Đối tượng PurchaseOrder cần lưu
     * @return void
     */
    public function save(PurchaseOrder $order): void;

    /**
     * Xóa đơn đặt hàng theo ID.
     *
     * @param string $id ID của đơn đặt hàng cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
