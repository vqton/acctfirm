<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\TaxRate;
use Accounting\Domain\Repository\TaxRateRepositoryInterface;

use \Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Danh mục Thuế suất (Tax Rate)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD thuế suất: thuế GTGT (VAT), thuế TNCN, thuế TNDN
 *   - Quản lý các mức thuế: 0%, 5%, 8%, 10% (GTGT), các loại thuế khác
 *   - Phân loại theo tax_type (vat, income, withholding, etc.)
 *
 * API endpoints:
 *   (Sử dụng CrudControllerTrait — CRUD chuẩn)
 *
 * Rủi ro:
 *   - Sai thuế suất → sai số thuế phải nộp → phạt chậm nộp
 *   - Không cập nhật theo thay đổi chính sách thuế (Thông tư mới)
 *   - Sai phân loại thuế → kê khai sai tờ khai thuế GTGT
 *
 * Tích hợp:
 *   - ApController dùng tax_rate để tính VAT đầu vào (1331)
 *   - ArController dùng tax_rate để tính VAT đầu ra (3331)
 *   - Báo cáo thuế GTGT tổng hợp
 */
class TaxRateController
{
    use CrudControllerTrait;

    private TaxRateRepositoryInterface $repo;
    public function __construct(TaxRateRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'tax_'; }

    protected function createEntity(array $data): object
    {
        return new TaxRate(
            $data['id'], $data['code'], $data['name'],
            (float)($data['rate'] ?? 0), $data['tax_type'] ?? 'vat'
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

    // API: danh sách thuế suất VAT active — dùng cho dropdown chọn thuế suất trong form
    public function vatRates(): void
    {
        $all = $this->repo()->findAll();
        $vatRates = array_values(array_filter(
            array_map(fn($x) => $x->toArray(), $all),
            fn($r) => ($r['tax_type'] ?? '') === 'vat' && !empty($r['status'])
        ));
        usort($vatRates, fn($a, $b) => (float)$a['rate'] <=> (float)$b['rate']);
        JsonResponse::ok($vatRates);
    }
}
