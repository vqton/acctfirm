<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\PeriodService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Kỳ Kế toán (Accounting Period)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý kỳ kế toán: tháng, quý, năm
 *   - Tạo kỳ mới với ngày bắt đầu/kết thúc
 *   - Đóng kỳ kế toán (chỉ Kế toán trưởng) — kỳ đã đóng là read-only
 *   - Kết chuyển cuối kỳ: doanh thu, chi phí → 911 → 421
 *   - Mở lại kỳ (nếu cần điều chỉnh — phải có phê duyệt đặc biệt)
 *
 * API endpoints:
 *   GET    /api/periods          — Danh sách kỳ kế toán
 *   GET    /api/periods/{id}     — Chi tiết kỳ
 *   POST   /api/periods          — Tạo kỳ mới
 *   POST   /api/periods/{id}/close   — Đóng kỳ
 *   POST   /api/periods/{id}/reopen  — Mở lại kỳ
 *   POST   /api/periods/{id}/close-year — Kết chuyển năm (tạo kỳ mới)
 *
 * Rủi ro:
 *   - R001 (CRITICAL): Post vào kỳ đã đóng → sai số liệu kỳ trước
 *   - Đóng kỳ trước khi kết chuyển đầy đủ → BC02 sai
 *   - Mở lại kỳ đã đóng → phải có audit trail lý do
 *   - R005: Kết chuyển sai tài khoản → số dư 421 sai
 *
 * Tích hợp:
 *   - PeriodService kiểm tra isPeriodOpen() trước mọi post request
 *   - FsService dùng thông tin kỳ để xuất BC01/BC02/BC03
 *   - JournalService kiểm tra period trước khi post entry
 */
class PeriodController
{
    private PeriodService $period;

    public function __construct(PeriodService $period) { $this->period = $period; }

    /**
     * Danh sách kỳ kế toán
     *
     * @return void
     */
    public function list(): void
    {
        JsonResponse::ok($this->period->getPeriods());
    }

    /**
     * Chi tiết kỳ kế toán
     *
     * @param int $id ID kỳ
     * @throws \InvalidArgumentException
     * @return void
     */
    public function get(int $id): void
    {
        try { JsonResponse::ok($this->period->getPeriod($id)); }
        catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage(), 404); }
    }

    /**
     * NGHIỆP VỤ: Tạo kỳ kế toán mới — tháng/quý/năm
     *
     * Input: { period_type (monthly|quarterly|yearly), period_code (2025-01), name, start_date, end_date, created_by? }
     * Output: { id, period_code, status: 'open' } — 201 Created
     * Service: PeriodService.createPeriod()
     * Rủi ro: Trùng period_code → 409. Ngày tháng phải không overlap với kỳ đã tồn tại
     * Ràng buộc: Mỗi kỳ phải có start_date < end_date. Kỳ mới mặc định status = 'open'
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    public function create(): void
    {
        Auth::checkCsrf();
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['period_type'], $data['period_code'], $data['name'], $data['start_date'], $data['end_date'])) {
            JsonResponse::error('Vui lòng nhập loại kỳ, mã kỳ, tên, ngày bắt đầu và ngày kết thúc');
            return;
        }
        try {
            $result = $this->period->createPeriod(
                $data['period_type'], $data['period_code'], $data['name'],
                $data['start_date'], $data['end_date'],
                $data['created_by'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * NGHIỆP VỤ: Đóng kỳ kế toán — kỳ đã đóng là READ-ONLY, không post/sửa/xóa được
     *
     * Input: id (URL)
     * Output: { period_id, status: 'closed', closed_by, closed_at }
     * Service: PeriodService.closePeriod() — cập nhật status → closed
     * Permission: system, edit (chỉ Kế toán trưởng)
     * Rủi ro: R001 (CRITICAL) — Đóng kỳ sai → không thể post bổ sung. Nếu cần sửa → reOpen (audit trail)
     * Pre-close: Nên chạy ReconciliationController.run() + canClose() trước khi close
     * Audit trail: Lưu user đóng + lý do + thời gian
     *
     * @param int $id ID kỳ
     * @throws \InvalidArgumentException
     * @return void
     */
    public function close(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('system', 'edit');
        try {
            $result = $this->period->closePeriod($id, $_SESSION['user']['username'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * NGHIỆP VỤ: Mở lại kỳ đã đóng — chỉ khi có điều chỉnh hồi tố
     *
     * Input: id (URL)
     * Output: { period_id, status: 'open', reopened_by, reason }
     * Service: PeriodService.reOpenPeriod()
     * Permission: system, edit (chỉ Kế toán trưởng)
     * Rủi ro: R001 — Mở lại kỳ có thể gây sai lệch BCTC đã nộp. Cần audit trail lý do chi tiết
     * Ràng buộc: Phải ghi nhận lý do reOpen (audit log). Cảnh báo: ảnh hưởng BCTC đã gửi cơ quan thuế
     * Quy trình: ReOpen → điều chỉnh → close → phát hành BCTC điều chỉnh
     *
     * @param int $id ID kỳ
     * @throws \InvalidArgumentException
     * @return void
     */
    public function reOpen(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('system', 'edit');
        try {
            $result = $this->period->reOpenPeriod($id, $_SESSION['user']['username'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * Kiểm tra khả năng đóng kỳ
     *
     * @param int $id ID kỳ
     * @return void
     */
    public function canClose(int $id): void
    {
        JsonResponse::ok($this->period->canClose($id));
    }

    /**
     * Checklist pre-close chi tiết
     *
     * @param int $id ID kỳ
     * @return void
     */
    public function checklist(int $id): void
    {
        JsonResponse::ok($this->period->getCloseChecklist($id));
    }

    /**
     * NGHIỆP VỤ: Thực hiện kết chuyển cuối kỳ — doanh thu, chi phí → 911 → 421
     *
     * Input: id (URL)
     * Output: { message: 'Closing entries executed' }
     * Service: PeriodService.executeClosingEntries() → JournalService.postEntry
     * Permission: system, edit (Kế toán trưởng)
     * Quy trình kết chuyển: (1) Kết chuyển 511,515,711 → 911 (2) Kết chuyển 632,635,641,642,811 → 911
     * (3) Kết chuyển 911 → 421 (lợi nhuận sau thuế). Nếu lỗ: Nợ 421 / Có 911
     * Rủi ro: R005 — Kết chuyển sai tài khoản → số dư 421 sai → BC02 sai
     * Ràng buộc: Phải chạy trước khi closePeriod. Không chạy lại nếu đã close
     *
     * @param int $id ID kỳ
     * @throws \InvalidArgumentException
     * @return void
     */
    public function executeClosing(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('system', 'edit');
        try {
            $this->period->executeClosingEntries($_SESSION['user']['username'] ?? 'system');
            JsonResponse::ok(['message' => 'Đã thực hiện kết chuyển cuối kỳ']);
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * NGHIỆP VỤ: Lưu trữ kỳ kế toán — chụp snapshot dữ liệu, không sửa được nữa
     *
     * Input: id (URL)
     * Output: { period_id, status: 'archived', snapshot_id }
     * Service: PeriodService.archivePeriod() — tạo accounting_period_snapshots
     * Permission: system, edit
     * Rủi ro: Sau archive, không thể mở lại (khác với close). Phục hồi cần DBA
     * Mục đích: Giữ dữ liệu BCTC cố định cho mục đích kiểm toán và lưu trữ pháp lý
     *
     * @param int $id ID kỳ
     * @throws \InvalidArgumentException
     * @return void
     */
    public function archive(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('system', 'edit');
        try {
            $result = $this->period->archivePeriod($id, $_SESSION['user']['username'] ?? 'system');
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * NGHIỆP VỤ: Đóng kỳ với checklist — pre-close checks → closePeriod
     *
     * Input: id (URL)
     * Output: { period_id, status, checks: [{check_name, passed, details}] }
     * Service: PeriodService.canClose() kiểm tra: Trial Balance Dr=Cr, all posted, closing entries done
     * Nếu can_close=false → trả về 422 kèm danh sách checks fail để kế toán xử lý
     * Rủi ro: R001 — Bỏ qua fail check và close → sai số liệu → phải reOpen
     * Ràng buộc: Đây là flow chuẩn. Gọi close() trực tiếp (không checklist) cần phê duyệt riêng
     * KỲ KẾ TOÁN: Quy trình đóng kỳ gồm 2 bước:
     * 1. Kiểm tra pre-close checklist (canClose) — nếu fail, trả về 422 kèm chi tiết
     * 2. Thực hiện đóng kỳ (closePeriod) — chỉ chạy nếu tất cả checks pass
     *
     * @param int $id ID kỳ
     * @throws \InvalidArgumentException
     * @return void
     */
    public function closeWithChecklist(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('system', 'edit');
        try {
            $checklist = $this->period->canClose($id);
            if (!$checklist['can_close']) {
                JsonResponse::error([
                    'message' => 'Kiểm tra trước khi đóng kỳ thất bại. Vui lòng khắc phục trước khi đóng.',
                    'checks' => $checklist['checks'],
                ], 422);
                return;
            }
            $result = $this->period->closePeriod($id, $_SESSION['user']['username'] ?? 'system');
            $result['checks'] = $checklist['checks'];
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * NGHIỆP VỤ: Thiết lập hạn chót (deadline) cho kỳ kế toán — sau deadline không post thêm
     *
     * Input: { deadline (datetime) }
     * Output: { period_id, deadline, set_by }
     * Service: PeriodService.setDeadline()
     * Permission: system, edit (Kế toán trưởng)
     * Rủi ro: Deadline quá sớm → chưa kịp ghi nhận hết nghiệp vụ. Quá muộn → chậm BCTC
     * Mục đích: Quản lý tiến độ khóa sổ, cảnh báo khi đến gần deadline
     * HARD DEADLINE: Kế toán trưởng thiết lập deadline cho kỳ
     *
     * NGHIỆP VỤ: Tự động tạo 12 kỳ tháng cho một năm tài chính
     *
     * Input: { fiscal_year: 2026 }
     * Output: [period, period, ...] — 12 kỳ đã tạo, status = 'open'
     * Service: PeriodService.generatePeriods()
     * Permission: system, edit (Kế toán trưởng)
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    public function generate(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('system', 'edit');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['fiscal_year'])) {
            JsonResponse::error('Vui lòng nhập năm tài chính (fiscal_year)');
            return;
        }
        try {
            $result = $this->period->generatePeriods(
                (int)$data['fiscal_year'],
                $_SESSION['user']['username'] ?? 'system'
            );
            JsonResponse::ok($result, 201);
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * CẤU HÌNH KỲ KẾ TOÁN: Lấy tất cả cấu hình
     *
     * @return void
     */
    public function listConfigs(): void
    {
        JsonResponse::ok($this->period->getAllPeriodConfigs());
    }

    /**
     * CẤU HÌNH KỲ KẾ TOÁN: Cập nhật giá trị cấu hình
     *
     * Input: { key: 'cit_rate', value: 0.20 }
     * Output: { key, value, updated_by }
     *
     * @return void
     */
    public function setConfig(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('system', 'edit');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['key'], $data['value'])) {
            JsonResponse::error('Vui lòng nhập key và value');
            return;
        }
        $this->period->setPeriodConfig($data['key'], (float)$data['value'], $_SESSION['user']['username'] ?? 'system');
        JsonResponse::ok(['key' => $data['key'], 'value' => (float)$data['value']]);
    }

    /**
     * Thiết lập hạn chót cho kỳ kế toán
     *
     * @param int $id ID kỳ
     * @throws \InvalidArgumentException
     * @return void
     */
    public function setDeadline(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('system', 'edit');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['deadline'])) {
            JsonResponse::error('Vui lòng nhập hạn chót');
            return;
        }
        try {
            JsonResponse::ok($this->period->setDeadline($id, $data['deadline'], $_SESSION['user']['username'] ?? 'system'));
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * NGHIỆP VỤ: Ghi đè deadline — cho phép ghi nhận bổ sung sau deadline
     *
     * Input: { reason? }
     * Output: { period_id, original_deadline, new_deadline, override_by, reason }
     * Service: PeriodService.overrideDeadline()
     * Permission: system, edit (chỉ Kế toán trưởng)
     * Rủi ro: Override deadline làm giảm tính kỷ luật kế toán. Phải ghi rõ lý do trong audit trail
     * Audit trail: Lưu user override, thời gian, lý do. Báo cáo số lần override mỗi kỳ
     * HARD DEADLINE: Kế toán trưởng override deadline để cho phép ghi nhận bổ sung
     *
     * @param int $id ID kỳ
     * @throws \InvalidArgumentException
     * @return void
     */
    public function overrideDeadline(int $id): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('system', 'edit');
        $data = json_decode(file_get_contents('php://input'), true);
        $reason = $data['reason'] ?? 'Ghi đè bởi Kế toán trưởng';
        try {
            JsonResponse::ok($this->period->overrideDeadline($id, $reason, $_SESSION['user']['username'] ?? 'system'));
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * NGHIỆP VỤ: So sánh số liệu giữa 2 kỳ kế toán (R-7)
     *
     * Mục đích: Phân tích biến động doanh thu, chi phí, tài sản giữa 2 kỳ
     * Sử dụng: BC quản trị, audit phát hiện biến động bất thường, so sánh thực tế vs kế hoạch
     *
     * Input: ?from=2025-01&to=2025-02 (period_code)
     * Output: { period_a, period_b, variance: { by_type, by_account, by_account_count } }
     *
     * Permission: report.read (ai có quyền xem BC)
     * Rủi ro: Query nặng nếu nhiều accounts — có thể cache hoặc async nếu data lớn
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    public function compare(): void
    {
        Auth::requirePermission('report', 'read');
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';
        if (!$from || !$to) {
            JsonResponse::error('Thiếu tham số from/to (period_code)', 400);
            return;
        }
        if ($from === $to) {
            JsonResponse::error('Kỳ so sánh phải khác nhau', 400);
            return;
        }
        try {
            JsonResponse::ok($this->period->comparePeriods($from, $to));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }
}
