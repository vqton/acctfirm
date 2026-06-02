<?php
declare(strict_types=1);
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\BudgetService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class BudgetController
{
    private BudgetService $service;

    public function __construct(BudgetService $service) { $this->service = $service; }

    public function scenarios(): void
    {
        Auth::requirePermission('budget', 'read');
        $year = (int)($_GET['year'] ?? date('Y'));
        JsonResponse::ok($this->service->getScenarios($year));
    }

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

    public function activateScenario(string $id): void
    {
        Auth::requirePermission('budget', 'update');
        Auth::checkCsrf();
        $this->service->activateScenario($id);
        JsonResponse::ok(['message' => 'Đã kích hoạt kịch bản']);
    }

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

    public function getBudgetLines(string $id): void
    {
        Auth::requirePermission('budget', 'read');
        JsonResponse::ok($this->service->getBudgetLines($id));
    }

    public function variance(string $id): void
    {
        Auth::requirePermission('budget', 'read');
        JsonResponse::ok([
            'summary' => $this->service->getSummary($id),
            'lines' => $this->service->getVarianceReport($id),
        ]);
    }

    public function dashboard(): void
    {
        Auth::requirePermission('budget', 'read');
        $year = (int)($_GET['year'] ?? date('Y'));
        JsonResponse::ok($this->service->getDashboard($year));
    }

    public function export(string $id): void
    {
        Auth::requirePermission('budget', 'read');
        $result = $this->service->exportVarianceReport($id);
        header('Content-Type: ' . $result['mime']);
        header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        echo $result['content'];
    }

    public function viewIndex(): void
    {
        Auth::requirePermission('budget', 'read');
        ob_start();
        require __DIR__ . '/../../public/views/budget.php';
        $content = ob_get_clean();
        require __DIR__ . '/../../public/views/layout.php';
    }
}
