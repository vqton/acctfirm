<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Model\Supplier;
use Accounting\Domain\Repository\SupplierRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Nhà cung cấp
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách nhà cung cấp
 *   - Theo dõi công nợ, lịch sử mua hàng
 *   - Liên kết với TK 331 (Phải trả người bán)
 *
 * API endpoints:
 *   GET    /api/suppliers — Danh sách
 *   POST   /api/suppliers — Tạo mới
 *   GET    /api/suppliers/{id} — Chi tiết
 *   PUT    /api/suppliers/{id} — Cập nhật
 *   DELETE /api/suppliers/{id} — Xoá
 *
 * Rủi ro:
 *   - R005: Xoá NCC đang có công nợ
 *
 * Tích hợp:
 *   - ApService theo dõi công nợ NCC
 *   - ApController xử lý giao dịch
 */
class SupplierController
{
    use CrudControllerTrait;

    /**
     * @param SupplierRepositoryInterface $repository
     */
    public function __construct(SupplierRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'suppliers';
    }

    protected function repo()
    {
        return $this->repository;
    }

    protected function idPrefix(): string
    {
        return 'sup_';
    }

    protected function createEntity(array $data): object
    {
        return new Supplier(
            id: $data['id'] ?? uniqid('sup_'),
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
