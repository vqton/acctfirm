<?php

declare(strict_types=1);

namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\PurchaseApproval;

/**
 * Giao diện repository cho phê duyệt mua hàng.
 *
 * Cung cấp các phương thức truy xuất và thao tác với phê duyệt
 * đơn mua hàng theo từng loại chứng từ và người phê duyệt.
 */
interface PurchaseApprovalRepositoryInterface
{
    /**
     * Tìm phê duyệt theo ID.
     *
     * @param string $id ID của phê duyệt
     * @return PurchaseApproval|null Đối tượng PurchaseApproval nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?PurchaseApproval;

    /**
     * Tìm phê duyệt theo loại chứng từ và ID chứng từ.
     *
     * @param string $docType Loại chứng từ (requisition, order, ...)
     * @param string $docId ID của chứng từ
     * @return PurchaseApproval[] Danh sách phê duyệt
     */
    public function findByDoc(string $docType, string $docId): array;

    /**
     * Tìm phê duyệt theo người phê duyệt.
     *
     * @param string $approverId ID của người phê duyệt
     * @return PurchaseApproval[] Danh sách phê duyệt
     */
    public function findByApprover(string $approverId): array;

    /**
     * Tìm phê duyệt theo trạng thái.
     *
     * @param string $status Trạng thái phê duyệt
     * @return PurchaseApproval[] Danh sách phê duyệt
     */
    public function findByStatus(string $status): array;

    /**
     * Lấy tất cả phê duyệt.
     *
     * @return PurchaseApproval[] Danh sách tất cả phê duyệt
     */
    public function findAll(): array;

    /**
     * Lưu phê duyệt (thêm mới hoặc cập nhật).
     *
     * @param PurchaseApproval $approval Đối tượng PurchaseApproval cần lưu
     * @return void
     */
    public function save(PurchaseApproval $approval): void;

    /**
     * Xóa phê duyệt theo ID.
     *
     * @param string $id ID của phê duyệt cần xóa
     * @return void
     */
    public function delete(string $id): void;
}
