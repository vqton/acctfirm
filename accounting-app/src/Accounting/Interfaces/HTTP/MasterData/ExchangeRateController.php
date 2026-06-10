<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
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
}
