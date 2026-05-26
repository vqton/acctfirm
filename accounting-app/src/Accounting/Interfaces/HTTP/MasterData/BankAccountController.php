<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\BankAccount;
use Accounting\Domain\Repository\BankAccountRepositoryInterface;

use \Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Danh mục Tài khoản Ngân hàng (Bank Account Master)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD tài khoản ngân hàng của doanh nghiệp
 *   - Quản lý thông tin: số tài khoản, ngân hàng, chủ tài khoản, chi nhánh
 *   - Theo dõi loại tiền tệ và số dư đầu kỳ
 *
 * API endpoints:
 *   (Sử dụng CrudControllerTrait — CRUD chuẩn)
 *   GET    /api/bank-accounts       — Danh sách
 *   GET    /api/bank-accounts/{id}  — Chi tiết
 *   POST   /api/bank-accounts       — Tạo mới
 *   PUT    /api/bank-accounts/{id}  — Cập nhật
 *   DELETE /api/bank-accounts/{id}  — Xóa
 *
 * Rủi ro:
 *   - Sai số tài khoản → chuyển tiền sai đích
 *   - Không đồng bộ với tài khoản kế toán (112) → sai số dư BC01
 *
 * Tích hợp:
 *   - BankReconciliationController dùng bank_account_code để đối chiếu
 *   - CashController tham chiếu khi ghi nhận giao dịch ngân hàng
 *   - Liên kết với account_code 112 (tiền gửi ngân hàng)
 */
class BankAccountController
{
    use CrudControllerTrait;

    private BankAccountRepositoryInterface $repo;
    public function __construct(BankAccountRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'ba_'; }
    protected function requiredFields(): array { return ['code', 'bank_name', 'account_number', 'account_holder']; }

    protected function createEntity(array $data): object
    {
        return new BankAccount(
            $data['id'], $data['code'], $data['bank_name'],
            $data['account_number'], $data['account_holder'], $data['branch'] ?? '',
            $data['currency'] ?? 'VND', (float)($data['opening_balance'] ?? 0)
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['bank_name'])) $entity->setBankName($data['bank_name']);
        if (isset($data['account_number'])) $entity->setAccountNumber($data['account_number']);
        if (isset($data['account_holder'])) $entity->setAccountHolder($data['account_holder']);
        if (isset($data['branch'])) $entity->setBranch($data['branch']);
        if (isset($data['currency'])) $entity->setCurrency($data['currency']);
        if (isset($data['opening_balance'])) $entity->setOpeningBalance((float)$data['opening_balance']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}
