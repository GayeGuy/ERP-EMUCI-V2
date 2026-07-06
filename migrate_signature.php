<?php
// Migration one-shot : ajoute la colonne signature à la table users
require_once __DIR__ . '/includes/db.php';

if (($_GET['secret'] ?? '') !== 'emuci2026import') {
    http_response_code(403); exit('Accès refusé.');
}

try {
    db_query("ALTER TABLE users ADD COLUMN IF NOT EXISTS signature LONGTEXT NULL AFTER telephone");
    echo "✅ Migration OK : colonne <strong>signature</strong> ajoutée à la table <em>users</em>.";
} catch (\Throwable $e) {
    echo "❌ Erreur : " . htmlspecialchars($e->getMessage());
}
