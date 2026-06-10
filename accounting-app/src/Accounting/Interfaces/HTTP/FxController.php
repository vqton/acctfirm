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

    /**
     * Đánh giá lại số dư ngoại tệ cuối kỳ — VAS 10
     *
     * @param int $periodId ID kỳ kế toán
     * @return void
     */
    public function revaluate(int $periodId): void
    {
        Auth::requirePermission('system', 'edit');
        try {
            JsonResponse::ok($this->fx->revaluate($periodId));
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * Báo cáo đánh giá lại ngoại tệ (xem trước, không tạo bút toán)
     *
     * @param int $periodId ID kỳ kế toán
     * @return void
     */
    public function report(int $periodId): void
    {
        try {
            JsonResponse::ok($this->fx->getRevaluationReport($periodId));
        } catch (\InvalidArgumentException $e) { JsonResponse::error($e->getMessage()); }
    }

    /**
     * View giao diện đánh giá lại ngoại tệ
     *
     * @return void
     */
    public function view(): void
    {
        require __DIR__ . '/../../../../public/views/fx_revaluation.php';
    }
}
