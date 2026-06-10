<?php
// ============================================================
//  includes/db.php  —  Connexion PDO centralisée
//  Compatible XAMPP (localhost) et Railway (variables env)
// ============================================================

define('DB_HOST',    getenv('MYSQLHOST')     ?: 'localhost');
define('DB_NAME',    getenv('MYSQLDATABASE') ?: 'stockapp');
define('DB_USER',    getenv('MYSQLUSER')     ?: 'root');
define('DB_PASS',    getenv('MYSQLPASSWORD') ?: '');
define('DB_PORT',    getenv('MYSQLPORT')     ?: '3306');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',    'DigiStock');
define('APP_VERSION', '1.0.0');
define('APP_URL',     getenv('APP_URL') ?: 'http://localhost/stockapp');
define('APP_TIMEZONE','Africa/Abidjan');

date_default_timezone_set(APP_TIMEZONE);
define('SESSION_LIFETIME', 28800);

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('<div style="font-family:Arial;padding:40px;max-width:500px;margin:60px auto;border-left:4px solid #e74c3c;background:#fff;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.1)"><h2 style="color:#e74c3c">Erreur de connexion BDD</h2><p><b>Erreur :</b> ' . htmlspecialchars($e->getMessage()) . '</p></div>');
        }
    }
    return $pdo;
}

function db_query(string $sql, array $params = []): PDOStatement {
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
function db_fetch_all(string $sql, array $params = []): array {
    return db_query($sql, $params)->fetchAll();
}
function db_fetch_one(string $sql, array $params = []): ?array {
    $row = db_query($sql, $params)->fetch();
    return $row ?: null;
}
function db_fetch_value(string $sql, array $params = []) {
    $row = db_query($sql, $params)->fetch(PDO::FETCH_NUM);
    return $row ? $row[0] : null;
}
function db_last_id(): string { return get_db()->lastInsertId(); }
function db_begin(): void    { get_db()->beginTransaction(); }
function db_commit(): void   { get_db()->commit(); }
function db_rollback(): void { if (get_db()->inTransaction()) get_db()->rollBack(); }
