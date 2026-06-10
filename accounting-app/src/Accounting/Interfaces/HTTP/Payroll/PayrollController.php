<?php
namespace Accounting\Interfaces\HTTP\Payroll;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Service\PayrollService;
use Accounting\Infrastructure\AuditLogger;

/**
 * MODULE: Bảng lương (Payroll)
 *
 * Mục đích nghiệp vụ:
 *   - Tính lương nhân viên hàng kỳ
 *   - Quản lý bảng lương, các khoản trích theo lương
 *   - Hạch toán lương và các khoản trích (334, 3383, 3384)
 *   - Tuân thủ quy định BHXH, BHYT, BHTN, KPCĐ
 *
 * API endpoints:
 *   POST /api/payroll/calculate — Tính lương
 *   POST /api/payroll/post — Ghi sổ
 *   GET  /api/payroll/list — Danh sách
 *   GET  /api/payroll/{id} — Chi tiết
 *
 * Rủi ro:
 *   - Sai lương -> ảnh hưởng thuế TNCN, BHXH
 *   - Sai hạch toán 334/338 -> sai BC01
 *
 * Tích hợp:
 *   - JournalService hạch toán bút toán lương
 *   - EmployeeController quản lý nhân viên
 *   - DepartmentController quản lý phòng ban
 */
class PayrollController
{
    private PayrollService $payroll;

    public function __construct(PayrollService $payroll) { $this->payroll = $payroll; }

    /**
     * Tính lương cho kỳ chỉ định
     *
     * @return void
     */
    public function calculate(): void
    {
        Auth::requirePermission('payroll', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        $period = $data['period'] ?? date('Y-m');
        try {
            $result = $this->payroll->calculatePayroll($period, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Ghi sổ bảng lương đã tính
     *
     * @return void
     */
    public function post(): void
    {
        Auth::requirePermission('payroll', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['period'])) {
            JsonResponse::error('Vui lòng nhập kỳ lương', 400);
            return;
        }
        try {
            $result = $this->payroll->postPayroll($data['period'], $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Danh sách bảng lương
     *
     * @return void
     */
    public function list(): void
    {
        Auth::requirePermission('payroll', 'read');
        $period = $_GET['period'] ?? date('Y-m');
        JsonResponse::ok($this->payroll->getPayrollList($period));
    }

    /**
     * Chi tiết bảng lương
     *
     * @param string $id Mã bảng lương
     * @return void
     */
    public function get(string $id): void
    {
        Auth::requirePermission('payroll', 'read');
        try {
            JsonResponse::ok($this->payroll->getPayrollDetail($id));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }
}
