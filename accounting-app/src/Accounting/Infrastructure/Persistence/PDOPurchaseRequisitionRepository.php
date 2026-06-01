<?php

declare(strict_types=1);

namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\PurchaseRequisition;
use Accounting\Domain\Repository\PurchaseRequisitionRepositoryInterface;

class PDOPurchaseRequisitionRepository implements PurchaseRequisitionRepositoryInterface
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(string $id): ?PurchaseRequisition
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM purchase_requisitions WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findOneByPrNumber(string $prNumber): ?PurchaseRequisition
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM purchase_requisitions WHERE pr_number = ?'
        );
        $stmt->execute([$prNumber]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByStatus(string $status): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM purchase_requisitions WHERE status = ?'
        );
        $stmt->execute([$status]);

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM purchase_requisitions');

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function save(PurchaseRequisition $requisition): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO purchase_requisitions (id, pr_number, status, requester_id, department_id, project_id, total_estimated, delivery_date, note, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                pr_number = VALUES(pr_number),
                status = VALUES(status),
                requester_id = VALUES(requester_id),
                department_id = VALUES(department_id),
                project_id = VALUES(project_id),
                total_estimated = VALUES(total_estimated),
                delivery_date = VALUES(delivery_date),
                note = VALUES(note),
                updated_at = VALUES(updated_at)'
        );

        $stmt->execute([
            $requisition->getId(),
            $requisition->getPrNumber(),
            $requisition->getStatus(),
            $requisition->getRequesterId(),
            $requisition->getDepartmentId(),
            $requisition->getProjectId(),
            $requisition->getTotalEstimated(),
            $requisition->getDeliveryDate(),
            $requisition->getNote(),
            $requisition->getCreatedAt(),
            $requisition->getUpdatedAt(),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM purchase_requisitions WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): PurchaseRequisition
    {
        return new PurchaseRequisition(
            $row['id'],
            $row['pr_number'],
            $row['status'],
            $row['requester_id'],
            $row['department_id'],
            $row['project_id'],
            $row['total_estimated'] !== null ? (float) $row['total_estimated'] : null,
            $row['delivery_date'],
            $row['note'],
            $row['created_at'],
            $row['updated_at']
        );
    }
}
