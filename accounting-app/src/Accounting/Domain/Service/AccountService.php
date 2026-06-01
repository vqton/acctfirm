<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Model\Account;
use Accounting\Domain\Repository\AccountRepositoryInterface;

class AccountService
{
    private AccountRepositoryInterface $repo;
    private ?AuditLoggerInterface $auditLogger;
    private ?JournalService $journalService;

    public function __construct(
        AccountRepositoryInterface $repo,
        ?AuditLoggerInterface $auditLogger = null,
        ?JournalService $journalService = null
    ) {
        $this->repo = $repo;
        $this->auditLogger = $auditLogger;
        $this->journalService = $journalService;
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
        if (!$a) throw new \InvalidArgumentException('Không tìm thấy tài khoản');
        $old = $a->toArray();

        if (isset($data['code'])) {
            $dup = $this->repo->findByCode($data['code']);
            if ($dup && $dup->getId() !== $id) throw new \InvalidArgumentException("Mã tài khoản {$data['code']} đã tồn tại");
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
        foreach ($this->repo->findAll() as $other) {
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
        if ($a->getBalance() != 0 && !$cfOverride) {
            throw new \InvalidArgumentException('Tài khoản có số dư ' . number_format($a->getBalance()) . ' VND. Cần CFO duyệt để khóa.');
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

    // ── MERGE (Q2) ──
    // Gộp nhiều tài khoản nguồn vào một tài khoản đích.
    // Approval: Kế toán trưởng (same-type), +CFO (cross-type)
    // Audit: Tạo bút toán chuyển số dư qua JournalService.
    public function mergeAccounts(array $sourceCodes, string $targetCode, string $approvedBy, string $reason, bool $cfOverride = false): array
    {
        if (count($sourceCodes) < 2) throw new \InvalidArgumentException('Cần ít nhất 2 tài khoản nguồn để gộp');

        $sources = [];
        foreach ($sourceCodes as $code) {
            $a = $this->repo->findByCode($code);
            if (!$a) throw new \InvalidArgumentException("Tài khoản nguồn {$code} không tồn tại");
            if (!$a->isStatus()) throw new \InvalidArgumentException("Tài khoản nguồn {$code} đang ngừng hoạt động");
            $sources[] = $a;
        }

        $target = $this->repo->findByCode($targetCode);
        if (!$target) throw new \InvalidArgumentException("Tài khoản đích {$targetCode} không tồn tại");

        // Validate same type
        $sourceType = $sources[0]->getType();
        foreach ($sources as $s) {
            if ($s->getType() !== $sourceType) throw new \InvalidArgumentException('Các tài khoản nguồn phải cùng loại');
        }

        // Cross-type check: source vs target
        $crossType = $sourceType !== $target->getType();
        if ($crossType && !$cfOverride) {
            throw new \InvalidArgumentException('Gộp khác loại tài khoản cần CFO duyệt');
        }

        // Validate no source has children
        $all = $this->repo->findAll();
        foreach ($sources as $s) {
            foreach ($all as $other) {
                if ($other->getParentId() === $s->getId()) {
                    throw new \InvalidArgumentException("Tài khoản {$s->getCode()} có tài khoản con — không thể gộp");
                }
            }
        }

        // Compute total balance
        $totalBalance = 0.0;
        $sourceBalances = [];
        foreach ($sources as $s) {
            $sourceBalances[$s->getCode()] = $s->getBalance();
            $totalBalance += $s->getBalance();
        }

        // Create transfer journal
        if ($this->journalService && $totalBalance != 0) {
            $lines = [];
            foreach ($sources as $s) {
                $lines[] = ['account_code' => $s->getCode(), 'amount' => abs($s->getBalance()), 'is_debit' => $s->getBalance() > 0];
            }
            // Target gets opposite side
            $lines[] = ['account_code' => $targetCode, 'amount' => abs($totalBalance), 'is_debit' => $totalBalance < 0];

            $txnRef = $this->journalService->postEntry(
                'Gộp tài khoản: ' . implode(', ', $sourceCodes) . ' → ' . $targetCode,
                '',
                $lines,
                $approvedBy,
                true, // allowControl
                'coa_merge'
            );
            $reference = $txnRef->getReference();
        } else {
            $reference = null;
        }

        // Deactivate sources, update target
        foreach ($sources as $s) {
            $s->setBalance(0);
            $s->setStatus(false);
            $this->repo->save($s);
        }

        $this->repo->save($target);

        $result = [
            'type' => 'merge',
            'source_codes' => $sourceCodes,
            'target_code' => $targetCode,
            'source_balances' => $sourceBalances,
            'total_balance' => $totalBalance,
            'transfer_reference' => $reference,
            'cross_type' => $crossType,
            'approved_by' => $approvedBy,
            'reason' => $reason,
        ];

        $this->auditLogger?->log('account.merge', 'account', $target->getId(), null, $result, $approvedBy);
        return $result;
    }

    // ── SPLIT (Q2) ──
    // Chia số dư tài khoản nguồn cho nhiều tài khoản đích.
    public function splitAccount(string $sourceCode, array $targets, string $approvedBy, string $reason): array
    {
        $source = $this->repo->findByCode($sourceCode);
        if (!$source) throw new \InvalidArgumentException("Tài khoản nguồn {$sourceCode} không tồn tại");
        if (!$source->isStatus()) throw new \InvalidArgumentException('Tài khoản nguồn đang ngừng hoạt động');

        $sourceBalance = $source->getBalance();

        // Validate targets
        $totalTarget = 0.0;
        $validTargets = [];
        foreach ($targets as $t) {
            $a = $this->repo->findByCode($t['code']);
            if (!$a) throw new \InvalidArgumentException("Tài khoản đích {$t['code']} không tồn tại");
            if ($a->getType() !== $source->getType()) throw new \InvalidArgumentException('Tài khoản đích phải cùng loại với tài khoản nguồn');
            $validTargets[] = ['account' => $a, 'amount' => (float)$t['amount']];
            $totalTarget += (float)$t['amount'];
        }

        if (abs($totalTarget - $sourceBalance) > 10) {
            throw new \InvalidArgumentException(
                'Tổng số dư các tài khoản đích (' . number_format($totalTarget) .
                ') không khớp với số dư tài khoản nguồn (' . number_format($sourceBalance) . ')'
            );
        }

        // Create transfer journal
        if ($this->journalService && $sourceBalance != 0) {
            $lines = [];
            $lines[] = ['account_code' => $sourceCode, 'amount' => abs($sourceBalance), 'is_debit' => $sourceBalance < 0];
            foreach ($validTargets as $vt) {
                $lines[] = ['account_code' => $vt['account']->getCode(), 'amount' => abs($vt['amount']), 'is_debit' => $vt['amount'] > 0];
            }

            $txnRef = $this->journalService->postEntry(
                'Tách tài khoản: ' . $sourceCode . ' → ' . implode(', ', array_column($targets, 'code')),
                '',
                $lines,
                $approvedBy,
                true,
                'coa_split'
            );
        }

        // Zero out source, update targets
        $source->setBalance(0);
        $source->setStatus(false);
        $this->repo->save($source);

        foreach ($validTargets as $vt) {
            $this->repo->save($vt['account']);
        }

        $result = [
            'type' => 'split',
            'source_code' => $sourceCode,
            'targets' => $targets,
            'total_balance' => $sourceBalance,
            'approved_by' => $approvedBy,
            'reason' => $reason,
        ];

        $this->auditLogger?->log('account.split', 'account', $source->getId(), null, $result, $approvedBy);
        return $result;
    }

    // ── BRANCH COA (Q4) ──
    // Tự động sao chép danh mục tài khoản cho chi nhánh mới.
    // Loại trừ: TK vốn CSH (4xx), TK 911, TK IC (136, 336)
    public function createBranchCOA(int $entityId, string $createdBy): array
    {
        $enterpriseAccounts = $this->repo->findAll();
        $excludedClasses = ['4']; // Equity — không copy cho branch
        $excludedCodes = ['911', '136', '336'];

        $copied = 0;
        $skipped = 0;
        foreach ($enterpriseAccounts as $src) {
            // Skip excluded
            if (in_array($src->getAccountClass(), $excludedClasses)) { $skipped++; continue; }
            if (in_array($src->getCode(), $excludedCodes)) { $skipped++; continue; }
            // Skip IC sub-accounts
            $isIC = false;
            foreach ($excludedCodes as $exc) {
                if (str_starts_with($src->getCode(), $exc)) { $isIC = true; break; }
            }
            if ($isIC) { $skipped++; continue; }

            // Create branch copy
            $suffix = ' (CN' . $entityId . ')';
            $name = $src->getName();
            if (mb_strlen($name . $suffix) > 100) {
                $name = mb_substr($name, 0, 100 - mb_strlen($suffix));
            }
            $branchAccount = new Account(
                uniqid('coa_'),
                $src->getCode(),
                $name . $suffix,
                $src->getType(),
                null, $src->getNormalBalance(), $src->getAccountClass(),
                $src->getDescription(),
                $src->getFsMappingCode(), $src->getFsMappingType(),
                $src->getAlternativeCode(), $src->getDetailBy()
            );
            $branchAccount->setStatus(true);
            $branchAccount->setControl(false);
            $this->repo->save($branchAccount);
            $copied++;
        }

        $result = ['copied' => $copied, 'skipped' => $skipped, 'entity_id' => $entityId];
        $this->auditLogger?->log('account.branch_coa', 'account', null, null, $result, $createdBy);
        return $result;
    }

    // ── FS REPORTING ──
    // Báo cáo ánh xạ BCTC: tài khoản nào đã có/chưa có chỉ tiêu
    public function getFsMappingReport(): array
    {
        $all = $this->repo->findAll();
        $mapped = 0; $unmapped = 0;
        $byType = [];
        $unmappedList = [];

        foreach ($all as $a) {
            if (!$a->isStatus()) continue; // Only active accounts
            if ($a->getAccountClass() === '0') continue; // Off-balance

            if ($a->getFsMappingCode()) {
                $mapped++;
                $type = $a->getFsMappingType() ?? 'unknown';
                $byType[$type][] = $a->getCode();
            } else {
                $unmapped++;
                $unmappedList[] = ['code' => $a->getCode(), 'name' => $a->getName(), 'type' => $a->getType()];
            }
        }

        return [
            'total_active' => $mapped + $unmapped,
            'mapped' => $mapped,
            'unmapped' => $unmapped,
            'completeness_pct' => ($mapped + $unmapped) > 0
                ? round($mapped / ($mapped + $unmapped) * 100, 1) : 0,
            'by_type' => $byType,
            'unmapped_accounts' => $unmappedList,
        ];
    }

    // Kiểm tra FS mapping trước khi đóng kỳ — trả về danh sách tài khoản chưa mapping
    public function validateFsForPeriodClose(): array
    {
        $report = $this->getFsMappingReport();
        if ($report['unmapped'] > 0) {
            throw new \InvalidArgumentException(
                'Còn ' . $report['unmapped'] . ' tài khoản đang hoạt động chưa có ánh xạ BCTC. ' .
                'Vui lòng cập nhật trước khi đóng kỳ. Tài khoản: ' .
                implode(', ', array_column($report['unmapped_accounts'], 'code'))
            );
        }
        return $report;
    }

    // ── QUERIES ──
    public function getTree(): array
    {
        $all = $this->repo->findAll();
        $map = []; $roots = [];
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

    public function getByType(string $type): array { return $this->repo->findByType($type); }
    public function getControlAccounts(): array { return $this->repo->findControlAccounts(); }
    public function search(string $query): array { return $this->repo->search($query); }
    public function count(): int { return $this->repo->count(); }

    // ── SEED ──
    public function seedFromArray(array $rows): array
    {
        $count = 0; $updateCount = 0;
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
            $a = new Account(uniqid('coa_'), $row[0], $row[1], $row[2], $row[5] ?? null, $row[4], $row[3]);
            $this->repo->save($a);
            $count++;
        }
        $parents = [];
        foreach ($rows as $row) { if (!empty($row[5])) $parents[$row[5]] = true; }
        foreach (array_keys($parents) as $code) {
            $a = $this->repo->findByCode($code);
            if ($a && !$a->isControl()) { $a->setControl(true); $this->repo->save($a); }
        }
        return ['new' => $count, 'updated' => $updateCount];
    }
}
