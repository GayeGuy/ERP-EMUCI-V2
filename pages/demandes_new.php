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

// Le lien est retiré du menu pour le lecteur, mais l'adresse resterait
// atteignable et le formulaire soumissible : on ferme ici, pour la page
// comme pour l'envoi.
if (!di_peut_creer($user)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        json_response(false, "Votre profil ne permet pas de déposer une demande.");
    }
    redirect_to('/pages/demandes.php');
    exit;
}

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
  .di-wrap{max-width:880px;margin:0 auto}
  .di-types{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px}
  .di-type-card{display:flex;flex-direction:column;gap:8px;padding:20px;border:1.5px solid #e8ecf3;
    border-radius:16px;background:var(--card,#fff);cursor:pointer;text-decoration:none;color:inherit;transition:.18s;box-shadow:0 1px 2px rgba(20,30,80,.04)}
  .di-type-card:hover{border-color:#7C92FF;transform:translateY(-3px);box-shadow:0 12px 28px rgba(59,79,190,.14)}
  .di-type-card .ic{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;
    background:linear-gradient(135deg,#3B4FBE,#7C92FF);color:#fff;font-size:21px}
  .di-type-card h4{margin:6px 0 0;font-size:15px;font-weight:700}
  .di-type-card p{margin:0;font-size:12.5px;color:var(--muted,#7f8c8d);line-height:1.5}
  .di-back{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#7482a0;text-decoration:none;font-weight:600;margin-bottom:14px;transition:.15s}
  .di-back:hover{color:#3B4FBE}
  .di-head{display:flex;align-items:flex-start;gap:14px;margin-bottom:18px}
  .di-head .ic{width:48px;height:48px;flex-shrink:0;border-radius:14px;display:flex;align-items:center;justify-content:center;
    background:linear-gradient(135deg,#3B4FBE,#7C92FF);color:#fff;font-size:23px;box-shadow:0 6px 16px rgba(59,79,190,.25)}
  .di-head h2{margin:0 0 3px;font-size:21px;font-weight:800;color:var(--navy,#06033A);letter-spacing:-.2px}
  .di-head p{margin:0;font-size:13.5px;color:var(--muted,#7f8c8d);line-height:1.5}
  .di-circuit{display:flex;align-items:center;flex-wrap:wrap;gap:8px;padding:13px 16px;margin-bottom:20px;background:#f4f6fd;border:1px solid #e6eaf8;border-radius:13px}
  .di-circuit-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#8a93b8;margin-right:2px}
  .di-cstep{display:inline-flex;align-items:center;gap:7px;background:#fff;border:1px solid #dfe4fb;border-radius:9px;padding:5px 12px 5px 6px;font-size:12.5px;font-weight:600;color:#3B4FBE}
  .di-cnum{width:19px;height:19px;border-radius:6px;background:linear-gradient(135deg,#3B4FBE,#7C92FF);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700}
  .di-carrow{color:#b9c2e6;font-size:13px}
  .di-form{background:var(--card,#fff);border:1px solid #e8ecf3;border-radius:18px;padding:28px 30px;box-shadow:0 4px 24px rgba(20,30,80,.05)}
  .di-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px 20px}
  .di-field{display:flex;flex-direction:column;gap:7px}
  .di-field>label{font-size:12.5px;font-weight:600;color:#46506b;letter-spacing:.1px}
  .di-field input:not([type=checkbox]),.di-field select,.di-field textarea{padding:0 14px;height:44px;border:1.5px solid #e4e8f1;
    border-radius:10px;font-size:14px;font-family:inherit;background:#f8fafc;color:#1f2a44;outline:none;width:100%;transition:border-color .15s,box-shadow .15s,background .15s}
  .di-field textarea{height:auto;min-height:92px;padding:11px 14px;resize:vertical;line-height:1.5}
  .di-field input::placeholder,.di-field textarea::placeholder{color:#a3adbf}
  .di-field input:not([type=checkbox]):hover,.di-field select:hover,.di-field textarea:hover{border-color:#cfd6e4}
  .di-field input:not([type=checkbox]):focus,.di-field select:focus,.di-field textarea:focus{border-color:#3B4FBE;background:#fff;box-shadow:0 0 0 3.5px rgba(59,79,190,.13)}
  .di-field input.di-auto{background:#eef1fc;color:#3B4FBE;font-weight:700;border-color:#cdd4f6;cursor:not-allowed}
  .di-field select{cursor:pointer;appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%237482a0' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");background-repeat:no-repeat;background-position:right 13px center;padding-right:40px}
  .di-field input[type=checkbox]{width:17px;height:17px;accent-color:#3B4FBE;cursor:pointer}
  .di-plats{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:9px}
  .di-plat{display:flex;align-items:center;gap:9px;font-size:13.5px;font-weight:500;cursor:pointer;padding:10px 13px;border:1.5px solid #e4e8f1;border-radius:10px;background:#f8fafc;transition:.15s}
  .di-plat:hover{border-color:#cfd6e4}
  .di-plat:has(input:checked){border-color:#3B4FBE;background:#eef1fc;color:#3B4FBE}
  .di-req-note{font-size:12px;color:#9aa4b8}
  .di-req-note b{color:#ef4444;font-weight:700}
  .di-actions{display:flex;gap:12px;justify-content:space-between;align-items:center;margin-top:24px;padding-top:20px;border-top:1px solid #eef1f6}
  .di-btn{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;border:1.5px solid transparent;border-radius:11px;font-weight:700;font-size:14px;cursor:pointer;font-family:inherit;transition:.16s}
  .di-btn i{font-size:17px}
  .di-btn-primary{background:linear-gradient(135deg,#3B4FBE,#7C92FF);color:#fff;box-shadow:0 6px 16px rgba(59,79,190,.28)}
  .di-btn-primary:hover{transform:translateY(-1px);box-shadow:0 9px 22px rgba(59,79,190,.34)}
  .di-btn-ghost{background:#f1f4fa;color:#475069}
  .di-btn-ghost:hover{background:#e6ebf5}
  .di-alert{display:none;padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:18px}
  .di-alert.err{display:flex;gap:8px;align-items:center;background:#fdf0ef;color:#e74c3c;border-left:3px solid #e74c3c}
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
  <a href="<?= APP_URL ?>/pages/demandes_new.php" class="di-back"><i class="ph-bold ph-arrow-left"></i> Changer de type</a>
  <div class="di-head">
    <div class="ic"><i class="ph-duotone ph-file-text"></i></div>
    <div>
      <h2><?= h($type['label']) ?></h2>
      <p><?= h($type['description']) ?></p>
    </div>
  </div>
  <div class="di-circuit">
    <span class="di-circuit-lbl">Circuit</span>
    <?php foreach ($wf as $i => $s): ?>
      <?php if ($i): ?><i class="ph-bold ph-arrow-right di-carrow"></i><?php endif; ?>
      <span class="di-cstep"><span class="di-cnum"><?= $i + 1 ?></span><?= h($s['label']) ?></span>
    <?php endforeach; ?>
  </div>

  <form class="di-form" id="di-form" onsubmit="return false">
    <input type="hidden" name="type_code" value="<?= h($sel) ?>">
    <div class="di-alert" id="di-err"></div>
    <div class="di-grid">
      <?php foreach ($fields as $f) echo di_render_field($f); ?>
    </div>
    <div class="di-actions">
      <span class="di-req-note"><b>*</b> champ obligatoire</span>
      <div style="display:flex;gap:12px">
        <button type="button" class="di-btn di-btn-ghost" onclick="diSubmit('brouillon')"><i class="ph-duotone ph-floppy-disk"></i> Enregistrer brouillon</button>
        <button type="button" class="di-btn di-btn-primary" onclick="diSubmit('soumettre')"><i class="ph-duotone ph-paper-plane-tilt"></i> Soumettre la demande</button>
      </div>
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

// Auto-remplissage agent : quand le demandeur sélectionne un agent dans la datalist,
// tous les champs correspondants présents dans le formulaire se remplissent automatiquement.
(function(){
  // Correspondance data-attribute → id du champ cible dans le formulaire
  var AGENT_FIELDS = {
    'email':       'f_agent_email',
    'telephone':   'f_agent_telephone',
    'fonction':    'f_agent_fonction',
    'matricule':   'f_agent_matricule',
    'departement': 'f_departement',
    'direction':   'f_direction',
    'site':        'f_site'
  };

  document.querySelectorAll('input[data-agentfill]').forEach(function(inp){
    var dl = document.getElementById(inp.getAttribute('list'));
    if(!dl) return;

    function findMatch(val){
      var opts = dl.options;
      for(var i = 0; i < opts.length; i++){
        if(opts[i].value === val) return opts[i];
      }
      return null;
    }

    function fillFromMatch(match){
      Object.keys(AGENT_FIELDS).forEach(function(attr){
        var el = document.getElementById(AGENT_FIELDS[attr]);
        if(!el) return;
        var val = match ? (match.dataset[attr] || '') : '';
        el.value = val;
        if(val){
          el.classList.add('di-auto');
          el.readOnly = true;
        } else {
          el.classList.remove('di-auto');
          el.readOnly = false;
        }
      });
    }

    inp.addEventListener('change', function(){
      var match = findMatch(inp.value.trim());
      fillFromMatch(match);
    });

    // Réinitialiser si l'utilisateur efface le champ
    inp.addEventListener('input', function(){
      if(!inp.value.trim()) fillFromMatch(null);
    });
  });
})();
</script>

<?php if ($sel === 'autorisation_absence'): ?>
<script>
// Auto-remplissage : Type de permission (jours entre parenthèses) + Du → jours, Au, reprise.
// La reprise saute au prochain jour ouvré (week-ends + jours fériés de Côte d'Ivoire).
(function(){
  var type  = document.getElementById('f_type_permission');
  var debut = document.getElementById('f_date_debut');
  var jours = document.getElementById('f_nb_jours');
  var fin   = document.getElementById('f_date_fin');
  var rep   = document.getElementById('f_date_reprise');
  if(!type || !debut || !jours || !fin || !rep) return;
  var autos = [jours, fin, rep];

  // Fériés islamiques (calendrier lunaire) : à confirmer/compléter chaque année selon le décret
  // officiel. Aïd el-Fitr, Tabaski, Maouloud. 2026 = dates officielles ; 2027 = estimations.
  var FERIES_ISLAM = {
    2026: ['2026-03-20', '2026-05-27', '2026-08-26'],
    2027: ['2027-03-10', '2027-05-16', '2027-08-15']
  };
  var cacheFeries = {};

  function parseISO(iso){ var p = iso.split('-'); return new Date(Date.UTC(+p[0], +p[1]-1, +p[2])); }
  function toISO(d){ return d.toISOString().slice(0, 10); }
  function addDays(iso, n){
    if(!iso) return '';
    var d = parseISO(iso);
    if(isNaN(d.getTime())) return '';
    d.setUTCDate(d.getUTCDate() + n);
    return toISO(d);
  }
  // Dimanche de Pâques (grégorien, algorithme de Computus)
  function paques(y){
    var a=y%19, b=Math.floor(y/100), c=y%100, d=Math.floor(b/4), e=b%4,
        f=Math.floor((b+8)/25), g=Math.floor((b-f+1)/3), h=(19*a+b-d-g+15)%30,
        i=Math.floor(c/4), k=c%4, l=(32+2*e+2*i-h-k)%7, m=Math.floor((a+11*h+22*l)/451),
        mo=Math.floor((h+l-7*m+114)/31), jr=((h+l-7*m+114)%31)+1;
    return new Date(Date.UTC(y, mo-1, jr));
  }
  function feriesDe(y){
    if(cacheFeries[y]) return cacheFeries[y];
    var s = {};
    // Fixes : jour de l'an, fête du travail, fête nationale, assomption, toussaint, paix, noël
    ['01-01','05-01','08-07','08-15','11-01','11-15','12-25'].forEach(function(md){ s[y+'-'+md]=1; });
    // Chrétiens mobiles : lundi de Pâques (+1), Ascension (+39), lundi de Pentecôte (+50)
    var p = paques(y);
    [1,39,50].forEach(function(off){ var d=new Date(p); d.setUTCDate(d.getUTCDate()+off); s[toISO(d)]=1; });
    (FERIES_ISLAM[y] || []).forEach(function(iso){ s[iso]=1; });
    cacheFeries[y] = s;
    return s;
  }
  function estChome(iso){
    var d = parseISO(iso), j = d.getUTCDay();
    if(j===0 || j===6) return true;            // dimanche / samedi
    return !!feriesDe(d.getUTCFullYear())[iso]; // jour férié
  }
  function prochainOuvre(iso){
    if(!iso) return '';
    for(var g=0; g<60 && estChome(iso); g++){ iso = addDays(iso, 1); }
    return iso;
  }
  function setAuto(on){
    autos.forEach(function(el){
      el.readOnly = on;
      el.classList.toggle('di-auto', on);
      el.style.pointerEvents = on ? 'none' : '';
      el.tabIndex = on ? -1 : 0;
    });
  }
  function selDays(){
    var opt = type.options[type.selectedIndex];
    var m = opt ? opt.text.match(/\((\d+)\s*j\)/i) : null;
    return m ? parseInt(m[1], 10) : null;
  }
  function apply(){
    var d = selDays();
    if(d){
      setAuto(true);
      jours.value = d;
      fin.value = debut.value ? addDays(debut.value, d - 1) : '';
      rep.value = debut.value ? prochainOuvre(addDays(debut.value, d)) : '';
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
