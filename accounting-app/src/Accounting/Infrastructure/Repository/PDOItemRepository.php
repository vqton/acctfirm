<?php
namespace Accounting\Infrastructure\Repository;

use Accounting\Domain\Model\Item;
use Accounting\Domain\Repository\ItemRepositoryInterface;
use PDO;

class PDOItemRepository implements ItemRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?Item
    {
        $stmt = $this->pdo->prepare('SELECT * FROM items WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?Item
    {
        $stmt = $this->pdo->prepare('SELECT * FROM items WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM items ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        return $items;
    }

    public function save(Item $item): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO items (id, code, name, item_type, unit, purchase_price, sale_price, stock_qty, min_stock, description, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name), item_type=VALUES(item_type),
             unit=VALUES(unit), purchase_price=VALUES(purchase_price), sale_price=VALUES(sale_price),
             stock_qty=VALUES(stock_qty), min_stock=VALUES(min_stock), description=VALUES(description), status=VALUES(status)'
        );
        $stmt->execute([
            $item->getId(), $item->getCode(), $item->getName(), $item->getItemType(),
            $item->getUnit(), $item->getPurchasePrice(), $item->getSalePrice(),
            $item->getStockQty(), $item->getMinStock(), $item->getDescription(),
            $item->isStatus() ? 1 : 0, $item->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM items WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): Item
    {
        $item = new Item(
            $row['id'], $row['code'], $row['name'], $row['item_type'],
            $row['unit'], (float)$row['purchase_price'], (float)$row['sale_price'],
            (float)$row['stock_qty'], (float)$row['min_stock'], $row['description']
        );
        $item->setStatus((bool)$row['status']);
        return $item;
    }
}