<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Contract\JournalServiceInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\SupplierRepositoryInterface;

//
// CÔNG NỢ PHẢI TRẢ (TK 331): Quản lý toàn bộ nghiệp vụ mua hàng và thanh toán cho nhà cung cấp
// Các nghiệp vụ: Mua hàng (Nợ 156/152 + Nợ 1331 / Có 331), thanh toán, tạm ứng, trả lại, chiết khấu, xóa nợ
// TK 331 là control account — chỉ post vào chi tiết từng NCC, không post trực tiếp vào 331 tổng hợp
// Rủi ro: Sai số dư 331 → BC01 MS 310 (Phải trả NCC) sai → mất đối chiếu với NCC → rủi ro pháp lý
//
// ĐỐI SOÁT GL: Số dư 331 trên GL phải khớp với tổng số dư chi tiết từng NCC trong sub-ledger.
// Nếu lệch → mất audit trail → không thể xác nhận công nợ với NCC.
//
// GIAO DỊCH: Các method ghi nhận nghiệp vụ KHÔNG wrap trong beginTransaction/commit.
// Rủi ro: Nếu JournalService::postEntry thành công nhưng INSERT INTO ap_invoices thất bại
// → ghi nhận Nợ/Có nhưng mất dấu vết công nợ → số dư 331 lệch giữa GL và sub-ledger.
// Cần refactor: thêm PDO transaction bao quanh mọi multi-step write operation.
//
// CONCURRENCY: Không có SELECT FOR UPDATE trên ap_invoices hoặc suppliers.
// Rủi ro: Hai request thanh toán đồng thời trên cùng hóa đơn → double payment.
// Giải pháp: recordPayment cần khóa hàng (FOR UPDATE) trước khi đọc balance.
//
class ApService
{
    private \PDO $pdo;
    private SupplierRepositoryInterface $supplierRepo;
    private AccountRepositoryInterface $accountRepo;
    private JournalServiceInterface $journal;
    private ?AuditLoggerInterface $auditLogger;

    public function __construct(\PDO $pdo, SupplierRepositoryInterface $supplierRepo, AccountRepositoryInterface $accountRepo, JournalServiceInterface $journal, ?AuditLoggerInterface $auditLogger = null)
    {
        $this->pdo = $pdo;
        $this->supplierRepo = $supplierRepo;
        $this->accountRepo = $accountRepo;
        $this->journal = $journal;
        $this->auditLogger = $auditLogger;
    }

    // ── Invoice ──

    //
    // NGHIỆP VỤ MUA HÀNG: Ghi nhận hóa đơn mua hàng từ nhà cung cấp
    // Hạch toán: Nợ TK Hàng tồn kho (152/156) — Nợ TK 1331 (VAT đầu vào) — Có TK 331 (tổng giá thanh toán)
    // Rủi ro: Nhập sai thuế 1331 → ảnh hưởng tờ khai thuế GTGT đầu vào
    // Chỉ được khấu trừ VAT nếu có hóa đơn đỏ hợp lệ theo TT 32/2025/TT-BTC
    // Ảnh hưởng BC02: MS 24 (Giá vốn hàng bán) thay đổi khi hàng được bán ra
    //
    public function recordInvoice(string $supplierId, string $invoiceNumber, string $invoiceDate, string $dueDate, float $netAmount, float $vatAmount, float $vatRate, string $description, string $inventoryAccount, string $createdBy, float $vatRatePct = null): array
    {
        $this->pdo->beginTransaction();
        try {
            $supplier = $this->supplierRepo->findById($supplierId);
            if (!$supplier) throw new \InvalidArgumentException("Không tìm thấy nhà cung cấp: {$supplierId}. Vui lòng kiểm tra lại mã nhà cung cấp.");

            $totalAmount = $netAmount + $vatAmount;
            $newBal = $supplier->getBalance() + $totalAmount;
            $cl = $supplier->getCreditLimit();
            if ($cl > 0 && $newBal > $cl) {
                throw new \InvalidArgumentException("Công nợ phải trả vượt quá hạn mức tín dụng của nhà cung cấp {$supplier->getName()}. Số dư hiện tại {$newBal} VND, hạn mức {$cl} VND.");
            }
            $vatRatePct = $vatRatePct ?? $vatRate;

            $lines = [['account_code' => $inventoryAccount, 'amount' => $netAmount, 'is_debit' => true]];
            if ($vatAmount > 0) {
                $lines[] = ['account_code' => '1331', 'amount' => $vatAmount, 'is_debit' => true];
            }
            $lines[] = ['account_code' => '331', 'amount' => $totalAmount, 'is_debit' => false];

            $txn = $this->journal->postEntry("AP invoice: {$description}", "INV-{$invoiceNumber}", $lines, $createdBy);

            $stmt = $this->pdo->prepare(
                'INSERT INTO ap_invoices (supplier_id, invoice_number, invoice_date, due_date, gross_amount, net_amount, vat_amount, vat_rate, balance, status, description, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$supplierId, $invoiceNumber, $invoiceDate, $dueDate, $totalAmount, $netAmount, $vatAmount, $vatRatePct, $totalAmount, 'unpaid', $description, $createdBy]);
            $invoiceId = (int)$this->pdo->lastInsertId();

            $supplier->setBalance($supplier->getBalance() + $totalAmount);
            $this->supplierRepo->save($supplier);

            $this->auditLogger?->log('ap.invoice', 'ap_invoice', (string)$invoiceId, null,
                ['supplier' => $supplierId, 'amount' => $totalAmount, 'invoice' => $invoiceNumber], $createdBy);

            $this->pdo->commit();
            return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $totalAmount];
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    // ── Payment ──

    //
    // NGHIỆP VỤ THANH TOÁN: Trả tiền cho nhà cung cấp
    // Hạch toán: Nợ 331 — Có 111 (tiền mặt) / Có 112 (tiền gửi ngân hàng)
    // Ràng buộc: Không thanh toán quá số dư hóa đơn, kiểm tra hóa đơn chưa tất toán
    // Tác động: Giảm số dư 331 trên BC01, giảm tiền trên BC03 MS 01-03
    //
    public function recordPayment(int $invoiceId, float $amount, string $createdBy): array
    {
        $this->pdo->beginTransaction();
        try {
            // SELECT FOR UPDATE: Khóa hàng ap_invoices để chống double payment dưới concurrent.
            // Nếu 2 request đến cùng lúc, request thứ 2 chờ request thứ 1 commit mới đọc được
            // balance đã cập nhật → thanh toán không vượt quá số dư.
            $stmt = $this->pdo->prepare('SELECT * FROM ap_invoices WHERE id = ? FOR UPDATE');
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$invoice) throw new \InvalidArgumentException("Không tìm thấy hóa đơn mã {$invoiceId}.");
            if ($invoice['status'] === 'paid') throw new \InvalidArgumentException("Hóa đơn này đã được thanh toán. Không thể thực hiện lại.");

            $payAmount = min($amount, $invoice['balance']);
            if ($payAmount <= 0) throw new \InvalidArgumentException("Hóa đơn không còn số dư để thanh toán.");

            $txn = $this->journal->postEntry("AP payment: {$invoice['invoice_number']}", "PAY-{$invoiceId}", [
                ['account_code' => '331', 'amount' => $payAmount, 'is_debit' => true],
                ['account_code' => '112', 'amount' => $payAmount, 'is_debit' => false],
            ], $createdBy);

            $newPaid = $invoice['paid_amount'] + $payAmount;
            $newBalance = $invoice['gross_amount'] - $newPaid;
            $newStatus = $newBalance <= 1 ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');

            $this->pdo->prepare('UPDATE ap_invoices SET paid_amount = ?, balance = ?, status = ? WHERE id = ?')
                ->execute([$newPaid, max(0, $newBalance), $newStatus, $invoiceId]);

            $this->pdo->prepare('INSERT INTO ap_payments (ap_invoice_id, transaction_id, amount, payment_type) VALUES (?, ?, ?, ?)')
                ->execute([$invoiceId, $txn->getId(), $payAmount, 'payment']);

            $supplier = $this->supplierRepo->findById($invoice['supplier_id']);
            if ($supplier) {
                $supplier->setBalance(max(0, $supplier->getBalance() - $payAmount));
                $this->supplierRepo->save($supplier);
            }

            $this->auditLogger?->log('ap.payment', 'ap_invoice', (string)$invoiceId,
                ['balance_before' => $invoice['balance']], ['payment' => $payAmount, 'balance_after' => max(0, $newBalance)], $createdBy);

            $this->pdo->commit();
            return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $payAmount, 'balance' => max(0, $newBalance)];
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    //
    // NGHIỆP VỤ TẠM ỨNG: Trả trước tiền cho nhà cung cấp khi chưa nhận được hàng
    // Hạch toán: Nợ 331 — Có 111/112
    // TK 331 có thể có số dư bên Nợ (dư Nợ 331) thể hiện "khoản phải thu NCC" — tạm ứng chưa thanh toán
    // Quản lý rủi ro: Nếu NCC không giao hàng → khó thu hồi tạm ứng, cần hợp đồng chặt chẽ
    //
    public function recordPrepayment(string $supplierId, float $amount, string $description, string $createdBy): array
    {
        $this->pdo->beginTransaction();
        try {
            $supplier = $this->supplierRepo->findById($supplierId);
            if (!$supplier) throw new \InvalidArgumentException("Không tìm thấy thông tin nhà cung cấp.");

            $txn = $this->journal->postEntry("AP prepayment: {$description}", "PRE-{$supplierId}", [
                ['account_code' => '331', 'amount' => $amount, 'is_debit' => true],
                ['account_code' => '112', 'amount' => $amount, 'is_debit' => false],
            ], $createdBy);

            $this->pdo->prepare(
                'INSERT INTO ap_invoices (supplier_id, invoice_number, invoice_date, due_date, gross_amount, net_amount, balance, status, description, created_by)
                 VALUES (?, ?, CURDATE(), CURDATE(), ?, 0, ?, ?, ?, ?)'
            )->execute([$supplierId, "PRE-{$txn->getId()}", -$amount, -$amount, 'prepayment', $description, $createdBy]);
            $invId = (int)$this->pdo->lastInsertId();

            $supplier->setBalance($supplier->getBalance() - $amount);
            $this->supplierRepo->save($supplier);

            $this->pdo->commit();
            return ['invoice_id' => $invId, 'transaction_id' => $txn->getId(), 'amount' => $amount];
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    // ── Return ──

    //
    // NGHIỆP VỤ TRẢ LẠI HÀNG MUA: Trả lại hàng cho nhà cung cấp (hàng kém chất lượng, sai quy cách)
    // Hạch toán: Nợ 331 — Có TK Hàng tồn kho — Có 1331 (thuế GTGT đầu vào hoàn lại)
    // Tác động 3 mặt: (1) Giảm công nợ 331, (2) Giảm giá trị hàng tồn kho, (3) Giảm VAT được khấu trừ
    // Yêu cầu: Phải có biên bản trả hàng và hóa đơn điều chỉnh giảm theo TT 32/2025/TT-BTC
    //
    public function recordReturn(int $invoiceId, float $returnAmount, string $inventoryAccount, string $createdBy): array
    {
        $this->pdo->beginTransaction();
        try {
            $invoice = $this->getInvoice($invoiceId);
            if ($invoice['balance'] <= 0) throw new \InvalidArgumentException("Hóa đơn đã được thanh toán đủ. Vui lòng sử dụng bút toán điều chỉnh riêng.");

            $vatReverse = $invoice['vat_rate'] > 0 ? round($returnAmount * $invoice['vat_rate'] / (100 + $invoice['vat_rate']), 0) : 0;
            $netReturn = $returnAmount - $vatReverse;

            $txn = $this->journal->postEntry("AP return: {$invoice['invoice_number']}", "RET-{$invoiceId}", [
                ['account_code' => '331', 'amount' => $returnAmount, 'is_debit' => true],
                ['account_code' => $inventoryAccount, 'amount' => $netReturn, 'is_debit' => false],
                ['account_code' => '1331', 'amount' => $vatReverse, 'is_debit' => false],
            ], $createdBy);

            $newBalance = $invoice['balance'] - $returnAmount;
            $this->pdo->prepare('UPDATE ap_invoices SET balance = ? WHERE id = ?')
                ->execute([max(0, $newBalance), $invoiceId]);

            $this->pdo->prepare('INSERT INTO ap_payments (ap_invoice_id, transaction_id, amount, payment_type) VALUES (?, ?, ?, ?)')
                ->execute([$invoiceId, $txn->getId(), $returnAmount, 'return']);

            $supplier = $this->supplierRepo->findById($invoice['supplier_id']);
            if ($supplier) {
                $supplier->setBalance(max(0, $supplier->getBalance() - $returnAmount));
                $this->supplierRepo->save($supplier);
            }

            $this->pdo->commit();
            return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $returnAmount];
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    // ── Discount ──

    //
    // NGHIỆP VỤ CHIẾT KHẤU THANH TOÁN ĐƯỢC HƯỞNG: NCC giảm giá khi DN thanh toán sớm
    // Hạch toán: Nợ 331 — Có 515 (Doanh thu hoạt động tài chính)
    // Tác động BC02: MS 23 (Chi phí tài chính) giảm tương đối — vì 515 là doanh thu tài chính
    // Phân biệt: Chiết khấu thương mại (giảm giá mua) hạch toán giảm giá vốn, không qua 515
    //
    public function recordDiscount(int $invoiceId, float $discountAmount, string $createdBy): array
    {
        $this->pdo->beginTransaction();
        try {
            $invoice = $this->getInvoice($invoiceId);
            if ($discountAmount > $invoice['balance']) throw new \InvalidArgumentException("Số tiền chiết khấu không được lớn hơn số dư còn lại của hóa đơn.");

            $txn = $this->journal->postEntry("AP discount: {$invoice['invoice_number']}", "DISC-{$invoiceId}", [
                ['account_code' => '331', 'amount' => $discountAmount, 'is_debit' => true],
                ['account_code' => '515', 'amount' => $discountAmount, 'is_debit' => false],
            ], $createdBy);

            $newBalance = $invoice['balance'] - $discountAmount;
            $this->pdo->prepare('UPDATE ap_invoices SET balance = ? WHERE id = ?')
                ->execute([max(0, $newBalance), $invoiceId]);

            $this->pdo->prepare('INSERT INTO ap_payments (ap_invoice_id, transaction_id, amount, payment_type) VALUES (?, ?, ?, ?)')
                ->execute([$invoiceId, $txn->getId(), $discountAmount, 'discount']);

            $supplier = $this->supplierRepo->findById($invoice['supplier_id']);
            if ($supplier) {
                $supplier->setBalance(max(0, $supplier->getBalance() - $discountAmount));
                $this->supplierRepo->save($supplier);
            }

            $this->pdo->commit();
            return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $discountAmount];
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    // ── Write-off ──

    //
    // NGHIỆP VỤ XÓA NỢ PHẢI TRẢ: Xóa số dư công nợ không còn nghĩa vụ thanh toán
    // Hạch toán: Nợ 331 — Có 711 (Thu nhập khác)
    // Áp dụng: NCC giải thể, phá sản, hết thời hiệu khởi kiện, hoặc thỏa thuận xóa nợ song phương
    // Rủi ro thuế: Khoản xóa nợ phải trả có thể bị tính thuế TNDN — cần tư vấn thuế trước khi xóa
    //
    public function writeOff(int $invoiceId, string $createdBy): array
    {
        // NGHIỆP VỤ XÓA NỢ PHẢI TRẢ: Xóa khoản nợ không còn nghĩa vụ thanh toán.
        // Hạch toán: Nợ 331 / Có 711 (Thu nhập khác).
        //
        // RỦI RO THUẾ TNDN: Khoản xóa nợ phải trả được coi là thu nhập chịu thuế.
        // Theo Thông tư 78/2014/TT-BTC: Nợ phải trả không xác định được chủ nợ, hết thời hiệu khởi kiện
        // → ghi nhận vào thu nhập khác (711) → tính thuế TNDN.
        // Cần tư vấn thuế trước khi xóa — nếu xóa sai → truy thu thuế + phạt.
        //
        // THỦ TỤC NỘI BỘ: Phải có biên bản Hội đồng xóa nợ, phê duyệt của TGĐ.
        // Lưu hồ sơ đầy đủ: hợp đồng, biên bản đối chiếu, thông báo xóa nợ.
        //
        // ẢNH HƯỞNG BC02: MS 31 (Thu nhập khác - 711) tăng → lợi nhuận tăng → thuế TNDN tăng.
        //
        $this->pdo->beginTransaction();
        try {
            $invoice = $this->getInvoice($invoiceId);
            if ($invoice['balance'] <= 0) throw new \InvalidArgumentException("Hóa đơn này không còn số dư để xóa sổ.");

            $amount = $invoice['balance'];

            $txn = $this->journal->postEntry("AP write-off: {$invoice['invoice_number']}", "WO-{$invoiceId}", [
                ['account_code' => '331', 'amount' => $amount, 'is_debit' => true],
                ['account_code' => '711', 'amount' => $amount, 'is_debit' => false],
            ], $createdBy);

            $this->pdo->prepare('UPDATE ap_invoices SET balance = 0, status = ? WHERE id = ?')
                ->execute(['written_off', $invoiceId]);

            $supplier = $this->supplierRepo->findById($invoice['supplier_id']);
            if ($supplier) {
                $supplier->setBalance(max(0, $supplier->getBalance() - $amount));
                $this->supplierRepo->save($supplier);
            }

            $this->pdo->commit();
            return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $amount];
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    // ── Reports ──

    //
    // BÁO CÁO TUỔI NỢ PHẢI TRẢ: Phân loại công nợ NCC theo thời gian quá hạn
    // Mục đích: Quản lý dòng tiền thanh toán, ưu tiên xử lý nợ đến hạn, tránh phạt quá hạn
    // Các bucket chuẩn: Hiện tại (chưa đến hạn), 1-30 ngày, 31-60, 61-90, 90+ ngày
    // Sử dụng: Lập kế hoạch thanh toán, đánh giá uy tín NCC, thương lượng thời hạn thanh toán
    //
    // ĐỘ CHÍNH XÁC CỦA AGING: Phụ thuộc vào due_date của hóa đơn. Nếu nhập sai due_date
    // → aging sai → quyết định thanh toán sai (trả tiền NCC quá hạn, mất uy tín).
    //
    // GIỚI HẠN: Chỉ báo cáo hóa đơn có balance > 1 VND và không phải prepayment.
    // Prepayment (số âm) bị loại trừ — cần báo cáo riêng để theo dõi tạm ứng.
    //
    public function getAgingReport(): array
    {
        $rows = $this->pdo->query(
            "SELECT i.*, s.name as supplier_name, s.code as supplier_code
             FROM ap_invoices i
             JOIN suppliers s ON s.id = i.supplier_id
             WHERE i.balance > 1 AND i.status != 'prepayment'
             ORDER BY i.due_date ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $buckets = ['current' => [], '1-30' => [], '31-60' => [], '61-90' => [], '90plus' => []];
        $totals = ['current' => 0, '1-30' => 0, '31-60' => 0, '61-90' => 0, '90plus' => 0];

        foreach ($rows as $r) {
            $days = (int)date_diff(date_create($r['due_date']), date_create('today'))->format('%a');
            $isOverdue = date_create($r['due_date']) < date_create('today');

            if (!$isOverdue) { $bucket = 'current'; }
            elseif ($days <= 30) { $bucket = '1-30'; }
            elseif ($days <= 60) { $bucket = '31-60'; }
            elseif ($days <= 90) { $bucket = '61-90'; }
            else { $bucket = '90plus'; }

            $r['aging_days'] = $isOverdue ? $days : 0;
            $buckets[$bucket][] = $r;
            $totals[$bucket] += $r['balance'];
        }

        return ['buckets' => $buckets, 'totals' => $totals, 'grand_total' => array_sum($totals)];
    }

    //
    // SAO KÊ CÔNG Nợ NCC: Chi tiết tất cả hóa đơn, thanh toán, trả lại, chiết khấu của một NCC
    // Mục đích: Đối chiếu công nợ với NCC theo định kỳ (cuối tháng/quý) — cơ sở xác nhận số dư 331
    // Nếu chênh lệch → điều chỉnh kịp thời trước khi khóa sổ
    //
    public function getSupplierStatement(string $supplierId): array
    {
        $invoices = $this->pdo->prepare(
            'SELECT i.*, s.name as supplier_name FROM ap_invoices i JOIN suppliers s ON s.id = i.supplier_id WHERE i.supplier_id = ? ORDER BY i.invoice_date DESC'
        );
        $invoices->execute([$supplierId]);
        return $invoices->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getInvoices(string $status = null, string $supplierId = null): array
    {
        $sql = 'SELECT i.*, s.name as supplier_name FROM ap_invoices i JOIN suppliers s ON s.id = i.supplier_id WHERE 1=1';
        $params = [];
        if ($status) { $sql .= ' AND i.status = ?'; $params[] = $status; }
        if ($supplierId) { $sql .= ' AND i.supplier_id = ?'; $params[] = $supplierId; }
        $sql .= ' ORDER BY i.created_at DESC LIMIT 200';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getInvoice(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT i.*, s.name as supplier_name FROM ap_invoices i JOIN suppliers s ON s.id = i.supplier_id WHERE i.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getPayments(int $invoiceId): array
    {
        $stmt = $this->pdo->prepare('SELECT p.*, t.description, t.reference, t.created_at as txn_date FROM ap_payments p JOIN transactions t ON t.id = p.transaction_id WHERE p.ap_invoice_id = ? ORDER BY p.created_at');
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getSuppliers(): array
    {
        $rows = $this->pdo->query('SELECT id, code, name, balance FROM suppliers ORDER BY name')->fetchAll(\PDO::FETCH_ASSOC);
        return $rows;
    }

    //
    // PHÂN BỔ THANH TOÁN NHIỀU HÓA ĐƠN: 1 lần chi tiền thanh toán nhiều hóa đơn NCC.
    // Input: allocations = [['invoice_id' => 1, 'amount' => 500000], ...]
    // Yêu cầu: Tất cả hóa đơn phải cùng một NCC và không được vượt quá số dư từng hóa đơn.
    //
    // NGHIỆP VỤ THỰC TẾ: Cuối tháng kế toán tổng hợp các hóa đơn cần thanh toán cho 1 NCC,
    // lập ủy nhiệm chi tổng số tiền, sau đó phân bổ vào từng hóa đơn.
    // Hạch toán: Nợ 331 (tổng số tiền) — Có 112 (tổng số tiền).
    //
    public function allocatePayment(array $allocations, string $paymentAccount, string $description, string $createdBy): array
    {
        $this->pdo->beginTransaction();
        try {
            $totalAmount = 0;
            $supplierId = null;
            $validated = [];

            foreach ($allocations as $i => $alloc) {
                $invoice = $this->getInvoice($alloc['invoice_id']);
                if (!$invoice) throw new \InvalidArgumentException("Không tìm thấy hóa đơn mã {$alloc['invoice_id']} trong hệ thống.");
                if ($i === 0) { $supplierId = $invoice['supplier_id']; }
                if ($invoice['supplier_id'] !== $supplierId) {
                    throw new \InvalidArgumentException("Tất cả hóa đơn phân bổ phải cùng một nhà cung cấp.");
                }
                if ($invoice['status'] === 'paid') {
                    throw new \InvalidArgumentException("Hóa đơn {$alloc['invoice_id']} đã được thanh toán xong.");
                }
                $amt = min($alloc['amount'], $invoice['balance']);
                if ($amt <= 0) throw new \InvalidArgumentException("Hóa đơn {$alloc['invoice_id']} không còn số dư để phân bổ.");
                if ($amt != $alloc['amount']) {
                    throw new \InvalidArgumentException("Số tiền phân bổ {$amt} VND vượt quá số dư {$invoice['balance']} VND của hóa đơn {$alloc['invoice_id']}.");
                }
                $totalAmount += $amt;
                $validated[] = ['invoice' => $invoice, 'amount' => $amt];
            }

            $txn = $this->journal->postEntry("AP allocation: {$description}", "ALLOC-{$supplierId}", [
                ['account_code' => '331', 'amount' => $totalAmount, 'is_debit' => true],
                ['account_code' => $paymentAccount, 'amount' => $totalAmount, 'is_debit' => false],
            ], $createdBy);

            $payStmt = $this->pdo->prepare('INSERT INTO payment_allocations (payment_type, transaction_id, invoice_id, amount) VALUES (?, ?, ?, ?)');
            $invUpd = $this->pdo->prepare('UPDATE ap_invoices SET paid_amount = paid_amount + ?, balance = GREATEST(balance - ?, 0), status = ? WHERE id = ?');

            foreach ($validated as $v) {
                $inv = $v['invoice'];
                $amt = $v['amount'];
                $newPaid = $inv['paid_amount'] + $amt;
                $newBal = $inv['gross_amount'] - $newPaid;
                $newStatus = $newBal <= 1 ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');

                $payId = $txn->getId();
                $payStmt->execute(['ap', $payId, $inv['id'], $amt]);
                $invUpd->execute([$amt, $amt, $newStatus, $inv['id']]);
            }

            $supplier = $this->supplierRepo->findById($supplierId);
            if ($supplier) {
                $supplier->setBalance(max(0, $supplier->getBalance() - $totalAmount));
                $this->supplierRepo->save($supplier);
            }

            $this->auditLogger?->log('ap.allocate', 'ap_payment', (string)$txn->getId(),
                null, ['allocations' => $allocations, 'total' => $totalAmount], $createdBy);

            $this->pdo->commit();
            return ['transaction_id' => $txn->getId(), 'total_amount' => $totalAmount, 'allocations' => $validated];
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    //
    // LẤY PHÂN BỔ THANH TOÁN: Trả về danh sách các hóa đơn được thanh toán từ 1 giao dịch.
    // Kết hợp dữ liệu từ payment_allocations với thông tin hóa đơn để hiển thị chi tiết.
    //
    public function getAllocationDetails(string $transactionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pa.*, i.invoice_number, i.supplier_id, s.name as supplier_name
             FROM payment_allocations pa
             JOIN ap_invoices i ON i.id = pa.invoice_id
             JOIN suppliers s ON s.id = i.supplier_id
             WHERE pa.payment_type = ? AND pa.transaction_id = ?
             ORDER BY pa.id'
        );
        $stmt->execute(['ap', $transactionId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
