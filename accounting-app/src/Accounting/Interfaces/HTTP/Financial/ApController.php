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
 *   GET    /api/ap/invoices       — Danh sách hóa đơn mua
 *   GET    /api/ap/invoices/{id}  — Chi tiết hóa đơn
 *   POST   /api/ap/invoices       — Tạo hóa đơn mua mới
 *   POST   /api/ap/{id}/pay       — Thanh toán hóa đơn
 *   POST   /api/ap/prepay         — Tạm ứng cho NCC
 *   POST   /api/ap/{id}/return    — Trả lại hàng
 *   GET    /api/ap/suppliers      — Danh sách nhà cung cấp
 *   GET    /api/ap/{id}/payments  — Lịch sử thanh toán
 *
 * Rủi ro:
 *   - R001: Post thanh toán vào kỳ đã đóng → sai số dư 331
 *   - R005: Sai tài khoản đối ứng (152/156/331) → sai BC01
 *   - R007: Ghi nhận thanh toán nhưng không cập nhật công nợ
 *   - Khấu trừ thuế VAT (1331) chỉ khi có hóa đơn đỏ hợp lệ
 *
 * Tích hợp:
 *   - ApService gọi JournalService ghi nhận bút toán Nợ 152/156/1331 / Có 331
 *   - ReceiptController nhập kho đồng thời với ghi nhận hóa đơn
 *   - Báo cáo công nợ (AP aging) ảnh hưởng BC01 khoản mục 331
 */
class ApController
{
    private ApService $ap;

    public function __construct(ApService $ap) { $this->ap = $ap; }

    public function invoices(): void { Auth::requirePermission('ap', 'read'); JsonResponse::ok($this->ap->getInvoices($_GET['status'] ?? null, $_GET['supplier_id'] ?? null)); }
    public function get(int $id): void { Auth::requirePermission('ap', 'read'); JsonResponse::ok($this->ap->getInvoice($id)); }
    public function payments(int $id): void { Auth::requirePermission('ap', 'read'); JsonResponse::ok($this->ap->getPayments($id)); }
    public function suppliers(): void { Auth::requirePermission('ap', 'read'); JsonResponse::ok($this->ap->getSuppliers()); }

    // NGHIỆP VỤ: Ghi nhận hóa đơn mua hàng từ nhà cung cấp
    // Input: { supplier_id, invoice_number, invoice_date?, due_date?, net_amount, vat_amount?, vat_rate?, description?, inventory_account?, created_by? }
    // Output: { invoice_id, transaction_id, reference } — 201 Created
    // Service: ApService.recordInvoice() → JournalService.postEntry
    // Transaction: Cần wrap (ghi nhận hóa đơn + cập nhật công nợ)
    // Hạch toán: Nợ 152,156 (inventory_account) / Nợ 1331 (VAT) / Có 331 (công nợ NCC)
    // Rủi ro: R001 — Kiểm tra kỳ mở. R005 — inventory_account phải đúng (152/156/153)
    // Thuế: Chỉ ghi nhận 1331 nếu có hóa đơn đỏ hợp lệ (TT 78/2021)
    // Ảnh hưởng: Tăng 331 (BC01) và tăng hàng tồn kho (BC01)
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

    // NGHIỆP VỤ: Thanh toán hóa đơn cho nhà cung cấp — giảm công nợ 331
    // Input: { amount?, created_by? } — amount mặc định = tổng dư nợ hóa đơn
    // Output: { payment_id, transaction_id, remaining_balance }
    // Service: ApService.recordPayment() → JournalService.postEntry
    // Hạch toán: Nợ 331 / Có 111,112 (tiền mặt/NH)
    // Rủi ro: R001 — Kỳ mở. Thanh toán vượt quá dư nợ → không hợp lệ
    // Tích hợp: CashController ghi nhận phiếu chi đồng thời
    public function pay(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ap', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ap->recordPayment($id, (float)($d['amount'] ?? 0), $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    // NGHIỆP VỤ: Tạm ứng cho nhà cung cấp trước khi nhận hàng
    // Input: { supplier_id, amount, description?, created_by? }
    // Output: { transaction_id, reference }
    // Service: ApService.recordPrepayment() → JournalService.postEntry
    // Hạch toán: Nợ 331 (tạm ứng) / Có 111,112
    // Rủi ro: Tạm ứng không có hóa đơn → theo dõi riêng. Cần đối chiếu khi nhận hàng
    // Ảnh hưởng: Tạm ứng NCC làm giảm tiền nhưng chưa ghi nhận hàng
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

    // NGHIỆP VỤ: Trả lại hàng cho nhà cung cấp — giảm công nợ 331, giảm tồn kho
    // Input: { amount?, inventory_account?, created_by? }
    // Output: { return_id, adjusted_balance }
    // Service: ApService.recordReturn() → JournalService.postEntry
    // Hạch toán: Nợ 331 / Có 152,156 (hàng trả lại) + Có 1331 (thuế GTGT nếu có)
    // Rủi ro: Phải điều chỉnh thuế GTGT đầu vào (1331) nếu đã kê khai
    // Tích hợp: ReturnToSupplierController xử lý nhập kho hàng trả lại
    public function returnGoods(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ap', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ap->recordReturn($id, (float)($d['amount'] ?? 0), $d['inventory_account'] ?? '152', $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    // NGHIỆP VỤ: Ghi nhận chiết khấu thanh toán từ NCC — giảm công nợ 331, ghi nhận 515
    // Input: { amount?, created_by? }
    // Output: { transaction_id, adjusted_balance }
    // Service: ApService.recordDiscount() → JournalService.postEntry
    // Hạch toán: Nợ 331 / Có 515 (doanh thu HĐTC) — chiết khấu được hưởng
    // Rủi ro: Chiết khấu thanh toán khác với giảm giá hàng bán (chiết khấu TM là 521)
    // Ảnh hưởng BC02: Tăng chỉ tiêu doanh thu HĐTC (515)
    public function discount(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ap', 'update');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ap->recordDiscount($id, (float)($d['amount'] ?? 0), $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    // NGHIỆP VỤ: Xóa sổ công nợ phải trả — NCC không còn đòi được / hết thời hiệu
    // Input: { created_by? }
    // Output: { transaction_id, status }
    // Service: ApService.writeOff() → JournalService.postEntry
    // Hạch toán: Nợ 331 / Có 711 (thu nhập khác)
    // Rủi ro: R007 — Xóa sai → mất công nợ. Cần phê duyệt (ApprovalController) cho giá trị lớn
    // Ảnh hưởng BC02: Tăng thu nhập khác (711) → tăng lợi nhuận trước thuế
    public function writeOff(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('ap', 'delete');
        $d = json_decode(file_get_contents('php://input'), true);
        try { JsonResponse::ok($this->ap->writeOff($id, $d['created_by'] ?? 'system')); }
        catch (\Throwable $e) { JsonResponse::error($e->getMessage()); }
    }

    public function aging(): void { Auth::requirePermission('ap', 'read'); JsonResponse::ok($this->ap->getAgingReport()); }
    public function statement(string $supplierId): void { Auth::requirePermission('ap', 'read'); JsonResponse::ok($this->ap->getSupplierStatement($supplierId)); }

    public function viewInvoices(): void { require __DIR__ . '/../../../../../public/views/ap_invoices.php'; }
    public function viewAging(): void { require __DIR__ . '/../../../../../public/views/ap_aging.php'; }
    public function viewStatement(): void { require __DIR__ . '/../../../../../public/views/ap_statement.php'; }
}
