<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\PeriodService;
use Accounting\Infrastructure\Helpers;

class PeriodController
{
    private PeriodService $period;

    public function __construct(PeriodService $period) { $this->period = $period; }

    public function list(): void
    {
        Helpers::jsonOk($this->period->getPeriods());
    }

    public function get(int $id): void
    {
        try { Helpers::jsonOk($this->period->getPeriod($id)); }
        catch (\InvalidArgumentException $e) { Helpers::jsonError($e->getMessage(), 404); }
    }

    public function create(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['period_type'], $data['period_code'], $data['name'], $data['start_date'], $data['end_date'])) {
            Helpers::jsonError('period_type, period_code, name, start_date, end_date required');
            return;
        }
        try {
            $result = $this->period->createPeriod(
                $data['period_type'], $data['period_code'], $data['name'],
                $data['start_date'], $data['end_date'],
                $data['created_by'] ?? 'system'
            );
            Helpers::jsonOk($result, 201);
        } catch (\InvalidArgumentException $e) { Helpers::jsonError($e->getMessage()); }
    }

    public function close(int $id): void
    {
        Helpers::requirePermission('system', 'edit');
        try {
            $result = $this->period->closePeriod($id, $_SESSION['user']['username'] ?? 'system');
            Helpers::jsonOk($result);
        } catch (\InvalidArgumentException $e) { Helpers::jsonError($e->getMessage()); }
    }

    public function reOpen(int $id): void
    {
        Helpers::requirePermission('system', 'edit');
        try {
            $result = $this->period->reOpenPeriod($id, $_SESSION['user']['username'] ?? 'system');
            Helpers::jsonOk($result);
        } catch (\InvalidArgumentException $e) { Helpers::jsonError($e->getMessage()); }
    }

    public function canClose(int $id): void
    {
        Helpers::jsonOk($this->period->canClose($id));
    }

    public function executeClosing(int $id): void
    {
        Helpers::requirePermission('system', 'edit');
        try {
            $this->period->executeClosingEntries($_SESSION['user']['username'] ?? 'system');
            Helpers::jsonOk(['message' => 'Closing entries executed']);
        } catch (\InvalidArgumentException $e) { Helpers::jsonError($e->getMessage()); }
    }
}
