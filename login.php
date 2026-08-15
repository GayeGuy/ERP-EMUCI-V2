<?php
// ============================================================
//  login.php  —  Page de connexion
// ============================================================

// CSP en tout premier — avant tout output (requis pour Chart.js / eval)
if (!headers_sent()) {
    header_remove('Content-Security-Policy');
    header("Content-Security-Policy: default-src * 'unsafe-inline' 'unsafe-eval' data: blob:; script-src * 'unsafe-inline' 'unsafe-eval' blob: data:; style-src * 'unsafe-inline' data:; font-src * data:; img-src * data: blob:;");
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/auth.php';

// session démarrée par session.php

// Si déjà connecté → rediriger
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/pages/accueil.php');
    exit;
}

$error   = '';
$success = '';
$timeout = !empty($_GET['timeout']);

// Traitement de la soumission AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    $result = auth_login($email, $password);

    if ($result['success']) {
        echo json_encode(['success' => true, 'redirect' => APP_URL . '/pages/accueil.php']);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message']]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    /* ===== RESET & BASE ===== */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --navy:    #0a1628;
      --blue:    #0f2d5c;
      --accent:  #2980d4;
      --accent2: #1a56a0;
      --light:   #f0f4f8;
      --white:   #ffffff;
      --text:    #2c3e50;
      --muted:   #7f8c8d;
      --danger:  #e74c3c;
      --success: #27ae60;
      --border:  #d5dde8;
      --shadow:  0 20px 60px rgba(13,31,53,.18);
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--navy);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      position: relative;
    }

    /* ===== ANIMATED BACKGROUND ===== */
    .bg-pattern {
      position: fixed; inset: 0; z-index: 0;
      background:
        radial-gradient(ellipse at 20% 50%, rgba(230,126,34,.12) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 20%, rgba(26,60,94,.6) 0%, transparent 50%),
        linear-gradient(135deg, #0d1f35 0%, #1a3c5e 50%, #0d1f35 100%);
    }

    .bg-grid {
      position: fixed; inset: 0; z-index: 0;
      background-image:
        linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
      background-size: 50px 50px;
    }

    /* Floating orbs */
    .orb {
      position: fixed; border-radius: 50%; filter: blur(80px); z-index: 0; animation: float 8s ease-in-out infinite;
    }
    .orb-1 { width: 400px; height: 400px; top: -100px; right: -100px; background: rgba(230,126,34,.15); animation-delay: 0s; }
    .orb-2 { width: 300px; height: 300px; bottom: -80px; left: -80px; background: rgba(26,60,94,.5); animation-delay: -4s; }

    @keyframes float {
      0%,100% { transform: translate(0,0) scale(1); }
      50%      { transform: translate(20px,-20px) scale(1.05); }
    }

    /* ===== CARD ===== */
    .card-wrap {
      position: relative; z-index: 10;
      display: grid;
      grid-template-columns: 300px 420px;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: var(--shadow);
      animation: slideUp .6s cubic-bezier(.22,1,.36,1) both;
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(30px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ===== LEFT PANEL ===== */
    .left-panel {
      background: linear-gradient(160deg, var(--accent) 0%, #c0392b 100%);
      padding: 48px 36px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      overflow: hidden;
    }

    .left-panel::before {
      content: '';
      position: absolute;
      width: 250px; height: 250px;
      border-radius: 50%;
      border: 40px solid rgba(255,255,255,.08);
      bottom: -60px; right: -60px;
    }
    .left-panel::after {
      content: '';
      position: absolute;
      width: 150px; height: 150px;
      border-radius: 50%;
      border: 30px solid rgba(255,255,255,.06);
      top: 40px; left: -40px;
    }

    .brand { position: relative; z-index: 1; }
    .brand-icon {
      width: 56px; height: 56px;
      background: rgba(255,255,255,.2);
      border-radius: 16px;
      display: flex; align-items: center; justify-content: center;
      font-size: 28px;
      margin-bottom: 20px;
      backdrop-filter: blur(10px);
    }
    .brand h1 {
      font-family: 'Montserrat', sans-serif;
      font-size: 28px; font-weight: 800;
      color: white;
      line-height: 1.1;
      letter-spacing: -0.5px;
    }
    .brand p {
      color: rgba(255,255,255,.75);
      font-size: 13px;
      margin-top: 8px;
      line-height: 1.5;
    }

    .stats-preview { position: relative; z-index: 1; }
    .stat-item {
      display: flex; align-items: center; gap: 12px;
      padding: 12px 0;
      border-bottom: 1px solid rgba(255,255,255,.15);
    }
    .stat-item:last-child { border-bottom: none; }
    .stat-dot {
      width: 8px; height: 8px; border-radius: 50%;
      background: rgba(255,255,255,.6); flex-shrink: 0;
    }
    .stat-item span { color: rgba(255,255,255,.8); font-size: 12px; }

    /* ===== RIGHT PANEL ===== */
    .right-panel {
      background: var(--white);
      padding: 48px 44px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .right-panel h2 {
      font-family: 'Montserrat', sans-serif;
      font-size: 26px; font-weight: 700;
      color: var(--navy);
      margin-bottom: 6px;
    }
    .right-panel .subtitle {
      color: var(--muted);
      font-size: 14px;
      margin-bottom: 36px;
    }

    /* ===== FORM ===== */
    .form-group { margin-bottom: 20px; }
    .form-group label {
      display: block;
      font-size: 13px; font-weight: 500;
      color: var(--text);
      margin-bottom: 8px;
    }

    .input-wrap {
      position: relative;
    }
    .input-wrap .icon {
      position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
      color: var(--muted); font-size: 16px; pointer-events: none;
    }
    .input-wrap input {
      width: 100%;
      padding: 13px 14px 13px 42px;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      font-family: 'DM Sans', sans-serif;
      font-size: 14px;
      color: var(--text);
      background: var(--light);
      transition: all .2s;
      outline: none;
    }
    .input-wrap input:focus {
      border-color: #2980d4;
      background: white;
      box-shadow: 0 0 0 3px rgba(41,128,212,.12);
    }
    .input-wrap .toggle-pw {
      position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
      cursor: pointer; color: var(--muted); font-size: 16px;
      background: none; border: none; padding: 0;
    }

    .form-options {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 28px;
    }
    .remember { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); cursor: pointer; }
    .remember input[type=checkbox] { accent-color: #2980d4; }
    .forgot { font-size: 13px; color: #2980d4; text-decoration: none; }
    .forgot:hover { text-decoration: underline; }

    .btn-login {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, #1a56a0, #2980d4);
      color: white;
      border: none;
      border-radius: 10px;
      font-family: 'Montserrat', sans-serif;
      font-size: 15px; font-weight: 700;
      letter-spacing: .3px;
      cursor: pointer;
      transition: all .2s;
      position: relative; overflow: hidden;
    }
    .btn-login:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(41,128,212,.4); }
    .btn-login:active { transform: translateY(0); }
    .btn-login.loading { pointer-events: none; opacity: .8; }

    .btn-login .spinner {
      display: none;
      width: 18px; height: 18px;
      border: 2px solid rgba(255,255,255,.4);
      border-top-color: white;
      border-radius: 50%;
      animation: spin .6s linear infinite;
      position: absolute; right: 20px; top: 50%; transform: translateY(-50%);
    }
    .btn-login.loading .spinner { display: block; }
    @keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }

    /* ===== ALERTS ===== */
    .alert {
      display: none;
      padding: 12px 16px;
      border-radius: 8px;
      font-size: 13px;
      margin-bottom: 20px;
      animation: fadeIn .3s ease;
    }
    .alert.show { display: flex; align-items: center; gap: 10px; }
    .alert-danger  { background: #fdf0ef; color: var(--danger-d);  border-left: 3px solid var(--danger); }
    .alert-success { background: #eafaf1; color: var(--success-d); border-left: 3px solid var(--success); }
    .alert-warning { background: #fef9e7; color: #e67e22;        border-left: 3px solid #e67e22; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 760px) {
      .card-wrap { grid-template-columns: 1fr; max-width: 420px; width: 95%; }
      .left-panel { display: none; }
      .right-panel { padding: 40px 28px; }
    }
  </style>
</head>
<body>

<div class="bg-pattern"></div>
<div class="bg-grid"></div>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<div class="card-wrap">

  <!-- LEFT -->
  <div class="left-panel">
    <div class="brand">
      <div class="brand-icon">📦</div>
      <h1>Stock<br>Manager</h1>
      <p>Gestion complète de votre inventaire d'équipements et consommables</p>
    </div>
    <div class="stats-preview">
      <div class="stat-item"><div class="stat-dot"></div><span>Suivi multi-sites</span></div>
      <div class="stat-item"><div class="stat-dot"></div><span>Gestion du cycle de vie</span></div>
      <div class="stat-item"><div class="stat-dot"></div><span>Rapports & exports Excel</span></div>
      <div class="stat-item"><div class="stat-dot"></div><span>Alertes & notifications</span></div>
    </div>
  </div>

  <!-- RIGHT -->
  <div class="right-panel">
    <h2>Connexion</h2>
    <p class="subtitle">Accédez à votre espace de gestion</p>

    <?php if ($timeout): ?>
    <div class="alert alert-warning show">⏱️ Votre session a expiré. Veuillez vous reconnecter.</div>
    <?php endif; ?>

    <div class="alert alert-danger" id="alert-error"></div>
    <div class="alert alert-success" id="alert-success"></div>

    <div class="form-group">
      <label for="email">Adresse email</label>
      <div class="input-wrap">
        <span class="icon">✉</span>
        <input type="email" id="email" name="email" placeholder="vous@domaine.ci" autocomplete="email" required>
      </div>
    </div>

    <div class="form-group">
      <label for="password">Mot de passe</label>
      <div class="input-wrap">
        <span class="icon">🔒</span>
        <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
        <button class="toggle-pw" type="button" onclick="togglePw()" title="Afficher/masquer">👁</button>
      </div>
    </div>

    <div class="form-options">
      <label class="remember">
        <input type="checkbox" id="remember"> Se souvenir de moi
      </label>
      <a href="forgot-password.php" class="forgot">Mot de passe oublié ?</a>
    </div>

    <button class="btn-login" id="btn-login" onclick="doLogin()">
      Se connecter
      <span class="spinner"></span>
    </button>
  </div>

</div>

<script>
  function togglePw() {
    const pw = document.getElementById('password');
    pw.type = pw.type === 'password' ? 'text' : 'password';
  }

  function showAlert(id, msg) {
    const el = document.getElementById(id);
    el.textContent = '';
    el.className = 'alert ' + (id.includes('error') ? 'alert-danger' : 'alert-success') + ' show';
    el.innerHTML = (id.includes('error') ? '❌ ' : '✅ ') + msg;
  }

  function hideAlerts() {
    ['alert-error','alert-success'].forEach(id => {
      document.getElementById(id).classList.remove('show');
    });
  }

  function doLogin() {
    hideAlerts();
    const email    = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const btn      = document.getElementById('btn-login');

    if (!email || !password) {
      showAlert('alert-error', 'Veuillez remplir tous les champs.');
      return;
    }

    btn.classList.add('loading');

    const fd = new FormData();
    fd.append('email', email);
    fd.append('password', password);

    fetch(window.location.href, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    })
    .then(r => r.json())
    .then(data => {
      btn.classList.remove('loading');
      if (data.success) {
        showAlert('alert-success', 'Connexion réussie, redirection…');
        setTimeout(() => { window.location.href = data.redirect; }, 800);
      } else {
        showAlert('alert-error', data.message || 'Erreur de connexion.');
      }
    })
    .catch(() => {
      btn.classList.remove('loading');
      showAlert('alert-error', 'Erreur réseau. Veuillez réessayer.');
    });
  }

  // Connexion avec Entrée
  document.addEventListener('keydown', e => {
    if (e.key === 'Enter') doLogin();
  });
</script>

</body>
</html>
