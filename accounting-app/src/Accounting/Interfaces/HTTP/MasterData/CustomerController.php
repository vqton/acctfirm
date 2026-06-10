<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Model\Customer;
use Accounting\Domain\Repository\CustomerRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Khách hàng
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách khách hàng
 *   - Theo dõi công nợ, hạn mức tín dụng, lịch sử giao dịch
 *   - Liên kết với TK 131 (Phải thu khách hàng)
 *
 * API endpoints:
 *   GET    /api/customers — Danh sách
 *   POST   /api/customers — Tạo mới
 *   GET    /api/customers/{id} — Chi tiết
 *   PUT    /api/customers/{id} — Cập nhật
 *   DELETE /api/customers/{id} — Xoá
 *
 * Rủi ro:
 *   - R005: Xoá khách hàng đang có công nợ
 *
 * Tích hợp:
 *   - ArService theo dõi công nợ
 *   - ArController xử lý giao dịch
 */
class CustomerController
{
    use CrudControllerTrait;

    /**
     * @param CustomerRepositoryInterface $repository
     */
    public function __construct(CustomerRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'customers';
    }

    protected function repo()
    {
        return $this->repository;
    }

    protected function idPrefix(): string
    {
        return 'cus_';
    }

    protected function createEntity(array $data): object
    {
        return new Customer(
            id: $data['id'] ?? uniqid('cus_'),
            code: $data['code'] ?? '',
            name: $data['name'] ?? '',
            taxCode: $data['tax_code'] ?? null,
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
            address: $data['address'] ?? null,
            contactPerson: $data['contact_person'] ?? null,
            paymentTerms: $data['payment_terms'] ?? null,
            creditLimit: (float)($data['credit_limit'] ?? 0),
            notes: $data['notes'] ?? null
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['tax_code'])) $entity->setTaxCode($data['tax_code']);
        if (isset($data['phone'])) $entity->setPhone($data['phone']);
        if (isset($data['email'])) $entity->setEmail($data['email']);
        if (isset($data['address'])) $entity->setAddress($data['address']);
        if (isset($data['contact_person'])) $entity->setContactPerson($data['contact_person']);
        if (isset($data['payment_terms'])) $entity->setPaymentTerms($data['payment_terms']);
        if (isset($data['credit_limit'])) $entity->setCreditLimit((float)$data['credit_limit']);
        if (isset($data['notes'])) $entity->setNotes($data['notes']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}
