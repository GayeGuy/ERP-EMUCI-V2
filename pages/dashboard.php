<?php
// ============================================================
//  pages/dashboard.php  —  Tableau de bord, par profil
// ============================================================
//
//  Cette page ne contient plus ni requête ni mise en forme : elle demande
//  au registre les blocs du profil de l'utilisateur et les affiche. Tout
//  est dans includes/dashboard.php, qui explique le pourquoi de ce
//  découpage.
//
//  La version précédente est conservée telle quelle dans
//  pages/dashboard_legacy.php : c'est la page d'atterrissage de tout le
//  monde après connexion, un retour arrière doit être immédiat.
// ------------------------------------------------------------
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/dashboard.php';

require_auth();

$user   = current_user();
$portee = dash_portee($user);
$blocs  = dash_blocs_visibles($user);

$page_title = 'Tableau de bord';
// Le sous-titre de l'en-tête reste générique : l'échange de contenu ne
// remplace que le bloc .pdg, donc tout ce qui vit dans l'en-tête resterait
// figé sur l'ancien site après un changement de filtre. Le périmètre
// s'affiche dans la barre de filtres et sous le titre, à l'intérieur de .pdg,
// là où il se met effectivement à jour.
$page_subtitle = 'Votre activité du jour';
$active_page   = 'dashboard';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/dash_style.php';
?>

<div class="pdg">

  <div class="pdg-topbar">
    <div>
      <div class="pdg-title">Bonjour, <?= h($user['prenom']) ?></div>
      <div class="pdg-sub">
        <?php
        // date('l F') dépend de la locale du serveur, qui n'est pas garantie
        // en français sur Render. Les noms sont donc portés ici.
        $jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
        $mois  = ['','janvier','février','mars','avril','mai','juin',
                  'juillet','août','septembre','octobre','novembre','décembre'];
        echo h($jours[(int)date('w')] . ' ' . date('j') . ' ' . $mois[(int)date('n')] . ' ' . date('Y'));
        ?>
        <?= $user['role_nom'] ? ' · ' . h($user['role_nom']) : '' ?>
        <?= $portee['site_nom'] !== '' ? ' · ' . h($portee['site_nom']) : '' ?>
      </div>
    </div>
    <div class="pdg-controls">
      <?php if (count($blocs) === 0): ?>
        <span class="alrt-pill alrt-warn">Aucun bloc pour ce profil</span>
      <?php endif; ?>
      <?php $sites_filtre = dash_sites_filtrables($portee); ?>
      <?php if ($sites_filtre): ?>
      <!-- L'identifiant pdg-filter-form est ce que templates/dash_anim.php
           reconnaît : le changement passe par un échange de contenu animé,
           sans rechargement et sans perdre la position de lecture. -->
      <form method="get" id="pdg-filter-form" style="display:flex;align-items:center;gap:8px">
        <select name="site_id" class="eq-sel" aria-label="Filtrer par site"
                onchange="this.form.submit()">
          <option value="0">Tous les sites</option>
          <?php foreach ($sites_filtre as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= $portee['site_id'] === (int)$s['id'] ? 'selected' : '' ?>>
            <?= h($s['nom']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php elseif ($portee['site_nom'] !== ''): ?>
        <span class="alrt-pill alrt-ok"><?= h($portee['site_nom']) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <?php if (count($blocs) === 0): ?>
    <?php
    // Ne devrait pas arriver : le profil « general » sert de repli et
    // porte toujours des blocs. Si on est ici, c'est que la liste du
    // profil ne correspond à rien dans le registre — mieux vaut le dire
    // que d'afficher une page nue.
    ?>
    <div class="card">
      <div class="card-ttl">Rien à afficher pour le moment</div>
      <div class="card-sub">
        Votre profil ne donne accès à aucun bloc de ce tableau de bord.
        Signalez-le à un administrateur : c'est très probablement une
        configuration de permissions à compléter.
      </div>
    </div>
  <?php else: ?>
    <div class="pdg-grid">
      <?php foreach ($blocs as $bloc): ?>
        <div class="<?= ($bloc['largeur'] ?? 'demi') === 'plein' ? 'plein' : '' ?>">
          <?php dash_afficher_bloc($bloc, $portee); ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../templates/dash_anim.php'; ?>
<?php include __DIR__ . '/../templates/footer.php'; ?>
