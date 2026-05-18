<?php
namespace Accounting\Domain\Service;

use Accounting\Domain\Contract\AuditLoggerInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Repository\SupplierRepositoryInterface;

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

    public function recordInvoice(string $supplierId, string $invoiceNumber, string $invoiceDate, string $dueDate, float $netAmount, float $vatAmount, float $vatRate, string $description, string $inventoryAccount, string $createdBy, float $vatRatePct = null): array
    {
        $supplier = $this->supplierRepo->findById($supplierId);
        if (!$supplier) throw new \InvalidArgumentException("Supplier not found: {$supplierId}");

        $totalAmount = $netAmount + $vatAmount;
        $vatRatePct = $vatRatePct ?? $vatRate;

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

    public function recordPayment(int $invoiceId, float $amount, string $createdBy): array
    {
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

    public function recordPrepayment(string $supplierId, float $amount, string $description, string $createdBy): array
    {
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

    public function recordReturn(int $invoiceId, float $returnAmount, string $inventoryAccount, string $createdBy): array
    {
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

    public function recordDiscount(int $invoiceId, float $discountAmount, string $createdBy): array
    {
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

    public function writeOff(int $invoiceId, string $createdBy): array
    {
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
