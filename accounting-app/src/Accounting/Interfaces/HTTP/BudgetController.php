<?php
declare(strict_types=1);
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\BudgetService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Dự toán Ngân sách (Budget & Planning)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý kịch bản dự toán ngân sách
 *   - So sánh dự toán vs thực tế (variance analysis)
 *   - Xuất báo cáo chênh lệch
 *
 * API endpoints:
 *   GET  /api/budget/scenarios — Danh sách kịch bản
 *   POST /api/budget/scenarios — Tạo kịch bản mới
 *   POST /api/budget/scenarios/{id}/activate — Kích hoạt
 *   PUT  /api/budget/{id} — Thiết lập dự toán
 *   GET  /api/budget/{id}/lines — Chi tiết
 *   GET  /api/budget/{id}/variance — So sánh dự toán/thực tế
 *   GET  /api/budget/dashboard — Dashboard
 *   GET  /api/budget/{id}/export — Xuất CSV
 *   GET  /api/budget/view — View HTML
 *
 * Tích hợp:
 *   - BudgetService so sánh với số liệu thực tế từ TransactionRepository
 *   - Module kết chuyển cuối kỳ
 */
class BudgetController
{
    private BudgetService $service;

    public function __construct(BudgetService $service) { $this->service = $service; }

    /**
     * Danh sách kịch bản dự toán theo năm
     *
     * @return void
     */
    public function scenarios(): void
    {
        Auth::requirePermission('budget', 'read');
        $year = (int)($_GET['year'] ?? date('Y'));
        JsonResponse::ok($this->service->getScenarios($year));
    }

    /**
     * Tạo kịch bản dự toán mới
     *
     * @return void
     */
    public function createScenario(): void
    {
        Auth::requirePermission('budget', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['name']) || !isset($data['year'])) {
            JsonResponse::error('Vui lòng nhập tên và năm', 400); return;
        }
        $r = $this->service->createScenario(
            $data['name'], (int)$data['year'], $data['type'] ?? 'operating',
            $data['notes'] ?? null, Auth::currentUser()
        );
        JsonResponse::ok($r, 201);
    }

    /**
     * Kích hoạt kịch bản dự toán
     *
     * @param string $id ID kịch bản
     * @return void
     */
    public function activateScenario(string $id): void
    {
        Auth::requirePermission('budget', 'update');
        Auth::checkCsrf();
        $this->service->activateScenario($id);
        JsonResponse::ok(['message' => 'Đã kích hoạt kịch bản']);
    }

    /**
     * Thiết lập dự toán cho một tài khoản/kỳ
     *
     * @param string $id ID kịch bản
     * @return void
     */
    public function setBudget(string $id): void
    {
        Auth::requirePermission('budget', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['period_code']) || !isset($data['account_code']) || !isset($data['amount'])) {
            JsonResponse::error('Vui lòng nhập kỳ, tài khoản và số tiền', 400); return;
        }
        $this->service->setBudget(
            $id, $data['period_code'], $data['account_code'],
            (float)$data['amount'], $data['notes'] ?? null
        );
        JsonResponse::ok(['message' => 'Đã thiết lập dự toán']);
    }

    /**
     * Chi tiết dự toán
     *
     * @param string $id ID kịch bản
     * @return void
     */
    public function getBudgetLines(string $id): void
    {
        Auth::requirePermission('budget', 'read');
        JsonResponse::ok($this->service->getBudgetLines($id));
    }

    /**
     * So sánh dự toán vs thực tế
     *
     * @param string $id ID kịch bản
     * @return void
     */
    public function variance(string $id): void
    {
        Auth::requirePermission('budget', 'read');
        JsonResponse::ok([
            'summary' => $this->service->getSummary($id),
            'lines' => $this->service->getVarianceReport($id),
        ]);
    }

    /**
     * Dashboard dự toán
     *
     * @return void
     */
    public function dashboard(): void
    {
        Auth::requirePermission('budget', 'read');
        $year = (int)($_GET['year'] ?? date('Y'));
        JsonResponse::ok($this->service->getDashboard($year));
    }

    /**
     * Xuất báo cáo chênh lệch
     *
     * @param string $id ID kịch bản
     * @return void
     */
    public function export(string $id): void
    {
        Auth::requirePermission('budget', 'read');
        $result = $this->service->exportVarianceReport($id);
        header('Content-Type: ' . $result['mime']);
        header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        echo $result['content'];
    }

    /**
     * View HTML
     *
     * @return void
     */
    public function viewIndex(): void
    {
        Auth::requirePermission('budget', 'read');
        require __DIR__ . '/../../../../public/views/budget.php';
    }
}
