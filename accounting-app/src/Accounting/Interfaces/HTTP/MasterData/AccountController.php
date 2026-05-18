<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Model\Account;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Infrastructure\JsonResponse;

class AccountController
{
    private AccountRepositoryInterface $repo;
    private ?AuditLoggerInterface $auditLogger;

    public function __construct(AccountRepositoryInterface $repo, ?AuditLoggerInterface $auditLogger = null) { $this->repo = $repo; $this->auditLogger = $auditLogger; }

    public function list(): void { JsonResponse::ok(array_map(fn($x) => $x->toArray(), $this->repo->findAll())); }

    public function get(string $id): void
    {
        $x = $this->repo->findById($id);
        if (!$x) { JsonResponse::error('Not found', 404); return; }
        JsonResponse::ok($x->toArray());
    }

    public function create(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['code'], $data['name'], $data['type'])) {
            JsonResponse::error('code, name, type required', 400); return;
        }
        if ($this->repo->findByCode($data['code'])) {
            JsonResponse::error('Code already exists', 409); return;
        }
        $x = new Account(
            $data['id'] ?? uniqid('coa_'), $data['code'], $data['name'], $data['type'],
            $data['parent_id'] ?? null, $data['normal_balance'] ?? 'D',
            $data['account_class'] ?? null, $data['description'] ?? null
        );
        $this->repo->save($x);
        $this->auditLogger->log('account.create', 'account', $x->getId(), null, $x->toArray(), $_SERVER['PHP_AUTH_USER'] ?? 'system');
        JsonResponse::ok($x->toArray(), 201);
    }

    public function update(string $id): void
    {
        $x = $this->repo->findById($id);
        if (!$x) { JsonResponse::error('Not found', 404); return; }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { JsonResponse::error('Invalid data', 400); return; }
        $old = $x->toArray();
        if (isset($data['code'])) $x->setCode($data['code']);
        if (isset($data['name'])) $x->setName($data['name']);
        if (isset($data['type'])) $x->setType($data['type']);
        if (isset($data['parent_id'])) $x->setParentId($data['parent_id']);
        if (isset($data['normal_balance'])) $x->setNormalBalance($data['normal_balance']);
        if (isset($data['account_class'])) $x->setAccountClass($data['account_class']);
        if (isset($data['description'])) $x->setDescription($data['description']);
        if (isset($data['status'])) $x->setStatus((bool)$data['status']);
        $this->repo->save($x);
        $this->auditLogger->log('account.update', 'account', $id, $old, $x->toArray(), $_SERVER['PHP_AUTH_USER'] ?? 'system');
        JsonResponse::ok($x->toArray());
    }

    public function delete(string $id): void
    {
        $x = $this->repo->findById($id);
        if (!$x) { JsonResponse::error('Not found', 404); return; }
        $old = $x->toArray();
        $this->repo->delete($id);
        $this->auditLogger->log('account.delete', 'account', $id, $old, null, $_SERVER['PHP_AUTH_USER'] ?? 'system');
        JsonResponse::ok(['message' => 'Deleted']);
    }

    public function seed(): void
    {
        $path = __DIR__ . '/../../../../data/coa_circular_99.json';
        $coa = json_decode(file_get_contents($path), true);
        if (!$coa) { JsonResponse::error('COA data file not found', 500); return; }

        // Seeding: create new + update existing (handles renames)
        $count = 0;
        $updateCount = 0;
        foreach ($coa as $row) {
            $existing = $this->repo->findByCode($row[0]);
            if ($existing) {
                $changed = false;
                if ($existing->getName() !== $row[1]) { $existing->setName($row[1]); $changed = true; }
                if ($existing->getType() !== $row[2]) { $existing->setType($row[2]); $changed = true; }
                if ($existing->getAccountClass() !== $row[3]) { $existing->setAccountClass($row[3]); $changed = true; }
                if ($existing->getNormalBalance() !== $row[4]) { $existing->setNormalBalance($row[4]); $changed = true; }
                if (($existing->getParentId() ?: null) !== ($row[5] ?? null)) { $existing->setParentId($row[5] ?? null); $changed = true; }
                if ($changed) { $this->repo->save($existing); $updateCount++; }
                continue;
            }
            $a = new Account(uniqid('coa_'), $row[0], $row[1], $row[2],
                $row[5] ?? null, $row[4], $row[3]);
            $this->repo->save($a);
            $count++;
        }

        // Mark all Level 1 accounts that have sub-accounts as control accounts
        $parents = [];
        foreach ($coa as $row) {
            if (!empty($row[5])) $parents[$row[5]] = true;
        }
        foreach (array_keys($parents) as $code) {
            $a = $this->repo->findByCode($code);
            if ($a && !$a->isControl()) { $a->setControl(true); $this->repo->save($a); }
        }

        $this->auditLogger->log('account.seed', 'account', null, null, ['new' => $count, 'updated' => $updateCount], $_SERVER['PHP_AUTH_USER'] ?? 'system');
        JsonResponse::ok(['message' => 'Seeded', 'new' => $count, 'updated' => $updateCount]);
    }
}