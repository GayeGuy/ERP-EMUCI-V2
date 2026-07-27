<?php
// ============================================================
//  pages/demandes_roles.php — Configuration : département lié par rôle de validation
//  Permet de lier un département à un rôle di, afin que tous les membres
//  de ce département puissent valider l'étape correspondante.
//  Réservé aux admin / superadmin.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/demandes.php';

require_auth();
$user = current_user();
if (!in_array(($user['role_slug'] ?? ''), ['admin','superadmin'], true)) {
    http_response_code(403); include __DIR__ . '/../templates/403.php'; exit;
}
$_SESSION['groupe_actif'] = 'DEMANDES';
$page_title  = 'Circuits — paramètres';
$active_page = 'demandes_roles';

// ── AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $action      = $_POST['action'] ?? '';
    $valid_roles = array_column(db_fetch_all("SELECT code FROM di_roles"), 'code');

    if ($action === 'save_dept') {
        $role    = trim($_POST['role_code'] ?? '');
        $dept_id = (isset($_POST['departement_id']) && $_POST['departement_id'] !== '')
                   ? (int)$_POST['departement_id'] : null;
        if (!in_array($role, $valid_roles, true)) json_response(false, 'Rôle invalide.');
        if ($dept_id) {
            $exists = db_fetch_value("SELECT COUNT(*) FROM departements WHERE id=?", [$dept_id]);
            if (!$exists) json_response(false, 'Département introuvable.');
        }
        db_query("UPDATE di_roles SET departement_id=? WHERE code=?", [$dept_id, $role]);
        json_response(true, $dept_id ? 'Département lié.' : 'Lien retiré.');
    }

    json_response(false, 'Action inconnue.');
}

$roles = db_fetch_all(
    "SELECT dr.code, dr.label, dr.departement_id, d.label AS dept_label
     FROM di_roles dr LEFT JOIN departements d ON d.id=dr.departement_id
     ORDER BY dr.ordre ASC"
);
$depts = db_fetch_all("SELECT id, label FROM departements ORDER BY label ASC");

// Membres actuels du département lié par rôle (pour aperçu)
$members_by_dept = [];
if ($depts) {
    $dept_ids = array_column($depts, 'id');
    foreach ($dept_ids as $did) {
        $members = db_fetch_all(
            "SELECT CONCAT(u.prenom,' ',u.nom) AS nom, ud.is_n1
             FROM user_departements ud JOIN users u ON u.id=ud.user_id
             WHERE ud.departement_id=? AND u.actif=1 ORDER BY u.nom", [$did]
        );
        $members_by_dept[$did] = $members;
    }
}

include __DIR__ . '/../templates/header.php';
?>
<style>
  .dr-wrap{max-width:900px;margin:0 auto}
  .dr-hint{font-size:13px;color:var(--muted,#7f8c8d);margin:0 0 22px}
  .dr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:14px}
  .dr-card{background:var(--card,#fff);border:1.5px solid var(--border,#e2e8f0);border-radius:14px;padding:16px 18px}
  .dr-card-head{display:flex;align-items:center;gap:10px;margin-bottom:12px}
  .dr-badge{font-size:11px;font-weight:700;padding:2px 9px;border-radius:8px;background:#eef1fc;color:#3B4FBE;font-family:monospace}
  .dr-label{font-size:14px;font-weight:700}
  .dr-select{width:100%;padding:9px 12px;border:1.5px solid var(--border,#d5dde8);border-radius:9px;
    font-size:13px;font-family:inherit;background:var(--input,#f8fafc);margin-bottom:10px}
  .dr-members{font-size:12px;color:var(--muted,#7f8c8d);padding:8px 10px;background:var(--input,#f8fafc);border-radius:8px;min-height:28px}
  .dr-member{display:inline-flex;align-items:center;gap:4px;margin:2px 4px 2px 0;
    background:#eef1fc;color:#3B4FBE;padding:2px 8px;border-radius:8px;font-size:11px;font-weight:600}
  .dr-n1{font-size:10px;background:#e8f8f5;color:#16a085;padding:1px 5px;border-radius:5px;margin-left:3px}
  #dr-toast{position:fixed;top:20px;right:20px;z-index:9999;padding:11px 18px;border-radius:10px;font-size:13px;
    font-weight:600;color:#fff;background:#27ae60;box-shadow:0 6px 20px rgba(0,0,0,.15);opacity:0;transition:.25s;pointer-events:none}
  #dr-toast.show{opacity:1}
</style>

<div class="dr-wrap">
  <div style="margin-bottom:20px">
    <h2 style="margin:0 0 4px">Circuits — paramètres avancés</h2>
    <p class="dr-hint">Liez un département à un rôle de validation : tous les membres de ce département pourront alors viser l'étape correspondante, sans configuration individuelle. Idéal pour les étapes multi-responsables (ex : Visa Administration → département Administration).</p>
  </div>

  <div class="dr-grid">
    <?php foreach ($roles as $r):
      $linked_members = $r['departement_id'] ? ($members_by_dept[$r['departement_id']] ?? []) : [];
    ?>
    <div class="dr-card" id="card-<?= h($r['code']) ?>">
      <div class="dr-card-head">
        <span class="dr-badge"><?= h($r['code']) ?></span>
        <span class="dr-label"><?= h($r['label']) ?></span>
        <?php if ($r['departement_id']): ?>
          <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:8px;background:#e8f8f5;color:#16a085;margin-left:auto">dept lié</span>
        <?php endif; ?>
      </div>
      <select class="dr-select" onchange="drSaveDept('<?= h($r['code']) ?>', this.value, this)">
        <option value="">— Aucun département lié —</option>
        <?php foreach ($depts as $d): ?>
        <option value="<?= $d['id'] ?>" <?= (string)$r['departement_id'] === (string)$d['id'] ? 'selected' : '' ?>>
          <?= h($d['label']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <?php if ($linked_members): ?>
      <div class="dr-members">
        <?php foreach ($linked_members as $m): ?>
          <span class="dr-member"><?= h($m['nom']) ?><?= $m['is_n1'] ? '<span class="dr-n1">N+1</span>' : '' ?></span>
        <?php endforeach; ?>
      </div>
      <?php elseif ($r['departement_id']): ?>
      <div class="dr-members" style="color:#e67e22">Aucun membre dans ce département.</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div id="dr-toast"></div>
<script>
function drToast(msg, ok) {
  const t = document.getElementById('dr-toast');
  t.textContent = msg; t.style.background = ok ? '#27ae60' : '#e74c3c';
  t.classList.add('show'); setTimeout(() => t.classList.remove('show'), 1800);
}
function drSaveDept(roleCode, deptId, sel) {
  const fd = new FormData();
  fd.append('action', 'save_dept'); fd.append('role_code', roleCode); fd.append('departement_id', deptId);
  fetch(window.location.pathname, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd})
    .then(r => r.json())
    .then(d => { drToast(d.message, d.success); if (d.success) setTimeout(() => location.reload(), 900); })
    .catch(() => drToast('Erreur réseau', false));
}
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
