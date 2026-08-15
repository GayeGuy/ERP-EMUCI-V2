<?php
// ============================================================
//  pages/achats/param_familles.php — Familles d'achat et types
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
$page_title  = 'Familles & types';
$active_page = 'achats_param_familles';

// ── AJAX ────────────────────────────────────────────────────
if (is_ajax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!$can_edit) json_response(false, 'Action réservée.');
    $action = $_POST['action'] ?? '';

    if ($action === 'create_famille') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $lib  = trim($_POST['libelle'] ?? '');
        if (!$code || !$lib) json_response(false, 'Code et libellé obligatoires.');
        try {
            db_query("INSERT INTO familles_achat (code, libelle) VALUES (?,?)", [$code, $lib]);
        } catch (Exception $e) { json_response(false, 'Ce code existe déjà.'); }
        $id = (int)db_last_id('familles_achat_id_seq');
        audit_log($user['id'], 'CREATE', 'achats_param', $id, "Création famille d'achat : $code — $lib");
        json_response(true, 'Famille créée.', ['id' => $id]);
    }

    if ($action === 'update_famille') {
        $id  = (int)($_POST['id'] ?? 0);
        $lib = trim($_POST['libelle'] ?? '');
        if (!$id || !$lib) json_response(false, 'Libellé obligatoire.');
        db_query("UPDATE familles_achat SET libelle=? WHERE id=?", [$lib, $id]);
        audit_log($user['id'], 'UPDATE', 'achats_param', $id, "Modification famille d'achat : $lib");
        json_response(true, 'Famille mise à jour.');
    }

    if ($action === 'toggle_famille') {
        $id = (int)($_POST['id'] ?? 0);
        $nv = (int)($_POST['actif'] ?? 0) ? 1 : 0;
        db_query("UPDATE familles_achat SET actif=? WHERE id=?", [$nv, $id]);
        audit_log($user['id'], 'UPDATE', 'achats_param', $id, $nv ? 'Réactivation famille' : 'Désactivation famille');
        json_response(true, $nv ? 'Famille réactivée.' : 'Famille désactivée.');
    }

    if ($action === 'create_type') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $lib  = trim($_POST['libelle'] ?? '');
        if (!$code || !$lib) json_response(false, 'Code et libellé obligatoires.');
        try {
            db_query("INSERT INTO achat_types (code, libelle) VALUES (?,?)", [$code, $lib]);
        } catch (Exception $e) { json_response(false, 'Ce code existe déjà.'); }
        $id = (int)db_last_id('achat_types_id_seq');
        audit_log($user['id'], 'CREATE', 'achats_param', $id, "Création type d'achat : $code — $lib");
        json_response(true, 'Type créé.', ['id' => $id]);
    }

    if ($action === 'update_type') {
        $id  = (int)($_POST['id'] ?? 0);
        $lib = trim($_POST['libelle'] ?? '');
        if (!$id || !$lib) json_response(false, 'Libellé obligatoire.');
        db_query("UPDATE achat_types SET libelle=? WHERE id=?", [$lib, $id]);
        audit_log($user['id'], 'UPDATE', 'achats_param', $id, "Modification type d'achat : $lib");
        json_response(true, 'Type mis à jour.');
    }

    if ($action === 'toggle_type') {
        $id = (int)($_POST['id'] ?? 0);
        $nv = (int)($_POST['actif'] ?? 0) ? 1 : 0;
        db_query("UPDATE achat_types SET actif=? WHERE id=?", [$nv, $id]);
        audit_log($user['id'], 'UPDATE', 'achats_param', $id, $nv ? 'Réactivation type' : 'Désactivation type');
        json_response(true, $nv ? 'Type réactivé.' : 'Type désactivé.');
    }

    json_response(false, 'Action inconnue.');
}

// ── PAGE PHP ─────────────────────────────────────────────────
$familles = db_fetch_all("SELECT * FROM familles_achat ORDER BY libelle ASC");
$types    = db_fetch_all("SELECT * FROM achat_types ORDER BY code ASC");

include __DIR__ . '/../../templates/header.php';
?>
<style>
.ach-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
@media (max-width:900px) { .ach-grid { grid-template-columns: minmax(0,1fr); } }
.ach-table-wrap{background:white;border:1px solid var(--border);border-radius:16px;overflow:hidden}
.ach-table-hdr{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border)}
.ach-table-ttl{font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;color:var(--navy)}
.ach-table{width:100%;border-collapse:collapse;font-size:13px}
.ach-table th{background:#f8fafc;color:var(--muted);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:10px 16px;text-align:left;border-bottom:1px solid var(--border)}
.ach-table td{padding:10px 16px;border-bottom:1px solid var(--border);vertical-align:middle}
.ach-table tr:last-child td{border-bottom:none}
.ach-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.ach-badge.on{background:#D1FAE5;color:#065F46}
.ach-badge.off{background:#F1F5F9;color:#475569}
.ach-empty{padding:30px 20px;text-align:center;color:var(--muted);font-size:13px}
.ach-modal-bg{display:none;position:fixed;inset:0;background:rgba(6,3,58,.45);z-index:2000;align-items:center;justify-content:center;padding:20px}
.ach-modal-bg.open{display:flex}
.ach-modal{background:white;border-radius:16px;padding:26px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.ach-modal h3{margin:0 0 16px;font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:var(--navy)}
.ach-fg{margin-bottom:14px}
.ach-fg label{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px}
.ach-fg input{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box}
.ach-modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px}
.ach-err{color:#dc2626;font-size:12px;margin-top:4px;display:none}
@media (max-width:768px) { .ach-fg input { min-height:44px; } }
</style>

<div class="ach-grid">
  <!-- FAMILLES -->
  <div class="ach-table-wrap">
    <div class="ach-table-hdr">
      <span class="ach-table-ttl">Familles d'achat (<?= count($familles) ?>)</span>
      <?php if ($can_edit): ?>
      <button type="button" class="btn btn-primary btn-sm" onclick="achOpenCreate('famille')">
        <i class="ph ph-plus" aria-hidden="true"></i> Nouvelle
      </button>
      <?php endif; ?>
    </div>
    <?php if (empty($familles)): ?>
      <div class="ach-empty">Aucune famille.</div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table class="ach-table">
      <thead><tr><th>Code</th><th>Libellé</th><th>Statut</th><?php if ($can_edit): ?><th>Actions</th><?php endif; ?></tr></thead>
      <tbody>
        <?php foreach ($familles as $f): ?>
        <tr>
          <td style="font-family:monospace;font-weight:700"><?= h($f['code']) ?></td>
          <td><?= h($f['libelle']) ?></td>
          <td><span class="ach-badge <?= $f['actif'] ? 'on' : 'off' ?>"><?= $f['actif'] ? 'Active' : 'Inactive' ?></span></td>
          <?php if ($can_edit): ?>
          <td style="display:flex;gap:8px">
            <button type="button" class="btn btn-secondary btn-sm" aria-label="Modifier <?= h($f['libelle']) ?>"
                    onclick='achOpenEdit("famille", <?= json_encode($f, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
              <i class="ph ph-pencil-simple" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-secondary btn-sm" aria-label="<?= $f['actif'] ? 'Désactiver' : 'Réactiver' ?> <?= h($f['libelle']) ?>"
                    onclick="achToggle('famille', <?= $f['id'] ?>, <?= $f['actif'] ? 0 : 1 ?>)">
              <i class="ph <?= $f['actif'] ? 'ph-prohibit' : 'ph-check-circle' ?>" aria-hidden="true"></i>
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

  <!-- TYPES -->
  <div class="ach-table-wrap">
    <div class="ach-table-hdr">
      <span class="ach-table-ttl">Types d'achat (<?= count($types) ?>)</span>
      <?php if ($can_edit): ?>
      <button type="button" class="btn btn-primary btn-sm" onclick="achOpenCreate('type')">
        <i class="ph ph-plus" aria-hidden="true"></i> Nouveau
      </button>
      <?php endif; ?>
    </div>
    <?php if (empty($types)): ?>
      <div class="ach-empty">Aucun type.</div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table class="ach-table">
      <thead><tr><th>Code</th><th>Libellé</th><th>Statut</th><?php if ($can_edit): ?><th>Actions</th><?php endif; ?></tr></thead>
      <tbody>
        <?php foreach ($types as $t): ?>
        <tr>
          <td style="font-family:monospace;font-weight:700"><?= h($t['code']) ?></td>
          <td><?= h($t['libelle']) ?></td>
          <td><span class="ach-badge <?= $t['actif'] ? 'on' : 'off' ?>"><?= $t['actif'] ? 'Actif' : 'Inactif' ?></span></td>
          <?php if ($can_edit): ?>
          <td style="display:flex;gap:8px">
            <button type="button" class="btn btn-secondary btn-sm" aria-label="Modifier <?= h($t['libelle']) ?>"
                    onclick='achOpenEdit("type", <?= json_encode($t, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
              <i class="ph ph-pencil-simple" aria-hidden="true"></i>
            </button>
            <button type="button" class="btn btn-secondary btn-sm" aria-label="<?= $t['actif'] ? 'Désactiver' : 'Réactiver' ?> <?= h($t['libelle']) ?>"
                    onclick="achToggle('type', <?= $t['id'] ?>, <?= $t['actif'] ? 0 : 1 ?>)">
              <i class="ph <?= $t['actif'] ? 'ph-prohibit' : 'ph-check-circle' ?>" aria-hidden="true"></i>
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
</div>

<!-- MODALE création/édition (famille ou type, meme forme) -->
<div class="ach-modal-bg" id="ach-modal">
  <div class="ach-modal" role="dialog" aria-labelledby="ach-modal-title">
    <h3 id="ach-modal-title"><span id="ach-modal-ttl-txt">Nouvelle famille</span></h3>
    <input type="hidden" id="f-kind" value="famille">
    <input type="hidden" id="f-id" value="">
    <div class="ach-fg">
      <label for="f-code">Code</label>
      <input type="text" id="f-code" required style="font-family:monospace;text-transform:uppercase">
    </div>
    <div class="ach-fg">
      <label for="f-libelle">Libellé</label>
      <input type="text" id="f-libelle" required>
    </div>
    <div class="ach-err" id="ach-err"></div>
    <div class="ach-modal-actions">
      <button type="button" class="btn btn-secondary" onclick="achCloseModal()">Annuler</button>
      <button type="button" class="btn btn-primary" onclick="achSave()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
function achOpenCreate(kind) {
  document.getElementById('f-kind').value = kind;
  document.getElementById('f-id').value = '';
  document.getElementById('ach-modal-ttl-txt').textContent = kind === 'famille' ? 'Nouvelle famille' : 'Nouveau type';
  document.getElementById('f-code').value = '';
  document.getElementById('f-code').disabled = false;
  document.getElementById('f-libelle').value = '';
  document.getElementById('ach-err').style.display = 'none';
  document.getElementById('ach-modal').classList.add('open');
  setTimeout(() => document.getElementById('f-code').focus(), 80);
}
function achOpenEdit(kind, row) {
  document.getElementById('f-kind').value = kind;
  document.getElementById('f-id').value = row.id;
  document.getElementById('ach-modal-ttl-txt').textContent = kind === 'famille' ? 'Modifier la famille' : 'Modifier le type';
  document.getElementById('f-code').value = row.code;
  document.getElementById('f-code').disabled = true; // le code est la cle metier, non modifiable apres creation
  document.getElementById('f-libelle').value = row.libelle;
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
  const kind = document.getElementById('f-kind').value;
  const id = document.getElementById('f-id').value;
  const code = document.getElementById('f-code').value.trim();
  const libelle = document.getElementById('f-libelle').value.trim();
  const err = document.getElementById('ach-err');
  if (!code || !libelle) { err.textContent = 'Code et libellé obligatoires.'; err.style.display = 'block'; return; }
  const action = (id ? 'update_' : 'create_') + kind;
  achPost({ action, id, code, libelle }).then(res => {
    if (!res.success) { err.textContent = res.message; err.style.display = 'block'; return; }
    toast(res.message, 'success');
    achCloseModal();
    setTimeout(() => location.reload(), 500);
  });
}

function achToggle(kind, id, actif) {
  if (!confirm(actif ? 'Réactiver ?' : 'Désactiver ?')) return;
  achPost({ action: 'toggle_' + kind, id, actif }).then(res => {
    toast(res.message, res.success ? 'success' : 'danger');
    if (res.success) setTimeout(() => location.reload(), 500);
  });
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
