<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Model\Contract;
use Accounting\Domain\Repository\ContractRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Hợp đồng (Master Data)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD danh sách hợp đồng mua/bán
 *   - Tích hợp với AP/AR để theo dõi thanh toán
 *
 * API endpoints:
 *   GET    /api/contracts — Danh sách
 *   POST   /api/contracts — Tạo mới
 *   GET    /api/contracts/{id} — Chi tiết
 *   PUT    /api/contracts/{id} — Cập nhật
 *   DELETE /api/contracts/{id} — Xoá
 *
 * Tích hợp:
 *   - ContractManagementController xử lý nghiệp vụ hợp đồng nâng cao
 */
class ContractController
{
    use CrudControllerTrait;

    /**
     * @param ContractRepositoryInterface $repository
     */
    public function __construct(ContractRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'contracts';
    }

    protected function repo()
    {
        return $this->repository;
    }

    protected function idPrefix(): string
    {
        return 'cnt_';
    }

    protected function createEntity(array $data): object
    {
        return new Contract(
            id: $data['id'] ?? uniqid('cnt_'),
            code: $data['code'] ?? '',
            name: $data['name'] ?? '',
            contractType: $data['contract_type'] ?? 'sale',
            partyId: $data['party_id'] ?? '',
            partyName: $data['party_name'] ?? '',
            contractDate: $data['contract_date'] ?? date('Y-m-d'),
            totalAmount: (float)($data['total_amount'] ?? 0),
            currency: $data['currency'] ?? 'VND',
            notes: $data['notes'] ?? null
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['contract_type'])) $entity->setContractType($data['contract_type']);
        if (isset($data['party_id'])) $entity->setPartyId($data['party_id']);
        if (isset($data['party_name'])) $entity->setPartyName($data['party_name']);
        if (isset($data['contract_date'])) $entity->setContractDate($data['contract_date']);
        if (isset($data['total_amount'])) $entity->setTotalAmount((float)$data['total_amount']);
        if (isset($data['currency'])) $entity->setCurrency($data['currency']);
        if (isset($data['notes'])) $entity->setNotes($data['notes']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}
