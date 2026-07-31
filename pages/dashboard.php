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

// Le rôle « lecteur » reste sur l'ancien tableau de bord ; tous les autres
// passent au rendu v2. L'interrupteur est lu par dash_carte_debut() et
// dash_vide(), qui servent la coquille correspondante.
$v2 = !dash_role_v1($user);
dash_v2($v2);

// En v2 la rangée d'indicateurs de tête remplace le bloc « Synthèse » : le
// garder afficherait deux fois les mêmes chiffres à trente pixels d'écart.
if ($v2) {
    $blocs = array_values(array_filter($blocs, fn($b) => ($b['id'] ?? '') !== 'synthese_kpi'));

    // Les trois formes de la maquette sont propres au rendu v2 : elles ne
    // figurent pas dans dash_profils(), qui sert aussi le rôle « lecteur ».
    // On les insère ici, en tête, et seulement si les permissions suivent.
    $registre = dash_registre();
    $tete = [];
    foreach (['v2_hero', 'v2_jauge', 'v2_top_sites'] as $id) {
        if (!isset($registre[$id])) continue;
        $bloc = $registre[$id] + ['id' => $id];
        if (dash_bloc_visible($bloc, $user)) $tete[] = $bloc;
    }
    // v2_hero ouvre la page, la jauge l'accompagne, le classement suit les
    // blocs d'action : on ne repousse pas ce qui demande un geste.
    $blocs = array_merge(array_slice($tete, 0, 2), $blocs, array_slice($tete, 2));
}

include __DIR__ . '/../templates/header.php';
// dash_style.php reste chargé même en v2 : le contenu interne des blocs
// (tableaux .dtbl, listes, graphes) s'appuie dessus. dash_v2_style.php ne
// remplace que la coquille, la rangée d'indicateurs et la grille, et
// réaligne le reste depuis `.dv2`.
include __DIR__ . '/../templates/dash_style.php';
if ($v2) include __DIR__ . '/../templates/dash_v2_style.php';
?>

<?php if ($v2): ?>
<div class="dv2">
  <div class="dv2-bar"></div>

  <div class="dv2-hd">
    <div>
      <div class="dv2-h1">Bonjour, <?= h($user['prenom']) ?></div>
      <div class="dv2-h1-sub">
        <?php
        $jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
        $mois  = ['','janvier','février','mars','avril','mai','juin',
                  'juillet','août','septembre','octobre','novembre','décembre'];
        echo h($jours[(int)date('w')] . ' ' . date('j') . ' ' . $mois[(int)date('n')] . ' ' . date('Y'));
        ?>
        <?= $user['role_nom'] ? ' · ' . h($user['role_nom']) : '' ?>
        <?= $portee['site_nom'] !== '' ? ' · ' . h($portee['site_nom']) : '' ?>
      </div>
    </div>
    <div class="dv2-tools">
      <?php $sites_filtre = dash_sites_filtrables($portee); ?>
      <?php if ($sites_filtre): ?>
      <form method="get" id="pdg-filter-form" style="display:flex;align-items:center;gap:8px">
        <select name="site_id" class="dv2-btn dv2-sel" aria-label="Filtrer par site"
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
        <span class="dv2-p dv2-p-b"><i class="ph-duotone ph-map-pin"></i> <?= h($portee['site_nom']) ?></span>
      <?php endif; ?>
      <?php if (can('rapports', 'can_read')): ?>
      <a class="dv2-btn dv2-btn-pri" href="<?= APP_URL ?>/pages/rapports.php">
        <i class="ph-duotone ph-download-simple"></i> Exporter
      </a>
      <?php endif; ?>
    </div>
  </div>

  <?php $kpis = dash_v2_kpis($portee); ?>
  <?php if ($kpis): ?>
  <div class="dv2-kpis">
    <?php foreach ($kpis as $k) dash_v2_kpi($k); ?>
  </div>
  <?php endif; ?>

  <?php if (count($blocs) === 0): ?>
    <div class="dv2-c">
      <div class="dv2-c-t">Rien à afficher pour le moment</div>
      <div class="dv2-c-s" style="margin-top:6px">
        Votre profil ne donne accès à aucun bloc de ce tableau de bord.
        Signalez-le à un administrateur : c'est très probablement une
        configuration de permissions à compléter.
      </div>
    </div>
  <?php else: ?>
    <div class="dv2-grid">
      <?php
      // Un seul bloc traverse les colonnes : la carte de tête, qui ouvre la
      // page. Chaque `column-span:all` coupe le flux en deux tronçons
      // équilibrés séparément, et un tronçon court se remplit mal — c'est
      // exactement ce qui creusait les vides. Les autres blocs larges, dont
      // les grands tableaux, tiennent dans une colonne : leur corps porte
      // déjà `overflow-x:auto` et défile au lieu d'être tronqué.
      // Le dernier bloc traverse lui aussi : deux colonnes de hauteurs
      // inégales laissent un bas irrégulier, et un bloc pleine largeur en
      // pied referme la page sur une ligne droite.
      $dernier = count($blocs) - 1;
      foreach ($blocs as $rang => $bloc):
          $cls = (($bloc['id'] ?? '') === 'v2_hero' || $rang === $dernier) ? 'dv2-w-all' : '';
      ?>
        <div class="<?= $cls ?>"><?php dash_afficher_bloc($bloc, $portee); ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>
<?php else: ?>

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
<?php endif; /* fin de la branche v1 (rôle lecteur) */ ?>

<?php
// L'ordre compte : dash_charts.php définit render(), que dash_anim.php
// appelle à chaque image pour redessiner les graphes pendant l'animation.
include __DIR__ . '/../templates/dash_charts.php';
include __DIR__ . '/../templates/dash_anim.php';
include __DIR__ . '/../templates/footer.php';
?>
