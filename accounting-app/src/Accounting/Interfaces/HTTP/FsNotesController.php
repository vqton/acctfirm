<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\FsNotesService;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;

/**
 * MODULE: BC09 — Thuyết minh Báo cáo Tài chính
 *
 * Mục đích nghiệp vụ:
 *   - Mẫu B09-DN theo TT 99
 *   - Sinh tự động các chỉ tiêu từ số dư tài khoản
 *   - Cập nhật chỉ tiêu nhập tay
 *   - Kiểm tra chéo số liệu BC09 với BC01/BC02
 *   - Mẫu chính sách kế toán
 *
 * API endpoints:
 *   GET  /api/fs/bc09/{periodId} — Báo cáo BC09
 *   POST /api/fs/bc09/{periodId}/generate — Sinh tự động
 *   PUT  /api/fs/bc09/{periodId}/indicator/{code} — Cập nhật
 *   POST /api/fs/bc09/{periodId}/validate — Kiểm tra chéo
 *   GET  /api/fs/bc09/policies — Mẫu chính sách
 *   GET  /bao-cao-tai-chinh/thuyet-minh-bc09 — View HTML
 *
 * Tích hợp:
 *   - FsNotesService xử lý logic BC09
 *   - Dữ liệu từ AccountRepository, LedgerEntry
 */
class FsNotesController
{
    private FsNotesService $fsNotes;

    public function __construct(FsNotesService $fsNotes)
    {
        $this->fsNotes = $fsNotes;
    }

    /**
     * Lấy báo cáo BC09 cho một kỳ
     *
     * @param string $periodId ID kỳ kế toán
     * @return void
     */
    public function getReport(string $periodId): void
    {
        Auth::requirePermission('report', 'read');
        $id = (int)$periodId;
        if ($id <= 0) {
            JsonResponse::error('Mã kỳ kế toán không hợp lệ', 400);
            return;
        }
        try {
            $data = $this->fsNotes->getReport($id);
            JsonResponse::ok($data);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * Sinh tự động các chỉ tiêu auto-calc
     *
     * @param string $periodId ID kỳ kế toán
     * @return void
     */
    public function generate(string $periodId): void
    {
        Auth::requirePermission('report', 'create');
        $id = (int)$periodId;
        if ($id <= 0) {
            JsonResponse::error('Mã kỳ kế toán không hợp lệ', 400);
            return;
        }
        try {
            $data = $this->fsNotes->generate($id);
            JsonResponse::ok([
                'period_id' => $id,
                'indicators_generated' => count($data),
                'items' => $data,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            JsonResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * Cập nhật chỉ tiêu nhập tay
     *
     * @param string $periodId ID kỳ kế toán
     * @param string $code Mã chỉ tiêu
     * @return void
     */
    public function updateIndicator(string $periodId, string $code): void
    {
        Auth::requirePermission('report', 'update');
        $id = (int)$periodId;
        if ($id <= 0) {
            JsonResponse::error('Mã kỳ kế toán không hợp lệ', 400);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) {
            JsonResponse::error('Dữ liệu đầu vào không hợp lệ', 400);
            return;
        }

        $yearStart = (float)($body['year_start'] ?? 0);
        $yearEnd = (float)($body['year_end'] ?? 0);
        $noteText = $body['note_text'] ?? null;

        try {
            $this->fsNotes->updateIndicator($id, $code, $yearStart, $yearEnd, $noteText);
            JsonResponse::ok(['ok' => true, 'indicator_code' => $code]);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * Kiểm tra chéo số liệu BC09 với BC01/BC02
     *
     * @param string $periodId ID kỳ kế toán
     * @return void
     */
    public function validate(string $periodId): void
    {
        Auth::requirePermission('report', 'read');
        $id = (int)$periodId;
        if ($id <= 0) {
            JsonResponse::error('Mã kỳ kế toán không hợp lệ', 400);
            return;
        }
        try {
            $result = $this->fsNotes->validate($id);
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * Danh sách mẫu chính sách kế toán
     *
     * @return void
     */
    public function getPolicies(): void
    {
        Auth::requirePermission('report', 'read');
        $policies = $this->fsNotes->getPolicyTemplates();
        JsonResponse::ok(['policies' => $policies]);
    }

    /**
     * View HTML
     *
     * @return void
     */
    public function viewIndex(): void
    {
        Auth::requirePermission('report', 'read');
        $title = 'Thuyết minh BCTC (BC09)';
        $activeMenu = 'fs_bc09';
        require __DIR__ . '/../../../../public/views/fs-bc09.php';
    }
}
