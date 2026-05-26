<?php
namespace Accounting\Domain\Service;

// Dịch vụ đối chiếu tổng hợp (General Ledger ↔ Sub-ledger Reconciliation)
//
// Nghiệp vụ: Đối chiếu số dư trên Sổ Cái (general ledger) với số dư trên
// các sổ chi tiết (sub-ledger) cho từng phân hệ. Đây là kiểm tra cốt lõi
// đảm bảo tính chính xác của số liệu kế toán trước khi lập BCTC.
//
// Nguyên tắc kế toán: Số dư trên GL phải bằng tổng số dư trên các sub-ledger
// tương ứng. Nếu có chênh lệch → có giao dịch chưa được hạch toán đúng.
//
// Process flow:
//   1. reconcileAll → gọi lần lượt từng phương thức đối chiếu
//   2. Mỗi phương thức: GL balance vs Sub-ledger balance
//   3. hasFailures → kiểm tra có chênh lệch vượt ngưỡng không
//
// Các đối tượng đối chiếu:
//   - AR (TK 131): GL 131 vs Σ(ar_invoices.balance) - đối chiếu công nợ phải thu
//   - AP (TK 331): GL 331 vs Σ(ap_invoices.balance) - đối chiếu công nợ phải trả
//   - Cash (TK 111): GL 111 (control) vs Σ(TK con 111%) - đối chiếu tiền mặt
//   - Bank (TK 112): GL 112 (control) vs Σ(TK con 112%) - đối chiếu tiền gửi
//   - Inventory (TK 152/153/155/156/157): GL vs Σ(qty * unit_cost) - đối chiếu hàng tồn kho
//   - Fixed Assets (TK 211-214): GL (211 - 214) vs Σ(fixed_assets.net_book_value) - đối chiếu TSCĐ
//
// Ảnh hưởng BCTC:
//   - AR/AP sai → BC01 chỉ tiêu 131/331 sai → Cân đối kế toán không khớp
//   - Inventory sai → BC01 chỉ tiêu 141 (Hàng tồn kho) sai + BC02 chỉ tiêu 24 (Giá vốn) sai
//   - FA sai → BC01 chỉ tiêu 211-214 sai, khấu hao BC02 sai
//   - Cash/Bank sai → BC01 chỉ tiêu 111/112 sai + BC03 (LCTT) sai
//
// Ngưỡng trọng yếu (materiality): 1.000 VND mặc định
//   - Chênh lệch > 1.000 VND → cần điều tra và xử lý trước khi khóa sổ
//   - Chênh lệch < 1.000 VND → có thể bỏ qua (sai số làm tròn)
//
// RỦI RO: Nếu GL ≠ Sub-ledger, BCTC sẽ sai. Chênh lệch lớn (> 1.000 VND)
// phải được điều tra trước khi lập BC01/BC02. Audit sẽ phát hiện ngay.
class ReconciliationService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function reconcileAll(): array
    {
        $results = [];
        foreach (['ar', 'ap', 'cash', 'bank', 'inventory', 'fa'] as $type) {
            $method = 'reconcile' . ucfirst($type);
            $results[$type] = $this->$method();
        }
        return $results;
    }

    // Kiểm tra kết quả đối chiếu có lỗi không
    //
    // Process:
    //   1. Duyệt tất cả kết quả đối chiếu
    //   2. Nếu bất kỳ mục nào có status = 'error' → return true (có lỗi nghiêm trọng)
    //   3. Nếu bất kỳ mục nào unmatched và |chênh lệch| > threshold → return true
    //   4. Ngược lại → tất cả đã khớp, không có lỗi
    //
    // Threshold mặc định: 1.000 VND
    //   - Có thể thay đổi tùy theo quy mô doanh nghiệp
    //   - Doanh nghiệp lớn (doanh thu > 100 tỷ) có thể đặt threshold = 1.000.000
    //   - Giá trị threshold cần được Kế toán trưởng phê duyệt
    //
    // RỦI RO: Threshold quá lớn → bỏ qua chênh lệch đáng kể → BCTC sai
    // Ngưỡng phù hợp được quy định trong chính sách kế toán doanh nghiệp
    public function hasFailures(array $results, float $threshold = 1000): bool
    {
        foreach ($results as $r) {
            if ($r['status'] === 'error') return true;
            if ($r['status'] === 'unmatched' && abs($r['difference']) > $threshold) return true;
        }
        return false;
    }

    // Lấy số dư GL cho một tài khoản theo mã tài khoản
    // Công thức: SUM(is_debit=1) - SUM(is_debit=0) = debit-normal balance
    // Với TK có normal balance = credit (như 331), kết quả sẽ âm (dư Có)
    private function glBalance(string $accountCode): float
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(CASE WHEN le.is_debit = 1 THEN le.amount ELSE -le.amount END), 0)
            FROM ledger_entries le
            JOIN accounts a ON a.id = le.account_id
            WHERE a.code = ?
        ");
        $stmt->execute([$accountCode]);
        return (float)$stmt->fetchColumn();
    }

    // Tạo cấu trúc kết quả đối chiếu cho một loại tài khoản
    //
    // Đầu vào: type (ar/ap/cash...), GL balance, Sub-ledger balance, label
    // Tính: difference = GL - Sub (làm tròn 2 chữ số thập phân)
    // Xác định: status = 'matched' nếu |diff| < 1.000, 'unmatched' nếu ≥ 1.000
    //
    // Lưu ý: Ngưỡng 1.000 VND ở đây là hard-coded, khác với threshold trong hasFailures
    // Có thể gây nhầm lẫn nếu một nơi dùng 1.000, nơi khác lại dùng threshold khác.
    // Nên đồng bộ ngưỡng về một hằng số hoặc config.
    private function result(string $type, float $glBal, float $subBal, string $label): array
    {
        $diff = round($glBal - $subBal, 2);
        $status = abs($diff) < 1000 ? 'matched' : 'unmatched';
        return [
            'type' => $type,
            'label' => $label,
            'gl_balance' => $glBal,
            'subledger_balance' => $subBal,
            'difference' => $diff,
            'status' => $status,
        ];
    }

    // Đối chiếu công nợ phải thu — AR (TK 131)
    //
    // So sánh: Số dư GL của TK 131 (tổng hợp) vs Tổng dư nợ các hóa đơn AR
    //
    // Logic: Σ(GL balance 131) == Σ(ar_invoices.balance)
    //   - Nếu GL > Sub-ledger: có bút toán ghi Nợ 131 nhưng chưa tạo invoice
    //     hoặc đã tất toán invoice nhưng chưa ghi Có 131
    //   - Nếu GL < Sub-ledger: có invoice chưa được hạch toán vào GL
    //
    // Filter: status != 'canceled' để loại trừ hóa đơn đã hủy
    //
    // RỦI RO: TK 131 là control account → mọi giao dịch phải post vào TK con (1311, 1312...)
    // glBalance('131') chỉ lấy tổng hợp. Nếu có giao dịch post vào TK con mà không
    // được tổng hợp lên 131 → chênh lệch. Cần kiểm tra thêm trigger/tổng hợp.
    public function reconcileAr(): array
    {
        $glBal = $this->glBalance('131');
        $stmt = $this->pdo->query("SELECT COALESCE(SUM(balance), 0) FROM ar_invoices WHERE status != 'canceled'");
        $subBal = (float)$stmt->fetchColumn();
        return $this->result('ar', $glBal, $subBal, 'AR — TK 131');
    }

    // Đối chiếu công nợ phải trả — AP (TK 331)
    //
    // So sánh: Số dư GL của TK 331 (tổng hợp) vs Tổng dư nợ các hóa đơn AP
    //
    // Logic tương tự AR nhưng với TK 331 (bên Có tự nhiên)
    // Lưu ý: 331 có thể dư cả Nợ (trả trước cho NCC) hoặc Có (phải trả NCC)
    // Cần kiểm tra dấu của balance để xác định bản chất số dư.
    public function reconcileAp(): array
    {
        $glBal = $this->glBalance('331');
        $stmt = $this->pdo->query("SELECT COALESCE(SUM(balance), 0) FROM ap_invoices WHERE status != 'canceled'");
        $subBal = (float)$stmt->fetchColumn();
        return $this->result('ap', $glBal, $subBal, 'AP — TK 331');
    }

    // Đối chiếu tiền mặt — Cash (TK 111)
    //
    // So sánh:
    //   - GL balance 111 (control account) vs Σ GL balance của TK con (1111, 1112, 1113)
    //
    // Logic: GL 111 phải = 1111 + 1112 + 1113
    //   Vì 111 là control account, không được post trực tiếp vào 111.
    //   Mọi giao dịch đều post vào TK con → số dư tổng hợp phải khớp.
    //   Nếu GL 111 ≠ Σ con → có giao dịch post sai (vào 111 thay vì 1111)
    //   hoặc trigger tổng hợp không hoạt động.
    public function reconcileCash(): array
    {
        $glBal = $this->glBalance('111');
        // Tính tổng số dư các TK con của 111 (1111, 1112, 1113...)
        $stmt = $this->pdo->query("
            SELECT COALESCE(SUM(CASE WHEN le.is_debit = 1 THEN le.amount ELSE -le.amount END), 0)
            FROM ledger_entries le
            JOIN accounts a ON a.id = le.account_id
            WHERE a.code LIKE '111%'
        ");
        $cashTotal = (float)$stmt->fetchColumn();
        return $this->result('cash', $glBal, $cashTotal, 'Cash — TK 111');
    }

    // Đối chiếu tiền gửi ngân hàng — Bank (TK 112)
    //
    // So sánh: GL 112 (control) vs Σ GL của TK con (1121, 1122...)
    //
    // Logic tương tự Cash. Nếu có chênh lệch → kiểm tra posting vào TK con.
    //
    // Lưu ý: TK 1122 (Ngoại tệ) cần được đánh giá lại tỷ giá trước khi đối chiếu.
    // Nếu chưa đánh giá lại, số dư GL 1122 sẽ tính theo tỷ giá ghi sổ, không phải
    // tỷ giá cuối kỳ → chênh lệch với sao kê ngân hàng (bank statement).
    public function reconcileBank(): array
    {
        $glBal = $this->glBalance('112');
        $stmt = $this->pdo->query("
            SELECT COALESCE(SUM(CASE WHEN le.is_debit = 1 THEN le.amount ELSE -le.amount END), 0)
            FROM ledger_entries le
            JOIN accounts a ON a.id = le.account_id
            WHERE a.code LIKE '112%'
        ");
        $bankTotal = (float)$stmt->fetchColumn();
        return $this->result('bank', $glBal, $bankTotal, 'Bank — TK 112');
    }

    // Đối chiếu hàng tồn kho — Inventory
    //
    // So sánh: GL balance (152 + 153 + 155 + 156 + 157) vs Σ(qty * unit_cost) từ cost layers
    //
    // Logic: Giá trị tồn kho trên GL phải = giá trị tồn kho theo phương pháp tính giá
    // (FIFO/BQGQ/Thực tế đích danh) được lưu trong inventory_cost_layers.
    //
    // RỦI RO:
    //   - Nếu GL > Sub-ledger: đã nhập kho nhưng chưa cập nhật cost layer
    //     hoặc đã xóa cost layer nhưng chưa điều chỉnh GL
    //   - Nếu GL < Sub-ledger: đã xuất kho nhưng chưa ghi nhận giá vốn (632)
    //   - inventory_cost_layers.qty * unit_cost có thể sai nếu unit_cost = 0
    //     (lỗi nhập kho) hoặc qty âm (xuất quá số nhập)
    public function reconcileInventory(): array
    {
        // GL balance: tổng số dư các TK hàng tồn kho (Nợ = nhập, Có = xuất)
        $stmt = $this->pdo->query("
            SELECT COALESCE(SUM(CASE WHEN le.is_debit = 1 THEN le.amount ELSE -le.amount END), 0)
            FROM ledger_entries le
            JOIN accounts a ON a.id = le.account_id
            WHERE a.code IN ('152','153','155','156','157')
        ");
        $glBal = (float)$stmt->fetchColumn();

        // Sub-ledger: giá trị tồn kho từ cost layers (qty * đơn giá xuất)
        $stmt = $this->pdo->query("
            SELECT COALESCE(SUM(qty * unit_cost), 0) FROM inventory_cost_layers
        ");
        $subBal = (float)$stmt->fetchColumn();

        return $this->result('inventory', $glBal, $subBal, 'Inventory — TK 152/153/155/156/157');
    }

    // Đối chiếu tài sản cố định — Fixed Assets
    //
    // So sánh: GL (211 - 214) vs Σ(net_book_value) trên sổ TSCĐ
    //
    // Công thức: Giá trị còn lại = Nguyên giá (211) - Hao mòn lũy kế (214)
    // net_book_value = original_cost - accumulated_depreciation
    //
    // Logic: GL ghi nhận tổng giá trị TSCĐ theo từng bút toán mua sắm, khấu hao.
    // Sổ TSCĐ (fixed_assets) ghi chi tiết từng tài sản.
    // Hai số liệu phải khớp vì mọi biến động TSCĐ đều được ghi đồng thời cả 2 nơi.
    //
    // RỦI RO:
    //   - Mua TSCĐ: Nếu đã hạch toán Nợ 211/Có 331 nhưng chưa tạo fixed_assets record
    //     → GL > net_book_value
    //   - Khấu hao: Nếu đã hạch toán Nợ 642/Có 214 nhưng chưa cập nhật accumulated_depr
    //     → GL (214) > fixed_assets.depreciation → net_book_value chênh lệch
    //   - Thanh lý: Nếu đã xóa fixed_assets record nhưng chưa hạch toán giảm 211/214
    //     → GL > sub-ledger
    //
    // Filter: status != 'disposed' để loại trừ TSCĐ đã thanh lý
    public function reconcileFa(): array
    {
        // GL: Nguyên giá TSCĐ (211)
        $stmt = $this->pdo->query("
            SELECT COALESCE(SUM(CASE WHEN le.is_debit = 1 THEN le.amount ELSE -le.amount END), 0)
            FROM ledger_entries le
            JOIN accounts a ON a.id = le.account_id
            WHERE a.code = '211'
        ");
        $cost = (float)$stmt->fetchColumn();

        // GL: Hao mòn lũy kế (214) — TK điều chỉnh giảm (contra-asset, số dư Có)
        $stmt = $this->pdo->query("
            SELECT COALESCE(SUM(CASE WHEN le.is_debit = 1 THEN le.amount ELSE -le.amount END), 0)
            FROM ledger_entries le
            JOIN accounts a ON a.id = le.account_id
            WHERE a.code = '214'
        ");
        $depr = (float)$stmt->fetchColumn();
        $glBal = $cost - abs($depr);

        // Sub-ledger: tổng giá trị còn lại từ sổ chi tiết TSCĐ
        $stmt = $this->pdo->query("SELECT COALESCE(SUM(net_book_value), 0) FROM fixed_assets WHERE status != 'disposed'");
        $subBal = (float)$stmt->fetchColumn();

        return $this->result('fa', $glBal, $subBal, 'FA — TK 211-214');
    }
}
