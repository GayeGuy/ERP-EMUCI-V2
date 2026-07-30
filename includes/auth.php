<?php
// ============================================================
//  includes/auth.php  —  Authentification
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/audit.php';

/**
 * Tente de connecter un utilisateur.
 *
 * @param string $email
 * @param string $password
 * @return array ['success' => bool, 'message' => string]
 */
function auth_login(string $email, string $password): array {
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Adresse email invalide.'];
    }

    $user = db_fetch_one(
        "SELECT * FROM users WHERE email = ? AND actif = 1",
        [$email]
    );

    if (!$user || !password_verify($password, $user['password_hash'])) {
        // Log tentative échouée
        audit_log(null, 'LOGIN', 'auth', null, "Tentative de connexion échouée pour : $email");
        return ['success' => false, 'message' => 'Email ou mot de passe incorrect.'];
    }

    // session déjà démarrée par session.php
    session_regenerate_id(true);
    unset($_SESSION['user_cache']); // forcer rechargement profil

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['role_slug']  = '';   // sera chargé via current_user()
    $_SESSION['_last_activity'] = time();
    $_SESSION['_last_regen']    = time();

    // Mettre à jour last_login
    db_query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);

    // Persister la session en BDD (optionnel)
    try {
        db_query(
            "INSERT INTO sessions (id, user_id, ip_address, user_agent, last_activity)
             VALUES (?, ?, ?, ?, ?)
             ON CONFLICT (id) DO UPDATE SET last_activity = EXCLUDED.last_activity",
            [session_id(), $user['id'], get_ip(), $_SERVER['HTTP_USER_AGENT'] ?? '', time()]
        );
    } catch (Exception $e) { /* table sessions optionnelle */ }

    audit_log($user['id'], 'LOGIN', 'auth', $user['id'],
        "Connexion réussie — IP: " . get_ip());

    return ['success' => true, 'message' => 'Connexion réussie.'];
}

/**
 * Déconnecte l'utilisateur courant.
 */
function auth_logout(): void {
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid) {
        audit_log($uid, 'LOGOUT', 'auth', $uid, 'Déconnexion');
    }
    $_SESSION = [];
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

/**
 * Change le mot de passe d'un utilisateur.
 *
 * @param int    $user_id
 * @param string $old_password   Mot de passe actuel (vide si réinitialisation par admin)
 * @param string $new_password
 * @param bool   $admin_reset    Si true, bypass la vérification de l'ancien mdp
 */
function auth_change_password(int $user_id, string $old_password, string $new_password, bool $admin_reset = false): array {
    if (!is_valid_password($new_password)) {
        return ['success' => false, 'message' => 'Mot de passe invalide (min 8 caractères, au moins une majuscule, un chiffre et un caractère spécial).'];
    }

    $user = db_fetch_one("SELECT * FROM users WHERE id = ?", [$user_id]);
    if (!$user) {
        return ['success' => false, 'message' => 'Utilisateur introuvable.'];
    }

    if (!$admin_reset && !password_verify($old_password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Mot de passe actuel incorrect.'];
    }

    $hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
    db_query("UPDATE users SET password_hash = ? WHERE id = ?", [$hash, $user_id]);

    $actor = current_user();
    audit_log($actor['id'] ?? $user_id, 'UPDATE', 'users', $user_id,
        'Changement de mot de passe' . ($admin_reset ? ' (réinitialisation admin)' : ''));

    return ['success' => true, 'message' => 'Mot de passe modifié avec succès.'];
}

/**
 * Génère un token de réinitialisation de mot de passe et l'envoie par email.
 */
function auth_forgot_password(string $email): array {
    $user = db_fetch_one("SELECT * FROM users WHERE email = ? AND actif = 1", [strtolower($email)]);
    // On retourne toujours succès pour ne pas révéler l'existence d'un compte
    if (!$user) {
        return ['success' => true, 'message' => 'Si cet email existe, un lien vous sera envoyé.'];
    }

    $token  = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', time() + 3600);

    db_query(
        "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?",
        [$token, $expiry, $user['id']]
    );

    // Envoyer l'email (via la fonction mailer dans notifications.php)
    $reset_link = APP_URL . '/reset-password.php?token=' . $token;
    send_reset_email($user['email'], $user['prenom'], $reset_link);

    audit_log($user['id'], 'UPDATE', 'auth', $user['id'], 'Demande de réinitialisation de mot de passe');

    return ['success' => true, 'message' => 'Si cet email existe, un lien vous sera envoyé.'];
}

/**
 * Réinitialise le mot de passe via un token.
 */
function auth_reset_password(string $token, string $new_password): array {
    $user = db_fetch_one(
        "SELECT * FROM users WHERE reset_token = ? AND reset_token_expiry > NOW() AND actif = 1",
        [$token]
    );

    if (!$user) {
        return ['success' => false, 'message' => 'Lien invalide ou expiré.'];
    }

    if (strlen($new_password) < 8) {
        return ['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.'];
    }

    $hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
    db_query(
        "UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?",
        [$hash, $user['id']]
    );

    audit_log($user['id'], 'UPDATE', 'auth', $user['id'], 'Mot de passe réinitialisé via token');

    return ['success' => true, 'message' => 'Mot de passe réinitialisé. Vous pouvez vous connecter.'];
}

/**
 * Crée un nouvel utilisateur (appelé par admin/superadmin).
 */
function auth_create_user(array $data): array {
    $actor = current_user();

    // Vérifications
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Email invalide.'];
    }
    if (empty($data['password']) || !is_valid_password($data['password'])) {
        return ['success' => false, 'message' => 'Mot de passe invalide (min 8 caractères, au moins une majuscule, un chiffre et un caractère spécial).'];
    }
    if (empty($data['nom']) || empty($data['prenom'])) {
        return ['success' => false, 'message' => 'Nom et prénom obligatoires.'];
    }
    if (!empty($data['telephone']) && !is_valid_telephone($data['telephone'])) {
        return ['success' => false, 'message' => 'Téléphone invalide (10 chiffres exactement).'];
    }
    $data['nom']    = format_nom_prenom($data['nom']);
    $data['prenom'] = format_nom_prenom($data['prenom']);
    if ($data['nom'] === '' || $data['prenom'] === '') {
        return ['success' => false, 'message' => 'Nom et prénom ne peuvent contenir que des lettres.'];
    }

    // Email unique
    $exists = db_fetch_value("SELECT COUNT(*) FROM users WHERE email = ?", [strtolower($data['email'])]);
    if ($exists > 0) {
        return ['success' => false, 'message' => 'Cet email est déjà utilisé.'];
    }

    // Un non-superadmin ne peut pas créer un superadmin
    if ($actor['role_slug'] !== 'superadmin' && (int)($data['role_id'] ?? 0) === 1) {
        return ['success' => false, 'message' => 'Action non autorisée.'];
    }

    $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

    db_query(
        "INSERT INTO users (nom, prenom, email, password_hash, role_id, site_id, telephone)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [
            $data['nom'],
            $data['prenom'],
            strtolower(trim($data['email'])),
            $hash,
            (int)$data['role_id'],
            !empty($data['site_id']) ? (int)$data['site_id'] : null,
            $data['telephone'] ?? null,
        ]
    );

    $new_id = (int)db_last_id();

    audit_log($actor['id'], 'CREATE', 'users', $new_id,
        "Création de l'utilisateur {$data['prenom']} {$data['nom']} ({$data['email']})");

    return ['success' => true, 'message' => 'Utilisateur créé avec succès.', 'id' => $new_id];
}

/**
 * Envoie un email de bienvenue / réinitialisation.
 * (Utilise PHPMailer — fonction définie dans notifications.php)
 */
function send_reset_email(string $to, string $prenom, string $link): void {
    // Inclure notifications.php si ce n'est pas déjà fait
    if (!function_exists('mailer_send')) {
        require_once __DIR__ . '/notifications.php';
    }
    $subject = '[' . APP_NAME . '] Réinitialisation de votre mot de passe';
    $body    = "Bonjour $prenom,<br><br>"
             . "Vous avez demandé la réinitialisation de votre mot de passe.<br>"
             . "Cliquez sur le lien ci-dessous (valable 1 heure) :<br><br>"
             . "<a href='$link'>$link</a><br><br>"
             . "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.<br><br>"
             . "— L'équipe " . APP_NAME;
    mailer_send($to, $subject, $body);
}

function get_ip(): string {
    return $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_X_REAL_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';
}

function redirect_to(string $path): void {
    header('Location: ' . APP_URL . $path);
    exit;
}
