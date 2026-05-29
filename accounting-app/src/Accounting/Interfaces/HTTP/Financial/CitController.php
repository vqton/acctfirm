<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\CitService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class CitController
{
    private CitService $cit;

    public function __construct(CitService $cit) { $this->cit = $cit; }

    public function list(): void
    {
        Auth::requirePermission('report', 'read');
        JsonResponse::ok($this->cit->getCalculations());
    }

    public function get(string $id): void
    {
        Auth::requirePermission('report', 'read');
        $calc = $this->cit->getCalculation($id);
        if (!$calc) { JsonResponse::error('Không tìm thấy quyết toán TNDN', 404); return; }
        JsonResponse::ok($calc);
    }

    public function prepare(): void
    {
        Auth::requirePermission('tax', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        $period = $data['period'] ?? date('Y-m');
        try {
            $result = $this->cit->prepareCalculation($period, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function finalise(string $id): void
    {
        Auth::requirePermission('tax', 'post');
        Auth::checkCsrf();
        try {
            JsonResponse::ok($this->cit->finalise($id));
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function view(): void
    {
        require __DIR__ . '/../../../../../public/views/cit_calculations.php';
    }
}
