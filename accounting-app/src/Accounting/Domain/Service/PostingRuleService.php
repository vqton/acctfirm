<?php
namespace Accounting\Domain\Service;

/**
 * Dịch vụ kiểm tra quy tắc hạch toán (Posting Rules — GL Validation Engine Phase 1).
 *
 * Nghiệp vụ: Mỗi cặp Nợ-Có (Dr-Cr) được kiểm tra theo các quy tắc đã định nghĩa
 * trong bảng posting_rules để đảm bảo tuân thủ thông lệ kế toán và phòng ngừa
 * sai sót nghiệp vụ. Kết quả trả về:
 *   - block: Cặp Nợ-Có không hợp lệ (VD: Nợ 156 / Có 511 không có cơ sở nghiệp vụ)
 *   - warn: Cặp hiếm gặp, cần xác nhận thêm (VD: Nợ 642 / Có 112 — chi phí bằng tiền)
 *   - pass: Không có quy tắc → cho phép (có thể là nghiệp vụ mới phát sinh)
 *
 * Mục đích:
 *   - Ngăn chặn hạch toán sai bản chất nghiệp vụ ngay từ khi nhập liệu
 *   - Giảm thiểu sai sót do kế toán viên mới hoặc nhầm lẫn
 *   - Lưu dấu audit cho mọi quyết định hạch toán
 *   - Từ chối các cặp Dr-Cr không có cơ sở kinh tế (VD: Dr 511/Cr 111)
 *
 * Posting rules có 3 cấp độ:
 *   1. Exact match (debit, credit, module): Rule cụ thể cho 1 module + 1 cặp TK
 *   2. Wildcard (debit, credit, module=NULL): Rule áp dụng cho mọi module
 *   3. Fallback: Không có rule → pass (cho phép)
 *
 * 75 rules đã được seed cho các nghiệp vụ phổ biến (mua hàng, bán hàng, thu chi...).
 * Các nghiệp vụ mới (VD: crypto, quỹ đầu tư) chưa có rule → pass.
 * Cần bổ sung rule khi phát sinh nghiệp vụ mới để kiểm soát chặt hơn.
 *
 * RỦI RO:
 *   - Block nhầm: Nếu rule block quá rộng → từ chối nghiệp vụ hợp lệ → user không làm được
 *   - Pass thiếu: Nếu nghiệp vụ sai không có rule → pass → dữ liệu sai
 *   - Bảo trì: Rule cần được review định kỳ theo Thông tư mới
 *   - Module-scope: Rule theo module có thể bỏ sót nghiệp vụ cross-module
 */
class PostingRuleService
{
    private \PDO $pdo;

    /**
     * Khởi tạo dịch vụ kiểm tra quy tắc hạch toán.
     *
     * @param \PDO $pdo Kết nối PDO đến database, dùng để truy vấn bảng posting_rules
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Kiểm tra một cặp Nợ-Có (Dr-Cr) theo quy tắc hạch toán.
     *
     * Process flow (two-phase lookup):
     *   Phase 1 — Exact match:
     *     WHERE debit_account_code = ? AND credit_account_code = ?
     *       AND (module = ? OR (module IS NULL AND ? IS NULL))
     *     → Tìm rule khớp chính xác cả cặp TK và module
     *     → Nếu module = null, match với rule có module = null OR module = ?
     *   Phase 2 — Wildcard:
     *     WHERE debit_account_code = ? AND credit_account_code = ? AND module IS NULL
     *     → Tìm rule áp dụng cho mọi module
     *     → Dùng làm fallback khi không có rule module-specific
     *   Fallback:
     *     → pass (cho phép) nếu không có rule nào match
     *
     * Lưu ý: Thứ tự tìm kiếm quan trọng — exact match trước, wildcard sau
     * Giúp ưu tiên rule cụ thể cho module hơn rule chung.
     *
     * Edge cases:
     *   - max_amount trong bảng nhưng KHÔNG được kiểm tra trong code
     *     → Rule có max_amount nhưng không áp dụng giới hạn số tiền
     *     → Có thể là lỗi: cần bổ sung kiểm tra amount nếu có max_amount
     *   - is_active = 1: chỉ lấy rule đang hiệu lực
     *   - Cặp TK đảo ngược (Dr 111/Cr 511 ≠ Dr 511/Cr 111): khác nhau hoàn toàn
     *
     * RỦI RO:
     *   - Nếu Phase 2 (wildcard) block nhưng Phase 1 (exact) cho phép
     *     → module-specific rule override wildcard (đúng design)
     *   - Nếu có 2 rule cùng debit, credit nhưng khác module:
     *     module-specific được ưu tiên (đúng)
     *   - max_amount không được kiểm tra → rule giới hạn amount vô hiệu
     *
     * Transaction boundary: READ-ONLY — không cần transaction.
     *
     * @param string $debitAccountCode Mã tài khoản Nợ (bên Dr)
     * @param string $creditAccountCode Mã tài khoản Có (bên Cr)
     * @param string|null $module Module nghiệp vụ (VD: 'purchase', 'sales', 'cash'), null = áp dụng cho mọi module
     * @return array{severity: string, message: string, rule_id: int|null} Kết quả kiểm tra: 'block' | 'warn' | 'pass'
     * @throws \PDOException Khi truy vấn database thất bại
     */
    public function validatePair(string $debitAccountCode, string $creditAccountCode, ?string $module = null): array
    {
        // Phase 1: Exact match (debit, credit, module)
        $sql = "SELECT id, severity, max_amount FROM posting_rules
                WHERE debit_account_code = ? AND credit_account_code = ? AND (module = ? OR (module IS NULL AND ? IS NULL)) AND is_active = 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$debitAccountCode, $creditAccountCode, $module, $module]);
        $rule = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($rule) {
            return [
                'severity' => $rule['severity'],
                'message' => "{$debitAccountCode}->{$creditAccountCode}: " . ($rule['severity'] === 'block' ? 'bị chặn bởi quy tắc hạch toán' : 'cảnh báo theo quy tắc hạch toán'),
                'rule_id' => (int)$rule['id'],
            ];
        }

        // Phase 2: Wildcard match (debit, credit, module = null — áp dụng cho mọi module)
        $stmt = $this->pdo->prepare(
            "SELECT id, severity FROM posting_rules
             WHERE debit_account_code = ? AND credit_account_code = ? AND module IS NULL AND is_active = 1"
        );
        $stmt->execute([$debitAccountCode, $creditAccountCode]);
        $rule = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($rule) {
            return [
                'severity' => $rule['severity'],
                'message' => "{$debitAccountCode}->{$creditAccountCode}: " . ($rule['severity'] === 'block' ? 'bị chặn bởi quy tắc hạch toán' : 'cảnh báo theo quy tắc hạch toán'),
                'rule_id' => (int)$rule['id'],
            ];
        }

        // Fallback: không có rule nào match → cho phép
        // Lưu ý: pass không có nghĩa là nghiệp vụ đúng — chỉ là không có rule cấm.
        // Kế toán trưởng nên review định kỳ các cặp pass để bổ sung rule nếu cần.
        return ['severity' => 'pass', 'message' => 'Không có quy tắc hạch toán', 'rule_id' => null];
    }

    /**
     * Kiểm tra tất cả cặp Dr-Cr trong một bút toán.
     *
     * Process flow:
     *   1. Tách lines thành 2 nhóm: debits (is_debit = true) và credits (is_debit = false)
     *   2. Validate từng cặp (Dr, Cr) — tích Descartes (Cartesian product)
     *   3. Mỗi cặp được kiểm tra qua validatePair()
     *   4. Trả về mảng tất cả kết quả
     *
     * Tại sao dùng Cartesian product:
     *   - Một bút toán có thể có nhiều dòng Nợ và nhiều dòng Có
     *   - Mỗi cặp Dr-Cr phải hợp lệ, không chỉ Dr đầu-Cr đầu
     *   - VD: Nợ 156 (hàng hóa), Nợ 1331 (thuế) / Có 331 (phải trả NCC)
     *     → Kiểm tra: 156→331, 1331→331 (cả 2 đều phải pass)
     *
     * Edge cases:
     *   - 1 Dr + 1 Cr: 1 cặp (đơn giản nhất)
     *   - N Dr + 1 Cr: N cặp (VD: mua hàng nhiều loại)
     *   - 1 Dr + N Cr: N cặp (VD: thanh toán nhiều hóa đơn)
     *   - N Dr + M Cr: N*M cặp (phức tạp — hiếm gặp)
     *   - Lines rỗng: không có cặp nào → trả về mảng rỗng
     *
     * Performance: Với N debit và M credit, cần N*M queries
     *   10 debit + 10 credit = 100 queries. Nếu thường xuyên > 20 dòng,
     *   cần cache posting_rules trong memory để tránh N*M round trips.
     *
     * RỦI RO: Cartesian product có thể bỏ sót kiểm tra tổng thể
     * (VD: Dr 156/Cr 331 pass, Dr 1331/Cr 331 pass, nhưng tổng Nợ ≠ tổng Có
     * — kiểm tra này do JournalService thực hiện, không phải PostingRuleService)
     *
     * @param array $lines Danh sách các dòng bút toán, mỗi dòng chứa 'account_code', 'is_debit', 'amount'
     * @param string|null $module Module nghiệp vụ (VD: 'purchase', 'sales', 'cash'), null = mọi module
     * @return array[] Mảng các kết quả từ validatePair(), mỗi phần tử có 'severity', 'message', 'rule_id'
     * @throws \PDOException Khi truy vấn database thất bại
     */
    public function validateEntry(array $lines, ?string $module = null): array
    {
        $results = [];
        $debits = [];
        $credits = [];

        foreach ($lines as $line) {
            if ($line['is_debit']) {
                $debits[] = $line['account_code'];
            } else {
                $credits[] = $line['account_code'];
            }
        }

        // Validate every Dr with every Cr
        foreach ($debits as $dr) {
            foreach ($credits as $cr) {
                $results[] = $this->validatePair($dr, $cr, $module);
            }
        }

        return $results;
    }

    /**
     * Kiểm tra có bất kỳ kết quả block nào không.
     *
     * Nếu có block → bút toán bị từ chối hoàn toàn.
     * Controller gọi hasBlock() trước, nếu true → trả về 422 Unprocessable Entity.
     *
     * RỦI RO: hasBlock() chỉ kiểm tra block — không quan tâm warn.
     * Nếu có block + warn, ưu tiên block (từ chối).
     * Người dùng cần sửa cặp Dr-Cr bị block trước, sau đó mới xử lý warn.
     *
     * @param array $validationResults Mảng kết quả từ validatePair() hoặc validateEntry()
     * @return bool True nếu có bất kỳ kết quả nào có severity = 'block'
     */
    public function hasBlock(array $validationResults): bool
    {
        foreach ($validationResults as $r) {
            if ($r['severity'] === 'block') return true;
        }
        return false;
    }

    /**
     * Kiểm tra có bất kỳ kết quả warning nào không.
     *
     * Nếu có warning (không có block) → bút toán được phép nhưng cần xác nhận.
     * Controller hiển thị warning cho user: "Cặp Nợ 642/Có 112 hiếm gặp. Bạn có chắc?"
     * User có thể xác nhận và tiếp tục.
     *
     * Lưu ý: hasWarning() KHÔNG loại trừ hasBlock().
     * Nếu có cả block và warn → block chiếm ưu tiên.
     * Caller nên kiểm tra hasBlock() trước, hasWarning() sau.
     *
     * @param array $validationResults Mảng kết quả từ validatePair() hoặc validateEntry()
     * @return bool True nếu có bất kỳ kết quả nào có severity = 'warn'
     */
    public function hasWarning(array $validationResults): bool
    {
        foreach ($validationResults as $r) {
            if ($r['severity'] === 'warn') return true;
        }
        return false;
    }
}
