<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\DepreciationPolicy;
use Accounting\Domain\Repository\DepreciationPolicyRepositoryInterface;

use \Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Chính sách Khấu hao (Depreciation Policy)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD chính sách khấu hao TSCĐ
 *   - Phương pháp khấu hao: đường thẳng (straight_line), số dư giảm dần, sản lượng
 *   - Thiết lập thời gian khấu hao mặc định và tỷ lệ giá trị thu hồi
 *
 * API endpoints:
 *   (Sử dụng CrudControllerTrait — CRUD chuẩn)
 *
 * Rủi ro:
 *   - Sai phương pháp khấu hao → sai chi phí hàng tháng → sai BC02
 *   - Thời gian khấu hao không phù hợp quy định TT 99 → bị kiểm toán từ chối
 *   - Thay đổi chính sách ảnh hưởng đến TSCĐ đã ghi nhận
 *
 * Tích hợp:
 *   - FixedAssetController gán depreciation_policy_id cho từng TSCĐ
 *   - FixedAssetService tính khấu hao dựa trên policy
 *   - Báo cáo khấu hao ảnh hưởng BC01 (214 - hao mòn) và BC02 (chi phí)
 */
class DepreciationPolicyController
{
    use CrudControllerTrait;

    private DepreciationPolicyRepositoryInterface $repo;
    public function __construct(DepreciationPolicyRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'dp_'; }

    protected function createEntity(array $data): object
    {
        return new DepreciationPolicy(
            $data['id'], $data['code'], $data['name'],
            $data['method'] ?? 'straight_line', (int)($data['default_life'] ?? 0),
            (float)($data['default_salvage_rate'] ?? 0)
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['method'])) $entity->setMethod($data['method']);
        if (isset($data['default_life'])) $entity->setDefaultLife((int)$data['default_life']);
        if (isset($data['default_salvage_rate'])) $entity->setDefaultSalvageRate((float)$data['default_salvage_rate']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}
