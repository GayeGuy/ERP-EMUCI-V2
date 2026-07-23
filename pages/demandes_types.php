<?php
// ============================================================
//  pages/demandes_types.php — Admin : Types de demande & circuits
//  Rend le module modulable : créer/modifier des types et définir
//  leur circuit de validation (étapes ordonnées = rôles valideurs).
//  Réservé admin / superadmin.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/demandes.php';
require_once __DIR__ . '/../includes/demandes_champs.php';

require_auth();
$user = current_user();
if (!in_array(($user['role_slug'] ?? ''), ['admin','superadmin'], true)) {
    http_response_code(403); include __DIR__ . '/../templates/403.php'; exit;
}
$_SESSION['groupe_actif'] = 'DEMANDES';
$page_title  = 'Types & circuits';
$active_page = 'demandes_types';

// ── AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $valid_roles = array_column(db_fetch_all("SELECT code FROM di_roles"), 'code');

    if ($action === 'save_type') {
        $code  = strtolower(preg_replace('/[^a-z0-9_]/', '_', trim($_POST['code'] ?? '')));
        $label = trim($_POST['label'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $tit   = ($_POST['traitement_it'] ?? '') === '1' ? 1 : 0;
        $steps = json_decode($_POST['steps'] ?? '[]', true);
        if ($code === '' || $label === '') json_response(false, 'Code et libellé obligatoires.');
        if (!is_array($steps) || count($steps) === 0) json_response(false, 'Au moins une étape de validation est requise.');
        foreach ($steps as $s) {
            if (!in_array($s['role_code'] ?? '', $valid_roles, true)) json_response(false, 'Rôle invalide dans le circuit.');
        }
        $existing = di_type($code);
        if ($existing) {
            db_query("UPDATE di_types SET label=?, description=?, traitement_it=? WHERE code=?", [$label, $desc, $tit, $code]);
            $tid = (int)$existing['id'];
        } else {
            $ordre = (int) db_fetch_value("SELECT COUNT(*) FROM di_types");
            db_query("INSERT INTO di_types (code,label,description,actif,traitement_it,date_auto_jours,ordre) VALUES (?,?,?,1,?,NULL,?)",
                [$code, $label, $desc, $tit, $ordre]);
            $tid = (int) db_last_id();
        }
        db_query("DELETE FROM di_etapes WHERE type_id=?", [$tid]);
        foreach (array_values($steps) as $i => $s) {
            $lbl = trim($s['label'] ?? '') !== '' ? trim($s['label']) : ('Visa ' . $s['role_code']);
            db_query("INSERT INTO di_etapes (type_id, role_code, label, ordre) VALUES (?,?,?,?)", [$tid, $s['role_code'], $lbl, $i]);
        }
        json_response(true, $existing ? 'Type mis à jour.' : 'Type créé.');
    }

    if ($action === 'toggle_type') {
        $code  = trim($_POST['code'] ?? '');
        $actif = ($_POST['actif'] ?? '') === '1' ? 1 : 0;
        db_query("UPDATE di_types SET actif=? WHERE code=?", [$actif, $code]);
        json_response(true, $actif ? 'Type activé.' : 'Type désactivé.');
    }
    json_response(false, 'Action inconnue.');
}

$types = db_fetch_all("SELECT * FROM di_types ORDER BY ordre ASC");
$roles = db_fetch_all("SELECT code, label FROM di_roles ORDER BY ordre ASC");
$etapes_by = [];
foreach (db_fetch_all(
    "SELECT t.code AS tcode, e.role_code, e.label FROM di_etapes e JOIN di_types t ON t.id=e.type_id ORDER BY e.ordre ASC"
) as $e) {
    $etapes_by[$e['tcode']][] = ['role_code' => $e['role_code'], 'label' => $e['label']];
}
$champs_codes = array_keys(di_champs_config());
// Données pour l'éditeur JS
$js_types = [];
foreach ($types as $t) {
    $js_types[$t['code']] = [
        'label' => $t['label'], 'description' => $t['description'],
        'traitement_it' => (int)$t['traitement_it'], 'etapes' => $etapes_by[$t['code']] ?? [],
    ];
}

include __DIR__ . '/../templates/header.php';
?>
<style>
  .dt-wrap{max-width:1000px;margin:0 auto}
  .dt-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
  .dt-hint{font-size:12px;color:var(--muted,#7f8c8d);margin:0 0 18px}
  .dt-btn{padding:10px 18px;border:none;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit;
    display:inline-flex;align-items:center;gap:7px}
  .dt-btn-primary{background:linear-gradient(135deg,#3B4FBE,#7C92FF);color:#fff}
  .dt-btn-ghost{background:var(--input,#f0f4f8);color:var(--text,#2c3e50)}
  .dt-card{background:var(--card,#fff);border:1.5px solid var(--border,#e2e8f0);border-radius:14px;padding:16px 18px;margin-bottom:12px}
  .dt-card.off{opacity:.6}
  .dt-card .head{display:flex;justify-content:space-between;align-items:start;gap:12px}
  .dt-card h4{margin:0 0 3px;font-size:15px}
  .dt-code{font-size:11px;color:var(--muted,#7f8c8d);font-family:monospace}
  .dt-chips{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px;align-items:center}
  .dt-chip{font-size:12px;font-weight:600;padding:4px 10px;border-radius:9px;background:#eef1fc;color:#3B4FBE}
  .dt-arrow{color:#cbd5e1;font-weight:700}
  .dt-tag{font-size:10px;font-weight:700;padding:2px 8px;border-radius:8px}
  .dt-tag.it{background:#e8f8f5;color:#16a085}
  .dt-tag.gen{background:#fef9e7;color:#e67e22}
  .dt-acts{display:flex;gap:8px;align-items:center;flex-shrink:0}
  .dt-link{font-size:13px;color:#3B4FBE;cursor:pointer;text-decoration:none;font-weight:600}
  .dt-link.red{color:#e74c3c}
  /* Modal */
  .dt-modal{display:none;position:fixed;inset:0;background:rgba(6,3,58,.45);z-index:999;align-items:center;justify-content:center;padding:20px}
  .dt-modal.open{display:flex}
  .dt-box{background:var(--card,#fff);border-radius:16px;padding:24px;max-width:560px;width:100%;max-height:90vh;overflow:auto}
  .dt-box h3{margin:0 0 16px}
  .dt-field{margin-bottom:14px}
  .dt-field label{display:block;font-size:13px;font-weight:600;margin-bottom:5px}
  .dt-field input,.dt-field textarea,.dt-field select{width:100%;padding:9px 12px;border:1.5px solid var(--border,#d5dde8);
    border-radius:9px;font-size:14px;font-family:inherit;background:var(--input,#f8fafc)}
  .dt-check{display:flex;align-items:center;gap:8px;font-size:14px;cursor:pointer}
  .dt-steps{border:1.5px dashed var(--border,#d5dde8);border-radius:10px;padding:12px;margin-top:4px}
  .dt-step{display:flex;gap:8px;align-items:center;margin-bottom:8px}
  .dt-step select{flex:0 0 160px}.dt-step input{flex:1}
  .dt-step .mv{cursor:pointer;color:#7f8c8d;font-weight:700;padding:0 4px;user-select:none}
  .dt-step .rm{cursor:pointer;color:#e74c3c;font-weight:700;padding:0 4px}
  .dt-err{display:none;background:#fdf0ef;color:#e74c3c;padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:12px}
  .dt-modal-acts{display:flex;justify-content:flex-end;gap:10px;margin-top:18px}
</style>

<div class="dt-wrap">
  <div class="dt-top">
    <h2 style="margin:0">Types de demande & circuits</h2>
    <button class="dt-btn dt-btn-primary" onclick="dtOpen(null)"><i class="ph-duotone ph-plus"></i> Nouveau type</button>
  </div>
  <p class="dt-hint">Créez des types de demande et définissez leur circuit de validation (étapes ordonnées). Les types sans formulaire dédié utilisent un formulaire générique (objet + détails).</p>

  <?php foreach ($types as $t): $steps = $etapes_by[$t['code']] ?? []; $hasForm = in_array($t['code'], $champs_codes, true); ?>
    <div class="dt-card <?= $t['actif'] ? '' : 'off' ?>">
      <div class="head">
        <div>
          <h4><?= h($t['label']) ?>
            <?php if ($t['traitement_it']): ?><span class="dt-tag it">Traitement IT</span><?php endif; ?>
            <?php if (!$hasForm): ?><span class="dt-tag gen">Formulaire générique</span><?php endif; ?>
          </h4>
          <span class="dt-code"><?= h($t['code']) ?></span>
          <div class="dt-chips">
            <?php foreach ($steps as $i => $s): ?>
              <?php if ($i): ?><span class="dt-arrow">→</span><?php endif; ?>
              <span class="dt-chip"><?= h($s['label']) ?></span>
            <?php endforeach; ?>
            <?php if (!$steps): ?><span style="color:#e74c3c;font-size:12px">⚠ aucun circuit défini</span><?php endif; ?>
          </div>
        </div>
        <div class="dt-acts">
          <a class="dt-link" onclick="dtOpen('<?= h($t['code']) ?>')">Modifier</a>
          <a class="dt-link <?= $t['actif'] ? 'red' : '' ?>" onclick="dtToggle('<?= h($t['code']) ?>', <?= $t['actif'] ? 0 : 1 ?>)">
            <?= $t['actif'] ? 'Désactiver' : 'Activer' ?></a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Modal type -->
<div class="dt-modal" id="dt-modal">
  <div class="dt-box">
    <h3 id="dt-title">Nouveau type de demande</h3>
    <div class="dt-err" id="dt-err"></div>
    <div class="dt-field">
      <label>Code (identifiant unique)</label>
      <input id="dt-code" placeholder="ex: demande_materiel">
      <div id="dt-code-hint" style="font-size:11px;color:#7f8c8d;margin-top:3px;display:none">Le code ne peut pas être modifié.</div>
    </div>
    <div class="dt-field"><label>Libellé affiché</label><input id="dt-label" placeholder="ex: Demande de matériel"></div>
    <div class="dt-field"><label>Description</label><textarea id="dt-desc" rows="2"></textarea></div>
    <div class="dt-field"><label class="dt-check"><input type="checkbox" id="dt-it" style="width:auto"> Nécessite un traitement par le service IT après approbation</label></div>
    <div class="dt-field">
      <label>Circuit de validation (étapes ordonnées)</label>
      <div class="dt-steps" id="dt-steps"></div>
      <button class="dt-btn dt-btn-ghost" style="margin-top:8px;font-size:12px;padding:7px 14px" onclick="dtAddStep()">+ Ajouter une étape</button>
    </div>
    <div class="dt-modal-acts">
      <button class="dt-btn dt-btn-ghost" onclick="dtClose()">Annuler</button>
      <button class="dt-btn dt-btn-primary" onclick="dtSave()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
const DT_TYPES = <?= json_encode($js_types, JSON_UNESCAPED_UNICODE) ?>;
const DT_ROLES = <?= json_encode($roles, JSON_UNESCAPED_UNICODE) ?>;
let dtEditCode = null;

function dtRoleOptions(sel){
  return DT_ROLES.map(r => `<option value="${r.code}"${r.code===sel?' selected':''}>${r.label}</option>`).join('');
}
function dtAddStep(role, label){
  const div = document.createElement('div');
  div.className = 'dt-step';
  div.innerHTML = `<select>${dtRoleOptions(role||DT_ROLES[0].code)}</select>`
    + `<input placeholder="Libellé de l'étape (ex: Visa N+1)" value="${(label||'').replace(/"/g,'&quot;')}">`
    + `<span class="mv" onclick="this.parentNode.previousElementSibling&&this.parentNode.parentNode.insertBefore(this.parentNode,this.parentNode.previousElementSibling)">▲</span>`
    + `<span class="mv" onclick="this.parentNode.nextElementSibling&&this.parentNode.parentNode.insertBefore(this.parentNode.nextElementSibling,this.parentNode)">▼</span>`
    + `<span class="rm" onclick="this.parentNode.remove()">✕</span>`;
  document.getElementById('dt-steps').appendChild(div);
}
function dtOpen(code){
  dtEditCode = code;
  document.getElementById('dt-err').style.display='none';
  document.getElementById('dt-steps').innerHTML='';
  const codeInput = document.getElementById('dt-code');
  if (code && DT_TYPES[code]) {
    const t = DT_TYPES[code];
    document.getElementById('dt-title').textContent = 'Modifier — ' + t.label;
    codeInput.value = code; codeInput.disabled = true;
    document.getElementById('dt-code-hint').style.display='block';
    document.getElementById('dt-label').value = t.label;
    document.getElementById('dt-desc').value = t.description || '';
    document.getElementById('dt-it').checked = !!t.traitement_it;
    (t.etapes||[]).forEach(e => dtAddStep(e.role_code, e.label));
  } else {
    document.getElementById('dt-title').textContent = 'Nouveau type de demande';
    codeInput.value=''; codeInput.disabled=false;
    document.getElementById('dt-code-hint').style.display='none';
    document.getElementById('dt-label').value=''; document.getElementById('dt-desc').value='';
    document.getElementById('dt-it').checked=false;
    dtAddStep();
  }
  document.getElementById('dt-modal').classList.add('open');
}
function dtClose(){ document.getElementById('dt-modal').classList.remove('open'); }
function dtErr(m){ const e=document.getElementById('dt-err'); e.textContent='⚠️ '+m; e.style.display='block'; }
function dtSave(){
  const code = document.getElementById('dt-code').value.trim();
  const label = document.getElementById('dt-label').value.trim();
  if(!code || !label){ dtErr('Code et libellé obligatoires.'); return; }
  const steps = [...document.querySelectorAll('#dt-steps .dt-step')].map(d => ({
    role_code: d.querySelector('select').value, label: d.querySelector('input').value.trim()
  }));
  if(steps.length===0){ dtErr('Au moins une étape de validation.'); return; }
  const fd = new FormData();
  fd.append('action','save_type'); fd.append('code',code); fd.append('label',label);
  fd.append('description',document.getElementById('dt-desc').value.trim());
  fd.append('traitement_it', document.getElementById('dt-it').checked?'1':'0');
  fd.append('steps', JSON.stringify(steps));
  fetch(window.location.pathname,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd})
    .then(r=>r.json()).then(d=>{ if(d.success) location.reload(); else dtErr(d.message); })
    .catch(()=>dtErr('Erreur réseau.'));
}
function dtToggle(code,actif){
  const fd=new FormData(); fd.append('action','toggle_type'); fd.append('code',code); fd.append('actif',actif);
  fetch(window.location.pathname,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd})
    .then(r=>r.json()).then(d=>{ if(d.success) location.reload(); else alert(d.message); });
}
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
