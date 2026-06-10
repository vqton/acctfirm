<?php
namespace Accounting\Interfaces\HTTP\MasterData;

use Accounting\Infrastructure\JsonResponse;
use Accounting\Infrastructure\Auth;
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
}
