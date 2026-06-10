<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Domain\Service\InvoiceService;
use Accounting\Domain\Service\VatDeclarationEngine;

/**
 * MODULE: Hóa đơn Điện tử (E-Invoice)
 *
 * Mục đích nghiệp vụ:
 *   - Vòng đời hóa đơn điện tử: tạo, điều chỉnh, thay thế, hủy
 *   - Phát hành hóa đơn từ giao dịch kế toán
 *   - Tải XML hóa đơn đã ký
 *   - Tính toán chỉ tiêu tờ khai 01/GTGT
 *
 * API endpoints:
 *   GET  /api/einvoice — Danh sách hóa đơn
 *   GET  /api/einvoice/{id} — Chi tiết
 *   POST /api/einvoice/create — Tạo từ giao dịch
 *   POST /api/einvoice/adjust — Điều chỉnh
 *   POST /api/einvoice/replace — Thay thế
 *   POST /api/einvoice/cancel — Hủy
 *   POST /api/einvoice/retry — Retry phát hành
 *   GET  /api/einvoice/download/{id} — Tải XML
 *   GET  /api/vat/declarations/{id}/export — Xuất XML tờ khai 01/GTGT
 *   POST /api/einvoice/vat-calculate — Tính 43 chỉ tiêu 01/GTGT
 *
 * Rủi ro:
 *   - Hủy hóa đơn đã phát hành -> sai số liệu kê khai thuế
 *   - Điều chỉnh không đúng thời điểm -> chậm nộp tờ khai
 *
 * Tích hợp:
 *   - InvoiceService xử lý nghiệp vụ hóa đơn
 *   - VatDeclarationEngine tính chỉ tiêu tờ khai
 */
class EInvoiceController
{
    private InvoiceService $invoiceService;
    private VatDeclarationEngine $vatEngine;

    public function __construct(InvoiceService $invoiceService, VatDeclarationEngine $vatEngine)
    {
        $this->invoiceService = $invoiceService;
        $this->vatEngine = $vatEngine;
    }

    /**
     * Danh sách hóa đơn
     *
     * @return void
     */
    public function list(): void
    {
        Auth::requirePermission('einvoice', 'read');
        $status = $_GET['status'] ?? '';
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        JsonResponse::ok($this->invoiceService->listInvoices($status, $from, $to));
    }

    /**
     * Chi tiết hóa đơn
     *
     * @param string $id ID hóa đơn
     * @return void
     */
    public function get(string $id): void
    {
        Auth::requirePermission('einvoice', 'read');
        $inv = $this->invoiceService->getInvoice($id);
        if (!$inv) { JsonResponse::error('Không tìm thấy hóa đơn.', 404); return; }
        JsonResponse::ok($inv);
    }

    /**
     * Tạo hóa đơn từ giao dịch kế toán
     *
     * @return void
     */
    public function create(): void
    {
        Auth::requirePermission('einvoice', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        $transactionId = $data['transaction_id'] ?? '';
        if (empty($transactionId)) { JsonResponse::error('Thiếu transaction_id.', 400); return; }
        try {
            $result = $this->invoiceService->createFromTransaction($transactionId);
            JsonResponse::ok($result, 201);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Tạo hóa đơn điều chỉnh
     *
     * @return void
     */
    public function adjust(): void
    {
        Auth::requirePermission('einvoice', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? '';
        if (empty($id)) { JsonResponse::error('Thiếu id hóa đơn.', 400); return; }
        try {
            JsonResponse::ok($this->invoiceService->adjustInvoice($id, $data));
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Thay thế hóa đơn
     *
     * @return void
     */
    public function replace(): void
    {
        Auth::requirePermission('einvoice', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? '';
        if (empty($id)) { JsonResponse::error('Thiếu id hóa đơn.', 400); return; }
        try {
            JsonResponse::ok($this->invoiceService->replaceInvoice($id, $data));
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Hủy hóa đơn
     *
     * @return void
     */
    public function cancel(): void
    {
        Auth::requirePermission('einvoice', 'delete');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? '';
        $reason = $data['reason'] ?? 'Hủy theo yêu cầu';
        if (empty($id)) { JsonResponse::error('Thiếu id hóa đơn.', 400); return; }
        try {
            JsonResponse::ok($this->invoiceService->cancelInvoice($id, $reason));
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Retry phát hành hóa đơn
     *
     * @return void
     */
    public function retry(): void
    {
        Auth::requirePermission('einvoice', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? '';
        if (empty($id)) { JsonResponse::error('Thiếu id hóa đơn.', 400); return; }
        try {
            JsonResponse::ok($this->invoiceService->retryPublish($id));
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Tải XML hóa đơn đã ký
     *
     * @param string $id ID hóa đơn
     * @return void
     */
    public function downloadXml(string $id): void
    {
        Auth::requirePermission('einvoice', 'read');
        $inv = $this->invoiceService->getInvoice($id);
        if (!$inv || empty($inv['xml_signed'])) {
            JsonResponse::error('Không tìm thấy XML hóa đơn.', 404);
            return;
        }
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="invoice_' . $inv['invoice_number'] . '.xml"');
        echo $inv['xml_signed'];
    }

    /**
     * Xuất XML tờ khai 01/GTGT theo chuẩn TĐT
     *
     * @param string $declarationId ID tờ khai
     * @return void
     */
    public function exportVatXml(string $declarationId): void
    {
        Auth::requirePermission('tax', 'read');
        try {
            $xml = $this->vatEngine->exportToXml($declarationId);
            header('Content-Type: application/xml; charset=utf-8');
            header('Content-Disposition: attachment; filename="01GTGT_' . $declarationId . '.xml"');
            echo $xml;
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * Tính toán 43 chỉ tiêu tờ khai 01/GTGT
     *
     * @return void
     */
    public function calculateVatIndicators(): void
    {
        Auth::requirePermission('tax', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        $period = $data['period'] ?? date('Y-m');
        try {
            $indicators = $this->vatEngine->calculateIndicators($period);
            JsonResponse::ok($indicators);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }
}
