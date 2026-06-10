<?php
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\ProductionOrder;

/**
 * Giao diện repository cho lệnh sản xuất (Production Order).
 *
 * Cung cấp các phương thức truy xuất và thao tác với lệnh sản xuất,
 * bao gồm tra cứu vật tư, nhân công và chi phí sản xuất chung.
 */
interface ProductionOrderRepositoryInterface
{
    /**
     * Tìm lệnh sản xuất theo ID.
     *
     * @param string $id ID của lệnh sản xuất
     * @return ProductionOrder|null Đối tượng ProductionOrder nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?ProductionOrder;

    /**
     * Tìm lệnh sản xuất theo số tham chiếu.
     *
     * @param string $ref Số tham chiếu
     * @return ProductionOrder|null Đối tượng ProductionOrder nếu tìm thấy, null nếu không
     */
    public function findByReference(string $ref): ?ProductionOrder;

    /**
     * Lấy tất cả lệnh sản xuất.
     *
     * @return ProductionOrder[] Danh sách tất cả lệnh sản xuất
     */
    public function findAll(): array;

    /**
     * Lưu lệnh sản xuất (thêm mới hoặc cập nhật).
     *
     * @param ProductionOrder $order Đối tượng ProductionOrder cần lưu
     * @return void
     */
    public function save(ProductionOrder $order): void;

    /**
     * Xóa lệnh sản xuất theo ID.
     *
     * @param string $id ID của lệnh sản xuất cần xóa
     * @return void
     */
    public function delete(string $id): void;

    /**
     * Lấy danh sách vật tư của lệnh sản xuất.
     *
     * @param string $poId ID của lệnh sản xuất
     * @return array Danh sách vật tư
     */
    public function getMaterials(string $poId): array;

    /**
     * Lấy danh sách nhân công của lệnh sản xuất.
     *
     * @param string $poId ID của lệnh sản xuất
     * @return array Danh sách nhân công
     */
    public function getLabor(string $poId): array;

    /**
     * Lấy danh sách chi phí sản xuất chung của lệnh sản xuất.
     *
     * @param string $poId ID của lệnh sản xuất
     * @return array Danh sách chi phí sản xuất chung
     */
    public function getOverhead(string $poId): array;
}
