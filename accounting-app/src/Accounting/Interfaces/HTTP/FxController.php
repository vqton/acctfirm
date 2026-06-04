<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Service\FxRevaluationService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Đánh giá lại Ngoại tệ (FX Revaluation)
 *
 * Mục đích nghiệp vụ:
 *   - Đánh giá lại số dư ngoại tệ cuối kỳ theo tỷ giá cuối kỳ
 *   - Tạo bút toán điều chỉnh chênh lệch tỷ giá
 *   - Xem báo cáo đánh giá lại trước khi ghi nhận
 *   - Chênh lệch ghi vào TK 515 (lãi) hoặc 635 (lỗ) tỷ giá
 *   - Tuân thủ VAS 10 — Ảnh hưởng của thay đổi tỷ giá hối đoái
 *
 * API endpoints:
 *   POST /api/fx/revaluate/{periodId} — Đánh giá lại và tạo bút toán
 *   GET  /api/fx/report/{periodId}    — Báo cáo đánh giá lại (xem trước)
 *   GET  /api/fx/view                 — View HTML
 *
 * Rủi ro:
 *   - Sai tỷ giá cuối kỳ → sai chênh lệch → sai BC02 (515/635)
 *   - R001: Đánh giá lại sau khi đóng kỳ → sai số dư
 *   - Bút toán điều chỉnh không được kiểm tra Dr = Cr → mất cân đối
 *   - Ngoại tệ TK 111, 112, 131, 331 đều cần đánh giá lại
 *
 * Tích hợp:
 *   - FxRevaluationService gọi JournalService để ghi bút toán
 *   - ExchangeRateController cung cấp tỷ giá cuối kỳ
 *   - Kết quả ảnh hưởng BC01 (số dư ngoại tệ) và BC02 (chênh lệch TG)
 */
class FxController
{
    private FxRevaluationService $fx;

    public function __construct(FxRevaluationService $fx) { $this->fx = $fx; }

    // NGHIỆP VỤ: Đánh giá lại số dư ngoại tệ cuối kỳ — VAS 10 (Ảnh hưởng thay đổi tỷ giá)
    // Input: periodId (int) — ID kỳ kế toán
    // Output: { transactions: [...], fx_gains: number, fx_losses: number }
    // Service: FxRevaluationService.revaluate() → gọi JournalService.postEntry cho mỗi chênh lệch
    // Permission: system, edit
    // Hạch toán: Nợ 111,112,131,331 / Có 515 (lãi TG) hoặc Nợ 635 / Có 111,112,131,331 (lỗ TG)
    // Rủi ro: R001 — Không cho đánh giá lại nếu kỳ đã đóng. Sai tỷ giá cuối kỳ → sai BC02 (515/635)
    // Ràng buộc: Bút toán điều chỉnh tự động được ghi = journal, phải đảm bảo Dr = Cr
    public function revaluate(int $periodId): void
    {
        Auth::requirePermission('system', 'edit');
        try {
            JsonResponse::ok($this->fx->revaluate($periodId));
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }

    // NGHIỆP VỤ: Xem trước báo cáo đánh giá lại — không tạo bút toán
    // Output: { items: [{ account_code, currency, book_balance, fx_rate, revalued_amount, difference }], total_gain, total_loss }
    // Service: FxRevaluationService.getRevaluationReport() — read-only
    // Mục đích: Kế toán kiểm tra trước khi xác nhận đánh giá lại
    // Rủi ro: Cần đảm bảo tỷ giá cuối kỳ đã được cập nhật (ExchangeRateController)
    // Báo cáo đánh giá lại (không tạo bút toán)
    public function report(int $periodId): void
    {
        try {
            JsonResponse::ok($this->fx->getRevaluationReport($periodId));
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }

    // View
    public function view(): void
    {
        require __DIR__ . '/../../../../public/views/fx_revaluation.php';
    }
}
