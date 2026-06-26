<?php
// ============================================================
//  pages/stock_bobines_vue.php
//  Vue synthétique stock bobines : par site × type
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

require_auth();

$user      = current_user();
$role_slug = $user['role_slug'] ?? '';
$page_title  = 'Vue Stock Bobines';
$active_page = 'stock_bobines_vue';

$roles_autorises = ['gestionnaire_stock_bobines','gestionnaire_stock','superviseur_operation',
                    'admin','superadmin','lecteur'];
if (!in_array($role_slug, $roles_autorises)) {
    http_response_code(403); include __DIR__ . '/../templates/403.php'; exit;
}

// ── DONNÉES ─────────────────────────────────────────────────
// Résumé par site × type (bobines actives)
$resume = db_fetch_all(
    "SELECT s.nom AS site_nom, s.id AS site_id,
            b.type_code,
            COUNT(*)                          AS nb_bobines,
            SUM(b.films_restants)             AS total_films,
            SUM(CASE WHEN b.statut='en_cours'  THEN 1 ELSE 0 END) AS nb_en_cours,
            SUM(CASE WHEN b.statut='en_stock'  THEN 1 ELSE 0 END) AS nb_en_stock
     FROM op_bobines b
     JOIN sites s ON s.id = b.site_id
     WHERE b.statut IN ('en_cours','en_stock')
     GROUP BY s.id, b.type_code
     ORDER BY s.nom, b.type_code"
);

// Totaux globaux
$total_bobines = 0; $total_films = 0;
$par_site = []; $par_type = [];
foreach ($resume as $r) {
    $total_bobines += $r['nb_bobines'];
    $total_films   += $r['total_films'];
    $par_site[$r['site_nom']]['nb_bobines'] = ($par_site[$r['site_nom']]['nb_bobines'] ?? 0) + $r['nb_bobines'];
    $par_site[$r['site_nom']]['total_films'] = ($par_site[$r['site_nom']]['total_films'] ?? 0) + $r['total_films'];
    $par_type[$r['type_code']]['nb_bobines'] = ($par_type[$r['type_code']]['nb_bobines'] ?? 0) + $r['nb_bobines'];
    $par_type[$r['type_code']]['total_films'] = ($par_type[$r['type_code']]['total_films'] ?? 0) + $r['total_films'];
}

// Colonnes types (pour l'entête)
$types = array_unique(array_column($resume, 'type_code'));
sort($types);

// Index rapide [site_nom][type_code] → row
$idx = [];
foreach ($resume as $r) $idx[$r['site_nom']][$r['type_code']] = $r;

// Bobines hors site (en_stock sans site_id)
$sans_site = db_fetch_all(
    "SELECT type_code, COUNT(*) AS nb, SUM(films_restants) AS films
     FROM op_bobines WHERE site_id IS NULL AND statut IN ('en_cours','en_stock')
     GROUP BY type_code ORDER BY type_code"
);

include __DIR__ . '/../templates/header.php';
?>
<style>
.vue-table{width:100%;border-collapse:collapse;font-size:14px}
.vue-table th{background:#06033A;color:#fff;padding:11px 14px;text-align:center;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.4px;position:sticky;top:0;z-index:2}
.vue-table th.site-col{text-align:left}
.vue-table td{padding:10px 14px;border-bottom:1px solid var(--border);text-align:center;vertical-align:middle}
.vue-table td.site-name{text-align:left;font-weight:600;color:var(--navy)}
.vue-table tr:hover td{background:#f8f9ff}
.vue-table tr.total-row td{background:#f0f4ff;font-weight:700;border-top:2px solid var(--primary)}
.vue-table tr.sans-site-row td{background:#fff8e1}
.cell-films{font-size:13px;color:#555}
.cell-bobines{font-size:16px;font-weight:700;color:var(--navy)}
.cell-empty{color:#ccc;font-size:12px}
.badge-type{display:inline-block;background:#e8edf8;color:#06033A;border-radius:6px;padding:2px 7px;font-size:11px;font-weight:700}
.kpi-bar{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:24px}
.kpi{background:#fff;border-radius:12px;border:1px solid var(--border);border-left:4px solid var(--primary);padding:14px 18px}
.kpi-v{font-size:26px;font-weight:800;color:var(--navy)}
.kpi-l{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-top:3px}
.overflow-wrap{overflow-x:auto;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07)}
</style>

<div class="page-header" style="margin-bottom:22px">
  <div>
    <h1 class="page-title">🎞️ Vue Stock Bobines</h1>
    <p style="color:var(--muted);font-size:14px;margin:4px 0 0">Synthèse par site et par type — bobines actives</p>
  </div>
</div>

<!-- KPI -->
<div class="kpi-bar">
  <div class="kpi">
    <div class="kpi-v"><?= $total_bobines ?></div>
    <div class="kpi-l">Bobines actives</div>
  </div>
  <div class="kpi" style="border-left-color:#2e7d32">
    <div class="kpi-v" style="color:#2e7d32"><?= number_format($total_films) ?></div>
    <div class="kpi-l">Films restants (total)</div>
  </div>
  <div class="kpi" style="border-left-color:#1565c0">
    <div class="kpi-v" style="color:#1565c0"><?= count($par_site) ?></div>
    <div class="kpi-l">Sites actifs</div>
  </div>
  <div class="kpi" style="border-left-color:#7b1fa2">
    <div class="kpi-v" style="color:#7b1fa2"><?= count($types) ?></div>
    <div class="kpi-l">Types de bobine</div>
  </div>
</div>

<!-- TABLEAU PRINCIPAL -->
<div class="overflow-wrap">
<table class="vue-table">
  <thead>
    <tr>
      <th class="site-col" style="min-width:160px">Site</th>
      <?php foreach ($types as $t): ?>
      <th style="min-width:100px"><span class="badge-type"><?= h($t) ?></span></th>
      <?php endforeach; ?>
      <th style="min-width:110px;border-left:2px solid rgba(255,255,255,.2)">Total</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($par_site as $site_nom => $site_totaux): ?>
    <tr>
      <td class="site-name"><?= h($site_nom) ?></td>
      <?php foreach ($types as $t):
            $cell = $idx[$site_nom][$t] ?? null; ?>
      <td>
        <?php if ($cell): ?>
          <div class="cell-bobines"><?= $cell['nb_bobines'] ?></div>
          <div class="cell-films"><?= number_format($cell['total_films']) ?> films</div>
          <?php if ($cell['nb_en_cours'] > 0): ?>
          <div style="font-size:11px;color:#1976d2;margin-top:2px"><?= $cell['nb_en_cours'] ?> en cours</div>
          <?php endif; ?>
        <?php else: ?>
          <span class="cell-empty">—</span>
        <?php endif; ?>
      </td>
      <?php endforeach; ?>
      <td style="border-left:2px solid var(--border)">
        <div class="cell-bobines"><?= $site_totaux['nb_bobines'] ?></div>
        <div class="cell-films"><?= number_format($site_totaux['total_films']) ?> films</div>
      </td>
    </tr>
    <?php endforeach; ?>

    <?php if (!empty($sans_site)): ?>
    <tr class="sans-site-row">
      <td class="site-name" style="color:#e65100">📦 En dépôt (sans site)</td>
      <?php foreach ($types as $t):
            $found = null;
            foreach ($sans_site as $ss) { if ($ss['type_code'] === $t) { $found = $ss; break; } } ?>
      <td>
        <?php if ($found): ?>
          <div class="cell-bobines"><?= $found['nb'] ?></div>
          <div class="cell-films"><?= number_format($found['films']) ?> films</div>
        <?php else: ?>
          <span class="cell-empty">—</span>
        <?php endif; ?>
      </td>
      <?php endforeach; ?>
      <td style="border-left:2px solid var(--border)">
        <div class="cell-bobines"><?= array_sum(array_column($sans_site,'nb')) ?></div>
        <div class="cell-films"><?= number_format(array_sum(array_column($sans_site,'films'))) ?> films</div>
      </td>
    </tr>
    <?php endif; ?>

    <!-- Ligne totaux -->
    <tr class="total-row">
      <td class="site-name">TOTAL</td>
      <?php foreach ($types as $t): ?>
      <td>
        <?php $tc = $par_type[$t] ?? null; ?>
        <?php if ($tc): ?>
          <div class="cell-bobines"><?= $tc['nb_bobines'] ?></div>
          <div class="cell-films"><?= number_format($tc['total_films']) ?> films</div>
        <?php else: ?><span class="cell-empty">—</span><?php endif; ?>
      </td>
      <?php endforeach; ?>
      <td style="border-left:2px solid var(--border)">
        <div class="cell-bobines"><?= $total_bobines ?></div>
        <div class="cell-films"><?= number_format($total_films) ?> films</div>
      </td>
    </tr>
  </tbody>
</table>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
