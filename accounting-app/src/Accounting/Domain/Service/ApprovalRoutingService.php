<?php
namespace Accounting\Domain\Service;

// Dịch vụ luồng phê duyệt (Approval Routing)
//
// Nghiệp vụ: Theo quy định kiểm soát nội bộ (Internal Control — COSO framework),
// các bút toán có giá trị lớn hoặc thuộc nghiệp vụ nhạy cảm cần được phê duyệt
// trước khi ghi nhận. Quy tắc phê duyệt được định nghĩa trong bảng approval_routing:
//   - Theo ngưỡng số tiền (min_amount, max_amount)
//   - Theo module nghiệp vụ (purchase, sales, cash...)
//   - Theo loại tài khoản (expense, revenue, asset...)
//   - Mặc định: Nếu không có quy tắc → cần Kế toán trưởng (chief_accountant) duyệt
//
// Process flow:
//   1. Kế toán viên tạo bút toán → gọi getRequiredRoles() để xác định role cần duyệt
//   2. Hệ thống hiển thị danh sách người dùng có role tương ứng
//   3. Người phê duyệt kiểm tra và phê duyệt/từ chối
//   4. Khi đủ số lượng phê duyệt yêu cầu → bút toán mới được post
//
// Phân quyền kiểm soát nội bộ (Segregation of Duties):
//   - Người tạo bút toán ≠ Người phê duyệt (nguyên tắc bất kiêm nhiệm)
//   - Giao dịch > ngưỡng → cần 2 người duyệt (accountant + chief accountant)
//   - Giao dịch đặc biệt (mua TSCĐ, thanh lý TS...) → cần Giám đốc duyệt
//
// RỦI RO:
//   - Không có quy tắc phê duyệt → mọi giao dịch chỉ cần chief_accountant duyệt
//     → quá tải cho CKT, chậm xử lý nghiệp vụ
//   - Quy tắc sai ngưỡng → giao dịch nhỏ cũng phải duyệt nhiều cấp → kém hiệu quả
//   - Phê duyệt sai role → người không có thẩm quyền duyệt → rủi ro gian lận
//   - Race condition: Nếu 2 người cùng duyệt 1 lúc → double approval. Cần lock.
class ApprovalRoutingService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Xác định role cần phê duyệt cho một bút toán.
     *
     * Process flow:
     *   1. Query bảng approval_routing với các tiêu chí: amount, module, account_type
     *   2. Rule đầu tiên match (ORDER BY priority ASC) được chọn
     *   3. Nếu không có rule nào match → mặc định trả về ['chief_accountant']
     *
     * Logic matching:
     *   - amount >= min_amount AND amount <= max_amount (NULL = không giới hạn)
     *   - module = ? (NULL = áp dụng cho mọi module)
     *   - account_type = ? (NULL = áp dụng cho mọi loại TK)
     *   - is_active = 1 (chỉ lấy rule đang có hiệu lực)
     *
     * Edge cases:
     *   - totalAmount = 0 (điều chỉnh không tiền): cần duyệt vì liên quan đến số dư
     *   - module không tồn tại trong bảng: fallback về chief_accountant
     *   - Nhiều rule match cùng priority: lấy rule đầu tiên (không xác định thứ tự)
     *
     * RỦI RO:
     *   - Nếu first-matching-rule là user thường (accountant) và bỏ qua chief_accountant
     *     → giao dịch lớn không được kiểm soát. Cần đảm bảo priority đúng.
     *   - Default về chief_accountant có thể gây nghẽn nếu CKT quá bận.
     *   - Không kiểm tra người tạo có trùng với người duyệt — phải kiểm tra ở caller.
     *
     * Transaction boundary: READ-ONLY — không transaction cần thiết.
     * Concurrent: Không vấn đề vì chỉ đọc dữ liệu cấu hình.
     */
    public function getRequiredRoles(float $totalAmount, ?string $module = null, ?string $accountType = null): array
    {
        $sql = "SELECT required_role FROM approval_routing
                WHERE is_active = 1
                  AND (min_amount IS NULL OR ? >= min_amount)
                  AND (max_amount IS NULL OR ? <= max_amount)
                  AND (module IS NULL OR module = ?)
                  AND (account_type IS NULL OR account_type = ?)
                ORDER BY priority ASC
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$totalAmount, $totalAmount, $module, $accountType]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? [$row['required_role']] : ['chief_accountant'];
    }
}
