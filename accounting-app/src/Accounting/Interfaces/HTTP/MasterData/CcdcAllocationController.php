<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Service\CcdcAllocationService;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;

class CcdcAllocationController
{
    private CcdcAllocationService $allocationService;

    public function __construct(CcdcAllocationService $allocationService)
    {
        $this->allocationService = $allocationService;
    }

    public function run(): void
    {
        Auth::requirePermission('inventory', 'post');
        $data = json_decode(file_get_contents('php://input'), true);
        $period = $data['period'] ?? date('Y-m');
        $results = $this->allocationService->runMonthlyAllocation($period, $_SESSION['user_id'] ?? 'system');
        $success = count(array_filter($results, fn($r) => !isset($r['error'])));
        JsonResponse::ok(['total' => count($results), 'success' => $success, 'results' => $results]);
    }

    public function history(): void
    {
        Auth::requirePermission('inventory', 'read');
        $ccdcId = $_GET['ccdc_id'] ?? null;
        JsonResponse::ok($this->allocationService->getAllocationHistory($ccdcId));
    }

    public function view(): void
    {
        require __DIR__ . '/../../../../../public/views/ccdc_allocations.php';
    }
}
