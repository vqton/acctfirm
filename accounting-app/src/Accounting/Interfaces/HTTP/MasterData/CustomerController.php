<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\Customer;
use Accounting\Domain\Repository\CustomerRepositoryInterface;

use \Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Danh mục Khách hàng (Customer Master)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD khách hàng: thông tin liên hệ, mã số thuế, địa chỉ
 *   - Quản lý điều khoản thanh toán (payment_terms) và hạn mức tín dụng
 *   - Cơ sở để quản lý công nợ phải thu (TK 131)
 *
 * API endpoints:
 *   (Sử dụng CrudControllerTrait — CRUD chuẩn)
 *
 * Rủi ro:
 *   - Trùng mã số thuế → nhầm lẫn giữa 2 khách hàng khác nhau
 *   - Sai hạn mức tín dụng → bán hàng vượt quá khả năng thanh toán
 *   - Khách hàng không còn hoạt động (status = inactive) vẫn có công nợ
 *
 * Tích hợp:
 *   - ArController tham chiếu customer_id để ghi nhận hóa đơn
 *   - Báo cáo AR aging dùng thông tin customer
 *   - ProjectController gán khách hàng cho dự án
 */
class CustomerController
{
    use CrudControllerTrait;

    private CustomerRepositoryInterface $repo;
    public function __construct(CustomerRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'cus_'; }

    protected function createEntity(array $data): object
    {
        return new Customer(
            $data['id'], $data['code'], $data['name'],
            $data['tax_code'] ?? null, $data['phone'] ?? null, $data['email'] ?? null,
            $data['address'] ?? null, $data['contact_person'] ?? null,
            $data['payment_terms'] ?? null, (float)($data['credit_limit'] ?? 0),
            $data['notes'] ?? null
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
