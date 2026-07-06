<?php
// Migration one-shot : ajoute la colonne signature à la table users
require_once __DIR__ . '/includes/db.php';

if (($_GET['secret'] ?? '') !== 'emuci2026import') {
    http_response_code(403); exit('Accès refusé.');
}

$exists = (int) db_fetch_value(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'signature'"
);

if ($exists > 0) {
    echo "ℹ️ La colonne <strong>signature</strong> existe déjà dans la table <em>users</em>.";
} else {
    try {
        db_query("ALTER TABLE users ADD COLUMN signature LONGTEXT NULL AFTER telephone");
        echo "✅ Migration OK : colonne <strong>signature</strong> ajoutée à la table <em>users</em>.";
    } catch (\Throwable $e) {
        echo "❌ Erreur : " . htmlspecialchars($e->getMessage());
    }
}
