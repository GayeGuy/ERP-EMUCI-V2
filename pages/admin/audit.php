<?php
// ============================================================
//  pages/admin/audit.php  —  Journal d'audit complet
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/notifications.php';

require_auth();
require_permission('audit', 'can_read');

$user        = current_user();
$page_title  = 'Journal d\'audit';
$active_page = 'audit';

// ============================================================
//  AJAX : détail d'une entrée
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $id  = (int)($_POST['id'] ?? 0);
    $row = db_fetch_one(
        "SELECT al.*, CONCAT(u.prenom,' ',u.nom) AS user_nom, u.email AS user_email
         FROM audit_log al LEFT JOIN users u ON u.id=al.user_id
         WHERE al.id=?", [$id]
    );
    if (!$row) json_response(false, 'Introuvable.');
    json_response(true, '', $row);
}

// ============================================================
//  LISIBILITÉ — le journal parlait en codes techniques
// ============================================================
//  Les colonnes Action et Module affichaient les valeurs brutes de la base
//  (CREATE, op_bobines…). Elles sont traduites ici, une seule fois, pour la
//  table comme pour les filtres.

function audit_action_label(string $a): string {
    return [
        'CREATE'   => 'Création',
        'UPDATE'   => 'Modification',
        'DELETE'   => 'Suppression',
        'LOGIN'    => 'Connexion',
        'LOGOUT'   => 'Déconnexion',
        'EXPORT'   => 'Export',
        'TRANSFER' => 'Transfert',
        'READ'     => 'Consultation',
    ][$a] ?? $a;
}

function audit_module_label(string $m): string {
    if ($m === '') return '—';
    return ucfirst(str_replace('_', ' ', $m));
}

/** « Aujourd'hui », « Hier », sinon la date en clair. */
function audit_jour_label(string $date): string {
    $j = date('Y-m-d', strtotime($date));
    if ($j === date('Y-m-d'))                       return "Aujourd'hui";
    if ($j === date('Y-m-d', strtotime('-1 day')))  return 'Hier';
    return fmt_date($j);
}

// ============================================================
//  FILTRES & PAGINATION
// ============================================================
$page     = max(1, (int)($_GET['page']      ?? 1));
// Nombre de lignes par page, choisi par l'utilisateur (valeurs autorisées uniquement).
$per_page_choix = [10, 20, 50, 100];
$per_page = (int)($_GET['per_page'] ?? 50);
if (!in_array($per_page, $per_page_choix, true)) $per_page = 50;
$search   = trim($_GET['q']                 ?? '');
$f_user   = (int)($_GET['user_id']          ?? 0);
$f_module = trim($_GET['module']            ?? '');
$f_action = trim($_GET['action_type']       ?? '');
$f_from   = trim($_GET['date_from']         ?? '');
$f_to     = trim($_GET['date_to']           ?? '');

$where  = ['1=1'];
$params = [];
if ($search)   { $where[] = 'al.description ILIKE ?'; $params[] = "%$search%"; }
if ($f_user)   { $where[] = 'al.user_id=?';           $params[] = $f_user; }
if ($f_module) { $where[] = 'al.module=?';             $params[] = $f_module; }
if ($f_action) { $where[] = 'al.action=?';             $params[] = $f_action; }
if ($f_from)   { $where[] = 'al.created_at >= ?';      $params[] = $f_from.' 00:00:00'; }
if ($f_to)     { $where[] = 'al.created_at <= ?';      $params[] = $f_to.' 23:59:59'; }

$wsql   = implode(' AND ', $where);
$total  = (int)db_fetch_value("SELECT COUNT(*) FROM audit_log al WHERE $wsql", $params);
$offset = ($page - 1) * $per_page;

$logs = db_fetch_all(
    "SELECT al.id, al.action, al.module, al.description, al.created_at,
            al.ip_address, al.entite_id,
            al.ancienne_valeur IS NOT NULL AS has_old,
            al.nouvelle_valeur IS NOT NULL AS has_new,
            CONCAT(u.prenom,' ',u.nom) AS user_nom,
            u.email AS user_email,
            r.slug AS role_slug
     FROM audit_log al
     LEFT JOIN users u ON u.id=al.user_id
     LEFT JOIN roles r ON r.id=u.role_id
     WHERE $wsql
     ORDER BY al.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$per_page, $offset])
);

// Pour les filtres
$users_list   = db_fetch_all("SELECT id,prenom,nom FROM users ORDER BY prenom");
$modules_list = db_fetch_all("SELECT DISTINCT module FROM audit_log ORDER BY module");
$actions_list = ['CREATE','READ','UPDATE','DELETE','LOGIN','LOGOUT','EXPORT','TRANSFER'];

// Stats rapides
$stats = db_fetch_all(
    "SELECT action, COUNT(*) AS n FROM audit_log
     WHERE created_at >= (CURRENT_DATE - INTERVAL '7 DAY')
     GROUP BY action ORDER BY n DESC"
);
$stats_map = array_column($stats, 'n', 'action');

include __DIR__ . '/../../templates/header.php';
?>
<style>
/* Libellés en clair depuis le lot 2 : les mettre en capitales les rendrait
   moins lisibles que les codes qu'ils remplacent. */
.audit-action-badge{font-size:10.5px;font-weight:700;padding:3px 8px;border-radius:5px;letter-spacing:.2px;white-space:nowrap}
.audit-action-badge.CREATE   {background:#d5f5e3;color:#1e8449}
.audit-action-badge.UPDATE   {background:#d6eaf8;color:#1a5276}
.audit-action-badge.DELETE   {background:#fdedec;color:#922b21}
.audit-action-badge.LOGIN    {background:#fef9e7;color:#9a7d0a}
.audit-action-badge.LOGOUT   {background:#eaecee;color:#424949}
.audit-action-badge.EXPORT   {background:#faf5ff;color:#6b21a8}
.audit-action-badge.TRANSFER {background:#e8f8f5;color:#1a7a5e}
.audit-action-badge.READ     {background:#f0f4f8;color:#475569}

/* En-tête maintenu visible : au-delà de vingt lignes on ne savait plus
   quelle colonne on lisait. */
.table-wrap{max-height:70vh;overflow-y:auto}
.table-wrap thead th{position:sticky;top:0;z-index:2;background:var(--navy,#0d1f35);color:white}

/* Ligne de séparation entre deux journées. */
.audit-jour td{background:var(--lighter,#f0f4f8);font-size:11px;font-weight:700;
  text-transform:uppercase;letter-spacing:.6px;color:var(--muted);padding:6px 12px}

/* La description tient sur deux lignes plutôt que d'être coupée net. */
.audit-desc{flex:1;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;
  overflow:hidden;line-height:1.35}

.role-badge{font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px}
.role-badge.superadmin{background:#fdf0ef;color:var(--danger-d)}
.role-badge.admin{background:#fef9e7;color:var(--warning-d)}
.role-badge.gestionnaire{background:#d6eaf8;color:#1a5276}
.role-badge.lecteur{background:#eaecee;color:#424949}

.fsel{padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;background:white;cursor:pointer;outline:none;font-family:'Manrope',sans-serif}
.fsel:focus{border-color:var(--blue-mid, #1a56a0)}

.diff-block{background:var(--lighter);border-radius:8px;padding:12px;font-size:12px;font-family:monospace;max-height:200px;overflow-y:auto;white-space:pre-wrap;word-break:break-all}
.diff-block.old{border-left:3px solid var(--danger)}
.diff-block.new{border-left:3px solid var(--success)}

.modal-overlay{display:none;position:fixed;inset:0;z-index:500;background:rgba(13,31,53,.5);backdrop-filter:blur(4px);align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:white;border-radius:16px;width:680px;max-width:95vw;max-height:92vh;overflow-y:auto;animation:mIn .25s cubic-bezier(.22,1,.36,1)}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.mhdr{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:white;z-index:10}
.mhdr h3{font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:700}
.mclose{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:none;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center}
.mbody{padding:24px}

/* timeline dot */
.tl-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:5px}
.tl-dot.CREATE{background:var(--success)}
.tl-dot.UPDATE{background:#2980b9}
.tl-dot.DELETE{background:var(--danger)}
.tl-dot.LOGIN{background:var(--warning)}
.tl-dot.LOGOUT{background:var(--muted)}
.tl-dot.EXPORT{background:#8e44ad}
.tl-dot.TRANSFER{background:#16a085}
.tl-dot.READ{background:#cbd5e1}
</style>

<!-- STATS 7 JOURS -->
<div style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap">
  <?php foreach($actions_list as $a):
    $n=$stats_map[$a]??0; if(!$n)continue; ?>
  <div style="padding:6px 12px;background:white;border:1px solid var(--border);border-radius:20px;display:flex;align-items:center;gap:6px">
    <span class="audit-action-badge <?= $a ?>"><?= h(audit_action_label($a)) ?></span>
    <strong style="font-size:13px"><?= $n ?></strong>
    <span style="font-size:11px;color:var(--muted)">7j</span>
  </div>
  <?php endforeach; ?>
  <div style="padding:6px 12px;background:var(--lighter);border-radius:20px;font-size:12.5px;color:var(--muted)">
    Total : <strong><?= fmt_number($total) ?></strong> entrées
  </div>
</div>

<!-- FILTRES -->
<form method="GET" style="background:white;border:1px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:18px">
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <div style="position:relative;flex:1;min-width:200px">
      <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted)">🔍</span>
      <input type="text" name="q" value="<?= h($search) ?>" aria-label="Rechercher dans les descriptions"
             placeholder="Rechercher dans les descriptions…"
             style="width:100%;padding:9px 14px 9px 36px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;outline:none;font-family:'Manrope',sans-serif">
    </div>
    <select name="user_id" class="fsel" aria-label="Filtrer par utilisateur" onchange="this.form.submit()">
      <option value="0">Tous les utilisateurs</option>
      <?php foreach($users_list as $u): ?>
      <option value="<?= $u['id'] ?>" <?= $f_user===$u['id']?'selected':'' ?>><?= h($u['prenom'].' '.$u['nom']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="module" class="fsel" aria-label="Filtrer par module" onchange="this.form.submit()">
      <option value="">Tous modules</option>
      <?php foreach($modules_list as $m): ?>
      <option value="<?= h($m['module']) ?>" <?= $f_module===$m['module']?'selected':'' ?>><?= h(audit_module_label($m['module'])) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="action_type" class="fsel" aria-label="Filtrer par type d'action" onchange="this.form.submit()">
      <option value="">Toutes actions</option>
      <?php foreach($actions_list as $a): ?>
      <option value="<?= $a ?>" <?= $f_action===$a?'selected':'' ?>><?= h(audit_action_label($a)) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" class="fsel" value="<?= h($f_from) ?>" title="Date de début" onchange="this.form.submit()">
    <input type="date" name="date_to"   class="fsel" value="<?= h($f_to) ?>"   title="Date de fin"   onchange="this.form.submit()">
    <select name="per_page" class="fsel" title="Lignes par page" onchange="this.form.submit()">
      <?php foreach($per_page_choix as $pp): ?>
      <option value="<?= $pp ?>" <?= $per_page===$pp?'selected':'' ?>><?= $pp ?> lignes</option>
      <?php endforeach; ?>
    </select>
    <?php if($search||$f_user||$f_module||$f_action||$f_from||$f_to): ?>
    <a href="audit.php" style="padding:9px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;background:white;text-decoration:none;color:var(--text);white-space:nowrap">✕ Reset</a>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
    <?php if(can('audit','can_export')): ?>
    <a href="<?= APP_URL ?>/api/export.php?type=audit" class="btn btn-secondary btn-sm" style="white-space:nowrap">📥 Excel</a>
    <?php endif; ?>
  </div>
</form>

<!-- JOURNAL -->
<div class="card">
  <div class="card-header">
    <h3>📋 Journal d'audit <span style="font-size:13px;font-weight:400;color:var(--muted)">(<?= fmt_number($total) ?> entrées)</span></h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th style="width:140px">Date / Heure</th>
        <th>Utilisateur</th>
        <th style="width:90px;text-align:center">Action</th>
        <th style="width:110px">Module</th>
        <th>Description</th>
        <th style="width:110px">IP</th>
        <th style="width:60px;text-align:center">Détail</th>
      </tr></thead>
      <tbody>
      <?php if(empty($logs)): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--muted)">Aucune entrée trouvée.</td></tr>
      <?php else: $jour_courant = null; foreach($logs as $l): ?>
        <?php
          // Une ligne de séparation par journée : sans elle, 100 lignes
          // d'horodatages se lisent comme un mur.
          $jour = date('Y-m-d', strtotime($l['created_at']));
          if ($jour !== $jour_courant): $jour_courant = $jour; ?>
        <tr class="audit-jour"><td colspan="7"><?= h(audit_jour_label($l['created_at'])) ?></td></tr>
        <?php endif; ?>
        <tr>
          <td style="white-space:nowrap">
            <div style="font-size:12.5px;font-weight:600"><?= date('d/m/Y', strtotime($l['created_at'])) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= date('H:i:s', strtotime($l['created_at'])) ?></div>
          </td>
          <td>
            <?php if($l['user_nom']): ?>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:28px;height:28px;border-radius:50%;background:var(--navy,#1E2B4A);display:flex;align-items:center;justify-content:center;color:white;font-size:10px;font-weight:700;flex-shrink:0">
                <?= strtoupper(substr($l['user_nom'],0,2)) ?>
              </div>
              <div>
                <div style="font-size:12.5px;font-weight:500"><?= h($l['user_nom']) ?></div>
                <?php if($l['role_slug']): ?><span class="role-badge <?= $l['role_slug'] ?>"><?= $l['role_slug'] ?></span><?php endif; ?>
              </div>
            </div>
            <?php else: ?>
            <span style="font-size:12px;color:var(--muted)">Système</span>
            <?php endif; ?>
          </td>
          <td style="text-align:center">
            <span class="audit-action-badge <?= $l['action'] ?>"><?= h(audit_action_label($l['action'])) ?></span>
          </td>
          <td style="font-size:12px;color:var(--muted)"><?= h(audit_module_label($l['module'])) ?><?= $l['entite_id']?" #".$l['entite_id']:'' ?></td>
          <td style="font-size:13px;max-width:340px">
            <div style="display:flex;align-items:flex-start;gap:8px">
              <div class="tl-dot <?= $l['action'] ?>"></div>
              <span class="audit-desc" title="<?= h($l['description']) ?>"><?= h($l['description']) ?></span>
            </div>
          </td>
          <td style="font-size:11.5px;color:var(--muted);font-family:monospace"><?= h($l['ip_address']) ?></td>
          <td style="text-align:center">
            <?php if($l['has_old']||$l['has_new']): ?>
            <button class="btn btn-secondary btn-sm" onclick="viewLog(<?= $l['id'] ?>)" title="Voir les modifications">🔍</button>
            <?php else: ?>
            <span style="color:var(--border)">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?= pagination($total,$per_page,$page,'audit.php?'.http_build_query(['q'=>$search,'user_id'=>$f_user,'module'=>$f_module,'action_type'=>$f_action,'date_from'=>$f_from,'date_to'=>$f_to,'per_page'=>$per_page])) ?>
</div>

<!-- MODAL DÉTAIL -->
<div class="modal-overlay" id="mLog">
  <div class="modal">
    <div class="mhdr"><h3>🔍 Détail de l'action</h3><button class="mclose" onclick="document.getElementById('mLog').classList.remove('open')">✕</button></div>
    <div class="mbody" id="logB"></div>
  </div>
</div>

<script>
// Mêmes libellés que côté PHP, pour la fenêtre de détail.
const ACTION_LABELS = <?= json_encode(array_combine(
    ['CREATE','UPDATE','DELETE','LOGIN','LOGOUT','EXPORT','TRANSFER','READ'],
    array_map('audit_action_label', ['CREATE','UPDATE','DELETE','LOGIN','LOGOUT','EXPORT','TRANSFER','READ'])
), JSON_UNESCAPED_UNICODE) ?>;
function viewLog(id){
  document.getElementById('mLog').classList.add('open');
  document.getElementById('logB').innerHTML='<div style="text-align:center;padding:40px;color:var(--muted)">Chargement…</div>';
  const fd=new FormData(); fd.append('id',id);
  fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd})
    .then(r=>r.json()).then(d=>{
      if(!d.success)return;
      const r=d.data;
      let oldJson='', newJson='';
      try { if(r.ancienne_valeur) oldJson=JSON.stringify(JSON.parse(r.ancienne_valeur),null,2); } catch(e){ oldJson=r.ancienne_valeur||''; }
      try { if(r.nouvelle_valeur) newJson=JSON.stringify(JSON.parse(r.nouvelle_valeur),null,2); } catch(e){ newJson=r.nouvelle_valeur||''; }

      document.getElementById('logB').innerHTML=`
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 20px;margin-bottom:20px">
          ${[['Action','<span class="audit-action-badge '+r.action+'">'+(ACTION_LABELS[r.action]||r.action)+'</span>'],
             ['Module',r.module+(r.entite_id?' #'+r.entite_id:'')],
             ['Utilisateur',r.user_nom||'Système'],['Email',r.user_email||'—'],
             ['Date',r.created_at],['IP',r.ip_address||'—'],
             ['Description',r.description]
            ].map(([l,v])=>`<div style="padding:7px 0;border-bottom:1px solid var(--border)">
              <div style="font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">${l}</div>
              <div style="font-size:13px">${v}</div></div>`).join('')}
        </div>
        ${oldJson?`<div style="margin-bottom:12px"><div style="font-size:11px;font-weight:700;color:var(--danger-d);margin-bottom:6px;text-transform:uppercase">📤 Ancienne valeur</div><div class="diff-block old">${escHtml(oldJson)}</div></div>`:''}
        ${newJson?`<div><div style="font-size:11px;font-weight:700;color:var(--success-d);margin-bottom:6px;text-transform:uppercase">📥 Nouvelle valeur</div><div class="diff-block new">${escHtml(newJson)}</div></div>`:''}
        ${!oldJson&&!newJson?'<p style="color:var(--muted);text-align:center;padding:20px">Aucune donnée de modification enregistrée.</p>':''}`;
    });
}
function escHtml(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
document.getElementById('mLog').addEventListener('click',e=>{if(e.target===e.currentTarget)e.currentTarget.classList.remove('open');});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
