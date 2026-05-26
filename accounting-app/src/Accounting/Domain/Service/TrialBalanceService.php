<?php
namespace Accounting\Domain\Service;

// Dịch vụ Bảng cân đối tài khoản (Trial Balance)
//
// Nghiệp vụ: Bảng cân đối tài khoản liệt kê tổng phát sinh Nợ và Có của
// tất cả tài khoản kế toán trong một kỳ. Đây là công cụ kiểm tra cơ bản
// trước khi lập Báo cáo tài chính.
//
// Nguyên tắc kế toán kép:
//   - Tổng số phát sinh Nợ = Tổng số phát sinh Có
//   - Nếu không cân (sai lệch > 10 VND) → phải tìm nguyên nhân trước khi lập BCTC
//   - Mỗi tài khoản có số dư Nợ hoặc Có tùy theo bản chất:
//       • Nợ: Tài sản (1xx), Chi phí (6xx, 8xx)
//       • Có: Nguồn vốn (3xx, 4xx), Doanh thu (5xx, 7xx)
//
// RỦI RO: TB không cân = dữ liệu kế toán sai. Không thể lập BCTC.
// Mọi bút toán đều phải đảm bảo Dr = Cr trước khi post.
//
// CÁCH ĐỌC TB:
//   - Tổng Dr = Tổng Cr → hệ thống cân (balanced = true).
//   - Từng tài khoản có net_balance = total_dr - total_cr.
//   - Net dương → TK dư Nợ (tài sản, chi phí).
//   - Net âm → TK dư Có (nguồn vốn, doanh thu).
//
// GIỚI HẠN CỦA TB HIỆN TẠI:
//   - Chỉ hiển thị phát sinh trong kỳ, không có số dư đầu kỳ và cuối kỳ.
//   - Không phân biệt tài khoản tổng hợp (control) và chi tiết.
//   - Không lọc theo module (AP/AR/Cash...).
//   - Không hỗ trợ so sánh giữa các kỳ.
// Các tính năng này cần được bổ sung để đáp ứng yêu cầu kiểm toán.
//
// TÍCH HỢP: FsService (Financial Statements) dùng dữ liệu từ TB để lập BC01/02/03.
// Số dư cuối kỳ của tài khoản = net_balance (sau khi đã cộng dồn các kỳ trước).
//
// READ-ONLY: Không ghi dữ liệu, chỉ đọc ledger_entries + transactions.
class TrialBalanceService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    //
    // LẬP BẢNG CÂN ĐỐI TÀI KHOẢN: Phát sinh Nợ/Có theo từng TK trong kỳ.
    // Input: periodCode (tùy chọn). Nếu không có → tất cả dữ liệu (từ đầu đến nay).
    // Process:
    //   1. Nếu có periodCode → JOIN accounting_periods để lấy start_date/end_date
    //   2. GROUP BY account_code, account_name, account_type
    //   3. SUM(is_debit=1 → Dr, is_debit=0 → Cr)
    //   4. Tính net_balance = Dr - Cr
    //   5. Kiểm tra cân: |grandDr - grandCr| < 10 VND
    // Output: danh sách TK + phát sinh + kiểm tra cân
    //
    // KIỂM TOÁN: TB là bước đầu tiên trong quy trình kiểm toán.
    // Nếu TB không cân → dừng lại, không lập BCTC. Sai lệch > 10 VND là bất thường.
    // Các nguyên nhân thường gặp: lỗi posting, mất dữ liệu transaction, lỗi phần mềm.
    //
    public function getTrialBalance(?string $periodCode = null): array
    {
        $where = '';
        $params = [];
        if ($periodCode) {
            $where = 'WHERE t.date >= (SELECT start_date FROM accounting_periods WHERE period_code = ?)
                      AND t.date <= (SELECT end_date FROM accounting_periods WHERE period_code = ?)';
            $params = [$periodCode, $periodCode];
        }

        $sql = "
            SELECT a.code, a.name, a.type,
                   COALESCE(SUM(CASE WHEN le.is_debit = 1 THEN le.amount ELSE 0 END), 0) AS total_dr,
                   COALESCE(SUM(CASE WHEN le.is_debit = 0 THEN le.amount ELSE 0 END), 0) AS total_cr
            FROM ledger_entries le
            JOIN transactions t ON t.id = le.transaction_id
            JOIN accounts a ON a.id = le.account_id
            {$where}
            GROUP BY a.code, a.name, a.type
            ORDER BY a.code
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $items = [];
        $grandDr = 0;
        $grandCr = 0;

        foreach ($rows as $r) {
            $dr = (float)$r['total_dr'];
            $cr = (float)$r['total_cr'];
            $net = $dr - $cr;
            $items[] = [
                'code' => $r['code'],
                'name' => $r['name'],
                'type' => $r['type'],
                'total_dr' => $dr,
                'total_cr' => $cr,
                'net_balance' => $net,
            ];
            $grandDr += $dr;
            $grandCr += $cr;
        }

        return [
            'items' => $items,
            'grand_total_dr' => $grandDr,
            'grand_total_cr' => $grandCr,
            'balanced' => abs($grandDr - $grandCr) < 10,
        ];
    }
}
