<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\CitService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Thuế TNDN (Corporate Income Tax)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý thuế thu nhập doanh nghiệp tạm tính và quyết toán năm
 *   - Theo dõi số thuế TNDN phải nộp (TK 3334)
 *   - Tính toán chênh lệch kế toán - thuế
 *   - Quản lý tạm tính hàng quý và quyết toán năm
 *
 * API endpoints:
 *   GET  /api/cit/declarations       — Danh sách tờ khai
 *   GET  /api/cit/declarations/{id}  — Chi tiết tờ khai
 *   POST /api/cit/declarations       — Tạo tờ khai mới
 *   POST /api/cit/declarations/{id}/finalize — Khoá tờ khai
 *   GET  /api/cit/dashboard          — Dashboard CIT
 *   GET  /api/cit/report             — Báo cáo tổng hợp
 *
 * Rủi ro:
 *   - Sai lợi nhuận tính thuế -> sai thuế TNDN -> phạt
 *   - Chênh lệch tạm tính và quyết toán -> truy thu/lãi chậm nộp
 *   - Không theo dõi được lỗ lũy kế -> mất quyền chuyển lỗ
 *
 * Tích hợp:
 *   - CitService đọc từ AccountRepository (TK 421, 821, 3334)
 *   - FsService cung cấp lợi nhuận kế toán từ BC02
 *   - Kết quả ảnh hưởng BC01 (3334) và BC02 (821)
 */
class CitController
{
    private CitService $cit;

    public function __construct(CitService $cit) { $this->cit = $cit; }

    /**
     * Danh sách tờ khai thuế TNDN
     *
     * @return void
     */
    public function declarations(): void
    {
        Auth::requirePermission('tax', 'read');
        $period = $_GET['period'] ?? date('Y');
        JsonResponse::ok($this->cit->getDeclarations($period));
    }

    /**
     * Chi tiết tờ khai thuế TNDN
     *
     * @param string $id ID tờ khai
     * @return void
     */
    public function getDeclaration(string $id): void
    {
        Auth::requirePermission('tax', 'read');
        $decl = $this->cit->getDeclaration($id);
        if (!$decl) { JsonResponse::error('Không tìm thấy tờ khai', 404); return; }
        JsonResponse::ok($decl);
    }

    /**
     * Tạo tờ khai thuế TNDN mới
     *
     * @return void
     */
    public function createDeclaration(): void
    {
        Auth::requirePermission('tax', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        $period = $data['period'] ?? date('Y');
        $createdBy = $_SESSION['user_id'] ?? 'system';
        try {
            $decl = $this->cit->createDeclaration($period, $createdBy);
            JsonResponse::ok($decl, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Khoá tờ khai thuế TNDN — sau finalize không sửa được
     *
     * @param string $id ID tờ khai
     * @return void
     */
    public function finalizeDeclaration(string $id): void
    {
        Auth::requirePermission('tax', 'post');
        Auth::checkCsrf();
        try {
            $this->cit->finalizeDeclaration($id);
            JsonResponse::ok(['message' => 'Đã khoá tờ khai']);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Dashboard CIT — tổng quan tình hình thuế TNDN
     *
     * @return void
     */
    public function dashboard(): void
    {
        Auth::requirePermission('tax', 'read');
        $year = (int)($_GET['year'] ?? date('Y'));
        JsonResponse::ok($this->cit->getDashboard($year));
    }

    /**
     * Báo cáo tổng hợp thuế TNDN
     *
     * @return void
     */
    public function report(): void
    {
        Auth::requirePermission('tax', 'read');
        $period = $_GET['period'] ?? date('Y');
        JsonResponse::ok($this->cit->getCitReport($period));
    }

    /**
     * View tờ khai CIT
     *
     * @return void
     */
    public function view(): void
    {
        require __DIR__ . '/../../../../../public/views/cit.php';
    }
}
