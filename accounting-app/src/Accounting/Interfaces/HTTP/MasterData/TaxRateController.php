<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
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
}
