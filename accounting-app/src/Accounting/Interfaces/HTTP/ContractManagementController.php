<?php
declare(strict_types=1);
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\ContractService;
use Accounting\Domain\Repository\ContractRepositoryInterface;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Quản lý Hợp đồng nâng cao (Contract Management)
 *
 * Mục đích nghiệp vụ:
 *   - Dashboard hợp đồng, thống kê
 *   - Chi tiết hợp đồng + lịch thanh toán + phụ lục
 *   - Liên kết chứng từ (AP/AR/Cash) với hợp đồng
 *   - Ghi nhận thanh toán theo lịch, thanh lý hợp đồng
 *
 * API endpoints:
 *   GET  /api/contracts/dashboard — Dashboard
 *   GET  /api/contracts/{id}      — Chi tiết
 *   POST /api/contracts/{id}/link — Liên kết chứng từ
 *   POST /api/contracts/{id}/payment-schedule — Thêm lịch TT
 *   POST /api/contracts/{id}/record-payment  — Ghi nhận TT
 *   POST /api/contracts/{id}/amendment        — Phụ lục
 *   POST /api/contracts/{id}/liquidate        — Thanh lý
 *   GET  /api/contracts/export  — Xuất danh sách
 *   GET  /api/contracts/view    — View HTML
 *
 * Rủi ro:
 *   - Thanh lý hợp đồng chưa tất toán công nợ
 *   - Phụ lục làm sai lệch tổng giá trị hợp đồng
 *
 * Tích hợp:
 *   - ContractService gọi JournalService cho bút toán
 *   - MasterData/ContractController quản lý CRUD cơ bản
 */
class ContractManagementController
{
    private ContractService $contractService;
    private ContractRepositoryInterface $contractRepo;

    public function __construct(ContractService $contractService, ContractRepositoryInterface $contractRepo)
    {
        $this->contractService = $contractService;
        $this->contractRepo = $contractRepo;
    }

    /**
     * Dashboard hợp đồng
     *
     * @return void
     */
    public function dashboard(): void
    {
        Auth::requirePermission('contract', 'read');
        $stats = $this->contractService->getDashboardStats();
        $expiring = $this->contractService->getExpiringContracts(30);
        $contracts = [];
        foreach ($this->contractRepo->findAll() as $c) {
            $contracts[] = $c->toArray();
        }
        JsonResponse::ok(['stats' => $stats, 'expiring' => $expiring, 'contracts' => $contracts]);
    }

    /**
     * Chi tiết hợp đồng kèm lịch thanh toán, phụ lục, link
     *
     * @param string $id ID hợp đồng
     * @return void
     */
    public function getDetail(string $id): void
    {
        Auth::requirePermission('contract', 'read');
        $contract = $this->contractRepo->findById($id);
        if (!$contract) { JsonResponse::error('Không tìm thấy hợp đồng', 404); return; }
        $data = $contract->toArray();
        $data['fulfillment_links'] = $this->contractService->getFulfillmentLinks($id);
        $data['payment_schedules'] = $this->contractService->getPaymentSchedules($id);
        $data['amendments'] = $this->contractService->getAmendments($id);
        JsonResponse::ok($data);
    }

    /**
     * Liên kết chứng từ với hợp đồng
     *
     * @param string $id ID hợp đồng
     * @return void
     */
    public function linkTransaction(string $id): void
    {
        Auth::requirePermission('contract', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['linked_type']) || !isset($data['linked_id'])) {
            JsonResponse::error('Vui lòng nhập loại và ID chứng từ', 400);
            return;
        }
        try {
            $this->contractService->linkTransaction(
                $id, $data['linked_type'], $data['linked_id'],
                $data['linked_reference'] ?? null, (float)($data['amount'] ?? 0),
                $data['description'] ?? '', Auth::currentUser()
            );
            JsonResponse::ok(['message' => 'Đã liên kết chứng từ với hợp đồng']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Thêm lịch thanh toán cho hợp đồng
     *
     * @param string $id ID hợp đồng
     * @return void
     */
    public function addPaymentSchedule(string $id): void
    {
        Auth::requirePermission('contract', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['due_date']) || !isset($data['amount'])) {
            JsonResponse::error('Vui lòng nhập ngày đến hạn và số tiền', 400);
            return;
        }
        try {
            $this->contractService->addPaymentSchedule(
                $id, $data['due_date'], (float)$data['amount'],
                $data['milestone'] ?? null, $data['notes'] ?? null
            );
            JsonResponse::ok(['message' => 'Đã thêm lịch thanh toán'], 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Ghi nhận thanh toán theo lịch
     *
     * @param string $id ID hợp đồng
     * @return void
     */
    public function recordPaymentSchedule(string $id): void
    {
        Auth::requirePermission('cash', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'])) {
            JsonResponse::error('Vui lòng nhập số tiền', 400);
            return;
        }
        try {
            $this->contractService->recordPaymentSchedule($id, (float)$data['amount'], Auth::currentUser());
            JsonResponse::ok(['message' => 'Đã ghi nhận thanh toán']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Thêm phụ lục hợp đồng
     *
     * @param string $id ID hợp đồng
     * @return void
     */
    public function addAmendment(string $id): void
    {
        Auth::requirePermission('contract', 'update');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amendment_no']) || !isset($data['amount_change'])) {
            JsonResponse::error('Vui lòng nhập số phụ lục và số tiền thay đổi', 400);
            return;
        }
        try {
            $this->contractService->addAmendment(
                $id, $data['amendment_no'], $data['date'] ?? date('Y-m-d'),
                $data['type'] ?? 'increase', (float)$data['amount_change'],
                $data['description'] ?? '', Auth::currentUser()
            );
            JsonResponse::ok(['message' => 'Đã thêm phụ lục hợp đồng']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Thanh lý hợp đồng
     *
     * @param string $id ID hợp đồng
     * @return void
     */
    public function liquidate(string $id): void
    {
        Auth::requirePermission('contract', 'delete');
        Auth::checkCsrf();
        try {
            $this->contractService->liquidateContract($id, Auth::currentUser());
            JsonResponse::ok(['message' => 'Đã thanh lý hợp đồng']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Xuất danh sách hợp đồng
     *
     * @return void
     */
    public function exportContract(): void
    {
        Auth::requirePermission('contract', 'read');
        $result = $this->contractService->exportContractList($_GET['format'] ?? 'csv', $_GET);
        header('Content-Type: ' . $result['mime']);
        header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        echo $result['content'];
    }

    /**
     * View HTML
     *
     * @return void
     */
    public function viewIndex(): void
    {
        Auth::requirePermission('contract', 'read');
        require __DIR__ . '/../../../../public/views/contracts.php';
    }
}
