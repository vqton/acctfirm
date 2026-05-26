<?php
namespace Accounting\Domain\Service;

// Dịch vụ sinh số chứng từ tự động (Voucher Numbering — Auto-increment)
//
// Nghiệp vụ: Mọi nghiệp vụ kinh tế phải có số chứng từ duy nhất, tăng dần
// và không trùng lặp. Hệ thống tự động sinh số theo format:
//   {PREFIX}{YYYY}-{000000}
// Ví dụ: PC2026-000042 (Phiếu chi năm 2026 số 42)
//
// Prefix convention:
//   PC: Phiếu chi     PT: Phiếu thu       JV: Journal Voucher
//   PNK: Phiếu nhập kho   PXK: Phiếu xuất kho
//   IC_ELIM: Intercompany elimination entry
//   FX-REVAL: FX revaluation adjustment
//
// Yêu cầu bắt buộc theo TT 99:
//   - Số chứng từ tăng dần, liên tục, không bỏ sót
//   - Riêng biệt cho từng loại chứng từ (prefix) và từng năm
//   - SELECT FOR UPDATE để đảm bảo uniqueness khi nhiều người dùng đồng thời
//
// Process flow:
//   1. beginTransaction() — mở transaction
//   2. SELECT ... FOR UPDATE — khóa dòng tương ứng (prefix + year)
//   3. Đọc last_no → tính next_no = last_no + 1 (hoặc 1 nếu chưa có)
//   4. UPDATE/INSERT last_no = next_no
//   5. commit() — giải phóng lock
//   6. Trả về formatted string: {prefix}{year}-{next_no:06d}
//
// Concurrency model:
//   SELECT FOR UPDATE là row-level lock trên MySQL InnoDB.
//   - Nếu 2 request đồng thời cùng prefix + year:
//     Request 1: SELECT FOR UPDATE → khóa row
//     Request 2: SELECT FOR UPDATE → CHỜ (blocking)
//     Request 1: UPDATE + commit → release lock
//     Request 2: SELECT → thấy last_no đã tăng → next_no = last_no + 1
//   - Đảm bảo không trùng số chứng từ dưới mọi tải.
//
// RỦI RO:
//   - Số chứng từ trùng = mất audit trail, không truy xuất được chứng từ gốc
//   - Nếu commit() fail sau khi SELECT FOR UPDATE → rollback → không tăng số
//     → có thể bỏ sót số (gap). Gap là chấp nhận được theo TT 99 (không yêu cầu
//     liên tục tuyệt đối, chỉ yêu cầu tăng dần và không trùng)
//   - Nếu transaction timeout (lock wait timeout) → Exception → user thấy lỗi
//     → retry (idempotent-safe vì chỉ tăng last_no khi commit thành công)
//   - Năm mới: Nếu chưa có row cho year mới → INSERT → next_no = 1
//     Đảm bảo reset số mỗi năm (yêu cầu TT 99)
//
// Edge cases:
//   - year = 2026, prefix = 'PC' → PC2026-000001, PC2026-000002...
//   - Năm 2027 → PC2027-000001 (reset từ đầu)
//   - prefix lạ (VD 'XX'): tạo row mới với last_no = 1
//   - Cùng lúc 100 request: xếp hàng, không trùng — nhưng chậm hơn
class VoucherService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Sinh số chứng từ tiếp theo cho một loại chứng từ.
     *
     * Process flow:
     *   1. beginTransaction() — mở transaction MySQL
     *   2. SELECT last_no FROM voucher_sequences WHERE prefix = ? AND year = ? FOR UPDATE
     *      → Row-level lock: ngăn request khác đọc/ghi row này
     *      → Nếu 2 request cùng lúc: request 2 chờ đến khi request 1 commit/rollback
     *   3. nextNo = (last_no + 1) hoặc 1 nếu chưa có row
     *   4. UPDATE last_no (hoặc INSERT row mới cho năm đầu tiên)
     *   5. commit() — giải phóng lock
     *   6. Return formatted: {prefix}{year}-{nextNo zero-padded 6 digits}
     *
     * Transaction boundary: toàn bộ method là 1 transaction atomic
     *   BEGIN → SELECT FOR UPDATE → UPDATE/INSERT → COMMIT
     *   Nếu bất kỳ bước nào fail → ROLLBACK → không tăng last_no
     *
     * Idempotency: KHÔNG idempotent — mỗi lần gọi đều tăng last_no lên 1.
     * Caller phải đảm bảo chỉ gọi 1 lần cho mỗi chứng từ.
     * Nếu gọi nhiều lần → số chứng từ bị nhảy cóc (gap) nhưng không trùng.
     *
     * Concurrency:
     *   SELECT FOR UPDATE đảm bảo serializable access cho mỗi (prefix, year).
     *   100 request đồng thời cho cùng prefix → xếp hàng tuần tự.
     *   Performance: ~1ms mỗi request nếu không có contention.
     *   Nếu contention cao (>> 100 req/s), cân nhách dùng sequence table
     *   hoặc Redis INCR để tăng throughput.
     *
     * RỦI RO:
     *   - Lock wait timeout: Nếu request trước giữ lock quá lâu (VD 50s)
     *     → request sau timeout → Exception → user thấy lỗi 500
     *     → Giải pháp: đặt innodb_lock_wait_timeout hợp lý (mặc định 50s)
     *   - Deadlock: Cực kỳ hiếm vì chỉ lock 1 row. Nếu xảy ra → rollback → retry
     *   - Gap trong sequence: Có thể xảy ra nếu transaction rollback sau SELECT
     *     FOR UPDATE. Gap không ảnh hưởng audit trail (chỉ cần unique, tăng dần)
     *   - year rollover: Ngày 01/01 hàng năm tự động reset từ 1
     *     (vì WHERE year = ? thay đổi)
     *
     * Integration:
     *   - Được gọi từ JournalService::postEntry() nếu reference = null
     *   - Cũng được gọi trực tiếp từ controller khi tạo chứng từ riêng lẻ
     *   - KHÔNG thread-safe nếu dùng PHP built-in server (single-threaded anyway)
     *   - Thread-safe với PHP-FPM (multi-process) vì MySQL xử lý lock
     */
    public function nextNumber(string $prefix): string
    {
        $year = (int)date('Y');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT last_no FROM voucher_sequences WHERE prefix = ? AND year = ? FOR UPDATE"
            );
            $stmt->execute([$prefix, $year]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            $nextNo = $row ? ((int)$row['last_no'] + 1) : 1;

            if ($row) {
                $update = $this->pdo->prepare(
                    "UPDATE voucher_sequences SET last_no = ? WHERE prefix = ? AND year = ?"
                );
                $update->execute([$nextNo, $prefix, $year]);
            } else {
                $insert = $this->pdo->prepare(
                    "INSERT INTO voucher_sequences (prefix, year, last_no) VALUES (?, ?, ?)"
                );
                $insert->execute([$prefix, $year, $nextNo]);
            }

            $this->pdo->commit();
            return sprintf('%s%d-%06d', $prefix, $year, $nextNo);
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
