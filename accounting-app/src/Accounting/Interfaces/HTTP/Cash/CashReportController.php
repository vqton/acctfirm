<?php
namespace Accounting\Interfaces\HTTP\Cash;

use Accounting\Domain\Service\CashReportService;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Báo cáo Tiền tệ
 *
 * Mục đích nghiệp vụ:
 *   - Dashboard tổng quan: KPI tiền mặt, số dư, dòng tiền
 *   - Sổ phụ ngân hàng (bank ledger) theo tài khoản
 *   - Báo cáo dòng tiền ngày (daily flow)
 *   - Biểu đồ tập trung tiền (concentration) và xu hướng (trend)
 *
 * API endpoints:
 *   GET /api/cash-report/kpis         — KPI tổng quan (tổng tiền mặt, tổng tiền gửi)
 *   GET /api/cash-report/position     — Vị thế tiền tệ hiện tại
 *   GET /api/cash-report/bank-ledger  — Sổ phụ ngân hàng (filter: from, to, account)
 *   GET /api/cash-report/daily-flow   — Dòng tiền theo ngày
 *   GET /api/cash-report/concentration — Tập trung tiền theo tài khoản
 *   GET /api/cash-report/trend        — Xu hướng dòng tiền (7/30 ngày)
 *
 * Rủi ro:
 *   - Số liệu báo cáo phụ thuộc vào dữ liệu đã post (draft không được tính)
 *   - Bank ledger cần đối chiếu với sao kê ngân hàng định kỳ
 *
 * Tích hợp:
 *   - CashReportService đọc từ AccountRepository + TransactionRepository
 *   - Số liệu KPI dùng để hiển thị dashboard màn hình chính
 */
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
