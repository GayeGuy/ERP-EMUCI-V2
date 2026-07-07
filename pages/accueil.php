<?php
// ============================================================
//  pages/accueil.php — Page d'accueil post-connexion
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/groupes_config.php';

require_auth();

$user        = current_user();
$page_title  = 'Accueil';
$active_page = 'accueil';

// ── Sélection d'un groupe → mémorise en session et redirige
if (!empty($_GET['set_groupe'])) {
    $slug        = strtoupper(trim($_GET['set_groupe']));
    $accessibles = get_groupes_pour_role($user['role_slug'] ?? '');
    if (in_array($slug, $accessibles)) {
        $_SESSION['groupe_actif'] = $slug;
        $def   = get_groupe_def($slug);
        $first = $def['first_page'] ?? 'pages/dashboard.php';
        header('Location: ' . APP_URL . '/' . $first);
        exit;
    }
}

unset($_SESSION['groupe_actif']);

$groupes = get_groupes_utilisateur();

include __DIR__ . '/../templates/header.php';
?>
<style>
/* Masquer les items nav — les cartes ci-dessous sont la navigation */
.sidebar nav { display: none; }

/* ── Grille des espaces ─────────────────────────────────── */
.groupes-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: 16px;
  max-width: 860px;
  margin: 0 auto;
}

.groupe-bloc {
  aspect-ratio: 1 / 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 20px 16px;
  background: white;
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
  position: absolute; inset: 0;
  background: var(--g-gradient);
  opacity: 0; transition: opacity .2s;
  border-radius: 18px;
}
.groupe-bloc:hover {
  border-color: transparent;
  box-shadow: 0 10px 32px rgba(6,3,58,.16);
  transform: translateY(-4px);
}
.groupe-bloc:hover::after           { opacity: 1; }
.groupe-bloc:hover .groupe-icon-box { background: rgba(255,255,255,.22); box-shadow: none; }
.groupe-bloc:hover .groupe-text h3  { color: #fff; }
.groupe-bloc:hover .groupe-text p   { color: rgba(255,255,255,.72); }

.groupe-icon-box, .groupe-text { position: relative; z-index: 1; }

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
  transition: color .2s; line-height: 1.2;
}
.groupe-text p {
  font-size: 11px; color: var(--muted);
  line-height: 1.4; transition: color .2s;
}

.bloc-dashboard {
  background: linear-gradient(135deg, #06033A 0%, #1B75BC 100%);
  border-color: transparent;
  box-shadow: 0 4px 18px rgba(6,3,58,.25);
}
.bloc-dashboard::after              { display: none; }
.bloc-dashboard .groupe-text h3     { color: #fff; }
.bloc-dashboard .groupe-text p      { color: rgba(255,255,255,.65); }
.bloc-dashboard .groupe-icon-box    { background: rgba(255,255,255,.2); box-shadow: none; }
.bloc-dashboard:hover               { box-shadow: 0 12px 36px rgba(6,3,58,.35); transform: translateY(-4px); }

/* Dark mode */
[data-theme="dark"] .groupe-bloc            { background: #1E293B; border-color: #2D4060; }
[data-theme="dark"] .groupe-text h3         { color: #CBD5E1; }
[data-theme="dark"] .groupe-text p          { color: #64748B; }
[data-theme="dark"] .bloc-dashboard         { background: linear-gradient(135deg, #06033A 0%, #1B75BC 100%); }
[data-theme="dark"] .bloc-dashboard .groupe-text h3 { color: #fff; }
[data-theme="dark"] .bloc-dashboard .groupe-text p  { color: rgba(255,255,255,.65); }

@media (max-width: 540px) {
  .groupes-list { grid-template-columns: repeat(2, 1fr); gap: 10px; }
  .groupe-text p { display: none; }
}
</style>

<!-- Titre section -->
<div style="text-align:center;margin-bottom:28px">
  <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:1.2px">
    Mes espaces &nbsp;·&nbsp; <?= count($groupes) ?> disponible<?= count($groupes) > 1 ? 's' : '' ?>
  </div>
</div>

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

<?php include __DIR__ . '/../templates/footer.php'; ?>
