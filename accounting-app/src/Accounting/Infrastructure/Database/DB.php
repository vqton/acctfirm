<?php
namespace Accounting\Infrastructure\Database;

class DB
{
    private static ?\PDO $pdo = null;

    private static function pdo(): \PDO
    {
        if (self::$pdo) return self::$pdo;
        self::$pdo = $GLOBALS['container']['pdo'] ?? null;
        if (!self::$pdo) throw new \RuntimeException('PDO not available');
        return self::$pdo;
    }

    public static function setPdo(?\PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function select(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function insertGetId(string $sql, array $params = []): string
    {
        self::execute($sql, $params);
        return self::pdo()->lastInsertId();
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function sqlIn(array $values): array
    {
        if (empty($values)) return ['', ''];
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        // MySQL IN clause: field IN (?,?,?)
        return [' IN (' . $placeholders . ')', array_values($values)];
    }

    public static function sqlInCondition(string $field, array $values): string
    {
        if (empty($values)) return '1=0';
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        return "{$field} IN ({$placeholders})";
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function fetchColumn(string $sql, array $params = []): mixed
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : null;
    }

    public static function tableExists(string $table): bool
    {
        $stmt = self::pdo()->query("SHOW TABLES LIKE " . self::pdo()->quote($table));
        return (bool)$stmt->fetch();
    }
}
