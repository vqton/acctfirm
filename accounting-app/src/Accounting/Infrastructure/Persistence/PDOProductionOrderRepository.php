<?php
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\ProductionOrder;
use Accounting\Domain\Repository\ProductionOrderRepositoryInterface;
use PDO;

class PDOProductionOrderRepository implements ProductionOrderRepositoryInterface
{
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?ProductionOrder
    {
        $stmt = $this->pdo->prepare('SELECT * FROM production_orders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByReference(string $ref): ?ProductionOrder
    {
        $stmt = $this->pdo->prepare('SELECT * FROM production_orders WHERE reference = ?');
        $stmt->execute([$ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $items = [];
        foreach ($this->pdo->query('SELECT * FROM production_orders ORDER BY created_at DESC') as $row) {
            $items[] = $this->hydrate($row);
        }
        return $items;
    }

    public function save(ProductionOrder $order): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO production_orders (id,reference,product_id,bom_id,qty,completed_qty,start_date,due_date,status,material_cost,labor_cost,overhead_cost,total_cost,unit_cost,notes,created_by,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE qty=VALUES(qty),completed_qty=VALUES(completed_qty),
             status=VALUES(status),material_cost=VALUES(material_cost),labor_cost=VALUES(labor_cost),
             overhead_cost=VALUES(overhead_cost),total_cost=VALUES(total_cost),
             unit_cost=VALUES(unit_cost),notes=VALUES(notes)'
        );
        $stmt->execute([
            $order->getId(), $order->getReference(), $order->getProductId(), $order->getBomId(),
            $order->getQty(), $order->getCompletedQty(), $order->getStartDate(), $order->getDueDate(),
            $order->getStatus(), $order->getMaterialCost(), $order->getLaborCost(),
            $order->getOverheadCost(), $order->getTotalCost(), $order->getUnitCost(),
            $order->getNotes(), $order->getCreatedBy(),
        ]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM production_orders WHERE id = ?')->execute([$id]);
    }

    public function getMaterials(string $poId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT pom.*, i.code as material_code, i.name as material_name
            FROM production_order_materials pom
            JOIN items i ON pom.material_id = i.id
            WHERE pom.production_order_id = ?
        ");
        $stmt->execute([$poId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLabor(string $poId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM production_order_labor WHERE production_order_id = ?');
        $stmt->execute([$poId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOverhead(string $poId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM production_order_overhead WHERE production_order_id = ?');
        $stmt->execute([$poId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function hydrate(array $row): ProductionOrder
    {
        $po = new ProductionOrder(
            $row['id'], $row['reference'], $row['product_id'], (float)$row['qty'],
            $row['start_date'] ?? null, $row['due_date'] ?? null, $row['bom_id'] ?? null
        );
        $po->setStatus($row['status'] ?? 'draft');
        $po->setCompletedQty((float)($row['completed_qty'] ?? 0));
        $po->setMaterialCost((float)($row['material_cost'] ?? 0));
        $po->setLaborCost((float)($row['labor_cost'] ?? 0));
        $po->setOverheadCost((float)($row['overhead_cost'] ?? 0));
        $po->setNotes($row['notes'] ?? null);
        $po->setCreatedBy($row['created_by'] ?? null);
        return $po;
    }
}
