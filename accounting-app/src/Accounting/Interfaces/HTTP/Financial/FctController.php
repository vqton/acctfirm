<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\FctService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Thuế TNCN (Personal Income Tax — FCT)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý thuế thu nhập cá nhân (TK 3335)
 *   - Tính thuế TNCN cho nhân viên theo biểu thuế lũy tiến
 *   - Theo dõi khấu trừ, quyết toán, hoàn thuế
 *   - Xuất file CSV/HCM theo chuẩn CQT
 *
 * API endpoints:
 *   POST /api/fct/calculate — Tính thuế TNCN
 *   GET  /api/fct/declarations — Danh sách tờ khai
 *   GET  /api/fct/report — Báo cáo tổng hợp
 *   GET  /api/fct/export — Xuất file CSV
 *
 * Rủi ro:
 *   - Sai biểu thuế -> sai thuế TNCN -> phạt
 *   - Không kê khai kịp thời -> lãi chậm nộp
 *   - Sai số người phụ thuộc -> sai mức giảm trừ
 *
 * Tích hợp:
 *   - FctService đọc từ PayrollService
 *   - ExportService xuất file CSV/HCM
 *   - PayrollController quản lý bảng lương
 */
class FctController
{
    private FctService $fct;

    public function __construct(FctService $fct) { $this->fct = $fct; }

    /**
     * Tính thuế TNCN cho nhân viên
     *
     * @return void
     */
    public function calculate(): void
    {
        Auth::requirePermission('tax', 'create');
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['period'])) {
            JsonResponse::error('Vui lòng nhập kỳ tính thuế', 400);
            return;
        }
        try {
            $result = $this->fct->calculate($data['period'], $_SESSION['user_id'] ?? 'system');
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Danh sách tờ khai thuế TNCN
     *
     * @return void
     */
    public function declarations(): void
    {
        Auth::requirePermission('tax', 'read');
        $period = $_GET['period'] ?? date('Y-m');
        JsonResponse::ok($this->fct->getDeclarations($period));
    }

    /**
     * Báo cáo tổng hợp thuế TNCN
     *
     * @return void
     */
    public function report(): void
    {
        Auth::requirePermission('tax', 'read');
        $period = $_GET['period'] ?? date('Y-m');
        JsonResponse::ok($this->fct->getFctReport($period));
    }

    /**
     * Xuất file CSV thuế TNCN theo chuẩn CQT
     *
     * @return void
     */
    public function export(): void
    {
        Auth::requirePermission('tax', 'read');
        $period = $_GET['period'] ?? date('Y-m');
        try {
            $result = $this->fct->exportCsv($period);
            header('Content-Type: ' . $result['mime']);
            header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
            echo $result['content'];
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * View thuế TNCN
     *
     * @return void
     */
    public function view(): void
    {
        require __DIR__ . '/../../../../../public/views/fct.php';
    }
}
