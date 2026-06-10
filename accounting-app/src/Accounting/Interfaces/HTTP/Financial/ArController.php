<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\ArService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Công nợ Phải thu (Accounts Receivable — TK 131)
 *
 * Mục đích nghiệp vụ:
 *   - Ghi nhận hóa đơn bán hàng (invoice) cho khách hàng
 *   - Ghi nhận thanh toán từ khách hàng (payment)
 *   - Quản lý tạm ứng của khách hàng (prepayment / deposit)
 *   - Xử lý hàng bán bị trả lại (customer return)
 *   - Theo dõi công nợ chi tiết theo từng khách hàng
 *
 * API endpoints:
 *   GET  /api/ar/invoices — Danh sách hóa đơn bán
 *   GET  /api/ar/invoices/{id} — Chi tiết
 *   POST /api/ar/invoices — Tạo hóa đơn bán
 *   POST /api/ar/{id}/pay — Ghi nhận thanh toán
 *   POST /api/ar/prepay — Tạm ứng KH
 *   POST /api/ar/{id}/return — Hàng bán trả lại
 *   GET  /api/ar/customers — DS KH
 *   GET  /api/ar/{id}/payments — Lịch sử thanh toán
 *
 * Rủi ro:
 *   - R001: Post vào kỳ đã đóng
 *   - R005: Sai TK đối ứng (511/3331/131)
 *   - Ghi nhận doanh thu sai kỳ
 *
 * Tích hợp:
 *   - ArService gọi JournalService
 *   - IssueController xuất kho
 */
class ArController
{
    private ArService $ar;
    public function __construct(ArService $ar) { $this->ar = $ar; }

    /**
     * Danh sách hóa đơn bán
     *
     * @return void
     */
    public function invoices(): void { Auth::requirePermission('ar', 'read'); JsonResponse::ok($this->ar->getInvoices($_GET['status'] ?? null, $_GET['customer_id'] ?? null)); }

    /**
     * Chi tiết hóa đơn
     *
     * @param int $id ID hóa đơn
     * @return void
     */
    public function get(int $id): void { Auth::requirePermission('ar', 'read'); JsonResponse::ok($this->ar->getInvoice($id)); }

    /**
     * Lịch sử thanh toán
     *
     * @param int $id ID hóa đơn
     * @return void
     */
    public function payments(int $id): void { Auth::requirePermission('ar', 'read'); JsonResponse::ok($this->ar->getPayments($id)); }

    /**
     * Danh sách khách hàng
     *
     * @return void
     */
    public function customers(): void { Auth::requirePermission('ar', 'read'); JsonResponse::ok($this->ar->getCustomers()); }

    /**
     * Ghi nhận hóa đơn bán hàng — Nợ 131 / Có 511,3331
     *
     * @return void
     */
    public function create(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ar', 'create');
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || !isset($d['customer_id'], $d['invoice_number'], $d['net_amount']))
            { JsonResponse::error('Vui lòng nhập mã khách hàng, số hóa đơn và tiền hàng'); return; }
        try {
            $r = $this->ar->recordInvoice($d['customer_id'], $d['invoice_number'], $d['invoice_date'] ?? date('Y-m-d'),
                $d['due_date'] ?? date('Y-m-d', strtotime('+30 days')),
                (float)$d['net_amount'], (float)($d['vat_amount'] ?? 0), (float)($d['vat_rate'] ?? 0),
                $d['description'] ?? '', $d['created_by'] ?? 'system');
            JsonResponse::ok($r, 201);
        } catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * Ghi nhận thanh toán từ KH — Nợ 111,112 / Có 131
     *
     * @param int $id ID hóa đơn
     * @return void
     */
    public function pay(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ar', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ar->recordPayment($id, (float)($d['amount'] ?? 0), $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * Tạm ứng của KH — Nợ 111,112 / Có 131
     *
     * @return void
     */
    public function prepay(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ar', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || !isset($d['customer_id'], $d['amount'])) { JsonResponse::error('Vui lòng nhập mã khách hàng và số tiền tạm ứng'); return; }
        try { JsonResponse::ok($this->ar->recordPrepayment($d['customer_id'], (float)$d['amount'], $d['description'] ?? '', $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * Hàng bán trả lại — Nợ 511,3331 / Có 131
     *
     * @param int $id ID hóa đơn
     * @return void
     */
    public function returnGoods(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ar', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ar->recordReturn($id, (float)($d['amount'] ?? 0), $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * Chiết khấu thanh toán cho KH — Nợ 521 / Có 131
     *
     * @param int $id ID hóa đơn
     * @return void
     */
    public function discount(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ar', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ar->recordSettlementDiscount($id, (float)($d['amount'] ?? 0), $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * Xóa sổ công nợ phải thu khó đòi — Nợ 642 / Có 131
     *
     * @param int $id ID hóa đơn
     * @return void
     */
    public function writeOff(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ar', 'delete');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ar->writeOff($id, $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * Báo cáo aging công nợ phải thu
     *
     * @return void
     */
    public function aging(): void { Auth::requirePermission('ar', 'read'); JsonResponse::ok($this->ar->getAgingReport()); }

    /**
     * Sao kê công nợ KH
     *
     * @param string $customerId ID KH
     * @return void
     */
    public function statement(string $customerId): void { Auth::requirePermission('ar', 'read'); JsonResponse::ok($this->ar->getCustomerStatement($customerId)); }

    /**
     * View hóa đơn bán
     *
     * @return void
     */
    public function viewInvoices(): void { require __DIR__ . '/../../../../../public/views/ar_invoices.php'; }

    /**
     * View aging
     *
     * @return void
     */
    public function viewAging(): void { require __DIR__ . '/../../../../../public/views/ar_aging.php'; }

    /**
     * View sao kê
     *
     * @return void
     */
    public function viewStatement(): void { require __DIR__ . '/../../../../../public/views/ar_statement.php'; }
}
