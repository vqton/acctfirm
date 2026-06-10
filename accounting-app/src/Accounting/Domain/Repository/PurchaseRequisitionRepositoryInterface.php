<?php

declare(strict_types=1);

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\PurchaseRequisition;

/**
 * Giao diện repository cho đề nghị mua hàng (Purchase Requisition).
 *
 * Cung cấp các phương thức truy xuất và thao tác với đề nghị mua hàng
 * nội bộ, bao gồm tra cứu theo số PR và trạng thái phê duyệt.
 */
interface PurchaseRequisitionRepositoryInterface
{
    /**
     * Tìm đề nghị mua hàng theo ID.
     *
     * @param string $id ID của đề nghị mua hàng
     * @return PurchaseRequisition|null Đối tượng PurchaseRequisition nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?PurchaseRequisition;

    /**
     * Tìm đề nghị mua hàng theo số PR.
     *
     * @param string $prNumber Số đề nghị mua hàng
     * @return PurchaseRequisition|null Đối tượng PurchaseRequisition nếu tìm thấy, null nếu không
     */
    public function findOneByPrNumber(string $prNumber): ?PurchaseRequisition;

    /**
     * Tìm đề nghị mua hàng theo trạng thái.
     *
     * @param string $status Trạng thái đề nghị
     * @return PurchaseRequisition[] Danh sách đề nghị
     */
    public function findByStatus(string $status): array;

    /**
     * Lấy tất cả đề nghị mua hàng.
     *
     * @return PurchaseRequisition[] Danh sách tất cả đề nghị
     */
    public function findAll(): array;

    /**
     * Lưu đề nghị mua hàng (thêm mới hoặc cập nhật).
     *
     * @param PurchaseRequisition $requisition Đối tượng PurchaseRequisition cần lưu
     * @return void
     */
    public function save(PurchaseRequisition $requisition): void;

    /**
     * Xóa đề nghị mua hàng theo ID.
     *
     * @param string $id ID của đề nghị cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
