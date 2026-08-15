<?php
// ============================================================
//  pages/admin/delegations.php
//  Gestion des délégations — Superviseur Opération → Gestionnaire Opération
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_auth();

$user      = current_user();
$role_slug = $user['role_slug'] ?? '';
$is_admin_role = in_array($role_slug, ['admin', 'superadmin'], true);
$page_title  = 'Délégations';
$active_page = 'delegations';

require_permission('delegations', 'can_read');

// ── TÂCHES DÉLÉGABLES
$modules_disponibles = [
    'validation_pj'       => '✅ Validation des Points Journaliers',
    'bobines'             => '🎞️ Gestion des Bobines',
    'inventaire_bobines'  => '📋 Inventaire Bobines',
    'receptions'          => '📦 Réceptions',
    'sites'               => '📍 Suivi des Sites',
    'affectations'        => '🔄 Affectations équipements',
    'rapports'            => '📊 Rapports & Analyses',
    'rivets'              => '🔩 Stock Rivets',
];

// ── AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $gest_id  = (int)($_POST['gestionnaire_id'] ?? 0);
        $module   = trim($_POST['module'] ?? '');
        $actif    = (int)($_POST['actif'] ?? 0);

        if (!isset($modules_disponibles[$module])) json_response(false, 'Module invalide.');

        // Vérifier que c'est bien un gestionnaire opération
        $gest = db_fetch_one("SELECT u.id, CONCAT(u.prenom,' ',u.nom) AS nom FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND r.slug='gestionnaire_operation' AND u.actif=1", [$gest_id]);
        if (!$gest) json_response(false, 'Gestionnaire introuvable.');

        if ($actif) {
            db_query("INSERT INTO delegations (superviseur_id, gestionnaire_id, module, libelle, actif) VALUES (?,?,?,?,1)
                      ON CONFLICT (superviseur_id,gestionnaire_id,module) DO UPDATE SET actif=1",
                [$user['id'], $gest_id, $module, $modules_disponibles[$module]]);
            json_response(true, "Tâche '{$modules_disponibles[$module]}' déléguée à {$gest['nom']}.");
        } else {
            db_query("UPDATE delegations SET actif=0 WHERE superviseur_id=? AND gestionnaire_id=? AND module=?",
                [$user['id'], $gest_id, $module]);
            json_response(true, "Délégation retirée.");
        }
    }

    // ── Responsables des sessions d'inventaire (n° 19 réunion ERP) —
    //    module réservé à l'admin/superadmin, délégable à n'importe quel
    //    utilisateur actif (pas seulement Gestionnaire Opération). Table
    //    delegations réutilisée, sans le filtre de rôle du bloc ci-dessus ;
    //    une action séparée pour ne pas fragiliser la délégation existante.
    if ($action === 'ajouter_responsable_session') {
        if (!$is_admin_role) json_response(false, 'Accès réservé aux administrateurs.');
        $cible_id = (int)($_POST['user_id'] ?? 0);
        $cible = db_fetch_one("SELECT id, CONCAT(prenom,' ',nom) AS nom FROM users WHERE id=? AND actif=1", [$cible_id]);
        if (!$cible) json_response(false, 'Utilisateur introuvable.');
        db_query("INSERT INTO delegations (superviseur_id, gestionnaire_id, module, libelle, actif) VALUES (?,?,?,?,1)
                  ON CONFLICT (superviseur_id,gestionnaire_id,module) DO UPDATE SET actif=1",
            [$user['id'], $cible_id, 'inventaire_sessions', 'Responsable sessions inventaire']);
        json_response(true, "{$cible['nom']} peut maintenant ouvrir et clôturer des sessions d'inventaire.");
    }

    if ($action === 'retirer_responsable_session') {
        if (!$is_admin_role) json_response(false, 'Accès réservé aux administrateurs.');
        $cible_id = (int)($_POST['user_id'] ?? 0);
        // Pas de filtre sur superviseur_id : la liste des responsables est
        // partagée entre admins, n'importe lequel peut retirer un accès
        // qu'un autre a accordé.
        db_query("UPDATE delegations SET actif=0 WHERE gestionnaire_id=? AND module='inventaire_sessions'", [$cible_id]);
        json_response(true, 'Accès retiré.');
    }

    json_response(false, 'Action inconnue.');
}

// ── DONNÉES
$gestionnaires = db_fetch_all(
    "SELECT u.id, u.prenom, u.nom, u.email, s.nom AS site_nom
     FROM users u JOIN roles r ON r.id=u.role_id
     LEFT JOIN sites s ON s.id=u.site_id
     WHERE r.slug='gestionnaire_operation' AND u.actif=1
     ORDER BY u.nom"
);

// Délégations actuelles
$delegations_actives = db_fetch_all(
    "SELECT d.gestionnaire_id, d.module FROM delegations d WHERE d.superviseur_id=? AND d.actif=1",
    [$user['id']]
);
$deleg_map = [];
foreach ($delegations_actives as $d) {
    $deleg_map[$d['gestionnaire_id']][$d['module']] = true;
}

// Responsables des sessions d'inventaire — visible seulement des admins
$responsables_session = [];
$utilisateurs_delegables = [];
if ($is_admin_role) {
    $responsables_session = db_fetch_all(
        "SELECT u.id, u.prenom, u.nom, r.nom AS role_nom
         FROM delegations d
         JOIN users u ON u.id = d.gestionnaire_id
         JOIN roles r ON r.id = u.role_id
         WHERE d.module = 'inventaire_sessions' AND d.actif = 1 AND u.actif = 1
         ORDER BY u.nom"
    );
    $deja_delegue = array_column($responsables_session, 'id');
    $utilisateurs_delegables = db_fetch_all(
        "SELECT u.id, u.prenom, u.nom, r.nom AS role_nom
         FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.actif = 1 AND u.id != ?
         " . (!empty($deja_delegue) ? "AND u.id NOT IN (" . implode(',', array_map('intval', $deja_delegue)) . ")" : "") . "
         ORDER BY u.nom",
        [$user['id']]
    );
}

include __DIR__ . '/../../templates/header.php';
?>
<style>
.deleg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(420px,1fr));gap:20px}
.deleg-card{background:white;border-radius:16px;border:1px solid var(--border);padding:0;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.deleg-head{background:var(--navy);padding:16px 20px;display:flex;align-items:center;gap:12px}
/* Fond uni navy (14:1 avec du blanc). Le dégradé précédent utilisait
   var(--sky), qui n'est definie nulle part : la déclaration entière
   devenait invalide, le fond disparaissait et les initiales blanches
   restaient invisibles. */
.deleg-avatar{width:40px;height:40px;border-radius:50%;background:var(--navy,#1E2B4A);display:flex;align-items:center;justify-content:center;font-weight:800;color:white;font-size:15px}
.deleg-name{color:white;font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:700}
.deleg-site{color:#94c2d4;font-size:11px;margin-top:2px}
.deleg-body{padding:16px 20px}
.task-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--border)}
.task-row:last-child{border-bottom:none}
.task-label{font-size:13px;color:var(--navy);font-weight:500}
.toggle-wrap{position:relative;width:44px;height:24px;cursor:pointer}
.toggle-wrap input{opacity:0;width:0;height:0;position:absolute}
.toggle-track{position:absolute;inset:0;background:#ccc;border-radius:12px;transition:.2s}
.toggle-thumb{position:absolute;top:3px;left:3px;width:18px;height:18px;background:white;border-radius:50%;transition:.2s;box-shadow:0 1px 4px rgba(0,0,0,.2)}
.toggle-wrap input:checked ~ .toggle-track{background:var(--success)}
.toggle-wrap input:checked ~ .toggle-thumb{transform:translateX(20px)}
</style>

<div style="margin-bottom:20px">
  <h2 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;color:var(--navy)">🔗 Gestion des Délégations</h2>
  <p style="font-size:13px;color:var(--muted);margin-top:4px">Attribuez des tâches à votre Gestionnaire Opération. Les modifications prennent effet immédiatement.</p>
</div>

<?php if(empty($gestionnaires)): ?>
<div class="card"><div class="card-body" style="text-align:center;padding:60px;color:var(--muted)">
  <div style="font-size:48px;margin-bottom:16px">👤</div>
  <div style="font-size:15px;font-weight:600;margin-bottom:8px">Aucun Gestionnaire Opération actif</div>
  <div style="font-size:13px">Créez un compte avec le profil "Gestionnaire Opération" depuis l'administration.</div>
</div></div>
<?php else: ?>
<div class="deleg-grid">
  <?php foreach($gestionnaires as $g):
    $initiales = strtoupper(substr($g['prenom'],0,1).substr($g['nom'],0,1));
    $actifs_count = count($deleg_map[$g['id']] ?? []);
  ?>
  <div class="deleg-card">
    <div class="deleg-head">
      <div class="deleg-avatar"><?= $initiales ?></div>
      <div>
        <div class="deleg-name"><?= h($g['prenom'].' '.$g['nom']) ?></div>
        <div class="deleg-site"><?= $g['site_nom'] ? h($g['site_nom']) : 'Tous sites' ?> · <?= $actifs_count ?> tâche(s) déléguée(s)</div>
      </div>
    </div>
    <div class="deleg-body">
      <?php foreach($modules_disponibles as $mod => $libelle):
        $checked = !empty($deleg_map[$g['id']][$mod]);
      ?>
      <div class="task-row">
        <span class="task-label"><?= $libelle ?></span>
        <label class="toggle-wrap">
          <input type="checkbox" <?= $checked?'checked':'' ?>
                 onchange="toggle(<?= $g['id'] ?>,'<?= $mod ?>',this.checked)">
          <span class="toggle-track"></span>
          <span class="toggle-thumb"></span>
        </label>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($is_admin_role): ?>
<div style="margin-top:36px">
  <h2 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;color:var(--navy)">📋 Responsables des sessions d'inventaire</h2>
  <p style="font-size:13px;color:var(--muted);margin-top:4px">
    Par défaut, seul un administrateur peut ouvrir et clôturer une session d'inventaire mensuel.
    Déléguez ce droit à la personne de votre choix, quel que soit son profil.
  </p>
</div>
<div class="card" style="margin-top:14px">
  <div class="card-body">
    <div style="display:flex;gap:10px;align-items:flex-end;margin-bottom:18px;flex-wrap:wrap">
      <div class="form-group" style="flex:1;min-width:220px;margin-bottom:0">
        <label for="selResponsable">Ajouter un responsable</label>
        <select class="form-control" id="selResponsable">
          <option value="">— Choisir un utilisateur —</option>
          <?php foreach ($utilisateurs_delegables as $u): ?>
          <option value="<?= $u['id'] ?>"><?= h($u['prenom'].' '.$u['nom']) ?> — <?= h($u['role_nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-primary" onclick="ajouterResponsable()">+ Déléguer</button>
    </div>

    <?php if (empty($responsables_session)): ?>
    <p style="font-size:13px;color:var(--muted);text-align:center;padding:20px 0">Aucune délégation active — seuls les administrateurs peuvent gérer les sessions.</p>
    <?php else: ?>
    <table style="width:100%;border-collapse:collapse">
      <thead><tr style="border-bottom:1.5px solid var(--border)">
        <th style="text-align:left;padding:8px 10px;font-size:11px;color:var(--muted);text-transform:uppercase">Utilisateur</th>
        <th style="text-align:left;padding:8px 10px;font-size:11px;color:var(--muted);text-transform:uppercase">Profil</th>
        <th style="width:80px"></th>
      </tr></thead>
      <tbody id="tblResponsables">
        <?php foreach ($responsables_session as $r): ?>
        <tr id="resp-row-<?= $r['id'] ?>" style="border-bottom:1px solid var(--border)">
          <td style="padding:9px 10px;font-weight:600"><?= h($r['prenom'].' '.$r['nom']) ?></td>
          <td style="padding:9px 10px;font-size:13px;color:var(--muted)"><?= h($r['role_nom']) ?></td>
          <td style="padding:9px 10px;text-align:right">
            <button class="btn btn-secondary btn-sm" onclick="retirerResponsable(<?= $r['id'] ?>)">Retirer</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<script>
function ap(d){return fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(d)}).then(r=>r.json());}
function toast(m,t='success'){let el=document.getElementById('toast-live');if(!el){el=document.createElement('div');el.id='toast-live';el.setAttribute('role','status');el.setAttribute('aria-live','polite');el.setAttribute('aria-atomic','true');document.body.appendChild(el);}clearTimeout(el._hideTimer);el.style.cssText=`position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:600;background:${t==='success'?'#27ae60':'#e74c3c'};color:white;box-shadow:0 4px 20px rgba(0,0,0,.15)`;el.textContent=m;el._hideTimer=setTimeout(()=>{el.style.display='none';},3000);}

function toggle(gestId, module, actif) {
  ap({action:'toggle', gestionnaire_id:gestId, module, actif:actif?1:0}).then(d=>{
    if(d.success) toast(d.message);
    else { toast(d.message,'error'); location.reload(); }
  });
}

function ajouterResponsable(){
  const sel = document.getElementById('selResponsable');
  if(!sel.value){toast('Choisissez un utilisateur.','error');return;}
  ap({action:'ajouter_responsable_session', user_id:sel.value}).then(d=>{
    toast(d.message, d.success?'success':'error');
    if(d.success) setTimeout(()=>location.reload(), 600);
  });
}
function retirerResponsable(userId){
  if(!confirm('Retirer ce responsable des sessions d\'inventaire ?')) return;
  ap({action:'retirer_responsable_session', user_id:userId}).then(d=>{
    toast(d.message, d.success?'success':'error');
    if(d.success){ const row=document.getElementById('resp-row-'+userId); if(row) row.remove(); }
  });
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
