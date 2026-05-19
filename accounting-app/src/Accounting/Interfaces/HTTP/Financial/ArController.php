<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\ArService;
use Accounting\Infrastructure\JsonResponse;

class ArController
{
    private ArService $ar;
    public function __construct(ArService $ar) { $this->ar = $ar; }

    public function invoices(): void { JsonResponse::ok($this->ar->getInvoices($_GET['status'] ?? null, $_GET['customer_id'] ?? null)); }
    public function get(int $id): void { JsonResponse::ok($this->ar->getInvoice($id)); }
    public function payments(int $id): void { JsonResponse::ok($this->ar->getPayments($id)); }
    public function customers(): void { JsonResponse::ok($this->ar->getCustomers()); }

    public function create(): void
    {
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || !isset($d['customer_id'], $d['invoice_number'], $d['net_amount']))
            { JsonResponse::error('customer_id, invoice_number, net_amount required'); return; }
        try {
            $r = $this->ar->recordInvoice($d['customer_id'], $d['invoice_number'], $d['invoice_date'] ?? date('Y-m-d'),
                $d['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
                (float)$d['net_amount'], (float)($d['vat_amount'] ?? 0), (float)($d['vat_rate'] ?? 0),
                $d['description'] ?? '', $d['created_by'] ?? 'system');
            JsonResponse::ok($r, 201);
        } catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function pay(int $id): void
    {
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ar->recordPayment($id, (float)($d['amount'] ?? 0), $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function prepay(): void
    {
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || !isset($d['customer_id'], $d['amount'])) { JsonResponse::error('customer_id, amount required'); return; }
        try { JsonResponse::ok($this->ar->recordPrepayment($d['customer_id'], (float)$d['amount'], $d['description'] ?? '', $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function returnGoods(int $id): void
    {
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ar->recordReturn($id, (float)($d['amount'] ?? 0), $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function discount(int $id): void
    {
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ar->recordSettlementDiscount($id, (float)($d['amount'] ?? 0), $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function writeOff(int $id): void
    {
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ar->writeOff($id, $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function aging(): void { JsonResponse::ok($this->ar->getAgingReport()); }
    public function statement(string $customerId): void { JsonResponse::ok($this->ar->getCustomerStatement($customerId)); }

    public function viewInvoices(): void { require __DIR__ . '/../../../../../public/views/ar_invoices.php'; }
    public function viewAging(): void { require __DIR__ . '/../../../../../public/views/ar_aging.php'; }
    public function viewStatement(): void { require __DIR__ . '/../../../../../public/views/ar_statement.php'; }
}
