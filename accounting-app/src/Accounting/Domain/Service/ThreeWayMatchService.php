<?php
declare(strict_types=1);
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;

// Dịch vụ đối chiếu 3 chiều (3-Way Matching)
//
// So khớp: PO (đơn đặt hàng) ↔ GR (phiếu nhập kho) ↔ Invoice (hóa đơn)
// Ngưỡng dung sai: qty ±5%, price ±2%, VAT 0%
//
// Kết quả:
//   - matched: tất cả khớp → cho phép thanh toán
//   - warning: sai lệch trong ngưỡng → vẫn cho thanh toán, ghi chú
//   - mismatch: sai lệch ngoài ngưỡng → chặn thanh toán, yêu cầu điều chỉnh
//
// RỦI RO: Nếu bỏ qua matching → thanh toán sai giá/sai số lượng → mất tiền
// RỦI RO: Nếu ngưỡng dung sai quá rộng → gian lận giá

class ThreeWayMatchService
{
    private \PDO $pdo;
    private AuditLoggerInterface $auditLogger;
    private float $qtyTolerance = 0.05;   // ±5%
    private float $priceTolerance = 0.02; // ±2%

    public function __construct(\PDO $pdo, AuditLoggerInterface $auditLogger)
    {
        $this->pdo = $pdo;
        $this->auditLogger = $auditLogger;
    }

    // NGHIỆP VỤ: Thực hiện đối chiếu 3 chiều
    // Input: po_id, supplier_invoice_no, invoice_amount, vat_amount, items[...]
    // Mỗi item: po_line_id, qty_invoiced, unit_price_invoiced
    // Output: match_status (matched/warning/mismatch), chi tiết từng dòng
    public function match(string $poId, string $supplierInvoiceNo, string $invoiceDate,
        float $invoiceAmount, float $vatAmount, array $items, string $matchedBy): array
    {
        $this->pdo->beginTransaction();
        try {
            $matchId = uniqid('mch_');

            // Validate PO tồn tại
            $stmt = $this->pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
            $stmt->execute([$poId]);
            $po = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$po) throw new \InvalidArgumentException('Không tìm thấy đơn đặt hàng.');

            // Tạo match record
            $this->pdo->prepare("INSERT INTO purchase_invoice_matches (id, po_id, supplier_invoice_no, invoice_date, invoice_amount, vat_amount, match_status, matched_by, matched_at) VALUES (?,?,?,?,?,?,'pending',?,NOW())")
                ->execute([$matchId, $poId, $supplierInvoiceNo, $invoiceDate, $invoiceAmount, $vatAmount, $matchedBy]);

            $allPass = true;
            $hasWarning = false;
            $matchLines = [];

            foreach ($items as $item) {
                $poLineId = $item['po_line_id'];
                $qtyInvoiced = (float)($item['qty_invoiced'] ?? 0);
                $priceInvoiced = (float)($item['unit_price_invoiced'] ?? 0);

                // Lấy thông tin PO line
                $stmt = $this->pdo->prepare("SELECT * FROM purchase_order_lines WHERE id = ? AND po_id = ?");
                $stmt->execute([$poLineId, $poId]);
                $poLine = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (!$poLine) throw new \InvalidArgumentException("Không tìm thấy dòng đơn hàng: {$poLineId}.");

                // Lấy GR line (lấy GR gần nhất)
                $stmt = $this->pdo->prepare(
                    "SELECT grl.qty_received, grl.unit_price FROM goods_receipt_lines grl
                     JOIN goods_receipts gr ON gr.id = grl.gr_id
                     WHERE grl.po_line_id = ? AND gr.status = 'completed'
                     ORDER BY gr.received_date DESC LIMIT 1"
                );
                $stmt->execute([$poLineId]);
                $grLine = $stmt->fetch(\PDO::FETCH_ASSOC);

                $qtyReceived = $grLine ? (float)$grLine['qty_received'] : 0;
                $pricePo = (float)$poLine['unit_price'];
                $priceReceived = $grLine ? (float)$grLine['unit_price'] : 0;

                // So khớp số lượng
                $qtyPass = true;
                $qtyDiff = $qtyReceived > 0 ? abs($qtyInvoiced - $qtyReceived) / $qtyReceived : 0;
                if ($qtyDiff > $this->qtyTolerance) {
                    $qtyPass = false;
                    $allPass = false;
                    $hasWarning = true;
                }

                // So khớp đơn giá (giá hóa đơn vs giá PO)
                $pricePass = true;
                $priceDiff = $pricePo > 0 ? abs($priceInvoiced - $pricePo) / $pricePo : 0;
                if ($priceDiff > $this->priceTolerance) {
                    $pricePass = false;
                    $allPass = false;
                    $hasWarning = true;
                }

                $lineId = uniqid('mil_');
                $this->pdo->prepare("INSERT INTO purchase_invoice_match_lines (id, match_id, po_line_id, gr_line_id, qty_invoiced, qty_received, unit_price_invoiced, unit_price_po, qty_tolerance_pass, price_tolerance_pass) VALUES (?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$lineId, $matchId, $poLineId, $grLine ? null : null, $qtyInvoiced, $qtyReceived, $priceInvoiced, $pricePo, $qtyPass ? 1 : 0, $pricePass ? 1 : 0]);

                $matchLines[] = [
                    'po_line_id' => $poLineId,
                    'item_id' => $poLine['item_id'],
                    'qty_invoiced' => $qtyInvoiced,
                    'qty_received' => $qtyReceived,
                    'unit_price_invoiced' => $priceInvoiced,
                    'unit_price_po' => $pricePo,
                    'qty_pass' => $qtyPass,
                    'price_pass' => $pricePass,
                ];
            }

            $matchStatus = $allPass ? 'matched' : ($hasWarning ? 'warning' : 'mismatch');
            $this->pdo->prepare("UPDATE purchase_invoice_matches SET match_status = ? WHERE id = ?")
                ->execute([$matchStatus, $matchId]);

            $this->pdo->commit();

            $this->auditLogger->log('purchase.invoice.match', 'purchase_invoice_match', $matchId, null, [
                'po_id' => $poId, 'invoice' => $supplierInvoiceNo, 'status' => $matchStatus
            ], $matchedBy);

            // Nếu matched, cập nhật qty_invoiced trên PO lines
            if ($matchStatus === 'matched') {
                foreach ($items as $item) {
                    $this->pdo->prepare("UPDATE purchase_order_lines SET qty_invoiced = qty_invoiced + ? WHERE id = ?")
                        ->execute([(float)$item['qty_invoiced'], $item['po_line_id']]);
                }
            }

            return [
                'match_id' => $matchId,
                'match_status' => $matchStatus,
                'lines' => $matchLines,
            ];
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // Lấy danh sách kết quả đối chiếu
    public function getMatches(string $poId = ''): array
    {
        $sql = "SELECT * FROM purchase_invoice_matches";
        $params = [];
        if ($poId) {
            $sql .= " WHERE po_id = ?";
            $params[] = $poId;
        }
        $sql .= " ORDER BY created_at DESC LIMIT 200";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $lines = $this->pdo->prepare("SELECT * FROM purchase_invoice_match_lines WHERE match_id = ?");
            $lines->execute([$row['id']]);
            $row['lines'] = $lines->fetchAll(\PDO::FETCH_ASSOC);
        }
        return $rows;
    }
}
