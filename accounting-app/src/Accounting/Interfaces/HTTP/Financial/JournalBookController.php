<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\JournalBookService;
use Accounting\Infrastructure\Auth;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Sổ Nhật ký (Journal Book)
 *
 * Mục đích nghiệp vụ:
 *   - Hiển thị sổ nhật ký chung
 *   - Xem lịch sử bút toán theo thời gian
 *   - Xuất sổ nhật ký (CSV/HTML)
 *
 * API endpoints:
 *   GET /api/journal-book — Sổ nhật ký (params: from, to, account)
 *
 * Rủi ro:
 *   - Query nặng nếu nhiều bút toán trong kỳ
 *
 * Tích hợp:
 *   - JournalBookService đọc từ TransactionRepository
 *   - ExportService xuất file
 */
class JournalBookController
{
    private JournalBookService $journalBook;

    public function __construct(JournalBookService $journalBook) { $this->journalBook = $journalBook; }

    /**
     * Sổ nhật ký chung
     *
     * @return void
     */
    public function index(): void
    {
        Auth::requirePermission('report', 'read');
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-t');
        $account = $_GET['account'] ?? null;
        try {
            JsonResponse::ok($this->journalBook->getJournalBook($from, $to, $account));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }
}
