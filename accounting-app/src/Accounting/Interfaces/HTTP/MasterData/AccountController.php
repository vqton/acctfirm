<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Model\Account;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Danh mục Hệ thống Tài khoản (Chart of Accounts)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD tài khoản kế toán theo Circular 99/2025/TT-BTC
 *   - Quản lý cấu trúc tài khoản: tài khoản tổng hợp (control) và tài khoản chi tiết
 *   - Xác định tính chất: normal_balance (D/N), account_class, parent
 *   - Kiểm soát tài khoản tổng hợp (control account) — không cho post trực tiếp
 *   - Audit trail cho mọi thay đổi tài khoản
 *
 * API endpoints:
 *   GET    /api/accounts       — Danh sách tài khoản
 *   GET    /api/accounts/{id}  — Chi tiết tài khoản
 *   POST   /api/accounts       — Tạo tài khoản mới
 *   PUT    /api/accounts/{id}  — Cập nhật tài khoản
 *   DELETE /api/accounts/{id}  — Xóa tài khoản (chỉ khi chưa phát sinh)
 *   GET    /api/accounts/tree  — Cây tài khoản phân cấp
 *
 * Rủi ro:
 *   - R005 (HIGH): Sai tài khoản → sai BC01/BC02/BC03
 *   - Xóa tài khoản đã phát sinh → mất dữ liệu lịch sử
 *   - Tài khoản tổng hợp không có tài khoản con → không post được
 *   - Trùng mã tài khoản → nhầm lẫn trong hạch toán
 *
 * Tích hợp:
 *   - AccountRepository được mọi service (JournalService, CashService, ...) dùng
 *   - PostingRuleService kiểm tra posting rules dựa trên account_code
 *   - Control account check trong PostingRuleService
 */
class AccountController
{
    private AccountRepositoryInterface $repo;
    private ?AuditLoggerInterface $auditLogger;

    public function __construct(AccountRepositoryInterface $repo, ?AuditLoggerInterface $auditLogger = null) { $this->repo = $repo; $this->auditLogger = $auditLogger; }

    public function list(): void { JsonResponse::ok(array_map(fn($x) => $x->toArray(), $this->repo->findAll())); }

    public function get(string $id): void
    {
        $x = $this->repo->findById($id);
        if (!$x) { JsonResponse::error('Không tìm thấy tài khoản', 404); return; }
        JsonResponse::ok($x->toArray());
    }

    public function create(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['code'], $data['name'], $data['type'])) {
            JsonResponse::error('Vui lòng nhập mã tài khoản, tên tài khoản và loại tài khoản', 400); return;
        }
        if ($this->repo->findByCode($data['code'])) {
            JsonResponse::error('Mã tài khoản đã tồn tại trong hệ thống', 409); return;
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
        if (!$x) { JsonResponse::error('Không tìm thấy tài khoản', 404); return; }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { JsonResponse::error('Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.', 400); return; }
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
        if (!$x) { JsonResponse::error('Không tìm thấy tài khoản', 404); return; }
        $old = $x->toArray();
        $this->repo->delete($id);
        $this->auditLogger->log('account.delete', 'account', $id, $old, null, $_SERVER['PHP_AUTH_USER'] ?? 'system');
        JsonResponse::ok(['message' => 'Đã xóa tài khoản thành công']);
    }

    // NGHIỆP VỤ: Seed (khởi tạo/cập nhật) toàn bộ Hệ thống Tài khoản Circular 99
    // Input: none (đọc từ data/coa_circular_99.json)
    // Output: { message: 'Seeded', new: N, updated: M }
    // Service: Tự gọi AccountRepository — không qua JournalService (đây là master data)
    // Permission: Không check CSRF (có thể gọi nội bộ)
    // Quy trình: (1) Đọc JSON → (2) Upsert từng tài khoản (thêm mới/cập nhật tên nếu thay đổi)
    // (3) Đánh dấu control account cho TK tổng hợp (có TK con)
    // Rủi ro: R005 — Thay đổi tên/cấu trúc TK đã phát sinh → sai BC lịch sử. Chạy lại không ảnh hưởng dữ liệu cũ
    // Audit trail: Ghi log 'account.seed' với số lượng thêm mới và cập nhật
    // Idempotent: Chạy nhiều lần không gây lỗi (upsert = findByCode + save nếu có thay đổi)
    public function seed(): void
    {
        $path = __DIR__ . '/../../../../data/coa_circular_99.json';
        $coa = json_decode(file_get_contents($path), true);
        if (!$coa) { JsonResponse::error('Không tìm thấy file dữ liệu hệ thống tài khoản', 500); return; }

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
        JsonResponse::ok(['message' => 'Đã khởi tạo dữ liệu', 'new' => $count, 'updated' => $updateCount]);
    }
}