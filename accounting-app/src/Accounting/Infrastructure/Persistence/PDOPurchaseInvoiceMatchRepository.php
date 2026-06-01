<?php

declare(strict_types=1);

namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\PurchaseInvoiceMatch;
use Accounting\Domain\Repository\PurchaseInvoiceMatchRepositoryInterface;

class PDOPurchaseInvoiceMatchRepository implements PurchaseInvoiceMatchRepositoryInterface
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(string $id): ?PurchaseInvoiceMatch
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM purchase_invoice_matches WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByPoId(string $poId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM purchase_invoice_matches WHERE po_id = ?'
        );
        $stmt->execute([$poId]);

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function findByStatus(string $status): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM purchase_invoice_matches WHERE match_status = ?'
        );
        $stmt->execute([$status]);

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM purchase_invoice_matches');

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function save(PurchaseInvoiceMatch $match): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO purchase_invoice_matches (id, po_id, gr_id, supplier_invoice_no, invoice_date, invoice_amount, vat_amount, match_status, matched_by, matched_at, note, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                po_id = VALUES(po_id),
                gr_id = VALUES(gr_id),
                supplier_invoice_no = VALUES(supplier_invoice_no),
                invoice_date = VALUES(invoice_date),
                invoice_amount = VALUES(invoice_amount),
                vat_amount = VALUES(vat_amount),
                match_status = VALUES(match_status),
                matched_by = VALUES(matched_by),
                matched_at = VALUES(matched_at),
                note = VALUES(note)'
        );

        $stmt->execute([
            $match->getId(),
            $match->getPoId(),
            $match->getGrId(),
            $match->getSupplierInvoiceNo(),
            $match->getInvoiceDate(),
            $match->getInvoiceAmount(),
            $match->getVatAmount(),
            $match->getMatchStatus(),
            $match->getMatchedBy(),
            $match->getMatchedAt(),
            $match->getNote(),
            $match->getCreatedAt(),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM purchase_invoice_matches WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): PurchaseInvoiceMatch
    {
        return new PurchaseInvoiceMatch(
            $row['id'],
            $row['po_id'],
            $row['gr_id'],
            $row['supplier_invoice_no'],
            $row['invoice_date'],
            $row['invoice_amount'] !== null ? (float) $row['invoice_amount'] : null,
            $row['vat_amount'] !== null ? (float) $row['vat_amount'] : null,
            $row['match_status'],
            $row['matched_by'],
            $row['matched_at'],
            $row['note'],
            $row['created_at']
        );
    }
}
