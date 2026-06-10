<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Model\TaxRate;
use Accounting\Domain\Repository\TaxRateRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Thuế suất
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách thuế suất (GTGT, TNCN, TNDN...)
 *   - Mỗi thuế suất gắn với account code tương ứng
 *
 * API endpoints:
 *   GET    /api/tax-rates — Danh sách
 *   POST   /api/tax-rates — Tạo mới
 *   GET    /api/tax-rates/{id} — Chi tiết
 *   PUT    /api/tax-rates/{id} — Cập nhật
 *   DELETE /api/tax-rates/{id} — Xoá
 *
 * Tích hợp:
 *   - VatController dùng để tính thuế
 *   - FsController dùng để lập BC
 */
class TaxRateController
{
    use CrudControllerTrait;

    /**
     * @param TaxRateRepositoryInterface $repository
     */
    public function __construct(TaxRateRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'tax_rates';
    }

    protected function repo()
    {
        return $this->repository;
    }

    protected function idPrefix(): string
    {
        return 'tax_';
    }

    protected function createEntity(array $data): object
    {
        return new TaxRate(
            id: $data['id'] ?? uniqid('tax_'),
            code: $data['code'] ?? '',
            name: $data['name'] ?? '',
            rate: (float)($data['rate'] ?? 0),
            taxType: $data['tax_type'] ?? 'vat'
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['rate'])) $entity->setRate((float)$data['rate']);
        if (isset($data['tax_type'])) $entity->setTaxType($data['tax_type']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}
