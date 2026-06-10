<?php
// ============================================================
//  includes/db.php  —  Connexion PDO centralisée
// ============================================================

// Lire les variables d'environnement Railway
$mysql_url = getenv('MYSQL_URL') 
          ?: ($_ENV['MYSQL_URL'] ?? null)
          ?: ($_SERVER['MYSQL_URL'] ?? null);

if ($mysql_url && $mysql_url !== 'NOT SET') {
    $parts = parse_url($mysql_url);
    define('DB_HOST', $parts['host']);
    define('DB_PORT', (string)($parts['port'] ?? 3306));
    define('DB_NAME', ltrim($parts['path'] ?? '/railway', '/'));
    define('DB_USER', $parts['user'] ?? 'root');
    define('DB_PASS', urldecode($parts['pass'] ?? ''));
} else {
    // Fallback XAMPP local
    define('DB_HOST', 'localhost');
    define('DB_PORT', '3306');
    define('DB_NAME', 'stockapp');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

define('DB_CHARSET', 'utf8mb4');
define('APP_NAME',    'DigiStock');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'https://stockapp-production-e306.up.railway.app');
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
            die('<div style="font-family:Arial;padding:40px;color:#e74c3c"><h2>Erreur DB</h2><p>' . htmlspecialchars($e->getMessage()) . '</p></div>');
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
