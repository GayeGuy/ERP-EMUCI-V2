<?php
// ============================================================
//  pages/admin/permissions.php  —  Gestion des permissions
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/notifications.php';

require_auth();
require_permission('users', 'can_update');

$user        = current_user();
$page_title  = 'Permissions & Rôles';
$active_page = 'permissions';

// ============================================================
//  AJAX
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // ── SAUVEGARDER PERMISSIONS D'UN RÔLE
    if ($action === 'save_permissions') {
        // Seul superadmin peut modifier les permissions
        if ($user['role_slug'] !== 'superadmin')
            json_response(false, 'Seul le Super Administrateur peut modifier les permissions.');

        $role_id = (int)($_POST['role_id'] ?? 0);
        // Empêcher modification du superadmin lui-même
        $role = db_fetch_one("SELECT slug FROM roles WHERE id=?", [$role_id]);
        if ($role && $role['slug'] === 'superadmin')
            json_response(false, 'Les permissions du Super Administrateur ne peuvent pas être modifiées.');

        $modules  = ['equipements','sites','consommables','users','rapports','nomenclatures','affectations','audit','receptions','bobines','inventaire_bobines','interventions','commandes','pmma'];
        $actions  = ['can_create','can_read','can_update','can_delete','can_export'];

        db_begin();
        try {
            foreach ($modules as $module) {
                $perms = [];
                foreach ($actions as $a) {
                    $key = "perm_{$role_id}_{$module}_{$a}";
                    $perms[$a] = !empty($_POST[$key]) ? 1 : 0;
                }
                // can_read toujours au moins 0 (pas de forçage)
                db_query(
                    "INSERT INTO permissions (role_id,module,can_create,can_read,can_update,can_delete,can_export)
                     VALUES (?,?,?,?,?,?,?)
                     ON CONFLICT (role_id,module) DO UPDATE SET
                       can_create=EXCLUDED.can_create, can_read=EXCLUDED.can_read,
                       can_update=EXCLUDED.can_update, can_delete=EXCLUDED.can_delete,
                       can_export=EXCLUDED.can_export",
                    [$role_id, $module, $perms['can_create'], $perms['can_read'],
                     $perms['can_update'], $perms['can_delete'], $perms['can_export']]
                );
            }
            audit_log($user['id'], 'UPDATE', 'users', $role_id,
                "Modification permissions rôle ID:$role_id");
            db_commit();
            json_response(true, 'Permissions sauvegardées.');
        } catch (Exception $e) {
            db_rollback();
            json_response(false, 'Erreur : ' . $e->getMessage());
        }
    }

    // ── CRÉER UN RÔLE PERSONNALISÉ
    if ($action === 'create_role') {
        if ($user['role_slug'] !== 'superadmin')
            json_response(false, 'Action réservée au Super Administrateur.');
        $nom  = trim($_POST['nom']  ?? '');
        $slug = strtolower(preg_replace('/[^a-z0-9_]/', '_', trim($_POST['slug'] ?? '')));
        $desc = trim($_POST['desc'] ?? '');
        if (!$nom || !$slug) json_response(false, 'Nom et slug obligatoires.');
        if (db_fetch_value("SELECT COUNT(*) FROM roles WHERE slug=?", [$slug]) > 0)
            json_response(false, "Le slug '$slug' existe déjà.");
        db_query("INSERT INTO roles (nom,slug,description) VALUES (?,?,?)", [$nom, $slug, $desc]);
        $id = (int)db_last_id();
        // Initialiser avec aucune permission
        $modules = ['equipements','sites','consommables','users','rapports','nomenclatures','affectations','audit','receptions','bobines','inventaire_bobines','interventions','commandes','pmma'];
        foreach ($modules as $m) {
            db_query("INSERT INTO permissions (role_id,module,can_read) VALUES (?,?,0)", [$id, $m]);
        }
        audit_log($user['id'], 'CREATE', 'users', $id, "Création rôle $nom");
        json_response(true, 'Rôle créé.', ['id' => $id]);
    }

    json_response(false, 'Action inconnue.');
}

// ============================================================
//  DONNÉES
// ============================================================
$roles   = db_fetch_all("SELECT * FROM roles ORDER BY id");
$modules = [
    'equipements'   => ['💻', 'Équipements'],
    'nomenclatures' => ['🏷️', 'Nomenclatures'],
    'sites'         => ['🏢', 'Sites'],
    'affectations'  => ['🔗', 'Affectations'],
    'consommables'  => ['🧴', 'Consommables'],
    'receptions'         => ['📦', 'Réceptions site'],
    'bobines'            => ['🎞️', 'Bobines & Opérations'],
    'inventaire_bobines' => ['📊', 'Inventaire bobines'],
    'interventions'      => ['🛠️', 'Interventions maintenance'],
    'commandes'          => ['📦', 'Commandes'],
    'pmma'               => ['🖨️', 'PMMA'],
    'rapports'      => ['📊', 'Rapports'],
    'users'         => ['👥', 'Utilisateurs'],
    'audit'         => ['📋', 'Journal d\'audit'],
];
$actions = [
    'can_read'   => ['👁', 'Lire'],
    'can_create' => ['➕', 'Créer'],
    'can_update' => ['✏️', 'Modifier'],
    'can_delete' => ['🗑', 'Supprimer'],
    'can_export' => ['📥', 'Exporter'],
];

// Charger toutes les permissions
$all_perms = [];
$rows = db_fetch_all("SELECT * FROM permissions");
foreach ($rows as $p) {
    $all_perms[$p['role_id']][$p['module']] = $p;
}

// Nb users par rôle
$users_by_role = array_column(
    db_fetch_all("SELECT role_id, COUNT(*) AS n FROM users WHERE actif=1 GROUP BY role_id"),
    'n', 'role_id'
);

include __DIR__ . '/../../templates/header.php';
?>
<style>
.perm-tabs{display:flex;gap:0;border-bottom:1px solid var(--border);margin-bottom:24px;overflow-x:auto}
.perm-tab{padding:12px 20px;font-size:13.5px;font-weight:500;color:var(--muted);cursor:pointer;border-bottom:2px solid transparent;background:none;border-top:none;border-left:none;border-right:none;white-space:nowrap;font-family:'DM Sans',sans-serif;transition:all .15s}
.perm-tab.active{color:var(--blue-mid, #1a56a0);border-bottom-color:var(--blue-mid, #1a56a0)}
.perm-pane{display:none}
.perm-pane.active{display:block}

.perm-table{width:100%;border-collapse:separate;border-spacing:0}
.perm-table th{padding:10px 14px;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;text-align:left;background:var(--lighter);border-bottom:1px solid var(--border)}
.perm-table th.center{text-align:center}
.perm-table td{padding:12px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
.perm-table tr:last-child td{border-bottom:none}
.perm-table tr:hover td{background:var(--lighter)}

.module-cell{display:flex;align-items:center;gap:10px}
.module-icon{font-size:18px;width:24px;text-align:center}
.module-name{font-size:13.5px;font-weight:500}

.perm-check-wrap{display:flex;justify-content:center}
.perm-toggle{
  width:40px;height:22px;border-radius:11px;background:var(--border);
  position:relative;cursor:pointer;transition:background .2s;border:none;
  flex-shrink:0;
}
.perm-toggle.on{background:var(--success)}
.perm-toggle.on.danger-perm{background:var(--danger)}
.perm-toggle::after{
  content:'';position:absolute;top:2px;left:2px;
  width:18px;height:18px;border-radius:50%;background:white;
  transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2);
}
.perm-toggle.on::after{left:20px}
.perm-toggle:disabled{opacity:.4;cursor:not-allowed}

.role-header{display:flex;align-items:center;gap:14px;padding:20px;background:linear-gradient(135deg,var(--navy),#1a3c5e);border-radius:12px;margin-bottom:20px;color:white}
.role-avatar{width:52px;height:52px;border-radius:14px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0}
.role-title{font-family:'Montserrat',sans-serif;font-size:18px;font-weight:800}
.role-sub{font-size:12px;opacity:.65;margin-top:3px}

.modal-overlay{display:none;position:fixed;inset:0;z-index:500;background:rgba(13,31,53,.5);backdrop-filter:blur(4px);align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:white;border-radius:16px;width:480px;max-width:95vw;max-height:92vh;overflow-y:auto;animation:mIn .25s cubic-bezier(.22,1,.36,1)}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.mhdr{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:white;z-index:10}
.mhdr h3{font-family:'Montserrat',sans-serif;font-size:17px;font-weight:700}
.mclose{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:none;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center}
.mbody{padding:24px}
.mfoot{padding:14px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px}
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <p style="font-size:13.5px;color:var(--muted);max-width:600px">
    Configurez les droits d'accès pour chaque rôle. Les modifications sont appliquées immédiatement pour tous les utilisateurs du rôle concerné.
  </p>
  <?php if($user['role_slug']==='superadmin'): ?>
  <button class="btn btn-secondary" onclick="document.getElementById('mRole').classList.add('open')">+ Nouveau rôle</button>
  <?php endif; ?>
</div>

<!-- TABS PAR RÔLE -->
<div class="perm-tabs" id="permTabs">
  <?php foreach($roles as $i=>$r): ?>
  <button class="perm-tab <?= $i===0?'active':'' ?>" onclick="showPerm('role-<?= $r['id'] ?>',this)">
    <?= h($r['nom']) ?>
    <span style="margin-left:6px;font-size:11px;opacity:.6">(<?= $users_by_role[$r['id']]??0 ?> user<?= ($users_by_role[$r['id']]??0)>1?'s':'' ?>)</span>
  </button>
  <?php endforeach; ?>
</div>

<?php foreach($roles as $r):
  $is_superadmin = $r['slug'] === 'superadmin';
  $can_edit      = $user['role_slug'] === 'superadmin' && !$is_superadmin;
  $role_icons    = ['superadmin'=>'👑','admin'=>'🛡️','gestionnaire'=>'⚙️','lecteur'=>'👁'];
?>
<div class="perm-pane <?= $r===reset($roles)?'active':'' ?>" id="role-<?= $r['id'] ?>">

  <!-- RÔLE HEADER -->
  <div class="role-header">
    <div class="role-avatar"><?= $role_icons[$r['slug']] ?? '👤' ?></div>
    <div style="flex:1">
      <div class="role-title"><?= h($r['nom']) ?></div>
      <div class="role-sub">
        <?= h($r['description'] ?: 'Aucune description') ?> ·
        <?= $users_by_role[$r['id']]??0 ?> utilisateur(s) actif(s)
      </div>
    </div>
    <?php if($is_superadmin): ?>
    <div style="background:rgba(255,255,255,.15);border-radius:8px;padding:8px 14px;font-size:12px;font-weight:600">
      🔒 Accès total — non modifiable
    </div>
    <?php elseif($can_edit): ?>
    <button class="btn" style="background:var(--blue-mid, #1a56a0);color:white" onclick="savePerms(<?= $r['id'] ?>)">
      💾 Sauvegarder
    </button>
    <?php else: ?>
    <div style="background:rgba(255,255,255,.1);border-radius:8px;padding:8px 14px;font-size:12px">
      Lecture seule
    </div>
    <?php endif; ?>
  </div>

  <?php if($is_superadmin): ?>
  <div class="alert alert-info">
    <span>👑</span> Le Super Administrateur a accès à toutes les fonctionnalités sans restriction. Ses permissions ne peuvent pas être modifiées.
  </div>

  <?php else: ?>
  <!-- TABLEAU DES PERMISSIONS -->
  <div class="card">
    <table class="perm-table">
      <thead>
        <tr>
          <th style="width:200px">Module</th>
          <?php foreach($actions as $ak=>[$aico,$albl]): ?>
          <th class="center"><?= $aico ?> <?= $albl ?></th>
          <?php endforeach; ?>
          <?php if($can_edit): ?>
          <th class="center">Tout cocher</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach($modules as $mk=>[$mico,$mlbl]):
          $perms = $all_perms[$r['id']][$mk] ?? [];
        ?>
        <tr>
          <td>
            <div class="module-cell">
              <span class="module-icon"><?= $mico ?></span>
              <span class="module-name"><?= $mlbl ?></span>
            </div>
          </td>
          <?php foreach($actions as $ak=>[$aico,$albl]):
            $val   = !empty($perms[$ak]);
            $isDel = $ak==='can_delete';
            $name  = "perm_{$r['id']}_{$mk}_{$ak}";
          ?>
          <td>
            <div class="perm-check-wrap">
              <button type="button"
                class="perm-toggle <?= $val?'on':'' ?> <?= $isDel&&$val?'danger-perm':'' ?>"
                id="<?= $name ?>"
                data-name="<?= $name ?>"
                data-val="<?= $val?1:0 ?>"
                onclick="<?= $can_edit?"togglePerm(this)":'void(0)' ?>"
                <?= !$can_edit?'disabled':'' ?>
                title="<?= $albl ?> — <?= $mlbl ?>">
              </button>
              <input type="hidden" id="h_<?= $name ?>" name="<?= $name ?>" value="<?= $val?1:0 ?>">
            </div>
          </td>
          <?php endforeach; ?>
          <?php if($can_edit): ?>
          <td style="text-align:center">
            <button class="btn btn-secondary btn-sm" onclick="toggleRow('<?= $r['id'] ?>','<?= $mk ?>')" title="Tout activer/désactiver">⇄</button>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if($can_edit): ?>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding:14px 18px;background:white;border:1px solid var(--border);border-radius:10px;flex-wrap:wrap;gap:10px">
    <div style="font-size:13px;color:var(--muted)">
      💡 Les modifications ne prennent effet qu'après avoir cliqué sur <strong>Sauvegarder</strong>.
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-secondary" onclick="allOff(<?= $r['id'] ?>)">🚫 Tout désactiver</button>
      <button class="btn btn-primary" onclick="savePerms(<?= $r['id'] ?>)">💾 Sauvegarder les permissions</button>
    </div>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- MODAL NOUVEAU RÔLE -->
<div class="modal-overlay" id="mRole">
  <div class="modal">
    <div class="mhdr"><h3>Nouveau rôle</h3><button class="mclose" onclick="document.getElementById('mRole').classList.remove('open')">✕</button></div>
    <div class="mbody">
      <div id="mRAlert"></div>
      <div class="form-group"><label>Nom du rôle *</label>
        <input type="text" class="form-control" id="rNom" placeholder="Ex: Superviseur">
      </div>
      <div class="form-group"><label>Slug * <span style="font-size:11px;color:var(--muted)">(identifiant unique, lettres/chiffres)</span></label>
        <input type="text" class="form-control" id="rSlug" placeholder="superviseur" oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9_]/g,'_')">
      </div>
      <div class="form-group"><label>Description</label>
        <textarea class="form-control" id="rDesc" rows="3" placeholder="Description du rôle…"></textarea>
      </div>
    </div>
    <div class="mfoot">
      <button class="btn btn-secondary" onclick="document.getElementById('mRole').classList.remove('open')">Annuler</button>
      <button class="btn btn-primary" onclick="saveRole()">💾 Créer le rôle</button>
    </div>
  </div>
</div>

<script>
function showPerm(id,btn){
  document.querySelectorAll('.perm-pane').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.perm-tab').forEach(b=>b.classList.remove('active'));
  document.getElementById(id).classList.add('active'); btn.classList.add('active');
}

function togglePerm(btn){
  const newVal = btn.dataset.val==='0'?1:0;
  btn.dataset.val=String(newVal);
  btn.classList.toggle('on',newVal===1);
  const isDel=btn.id.includes('_can_delete');
  btn.classList.toggle('danger-perm',newVal===1&&isDel);
  document.getElementById('h_'+btn.id).value=newVal;
}

function toggleRow(roleId, module){
  const actions=['can_read','can_create','can_update','can_delete','can_export'];
  const allOn=actions.every(a=>{
    const b=document.getElementById(`perm_${roleId}_${module}_${a}`);
    return b&&b.dataset.val==='1';
  });
  actions.forEach(a=>{
    const b=document.getElementById(`perm_${roleId}_${module}_${a}`);
    if(b){
      const newVal=allOn?0:1;
      b.dataset.val=String(newVal);
      b.classList.toggle('on',newVal===1);
      b.classList.toggle('danger-perm',newVal===1&&a==='can_delete');
      document.getElementById('h_'+b.id).value=newVal;
    }
  });
}

function allOff(roleId){
  if(!confirm('Désactiver TOUTES les permissions de ce rôle ?'))return;
  const modules=['equipements','nomenclatures','sites','affectations','consommables','receptions','bobines','inventaire_bobines','interventions','commandes','pmma','rapports','users','audit'];
  const actions=['can_read','can_create','can_update','can_delete','can_export'];
  modules.forEach(m=>actions.forEach(a=>{
    const b=document.getElementById(`perm_${roleId}_${m}_${a}`);
    if(b){b.dataset.val='0';b.classList.remove('on','danger-perm');document.getElementById('h_'+b.id).value=0;}
  }));
}

function savePerms(roleId){
  const modules=['equipements','nomenclatures','sites','affectations','consommables','receptions','bobines','inventaire_bobines','interventions','commandes','pmma','rapports','users','audit'];
  const actions=['can_read','can_create','can_update','can_delete','can_export'];
  const fd=new FormData();
  fd.append('action','save_permissions');
  fd.append('role_id',roleId);
  modules.forEach(m=>actions.forEach(a=>{
    const hid=document.getElementById(`h_perm_${roleId}_${m}_${a}`);
    if(hid)fd.append(`perm_${roleId}_${m}_${a}`,hid.value);
  }));
  fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd})
    .then(r=>r.json())
    .then(d=>toast(d.message,d.success?'success':'danger'));
}

function saveRole(){
  const fd=new FormData();
  fd.append('action','create_role');
  fd.append('nom',document.getElementById('rNom').value);
  fd.append('slug',document.getElementById('rSlug').value);
  fd.append('desc',document.getElementById('rDesc').value);
  fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd})
    .then(r=>r.json())
    .then(d=>{
      if(d.success){toast(d.message,'success');document.getElementById('mRole').classList.remove('open');setTimeout(()=>location.reload(),800);}
      else document.getElementById('mRAlert').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;
    });
}
document.getElementById('mRole').addEventListener('click',e=>{if(e.target===e.currentTarget)e.currentTarget.classList.remove('open');});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
