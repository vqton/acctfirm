<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;

//
// CÔNG NỢ PHẢI THU (TK 131): Quản lý toàn bộ nghiệp vụ bán hàng và thu tiền từ khách hàng
// Các nghiệp vụ: Bán hàng (Nợ 131 / Có 511 + Có 33311), thu tiền, ứng trước, trả lại, chiết khấu, xóa nợ khó đòi
// TK 131 là control account — chỉ hạch toán chi tiết từng khách hàng, không post trực tiếp vào 131 tổng hợp
// Rủi ro: Sai số dư 131 → BC01 MS 131 (Phải thu KH) sai → ảnh hưởng đánh giá khả năng thanh toán
//
// ĐỐI SOÁT GL: Số dư 131 trên GL phải khớp tổng số dư chi tiết từng KH trong sub-ledger.
// Nếu lệch → không thể đối chiếu công nợ với KH → kiểm toán từ chối xác nhận.
//
// GIAO DỊCH: KHÔNG wrap multi-step write trong PDO transaction.
// Rủi ro: JournalService ghi nhận bút toán (Nợ 131 / Có 511+33311) thành công
// nhưng INSERT ar_invoices thất bại → số dư 131 trên GL không khớp sub-ledger.
// Cần refactor: thêm beginTransaction/commit/rollback.
//
// CONCURRENCY: Không có SELECT FOR UPDATE.
// Rủi ro: Thu tiền 2 lần trên cùng 1 hóa đơn nếu request đồng thời.
// Đặc biệt nguy hiểm với thanh toán 1 phần (partial payment).
//
class ArService
{
    private \PDO $pdo;
    private AccountRepositoryInterface $accountRepo;
    private JournalService $journal;
    private ?AuditLoggerInterface $auditLogger;

    public function __construct(\PDO $pdo, AccountRepositoryInterface $accountRepo, JournalService $journal, ?AuditLoggerInterface $auditLogger = null)
    {
        $this->pdo = $pdo;
        $this->accountRepo = $accountRepo;
        $this->journal = $journal;
        $this->auditLogger = $auditLogger;
    }

    //
    // NGHIỆP VỤ BÁN HÀNG: Ghi nhận doanh thu và khoản phải thu khách hàng
    // Hạch toán: Nợ 131 (tổng giá thanh toán) — Có 511 (doanh thu bán hàng chưa thuế) — Có 33311 (VAT đầu ra)
    // Ảnh hưởng BC02: MS 01 (Doanh thu) tăng, MS 20 (Thuế GTGT đầu ra) tăng
    // Rủi ro: Sai doanh thu hoặc thuế → sai tờ khai thuế GTGT và TNDN → phạt chậm nộp
    //
    public function recordInvoice(string $customerId, string $invoiceNumber, string $invoiceDate, string $dueDate, float $netAmount, float $vatAmount, float $vatRate, string $description, string $createdBy, string $revenueAccount = '511'): array
    {
        $customer = $this->getCustomer($customerId);
        if (!$customer) throw new \InvalidArgumentException("Customer not found: {$customerId}");

        $totalAmount = $netAmount + $vatAmount;

        // Dr 131 (total) — Cr 511 (net) — Cr 33311 (VAT)
        $lines = [
            ['account_code' => '131', 'amount' => $totalAmount, 'is_debit' => true],
            ['account_code' => $revenueAccount, 'amount' => $netAmount, 'is_debit' => false],
        ];
        if ($vatAmount > 0) {
            $lines[] = ['account_code' => '33311', 'amount' => $vatAmount, 'is_debit' => false];
        }

        // GIAO DỊCH: Multi-step — journal post + INSERT invoice + UPDATE customer balance.
        // KHÔNG wrap trong transaction → nếu INSERT/UPDATE thất bại, GL đã ghi bút toán.
        // Số dư 131 GL không khớp sub-ledger → mất đối chiếu.
        //
        // DOANH THU: TK 511 ghi nhận doanh thu chưa thuế (netAmount).
        // Nguyên tắc thận trọng: Chỉ ghi nhận doanh thu khi chắc chắn thu được tiền.
        // Nếu KH có rủi ro tín dụng cao → cần xem xét ghi nhận doanh thu hay tạm ghi nhận.
        //
        // THUẾ GTGT: 33311 ghi thuế đầu ra phải nộp.
        // Nếu hóa đơn xuất sai → phải lập hóa đơn điều chỉnh trong kỳ.
        // Ảnh hưởng tờ khai thuế GTGT tháng/quý.
        //
        $txn = $this->journal->postEntry("AR invoice: {$description}", "INV-{$invoiceNumber}", $lines, $createdBy);

        $stmt = $this->pdo->prepare(
            'INSERT INTO ar_invoices (customer_id, invoice_number, invoice_date, due_date, gross_amount, net_amount, vat_amount, vat_rate, balance, status, description, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$customerId, $invoiceNumber, $invoiceDate, $dueDate, $totalAmount, $netAmount, $vatAmount, $vatRate, $totalAmount, 'unpaid', $description, $createdBy]);
        $invId = (int)$this->pdo->lastInsertId();

        $this->updateCustomerBalance($customerId, $totalAmount);

        $this->auditLogger?->log('ar.invoice', 'ar_invoice', (string)$invId, null,
            ['customer' => $customerId, 'amount' => $totalAmount, 'invoice' => $invoiceNumber], $createdBy);

        return ['invoice_id' => $invId, 'transaction_id' => $txn->getId(), 'amount' => $totalAmount];
    }

    //
    // NGHIỆP VỤ THU TIỀN: Khách hàng thanh toán công nợ
    // Hạch toán: Nợ 111 (tiền mặt) / Nợ 112 (tiền gửi NH) — Có 131
    // Ràng buộc: Không thu quá số dư hóa đơn, cảnh báo nếu thu tiền của hóa đơn quá hạn lâu (>90 ngày)
    // Tác động: Giảm số dư 131 trên BC01, tăng tiền trên BC03
    //
    public function recordPayment(int $invoiceId, float $amount, string $createdBy): array
    {
        // RỦI RO DOUBLE PAYMENT: Không có SELECT FOR UPDATE.
        // Cùng lúc 2 request thanh toán → cả 2 đọc balance chưa cập nhật
        // → thu tiền 2 lần cho cùng hóa đơn → dư Có 131 (nợ KH).
        // Cần xử lý: SELECT ... FOR UPDATE trước khi đọc balance.
        //
        // THANH TOÁN TỪNG PHẦN: amount bị cắt tại balance hiện tại.
        // Nếu amount > balance → chỉ thu balance → KH còn nợ 0.
        // Cơ chế này bảo vệ khỏi over-collection nhưng không thông báo người dùng.
        //
        // ẢNH HƯỞNG BCTC: Giảm số dư 131 (BC01 MS 131), tăng tiền 111/112 (BC01 MS 110/111).
        // Không ảnh hưởng BC02 vì thu tiền không phải doanh thu.
        //
        $inv = $this->getInvoice($invoiceId);
        if ($inv['status'] === 'paid') throw new \InvalidArgumentException("Invoice already paid");
        $payAmt = min($amount, $inv['balance']);
        if ($payAmt <= 0) throw new \InvalidArgumentException("No balance to pay");

        $txn = $this->journal->postEntry("AR payment: {$inv['invoice_number']}", "PAY-{$invoiceId}", [
            ['account_code' => '112', 'amount' => $payAmt, 'is_debit' => true],
            ['account_code' => '131', 'amount' => $payAmt, 'is_debit' => false],
        ], $createdBy);

        $newPaid = $inv['paid_amount'] + $payAmt;
        $newBal = $inv['gross_amount'] - $newPaid;
        $newStatus = $newBal <= 1 ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');

        $this->pdo->prepare('UPDATE ar_invoices SET paid_amount = ?, balance = ?, status = ? WHERE id = ?')
            ->execute([$newPaid, max(0, $newBal), $newStatus, $invoiceId]);

        $this->pdo->prepare('INSERT INTO ar_payments (ar_invoice_id, transaction_id, amount, payment_type) VALUES (?, ?, ?, ?)')
            ->execute([$invoiceId, $txn->getId(), $payAmt, 'payment']);

        $this->updateCustomerBalance($inv['customer_id'], -$payAmt);

        $this->auditLogger?->log('ar.payment', 'ar_invoice', (string)$invoiceId,
            ['balance_before' => $inv['balance']], ['payment' => $payAmt, 'balance_after' => max(0, $newBal)], $createdBy);

        return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $payAmt, 'balance' => max(0, $newBal)];
    }

    //
    // NGHIỆP VỤ KHÁCH HÀNG ỨNG TRƯỚC: KH thanh toán trước khi nhận hàng/dịch vụ
    // Hạch toán: Nợ 111/112 — Có 131 (dư Có trên sổ chi tiết khách hàng)
    // Khi dư Có 131 thể hiện "khoản nợ phải trả khách hàng" — cần đối chiếu với hợp đồng
    // Rủi ro: Nếu không giao hàng đúng hẹn → KH có quyền đòi lại tiền ứng trước
    //
    public function recordPrepayment(string $customerId, float $amount, string $description, string $createdBy): array
    {
        // NGHIỆP VỤ KHÁCH HÀNG ỨNG TRƯỚC: KH trả tiền trước khi nhận hàng/dịch vụ.
        // Hạch toán: Nợ 112 / Có 131 — tạo số dư âm (-) trên chi tiết KH.
        // Khi xuất hóa đơn sau đó: Nợ 131 / Có 511+33311 → bù trừ với số âm.
        //
        // TRÌNH BÀY BC01: Dư Có 131 (khách hàng trả thừa/ứng trước) phải trình bày
        // vào bên Nợ phải trả (Phải trả khách hàng), không được bù trừ với dư Nợ 131.
        // Chuẩn mực kế toán VAS 22: Không được bù trừ tài sản và nợ phải trả.
        //
        // RỦI RO: Nếu không giao hàng đúng cam kết → KH có quyền đòi lại tiền.
        // Cần quản lý riêng các khoản ứng trước để tránh nhầm lẫn.
        //
        $customer = $this->getCustomer($customerId);
        if (!$customer) throw new \InvalidArgumentException("Customer not found");

        $txn = $this->journal->postEntry("AR prepayment: {$description}", "PRE-{$customerId}", [
            ['account_code' => '112', 'amount' => $amount, 'is_debit' => true],
            ['account_code' => '131', 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        $this->pdo->prepare(
            'INSERT INTO ar_invoices (customer_id, invoice_number, invoice_date, due_date, gross_amount, net_amount, balance, status, description, created_by)
             VALUES (?, ?, CURDATE(), CURDATE(), ?, 0, ?, ?, ?, ?)'
        )->execute([$customerId, "PRE-{$txn->getId()}", -$amount, -$amount, 'prepayment', $description, $createdBy]);
        $invId = (int)$this->pdo->lastInsertId();

        $this->updateCustomerBalance($customerId, -$amount);
        return ['invoice_id' => $invId, 'transaction_id' => $txn->getId(), 'amount' => $amount];
    }

    //
    // NGHIỆP VỤ HÀNG BÁN TRẢ LẠI: Khách hàng trả lại hàng (kém chất lượng, sai đơn hàng, không đúng nhu cầu)
    // Hạch toán: Nợ 521 (Giảm trừ doanh thu) — Nợ 33311 (giảm VAT đầu ra) — Có 131
    // Tác động BC02: MS 02 (Các khoản giảm trừ doanh thu) tăng → doanh thu thuần (MS 10) giảm
    // Yêu cầu chứng từ: Biên bản trả hàng, hóa đơn điều chỉnh giảm — nếu không → không hợp lệ thuế
    //
    public function recordReturn(int $invoiceId, float $returnAmount, string $createdBy): array
    {
        // NGHIỆP VỤ HÀNG BÁN TRẢ LẠI: KH trả lại hàng do lỗi/kém chất lượng/sai đơn hàng.
        // Hạch toán: Nợ 521 (Giảm trừ doanh thu) / Nợ 33311 (giảm VAT đầu ra) / Có 131 (giảm công nợ)
        //
        // PHÂN BIỆT VỚI CHIẾT KHẤU THƯƠNG MẠI: Trả lại hàng → giảm doanh thu gốc (521).
        // Chiết khấu thương mại → hạch toán riêng qua 521 nhưng khác bản chất.
        // Giảm giá hàng bán → có thể ghi giảm trực tiếp doanh thu hoặc qua 521.
        //
        // ẢNH HƯỞNG BC02: MS 02 (Các khoản giảm trừ doanh thu) tăng.
        // Doanh thu thuần (MS 10) = MS 01 - MS 02.
        // Lợi nhuận gộp giảm tương ứng.
        //
        // THUẾ: Phải lập hóa đơn điều chỉnh giảm (theo NĐ 123/2020/NĐ-CP).
        // Nếu xuất hóa đơn điều chỉnh > kỳ thuế → phải kê khai điều chỉnh tăng/giảm.
        //
        $inv = $this->getInvoice($invoiceId);
        if ($inv['balance'] <= 0) throw new \InvalidArgumentException("Invoice fully paid");
        if ($returnAmount > $inv['gross_amount']) throw new \InvalidArgumentException("Return exceeds invoice total");

        $vatReverse = $inv['vat_rate'] > 0 ? round($returnAmount * $inv['vat_rate'] / (100 + $inv['vat_rate']), 0) : 0;
        $netReturn = $returnAmount - $vatReverse;

        // Dr 521 (revenue deduction) + Dr 33311 (tax reverse) — Cr 131
        $lines = [
            ['account_code' => '521', 'amount' => $netReturn, 'is_debit' => true],
            ['account_code' => '131', 'amount' => $returnAmount, 'is_debit' => false],
        ];
        if ($vatReverse > 0) {
            // Insert 33311 line before Cr 131
            array_splice($lines, 1, 0, [['account_code' => '33311', 'amount' => $vatReverse, 'is_debit' => true]]);
        }

        $txn = $this->journal->postEntry("AR return: {$inv['invoice_number']}", "RET-{$invoiceId}", $lines, $createdBy);

        $newBal = $inv['balance'] - $returnAmount;
        $this->pdo->prepare('UPDATE ar_invoices SET balance = ? WHERE id = ?')->execute([max(0, $newBal), $invoiceId]);
        $this->pdo->prepare('INSERT INTO ar_payments (ar_invoice_id, transaction_id, amount, payment_type) VALUES (?, ?, ?, ?)')
            ->execute([$invoiceId, $txn->getId(), $returnAmount, 'return']);
        $this->updateCustomerBalance($inv['customer_id'], -$returnAmount);

        return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $returnAmount];
    }

    //
    // NGHIỆP VỤ CHIẾT KHẤU THANH TOÁN CHO KH: Giảm giá cho KH do thanh toán sớm hơn thời hạn
    // Hạch toán: Nợ 635 (Chi phí tài chính) — Có 131
    // Tác động BC02: MS 23 (Chi phí tài chính) tăng → lợi nhuận giảm
    // Phân biệt: Chiết khấu thương mại (giảm giá hàng bán) qua 521 — chiết khấu thanh toán qua 635
    //
    public function recordSettlementDiscount(int $invoiceId, float $discountAmount, string $createdBy): array
    {
        // NGHIỆP VỤ CHIẾT KHẤU THANH TOÁN: Giảm giá cho KH thanh toán sớm hơn thời hạn.
        // Hạch toán: Nợ 635 (Chi phí tài chính) / Có 131 (giảm công nợ KH).
        //
        // PHÂN BIỆT: Chiết khấu thanh toán (635) ≠ Chiết khấu thương mại (521).
        // - 635: KH thanh toán trước hạn → DN mất 1 phần doanh thu tài chính → chi phí.
        // - 521: Giảm giá do mua số lượng lớn → giảm doanh thu thuần.
        //
        // ẢNH HƯỞNG BC02: MS 23 (Chi phí tài chính - 635) tăng → LNTT giảm.
        // Ảnh hưởng đến thuế TNDN: Chi phí 635 là chi phí hợp lý, được trừ khi tính thuế.
        //
        // RỦI RO: Nếu discount > balance → không thể giảm thêm → throw exception.
        // Trường hợp discount = balance → hóa đơn tất toán hoàn toàn.
        //
        $inv = $this->getInvoice($invoiceId);
        if ($discountAmount > $inv['balance']) throw new \InvalidArgumentException("Discount exceeds balance");

        // Dr 635 (finance cost) — Cr 131
        $txn = $this->journal->postEntry("AR settlement discount: {$inv['invoice_number']}", "DISC-{$invoiceId}", [
            ['account_code' => '635', 'amount' => $discountAmount, 'is_debit' => true],
            ['account_code' => '131', 'amount' => $discountAmount, 'is_debit' => false],
        ], $createdBy);

        $newBal = $inv['balance'] - $discountAmount;
        $this->pdo->prepare('UPDATE ar_invoices SET balance = ? WHERE id = ?')->execute([max(0, $newBal), $invoiceId]);
        $this->pdo->prepare('INSERT INTO ar_payments (ar_invoice_id, transaction_id, amount, payment_type) VALUES (?, ?, ?, ?)')
            ->execute([$invoiceId, $txn->getId(), $discountAmount, 'discount']);
        $this->updateCustomerBalance($inv['customer_id'], -$discountAmount);

        return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $discountAmount];
    }

    //
    // NGHIỆP VỤ XÓA NỢ PHẢI THU KHÓ ĐÒI: Xóa khoản nợ không có khả năng thu hồi
    // Hạch toán: Nợ 2293 (Dự phòng phải thu khó đòi) — Nợ 642 (phần vượt dự phòng) — Có 131
    // Cơ sở pháp lý: TT 48/2019/TT-BTC về trích lập và xử lý dự phòng
    // Điều kiện: KH giải thể/mất tích/quá hạn > 3 năm/có quyết định của Tòa án
    // Rủi ro: Xóa nợ xong → mất quyền đòi nợ về mặt pháp lý — cần Hội đồng xóa nợ phê duyệt
    // Ảnh hưởng BC02: MS 25 (Chi phí quản lý DN - 642) tăng → lợi nhuận giảm
    //
    public function writeOff(int $invoiceId, string $createdBy): array
    {
        // NGHIỆP VỤ XÓA NỢ PHẢI THU KHÓ ĐÒI: Xóa khoản không có khả năng thu hồi.
        // Hạch toán: Nợ 2293 (sử dụng dự phòng) / Nợ 642 (phần vượt dự phòng) / Có 131 (xóa nợ).
        //
        // CƠ SỞ TRÍCH LẬP: TT 48/2019/TT-BTC quy định tỷ lệ trích lập theo thời gian quá hạn:
        //   - 6-12 tháng: 30%
        //   - 12-18 tháng: 50%
        //   - 18-36 tháng: 70%
        //   - >36 tháng: 100%
        //   - Đã giải thể/phá sản: 100%
        //
        // PHƯƠNG PHÁP: Sử dụng dự phòng (2293) trước, phần còn lại ghi vào chi phí (642).
        // Nếu dự phòng đủ → không ảnh hưởng P&L. Nếu thiếu → ảnh hưởng lợi nhuận.
        //
        // RỦI RO PHÁP LÝ: Sau khi xóa nợ, DN mất quyền đòi nợ hợp pháp.
        // Cần phê duyệt của Hội đồng xóa nợ (có biên bản, quyết định).
        // Kiểm toán yêu cầu xem biên bản này khi kiểm toán cuối năm.
        //
        // THUẾ: Khoản xóa nợ có dự phòng đã được tính vào chi phí được trừ trước đó.
        // Nếu xóa nợ không đúng điều kiện → chi phí 642 bị loại khi tính thuế TNDN.
        //
        $inv = $this->getInvoice($invoiceId);
        if ($inv['balance'] <= 0) throw new \InvalidArgumentException("No balance to write off");

        $amount = $inv['balance'];
        $provision = $this->accountRepo->findByCode('2293');
        $provisionBal = $provision ? $provision->getBalance() : 0;
        $useProvision = min(abs($provisionBal), $amount);
        $excess = $amount - $useProvision;

        // Dr 2293 (provision) + Dr 642 (excess) — Cr 131
        $lines = [];
        if ($useProvision > 0) $lines[] = ['account_code' => '2293', 'amount' => $useProvision, 'is_debit' => true];
        if ($excess > 0) $lines[] = ['account_code' => '642', 'amount' => $excess, 'is_debit' => true];
        $lines[] = ['account_code' => '131', 'amount' => $amount, 'is_debit' => false];

        $txn = $this->journal->postEntry("AR write-off: {$inv['invoice_number']}", "WO-{$invoiceId}", $lines, $createdBy);

        $this->pdo->prepare('UPDATE ar_invoices SET balance = 0, status = ? WHERE id = ?')->execute(['written_off', $invoiceId]);
        $this->updateCustomerBalance($inv['customer_id'], -$amount);

        return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $amount, 'used_provision' => $useProvision, 'excess_expense' => $excess];
    }

    // ── Reports ──

    //
    // BÁO CÁO TUỔI NỢ PHẢI THU: Phân tích công nợ KH theo thời gian quá hạn
    // Mục đích: Đánh giá khả năng thu hồi, căn cứ trích lập dự phòng phải thu khó đòi (TK 2293)
    // Các bucket: Hiện tại (chưa đến hạn), 1-30 ngày, 31-60, 61-90, 90+ ngày
    // Ảnh hưởng BC01: MS 131 (Phải thu KH), MS 229 (Dự phòng — giảm trừ tài sản)
    // Căn cứ TT 48/2019/TT-BTC: Nợ quá hạn 6-12 tháng trích 30%, >12 tháng trích 50%, >3 năm trích 100%
    //
    // ĐỘ CHÍNH XÁC: Phụ thuộc due_date hóa đơn. Sai due_date → aging sai → trích lập dự phòng sai.
    // GIỚI HẠN: Bỏ qua hóa đơn có balance <= 1 VND (đã tất toán) và prepayment (số âm).
    // Cảnh báo: Aging dùng date_create('today') — nếu cron chạy quá đêm → aging lệch 1 ngày.
    //
    public function getAgingReport(): array
    {
        $rows = $this->pdo->query(
            "SELECT i.*, c.name as customer_name, c.code as customer_code
             FROM ar_invoices i JOIN customers c ON c.id = i.customer_id
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
    // SAO KÊ CÔNG NỢ KH: Chi tiết tất cả hóa đơn, thanh toán, trả lại, chiết khấu của một KH
    // Mục đích: Đối chiếu công nợ với KH định kỳ (cuối tháng), xuất biên bản đối chiếu xác nhận số dư 131
    // Cơ sở cho kiểm toán độc lập xác nhận số dư phải thu KH
    //
    public function getCustomerStatement(string $customerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.*, c.name as customer_name FROM ar_invoices i JOIN customers c ON c.id = i.customer_id WHERE i.customer_id = ? ORDER BY i.invoice_date DESC'
        );
        $stmt->execute([$customerId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getInvoices(string $status = null, string $customerId = null): array
    {
        $sql = 'SELECT i.*, c.name as customer_name FROM ar_invoices i JOIN customers c ON c.id = i.customer_id WHERE 1=1';
        $params = [];
        if ($status) { $sql .= ' AND i.status = ?'; $params[] = $status; }
        if ($customerId) { $sql .= ' AND i.customer_id = ?'; $params[] = $customerId; }
        $sql .= ' ORDER BY i.created_at DESC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getInvoice(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT i.*, c.name as customer_name FROM ar_invoices i JOIN customers c ON c.id = i.customer_id WHERE i.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getPayments(int $invoiceId): array
    {
        $stmt = $this->pdo->prepare('SELECT p.*, t.description, t.reference, t.created_at as txn_date FROM ar_payments p JOIN transactions t ON t.id = p.transaction_id WHERE p.ar_invoice_id = ? ORDER BY p.created_at');
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCustomers(): array
    {
        return $this->pdo->query('SELECT id, code, name, balance FROM customers ORDER BY name')->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getCustomer(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function updateCustomerBalance(string $customerId, float $amount): void
    {
        $this->pdo->prepare('UPDATE customers SET balance = balance + ? WHERE id = ?')->execute([$amount, $customerId]);
    }
}
