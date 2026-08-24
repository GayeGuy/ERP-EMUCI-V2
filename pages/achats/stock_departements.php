<?php
// ============================================================
//  pages/achats/stock_departements.php — Stock par département
//  Traçabilité de « qui détient quoi » : plusieurs départements partagent
//  souvent un même site (le siège). Purement informatif — n'entre jamais
//  dans l'arbitrage stock/achat (cf. ach_stock_magasin() dans achats.php).
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/achats.php';

require_auth();
$user = current_user();
$uid  = (int)$user['id'];

// Le N+1 d'un département n'a pas forcément achats_suivi.can_read (ce n'est
// pas un profil Achats) mais doit pouvoir consulter le stock de SON
// département et sa valorisation — même logique d'ouverture d'écran que
// pages/achats/receptions.php (ach_departements_n1(), $can_dept_reception).
$is_admin        = in_array($user['role_slug'] ?? '', ['admin', 'superadmin'], true);
$departements_n1 = ach_departements_n1($uid);
$can_dept_view   = $is_admin || !empty($departements_n1);

if (!can('achats_suivi', 'can_read') && !$can_dept_view) {
    http_response_code(403);
    include __DIR__ . '/../../templates/403.php';
    exit;
}

// Sans lecture globale, un N+1 ne voit que le stock de son (ses) département(s).
$departements_n1_force = (!can('achats_suivi', 'can_read') && !$is_admin) ? $departements_n1 : [];

$_SESSION['groupe_actif'] = 'ACHATS';
$page_title  = 'Stock par département';
$active_page = 'achats_stock_departements';

$f_departement = (int)($_GET['departement'] ?? 0);
$f_q           = trim($_GET['q'] ?? '');

$where  = ['sd.quantite > 0'];
$params = [];
if ($departements_n1_force) {
    $placeholders = implode(',', array_fill(0, count($departements_n1_force), '?'));
    $where[] = "sd.departement_id IN ($placeholders)";
    array_push($params, ...$departements_n1_force);
}
if ($f_departement) { $where[] = 'sd.departement_id = ?'; $params[] = $f_departement; }
if ($f_q !== '')     { $where[] = '(a.libelle ILIKE ? OR a.code ILIKE ?)'; $params[] = '%' . $f_q . '%'; $params[] = '%' . $f_q . '%'; }

$lignes = db_fetch_all(
    "SELECT sd.quantite, sd.updated_at, d.label AS departement_label, a.code AS article_code, a.libelle AS article_libelle, a.unite, a.prix_unitaire
     FROM stock_departement sd
     JOIN departements d ON d.id = sd.departement_id
     JOIN articles a     ON a.id = sd.article_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY d.label, a.libelle",
    $params
);
$total_valorisation = 0;
foreach ($lignes as &$l) {
    $l['valorisation'] = (int)$l['quantite'] * (int)$l['prix_unitaire'];
    $total_valorisation += $l['valorisation'];
}
unset($l);

$departements_list = $departements_n1_force
    ? db_fetch_all("SELECT id, label FROM departements WHERE actif=1 AND id IN (" . implode(',', array_fill(0, count($departements_n1_force), '?')) . ") ORDER BY label", $departements_n1_force)
    : db_fetch_all("SELECT id, label FROM departements WHERE actif=1 ORDER BY label");

include __DIR__ . '/../../templates/header.php';
?>
<style>
.ach-toolbar{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:18px}
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

<div class="ach-empty" style="text-align:left;background:#EFF6FF;color:#1D4ED8;border-radius:12px;padding:12px 16px;margin-bottom:18px;font-size:13px">
  <i class="ph ph-info" aria-hidden="true"></i>
  Traçabilité uniquement : ce stock est déjà affecté à un département, il n'est pas redisponible pour couvrir une nouvelle demande d'achat (l'arbitrage compare au stock des sites de type Magasin).
</div>

<div class="ach-toolbar">
  <form class="ach-filters" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap" method="GET">
    <div class="ach-fg">
      <label for="f-q">Article (nom ou code)</label>
      <input type="text" id="f-q" name="q" value="<?= h($f_q) ?>" placeholder="ex : ordinateur">
    </div>
    <?php if (count($departements_list) > 1): ?>
    <div class="ach-fg">
      <label for="f-departement">Département</label>
      <select id="f-departement" name="departement">
        <option value="0">Tous</option>
        <?php foreach ($departements_list as $d): ?>
          <option value="<?= $d['id'] ?>" <?= $f_departement === (int)$d['id'] ? 'selected' : '' ?>><?= h($d['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <button type="submit" class="btn btn-secondary">Filtrer</button>
  </form>
  <div class="ach-summary"><?= count($lignes) ?> ligne(s) — <?= fmt_number((float)$total_valorisation) ?> XOF valorisés</div>
</div>

<div class="ach-table-wrap">
  <?php if (empty($lignes)): ?>
    <div class="ach-empty">Aucun stock départemental pour ces filtres.</div>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="ach-table">
    <thead><tr>
      <th>Département</th><th>Article</th><th>Code</th><th>Quantité</th><th>Valorisation</th><th>Dernière réception</th>
    </tr></thead>
    <tbody>
      <?php foreach ($lignes as $l): ?>
      <tr>
        <td style="font-weight:700;color:var(--navy)"><?= h($l['departement_label']) ?></td>
        <td><?= h($l['article_libelle']) ?></td>
        <td style="font-family:monospace;color:var(--muted)"><?= h($l['article_code']) ?></td>
        <td style="font-weight:700"><?= (int)$l['quantite'] ?> <?= h($l['unite'] ?: '') ?></td>
        <td><?= fmt_number((float)$l['valorisation']) ?> XOF</td>
        <td style="color:var(--muted)"><?= fmt_date($l['updated_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="total-row">
        <td colspan="4" style="font-weight:700;text-align:right">Total valorisation</td>
        <td colspan="2" style="font-weight:700"><?= fmt_number((float)$total_valorisation) ?> XOF</td>
      </tr>
    </tfoot>
  </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
