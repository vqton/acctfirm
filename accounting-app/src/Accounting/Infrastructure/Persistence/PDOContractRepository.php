<?php
// Quản lý dữ liệu: danh mục hợp đồng
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\Contract;
use Accounting\Domain\Repository\ContractRepositoryInterface;
use PDO;

class PDOContractRepository implements ContractRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?Contract
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contracts WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?Contract
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contracts WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM contracts ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        return $items;
    }

    public function save(Contract $contract): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO contracts (id, code, name, contract_type, party_id, party_name, contract_date, total_amount, currency, status, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name),
             contract_type=VALUES(contract_type), party_id=VALUES(party_id),
             party_name=VALUES(party_name), contract_date=VALUES(contract_date),
             total_amount=VALUES(total_amount), currency=VALUES(currency), status=VALUES(status),
             notes=VALUES(notes)'
        );
        $stmt->execute([
            $contract->getId(), $contract->getCode(), $contract->getName(),
            $contract->getContractType(), $contract->getPartyId(), $contract->getPartyName(),
            $contract->getContractDate(), $contract->getTotalAmount(), $contract->getCurrency(),
            $contract->isStatus() ? 1 : 0, $contract->getNotes(),
            $contract->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM contracts WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): Contract
    {
        $contract = new Contract(
            $row['id'], $row['code'], $row['name'], $row['contract_type'],
            $row['party_id'], $row['party_name'], $row['contract_date'],
            (float)$row['total_amount'], $row['currency'], $row['notes']
        );
        $contract->setStatus((bool)$row['status']);
        return $contract;
    }
}
