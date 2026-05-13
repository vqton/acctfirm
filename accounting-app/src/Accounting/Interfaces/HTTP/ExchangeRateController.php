<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Model\ExchangeRate;
use Accounting\Domain\Repository\ExchangeRateRepositoryInterface;

class ExchangeRateController
{
    private ExchangeRateRepositoryInterface $repo;

    public function __construct(ExchangeRateRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function list(): void
    {
        echo json_encode(array_map(fn($i) => $i->toArray(), $this->repo->findAll()));
    }

    public function get(string $id): void
    {
        $item = $this->repo->findById($id);
        if (!$item) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
        echo json_encode($item->toArray());
    }

    public function create(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['currency_code'], $data['currency_name'], $data['rate'], $data['rate_date'])) {
            http_response_code(400); echo json_encode(['error' => 'currency_code, currency_name, rate, rate_date required']); return;
        }
        if ($this->repo->findByCode($data['currency_code'])) {
            http_response_code(409); echo json_encode(['error' => 'Currency code already exists']); return;
        }
        $item = new ExchangeRate(
            $data['id'] ?? uniqid('exr_'), $data['currency_code'], $data['currency_name'],
            (float)$data['rate'], $data['rate_date']
        );
        $this->repo->save($item);
        http_response_code(201);
        echo json_encode($item->toArray());
    }

    public function update(string $id): void
    {
        $item = $this->repo->findById($id);
        if (!$item) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { http_response_code(400); echo json_encode(['error' => 'Invalid data']); return; }

        if (isset($data['currency_code'])) $item->setCurrencyCode($data['currency_code']);
        if (isset($data['currency_name'])) $item->setCurrencyName($data['currency_name']);
        if (isset($data['rate'])) $item->setRate((float)$data['rate']);
        if (isset($data['rate_date'])) $item->setRateDate($data['rate_date']);

        $this->repo->save($item);
        echo json_encode($item->toArray());
    }

    public function delete(string $id): void
    {
        if (!$this->repo->findById($id)) {
            http_response_code(404); echo json_encode(['error' => 'Not found']); return;
        }
        $this->repo->delete($id);
        echo json_encode(['message' => 'Deleted']);
    }
}
