<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\ApService;
use Accounting\Infrastructure\JsonResponse;

class ApController
{
    private ApService $ap;

    public function __construct(ApService $ap) { $this->ap = $ap; }

    public function invoices(): void { JsonResponse::ok($this->ap->getInvoices($_GET['status'] ?? null, $_GET['supplier_id'] ?? null)); }
    public function get(int $id): void { JsonResponse::ok($this->ap->getInvoice($id)); }
    public function payments(int $id): void { JsonResponse::ok($this->ap->getPayments($id)); }
    public function suppliers(): void { JsonResponse::ok($this->ap->getSuppliers()); }

    public function create(): void
    {
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || !isset($d['supplier_id'], $d['invoice_number'], $d['net_amount']))
            { JsonResponse::error('supplier_id, invoice_number, net_amount required'); return; }
        try {
            $r = $this->ap->recordInvoice($d['supplier_id'], $d['invoice_number'], $d['invoice_date'] ?? date('Y-m-d'),
                $d['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
                (float)$d['net_amount'], (float)($d['vat_amount'] ?? 0), (float)($d['vat_rate'] ?? 0),
                $d['description'] ?? '', $d['inventory_account'] ?? '152', $d['created_by'] ?? 'system');
            JsonResponse::ok($r, 201);
        } catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function pay(int $id): void
    {
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ap->recordPayment($id, (float)($d['amount'] ?? 0), $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function prepay(): void
    {
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || !isset($d['supplier_id'], $d['amount']))
            { JsonResponse::error('supplier_id, amount required'); return; }
        try { JsonResponse::ok($this->ap->recordPrepayment($d['supplier_id'], (float)$d['amount'], $d['description'] ?? '', $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function returnGoods(int $id): void
    {
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ap->recordReturn($id, (float)($d['amount'] ?? 0), $d['inventory_account'] ?? '152', $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function discount(int $id): void
    {
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ap->recordDiscount($id, (float)($d['amount'] ?? 0), $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function writeOff(int $id): void
    {
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ap->writeOff($id, $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function aging(): void { JsonResponse::ok($this->ap->getAgingReport()); }
    public function statement(string $supplierId): void { JsonResponse::ok($this->ap->getSupplierStatement($supplierId)); }

    public function viewInvoices(): void { require __DIR__ . '/../../../../../public/views/ap_invoices.php'; }
    public function viewAging(): void { require __DIR__ . '/../../../../../public/views/ap_aging.php'; }
    public function viewStatement(): void { require __DIR__ . '/../../../../../public/views/ap_statement.php'; }
}
