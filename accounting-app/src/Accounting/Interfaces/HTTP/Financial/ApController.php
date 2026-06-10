<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\ApService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Công nợ Phải trả (Accounts Payable — TK 331)
 *
 * Mục đích nghiệp vụ:
 *   - Ghi nhận hóa đơn mua hàng (invoice) từ nhà cung cấp
 *   - Ghi nhận thanh toán cho nhà cung cấp (payment)
 *   - Quản lý tạm ứng cho nhà cung cấp (prepayment)
 *   - Xử lý hàng trả lại nhà cung cấp (return goods)
 *   - Theo dõi công nợ chi tiết theo từng nhà cung cấp
 *
 * API endpoints:
 *   GET  /api/ap/invoices — Danh sách hóa đơn mua
 *   GET  /api/ap/invoices/{id} — Chi tiết
 *   POST /api/ap/invoices — Tạo hóa đơn mua
 *   POST /api/ap/{id}/pay — Thanh toán
 *   POST /api/ap/prepay — Tạm ứng NCC
 *   POST /api/ap/{id}/return — Trả lại hàng
 *   GET  /api/ap/suppliers — DS NCC
 *   GET  /api/ap/{id}/payments — Lịch sử thanh toán
 *
 * Rủi ro:
 *   - R001: Post vào kỳ đã đóng
 *   - R005: Sai TK đối ứng
 *   - R007: Thanh toán không cập nhật công nợ
 *
 * Tích hợp:
 *   - ApService gọi JournalService
 *   - ReceiptController nhập kho
 */
class ApController
{
    private ApService $ap;

    public function __construct(ApService $ap) { $this->ap = $ap; }

    /**
     * Danh sách hóa đơn mua
     *
     * @return void
     */
    public function invoices(): void { Auth::requirePermission('ap', 'read'); JsonResponse::ok($this->ap->getInvoices($_GET['status'] ?? null, $_GET['supplier_id'] ?? null)); }

    /**
     * Chi tiết hóa đơn
     *
     * @param int $id ID hóa đơn
     * @return void
     */
    public function get(int $id): void { Auth::requirePermission('ap', 'read'); JsonResponse::ok($this->ap->getInvoice($id)); }

    /**
     * Lịch sử thanh toán của hóa đơn
     *
     * @param int $id ID hóa đơn
     * @return void
     */
    public function payments(int $id): void { Auth::requirePermission('ap', 'read'); JsonResponse::ok($this->ap->getPayments($id)); }

    /**
     * Danh sách nhà cung cấp
     *
     * @return void
     */
    public function suppliers(): void { Auth::requirePermission('ap', 'read'); JsonResponse::ok($this->ap->getSuppliers()); }

    /**
     * Ghi nhận hóa đơn mua hàng — Nợ 152,156,1331 / Có 331
     *
     * @return void
     */
    public function create(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ap', 'create');
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || !isset($d['supplier_id'], $d['invoice_number'], $d['net_amount']))
            { JsonResponse::error('Vui lòng nhập mã nhà cung cấp, số hóa đơn và tiền hàng'); return; }
        try {
            $r = $this->ap->recordInvoice($d['supplier_id'], $d['invoice_number'], $d['invoice_date'] ?? date('Y-m-d'),
                $d['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
                (float)$d['net_amount'], (float)($d['vat_amount'] ?? 0), (float)($d['vat_rate'] ?? 0),
                $d['description'] ?? '', $d['inventory_account'] ?? '152', $d['created_by'] ?? 'system');
            JsonResponse::ok($r, 201);
        } catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * Thanh toán hóa đơn NCC — Nợ 331 / Có 111,112
     *
     * @param int $id ID hóa đơn
     * @return void
     */
    public function pay(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ap', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ap->recordPayment($id, (float)($d['amount'] ?? 0), $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * Tạm ứng cho NCC — Nợ 331 / Có 111,112
     *
     * @return void
     */
    public function prepay(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ap', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || !isset($d['supplier_id'], $d['amount']))
            { JsonResponse::error('Vui lòng nhập mã nhà cung cấp và số tiền tạm ứng'); return; }
        try { JsonResponse::ok($this->ap->recordPrepayment($d['supplier_id'], (float)$d['amount'], $d['description'] ?? '', $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * Trả lại hàng cho NCC — Nợ 331 / Có 152,156,1331
     *
     * @param int $id ID hóa đơn
     * @return void
     */
    public function returnGoods(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ap', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ap->recordReturn($id, (float)($d['amount'] ?? 0), $d['inventory_account'] ?? '152', $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * Chiết khấu thanh toán NCC — Nợ 331 / Có 515
     *
     * @param int $id ID hóa đơn
     * @return void
     */
    public function discount(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ap', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ap->recordDiscount($id, (float)($d['amount'] ?? 0), $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * Xóa sổ công nợ phải trả — Nợ 331 / Có 711
     *
     * @param int $id ID hóa đơn
     * @return void
     */
    public function writeOff(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ap', 'delete');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ap->writeOff($id, $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * Báo cáo aging công nợ phải trả
     *
     * @return void
     */
    public function aging(): void { Auth::requirePermission('ap', 'read'); JsonResponse::ok($this->ap->getAgingReport()); }

    /**
     * Sao kê công nợ NCC
     *
     * @param string $supplierId ID NCC
     * @return void
     */
    public function statement(string $supplierId): void { Auth::requirePermission('ap', 'read'); JsonResponse::ok($this->ap->getSupplierStatement($supplierId)); }

    /**
     * View hóa đơn mua
     *
     * @return void
     */
    public function viewInvoices(): void { require __DIR__ . '/../../../../../public/views/ap_invoices.php'; }

    /**
     * View aging
     *
     * @return void
     */
    public function viewAging(): void { require __DIR__ . '/../../../../../public/views/ap_aging.php'; }

    /**
     * View sao kê
     *
     * @return void
     */
    public function viewStatement(): void { require __DIR__ . '/../../../../../public/views/ap_statement.php'; }
}
