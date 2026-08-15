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
    /* Un changement de filtre (site, statut…) recharge la page en GET classique :
       sans ceci, le navigateur affiche un blanc le temps du aller-retour serveur.
       Avec, il fait un fondu entre l'ancienne et la nouvelle page — la navigation
       reste une vraie navigation, seul le rendu change. Ignoré sans effet de bord
       par les navigateurs qui ne le connaissent pas encore. */
    @view-transition {
      navigation: auto;
    }
    /* ===== DESIGN SYSTEM v4 — Palette Soft UI ===== */
    :root {
      /* Brand */
      --primary:    #7C92FF;
      /* -d = variante « texte lisible » : marque et texte ont des exigences de
         contraste différentes (AA texte = 4.5:1 sur fond clair), donc deux
         rôles distincts plutôt qu'une seule teinte réutilisée partout.
         #7C92FF ne passe pas (2.83:1) ; #3D4FD1 passe (6.49:1) en gardant la
         même teinte. Ne remplace jamais --primary : l'icône active de la
         sidebar (fond sombre) a besoin de la teinte claire, qui y passe déjà
         (4.94:1) — la foncer inverserait le problème. */
      --primary-d:  #3D4FD1;
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
      /* Variantes « texte lisible » des couleurs d'état, même logique que
         --primary-d ci-dessus : #F87171/#34D399/#FBBF24 ne passent pas AA en
         texte sur fond clair (2.77 / 1.92 / 1.67:1). À utiliser pour tout
         color: sur fond clair, ou tout fond plein avec texte blanc dessus
         (bouton, pastille) ; --danger/--success/--warning restent la couleur
         « marque » (bordures, puces, fonds décoratifs pâles). */
      --danger-d:   #C0392B;
      --success-d:  #0A7A52;
      --warning-d:  #8A5A00;
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
    .nav-item.active i { color: var(--primary-d); }
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
    .nav-item.active .nav-icon { background: var(--primary-d); box-shadow: 0 4px 12px rgba(124,146,255,.5); }
    .nav-item.active .nav-icon i { color: white; }
    .nav-item.nav-equip.active .nav-icon { background: rgba(124,146,255,.3); box-shadow: none; }
    .nav-item.nav-equip.active {
      color: var(--navy);
      background: rgba(255,255,255,.12);
      box-shadow: none;
    }
    .nav-item.nav-equip.active i { color: var(--primary-d); }
    .nav-badge {
      margin-left: auto;
      background: var(--primary-d);
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
    .user-card-link {
      display: flex; align-items: center; gap: 10px;
      flex: 1; min-width: 0;
      text-decoration: none; border-radius: 8px;
      padding: 4px 6px; margin: -4px -6px;
      transition: background .15s;
    }
    .user-card-link:hover { background: rgba(255,255,255,.10); }
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
      /* Item flex : sans min-width:0 il garde min-width:auto et refuse de
         descendre sous la largeur de son contenu. Un tableau large ou une
         barre d'onglets nombreuse l'étirait alors au-delà du viewport, et
         c'était toute l'application — barre du haut comprise — qui partait
         en défilement horizontal, au lieu du seul bloc concerné. */
      min-width: 0;
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
      color: var(--navy); flex: 1; min-width: 0;
      /* Pas de white-space:nowrap ici : la sous-ligne <small> (dashboard.php,
         dashboard_legacy.php) est un bloc à part, un nowrap sur le parent la
         forcerait sur la même ligne. min-width:0 suffit à corriger le bug
         (flex item qui refuse de rétrécir sous son contenu, cf. .main-wrap
         plus haut) : le titre repasse à la ligne plutôt que de pousser les
         actions de la barre du haut hors du viewport. */
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
      width: 44px; height: 44px; border-radius: 12px;
      border: 1.5px solid var(--border);
      background: var(--tertiary);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; font-size: 18px;
      transition: all .15s;
    }
    .notif-btn:hover { background: var(--primary-l); border-color: var(--primary-d); }
    .notif-count {
      position: absolute; top: -5px; right: -5px;
      background: var(--danger-d); color: white;
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
    .notif-header a  { font-size: 12px; color: var(--primary-d); text-decoration: none; font-weight: 600; }
    .notif-list { max-height: 320px; overflow-y: auto; }
    .notif-item { padding: 12px 18px; border-bottom: 1px solid var(--border); cursor: pointer; transition: background .15s; }
    .notif-item:hover { background: var(--tertiary); }
    .notif-item:last-child { border-bottom: none; }
    .notif-item .n-titre { font-size: 13px; font-weight: 600; color: var(--navy); }
    .notif-item .n-date  { font-size: 11px; color: var(--muted); margin-top: 3px; }
    .notif-empty { padding: 28px; text-align: center; color: var(--muted); font-size: 13px; }

    /* ===== PAGE CONTENT ===== */
    /* min-width:0 : même raison que .main-wrap ci-dessus, un niveau plus bas.
       Flex item par défaut refuse de descendre sous la largeur de son
       contenu (min-width:auto) — un tableau large dans .page-content
       l'étirait donc au-delà du viewport, entraînant tout .main-wrap (barre
       du haut comprise) dans le débordement au lieu de rester contenu par
       le overflow-x:auto de .table-wrap. */
    .page-content { flex: 1; min-width: 0; padding: 28px; }

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
      display: inline-flex; align-items: center; justify-content: center; gap: 7px;
      padding: 10px 20px;
      min-height: 44px; /* plancher tactile — cf. n° réunion ERP « adapt » */
      border-radius: var(--radius-sm);
      font-family: 'Manrope', sans-serif;
      font-size: 13px; font-weight: 700;
      cursor: pointer; border: none; text-decoration: none;
      transition: all .18s cubic-bezier(.4,0,.2,1);
      letter-spacing: .1px;
    }
    .btn-primary   { background: var(--primary-d); color: white; box-shadow: 0 2px 12px rgba(124,146,255,.35); }
    .btn-primary:hover { background: var(--primary-d); box-shadow: 0 4px 16px rgba(124,146,255,.45); transform: translateY(-1px); }
    .btn-secondary { background: white; color: var(--navy); border: 1.5px solid var(--border); }
    .btn-secondary:hover { background: var(--tertiary); border-color: var(--primary-d); color: var(--primary-d); }
    .btn-danger    { background: #FEE2E2; color: #991B1B; border: 1.5px solid #FCA5A5; }
    .btn-danger:hover  { background: var(--danger-d); color: white; border-color: var(--danger-d); }
    .btn-success   { background: #D1FAE5; color: #065F46; border: 1.5px solid #6EE7B7; }
    .btn-success:hover { background: var(--success-d); color: white; border-color: var(--success-d); }
    /* Exception volontaire au plancher de 44px : action secondaire répétée
       dans une colonne « Actions » de tableau (plusieurs par ligne, cf.
       equipements.php, bobines.php, commandes.php...). Un plancher de 44px
       y ferait exploser la hauteur de chaque ligne dans un tableau déjà
       dense. À revoir si l'app doit un jour cibler l'usage tactile plutôt
       que le clic souris pour ces écrans. */
    .btn-sm { padding: 6px 13px; font-size: 12px; border-radius: 8px; min-height: 32px; }

    /* ===== FORMS ===== */
    .form-row { display: grid; gap: 16px; margin-bottom: 16px; }
    .form-row.cols-2 { grid-template-columns: 1fr 1fr; }
    .form-row.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
    .form-group label {
      display: block; font-size: 12.5px; font-weight: 700;
      margin-bottom: 7px; color: var(--navy);
      font-family: 'Manrope', sans-serif; letter-spacing: .1px;
    }
    /* Marquage automatique des champs obligatoires : un seul endroit à tenir
       à jour plutôt qu'un <span>*</span> à ajouter à la main dans chaque
       formulaire (fait dans quelques pages seulement jusqu'ici). Ne s'affiche
       que pour l'attribut required natif — les champs obligatoires
       uniquement en JS (upload, motif conditionnel…) gardent leur marquage
       manuel existant, non concerné par cette règle. */
    .form-group:has(> .form-control:required) > label::after,
    .form-group:has(> input:required) > label::after,
    .form-group:has(> select:required) > label::after,
    .form-group:has(> textarea:required) > label::after {
      content: " *";
      color: var(--danger-d);
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
      border-color: var(--primary-d);
      box-shadow: 0 0 0 3px rgba(124,146,255,.15);
    }
    select.form-control { cursor: pointer; }
    textarea.form-control { resize: vertical; min-height: 80px; }

    /* ===== PAGINATION ===== */
    .pagination { display: flex; align-items: center; gap: 4px; padding: 16px; flex-wrap: wrap; }
    .page-btn {
      display: inline-flex; align-items: center; justify-content: center;
      padding: 8px 13px; min-height: 44px; min-width: 44px; box-sizing: border-box;
      border-radius: 10px;
      border: 1.5px solid var(--border);
      font-size: 13px; color: var(--text);
      text-decoration: none; transition: all .15s;
      font-weight: 500;
    }
    .page-btn:hover { background: var(--primary-l); border-color: var(--primary-d); color: var(--primary-d); }
    .page-btn.active { background: var(--primary-d); color: white; border-color: var(--primary-d); }
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
      .form-row.cols-2, .form-row.cols-3 { grid-template-columns: minmax(0,1fr); }
      /* Rail réduit à 68px : le pied de sidebar (avatar + logout) ne
         tenait déjà plus dans cette largeur avant même ce correctif — il
         débordait silencieusement, masqué par overflow-x:hidden sur
         .sidebar (donc invisible plutôt que planté). Le bouton logout est
         redondant avec celui du menu utilisateur de la barre du haut : on
         le masque ici plutôt que de le contraindre dans un espace qui n'a
         jamais été prévu pour lui, et on retire le padding du user-card
         pour que l'avatar (36px) tienne dans les 36px utiles du rail. */
      .sidebar .sidebar-footer .logout-btn { display: none; }
      .sidebar .sidebar-footer .user-card { padding: 0; justify-content: center; background: none; }
      .sidebar .sidebar-footer .user-card-link { flex: 0 0 auto; padding: 0; margin: 0; }
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

  /* ===== DARK MODE ===== */

  /* 1 — Surcharge des variables CSS : corrige tout le texte utilisant var(--text), var(--muted), etc. */
  [data-theme="dark"] {
    --text:     #E2E8F0;
    --muted:    #94A3B8;
    --border:   #2D4060;
    --tertiary: #162032;
    --white:    #1E293B;
  }

  /* 2 — Surfaces principales */
  [data-theme="dark"] body                 { background: #0F172A; color: #E2E8F0; }
  [data-theme="dark"] .main-wrap           { background: #0F172A; }
  [data-theme="dark"] .card                { background: #1E293B !important; border-color: #2D4060; }
  [data-theme="dark"] .card-header         { background: #1E293B !important; border-color: #2D4060; }
  [data-theme="dark"] .card-header h3      { color: #E2E8F0; }
  [data-theme="dark"] .card-body           { background: #1E293B; }
  [data-theme="dark"] .topbar              { background: #1A2848; border-color: #2D4060; box-shadow: 0 1px 8px rgba(0,0,0,.3); }
  [data-theme="dark"] .topbar-title        { color: #E2E8F0; }
  [data-theme="dark"] .topbar-title small  { color: #7A99BE; }

  /* 3 — Tables */
  [data-theme="dark"] th                   { background: #162032 !important; color: #94A3B8 !important; border-color: #2D4060 !important; }
  [data-theme="dark"] td                   { color: #CBD5E1; border-bottom-color: #2D4060; }
  [data-theme="dark"] tr:hover td          { background: #253349; }
  [data-theme="dark"] tr:last-child td     { border-bottom: none; }

  /* 4 — Formulaires */
  [data-theme="dark"] .form-control                { background: #0F172A; color: #E2E8F0; border-color: #334155; }
  [data-theme="dark"] .form-control:focus          { background: #162032; }
  [data-theme="dark"] .form-control::placeholder   { color: #4A6580; }
  [data-theme="dark"] .form-group label            { color: #CBD5E1; }
  [data-theme="dark"] select.form-control option   { background: #1E293B; }
  [data-theme="dark"] textarea.form-control        { background: #0F172A; color: #E2E8F0; }
  [data-theme="dark"] input[type="date"].form-control,
  [data-theme="dark"] input[type="time"].form-control { color-scheme: dark; }

  /* 5 — Boutons */
  [data-theme="dark"] .btn-secondary        { background: #1E293B; color: #CBD5E1; border-color: #334155; }
  [data-theme="dark"] .btn-secondary:hover  { background: #253349; border-color: var(--primary); color: #E2E8F0; }

  /* 6 — Alertes */
  [data-theme="dark"] .alert-danger  { background: #4A1D1D; color: #FCA5A5; border-color: #F87171; }
  [data-theme="dark"] .alert-success { background: #064E3B; color: #6EE7B7; border-color: #34D399; }
  [data-theme="dark"] .alert-warning { background: #451A03; color: #FCD34D; border-color: #FBBF24; }
  [data-theme="dark"] .alert-info    { background: #1C3B6E; color: #93C5FD; border-color: var(--primary); }

  /* 7 — Badges */
  [data-theme="dark"] .badge-dark    { background: #334155; color: #94A3B8; }
  [data-theme="dark"] .badge-success { background: #064E3B; color: #6EE7B7; }
  [data-theme="dark"] .badge-warning { background: #451A03; color: #FCD34D; }
  [data-theme="dark"] .badge-danger  { background: #4A1D1D; color: #FCA5A5; }
  [data-theme="dark"] .badge-info,
  [data-theme="dark"] .badge-primary { background: #1C3B6E; color: #93C5FD; }

  /* 8 — Pagination */
  [data-theme="dark"] .page-btn        { background: #1E293B; color: #CBD5E1; border-color: #334155; }
  [data-theme="dark"] .page-btn:hover  { background: #253349; border-color: var(--primary); color: #93C5FD; }
  [data-theme="dark"] .page-btn.active { background: var(--primary-d); color: white; border-color: var(--primary-d); }
  [data-theme="dark"] .page-info       { color: #7A99BE; }

  /* 9 — Notifications dropdown */
  [data-theme="dark"] .notif-dropdown      { background: #1E293B; border-color: #2D4060; }
  [data-theme="dark"] .notif-header        { border-color: #2D4060; }
  [data-theme="dark"] .notif-header h4     { color: #E2E8F0; }
  [data-theme="dark"] .notif-item          { border-color: #2D4060; }
  [data-theme="dark"] .notif-item:hover    { background: #253349; }
  [data-theme="dark"] .notif-item .n-titre { color: #CBD5E1; }
  [data-theme="dark"] .notif-empty         { color: #7A99BE; }

  /* 10 — User menu topbar */
  [data-theme="dark"] #user-chip           { background: #162032 !important; border-color: #2D4060 !important; }
  [data-theme="dark"] .uc-name             { color: #E2E8F0 !important; }
  [data-theme="dark"] .uc-role             { color: #7A99BE !important; }
  [data-theme="dark"] #user-menu-dd        { background: #1E293B; border-color: #2D4060; }
  [data-theme="dark"] .um-item             { color: #CBD5E1 !important; }
  [data-theme="dark"] .um-item:hover       { background: #253349 !important; }
  [data-theme="dark"] .um-danger           { color: #FCA5A5 !important; }

  /* 11 — Modaux (pattern commun : position:fixed + inner div background white) */
  [data-theme="dark"] .main-wrap [style*="background:white"],
  [data-theme="dark"] .main-wrap [style*="background: white"] {
    background: #1E293B !important;
    color: #E2E8F0;
  }
  /* Séparateurs dans les modaux */
  [data-theme="dark"] .main-wrap [style*="background:white"] [style*="border-bottom:1px solid"],
  [data-theme="dark"] .main-wrap [style*="background:white"] [style*="border-top:1px solid"] {
    border-color: #2D4060 !important;
  }

  /* 12 — Toast */
  [data-theme="dark"] .toast-success { background: #064E3B; color: #6EE7B7; border-left-color: #34D399; }
  [data-theme="dark"] .toast-danger  { background: #4A1D1D; color: #FCA5A5; border-left-color: #F87171; }
  [data-theme="dark"] .toast-warning { background: #451A03; color: #FCD34D; border-left-color: #FBBF24; }
  [data-theme="dark"] .toast-info    { background: #1C3B6E; color: #93C5FD; border-left-color: var(--primary); }

  /* 13 — Éléments inline courants dans les pages */
  [data-theme="dark"] .table-wrap          { background: #1E293B; }
  [data-theme="dark"] .vue-table tbody tr:nth-child(even) td { background: #1A2848; }
  [data-theme="dark"] .vue-table tr.total-row td { background: #0F172A !important; color: #E2E8F0 !important; }
  [data-theme="dark"] h2, [data-theme="dark"] h3,
  [data-theme="dark"] h4, [data-theme="dark"] h5 { color: #E2E8F0; }

  /* ═══ FIX PRINCIPAL : --navy dans le contenu principal → clair ═══
     La sidebar est <aside> hors de .main-wrap → background:var(--navy) n'est pas affecté.
     Tous les inline style="color:var(--navy)" dans les pages sont corrigés d'un coup. */
  [data-theme="dark"] .main-wrap { --navy: #E2E8F0; --blue-mid: #93C5FD; --blue-light: #93C5FD; }

  /* ═══ CARTES KPI / STAT (communes à toutes les pages) ═══ */
  [data-theme="dark"] .ik,
  [data-theme="dark"] .ek,
  [data-theme="dark"] .stat-card     { background: #1E293B !important; }
  [data-theme="dark"] .ik-val,
  [data-theme="dark"] .ek-val,
  [data-theme="dark"] .stat-val      { color: #E2E8F0 !important; }
  [data-theme="dark"] .ek-lbl,
  [data-theme="dark"] .ik-lbl,
  [data-theme="dark"] .stat-lbl      { color: #94A3B8 !important; }

  /* ═══ FILTRES SELECT inline background:white ═══ */
  [data-theme="dark"] .fsel,
  [data-theme="dark"] select[style*="background:white"],
  [data-theme="dark"] select[style*="background: white"] {
    background: #0F172A !important;
    color: #E2E8F0 !important;
    border-color: #334155 !important;
  }

  /* ═══ COULEURS HARDCODÉES dans les cellules PHP-générées ═══ */
  [data-theme="dark"] .main-wrap [style*="color:#06033A"],
  [data-theme="dark"] .main-wrap [style*="color: #06033A"]  { color: #E2E8F0 !important; }
  [data-theme="dark"] .main-wrap td[style*="color:#06033A"] { color: #E2E8F0 !important; }
  [data-theme="dark"] .main-wrap td[style*="color:#1E2B4A"] { color: #E2E8F0 !important; }
  [data-theme="dark"] .main-wrap td[style*="color:#1B75BC"] { color: #93C5FD !important; }
  /* Liens/spans colorés hardcodés */
  [data-theme="dark"] .main-wrap [style*="color:#1B75BC"]   { color: #93C5FD !important; }

  /* ═══ LIGNES TR colorées inline ═══ */
  [data-theme="dark"] tr[style*="background:#fff5f5"] td    { background: #2D1515 !important; }

  /* ═══ IMPORT EMUCI — classes spécifiques ═══ */
  [data-theme="dark"] .irb-stat-val                         { color: #E2E8F0 !important; }
  [data-theme="dark"] .irb-stat-lbl                         { color: #94A3B8 !important; }
  [data-theme="dark"] .import-result-banner.success         { background: #064E3B !important; border-color: #34D399 !important; }
  [data-theme="dark"] .import-result-banner.danger          { background: #4A1D1D !important; border-color: #F87171 !important; }
  [data-theme="dark"] .ld-title                             { color: #E2E8F0 !important; }
  [data-theme="dark"] .ld-sub                               { color: #94A3B8 !important; }

  /* ═══ TAB BUTTONS ═══ */
  [data-theme="dark"] .tab-btn       { color: #94A3B8 !important; background: transparent; border-color: #334155 !important; }
  [data-theme="dark"] .tab-btn:hover { color: #E2E8F0 !important; }
  [data-theme="dark"] .tab-btn.active { color: var(--primary-d) !important; border-color: var(--primary) !important; }

  /* ═══ COMMANDES BOBINES — statuts pills ═══ */
  [data-theme="dark"] .s-attente        { background: #451A03 !important; }
  [data-theme="dark"] .s-valide         { background: #1C3B6E !important; }
  [data-theme="dark"] .s-expedie        { background: #3D1A0A !important; }
  [data-theme="dark"] .s-recu           { background: #064E3B !important; }
  [data-theme="dark"] .st-en_attente    { background: #451A03 !important; }
  [data-theme="dark"] .st-valide        { background: #1C3B6E !important; }
  [data-theme="dark"] .st-en_preparation,
  [data-theme="dark"] .st-expedie       { background: #3D1A0A !important; }
  [data-theme="dark"] .st-recu          { background: #064E3B !important; }
  [data-theme="dark"] .st-rejete        { background: #4A1D1D !important; }
  [data-theme="dark"] .st-annule        { background: #1E293B !important; color: #94A3B8 !important; }

  /* ═══ VUE STOCK PAR SITE ═══ */
  [data-theme="dark"] .cell-nb          { color: #E2E8F0 !important; }
  [data-theme="dark"] .cell-films       { color: #7A99BE !important; }
  [data-theme="dark"] .cell-empty       { color: #334155 !important; }
  [data-theme="dark"] .tag-cours        { background: #1C3B6E !important; color: #93C5FD !important; }

  /* ═══ ÉQUIPEMENTS — badges état ═══ */
  [data-theme="dark"] .etat-hs          { background: #334155 !important; color: #E2E8F0 !important; }
  [data-theme="dark"] .main-wrap [style*="background:#e8f4f9"] { background: #1C3B6E !important; color: #93C5FD !important; }
  [data-theme="dark"] .main-wrap [style*="background:#f0f0f0"] { background: #253349 !important; color: #94A3B8 !important; }

  /* ═══ BOBINES — statuts validation ═══ */
  [data-theme="dark"] .s-in_use         { background: #064E3B !important; color: #6EE7B7 !important; }
  [data-theme="dark"] .s-reserved       { background: #1C3B6E !important; color: #93C5FD !important; }
  [data-theme="dark"] .s-declared_broken { background: #4A1D1D !important; color: #FCA5A5 !important; }
  [data-theme="dark"] .s-lost           { background: #253349 !important; color: #94A3B8 !important; }
  </style>

  <script>
  /* Appliquer le thème sauvegardé avant le rendu pour éviter le flash */
  (function () {
    var pref = localStorage.getItem('ds-theme-pref') || 'auto';
    var sys  = window.matchMedia('(prefers-color-scheme: dark)').matches;
    var t    = pref === 'dark' ? 'dark' : pref === 'light' ? 'light' : (sys ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', t);
  })();
  </script>
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
      <h1>ERP EMUCI</h1>
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
      <a href="<?= APP_URL ?>/pages/mon_profil.php" class="user-card-link" title="Mon profil">
        <div class="user-avatar"><?= strtoupper(substr($user['prenom'],0,1) . substr($user['nom'],0,1)) ?></div>
        <div class="user-info">
          <div class="name"><?= h($user['prenom'] . ' ' . $user['nom']) ?></div>
          <div class="role"><?= h($user['role_nom']) ?></div>
        </div>
      </a>
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

      <!-- User menu -->
      <div style="position:relative" id="user-menu-wrap">
        <button type="button" onclick="toggleUserMenu(event)" id="user-chip"
                style="display:flex;align-items:center;gap:10px;padding:6px 12px 6px 6px;background:var(--tertiary);border-radius:40px;border:1.5px solid var(--border);cursor:pointer;transition:all .15s;font-family:inherit">
          <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;color:white;font-size:13px;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;flex-shrink:0">
            <?= strtoupper(substr($user['prenom']??'',0,1).substr($user['nom']??'',0,1)) ?>
          </div>
          <div style="text-align:left">
            <div class="uc-name" style="font-size:12.5px;font-weight:700;color:var(--navy);font-family:'Plus Jakarta Sans',sans-serif;line-height:1.2"><?= h(($user['prenom']??'').' '.($user['nom']??'')) ?></div>
            <div class="uc-role" style="font-size:10.5px;color:var(--muted);line-height:1.2"><?= h($user['role_nom']??'') ?></div>
          </div>
          <i class="ph-duotone ph-caret-down" style="font-size:12px;color:var(--muted);margin-left:2px;flex-shrink:0;transition:transform .2s" id="user-chip-caret"></i>
        </button>

        <div id="user-menu-dd"
             style="display:none;position:absolute;top:calc(100% + 8px);right:0;width:200px;background:white;border:1.5px solid var(--border);border-radius:14px;box-shadow:0 8px 28px rgba(30,43,74,.14);z-index:300;overflow:hidden">
          <a href="<?= APP_URL ?>/pages/mon_profil.php" class="um-item"
             style="display:flex;align-items:center;gap:10px;padding:12px 16px;text-decoration:none;font-size:13px;font-weight:500;transition:background .12s;color:var(--text)"
             onmouseover="this.style.background='var(--tertiary)'" onmouseout="this.style.background=''">
            <i class="ph-duotone ph-user-circle" style="font-size:18px;color:var(--primary-d)"></i>
            Mon profil
          </a>
          <div style="height:1px;background:var(--border)"></div>
          <a href="<?= APP_URL ?>/logout.php" class="um-item um-danger"
             style="display:flex;align-items:center;gap:10px;padding:12px 16px;text-decoration:none;font-size:13px;font-weight:500;transition:background .12s;color:#991B1B"
             onmouseover="this.style.background='#FEE2E2'" onmouseout="this.style.background=''">
            <i class="ph-duotone ph-sign-out" style="font-size:18px"></i>
            Déconnexion
          </a>
        </div>
      </div>

    </div>
  </header>

  <!-- NOTIFICATION DROPDOWN -->
  <div class="notif-dropdown" id="notif-dropdown">
    <div class="notif-header">
      <h4>Notifications <?php if ($unread): ?><span style="color:var(--danger-d)">(<?= $unread ?>)</span><?php endif; ?></h4>
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
