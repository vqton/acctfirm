<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\DashboardService;

/**
 * MODULE: Bảng điều khiển (Dashboard)
 *
 * Tổng hợp các chỉ số KPI cho giao diện chính:
 * - Số dư tiền mặt, ngân hàng
 * - Doanh thu và chi phí theo kỳ
 * - Công nợ phải thu/phải trả quá hạn
 * - Số dư thử (trial balance)
 * - Biểu đồ dòng tiền
 *
 * API endpoints:
 *   GET /api/dashboard — Tổng hợp KPI
 *
 * Rủi ro:
 *   - Query nặng nếu nhiều dữ liệu — có thể cache
 *   - Permission: report.read
 *
 * Tích hợp:
 *   - DashboardService tổng hợp từ nhiều repository
 *   - Gọi từ trang chính (index) sau khi đăng nhập
 */
class DashboardController
{
    public function __construct(
        private DashboardService $service,
    ) {}

    /**
     * Lấy tổng hợp KPI cho bảng điều khiển
     *
     * GET /api/dashboard
     *
     * @return void
     */
    public function index(): void
    {
        \Accounting\Infrastructure\Auth::requirePermission('report', 'read');
        $data = $this->service->getKPIs();
        \Accounting\Infrastructure\JsonResponse::ok($data);
    }
}
