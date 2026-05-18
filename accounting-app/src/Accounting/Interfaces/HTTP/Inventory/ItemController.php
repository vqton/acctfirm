<?php
namespace Accounting\Interfaces\HTTP\Inventory;

use Accounting\Domain\Model\Item;
use Accounting\Domain\Repository\ItemRepositoryInterface;

class ItemController
{
    use CrudControllerTrait;

    private ItemRepositoryInterface $repo;
    public function __construct(ItemRepositoryInterface $repo) { $this->repo = $repo; }
    protected function repo() { return $this->repo; }
    protected function idPrefix(): string { return 'itm_'; }

    protected function createEntity(array $data): object
    {
        return new Item(
            $data['id'], $data['code'], $data['name'],
            $data['item_type'] ?? 'material', $data['unit'] ?? 'cai',
            (float)($data['purchase_price'] ?? 0), (float)($data['sale_price'] ?? 0),
            (float)($data['stock_qty'] ?? 0), (float)($data['min_stock'] ?? 0),
            $data['description'] ?? null, $data['valuation_method_id'] ?? null
        );
    }

    protected function updateEntity(object $entity, array $data): void
    {
        if (isset($data['code'])) $entity->setCode($data['code']);
        if (isset($data['name'])) $entity->setName($data['name']);
        if (isset($data['item_type'])) $entity->setItemType($data['item_type']);
        if (isset($data['unit'])) $entity->setUnit($data['unit']);
        if (isset($data['purchase_price'])) $entity->setPurchasePrice((float)$data['purchase_price']);
        if (isset($data['sale_price'])) $entity->setSalePrice((float)$data['sale_price']);
        if (isset($data['stock_qty'])) $entity->setStockQty((float)$data['stock_qty']);
        if (isset($data['min_stock'])) $entity->setMinStock((float)$data['min_stock']);
        if (isset($data['description'])) $entity->setDescription($data['description']);
        if (isset($data['status'])) $entity->setStatus((bool)$data['status']);
    }
}
