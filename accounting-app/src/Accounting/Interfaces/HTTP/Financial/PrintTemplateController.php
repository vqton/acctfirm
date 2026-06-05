<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\PrintTemplateService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;
use PDO;
use RuntimeException;

// PrintTemplateController — R-10 Print Designer v1
//
// Endpoints:
//   GET    /api/print/templates?type=ap_invoice&active=1   — danh sách
//   GET    /api/print/templates/:id                        — chi tiết
//   POST   /api/print/templates                           — tạo mới / cập nhật
//   POST   /api/print/templates/:id/preview                — render preview với sample data
//   POST   /api/print/templates/:id/render                 — render với data thực (từ transaction)
//   DELETE /api/print/templates/:id                        — soft delete (deactivate)
class PrintTemplateController
{
    public function __construct(
        private PrintTemplateService $service,
        private PDO $pdo
    ) {}

    public function list(): void
    {
        Auth::requirePermission('print', 'read');
        $type = $_GET['type'] ?? null;
        $activeOnly = !isset($_GET['active']) || $_GET['active'] !== '0';
        JsonResponse::ok(['data' => $this->service->list($type, $activeOnly)]);
    }

    public function get(string $id): void
    {
        Auth::requirePermission('print', 'read');
        $tpl = $this->service->getById($id);
        if (!$tpl) {
            JsonResponse::error("Không tìm thấy template: {$id}", 404);
            return;
        }
        JsonResponse::ok($tpl);
    }

    public function save(): void
    {
        Auth::requirePermission('print', 'update');
        Auth::checkCsrf();
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $actor = Auth::user()['username'] ?? 'system';
        try {
            $id = $this->service->save($body, $actor);
            AuditLogger::log('print_template.save', 'print_template', $id, null, ['type' => $body['template_type'] ?? null, 'code' => $body['code'] ?? null], $actor);
            JsonResponse::ok(['id' => $id, 'message' => 'Đã lưu template']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function preview(string $id): void
    {
        Auth::requirePermission('print', 'read');
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $sample = $body['sample'] ?? [];
        try {
            $tpl = $this->service->getById($id);
            if (!$tpl) {
                JsonResponse::error("Không tìm thấy template: {$id}", 404);
                return;
            }
            $html = $this->service->render($tpl['content'], $sample);
            JsonResponse::ok(['html' => $html]);
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    // Render với data thực từ transaction (gắn với resource thực)
    public function render(string $id): void
    {
        Auth::requirePermission('print', 'read');
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $resourceType = $body['resource_type'] ?? null;
        $resourceId = $body['resource_id'] ?? null;
        if (!$resourceType || !$resourceId) {
            JsonResponse::error("Thiếu resource_type hoặc resource_id", 400);
            return;
        }
        $data = $this->loadResourceData($resourceType, $resourceId);
        try {
            $html = $this->service->renderById($id, $data);
            JsonResponse::ok(['html' => $html, 'data' => $data]);
        } catch (RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    public function deactivate(string $id): void
    {
        Auth::requirePermission('print', 'update');
        Auth::checkCsrf();
        $ok = $this->service->deactivate($id);
        if (!$ok) {
            JsonResponse::error("Không tìm thấy template: {$id}", 404);
            return;
        }
        $actor = Auth::user()['username'] ?? 'system';
        AuditLogger::log('print_template.deactivate', 'print_template', $id, null, null, $actor);
        JsonResponse::ok(['message' => 'Đã vô hiệu hóa template']);
    }

    // Load data thực từ DB cho 1 resource
    private function loadResourceData(string $resourceType, string $resourceId): array
    {
        switch ($resourceType) {
            case 'ap_invoice':
                return $this->loadApInvoiceData($resourceId);
            case 'ar_invoice':
                return $this->loadArInvoiceData($resourceId);
            case 'sales_order':
                return $this->loadSalesOrderData($resourceId);
            default:
                return ['reference' => $resourceId, 'transaction_date' => date('Y-m-d')];
        }
    }

    private function loadApInvoiceData(string $id): array
    {
        // Load AP invoice row (ap_invoices.id = resource_id truyền từ view)
        $stmt = $this->pdo->prepare("SELECT ai.*, s.name AS supplier_name, s.tax_code AS supplier_tax_code
            FROM ap_invoices ai LEFT JOIN suppliers s ON ai.supplier_id = s.id WHERE ai.id = ?");
        $stmt->execute([$id]);
        $inv = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$inv) {
            // Fallback: thử từ transactions
            $txnStmt = $this->pdo->prepare("SELECT * FROM transactions WHERE id = ?");
            $txnStmt->execute([$id]);
            $txn = $txnStmt->fetch(PDO::FETCH_ASSOC);
            if (!$txn) return ['reference' => $id];
            $inv = ['invoice_number' => $txn['reference'] ?? '', 'invoice_date' => $txn['transaction_date'] ?? '',
                'net_amount' => 0, 'vat_amount' => 0, 'vat_rate' => 0, 'gross_amount' => 0,
                'description' => $txn['description'] ?? '', 'supplier_name' => '', 'supplier_tax_code' => ''];
        }

        $net = (float)($inv['net_amount'] ?? 0);
        $vat = (float)($inv['vat_amount'] ?? 0);
        $total = (float)($inv['gross_amount'] ?? $net + $vat);
        $vatRate = (float)($inv['vat_rate'] ?? 0);

        return [
            'reference' => $inv['invoice_number'] ?? '',
            'transaction_date' => $inv['invoice_date'] ?? '',
            'supplier_name' => $inv['supplier_name'] ?? '',
            'supplier_tax_code' => $inv['supplier_tax_code'] ?? '',
            'lines' => [[
                'description' => $inv['description'] ?? 'Hàng hóa/dịch vụ',
                'quantity' => 1,
                'unit_price' => number_format($net, 0, ',', '.'),
                'amount' => number_format($net, 0, ',', '.'),
            ]],
            'total_amount' => number_format($net, 0, ',', '.'),
            'vat_amount' => $vat > 0 ? number_format($vat, 0, ',', '.') : '',
            'vat_rate' => $vatRate > 0 ? (string)$vatRate : '',
            'grand_total' => number_format($total, 0, ',', '.'),
            'print_date' => date('Y-m-d H:i:s'),
        ];
    }

    private function loadArInvoiceData(string $id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmt->execute([$id]);
        $txn = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$txn) return ['reference' => $id];

        $custName = '';
        if (!empty($txn['payer_id'])) {
            $c = $this->pdo->prepare("SELECT name FROM customers WHERE id = ?");
            $c->execute([$txn['payer_id']]);
            $custName = $c->fetchColumn() ?: '';
        }

        $lines = $this->pdo->prepare("SELECT * FROM ledger_entries WHERE transaction_id = ? ORDER BY line_order");
        $lines->execute([$id]);
        $lineRows = $lines->fetchAll(PDO::FETCH_ASSOC);

        $total = 0;
        foreach ($lineRows as $l) {
            if ($l['is_debit']) $total += (float)$l['amount'];
        }

        return [
            'reference' => $txn['reference'] ?? '',
            'transaction_date' => $txn['transaction_date'] ?? '',
            'customer_name' => $custName,
            'lines' => array_map(function($l) {
                return [
                    'description' => $l['note'] ?? '',
                    'quantity' => 1,
                    'unit_price' => number_format((float)$l['amount'], 0, ',', '.'),
                    'amount' => number_format((float)$l['amount'], 0, ',', '.'),
                ];
            }, $lineRows),
            'total_amount' => number_format($total, 0, ',', '.'),
            'vat_amount' => '',
            'grand_total' => number_format($total, 0, ',', '.'),
        ];
    }

    private function loadSalesOrderData(string $id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sales_orders WHERE id = ?");
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) return ['order_no' => $id];

        $items = $this->pdo->prepare("SELECT sol.*, i.name AS item_name FROM sales_order_lines sol LEFT JOIN items i ON sol.item_id = i.id WHERE sol.order_id = ?");
        $items->execute([$id]);
        $itemRows = $items->fetchAll(PDO::FETCH_ASSOC);

        $custName = '';
        if (!empty($order['customer_id'])) {
            $c = $this->pdo->prepare("SELECT name FROM customers WHERE id = ?");
            $c->execute([$order['customer_id']]);
            $custName = $c->fetchColumn() ?: '';
        }

        $total = 0;
        $itemsList = [];
        $lineNo = 1;
        foreach ($itemRows as $r) {
            $amount = (float)($r['amount'] ?? 0);
            $total += $amount;
            $itemsList[] = [
                'line_no' => $lineNo++,
                'item_name' => $r['item_name'] ?? '',
                'quantity' => $r['quantity'] ?? 0,
                'unit_price' => number_format((float)($r['unit_price'] ?? 0), 0, ',', '.'),
                'amount' => number_format($amount, 0, ',', '.'),
            ];
        }

        return [
            'order_no' => $order['order_no'] ?? '',
            'order_date' => $order['order_date'] ?? '',
            'customer_name' => $custName,
            'notes' => $order['notes'] ?? '',
            'items' => $itemsList,
            'total' => number_format($total, 0, ',', '.'),
        ];
    }
}
