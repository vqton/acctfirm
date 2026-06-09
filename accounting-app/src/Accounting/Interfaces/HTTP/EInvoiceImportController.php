<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\EInvoiceImportService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class EInvoiceImportController
{
    private EInvoiceImportService $importService;

    public function __construct(EInvoiceImportService $importService)
    {
        $this->importService = $importService;
    }

    // POST /api/einvoice/import — Import XML hóa đơn đầu vào
    public function import(): void
    {
        Auth::requirePermission('einvoice', 'create');
        Auth::checkCsrf();

        if (empty($_FILES['xml_file']['tmp_name'])) {
            JsonResponse::error('Vui lòng chọn file XML hóa đơn.', 400);
            return;
        }

        $xmlContent = file_get_contents($_FILES['xml_file']['tmp_name']);
        if (!$xmlContent) {
            JsonResponse::error('Không đọc được file XML.', 400);
            return;
        }

        $input = json_decode($_POST['options'] ?? '{}', true) ?: [];
        $options = [
            'auto_goods_receipt' => !empty($_POST['auto_goods_receipt']) || !empty($input['auto_goods_receipt']),
            'warehouse_id' => $_POST['warehouse_id'] ?? $input['warehouse_id'] ?? null,
            'receipt_type' => $_POST['receipt_type'] ?? $input['receipt_type'] ?? 'purchase',
        ];

        try {
            $result = $this->importService->importXml(
                $xmlContent,
                $_SESSION['user_id'] ?? 'system',
                $options
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            JsonResponse::error('Lỗi import: ' . $e->getMessage(), 500);
        }
    }

    // POST /api/einvoice/import/preview — Xem trước dữ liệu từ XML
    public function preview(): void
    {
        Auth::requirePermission('einvoice', 'read');

        if (empty($_FILES['xml_file']['tmp_name'])) {
            JsonResponse::error('Vui lòng chọn file XML hóa đơn.', 400);
            return;
        }

        $xmlContent = file_get_contents($_FILES['xml_file']['tmp_name']);
        if (!$xmlContent) {
            JsonResponse::error('Không đọc được file XML.', 400);
            return;
        }

        try {
            $parsed = $this->importService->parseXml($xmlContent);

            // Kiểm tra trùng
            $isDuplicate = $this->importService->checkDuplicate($parsed['fkey']);

            JsonResponse::ok([
                'fkey' => $parsed['fkey'],
                'invoice_number' => $parsed['invoice_number'],
                'invoice_date' => $parsed['invoice_date'],
                'template_code' => $parsed['template_code'],
                'template_symbol' => $parsed['template_symbol'],
                'currency' => $parsed['currency'],
                'supplier' => $parsed['supplier'],
                'buyer' => $parsed['buyer'],
                'totals' => $parsed['totals'],
                'items' => $parsed['items'],
                'is_duplicate' => $isDuplicate,
            ]);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            JsonResponse::error('Lỗi đọc XML: ' . $e->getMessage(), 500);
        }
    }

    // GET /api/einvoice/imports — Danh sách lịch sử import
    public function list(): void
    {
        Auth::requirePermission('einvoice', 'read');
        JsonResponse::ok($this->importService->listImports());
    }

    // GET /api/einvoice/imports/:id — Chi tiết import
    public function get(string $id): void
    {
        Auth::requirePermission('einvoice', 'read');
        $import = $this->importService->getImport($id);
        if (!$import) {
            JsonResponse::error('Không tìm thấy import.', 404);
            return;
        }
        JsonResponse::ok($import);
    }

    // GET /api/einvoice/import/parse — Parse XML preview (for AJAX in browser)
    public function parseXml(): void
    {
        Auth::requirePermission('einvoice', 'read');
        $input = json_decode(file_get_contents('php://input'), true);
        $xmlContent = $input['xml_content'] ?? '';

        if (!$xmlContent) {
            JsonResponse::error('Thiếu nội dung XML.', 400);
            return;
        }

        try {
            $parsed = $this->importService->parseXml($xmlContent);
            $isDuplicate = $this->importService->checkDuplicate($parsed['fkey']);
            $parsed['is_duplicate'] = $isDuplicate;
            JsonResponse::ok($parsed);
        } catch (\Throwable $e) {
            JsonResponse::error('Lỗi parse XML: ' . $e->getMessage(), 422);
        }
    }

    // GET /api/einvoice/import/vat-summary/:period — Tổng hợp VAT từ hóa đơn đã import
    public function vatSummary(string $period): void
    {
        Auth::requirePermission('einvoice', 'read');
        JsonResponse::ok($this->importService->getVatSummary($period));
    }

    // POST /api/einvoice/import/{id}/prepay — Ghi nhận tạm ứng cho hóa đơn đã import
    public function prepay(string $id): void
    {
        Auth::requirePermission('einvoice', 'create');
        Auth::checkCsrf();
        $input = json_decode(file_get_contents('php://input'), true);
        $amount = (float)($input['amount'] ?? 0);
        $txnId = trim($input['transaction_id'] ?? '');
        if ($amount <= 0) {
            JsonResponse::error('Số tiền tạm ứng phải lớn hơn 0.', 400);
            return;
        }
        if (!$txnId) {
            JsonResponse::error('Vui lòng nhập ID giao dịch tạm ứng.', 400);
            return;
        }
        try {
            $result = $this->importService->recordPrepay(
                $id, $amount, $txnId, $_SESSION['user_id'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            JsonResponse::error('Lỗi: ' . $e->getMessage(), 500);
        }
    }

    // POST /api/einvoice/import/{id}/allocate — Liên kết hóa đơn với lệnh sản xuất
    public function allocate(string $id): void
    {
        Auth::requirePermission('einvoice', 'create');
        Auth::checkCsrf();
        $input = json_decode(file_get_contents('php://input'), true);
        $poId = trim($input['production_order_id'] ?? '');
        $category = trim($input['cost_category'] ?? 'raw_material');
        if (!$poId) {
            JsonResponse::error('Vui lòng nhập mã lệnh sản xuất.', 400);
            return;
        }
        if (!in_array($category, ['raw_material', 'overhead', 'other'], true)) {
            JsonResponse::error('Loại chi phí không hợp lệ.', 400);
            return;
        }
        try {
            $result = $this->importService->allocateToProduction(
                $id, $poId, $category, $_SESSION['user_id'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            JsonResponse::error('Lỗi: ' . $e->getMessage(), 500);
        }
    }

    // POST /api/einvoice/import/{id}/pay — Ghi nhận thanh toán hóa đơn đã import
    public function pay(string $id): void
    {
        Auth::requirePermission('einvoice', 'create');
        Auth::checkCsrf();
        $input = json_decode(file_get_contents('php://input'), true);
        $amount = (float)($input['amount'] ?? 0);
        if ($amount <= 0) {
            JsonResponse::error('Số tiền thanh toán phải lớn hơn 0.', 400);
            return;
        }
        try {
            $result = $this->importService->recordPayment(
                $id, $amount, $_SESSION['user_id'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            JsonResponse::error('Lỗi: ' . $e->getMessage(), 500);
        }
    }
}
