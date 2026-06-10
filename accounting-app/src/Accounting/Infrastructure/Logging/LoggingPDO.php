<?php
namespace Accounting\Infrastructure\Logging;

/**
 * Bọc PDO để log tất cả câu SQL.
 *
 * Decorator pattern: LoggingPDO extends PDO nhưng delegate toàn bộ cho $wrapped PDO thật.
 * Cho phép bật/tắt SQL logging qua biến môi trường APP_LOG_SQL.
 *
 * @see LoggingStatement Bọc PDOStatement để log execution.
 */
class LoggingPDO extends \PDO
{
    private \PDO $wrapped;
    private bool $enabled = false;

    /**
     * @param \PDO $wrapped PDO thật cần wrap.
     */
    public function __construct(\PDO $wrapped)
    {
        $this->wrapped = $wrapped;
        $this->enabled = getenv('APP_LOG_SQL') ?: true;
    }

    /**
     * Chuẩn bị câu lệnh SQL.
     *
     * Bọc statement trong LoggingStatement để log execution.
     * LoggingStatement tự động ghi lại SQL + params + duration khi execute().
     *
     * @param string $query Câu SQL với ? placeholder.
     * @param array $options Tùy chọn prepare.
     * @return \PDOStatement|false LoggingStatement hoặc false nếu lỗi.
     */
    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $stmt = $this->wrapped->prepare($query, $options);
        $stmt = new LoggingStatement($stmt, $query, $this->enabled);
        return $stmt;
    }

    /**
     * Thực thi query trực tiếp (không prepared).
     *
     * Chỉ dùng cho các câu lệnh không có user input (EXPLAIN, SET NAMES, ...).
     *
     * @param string $query Câu SQL.
     * @param int|null $fetchMode Fetch mode.
     * @param mixed ...$fetchModeArgs Tham số fetch mode.
     * @return \PDOStatement|false PDOStatement hoặc false nếu lỗi.
     */
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

    /**
     * Thực thi câu lệnh không trả về kết quả (INSERT/UPDATE/DELETE).
     *
     * Dùng cho migration, seed data, các thao tác admin.
     *
     * @param string $statement Câu SQL.
     * @return int|false Số dòng bị ảnh hưởng hoặc false nếu lỗi.
     */
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

    /**
     * Bắt đầu transaction (delegate).
     *
     * @return bool True nếu thành công.
     */
    public function beginTransaction(): bool { return $this->wrapped->beginTransaction(); }

    /**
     * Commit transaction (delegate).
     *
     * @return bool True nếu thành công.
     */
    public function commit(): bool { return $this->wrapped->commit(); }

    /**
     * Rollback transaction (delegate).
     *
     * @return bool True nếu thành công.
     */
    public function rollBack(): bool { return $this->wrapped->rollBack(); }

    /**
     * Kiểm tra đang trong transaction (delegate).
     *
     * @return bool True nếu đang trong transaction.
     */
    public function inTransaction(): bool { return $this->wrapped->inTransaction(); }

    /**
     * Lấy ID vừa insert (delegate).
     *
     * @param string|null $name Tên sequence object.
     * @return string|false ID vừa insert.
     */
    public function lastInsertId(?string $name = null): string|false { return $this->wrapped->lastInsertId($name); }

    /**
     * Lấy error code (delegate).
     *
     * @return string|null Error code.
     */
    public function errorCode(): ?string { return $this->wrapped->errorCode(); }

    /**
     * Lấy error info (delegate).
     *
     * @return array Thông tin lỗi.
     */
    public function errorInfo(): array { return $this->wrapped->errorInfo(); }

    /**
     * Set attribute PDO (delegate).
     *
     * @param int $attribute Attribute key.
     * @param mixed $value Giá trị.
     * @return bool True nếu thành công.
     */
    public function setAttribute(int $attribute, mixed $value): bool { return $this->wrapped->setAttribute($attribute, $value); }

    /**
     * Get attribute PDO (delegate).
     *
     * @param int $attribute Attribute key.
     * @return mixed Giá trị attribute.
     */
    public function getAttribute(int $attribute): mixed { return $this->wrapped->getAttribute($attribute); }

    /**
     * Quote string (delegate).
     *
     * @param string $string Chuỗi cần quote.
     * @param int $type Kiểu PDO param.
     * @return string Chuỗi đã quote.
     */
    public function quote(string $string, int $type = \PDO::PARAM_STR): string { return $this->wrapped->quote($string, $type); }
}

/**
 * Log từng câu prepared statement.
 *
 * Wrapper cho PDOStatement — tự động ghi log khi execute() được gọi.
 * Ghi lại SQL + params + thời gian chạy.
 * Lưu params để có thể debug: "Lỗi SQL với params: [123, 'ABC']".
 */
class LoggingStatement extends \PDOStatement
{
    private \PDOStatement $wrapped;
    private string $sql;
    private bool $enabled;
    private array $params = [];

    /**
     * @param \PDOStatement $wrapped PDOStatement thật cần wrap.
     * @param string $sql Câu SQL gốc với ? placeholder.
     * @param bool $enabled True nếu logging được bật.
     */
    public function __construct(\PDOStatement $wrapped, string $sql, bool $enabled)
    {
        $this->wrapped = $wrapped;
        $this->sql = $sql;
        $this->enabled = $enabled;
    }

    /**
     * Thực thi prepared statement.
     *
     * Lưu params trước execute để có thể trace dù execute thất bại.
     * Duration tính bằng milliseconds — giúp phát hiện slow query.
     *
     * @param array|null $params Tham số bound.
     * @return bool True nếu thành công.
     */
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

    /**
     * Fetch một dòng (delegate).
     *
     * @param int $mode Fetch mode.
     * @param int $cursorOrientation Cursor orientation.
     * @param int $cursorOffset Cursor offset.
     * @return mixed Dữ liệu dòng hoặc false.
     */
    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->wrapped->fetch($mode, $cursorOrientation, $cursorOffset);
    }

    /**
     * Fetch tất cả dòng (delegate).
     *
     * @param int $mode Fetch mode.
     * @param mixed ...$args Tham số fetch.
     * @return array Mảng dữ liệu.
     */
    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->wrapped->fetchAll($mode, ...$args);
    }

    /**
     * Fetch một cột (delegate).
     *
     * @param int $column Chỉ số cột (0-based).
     * @return mixed Giá trị cột hoặc false.
     */
    public function fetchColumn(int $column = 0): mixed
    {
        return $this->wrapped->fetchColumn($column);
    }

    /**
     * Fetch dòng dưới dạng object (delegate).
     *
     * @param string|null $class Tên class.
     * @param array $constructorArgs Tham số constructor.
     * @return object|false Object hoặc false.
     */
    public function fetchObject(?string $class = 'stdClass', array $constructorArgs = []): object|false
    {
        return $this->wrapped->fetchObject($class, $constructorArgs);
    }

    /**
     * Đếm số dòng bị ảnh hưởng (delegate).
     *
     * @return int Số dòng.
     */
    public function rowCount(): int
    {
        return $this->wrapped->rowCount();
    }

    /**
     * Đóng cursor (delegate).
     *
     * @return bool True nếu thành công.
     */
    public function closeCursor(): bool
    {
        return $this->wrapped->closeCursor();
    }

    /**
     * Đếm số cột (delegate).
     *
     * @return int Số cột.
     */
    public function columnCount(): int
    {
        return $this->wrapped->columnCount();
    }

    /**
     * Lấy meta của cột (delegate).
     *
     * @param int $column Chỉ số cột.
     * @return array|false Thông tin meta hoặc false.
     */
    public function getColumnMeta(int $column): array|false
    {
        return $this->wrapped->getColumnMeta($column);
    }

    /**
     * Set fetch mode (delegate).
     *
     * @param int $mode Fetch mode.
     * @param mixed ...$args Tham số.
     * @return bool True nếu thành công.
     */
    public function setFetchMode(int $mode, mixed ...$args): bool
    {
        return $this->wrapped->setFetchMode($mode, ...$args);
    }

    /**
     * Bind tham số bằng reference (delegate).
     *
     * @param string|int $param Tham số placeholder.
     * @param mixed $var Biến reference.
     * @param int $type Kiểu PDO param.
     * @param int $maxLength Độ dài tối đa.
     * @param mixed $driverOptions Tùy chọn driver.
     * @return bool True nếu thành công.
     */
    public function bindParam(string|int $param, mixed &$var, int $type = \PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        return $this->wrapped->bindParam($param, $var, $type, $maxLength, $driverOptions);
    }

    /**
     * Bind giá trị (delegate).
     *
     * @param string|int $param Tham số placeholder.
     * @param mixed $value Giá trị.
     * @param int $type Kiểu PDO param.
     * @return bool True nếu thành công.
     */
    public function bindValue(string|int $param, mixed $value, int $type = \PDO::PARAM_STR): bool
    {
        return $this->wrapped->bindValue($param, $value, $type);
    }

    /**
     * Bind cột (delegate).
     *
     * @param string|int $column Tên cột hoặc chỉ số.
     * @param mixed $var Biến reference.
     * @param int $type Kiểu PDO param.
     * @param int $maxLength Độ dài tối đa.
     * @param mixed $driverOptions Tùy chọn driver.
     * @return bool True nếu thành công.
     */
    public function bindColumn(string|int $column, mixed &$var, int $type = \PDO::PARAM_STR, int $maxLength = 0, mixed $driverOptions = null): bool
    {
        return $this->wrapped->bindColumn($column, $var, $type, $maxLength, $driverOptions);
    }

    /**
     * Debug dump params (delegate).
     *
     * @return bool|null Kết quả dump.
     */
    public function debugDumpParams(): ?bool
    {
        return $this->wrapped->debugDumpParams();
    }

    /**
     * Chuyển đến rowset tiếp theo (delegate).
     *
     * @return bool True nếu có rowset tiếp theo.
     */
    public function nextRowset(): bool
    {
        return $this->wrapped->nextRowset();
    }

    /**
     * Lấy error code (delegate).
     *
     * @return string|null Error code.
     */
    public function errorCode(): ?string
    {
        return $this->wrapped->errorCode();
    }

    /**
     * Lấy error info (delegate).
     *
     * @return array Thông tin lỗi.
     */
    public function errorInfo(): array
    {
        return $this->wrapped->errorInfo();
    }
}
