<?php
// ============================================================
//  pages/admin/departements.php — Gestion des départements & N+1
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/notifications.php';

require_auth();
$user = current_user();
if (!in_array($user['role_slug'] ?? '', ['admin','superadmin'])) {
    http_response_code(403); include __DIR__ . '/../../templates/403.php'; exit;
}
$_SESSION['groupe_actif'] = 'ADMINISTRATION';
$page_title  = 'Départements';
$active_page = 'departements';

// ── AJAX ────────────────────────────────────────────────────
if (is_ajax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'create_dept') {
        $label = trim($_POST['label'] ?? '');
        $code  = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $_POST['code'] ?? '')));
        if (!$label || !$code) json_response(false, 'Nom et code obligatoires.');
        try {
            db_query("INSERT INTO departements (code, label) VALUES (?,?)", [$code, $label]);
            $id = (int)db_last_id('departements_id_seq');
            json_response(true, 'Département créé.', ['id'=>$id,'code'=>$code,'label'=>$label,'nb_membres'=>0,'nb_n1'=>0]);
        } catch (Exception $e) {
            json_response(false, 'Ce code est déjà utilisé.');
        }
    }

    if ($action === 'update_dept') {
        $id    = (int)($_POST['id'] ?? 0);
        $label = trim($_POST['label'] ?? '');
        if (!$label) json_response(false, 'Nom obligatoire.');
        db_query("UPDATE departements SET label=? WHERE id=?", [$label, $id]);
        json_response(true, 'Mis à jour.', ['label'=>$label]);
    }

    if ($action === 'delete_dept') {
        $id = (int)($_POST['id'] ?? 0);
        $nb = (int)db_fetch_value("SELECT COUNT(*) FROM user_departements WHERE departement_id=?", [$id]);
        if ($nb > 0) json_response(false, 'Retirez d\'abord tous les membres avant de supprimer.');
        db_query("DELETE FROM departements WHERE id=?", [$id]);
        json_response(true, 'Département supprimé.');
    }

    if ($action === 'get_dept') {
        $id   = (int)($_POST['id'] ?? 0);
        $dept = db_fetch_one("SELECT * FROM departements WHERE id=?", [$id]);
        if (!$dept) json_response(false, 'Introuvable.');
        $members = db_fetch_all(
            "SELECT u.id, u.prenom, u.nom, u.email, r.nom AS role_nom, ud.is_n1
             FROM user_departements ud
             JOIN users u ON u.id=ud.user_id
             LEFT JOIN roles r ON r.id=u.role_id
             WHERE ud.departement_id=?
             ORDER BY ud.is_n1 DESC, u.nom ASC",
            [$id]
        );
        // Tous les users sauf ceux déjà dans CE département — avec leur dept actuel si assigné ailleurs
        $assigned_ids = array_column($members, 'id');
        $ph_excl = $assigned_ids
            ? 'AND u.id NOT IN (' . implode(',', array_fill(0, count($assigned_ids), '?')) . ')'
            : '';
        $available = db_fetch_all(
            "SELECT u.id, CONCAT(u.prenom,' ',u.nom) AS fullname, u.email,
                    d.label AS dept_actuel
             FROM users u
             LEFT JOIN user_departements ud ON ud.user_id = u.id
             LEFT JOIN departements d ON d.id = ud.departement_id
             WHERE 1=1 $ph_excl
             ORDER BY u.nom ASC",
            $assigned_ids
        );
        json_response(true, '', ['dept'=>$dept,'members'=>$members,'available'=>$available]);
    }

    if ($action === 'assign') {
        $dept_id = (int)($_POST['dept_id'] ?? 0);
        $uid     = (int)($_POST['user_id'] ?? 0);
        $is_n1   = (int)($_POST['is_n1'] ?? 0);
        if (!$dept_id || !$uid) json_response(false, 'Données manquantes.');
        // Si le user est dans un autre dept, on le transfère automatiquement
        db_query("DELETE FROM user_departements WHERE user_id=? AND departement_id != ?", [$uid, $dept_id]);
        db_query(
            "INSERT INTO user_departements (user_id, departement_id, is_n1) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE is_n1=?",
            [$uid, $dept_id, $is_n1, $is_n1]
        );
        json_response(true, 'Compte affecté.');
    }

    if ($action === 'remove') {
        $dept_id = (int)($_POST['dept_id'] ?? 0);
        $uid     = (int)($_POST['user_id'] ?? 0);
        db_query("DELETE FROM user_departements WHERE user_id=? AND departement_id=?", [$uid, $dept_id]);
        json_response(true, 'Retiré du département.');
    }

    if ($action === 'toggle_n1') {
        $dept_id = (int)($_POST['dept_id'] ?? 0);
        $uid     = (int)($_POST['user_id'] ?? 0);
        $is_n1   = (int)($_POST['is_n1'] ?? 0);
        db_query("UPDATE user_departements SET is_n1=? WHERE user_id=? AND departement_id=?", [$is_n1, $uid, $dept_id]);
        json_response(true, $is_n1 ? 'Défini comme N+1.' : 'Rôle N+1 retiré.');
    }

    json_response(false, 'Action inconnue.');
}

// ── PAGE PHP ─────────────────────────────────────────────────
$depts = db_fetch_all(
    "SELECT d.id, d.code, d.label, d.ordre, d.actif,
            COUNT(ud.user_id) AS nb_membres,
            COALESCE(SUM(ud.is_n1),0) AS nb_n1
     FROM departements d
     LEFT JOIN user_departements ud ON ud.departement_id=d.id
     GROUP BY d.id, d.code, d.label, d.ordre, d.actif
     ORDER BY d.ordre ASC, d.label ASC"
);

include __DIR__ . '/../../templates/header.php';
?>
<style>
.dept-layout{display:grid;grid-template-columns:300px 1fr;gap:20px;align-items:start}
@media(max-width:780px){.dept-layout{grid-template-columns:1fr}}
.dept-panel{background:white;border:1px solid var(--border);border-radius:16px;overflow:hidden}
.dept-panel-hdr{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.dept-panel-ttl{font-family:'Montserrat',sans-serif;font-size:13px;font-weight:800;color:var(--navy)}
.dept-card{padding:14px 18px;border-bottom:1px solid var(--border);cursor:pointer;transition:.12s;display:flex;align-items:center;gap:12px}
.dept-card:last-child{border-bottom:none}
.dept-card:hover{background:#f8fafc}
.dept-card.active{background:#eef0f8;border-left:3px solid var(--navy)}
.dept-ico{width:36px;height:36px;border-radius:10px;background:#eef0f8;color:var(--navy);display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.dept-card.active .dept-ico{background:var(--navy);color:white}
.dept-meta{flex:1;min-width:0}
.dept-name{font-weight:700;font-size:13px;color:var(--navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dept-count{font-size:11px;color:var(--muted);margin-top:2px}
.dept-empty{padding:40px 20px;text-align:center;color:var(--muted);font-size:13px}
.btn-add{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:var(--navy);color:white;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit}
.btn-sm{display:inline-flex;align-items:center;gap:4px;padding:5px 10px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--border);background:#f8fafc;color:#374151;font-family:inherit}
.btn-sm:hover{background:#f1f5f9}
.btn-danger-sm{color:#dc2626;border-color:#fecaca;background:#fff5f5}
.btn-danger-sm:hover{background:#fee2e2}
.btn-n1-on{color:#16a34a;border-color:#bbf7d0;background:#f0fdf4}
.btn-n1-off{color:#94a3b8;border-color:#e2e8f0;background:#f8fafc}
.member-row{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border)}
.member-row:last-child{border-bottom:none}
.member-ava{width:34px;height:34px;border-radius:50%;background:#eef0f8;color:var(--navy);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex-shrink:0}
.member-info{flex:1;min-width:0}
.member-name{font-weight:700;font-size:13px;color:var(--navy)}
.member-role{font-size:11px;color:var(--muted)}
.badge-n1{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:800;background:#dcfce7;color:#16a34a;margin-left:6px}
.detail-hdr{display:flex;align-items:center;gap:10px;margin-bottom:20px;flex-wrap:wrap}
.detail-ttl{font-family:'Montserrat',sans-serif;font-size:16px;font-weight:900;color:var(--navy);flex:1}
.section-lbl{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin:18px 0 10px}
.add-form{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:12px;background:#f8fafc;border:1px solid var(--border);border-radius:10px;margin-top:12px}
.add-form select{flex:1;min-width:180px;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background:white;font-family:inherit}
.add-form label{display:flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:#374151;white-space:nowrap}
.add-form input[type=checkbox]{width:15px;height:15px;accent-color:var(--navy)}
.no-dept-msg{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;color:var(--muted);text-align:center;gap:10px}
.toast{position:fixed;bottom:24px;right:24px;z-index:9999;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;background:#06033A;color:white;box-shadow:0 8px 24px rgba(0,0,0,.2);transform:translateY(80px);opacity:0;transition:.3s}
.toast.show{transform:translateY(0);opacity:1}
.modal-bg{display:none;position:fixed;inset:0;background:rgba(6,3,58,.45);z-index:2000;align-items:center;justify-content:center}
.modal-bg.open{display:flex}
.modal-box{background:white;border-radius:16px;padding:26px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal-box h3{margin:0 0 16px;font-family:'Montserrat',sans-serif;font-size:16px;font-weight:800;color:var(--navy)}
.form-group{margin-bottom:14px}
.form-group label{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px}
.form-group input{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;outline:none;box-sizing:border-box}
.form-group input:focus{border-color:var(--navy)}
.modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px}
.btn-primary{padding:9px 18px;background:var(--navy);color:white;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit}
.btn-ghost{padding:9px 18px;background:#f1f5f9;color:#374151;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit}
</style>

<!-- Toast -->
<div class="toast" id="toast"></div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <div>
    <div style="font-family:'Montserrat',sans-serif;font-size:20px;font-weight:900;color:var(--navy)">Départements</div>
    <div style="font-size:13px;color:var(--muted);margin-top:2px">Créez les départements et affectez les comptes avec leur niveau hiérarchique (N+1)</div>
  </div>
  <button class="btn-add" onclick="openCreateModal()">
    <i class="ph-duotone ph-plus"></i> Nouveau département
  </button>
</div>

<div class="dept-layout">
  <!-- PANEL GAUCHE : liste départements -->
  <div class="dept-panel" id="dept-list-panel">
    <div class="dept-panel-hdr">
      <span class="dept-panel-ttl">Départements (<?= count($depts) ?>)</span>
    </div>
    <?php if (empty($depts)): ?>
      <div class="dept-empty">
        <i class="ph-duotone ph-buildings" style="font-size:32px;display:block;margin-bottom:8px"></i>
        Aucun département créé
      </div>
    <?php else: ?>
      <?php foreach ($depts as $d): ?>
        <div class="dept-card" id="card-<?= $d['id'] ?>" onclick="selectDept(<?= $d['id'] ?>)">
          <div class="dept-ico"><i class="ph-duotone ph-buildings"></i></div>
          <div class="dept-meta">
            <div class="dept-name"><?= h($d['label']) ?></div>
            <div class="dept-count">
              <?= $d['nb_membres'] ?> membre<?= $d['nb_membres']!=1?'s':'' ?>
              <?php if ($d['nb_n1'] > 0): ?> · <span style="color:#16a34a;font-weight:700"><?= $d['nb_n1'] ?> N+1</span><?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- PANEL DROIT : détail département -->
  <div class="dept-panel" id="dept-detail-panel">
    <div class="no-dept-msg" id="no-dept-msg">
      <i class="ph-duotone ph-buildings" style="font-size:44px;color:#cbd5e1"></i>
      <div style="font-weight:700;color:#374151">Sélectionnez un département</div>
      <div style="font-size:12px">Cliquez sur un département pour gérer ses membres</div>
    </div>
    <div id="dept-detail-content" style="display:none;padding:20px"></div>
  </div>
</div>

<!-- MODAL : créer département -->
<div class="modal-bg" id="modal-create">
  <div class="modal-box">
    <h3><i class="ph-duotone ph-plus-circle" style="vertical-align:middle;margin-right:6px"></i>Nouveau département</h3>
    <div class="form-group">
      <label>Nom du département <span style="color:#dc2626">*</span></label>
      <input type="text" id="new-label" placeholder="ex: Opérations, Finance, IT…" oninput="autoCode()">
    </div>
    <div class="form-group">
      <label>Code (identifiant unique)</label>
      <input type="text" id="new-code" placeholder="ex: operations" style="font-family:monospace">
    </div>
    <div id="create-err" style="color:#dc2626;font-size:12px;margin-top:4px;display:none"></div>
    <div class="modal-actions">
      <button class="btn-ghost" onclick="closeCreateModal()">Annuler</button>
      <button class="btn-primary" onclick="doCreate()">Créer</button>
    </div>
  </div>
</div>

<script>
const APP = '<?= APP_URL ?>';
let activeDeptId = null;

// ── Toast
function toast(msg, ok=true) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.background = ok ? '#16a34a' : '#dc2626';
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2800);
}

// ── POST helper
function post(data) {
    const fd = new FormData();
    Object.entries(data).forEach(([k,v]) => fd.append(k,v));
    return fetch(window.location.href, {
        method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd
    }).then(r=>r.json());
}

// ── Select département
function selectDept(id) {
    document.querySelectorAll('.dept-card').forEach(c => c.classList.remove('active'));
    const card = document.getElementById('card-'+id);
    if (card) card.classList.add('active');
    activeDeptId = id;
    post({action:'get_dept', id}).then(res => {
        if (!res.success) { toast(res.message, false); return; }
        renderDetail(res.data);
    });
}

// ── Render detail panel
function renderDetail(data) {
    const {dept, members, available} = data;
    document.getElementById('no-dept-msg').style.display = 'none';
    const el = document.getElementById('dept-detail-content');
    el.style.display = 'block';

    const membersHtml = members.length ? members.map(m => {
        const initials = ((m.prenom||'').charAt(0)+(m.nom||'').charAt(0)).toUpperCase();
        const n1Badge  = m.is_n1==1 ? '<span class="badge-n1">N+1</span>' : '';
        const n1Btn    = m.is_n1==1
            ? `<button class="btn-sm btn-n1-on" onclick="toggleN1(${dept.id},${m.id},0)" title="Retirer N+1"><i class="ph-duotone ph-star-fill"></i> N+1</button>`
            : `<button class="btn-sm btn-n1-off" onclick="toggleN1(${dept.id},${m.id},1)" title="Définir comme N+1"><i class="ph-duotone ph-star"></i> N+1</button>`;
        return `<div class="member-row">
            <div class="member-ava">${initials}</div>
            <div class="member-info">
                <div class="member-name">${esc(m.prenom+' '+m.nom)}${n1Badge}</div>
                <div class="member-role">${esc(m.role_nom||'—')} · ${esc(m.email||'')}</div>
            </div>
            <div style="display:flex;gap:6px;flex-shrink:0">
                ${n1Btn}
                <button class="btn-sm btn-danger-sm" onclick="removeMember(${dept.id},${m.id})"><i class="ph-duotone ph-x"></i></button>
            </div>
        </div>`;
    }).join('') : `<div style="padding:20px;text-align:center;color:var(--muted);font-size:13px"><i class="ph-duotone ph-users" style="font-size:28px;display:block;margin-bottom:6px"></i>Aucun membre</div>`;

    const availOpts = available.map(u => {
        const deptInfo = u.dept_actuel ? ` · [${esc(u.dept_actuel)}]` : '';
        return `<option value="${u.id}">${esc(u.fullname)}${deptInfo} — ${esc(u.email||'')}</option>`;
    }).join('');

    el.innerHTML = `
        <div class="detail-hdr">
            <div class="detail-ttl" id="dept-title-${dept.id}">${esc(dept.label)}</div>
            <button class="btn-sm" onclick="renamePrompt(${dept.id})"><i class="ph-duotone ph-pencil-simple"></i> Renommer</button>
            <button class="btn-sm btn-danger-sm" onclick="deleteDept(${dept.id})"><i class="ph-duotone ph-trash"></i></button>
        </div>

        <div style="font-size:12px;color:var(--muted);margin-bottom:16px">
            Code : <code style="background:#f1f5f9;padding:2px 7px;border-radius:5px">${esc(dept.code)}</code>
            · ${members.length} membre${members.length!=1?'s':''}
            · <span style="color:${members.filter(m=>m.is_n1==1).length>0?'#16a34a':'#94a3b8'};font-weight:700">
                ${members.filter(m=>m.is_n1==1).length} N+1
              </span>
        </div>

        <div class="section-lbl">Membres du département</div>
        <div id="members-list">${membersHtml}</div>

        ${available.length ? `
        <div class="section-lbl">Ajouter / transférer un compte</div>
        <div style="font-size:11px;color:var(--muted);margin-bottom:8px">Les comptes marqués <strong>[Département]</strong> seront transférés automatiquement.</div>
        <div class="add-form">
            <select id="sel-user"><option value="">— Sélectionner un compte —</option>${availOpts}</select>
            <label><input type="checkbox" id="chk-n1"> N+1</label>
            <button class="btn-primary" style="padding:8px 14px;font-size:12px" onclick="assignUser(${dept.id})">
                <i class="ph-duotone ph-plus"></i> Ajouter
            </button>
        </div>` : '<div style="font-size:12px;color:var(--muted);margin-top:10px">Tous les comptes de l\'application sont membres de ce département.</div>'}
    `;
}

function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ── Renommer
function renamePrompt(id) {
    const cur = document.getElementById('dept-title-'+id)?.textContent || '';
    const label = prompt('Nouveau nom du département :', cur);
    if (!label || label.trim() === cur.trim()) return;
    post({action:'update_dept', id, label: label.trim()}).then(res => {
        toast(res.message, res.success);
        if (res.success) {
            const el = document.getElementById('dept-title-'+id);
            if (el) el.textContent = label.trim();
            const card = document.getElementById('card-'+id);
            if (card) card.querySelector('.dept-name').textContent = label.trim();
        }
    });
}

// ── Supprimer département
function deleteDept(id) {
    if (!confirm('Supprimer ce département ? (tous les membres doivent être retirés d\'abord)')) return;
    post({action:'delete_dept', id}).then(res => {
        toast(res.message, res.success);
        if (res.success) {
            const card = document.getElementById('card-'+id);
            if (card) card.remove();
            document.getElementById('no-dept-msg').style.display = '';
            document.getElementById('dept-detail-content').style.display = 'none';
            activeDeptId = null;
        }
    });
}

// ── Assigner
function assignUser(deptId) {
    const uid   = document.getElementById('sel-user')?.value;
    const is_n1 = document.getElementById('chk-n1')?.checked ? 1 : 0;
    if (!uid) { toast('Sélectionnez un compte.', false); return; }
    post({action:'assign', dept_id:deptId, user_id:uid, is_n1}).then(res => {
        toast(res.message, res.success);
        if (res.success) selectDept(deptId);
    });
}

// ── Retirer membre
function removeMember(deptId, userId) {
    if (!confirm('Retirer ce compte du département ?')) return;
    post({action:'remove', dept_id:deptId, user_id:userId}).then(res => {
        toast(res.message, res.success);
        if (res.success) selectDept(deptId);
    });
}

// ── Toggle N+1
function toggleN1(deptId, userId, is_n1) {
    post({action:'toggle_n1', dept_id:deptId, user_id:userId, is_n1}).then(res => {
        toast(res.message, res.success);
        if (res.success) selectDept(deptId);
    });
}

// ── Modal créer
function openCreateModal() {
    document.getElementById('new-label').value = '';
    document.getElementById('new-code').value  = '';
    document.getElementById('create-err').style.display = 'none';
    document.getElementById('modal-create').classList.add('open');
    setTimeout(() => document.getElementById('new-label').focus(), 100);
}
function closeCreateModal() { document.getElementById('modal-create').classList.remove('open'); }
function autoCode() {
    const v = document.getElementById('new-label').value;
    document.getElementById('new-code').value = v.toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g,'')
        .replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'');
}
function doCreate() {
    const label = document.getElementById('new-label').value.trim();
    const code  = document.getElementById('new-code').value.trim();
    const err   = document.getElementById('create-err');
    if (!label || !code) { err.textContent='Nom et code obligatoires.'; err.style.display='block'; return; }
    post({action:'create_dept', label, code}).then(res => {
        if (!res.success) { err.textContent=res.message; err.style.display='block'; return; }
        toast('Département créé.', true);
        closeCreateModal();
        // Ajouter la carte sans recharger
        const d = res.data;
        const panel = document.getElementById('dept-list-panel');
        const emptyMsg = panel.querySelector('.dept-empty');
        if (emptyMsg) emptyMsg.remove();
        const card = document.createElement('div');
        card.className = 'dept-card'; card.id = 'card-'+d.id;
        card.onclick = () => selectDept(d.id);
        card.innerHTML = `<div class="dept-ico"><i class="ph-duotone ph-buildings"></i></div>
            <div class="dept-meta">
                <div class="dept-name">${esc(d.label)}</div>
                <div class="dept-count">0 membre</div>
            </div>`;
        panel.appendChild(card);
        selectDept(d.id);
    });
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeCreateModal(); });
document.getElementById('modal-create').addEventListener('click', e => { if(e.target===e.currentTarget) closeCreateModal(); });
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
