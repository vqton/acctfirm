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
 *   GET    /api/ar/invoices       — Danh sách hóa đơn bán
 *   GET    /api/ar/invoices/{id}  — Chi tiết hóa đơn
 *   POST   /api/ar/invoices       — Tạo hóa đơn bán mới
 *   POST   /api/ar/{id}/pay       — Ghi nhận thanh toán
 *   POST   /api/ar/prepay         — Tạm ứng của KH
 *   POST   /api/ar/{id}/return    — Hàng bán trả lại
 *   GET    /api/ar/customers      — Danh sách khách hàng
 *   GET    /api/ar/{id}/payments  — Lịch sử thanh toán
 *
 * Rủi ro:
 *   - R001: Post thu tiền vào kỳ đã đóng → sai số dư 131
 *   - R005: Sai tài khoản đối ứng (511/3331/131) → sai BC02
 *   - Ghi nhận doanh thu (511) sai kỳ → sai BC02 chỉ tiêu 1
 *   - Thuế GTGT đầu ra (3331) phải khớp với tờ khai thuế
 *
 * Tích hợp:
 *   - ArService gọi JournalService ghi nhận bút toán Nợ 131 / Có 511, 3331
 *   - IssueController xuất kho đồng thời với ghi nhận hóa đơn
 *   - CustomerReturnController quản lý hàng bán trả lại riêng
 */
class ArController
{
    private ArService $ar;
    public function __construct(ArService $ar) { $this->ar = $ar; }

    public function invoices(): void { Auth::requirePermission('ar', 'read'); JsonResponse::ok($this->ar->getInvoices($_GET['status'] ?? null, $_GET['customer_id'] ?? null)); }
    public function get(int $id): void { Auth::requirePermission('ar', 'read'); JsonResponse::ok($this->ar->getInvoice($id)); }
    public function payments(int $id): void { Auth::requirePermission('ar', 'read'); JsonResponse::ok($this->ar->getPayments($id)); }
    public function customers(): void { Auth::requirePermission('ar', 'read'); JsonResponse::ok($this->ar->getCustomers()); }

    // NGHIỆP VỤ: Ghi nhận hóa đơn bán hàng cho khách hàng
    // Input: { customer_id, invoice_number, invoice_date?, due_date?, net_amount, vat_amount?, vat_rate?, description?, created_by? }
    // Output: { invoice_id, transaction_id, reference } — 201 Created
    // Service: ArService.recordInvoice() → JournalService.postEntry
    // Hạch toán: Nợ 131 (công nợ KH) / Có 511 (doanh thu) + Có 3331 (thuế GTGT đầu ra)
    // Rủi ro: R001 — Kiểm tra kỳ mở. Ghi nhận doanh thu (511) sai kỳ → sai BC02
    // Thuế: 3331 phải khớp với tờ khai thuế GTGT. Mẫu số: 01/GTGT
    // Ảnh hưởng BC01: Tăng 131, BC02: Tăng 511
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

    // NGHIỆP VỤ: Ghi nhận thanh toán từ khách hàng — giảm công nợ 131
    // Input: { amount?, created_by? }
    // Output: { payment_id, transaction_id, remaining_balance }
    // Service: ArService.recordPayment() → JournalService.postEntry
    // Hạch toán: Nợ 111,112 / Có 131 (thu hồi công nợ)
    // Rủi ro: R006 — Trùng số CT. Thanh toán vượt dư nợ → báo lỗi
    // Tích hợp: CashController ghi nhận phiếu thu đồng thời
    public function pay(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ar', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ar->recordPayment($id, (float)($d['amount'] ?? 0), $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    // NGHIỆP VỤ: Tạm ứng của khách hàng (KH trả trước) — ghi nhận 131 dư Có
    // Input: { customer_id, amount, description?, created_by? }
    // Output: { transaction_id, reference }
    // Service: ArService.recordPrepayment() → JournalService.postEntry
    // Hạch toán: Nợ 111,112 / Có 131 (KH trả trước — 131 dư Có)
    // Rủi ro: KH trả trước làm 131 dư Có — cần theo dõi riêng để bù trừ khi xuất hóa đơn
    // Ảnh hưởng BC01: 131 dư Có sẽ được phân loại là 331 (phải trả KH)
    public function prepay(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ar', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        if (!$d || !isset($d['customer_id'], $d['amount'])) { JsonResponse::error('Vui lòng nhập mã khách hàng và số tiền tạm ứng'); return; }
        try { JsonResponse::ok($this->ar->recordPrepayment($d['customer_id'], (float)$d['amount'], $d['description'] ?? '', $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    // NGHIỆP VỤ: Hàng bán bị trả lại — ghi giảm doanh thu, giảm công nợ
    // Input: { amount?, created_by? }
    // Output: { return_id, adjusted_balance }
    // Service: ArService.recordReturn() → JournalService.postEntry
    // Hạch toán: Nợ 511 (doanh thu) / Nợ 3331 (thuế) / Có 131 (công nợ KH)
    // Rủi ro: Giá trị trả lại phải <= giá trị hóa đơn gốc. Nếu đã xuất kho, cần nhập kho lại
    // Ảnh hưởng BC02: Giảm doanh thu (511). Tích hợp: CustomerReturnController nhập kho
    public function returnGoods(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ar', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ar->recordReturn($id, (float)($d['amount'] ?? 0), $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    // NGHIỆP VỤ: Chiết khấu thanh toán cho KH (KH trả sớm được hưởng chiết khấu)
    // Input: { amount?, created_by? }
    // Output: { transaction_id, adjusted_balance }
    // Service: ArService.recordSettlementDiscount() → JournalService.postEntry
    // Hạch toán: Nợ 521 (chiết khấu thương mại - giảm trừ doanh thu) / Có 131
    // Rủi ro: Chiết khấu > giá trị hóa đơn → không hợp lệ. Cần hợp đồng quy định rõ
    // Ảnh hưởng BC02: Giảm doanh thu thuần (511 - 521)
    public function discount(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ar', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ar->recordSettlementDiscount($id, (float)($d['amount'] ?? 0), $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    // NGHIỆP VỤ: Xóa sổ công nợ phải thu khó đòi — KH không có khả năng thanh toán
    // Input: { created_by? }
    // Output: { write_off_id, adjusted_balance }
    // Service: ArService.writeOff() → JournalService.postEntry
    // Hạch toán: Nợ 642 (chi phí QLDN) / Có 131 (xóa công nợ KH)
    // Rủi ro: R007 — Xóa sai → mất quyền đòi nợ. Cần phê duyệt (ApprovalController)
    // Ảnh hưởng BC02: Tăng chi phí QLDN (642). Thuế: Xóa nợ không được khấu trừ thuế TNDN
    // Audit trail: Cần lưu lý do xóa và chứng từ gốc đầy đủ
    public function writeOff(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ar', 'delete');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ar->writeOff($id, $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function aging(): void { Auth::requirePermission('ar', 'read'); JsonResponse::ok($this->ar->getAgingReport()); }
    public function statement(string $customerId): void { Auth::requirePermission('ar', 'read'); JsonResponse::ok($this->ar->getCustomerStatement($customerId)); }

    public function viewInvoices(): void { require __DIR__ . '/../../../../../public/views/ar_invoices.php'; }
    public function viewAging(): void { require __DIR__ . '/../../../../../public/views/ar_aging.php'; }
    public function viewStatement(): void { require __DIR__ . '/../../../../../public/views/ar_statement.php'; }
}
