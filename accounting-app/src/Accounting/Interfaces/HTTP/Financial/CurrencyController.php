<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\CurrencyDisplayService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Ngoại tệ (Multi-Currency Display)
 *
 * Mục đích nghiệp vụ:
 *   - Hiển thị danh sách ngoại tệ active
 *   - Xem tỷ giá quy đổi
 *   - Chuyển đổi ngoại tệ
 *   - Thiết lập display_currency cho user
 *
 * API endpoints:
 *   GET  /api/currencies                — Danh sách ngoại tệ active
 *   GET  /api/currencies/rate/{code}    — Tỷ giá 1 ngoại tệ vs VND
 *   POST /api/currencies/convert        — Quy đổi { amount, from, to, date? }
 *   GET  /api/currencies/preference     — Display currency của user
 *   POST /api/currencies/preference     — Set display currency
 *
 * Tích hợp:
 *   - CurrencyDisplayService
 *   - ExchangeRateController cung cấp tỷ giá
 *   - FxController xử lý đánh giá lại cuối kỳ
 */
class CurrencyController
{
    private CurrencyDisplayService $svc;

    /**
     * @param CurrencyDisplayService $svc
     */
    public function __construct(CurrencyDisplayService $svc)
    {
        $this->svc = $svc;
    }

    /**
     * Danh sách ngoại tệ active
     *
     * @return void
     */
    public function listCurrencies(): void
    {
        Auth::requirePermission('report', 'read');
        JsonResponse::ok(['currencies' => $this->svc->listCurrencies()]);
    }

    /**
     * Tỷ giá của một ngoại tệ so với VND
     *
     * @param string $code Mã ngoại tệ (USD, EUR,...)
     * @return void
     */
    public function getRate(string $code): void
    {
        Auth::requirePermission('report', 'read');
        $rate = $this->svc->getRate($code);
        if ($rate === null) { JsonResponse::error('Không tìm thấy tỷ giá', 404); return; }
        JsonResponse::ok(['code' => $code, 'rate' => $rate]);
    }

    /**
     * Quy đổi tiền tệ
     *
     * @return void
     */
    public function convert(): void
    {
        Auth::requirePermission('report', 'read');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['amount'], $data['from'], $data['to'])) {
            JsonResponse::error('Vui lòng nhập số tiền, ngoại tệ nguồn và đích', 400);
            return;
        }
        try {
            $result = $this->svc->convert((float)$data['amount'], $data['from'], $data['to'], $data['date'] ?? null);
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Lấy display_currency của user hiện tại
     *
     * @return void
     */
    public function getPreference(): void
    {
        Auth::requirePermission('report', 'read');
        $userId = $_SESSION['user_id'] ?? '0';
        JsonResponse::ok(['currency' => $this->svc->getUserCurrency($userId)]);
    }

    /**
     * Thiết lập display_currency cho user
     *
     * @return void
     */
    public function setPreference(): void
    {
        Auth::requirePermission('report', 'read');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['currency'])) { JsonResponse::error('Vui lòng nhập mã ngoại tệ', 400); return; }
        $userId = $_SESSION['user_id'] ?? '0';
        $this->svc->setUserCurrency($userId, $data['currency']);
        JsonResponse::ok(['currency' => $data['currency']]);
    }
}
