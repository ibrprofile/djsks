<?php
/**
 * Класс для работы с базой данных
 * Использует PDO с подготовленными запросами для защиты от SQL-инъекций
 */

class Database {
    private static $instance = null;
    private $connection;
    private $transactionLevel = 0;
    
    private function __construct() {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_CHARSET
            );
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Ошибка подключения к базе данных");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Выполнение запроса с параметрами
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("Ошибка выполнения запроса");
        }
    }
    
    /**
     * Получить все строки
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Получить одну строку
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Получить одно значение
     */
    public function fetchColumn($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Вставка данных
     */
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }
    
    /**
     * Обновление данных
     */
    public function update($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Удаление данных
     */
    public function delete($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Начать транзакцию
     */
    public function beginTransaction() {
        if ($this->transactionLevel === 0) {
            $this->connection->beginTransaction();
        }
        $this->transactionLevel++;
    }
    
    /**
     * Подтвердить транзакцию
     */
    public function commit() {
        $this->transactionLevel--;
        if ($this->transactionLevel === 0) {
            $this->connection->commit();
        }
    }
    
    /**
     * Откатить транзакцию
     */
    public function rollback() {
        if ($this->transactionLevel > 0) {
            $this->transactionLevel = 0;
            $this->connection->rollBack();
        }
    }
    
    /**
     * Экранирование для LIKE запросов
     */
    public function escapeLike($string) {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $string);
    }
    
    private function __clone() {}
    private function __wakeup() {}
}
?>
