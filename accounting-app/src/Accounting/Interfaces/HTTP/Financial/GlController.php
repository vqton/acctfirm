<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\GlService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Sổ Cái (General Ledger)
 *
 * Mục đích nghiệp vụ:
 *   - Hiển thị sổ cái cho từng tài khoản kế toán
 *   - Xem chi tiết phát sinh Nợ/Có và số dư luỹ kế
 *
 * API endpoints:
 *   GET /api/gl/{accountCode} — Sổ cái tài khoản (params: from, to)
 *
 * Rủi ro:
 *   - Số liệu chỉ tính các giao dịch đã post
 *   - Query nặng nếu nhiều dữ liệu
 *
 * Tích hợp:
 *   - GlService đọc từ TransactionRepository
 *   - ExportController sử dụng GL data xuất file
 */
class GlController
{
    private GlService $gl;

    public function __construct(GlService $gl) { $this->gl = $gl; }

    /**
     * Sổ cái chi tiết cho một tài khoản
     *
     * @param string $accountCode Mã tài khoản
     * @return void
     */
    public function index(string $accountCode): void
    {
        Auth::requirePermission('report', 'read');
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        try {
            JsonResponse::ok($this->gl->getGeneralLedger($accountCode, $from, $to));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }
}
