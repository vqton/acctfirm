<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\PeriodService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class PeriodController
{
    private PeriodService $period;

    public function __construct(PeriodService $period) { $this->period = $period; }

    public function list(): void
    {
        JsonResponse::ok($this->period->getPeriods());
    }

    public function get(int $id): void
    {
        try { JsonResponse::ok($this->period->getPeriod($id)); }
        catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage(), 404); }
    }

    public function create(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['period_type'], $data['period_code'], $data['name'], $data['start_date'], $data['end_date'])) {
            JsonResponse::error('period_type, period_code, name, start_date, end_date required');
            return;
        }
        try {
            $result = $this->period->createPeriod(
                $data['period_type'], $data['period_code'], $data['name'],
                $data['start_date'], $data['end_date'],
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }

    public function close(int $id): void
    {
        Auth::requirePermission('system', 'edit');
        try {
            $result = $this->period->closePeriod($id, $_SESSION['user']['username'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }

    public function reOpen(int $id): void
    {
        Auth::requirePermission('system', 'edit');
        try {
            $result = $this->period->reOpenPeriod($id, $_SESSION['user']['username'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }

    public function canClose(int $id): void
    {
        JsonResponse::ok($this->period->canClose($id));
    }

    public function executeClosing(int $id): void
    {
        Auth::requirePermission('system', 'edit');
        try {
            $this->period->executeClosingEntries($_SESSION['user']['username'] ?? 'system');
            JsonResponse::ok(['message' => 'Closing entries executed']);
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }
}
