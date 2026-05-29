<?php
namespace Accounting\Infrastructure\Persistence;

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

    public function findByFsMapping(string $fsMappingCode): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM accounts WHERE fs_mapping_code = ? ORDER BY code');
        $stmt->execute([$fsMappingCode]);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $items[] = $this->hydrate($row); }
        return $items;
    }

    public function findControlAccounts(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM accounts WHERE is_control = 1 ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $items[] = $this->hydrate($row); }
        return $items;
    }

    public function findLocked(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM accounts WHERE is_locked = 1 ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $items[] = $this->hydrate($row); }
        return $items;
    }

    public function findByType(string $type): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM accounts WHERE type = ? ORDER BY code');
        $stmt->execute([$type]);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $items[] = $this->hydrate($row); }
        return $items;
    }

    public function search(string $query): array
    {
        $like = '%' . $query . '%';
        $stmt = $this->pdo->prepare(
            'SELECT * FROM accounts WHERE code LIKE ? OR name LIKE ? ORDER BY code LIMIT 50'
        );
        $stmt->execute([$like, $like]);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $items[] = $this->hydrate($row); }
        return $items;
    }

    public function count(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn();
    }

    public function save(Account $a): void
    {
        $s = $this->pdo->prepare(
            'INSERT INTO accounts (id, code, name, type, parent_id, normal_balance, account_class,
                balance, description, status, is_control,
                fs_mapping_code, fs_mapping_type, is_locked, locked_by, locked_reason, locked_at,
                is_system, alternative_code, detail_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                code=VALUES(code), name=VALUES(name), type=VALUES(type),
                parent_id=VALUES(parent_id), normal_balance=VALUES(normal_balance),
                account_class=VALUES(account_class),
                balance=VALUES(balance), description=VALUES(description),
                status=VALUES(status), is_control=VALUES(is_control),
                fs_mapping_code=VALUES(fs_mapping_code),
                fs_mapping_type=VALUES(fs_mapping_type),
                is_locked=VALUES(is_locked), locked_by=VALUES(locked_by),
                locked_reason=VALUES(locked_reason), locked_at=VALUES(locked_at),
                is_system=VALUES(is_system),
                alternative_code=VALUES(alternative_code),
                detail_by=VALUES(detail_by)'
        );
        $s->execute([
            $a->getId(), $a->getCode(), $a->getName(), $a->getType(),
            $a->getParentId(), $a->getNormalBalance(), $a->getAccountClass(),
            $a->getBalance(), $a->getDescription(), $a->isStatus() ? 1 : 0,
            $a->isControl() ? 1 : 0,
            $a->getFsMappingCode(), $a->getFsMappingType(),
            $a->isLocked() ? 1 : 0, $a->getLockedBy(), $a->getLockedReason(), $a->getLockedAt(),
            $a->isSystem() ? 1 : 0, $a->getAlternativeCode(), $a->getDetailBy(),
            $a->getCreatedAt()->format('Y-m-d H:i:s')
        ]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM accounts WHERE id = ?')->execute([$id]);
    }

    private function hydrate(array $r): Account
    {
        $a = new Account(
            $r['id'], $r['code'], $r['name'], $r['type'],
            $r['parent_id'], $r['normal_balance'], $r['account_class'],
            $r['description'],
            $r['fs_mapping_code'] ?? null,
            $r['fs_mapping_type'] ?? null,
            $r['alternative_code'] ?? null,
            $r['detail_by'] ?? null
        );
        $a->setStatus((bool)($r['status'] ?? 1));
        $a->setControl((bool)($r['is_control'] ?? 0));
        $a->setBalance((float)($r['balance'] ?? 0));
        $a->setIsLocked((bool)($r['is_locked'] ?? 0));
        $a->setLockedBy($r['locked_by'] ?? null);
        $a->setLockedReason($r['locked_reason'] ?? null);
        $a->setLockedAt($r['locked_at'] ?? null);
        $a->setIsSystem((bool)($r['is_system'] ?? 0));

        // Preserve created_at from DB
        if (isset($r['created_at'])) {
            $prop = new \ReflectionProperty($a, 'createdAt');
            $prop->setAccessible(true);
            $prop->setValue($a, new \DateTimeImmutable($r['created_at']));
        }

        return $a;
    }
}
