<?php
// ============================================================
//  pages/achats/param_budget.php — Lignes budgétaires par département
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/achats.php';

require_auth();
$user = current_user();
require_permission('achats_param', 'can_read');
$can_edit = can('achats_param', 'can_update');
$_SESSION['groupe_actif'] = 'ACHATS';
$page_title  = 'Lignes budgétaires';
$active_page = 'achats_param_budget';

$COMPORTEMENTS = ['aucun' => 'Aucun contrôle', 'alerte' => 'Alerte au dépassement', 'blocage' => 'Blocage au dépassement'];

// ── AJAX ────────────────────────────────────────────────────
if (is_ajax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'get_contributions') {
        $departement_id = (int)($_POST['departement_id'] ?? 0);
        $famille_id     = (int)($_POST['famille_id'] ?? 0);
        $exercice       = (int)($_POST['exercice'] ?? 0);
        $rows = db_fetch_all(
            "SELECT f.numero, f.objet, f.statut, fl.designation, fl.montant_ttc,
                    a.libelle AS article_libelle
             FROM feb_lignes fl
             JOIN feb f ON f.id = fl.feb_id
             LEFT JOIN articles a ON a.id = fl.article_id
             WHERE f.departement_id=? AND fl.famille_id=? AND f.exercice=? AND fl.arbitrage='achat'
               AND f.statut IN ('confirmee','en_validation')
             ORDER BY f.numero",
            [$departement_id, $famille_id, $exercice]
        );
        json_response(true, '', $rows);
    }

    if (!$can_edit) json_response(false, 'Action réservée.');

    if ($action === 'save') {
        $id             = (int)($_POST['id'] ?? 0);
        $departement_id = (int)($_POST['departement_id'] ?? 0);
        $famille_id     = (int)($_POST['famille_id'] ?? 0);
        $exercice       = (int)($_POST['exercice'] ?? 0);
        $envRaw         = trim($_POST['enveloppe'] ?? '');
        $env            = $envRaw === '' ? null : (int)$envRaw;
        $comport        = $_POST['comportement'] ?? 'aucun';

        if (!$departement_id || !$famille_id || !$exercice) json_response(false, 'Département, famille et exercice sont obligatoires.');
        if (!isset($COMPORTEMENTS[$comport])) json_response(false, 'Comportement invalide.');

        // Le sélecteur département se limite déjà aux départements actifs
        // côté écran (point 11) ; contrôle serveur redondant ici.
        $dept = db_fetch_one("SELECT * FROM departements WHERE id=? AND actif=1", [$departement_id]);
        if (!$dept) json_response(false, 'Département introuvable ou inactif.');
        $fam = db_fetch_one("SELECT * FROM familles_achat WHERE id=? AND actif=1", [$famille_id]);
        if (!$fam) json_response(false, 'Famille introuvable ou inactive.');

        // code_comptable/designation ne sont plus saisis : ils dérivent
        // toujours de la famille choisie (compte SYSCOHADA ← famille_achat),
        // jamais d'une valeur tapée indépendamment qui pourrait diverger.
        $code_comptable = $fam['compte_comptable'] ?: $fam['code'];
        $designation    = $dept['label'] . ' — ' . $fam['libelle'];

        try {
            if ($id) {
                db_query(
                    "UPDATE lignes_budgetaires SET departement_id=?, famille_id=?, exercice=?, code_comptable=?, designation=?, enveloppe=?, comportement=? WHERE id=?",
                    [$departement_id, $famille_id, $exercice, $code_comptable, $designation, $env, $comport, $id]
                );
                audit_log($user['id'], 'UPDATE', 'achats_param', $id, "Modification ligne budgétaire : {$dept['label']} / {$fam['libelle']} ($exercice)");
                json_response(true, 'Ligne mise à jour.');
            } else {
                db_query(
                    "INSERT INTO lignes_budgetaires (departement_id, famille_id, exercice, code_comptable, designation, enveloppe, comportement) VALUES (?,?,?,?,?,?,?)",
                    [$departement_id, $famille_id, $exercice, $code_comptable, $designation, $env, $comport]
                );
                $newId = (int)db_last_id('lignes_budgetaires_id_seq');
                audit_log($user['id'], 'CREATE', 'achats_param', $newId, "Création ligne budgétaire : {$dept['label']} / {$fam['libelle']} ($exercice)");
                json_response(true, 'Ligne créée.', ['id' => $newId]);
            }
        } catch (Exception $e) {
            json_response(false, 'Une ligne budgétaire existe déjà pour ce département, cette famille et cet exercice.');
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $nv = (int)($_POST['actif'] ?? 0) ? 1 : 0;
        db_query("UPDATE lignes_budgetaires SET actif=? WHERE id=?", [$nv, $id]);
        audit_log($user['id'], 'UPDATE', 'achats_param', $id, $nv ? 'Réactivation ligne budgétaire' : 'Désactivation ligne budgétaire');
        json_response(true, $nv ? 'Ligne réactivée.' : 'Ligne désactivée.');
    }

    json_response(false, 'Action inconnue.');
}

// ── PAGE PHP ─────────────────────────────────────────────────
$exercices = db_fetch_all("SELECT DISTINCT exercice FROM lignes_budgetaires ORDER BY exercice DESC");
$exerciceActuel = (int)(date('Y'));
$exercice = (int)($_GET['exercice'] ?? ($exercices[0]['exercice'] ?? $exerciceActuel));
$f_departement = (int)($_GET['departement'] ?? 0);

$where  = ['lb.exercice = ?'];
$params = [$exercice];
if ($f_departement) { $where[] = 'lb.departement_id = ?'; $params[] = $f_departement; }

$lignes = db_fetch_all(
    "SELECT lb.*, d.label AS departement_label, d.code AS departement_code,
            f.libelle AS famille_libelle, f.code AS famille_code, f.compte_comptable
     FROM lignes_budgetaires lb
     LEFT JOIN departements d   ON d.id = lb.departement_id
     LEFT JOIN familles_achat f ON f.id = lb.famille_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY d.label ASC, f.libelle ASC",
    $params
);

// Situation (engagé/réservé/disponible) par ligne — Bloc 2, ach_budget_situation.
foreach ($lignes as &$l) {
    if ($l['departement_id'] && $l['famille_id']) {
        $sit = ach_budget_situation((int)$l['departement_id'], (int)$l['famille_id'], $exercice);
        $l['engage'] = $sit['engage']; $l['reserve'] = $sit['reserve'];
        $l['total_engage'] = $sit['total_engage']; $l['disponible'] = $sit['disponible'];
    } else {
        $l['engage'] = $l['reserve'] = $l['total_engage'] = 0; $l['disponible'] = null;
    }
}
unset($l);

// Groupé par département, sous-total par famille à l'intérieur (Bloc 4, point 22).
$grouped = [];
foreach ($lignes as $l) {
    $key = (int)($l['departement_id'] ?: 0);
    if (!isset($grouped[$key])) {
        $grouped[$key] = ['label' => $l['departement_label'] ?: '— (sans département)', 'lignes' => [], 'total_enveloppe' => 0, 'total_engage' => 0];
    }
    $grouped[$key]['lignes'][] = $l;
    if ($l['enveloppe'] !== null) $grouped[$key]['total_enveloppe'] += (int)$l['enveloppe'];
    $grouped[$key]['total_engage'] += (int)$l['total_engage'];
}

$departements_actifs = db_fetch_all("SELECT id, code, label FROM departements WHERE actif=1 ORDER BY label ASC");
$familles = db_fetch_all("SELECT id, code, libelle, compte_comptable FROM familles_achat WHERE actif=1 ORDER BY libelle ASC");

include __DIR__ . '/../../templates/header.php';
?>
<style>
.ach-toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:18px}
.ach-toolbar select{padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-family:inherit;background:white}
.dept-card{background:white;border:1px solid var(--border);border-radius:16px;margin-bottom:18px;overflow:hidden}
.dept-hdr{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:14px 18px;background:#f8fafc;border-bottom:1px solid var(--border)}
.dept-nom{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;color:var(--navy);font-size:14px}
.dept-meta{font-size:12px;color:var(--muted)}
.ach-table{width:100%;border-collapse:collapse;font-size:13px}
.ach-table th{background:#f8fafc;color:var(--muted);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:10px 16px;text-align:left;border-bottom:1px solid var(--border)}
.ach-table td{padding:10px 16px;border-bottom:1px solid var(--border);vertical-align:middle}
.ach-table tr:last-child td{border-bottom:none}
.ach-table tr.drill-row{cursor:pointer}
.ach-table tr.drill-row:hover{background:#f8fafc}
.ach-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.ach-badge.on{background:#D1FAE5;color:#065F46}
.ach-badge.off{background:#F1F5F9;color:#475569}
.ach-badge.alerte{background:#FEF3C7;color:#92400E}
.ach-badge.blocage{background:#FEE2E2;color:#991B1B}
.ach-badge.aucun{background:#F1F5F9;color:#475569}
.budget-bar{width:100px;height:6px;background:var(--border);border-radius:3px;overflow:hidden}
.budget-fill{height:100%;border-radius:3px}
.ach-empty{padding:40px 20px;text-align:center;color:var(--muted);font-size:13px}
.ach-modal-bg{display:none;position:fixed;inset:0;background:rgba(6,3,58,.45);z-index:2000;align-items:center;justify-content:center;padding:20px}
.ach-modal-bg.open{display:flex}
.ach-modal{background:white;border-radius:16px;padding:26px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.ach-modal h3{margin:0 0 16px;font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:var(--navy)}
.ach-fg{margin-bottom:14px}
.ach-fg label{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px}
.ach-fg input,.ach-fg select{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box;background:white}
.ach-row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.ach-modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px}
.ach-err{color:#dc2626;font-size:12px;margin-top:4px;display:none}
.ach-hint{font-size:11.5px;color:var(--muted);margin-top:4px}
@media (max-width:768px) {
  .ach-toolbar select, .ach-fg input, .ach-fg select { min-height:44px; }
  .ach-row2 { grid-template-columns: minmax(0,1fr); }
}
</style>

<div class="ach-toolbar">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <label for="ach-exercice" style="font-size:13px;font-weight:600;color:#374151">Exercice</label>
    <select id="ach-exercice" name="exercice" onchange="this.form.submit()">
      <?php
      $exList = array_unique(array_merge(array_column($exercices, 'exercice'), [$exerciceActuel]));
      rsort($exList);
      foreach ($exList as $ex): ?>
        <option value="<?= $ex ?>" <?= $ex == $exercice ? 'selected' : '' ?>><?= $ex ?></option>
      <?php endforeach; ?>
    </select>
    <label for="ach-departement" style="font-size:13px;font-weight:600;color:#374151">Département</label>
    <select id="ach-departement" name="departement" onchange="this.form.submit()">
      <option value="0">Tous</option>
      <?php foreach ($departements_actifs as $d): ?>
        <option value="<?= $d['id'] ?>" <?= $f_departement === (int)$d['id'] ? 'selected' : '' ?>><?= h($d['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php if ($can_edit): ?>
  <button type="button" class="btn btn-primary" onclick="achOpenCreate()">
    <i class="ph ph-plus" aria-hidden="true"></i> Nouvelle ligne
  </button>
  <?php endif; ?>
</div>

<?php if (empty($grouped)): ?>
  <div class="dept-card"><div class="ach-empty">Aucune ligne budgétaire pour ces filtres.</div></div>
<?php else: foreach ($grouped as $dept_id => $g):
  $pct = $g['total_enveloppe'] > 0 ? min(100, round($g['total_engage'] / $g['total_enveloppe'] * 100)) : 0;
?>
<div class="dept-card">
  <div class="dept-hdr">
    <span class="dept-nom"><?= h($g['label']) ?></span>
    <span class="dept-meta">
      <?= fmt_number((float)$g['total_engage']) ?> XOF engagés
      <?= $g['total_enveloppe'] > 0 ? ' / ' . fmt_number((float)$g['total_enveloppe']) . ' XOF (' . $pct . '%)' : '' ?>
    </span>
  </div>
  <div style="overflow-x:auto">
  <table class="ach-table">
    <thead><tr>
      <th>Famille</th><th>Compte</th><th>Enveloppe</th><th>Engagé + réservé</th><th>Disponible</th><th>Comportement</th><th>Statut</th>
      <?php if ($can_edit): ?><th>Actions</th><?php endif; ?>
    </tr></thead>
    <tbody>
      <?php foreach ($g['lignes'] as $l):
        $famille_pct = $l['enveloppe'] !== null && $l['enveloppe'] > 0 ? min(100, round($l['total_engage'] / $l['enveloppe'] * 100)) : null;
        $fill_color = $famille_pct === null ? '#94a3b8' : ($famille_pct >= 100 ? '#dc2626' : ($famille_pct >= 80 ? '#d97706' : '#16a34a'));
      ?>
      <tr class="drill-row" onclick="pbDrillDown(<?= (int)$l['departement_id'] ?>, <?= (int)$l['famille_id'] ?>, <?= $exercice ?>, <?= json_encode($l['famille_libelle']) ?>)">
        <td style="font-weight:700;color:var(--navy)"><?= h($l['famille_libelle'] ?? '—') ?></td>
        <td style="font-family:monospace"><?= h($l['compte_comptable'] ?: '—') ?></td>
        <td><?= $l['enveloppe'] !== null ? h(number_format((float)$l['enveloppe'], 0, ',', ' ')) : '<span style="color:var(--muted)">Non plafonnée</span>' ?></td>
        <td>
          <?= fmt_number((float)$l['total_engage']) ?> XOF
          <?php if ($famille_pct !== null): ?>
          <div class="budget-bar" style="margin-top:4px"><div class="budget-fill" style="width:<?= $famille_pct ?>%;background:<?= $fill_color ?>"></div></div>
          <?php endif; ?>
        </td>
        <td style="font-weight:700;color:<?= $l['disponible'] !== null && $l['disponible'] < 0 ? '#991B1B' : 'inherit' ?>">
          <?= $l['disponible'] !== null ? fmt_number((float)$l['disponible']) . ' XOF' : '—' ?>
        </td>
        <td><span class="ach-badge <?= h($l['comportement']) ?>"><?= h($COMPORTEMENTS[$l['comportement']] ?? $l['comportement']) ?></span></td>
        <td><span class="ach-badge <?= $l['actif'] ? 'on' : 'off' ?>"><?= $l['actif'] ? 'Active' : 'Inactive' ?></span></td>
        <?php if ($can_edit): ?>
        <td onclick="event.stopPropagation()" style="display:flex;gap:8px">
          <button type="button" class="btn btn-secondary btn-sm" aria-label="Modifier <?= h($l['famille_libelle'] ?? '') ?>"
                  onclick='achOpenEdit(<?= json_encode($l, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
            <i class="ph ph-pencil-simple" aria-hidden="true"></i>
          </button>
          <button type="button" class="btn btn-secondary btn-sm" aria-label="<?= $l['actif'] ? 'Désactiver' : 'Réactiver' ?>"
                  onclick="achToggle(<?= $l['id'] ?>, <?= $l['actif'] ? 0 : 1 ?>)">
            <i class="ph <?= $l['actif'] ? 'ph-prohibit' : 'ph-check-circle' ?>" aria-hidden="true"></i>
          </button>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endforeach; endif; ?>

<!-- MODALE création/édition -->
<div class="ach-modal-bg" id="ach-modal">
  <div class="ach-modal" role="dialog" aria-labelledby="ach-modal-title">
    <h3 id="ach-modal-title"><span id="ach-modal-ttl-txt">Nouvelle ligne budgétaire</span></h3>
    <input type="hidden" id="f-id" value="">
    <div class="ach-row2">
      <div class="ach-fg">
        <label for="f-departement">Département</label>
        <select id="f-departement" required>
          <option value="">— Sélectionner —</option>
          <?php foreach ($departements_actifs as $d): ?><option value="<?= $d['id'] ?>"><?= h($d['label']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="ach-fg">
        <label for="f-exercice">Exercice</label>
        <input type="number" id="f-exercice" required value="<?= $exercice ?>">
      </div>
    </div>
    <div class="ach-fg">
      <label for="f-famille">Famille</label>
      <select id="f-famille" required>
        <option value="">— Sélectionner —</option>
        <?php foreach ($familles as $f): ?>
          <option value="<?= $f['id'] ?>"><?= h($f['libelle']) ?> (<?= h($f['compte_comptable'] ?: $f['code']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <div class="ach-hint">Le compte comptable et la désignation sont dérivés automatiquement de la famille choisie.</div>
    </div>
    <div class="ach-fg">
      <label for="f-enveloppe">Enveloppe (XOF)</label>
      <input type="number" id="f-enveloppe" min="0" step="1" placeholder="vide = non plafonnée">
      <div class="ach-hint">Laisser vide pour une ligne non plafonnée.</div>
    </div>
    <div class="ach-fg">
      <label for="f-comportement">Comportement au dépassement</label>
      <select id="f-comportement">
        <?php foreach ($COMPORTEMENTS as $k => $v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="ach-err" id="ach-err"></div>
    <div class="ach-modal-actions">
      <button type="button" class="btn btn-secondary" onclick="achCloseModal()">Annuler</button>
      <button type="button" class="btn btn-primary" onclick="achSave()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- MODALE drill-down -->
<div class="ach-modal-bg" id="drill-modal">
  <div class="ach-modal" role="dialog" aria-labelledby="drill-modal-title" style="max-width:640px">
    <h3 id="drill-modal-title">FEB contribuant à <span id="drill-famille"></span></h3>
    <div style="overflow-x:auto">
    <table class="ach-table">
      <thead><tr><th>FEB</th><th>Objet</th><th>Article / désignation</th><th>Montant</th><th>Statut</th></tr></thead>
      <tbody id="drill-tbody"></tbody>
    </table>
    </div>
    <div class="ach-modal-actions">
      <button type="button" class="btn btn-secondary" onclick="document.getElementById('drill-modal').classList.remove('open')">Fermer</button>
    </div>
  </div>
</div>

<script>
function achOpenCreate() {
  document.getElementById('f-id').value = '';
  document.getElementById('ach-modal-ttl-txt').textContent = 'Nouvelle ligne budgétaire';
  document.getElementById('f-departement').value = '';
  document.getElementById('f-exercice').value = '<?= $exercice ?>';
  document.getElementById('f-famille').value = '';
  document.getElementById('f-enveloppe').value = '';
  document.getElementById('f-comportement').value = 'aucun';
  document.getElementById('ach-err').style.display = 'none';
  document.getElementById('ach-modal').classList.add('open');
}
function achOpenEdit(l) {
  document.getElementById('f-id').value = l.id;
  document.getElementById('ach-modal-ttl-txt').textContent = 'Modifier la ligne budgétaire';
  document.getElementById('f-departement').value = l.departement_id || '';
  document.getElementById('f-exercice').value = l.exercice;
  document.getElementById('f-famille').value = l.famille_id || '';
  document.getElementById('f-enveloppe').value = l.enveloppe ?? '';
  document.getElementById('f-comportement').value = l.comportement;
  document.getElementById('ach-err').style.display = 'none';
  document.getElementById('ach-modal').classList.add('open');
}
function achCloseModal() { document.getElementById('ach-modal').classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') { achCloseModal(); document.getElementById('drill-modal').classList.remove('open'); } });
document.getElementById('ach-modal').addEventListener('click', e => { if (e.target === e.currentTarget) achCloseModal(); });
document.getElementById('drill-modal').addEventListener('click', e => { if (e.target === e.currentTarget) e.currentTarget.classList.remove('open'); });

function achPost(data) {
  const fd = new FormData();
  Object.entries(data).forEach(([k, v]) => fd.append(k, v));
  return fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd }).then(r => r.json());
}

function achSave() {
  const err = document.getElementById('ach-err');
  const departement_id = document.getElementById('f-departement').value;
  const famille_id = document.getElementById('f-famille').value;
  const exercice = document.getElementById('f-exercice').value;
  if (!departement_id || !famille_id || !exercice) { err.textContent = 'Département, famille et exercice sont obligatoires.'; err.style.display = 'block'; return; }
  achPost({
    action: 'save',
    id: document.getElementById('f-id').value,
    departement_id, famille_id, exercice,
    enveloppe: document.getElementById('f-enveloppe').value,
    comportement: document.getElementById('f-comportement').value,
  }).then(res => {
    if (!res.success) { err.textContent = res.message; err.style.display = 'block'; return; }
    toast(res.message, 'success');
    achCloseModal();
    setTimeout(() => location.reload(), 500);
  });
}

function achToggle(id, actif) {
  if (!confirm(actif ? 'Réactiver cette ligne ?' : 'Désactiver cette ligne ?')) return;
  achPost({ action: 'toggle', id, actif }).then(res => {
    toast(res.message, res.success ? 'success' : 'danger');
    if (res.success) setTimeout(() => location.reload(), 500);
  });
}

const esc = s => String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
function pbDrillDown(departement_id, famille_id, exercice, familleLibelle) {
  document.getElementById('drill-famille').textContent = familleLibelle;
  document.getElementById('drill-tbody').innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:18px">Chargement…</td></tr>';
  document.getElementById('drill-modal').classList.add('open');
  achPost({ action: 'get_contributions', departement_id, famille_id, exercice }).then(res => {
    if (!res.success) { document.getElementById('drill-tbody').innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--muted)">${esc(res.message)}</td></tr>`; return; }
    const rows = res.data || [];
    document.getElementById('drill-tbody').innerHTML = rows.map(r => `
      <tr>
        <td style="font-weight:700;color:var(--navy)">${esc(r.numero)}</td>
        <td>${esc(r.objet)}</td>
        <td>${esc(r.article_libelle || r.designation)}</td>
        <td>${Number(r.montant_ttc).toLocaleString('fr-FR')} XOF</td>
        <td>${esc(r.statut)}</td>
      </tr>`).join('') || '<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:18px">Aucune FEB ne contribue à cette enveloppe pour l\'instant.</td></tr>';
  });
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
