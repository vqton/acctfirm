<?php
namespace Accounting\Interfaces\HTTP\Cash;

use Accounting\Domain\Service\CashReportService;
use Accounting\Infrastructure\JsonResponse;

class CashReportController
{
    private CashReportService $report;

    public function __construct(CashReportService $report)
    {
        $this->report = $report;
    }

    public function kpis(): void
    {
        JsonResponse::ok($this->report->getKPIs());
    }

    public function position(): void
    {
        JsonResponse::ok($this->report->getCashPosition());
    }

    public function bankLedger(): void
    {
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        $account = $_GET['account'] ?? '112';
        JsonResponse::ok($this->report->getBankLedger($from, $to, $account));
    }

    public function dailyFlow(): void
    {
        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to = $_GET['to'] ?? date('Y-m-d');
        JsonResponse::ok($this->report->getDailyCashFlow($from, $to));
    }

    public function concentration(): void
    {
        JsonResponse::ok($this->report->getCashConcentration());
    }

    public function trend(): void
    {
        $days = (int)($_GET['days'] ?? 7);
        JsonResponse::ok($this->report->getCashFlowTrend($days));
    }
}
