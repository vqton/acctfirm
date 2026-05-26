<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Domain\Model\ExchangeRate;
use Accounting\Domain\Repository\ExchangeRateRepositoryInterface;

use \Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Tỷ giá Ngoại tệ (Exchange Rate)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD tỷ giá ngoại tệ theo ngày
 *   - Cập nhật tỷ giá mua/bán cho từng loại ngoại tệ
 *   - Cơ sở để quy đổi giao dịch ngoại tệ và đánh giá lại cuối kỳ
 *
 * API endpoints:
 *   (Sử dụng CrudControllerTrait — CRUD chuẩn)
 *
 * Rủi ro:
 *   - Sai tỷ giá → sai giá trị quy đổi → sai BC01/BC02
 *   - Tỷ giá không được cập nhật kịp thời → đánh giá lại sai
 *   - Chênh lệch tỷ giá cuối kỳ cần hạch toán vào 515 (lãi) hoặc 635 (lỗ)
 *
 * Tích hợp:
 *   - FxController dùng tỷ giá để đánh giá lại cuối kỳ
 *   - CashController dùng khi ghi nhận giao dịch ngoại tệ
 *   - Báo cáo tài chính cần quy đổi ngoại tệ theo tỷ giá cuối kỳ
 */
class ExchangeRateController
{
    use CrudControllerTrait;

    private ExchangeRateRepositoryInterface $repo;
    public function __construct(ExchangeRateRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'exr_'; }
    protected function codeField(): string { return 'currency_code'; }
    protected function requiredFields(): array { return ['currency_code', 'currency_name', 'rate', 'rate_date']; }

    protected function createEntity(array $data): object
    {
        return new ExchangeRate(
            $data['id'], $data['currency_code'], $data['currency_name'],
            (float)$data['rate'], $data['rate_date']
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['currency_code'])) $entity->setCurrencyCode($data['currency_code']);
        if (isset($data['currency_name'])) $entity->setCurrencyName($data['currency_name']);
        if (isset($data['rate'])) $entity->setRate((float)$data['rate']);
        if (isset($data['rate_date'])) $entity->setRateDate($data['rate_date']);
    }
}
