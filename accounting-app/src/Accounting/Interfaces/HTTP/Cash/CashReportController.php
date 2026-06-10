<?php
namespace Accounting\Interfaces\HTTP\Cash;

use Accounting\Domain\Service\CashReportService;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Báo cáo Tiền tệ
 *
 * Mục đích nghiệp vụ:
 *   - Dashboard KPI tiền mặt, số dư, dòng tiền
 *   - Sổ phụ ngân hàng theo tài khoản
 *   - Báo cáo dòng tiền ngày
 *   - Biểu đồ tập trung tiền và xu hướng
 *
 * API endpoints:
 *   GET /api/cash-report/kpis — KPI tổng quan
 *   GET /api/cash-report/position — Vị thế tiền tệ
 *   GET /api/cash-report/bank-ledger — Sổ phụ ngân hàng
 *   GET /api/cash-report/daily-flow — Dòng tiền theo ngày
 *   GET /api/cash-report/concentration — Tập trung tiền
 *   GET /api/cash-report/trend — Xu hướng dòng tiền
 *
 * Tích hợp:
 *   - CashReportService đọc từ AccountRepository + TransactionRepository
 */
class CashReportController
{
    private CashReportService $report;

    public function __construct(CashReportService $report)
    {
        $this->report = $report;
    }

    /**
     * KPI tổng quan tiền tệ
     *
     * @return void
     */
    public function kpis(): void
    {
        JsonResponse::ok($this->report->getKPIs());
    }

    /**
     * Vị thế tiền tệ hiện tại
     *
     * @return void
     */
    public function position(): void
    {
        JsonResponse::ok($this->report->getCashPosition());
    }

    /**
     * Sổ phụ ngân hàng
     *
     * @return void
     */
    public function bankLedger(): void
    {
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        $account = $_GET['account'] ?? '112';
        JsonResponse::ok($this->report->getBankLedger($from, $to, $account));
    }

    /**
     * Dòng tiền theo ngày
     *
     * @return void
     */
    public function dailyFlow(): void
    {
        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to = $_GET['to'] ?? date('Y-m-d');
        JsonResponse::ok($this->report->getDailyCashFlow($from, $to));
    }

    /**
     * Tập trung tiền theo tài khoản
     *
     * @return void
     */
    public function concentration(): void
    {
        JsonResponse::ok($this->report->getCashConcentration());
    }

    /**
     * Xu hướng dòng tiền
     *
     * @return void
     */
    public function trend(): void
    {
        $days = (int)($_GET['days'] ?? 7);
        JsonResponse::ok($this->report->getCashFlowTrend($days));
    }
}
