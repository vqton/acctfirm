<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
use Accounting\Domain\Model\ExchangeRate;
use Accounting\Domain\Repository\ExchangeRateRepositoryInterface;
use Accounting\Interfaces\HTTP\CrudControllerTrait;

/**
 * MODULE: Quản lý Tỷ giá (Exchange Rates)
 *
 * Mục đích nghiệp vụ:
 *   - CRUD tỷ giá ngoại tệ
 *   - Tự động cập nhật tỷ giá theo ngày
 *
 * API endpoints:
 *   GET    /api/exchange-rates — Danh sách
 *   POST   /api/exchange-rates — Cập nhật
 *   GET    /api/exchange-rates/{id} — Chi tiết
 *   PUT    /api/exchange-rates/{id} — Sửa
 *   DELETE /api/exchange-rates/{id} — Xoá
 *
 * Tích hợp:
 *   - FxController sử dụng để quy đổi ngoại tệ
 */
class ExchangeRateController
{
    use CrudControllerTrait;

    /**
     * @param ExchangeRateRepositoryInterface $repository
     */
    public function __construct(ExchangeRateRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->module = 'exchange_rates';
    }

    protected function repo()
    {
        return $this->repository;
    }

    protected function idPrefix(): string
    {
        return 'fx_';
    }

    protected function createEntity(array $data): object
    {
        return new ExchangeRate(
            id: $data['id'] ?? uniqid('fx_'),
            currencyCode: $data['currency_code'] ?? '',
            currencyName: $data['currency_name'] ?? '',
            rate: (float)($data['rate'] ?? 0),
            rateDate: $data['rate_date'] ?? date('Y-m-d')
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
