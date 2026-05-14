<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\CashReportService;
use Accounting\Infrastructure\Helpers;

class CashReportController
{
    private CashReportService $report;

    public function __construct(CashReportService $report)
    {
        $this->report = $report;
    }

    public function position(): void
    {
        Helpers::jsonOk($this->report->getCashPosition());
    }

    public function bankLedger(): void
    {
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        $account = $_GET['account'] ?? '112';
        Helpers::jsonOk($this->report->getBankLedger($from, $to, $account));
    }

    public function dailyFlow(): void
    {
        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to = $_GET['to'] ?? date('Y-m-d');
        Helpers::jsonOk($this->report->getDailyCashFlow($from, $to));
    }

    public function concentration(): void
    {
        Helpers::jsonOk($this->report->getCashConcentration());
    }

    public function trend(): void
    {
        $days = (int)($_GET['days'] ?? 7);
        Helpers::jsonOk($this->report->getCashFlowTrend($days));
    }
}
