<?php
//
// CURRENCY CONTROLLER — R-11 Multi-Currency Display
//
// Endpoints:
//   GET  /api/currencies                    — list ngoại tệ active
//   GET  /api/currencies/rate/:code         — tỷ giá 1 ngoại tệ vs VND
//   POST /api/currencies/convert            — quy đổi { amount, from, to, date? }
//   GET  /api/currencies/preference         — display_currency hiện tại của user
//   POST /api/currencies/preference         — set display_currency (body: { currency })
//
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\CurrencyDisplayService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

class CurrencyController
{
    private CurrencyDisplayService $svc;

    public function __construct(CurrencyDisplayService $svc)
    {
        $this->svc = $svc;
    }

    public function listCurrencies(): void
    {
        Auth::requirePermission('report', 'read');
        JsonResponse::ok(['currencies' => $this->svc->listCurrencies()]);
    }

    public function getRate(string $code): void
    {
        Auth::requirePermission('report', 'read');
        $rate = $this->svc->getRate($code);
        if (!$rate) {
            JsonResponse::error("Không tìm thấy tỷ giá cho {$code}", 404);
            return;
        }
        JsonResponse::ok($rate);
    }

    //
    // POST /api/currencies/convert
    // Body: { amount: 1000000, from: 'VND', to: 'USD', date?: '2026-05-19' }
    //
    public function convert(): void
    {
        Auth::requirePermission('report', 'read');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $amount = $data['amount'] ?? null;
        $from = strtoupper($data['from'] ?? 'VND');
        $to = strtoupper($data['to'] ?? '');
        $date = $data['date'] ?? null;

        if ($amount === null || !is_numeric($amount)) {
            JsonResponse::error('Thiếu amount (số)', 400);
            return;
        }
        if (!$to) {
            JsonResponse::error('Thiếu to (target currency)', 400);
            return;
        }

        if ($from === $to) {
            JsonResponse::ok([
                'amount' => (float)$amount,
                'currency' => $to,
                'rate' => 1.0,
                'note' => 'Same currency, no conversion',
            ]);
            return;
        }

        // Quy đổi qua VND làm trung gian
        $vndAmount = ($from === 'VND')
            ? (float)$amount
            : ($this->svc->convertToVnd((float)$amount, $from, $date)['amount'] ?? null);
        if ($vndAmount === null) {
            JsonResponse::error("Không tìm thấy tỷ giá cho {$from}", 404);
            return;
        }

        $result = $this->svc->convertFromVnd($vndAmount, $to, $date);
        if (!$result) {
            JsonResponse::error("Không tìm thấy tỷ giá cho {$to}", 404);
            return;
        }
        $result['original'] = ['amount' => (float)$amount, 'currency' => $from];
        JsonResponse::ok($result);
    }

    public function getPreference(): void
    {
        Auth::requirePermission('report', 'read');
        $userId = Auth::getCurrentUserId() ?? 'system';
        $currency = $this->svc->getUserDisplayCurrency($userId);
        JsonResponse::ok(['user_id' => $userId, 'display_currency' => $currency]);
    }

    //
    // POST /api/currencies/preference
    // Body: { currency: 'USD' }
    //
    public function setPreference(): void
    {
        Auth::checkCsrf();
        Auth::requirePermission('system', 'edit');
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $currency = strtoupper($data['currency'] ?? '');
        if (!$currency) {
            JsonResponse::error('Thiếu currency', 400);
            return;
        }
        $userId = Auth::getCurrentUserId() ?? 'system';
        try {
            $this->svc->setUserDisplayCurrency($userId, $currency);
            JsonResponse::ok(['user_id' => $userId, 'display_currency' => $currency]);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }
}
