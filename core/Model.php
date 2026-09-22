<?php
// core/Model.php - Lớp cơ sở ORM & Query Helper siêu nhẹ, an toàn & chuẩn mực

class Model {
    protected static $pdo_instance = null;
    protected $table = '';
    protected $primaryKey = 'id';

    public function __construct($table = null) {
        if ($table) {
            $this->table = $table;
        }
        self::initDb();
    }

    public static function initDb() {
        if (self::$pdo_instance === null) {
            global $pdo;
            if (!$pdo) {
                require_once __DIR__ . '/../config/database.php';
            }
            self::$pdo_instance = $pdo;
        }
        return self::$pdo_instance;
    }

    public static function pdo() {
        return self::initDb();
    }

    public static function table($tableName) {
        return new self($tableName);
    }

    public function find($id) {
        $stmt = self::pdo()->prepare("SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function all($orderBy = 'id DESC', $limit = null) {
        $sql = "SELECT * FROM `{$this->table}` ORDER BY {$orderBy}";
        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }
        return self::pdo()->query($sql)->fetchAll();
    }

    public function where($column, $value, $operator = '=') {
        $stmt = self::pdo()->prepare("SELECT * FROM `{$this->table}` WHERE `{$column}` {$operator} ?");
        $stmt->execute([$value]);
        return $stmt->fetchAll();
    }

    public function count($whereClause = '1=1', $params = []) {
        $stmt = self::pdo()->prepare("SELECT COUNT(*) FROM `{$this->table}` WHERE {$whereClause}");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function insert(array $data) {
        $columns = array_keys($data);
        $fields = '`' . implode('`, `', $columns) . '`';
        $placeholders = ':' . implode(', :', $columns);

        $sql = "INSERT INTO `{$this->table}` ({$fields}) VALUES ({$placeholders})";
        $stmt = self::pdo()->prepare($sql);

        $bindings = [];
        foreach ($data as $key => $val) {
            $bindings[':' . $key] = $val;
        }

        $stmt->execute($bindings);
        return (int)self::pdo()->lastInsertId();
    }

    public function update($id, array $data) {
        $setParts = [];
        $bindings = [':_id' => $id];

        foreach ($data as $key => $val) {
            $setParts[] = "`{$key}` = :{$key}";
            $bindings[':' . $key] = $val;
        }

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $setParts) . " WHERE `{$this->primaryKey}` = :_id";
        $stmt = self::pdo()->prepare($sql);
        return $stmt->execute($bindings);
    }

    public function delete($id) {
        $stmt = self::pdo()->prepare("DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?");
        return $stmt->execute([$id]);
    }

    public function paginate($page = 1, $perPage = 10, $whereClause = '1=1', $params = [], $select = '*', $orderBy = 'id DESC') {
        $page = max(1, (int)$page);
        $perPage = max(1, (int)$perPage);
        $offset = ($page - 1) * $perPage;

        // Đếm tổng
        $countStmt = self::pdo()->prepare("SELECT COUNT(*) FROM `{$this->table}` WHERE {$whereClause}");
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();
        $totalPages = ceil($totalRecords / $perPage);

        // Lấy dữ liệu
        $dataStmt = self::pdo()->prepare("SELECT {$select} FROM `{$this->table}` WHERE {$whereClause} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}");
        $dataStmt->execute($params);
        $items = $dataStmt->fetchAll();

        return [
            'items' => $items,
            'total' => $totalRecords,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages
        ];
    }
}
