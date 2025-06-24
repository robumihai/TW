<?php
/**
 * REMS - Real Estate Management System
 * Database Connection and Query Manager
 * 
 * Handles database connections, queries, and transactions with security features
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
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Establish database connection
     */
    private function connect(): void
    {
        $connectionName = $this->config['default'];
        $connectionConfig = $this->config['connections'][$connectionName];
        
        try {
            if ($connectionConfig['driver'] === 'sqlite') {
                $this->connectSQLite($connectionConfig);
            } elseif ($connectionConfig['driver'] === 'mysql') {
                $this->connectMySQL($connectionConfig);
            } else {
                throw new InvalidArgumentException("Unsupported database driver: {$connectionConfig['driver']}");
            }
            
            // Set additional PDO attributes
            foreach ($connectionConfig['options'] as $option => $value) {
                $this->connection->setAttribute($option, $value);
            }
            
            // Initialize database if using SQLite
            if ($connectionConfig['driver'] === 'sqlite') {
                $this->initializeSQLite();
            }
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new RuntimeException("Database connection failed: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Connect to SQLite database
     */
    private function connectSQLite(array $config): void
    {
        $dbPath = $config['database'];
        $dbDir = dirname($dbPath);
        
        // Create database directory if it doesn't exist
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }
        
        $dsn = "sqlite:{$dbPath}";
        $this->connection = new PDO($dsn, null, null, $config['options']);
        
        // Enable WAL mode for better concurrency
        $this->connection->exec('PRAGMA journal_mode=WAL');
        $this->connection->exec('PRAGMA synchronous=NORMAL');
        $this->connection->exec('PRAGMA temp_store=MEMORY');
        $this->connection->exec('PRAGMA mmap_size=268435456'); // 256MB
    }
    
    /**
     * Connect to MySQL database
     */
    private function connectMySQL(array $config): void
    {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        
        $this->connection = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            $config['options']
        );
    }
    
    /**
     * Initialize SQLite database with schema
     */
    private function initializeSQLite(): void
    {
        $schemaFile = __DIR__ . '/../../database/schema.sql';
        
        if (file_exists($schemaFile)) {
            // Check if tables exist
            $result = $this->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
            
            if (empty($result)) {
                // Execute schema file
                $schema = file_get_contents($schemaFile);
                $this->connection->exec($schema);
            }
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
            $this->logError($sql, $params, $e->getMessage());
            throw new RuntimeException("Query failed: " . $e->getMessage(), 0, $e);
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
            $this->logError($sql, $params, $e->getMessage());
            throw new RuntimeException("Execute failed: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Insert record and return last insert ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);
        
        $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $this->execute($sql, $data);
        return (int) $this->connection->lastInsertId();
    }
    
    /**
     * Update records
     */
    public function update(string $table, array $data, array $where): int
    {
        $setParts = array_map(fn($col) => "$col = :$col", array_keys($data));
        $whereParts = array_map(fn($col) => "$col = :where_$col", array_keys($where));
        
        $sql = "UPDATE $table SET " . implode(', ', $setParts) . " WHERE " . implode(' AND ', $whereParts);
        
        // Merge data and where parameters
        $params = $data;
        foreach ($where as $key => $value) {
            $params["where_$key"] = $value;
        }
        
        return $this->execute($sql, $params);
    }
    
    /**
     * Delete records
     */
    public function delete(string $table, array $where): int
    {
        $whereParts = array_map(fn($col) => "$col = :$col", array_keys($where));
        $sql = "DELETE FROM $table WHERE " . implode(' AND ', $whereParts);
        
        return $this->execute($sql, $where);
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
} 