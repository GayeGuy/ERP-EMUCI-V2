<?php
// ============================================================
//  pages/accueil.php — Page d'accueil post-connexion (standalone)
//  Pas de sidebar — navbar minimaliste + blocs groupes
// ============================================================
if (!headers_sent()) {
    header_remove('Content-Security-Policy');
    header("Content-Security-Policy: default-src * 'unsafe-inline' 'unsafe-eval' data: blob:;");
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/groupes_config.php';

require_auth();

$user = current_user();

// ── Sélection d'un groupe → mémorise en session et redirige
if (!empty($_GET['set_groupe'])) {
    $slug = strtoupper(trim($_GET['set_groupe']));
    $accessibles = get_groupes_pour_role($user['role_slug'] ?? '');
    if (in_array($slug, $accessibles)) {
        $_SESSION['groupe_actif'] = $slug;
        $def = get_groupe_def($slug);
        $first = $def['first_page'] ?? 'pages/dashboard.php';
        header('Location: ' . APP_URL . '/' . $first);
        exit;
    }
}

// ── Effacer le groupe actif quand on revient à l'accueil
unset($_SESSION['groupe_actif']);

$groupes = get_groupes_utilisateur();
$initiales = strtoupper(substr($user['prenom']??'',0,1).substr($user['nom']??'',0,1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Accueil — <?= APP_NAME ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/@phosphor-icons/web@2.1.1/src/index.js"></script>

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --navy:    #06033A;
      --blue:    #1B75BC;
      --sky:     #00AEEF;
      --text:    #1E2B4A;
      --muted:   #64748B;
      --border:  #E2E8F0;
      --bg:      #F4F7FB;
      --white:   #ffffff;
    }

    body {
      font-family: 'Manrope', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ===== NAVBAR ===== */
    .navbar {
      height: 60px;
      background: var(--white);
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 32px;
      position: sticky; top: 0; z-index: 100;
      box-shadow: 0 1px 8px rgba(6,3,58,.06);
    }

    /* Brand / Accueil (gauche) */
    .navbar-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      color: var(--text);
    }
    .navbar-brand-logo {
      width: 36px; height: 36px;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--navy), var(--blue));
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .navbar-brand-logo svg { display: block; }
    .navbar-brand-name {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 16px; font-weight: 800;
      color: var(--navy);
      letter-spacing: -.3px;
    }
    .navbar-brand-sep {
      width: 1px; height: 18px;
      background: var(--border);
      margin: 0 4px;
    }
    .navbar-accueil {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 14px; font-weight: 600;
      color: var(--blue);
    }

    /* Droite navbar */
    .navbar-right {
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .navbar-user {
      display: flex;
      align-items: center;
      gap: 9px;
    }
    .navbar-avatar {
      width: 34px; height: 34px;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--blue), var(--sky));
      display: flex; align-items: center; justify-content: center;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 12px; font-weight: 800;
      color: white; flex-shrink: 0;
    }
    .navbar-user-info { line-height: 1.3; }
    .navbar-user-name {
      font-size: 13px; font-weight: 700;
      color: var(--navy);
    }
    .navbar-user-role {
      font-size: 11px;
      color: var(--muted);
    }
    .navbar-sep {
      width: 1px; height: 24px;
      background: var(--border);
    }
    .navbar-logout {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 8px 14px;
      border-radius: 8px;
      border: 1.5px solid var(--border);
      background: var(--white);
      color: var(--muted);
      font-size: 13px; font-weight: 600;
      text-decoration: none;
      transition: all .15s;
      font-family: 'Manrope', sans-serif;
    }
    .navbar-logout:hover {
      border-color: #F87171;
      color: #991B1B;
      background: #FEE2E2;
    }
    .navbar-logout i { font-size: 16px; }

    /* ===== PAGE BODY ===== */
    .page-body {
      flex: 1;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: 40px 24px 60px;
    }

    .accueil-wrap {
      width: 100%;
      max-width: 860px;
    }

    /* Titre section */
    .accueil-subtitle {
      font-size: 12px; font-weight: 700;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 1.2px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .accueil-subtitle::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }

    /* ===== GRILLE GROUPES ===== */
    .groupes-list {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 14px;
    }

    /* Carré : aspect-ratio forcé */
    .groupe-bloc {
      aspect-ratio: 1 / 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 12px;
      padding: 20px 16px;
      background: var(--white);
      border-radius: 18px;
      border: 1.5px solid var(--border);
      box-shadow: 0 2px 10px rgba(6,3,58,.05);
      text-decoration: none;
      color: inherit;
      transition: all .2s cubic-bezier(.4,0,.2,1);
      position: relative;
      overflow: hidden;
      text-align: center;
    }
    .groupe-bloc::after {
      content: '';
      position: absolute;
      inset: 0;
      background: var(--g-gradient);
      opacity: 0;
      transition: opacity .2s;
      border-radius: 18px;
    }
    .groupe-bloc:hover {
      border-color: transparent;
      box-shadow: 0 10px 32px rgba(6,3,58,.16);
      transform: translateY(-4px);
    }
    .groupe-bloc:hover::after { opacity: 1; }
    .groupe-bloc:hover .groupe-icon-box { background: rgba(255,255,255,.22); box-shadow: none; }
    .groupe-bloc:hover .groupe-text h3  { color: #fff; }
    .groupe-bloc:hover .groupe-text p   { color: rgba(255,255,255,.72); }

    /* Contenu au-dessus du ::after */
    .groupe-icon-box,
    .groupe-text { position: relative; z-index: 1; }

    .groupe-icon-box {
      width: 58px; height: 58px;
      border-radius: 16px;
      background: var(--g-gradient);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      box-shadow: 0 4px 14px rgba(6,3,58,.18);
      transition: background .2s, box-shadow .2s;
    }
    .groupe-icon-box i { font-size: 28px; color: white; }

    .groupe-text { text-align: center; }
    .groupe-text h3 {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 13.5px; font-weight: 700;
      color: var(--navy);
      margin-bottom: 4px;
      transition: color .2s;
      line-height: 1.2;
    }
    .groupe-text p {
      font-size: 11px;
      color: var(--muted);
      line-height: 1.4;
      transition: color .2s;
    }

    /* Dashboard — toujours avec fond coloré */
    .bloc-dashboard {
      background: linear-gradient(135deg, #06033A 0%, #1B75BC 100%);
      border-color: transparent;
      box-shadow: 0 4px 18px rgba(6,3,58,.25);
    }
    .bloc-dashboard::after { display: none; }
    .bloc-dashboard .groupe-text h3 { color: #fff; }
    .bloc-dashboard .groupe-text p  { color: rgba(255,255,255,.65); }
    .bloc-dashboard .groupe-icon-box {
      background: rgba(255,255,255,.2);
      box-shadow: none;
    }
    .bloc-dashboard:hover {
      box-shadow: 0 12px 36px rgba(6,3,58,.35);
      transform: translateY(-4px);
    }

    @media (max-width: 700px) {
      .groupes-list { grid-template-columns: repeat(3, 1fr); gap: 10px; }
      .groupe-icon-box { width: 46px; height: 46px; border-radius: 12px; }
      .groupe-icon-box i { font-size: 22px; }
      .groupe-text p { display: none; }
    }
    @media (max-width: 480px) {
      .navbar { padding: 0 14px; }
      .navbar-brand-sep, .navbar-accueil { display: none; }
      .navbar-user-info { display: none; }
      .page-body { padding: 20px 12px 40px; }
      .groupes-list { grid-template-columns: repeat(3, 1fr); gap: 8px; }
      .groupe-bloc { border-radius: 14px; gap: 8px; }
      .groupe-text h3 { font-size: 12px; }
    }
  </style>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="navbar">

  <!-- Gauche : Logo + "Accueil" -->
  <a href="<?= APP_URL ?>/pages/accueil.php" class="navbar-brand">
    <div class="navbar-brand-logo">
      <svg viewBox="0 0 22 22" width="22" height="22" fill="none">
        <rect x="1" y="1" width="8.5" height="8.5" rx="2" fill="white" opacity=".95"/>
        <rect x="12.5" y="1" width="8.5" height="8.5" rx="2" fill="white" opacity=".5"/>
        <rect x="1" y="12.5" width="8.5" height="8.5" rx="2" fill="white" opacity=".5"/>
        <rect x="12.5" y="12.5" width="8.5" height="8.5" rx="2" fill="white" opacity=".95"/>
      </svg>
    </div>
    <span class="navbar-brand-name">DigiStock</span>
    <span class="navbar-brand-sep"></span>
    <span class="navbar-accueil">Accueil</span>
  </a>

  <!-- Droite : Utilisateur + Déconnexion -->
  <div class="navbar-right">
    <div class="navbar-user">
      <div class="navbar-avatar"><?= $initiales ?></div>
      <div class="navbar-user-info">
        <div class="navbar-user-name"><?= h(($user['prenom']??'').' '.($user['nom']??'')) ?></div>
        <div class="navbar-user-role"><?= h($user['role_nom']??'') ?></div>
      </div>
    </div>
    <div class="navbar-sep"></div>
    <a href="<?= APP_URL ?>/logout.php" class="navbar-logout">
      <i class="ph-duotone ph-sign-out"></i>
      Déconnexion
    </a>
  </div>

</nav>

<!-- ===== CONTENU ===== -->
<div class="page-body">
  <div class="accueil-wrap">

    <div class="accueil-subtitle">Mes espaces (<?= count($groupes) ?>)</div>

    <div class="groupes-list">
      <?php foreach ($groupes as $slug => $groupe): ?>
      <a href="?set_groupe=<?= urlencode($slug) ?>"
         class="groupe-bloc <?= $slug === 'DASHBOARD' ? 'bloc-dashboard' : '' ?>"
         style="--g-gradient: <?= h($groupe['gradient']) ?>">

        <div class="groupe-icon-box">
          <i class="ph-duotone <?= h($groupe['icon']) ?>"></i>
        </div>

        <div class="groupe-text">
          <h3><?= h($groupe['titre']) ?></h3>
          <p><?= h($groupe['description']) ?></p>
        </div>

      </a>
      <?php endforeach; ?>
    </div>

  </div>
</div>

</body>
</html>
