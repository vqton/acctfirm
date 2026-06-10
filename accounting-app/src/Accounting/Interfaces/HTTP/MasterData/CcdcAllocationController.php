<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Service\CcdcAllocationService;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;

/**
 * MODULE: Phân bổ CCDC (Công cụ dụng cụ)
 *
 * Mục đích nghiệp vụ:
 *   - Chạy phân bổ giá trị CCDC vào nhiều kỳ
 *   - Xem lịch sử phân bổ
 *
 * API endpoints:
 *   POST   /api/ccdc-allocations/run — Chạy phân bổ
 *   GET    /api/ccdc-allocations/history — Lịch sử phân bổ
 *   GET    /api/ccdc-allocations — View HTML
 *
 * Tích hợp:
 *   - CcdcAllocationService xử lý nghiệp vụ phân bổ
 */
class CcdcAllocationController
{
    private CcdcAllocationService $allocationService;

    /**
     * @param CcdcAllocationService $allocationService
     */
    public function __construct(CcdcAllocationService $allocationService)
    {
        $this->allocationService = $allocationService;
    }

    /**
     * Chạy phân bổ CCDC hàng tháng
     *
     * @return void
     */
    public function run(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('inventory', 'post');
        $data = json_decode(file_get_contents('php://input'), true);
        $period = $data['period'] ?? date('Y-m');
        $results = $this->allocationService->runMonthlyAllocation($period, $_SESSION['user_id'] ?? 'system');
        $success = count(array_filter($results, fn($r) => !isset($r['error'])));
        JsonResponse::ok(['total' => count($results), 'success' => $success, 'results' => $results]);
    }

    /**
     * Lịch sử phân bổ
     *
     * @return void
     */
    public function history(): void
    {
        Auth::requirePermission('inventory', 'read');
        $ccdcId = $_GET['ccdc_id'] ?? null;
        JsonResponse::ok($this->allocationService->getAllocationHistory($ccdcId));
    }

    /**
     * View HTML phân bổ CCDC
     *
     * @return void
     */
    public function view(): void
    {
        require __DIR__ . '/../../../../../public/views/ccdc_allocations.php';
    }
}
