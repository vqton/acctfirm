<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\DebtCollectionService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Đòi nợ & Xử lý Công nợ (Debt Collection)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý quy trình đòi nợ khách hàng
 *   - Theo dõi lịch sử nhắc nợ, đàm phán, thỏa thuận
 *   - Xử lý nợ xấu, nợ khó đòi
 *   - Trích lập dự phòng phải thu khó đòi (TK 2293)
 *   - Báo cáo aging và phân tích nợ
 *
 * API endpoints:
 *   GET  /api/debt-collection/dashboard     — Dashboard
 *   GET  /api/debt-collection/aging         — Báo cáo aging
 *   GET  /api/debt-collection/provision     — Dự phòng
 *   POST /api/debt-collection/provision/calculate — Tính dự phòng
 *   POST /api/debt-collection/{id}/remind   — Gửi nhắc nợ
 *   POST /api/debt-collection/{id}/promise  — Ghi nhận hẹn trả
 *   POST /api/debt-collection/{id}/writeoff — Xoá nợ
 *
 * Rủi ro:
 *   - Xóa nợ sai -> mất quyền đòi nợ
 *   - Dự phòng không đúng TT 48 -> sai BC01
 *   - Nhắc nợ không đúng quy trình -> ảnh hưởng quan hệ KH
 *
 * Tích hợp:
 *   - DebtCollectionService gọi ArService / ApService
 *   - JournalService cho bút toán trích lập/xóa dự phòng
 *   - Báo cáo aging ảnh hưởng BC01 (dự phòng 2293)
 *   - Provision tính theo TT 48/2019/TT-BTC
 */
class DebtCollectionController
{
    private DebtCollectionService $service;

    public function __construct(DebtCollectionService $service)
    {
        $this->service = $service;
    }

    /**
     * Dashboard tổng quan tình hình công nợ
     *
     * @return void
     */
    public function dashboard(): void
    {
        Auth::requirePermission('ar', 'read');
        JsonResponse::ok($this->service->getDashboard());
    }

    /**
     * Báo cáo aging công nợ phải thu (theo kỳ hạn nợ)
     *
     * @return void
     */
    public function aging(): void
    {
        Auth::requirePermission('ar', 'read');
        $asOf = $_GET['as_of'] ?? date('Y-m-d');
        $type = $_GET['type'] ?? 'ar';
        JsonResponse::ok($this->service->getAgingReport($asOf, $type));
    }

    /**
     * Báo cáo dự phòng phải thu khó đòi (TK 2293)
     *
     * @return void
     */
    public function provision(): void
    {
        Auth::requirePermission('ar', 'read');
        $period = $_GET['period'] ?? date('Y-m');
        JsonResponse::ok($this->service->getProvisionReport($period));
    }

    /**
     * Tính toán dự phòng phải thu khó đòi theo TT 48
     *
     * @return void
     */
    public function calculateProvision(): void
    {
        Auth::requirePermission('ar', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        $period = $data['period'] ?? date('Y-m');
        try {
            $result = $this->service->calculateProvision($period, $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Ghi nhận nhắc nợ cho khách hàng
     *
     * @param string $id Mã hóa đơn/công nợ
     * @return void
     */
    public function sendReminder(string $id): void
    {
        Auth::requirePermission('ar', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $result = $this->service->sendReminder($id, $data['method'] ?? 'email', $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Ghi nhận lịch hẹn trả nợ của khách hàng
     *
     * @param string $id Mã hóa đơn/công nợ
     * @return void
     */
    public function recordPromise(string $id): void
    {
        Auth::requirePermission('ar', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['promised_date'])) {
            JsonResponse::error('Vui lòng nhập ngày hẹn trả', 400);
            return;
        }
        try {
            $result = $this->service->recordPromise($id, $data['promised_date'], $data['notes'] ?? '', $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Xóa sổ công nợ khó đòi (write-off)
     *
     * @param string $id Mã hóa đơn/công nợ
     * @return void
     */
    public function writeOff(string $id): void
    {
        Auth::requirePermission('ar', 'delete');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $result = $this->service->writeOff($id, $data['reason'] ?? '', $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Báo cáo chi tiết nợ quá hạn
     *
     * @return void
     */
    public function overdueReport(): void
    {
        Auth::requirePermission('ar', 'read');
        $daysOverdue = (int)($_GET['days'] ?? 30);
        JsonResponse::ok($this->service->getOverdueReport($daysOverdue));
    }

    /**
     * View báo cáo công nợ
     *
     * @return void
     */
    public function viewIndex(): void
    {
        require __DIR__ . '/../../../../../public/views/debt-collection.php';
    }

    /**
     * Ghi nhận thỏa thuận thanh toán với khách hàng
     *
     * @return void
     */
    public function recordSettlement(): void
    {
        Auth::requirePermission('ar', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['ar_invoice_id'], $data['settlement_amount'])) {
            JsonResponse::error('Vui lòng nhập mã hóa đơn và số tiền thỏa thuận', 400);
            return;
        }
        try {
            $result = $this->service->recordSettlement(
                $data['ar_invoice_id'],
                (float)$data['settlement_amount'],
                $data['reason'] ?? '', $_SESSION['user_id'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Khởi kiện — ghi nhận hồ sơ khởi kiện khách hàng nợ
     *
     * @return void
     */
    public function initiateLawsuit(): void
    {
        Auth::requirePermission('ar', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['customer_id'], $data['amount'])) {
            JsonResponse::error('Vui lòng nhập mã khách hàng và số tiền khởi kiện', 400);
            return;
        }
        try {
            $result = $this->service->initiateLawsuit(
                $data['customer_id'],
                (float)$data['amount'],
                $data['reason'] ?? '', $_SESSION['user_id'] ?? 'system'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Danh sách hồ sơ khởi kiện
     *
     * @return void
     */
    public function listLawsuits(): void
    {
        Auth::requirePermission('ar', 'read');
        $status = $_GET['status'] ?? '';
        JsonResponse::ok($this->service->listLawsuits($status));
    }
}
