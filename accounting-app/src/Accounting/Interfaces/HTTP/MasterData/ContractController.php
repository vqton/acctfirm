<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\Contract;
use Accounting\Domain\Repository\ContractRepositoryInterface;

use \Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Danh mục Hợp đồng (Contract Master)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD hợp đồng kinh tế với khách hàng/nhà cung cấp
 *   - Quản lý thông tin: loại hợp đồng, đối tác, giá trị, thời hạn
 *   - Cơ sở để theo dõi doanh thu/chi phí theo hợp đồng
 *
 * API endpoints:
 *   (Sử dụng CrudControllerTrait — CRUD chuẩn)
 *
 * Rủi ro:
 *   - Hợp đồng hết hạn không được gia hạn → sai doanh thu dự kiến
 *   - Giá trị hợp đồng không đồng bộ với thực tế thanh toán
 *
 * Tích hợp:
 *   - ProjectController sử dụng contract_id (nếu có)
 *   - ArController/ApController ghi nhận hóa đơn theo hợp đồng
 *   - FsController cần thông tin hợp đồng cho thuyết minh BCTC
 */
class ContractController
{
    use CrudControllerTrait;

    private ContractRepositoryInterface $repo;
    public function __construct(ContractRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'ct_'; }
    protected function requiredFields(): array { return ['code', 'name', 'contract_type', 'party_id', 'party_name', 'contract_date']; }

    protected function createEntity(array $data): object
    {
        return new Contract(
            $data['id'], $data['code'], $data['name'], $data['contract_type'],
            $data['party_id'], $data['party_name'], $data['contract_date'],
            (float)($data['total_amount'] ?? 0), $data['currency'] ?? 'VND', $data['notes'] ?? null
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
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
        if (isset($data['notes'])) $entity->setNotes($data['notes']);
    }
}
