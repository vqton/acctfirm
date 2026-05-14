<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Infrastructure\Database\AuditLogger;

class ArService
{
    private \PDO $pdo;
    private AccountRepositoryInterface $accountRepo;
    private JournalService $journal;

    public function __construct(\PDO $pdo, AccountRepositoryInterface $accountRepo)
    {
        $this->pdo = $pdo;
        $this->accountRepo = $accountRepo;
        $this->journal = new JournalService($accountRepo, new \Accounting\Infrastructure\Repository\PDOTransactionRepository($pdo));
    }

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

        $txn = $this->journal->postEntry("AR invoice: {$description}", "INV-{$invoiceNumber}", $lines, $createdBy);

        $stmt = $this->pdo->prepare(
            'INSERT INTO ar_invoices (customer_id, invoice_number, invoice_date, due_date, gross_amount, net_amount, vat_amount, vat_rate, balance, status, description, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$customerId, $invoiceNumber, $invoiceDate, $dueDate, $totalAmount, $netAmount, $vatAmount, $vatRate, $totalAmount, 'unpaid', $description, $createdBy]);
        $invId = (int)$this->pdo->lastInsertId();

        $this->updateCustomerBalance($customerId, $totalAmount);

        AuditLogger::log('ar.invoice', 'ar_invoice', (string)$invId, null,
            ['customer' => $customerId, 'amount' => $totalAmount, 'invoice' => $invoiceNumber], $createdBy);

        return ['invoice_id' => $invId, 'transaction_id' => $txn->getId(), 'amount' => $totalAmount];
    }

    public function recordPayment(int $invoiceId, float $amount, string $createdBy): array
    {
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

        AuditLogger::log('ar.payment', 'ar_invoice', (string)$invoiceId,
            ['balance_before' => $inv['balance']], ['payment' => $payAmt, 'balance_after' => max(0, $newBal)], $createdBy);

        return ['invoice_id' => $invoiceId, 'transaction_id' => $txn->getId(), 'amount' => $payAmt, 'balance' => max(0, $newBal)];
    }

    public function recordPrepayment(string $customerId, float $amount, string $description, string $createdBy): array
    {
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

    public function recordReturn(int $invoiceId, float $returnAmount, string $createdBy): array
    {
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

    public function recordSettlementDiscount(int $invoiceId, float $discountAmount, string $createdBy): array
    {
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

    public function writeOff(int $invoiceId, string $createdBy): array
    {
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
