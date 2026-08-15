<?php
// ============================================================
//  pages/achats/param_general.php — Paramètres généraux du module Achats
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
$page_title  = 'Paramètres généraux';
$active_page = 'achats_param_general';

// ── AJAX ────────────────────────────────────────────────────
if (is_ajax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!$can_edit) json_response(false, 'Action réservée.');
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $cle    = trim($_POST['cle'] ?? '');
        $valeur = trim($_POST['valeur'] ?? '');
        if (!$cle) json_response(false, 'Paramètre introuvable.');

        $param = db_fetch_one("SELECT * FROM achat_parametres WHERE cle = ?", [$cle]);
        if (!$param) json_response(false, 'Ce paramètre n\'existe pas.');

        if ($param['type'] === 'nombre' && $valeur !== '' && !is_numeric($valeur)) {
            json_response(false, 'Ce paramètre attend une valeur numérique.');
        }
        if ($param['type'] === 'liste') {
            $options = array_map('trim', explode(',', (string)$param['options']));
            if (!in_array($valeur, $options, true)) {
                json_response(false, 'Valeur hors de la liste autorisée.');
            }
        }
        if ($valeur === '') json_response(false, 'La valeur ne peut pas être vide.');

        db_query(
            "UPDATE achat_parametres SET valeur=?, modifie_par=?, modifie_le=NOW() WHERE cle=?",
            [$valeur, $user['id'], $cle]
        );
        audit_log($user['id'], 'UPDATE', 'achats_param', null, "Modification paramètre achats « $cle » → « $valeur »");
        json_response(true, 'Paramètre mis à jour.');
    }

    json_response(false, 'Action inconnue.');
}

// ── PAGE PHP ─────────────────────────────────────────────────
$parametres = db_fetch_all("SELECT * FROM achat_parametres ORDER BY libelle ASC");

include __DIR__ . '/../../templates/header.php';
?>
<style>
.ach-table-wrap{background:white;border:1px solid var(--border);border-radius:16px;overflow:hidden}
.ach-param-row{display:flex;align-items:center;gap:16px;padding:16px 20px;border-bottom:1px solid var(--border);flex-wrap:wrap}
.ach-param-row:last-child{border-bottom:none}
.ach-param-info{flex:1;min-width:220px}
.ach-param-lbl{font-weight:700;font-size:13.5px;color:var(--navy)}
.ach-param-cle{font-size:11.5px;color:var(--muted);font-family:monospace;margin-top:2px}
.ach-param-input{display:flex;align-items:center;gap:10px;flex-shrink:0}
.ach-param-input input,.ach-param-input select{padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;min-width:180px;box-sizing:border-box;background:white}
.ach-save-msg{font-size:12px;color:#065F46;margin-left:6px;min-height:16px}
@media (max-width:768px) {
  .ach-param-row { flex-direction:column; align-items:stretch; }
  .ach-param-input input, .ach-param-input select, .ach-param-input button { min-height:44px; }
}
</style>

<div class="ach-table-wrap">
  <?php foreach ($parametres as $p): $inputId = 'p-' . h($p['cle']); ?>
  <div class="ach-param-row">
    <div class="ach-param-info">
      <div class="ach-param-lbl"><label for="<?= $inputId ?>"><?= h($p['libelle']) ?></label></div>
      <div class="ach-param-cle"><?= h($p['cle']) ?></div>
    </div>
    <div class="ach-param-input">
      <?php if ($p['type'] === 'liste'):
        $options = array_map('trim', explode(',', (string)$p['options'])); ?>
        <select id="<?= $inputId ?>" data-cle="<?= h($p['cle']) ?>" <?= $can_edit ? '' : 'disabled' ?>>
          <?php foreach ($options as $opt): ?>
            <option value="<?= h($opt) ?>" <?= $opt === $p['valeur'] ? 'selected' : '' ?>><?= h($opt) ?></option>
          <?php endforeach; ?>
        </select>
      <?php elseif ($p['type'] === 'nombre'): ?>
        <input type="number" id="<?= $inputId ?>" data-cle="<?= h($p['cle']) ?>" value="<?= h($p['valeur']) ?>" <?= $can_edit ? '' : 'readonly' ?>>
      <?php else: ?>
        <input type="text" id="<?= $inputId ?>" data-cle="<?= h($p['cle']) ?>" value="<?= h($p['valeur']) ?>" <?= $can_edit ? '' : 'readonly' ?>>
      <?php endif; ?>
      <?php if ($can_edit): ?>
      <button type="button" class="btn btn-secondary btn-sm" onclick="achSaveParam('<?= h($p['cle']) ?>')">Enregistrer</button>
      <?php endif; ?>
      <span class="ach-save-msg" id="msg-<?= h($p['cle']) ?>"></span>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<script>
function achSaveParam(cle) {
  const el = document.querySelector(`[data-cle="${CSS.escape(cle)}"]`);
  const msg = document.getElementById('msg-' + cle);
  if (!el) return;
  const fd = new FormData();
  fd.append('action', 'save');
  fd.append('cle', cle);
  fd.append('valeur', el.value);
  fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
    .then(r => r.json())
    .then(res => {
      toast(res.message, res.success ? 'success' : 'danger');
      msg.textContent = res.success ? 'Enregistré' : '';
      if (res.success) setTimeout(() => { msg.textContent = ''; }, 2500);
    });
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
