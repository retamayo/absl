<?php

namespace Retamayo\Absl;

use PDO;
use Exception;
use PDOStatement;
use function array_fill;
use function array_map;
use function array_shift;
use function gettype;
use function implode;
use function is_string;
use function json_last_error;
use function json_last_error_msg;
use function password_verify;
use function print_r;
use function str_replace;
use function var_dump;

class Absl
{
    private PDO $connection;
    private array $tables = [];
    private ?string $tableName = null;
    private ?string $primaryKey = null;
    private array $columns = [];
    private int $rowCount = 10;


    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    public function defineTable(string $tableName, string $primaryKey, array $columns): self
    {
        $this->tables[$tableName] = [
            'primary' => $primaryKey,
            'columns' => $columns,
        ];

        return $this;
    }

    public function useTable(string $tableName): self
    {
        $table = $this->tables[$tableName] ?? null;

        if ($table === null) {
            throw new Exception('Table "' . $tableName . '" not found, define a table first.');
        }

        $this->tableName = $tableName;
        $this->primaryKey = $table['primary'];
        $this->columns = $table['columns'];

        return $this;
    }

    public function list(array $columns = []): array
    {
        if (!isset($this->tableName)) {
            throw new Exception('Use a table first.');
        }

        $sql =
            'SELECT ' . $this->quote(...($columns !== [] ? $columns : $this->columns)) .
            ' FROM ' . $this->quote($this->tableName);

        return $this->exec($sql)->fetchAll();
    }

    public function listJSON(array $columns = []): string
    {
        $data = $this->list($columns);

        return $this->json($data);
    }

    public function fetch(array $columns, string $where, string $whereValue): array
    {
        if ($columns === [] || $where === '' || $whereValue === '') {
            throw new Exception('Missing or empty arguments passed to function : fetch.');
        }

        $sql =
            'SELECT ' . $this->quote(...$columns) .
            ' FROM ' . $this->quote($this->tableName) .
            ' WHERE ' . $this->quote($where) . ' = ?';

        return $this->exec($sql, $this->sanitizeValue($whereValue))->fetch();
    }

    public function fetchJSON(array $columns, string $where, string $whereValue): string
    {
        $data = $this->fetch($columns, $where, $whereValue);

        return $this->json($data);
    }

    public function create(array $rowData): bool
    {
        if ($rowData === []) {
            throw new Exception('Missing or empty arguments passed to function : create.');
        }

        $columns = array_keys($rowData);

        if (array_diff($columns, $this->columns) !== []) {
            throw new Exception('Row data does not match of columns defined in the current table.');
        }

        $sqlValues = implode(
            ', ',
            array_fill(0, count($columns), '?')
        );

        $sql =
            'INSERT INTO ' . $this->quote($this->tableName) .
            ' (' . $this->quote(...$columns) . ') ' .
            'VALUES (' . $sqlValues . ')';

        return (bool)$this->exec($sql, ...array_values($rowData))->rowCount();
    }

    public function update(array $rowData, string $where, string $whereValue): int
    {
        if ($rowData === [] || $where === '' || $whereValue === '') {
            throw new Exception('Missing or empty arguments passed to function : update.');
        }

        $columns = array_keys($rowData);

        if (array_diff($columns, $this->columns) !== []) {
            throw new Exception('Row data does not match of columns defined in the current table.');
        }

        $sets = [];
        $values = [];

        foreach ($rowData as $column => $value) {
            $sets[] = $this->quote($column) . ' = ?';
            $values[] = $value;
        }

        $sql =
            'UPDATE ' . $this->quote($this->tableName) . ' SET ' .
            implode(', ', $sets) .
            ' WHERE ' . $this->quote($where) . ' = ?';
        $values[] = $whereValue;

        return $this->exec($sql, ...$values)->rowCount();
    }

    public function delete(string $where, string $whereValue): int
    {
        if ($where === '' || $whereValue === '') {
            throw new Exception('Missing or empty arguments passed to function : delete.');
        }

        $sql =
            'DELETE FROM ' . $this->quote($this->tableName) .
            ' WHERE ' . $this->quote($where) . ' = ?';

        return $this->exec($sql, $whereValue)->rowCount();
    }

    public function authenticate(array $credentials): bool
    {
        if (count($credentials) !== 2) {
            throw new Exception('The arguments passed to function : authenticate must contain exactly 2 items.');
        }

        $credentialsKeys = array_keys($credentials);
        $credentialsValues = array_values($credentials);

        $userColumn = $credentialsKeys[0];
        $userHash = $credentialsValues[0];

        $passwordColumn = $credentialsKeys[1];
        $passwordHash = $credentialsValues[1];

        $sql =
            'SELECT ' . $this->quote($passwordColumn) .
            ' FROM ' . $this->quote($this->tableName) .
            ' WHERE ' . $this->quote($userColumn) . ' = ? LIMIT 1';

        $hash = $this->exec($sql, $userHash)->fetchColumn();

        return is_string($hash) && password_verify($passwordHash, $hash);
    }

    public function search(string $pattern, string $inColumn): array
    {
        $sql =
            'SELECT ' . $this->quote(...$this->columns) .
            ' FROM ' . $this->quote($this->tableName) .
            ' WHERE ' . $this->quote($inColumn) . ' REGEXP "^' . $pattern . '"';

        return $this->exec($sql)->fetchAll();
    }

    public function setPageRowCount(int $count): self
    {
        $this->rowCount = $count;

        return $this;
    }

    public function paginate(int $currentPage): array
    {
        $totalRows = $this->exec('SELECT COUNT(*) FROM ' . $this->quote($this->tableName))->fetchColumn();

        $totalPages = ceil($totalRows / $this->rowCount);
        $currentPage = min($totalPages, max(1, $currentPage));
        $start = --$currentPage * $this->rowCount;
        var_dump($start, $this->rowCount);

        $sql =
            'SELECT ' . $this->quote(...$this->columns) .
            ' FROM ' . $this->quote($this->tableName) .
            ' LIMIT ?, ?';

        return $this->exec($sql, $start, $this->rowCount)->fetchAll();
    }

    public function checkDuplicate(string $uniqueColumn, string $checkValue): bool
    {
        $sql =
            'SELECT 0 FROM ' . $this->quote($this->tableName) .
            ' WHERE ' . $this->quote($uniqueColumn) . ' = ? LIMIT 1';

        return (bool)$this->exec($sql, $checkValue)->rowCount();
    }

    private function sanitizeValue($value)
    {
        $type = gettype($value);

        switch ($type) {
            case 'boolean':
            case 'integer':
            case 'double':
                return $value;
            case 'string':
                return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // Sanitize HTML entities
            case 'object':
            case 'array':
                return $this->json((array)$value);
            case 'NULL':
                return 'NULL';
            default:
                throw new Exception('Unsanitizable value type, given ' . $type);
        }
    }

    private function quote(string ...$names): string
    {
        $quotes = array_map(
            static fn(string $v) => sprintf('`%s`', str_replace('`', '\`', $v)),
            $names
        );

        return implode(', ', $quotes);
    }

    private function exec(string $sql, ...$values): object
    {
        try {
            $values = array_map(fn($p) => $this->sanitizeValue($p), $values);
            $stmt = $this->connection->prepare($sql);

            foreach ($values as $key => $value) {
                $stmt->bindValue(++$key, $value, $this->pdoTypeFor($value));
            }

            $stmt->execute();

            return new class($stmt) {
                private PDOStatement $stmt;

                public function __construct(PDOStatement $stmt)
                {
                    $this->stmt = $stmt;
                }

                public function __destruct()
                {
                    $this->stmt->closeCursor();
                }

                public function fetch(): array
                {
                    return $this->stmt->fetch(PDO::FETCH_ASSOC);
                }

                public function fetchAll(): array
                {
                    return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
                }

                public function fetchColumn()
                {
                    $row = $this->fetch();

                    return array_shift($row);
                }

                public function rowCount(): int
                {
                    return $this->stmt->rowCount();
                }
            };
        } catch (Exception $e) {
            throw new Exception('An error occurred while executing the query.', 0, $e);
        }
    }
  
    public function pdoTypeFor($value): int
    {
        switch (gettype($value)) {
            case 'string':
                return PDO::PARAM_STR;
            case 'integer':
            case 'double':
                return PDO::PARAM_INT;
            case 'boolean':
                return PDO::PARAM_BOOL;
            case 'NULL':
                return PDO::PARAM_NULL;
            default:
                throw new Exception('Missing PDO type for ' . gettype($value));
        }
    }

    private function json($data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new Exception('JSON error: ' . json_last_error_msg(), json_last_error());
        }

        return $json;
    }
}
