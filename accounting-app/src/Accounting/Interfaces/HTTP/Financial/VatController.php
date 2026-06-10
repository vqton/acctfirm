<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\VatService;
use Accounting\Domain\Service\VatDeclarationEngine;
use Accounting\Domain\Service\VatRateService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Thuế GTGT (VAT Management)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý thuế GTGT đầu vào (133) và đầu ra (3331)
 *   - Hỗ trợ nhiều mức thuế suất (0%, 5%, 8%, 10%) theo dữ liệu VAT groups
 *   - Scan hóa đơn không đủ điều kiện khấu trừ
 *   - Tổng hợp chỉ tiêu tờ khai 01/GTGT
 *   - Theo dõi tình trạng khai thuế và nộp thuế
 *
 * API endpoints:
 *   GET  /api/vat/declarations         — Danh sách tờ khai
 *   GET  /api/vat/declarations/{id}    — Chi tiết tờ khai
 *   POST /api/vat/declarations         — Tạo tờ khai mới
 *   POST /api/vat/declarations/{id}/finalize   — Khoá tờ khai
 *   GET  /api/vat/non-deductible-scan  — Scan hóa đơn không khấu trừ
 *
 * Rủi ro:
 *   - Sai thuế suất -> sai chỉ tiêu tờ khai -> phạt thuế
 *   - Không phát hiện hóa đơn không khấu trừ -> khai sai
 *   - Bỏ sót VAT đầu vào -> mất quyền khấu trừ
 *
 * Tích hợp:
 *   - VatService đọc từ TransactionRepository
 *   - VatDeclarationEngine tổng hợp 43 chỉ tiêu 01/GTGT
 *   - VatRateService cung cấp danh sách thuế suất
 */
class VatController
{
    private VatService $vat;
    private VatDeclarationEngine $declaration;
    private VatRateService $vatRate;

    public function __construct(VatService $vat, VatDeclarationEngine $declaration, VatRateService $vatRate)
    {
        $this->vat = $vat;
        $this->declaration = $declaration;
        $this->vatRate = $vatRate;
    }

    /**
     * Danh sách tờ khai thuế GTGT
     *
     * @return void
     */
    public function declarations(): void
    {
        Auth::requirePermission('tax', 'read');
        $period = $_GET['period'] ?? date('Y-m');
        JsonResponse::ok($this->vat->getDeclarations($period));
    }

    /**
     * Chi tiết tờ khai thuế GTGT
     *
     * @param string $id ID tờ khai
     * @return void
     */
    public function getDeclaration(string $id): void
    {
        Auth::requirePermission('tax', 'read');
        $decl = $this->vat->getDeclaration($id);
        if (!$decl) { JsonResponse::error('Không tìm thấy tờ khai'); return; }
        JsonResponse::ok($decl);
    }

    /**
     * Tạo tờ khai thuế GTGT mới — tính toán 43 chỉ tiêu từ dữ liệu kỳ
     *
     * @return void
     */
    public function createDeclaration(): void
    {
        Auth::requirePermission('tax', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        $period = $data['period'] ?? date('Y-m');
        $createdBy = $_SESSION['user_id'] ?? 'system';
        try {
            $decl = $this->vat->createDeclaration($period, $createdBy);
            JsonResponse::ok($decl, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Khoá tờ khai thuế — sau khi finalize không sửa được
     *
     * @param string $id ID tờ khai
     * @return void
     */
    public function finalizeDeclaration(string $id): void
    {
        Auth::requirePermission('tax', 'post');
        Auth::checkCsrf();
        try {
            $this->vat->finalizeDeclaration($id);
            JsonResponse::ok(['message' => 'Đã khoá tờ khai']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Quét hóa đơn đầu vào không đủ điều kiện khấu trừ (non-deductible)
     *
     * @return void
     */
    public function nonDeductibleScan(): void
    {
        Auth::requirePermission('tax', 'read');
        $data = json_decode(file_get_contents('php://input'), true);
        $period = $data['period'] ?? $_GET['period'] ?? date('Y-m');
        try {
            $result = $this->vat->scanNonDeductible($period);
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Danh sách thuế suất (VAT groups)
     *
     * @return void
     */
    public function rates(): void
    {
        JsonResponse::ok($this->vatRate->getAllVatGroups());
    }

    /**
     * Tổng hợp chỉ tiêu tờ khai 01/GTGT (43 chỉ tiêu)
     *
     * @return void
     */
    public function calculateIndicators(): void
    {
        Auth::requirePermission('tax', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        $period = $data['period'] ?? date('Y-m');
        try {
            $indicators = $this->declaration->calculateIndicators($period);
            JsonResponse::ok($indicators);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Đối chiếu VAT đầu vào/đầu ra giữa các kỳ
     *
     * @return void
     */
    public function reconcile(): void
    {
        Auth::requirePermission('tax', 'read');
        $period = $_GET['period'] ?? date('Y-m');
        $type = $_GET['type'] ?? 'input';
        try {
            $result = $this->vat->reconcileVat($period, $type);
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Báo cáo tổng hợp VAT theo kỳ
     *
     * @return void
     */
    public function report(): void
    {
        Auth::requirePermission('tax', 'read');
        $period = $_GET['period'] ?? date('Y-m');
        JsonResponse::ok($this->vat->getVatSummary($period));
    }

    /**
     * View tờ khai thuế
     *
     * @return void
     */
    public function viewDeclarations(): void
    {
        require __DIR__ . '/../../../../../public/views/vat_declarations.php';
    }
}
