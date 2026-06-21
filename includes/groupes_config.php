<?php
// ============================================================
//  includes/groupes_config.php — Configuration centralisée des groupes de menus
//  7 groupes — aucune page en double
// ============================================================

define('TOUS_LES_GROUPES', [
    'DASHBOARD','STOCK','BOBINES','OPERATIONS',
    'INFORMATIQUE','RAPPORTS','ADMINISTRATION'
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
                ['label'=>'Tableau de bord','icon'=>'ph-squares-four',
                 'url'=>'pages/dashboard.php','active_keys'=>['dashboard']],
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
                 'url'=>'pages/interventions.php','active_keys'=>['interventions']],
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
                 'url'=>'pages/nomenclatures.php','active_keys'=>['nomenclatures']],
                ['label'=>'Audit',         'icon'=>'ph-shield-check',
                 'url'=>'pages/admin/audit.php','active_keys'=>['audit']],
                ['label'=>'Délégations',   'icon'=>'ph-handshake',
                 'url'=>'pages/delegations.php','active_keys'=>['delegations']],
                ['label'=>'Sites',         'icon'=>'ph-buildings',
                 'url'=>'pages/sites.php','active_keys'=>['sites']],
            ],
        ],
    ];
}

// ── Groupes accessibles selon le rôle (9 → 7 groupes, plus de doublons)
function get_groupes_pour_role(string $role_slug): array {
    $all = TOUS_LES_GROUPES;
    $map = [
        // Terrain production
        'coordinateur_site'          => ['DASHBOARD','STOCK','BOBINES','OPERATIONS'],
        // Stock & approvisionnement (accès commandes via OPERATIONS)
        'gestionnaire_stock'         => ['DASHBOARD','STOCK','BOBINES','OPERATIONS'],
        // Supervision opérationnelle
        'superviseur_operation'      => ['DASHBOARD','STOCK','OPERATIONS','BOBINES','RAPPORTS'],
        // IT maintenance
        'maintenance_info'           => ['DASHBOARD','STOCK','INFORMATIQUE'],
        'superviseur_it'             => ['DASHBOARD','STOCK','INFORMATIQUE'],
        'support_it'                 => ['DASHBOARD','INFORMATIQUE'],
        // Achat & approvisionnement (accès commandes via OPERATIONS)
        'superviseur_achat'          => ['DASHBOARD','OPERATIONS','STOCK'],
        // Production (ex-PRODUCTION → OPERATIONS)
        'controleur_production'      => ['DASHBOARD','BOBINES','OPERATIONS'],
        // Opérations
        'gestionnaire_operation'     => ['DASHBOARD','OPERATIONS'],
        // Gestionnaire polyvalent (ex-EQUIPEMENTS → STOCK)
        'gestionnaire'               => ['DASHBOARD','STOCK'],
        // GSB (gestionnaire stock bobines)
        'gestionnaire_stock_bobines' => ['DASHBOARD','STOCK','BOBINES','OPERATIONS'],
        // Lecture seule
        'lecteur'                    => ['DASHBOARD'],
        // Admin
        'admin'                      => $all,
        'superadmin'                 => $all,
    ];
    return $map[$role_slug] ?? ['DASHBOARD'];
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
