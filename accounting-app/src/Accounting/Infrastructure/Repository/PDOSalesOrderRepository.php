<?php
declare(strict_types=1);
namespace Accounting\Infrastructure\Repository;

use Accounting\Domain\Model\SalesOrder;
use Accounting\Domain\Model\SalesOrderLine;
use Accounting\Domain\Repository\SalesOrderRepositoryInterface;
use PDO;

/**
 * Repository PDO cho module Đơn bán hàng (Sales Orders).
 *
 * Triển khai các thao tác CRUD với bảng sales_orders, sales_order_lines,
 * sales_order_links sử dụng PDO prepared statements.
 */
class PDOSalesOrderRepository implements SalesOrderRepositoryInterface
{
    private PDO $pdo;

    /**
     * @param PDO $pdo Kết nối PDO đến MySQL
     */
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    /**
     * Tìm đơn bán hàng theo ID.
     *
     * @param string $id ID của đơn bán hàng
     * @return SalesOrder|null Đối tượng SalesOrder nếu tìm thấy, null nếu không
     */
    public function findById(string $id): ?SalesOrder
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales_orders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $order = $this->hydrate($row);
        $order->setLines($this->loadLines((int)$id));
        return $order;
    }

    /**
     * Tìm đơn bán hàng theo số tham chiếu.
     *
     * @param string $reference Số tham chiếu của đơn bán hàng
     * @return SalesOrder|null Đối tượng SalesOrder nếu tìm thấy, null nếu không
     */
    public function findByReference(string $reference): ?SalesOrder
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales_orders WHERE reference = ?');
        $stmt->execute([$reference]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return $this->hydrate($row);
    }

    /**
     * Tìm đơn bán hàng theo mã khách hàng.
     *
     * @param int $customerId ID của khách hàng
     * @return SalesOrder[] Danh sách đơn bán hàng, sắp xếp theo created_at DESC
     */
    public function findByCustomer(int $customerId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales_orders WHERE customer_id = ? ORDER BY created_at DESC');
        $stmt->execute([$customerId]);
        return $this->hydrateAll($stmt);
    }

    /**
     * Tìm đơn bán hàng theo trạng thái.
     *
     * @param string $status Trạng thái đơn hàng (draft, confirmed, processing, shipped, delivered, cancelled, etc.)
     * @return SalesOrder[] Danh sách đơn bán hàng, sắp xếp theo created_at DESC
     */
    public function findByStatus(string $status): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales_orders WHERE status = ? ORDER BY created_at DESC');
        $stmt->execute([$status]);
        return $this->hydrateAll($stmt);
    }

    /**
     * Lấy danh sách đơn bán hàng có phân trang.
     *
     * @param int $limit Số lượng bản ghi tối đa (mặc định 50)
     * @param int $offset Số lượng bản ghi bỏ qua (mặc định 0)
     * @return SalesOrder[] Danh sách đơn bán hàng
     */
    public function findAll(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales_orders ORDER BY created_at DESC LIMIT ? OFFSET ?');
        $stmt->execute([$limit, $offset]);
        return $this->hydrateAll($stmt);
    }

    /**
     * Lưu đơn bán hàng (thêm mới hoặc cập nhật).
     *
     * Sử dụng ON DUPLICATE KEY UPDATE để hỗ trợ cả insert và update.
     * Nếu đơn hàng có ID numeric, tự động lưu các dòng chi tiết.
     *
     * @param SalesOrder $order Đối tượng SalesOrder cần lưu
     * @return void
     */
    public function save(SalesOrder $order): void
    {
        $id = $order->getId();
        $stmt = $this->pdo->prepare(
            'INSERT INTO sales_orders (id, reference, customer_id, order_date, delivery_date, payment_terms, payment_method, status, currency, exchange_rate, total_amount, discount_amount, tax_amount, grand_total, amount_paid, amount_invoiced, notes, is_quotation_converted, quotation_id, created_by, approved_by, cancelled_by, cancel_reason, cancelled_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
             reference=VALUES(reference), customer_id=VALUES(customer_id), order_date=VALUES(order_date),
             delivery_date=VALUES(delivery_date), payment_terms=VALUES(payment_terms),
             payment_method=VALUES(payment_method), status=VALUES(status),
             total_amount=VALUES(total_amount), discount_amount=VALUES(discount_amount),
             tax_amount=VALUES(tax_amount), grand_total=VALUES(grand_total),
             amount_paid=VALUES(amount_paid), amount_invoiced=VALUES(amount_invoiced),
             notes=VALUES(notes), updated_at=NOW()'
        );
        $stmt->execute([
            $id, $order->getReference(), $order->getCustomerId(),
            $order->getOrderDate(), $order->getDeliveryDate(),
            $order->getPaymentTerms(), $order->getPaymentMethod(),
            $order->getStatus(), $order->getCurrency(), $order->getExchangeRate(),
            $order->getTotalAmount(), $order->getDiscountAmount(), $order->getTaxAmount(),
            $order->getGrandTotal(), $order->getAmountPaid(), $order->getAmountInvoiced(),
            $order->getNotes(), $order->getIsQuotationConverted() ? 1 : 0,
            $order->getQuotationId(), $order->getCreatedBy(), $order->getApprovedBy(),
            $order->getCancelledBy(), $order->getCancelReason(), $order->getCancelledAt(),
        ]);

        if ($id && is_numeric($id)) {
            $this->saveLines((int)$id, $order->getLines());
        }
    }

    /**
     * Xóa đơn bán hàng và các dữ liệu liên quan.
     *
     * Xóa theo thứ tự: links → lines → order.
     *
     * @param string $id ID của đơn bán hàng cần xóa
     * @return void
     */
    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM sales_order_links WHERE sales_order_id = ?');
        $stmt->execute([$id]);
        $stmt = $this->pdo->prepare('DELETE FROM sales_order_lines WHERE sales_order_id = ?');
        $stmt->execute([$id]);
        $stmt = $this->pdo->prepare('DELETE FROM sales_orders WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Đếm số lượng đơn bán hàng theo trạng thái.
     *
     * @param string $status Trạng thái cần đếm
     * @return int Số lượng đơn bán hàng
     */
    public function countByStatus(string $status): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM sales_orders WHERE status = ?');
        $stmt->execute([$status]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Lưu liên kết giữa đơn bán hàng và đối tượng khác (hóa đơn, phiếu xuất...).
     *
     * @param string $orderId ID của đơn bán hàng
     * @param string $linkedType Loại đối tượng liên kết (invoice, delivery_note, etc.)
     * @param string $linkedId ID của đối tượng liên kết
     * @param string|null $linkedRef Số tham chiếu của đối tượng liên kết
     * @param float $amount Số tiền liên kết
     * @param string $createdBy Người tạo liên kết
     * @return void
     */
    public function saveLink(string $orderId, string $linkedType, string $linkedId, ?string $linkedRef, float $amount, string $createdBy): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sales_order_links (sales_order_id, linked_type, linked_id, linked_reference, amount, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$orderId, $linkedType, $linkedId, $linkedRef, $amount, $createdBy]);
    }

    /**
     * Lấy danh sách liên kết của một đơn bán hàng.
     *
     * @param string $orderId ID của đơn bán hàng
     * @return array Danh sách các liên kết (mảng kết hợp)
     */
    public function getLinks(string $orderId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales_order_links WHERE sales_order_id = ? ORDER BY created_at');
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Nạp danh sách dòng chi tiết của đơn bán hàng.
     *
     * @param int $orderId ID của đơn bán hàng
     * @return SalesOrderLine[] Danh sách các dòng chi tiết
     */
    private function loadLines(int $orderId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales_order_lines WHERE sales_order_id = ? ORDER BY sort_order, line_no');
        $stmt->execute([$orderId]);
        $lines = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $lines[] = new SalesOrderLine(
                (string)$row['id'], (string)$row['sales_order_id'], (int)$row['line_no'],
                $row['item_id'] ? (int)$row['item_id'] : null, $row['item_code'],
                $row['item_name'], $row['unit'], (float)$row['qty_ordered'],
                (float)$row['qty_shipped'], (float)$row['qty_invoiced'],
                (float)$row['unit_price'], (float)$row['discount_pct'],
                (float)$row['discount_amount'], (float)$row['tax_rate'],
                (float)$row['tax_amount'], (float)$row['line_total'],
                (bool)$row['is_service'], (int)$row['sort_order']
            );
        }
        return $lines;
    }

    /**
     * Lưu danh sách dòng chi tiết cho đơn bán hàng.
     *
     * Xóa các dòng cũ trước khi insert các dòng mới.
     *
     * @param int $orderId ID của đơn bán hàng
     * @param SalesOrderLine[] $lines Danh sách các dòng chi tiết
     * @return void
     */
    private function saveLines(int $orderId, array $lines): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM sales_order_lines WHERE sales_order_id = ?');
        $stmt->execute([$orderId]);
        if (empty($lines)) return;
        $insert = $this->pdo->prepare(
            'INSERT INTO sales_order_lines (sales_order_id, line_no, item_id, item_code, item_name, unit, qty_ordered, qty_shipped, qty_invoiced, unit_price, discount_pct, discount_amount, tax_rate, tax_amount, line_total, is_service, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($lines as $line) {
            $insert->execute([
                $orderId, $line->getLineNo(), $line->getItemId(), $line->getItemCode(),
                $line->getItemName(), $line->getUnit(), $line->getQtyOrdered(),
                $line->getQtyShipped(), $line->getQtyInvoiced(), $line->getUnitPrice(),
                $line->getDiscountPct(), $line->getDiscountAmount(), $line->getTaxRate(),
                $line->getTaxAmount(), $line->getLineTotal(), $line->getIsService() ? 1 : 0,
                $line->getSortOrder(),
            ]);
        }
    }

    /**
     * Hydrate một dòng dữ liệu từ database thành đối tượng SalesOrder.
     *
     * @param array $row Mảng dữ liệu từ database (PDO::FETCH_ASSOC)
     * @return SalesOrder Đối tượng SalesOrder đã được khởi tạo
     */
    private function hydrate(array $row): SalesOrder
    {
        return new SalesOrder(
            (string)$row['id'], $row['reference'], (int)$row['customer_id'],
            $row['order_date'], $row['delivery_date'], $row['payment_terms'],
            $row['payment_method'], $row['status'], $row['currency'],
            (float)$row['exchange_rate'], (float)$row['total_amount'],
            (float)$row['discount_amount'], (float)$row['tax_amount'],
            (float)$row['grand_total'], (float)$row['amount_paid'],
            (float)$row['amount_invoiced'], $row['notes'],
            (bool)$row['is_quotation_converted'], $row['quotation_id'],
            $row['created_by'], $row['approved_by'], $row['cancelled_by'],
            $row['cancel_reason'], $row['cancelled_at'], $row['created_at'], $row['updated_at']
        );
    }

    /**
     * Hydrate nhiều dòng dữ liệu từ statement thành mảng các SalesOrder.
     *
     * @param \PDOStatement $stmt PDO statement đã được execute
     * @return SalesOrder[] Danh sách đối tượng SalesOrder
     */
    private function hydrateAll(\PDOStatement $stmt): array
    {
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        return $items;
    }
}
