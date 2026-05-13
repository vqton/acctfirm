<?php
namespace Accounting\Infrastructure\Repository;

use Accounting\Domain\Model\Project;
use Accounting\Domain\Repository\ProjectRepositoryInterface;
use PDO;

class PDOProjectRepository implements ProjectRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?Project
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projects WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?Project
    {
        $stmt = $this->pdo->prepare('SELECT * FROM projects WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM projects ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        return $items;
    }

    public function save(Project $project): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO projects (id, code, name, customer_id, start_date, end_date, budget, status, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name),
             customer_id=VALUES(customer_id), start_date=VALUES(start_date),
             end_date=VALUES(end_date), budget=VALUES(budget), status=VALUES(status),
             notes=VALUES(notes)'
        );
        $stmt->execute([
            $project->getId(), $project->getCode(), $project->getName(), $project->getCustomerId(),
            $project->getStartDate(), $project->getEndDate(), $project->getBudget(),
            $project->isStatus() ? 1 : 0, $project->getNotes(),
            $project->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM projects WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): Project
    {
        $project = new Project(
            $row['id'], $row['code'], $row['name'], $row['customer_id'],
            $row['start_date'], $row['end_date'], (float)$row['budget'], $row['notes']
        );
        $project->setStatus((bool)$row['status']);
        return $project;
    }
}
