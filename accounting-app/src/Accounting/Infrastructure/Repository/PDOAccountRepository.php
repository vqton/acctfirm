<?php
// src/Accounting/Infrastructure/Repository/PDOAccountRepository.php

namespace Accounting\Infrastructure\Repository;

use Accounting\Domain\Model\Account;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use PDO;

class PDOAccountRepository implements AccountRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(string $id): ?Account
    {
        $stmt = $this->pdo->prepare('SELECT id, name, type, balance, created_at FROM accounts WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $account = new Account($row['id'], $row['name'], $row['type']);
        $account->balance = (float) $row['balance'];
        return $account;
    }

    public function findByNumber(string $number): ?Account
    {
        $stmt = $this->pdo->prepare('SELECT id, name, type, balance, created_at FROM accounts WHERE id = ?');
        $stmt->execute([$number]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $account = new Account($row['id'], $row['name'], $row['type']);
        $account->balance = (float) $row['balance'];
        return $account;
    }

    public function save(Account $account): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO accounts (id, name, type, balance, created_at) VALUES (?, ?, ?, ?, ?) ' .
            'ON DUPLICATE KEY UPDATE name = ?, type = ?, balance = ?'
        );
        $stmt->execute([
            $account->getId(),
            $account->getName(),
            $account->getType(),
            $account->getBalance(),
            $account->getCreatedAt()->format('Y-m-d H:i:s'),
            $account->getName(),
            $account->getType(),
            $account->getBalance()
        ]);
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, name, type, balance, created_at FROM accounts ORDER BY id');
        $accounts = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $account = new Account($row['id'], $row['name'], $row['type']);
            $account->balance = (float) $row['balance'];
            $accounts[] = $account;
        }

        return $accounts;
    }
}