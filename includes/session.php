<?php
// ============================================================
//  includes/session.php — Authentification & Droits v3
//  Gère : profils normaux + Support IT sous-rôles + délégations
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => SESSION_LIFETIME,
        'cookie_httponly'  => true,
        'cookie_samesite'  => 'Lax',
    ]);
}

// ── Retourner l'utilisateur connecté (cache par requête uniquement — pas de session cache pour éviter les données périmées)
function current_user(): ?array {
    static $user_cached = null;
    if ($user_cached !== null) return $user_cached;
    if (!isset($_SESSION['user_id'])) return null;

    $u = db_fetch_one(
        "SELECT u.*, r.slug AS role_slug, r.nom AS role_nom, s.nom AS site_nom
         FROM users u
         LEFT JOIN roles r ON r.id = u.role_id
         LEFT JOIN sites s ON s.id = u.site_id
         WHERE u.id = ? AND u.actif = 1",
        [$_SESSION['user_id']]
    );
    if (!$u) { session_destroy(); return null; }

    // Charger les sous-rôles Support IT
    if ($u['role_slug'] === 'support_it') {
        $sous_roles = db_fetch_all(
            "SELECT sous_role FROM support_it_roles WHERE user_id=? AND actif=1",
            [$u['id']]
        );
        $u['support_it_sous_roles'] = array_column($sous_roles, 'sous_role');
    } else {
        $u['support_it_sous_roles'] = [];
    }

    // Charger les délégations reçues (pour Gestionnaire Opération)
    if ($u['role_slug'] === 'gestionnaire_operation') {
        $deleg = db_fetch_all(
            "SELECT module FROM delegations WHERE gestionnaire_id=? AND actif=1",
            [$u['id']]
        );
        $u['delegations_recues'] = array_column($deleg, 'module');
    } else {
        $u['delegations_recues'] = [];
    }

    $user_cached = $u;
    return $u;
}

function is_logged_in(): bool { return isset($_SESSION['user_id']); }

function require_auth(): void {
    if (!is_logged_in()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

// ── Vérifier si l'utilisateur a un droit sur un module
function can(string $module, string $droit = 'can_read'): bool {
    $user = current_user();
    if (!$user) return false;

    // Admin et superadmin ont tout
    if (in_array($user['role_slug'], ['admin', 'superadmin'])) return true;

    // Support IT : vérifier les sous-rôles actifs
    if ($user['role_slug'] === 'support_it') {
        return _support_it_can($user, $module, $droit);
    }

    // Gestionnaire Opération : droits de base + délégations
    if ($user['role_slug'] === 'gestionnaire_operation') {
        $deleg = $user['delegations_recues'] ?? [];
        // Modules délégués = droits étendus
        if (in_array($module, $deleg)) {
            return _check_permission_db($user['role_id'], $module, $droit);
        }
        // Modules non délégués = lecture seule si déjà has_read
        if ($droit !== 'can_read') return false;
    }

    return _check_permission_db($user['role_id'], $module, $droit);
}

function _check_permission_db(int $role_id, string $module, string $droit): bool {
    $val = db_fetch_value(
        "SELECT $droit FROM permissions WHERE role_id=? AND module=?",
        [$role_id, $module]
    );
    return (bool)$val;
}

function _support_it_can(array $user, string $module, string $droit): bool {
    $sous_roles = $user['support_it_sous_roles'] ?? [];

    $acces = [
        'maintenance'            => ['interventions','equipements','sites'],
        'controleur_production'  => ['import_emuci','point_emuci','equipements','sites'],
        'gestionnaire_bobines'   => ['bobines','inventaire_bobines','equipements','sites'],
    ];

    $modules_autorises = [];
    foreach ($sous_roles as $sr) {
        $modules_autorises = array_merge($modules_autorises, $acces[$sr] ?? []);
    }
    $modules_autorises = array_unique($modules_autorises);

    if (!in_array($module, $modules_autorises)) return false;

    // Vérifier le droit spécifique en BDD
    return _check_permission_db($user['role_id'], $module, $droit);
}

function require_permission(string $module, string $droit = 'can_read'): void {
    if (!can($module, $droit)) {
        http_response_code(403);
        include __DIR__ . '/../templates/403.php';
        exit;
    }
}

function is_ajax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function json_response(bool $success, string $message = '', $data = null): never {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

function logout(): void {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// ── Helpers profil
function is_support_it_with(string $sous_role): bool {
    $u = current_user();
    if (!$u || $u['role_slug'] !== 'support_it') return false;
    return in_array($sous_role, $u['support_it_sous_roles'] ?? []);
}

function has_delegation(string $module): bool {
    $u = current_user();
    if (!$u || $u['role_slug'] !== 'gestionnaire_operation') return false;
    return in_array($module, $u['delegations_recues'] ?? []);
}

function invalidate_user_cache(): void {
    unset($_SESSION['user_cache']);
}
