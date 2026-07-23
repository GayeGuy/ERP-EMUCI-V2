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
$champs_dispo = array_keys(di_champs_config());

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
  .di-actions{display:flex;gap:12px;justify-content:flex-end;margin-top:22px}
  .di-btn{padding:11px 22px;border:none;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer;font-family:inherit}
  .di-btn-primary{background:linear-gradient(135deg,#3B4FBE,#7C92FF);color:#fff}
  .di-btn-ghost{background:var(--input,#f0f4f8);color:var(--text,#2c3e50)}
  .di-alert{display:none;padding:12px 16px;border-radius:9px;font-size:13px;margin-bottom:16px}
  .di-alert.err{display:block;background:#fdf0ef;color:#e74c3c;border-left:3px solid #e74c3c}
</style>

<div class="di-wrap">
<?php if ($sel === '' || !in_array($sel, $champs_dispo, true)): ?>
  <h2 style="margin:0 0 6px">Nouvelle demande interne</h2>
  <p style="color:var(--muted,#7f8c8d);margin:0 0 22px">Choisissez le type de demande à soumettre.</p>
  <div class="di-types">
    <?php foreach ($types as $t):
        $dispo = in_array($t['code'], $champs_dispo, true);
        $href  = $dispo ? '?type=' . urlencode($t['code']) : '#'; ?>
      <a class="di-type-card <?= $dispo ? '' : 'soon' ?>" href="<?= h($href) ?>"<?= $dispo ? '' : ' onclick="return false"' ?>>
        <div class="ic"><i class="ph-duotone ph-file-text"></i></div>
        <h4><?= h($t['label']) ?></h4>
        <p><?= h($t['description']) ?></p>
        <?php if (!$dispo): ?><span class="di-badge-soon">Bientôt</span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
<?php else:
    $type   = di_type($sel);
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

<?php include __DIR__ . '/../templates/footer.php'; ?>
