<?php
// ============================================================
//  pages/achats/dashboard.php — Dashboard opérationnel Achats (J9)
//  Réservé à Achats et aux responsables — accès filtré par département
//  pour un responsable qui n'en couvre qu'un (ach_perimetre_departements).
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
$page_title  = 'Dashboard Achats';
$active_page = 'achats_dashboard';

$perimetre = ach_perimetre_departements($user);       // null = vue globale
$vue_globale = $perimetre === null;

// ── AJAX — listes de drill-down des alertes ────────────────
if (is_ajax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $du = trim($_POST['du'] ?? '') ?: date('Y-m-d', strtotime('-30 days'));
    $au = trim($_POST['au'] ?? '') ?: date('Y-m-d');
    $depts = $vue_globale && !empty($_POST['departement']) ? [(int)$_POST['departement']] : $perimetre;

    if ($action === 'alerte_validation') {
        json_response(true, '', ach_alertes_retard_validation($depts));
    }
    if ($action === 'alerte_suivi') {
        $rows = ach_alertes_suivi_retard($depts);
        foreach ($rows as &$r) { $r['ecart'] = (int)$r['quantite_commandee'] - (int)$r['quantite_recue']; }
        unset($r);
        json_response(true, '', $rows);
    }
    if ($action === 'alerte_budget') {
        json_response(true, '', ach_alertes_depassement_budget($depts, $du, $au));
    }
    json_response(false, 'Action inconnue.');
}

// ── PAGE PHP ─────────────────────────────────────────────────
$du = trim($_GET['du'] ?? '') ?: date('Y-m-d', strtotime('-30 days'));
$au = trim($_GET['au'] ?? '') ?: date('Y-m-d');
$f_departement = $vue_globale ? (int)($_GET['departement'] ?? 0) : 0;
$depts_effectifs = $vue_globale ? ($f_departement ? [$f_departement] : null) : $perimetre;

$perimetre_vide = is_array($depts_effectifs) && empty($depts_effectifs);

$kpis = $perimetre_vide ? null : ach_dashboard_kpis($user, $depts_effectifs, $du, $au);
$dep_famille = $perimetre_vide ? [] : ach_repartition_depenses('famille', $depts_effectifs, $du, $au);
$dep_dept    = ($vue_globale && !$perimetre_vide) ? ach_repartition_depenses('departement', $depts_effectifs, $du, $au) : [];
$dep_fourn   = $perimetre_vide ? [] : ach_repartition_depenses('fournisseur', $depts_effectifs, $du, $au);

$nb_alerte_validation = $perimetre_vide ? 0 : count(ach_alertes_retard_validation($depts_effectifs));
$nb_alerte_suivi       = $perimetre_vide ? 0 : count(ach_alertes_suivi_retard($depts_effectifs));
$nb_alerte_budget      = $perimetre_vide ? 0 : count(ach_alertes_depassement_budget($depts_effectifs, $du, $au));

$departements_actifs = db_fetch_all("SELECT id, label FROM departements WHERE actif=1 ORDER BY label");

include __DIR__ . '/../../templates/header.php';
?>
<style>
.dash-toolbar{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:18px}
.ach-fg label{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px}
.ach-fg select,.ach-fg input{padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-family:inherit}
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:22px}
.kpi-tile{background:white;border:1px solid var(--border);border-radius:14px;padding:16px 18px}
.kpi-tile.clic{cursor:pointer;transition:box-shadow .15s}
.kpi-tile.clic:hover{box-shadow:0 4px 16px rgba(0,0,0,.08)}
.kpi-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:26px;font-weight:900;color:var(--navy);line-height:1}
.kpi-lbl{font-size:12px;color:var(--muted);margin-top:6px}
.kpi-sub{font-size:11.5px;color:var(--muted);margin-top:4px}
.alert-tile{background:white;border:1px solid var(--border);border-left:4px solid #ec835a;border-radius:12px;padding:14px 18px;cursor:pointer;transition:box-shadow .15s}
.alert-tile:hover{box-shadow:0 4px 16px rgba(0,0,0,.08)}
.alert-tile.zero{border-left-color:#0ca30c}
.alert-count{font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:900;color:var(--navy)}
.section-ttl{font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;font-weight:800;color:var(--navy);margin:24px 0 12px}
.ach-panel{background:white;border:1px solid var(--border);border-radius:16px;padding:18px 20px}
.ach-barres{display:flex;flex-direction:column;gap:10px}
.ach-barre-row{display:grid;grid-template-columns:140px 1fr 110px;gap:10px;align-items:center;font-size:12.5px}
.ach-barre-label{color:var(--navy);font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ach-barre-track{height:10px;background:#e1e0d9;border-radius:5px;overflow:hidden}
.ach-barre-fill{height:100%;background:#2a78d6;border-radius:5px}
.ach-barre-val{text-align:right;font-weight:700;color:var(--navy);white-space:nowrap}
.ach-empty{padding:20px;text-align:center;color:var(--muted);font-size:13px}
.dash-grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px}
@media(max-width:900px){.dash-grid2{grid-template-columns:1fr}.ach-barre-row{grid-template-columns:100px 1fr 90px}}
.ach-modal-bg{display:none;position:fixed;inset:0;background:rgba(6,3,58,.45);z-index:2000;align-items:center;justify-content:center;padding:20px}
.ach-modal-bg.open{display:flex}
.ach-modal{background:white;border-radius:16px;padding:26px;width:100%;max-width:700px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.ach-table{width:100%;border-collapse:collapse;font-size:13px}
.ach-table th{background:#f8fafc;color:var(--muted);font-size:11.5px;font-weight:700;text-transform:uppercase;padding:8px 10px;text-align:left;border-bottom:1px solid var(--border)}
.ach-table td{padding:8px 10px;border-bottom:1px solid var(--border)}
.ach-table tr.clic{cursor:pointer}
.ach-table tr.clic:hover{background:#f8fafc}
</style>

<form class="dash-toolbar" method="GET">
  <div class="ach-fg"><label for="d-du">Du</label><input type="date" id="d-du" name="du" value="<?= h($du) ?>"></div>
  <div class="ach-fg"><label for="d-au">Au</label><input type="date" id="d-au" name="au" value="<?= h($au) ?>"></div>
  <?php if ($vue_globale): ?>
  <div class="ach-fg">
    <label for="d-dept">Département</label>
    <select id="d-dept" name="departement">
      <option value="0">Tous</option>
      <?php foreach ($departements_actifs as $d): ?>
        <option value="<?= $d['id'] ?>" <?= $f_departement === (int)$d['id'] ? 'selected' : '' ?>><?= h($d['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <button type="submit" class="btn btn-secondary">Filtrer</button>
</form>

<?php if ($perimetre_vide): ?>
  <div class="ach-panel"><div class="ach-empty">Aucun département dans votre périmètre.</div></div>
<?php else: ?>

<div class="kpi-grid">
  <div class="kpi-tile clic" onclick="document.getElementById('kpi-a-traiter-detail').classList.toggle('open')">
    <div class="kpi-val"><?= $kpis['a_traiter'] ?></div>
    <div class="kpi-lbl">À traiter aujourd'hui</div>
    <div class="kpi-sub"><?= $kpis['a_viser'] ?> visa(s) en attente + <?= $kpis['non_prise'] ?> en file non prise en charge</div>
  </div>
  <div class="kpi-tile">
    <div class="kpi-val"><?= $kpis['taux_service_stock'] !== null ? $kpis['taux_service_stock'] . '%' : '—' ?></div>
    <div class="kpi-lbl">Taux de service sur stock</div>
  </div>
  <div class="kpi-tile">
    <div class="kpi-val"><?= $kpis['taux_livraison_temps'] !== null ? $kpis['taux_livraison_temps'] . '%' : '—' ?></div>
    <div class="kpi-lbl">Livraisons à temps et complètes</div>
  </div>
</div>

<div class="section-ttl">Délais (moyenne / médiane, en jours)</div>
<div class="kpi-grid">
  <div class="kpi-tile">
    <div class="kpi-val"><?= $kpis['delai_prise_charge']['moyenne'] ?? '—' ?> / <?= $kpis['delai_prise_charge']['mediane'] ?? '—' ?></div>
    <div class="kpi-lbl">Prise en charge (soumission → prise en charge)</div>
    <div class="kpi-sub"><?= $kpis['delai_prise_charge']['n'] ?> FEB mesurée(s)</div>
  </div>
  <div class="kpi-tile">
    <div class="kpi-val"><?= $kpis['delai_validation']['moyenne'] ?? '—' ?> / <?= $kpis['delai_validation']['mediane'] ?? '—' ?></div>
    <div class="kpi-lbl">Validation (lancement → confirmation)</div>
    <div class="kpi-sub"><?= $kpis['delai_validation']['n'] ?> FEB mesurée(s)</div>
  </div>
  <div class="kpi-tile">
    <div class="kpi-val"><?= $kpis['delai_livraison']['moyenne'] ?? '—' ?> / <?= $kpis['delai_livraison']['mediane'] ?? '—' ?></div>
    <div class="kpi-lbl">Livraison (confirmation → réception réelle)</div>
    <div class="kpi-sub"><?= $kpis['delai_livraison']['n'] ?> ligne(s) mesurée(s)</div>
  </div>
  <div class="kpi-tile">
    <div class="kpi-val"><?= $kpis['delai_da_bc']['moyenne'] ?? '—' ?> / <?= $kpis['delai_da_bc']['mediane'] ?? '—' ?></div>
    <div class="kpi-lbl">DA → BC (Sage)</div>
    <div class="kpi-sub"><?= $kpis['delai_da_bc']['n'] ?> ligne(s) mesurée(s)</div>
  </div>
</div>

<div class="section-ttl">Alertes</div>
<div class="kpi-grid">
  <div class="alert-tile <?= $nb_alerte_validation === 0 ? 'zero' : '' ?>" onclick="dashOuvrirAlerte('validation', 'FEB en retard de validation')">
    <div class="alert-count"><?= $nb_alerte_validation ?></div>
    <div class="kpi-lbl">FEB en retard de validation</div>
  </div>
  <div class="alert-tile <?= $nb_alerte_suivi === 0 ? 'zero' : '' ?>" onclick="dashOuvrirAlerte('suivi', 'Lignes de suivi en retard')">
    <div class="alert-count"><?= $nb_alerte_suivi ?></div>
    <div class="kpi-lbl">Lignes de suivi en retard</div>
  </div>
  <div class="alert-tile <?= $nb_alerte_budget === 0 ? 'zero' : '' ?>" onclick="dashOuvrirAlerte('budget', 'Dépassements budgétaires (alerte)')">
    <div class="alert-count"><?= $nb_alerte_budget ?></div>
    <div class="kpi-lbl">Dépassements budgétaires (mode alerte)</div>
  </div>
</div>

<div class="section-ttl">Répartition des dépenses</div>
<div class="dash-grid2">
  <div class="ach-panel">
    <div style="font-weight:700;color:var(--navy);margin-bottom:12px;font-size:13px">Par famille SYSCOHADA</div>
    <?= ach_html_barres($dep_famille) ?>
  </div>
  <?php if ($vue_globale): ?>
  <div class="ach-panel">
    <div style="font-weight:700;color:var(--navy);margin-bottom:12px;font-size:13px">Par département</div>
    <?= ach_html_barres($dep_dept) ?>
  </div>
  <?php else: ?>
  <div class="ach-panel">
    <div style="font-weight:700;color:var(--navy);margin-bottom:12px;font-size:13px">Par fournisseur</div>
    <?= ach_html_barres($dep_fourn, 'Aucune dépense fournisseur — le référentiel fournisseurs est encore vide.') ?>
  </div>
  <?php endif; ?>
</div>
<?php if ($vue_globale): ?>
<div class="ach-panel" style="margin-top:18px">
  <div style="font-weight:700;color:var(--navy);margin-bottom:12px;font-size:13px">Par fournisseur</div>
  <?= ach_html_barres($dep_fourn, 'Aucune dépense fournisseur — le référentiel fournisseurs est encore vide.') ?>
</div>
<?php endif; ?>

<?php endif; // perimetre_vide ?>

<!-- MODALE drill-down alerte -->
<div class="ach-modal-bg" id="alerte-modal">
  <div class="ach-modal" role="dialog" aria-labelledby="alerte-modal-title">
    <h3 id="alerte-modal-title" style="margin:0 0 14px;font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:var(--navy)"></h3>
    <div id="alerte-modal-body"><div class="ach-empty">Chargement…</div></div>
    <div style="display:flex;justify-content:flex-end;margin-top:16px">
      <button type="button" class="btn btn-secondary" onclick="document.getElementById('alerte-modal').classList.remove('open')">Fermer</button>
    </div>
  </div>
</div>
<!-- MODALE detail FEB -->
<div class="ach-modal-bg" id="feb-detail-modal">
  <div class="ach-modal" role="dialog" style="max-width:480px">
    <h3 style="margin:0 0 14px;font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:var(--navy)">Détail FEB</h3>
    <div id="feb-detail-body"></div>
    <div style="display:flex;justify-content:flex-end;margin-top:16px">
      <button type="button" class="btn btn-secondary" onclick="document.getElementById('feb-detail-modal').classList.remove('open')">Fermer</button>
    </div>
  </div>
</div>

<script>
const esc = s => String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
function dashPost(data) {
  const fd = new FormData();
  fd.append('du', <?= json_encode($du) ?>);
  fd.append('au', <?= json_encode($au) ?>);
  <?php if ($f_departement): ?>fd.append('departement', <?= $f_departement ?>);<?php endif; ?>
  Object.entries(data).forEach(([k, v]) => fd.append(k, v));
  return fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd }).then(r => r.json());
}
function dashOuvrirAlerte(type, titre) {
  document.getElementById('alerte-modal-title').textContent = titre;
  document.getElementById('alerte-modal-body').innerHTML = '<div class="ach-empty">Chargement…</div>';
  document.getElementById('alerte-modal').classList.add('open');
  dashPost({ action: 'alerte_' + type }).then(res => {
    const rows = res.data || [];
    if (!rows.length) { document.getElementById('alerte-modal-body').innerHTML = '<div class="ach-empty">Aucune FEB concernée.</div>'; return; }
    let cols;
    if (type === 'validation') cols = r => `<td>${esc(r.numero)}</td><td>${esc(r.objet)}</td><td>${esc(r.demandeur_nom)}</td><td>${esc(r.departement_label)}</td><td>${Math.trunc(r.jours_ecoules)} j</td>`;
    else if (type === 'suivi') cols = r => `<td>${esc(r.numero)}</td><td>${esc(r.designation)}</td><td>${esc(r.demandeur_nom)}</td><td>${esc(r.departement_label)}</td><td>écart ${r.ecart}</td>`;
    else cols = r => `<td>${esc(r.numero)}</td><td>${esc(r.objet)}</td><td>${esc(r.demandeur_nom)}</td><td>${esc(r.departement_label)}</td><td>${esc(r.description)}</td>`;
    const head = type === 'validation' ? 'FEB;Objet;Demandeur;Département;Ancienneté'
               : type === 'suivi' ? 'FEB;Article;Demandeur;Département;Écart'
               : 'FEB;Objet;Demandeur;Département;Détail';
    const ths = head.split(';').map(h => `<th>${h}</th>`).join('');
    document.getElementById('alerte-modal-body').innerHTML = `<div style="overflow-x:auto"><table class="ach-table"><thead><tr>${ths}</tr></thead><tbody>` +
      rows.map(r => `<tr class="clic" onclick='dashDetailFeb(${JSON.stringify(r)})'>${cols(r)}</tr>`).join('') +
      '</tbody></table></div>';
  });
}
function dashDetailFeb(r) {
  const febId = r.id || r.feb_id;
  document.getElementById('feb-detail-body').innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px 16px;font-size:13px">
      <div><div style="font-size:11px;color:var(--muted);text-transform:uppercase">Numéro</div><div style="font-weight:700">${esc(r.numero)}</div></div>
      <div><div style="font-size:11px;color:var(--muted);text-transform:uppercase">Demandeur</div><div style="font-weight:700">${esc(r.demandeur_nom || '—')}</div></div>
      <div><div style="font-size:11px;color:var(--muted);text-transform:uppercase">Département</div><div style="font-weight:700">${esc(r.departement_label || '—')}</div></div>
      <div><div style="font-size:11px;color:var(--muted);text-transform:uppercase">Montant</div><div style="font-weight:700">${Number(r.montant_total || 0).toLocaleString('fr-FR')} XOF</div></div>
      <div style="grid-column:1/-1"><div style="font-size:11px;color:var(--muted);text-transform:uppercase">Objet</div><div>${esc(r.objet || r.designation || '—')}</div></div>
    </div>
    <div style="margin-top:14px"><a href="mes_feb.php" class="btn btn-secondary btn-sm">Voir dans Mes FEB</a>
    <a href="suivi_achats.php?q=${encodeURIComponent(r.numero)}" class="btn btn-secondary btn-sm">Voir dans Suivi Achats</a></div>
  `;
  document.getElementById('feb-detail-modal').classList.add('open');
}
document.querySelectorAll('.ach-modal-bg').forEach(m => m.addEventListener('click', e => { if (e.target === e.currentTarget) m.classList.remove('open'); }));
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.ach-modal-bg.open').forEach(m => m.classList.remove('open')); });
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
