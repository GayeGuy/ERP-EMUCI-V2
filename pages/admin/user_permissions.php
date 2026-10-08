<?php
// ============================================================
//  pages/admin/user_permissions.php  —  Exceptions de permissions
//  par compte individuel (au-delà du rôle)
//
//  Complète pages/admin/permissions.php (permissions par rôle, partagées
//  par tous les comptes du rôle) : ici, un administrateur peut dire "ce
//  compte précis n'a pas tel droit, même si son rôle l'autorise" (ou
//  l'inverse), sans toucher aux autres comptes du même rôle. Table
//  user_permissions (sql/migration_user_permissions_exceptions.sql),
//  consultée dans can() en tout dernier recours (includes/session.php).
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/notifications.php';

require_auth();
require_permission('users', 'can_update');

$user = current_user();

// Écran plus sensible que la grille par rôle (détails d'un compte précis) :
// réservé strictement au superadmin, dès l'affichage et pas seulement à la
// sauvegarde — même règle que save_permissions dans permissions.php, mais
// appliquée plus tôt.
if ($user['role_slug'] !== 'superadmin') {
    http_response_code(403);
    include __DIR__ . '/../../templates/403.php';
    exit;
}

require_once __DIR__ . '/../../includes/permissions_modules.php';

$target_id = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
$target = db_fetch_one(
    "SELECT u.id, u.nom, u.prenom, u.email, u.actif, u.role_id,
            r.nom AS role_nom, r.slug AS role_slug
     FROM users u JOIN roles r ON r.id = u.role_id
     WHERE u.id = ?",
    [$target_id]
);

// Cible protégée : même un superadmin ne doit pas poser d'exception sur un
// autre admin/superadmin — le bypass total de can() (includes/session.php)
// les ignore de toute façon, une ligne ici n'aurait jamais d'effet réel.
$cible_invalide = !$target || in_array($target['role_slug'], ['admin', 'superadmin']);

$page_title  = 'Permissions spécifiques';
$active_page = 'users';

// ============================================================
//  AJAX
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'save_user_permissions') {
        if ($cible_invalide)
            json_response(false, "Compte introuvable ou protégé : impossible d'y poser une exception.");

        $actions = ['can_create','can_read','can_update','can_delete','can_export'];

        db_begin();
        try {
            foreach (array_keys($modules) as $module) {
                $vals = [];
                $has_override = false;
                foreach ($actions as $a) {
                    // 3 valeurs possibles : '' (hérité/NULL), '0' (refusé),
                    // '1' (autorisé) — à la différence de save_permissions
                    // (2 états), '0' est une valeur explicite à conserver,
                    // pas une absence de valeur : !empty() serait faux ici.
                    $raw = $_POST["uperm_{$target_id}_{$module}_{$a}"] ?? '';
                    $vals[$a] = ($raw === '0' || $raw === '1') ? (int)$raw : null;
                    if ($vals[$a] !== null) $has_override = true;
                }
                if ($has_override) {
                    db_query(
                        "INSERT INTO user_permissions (user_id,module,can_create,can_read,can_update,can_delete,can_export,created_by,updated_at)
                         VALUES (?,?,?,?,?,?,?,?,NOW())
                         ON CONFLICT (user_id,module) DO UPDATE SET
                           can_create=EXCLUDED.can_create, can_read=EXCLUDED.can_read,
                           can_update=EXCLUDED.can_update, can_delete=EXCLUDED.can_delete,
                           can_export=EXCLUDED.can_export, updated_at=NOW()",
                        [$target_id, $module, $vals['can_create'], $vals['can_read'],
                         $vals['can_update'], $vals['can_delete'], $vals['can_export'], $user['id']]
                    );
                } else {
                    // Aucun droit surchargé pour ce module : repasser en
                    // héritage pur, pas de ligne 100% NULL qui ne sert à rien.
                    db_query("DELETE FROM user_permissions WHERE user_id=? AND module=?", [$target_id, $module]);
                }
            }
            audit_log($user['id'], 'UPDATE', 'users', $target_id,
                "Modification permissions individuelles compte ID:$target_id");
            db_commit();
            json_response(true, 'Permissions individuelles sauvegardées.');
        } catch (Exception $e) {
            db_rollback();
            json_response(false, 'Erreur : ' . $e->getMessage());
        }
    }

    json_response(false, 'Action inconnue.');
}

if ($cible_invalide) {
    include __DIR__ . '/../../templates/header.php';
    ?>
    <div class="alert alert-danger">
      <i class="ph ph-warning" aria-hidden="true"></i>
      Ce compte est introuvable, ou son rôle (Administrateur/Super Administrateur) a de toute façon un accès total non restreignable.
    </div>
    <a href="users.php" class="btn btn-secondary"><i class="ph ph-arrow-left" aria-hidden="true"></i> Retour aux utilisateurs</a>
    <?php
    include __DIR__ . '/../../templates/footer.php';
    exit;
}

// ============================================================
//  DONNÉES
// ============================================================
$actions = [
    'can_read'   => ['<i class="ph ph-eye" aria-hidden="true"></i>', 'Lire'],
    'can_create' => ['<i class="ph ph-plus" aria-hidden="true"></i>', 'Créer'],
    'can_update' => ['<i class="ph ph-pencil-simple" aria-hidden="true"></i>', 'Modifier'],
    'can_delete' => ['<i class="ph ph-trash" aria-hidden="true"></i>', 'Supprimer'],
    'can_export' => ['<i class="ph ph-download-simple" aria-hidden="true"></i>', 'Exporter'],
];

// Droits du rôle actuel du compte — pour afficher "Hérité : Autorisé/Refusé"
$role_perms = [];
foreach (db_fetch_all("SELECT * FROM permissions WHERE role_id=?", [$target['role_id']]) as $p) {
    $role_perms[$p['module']] = $p;
}

// Exceptions déjà posées pour ce compte
$overrides = [];
foreach (db_fetch_all("SELECT * FROM user_permissions WHERE user_id=?", [$target_id]) as $o) {
    $overrides[$o['module']] = $o;
}
$nb_overrides = 0;
foreach ($overrides as $o) {
    foreach (['can_create','can_read','can_update','can_delete','can_export'] as $a) {
        if ($o[$a] !== null) $nb_overrides++;
    }
}

$grp_defaut = (string) array_key_first(
    array_intersect_key($module_groupes, $modules_par_groupe));

include __DIR__ . '/../../templates/header.php';
?>
<style>
.uperm-tabs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px}
.uperm-tab{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;font-size:12.5px;font-weight:600;
  font-family:'Manrope',sans-serif;color:var(--text,#2c3e50);background:var(--card,#fff);
  border:1px solid var(--border);border-radius:20px;cursor:pointer;transition:background .15s,color .15s,border-color .15s}
.uperm-tab:hover{background:var(--lighter,#f0f4f8)}
.uperm-tab.active{background:var(--navy,#1E2B4A);color:#fff;border-color:var(--navy,#1E2B4A)}
.uperm-pane{display:none}
.uperm-pane.active{display:block}

.uperm-table-wrap{max-height:446px;overflow-y:auto}
.uperm-table{width:100%;border-collapse:separate;border-spacing:0}
.uperm-table thead{position:sticky;top:0;z-index:5}
.uperm-table th{padding:10px 14px;font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;text-align:left;background:var(--lighter,#f0f4f8);border-bottom:1px solid var(--border)}
.uperm-table th.center{text-align:center}
.uperm-table td{padding:10px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
.uperm-table tr:last-child td{border-bottom:none}
.uperm-table tr:hover td{background:var(--lighter)}

.module-cell{display:flex;align-items:center;gap:10px}
.module-icon{font-size:18px;width:24px;text-align:center}
.module-name{font-size:13.5px;font-weight:500}

.uperm-wrap{display:flex;flex-direction:column;align-items:center;gap:3px}
.uperm-seg{display:inline-flex;border:1px solid var(--border);border-radius:8px;overflow:hidden}
.useg{width:28px;height:26px;border:none;background:white;cursor:pointer;font-size:13px;color:var(--muted);
  display:flex;align-items:center;justify-content:center;transition:background .15s,color .15s}
.useg:not(:last-child){border-right:1px solid var(--border)}
.useg.inherit.on{background:var(--lighter,#f0f4f8);color:var(--text)}
.useg.allow.on{background:var(--success);color:white}
.useg.deny.on{background:var(--danger);color:white}
.uperm-hint{font-size:10.5px;color:var(--muted);white-space:nowrap}

.target-header{display:flex;align-items:center;gap:14px;padding:20px;background:linear-gradient(135deg,var(--navy),#1a3c5e);border-radius:12px;margin-bottom:20px;color:white}
.target-avatar{width:52px;height:52px;border-radius:14px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;flex-shrink:0}
.target-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800}
.target-sub{font-size:12px;opacity:.7;margin-top:3px}
</style>

<a href="users.php" style="display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--muted);text-decoration:none;margin-bottom:14px">
  <i class="ph ph-arrow-left" aria-hidden="true"></i> Retour aux utilisateurs
</a>

<div class="target-header">
  <div class="target-avatar"><?= strtoupper(substr($target['prenom'],0,1).substr($target['nom'],0,1)) ?></div>
  <div style="flex:1">
    <div class="target-title"><?= h($target['prenom'].' '.$target['nom']) ?></div>
    <div class="target-sub"><?= h($target['email']) ?> · Rôle : <?= h($target['role_nom']) ?><?= $nb_overrides ? ' · '.$nb_overrides.' exception(s) active(s)' : '' ?></div>
  </div>
  <button class="btn" style="background:var(--navy,#1E2B4A);color:white" onclick="saveUPerms()">
    <i class="ph ph-floppy-disk" aria-hidden="true"></i> Sauvegarder
  </button>
</div>

<div class="alert alert-info" style="margin-bottom:18px">
  <i class="ph ph-info" aria-hidden="true"></i>
  Par défaut, chaque droit est <strong>Hérité</strong> du rôle « <?= h($target['role_nom']) ?> ». Cliquez sur <strong>✓</strong> pour autoriser explicitement ce compte, ou <strong>✗</strong> pour lui refuser explicitement un droit — même si son rôle dit le contraire. Les autres comptes du même rôle ne sont jamais affectés.
</div>

<nav class="uperm-tabs" aria-label="Groupes de modules">
  <?php foreach($module_groupes as $gk=>[$gico,$glbl]):
    if (empty($modules_par_groupe[$gk])) continue;
    $gcount = count($modules_par_groupe[$gk]);
  ?>
  <button class="uperm-tab <?= $gk===$grp_defaut?'active':'' ?>" onclick="showUGrp('<?= $gk ?>',this)">
    <?= $gico ?> <?= h($glbl) ?><span style="opacity:.6;font-size:11px">(<?= $gcount ?>)</span>
  </button>
  <?php endforeach; ?>
</nav>

<?php foreach($module_groupes as $gk=>[$gico,$glbl]):
  if (empty($modules_par_groupe[$gk])) continue;
?>
<div class="card uperm-pane <?= $gk===$grp_defaut?'active':'' ?>" id="ugrp-<?= $gk ?>">
  <div class="uperm-table-wrap">
  <table class="uperm-table">
    <thead>
      <tr>
        <th style="width:220px"><?= $gico ?> <?= h($glbl) ?></th>
        <?php foreach($actions as $ak=>[$aico,$albl]): ?>
        <th class="center"><?= $aico ?> <?= $albl ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach($modules_par_groupe[$gk] as $mk=>[$mico,$mlbl]):
        $role_mod = $role_perms[$mk] ?? [];
        $ov_mod   = $overrides[$mk] ?? [];
      ?>
      <tr>
        <td>
          <div class="module-cell">
            <span class="module-icon"><?= $mico ?></span>
            <span class="module-name"><?= $mlbl ?></span>
          </div>
        </td>
        <?php foreach($actions as $ak=>[$aico,$albl]):
          $role_val = !empty($role_mod[$ak]);
          $ov_val   = array_key_exists($ak, $ov_mod) ? $ov_mod[$ak] : null;
          $state    = $ov_val === null ? '' : (string)(int)$ov_val;
          $name     = "uperm_{$target_id}_{$mk}_{$ak}";
        ?>
        <td>
          <div class="uperm-wrap">
            <div class="uperm-seg" id="<?= $name ?>" data-role="<?= $role_val?1:0 ?>">
              <button type="button" class="useg inherit <?= $state===''?'on':'' ?>" onclick="setUPerm('<?= $name ?>','')" title="Hérité du rôle (<?= $role_val?'Autorisé':'Refusé' ?>)">·</button>
              <button type="button" class="useg allow <?= $state==='1'?'on':'' ?>" onclick="setUPerm('<?= $name ?>','1')" title="Autorisé explicitement">✓</button>
              <button type="button" class="useg deny <?= $state==='0'?'on':'' ?>" onclick="setUPerm('<?= $name ?>','0')" title="Refusé explicitement">✗</button>
            </div>
            <input type="hidden" id="h_<?= $name ?>" name="<?= $name ?>" value="<?= $state ?>">
            <div class="uperm-hint" id="hint_<?= $name ?>" style="<?= $state!==''?'visibility:hidden':'' ?>">
              <?= $role_val?'Hérité : autorisé':'Hérité : refusé' ?>
            </div>
          </div>
        </td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endforeach; ?>

<div style="display:flex;justify-content:flex-end;margin-top:16px">
  <button class="btn btn-primary" onclick="saveUPerms()"><i class="ph ph-floppy-disk" aria-hidden="true"></i> Sauvegarder les permissions</button>
</div>

<script>
const U_TARGET_ID = <?= (int)$target_id ?>;
const U_MODULES   = <?= json_encode(array_keys($modules)) ?>;
const U_ACTIONS   = ['can_create','can_read','can_update','can_delete','can_export'];

function showUGrp(groupe, btn){
  document.querySelectorAll('.uperm-pane').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.uperm-tab').forEach(b=>b.classList.remove('active'));
  const target = document.getElementById('ugrp-'+groupe);
  if(target) target.classList.add('active');
  btn.classList.add('active');
}

function setUPerm(name, state){
  const seg = document.getElementById(name);
  if(!seg) return;
  seg.querySelectorAll('.useg').forEach(b=>b.classList.remove('on'));
  const sel = state==='' ? seg.querySelector('.inherit') : (state==='1' ? seg.querySelector('.allow') : seg.querySelector('.deny'));
  if(sel) sel.classList.add('on');
  document.getElementById('h_'+name).value = state;
  const hint = document.getElementById('hint_'+name);
  if(hint) hint.style.visibility = state==='' ? 'visible' : 'hidden';
}

function saveUPerms(){
  const fd = new FormData();
  fd.append('action','save_user_permissions');
  fd.append('user_id', U_TARGET_ID);
  U_MODULES.forEach(m=>U_ACTIONS.forEach(a=>{
    const hid = document.getElementById(`h_uperm_${U_TARGET_ID}_${m}_${a}`);
    if(hid) fd.append(`uperm_${U_TARGET_ID}_${m}_${a}`, hid.value);
  }));
  fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd})
    .then(r=>r.json())
    .then(d=>{ toast(d.message, d.success?'success':'danger'); if(d.success) setTimeout(()=>location.reload(),800); })
    .catch(()=>toast('Erreur réseau lors de la sauvegarde.','danger'));
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
