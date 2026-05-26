<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\GlService;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Sổ Cái (General Ledger)
 *
 * Mục đích nghiệp vụ:
 *   - Xem sổ cái chi tiết theo tài khoản (detail mode)
 *   - Xem sổ cái tổng hợp theo tháng (monthly mode)
 *   - Tra cứu số dư đầu kỳ, phát sinh, số dư cuối kỳ
 *   - Hỗ trợ filter theo khoảng thời gian
 *
 * API endpoints:
 *   GET /api/gl/ledger   — Sổ cái (params: account, from, to, mode=detail|monthly)
 *   GET /api/gl/accounts — Danh sách tài khoản kế toán
 *   GET /api/gl          — View sổ cái (HTML)
 *
 * Rủi ro:
 *   - Số dư sai nếu ledger entries không cân đối (Dr ≠ Cr)
 *   - Cần phân biệt số dư Nợ/Có theo normal_balance của tài khoản
 *   - Monthly mode cần tính đúng số dư lũy kế
 *
 * Tích hợp:
 *   - GlService đọc từ TransactionRepository + AccountRepository
 *   - Api này dùng để kiểm tra đối chiếu giữa sổ chi tiết và sổ tổng hợp
 *   - Kế toán trưởng kiểm tra trước khi khóa sổ cuối kỳ
 */
class GlController
{
    private GlService $gl;

    public function __construct(GlService $gl) { $this->gl = $gl; }

    // NGHIỆP VỤ: Sổ cái chi tiết/tổng hợp — xem phát sinh và số dư theo tài khoản
    // Input: GET ?account=111&from=2025-01-01&to=2025-01-31&mode=detail|monthly
    // Output: { account_code, opening_balance, transactions: [{date, ref, description, dr, cr}], closing_balance }
    // Service: GlService.getGeneralLedger() hoặc getMonthlyLedger() — từ TransactionRepository
    // Mode detail: Từng giao dịch. Mode monthly: Tổng hợp theo tháng (dòng Month-to-date)
    // Rủi ro: Sai số dư nếu ledger entries không cân đối hoặc normal_balance sai
    // Mục đích: Đối chiếu sổ chi tiết với sổ tổng hợp trước khi khóa sổ
    public function ledger(): void
    {
        $account = $_GET['account'] ?? '111';
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        $mode = $_GET['mode'] ?? 'detail';
        try {
            $data = $mode === 'monthly'
                ? $this->gl->getMonthlyLedger($account, $from, $to)
                : $this->gl->getGeneralLedger($account, $from, $to);
            JsonResponse::ok($data);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    public function accounts(): void
    {
        JsonResponse::ok($this->gl->getAccounts());
    }

    public function view(): void
    {
        require __DIR__ . '/../../../../../public/views/so_cai.php';
    }
}
