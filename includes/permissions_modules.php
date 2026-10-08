<?php
// ============================================================
//  includes/permissions_modules.php
//  Référentiel partagé des modules gérés par le système de permissions.
//
//  Extrait de pages/admin/permissions.php (2026-10) au moment d'ajouter
//  pages/admin/user_permissions.php (exceptions par compte individuel) :
//  les deux écrans doivent afficher exactement les mêmes modules/groupes,
//  sinon une case visible sur l'un et absente de l'autre reproduirait le
//  bug de désynchronisation déjà rencontré une fois sur cette page
//  (inventaire/inventaire_rivets/inventaire_pmma/inventaire_equipements
//  présents dans le tableau mais absents de la liste JS de sauvegarde —
//  remis silencieusement à zéro à chaque clic sur Sauvegarder, 2026-08-29).
//  Source unique désormais partagée par les deux pages.
// ============================================================

// Groupes d'onglets (n° 2.7 réunion PDG — la matrice de 41 modules dans un
// seul tableau était illisible). Les clés reprennent les groupes de
// navigation de includes/groupes_config.php plutôt que la liste du CR, qui
// n'en cite que 6 et laisse 11 modules sans rattachement (écarts,
// inventaires, demandes, annuaire). Même découpage partout = un seul
// modèle mental pour l'administrateur.
$module_groupes = [
    // Le groupe DASHBOARD n'apparaissait pas ici tant qu'aucun module ne
    // s'y rattachait : dashboard.php et pdg_overview.php ne passent pas
    // par require_permission(). Sans cette entrée, le Dashboard KPI serait
    // devenu invisible sur cet écran — donc impossible à ouvrir à un rôle —
    // en rejoignant le groupe : l'onglet ne s'affiche que si $module_groupes
    // le déclare.
    'DASHBOARD'      => ['<i class="ph ph-squares-four" aria-hidden="true"></i>', 'Dashboard'],
    'STOCK'          => ['<i class="ph ph-package" aria-hidden="true"></i>', 'Stock'],
    'BOBINES'        => ['<i class="ph ph-film-strip" aria-hidden="true"></i>', 'Bobines'],
    'INVENTAIRE'     => ['<i class="ph ph-clipboard-text" aria-hidden="true"></i>', 'Inventaire'],
    'OPERATIONS'     => ['<i class="ph ph-lightning" aria-hidden="true"></i>', 'Opérations'],
    'INFORMATIQUE'   => ['<i class="ph ph-desktop-tower" aria-hidden="true"></i>', 'Informatique'],
    'RAPPORTS'       => ['<i class="ph ph-chart-bar" aria-hidden="true"></i>', 'Rapports'],
    'DEMANDES'       => ['<i class="ph ph-file-text" aria-hidden="true"></i>', 'Demandes internes'],
    // Ajouté 2026-09-10 : le module Achats (4 droits) existe en base depuis
    // sql/migration_achats_03_permissions.sql (2026-08) mais n'avait jamais
    // été exposé ici — impossible d'ajuster ces droits sans repasser par une
    // migration SQL. Même regroupement que includes/groupes_config.php.
    'ACHATS'         => ['<i class="ph ph-shopping-cart" aria-hidden="true"></i>', 'Achats'],
    'ADMINISTRATION' => ['<i class="ph ph-shield-check" aria-hidden="true"></i>', 'Administration'],
];

// Source unique des modules gérés par le système de permissions (icône +
// libellé + groupe) — utilisée à la fois pour générer les tableaux et pour
// savoir quels modules traiter à la sauvegarde (array_keys), au lieu de
// listes recopiées à la main qui finissent par diverger (cf. note en tête
// de fichier, 2026-08-29).
//
// 3e élément = groupe d'onglet. Les déstructurations à 2 éléments
// (foreach ... as [$mico,$mlbl]) restent valides : PHP ignore le surplus.
$modules = [
    // Module Achats — cf. note sur $module_groupes ci-dessus (2026-09-10).
    'achats'             => ['<i class="ph ph-list-checks" aria-hidden="true"></i>', 'Achats (FEB)', 'ACHATS'],
    'achats_dashboard'   => ['<i class="ph ph-gauge" aria-hidden="true"></i>', 'Dashboard Achats', 'ACHATS'],
    'achats_param'       => ['<i class="ph ph-sliders-horizontal" aria-hidden="true"></i>', 'Paramétrage achats', 'ACHATS'],
    'achats_suivi'       => ['<i class="ph ph-truck" aria-hidden="true"></i>', 'Suivi achats (DA/BC)', 'ACHATS'],
    // Ordre + regroupement alignés sur le menu Informatique
    // (includes/groupes_config.php) : Interventions, Rapport journalier,
    // Affectations IT, Transfert équipement. 'affectations' gouverne aussi
    // "Historique des mouvements" dans le menu Stock (can_read) : un seul
    // onglet ne peut pas représenter les deux à la fois, priorité donnée
    // ici à Informatique sur demande explicite.
    'interventions'      => ['<i class="ph ph-wrench" aria-hidden="true"></i>', 'Interventions', 'INFORMATIQUE'],
    'rapport_journalier' => ['<i class="ph ph-file-text" aria-hidden="true"></i>', 'Rapport journalier', 'INFORMATIQUE'],
    'affectations_it'    => ['<i class="ph ph-headset" aria-hidden="true"></i>', 'Affectations IT', 'INFORMATIQUE'],
    'affectations'       => ['<i class="ph ph-link" aria-hidden="true"></i>', 'Transfert équipement', 'INFORMATIQUE'],
    'bobines'            => ['<i class="ph ph-film-strip" aria-hidden="true"></i>', 'Bobines', 'STOCK'],
    'commandes'          => ['<i class="ph ph-storefront" aria-hidden="true"></i>', 'Commandes', 'STOCK'],
    'commandes_bobines'  => ['<i class="ph ph-shopping-cart" aria-hidden="true"></i>', 'Commande bobines', 'BOBINES'],
    'consommables'       => ['<i class="ph ph-flask" aria-hidden="true"></i>', 'Consommables', 'STOCK'],
    'delegations'        => ['<i class="ph ph-handshake" aria-hidden="true"></i>', 'Délégations', 'ADMINISTRATION'],
    // Scission par écran (2026-09) : "demandes" ne gouverne plus que "Mes
    // demandes" — cf. sql/migration_split_demandes_par_ecran.sql. "Types &
    // circuits" et "Circuits avancés" restent réservés admin/superadmin en
    // dur, hors table permissions : pas de module pour ces deux écrans.
    'demandes'           => ['<i class="ph ph-note-pencil" aria-hidden="true"></i>', 'Mes demandes', 'DEMANDES'],
    'demandes_new'       => ['<i class="ph ph-plus-circle" aria-hidden="true"></i>', 'Nouvelle demande', 'DEMANDES'],
    'demandes_valider'   => ['<i class="ph ph-seal-check" aria-hidden="true"></i>', 'À valider', 'DEMANDES'],
    'demandes_it'        => ['<i class="ph ph-wrench" aria-hidden="true"></i>', 'Traitements IT', 'DEMANDES'],
    'agents'             => ['<i class="ph ph-users" aria-hidden="true"></i>', 'Annuaire agents', 'DEMANDES'],
    'departements'       => ['<i class="ph ph-buildings" aria-hidden="true"></i>', 'Départements', 'ADMINISTRATION'],
    'ecarts_bobines'     => ['<i class="ph ph-warning-diamond" aria-hidden="true"></i>', 'Écarts bobines', 'INVENTAIRE'],
    'ecarts_rivets'      => ['<i class="ph ph-warning-diamond" aria-hidden="true"></i>', 'Écarts rivets', 'INVENTAIRE'],
    'ecarts_pmma'        => ['<i class="ph ph-warning-diamond" aria-hidden="true"></i>', 'Écarts PMMA', 'INVENTAIRE'],
    'ecarts_equipements' => ['<i class="ph ph-warning-diamond" aria-hidden="true"></i>', 'Écarts équipements', 'INVENTAIRE'],
    // Scission Informatique/Opérationnel (2026-09) : deux modules distincts
    // au lieu d'un seul 'equipements' couvrant les deux catégories — cf.
    // sql/migration_split_equipements_operationnel_vignette.sql.
    'equipements'        => ['<i class="ph ph-desktop" aria-hidden="true"></i>', 'Équipements Informatique', 'STOCK'],
    'equipements_operationnel' => ['<i class="ph ph-hard-hat" aria-hidden="true"></i>', 'Équipements Opérationnel', 'STOCK'],
    // Ordre aligné sur le menu Opérations (includes/groupes_config.php) :
    // Point journalier, Demande d'intervention, Suivi des observations,
    // Point EMUCI, Import EMUCI.
    'operations'         => ['<i class="ph ph-truck" aria-hidden="true"></i>', 'Points journaliers', 'OPERATIONS'],
    'observations'       => ['<i class="ph ph-chat-dots" aria-hidden="true"></i>', 'Suivi des observations', 'OPERATIONS'],
    'point_emuci'        => ['<i class="ph ph-magnifying-glass" aria-hidden="true"></i>', 'Point EMUCI', 'OPERATIONS'],
    'import_emuci'       => ['<i class="ph ph-download-simple" aria-hidden="true"></i>', 'Import EMUCI', 'OPERATIONS'],
    'inventaire'         => ['<i class="ph ph-clipboard-text" aria-hidden="true"></i>', 'Inventaire (accès module)', 'INVENTAIRE'],
    'inventaire_bobines' => ['<i class="ph ph-chart-bar" aria-hidden="true"></i>', 'Inventaire bobines', 'INVENTAIRE'],
    'inventaire_rivets'  => ['<i class="ph ph-chart-bar" aria-hidden="true"></i>', 'Inventaire rivets', 'INVENTAIRE'],
    'inventaire_pmma'    => ['<i class="ph ph-chart-bar" aria-hidden="true"></i>', 'Inventaire PMMA', 'INVENTAIRE'],
    'inventaire_equipements' => ['<i class="ph ph-chart-bar" aria-hidden="true"></i>', 'Inventaire équipements', 'INVENTAIRE'],
    'audit'              => ['<i class="ph ph-clipboard-text" aria-hidden="true"></i>', 'Journal d\'audit', 'ADMINISTRATION'],
    'kpi_dashboard'      => ['<i class="ph ph-gauge" aria-hidden="true"></i>', 'Dashboard KPI', 'DASHBOARD'],
    'nomenclatures'      => ['<i class="ph ph-tag" aria-hidden="true"></i>', 'Nomenclatures', 'ADMINISTRATION'],
    'pmma'               => ['<i class="ph ph-printer" aria-hidden="true"></i>', 'PMMA', 'STOCK'],
    'rapports'           => ['<i class="ph ph-chart-bar" aria-hidden="true"></i>', 'Rapports & Analyses', 'RAPPORTS'],
    'rapports_gsb'       => ['<i class="ph ph-clipboard-text" aria-hidden="true"></i>', 'Rapports & Exports', 'BOBINES'],
    'receptions'         => ['<i class="ph ph-package" aria-hidden="true"></i>', 'Réceptions site', 'STOCK'],
    'resume_superviseur' => ['<i class="ph ph-chart-line-up" aria-hidden="true"></i>', 'Résumé superviseur', 'RAPPORTS'],
    'rivets'             => ['<i class="ph ph-wrench" aria-hidden="true"></i>', 'Rivets', 'STOCK'],
    'simulation_stocks'  => ['<i class="ph ph-trend-up" aria-hidden="true"></i>', 'Simulation & projection', 'RAPPORTS'],
    'sites'              => ['<i class="ph ph-buildings" aria-hidden="true"></i>', 'Sites', 'ADMINISTRATION'],
    'tracabilite_endommagements' => ['<i class="ph ph-first-aid-kit" aria-hidden="true"></i>', 'Traçabilité endommagements', 'BOBINES'],
    'referentiels_operations' => ['<i class="ph ph-sliders-horizontal" aria-hidden="true"></i>', 'Référentiels & capacités', 'ADMINISTRATION'],
    'users'              => ['<i class="ph ph-users" aria-hidden="true"></i>', 'Utilisateurs', 'ADMINISTRATION'],
    'validation_stock'   => ['<i class="ph ph-check-circle" aria-hidden="true"></i>', 'Validation stock jour', 'BOBINES'],
    'stock_bobines'      => ['<i class="ph ph-chart-line-up" aria-hidden="true"></i>', 'Vue stock par site', 'BOBINES'],
    // Scission Bobines/Vignette (2026-09) : module distinct de 'bobines' —
    // cf. sql/migration_split_equipements_operationnel_vignette.sql.
    'vignette'           => ['<i class="ph ph-sticker" aria-hidden="true"></i>', 'Vignette', 'STOCK'],
];

// Modules indexés par groupe, pour le rendu des onglets. Construit depuis
// $modules (et non recopié) : un module ajouté plus haut apparaît
// forcément dans un onglet.
$modules_par_groupe = [];
foreach ($modules as $mk => $m) {
    $modules_par_groupe[$m[2] ?? 'ADMINISTRATION'][$mk] = $m;
}
