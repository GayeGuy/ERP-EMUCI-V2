<?php
// ============================================================
//  includes/db.php  —  Connexion PDO centralisée (PostgreSQL / Neon)
// ============================================================
//
//  La configuration est lue depuis les variables d'environnement
//  (Render, Neon, Docker...). Aucun secret n'est stocké en dur.
//  En développement local, des valeurs par défaut sont utilisées.
//
//  Variables attendues :
//    DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
//    DB_SSLMODE  (require pour Neon, disable/prefer en local)
//    APP_URL     (URL publique de l'application)
// ------------------------------------------------------------

/**
 * Récupère une variable d'environnement (getenv + $_ENV + $_SERVER),
 * avec une valeur par défaut si absente ou vide.
 */
function env(string $key, ?string $default = null): ?string {
    $val = getenv($key);
    if ($val === false || $val === '') {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }
    return ($val === null || $val === '') ? $default : $val;
}

// --- Configuration base de données -------------------------------------
define('DB_HOST',    env('DB_HOST',    'localhost'));
define('DB_PORT',    env('DB_PORT',    '5432'));
define('DB_NAME',    env('DB_NAME',    'stockapp'));
define('DB_USER',    env('DB_USER',    'postgres'));
define('DB_PASS',    env('DB_PASS',    'postgres'));
define('DB_SSLMODE', env('DB_SSLMODE', 'prefer'));   // Neon => require

// --- Configuration application -----------------------------------------
define('APP_NAME',    'ERP EMUCI');
define('APP_VERSION', '2.0.0');
define('APP_URL',     env('APP_URL', 'http://localhost:8080'));
define('APP_TIMEZONE', env('APP_TIMEZONE', 'Africa/Abidjan'));

date_default_timezone_set(APP_TIMEZONE);
define('SESSION_LIFETIME', 28800);
// Filet de sécurité côté serveur pour la déconnexion pour inactivité : le
// minuteur JS (templates/footer.php) ne suffit pas seul, un onglet mis en
// arrière-plan peut être déchargé par le navigateur (JS perdu, la page se
// recharge simplement au retour sans passer par la déconnexion) — ce garde-
// fou tranche sur la dernière requête reçue, indépendamment du JS.
define('INACTIVITY_TIMEOUT', 900);   // 15 min — cf. le même délai côté JS dans templates/footer.php

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_SSLMODE
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Encodage client UTF-8 (équivalent utf8mb4)
            $pdo->exec("SET client_encoding TO 'UTF8'");
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
    try {
        $stmt->execute($params);
    } catch (PDOException $e) {
        // SQLSTATE classe 01xxx = warnings (ex: 01000) — la requête a quand
        // même été exécutée : on logue et on continue.
        if (substr((string)$e->getCode(), 0, 2) === '01') {
            error_log('DB warning (ignored): ' . $e->getMessage());
        } else {
            throw $e;
        }
    }
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
// PostgreSQL : lastInsertId() sans argument renvoie lastval() (dernière
// valeur de séquence obtenue dans la session). Un nom de séquence optionnel
// peut être fourni : "<table>_<colonne>_seq".
function db_last_id(?string $seq = null): string {
    return $seq ? get_db()->lastInsertId($seq) : get_db()->lastInsertId();
}
function db_begin(): void    { get_db()->beginTransaction(); }
function db_commit(): void   { get_db()->commit(); }
function db_rollback(): void { if (get_db()->inTransaction()) get_db()->rollBack(); }
