<?php
// ============================================================
//  pages/achats/param_budget.php — Lignes budgétaires
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
    if (!$can_edit) json_response(false, 'Action réservée.');
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $code     = trim($_POST['code_comptable'] ?? '');
        $desig    = trim($_POST['designation'] ?? '');
        $exercice = (int)($_POST['exercice'] ?? 0);
        $envRaw   = trim($_POST['enveloppe'] ?? '');
        $env      = $envRaw === '' ? null : (int)$envRaw;
        $comport  = $_POST['comportement'] ?? 'aucun';
        $familleId = (int)($_POST['famille_id'] ?? 0) ?: null;

        if (!$code || !$desig || !$exercice) json_response(false, 'Code, désignation et exercice sont obligatoires.');
        if (!isset($COMPORTEMENTS[$comport])) json_response(false, 'Comportement invalide.');

        try {
            if ($id) {
                db_query(
                    "UPDATE lignes_budgetaires SET code_comptable=?, designation=?, exercice=?, enveloppe=?, comportement=?, famille_id=? WHERE id=?",
                    [$code, $desig, $exercice, $env, $comport, $familleId, $id]
                );
                audit_log($user['id'], 'UPDATE', 'achats_param', $id, "Modification ligne budgétaire : $code ($exercice)");
                json_response(true, 'Ligne mise à jour.');
            } else {
                db_query(
                    "INSERT INTO lignes_budgetaires (code_comptable, designation, exercice, enveloppe, comportement, famille_id) VALUES (?,?,?,?,?,?)",
                    [$code, $desig, $exercice, $env, $comport, $familleId]
                );
                $newId = (int)db_last_id('lignes_budgetaires_id_seq');
                audit_log($user['id'], 'CREATE', 'achats_param', $newId, "Création ligne budgétaire : $code ($exercice)");
                json_response(true, 'Ligne créée.', ['id' => $newId]);
            }
        } catch (Exception $e) {
            json_response(false, 'Ce code comptable existe déjà pour cet exercice.');
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

$lignes   = db_fetch_all(
    "SELECT lb.*, f.libelle AS famille_libelle
     FROM lignes_budgetaires lb
     LEFT JOIN familles_achat f ON f.id = lb.famille_id
     WHERE lb.exercice = ?
     ORDER BY lb.code_comptable ASC",
    [$exercice]
);
$familles = db_fetch_all("SELECT id, libelle FROM familles_achat WHERE actif = 1 ORDER BY libelle ASC");

include __DIR__ . '/../../templates/header.php';
?>
<style>
.ach-toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:18px}
.ach-toolbar select{padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-family:inherit;background:white}
.ach-table-wrap{background:white;border:1px solid var(--border);border-radius:16px;overflow:hidden}
.ach-table{width:100%;border-collapse:collapse;font-size:13px}
.ach-table th{background:#f8fafc;color:var(--muted);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:11px 16px;text-align:left;border-bottom:1px solid var(--border)}
.ach-table td{padding:11px 16px;border-bottom:1px solid var(--border);vertical-align:middle}
.ach-table tr:last-child td{border-bottom:none}
.ach-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.ach-badge.on{background:#D1FAE5;color:#065F46}
.ach-badge.off{background:#F1F5F9;color:#475569}
.ach-badge.alerte{background:#FEF3C7;color:#92400E}
.ach-badge.blocage{background:#FEE2E2;color:#991B1B}
.ach-badge.aucun{background:#F1F5F9;color:#475569}
.ach-empty{padding:40px 20px;text-align:center;color:var(--muted);font-size:13px}
.ach-modal-bg{display:none;position:fixed;inset:0;background:rgba(6,3,58,.45);z-index:2000;align-items:center;justify-content:center;padding:20px}
.ach-modal-bg.open{display:flex}
.ach-modal{background:white;border-radius:16px;padding:26px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2)}
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
  <form method="GET" style="display:flex;gap:8px;align-items:center">
    <label for="ach-exercice" style="font-size:13px;font-weight:600;color:#374151">Exercice</label>
    <select id="ach-exercice" name="exercice" onchange="this.form.submit()">
      <?php
      $exList = array_unique(array_merge(array_column($exercices, 'exercice'), [$exerciceActuel]));
      rsort($exList);
      foreach ($exList as $ex): ?>
        <option value="<?= $ex ?>" <?= $ex == $exercice ? 'selected' : '' ?>><?= $ex ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php if ($can_edit): ?>
  <button type="button" class="btn btn-primary" onclick="achOpenCreate()">
    <i class="ph ph-plus" aria-hidden="true"></i> Nouvelle ligne
  </button>
  <?php endif; ?>
</div>

<div class="ach-table-wrap">
  <?php if (empty($lignes)): ?>
    <div class="ach-empty">Aucune ligne budgétaire pour l'exercice <?= h((string)$exercice) ?>.</div>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="ach-table">
    <thead><tr>
      <th>Code comptable</th><th>Désignation</th><th>Famille</th><th>Enveloppe (XOF)</th><th>Comportement</th><th>Statut</th>
      <?php if ($can_edit): ?><th>Actions</th><?php endif; ?>
    </tr></thead>
    <tbody>
      <?php foreach ($lignes as $l): ?>
      <tr>
        <td style="font-family:monospace;font-weight:700"><?= h($l['code_comptable']) ?></td>
        <td><?= h($l['designation']) ?></td>
        <td><?= h($l['famille_libelle'] ?? '—') ?></td>
        <td><?= $l['enveloppe'] !== null ? h(number_format((float)$l['enveloppe'], 0, ',', ' ')) : '<span style="color:var(--muted)">Non plafonnée</span>' ?></td>
        <td><span class="ach-badge <?= h($l['comportement']) ?>"><?= h($COMPORTEMENTS[$l['comportement']] ?? $l['comportement']) ?></span></td>
        <td><span class="ach-badge <?= $l['actif'] ? 'on' : 'off' ?>"><?= $l['actif'] ? 'Active' : 'Inactive' ?></span></td>
        <?php if ($can_edit): ?>
        <td style="display:flex;gap:8px">
          <button type="button" class="btn btn-secondary btn-sm" aria-label="Modifier <?= h($l['code_comptable']) ?>"
                  onclick='achOpenEdit(<?= json_encode($l, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
            <i class="ph ph-pencil-simple" aria-hidden="true"></i>
          </button>
          <button type="button" class="btn btn-secondary btn-sm" aria-label="<?= $l['actif'] ? 'Désactiver' : 'Réactiver' ?> <?= h($l['code_comptable']) ?>"
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
  <?php endif; ?>
</div>

<!-- MODALE création/édition -->
<div class="ach-modal-bg" id="ach-modal">
  <div class="ach-modal" role="dialog" aria-labelledby="ach-modal-title">
    <h3 id="ach-modal-title"><span id="ach-modal-ttl-txt">Nouvelle ligne budgétaire</span></h3>
    <input type="hidden" id="f-id" value="">
    <div class="ach-row2">
      <div class="ach-fg">
        <label for="f-code">Code comptable</label>
        <input type="text" id="f-code" required style="font-family:monospace">
      </div>
      <div class="ach-fg">
        <label for="f-exercice">Exercice</label>
        <input type="number" id="f-exercice" required value="<?= $exercice ?>">
      </div>
    </div>
    <div class="ach-fg">
      <label for="f-designation">Désignation</label>
      <input type="text" id="f-designation" required>
    </div>
    <div class="ach-fg">
      <label for="f-famille">Famille</label>
      <select id="f-famille">
        <option value="">— Aucune —</option>
        <?php foreach ($familles as $f): ?><option value="<?= $f['id'] ?>"><?= h($f['libelle']) ?></option><?php endforeach; ?>
      </select>
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

<script>
function achOpenCreate() {
  document.getElementById('f-id').value = '';
  document.getElementById('ach-modal-ttl-txt').textContent = 'Nouvelle ligne budgétaire';
  document.getElementById('f-code').value = '';
  document.getElementById('f-exercice').value = '<?= $exercice ?>';
  document.getElementById('f-designation').value = '';
  document.getElementById('f-famille').value = '';
  document.getElementById('f-enveloppe').value = '';
  document.getElementById('f-comportement').value = 'aucun';
  document.getElementById('ach-err').style.display = 'none';
  document.getElementById('ach-modal').classList.add('open');
  setTimeout(() => document.getElementById('f-code').focus(), 80);
}
function achOpenEdit(l) {
  document.getElementById('f-id').value = l.id;
  document.getElementById('ach-modal-ttl-txt').textContent = 'Modifier la ligne budgétaire';
  document.getElementById('f-code').value = l.code_comptable;
  document.getElementById('f-exercice').value = l.exercice;
  document.getElementById('f-designation').value = l.designation;
  document.getElementById('f-famille').value = l.famille_id || '';
  document.getElementById('f-enveloppe').value = l.enveloppe ?? '';
  document.getElementById('f-comportement').value = l.comportement;
  document.getElementById('ach-err').style.display = 'none';
  document.getElementById('ach-modal').classList.add('open');
}
function achCloseModal() { document.getElementById('ach-modal').classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') achCloseModal(); });
document.getElementById('ach-modal').addEventListener('click', e => { if (e.target === e.currentTarget) achCloseModal(); });

function achPost(data) {
  const fd = new FormData();
  Object.entries(data).forEach(([k, v]) => fd.append(k, v));
  return fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd }).then(r => r.json());
}

function achSave() {
  const err = document.getElementById('ach-err');
  const code = document.getElementById('f-code').value.trim();
  const exercice = document.getElementById('f-exercice').value;
  const designation = document.getElementById('f-designation').value.trim();
  if (!code || !exercice || !designation) { err.textContent = 'Code, exercice et désignation sont obligatoires.'; err.style.display = 'block'; return; }
  achPost({
    action: 'save',
    id: document.getElementById('f-id').value,
    code_comptable: code,
    exercice: exercice,
    designation: designation,
    famille_id: document.getElementById('f-famille').value,
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
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
