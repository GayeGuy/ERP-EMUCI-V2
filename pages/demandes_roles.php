<?php
// ============================================================
//  pages/demandes_roles.php — Attribution des rôles de validation (Demandes internes)
//  Réservé aux admin / superadmin.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/demandes.php';

require_auth();
$user = current_user();
if (!in_array(($user['role_slug'] ?? ''), ['admin','superadmin'], true)) {
    http_response_code(403); include __DIR__ . '/../templates/403.php'; exit;
}
$_SESSION['groupe_actif'] = 'DEMANDES';
$page_title  = 'Rôles valideurs';
$active_page = 'demandes_roles';

// ── AJAX : cocher / décocher un rôle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $uid   = (int)($_POST['user_id'] ?? 0);
    $role  = trim($_POST['role_code'] ?? '');
    $on    = ($_POST['on'] ?? '') === '1';
    $valid_roles = array_column(db_fetch_all("SELECT code FROM di_roles"), 'code');
    if (!$uid || !in_array($role, $valid_roles, true)) json_response(false, 'Paramètres invalides.');
    // DELETE puis INSERT => idempotent et portable (pas de ON CONFLICT / INSERT IGNORE)
    db_query("DELETE FROM di_user_roles WHERE user_id=? AND role_code=?", [$uid, $role]);
    if ($on) db_query("INSERT INTO di_user_roles (user_id, role_code) VALUES (?, ?)", [$uid, $role]);
    json_response(true, $on ? 'Rôle attribué.' : 'Rôle retiré.');
}

$roles = db_fetch_all("SELECT code, label FROM di_roles ORDER BY ordre ASC");
$search = trim($_GET['q'] ?? '');
$where = 'u.actif=1';
$params = [];
if ($search !== '') {
    $where .= ' AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)';
    $s = "%$search%"; $params = [$s, $s, $s];
}
$users = db_fetch_all(
    "SELECT u.id, u.nom, u.prenom, u.email, r.nom AS role_nom
     FROM users u LEFT JOIN roles r ON r.id=u.role_id
     WHERE $where ORDER BY u.nom, u.prenom", $params
);
// Map user_id => [role_code => true]
$assign = [];
foreach (db_fetch_all("SELECT user_id, role_code FROM di_user_roles") as $ur) {
    $assign[$ur['user_id']][$ur['role_code']] = true;
}

include __DIR__ . '/../templates/header.php';
?>
<style>
  .dr-wrap{max-width:1000px;margin:0 auto}
  .dr-top{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:18px;flex-wrap:wrap}
  .dr-search{padding:10px 14px;border:1.5px solid var(--border,#d5dde8);border-radius:10px;font-size:14px;min-width:240px;background:var(--input,#f8fafc)}
  .dr-tbl{width:100%;border-collapse:collapse;background:var(--card,#fff);border:1.5px solid var(--border,#e2e8f0);border-radius:14px;overflow:hidden}
  .dr-tbl th{padding:12px 14px;font-size:12px;color:var(--muted,#7f8c8d);background:var(--input,#f8fafc);text-align:center}
  .dr-tbl th.user{text-align:left}
  .dr-tbl td{padding:11px 14px;border-top:1px solid var(--border,#eef2f7);font-size:14px;text-align:center}
  .dr-tbl td.user{text-align:left}
  .dr-tbl tr:hover td{background:var(--input,#fbfcfe)}
  .dr-uname{font-weight:600}
  .dr-umail{font-size:12px;color:var(--muted,#7f8c8d)}
  .dr-chk{width:20px;height:20px;cursor:pointer;accent-color:#3B4FBE}
  .dr-empty{text-align:center;padding:44px;color:var(--muted,#7f8c8d)}
  .dr-hint{font-size:12px;color:var(--muted,#7f8c8d);margin:0 0 16px}
  #dr-toast{position:fixed;top:20px;right:20px;z-index:9999;padding:11px 18px;border-radius:10px;font-size:13px;
    font-weight:600;color:#fff;background:#27ae60;box-shadow:0 6px 20px rgba(0,0,0,.15);opacity:0;transition:.25s;pointer-events:none}
  #dr-toast.show{opacity:1}
</style>

<div class="dr-wrap">
  <div class="dr-top">
    <div>
      <h2 style="margin:0 0 2px">Rôles de validation</h2>
      <p class="dr-hint" style="margin:0">Cochez les circuits que chaque personne peut viser. Un utilisateur peut cumuler plusieurs rôles.</p>
    </div>
    <form method="get" onsubmit="return true">
      <input class="dr-search" type="text" name="q" value="<?= h($search) ?>" placeholder="Rechercher un utilisateur…">
    </form>
  </div>

  <?php if (empty($users)): ?>
    <div class="dr-tbl dr-empty">Aucun utilisateur trouvé.</div>
  <?php else: ?>
    <table class="dr-tbl">
      <thead>
        <tr>
          <th class="user">Utilisateur</th>
          <?php foreach ($roles as $r): ?><th title="<?= h($r['label']) ?>"><?= h(strtoupper($r['code'])) ?></th><?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td class="user">
            <div class="dr-uname"><?= h($u['prenom'].' '.$u['nom']) ?></div>
            <div class="dr-umail"><?= h($u['email']) ?><?= $u['role_nom'] ? ' · '.h($u['role_nom']) : '' ?></div>
          </td>
          <?php foreach ($roles as $r):
            $ck = !empty($assign[$u['id']][$r['code']]) ? ' checked' : ''; ?>
            <td>
              <input type="checkbox" class="dr-chk"<?= $ck ?>
                     onchange="drToggle(<?= (int)$u['id'] ?>, '<?= h($r['code']) ?>', this)">
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div id="dr-toast"></div>
<script>
function drToast(msg, ok){
  const t=document.getElementById('dr-toast');
  t.textContent=msg; t.style.background = ok ? '#27ae60' : '#e74c3c';
  t.classList.add('show'); setTimeout(()=>t.classList.remove('show'), 1800);
}
function drToggle(uid, role, el){
  const on = el.checked ? '1' : '0';
  const fd = new FormData();
  fd.append('user_id', uid); fd.append('role_code', role); fd.append('on', on);
  fetch(window.location.pathname, {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd})
    .then(r=>r.json())
    .then(d=>{ if(!d.success){ el.checked=!el.checked; drToast(d.message||'Erreur', false); }
               else drToast(d.message, true); })
    .catch(()=>{ el.checked=!el.checked; drToast('Erreur réseau', false); });
}
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
