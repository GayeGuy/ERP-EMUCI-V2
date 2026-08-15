<?php
// ============================================================
//  includes/groupes_config.php — Configuration centralisée des groupes de menus
//  7 groupes — aucune page en double
// ============================================================

define('TOUS_LES_GROUPES', [
    'DASHBOARD','OPERATIONS','BOBINES','STOCK',
    'INFORMATIQUE','RAPPORTS','DEMANDES','ACHATS','ADMINISTRATION'
]);

function _groupes_def(): array {
    return [

        // ── 1. DASHBOARD
        'DASHBOARD' => [
            'icon'        => 'ph-squares-four',
            'titre'       => 'Dashboard',
            'description' => 'Vue d\'ensemble et indicateurs clés de performance',
            'couleur'     => '#06033A',
            'gradient'    => 'linear-gradient(135deg, #06033A 0%, #1B75BC 100%)',
            'first_page'  => 'pages/dashboard.php',
            'nav' => [
                // Le lecteur n'a que la Vue exécutive : le tableau de bord
                // opérationnel ne lui sert pas.
                ['label'=>'Tableau de bord','icon'=>'ph-squares-four',
                 'url'=>'pages/dashboard.php','active_keys'=>['dashboard'],
                 'roles_exclude'=>['lecteur']],
                // n° 07 réunion ERP : visible par tous les comptes, pas
                // réservée au lecteur — second bouton menu vers la même vue
                // que celle du PDG.
                ['label'=>'Vue exécutive','icon'=>'ph-chart-pie-slice',
                 'url'=>'pages/pdg_overview.php','active_keys'=>['pdg_overview']],
            ],
        ],

        // ── 2. STOCK (équipements + articles + réceptions)
        'STOCK' => [
            'icon'        => 'ph-package',
            'titre'       => 'Stock',
            'description' => 'Équipements, articles, affectations et inventaire',
            'couleur'     => '#1B75BC',
            'gradient'    => 'linear-gradient(135deg, #1B75BC 0%, #00AEEF 100%)',
            'first_page'  => 'pages/equipements.php',
            'nav' => [
                ['label'=>'Équipements',   'icon'=>'ph-monitor',
                 'url'=>'pages/equipements.php',
                 'active_keys'=>['equipements','equipements_info','equipements_op']],
                ['label'=>'Articles',      'icon'=>'ph-cube',
                 'url'=>'pages/articles.php','active_keys'=>['consommables'],
                 'perm'=>['consommables','can_read']],
                ['label'=>'Affectations',  'icon'=>'ph-arrows-left-right',
                 'url'=>'pages/affectations.php','active_keys'=>['affectations'],
                 'perm'=>['equipements','can_read'],
                 'roles_exclude'=>['coordinateur_site']],
                ['label'=>'PMMA',      'icon'=>'ph-printer',
                 'url'=>'pages/pmma.php','active_keys'=>['pmma'],
                 'perm'=>['pmma','can_read']],
                ['label'=>'Rivets',    'icon'=>'ph-nut',
                 'url'=>'pages/operations/rivets.php','active_keys'=>['rivets']],
                ['label'=>'Commandes', 'icon'=>'ph-shopping-cart',
                 'url'=>'pages/commandes.php','active_keys'=>['commandes'],
                 'perm'=>['commandes','can_read']],
            ],
        ],

        // ── 3. BOBINES (module dédié — était dispersé dans STOCK + OPERATIONS)
        'BOBINES' => [
            'icon'        => 'ph-film-strip',
            'titre'       => 'Bobines',
            'description' => 'Stock bobines, validation matin, PMMA, rivets et imports',
            'couleur'     => '#0D5C8A',
            'gradient'    => 'linear-gradient(135deg, #0D5C8A 0%, #1B75BC 100%)',
            'first_page'  => 'pages/operations/bobines.php',
            'nav' => [
                ['label'=>'Commande bobines',     'icon'=>'ph-shopping-cart',
                 'url'=>'pages/commandes_bobines.php','active_keys'=>['commandes_bobines']],
                ['label'=>'Gestion bobines',      'icon'=>'ph-film-strip',
                 'url'=>'pages/operations/bobines.php','active_keys'=>['bobines']],
                ['label'=>'Validation stock jour','icon'=>'ph-seal-check',
                 'url'=>'pages/validation_stock_matin.php','active_keys'=>['validation_stock_matin']],
                ['label'=>'Inventaire bobines',   'icon'=>'ph-clipboard-text',
                 'url'=>'pages/inventaire_bobines.php','active_keys'=>['inventaire_bobines']],
                // n° 19 réunion ERP : réservé au responsable des sessions
                // (admin/superadmin ou délégation, cf. can() et Délégations).
                ['label'=>"Sessions d'inventaire", 'icon'=>'ph-calendar-check',
                 'url'=>'pages/inventaire_sessions.php','active_keys'=>['inventaire_sessions'],
                 'perm'=>['inventaire_sessions','can_read']],
                ['label'=>'Rapports & Exports',   'icon'=>'ph-file-arrow-down',
                 'url'=>'pages/rapports_gsb.php','active_keys'=>['rapports_gsb'],
                 'roles_include'=>['admin','superadmin','gestionnaire_stock_bobines','gestionnaire_stock','superviseur_operation']],
                ['label'=>'Vue stock par site',   'icon'=>'ph-table',
                 'url'=>'pages/stock_bobines_vue.php','active_keys'=>['stock_bobines_vue'],
                 'roles_exclude'=>['coordinateur_site']],
                ['label'=>'Écarts bobines',       'icon'=>'ph-warning-diamond',
                 'url'=>'pages/ecarts_bobines.php','active_keys'=>['ecarts_bobines'],
                 'perm'=>['ecarts_bobines','can_read']],
            ],
        ],

        // ── 4. OPERATIONS (terrain quotidien — PRODUCTION fusionné ici)
        'OPERATIONS' => [
            'icon'        => 'ph-lightning',
            'titre'       => 'Opérations',
            'description' => 'Point journalier, commandes terrain, EMUCI et résumés',
            'couleur'     => '#00629B',
            'gradient'    => 'linear-gradient(135deg, #00629B 0%, #00AEEF 100%)',
            'first_page'  => 'pages/operations/point_journalier.php',
            'nav' => [
                ['label'=>'Point journalier',       'icon'=>'ph-clipboard-text',
                 'url'=>'pages/operations/point_journalier.php','active_keys'=>['operations']],
                ['label'=>'Demande d\'intervention','icon'=>'ph-warning-circle',
                 'url'=>'pages/interventions.php','active_keys'=>['interventions'],
                 'roles_exclude'=>['coordinateur_site']],
                // Commandes dans OPERATIONS uniquement pour les profils sans STOCK
                ['label'=>'Commandes',              'icon'=>'ph-shopping-cart',
                 'url'=>'pages/commandes.php','active_keys'=>['commandes'],
                 'perm'=>['commandes','can_read'],
                 'roles_include'=>['superviseur_operation','gestionnaire_operation']],
                // EMUCI : réservé aux profils de supervision, pas au coordinateur
                ['label'=>'Point EMUCI',            'icon'=>'ph-chart-scatter',
                 'url'=>'pages/point_emuci.php','active_keys'=>['point_emuci'],
                 'roles_exclude'=>['coordinateur_site']],
                ['label'=>'Import EMUCI',           'icon'=>'ph-upload-simple',
                 'url'=>'pages/import_emuci.php','active_keys'=>['import_emuci'],
                 'roles_exclude'=>['coordinateur_site']],
            ],
        ],

        // ── 5. INFORMATIQUE (maintenance IT)
        'INFORMATIQUE' => [
            'icon'        => 'ph-desktop-tower',
            'titre'       => 'Informatique',
            'description' => 'Interventions maintenance, rapports IT et affectations support',
            'couleur'     => '#06033A',
            'gradient'    => 'linear-gradient(135deg, #06033A 0%, #3B4FBE 100%)',
            'first_page'  => 'pages/interventions.php',
            'nav' => [
                ['label'=>'Interventions',    'icon'=>'ph-wrench',
                 'url'=>'pages/interventions.php','active_keys'=>['interventions']],
                ['label'=>'Rapport journalier','icon'=>'ph-file-text',
                 'url'=>'pages/rapport_journalier.php','active_keys'=>['rapport_journalier']],
                ['label'=>'Affectations IT',  'icon'=>'ph-arrows-left-right',
                 'url'=>'pages/affectations_it.php','active_keys'=>['affectations_it']],
            ],
        ],

        // ── 7. RAPPORTS (analyse et exports)
        'RAPPORTS' => [
            'icon'        => 'ph-chart-bar',
            'titre'       => 'Rapports',
            'description' => 'Résumés superviseur, analyses et exports de données',
            'couleur'     => '#0D5C8A',
            'gradient'    => 'linear-gradient(135deg, #0D5C8A 0%, #1B75BC 100%)',
            'first_page'  => 'pages/resume_superviseur.php',
            'nav' => [
                ['label'=>'Résumé superviseur','icon'=>'ph-chart-line-up',
                 'url'=>'pages/resume_superviseur.php','active_keys'=>['resume_superviseur']],
                ['label'=>'Rapports généraux','icon'=>'ph-chart-bar',
                 'url'=>'pages/rapports.php','active_keys'=>['rapports']],
                ['label'=>'Exports',          'icon'=>'ph-export',
                 'url'=>'pages/export.php','active_keys'=>['export']],
            ],
        ],

        // ── DEMANDES INTERNES (transversal — tous les employés)
        'DEMANDES' => [
            'icon'        => 'ph-file-text',
            'titre'       => 'Demandes internes',
            'description' => 'Demandes administratives dématérialisées et circuits de validation',
            'couleur'     => '#3B4FBE',
            'gradient'    => 'linear-gradient(135deg, #3B4FBE 0%, #7C92FF 100%)',
            'first_page'  => 'pages/demandes.php',
            'nav' => [
                // Le lecteur ne dépose pas de demande : ni la création, ni la
                // liste de ses propres demandes n'ont de sens pour lui. Il ne
                // garde que la file de validation.
                ['label'=>'Mes demandes',    'icon'=>'ph-list-checks',
                 'url'=>'pages/demandes.php','active_keys'=>['demandes'],
                 'roles_exclude'=>['lecteur']],
                ['label'=>'Nouvelle demande','icon'=>'ph-plus-circle',
                 'url'=>'pages/demandes_new.php','active_keys'=>['demandes_new'],
                 'roles_exclude'=>['lecteur']],
                ['label'=>'À valider',       'icon'=>'ph-seal-check',
                 'url'=>'pages/demandes_a_valider.php','active_keys'=>['demandes_valider'],
                 'roles_exclude'=>['coordinateur_site']],
                ['label'=>'Traitements IT',  'icon'=>'ph-wrench',
                 'url'=>'pages/demandes_it.php','active_keys'=>['demandes_it'],
                 'roles_include'=>['admin','superadmin','support_it','superviseur_it','maintenance_info']],
                ['label'=>'Types & circuits','icon'=>'ph-git-branch',
                 'url'=>'pages/demandes_types.php','active_keys'=>['demandes_types'],
                 'roles_include'=>['admin','superadmin']],
                ['label'=>'Circuits avancés','icon'=>'ph-buildings',
                 'url'=>'pages/demandes_roles.php','active_keys'=>['demandes_roles'],
                 'roles_include'=>['admin','superadmin']],
                ['label'=>'Annuaire agents', 'icon'=>'ph-address-book',
                 'url'=>'pages/agents.php','active_keys'=>['agents'],
                 'perm'=>['agents','can_read']],
            ],
        ],

        // ── ACHATS — transversal (cf. get_groupes_pour_role) : « Mes FEB » et
        //    « Nouvelle FEB » sont ouverts à tout titulaire du droit de
        //    lecture/création sur `achats` (tout le monde sauf le lecteur/PDG,
        //    cf. migration_achats_03_permissions.sql et ach_peut_creer()) ;
        //    les 5 écrans de paramétrage restent filtrés par `achats_param`,
        //    donc invisibles pour la plupart des rôles malgré le groupe visible.
        'ACHATS' => [
            'icon'        => 'ph-shopping-cart',
            'titre'       => 'Achats',
            'description' => 'Expression de besoin, fournisseurs et paramétrage budgétaire',
            'couleur'     => '#B45309',
            'gradient'    => 'linear-gradient(135deg, #B45309 0%, #F59E0B 100%)',
            'first_page'  => 'pages/achats/mes_feb.php',
            'nav' => [
                ['label'=>'Mes FEB',               'icon'=>'ph-list-checks',
                 'url'=>'pages/achats/mes_feb.php','active_keys'=>['achats_mes_feb'],
                 'perm'=>['achats','can_read']],
                ['label'=>'Nouvelle FEB',          'icon'=>'ph-plus-circle',
                 'url'=>'pages/achats/feb_fiche.php','active_keys'=>['achats_mes_feb'],
                 'perm'=>['achats','can_read'],
                 'roles_exclude'=>['lecteur']],
                ['label'=>'File d\'attente Achats', 'icon'=>'ph-queue',
                 'url'=>'pages/achats/file_attente.php','active_keys'=>['achats_file_attente'],
                 'perm'=>['achats','can_update']],
                ['label'=>'Mes visas',             'icon'=>'ph-signature',
                 'url'=>'pages/achats/mes_visas.php','active_keys'=>['achats_mes_visas'],
                 'perm'=>['achats','can_update']],
                ['label'=>'Fournisseurs',          'icon'=>'ph-storefront',
                 'url'=>'pages/achats/param_fournisseurs.php','active_keys'=>['achats_param_fournisseurs'],
                 'perm'=>['achats_param','can_read']],
                ['label'=>'Familles & types',      'icon'=>'ph-tag',
                 'url'=>'pages/achats/param_familles.php','active_keys'=>['achats_param_familles'],
                 'perm'=>['achats_param','can_read']],
                ['label'=>'Paliers de validation',  'icon'=>'ph-stairs',
                 'url'=>'pages/achats/param_paliers.php','active_keys'=>['achats_param_paliers'],
                 'perm'=>['achats_param','can_read']],
                ['label'=>'Lignes budgétaires',     'icon'=>'ph-calculator',
                 'url'=>'pages/achats/param_budget.php','active_keys'=>['achats_param_budget'],
                 'perm'=>['achats_param','can_read']],
                ['label'=>'Paramètres généraux',    'icon'=>'ph-gear',
                 'url'=>'pages/achats/param_general.php','active_keys'=>['achats_param_general'],
                 'perm'=>['achats_param','can_read']],
            ],
        ],

        // ── 8. ADMINISTRATION
        'ADMINISTRATION' => [
            'icon'        => 'ph-shield-check',
            'titre'       => 'Administration',
            'description' => 'Utilisateurs, rôles, permissions et configuration système',
            'couleur'     => '#06033A',
            'gradient'    => 'linear-gradient(135deg, #06033A 0%, #1B75BC 100%)',
            'first_page'  => 'pages/admin/users.php',
            'nav' => [
                ['label'=>'Utilisateurs',  'icon'=>'ph-users',
                 'url'=>'pages/admin/users.php','active_keys'=>['users']],
                ['label'=>'Permissions',   'icon'=>'ph-lock-key',
                 'url'=>'pages/admin/permissions.php','active_keys'=>['permissions']],
                ['label'=>'Nomenclatures', 'icon'=>'ph-tag',
                 'url'=>'pages/admin/nomenclatures.php','active_keys'=>['nomenclatures']],
                ['label'=>'Audit',         'icon'=>'ph-shield-check',
                 'url'=>'pages/admin/audit.php','active_keys'=>['audit']],
                ['label'=>'Délégations',   'icon'=>'ph-handshake',
                 'url'=>'pages/admin/delegations.php','active_keys'=>['delegations']],
                ['label'=>'Sites',         'icon'=>'ph-buildings',
                 'url'=>'pages/admin/sites.php','active_keys'=>['sites']],
                ['label'=>'Départements',  'icon'=>'ph-tree-structure',
                 'url'=>'pages/admin/departements.php','active_keys'=>['departements']],
            ],
        ],
    ];
}

// ── Groupes accessibles selon le rôle (9 → 7 groupes, plus de doublons)
function get_groupes_pour_role(string $role_slug): array {
    $all = TOUS_LES_GROUPES;
    $map = [
        // Terrain production
        'coordinateur_site'          => ['DASHBOARD','OPERATIONS','BOBINES','STOCK'],
        // Stock & approvisionnement (accès commandes via OPERATIONS)
        'gestionnaire_stock'         => ['DASHBOARD','OPERATIONS','BOBINES','STOCK'],
        // Supervision opérationnelle
        'superviseur_operation'      => ['DASHBOARD','OPERATIONS','BOBINES','STOCK','RAPPORTS'],
        // IT maintenance
        'maintenance_info'           => ['DASHBOARD','INFORMATIQUE','STOCK'],
        'superviseur_it'             => ['DASHBOARD','INFORMATIQUE','STOCK'],
        'support_it'                 => ['DASHBOARD','INFORMATIQUE'],
        // Achat & approvisionnement (accès commandes via OPERATIONS)
        'superviseur_achat'          => ['DASHBOARD','OPERATIONS','STOCK','ACHATS'],
        // Production (ex-PRODUCTION → OPERATIONS)
        'controleur_production'      => ['DASHBOARD','OPERATIONS','BOBINES'],
        // Opérations
        'gestionnaire_operation'     => ['DASHBOARD','OPERATIONS'],
        // GSB (gestionnaire stock bobines)
        'gestionnaire_stock_bobines' => ['DASHBOARD','OPERATIONS','BOBINES','STOCK'],
        // Lecture seule : uniquement la vue PDG et les demandes à valider.
        // Les droits fins page par page (ex. resume_superviseur.php, qui
        // autorise explicitement 'lecteur') ne changent pas — seule la
        // page d'accueil et la navigation par groupes se resserrent.
        'lecteur'                    => ['DASHBOARD'],
        // RAF / DAF — validation administrative et financière
        'raf'                        => ['DASHBOARD','RAPPORTS'],
        'daf'                        => ['DASHBOARD','RAPPORTS'],
        // Admin
        'admin'                      => $all,
        'superadmin'                 => $all,
    ];
    $groupes = $map[$role_slug] ?? ['DASHBOARD'];
    // « Demandes internes » et « Achats » sont transversaux : visibles par
    // tous les rôles. Pour Achats, seuls « Mes FEB »/« Nouvelle FEB »
    // s'affichent réellement pour la plupart (filtrage par item dans
    // get_groupe_nav_items — les écrans de paramétrage restent réservés à
    // achats_param).
    if (!in_array('DEMANDES', $groupes, true)) $groupes[] = 'DEMANDES';
    if (!in_array('ACHATS', $groupes, true)) $groupes[] = 'ACHATS';
    return $groupes;
}

// ── Retourne les groupes accessibles pour l'utilisateur connecté (clé => def)
function get_groupes_utilisateur(): array {
    $user = current_user();
    if (!$user) return [];
    $slugs = get_groupes_pour_role($user['role_slug'] ?? '');
    $def   = _groupes_def();
    $result = [];
    foreach ($slugs as $slug) {
        if (isset($def[$slug])) $result[$slug] = $def[$slug];
    }
    return $result;
}

// ── Retourne la définition d'un groupe par sa clé
function get_groupe_def(string $slug): ?array {
    $def = _groupes_def();
    return $def[$slug] ?? null;
}

// ── Retourne les nav items d'un groupe, filtrés par permissions ET rôle
function get_groupe_nav_items(string $slug): array {
    $def  = get_groupe_def($slug);
    if (!$def) return [];
    $user = current_user();
    $role = $user['role_slug'] ?? '';
    $items = [];
    foreach ($def['nav'] as $item) {
        // Filtre permission DB
        if (!empty($item['perm'])) {
            [$module, $droit] = $item['perm'];
            if (!can($module, $droit)) continue;
        }
        // Filtre rôles exclus (blacklist)
        if (!empty($item['roles_exclude']) && in_array($role, $item['roles_exclude'])) continue;
        // Filtre rôles autorisés (whitelist) — si défini, seuls ces rôles voient l'item
        if (!empty($item['roles_include']) && !in_array($role, $item['roles_include'])) continue;
        $items[] = $item;
    }
    return $items;
}
