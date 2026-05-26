<?php
namespace Accounting\Domain\Service;

// Dịch vụ Sổ Nhật ký chung (General Journal)
//
// Nghiệp vụ: Sổ Nhật ký chung là sổ kế toán tổng hợp dùng để ghi chép
// mọi nghiệp vụ kinh tế phát sinh theo trình tự thời gian. Đây là sổ bắt buộc
// theo Thông tư 99 — căn cứ để ghi Sổ Cái và lập BCTC.
//
// Đặc điểm:
//   - Ghi nhận mọi bút toán đã posted, sắp xếp theo ngày chứng từ
//   - Hiển thị tài khoản Nợ và Có đối ứng cho từng giao dịch
//   - Tính tổng luỹ kế Nợ/Có (tổng Dr luôn = tổng Cr)
//
// RỦI RO: Nếu mất dữ liệu Sổ Nhật ký chung, không thể truy xuất nguồn gốc
// các bút toán → mất audit trail → rủi ro kiểm toán và pháp lý.
//
// KHÁC BIỆT GIỮA NKC VÀ SỔ CÁI:
//   - Sổ NKC: Ghi theo thời gian, mỗi bút toán hiển thị đầy đủ Dr và Cr.
//   - Sổ Cái: Ghi theo tài khoản, chỉ hiển thị các dòng của 1 TK.
// Sổ NKC là cơ sở kiểm tra: tổng Dr (luỹ kế) = tổng Cr (luỹ kế) → trial balance cân.
//
// TÍCH HỢP: Dùng trong module Kế toán tổng hợp — kế toán trưởng kiểm tra hàng ngày.
// ActionJournal ghi nhận request gọi API này → có audit trail ai xem sổ.
//
// READ-ONLY: Chỉ đọc transactions đã posted (t.status = 'posted').
// Draft entries không hiển thị trên sổ NKC chính thức.
class JournalBookService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    //
    // XUẤT SỔ NHẬT KÝ CHUNG: Tất cả bút toán đã posted trong khoảng thời gian.
    // Input: fromDate, toDate (mặc định: từ 01/01 đến hôm nay).
    // Process:
    //   1. Query transactions + ledger_entries + accounts, JOIN để lấy đầy đủ thông tin
    //   2. Nhóm theo transaction_id → mỗi giao dịch thành 1 nhóm
    //   3. Với mỗi dòng: xác định tài khoản đối ứng (trừ tài khoản hiện tại)
    //   4. Tính cumulative Dr và Cr luỹ kế — kiểm tra Dr = Cr
    // Output: từng dòng sổ với số dư luỹ kế
    //
    // ĐẶC TẢ NGHIỆP VỤ: Sổ NKC phải thỏa mãn:
    //   - Tổng Dr (entries cuối) = Tổng Cr (entries cuối) = tính năng kiểm tra
    //   - Mỗi dòng đều có tài khoản đối ứng (contra_account)
    //   - Giao dịch sắp xếp theo ngày tăng dần, id tăng dần
    //
    // PERFORMANCE: Query JOIN 3 bảng (transactions + ledger_entries + accounts).
    // Với DB lớn (>100K giao dịch), cần index trên: t.status, t.transaction_date, t.id.
    //
    public function getGeneralJournal(?string $fromDate = null, ?string $toDate = null): array
    {
        if (!$fromDate) $fromDate = date('Y-01-01');
        if (!$toDate) $toDate = date('Y-m-d');

        $params = [$fromDate, $toDate . ' 23:59:59'];

        $stmt = $this->pdo->prepare(
            "SELECT t.id AS txn_id, COALESCE(t.transaction_date, DATE(t.date)) AS txn_date,
                    t.reference, t.description,
                    le.id AS le_id, a.code AS account_code, a.name AS account_name,
                    le.amount, le.is_debit
             FROM transactions t
             JOIN ledger_entries le ON le.transaction_id = t.id
             LEFT JOIN accounts a ON a.id = le.account_id
             WHERE t.status = 'posted'
               AND (COALESCE(t.transaction_date, DATE(t.date)) BETWEEN ? AND ?)
             ORDER BY COALESCE(t.transaction_date, DATE(t.date)) ASC, t.id ASC, le.is_debit DESC, le.id ASC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $txnGroups = [];
        foreach ($rows as $r) {
            $txnGroups[$r['txn_id']][] = $r;
        }

        $result = [];
        $cumulativeDr = 0;
        $cumulativeCr = 0;

        foreach ($txnGroups as $txnId => $entries) {
            $drAccounts = [];
            $crAccounts = [];
            foreach ($entries as $e) {
                if ($e['is_debit']) {
                    $drAccounts[$e['account_code']] = true;
                } else {
                    $crAccounts[$e['account_code']] = true;
                }
            }

            $isFirst = true;
            foreach ($entries as $e) {
                $contraCodes = $e['is_debit'] ? array_keys($crAccounts) : array_keys($drAccounts);
                $contraCodes = array_values(array_filter($contraCodes, fn($c) => $c !== $e['account_code']));
                $contraStr = !empty($contraCodes) ? implode(', ', $contraCodes) : '—';

                $drAmount = $e['is_debit'] ? (float)$e['amount'] : 0;
                $crAmount = $e['is_debit'] ? 0 : (float)$e['amount'];
                $cumulativeDr += $drAmount;
                $cumulativeCr += $crAmount;

                $result[] = [
                    'date' => $e['txn_date'],
                    'reference' => $e['reference'],
                    'description' => $isFirst ? $e['description'] : '',
                    'account_code' => $e['account_code'],
                    'account_name' => $e['account_name'],
                    'contra_account' => $contraStr,
                    'debit' => $drAmount,
                    'credit' => $crAmount,
                    'cumulative_dr' => round($cumulativeDr, 2),
                    'cumulative_cr' => round($cumulativeCr, 2),
                ];
                $isFirst = false;
            }
        }

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'total_debit' => round($cumulativeDr, 2),
            'total_credit' => round($cumulativeCr, 2),
            'entries' => $result,
        ];
    }
}
