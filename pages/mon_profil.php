<?php
// ============================================================
//  pages/mon_profil.php — Profil utilisateur
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
require_auth();

$user        = current_user();
$page_title  = 'Mon Profil';
$active_page = 'mon_profil';

// ── AJAX ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    $action = $_POST['action'] ?? '';

    // ── Mettre à jour les informations personnelles
    if ($action === 'update_profile') {
        $prenom = trim($_POST['prenom'] ?? '');
        $nom    = trim($_POST['nom']    ?? '');
        $email  = strtolower(trim($_POST['email'] ?? ''));
        $tel    = trim($_POST['telephone'] ?? '');

        if (!$prenom || !$nom)
            json_response(false, 'Prénom et nom sont obligatoires.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            json_response(false, 'Adresse email invalide.');
        if (strlen($prenom) > 80 || strlen($nom) > 80)
            json_response(false, 'Nom ou prénom trop long (80 caractères max).');

        $exists = db_fetch_value(
            "SELECT COUNT(*) FROM users WHERE email=? AND id!=?",
            [$email, $user['id']]
        );
        if ($exists > 0)
            json_response(false, 'Cette adresse email est déjà utilisée par un autre compte.');

        db_query(
            "UPDATE users SET prenom=?, nom=?, email=?, telephone=? WHERE id=?",
            [$prenom, $nom, $email, $tel ?: null, $user['id']]
        );
        audit_log(
            $user['id'], 'UPDATE', 'users', $user['id'],
            "Mise à jour profil : {$user['prenom']} {$user['nom']} ({$user['email']}) → $prenom $nom ($email)"
        );
        json_response(true, 'Profil mis à jour avec succès.');
    }

    // ── Changer le mot de passe
    if ($action === 'change_password') {
        $old  = $_POST['old_password']     ?? '';
        $new  = $_POST['new_password']     ?? '';
        $conf = $_POST['confirm_password'] ?? '';

        if (!$old) json_response(false, 'Le mot de passe actuel est requis.');
        if ($new !== $conf) json_response(false, 'Les nouveaux mots de passe ne correspondent pas.');

        $result = auth_change_password($user['id'], $old, $new, false);
        json_response($result['success'], $result['message']);
    }

    json_response(false, 'Action inconnue.');
}

include __DIR__ . '/../templates/header.php';
?>
<style>
/* ── Layout profil ─────────────────────────────────────────── */
.profil-layout {
  max-width: 960px; margin: 0 auto;
  display: grid;
  grid-template-columns: 260px 1fr;
  gap: 24px;
  align-items: start;
}

/* ── Carte résumé ──────────────────────────────────────────── */
.profil-avatar {
  width: 88px; height: 88px; border-radius: 50%;
  background: linear-gradient(135deg, var(--primary), var(--secondary));
  display: flex; align-items: center; justify-content: center;
  font-size: 32px; font-weight: 800; color: #fff;
  font-family: 'Plus Jakarta Sans', sans-serif;
  margin: 0 auto 16px;
  box-shadow: 0 8px 28px rgba(124,146,255,.35);
  flex-shrink: 0;
}
.profil-role-pill {
  display: inline-block;
  background: var(--primary-l); color: var(--primary-d);
  font-size: 11px; font-weight: 700;
  padding: 3px 12px; border-radius: 20px;
  letter-spacing: .3px; margin-bottom: 18px;
}
.profil-meta {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 9px 0; border-bottom: 1px solid var(--border);
  font-size: 12.5px;
}
.profil-meta:last-child { border-bottom: none; }
.profil-meta i { font-size: 16px; color: var(--primary); flex-shrink: 0; margin-top: 2px; }
.profil-meta-val { color: var(--text); font-weight: 600; word-break: break-all; line-height: 1.3; }
.profil-meta-lbl { font-size: 10.5px; color: var(--muted); margin-top: 2px; }

/* ── Thème options ─────────────────────────────────────────── */
.theme-opts { display: flex; gap: 12px; flex-wrap: wrap; }
.theme-opt {
  flex: 1; min-width: 130px;
  border: 2px solid var(--border);
  border-radius: 14px; padding: 18px 14px;
  text-align: center; cursor: pointer;
  transition: all .18s; background: white;
  user-select: none;
}
.theme-opt:hover { border-color: var(--primary); background: var(--primary-l); }
.theme-opt.active {
  border-color: var(--primary);
  background: var(--primary-l);
  box-shadow: 0 0 0 3px rgba(124,146,255,.18);
}
.theme-opt .t-em  { font-size: 30px; margin-bottom: 8px; line-height: 1; display: block; }
.theme-opt .t-lbl { font-size: 13px; font-weight: 700; color: var(--navy); display: block; }
.theme-opt .t-sub { font-size: 11px; color: var(--muted); margin-top: 3px; display: block; }
.theme-opt.active .t-lbl { color: var(--primary-d); }

/* ── Force mot de passe ────────────────────────────────────── */
.pw-track { height: 5px; background: var(--border); border-radius: 5px; margin-top: 8px; }
.pw-fill  { height: 100%; width: 0; border-radius: 5px; transition: width .3s, background .3s; }

/* ── Responsive ────────────────────────────────────────────── */
@media (max-width: 720px) {
  .profil-layout { grid-template-columns: 1fr; }
  .theme-opts { flex-direction: column; }
  .theme-opt { min-width: 0; }
}

/* ── Dark mode ─────────────────────────────────────────────── */
[data-theme="dark"] .theme-opt { background: #1E293B; border-color: #334155; }
[data-theme="dark"] .theme-opt:hover { background: #253349; border-color: var(--primary); }
[data-theme="dark"] .theme-opt.active { background: #1C3B6E; border-color: var(--primary); }
[data-theme="dark"] .theme-opt .t-lbl { color: #CBD5E1; }
[data-theme="dark"] .theme-opt.active .t-lbl { color: #93C5FD; }
[data-theme="dark"] .profil-meta-val { color: #CBD5E1; }
</style>

<div style="margin-bottom:20px">
  <a href="javascript:history.back()" class="btn btn-secondary btn-sm">
    <i class="ph-duotone ph-arrow-left"></i> Retour
  </a>
</div>

<div class="profil-layout">

  <!-- ═══ Carte résumé (gauche) ═══ -->
  <div class="card">
    <div class="card-body" style="text-align:center;padding:28px 22px">

      <div class="profil-avatar" id="js-initiales">
        <?= strtoupper(substr($user['prenom'],0,1).substr($user['nom'],0,1)) ?>
      </div>

      <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:800;color:var(--navy);margin-bottom:6px" id="js-fullname">
        <?= h($user['prenom'].' '.$user['nom']) ?>
      </div>
      <div class="profil-role-pill"><?= h($user['role_nom']) ?></div>

      <div style="text-align:left">

        <?php if ($user['site_nom']): ?>
        <div class="profil-meta">
          <i class="ph-duotone ph-map-pin"></i>
          <div>
            <div class="profil-meta-val"><?= h($user['site_nom']) ?></div>
            <div class="profil-meta-lbl">Site assigné</div>
          </div>
        </div>
        <?php endif; ?>

        <div class="profil-meta">
          <i class="ph-duotone ph-envelope"></i>
          <div>
            <div class="profil-meta-val" id="js-email-display"><?= h($user['email']) ?></div>
            <div class="profil-meta-lbl">Email</div>
          </div>
        </div>

        <?php if (!empty($user['telephone'])): ?>
        <div class="profil-meta">
          <i class="ph-duotone ph-phone"></i>
          <div>
            <div class="profil-meta-val"><?= h($user['telephone']) ?></div>
            <div class="profil-meta-lbl">Téléphone</div>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($user['last_login'])): ?>
        <div class="profil-meta">
          <i class="ph-duotone ph-clock-counter-clockwise"></i>
          <div>
            <div class="profil-meta-val"><?= fmt_datetime($user['last_login']) ?></div>
            <div class="profil-meta-lbl">Dernière connexion</div>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

  <!-- ═══ Formulaires (droite) ═══ -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- ── Informations personnelles ── -->
    <div class="card">
      <div class="card-header">
        <h3>
          <i class="ph-duotone ph-user-circle" style="margin-right:8px;color:var(--primary)"></i>
          Informations personnelles
        </h3>
      </div>
      <div class="card-body">
        <div id="alert-profil"></div>
        <form id="form-profil" autocomplete="off">
          <div class="form-row cols-2">
            <div class="form-group">
              <label>Prénom</label>
              <input type="text" class="form-control" name="prenom"
                     value="<?= h($user['prenom']) ?>" required maxlength="80">
            </div>
            <div class="form-group">
              <label>Nom</label>
              <input type="text" class="form-control" name="nom"
                     value="<?= h($user['nom']) ?>" required maxlength="80">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Adresse email</label>
              <input type="email" class="form-control" name="email"
                     value="<?= h($user['email']) ?>" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Téléphone
                <span style="font-weight:400;color:var(--muted)">(optionnel)</span>
              </label>
              <input type="tel" class="form-control" name="telephone"
                     value="<?= h($user['telephone'] ?? '') ?>" maxlength="20">
            </div>
          </div>
          <button type="submit" class="btn btn-primary" id="btn-profil">
            <i class="ph-duotone ph-floppy-disk"></i> Enregistrer les modifications
          </button>
        </form>
      </div>
    </div>

    <!-- ── Mot de passe ── -->
    <div class="card">
      <div class="card-header">
        <h3>
          <i class="ph-duotone ph-lock-key" style="margin-right:8px;color:var(--primary)"></i>
          Changer le mot de passe
        </h3>
      </div>
      <div class="card-body">
        <div id="alert-pwd"></div>
        <form id="form-pwd" autocomplete="off">
          <div class="form-row">
            <div class="form-group">
              <label>Mot de passe actuel</label>
              <input type="password" class="form-control" name="old_password"
                     required autocomplete="current-password">
            </div>
          </div>
          <div class="form-row cols-2">
            <div class="form-group">
              <label>Nouveau mot de passe</label>
              <input type="password" class="form-control" name="new_password"
                     id="inp-npw" required minlength="8" autocomplete="new-password">
              <div class="pw-track"><div class="pw-fill" id="pw-bar"></div></div>
              <div style="font-size:11px;margin-top:4px;font-weight:600;min-height:16px"
                   id="pw-strength-lbl"></div>
            </div>
            <div class="form-group">
              <label>Confirmer le nouveau mot de passe</label>
              <input type="password" class="form-control" name="confirm_password"
                     id="inp-cpw" required autocomplete="new-password">
              <div style="font-size:11px;margin-top:4px;font-weight:600;min-height:16px"
                   id="pw-match-lbl"></div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary" id="btn-pwd">
            <i class="ph-duotone ph-lock-key"></i> Modifier le mot de passe
          </button>
        </form>
      </div>
    </div>

    <!-- ── Préférences d'affichage ── -->
    <div class="card">
      <div class="card-header">
        <h3>
          <i class="ph-duotone ph-paint-bucket" style="margin-right:8px;color:var(--primary)"></i>
          Préférences d'affichage
        </h3>
      </div>
      <div class="card-body">
        <p style="font-size:13px;color:var(--muted);margin-bottom:18px">
          Choisissez le thème de l'interface. La préférence est enregistrée localement dans ce navigateur.
        </p>
        <div class="theme-opts">
          <div class="theme-opt" data-pref="light" onclick="setThemePref('light')">
            <span class="t-em">☀️</span>
            <span class="t-lbl">Clair</span>
            <span class="t-sub">Toujours en mode clair</span>
          </div>
          <div class="theme-opt" data-pref="dark" onclick="setThemePref('dark')">
            <span class="t-em">🌙</span>
            <span class="t-lbl">Sombre</span>
            <span class="t-sub">Toujours en mode sombre</span>
          </div>
          <div class="theme-opt" data-pref="auto" onclick="setThemePref('auto')">
            <span class="t-em">💻</span>
            <span class="t-lbl">Système</span>
            <span class="t-sub">Suit les réglages de l'OS</span>
          </div>
        </div>
        <div id="theme-fb" style="margin-top:14px;font-size:12.5px;color:var(--primary);font-weight:600;min-height:20px"></div>
      </div>
    </div>

  </div><!-- /.right col -->
</div><!-- /.profil-layout -->

<script>
const _URL = window.location.href;

// ── Informations personnelles ──────────────────────────────
document.getElementById('form-profil').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-profil');
  btn.disabled = true;
  btn.innerHTML = '<i class="ph-duotone ph-spinner"></i> Enregistrement...';

  const fd = new FormData(this);
  fd.append('action', 'update_profile');

  try {
    const d = await apPost(fd);
    alertBox('alert-profil', d.success, d.message);
    if (d.success) {
      const prenom = this.prenom.value.trim();
      const nom    = this.nom.value.trim();
      // Mettre à jour la carte résumé sans rechargement
      document.getElementById('js-initiales').textContent =
        (prenom[0] || '').toUpperCase() + (nom[0] || '').toUpperCase();
      document.getElementById('js-fullname').textContent  = prenom + ' ' + nom;
      document.getElementById('js-email-display').textContent = this.email.value.trim();
    }
  } catch { alertBox('alert-profil', false, 'Erreur réseau.'); }

  btn.disabled = false;
  btn.innerHTML = '<i class="ph-duotone ph-floppy-disk"></i> Enregistrer les modifications';
});

// ── Mot de passe ────────────────────────────────────────────
document.getElementById('form-pwd').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('btn-pwd');
  btn.disabled = true;
  btn.innerHTML = '<i class="ph-duotone ph-spinner"></i> Modification...';

  const fd = new FormData(this);
  fd.append('action', 'change_password');

  try {
    const d = await apPost(fd);
    alertBox('alert-pwd', d.success, d.message);
    if (d.success) this.reset();
  } catch { alertBox('alert-pwd', false, 'Erreur réseau.'); }

  btn.disabled = false;
  btn.innerHTML = '<i class="ph-duotone ph-lock-key"></i> Modifier le mot de passe';
});

// ── Indicateur de force ────────────────────────────────────
document.getElementById('inp-npw').addEventListener('input', function() {
  const v = this.value;
  let s = 0;
  if (v.length >= 8)           s++;
  if (v.length >= 12)          s++;
  if (/[A-Z]/.test(v))         s++;
  if (/[0-9]/.test(v))         s++;
  if (/[^A-Za-z0-9]/.test(v)) s++;

  const colors = ['', '#F87171','#FBBF24','#A3E635','#34D399','#059669'];
  const labels = ['', 'Faible', 'Moyen', 'Acceptable', 'Fort', 'Très fort'];
  const widths  = ['0%','20%','40%','65%','85%','100%'];

  const bar = document.getElementById('pw-bar');
  const lbl = document.getElementById('pw-strength-lbl');
  bar.style.width      = v ? widths[s]  : '0%';
  bar.style.background = v ? colors[s]  : '';
  lbl.style.color      = colors[s] || '';
  lbl.textContent      = v ? labels[s]  : '';
  checkMatch();
});
document.getElementById('inp-cpw').addEventListener('input', checkMatch);

function checkMatch() {
  const a   = document.getElementById('inp-npw').value;
  const b   = document.getElementById('inp-cpw').value;
  const lbl = document.getElementById('pw-match-lbl');
  if (!b) { lbl.textContent = ''; return; }
  lbl.style.color  = a === b ? '#059669' : '#F87171';
  lbl.textContent  = a === b ? '✓ Les mots de passe correspondent' : '✗ Ne correspondent pas';
}

// ── Thème ──────────────────────────────────────────────────
function setThemePref(pref) {
  localStorage.setItem('ds-theme-pref', pref);
  const sys = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const t   = pref === 'auto' ? (sys ? 'dark' : 'light') : pref;
  document.documentElement.setAttribute('data-theme', t);

  document.querySelectorAll('.theme-opt').forEach(el =>
    el.classList.toggle('active', el.dataset.pref === pref)
  );

  const msgs = { light: '☀️ Thème clair', dark: '🌙 Thème sombre', auto: '💻 Thème système' };
  const fb = document.getElementById('theme-fb');
  fb.textContent = (msgs[pref] || '') + ' — préférence enregistrée.';
  setTimeout(() => { fb.textContent = ''; }, 3000);
}

// Activer l'option courante au chargement
(function () {
  const pref = localStorage.getItem('ds-theme-pref') || 'auto';
  document.querySelectorAll('.theme-opt').forEach(el =>
    el.classList.toggle('active', el.dataset.pref === pref)
  );
})();

// ── Utilitaires ────────────────────────────────────────────
async function apPost(fd) {
  const r = await fetch(_URL, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: fd
  });
  return r.json();
}
function alertBox(id, ok, msg) {
  const el = document.getElementById(id);
  el.innerHTML = `<div class="alert alert-${ok ? 'success' : 'danger'}" style="margin-bottom:16px">${msg}</div>`;
  if (ok) setTimeout(() => { el.innerHTML = ''; }, 5000);
}
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
