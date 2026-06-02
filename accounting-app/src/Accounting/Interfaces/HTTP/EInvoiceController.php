<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Domain\Service\InvoiceService;
use Accounting\Domain\Service\VatDeclarationEngine;

//
// CONTROLLER HÓA ĐƠN ĐIỆN TỬ
//
// API endpoints cho vòng đời hóa đơn điện tử:
//   GET  /api/einvoice            — danh sách hóa đơn
//   GET  /api/einvoice/:id         — chi tiết hóa đơn
//   POST /api/einvoice/create      — tạo từ giao dịch
//   POST /api/einvoice/adjust      — điều chỉnh
//   POST /api/einvoice/replace     — thay thế
//   POST /api/einvoice/cancel      — hủy
//   POST /api/einvoice/retry       — retry phát hành
//   GET  /api/einvoice/download/:id — tải XML
//   GET  /api/vat/declarations/:id/export — xuất XML tờ khai 01/GTGT
//
class EInvoiceController
{
    private InvoiceService $invoiceService;
    private VatDeclarationEngine $vatEngine;

    public function __construct(InvoiceService $invoiceService, VatDeclarationEngine $vatEngine)
    {
        $this->invoiceService = $invoiceService;
        $this->vatEngine = $vatEngine;
    }

    // Danh sách hóa đơn
    public function list(): void
    {
        Auth::requirePermission('einvoice', 'read');
        $status = $_GET['status'] ?? '';
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        JsonResponse::ok($this->invoiceService->listInvoices($status, $from, $to));
    }

    // Chi tiết hóa đơn
    public function get(string $id): void
    {
        Auth::requirePermission('einvoice', 'read');
        $inv = $this->invoiceService->getInvoice($id);
        if (!$inv) { JsonResponse::error('Không tìm thấy hóa đơn.', 404); return; }
        JsonResponse::ok($inv);
    }

    // Tạo hóa đơn từ giao dịch
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

    // Tạo hóa đơn điều chỉnh
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

    // Thay thế hóa đơn
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

    // Hủy hóa đơn
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

    // Retry publish
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

    // Tải XML hóa đơn đã ký
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

    // Xuất XML tờ khai 01/GTGT theo chuẩn TĐT
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

    // Tính toán 43 chỉ tiêu tờ khai 01/GTGT
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
