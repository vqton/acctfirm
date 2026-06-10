<?php
namespace Accounting\Interfaces\HTTP\Financial;

use Accounting\Domain\Service\JournalBookService;
use Accounting\Infrastructure\JsonResponse;

/**
 * MODULE: Sổ Nhật ký Chung (General Journal)
 *
 * Mục đích nghiệp vụ:
 *   - Xuất sổ nhật ký chung theo khoảng thời gian
 *   - Ghi nhận mọi bút toán theo trình tự thời gian
 *   - Cơ sở để lập sổ cái và báo cáo tài chính
 *
 * API endpoints:
 *   GET /api/journal-book       — Dữ liệu sổ nhật ký chung (params: from, to)
 *   GET /api/journal-book/view  — View HTML
 *
 * Rủi ro:
 *   - Dữ liệu lớn nếu không filter → cần phân trang hoặc giới hạn thời gian
 *   - Sổ nhật ký phải đầy đủ để kiểm toán viên đối chiếu
 *   - Số thứ tự dòng (line number) phải liên tục
 *
 * Tích hợp:
 *   - JournalBookService đọc từ TransactionRepository
 *   - Kết quả được dùng làm đầu vào cho báo cáo thuế
 *   - Kiểm toán viên sử dụng để kiểm tra tính đầy đủ của bút toán
 */
class JournalBookController
{
    private JournalBookService $service;

    public function __construct(JournalBookService $service)
    {
        $this->service = $service;
    }

    // NGHIỆP VỤ: Sổ Nhật ký Chung — mọi bút toán theo trình tự thời gian
    // Input: GET ?from=2025-01-01&to=2025-01-31
    // Output: { entries: [{date, reference, description, line_no, account_code, account_name, dr, cr}] }
    // Service: JournalBookService.getGeneralJournal() — đọc từ TransactionRepository
    // Rủi ro: Nếu dữ liệu quá lớn, cần phân trang hoặc giới hạn thời gian
    // Mục đích: Cơ sở để kiểm toán viên đối chiếu, dùng để lập sổ cái và BC01/02/03
    // Ràng buộc: Số dư Dr = Cr cho mỗi dòng. Số thứ tự dòng phải liên tục
    public function journal(): void
    {
        $from = $_GET['from'] ?? date('Y-01-01');
        $to = $_GET['to'] ?? date('Y-m-d');
        try {
            JsonResponse::ok($this->service->getGeneralJournal($from, $to));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 404);
        }
    }

    public function view(): void
    {
        require __DIR__ . '/../../../../../public/views/so_nhat_ky_chung.php';
    }
}
