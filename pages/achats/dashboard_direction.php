<?php
// ============================================================
//  pages/achats/dashboard_direction.php — Vue resserrée direction (J9)
//  Dépenses totales, budget consommé par département, économies (V2),
//  performance globale des trois délais — sans détail ligne par ligne.
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/achats.php';

require_auth();
$user = current_user();
require_permission('achats_dashboard', 'can_read');
$_SESSION['groupe_actif'] = 'ACHATS';
$page_title  = 'Dashboard Direction';
$active_page = 'achats_dashboard_direction';

// Même filtre par département que le dashboard opérationnel (point 21) —
// élargi ici seulement dans la mesure où le rôle le permet déjà (même
// fonction, ach_perimetre_departements ne distingue pas les deux écrans).
$perimetre   = ach_perimetre_departements($user);
$vue_globale = $perimetre === null;

$du = trim($_GET['du'] ?? '') ?: date('Y-m-d', strtotime('-30 days'));
$au = trim($_GET['au'] ?? '') ?: date('Y-m-d');
$exercice = (int)($_GET['exercice'] ?? date('Y'));

$perimetre_vide = is_array($perimetre) && empty($perimetre);

$depense_totale = 0;
$budget_dept = [];
$kpis = null;
if (!$perimetre_vide) {
    [$clause, $pd] = ach_clause_departement($perimetre, 'f');
    $depense_totale = (int) db_fetch_value(
        "SELECT COALESCE(SUM(fl.montant_ttc),0) FROM feb_lignes fl JOIN feb f ON f.id=fl.feb_id
          WHERE fl.arbitrage='achat' AND f.date_soumission BETWEEN ? AND ?$clause",
        array_merge([$du, $au], $pd)
    );
    $budget_dept = ach_budget_departements($perimetre, $exercice);
    $kpis = ach_dashboard_kpis($user, $perimetre, $du, $au);
}

$exercices_dispo = array_unique(array_merge(
    array_column(db_fetch_all("SELECT DISTINCT exercice FROM lignes_budgetaires ORDER BY exercice DESC"), 'exercice'),
    [(int)date('Y')]
));
rsort($exercices_dispo);

include __DIR__ . '/../../templates/header.php';
?>
<style>
.dash-toolbar{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:18px}
.ach-fg label{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px}
.ach-fg select,.ach-fg input{padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-family:inherit}
.hero-tile{background:linear-gradient(135deg,#B45309 0%,#F59E0B 100%);border-radius:16px;padding:22px 26px;color:white;margin-bottom:22px}
.hero-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:34px;font-weight:900}
.hero-lbl{font-size:13px;opacity:.9;margin-top:4px}
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:22px}
.kpi-tile{background:white;border:1px solid var(--border);border-radius:14px;padding:16px 18px}
.kpi-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;font-weight:900;color:var(--navy);line-height:1}
.kpi-lbl{font-size:12px;color:var(--muted);margin-top:6px}
.section-ttl{font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;font-weight:800;color:var(--navy);margin:24px 0 12px}
.ach-panel{background:white;border:1px solid var(--border);border-radius:16px;padding:18px 20px}
.dept-row{display:grid;grid-template-columns:160px 1fr 90px;gap:12px;align-items:center;padding:9px 0;border-bottom:1px solid var(--border);font-size:13px}
.dept-row:last-child{border-bottom:none}
.dept-bar-track{height:10px;background:#e1e0d9;border-radius:5px;overflow:hidden}
.dept-bar-fill{height:100%;border-radius:5px}
.ach-empty{padding:20px;text-align:center;color:var(--muted);font-size:13px}
</style>

<form class="dash-toolbar" method="GET">
  <div class="ach-fg"><label for="d-du">Du</label><input type="date" id="d-du" name="du" value="<?= h($du) ?>"></div>
  <div class="ach-fg"><label for="d-au">Au</label><input type="date" id="d-au" name="au" value="<?= h($au) ?>"></div>
  <div class="ach-fg">
    <label for="d-exercice">Exercice (budget)</label>
    <select id="d-exercice" name="exercice">
      <?php foreach ($exercices_dispo as $ex): ?><option value="<?= $ex ?>" <?= $ex === $exercice ? 'selected' : '' ?>><?= $ex ?></option><?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-secondary">Filtrer</button>
</form>

<?php if ($perimetre_vide): ?>
  <div class="ach-panel"><div class="ach-empty">Aucun département dans votre périmètre.</div></div>
<?php else: ?>

<div class="hero-tile">
  <div class="hero-val"><?= fmt_number((float)$depense_totale) ?> XOF</div>
  <div class="hero-lbl">Dépenses totales sur la période (<?= fmt_date($du) ?> — <?= fmt_date($au) ?>)</div>
</div>

<div class="kpi-grid">
  <div class="kpi-tile">
    <div class="kpi-val">—</div>
    <div class="kpi-lbl">Économies réalisées</div>
    <div style="font-size:11px;color:var(--muted);margin-top:4px">Champ posé, calculé nul tant que les négociations ne sont pas historisées (V2).</div>
  </div>
  <div class="kpi-tile">
    <div class="kpi-val"><?= $kpis['taux_livraison_temps'] !== null ? $kpis['taux_livraison_temps'] . '%' : '—' ?></div>
    <div class="kpi-lbl">Livraisons à temps et complètes</div>
  </div>
  <div class="kpi-tile">
    <div class="kpi-val"><?= $kpis['taux_service_stock'] !== null ? $kpis['taux_service_stock'] . '%' : '—' ?></div>
    <div class="kpi-lbl">Taux de service sur stock</div>
  </div>
</div>

<div class="section-ttl">Performance des délais (moyenne / médiane, jours)</div>
<div class="kpi-grid">
  <div class="kpi-tile">
    <div class="kpi-val"><?= $kpis['delai_prise_charge']['moyenne'] ?? '—' ?> / <?= $kpis['delai_prise_charge']['mediane'] ?? '—' ?></div>
    <div class="kpi-lbl">Prise en charge</div>
  </div>
  <div class="kpi-tile">
    <div class="kpi-val"><?= $kpis['delai_validation']['moyenne'] ?? '—' ?> / <?= $kpis['delai_validation']['mediane'] ?? '—' ?></div>
    <div class="kpi-lbl">Validation</div>
  </div>
  <div class="kpi-tile">
    <div class="kpi-val"><?= $kpis['delai_livraison']['moyenne'] ?? '—' ?> / <?= $kpis['delai_livraison']['mediane'] ?? '—' ?></div>
    <div class="kpi-lbl">Livraison</div>
  </div>
</div>

<div class="section-ttl">Budget consommé par département (exercice <?= $exercice ?>)</div>
<div class="ach-panel">
  <?php if (empty($budget_dept)): ?>
    <div class="ach-empty">Aucun département actif.</div>
  <?php else: foreach ($budget_dept as $d):
    $pct = $d['taux'] !== null ? min(100, $d['taux']) : 0;
    $color = $d['taux'] === null ? '#94a3b8' : ($d['taux'] >= 100 ? '#dc2626' : ($d['taux'] >= 80 ? '#d97706' : '#16a34a'));
  ?>
    <div class="dept-row">
      <div style="font-weight:700;color:var(--navy)"><?= h($d['label']) ?></div>
      <div>
        <div class="dept-bar-track"><div class="dept-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div></div>
        <div style="font-size:11.5px;color:var(--muted);margin-top:3px">
          <?= fmt_number((float)$d['engage']) ?> XOF engagés <?= $d['enveloppe'] > 0 ? '/ ' . fmt_number((float)$d['enveloppe']) . ' XOF' : '(non plafonné)' ?>
        </div>
      </div>
      <div style="text-align:right;font-weight:700;color:<?= $color ?>"><?= $d['taux'] !== null ? $d['taux'] . '%' : '—' ?></div>
    </div>
  <?php endforeach; endif; ?>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
