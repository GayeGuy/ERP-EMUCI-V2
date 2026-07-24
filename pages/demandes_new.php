<?php
// ============================================================
//  pages/demandes_new.php — Nouvelle demande interne
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/demandes.php';
require_once __DIR__ . '/../includes/demandes_champs.php';

require_auth();
$user = current_user();
$_SESSION['groupe_actif'] = 'DEMANDES';
$page_title  = 'Nouvelle demande';
$active_page = 'demandes_new';

// ── AJAX : création / soumission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $is_admin_role = in_array($user['role_slug'] ?? '', ['admin', 'superadmin'], true);
    $has_dept = (bool)db_fetch_value(
        "SELECT COUNT(*) FROM user_departements WHERE user_id=?", [$user['id']]
    );
    if (!$has_dept && !$is_admin_role) {
        json_response(false, 'Vous devez être rattaché à un département pour soumettre une demande.');
    }
    $type      = trim($_POST['type_code'] ?? '');
    $champs    = $_POST['champs'] ?? [];
    $soumettre = ($_POST['action'] ?? '') === 'soumettre';
    if (!is_array($champs)) $champs = [];
    try {
        $id = di_creer($user, $type, $champs, $soumettre);
        json_response(true, $soumettre ? 'Demande soumise.' : 'Brouillon enregistré.', ['id' => $id]);
    } catch (Exception $e) {
        json_response(false, $e->getMessage());
    }
}

$types = di_types_actifs();
$sel   = trim($_GET['type'] ?? '');
$sel_type = $sel !== '' ? di_type($sel) : null;

// Vérifier si l'utilisateur a un département assigné
$user_dept = db_fetch_one(
    "SELECT d.label FROM user_departements ud JOIN departements d ON d.id=ud.departement_id WHERE ud.user_id=?",
    [$user['id']]
);
$has_dept = $user_dept !== null;

// Admin et superadmin peuvent toujours soumettre (pas soumis au circuit N+1)
$is_admin_role = in_array($user['role_slug'] ?? '', ['admin', 'superadmin'], true);
$can_submit = $has_dept || $is_admin_role;

include __DIR__ . '/../templates/header.php';
?>
<style>
  .di-wrap{max-width:900px;margin:0 auto}
  .di-types{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px}
  .di-type-card{display:flex;flex-direction:column;gap:6px;padding:18px;border:1.5px solid var(--border,#e2e8f0);
    border-radius:14px;background:var(--card,#fff);cursor:pointer;text-decoration:none;color:inherit;transition:.15s}
  .di-type-card:hover{border-color:var(--primary,#7C92FF);transform:translateY(-2px);box-shadow:0 8px 22px rgba(59,79,190,.12)}
  .di-type-card.soon{opacity:.55;cursor:not-allowed}
  .di-type-card .ic{width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;
    background:linear-gradient(135deg,#3B4FBE,#7C92FF);color:#fff;font-size:20px}
  .di-type-card h4{margin:4px 0 0;font-size:15px}
  .di-type-card p{margin:0;font-size:12px;color:var(--muted,#7f8c8d)}
  .di-badge-soon{align-self:flex-start;font-size:10px;font-weight:700;padding:2px 8px;border-radius:8px;background:#fef9e7;color:#e67e22}
  .di-form{background:var(--card,#fff);border:1.5px solid var(--border,#e2e8f0);border-radius:16px;padding:26px}
  .di-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .di-field{display:flex;flex-direction:column;gap:6px}
  .di-field label{font-size:13px;font-weight:600;color:var(--text,#2c3e50)}
  .di-field input,.di-field select,.di-field textarea{padding:10px 12px;border:1.5px solid var(--border,#d5dde8);
    border-radius:9px;font-size:14px;font-family:inherit;background:var(--input,#f8fafc);outline:none;width:100%}
  .di-field input:focus,.di-field select:focus,.di-field textarea:focus{border-color:var(--primary,#3B4FBE);background:#fff}
  .di-field input.di-auto{background:#eef1fc;color:#3B4FBE;font-weight:700;border-color:#c9d2f7;cursor:not-allowed}
  .di-actions{display:flex;gap:12px;justify-content:flex-end;margin-top:22px}
  .di-btn{padding:11px 22px;border:none;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer;font-family:inherit}
  .di-btn-primary{background:linear-gradient(135deg,#3B4FBE,#7C92FF);color:#fff}
  .di-btn-ghost{background:var(--input,#f0f4f8);color:var(--text,#2c3e50)}
  .di-alert{display:none;padding:12px 16px;border-radius:9px;font-size:13px;margin-bottom:16px}
  .di-alert.err{display:block;background:#fdf0ef;color:#e74c3c;border-left:3px solid #e74c3c}
</style>

<div class="di-wrap">

<?php if (!$can_submit): ?>
  <div style="text-align:center;padding:60px 20px;background:white;border:1.5px solid var(--border,#e2e8f0);border-radius:16px">
    <div style="width:56px;height:56px;border-radius:16px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:26px;color:#94a3b8">
      <i class="ph-duotone ph-lock"></i>
    </div>
    <div style="font-family:'Montserrat',sans-serif;font-size:17px;font-weight:800;color:var(--navy,#06033A);margin-bottom:8px">
      Département non assigné
    </div>
    <div style="font-size:13px;color:var(--muted,#94a3b8);max-width:380px;margin:0 auto;line-height:1.6">
      Vous devez être rattaché à un département avant de pouvoir soumettre une demande interne.<br>
      Contactez un administrateur pour qu'il vous assigne à votre département.
    </div>
  </div>

<?php elseif (!$sel_type): ?>
  <h2 style="margin:0 0 6px">Nouvelle demande interne</h2>
  <p style="color:var(--muted,#7f8c8d);margin:0 0 22px">Choisissez le type de demande à soumettre.</p>
  <div class="di-types">
    <?php foreach ($types as $t): ?>
      <a class="di-type-card" href="?type=<?= urlencode($t['code']) ?>">
        <div class="ic"><i class="ph-duotone ph-file-text"></i></div>
        <h4><?= h($t['label']) ?></h4>
        <p><?= h($t['description']) ?></p>
      </a>
    <?php endforeach; ?>
  </div>
<?php else:
    $type   = $sel_type;
    $fields = di_champs_of($sel);
    $wf     = di_workflow($sel);
?>
  <a href="<?= APP_URL ?>/pages/demandes_new.php" style="font-size:13px;color:var(--muted,#7f8c8d);text-decoration:none">← Changer de type</a>
  <h2 style="margin:8px 0 4px"><?= h($type['label']) ?></h2>
  <p style="color:var(--muted,#7f8c8d);margin:0 0 8px"><?= h($type['description']) ?></p>
  <p style="font-size:12px;color:var(--muted,#7f8c8d);margin:0 0 20px">
    Circuit : <?= implode(' → ', array_map(fn($s)=>h($s['label']), $wf)) ?>
  </p>

  <form class="di-form" id="di-form" onsubmit="return false">
    <input type="hidden" name="type_code" value="<?= h($sel) ?>">
    <div class="di-alert" id="di-err"></div>
    <div class="di-grid">
      <?php foreach ($fields as $f) echo di_render_field($f); ?>
    </div>
    <div class="di-actions">
      <button type="button" class="di-btn di-btn-ghost" onclick="diSubmit('brouillon')">Enregistrer brouillon</button>
      <button type="button" class="di-btn di-btn-primary" onclick="diSubmit('soumettre')">Soumettre la demande</button>
    </div>
  </form>
<?php endif; ?>
</div>

<script>
function diSubmit(action){
  const form = document.getElementById('di-form');
  const err  = document.getElementById('di-err');
  err.classList.remove('err'); err.textContent='';
  const fd = new FormData(form);
  fd.append('action', action);
  fetch(window.location.href, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd})
    .then(r=>r.json())
    .then(d=>{
      if(d.success){ window.location.href='<?= APP_URL ?>/pages/demandes.php?id='+d.data.id; }
      else { err.classList.add('err'); err.textContent='⚠️ '+(d.message||'Erreur'); window.scrollTo(0,0); }
    })
    .catch(()=>{ err.classList.add('err'); err.textContent='Erreur réseau.'; });
}
</script>

<?php if ($sel === 'autorisation_absence'): ?>
<script>
// Auto-remplissage : Type de permission (jours entre parenthèses) + Du → jours, Au, reprise
(function(){
  var type  = document.getElementById('f_type_permission');
  var debut = document.getElementById('f_date_debut');
  var jours = document.getElementById('f_nb_jours');
  var fin   = document.getElementById('f_date_fin');
  var rep   = document.getElementById('f_date_reprise');
  if(!type || !debut || !jours || !fin || !rep) return;
  var autos = [jours, fin, rep];

  function selDays(){
    var opt = type.options[type.selectedIndex];
    var m = opt ? opt.text.match(/\((\d+)\s*j\)/i) : null;
    return m ? parseInt(m[1], 10) : null;
  }
  function addDays(iso, n){
    if(!iso) return '';
    var d = new Date(iso + 'T00:00:00');
    if(isNaN(d.getTime())) return '';
    d.setDate(d.getDate() + n);
    return d.toISOString().slice(0, 10);
  }
  function setAuto(on){
    autos.forEach(function(el){
      el.readOnly = on;
      el.classList.toggle('di-auto', on);
      el.style.pointerEvents = on ? 'none' : '';
      el.tabIndex = on ? -1 : 0;
    });
  }
  function apply(){
    var d = selDays();
    if(d){
      setAuto(true);
      jours.value = d;
      fin.value = debut.value ? addDays(debut.value, d - 1) : '';
      rep.value = debut.value ? addDays(debut.value, d)     : '';
    } else {
      setAuto(false);
    }
  }
  type.addEventListener('change', apply);
  debut.addEventListener('change', apply);
  apply();
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
