<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;
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
    private JournalService $journal;
    private ?AuditLoggerInterface $auditLogger;

    public function __construct(\PDO $pdo, SupplierRepositoryInterface $supplierRepo, AccountRepositoryInterface $accountRepo, JournalService $journal, ?AuditLoggerInterface $auditLogger = null)
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
    // Chỉ được khấu trừ VAT nếu có hóa đơn đỏ hợp lệ theo TT 78/2021/TT-BTC
    // Ảnh hưởng BC02: MS 24 (Giá vốn hàng bán) thay đổi khi hàng được bán ra
    //
    public function recordInvoice(string $supplierId, string $invoiceNumber, string $invoiceDate, string $dueDate, float $netAmount, float $vatAmount, float $vatRate, string $description, string $inventoryAccount, string $createdBy, float $vatRatePct = null): array
    {
        $supplier = $this->supplierRepo->findById($supplierId);
        if (!$supplier) throw new \InvalidArgumentException("Supplier not found: {$supplierId}");

        $totalAmount = $netAmount + $vatAmount;
        $vatRatePct = $vatRatePct ?? $vatRate;

        // GIAO DỊCH: Multi-step write — journal post + INSERT invoice + UPDATE supplier.
        // KHÔNG wrap trong transaction → nếu INSERT hoặc UPDATE thất bại sau khi postEntry thành công,
        // GL đã ghi nhận bút toán nhưng sub-ledger AP không có hóa đơn → số dư 331 lệch.
        //
        // THUẾ: VAT 1331 chỉ được khấu trừ nếu có hóa đơn đỏ hợp lệ (TT 78/2021/TT-BTC).
        // Nhập sai VAT → sai tờ khai GTGT tháng/quý → phạt chậm nộp + thiếu thuế.
        //
        // Post journal entry: Dr Inventory — Dr 133 — Cr 331
        $lines = [['account_code' => $inventoryAccount, 'amount' => $netAmount, 'is_debit' => true]];
        if ($vatAmount > 0) {
            $lines[] = ['account_code' => '1331', 'amount' => $vatAmount, 'is_debit' => true];
        }
        $lines[] = ['account_code' => '331', 'amount' => $totalAmount, 'is_debit' => false];

        $txn = $this->journal->postEntry("AP invoice: {$description}", "INV-{$invoiceNumber}", $lines, $createdBy);

        // Create invoice record
        $stmt = $this->pdo->prepare(
            'INSERT INTO ap_invoices (supplier_id, invoice_number, invoice_date, due_date, gross_amount, net_amount, vat_amount, vat_rate, balance, status, description, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$supplierId, $invoiceNumber, $invoiceDate, $dueDate, $totalAmount, $netAmount, $vatAmount, $vatRatePct, $totalAmount, 'unpaid', $description, $createdBy]);
        $invoiceId = (int)$this->pdo->lastInsertId();

        // Update supplier balance
        $supplier->setBalance($supplier->getBalance() + $totalAmount);
        $this->supplierRepo->save($supplier);

        $this->auditLogger?->log('ap.invoice', 'ap_invoice', (string)$invoiceId, null,
            ['supplier' => $supplierId, 'amount' => $totalAmount, 'invoice' => $invoiceNumber], $createdBy);

        return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $totalAmount];
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
        // RỦI RO DOUBLE PAYMENT: Không có SELECT FOR UPDATE trên ap_invoices.
        // Nếu 2 request đến đồng thời: cả 2 đều đọc balance chưa thay đổi → cả 2 đều cho phép thanh toán
        // → thanh toán vượt quá số dư hóa đơn (overpayment).
        // Giải pháp: Bắt đầu PDO transaction, SELECT ... FOR UPDATE, sau đó mới đọc balance.
        //
        // THANH TOÁN TỪNG PHẦN: Method hỗ trợ partial payment (thanh toán 1 phần).
        // Khoản thanh toán bị cắt tại min(amount, balance) — không báo lỗi nếu amount > balance.
        // Rủi ro: Người dùng không biết chỉ thanh toán 1 phần → tưởng đã tất toán → aging sai.
        // Cần warning/log khi amount bị cắt.
        //
        // ẢNH HƯỞNG BCTC: Giảm số dư 331 (BC01 MS 310), giảm tiền 111/112 (BC01 MS 110/111).
        // Không ảnh hưởng BC02 (P&L) vì thanh toán công nợ không phải chi phí.
        //
        $invoice = $this->getInvoice($invoiceId);
        if ($invoice['status'] === 'paid') throw new \InvalidArgumentException("Invoice already paid");

        $payAmount = min($amount, $invoice['balance']);
        if ($payAmount <= 0) throw new \InvalidArgumentException("No balance to pay");

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

        return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $payAmount, 'balance' => max(0, $newBalance)];
    }

    //
    // NGHIỆP VỤ TẠM ỨNG: Trả trước tiền cho nhà cung cấp khi chưa nhận được hàng
    // Hạch toán: Nợ 331 — Có 111/112
    // TK 331 có thể có số dư bên Nợ (dư Nợ 331) thể hiện "khoản phải thu NCC" — tạm ứng chưa thanh toán
    // Quản lý rủi ro: Nếu NCC không giao hàng → khó thu hồi tạm ứng, cần hợp đồng chặt chẽ
    //
    public function recordPrepayment(string $supplierId, float $amount, string $description, string $createdBy): array
    {
        // NGHIỆP VỤ TẠM ỨNG NCC: Thanh toán trước khi nhận hàng.
        // Hạch toán Nợ 331 / Có 112 tạo số dư âm (-) trên chi tiết NCC → thể hiện "khoản phải thu NCC".
        // Khi nhận hàng: hóa đơn ghi Có 331 → bù trừ với số âm → số dư về 0 hoặc dương.
        //
        // RỦI RO ĐỐI SOÁT: Số dư âm 331 trên sub-ledger không hiển thị riêng trên BC01.
        // Chuẩn mực kế toán: Dư Nợ 331 phải trình bày vào bên Tài sản (Phải thu khác).
        // Nếu không tách → sai BC01 → sai chỉ tiêu đánh giá thanh khoản.
        //
        // RỦI RO TÍN DỤNG: NCC có thể không giao hàng sau khi nhận tạm ứng.
        // Cần quy định: chỉ tạm ứng cho NCC có uy tín, có hợp đồng rõ ràng.
        //
        $supplier = $this->supplierRepo->findById($supplierId);
        if (!$supplier) throw new \InvalidArgumentException("Supplier not found");

        $txn = $this->journal->postEntry("AP prepayment: {$description}", "PRE-{$supplierId}", [
            ['account_code' => '331', 'amount' => $amount, 'is_debit' => true],
            ['account_code' => '112', 'amount' => $amount, 'is_debit' => false],
        ], $createdBy);

        // Create a prepayment invoice record (negative balance)
        $this->pdo->prepare(
            'INSERT INTO ap_invoices (supplier_id, invoice_number, invoice_date, due_date, gross_amount, net_amount, balance, status, description, created_by)
             VALUES (?, ?, CURDATE(), CURDATE(), ?, 0, ?, ?, ?, ?)'
        )->execute([$supplierId, "PRE-{$txn->getId()}", -$amount, -$amount, 'prepayment', $description, $createdBy]);
        $invId = (int)$this->pdo->lastInsertId();

        $supplier->setBalance($supplier->getBalance() - $amount);
        $this->supplierRepo->save($supplier);

        return ['invoice_id' => $invId, 'transaction_id' => $txn->getId(), 'amount' => $amount];
    }

    // ── Return ──

    //
    // NGHIỆP VỤ TRẢ LẠI HÀNG MUA: Trả lại hàng cho nhà cung cấp (hàng kém chất lượng, sai quy cách)
    // Hạch toán: Nợ 331 — Có TK Hàng tồn kho — Có 1331 (thuế GTGT đầu vào hoàn lại)
    // Tác động 3 mặt: (1) Giảm công nợ 331, (2) Giảm giá trị hàng tồn kho, (3) Giảm VAT được khấu trừ
    // Yêu cầu: Phải có biên bản trả hàng và hóa đơn điều chỉnh giảm theo TT 78/2021/TT-BTC
    //
    public function recordReturn(int $invoiceId, float $returnAmount, string $inventoryAccount, string $createdBy): array
    {
        // NGHIỆP VỤ TRẢ LẠI HÀNG MUA: Xử lý khi hàng mua bị trả lại NCC.
        // Hạch toán: Nợ 331 (giảm công nợ) / Có Hàng tồn kho (giảm giá trị hàng) / Có 1331 (giảm VAT đầu vào)
        //
        // THUẾ: VAT hoàn lại tính từ vat_rate của hóa đơn gốc (round toán học).
        // Yêu cầu chứng từ: Biên bản trả hàng + hóa đơn điều chỉnh giảm (theo TT 78/2021/TT-BTC).
        // Nếu không có hóa đơn điều chỉnh → cơ quan thuế không chấp nhận giảm VAT đầu vào.
        //
        // RỦI RO: Nếu hóa đơn đã được thanh toán 1 phần, việc trả lại làm thay đổi cơ cấu thanh toán.
        // Trường hợp đã thanh toán > giá trị hàng còn lại sau trả → phải thu hồi tiền từ NCC.
        //
        $invoice = $this->getInvoice($invoiceId);
        if ($invoice['balance'] <= 0) throw new \InvalidArgumentException("Invoice fully paid, adjust via separate entry");

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

        return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $returnAmount];
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
        // NGHIỆP VỤ CHIẾT KHẤU THANH TOÁN: NCC giảm giá khi DN thanh toán sớm.
        // Hạch toán: Nợ 331 / Có 515 (Doanh thu hoạt động tài chính).
        //
        // PHÂN BIỆT QUAN TRỌNG:
        // - Chiết khấu thanh toán (hạch toán 515): giảm giá do trả tiền sớm → tăng doanh thu tài chính.
        // - Chiết khấu thương mại (hạch toán giảm giá mua): giảm giá do mua số lượng lớn → giảm giá vốn.
        // Method này chỉ xử lý chiết khấu thanh toán. Chiết khấu thương mại cần xử lý riêng.
        //
        // RỦI RO: Nếu hóa đơn đã được chiết khấu 1 phần → số dư còn lại nhỏ hơn discountAmount.
        // Validation: kiểm tra discountAmount <= balance để tránh âm balance.
        //
        $invoice = $this->getInvoice($invoiceId);
        if ($discountAmount > $invoice['balance']) throw new \InvalidArgumentException("Discount exceeds balance");

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

        return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $discountAmount];
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
        $invoice = $this->getInvoice($invoiceId);
        if ($invoice['balance'] <= 0) throw new \InvalidArgumentException("Invoice has no balance to write off");

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

        return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $amount];
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
}
