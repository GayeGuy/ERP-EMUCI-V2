<?php
// ============================================================
//  pages/export.php — Menu des exports Excel
//  La génération du fichier vit désormais uniquement dans api/export.php
//  (ce fichier en était une quasi-duplication caractère pour caractère,
//  ~320 lignes de requêtes SQL à maintenir en double) — un type dans
//  l'URL redirige directement vers api/export.php, qui fait le travail.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

require_auth();
$type = trim($_GET['type'] ?? '');

if ($type !== '') {
    header('Location: ' . APP_URL . '/api/export.php?' . $_SERVER['QUERY_STRING']);
    exit;
}

// ── PAS DE TYPE : le menu "Rapports → Exports" pointe ici sans paramètre.
//    La page n'avait pas d'écran de choix, seulement le moteur de
//    génération : ouvrir "Exports" depuis le menu tombait donc toujours
//    sur "Type d'export non reconnu". On affiche ici une liste de cartes,
//    une par type, chacune filtrée par le même droit can_export que
//    l'export lui-même applique dans api/export.php.
$page_title  = 'Exports';
$active_page = 'export';
$exports = [
    ['type'=>'equipements',   'perm'=>['equipements','can_export'],   'icon'=>'ph-desktop-tower', 'label'=>'Équipements',        'desc'=>'Parc complet, état, site, utilisateur, fin de cycle.'],
    ['type'=>'mouvements',    'perm'=>['affectations','can_export'],  'icon'=>'ph-arrows-left-right','label'=>'Affectations / Mouvements', 'desc'=>'Historique des transferts, entrées, sorties.'],
    ['type'=>'consommables',  'perm'=>['consommables','can_export'],  'icon'=>'ph-cube',          'label'=>'Consommables',       'desc'=>'Stock global, seuils d\'alerte, sites.'],
    ['type'=>'livraisons',    'perm'=>['consommables','can_export'],  'icon'=>'ph-truck',         'label'=>'Livraisons',         'desc'=>'Historique des livraisons de consommables.'],
    ['type'=>'interventions', 'perm'=>['interventions','can_export'], 'icon'=>'ph-wrench',        'label'=>'Interventions',      'desc'=>'Interventions de maintenance par site.'],
    ['type'=>'audit',         'perm'=>['audit','can_export'],         'icon'=>'ph-shield-check',  'label'=>'Journal d\'audit',   'desc'=>'Actions utilisateurs (5000 dernières lignes).'],
    ['type'=>'couts_sites',   'perm'=>['rapports','can_export'],      'icon'=>'ph-coins',         'label'=>'Coûts par site',     'desc'=>'Coût total consommables par site, année en cours.',
     'extra'=>'&annee='.date('Y')],
    ['type'=>'bilan_mensuel', 'perm'=>['rapports','can_export'],      'icon'=>'ph-calendar-check','label'=>'Bilan mensuel',      'desc'=>'Bilan consommables du mois en cours.',
     'extra'=>'&annee='.date('Y').'&mois='.date('n')],
];
include __DIR__ . '/../templates/header.php';
?>
<style>
.exp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}
.exp-card{background:var(--card,#fff);border:1.5px solid var(--border,#e2e8f0);border-radius:14px;padding:18px;display:flex;flex-direction:column;gap:8px}
.exp-card i{font-size:26px;color:var(--blue,#1B75BC)}
.exp-card h4{margin:0;font-size:15px}
.exp-card p{margin:0;font-size:12.5px;color:var(--muted,#7f8c8d);flex:1}
</style>
<h2 style="margin-bottom:16px"><i class="ph ph-upload-simple" aria-hidden="true"></i> Exports</h2>
<div class="exp-grid">
  <?php foreach ($exports as $e):
    if (!can($e['perm'][0], $e['perm'][1])) continue;
  ?>
  <div class="exp-card">
    <i class="ph-duotone <?= h($e['icon']) ?>"></i>
    <h4><?= h($e['label']) ?></h4>
    <p><?= h($e['desc']) ?></p>
    <a href="?type=<?= $e['type'] ?><?= $e['extra'] ?? '' ?>" class="btn btn-primary btn-sm"><i class="ph ph-download-simple" aria-hidden="true"></i> Télécharger</a>
  </div>
  <?php endforeach; ?>
</div>
<?php
include __DIR__ . '/../templates/footer.php';
