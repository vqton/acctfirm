<?php
namespace Accounting\Infrastructure\Repository;

use Accounting\Domain\Model\Account;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use PDO;

class PDOAccountRepository implements AccountRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?Account
    {
        $stmt = $this->pdo->prepare('SELECT * FROM accounts WHERE id = ?');
        $stmt->execute([$id]);
        return ($row = $stmt->fetch(PDO::FETCH_ASSOC)) ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?Account
    {
        $stmt = $this->pdo->prepare('SELECT * FROM accounts WHERE code = ?');
        $stmt->execute([$code]);
        return ($row = $stmt->fetch(PDO::FETCH_ASSOC)) ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM accounts ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $items[] = $this->hydrate($row); }
        return $items;
    }

    public function save(Account $a): void
    {
        $s = $this->pdo->prepare(
            'INSERT INTO accounts (id, code, name, type, parent_id, normal_balance, account_class, balance, description, status, is_control, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name), type=VALUES(type),
             parent_id=VALUES(parent_id), normal_balance=VALUES(normal_balance), account_class=VALUES(account_class),
             balance=VALUES(balance), description=VALUES(description), status=VALUES(status), is_control=VALUES(is_control)'
        );
        $s->execute([$a->getId(), $a->getCode(), $a->getName(), $a->getType(),
            $a->getParentId(), $a->getNormalBalance(), $a->getAccountClass(),
            $a->getBalance(), $a->getDescription(), $a->isStatus() ? 1 : 0,
            $a->isControl() ? 1 : 0,
            $a->getCreatedAt()->format('Y-m-d H:i:s')]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM accounts WHERE id = ?')->execute([$id]);
    }

    private function hydrate(array $r): Account
    {
        $a = new Account($r['id'], $r['code'], $r['name'], $r['type'],
            $r['parent_id'], $r['normal_balance'], $r['account_class'], $r['description']);
        $a->setStatus((bool)$r['status']);
        $a->setControl((bool)($r['is_control'] ?? 0));
        $a->setBalance((float)$r['balance']);
        return $a;
    }
}