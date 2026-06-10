<?php
declare(strict_types=1);
namespace Accounting\Interfaces\HTTP\Purchase;

use Accounting\Domain\Service\ProcurementService;
use Accounting\Domain\Service\ThreeWayMatchService;
use Accounting\Domain\Service\BudgetControlService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Mua hàng (Procurement Engine)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý đề nghị mua hàng (PR), đơn đặt hàng (PO), phiếu nhập kho (GR)
 *   - Đối chiếu 3 chiều (Three-Way Matching): PO - GR - Invoice
 *   - Kiểm soát ngân sách mua hàng theo phòng ban/kỳ
 *
 * Endpoints:
 *   PR:      GET/POST /api/purchase/requisitions, GET /api/purchase/requisitions/:id, POST approve/:id
 *   PO:      GET/POST /api/purchase/orders, GET /api/purchase/orders/:id
 *   GR:      GET/POST /api/purchase/receipts, GET /api/purchase/receipts/:id
 *   Match:   GET/POST /api/purchase/matches
 *   Budget:  GET/POST /api/purchase/budgets, GET /api/purchase/budgets/check
 *
 * Tích hợp:
 *   - ProcurementService, ThreeWayMatchService, BudgetControlService
 */
class ProcurementController
{
    private ProcurementService $procurement;
    private ThreeWayMatchService $matchService;
    private BudgetControlService $budgetService;

    /**
     * @param ProcurementService $procurement
     * @param ThreeWayMatchService $matchService
     * @param BudgetControlService $budgetService
     */
    public function __construct(
        ProcurementService $procurement,
        ThreeWayMatchService $matchService,
        BudgetControlService $budgetService
    ) {
        $this->procurement = $procurement;
        $this->matchService = $matchService;
        $this->budgetService = $budgetService;
    }

    // ── PR ──

    /**
     * Danh sách đề nghị mua hàng
     *
     * @return void
     */
    public function listPRs(): void
    {
        Auth::requirePermission('purchase', 'view');
        $status = $_GET['status'] ?? '';
        JsonResponse::ok($this->procurement->getPRList($status));
    }

    /**
     * Chi tiết đề nghị mua hàng
     *
     * @param string $id
     * @return void
     */
    public function getPR(string $id): void
    {
        Auth::requirePermission('purchase', 'view');
        $pr = $this->procurement->getPR($id);
        if (!$pr) { JsonResponse::error('Không tìm thấy đề nghị mua hàng.', 404); return; }
        JsonResponse::ok($pr);
    }

    /**
     * Tạo đề nghị mua hàng
     *
     * @return void
     */
    public function createPR(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('purchase', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['lines'])) {
            JsonResponse::error('Vui lòng nhập thông tin đề nghị mua hàng.', 400);
            return;
        }
        try {
            $userId = $_SESSION['user_id'] ?? 'system';
            $result = $this->procurement->createPR($data, $userId);
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Phê duyệt đề nghị mua hàng
     *
     * @param string $id
     * @return void
     */
    public function approvePR(string $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('purchase', 'approve');
        $userId = $_SESSION['user_id'] ?? 'system';
        try {
            $result = $this->procurement->approvePR($id, $userId);
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    // ── PO ──

    /**
     * Danh sách đơn đặt hàng
     *
     * @return void
     */
    public function listPOs(): void
    {
        Auth::requirePermission('purchase', 'view');
        $status = $_GET['status'] ?? '';
        JsonResponse::ok($this->procurement->getPOList($status));
    }

    /**
     * Chi tiết đơn đặt hàng
     *
     * @param string $id
     * @return void
     */
    public function getPO(string $id): void
    {
        Auth::requirePermission('purchase', 'view');
        $po = $this->procurement->getPO($id);
        if (!$po) { JsonResponse::error('Không tìm thấy đơn đặt hàng.', 404); return; }
        JsonResponse::ok($po);
    }

    /**
     * Tạo đơn đặt hàng từ đề nghị mua hàng
     *
     * @return void
     */
    public function createPO(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('purchase', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['pr_id'], $data['supplier_id'])) {
            JsonResponse::error('Vui lòng nhập mã đề nghị mua hàng và nhà cung cấp.', 400);
            return;
        }
        try {
            $userId = $_SESSION['user_id'] ?? 'system';
            $result = $this->procurement->createPO($data['pr_id'], $data['supplier_id'], $userId, $data);
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    // ── GR ──

    /**
     * Danh sách phiếu nhập kho
     *
     * @return void
     */
    public function listGRs(): void
    {
        Auth::requirePermission('inventory', 'view');
        $poId = $_GET['po_id'] ?? '';
        JsonResponse::ok($this->procurement->getGRList($poId));
    }

    /**
     * Chi tiết phiếu nhập kho
     *
     * @param string $id
     * @return void
     */
    public function getGR(string $id): void
    {
        Auth::requirePermission('inventory', 'view');
        $gr = $this->procurement->getGR($id);
        if (!$gr) { JsonResponse::error('Không tìm thấy phiếu nhập kho.', 404); return; }
        JsonResponse::ok($gr);
    }

    /**
     * Tạo phiếu nhập kho từ đơn đặt hàng
     *
     * @return void
     */
    public function createGR(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['po_id'], $data['items'])) {
            JsonResponse::error('Vui lòng nhập đơn đặt hàng và danh sách hàng nhập.', 400);
            return;
        }
        try {
            $userId = $_SESSION['user_id'] ?? 'system';
            $result = $this->procurement->createGR(
                $data['po_id'],
                $data['warehouse_id'] ?? '',
                $data['received_date'] ?? date('Y-m-d'),
                $data['items'],
                $userId
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    // ── Invoice Matching ──

    /**
     * Danh sách đối chiếu hóa đơn
     *
     * @return void
     */
    public function listMatches(): void
    {
        Auth::requirePermission('purchase', 'view');
        $poId = $_GET['po_id'] ?? '';
        JsonResponse::ok($this->matchService->getMatches($poId));
    }

    /**
     * Tạo đối chiếu 3 chiều (PO - GR - Invoice)
     *
     * @return void
     */
    public function createMatch(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('purchase', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['po_id'], $data['supplier_invoice_no'], $data['items'])) {
            JsonResponse::error('Vui lòng nhập thông tin hóa đơn và chi tiết đối chiếu.', 400);
            return;
        }
        try {
            $userId = $_SESSION['user_id'] ?? 'system';
            $result = $this->matchService->match(
                $data['po_id'], $data['supplier_invoice_no'],
                $data['invoice_date'] ?? date('Y-m-d'),
                (float)($data['invoice_amount'] ?? 0),
                (float)($data['vat_amount'] ?? 0),
                $data['items'], $userId
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    // ── Budget ──

    /**
     * Danh sách ngân sách mua hàng
     *
     * @return void
     */
    public function listBudgets(): void
    {
        Auth::requirePermission('purchase', 'view');
        $deptId = $_GET['department_id'] ?? '';
        JsonResponse::ok($this->budgetService->getBudgetReport($deptId));
    }

    /**
     * Thiết lập ngân sách mua hàng cho phòng ban/kỳ
     *
     * @return void
     */
    public function setBudget(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('purchase', 'create');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['department_id'], $data['period'], $data['amount'])) {
            JsonResponse::error('Vui lòng nhập phòng ban, kỳ và số tiền ngân sách.', 400);
            return;
        }
        try {
            $userId = $_SESSION['user_id'] ?? 'system';
            $result = $this->budgetService->setBudget($data['department_id'], $data['period'], (float)$data['amount'], $userId);
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Kiểm tra ngân sách còn khả dụng
     *
     * @return void
     */
    public function checkBudget(): void
    {
        Auth::requirePermission('purchase', 'view');
        $deptId = $_GET['department_id'] ?? '';
        $period = $_GET['period'] ?? date('Y-m');
        $amount = (float)($_GET['amount'] ?? 0);
        if (!$deptId) { JsonResponse::error('Vui lòng nhập phòng ban.', 400); return; }
        JsonResponse::ok($this->budgetService->checkBudget($deptId, $period, $amount));
    }
}
