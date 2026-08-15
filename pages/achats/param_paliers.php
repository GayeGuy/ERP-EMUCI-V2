<?php
// ============================================================
//  pages/achats/param_paliers.php — Paliers de validation
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
$page_title  = 'Paliers de validation';
$active_page = 'achats_param_paliers';

const ACH_INF = 2147483647; // infini pratique pour le controle de chevauchement (borne_max NULL)

// ── AJAX ────────────────────────────────────────────────────
if (is_ajax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!$can_edit) json_response(false, 'Action réservée.');
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id       = (int)($_POST['id'] ?? 0);
        $lib      = trim($_POST['libelle'] ?? '');
        $bmin     = (int)($_POST['borne_min'] ?? 0);
        $bmaxRaw  = trim($_POST['borne_max'] ?? '');
        $bmax     = $bmaxRaw === '' ? null : (int)$bmaxRaw;
        $ordre    = (int)($_POST['ordre'] ?? 0);
        $signataires = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['signataires'] ?? [])))));

        if (!$lib) json_response(false, 'Le libellé est obligatoire.');
        if ($bmax !== null && $bmax < $bmin) json_response(false, 'La borne haute doit être supérieure à la borne basse.');
        if (count($signataires) > 3) json_response(false, 'Trois signataires maximum.');
        if (empty($signataires)) json_response(false, 'Au moins un signataire est requis.');

        // Contrôle de non-chevauchement avec les autres paliers actifs
        // Cast explicite (::integer) sur les paramètres potentiellement NULL dans
        // COALESCE : sans lui, PDO/pgsql ne peut pas déduire leur type et Postgres
        // rejette la comparaison (« operator does not exist: integer <= text »).
        $chevauche = db_fetch_all(
            "SELECT id, libelle FROM achat_paliers
              WHERE actif = 1 AND id != ?
                AND ? <= COALESCE(borne_max, ?::integer)
                AND borne_min <= COALESCE(?::integer, ?::integer)",
            [$id, $bmin, ACH_INF, $bmax, ACH_INF]
        );
        if ($chevauche) {
            json_response(false, 'Cette plage chevauche le palier « ' . $chevauche[0]['libelle'] . ' ».');
        }

        $sigJson = json_encode($signataires);
        if ($id) {
            db_query(
                "UPDATE achat_paliers SET libelle=?, borne_min=?, borne_max=?, signataires=?::jsonb, ordre=? WHERE id=?",
                [$lib, $bmin, $bmax, $sigJson, $ordre, $id]
            );
            audit_log($user['id'], 'UPDATE', 'achats_param', $id, "Modification palier : $lib");
            json_response(true, 'Palier mis à jour.');
        } else {
            db_query(
                "INSERT INTO achat_paliers (libelle, borne_min, borne_max, signataires, ordre) VALUES (?,?,?,?::jsonb,?)",
                [$lib, $bmin, $bmax, $sigJson, $ordre]
            );
            $newId = (int)db_last_id('achat_paliers_id_seq');
            audit_log($user['id'], 'CREATE', 'achats_param', $newId, "Création palier : $lib");
            json_response(true, 'Palier créé.', ['id' => $newId]);
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $nv = (int)($_POST['actif'] ?? 0) ? 1 : 0;
        db_query("UPDATE achat_paliers SET actif=? WHERE id=?", [$nv, $id]);
        audit_log($user['id'], 'UPDATE', 'achats_param', $id, $nv ? 'Réactivation palier' : 'Désactivation palier');
        json_response(true, $nv ? 'Palier réactivé.' : 'Palier désactivé.');
    }

    json_response(false, 'Action inconnue.');
}

// ── PAGE PHP ─────────────────────────────────────────────────
$paliers   = db_fetch_all("SELECT * FROM achat_paliers ORDER BY ordre ASC, borne_min ASC");
$roles     = db_fetch_all("SELECT slug, nom FROM roles ORDER BY nom ASC");
$roleNames = array_column($roles, 'nom', 'slug');

include __DIR__ . '/../../templates/header.php';
?>
<style>
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
.ach-chip{display:inline-flex;align-items:center;padding:2px 9px;border-radius:14px;font-size:11.5px;font-weight:700;background:#E8ECFF;color:#3D4FD1;margin-right:4px}
.ach-empty{padding:30px 20px;text-align:center;color:var(--muted);font-size:13px}
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
@media (max-width:768px) {
  .ach-fg input, .ach-fg select { min-height:44px; }
  .ach-row2 { grid-template-columns: minmax(0,1fr); }
}
</style>

<div class="ach-table-wrap">
  <div class="ach-table-hdr">
    <span class="ach-table-ttl">Paliers de validation (<?= count($paliers) ?>)</span>
    <?php if ($can_edit): ?>
    <button type="button" class="btn btn-primary btn-sm" onclick="achOpenCreate()">
      <i class="ph ph-plus" aria-hidden="true"></i> Nouveau palier
    </button>
    <?php endif; ?>
  </div>
  <?php if (empty($paliers)): ?>
    <div class="ach-empty">Aucun palier configuré.</div>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="ach-table">
    <thead><tr><th>Ordre</th><th>Libellé</th><th>Plage (XOF)</th><th>Signataires</th><th>Statut</th><?php if ($can_edit): ?><th>Actions</th><?php endif; ?></tr></thead>
    <tbody>
      <?php foreach ($paliers as $p):
        $sig = json_decode($p['signataires'], true) ?? [];
        $plage = number_format((float)$p['borne_min'], 0, ',', ' ') . ' — ' . ($p['borne_max'] !== null ? number_format((float)$p['borne_max'], 0, ',', ' ') : '∞');
      ?>
      <tr>
        <td><?= (int)$p['ordre'] ?></td>
        <td style="font-weight:700;color:var(--navy)"><?= h($p['libelle']) ?></td>
        <td><?= h($plage) ?></td>
        <td><?php foreach ($sig as $s): ?><span class="ach-chip"><?= h($roleNames[$s] ?? $s) ?></span><?php endforeach; ?></td>
        <td><span class="ach-badge <?= $p['actif'] ? 'on' : 'off' ?>"><?= $p['actif'] ? 'Actif' : 'Inactif' ?></span></td>
        <?php if ($can_edit): ?>
        <td style="display:flex;gap:8px">
          <button type="button" class="btn btn-secondary btn-sm" aria-label="Modifier <?= h($p['libelle']) ?>"
                  onclick='achOpenEdit(<?= json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
            <i class="ph ph-pencil-simple" aria-hidden="true"></i>
          </button>
          <button type="button" class="btn btn-secondary btn-sm" aria-label="<?= $p['actif'] ? 'Désactiver' : 'Réactiver' ?> <?= h($p['libelle']) ?>"
                  onclick="achToggle(<?= $p['id'] ?>, <?= $p['actif'] ? 0 : 1 ?>)">
            <i class="ph <?= $p['actif'] ? 'ph-prohibit' : 'ph-check-circle' ?>" aria-hidden="true"></i>
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
    <h3 id="ach-modal-title"><span id="ach-modal-ttl-txt">Nouveau palier</span></h3>
    <input type="hidden" id="f-id" value="">
    <div class="ach-fg">
      <label for="f-libelle">Libellé</label>
      <input type="text" id="f-libelle" required placeholder="ex : RAF + DAF">
    </div>
    <div class="ach-row2">
      <div class="ach-fg">
        <label for="f-bmin">Borne basse (XOF)</label>
        <input type="number" id="f-bmin" required min="0" step="1">
      </div>
      <div class="ach-fg">
        <label for="f-bmax">Borne haute (XOF)</label>
        <input type="number" id="f-bmax" min="0" step="1" placeholder="vide = pas de plafond">
      </div>
    </div>
    <div class="ach-fg">
      <label for="f-ordre">Ordre d'affichage</label>
      <input type="number" id="f-ordre" min="0" step="1" value="0">
    </div>
    <div class="ach-fg">
      <label for="f-sig1">Signataire 1</label>
      <select id="f-sig1" required>
        <option value="">— Sélectionner —</option>
        <?php foreach ($roles as $r): ?><option value="<?= h($r['slug']) ?>"><?= h($r['nom']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="ach-fg">
      <label for="f-sig2">Signataire 2</label>
      <select id="f-sig2">
        <option value="">— Aucun —</option>
        <?php foreach ($roles as $r): ?><option value="<?= h($r['slug']) ?>"><?= h($r['nom']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="ach-fg">
      <label for="f-sig3">Signataire 3</label>
      <select id="f-sig3">
        <option value="">— Aucun —</option>
        <?php foreach ($roles as $r): ?><option value="<?= h($r['slug']) ?>"><?= h($r['nom']) ?></option><?php endforeach; ?>
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
  document.getElementById('ach-modal-ttl-txt').textContent = 'Nouveau palier';
  document.getElementById('f-libelle').value = '';
  document.getElementById('f-bmin').value = '';
  document.getElementById('f-bmax').value = '';
  document.getElementById('f-ordre').value = '0';
  document.getElementById('f-sig1').value = '';
  document.getElementById('f-sig2').value = '';
  document.getElementById('f-sig3').value = '';
  document.getElementById('ach-err').style.display = 'none';
  document.getElementById('ach-modal').classList.add('open');
  setTimeout(() => document.getElementById('f-libelle').focus(), 80);
}
function achOpenEdit(p) {
  document.getElementById('f-id').value = p.id;
  document.getElementById('ach-modal-ttl-txt').textContent = 'Modifier le palier';
  document.getElementById('f-libelle').value = p.libelle;
  document.getElementById('f-bmin').value = p.borne_min;
  document.getElementById('f-bmax').value = p.borne_max ?? '';
  document.getElementById('f-ordre').value = p.ordre;
  const sig = JSON.parse(p.signataires || '[]');
  document.getElementById('f-sig1').value = sig[0] || '';
  document.getElementById('f-sig2').value = sig[1] || '';
  document.getElementById('f-sig3').value = sig[2] || '';
  document.getElementById('ach-err').style.display = 'none';
  document.getElementById('ach-modal').classList.add('open');
}
function achCloseModal() { document.getElementById('ach-modal').classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') achCloseModal(); });
document.getElementById('ach-modal').addEventListener('click', e => { if (e.target === e.currentTarget) achCloseModal(); });

function achPost(data) {
  const fd = new FormData();
  Object.entries(data).forEach(([k, v]) => {
    if (Array.isArray(v)) v.forEach(item => fd.append(k + '[]', item));
    else fd.append(k, v);
  });
  return fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd }).then(r => r.json());
}

function achSave() {
  const err = document.getElementById('ach-err');
  const libelle = document.getElementById('f-libelle').value.trim();
  const bmin = document.getElementById('f-bmin').value;
  const bmax = document.getElementById('f-bmax').value;
  const sig = [document.getElementById('f-sig1').value, document.getElementById('f-sig2').value, document.getElementById('f-sig3').value]
    .filter(Boolean);
  if (!libelle || bmin === '') { err.textContent = 'Libellé et borne basse obligatoires.'; err.style.display = 'block'; return; }
  if (!sig.length) { err.textContent = 'Au moins un signataire est requis.'; err.style.display = 'block'; return; }
  if (new Set(sig).size !== sig.length) { err.textContent = 'Un même rôle ne peut pas signer deux fois.'; err.style.display = 'block'; return; }
  if (bmax !== '' && Number(bmax) < Number(bmin)) { err.textContent = 'La borne haute doit être supérieure à la borne basse.'; err.style.display = 'block'; return; }

  achPost({
    action: 'save',
    id: document.getElementById('f-id').value,
    libelle, borne_min: bmin, borne_max: bmax,
    ordre: document.getElementById('f-ordre').value,
    signataires: sig,
  }).then(res => {
    if (!res.success) { err.textContent = res.message; err.style.display = 'block'; return; }
    toast(res.message, 'success');
    achCloseModal();
    setTimeout(() => location.reload(), 500);
  });
}

function achToggle(id, actif) {
  if (!confirm(actif ? 'Réactiver ce palier ?' : 'Désactiver ce palier ?')) return;
  achPost({ action: 'toggle', id, actif }).then(res => {
    toast(res.message, res.success ? 'success' : 'danger');
    if (res.success) setTimeout(() => location.reload(), 500);
  });
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
