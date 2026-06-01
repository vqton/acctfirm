<?php

declare(strict_types=1);

namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\PurchaseApproval;
use Accounting\Domain\Repository\PurchaseApprovalRepositoryInterface;

class PDOPurchaseApprovalRepository implements PurchaseApprovalRepositoryInterface
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(string $id): ?PurchaseApproval
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM purchase_approvals WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByDoc(string $docType, string $docId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM purchase_approvals WHERE doc_type = ? AND doc_id = ?'
        );
        $stmt->execute([$docType, $docId]);

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function findByApprover(string $approverId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM purchase_approvals WHERE approver_id = ?'
        );
        $stmt->execute([$approverId]);

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function findByStatus(string $status): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM purchase_approvals WHERE status = ?'
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
        $stmt = $this->pdo->query('SELECT * FROM purchase_approvals');

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function save(PurchaseApproval $approval): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO purchase_approvals (id, doc_type, doc_id, step, approver_id, status, note, acted_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                doc_type = VALUES(doc_type),
                doc_id = VALUES(doc_id),
                step = VALUES(step),
                approver_id = VALUES(approver_id),
                status = VALUES(status),
                note = VALUES(note),
                acted_at = VALUES(acted_at)'
        );

        $stmt->execute([
            $approval->getId(),
            $approval->getDocType(),
            $approval->getDocId(),
            $approval->getStep(),
            $approval->getApproverId(),
            $approval->getStatus(),
            $approval->getNote(),
            $approval->getActedAt(),
            $approval->getCreatedAt(),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM purchase_approvals WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): PurchaseApproval
    {
        return new PurchaseApproval(
            $row['id'],
            $row['doc_type'],
            $row['doc_id'],
            (int) $row['step'],
            $row['approver_id'],
            $row['status'],
            $row['note'],
            $row['acted_at'],
            $row['created_at']
        );
    }
}
