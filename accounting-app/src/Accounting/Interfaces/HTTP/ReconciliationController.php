<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\ReconciliationService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Đối chiếu Tổng hợp (Reconciliation Dashboard)
 *
 * Mục đích nghiệp vụ:
 *   - Đối chiếu tổng thể giữa các module trong hệ thống
 *   - Kiểm tra: Trial Balance (Dr = Cr), sổ cái vs sổ chi tiết
 *   - Phát hiện mất cân đối và báo cáo chênh lệch
 *   - Hỗ trợ đối chiếu theo loại (all, trial_balance, coa, etc.)
 *
 * API endpoints:
 *   GET /api/reconciliation — Chạy đối chiếu (param: type=all|trial_balance|...)
 *
 * Rủi ro:
 *   - R002: Trial Balance không cân → toàn bộ hệ thống sai
 *   - Đối chiếu không phát hiện hết lỗi → sai BCTC
 *   - Cần chạy định kỳ trước khi khóa sổ
 *
 * Tích hợp:
 *   - ReconciliationService tổng hợp từ AccountRepository
 *   - Gọi trước khi PeriodController đóng kỳ
 *   - Kết quả dùng để kiểm tra tính toàn vẹn dữ liệu
 */
class ReconciliationController
{
    private ReconciliationService $service;

    public function __construct(ReconciliationService $service)
    {
        $this->service = $service;
    }

    // NGHIỆP VỤ: Đối chiếu tổng thể hệ thống — kiểm tra tính toàn vẹn dữ liệu
    // Input: GET ?type=all|trial_balance|coa|...
    // Output: { trial_balance: {total_dr, total_cr, balanced}, ... }
    // Service: ReconciliationService.reconcileAll() — gọi nhiều sub-method
    // Permission: report, read
    // Rủi ro: R002 — Trial Balance không cân = toàn bộ hệ thống sai
    // Quy trình: Chạy trước khi PeriodController đóng kỳ, nếu fail thì không cho đóng
    public function run(): void
    {
        Auth::requirePermission('report', 'read');
        $type = $_GET['type'] ?? 'all';
        if ($type === 'all') {
            $results = $this->service->reconcileAll();
        } else {
            $method = 'reconcile' . ucfirst($type);
            $results = method_exists($this->service, $method) ? [$type => $this->service->$method()] : ['error' => 'Loại đối chiếu không hợp lệ'];
        }
        JsonResponse::ok($results);
    }
}
