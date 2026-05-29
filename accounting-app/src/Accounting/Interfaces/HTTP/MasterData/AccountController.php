<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Service\AccountService;
use Accounting\Infrastructure\JsonResponse;

class AccountController
{
    private AccountService $accountService;

    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }

    public function list(): void
    {
        JsonResponse::ok($this->accountService->getTree());
    }

    public function flatList(): void
    {
        JsonResponse::ok(array_map(
            fn($a) => $a->toArray(),
            $this->accountService->search('')
        ));
    }

    public function get(string $id): void
    {
        try {
            $all = $this->accountService->search($id);
            $found = null;
            foreach ($all as $a) {
                if ($a->getId() === $id || $a->getCode() === $id) { $found = $a; break; }
            }
            if (!$found) { JsonResponse::error('Không tìm thấy tài khoản', 404); return; }
            JsonResponse::ok($found->toArray());
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage(), 400);
        }
    }

    public function create(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['code'], $data['name'], $data['type'])) {
            JsonResponse::error('Vui lòng nhập mã tài khoản, tên tài khoản và loại tài khoản', 400); return;
        }
        try {
            $a = $this->accountService->create(
                $data['code'], $data['name'], $data['type'],
                $data['parent_id'] ?? null, $data['normal_balance'] ?? 'D',
                $data['account_class'] ?? null, $data['description'] ?? null,
                $data['fs_mapping_code'] ?? null, $data['fs_mapping_type'] ?? null,
                $data['alternative_code'] ?? null, $data['detail_by'] ?? null,
                $data['id'] ?? null
            );
            JsonResponse::ok($a->toArray(), 201);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 409);
        }
    }

    public function update(string $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { JsonResponse::error('Dữ liệu không hợp lệ. Vui lòng kiểm tra lại.', 400); return; }
        try {
            $a = $this->accountService->update($id, $data);
            JsonResponse::ok($a->toArray());
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    public function delete(string $id): void
    {
        try {
            $this->accountService->delete($id);
            JsonResponse::ok(['message' => 'Đã xóa tài khoản thành công']);
        } catch (\InvalidArgumentException $e) {
            $code = str_contains($e->getMessage(), 'không tìm thấy') ? 404 : 403;
            JsonResponse::error($e->getMessage(), $code);
        }
    }

    public function activate(string $id): void
    {
        try {
            $a = $this->accountService->activate($id);
            JsonResponse::ok($a->toArray());
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function deactivate(string $id): void
    {
        try {
            $a = $this->accountService->deactivate($id);
            JsonResponse::ok($a->toArray());
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function lockAccount(string $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $a = $this->accountService->lock(
                $id,
                $data['locked_by'] ?? ($_SERVER['PHP_AUTH_USER'] ?? 'system'),
                $data['locked_reason'] ?? '',
                (bool)($data['cf_override'] ?? false)
            );
            JsonResponse::ok($a->toArray());
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function unlockAccount(string $id): void
    {
        try {
            $a = $this->accountService->unlock($id);
            JsonResponse::ok($a->toArray());
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    public function search(): void
    {
        $q = $_GET['q'] ?? '';
        $results = $this->accountService->search($q);
        JsonResponse::ok(array_map(fn($a) => $a->toArray(), $results));
    }

    public function byType(string $type): void
    {
        $results = $this->accountService->getByType($type);
        JsonResponse::ok(array_map(fn($a) => $a->toArray(), $results));
    }

    public function seed(): void
    {
        $path = __DIR__ . '/../../../../data/coa_circular_99.json';
        $coa = json_decode(file_get_contents($path), true);
        if (!$coa) { JsonResponse::error('Không tìm thấy file dữ liệu hệ thống tài khoản', 500); return; }
        $result = $this->accountService->seedFromArray($coa);
        JsonResponse::ok(['message' => 'Đã khởi tạo dữ liệu', 'new' => $result['new'], 'updated' => $result['updated']]);
    }

    // ── MERGE ──
    public function merge(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['source_codes'], $data['target_code'])) {
            JsonResponse::error('Vui lòng nhập tài khoản nguồn và tài khoản đích', 400); return;
        }
        try {
            $result = $this->accountService->mergeAccounts(
                $data['source_codes'],
                $data['target_code'],
                $data['approved_by'] ?? ($_SERVER['PHP_AUTH_USER'] ?? 'system'),
                $data['reason'] ?? 'Gộp tài khoản',
                (bool)($data['cf_override'] ?? false)
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    // ── SPLIT ──
    public function split(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['source_code'], $data['targets'])) {
            JsonResponse::error('Vui lòng nhập tài khoản nguồn và danh sách tài khoản đích', 400); return;
        }
        try {
            $result = $this->accountService->splitAccount(
                $data['source_code'],
                $data['targets'],
                $data['approved_by'] ?? ($_SERVER['PHP_AUTH_USER'] ?? 'system'),
                $data['reason'] ?? 'Tách tài khoản'
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }

    // ── FS REPORT ──
    public function fsReport(): void
    {
        JsonResponse::ok($this->accountService->getFsMappingReport());
    }

    // ── BRANCH COA ──
    public function branchCoa(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['entity_id'])) {
            JsonResponse::error('Vui lòng nhập ID đơn vị kế toán', 400); return;
        }
        try {
            $result = $this->accountService->createBranchCOA(
                (int)$data['entity_id'],
                $data['created_by'] ?? ($_SERVER['PHP_AUTH_USER'] ?? 'system')
            );
            JsonResponse::ok($result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 422);
        }
    }
}
