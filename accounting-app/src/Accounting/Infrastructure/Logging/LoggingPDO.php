<?php
namespace Accounting\Infrastructure\Logging;

class LoggingPDO extends \PDO
{
    private \PDO $wrapped;
    private bool $enabled = false;

    public function __construct(\PDO $wrapped)
    {
        $this->wrapped = $wrapped;
        $this->enabled = getenv('APP_LOG_SQL') ?: true;
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $stmt = $this->wrapped->prepare($query, $options);
        $stmt = new LoggingStatement($stmt, $query, $this->enabled);
        return $stmt;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        $start = microtime(true);
        $result = $this->wrapped->query($query, $fetchMode, ...$fetchModeArgs);
        $duration = (microtime(true) - $start) * 1000;
        if ($this->enabled) {
            Logger::addQuery($query, [], $duration);
        }
        return $result;
    }

    public function exec(string $statement): int|false
    {
        $start = microtime(true);
        $result = $this->wrapped->exec($statement);
        $duration = (microtime(true) - $start) * 1000;
        if ($this->enabled) {
            Logger::addQuery($statement, [], $duration);
        }
        return $result;
    }

    public function beginTransaction(): bool { return $this->wrapped->beginTransaction(); }
    public function commit(): bool { return $this->wrapped->commit(); }
    public function rollBack(): bool { return $this->wrapped->rollBack(); }
    public function inTransaction(): bool { return $this->wrapped->inTransaction(); }
    public function lastInsertId(?string $name = null): string|false { return $this->wrapped->lastInsertId($name); }
    public function errorCode(): ?string { return $this->wrapped->errorCode(); }
    public function errorInfo(): array { return $this->wrapped->errorInfo(); }
    public function setAttribute(int $attribute, mixed $value): bool { return $this->wrapped->setAttribute($attribute, $value); }
    public function getAttribute(int $attribute): mixed { return $this->wrapped->getAttribute($attribute); }
    public function quote(string $string, int $type = \PDO::PARAM_STR): string { return $this->wrapped->quote($string, $type); }
}

class LoggingStatement extends \PDOStatement
{
    private \PDOStatement $wrapped;
    private string $sql;
    private bool $enabled;
    private array $params = [];

    public function __construct(\PDOStatement $wrapped, string $sql, bool $enabled)
    {
        $this->wrapped = $wrapped;
        $this->sql = $sql;
        $this->enabled = $enabled;
    }

    public function execute(?array $params = null): bool
    {
        $this->params = $params ?? [];
        $start = microtime(true);
        $result = $this->wrapped->execute($params);
        $duration = (microtime(true) - $start) * 1000;
        if ($this->enabled) {
            Logger::addQuery($this->sql, $this->params, $duration);
        }
        return $result;
    }

    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->wrapped->fetch($mode, $cursorOrientation, $cursorOffset);
    }

    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->wrapped->fetchAll($mode, ...$args);
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->wrapped->fetchColumn($column);
    }

    public function fetchObject(?string $class = 'stdClass', array $constructorArgs = []): object|false
    {
        return $this->wrapped->fetchObject($class, $constructorArgs);
    }

    public function rowCount(): int
    {
        return $this->wrapped->rowCount();
    }

    public function closeCursor(): bool
    {
        return $this->wrapped->closeCursor();
    }

    public function columnCount(): int
    {
        return $this->wrapped->columnCount();
    }

    public function getColumnMeta(int $column): array|false
    {
        return $this->wrapped->getColumnMeta($column);
    }

    public function setFetchMode(int $mode, mixed ...$args): bool
    {
        return $this->wrapped->setFetchMode($mode, ...$args);
    }

    public function bindParam(string|int $param, mixed &$var, int $type = \PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        return $this->wrapped->bindParam($param, $var, $type, $maxLength, $driverOptions);
    }

    public function bindValue(string|int $param, mixed $value, int $type = \PDO::PARAM_STR): bool
    {
        return $this->wrapped->bindValue($param, $value, $type);
    }

    public function bindColumn(string|int $column, mixed &$var, int $type = \PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        return $this->wrapped->bindColumn($column, $var, $type, $maxLength, $driverOptions);
    }

    public function debugDumpParams(): ?bool
    {
        return $this->wrapped->debugDumpParams();
    }

    public function nextRowset(): bool
    {
        return $this->wrapped->nextRowset();
    }

    public function errorCode(): ?string
    {
        return $this->wrapped->errorCode();
    }

    public function errorInfo(): array
    {
        return $this->wrapped->errorInfo();
    }
}
