<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\DashboardService;

class DashboardController
{
    public function __construct(
        private DashboardService $service,
    ) {}

    // GET /api/dashboard
    public function index(): void
    {
        \Accounting\Infrastructure\Auth::requirePermission('report', 'read');
        $data = $this->service->getKPIs();
        \Accounting\Infrastructure\JsonResponse::ok($data);
    }
}
