<?php
// ============================================================
//  migrate_site_id.php — Migration one-shot (variante MySQL)
//  Ajoute site_id dans articles et di_demandes
//  Usage : /migrate_site_id.php?secret=migrate2026site
// ============================================================
$secret = $_GET['secret'] ?? '';
if ($secret !== 'migrate2026site') { http_response_code(403); die('Accès refusé'); }

require_once __DIR__ . '/includes/db.php';
$db  = get_db();
$log = [];

function run(PDO $db, string $label, string $sql, array &$log): void {
    try {
        $db->exec($sql);
        $log[] = ['ok', $label];
    } catch (Throwable $e) {
        $log[] = ['err', $label . ' : ' . $e->getMessage()];
    }
}

// ── 1. articles — ajouter site_id (nullable, FK sites)
// MySQL 8 : ADD COLUMN IF NOT EXISTS supporté ; la clé étrangère et l'index
// s'ajoutent en instructions séparées (pas de REFERENCES inline sur ADD
// COLUMN). Un rejeu tombe sur une erreur "duplicate" non bloquante, déjà
// captée par run() ci-dessus.
run($db, 'articles: ADD COLUMN site_id',
    "ALTER TABLE articles ADD COLUMN IF NOT EXISTS site_id INT UNSIGNED NULL",
    $log);

run($db, 'articles: FK site_id',
    "ALTER TABLE articles ADD CONSTRAINT fk_articles_site_id FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL",
    $log);

run($db, 'articles: INDEX site_id',
    "CREATE INDEX idx_articles_site_id ON articles(site_id)",
    $log);

// ── 2. di_demandes — ajouter site_id (nullable, FK sites)
run($db, 'di_demandes: ADD COLUMN site_id',
    "ALTER TABLE di_demandes ADD COLUMN IF NOT EXISTS site_id INT UNSIGNED NULL",
    $log);

run($db, 'di_demandes: FK site_id',
    "ALTER TABLE di_demandes ADD CONSTRAINT fk_di_demandes_site_id FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL",
    $log);

run($db, 'di_demandes: INDEX site_id',
    "CREATE INDEX idx_di_demandes_site_id ON di_demandes(site_id)",
    $log);

// ── 3. Backfill di_demandes.site_id depuis users.site_id du demandeur
run($db, 'di_demandes: BACKFILL site_id depuis demandeur',
    "UPDATE di_demandes d
     JOIN users u ON u.id = d.demandeur_id
     SET d.site_id = u.site_id
     WHERE d.site_id IS NULL
       AND u.site_id IS NOT NULL",
    $log);

// ── Résultat
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><title>Migration site_id</title>
<style>body{font-family:system-ui,sans-serif;max-width:700px;margin:40px auto;padding:0 20px}
h2{color:#06033A}
.ok{background:#d1fae5;color:#065f46;padding:8px 14px;border-radius:8px;margin:6px 0;font-size:13px}
.err{background:#fee2e2;color:#991b1b;padding:8px 14px;border-radius:8px;margin:6px 0;font-size:13px}
.ok::before{content:'✓  '}.err::before{content:'✗  '}
</style></head><body>
<h2>Migration — Ajout site_id</h2>
<?php foreach ($log as [$status, $msg]): ?>
<div class="<?= $status ?>"><?= htmlspecialchars($msg) ?></div>
<?php endforeach; ?>
<p style="margin-top:20px;font-size:12px;color:#64748b">
  Migration terminée. Supprimez ce fichier après vérification.
</p>
</body></html>
