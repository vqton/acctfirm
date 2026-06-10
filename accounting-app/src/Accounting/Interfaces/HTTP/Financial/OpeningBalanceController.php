<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\OpeningBalanceService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Số dư đầu kỳ (Opening Balance)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý số dư đầu kỳ cho từng tài khoản
 *   - Import số dư đầu kỳ từ kỳ trước hoặc file
 *   - Kiểm tra cân đối Dr = Cr của số dư đầu kỳ
 *
 * API endpoints:
 *   GET  /api/opening-balance/{periodId} — Số dư đầu kỳ
 *   POST /api/opening-balance/{periodId} — Thiết lập số dư
 *   POST /api/opening-balance/{periodId}/lock — Khoá số dư
 *
 * Rủi ro:
 *   - Số dư đầu kỳ không cân -> sai toàn bộ BC
 *   - Nhập sai số dư -> sai số liệu kỳ hiện tại
 *   - Không khoá sau khi nhập -> có thể bị sửa
 *
 * Tích hợp:
 *   - OpeningBalanceService đọc/ghi bảng opening_balances
 *   - PeriodService kiểm tra kỳ mở
 *   - AccountRepository cung cấp danh sách tài khoản
 */
class OpeningBalanceController
{
    private OpeningBalanceService $service;

    public function __construct(OpeningBalanceService $service) { $this->service = $service; }

    /**
     * Lấy số dư đầu kỳ cho một kỳ
     *
     * @param string $periodId ID kỳ kế toán
     * @return void
     */
    public function get(string $periodId): void
    {
        Auth::requirePermission('master_data', 'read');
        try {
            JsonResponse::ok($this->service->getBalances((int)$periodId));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * Thiết lập số dư đầu kỳ
     *
     * @param string $periodId ID kỳ kế toán
     * @return void
     */
    public function save(string $periodId): void
    {
        Auth::requirePermission('master_data', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['balances'])) {
            JsonResponse::error('Vui lòng nhập danh sách số dư', 400);
            return;
        }
        try {
            $this->service->saveBalances((int)$periodId, $data['balances'], $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok(['message' => 'Đã lưu số dư đầu kỳ']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Khoá số dư đầu kỳ — sau khoá không sửa được
     *
     * @param string $periodId ID kỳ kế toán
     * @return void
     */
    public function lock(string $periodId): void
    {
        Auth::requirePermission('master_data', 'update');
        Auth::checkCsrf();
        try {
            $this->service->lockBalances((int)$periodId);
            JsonResponse::ok(['message' => 'Đã khoá số dư đầu kỳ']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }
}
