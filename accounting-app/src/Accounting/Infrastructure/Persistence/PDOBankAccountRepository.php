<?php
// Quản lý dữ liệu: danh mục tài khoản ngân hàng
namespace Accounting\Infrastructure\Persistence;

use Accounting\Domain\Model\BankAccount;
use Accounting\Domain\Repository\BankAccountRepositoryInterface;
use PDO;

class PDOBankAccountRepository implements BankAccountRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function findById(string $id): ?BankAccount
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bank_accounts WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?BankAccount
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bank_accounts WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM bank_accounts ORDER BY code');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrate($row);
        }
        return $items;
    }

    public function save(BankAccount $account): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO bank_accounts (id, code, bank_name, account_number, account_holder, branch, currency, opening_balance, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE code=VALUES(code), bank_name=VALUES(bank_name),
             account_number=VALUES(account_number), account_holder=VALUES(account_holder),
             branch=VALUES(branch), currency=VALUES(currency), opening_balance=VALUES(opening_balance),
             status=VALUES(status)'
        );
        $stmt->execute([
            $account->getId(), $account->getCode(), $account->getBankName(),
            $account->getAccountNumber(), $account->getAccountHolder(), $account->getBranch(),
            $account->getCurrency(), $account->getOpeningBalance(),
            $account->isStatus() ? 1 : 0, $account->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM bank_accounts WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function hydrate(array $row): BankAccount
    {
        $account = new BankAccount(
            $row['id'], $row['code'], $row['bank_name'], $row['account_number'],
            $row['account_holder'], $row['branch'], $row['currency'],
            (float)$row['opening_balance']
        );
        $account->setStatus((bool)$row['status']);
        return $account;
    }
}
