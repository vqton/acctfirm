<?php
declare(strict_types=1);
namespace Accounting\Domain\Repository;

use Accounting\Domain\Model\SalesOrder;

/**
 * Giao diện repository cho đơn bán hàng (Sales Order).
 *
 * Cung cấp các phương thức truy xuất và thao tác với đơn bán hàng,
 * bao gồm tra cứu theo khách hàng, trạng thái và quản lý liên kết.
 */
interface SalesOrderRepositoryInterface
{
    /**
     * Tìm đơn bán hàng theo ID.
     *
     * @param string $id ID của đơn bán hàng
     * @return SalesOrder|null Đối tượng SalesOrder nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?SalesOrder;

    /**
     * Tìm đơn bán hàng theo số tham chiếu.
     *
     * @param string $reference Số tham chiếu
     * @return SalesOrder|null Đối tượng SalesOrder nếu tìm thấy, null nếu không
     */
    public function findByReference(string $reference): ?SalesOrder;

    /**
     * Tìm đơn bán hàng theo khách hàng.
     *
     * @param int $customerId ID của khách hàng
     * @return SalesOrder[] Danh sách đơn bán hàng
     */
    public function findByCustomer(int $customerId): array;

    /**
     * Tìm đơn bán hàng theo trạng thái.
     *
     * @param string $status Trạng thái đơn bán hàng
     * @return SalesOrder[] Danh sách đơn bán hàng
     */
    public function findByStatus(string $status): array;

    /**
     * Lấy tất cả đơn bán hàng, phân trang.
     *
     * @param int $limit Số lượng tối đa (mặc định 50)
     * @param int $offset Số lượng bỏ qua (mặc định 0)
     * @return SalesOrder[] Danh sách đơn bán hàng
     */
    public function findAll(int $limit = 50, int $offset = 0): array;

    /**
     * Lưu đơn bán hàng (thêm mới hoặc cập nhật).
     *
     * @param SalesOrder $order Đối tượng SalesOrder cần lưu
     * @return void
     */
    public function save(SalesOrder $order): void;

    /**
     * Xóa đơn bán hàng theo ID.
     *
     * @param string $id ID của đơn bán hàng cần xóa
     * @return void
     */
    public function delete(string $id): void;

    /**
     * Đếm số đơn bán hàng theo trạng thái.
     *
     * @param string $status Trạng thái cần đếm
     * @return int Số lượng đơn bán hàng
     */
    public function countByStatus(string $status): int;

    /**
     * Lưu liên kết giữa đơn bán hàng và chứng từ khác.
     *
     * @param string $orderId ID của đơn bán hàng
     * @param string $linkedType Loại chứng từ liên kết
     * @param string $linkedId ID của chứng từ liên kết
     * @param string|null $linkedRef Số tham chiếu của chứng từ liên kết
     * @param float $amount Số tiền liên kết
     * @param string $createdBy Người tạo liên kết
     * @return void
     */
    public function saveLink(string $orderId, string $linkedType, string $linkedId, ?string $linkedRef, float $amount, string $createdBy): void;

    /**
     * Lấy danh sách liên kết của đơn bán hàng.
     *
     * @param string $orderId ID của đơn bán hàng
     * @return array Danh sách liên kết
     */
    public function getLinks(string $orderId): array;
}
