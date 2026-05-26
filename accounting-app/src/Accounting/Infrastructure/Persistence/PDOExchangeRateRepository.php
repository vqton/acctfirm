<?php
// Quản lý dữ liệu: tỷ giá ngoại tệ
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\ExchangeRate;
use Accounting\Domain\Repository\ExchangeRateRepositoryInterface;
use PDO;

class PDOExchangeRateRepository implements ExchangeRateRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?ExchangeRate
    {
        $stmt = $this->pdo->prepare('SELECT * FROM exchange_rates WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?ExchangeRate
    {
        $stmt = $this->pdo->prepare('SELECT * FROM exchange_rates WHERE currency_code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM exchange_rates ORDER BY currency_code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        return $items;
    }

    public function save(ExchangeRate $rate): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO exchange_rates (id, currency_code, currency_name, rate, rate_date, created_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE currency_code=VALUES(currency_code),
             currency_name=VALUES(currency_name), rate=VALUES(rate), rate_date=VALUES(rate_date)'
        );
        $stmt->execute([
            $rate->getId(), $rate->getCurrencyCode(), $rate->getCurrencyName(), $rate->getRate(),
            $rate->getRateDate(), $rate->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM exchange_rates WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): ExchangeRate
    {
        return new ExchangeRate(
            $row['id'], $row['currency_code'], $row['currency_name'],
            (float)$row['rate'], $row['rate_date']
        );
    }
}
