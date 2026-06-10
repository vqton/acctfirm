<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Model\BankAccount;
use Accounting\Domain\Repository\BankAccountRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Tài khoản Ngân hàng
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách tài khoản ngân hàng của doanh nghiệp
 *   - Liên kết với TK 112 (Tiền gửi ngân hàng)
 *
 * API endpoints:
 *   GET    /api/bank-accounts — Danh sách
 *   POST   /api/bank-accounts — Tạo mới
 *   GET    /api/bank-accounts/{id} — Chi tiết
 *   PUT    /api/bank-accounts/{id} — Cập nhật
 *   DELETE /api/bank-accounts/{id} — Xoá
 *
 * Tích hợp:
 *   - CashService dùng để ghi nhận giao dịch ngân hàng
 */
class BankAccountController
{
    use CrudControllerTrait;

    /**
     * @param BankAccountRepositoryInterface $repository
     */
    public function __construct(BankAccountRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'bank_accounts';
    }

    protected function repo()
    {
        return $this->repository;
    }

    protected function idPrefix(): string
    {
        return 'ba_';
    }

    protected function createEntity(array $data): object
    {
        return new BankAccount(
            id: $data['id'] ?? uniqid('ba_'),
            code: $data['code'] ?? '',
            bankName: $data['bank_name'] ?? '',
            accountNumber: $data['account_number'] ?? '',
            accountHolder: $data['account_holder'] ?? '',
            branch: $data['branch'] ?? '',
            currency: $data['currency'] ?? 'VND',
            openingBalance: (float)($data['opening_balance'] ?? 0)
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
