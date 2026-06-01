<?php

declare(strict_types=1);

namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\SupplierPerformance;
use Accounting\Domain\Repository\SupplierPerformanceRepositoryInterface;

class PDOSupplierPerformanceRepository implements SupplierPerformanceRepositoryInterface
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(string $id): ?SupplierPerformance
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM supplier_performances WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findBySupplier(string $supplierId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM supplier_performances WHERE supplier_id = ?'
        );
        $stmt->execute([$supplierId]);

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM supplier_performances');

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function save(SupplierPerformance $performance): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO supplier_performances (id, supplier_id, period, on_time_rate, quality_reject_rate, price_competitiveness, overall_rating, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                supplier_id = VALUES(supplier_id),
                period = VALUES(period),
                on_time_rate = VALUES(on_time_rate),
                quality_reject_rate = VALUES(quality_reject_rate),
                price_competitiveness = VALUES(price_competitiveness),
                overall_rating = VALUES(overall_rating)'
        );

        $stmt->execute([
            $performance->getId(),
            $performance->getSupplierId(),
            $performance->getPeriod(),
            $performance->getOnTimeRate(),
            $performance->getQualityRejectRate(),
            $performance->getPriceCompetitiveness(),
            $performance->getOverallRating(),
            $performance->getCreatedAt(),
        ]);
    }

    private function hydrate(array $row): SupplierPerformance
    {
        return new SupplierPerformance(
            $row['id'],
            $row['supplier_id'],
            $row['period'],
            $row['on_time_rate'] !== null ? (float) $row['on_time_rate'] : null,
            $row['quality_reject_rate'] !== null ? (float) $row['quality_reject_rate'] : null,
            $row['price_competitiveness'] !== null ? (float) $row['price_competitiveness'] : null,
            $row['overall_rating'] !== null ? (float) $row['overall_rating'] : null,
            $row['created_at']
        );
    }
}
