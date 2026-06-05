<?php
//
// NGHIỆP VỤ: Hiển thị đa tiền tệ (Multi-Currency Display) — R-11
//
// Mục đích: Kế toán/ban giám đốc xem báo cáo tài chính theo VND (BC chính thức)
// NHƯNG có thể tùy chọn xem thêm số quy đổi sang USD/EUR/JPY... để dễ hiểu.
//
// Nguyên tắc:
//   1. Số gốc LUÔN là VND (chuẩn TT 99/2025/TT-BTC) — không thay đổi
//   2. Số quy đổi = VND / exchange_rate_to_target
//   3. Tỷ giá lấy từ bảng exchange_rates (mới nhất nếu không chỉ định ngày)
//   4. Mỗi user chọn display_currency mặc định trong users.display_currency
//
// KHÔNG ảnh hưởng:
//   - BC chính thức (vẫn theo VND)
//   - Bút toán gốc (không revalue)
//   - Audit trail (giữ nguyên giá trị gốc)
//
// Rủi ro:
//   - Tỷ giá thay đổi → số quy đổi hiển thị thay đổi (chấp nhận được cho báo cáo quản trị)
//   - Không có tỷ giá cho currency code → trả về null (không throw để view vẫn render)
//
namespace Accounting\Domain\Service;

class CurrencyDisplayService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    //
    // Danh sách ngoại tệ đang active (mới nhất)
    // Output: [ { code, name, rate, rate_date }, ... ]
    //
    public function listCurrencies(): array
    {
        $rows = $this->pdo->query(
            "SELECT currency_code AS code, currency_name AS name, rate, rate_date
             FROM exchange_rates
             WHERE (currency_code, rate_date) IN (
                 SELECT currency_code, MAX(rate_date) FROM exchange_rates GROUP BY currency_code
             )
             ORDER BY currency_code"
        )->fetchAll(\PDO::FETCH_ASSOC);
        // Luôn thêm VND là base currency với rate = 1
        array_unshift($rows, [
            'code' => 'VND', 'name' => 'Vietnamese Dong (base)',
            'rate' => 1.0, 'rate_date' => '1970-01-01',
        ]);
        return $rows;
    }

    //
    // Lấy tỷ giá 1 ngoại tệ so với VND
    // Output: ['code' => 'USD', 'rate' => 25480.0, 'rate_date' => '2026-05-19'] | null
    //
    public function getRate(string $currencyCode, ?string $date = null): ?array
    {
        $code = strtoupper($currencyCode);
        if ($code === 'VND') {
            return ['code' => 'VND', 'rate' => 1.0, 'rate_date' => '1970-01-01'];
        }
        $sql = "SELECT currency_code AS code, rate, rate_date FROM exchange_rates
                WHERE currency_code = ?";
        $params = [$code];
        if ($date) {
            $sql .= " AND rate_date <= ? ORDER BY rate_date DESC LIMIT 1";
            $params[] = $date;
        } else {
            $sql .= " ORDER BY rate_date DESC LIMIT 1";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    //
    // Quy đổi 1 số tiền từ VND sang currency khác
    //   $amountVnd = 1,000,000 VND → USD = 1,000,000 / 25,480 = 39.25 USD
    //
    // Output: ['amount' => 39.25, 'currency' => 'USD', 'rate' => 25480.0] | null
    //
    public function convertFromVnd(float $amountVnd, string $toCurrency, ?string $date = null): ?array
    {
        $to = strtoupper($toCurrency);
        if ($to === 'VND') {
            return ['amount' => $amountVnd, 'currency' => 'VND', 'rate' => 1.0];
        }
        $rate = $this->getRate($to, $date);
        if (!$rate) {
            return null;
        }
        return [
            'amount' => round($amountVnd / (float)$rate['rate'], 4),
            'currency' => $to,
            'rate' => (float)$rate['rate'],
            'rate_date' => $rate['rate_date'],
        ];
    }

    //
    // Quy đổi ngược: từ ngoại tệ → VND
    //   100 USD → 100 * 25,480 = 2,548,000 VND
    //
    public function convertToVnd(float $amount, string $fromCurrency, ?string $date = null): ?array
    {
        $from = strtoupper($fromCurrency);
        if ($from === 'VND') {
            return ['amount' => $amount, 'currency' => 'VND', 'rate' => 1.0];
        }
        $rate = $this->getRate($from, $date);
        if (!$rate) {
            return null;
        }
        return [
            'amount' => round($amount * (float)$rate['rate'], 2),
            'currency' => 'VND',
            'rate' => (float)$rate['rate'],
            'rate_date' => $rate['rate_date'],
        ];
    }

    //
    // Lấy display_currency của user (mặc định VND)
    //
    public function getUserDisplayCurrency(string $userId): string
    {
        $stmt = $this->pdo->prepare("SELECT display_currency FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $val = $stmt->fetchColumn();
        return $val ?: 'VND';
    }

    //
    // Set display_currency cho user
    // Throw exception nếu currency không tồn tại trong exchange_rates
    //
    public function setUserDisplayCurrency(string $userId, string $currency): void
    {
        $code = strtoupper($currency);
        if ($code !== 'VND' && !$this->getRate($code)) {
            throw new \InvalidArgumentException("Không tìm thấy tỷ giá cho {$code}");
        }
        $stmt = $this->pdo->prepare("UPDATE users SET display_currency = ? WHERE id = ?");
        $stmt->execute([$code, $userId]);
    }

    //
    // Format số tiền với ký hiệu tiền tệ
    // Output: "1,000,000 VND" hoặc "39.25 USD"
    //
    public function format(float $amount, string $currency, int $decimals = null): string
    {
        $code = strtoupper($currency);
        // JPY không có decimal, các loại khác dùng 2
        $dec = $decimals ?? (in_array($code, ['JPY', 'VND']) ? 0 : 2);
        return number_format($amount, $dec, '.', ',') . ' ' . $code;
    }

    //
    // Format song song: hiển thị VND + converted
    // Output: "1,000,000 VND (~39.25 USD)"
    //
    public function formatDual(float $amountVnd, string $displayCurrency, ?string $date = null): string
    {
        $primary = $this->format($amountVnd, 'VND');
        $code = strtoupper($displayCurrency);
        if ($code === 'VND') {
            return $primary;
        }
        $converted = $this->convertFromVnd($amountVnd, $code, $date);
        if (!$converted) {
            return $primary;
        }
        return $primary . ' (~' . $this->format($converted['amount'], $code) . ')';
    }
}
