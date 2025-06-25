<?php
/**
 * REMS - Real Estate Management System
 * Database Class - Updated for SQLite
 * 
 * Singleton database connection manager
 */

declare(strict_types=1);

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;
    private array $config;
    private array $queryLog = [];
    private bool $inTransaction = false;
    
    private function __construct()
    {
        $this->config = require __DIR__ . '/../config/database.php';
        $this->connect();
    }
    
    /**
     * Get singleton instance of Database
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    /**
     * Establish database connection
     */
    private function connect(): void
    {
        try {
            $dsn = 'sqlite:' . $this->config['path'];
            
            $this->connection = new PDO(
                $dsn,
                null,
                null,
                $this->config['options']
            );
            
            // Set SQLite specific pragmas for performance
            $this->connection->exec('PRAGMA journal_mode = WAL');
            $this->connection->exec('PRAGMA synchronous = NORMAL');
            $this->connection->exec('PRAGMA cache_size = 1000');
            $this->connection->exec('PRAGMA temp_store = memory');
            $this->connection->exec('PRAGMA foreign_keys = ON');
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get PDO connection
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }
    
    /**
     * Execute a query with parameters (SELECT)
     */
    public function query(string $sql, array $params = []): array
    {
        $startTime = microtime(true);
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll();
            
            $this->logQuery($sql, $params, microtime(true) - $startTime);
            
            return $result;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage() . " SQL: " . $sql);
            throw new RuntimeException("Query failed: " . $e->getMessage());
        }
    }
    
    /**
     * Execute a query and return single row
     */
    public function queryOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params);
        return $result[0] ?? null;
    }
    
    /**
     * Execute a query and return single value
     */
    public function queryScalar(string $sql, array $params = [])
    {
        $result = $this->queryOne($sql, $params);
        return $result ? array_values($result)[0] : null;
    }
    
    /**
     * Execute an INSERT, UPDATE, or DELETE query
     */
    public function execute(string $sql, array $params = []): int
    {
        $startTime = microtime(true);
        
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            $affectedRows = $stmt->rowCount();
            
            $this->logQuery($sql, $params, microtime(true) - $startTime);
            
            return $affectedRows;
        } catch (PDOException $e) {
            error_log("Execute failed: " . $e->getMessage() . " SQL: " . $sql);
            throw new RuntimeException("Execute failed: " . $e->getMessage());
        }
    }
    
    /**
     * Insert record and return last insert ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        try {
            $stmt = $this->connection->prepare($sql);
            
            // Bind parameters with proper types
            foreach ($data as $key => $value) {
                $stmt->bindValue(':' . $key, $value, $this->getPdoType($value));
            }
            
            $stmt->execute();
            return (int)$this->connection->lastInsertId();
        } catch (PDOException $e) {
            error_log("Insert failed: " . $e->getMessage() . " SQL: " . $sql);
            throw new RuntimeException("Insert failed: " . $e->getMessage());
        }
    }
    
    /**
     * Update records
     */
    public function update(string $table, array $data, array $where): bool
    {
        $setClause = implode(', ', array_map(fn($col) => "$col = :$col", array_keys($data)));
        $whereClause = implode(' AND ', array_map(fn($col) => "$col = :where_$col", array_keys($where)));
        
        $sql = "UPDATE $table SET $setClause WHERE $whereClause";
        
        try {
            $stmt = $this->connection->prepare($sql);
            
            // Bind data parameters
            foreach ($data as $key => $value) {
                $stmt->bindValue(':' . $key, $value, $this->getPdoType($value));
            }
            
            // Bind where parameters
            foreach ($where as $key => $value) {
                $stmt->bindValue(':where_' . $key, $value, $this->getPdoType($value));
            }
            
                         return $stmt->execute();
         } catch (PDOException $e) {
             error_log("Update failed: " . $e->getMessage() . " SQL: " . $sql);
             throw new RuntimeException("Update failed: " . $e->getMessage());
         }
    }
    
    /**
     * Delete records
     */
    public function delete(string $table, array $where): bool
    {
        $whereClause = implode(' AND ', array_map(fn($col) => "$col = :$col", array_keys($where)));
        $sql = "DELETE FROM $table WHERE $whereClause";
        
        try {
            $stmt = $this->connection->prepare($sql);
            
            foreach ($where as $key => $value) {
                $stmt->bindValue(':' . $key, $value, $this->getPdoType($value));
            }
            
                         return $stmt->execute();
         } catch (PDOException $e) {
             error_log("Delete failed: " . $e->getMessage() . " SQL: " . $sql);
             throw new RuntimeException("Delete failed: " . $e->getMessage());
         }
    }
    
    /**
     * Start database transaction
     */
    public function beginTransaction(): bool
    {
        if ($this->inTransaction) {
            throw new RuntimeException("Transaction already in progress");
        }
        
        $result = $this->connection->beginTransaction();
        $this->inTransaction = $result;
        return $result;
    }
    
    /**
     * Commit database transaction
     */
    public function commit(): bool
    {
        if (!$this->inTransaction) {
            throw new RuntimeException("No transaction in progress");
        }
        
        $result = $this->connection->commit();
        $this->inTransaction = false;
        return $result;
    }
    
    /**
     * Rollback database transaction
     */
    public function rollback(): bool
    {
        if (!$this->inTransaction) {
            throw new RuntimeException("No transaction in progress");
        }
        
        $result = $this->connection->rollBack();
        $this->inTransaction = false;
        return $result;
    }
    
    /**
     * Check if in transaction
     */
    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }
    
    /**
     * Execute callback within transaction
     */
    public function transaction(callable $callback)
    {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Get table columns information
     */
    public function getTableColumns(string $table): array
    {
        if ($this->config['connections'][$this->config['default']]['driver'] === 'sqlite') {
            $sql = "PRAGMA table_info($table)";
        } else {
            $sql = "SHOW COLUMNS FROM $table";
        }
        
        return $this->query($sql);
    }
    
    /**
     * Check if table exists
     */
    public function tableExists(string $table): bool
    {
        if ($this->config['connections'][$this->config['default']]['driver'] === 'sqlite') {
            $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name = ?";
        } else {
            $sql = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
        }
        
        $result = $this->query($sql, [$table]);
        return !empty($result);
    }
    
    /**
     * Get database size in bytes
     */
    public function getDatabaseSize(): int
    {
        if ($this->config['connections'][$this->config['default']]['driver'] === 'sqlite') {
            $dbPath = $this->config['connections'][$this->config['default']]['database'];
            return file_exists($dbPath) ? filesize($dbPath) : 0;
        } else {
            $sql = "SELECT SUM(data_length + index_length) as size FROM information_schema.TABLES WHERE table_schema = DATABASE()";
            $result = $this->queryScalar($sql);
            return (int) ($result ?? 0);
        }
    }
    
    /**
     * Log query for debugging
     */
    private function logQuery(string $sql, array $params, float $executionTime): void
    {
        if ($this->config['settings']['log_queries']) {
            $this->queryLog[] = [
                'sql' => $sql,
                'params' => $params,
                'execution_time' => $executionTime,
                'timestamp' => date('Y-m-d H:i:s'),
            ];
            
            // Log slow queries
            if ($executionTime > $this->config['settings']['slow_query_threshold']) {
                error_log("Slow query ({$executionTime}s): $sql");
            }
        }
    }
    
    /**
     * Log query error
     */
    private function logError(string $sql, array $params, string $error): void
    {
        $logData = [
            'sql' => $sql,
            'params' => $params,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        
        error_log("Database error: " . json_encode($logData));
    }
    
    /**
     * Get query log
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }
    
    /**
     * Clear query log
     */
    public function clearQueryLog(): void
    {
        $this->queryLog = [];
    }
    
    /**
     * Escape string for LIKE queries
     */
    public function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
    
    /**
     * Build WHERE clause from array
     */
    public function buildWhereClause(array $conditions): array
    {
        $whereParts = [];
        $params = [];
        
        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                // Handle IN clauses
                $placeholders = array_map(fn($i) => ":where_{$column}_{$i}", array_keys($value));
                $whereParts[] = "$column IN (" . implode(', ', $placeholders) . ")";
                
                foreach ($value as $i => $v) {
                    $params["where_{$column}_{$i}"] = $v;
                }
            } elseif (strpos($column, ' ') !== false) {
                // Handle operators (e.g., "age >")
                $whereParts[] = $column . " :where_" . str_replace(' ', '_', $column);
                $params["where_" . str_replace(' ', '_', $column)] = $value;
            } else {
                // Simple equality
                $whereParts[] = "$column = :where_$column";
                $params["where_$column"] = $value;
            }
        }
        
        return [
            'clause' => implode(' AND ', $whereParts),
            'params' => $params
        ];
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Get appropriate PDO parameter type
     */
    private function getPdoType($value): int
    {
        if (is_null($value)) {
            return PDO::PARAM_NULL;
        } elseif (is_bool($value)) {
            return PDO::PARAM_BOOL;
        } elseif (is_int($value)) {
            return PDO::PARAM_INT;
        } else {
            return PDO::PARAM_STR;
        }
    }
    
    /**
     * Check if database file exists and create if needed
     */
    public function ensureDatabaseExists(): void
    {
        $dbPath = $this->config['path'];
        $dbDir = dirname($dbPath);
        
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        
        if (!file_exists($dbPath)) {
            // Create empty database file
            touch($dbPath);
            chmod($dbPath, 0644);
        }
    }
    
    /**
     * Initialize database with schema if empty
     */
    public function initializeSchema(): void
    {
        $schemaFile = __DIR__ . '/../../database/schema.sql';
        
        if (file_exists($schemaFile)) {
            $schema = file_get_contents($schemaFile);
            $this->connection->exec($schema);
        }
    }
} 