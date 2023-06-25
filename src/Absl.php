<?php

namespace Retamayo\Absl;

use PDO;
use Exception;

class Absl
{
    private PDO $connection;
    private array $tables;
    private string $tableName;
    private string $primaryKey;
    private array $columns;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->tables = [];
    }

    public function defineTable(string $tableName, string $primaryKey, array $columns)
    {
        $this->tables[$tableName] = ["primary" => $primaryKey, "columns" => $columns];
    }

    public function useTable(string $tableName)
    {
        if (array_key_exists($tableName, $this->tables)) {
            $this->tableName = $tableName;
            $this->primaryKey = $this->tables[$tableName]['primary'];
            $this->columns = $this->tables[$tableName]['columns'];
        } else {
            throw new Exception('Define a table first.');
        }
    }

    public function list(array $columns = [])
    {
        if (isset($this->tableName)) {
            if (count($columns) > 1) {
                $columns = implode(', ', $columns);
                $sql = 'SELECT ' . $columns . ' FROM ' . $this->tableName . ';';
            } else if (count($columns) == 1) {
                $columns = implode('', $columns);
                $sql = 'SELECT ' . $columns . ' FROM ' . $this->tableName . ';';
            } else {
                $sql = 'SELECT * FROM ' . $this->tableName . ';';
            }
            $stmt = $this->connection->prepare($sql);
            try {
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $data;
            } catch (Exception $e) {
                throw new Exception('An error occured while executing the query.');
            }
        } else {
            throw new Exception('Use a table first.');
        }
    }

    public function listJSON(array $columns = [])
    {
        $json = $this->list($columns);
        return json_encode($json);
    }

    public function fetch(array $columns, string $where, string $whereValue)
    {
        $whereValue = $this->sanitize_value($whereValue);

        if (count($columns) < 1 || $where == '' || $whereValue == '') {
            throw new Exception('Missing or empty arguments passed to function : fetch.');
        }
        if (count($columns) > 1) {
            $columns = implode(', ', $columns);
            $sql = 'SELECT ' . $columns . ' FROM ' . $this->tableName . ' WHERE ' . $where . ' = ' . $whereValue . ';';
        } else if (count($columns) == 1) {
            $columns = implode('', $columns);
            $sql = 'SELECT ' . $columns . ' FROM ' . $this->tableName . ' WHERE ' . $where . ' = ' . $whereValue . ';';
        } else {
            throw new Exception('Missing or empty arguments passed to function : fetch.');
        }
        $stmt = $this->connection->prepare($sql);
        try {
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $data;
        } catch (Exception $e) {
            throw new Exception('An error occured while executing the query.');
        }
    }

    public function fetchJSON(array $columns, string $where, string $whereValue)
    {
        $json = $this->fetch($columns, $where, $whereValue);
        return json_encode($json);
    }

    public function create(array $columns)
    {
        $columns = $this->sanitize_array_values($columns);
        if (count($columns) < 1) {
            throw new Exception('Missing or empty arguments passed to function : create.');
        }
        if (count($columns) !== count($this->columns)) {
            throw new Exception('Number of input parameters does not match number of columns defined in the current table.');
        }
        $columns_keys = array_keys($columns);
        $columns_values = array_values($columns);
        $string_columns = '';
        $string_values = '';
        foreach ($columns_keys as $column) {
            $string_columns .= $column . ', ';
            $string_values .= '?, ';
        }
        $string_columns = rtrim($string_columns, ', ');
        $string_values = rtrim($string_values, ', ');
        $sql = 'INSERT INTO ' . $this->tableName . ' (' . $string_columns . ') VALUES (' . $string_values . ');';
        $stmt = $this->connection->prepare($sql);
        try {
            $stmt->execute($columns_values);
            return true;
        } catch (Exception $e) {
            throw new Exception('An error occured while executing the query.');
        }
    }

    public function update(array $columns, string $where, string $whereValue)
    {
        $whereValue = $this->sanitize_value($whereValue);
        $columns = $this->sanitize_array_values($columns);
        if (count($columns) < 1 || $where == '' || $whereValue == '') {
            throw new Exception('Missing or empty arguments passed to function : update.');
        }

        $columns_values = [];
        foreach ($columns as $key => $value) {
            $columns_values[] = $key . ' = ?';
        }
        $string_columns = implode(', ', $columns_values);
        $sql = 'UPDATE ' . $this->tableName . ' SET ' . $string_columns . ' WHERE ' . $where . ' = ?;';
        $stmt = $this->connection->prepare($sql);

        try {
            $values = array_values($columns);
            $new_params = array_merge($values, [$whereValue]);
            $stmt->execute($new_params);
            return true;
        } catch (Exception $e) {
            throw new Exception('An error occured while executing the query.');
        }
    }

    public function delete(string $where, string $whereValue)
    {
        $whereValue = $this->sanitize_value($whereValue);
        if ($where == '' || $whereValue == '') {
            throw new Exception('Missing or empty arguments passed to function : delete.');
        }
        $sql = 'DELETE FROM ' . $this->tableName . ' WHERE ' . $where . ' = ?;';
        $stmt = $this->connection->prepare($sql);
        try {
            $stmt->execute(array($whereValue));
            return true;
        } catch (Exception $e) {
            throw new Exception('An error occured while executing the query.');
        }
    }

    public function authenticate(array $credentials)
    {
        if (count($credentials) > 2) {
            throw new Exception('The arguments passed to function : authenticate contain more than 2 items.');
        }

        $credentialsKeys = array_keys($credentials);
        $credentialsValues = array_values($credentials);

        $credentialXKey = $credentialsKeys[0];
        $credentialXValue = $credentialsValues[0];

        $credentialYKey = $credentialsKeys[1];
        $credentialYValue = $credentialsValues[1];

        $sql = 'SELECT * FROM ' . $this->tableName . ' WHERE ' . $credentialXKey . ' = ? LIMIT 1;';
        $stmt = $this->connection->prepare($sql);
        try {
            $stmt->execute(array($credentialXValue));
            if ($stmt->rowCount() > 0) {
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (password_verify($credentialYValue, $data[0][$credentialYKey])) {
                    return true;
                }
                return false;
            }
            return false;
        } catch (Exception $e) {
            throw new Exception('An error occured while executing the query.');
        }
    }

    public function checkDuplicate(string $uniqueColumn, string $checkValue)
    {
        $sql = 'SELECT * FROM ' . $this->tableName . ' WHERE ' . $uniqueColumn . ' = ? LIMIT 1;';
        $stmt = $this->connection->prepare($sql);
        try {
            $stmt->execute(array($checkValue));
            if ($stmt->rowCount() > 0) {
                return true;
            }
            return false;
        } catch (Exception $e) {
            throw new Exception('An error occured while executing the query.');
        }
    }

    private function sanitize_value($value)
    {
        switch (gettype($value)) {
            case 'string':
                $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // Sanitize HTML entities
                break;
            case 'integer':
                $value = (int) $value; // Convert to integer
                break;
            case 'double':
                $value = (float) $value; // Convert to float
                break;
            case 'boolean':
                $value = (bool) $value; // Convert to boolean
                break;
            default:
                $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // Sanitize HTML entities
                break;
        }
        return $value;
    }

    private function sanitize_array_values(array $data)
    {
        foreach ($data as &$value) {
            switch (gettype($value)) {
                case 'string':
                    $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // Sanitize HTML entities
                    break;
                case 'integer':
                    $value = (int) $value; // Convert to integer
                    break;
                case 'double':
                    $value = (float) $value; // Convert to float
                    break;
                case 'boolean':
                    $value = (bool) $value; // Convert to boolean
                    break;
                default:
                    $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'); // Sanitize HTML entities
                    break;
            }
        }
        unset($value); // Unset the reference to the last value to avoid unexpected behavior
        return $data;
    }
}
