<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\FsNotesService;
use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;

//
// BC09 — Thuyết minh Báo cáo tài chính (Notes to Financial Statements)
// Mẫu B09-DN theo Thông tư 99/2025/TT-BTC
//
// API endpoints:
//   GET  /api/fs/bc09/:periodId          — Lấy báo cáo BC09
//   POST /api/fs/bc09/:periodId/generate  — Sinh tự động chỉ tiêu auto-calc
//   PUT  /api/fs/bc09/:periodId/indicator/:code — Cập nhật chỉ tiêu thủ công
//   POST /api/fs/bc09/:periodId/validate  — Kiểm tra chéo số liệu
//   GET  /api/fs/bc09/policies            — Mẫu chính sách kế toán
//   GET  /bao-cao-tai-chinh/thuyet-minh-bc09 — Giao diện người dùng
//
class FsNotesController
{
    private FsNotesService $fsNotes;

    public function __construct(FsNotesService $fsNotes)
    {
        $this->fsNotes = $fsNotes;
    }

    //
    // GET /api/fs/bc09/{periodId}
    // Trả về toàn bộ báo cáo BC09 cho một kỳ kế toán
    //
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

    //
    // POST /api/fs/bc09/{periodId}/generate
    // Sinh tự động các chỉ tiêu auto-calc từ số dư tài khoản
    //
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

    //
    // PUT /api/fs/bc09/{periodId}/indicator/{code}
    // Cập nhật số liệu cho một chỉ tiêu (nhập tay)
    // Body JSON: { "year_start": 1000000, "year_end": 1500000, "note_text": "..." }
    //
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

    //
    // POST /api/fs/bc09/{periodId}/validate
    // Kiểm tra chéo số liệu BC09 với BC01/BC02
    //
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

    //
    // GET /api/fs/bc09/policies
    // Trả về danh sách mẫu chính sách kế toán (Section IV)
    //
    public function getPolicies(): void
    {
        Auth::requirePermission('report', 'read');
        $policies = $this->fsNotes->getPolicyTemplates();
        JsonResponse::ok(['policies' => $policies]);
    }

    //
    // GET /bao-cao-tai-chinh/thuyet-minh-bc09
    // Giao diện người dùng
    //
    public function viewIndex(): void
    {
        Auth::requirePermission('report', 'read');
        $title = 'Thuyết minh BCTC (BC09)';
        $activeMenu = 'fs_bc09';
        require __DIR__ . '/../../../../public/views/fs-bc09.php';
    }
}
