<?php
namespace Accounting\Infrastructure\Repository;

use Accounting\Domain\Model\TaxRate;
use Accounting\Domain\Repository\TaxRateRepositoryInterface;
use PDO;

class PDOTaxRateRepository implements TaxRateRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?TaxRate
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tax_rates WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?TaxRate
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tax_rates WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM tax_rates ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        return $items;
    }

    public function save(TaxRate $taxRate): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tax_rates (id, code, name, rate, tax_type, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name), rate=VALUES(rate),
             tax_type=VALUES(tax_type), status=VALUES(status)'
        );
        $stmt->execute([
            $taxRate->getId(), $taxRate->getCode(), $taxRate->getName(), $taxRate->getRate(),
            $taxRate->getTaxType(), $taxRate->isStatus() ? 1 : 0,
            $taxRate->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM tax_rates WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): TaxRate
    {
        $taxRate = new TaxRate(
            $row['id'], $row['code'], $row['name'],
            (float)$row['rate'], $row['tax_type']
        );
        $taxRate->setStatus((bool)$row['status']);
        return $taxRate;
    }
}
