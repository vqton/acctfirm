<?php
namespace Accounting\Domain\Service;

//
// DỊCH VỤ CẤU HÌNH NGHIỆP VỤ — thay thế hardcoded constants
//
// Nguồn: bảng business_config (migration 091)
// Pattern: get(key, default) — default = giá trị hardcoded cũ
// Nguyên tắc: KHÔNG thay đổi behavior khi config chưa được seed
//
class ConfigService
{
    private \PDO $pdo;
    private array $cache = [];

    public function __construct(\PDO $pdo) { $this->pdo = $pdo; }

    public function get(string $key, mixed $default = null): mixed
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $stmt = $this->pdo->prepare(
            "SELECT config_value, config_type FROM business_config WHERE config_key = ? AND is_active = 1"
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) return $default;

        $val = $this->cast($row['config_value'], $row['config_type']);
        $this->cache[$key] = $val;
        return $val;
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int)$this->get($key, $default);
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        return (float)$this->get($key, $default);
    }

    public function getPercent(string $key, float $default = 0.0): float
    {
        return (float)$this->get($key, $default) / 100.0;
    }

    public function getString(string $key, string $default = ''): string
    {
        return (string)$this->get($key, $default);
    }

    public function getJson(string $key, array $default = []): array
    {
        $val = $this->get($key, $default);
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            return is_array($decoded) ? $decoded : $default;
        }
        return is_array($val) ? $val : $default;
    }

    public function clearCache(string $key = null): void
    {
        if ($key) unset($this->cache[$key]);
        else $this->cache = [];
    }

    private function cast(string $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int)$value,
            'decimal', 'percent' => (float)$value,
            'json' => json_decode($value, true) ?? $value,
            default => $value,
        };
    }
}
