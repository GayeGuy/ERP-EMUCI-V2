<?php
// ============================================================
//  templates/header.php

// CSP permissive — compatible Chart.js (unsafe-eval) + Google Fonts
if (!headers_sent()) {
    header_remove('Content-Security-Policy');
    header("Content-Security-Policy: default-src * 'unsafe-inline' 'unsafe-eval' data: blob:; script-src * 'unsafe-inline' 'unsafe-eval' blob: data:; style-src * 'unsafe-inline' data:; font-src * data:; img-src * data: blob:;");
}
// ============================================================

$user  = current_user();
$notifs = notif_get_unread($user['id']);
$unread = count($notifs);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($page_title ?? 'Dashboard') ?> — <?= APP_NAME ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    /* ===== DESIGN SYSTEM v4 — Palette Soft UI ===== */
    :root {
      /* Brand */
      --primary:    #7C92FF;
      --primary-d:  #5B76FF;
      --primary-l:  #E8ECFF;
      --secondary:  #A5D8FF;
      --secondary-l:#E8F5FF;
      --tertiary:   #F0F4FF;
      --neutral:    #64748B;

      /* Aliases (compatibilité pages existantes) */
      --navy:       #1E2B4A;
      --blue:       #7C92FF;
      --blue-mid:   #5B76FF;
      --blue-light: #7C92FF;
      --blue-pale:  #E8ECFF;
      --accent:     #7C92FF;
      --accent-d:   #5B76FF;
      --mist:       #A5D8FF;
      --light:      #E8ECFF;
      --lighter:    #F0F4FF;
      --white:      #ffffff;
      --text:       #1E2B4A;
      --muted:      #64748B;
      --border:     #E2E8F0;
      --danger:     #F87171;
      --success:    #34D399;
      --warning:    #FBBF24;
      --info:       #7C92FF;
      --sidebar-w:  252px;
      --topbar-h:   64px;
      --radius:     16px;
      --radius-sm:  10px;
      --shadow:     0 4px 24px rgba(124,146,255,.10);
      --shadow-md:  0 8px 32px rgba(124,146,255,.15);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Manrope', sans-serif;
      background: var(--tertiary);
      color: var(--text);
      display: flex;
      min-height: 100vh;
    }

    /* ===== SIDEBAR ===== */
    .sidebar {
      width: var(--sidebar-w);
      background: var(--navy);
      height: 100vh;
      position: fixed; top: 0; left: 0; z-index: 100;
      display: flex; flex-direction: column;
      overflow-y: auto; overflow-x: hidden; scroll-behavior: auto;
      transition: width .25s ease;
      box-shadow: 4px 0 24px rgba(30,43,74,.12);
    }

    .sidebar-brand {
      padding: 22px 20px 18px;
      border-bottom: 1px solid rgba(255,255,255,.08);
      display: flex; align-items: center; gap: 12px;
    }
    .brand-logo {
      width: 42px; height: 42px; border-radius: 14px;
      background: linear-gradient(135deg, var(--primary), #00aeef);
      display: flex; align-items: center; justify-content: center;
      font-size: 20px; flex-shrink: 0;
      box-shadow: 0 4px 14px rgba(124,146,255,.4);
    }
    .brand-text h1 {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 16px; font-weight: 800; color: white;
      letter-spacing: -.3px;
    }
    .brand-text span { font-size: 11px; color: rgba(165,216,255,.6); }

    .sidebar-nav { flex: 1; padding: 12px 0; }

    .nav-section {
      padding: 16px 20px 5px;
      font-size: 10px; font-weight: 700; letter-spacing: 1.4px;
      color: rgba(255,255,255,.35);
      text-transform: uppercase;
    }

    .nav-item {
      display: flex; align-items: center; gap: 11px;
      padding: 10px 16px;
      margin: 2px 10px;
      color: rgba(255,255,255,.7);
      text-decoration: none;
      font-size: 13.5px; font-weight: 500;
      border-radius: 12px;
      border-left: none;
      transition: all .18s cubic-bezier(.4,0,.2,1);
    }
    .nav-item:hover {
      color: white;
      background: rgba(255,255,255,.08);
    }
    .nav-item.active {
      color: var(--navy);
      background: white;
      font-weight: 700;
      box-shadow: 0 2px 12px rgba(0,0,0,.12);
    }
    .nav-item.active i { color: var(--primary); }
    .nav-item .nav-icon {
      width: 34px; height: 34px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      border-radius: 10px;
      background: rgba(255,255,255,.1);
      transition: all .18s;
    }
    .nav-item .nav-icon i { font-size: 17px; line-height: 1; color: white; transition: color .18s; }
    .nav-item:hover .nav-icon { background: rgba(255,255,255,.18); }
    .nav-item.active .nav-icon { background: var(--primary); box-shadow: 0 4px 12px rgba(124,146,255,.5); }
    .nav-item.active .nav-icon i { color: white; }
    .nav-item.nav-equip.active .nav-icon { background: rgba(124,146,255,.3); box-shadow: none; }
    .nav-item.nav-equip.active {
      color: var(--navy);
      background: rgba(255,255,255,.12);
      box-shadow: none;
    }
    .nav-item.nav-equip.active i { color: var(--primary); }
    .nav-badge {
      margin-left: auto;
      background: var(--primary);
      color: white; font-size: 10px; font-weight: 700;
      padding: 2px 7px; border-radius: 20px;
    }

    .sidebar-footer {
      padding: 14px 16px;
      border-top: 1px solid rgba(255,255,255,.08);
      margin: 0 0 4px;
    }
    .user-card {
      display: flex; align-items: center; gap: 10px;
      padding: 10px 12px;
      border-radius: 12px;
      background: rgba(255,255,255,.06);
    }
    .user-avatar {
      width: 36px; height: 36px; border-radius: 10px;
      background: linear-gradient(135deg, var(--primary), #A5D8FF);
      display: flex; align-items: center; justify-content: center;
      color: white; font-size: 13px; font-weight: 700; flex-shrink: 0;
    }
    .user-info { flex: 1; min-width: 0; }
    .user-info .name { font-size: 12.5px; font-weight: 700; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-info .role { font-size: 10.5px; color: rgba(165,216,255,.6); margin-top: 1px; }
    .logout-btn { color: rgba(255,255,255,.55); text-decoration: none; font-size: 18px; transition: color .15s, background .15s; flex-shrink: 0; width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; }
    .logout-btn:hover { color: #f87171; background: rgba(248,113,113,.12); }

    /* ===== MAIN AREA ===== */
    .main-wrap {
      margin-left: var(--sidebar-w);
      flex: 1;
      display: flex; flex-direction: column;
      min-height: 100vh;
    }

    /* ===== TOP BAR ===== */
    .topbar {
      height: var(--topbar-h);
      background: white;
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center;
      padding: 0 28px;
      gap: 16px;
      position: sticky; top: 0; z-index: 50;
      box-shadow: 0 1px 8px rgba(124,146,255,.06);
    }
    .topbar-title {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 17px; font-weight: 700;
      color: var(--navy); flex: 1;
    }
    .topbar-title small {
      display: block;
      font-family: 'Manrope', sans-serif;
      font-size: 12px; font-weight: 400; color: var(--muted);
      margin-top: 1px;
    }
    .topbar-actions { display: flex; align-items: center; gap: 10px; }

    .notif-btn {
      position: relative;
      width: 40px; height: 40px; border-radius: 12px;
      border: 1.5px solid var(--border);
      background: var(--tertiary);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; font-size: 18px;
      transition: all .15s;
    }
    .notif-btn:hover { background: var(--primary-l); border-color: var(--primary); }
    .notif-count {
      position: absolute; top: -5px; right: -5px;
      background: var(--danger); color: white;
      font-size: 10px; font-weight: 700;
      width: 18px; height: 18px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
    }

    /* ===== NOTIFICATION DROPDOWN ===== */
    .notif-dropdown {
      display: none;
      position: absolute; top: calc(var(--topbar-h) + 4px); right: 28px;
      width: 340px;
      background: white;
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow-md);
      z-index: 200;
    }
    .notif-dropdown.open { display: block; animation: dropDown .2s ease; }
    @keyframes dropDown { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
    .notif-header {
      padding: 16px 18px;
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
    }
    .notif-header h4 { font-size: 14px; font-weight: 700; color: var(--navy); font-family: 'Plus Jakarta Sans',sans-serif; }
    .notif-header a  { font-size: 12px; color: var(--primary); text-decoration: none; font-weight: 600; }
    .notif-list { max-height: 320px; overflow-y: auto; }
    .notif-item { padding: 12px 18px; border-bottom: 1px solid var(--border); cursor: pointer; transition: background .15s; }
    .notif-item:hover { background: var(--tertiary); }
    .notif-item:last-child { border-bottom: none; }
    .notif-item .n-titre { font-size: 13px; font-weight: 600; color: var(--navy); }
    .notif-item .n-date  { font-size: 11px; color: var(--muted); margin-top: 3px; }
    .notif-empty { padding: 28px; text-align: center; color: var(--muted); font-size: 13px; }

    /* ===== PAGE CONTENT ===== */
    .page-content { flex: 1; padding: 28px; }

    /* ===== BADGES ===== */
    .badge { padding: 4px 11px; border-radius: 20px; font-size: 11.5px; font-weight: 600; letter-spacing: .2px; }
    .badge-success { background: #D1FAE5; color: #065F46; }
    .badge-info    { background: var(--primary-l); color: var(--primary-d); }
    .badge-warning { background: #FEF3C7; color: #92400E; }
    .badge-danger  { background: #FEE2E2; color: #991B1B; }
    .badge-dark    { background: #F1F5F9; color: var(--neutral); }
    .badge-primary { background: var(--primary-l); color: var(--primary-d); }

    /* ===== CARDS ===== */
    .card {
      background: white;
      border-radius: var(--radius);
      border: 1.5px solid var(--border);
      overflow: hidden;
      box-shadow: var(--shadow);
    }
    .card-header {
      padding: 18px 22px;
      border-bottom: 1.5px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
    }
    .card-header h3 {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 15px; font-weight: 700; color: var(--navy);
    }
    .card-body { padding: 22px; }

    /* ===== TABLES ===== */
    .table-wrap { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th {
      background: var(--tertiary) !important;
      color: var(--muted) !important;
      padding: 12px 16px;
      font-size: 11px; font-weight: 700; letter-spacing: .8px;
      text-align: left;
      border-bottom: 1.5px solid var(--border);
      white-space: nowrap;
      text-transform: uppercase;
      font-family: 'Manrope', sans-serif;
    }
    td {
      padding: 13px 16px;
      font-size: 13.5px;
      border-bottom: 1px solid var(--border);
      vertical-align: middle;
      color: var(--text);
    }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: var(--tertiary); }

    /* ===== BUTTONS ===== */
    .btn {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 10px 20px;
      border-radius: var(--radius-sm);
      font-family: 'Manrope', sans-serif;
      font-size: 13px; font-weight: 700;
      cursor: pointer; border: none; text-decoration: none;
      transition: all .18s cubic-bezier(.4,0,.2,1);
      letter-spacing: .1px;
    }
    .btn-primary   { background: var(--primary); color: white; box-shadow: 0 2px 12px rgba(124,146,255,.35); }
    .btn-primary:hover { background: var(--primary-d); box-shadow: 0 4px 16px rgba(124,146,255,.45); transform: translateY(-1px); }
    .btn-secondary { background: white; color: var(--navy); border: 1.5px solid var(--border); }
    .btn-secondary:hover { background: var(--tertiary); border-color: var(--primary); color: var(--primary-d); }
    .btn-danger    { background: #FEE2E2; color: #991B1B; border: 1.5px solid #FCA5A5; }
    .btn-danger:hover  { background: var(--danger); color: white; border-color: var(--danger); }
    .btn-success   { background: #D1FAE5; color: #065F46; border: 1.5px solid #6EE7B7; }
    .btn-success:hover { background: var(--success); color: white; border-color: var(--success); }
    .btn-sm { padding: 6px 13px; font-size: 12px; border-radius: 8px; }

    /* ===== FORMS ===== */
    .form-row { display: grid; gap: 16px; margin-bottom: 16px; }
    .form-row.cols-2 { grid-template-columns: 1fr 1fr; }
    .form-row.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
    .form-group label {
      display: block; font-size: 12.5px; font-weight: 700;
      margin-bottom: 7px; color: var(--navy);
      font-family: 'Manrope', sans-serif; letter-spacing: .1px;
    }
    .form-control {
      width: 100%; padding: 11px 14px;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm); font-family: 'Manrope', sans-serif;
      font-size: 13.5px; color: var(--text);
      background: white; outline: none;
      transition: border-color .15s, box-shadow .15s;
    }
    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(124,146,255,.15);
    }
    select.form-control { cursor: pointer; }
    textarea.form-control { resize: vertical; min-height: 80px; }

    /* ===== PAGINATION ===== */
    .pagination { display: flex; align-items: center; gap: 4px; padding: 16px; flex-wrap: wrap; }
    .page-btn {
      padding: 8px 13px; border-radius: 10px;
      border: 1.5px solid var(--border);
      font-size: 13px; color: var(--text);
      text-decoration: none; transition: all .15s;
      font-weight: 500;
    }
    .page-btn:hover { background: var(--primary-l); border-color: var(--primary); color: var(--primary-d); }
    .page-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
    .page-info { margin-left: auto; font-size: 12px; color: var(--muted); }

    /* ===== ALERTS ===== */
    .alert { padding: 13px 18px; border-radius: var(--radius-sm); font-size: 13.5px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
    .alert-danger  { background: #FEE2E2; color: #991B1B; border-left: 4px solid var(--danger); }
    .alert-success { background: #D1FAE5; color: #065F46; border-left: 4px solid var(--success); }
    .alert-warning { background: #FEF3C7; color: #92400E; border-left: 4px solid var(--warning); }
    .alert-info    { background: var(--primary-l); color: var(--primary-d); border-left: 4px solid var(--primary); }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 900px) {
      .sidebar { width: 68px; }
      .sidebar .brand-text, .sidebar .nav-section,
      .sidebar .nav-item span:not(.nav-icon), .sidebar .sidebar-footer .user-info,
      .sidebar .nav-badge { display: none; }
      .main-wrap { margin-left: 68px; }
      .form-row.cols-2, .form-row.cols-3 { grid-template-columns: 1fr; }
    }
  </style>
  <script src="https://unpkg.com/@phosphor-icons/web@2.1.1/src/index.js"></script>

  <style>
  /* ===== SIDEBAR — Groupe actif badge ===== */
  .nav-group-label {
    display: flex; align-items: center; gap: 8px;
    padding: 14px 20px 6px;
    font-size: 10px; font-weight: 700; letter-spacing: 1.4px;
    color: rgba(255,255,255,.35);
    text-transform: uppercase;
    border-top: 1px solid rgba(255,255,255,.07);
    margin-top: 4px;
  }
  .nav-group-label i { font-size: 13px; opacity: .6; }

  /* Accueil button — toujours visible */
  .nav-item-home {
    margin: 8px 10px 2px !important;
    background: rgba(255,255,255,.05) !important;
    border: 1px solid rgba(255,255,255,.1) !important;
  }
  .nav-item-home:hover {
    background: rgba(255,255,255,.12) !important;
    border-color: rgba(255,255,255,.2) !important;
  }
  .nav-item-home.active {
    background: rgba(0,174,239,.2) !important;
    border-color: rgba(0,174,239,.4) !important;
    color: #00AEEF !important;
  }
  .nav-item-home.active .nav-icon { background: rgba(0,174,239,.3) !important; box-shadow: 0 4px 12px rgba(0,174,239,.4) !important; }
  .nav-item-home.active .nav-icon i { color: #00AEEF !important; }
  </style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-logo">
      <svg viewBox="0 0 26 26" width="26" height="26" fill="none">
        <rect x="1.5" y="1.5" width="10" height="10" rx="2.5" fill="white" opacity=".95"/>
        <rect x="14.5" y="1.5" width="10" height="10" rx="2.5" fill="white" opacity=".5"/>
        <rect x="1.5" y="14.5" width="10" height="10" rx="2.5" fill="white" opacity=".5"/>
        <rect x="14.5" y="14.5" width="10" height="10" rx="2.5" fill="white" opacity=".95"/>
      </svg>
    </div>
    <div class="brand-text">
      <h1>DigiStock</h1>
      <span>by EMUCI</span>
    </div>
  </div>
  <nav class="sidebar-nav">
    <?php
    if (!function_exists('get_groupe_def')) {
        require_once __DIR__ . '/../includes/groupes_config.php';
    }
    $groupe_actif = $_SESSION['groupe_actif'] ?? null;
    ?>

    <!-- ── Bouton Accueil — toujours visible ── -->
    <a href="<?= APP_URL ?>/pages/accueil.php"
       class="nav-item nav-item-home <?= ($active_page??'')==='accueil'?'active':'' ?>">
      <span class="nav-icon"><i class="ph-duotone ph-house-simple"></i></span>
      <span>Accueil</span>
    </a>

    <?php if ($groupe_actif): ?>
      <?php
      $g_def   = get_groupe_def($groupe_actif);
      $g_items = get_groupe_nav_items($groupe_actif);
      if ($g_def && $g_items):
      ?>

      <!-- Label du groupe actif -->
      <div class="nav-group-label">
        <i class="ph-duotone <?= h($g_def['icon']) ?>"></i>
        <span><?= h($g_def['titre']) ?></span>
      </div>

      <?php foreach ($g_items as $item): ?>
      <a href="<?= APP_URL ?>/<?= h($item['url']) ?>"
         class="nav-item <?= in_array($active_page??'', $item['active_keys']) ? 'active' : '' ?>">
        <span class="nav-icon"><i class="ph-duotone <?= h($item['icon']) ?>"></i></span>
        <span><?= h($item['label']) ?></span>
      </a>
      <?php endforeach; ?>

      <?php endif; ?>
    <?php endif; ?>

  </nav>
  <div class="sidebar-footer">
    <div class="user-card">
      <div class="user-avatar"><?= strtoupper(substr($user['prenom'],0,1) . substr($user['nom'],0,1)) ?></div>
      <div class="user-info">
        <div class="name"><?= h($user['prenom'] . ' ' . $user['nom']) ?></div>
        <div class="role"><?= h($user['role_nom']) ?></div>
      </div>
      <a href="<?= APP_URL ?>/logout.php" class="logout-btn" title="Déconnexion">
        <i class="ph-duotone ph-sign-out"></i>
      </a>
    </div>
  </div>
</aside>

<div class="main-wrap">

  <!-- TOP BAR -->
  <header class="topbar">
    <div class="topbar-title">
      <?= h($page_title ?? 'Dashboard') ?>
      <?php if (!empty($page_subtitle)): ?>
        <small><?= h($page_subtitle) ?></small>
      <?php endif; ?>
    </div>
    <div class="topbar-actions">

      <!-- Notifications -->
      <button class="notif-btn" onclick="toggleNotifs()" title="Notifications">
        <i class="ph-duotone ph-bell" style="font-size:20px;color:var(--muted)"></i>
        <?php if ($unread > 0): ?>
          <span class="notif-count"><?= $unread > 9 ? '9+' : $unread ?></span>
        <?php endif; ?>
      </button>

      <!-- User -->
      <div style="display:flex;align-items:center;gap:10px;padding:6px 14px 6px 6px;background:var(--tertiary);border-radius:40px;border:1.5px solid var(--border)">
        <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;color:white;font-size:13px;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif">
          <?= strtoupper(substr($user['prenom']??'',0,1).substr($user['nom']??'',0,1)) ?>
        </div>
        <div>
          <div style="font-size:12.5px;font-weight:700;color:var(--navy);font-family:'Plus Jakarta Sans',sans-serif;line-height:1.2"><?= h(($user['prenom']??'').' '.($user['nom']??'')) ?></div>
          <div style="font-size:10.5px;color:var(--muted);line-height:1.2"><?= h($user['role_nom']??'') ?></div>
        </div>
      </div>

    </div>
  </header>

  <!-- NOTIFICATION DROPDOWN -->
  <div class="notif-dropdown" id="notif-dropdown">
    <div class="notif-header">
      <h4>Notifications <?php if ($unread): ?><span style="color:var(--danger)">(<?= $unread ?>)</span><?php endif; ?></h4>
      <a href="javascript:void(0)" onclick="markAllRead()">Tout marquer lu</a>
    </div>
    <div class="notif-list">
      <?php if (empty($notifs)): ?>
        <div class="notif-empty">✅ Aucune notification</div>
      <?php else: ?>
        <?php foreach ($notifs as $n): ?>
          <div class="notif-item" onclick="readNotif(<?= $n['id'] ?>, '<?= h($n['lien']??'') ?>')">
            <div class="n-titre"><?= h($n['titre']??'') ?></div>
            <div class="n-date"><?= fmt_datetime($n['created_at']??'') ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- PAGE CONTENT starts here -->
  <main class="page-content">
