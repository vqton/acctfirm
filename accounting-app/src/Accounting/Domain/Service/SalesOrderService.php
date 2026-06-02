<?php
declare(strict_types=1);
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\JournalServiceInterface;
use Accounting\Domain\Repository\SalesOrderRepositoryInterface;
use Accounting\Domain\Model\SalesOrder;
use Accounting\Domain\Model\SalesOrderLine;
use Accounting\Infrastructure\Auth;
use PDO;

class SalesOrderService
{
    private SalesOrderRepositoryInterface $orderRepo;
    private JournalServiceInterface $journal;
    private VoucherService $voucher;
    private InventoryService $inventory;
    private PDO $pdo;
    private ReportExportService $export;

    public function __construct(
        SalesOrderRepositoryInterface $orderRepo,
        JournalServiceInterface $journal,
        VoucherService $voucher,
        InventoryService $inventory,
        PDO $pdo,
        ReportExportService $export
    ) {
        $this->orderRepo = $orderRepo;
        $this->journal = $journal;
        $this->voucher = $voucher;
        $this->inventory = $inventory;
        $this->pdo = $pdo;
        $this->export = $export;
    }

    public function createOrder(array $data, string $createdBy): SalesOrder
    {
        $id = uniqid('so_');
        $reference = $this->voucher->nextNumber('SO');
        $order = new SalesOrder(
            $id, $reference, (int)$data['customer_id'], $data['order_date'] ?? date('Y-m-d'),
            $data['delivery_date'] ?? null, $data['payment_terms'] ?? null,
            $data['payment_method'] ?? null, 'draft', 'VND', 1.0,
            0, 0, 0, 0, 0, 0,
            $data['notes'] ?? null, false, null, $createdBy
        );
        $lines = [];
        foreach ($data['lines'] ?? [] as $i => $ld) {
            $amount = (float)$ld['unit_price'] * (float)$ld['qty_ordered'];
            $discAmt = $amount * ((float)($ld['discount_pct'] ?? 0)) / 100;
            $lineTotal = $amount - $discAmt;
            $taxAmt = $lineTotal * ((float)($ld['tax_rate'] ?? 10)) / 100;
            $lines[] = new SalesOrderLine(
                null, null, $i + 1,
                isset($ld['item_id']) ? (int)$ld['item_id'] : null,
                $ld['item_code'] ?? null, $ld['item_name'],
                $ld['unit'] ?? null, (float)$ld['qty_ordered'],
                0, 0, (float)$ld['unit_price'],
                (float)($ld['discount_pct'] ?? 0), $discAmt,
                (float)($ld['tax_rate'] ?? 10), $taxAmt,
                $lineTotal, (bool)($ld['is_service'] ?? false), $i + 1
            );
        }
        $order->setLines($lines);
        $order->updateAmounts();
        $this->orderRepo->save($order);
        return $order;
    }

    public function confirmOrder(string $id, string $userId): SalesOrder
    {
        $order = $this->orderRepo->findById($id);
        if (!$order) throw new \InvalidArgumentException('Không tìm thấy đơn hàng');
        if (!$order->canTransitionTo('confirmed')) {
            throw new \InvalidArgumentException('Đơn hàng không thể xác nhận từ trạng thái: ' . $order->getStatus());
        }
        $order->setStatus('confirmed');
        $order->setApprovedBy($userId);
        $this->orderRepo->save($order);
        return $order;
    }

    public function shipOrder(string $id, float $qty, string $userId): SalesOrder
    {
        $order = $this->orderRepo->findById($id);
        if (!$order) throw new \InvalidArgumentException('Không tìm thấy đơn hàng');
        if (!$order->canTransitionTo('partially_shipped') && $order->getStatus() !== 'confirmed' && $order->getStatus() !== 'partially_shipped') {
            throw new \InvalidArgumentException('Đơn hàng không thể xuất kho từ trạng thái: ' . $order->getStatus());
        }
        foreach ($order->getLines() as $line) {
            $remaining = $line->getQtyOrdered() - $line->getQtyShipped();
            if ($remaining > 0 && !$line->getIsService()) {
                $this->inventory->issueGoods(
                    $line->getItemId(), min($remaining, $qty),
                    $line->getLineTotal() / max($line->getQtyOrdered(), 1),
                    'Xuất kho theo đơn: ' . $order->getReference(),
                    $order->getReference(), $userId
                );
            }
            $line->setQtyShipped($line->getQtyShipped() + min($remaining, $qty));
        }
        $allShipped = true;
        foreach ($order->getLines() as $line) {
            if ($line->getQtyOrdered() > $line->getQtyShipped() + 0.001) $allShipped = false;
        }
        $order->setStatus($allShipped ? 'shipped' : 'partially_shipped');
        $this->orderRepo->save($order);
        $this->orderRepo->saveLink($id, 'delivery_order', $order->getReference(), $order->getReference(), $order->getGrandTotal(), $userId);
        return $order;
    }

    public function invoiceOrder(string $id, string $userId): SalesOrder
    {
        $order = $this->orderRepo->findById($id);
        if (!$order) throw new \InvalidArgumentException('Không tìm thấy đơn hàng');
        $newStatus = $order->getAmountInvoiced() <= 0 ? 'invoiced' : 'partially_invoiced';
        if (!$order->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException('Đơn hàng không thể xuất hóa đơn từ trạng thái: ' . $order->getStatus());
        }
        $this->pdo->beginTransaction();
        try {
            $entry = $this->journal->createDraft(
                [
                    ['account_code' => '131', 'is_debit' => true, 'amount' => $order->getGrandTotal()],
                    ['account_code' => '5111', 'is_debit' => false, 'amount' => $order->getTotalAmount()],
                    ['account_code' => '33311', 'is_debit' => false, 'amount' => $order->getTaxAmount()],
                ],
                'Doanh thu bán hàng - ' . $order->getReference(),
                $order->getReference(),
                $userId
            );
            $this->journal->postEntry($entry['id'], $userId);
            $order->setAmountInvoiced($order->getAmountInvoiced() + $order->getGrandTotal());
            $order->setStatus($newStatus);
            $this->orderRepo->save($order);
            $this->orderRepo->saveLink($id, 'sales_invoice', $entry['id'], $order->getReference(), $order->getGrandTotal(), $userId);
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        return $order;
    }

    public function receivePayment(string $id, float $amount, string $method, string $userId): SalesOrder
    {
        $order = $this->orderRepo->findById($id);
        if (!$order) throw new \InvalidArgumentException('Không tìm thấy đơn hàng');
        $newAmount = $order->getAmountPaid() + $amount;
        $newStatus = $newAmount >= $order->getGrandTotal() - 0.01 ? 'paid' : 'partially_paid';
        if ($newStatus === 'paid' && !$order->canTransitionTo('paid') && $order->getStatus() !== 'partially_paid') {
            throw new \InvalidArgumentException('Đơn hàng không thể thanh toán từ trạng thái: ' . $order->getStatus());
        }
        $this->pdo->beginTransaction();
        try {
            $accountCode = $method === 'bank' ? '1121' : '1111';
            $this->journal->createDraft(
                [
                    ['account_code' => $accountCode, 'is_debit' => true, 'amount' => $amount],
                    ['account_code' => '131', 'is_debit' => false, 'amount' => $amount],
                ],
                'Thu tiền đơn hàng - ' . $order->getReference(),
                $order->getReference(),
                $userId
            );
            $order->setAmountPaid($newAmount);
            $order->setStatus($newStatus);
            $this->orderRepo->save($order);
            $this->orderRepo->saveLink($id, 'receipt', $order->getReference(), $order->getReference(), $amount, $userId);
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        return $order;
    }

    public function cancelOrder(string $id, string $reason, string $userId): SalesOrder
    {
        $order = $this->orderRepo->findById($id);
        if (!$order) throw new \InvalidArgumentException('Không tìm thấy đơn hàng');
        if (!in_array($order->getStatus(), ['draft', 'confirmed', 'pending_stock'])) {
            throw new \InvalidArgumentException('Không thể hủy đơn hàng ở trạng thái: ' . $order->getStatus());
        }
        $order->setStatus('cancelled');
        $order->setCancelledBy($userId);
        $order->setCancelReason($reason);
        $order->setCancelledAt(date('Y-m-d H:i:s'));
        $this->orderRepo->save($order);
        return $order;
    }

    public function searchOrders(?int $customerId, ?string $status, ?string $dateFrom, ?string $dateTo, string $keyword = '', int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT * FROM sales_orders WHERE 1=1';
        $params = [];

        if ($customerId) { $sql .= ' AND customer_id = ?'; $params[] = $customerId; }
        if ($status) { $sql .= ' AND status = ?'; $params[] = $status; }
        if ($dateFrom) { $sql .= ' AND order_date >= ?'; $params[] = $dateFrom; }
        if ($dateTo) { $sql .= ' AND order_date <= ?'; $params[] = $dateTo; }
        if ($keyword) { $sql .= ' AND (reference LIKE ? OR notes LIKE ?)';
            $params[] = "%$keyword%"; $params[] = "%$keyword%"; }
        $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit; $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $orders = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $orders[] = $row;
        }
        return $orders;
    }

    public function getDashboardStats(): array
    {
        return [
            'draft' => $this->orderRepo->countByStatus('draft'),
            'confirmed' => $this->orderRepo->countByStatus('confirmed'),
            'shipped' => $this->orderRepo->countByStatus('shipped'),
            'invoiced' => $this->orderRepo->countByStatus('invoiced'),
            'paid' => $this->orderRepo->countByStatus('paid'),
            'cancelled' => $this->orderRepo->countByStatus('cancelled'),
        ];
    }

    public function getLinks(string $orderId): array
    {
        return $this->orderRepo->getLinks($orderId);
    }

    public function exportSalesOrders(string $format, array $filters): array
    {
        $orders = $this->searchOrders(
            $filters['customer_id'] ?? null,
            $filters['status'] ?? null,
            $filters['date_from'] ?? null,
            $filters['date_to'] ?? null,
            $filters['keyword'] ?? '',
            1000, 0
        );
        $headers = ['Số ĐH', 'Ngày', 'Khách hàng', 'Trạng thái', 'Tiền hàng', 'Thuế', 'Tổng cộng', 'Đã thu', 'Phương thức'];
        $rows = [];
        foreach ($orders as $o) {
            $rows[] = [
                $o['reference'], $o['order_date'], $o['customer_id'], $o['status'],
                $o['total_amount'], $o['tax_amount'], $o['grand_total'],
                $o['amount_paid'], $o['payment_method'] ?? '',
            ];
        }
        return $this->export->exportCsv($headers, $rows, 'don_hang_ban_' . date('Ymd') . '.csv');
    }
}
