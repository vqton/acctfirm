<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\CurrencyService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Ngoại tệ (Currency Management)
 *
 * Mục đích nghiệp vụ:
 *   - Quản lý giao dịch ngoại tệ
 *   - Theo dõi số dư ngoại tệ theo từng loại tiền
 *   - Đánh giá lại ngoại tệ cuối kỳ (VAS 10)
 *
 * API endpoints:
 *   GET /api/currency/balances — Số dư ngoại tệ
 *   GET /api/currency/transactions/{accountCode} — Giao dịch theo TK ngoại tệ
 *
 * Rủi ro:
 *   - Sai tỷ giá -> sai số dư quy đổi VND
 *   - Không đánh giá lại cuối kỳ -> BCTC sai
 *
 * Tích hợp:
 *   - CurrencyService đọc từ TransactionRepository
 *   - ExchangeRateController cung cấp tỷ giá
 *   - FxController xử lý đánh giá lại
 */
class CurrencyController
{
    private CurrencyService $currency;

    public function __construct(CurrencyService $currency) { $this->currency = $currency; }

    /**
     * Số dư các tài khoản ngoại tệ
     *
     * @return void
     */
    public function balances(): void
    {
        Auth::requirePermission('report', 'read');
        JsonResponse::ok($this->currency->getFcBalances());
    }

    /**
     * Giao dịch ngoại tệ cho một tài khoản
     *
     * @param string $accountCode Mã tài khoản (1112, 1122, 131, 331)
     * @return void
     */
    public function transactions(string $accountCode): void
    {
        Auth::requirePermission('report', 'read');
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        JsonResponse::ok($this->currency->getCurrencyTransactions($accountCode, $from, $to));
    }

    /**
     * View ngoại tệ
     *
     * @return void
     */
    public function view(): void
    {
        require __DIR__ . '/../../../../../public/views/currency.php';
    }
}
