<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Model\BankAccount;
use Accounting\Domain\Repository\BankAccountRepositoryInterface;

class BankAccountController
{
    private BankAccountRepositoryInterface $repo;

    public function __construct(BankAccountRepositoryInterface $repo)
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
        if (!$data || !isset($data['code'], $data['bank_name'], $data['account_number'], $data['account_holder'])) {
            http_response_code(400); echo json_encode(['error' => 'code, bank_name, account_number, account_holder required']); return;
        }
        if ($this->repo->findByCode($data['code'])) {
            http_response_code(409); echo json_encode(['error' => 'Code already exists']); return;
        }
        $item = new BankAccount(
            $data['id'] ?? uniqid('ba_'), $data['code'], $data['bank_name'],
            $data['account_number'], $data['account_holder'], $data['branch'] ?? '',
            $data['currency'] ?? 'VND', (float)($data['opening_balance'] ?? 0)
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

        if (isset($data['code'])) $item->setCode($data['code']);
        if (isset($data['bank_name'])) $item->setBankName($data['bank_name']);
        if (isset($data['account_number'])) $item->setAccountNumber($data['account_number']);
        if (isset($data['account_holder'])) $item->setAccountHolder($data['account_holder']);
        if (isset($data['branch'])) $item->setBranch($data['branch']);
        if (isset($data['currency'])) $item->setCurrency($data['currency']);
        if (isset($data['opening_balance'])) $item->setOpeningBalance((float)$data['opening_balance']);
        if (isset($data['status'])) $item->setStatus((bool)$data['status']);

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
