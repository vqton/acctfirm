<?php
declare(strict_types=1);
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\ProjectAccountingService;
use Accounting\Domain\Repository\ProjectRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Kế toán Dự án (Project Accounting)
 *
 * Mục đích nghiệp vụ:
 *   - Dashboard dự án
 *   - Báo cáo chi phí/doanh thu dự án
 *   - Phân bổ chi phí vào dự án
 *   - Tạo chứng từ yêu cầu thanh toán (Progress Billing)
 *   - Ghi nhận doanh thu dự án
 *   - Kết thúc dự án
 *   - Thiết lập ngân sách dự án
 *
 * API endpoints:
 *   GET  /api/project/dashboard — Dashboard
 *   GET  /api/project/{id} — Báo cáo
 *   POST /api/project/{id}/allocate-cost — Phân bổ chi phí
 *   POST /api/project/{id}/billing — Tạo billing
 *   POST /api/project/{id}/recognize-revenue — Ghi nhận DT
 *   POST /api/project/{id}/finalize — Kết thúc
 *   PUT  /api/project/{id}/budget — Ngân sách
 *   GET  /api/project/{id}/export — Xuất CSV
 *   GET  /api/project/view — View HTML
 *
 * Tích hợp:
 *   - ProjectAccountingService gọi JournalService
 *   - MasterData/ProjectController quản lý CRUD
 */
class ProjectAccountingController
{
    private ProjectAccountingService $service;
    private ProjectRepositoryInterface $projectRepo;

    public function __construct(ProjectAccountingService $service, ProjectRepositoryInterface $projectRepo)
    {
        $this->service = $service;
        $this->projectRepo = $projectRepo;
    }

    /**
     * Dashboard dự án
     *
     * @return void
     */
    public function dashboard(): void
    {
        Auth::requirePermission('project', 'read');
        JsonResponse::ok([
            'stats' => $this->service->getDashboardStats(),
            'projects' => $this->service->getActiveProjectsList(),
        ]);
    }

    /**
     * Báo cáo chi phí/doanh thu dự án
     *
     * @param string $id ID dự án
     * @return void
     */
    public function report(string $id): void
    {
        Auth::requirePermission('project', 'read');
        try {
            JsonResponse::ok($this->service->getProjectReport($id));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * Phân bổ chi phí vào dự án
     *
     * @param string $id ID dự án
     * @return void
     */
    public function allocateCost(string $id): void
    {
        Auth::requirePermission('project', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['transaction_id']) || !isset($data['account_code']) || !isset($data['amount'])) {
            JsonResponse::error('Vui lòng nhập ID chứng từ, tài khoản và số tiền', 400);
            return;
        }
        try {
            $this->service->allocateCost(
                $id, $data['transaction_id'], $data['account_code'],
                (float)$data['amount'], (bool)($data['is_debit'] ?? true)
            );
            JsonResponse::ok(['message' => 'Đã phân bổ chi phí vào dự án']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Tạo chứng từ yêu cầu thanh toán (Progress Billing)
     *
     * @param string $id ID dự án
     * @return void
     */
    public function createBilling(string $id): void
    {
        Auth::requirePermission('project', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['billing_date']) || !isset($data['amount'])) {
            JsonResponse::error('Vui lòng nhập ngày và số tiền', 400);
            return;
        }
        try {
            $billId = $this->service->createProgressBilling(
                $id, $data['billing_date'], (float)$data['amount'],
                (float)($data['pct_complete'] ?? 0), $data['description'] ?? '', Auth::currentUser()
            );
            JsonResponse::ok(['id' => $billId, 'message' => 'Đã tạo chứng từ yêu cầu thanh toán'], 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Ghi nhận doanh thu dự án
     *
     * @param string $id ID dự án
     * @return void
     */
    public function recognizeRevenue(string $id): void
    {
        Auth::requirePermission('project', 'update');
        Auth::checkCsrf();
        try {
            $revenue = $this->service->recognizeRevenue($id, Auth::currentUser());
            JsonResponse::ok(['revenue' => $revenue, 'message' => 'Đã ghi nhận doanh thu: ' . number_format($revenue)]);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Kết thúc dự án
     *
     * @param string $id ID dự án
     * @return void
     */
    public function finalize(string $id): void
    {
        Auth::requirePermission('project', 'delete');
        Auth::checkCsrf();
        try {
            $this->service->finalizeProject($id);
            JsonResponse::ok(['message' => 'Đã kết thúc dự án']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Thiết lập ngân sách dự án
     *
     * @param string $id ID dự án
     * @return void
     */
    public function setBudget(string $id): void
    {
        Auth::requirePermission('project', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['account_code']) || !isset($data['amount'])) {
            JsonResponse::error('Vui lòng nhập tài khoản và số tiền', 400);
            return;
        }
        try {
            $this->service->setBudgetLine($id, $data['account_code'], (float)$data['amount'], $data['notes'] ?? null);
            JsonResponse::ok(['message' => 'Đã thiết lập ngân sách cho tài khoản']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Xuất báo cáo dự án
     *
     * @param string $id ID dự án
     * @return void
     */
    public function exportReport(string $id): void
    {
        Auth::requirePermission('project', 'read');
        $result = $this->service->exportProjectReport($_GET['format'] ?? 'csv', $id);
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
        Auth::requirePermission('project', 'read');
        require __DIR__ . '/../../../../public/views/project-accounting.php';
    }
}
