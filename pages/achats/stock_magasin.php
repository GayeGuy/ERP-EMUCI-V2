<?php
// ============================================================
//  pages/achats/stock_magasin.php — Stock au magasin
//  Ce que le magasin a reçu et n'a pas encore expédié vers un département
//  (pages/achats/receptions.php, étapes 1-2). Même définition du magasin
//  que l'arbitrage stock/achat : sites.type = 'magasin' (ach_stock_magasin()
//  dans includes/achats.php).
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/achats.php';

require_auth();
$user = current_user();
require_permission('achats_suivi', 'can_read');
$_SESSION['groupe_actif'] = 'ACHATS';
$page_title  = 'Stock magasin';
$active_page = 'achats_stock_magasin';

$f_site = (int)($_GET['site'] ?? 0);
$f_q    = trim($_GET['q'] ?? '');

$where  = ['ss.quantite > 0', "s.type = 'magasin'", 's.actif = 1'];
$params = [];
if ($f_site) { $where[] = 'ss.site_id = ?'; $params[] = $f_site; }
if ($f_q !== '') { $where[] = '(a.libelle ILIKE ? OR a.code ILIKE ?)'; $params[] = '%' . $f_q . '%'; $params[] = '%' . $f_q . '%'; }

$lignes = db_fetch_all(
    "SELECT ss.quantite, ss.updated_at, s.nom AS site_nom, a.code AS article_code, a.libelle AS article_libelle, a.unite
     FROM stock_site ss
     JOIN sites s    ON s.id = ss.site_id
     JOIN articles a ON a.id = ss.article_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY s.nom, a.libelle",
    $params
);
$total_unites = array_sum(array_column($lignes, 'quantite'));

$sites_magasin = db_fetch_all("SELECT id, nom FROM sites WHERE type='magasin' AND actif=1 ORDER BY nom");

include __DIR__ . '/../../templates/header.php';
?>
<style>
.ach-toolbar{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;justify-content:space-between;margin-bottom:18px}
.ach-filters{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}
.ach-fg{margin:0}
.ach-fg label{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px}
.ach-fg select,.ach-fg input{padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-family:inherit;box-sizing:border-box}
.ach-table-wrap{background:white;border:1px solid var(--border);border-radius:16px;overflow:hidden}
.ach-table{width:100%;border-collapse:collapse;font-size:13px}
.ach-table th{background:#f8fafc;color:var(--muted);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:10px 14px;text-align:left;border-bottom:1px solid var(--border)}
.ach-table td{padding:10px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
.ach-table tr:last-child td{border-bottom:none}
.ach-empty{padding:40px 20px;text-align:center;color:var(--muted);font-size:13px}
.ach-summary{font-size:13px;color:var(--muted)}
@media (max-width:768px) { .ach-fg select, .ach-fg input, .btn { min-height:44px; } }
</style>

<div class="ach-empty" style="text-align:left;background:#FFF7ED;color:#B45309;border-radius:12px;padding:12px 16px;margin-bottom:18px;font-size:13px">
  <i class="ph ph-info" aria-hidden="true"></i>
  Ce qui est arrivé au magasin (réception) et n'a pas encore été expédié vers un département — cf. « Réceptions », étape 2.
  Une fois expédiée, la quantité disparaît d'ici et n'est comptée « au département » qu'après confirmation de réception (étape 3).
</div>

<div class="ach-toolbar">
  <form class="ach-filters" method="GET">
    <div class="ach-fg">
      <label for="f-q">Article (nom ou code)</label>
      <input type="text" id="f-q" name="q" value="<?= h($f_q) ?>" placeholder="ex : ordinateur">
    </div>
    <?php if (count($sites_magasin) > 1): ?>
    <div class="ach-fg">
      <label for="f-site">Site magasin</label>
      <select id="f-site" name="site">
        <option value="0">Tous</option>
        <?php foreach ($sites_magasin as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $f_site === (int)$s['id'] ? 'selected' : '' ?>><?= h($s['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <button type="submit" class="btn btn-secondary">Filtrer</button>
  </form>
  <div class="ach-summary"><?= count($lignes) ?> ligne(s) — <?= fmt_number((float)$total_unites) ?> unité(s) au total</div>
</div>

<div class="ach-table-wrap">
  <?php if (empty($lignes)): ?>
    <?php if (empty($sites_magasin)): ?>
      <div class="ach-empty">Aucun site de type « Magasin » n'est configuré (Administration → Sites).</div>
    <?php else: ?>
      <div class="ach-empty">Rien en stock au magasin pour ces filtres.</div>
    <?php endif; ?>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="ach-table">
    <thead><tr>
      <th>Site</th><th>Article</th><th>Code</th><th>Quantité</th><th>Dernier mouvement</th>
    </tr></thead>
    <tbody>
      <?php foreach ($lignes as $l): ?>
      <tr>
        <td style="font-weight:700;color:var(--navy)"><?= h($l['site_nom']) ?></td>
        <td><?= h($l['article_libelle']) ?></td>
        <td style="font-family:monospace;color:var(--muted)"><?= h($l['article_code']) ?></td>
        <td style="font-weight:700"><?= (int)$l['quantite'] ?> <?= h($l['unite'] ?: '') ?></td>
        <td style="color:var(--muted)"><?= fmt_date($l['updated_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
