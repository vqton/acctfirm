<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Model\Account;
use Accounting\Domain\Repository\AccountRepositoryInterface;

class AccountService
{
    private AccountRepositoryInterface $repo;
    private ?AuditLoggerInterface $auditLogger;

    public function __construct(
        AccountRepositoryInterface $repo,
        ?AuditLoggerInterface $auditLogger = null
    ) {
        $this->repo = $repo;
        $this->auditLogger = $auditLogger;
    }

    // ── CREATE ──
    public function create(
        string $code,
        string $name,
        string $type,
        ?string $parentId = null,
        string $normalBalance = 'D',
        ?string $accountClass = null,
        ?string $description = null,
        ?string $fsMappingCode = null,
        ?string $fsMappingType = null,
        ?string $alternativeCode = null,
        ?string $detailBy = null,
        ?string $id = null
    ): Account {
        if ($this->repo->findByCode($code)) {
            throw new \InvalidArgumentException("Mã tài khoản {$code} đã tồn tại trong hệ thống");
        }
        if ($parentId) {
            $parent = $this->repo->findByCode($parentId);
            if (!$parent) {
                throw new \InvalidArgumentException("Tài khoản cha {$parentId} không tồn tại");
            }
        }
        $normalBalance = strtoupper($normalBalance);
        if (!in_array($normalBalance, ['D', 'C'], true)) {
            throw new \InvalidArgumentException('normal_balance phải là D (dư Nợ) hoặc C (dư Có)');
        }
        $validTypes = ['asset', 'liability', 'equity', 'revenue', 'expense'];
        if (!in_array($type, $validTypes, true)) {
            throw new \InvalidArgumentException("Loại tài khoản {$type} không hợp lệ");
        }

        $a = new Account(
            $id ?? uniqid('coa_'), $code, $name, $type,
            $parentId, $normalBalance, $accountClass, $description,
            $fsMappingCode, $fsMappingType, $alternativeCode, $detailBy
        );
        $this->repo->save($a);
        $this->auditLogger?->log('account.create', 'account', $a->getId(), null, $a->toArray(), $_SERVER['PHP_AUTH_USER'] ?? 'system');
        return $a;
    }

    // ── UPDATE ──
    public function update(string $id, array $data): Account
    {
        $a = $this->repo->findById($id);
        if (!$a) {
            throw new \InvalidArgumentException('Không tìm thấy tài khoản');
        }
        $old = $a->toArray();

        if (isset($data['code'])) {
            $dup = $this->repo->findByCode($data['code']);
            if ($dup && $dup->getId() !== $id) {
                throw new \InvalidArgumentException("Mã tài khoản {$data['code']} đã tồn tại");
            }
            $a->setCode($data['code']);
        }
        if (isset($data['name'])) $a->setName($data['name']);
        if (isset($data['type'])) $a->setType($data['type']);
        if (array_key_exists('parent_id', $data)) $a->setParentId($data['parent_id']);
        if (isset($data['normal_balance'])) $a->setNormalBalance($data['normal_balance']);
        if (array_key_exists('account_class', $data)) $a->setAccountClass($data['account_class']);
        if (array_key_exists('description', $data)) $a->setDescription($data['description']);
        if (array_key_exists('status', $data)) $a->setStatus((bool)$data['status']);
        if (array_key_exists('fs_mapping_code', $data)) $a->setFsMappingCode($data['fs_mapping_code']);
        if (array_key_exists('fs_mapping_type', $data)) $a->setFsMappingType($data['fs_mapping_type']);
        if (array_key_exists('alternative_code', $data)) $a->setAlternativeCode($data['alternative_code']);
        if (array_key_exists('detail_by', $data)) $a->setDetailBy($data['detail_by']);

        $this->repo->save($a);
        $this->auditLogger?->log('account.update', 'account', $id, $old, $a->toArray(), $_SERVER['PHP_AUTH_USER'] ?? 'system');
        return $a;
    }

    // ── DELETE ──
    public function delete(string $id): void
    {
        $a = $this->repo->findById($id);
        if (!$a) throw new \InvalidArgumentException('Không tìm thấy tài khoản');
        if ($a->isSystem()) throw new \InvalidArgumentException('Không thể xóa tài khoản hệ thống');
        if ($a->getBalance() != 0) throw new \InvalidArgumentException('Không thể xóa tài khoản có số dư khác 0');

        // Check no children
        $all = $this->repo->findAll();
        foreach ($all as $other) {
            if ($other->getParentId() === $a->getId()) {
                throw new \InvalidArgumentException('Không thể xóa tài khoản có tài khoản con');
            }
        }

        $old = $a->toArray();
        $this->repo->delete($id);
        $this->auditLogger?->log('account.delete', 'account', $id, $old, null, $_SERVER['PHP_AUTH_USER'] ?? 'system');
    }

    // ── ACTIVATE ──
    public function activate(string $id): Account
    {
        $a = $this->repo->findById($id);
        if (!$a) throw new \InvalidArgumentException('Không tìm thấy tài khoản');
        if ($a->isStatus()) throw new \InvalidArgumentException('Tài khoản đã ở trạng thái hoạt động');

        // Q1 enforcement: FS mapping required for non-off-balance accounts
        if ($a->getAccountClass() !== '0' && !$a->getFsMappingCode()) {
            throw new \InvalidArgumentException('Vui lòng chọn chỉ tiêu BCTC trước khi kích hoạt');
        }

        $a->setStatus(true);
        $this->repo->save($a);
        $this->auditLogger?->log('account.activate', 'account', $id, ['status' => false], ['status' => true], $_SERVER['PHP_AUTH_USER'] ?? 'system');
        return $a;
    }

    // ── DEACTIVATE ──
    public function deactivate(string $id): Account
    {
        $a = $this->repo->findById($id);
        if (!$a) throw new \InvalidArgumentException('Không tìm thấy tài khoản');
        if ($a->isSystem()) throw new \InvalidArgumentException('Không thể vô hiệu hóa tài khoản hệ thống');
        if ($a->getBalance() != 0) throw new \InvalidArgumentException('Không thể vô hiệu hóa tài khoản có số dư khác 0');

        $a->setStatus(false);
        $this->repo->save($a);
        $this->auditLogger?->log('account.deactivate', 'account', $id, ['status' => true], ['status' => false], $_SERVER['PHP_AUTH_USER'] ?? 'system');
        return $a;
    }

    // ── LOCK ──
    public function lock(string $id, string $by, string $reason, bool $cfOverride = false): Account
    {
        $a = $this->repo->findById($id);
        if (!$a) throw new \InvalidArgumentException('Không tìm thấy tài khoản');
        if ($a->isLocked()) throw new \InvalidArgumentException('Tài khoản đã bị khóa');

        // Q3: balance != 0 requires CFO override
        if ($a->getBalance() != 0 && !$cfOverride) {
            throw new \InvalidArgumentException(
                'Tài khoản có số dư ' . number_format($a->getBalance()) . ' VND. Cần CFO duyệt để khóa.'
            );
        }

        $a->lock($by, $reason);
        $this->repo->save($a);
        $this->auditLogger?->log('account.lock', 'account', $id, ['is_locked' => false], $a->toArray(), $_SERVER['PHP_AUTH_USER'] ?? 'system');
        return $a;
    }

    // ── UNLOCK ──
    public function unlock(string $id): Account
    {
        $a = $this->repo->findById($id);
        if (!$a) throw new \InvalidArgumentException('Không tìm thấy tài khoản');
        if (!$a->isLocked()) throw new \InvalidArgumentException('Tài khoản chưa bị khóa');

        $a->unlock();
        $this->repo->save($a);
        $this->auditLogger?->log('account.unlock', 'account', $id, ['is_locked' => true], ['is_locked' => false], $_SERVER['PHP_AUTH_USER'] ?? 'system');
        return $a;
    }

    // ── QUERIES ──
    public function getTree(): array
    {
        $all = $this->repo->findAll();
        $map = [];
        $roots = [];
        foreach ($all as $a) {
            $node = $a->toArray();
            $node['children'] = [];
            $map[$a->getId()] = $node;
        }
        foreach ($map as &$node) {
            if ($node['parent_id'] && isset($map[$node['parent_id']])) {
                $map[$node['parent_id']]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }
        return $roots;
    }

    public function getByType(string $type): array
    {
        return $this->repo->findByType($type);
    }

    public function getControlAccounts(): array
    {
        return $this->repo->findControlAccounts();
    }

    public function search(string $query): array
    {
        return $this->repo->search($query);
    }

    public function count(): int
    {
        return $this->repo->count();
    }

    // ── SEED — upsert from JSON array ──
    public function seedFromArray(array $rows): array
    {
        $count = 0;
        $updateCount = 0;
        foreach ($rows as $row) {
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

        // Mark control accounts
        $parents = [];
        foreach ($rows as $row) {
            if (!empty($row[5])) $parents[$row[5]] = true;
        }
        foreach (array_keys($parents) as $code) {
            $a = $this->repo->findByCode($code);
            if ($a && !$a->isControl()) { $a->setControl(true); $this->repo->save($a); }
        }

        return ['new' => $count, 'updated' => $updateCount];
    }
}
