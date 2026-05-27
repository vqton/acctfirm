<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\CustomerRepositoryInterface;

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
    private ?CustomerRepositoryInterface $customerRepo;

    public function __construct(\PDO $pdo, AccountRepositoryInterface $accountRepo, JournalService $journal, ?AuditLoggerInterface $auditLogger = null, ?CustomerRepositoryInterface $customerRepo = null)
    {
        $this->pdo = $pdo;
        $this->accountRepo = $accountRepo;
        $this->journal = $journal;
        $this->auditLogger = $auditLogger;
        $this->customerRepo = $customerRepo;
    }

    //
    // NGHIỆP VỤ BÁN HÀNG: Ghi nhận doanh thu và khoản phải thu khách hàng
    // Hạch toán: Nợ 131 (tổng giá thanh toán) — Có 511 (doanh thu bán hàng chưa thuế) — Có 33311 (VAT đầu ra)
    // Ảnh hưởng BC02: MS 01 (Doanh thu) tăng, MS 20 (Thuế GTGT đầu ra) tăng
    // Rủi ro: Sai doanh thu hoặc thuế → sai tờ khai thuế GTGT và TNDN → phạt chậm nộp
    //
    public function recordInvoice(string $customerId, string $invoiceNumber, string $invoiceDate, string $dueDate, float $netAmount, float $vatAmount, float $vatRate, string $description, string $createdBy, string $revenueAccount = '511'): array
    {
        $this->pdo->beginTransaction();
        try {
            $customer = $this->getCustomer($customerId);
            if (!$customer) throw new \InvalidArgumentException("Không tìm thấy khách hàng: {$customerId}. Vui lòng kiểm tra lại mã khách hàng.");

            $totalAmount = $netAmount + $vatAmount;
            $newBal = (float)$customer['balance'] + $totalAmount;
            $cl = (float)($customer['credit_limit'] ?? 0);
            if ($cl > 0 && $newBal > $cl) {
                throw new \InvalidArgumentException("Công nợ phải thu vượt quá hạn mức tín dụng của khách hàng {$customer['name']}. Số dư hiện tại {$newBal} VND, hạn mức {$cl} VND.");
            }

            $lines = [
                ['account_code' => '131', 'amount' => $totalAmount, 'is_debit' => true],
                ['account_code' => $revenueAccount, 'amount' => $netAmount, 'is_debit' => false],
            ];
            if ($vatAmount > 0) {
                $lines[] = ['account_code' => '33311', 'amount' => $vatAmount, 'is_debit' => false];
            }

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

            $this->pdo->commit();
            return ['invoice_id' => $invId, 'transaction_id' => $txn->getId(), 'amount' => $totalAmount];
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    //
    // NGHIỆP VỤ THU TIỀN: Khách hàng thanh toán công nợ
    // Hạch toán: Nợ 111 (tiền mặt) / Nợ 112 (tiền gửi NH) — Có 131
    // Ràng buộc: Không thu quá số dư hóa đơn, cảnh báo nếu thu tiền của hóa đơn quá hạn lâu (>90 ngày)
    // Tác động: Giảm số dư 131 trên BC01, tăng tiền trên BC03
    //
    public function recordPayment(int $invoiceId, float $amount, string $createdBy): array
    {
        $this->pdo->beginTransaction();
        try {
            // SELECT FOR UPDATE: Khóa hàng ar_invoices để chống double collection dưới concurrent.
            $stmt = $this->pdo->prepare('SELECT * FROM ar_invoices WHERE id = ? FOR UPDATE');
            $stmt->execute([$invoiceId]);
            $inv = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$inv) throw new \InvalidArgumentException("Không tìm thấy hóa đơn trong hệ thống.");
            if ($inv['status'] === 'paid') throw new \InvalidArgumentException("Hóa đơn này đã được khách hàng thanh toán. Không thể thực hiện lại.");
            $payAmt = min($amount, $inv['balance']);
            if ($payAmt <= 0) throw new \InvalidArgumentException("Hóa đơn không còn số dư phải thu.");

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

            $this->pdo->commit();
            return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $payAmt, 'balance' => max(0, $newBal)];
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    //
    // NGHIỆP VỤ KHÁCH HÀNG ỨNG TRƯỚC: KH thanh toán trước khi nhận hàng/dịch vụ
    // Hạch toán: Nợ 111/112 — Có 131 (dư Có trên sổ chi tiết khách hàng)
    // Khi dư Có 131 thể hiện "khoản nợ phải trả khách hàng" — cần đối chiếu với hợp đồng
    // Rủi ro: Nếu không giao hàng đúng hẹn → KH có quyền đòi lại tiền ứng trước
    //
    public function recordPrepayment(string $customerId, float $amount, string $description, string $createdBy): array
    {
        $this->pdo->beginTransaction();
        try {
            $customer = $this->getCustomer($customerId);
            if (!$customer) throw new \InvalidArgumentException("Không tìm thấy thông tin khách hàng.");

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
            $this->pdo->commit();
            return ['invoice_id' => $invId, 'transaction_id' => $txn->getId(), 'amount' => $amount];
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    //
    // NGHIỆP VỤ HÀNG BÁN TRẢ LẠI: Khách hàng trả lại hàng (kém chất lượng, sai đơn hàng, không đúng nhu cầu)
    // Hạch toán: Nợ 521 (Giảm trừ doanh thu) — Nợ 33311 (giảm VAT đầu ra) — Có 131
    // Tác động BC02: MS 02 (Các khoản giảm trừ doanh thu) tăng → doanh thu thuần (MS 10) giảm
    // Yêu cầu chứng từ: Biên bản trả hàng, hóa đơn điều chỉnh giảm — nếu không → không hợp lệ thuế
    //
    public function recordReturn(int $invoiceId, float $returnAmount, string $createdBy): array
    {
        $this->pdo->beginTransaction();
        try {
            $inv = $this->getInvoice($invoiceId);
            if ($inv['balance'] <= 0) throw new \InvalidArgumentException("Hóa đơn đã được thanh toán đủ.");
            if ($returnAmount > $inv['gross_amount']) throw new \InvalidArgumentException("Giá trị hàng trả lại không được vượt quá tổng giá trị hóa đơn gốc.");

            $vatReverse = $inv['vat_rate'] > 0 ? round($returnAmount * $inv['vat_rate'] / (100 + $inv['vat_rate']), 0) : 0;
            $netReturn = $returnAmount - $vatReverse;

            $lines = [
                ['account_code' => '521', 'amount' => $netReturn, 'is_debit' => true],
                ['account_code' => '131', 'amount' => $returnAmount, 'is_debit' => false],
            ];
            if ($vatReverse > 0) {
                array_splice($lines, 1, 0, [['account_code' => '33311', 'amount' => $vatReverse, 'is_debit' => true]]);
            }

            $txn = $this->journal->postEntry("AR return: {$inv['invoice_number']}", "RET-{$invoiceId}", $lines, $createdBy);

            $newBal = $inv['balance'] - $returnAmount;
            $this->pdo->prepare('UPDATE ar_invoices SET balance = ? WHERE id = ?')->execute([max(0, $newBal), $invoiceId]);
            $this->pdo->prepare('INSERT INTO ar_payments (ar_invoice_id, transaction_id, amount, payment_type) VALUES (?, ?, ?, ?)')
                ->execute([$invoiceId, $txn->getId(), $returnAmount, 'return']);
            $this->updateCustomerBalance($inv['customer_id'], -$returnAmount);

            $this->pdo->commit();
            return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $returnAmount];
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    //
    // NGHIỆP VỤ CHIẾT KHẤU THANH TOÁN CHO KH: Giảm giá cho KH do thanh toán sớm hơn thời hạn
    // Hạch toán: Nợ 635 (Chi phí tài chính) — Có 131
    // Tác động BC02: MS 23 (Chi phí tài chính) tăng → lợi nhuận giảm
    // Phân biệt: Chiết khấu thương mại (giảm giá hàng bán) qua 521 — chiết khấu thanh toán qua 635
    //
    public function recordSettlementDiscount(int $invoiceId, float $discountAmount, string $createdBy): array
    {
        $this->pdo->beginTransaction();
        try {
            $inv = $this->getInvoice($invoiceId);
            if ($discountAmount > $inv['balance']) throw new \InvalidArgumentException("Số tiền chiết khấu không được lớn hơn số dư còn lại của hóa đơn.");

            $txn = $this->journal->postEntry("AR settlement discount: {$inv['invoice_number']}", "DISC-{$invoiceId}", [
                ['account_code' => '635', 'amount' => $discountAmount, 'is_debit' => true],
                ['account_code' => '131', 'amount' => $discountAmount, 'is_debit' => false],
            ], $createdBy);

            $newBal = $inv['balance'] - $discountAmount;
            $this->pdo->prepare('UPDATE ar_invoices SET balance = ? WHERE id = ?')->execute([max(0, $newBal), $invoiceId]);
            $this->pdo->prepare('INSERT INTO ar_payments (ar_invoice_id, transaction_id, amount, payment_type) VALUES (?, ?, ?, ?)')
                ->execute([$invoiceId, $txn->getId(), $discountAmount, 'discount']);
            $this->updateCustomerBalance($inv['customer_id'], -$discountAmount);

            $this->pdo->commit();
            return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $discountAmount];
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
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
        $this->pdo->beginTransaction();
        try {
            $inv = $this->getInvoice($invoiceId);
            if ($inv['balance'] <= 0) throw new \InvalidArgumentException("Hóa đơn không còn số dư để xóa sổ.");

            $amount = $inv['balance'];
            $provision = $this->accountRepo->findByCode('2293');
            $provisionBal = $provision ? $provision->getBalance() : 0;
            $useProvision = min(abs($provisionBal), $amount);
            $excess = $amount - $useProvision;

            $lines = [];
            if ($useProvision > 0) $lines[] = ['account_code' => '2293', 'amount' => $useProvision, 'is_debit' => true];
            if ($excess > 0) $lines[] = ['account_code' => '642', 'amount' => $excess, 'is_debit' => true];
            $lines[] = ['account_code' => '131', 'amount' => $amount, 'is_debit' => false];

            $txn = $this->journal->postEntry("AR write-off: {$inv['invoice_number']}", "WO-{$invoiceId}", $lines, $createdBy);

            $this->pdo->prepare('UPDATE ar_invoices SET balance = 0, status = ? WHERE id = ?')->execute(['written_off', $invoiceId]);
            $this->updateCustomerBalance($inv['customer_id'], -$amount);

            $this->pdo->commit();
            return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $amount, 'used_provision' => $useProvision, 'excess_expense' => $excess];
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
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
            $r['provision_rate'] = $this->getProvisionRate($r['aging_days']);
            $r['provision_amount'] = round($r['balance'] * $r['provision_rate'] / 100, 0);
            $buckets[$bucket][] = $r;
            $totals[$bucket] += $r['balance'];
        }
        return ['buckets' => $buckets, 'totals' => $totals, 'grand_total' => array_sum($totals)];
    }

    // TỶ LỆ TRÍCH LẬP DỰ PHÒNG THEO TT 48/2019/TT-BTC
    // Nợ quá hạn 6-12 tháng → 30%, 12-18 tháng → 50%, 18-36 tháng → 70%, >36 tháng → 100%
    public function getProvisionRate(int $daysOverdue): float
    {
        if ($daysOverdue <= 180) return 0;
        if ($daysOverdue <= 365) return 30;
        if ($daysOverdue <= 545) return 50;
        if ($daysOverdue <= 1095) return 70;
        return 100;
    }

    //
    // BÁO CÁO TRÍCH LẬP DỰ PHÒNG: Tổng hợp số dư và dự phòng theo các khung thời gian TT 48/2019
    // Mục đích: Căn cứ để ghi nhận dự phòng phải thu khó đòi (TK 2293) cuối kỳ
    // Ảnh hưởng BC01: MS 229 (Dự phòng — giảm trừ tài sản), BC02: MS 25 (Chi phí QLDN - 642)
    //
    public function getProvisionSummary(): array
    {
        $rows = $this->pdo->query(
            "SELECT i.id, i.balance, i.due_date, i.customer_id, c.name as customer_name
             FROM ar_invoices i JOIN customers c ON c.id = i.customer_id
             WHERE i.balance > 1 AND i.status NOT IN ('paid','prepayment','written_off')
             ORDER BY i.due_date"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $buckets = [
            '0-180d'   => ['label' => '0-6 tháng', 'rate' => 0,  'balance' => 0, 'provision' => 0],
            '181-365d' => ['label' => '6-12 tháng', 'rate' => 30, 'balance' => 0, 'provision' => 0],
            '366-545d' => ['label' => '12-18 tháng', 'rate' => 50, 'balance' => 0, 'provision' => 0],
            '546-1095d'=> ['label' => '18-36 tháng', 'rate' => 70, 'balance' => 0, 'provision' => 0],
            '1096d+'   => ['label' => '>36 tháng', 'rate' => 100, 'balance' => 0, 'provision' => 0],
        ];
        $totalBalance = 0;
        $totalProvision = 0;
        $details = [];

        foreach ($rows as $r) {
            $days = (int)date_diff(date_create($r['due_date']), date_create('today'))->format('%a');
            $isOverdue = date_create($r['due_date']) < date_create('today');
            $agingDays = $isOverdue ? $days : 0;
            $rate = $this->getProvisionRate($agingDays);
            $provAmt = round($r['balance'] * $rate / 100, 0);

            if ($agingDays <= 180) $key = '0-180d';
            elseif ($agingDays <= 365) $key = '181-365d';
            elseif ($agingDays <= 545) $key = '366-545d';
            elseif ($agingDays <= 1095) $key = '546-1095d';
            else $key = '1096d+';

            $buckets[$key]['balance'] += $r['balance'];
            $buckets[$key]['provision'] += $provAmt;
            $totalBalance += $r['balance'];
            $totalProvision += $provAmt;

            $r['aging_days'] = $agingDays;
            $r['provision_rate'] = $rate;
            $r['provision_amount'] = $provAmt;
            $details[] = $r;
        }

        return [
            'buckets' => $buckets,
            'total_balance' => $totalBalance,
            'total_provision' => $totalProvision,
            'details' => $details,
        ];
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

    //
    // PHÂN BỔ THU TIỀN NHIỀU HÓA ĐƠN: 1 lần thu tiền từ KH thanh toán nhiều hóa đơn.
    // Input: allocations = [['invoice_id' => 1, 'amount' => 500000], ...]
    // Yêu cầu: Tất cả hóa đơn phải cùng một KH và không vượt quá số dư từng hóa đơn.
    //
    // NGHIỆP VỤ THỰC TẾ: KH chuyển khoản tổng số tiền cho nhiều hóa đơn chưa thanh toán.
    // Kế toán ghi 1 phiếu thu và phân bổ vào từng hóa đơn.
    // Hạch toán: Nợ 112 (tổng số tiền) — Có 131 (tổng số tiền).
    //
    public function allocateReceipt(array $allocations, string $receiptAccount, string $description, string $createdBy): array
    {
        $this->pdo->beginTransaction();
        try {
            $totalAmount = 0;
            $customerId = null;
            $validated = [];

            foreach ($allocations as $i => $alloc) {
                $inv = $this->getInvoice($alloc['invoice_id']);
                if (!$inv) throw new \InvalidArgumentException("Không tìm thấy hóa đơn mã {$alloc['invoice_id']} trong hệ thống.");
                if ($i === 0) { $customerId = $inv['customer_id']; }
                if ($inv['customer_id'] !== $customerId) {
                    throw new \InvalidArgumentException("Tất cả hóa đơn phân bổ phải cùng một khách hàng.");
                }
                if ($inv['status'] === 'paid') {
                    throw new \InvalidArgumentException("Hóa đơn {$alloc['invoice_id']} đã được thanh toán xong.");
                }
                $amt = min($alloc['amount'], $inv['balance']);
                if ($amt <= 0) throw new \InvalidArgumentException("Hóa đơn {$alloc['invoice_id']} không còn số dư để phân bổ.");
                if ($amt != $alloc['amount']) {
                    throw new \InvalidArgumentException("Số tiền phân bổ {$amt} VND vượt quá số dư {$inv['balance']} VND của hóa đơn {$alloc['invoice_id']}.");
                }
                $totalAmount += $amt;
                $validated[] = ['invoice' => $inv, 'amount' => $amt];
            }

            $txn = $this->journal->postEntry("AR receipt: {$description}", "ALLOC-{$customerId}", [
                ['account_code' => $receiptAccount, 'amount' => $totalAmount, 'is_debit' => true],
                ['account_code' => '131', 'amount' => $totalAmount, 'is_debit' => false],
            ], $createdBy);

            $payStmt = $this->pdo->prepare('INSERT INTO payment_allocations (payment_type, transaction_id, invoice_id, amount) VALUES (?, ?, ?, ?)');
            $invUpd = $this->pdo->prepare('UPDATE ar_invoices SET paid_amount = paid_amount + ?, balance = GREATEST(balance - ?, 0), status = ? WHERE id = ?');

            foreach ($validated as $v) {
                $inv = $v['invoice'];
                $amt = $v['amount'];
                $newPaid = $inv['paid_amount'] + $amt;
                $newBal = $inv['gross_amount'] - $newPaid;
                $newStatus = $newBal <= 1 ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');

                $payId = $txn->getId();
                $payStmt->execute(['ar', $payId, $inv['id'], $amt]);
                $invUpd->execute([$amt, $amt, $newStatus, $inv['id']]);
            }

            $this->updateCustomerBalance($customerId, -$totalAmount);

            $this->auditLogger?->log('ar.allocate', 'ar_payment', (string)$txn->getId(),
                null, ['allocations' => $allocations, 'total' => $totalAmount], $createdBy);

            $this->pdo->commit();
            return ['transaction_id' => $txn->getId(), 'total_amount' => $totalAmount, 'allocations' => $validated];
        } catch (\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    //
    // LẤY PHÂN BỔ THU TIỀN: Trả về danh sách các hóa đơn được thu từ 1 giao dịch.
    //
    public function getReceiptAllocationDetails(string $transactionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pa.*, i.invoice_number, i.customer_id, c.name as customer_name
             FROM payment_allocations pa
             JOIN ar_invoices i ON i.id = pa.invoice_id
             JOIN customers c ON c.id = i.customer_id
             WHERE pa.payment_type = ? AND pa.transaction_id = ?
             ORDER BY pa.id'
        );
        $stmt->execute(['ar', $transactionId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
