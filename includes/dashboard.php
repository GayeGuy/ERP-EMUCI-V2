<?php
// ============================================================
//  includes/dashboard.php  —  Registre des blocs de tableau de bord
// ============================================================
//
//  pages/dashboard.php contenait 72 requêtes réparties en quatre branches
//  par nom de rôle, qui recalculaient les mêmes indicateurs avec des WHERE
//  différents. Aucune des huit conditions d'affichage ne regardait les
//  permissions ; la plupart étaient négatives (« sauf coordinateur »), donc
//  un rôle nouvellement créé voyait tout.
//
//  Trois notions séparées remplacent ça :
//
//   1. LA PORTÉE — un seul objet, déduit du rôle, appliqué à toutes les
//      requêtes. Le coordinateur ne voit que son site ; la maintenance
//      informatique ne voit que sa catégorie d'équipements.
//
//   2. LE REGISTRE — chaque bloc se déclare : son titre, son module de
//      permission, s'il respecte la portée, comment il lit ses données et
//      comment il s'affiche. Ajouter un bloc, c'est ajouter une entrée.
//
//   3. LES PROFILS — un profil métier est une liste ordonnée d'identifiants
//      de blocs. C'est le seul endroit où des noms de rôle subsistent, et
//      c'est normal : « ce que ce métier regarde en premier » n'est pas
//      déductible d'une permission.
//
//  Le rendu réutilise le vocabulaire de templates/dash_style.php et se fait
//  animer par templates/dash_anim.php sans rien déclarer : le moteur
//  découvre les valeurs en parcourant le DOM.
// ------------------------------------------------------------

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';

// ============================================================
//  MISE EN FORME
// ============================================================

// Nombre entier, avec une garde pour ne pas afficher « 0 » là où il y a
// quelque chose de non nul mais inférieur à l'unité.
if (!function_exists('ent')) {
    function ent(?float $v, bool $signe = false): string {
        if ($v === null) return '—';
        $r = (int)round($v);
        if ($r === 0 && $v >  0.0001) return '&lt; 1';
        if ($r === 0 && $v < -0.0001) return '&gt; -1';
        return ($signe && $r > 0 ? '+' : '') . number_format($r, 0, ',', ' ');
    }
}

// Répartition en entiers dont la somme fait exactement 100 (plus grand
// reste). Arrondir chaque part séparément donnait des totaux à 101 %.
if (!function_exists('pct_entiers')) {
    function pct_entiers(array $valeurs, float $total): array {
        $n = count($valeurs);
        if ($n === 0) return [];
        if ($total <= 0) return array_fill(0, $n, 0);
        $bruts = array_map(fn($v) => $v / $total * 100, $valeurs);
        $bas   = array_map(fn($p) => (int)floor($p), $bruts);
        $reste = 100 - array_sum($bas);
        $ordre = range(0, $n - 1);
        usort($ordre, fn($a, $b) => ($bruts[$b] - $bas[$b]) <=> ($bruts[$a] - $bas[$a]));
        for ($i = 0; $i < $reste && $i < $n; $i++) $bas[$ordre[$i]]++;
        return $bas;
    }
}

// ============================================================
//  PORTÉE
// ============================================================

/**
 * Périmètre de données de l'utilisateur courant.
 *
 * Un seul endroit décide « de quoi cette personne parle », et toutes les
 * requêtes du registre s'y conforment. C'est ce qui évite de réécrire
 * quatre variantes de la même requête.
 *
 * @return array{site_id:int, categorie:string, libelle:string, site_nom:string}
 */
function dash_portee(?array $user = null): array {
    $user = $user ?? current_user();
    $role = $user['role_slug'] ?? '';

    $site_id   = 0;
    $categorie = '';
    $verrouille = false;

    if ($role === 'coordinateur_site' && !empty($user['site_id'])) {
        // Site imposé par le rôle : le paramètre d'URL est ignoré, sinon un
        // coordinateur consulterait les chiffres d'un autre site en
        // modifiant l'adresse à la main.
        $site_id    = (int)$user['site_id'];
        $verrouille = true;
    } elseif (isset($_GET['site_id']) && (int)$_GET['site_id'] > 0) {
        // Site choisi dans la barre de filtres. Vérifié en base : un
        // identifiant inventé ou désactivé ramène à « tous les sites »
        // plutôt que de produire des blocs vides sans explication.
        $demande = (int)$_GET['site_id'];
        if (db_fetch_value("SELECT id FROM sites WHERE id=? AND actif=1", [$demande])) {
            $site_id = $demande;
        }
    }

    if ($role === 'maintenance_info') {
        $categorie = 'informatique';
    }

    $site_nom = '';
    if ($site_id) {
        $site_nom = (string)db_fetch_value("SELECT nom FROM sites WHERE id=?", [$site_id]);
    }

    $libelle = $site_nom !== '' ? $site_nom
             : ($categorie !== '' ? 'Parc informatique' : 'Tous les sites');

    return [
        'site_id'    => $site_id,
        'categorie'  => $categorie,
        'libelle'    => $libelle,
        'site_nom'   => $site_nom,
        'verrouille' => $verrouille,
    ];
}

/** Sites proposés au filtre. Vide si la portée est imposée par le rôle. */
function dash_sites_filtrables(array $portee): array {
    if ($portee['verrouille']) return [];
    return db_fetch_all("SELECT id, nom FROM sites WHERE actif=1 ORDER BY nom");
}

/**
 * Fragment SQL et paramètres pour restreindre une requête à la portée.
 *
 * La colonne est donnée en entier, pas sous forme de préfixe. Une première
 * version prenait un alias et ajoutait « site_id » derrière : elle produisait
 * « s.site_id » pour les requêtes qui partent de la table sites, dont la
 * colonne s'appelle « id ». Les blocs concernés échouaient dès qu'un site
 * était choisi, et seulement dans ce cas — sans filtre, la clause est vide.
 *
 * @param string $colonne  Colonne portant le site (« pj.site_id », « s.id »).
 * @return array{0:string, 1:array}  Clause à concaténer, paramètres.
 */
function dash_filtre_site(array $portee, string $colonne = 'site_id'): array {
    if (!$portee['site_id']) return ['', []];
    return [" AND $colonne = ?", [$portee['site_id']]];
}

function dash_filtre_categorie(array $portee, string $colonne = 'categorie'): array {
    if ($portee['categorie'] === '') return ['', []];
    return [" AND $colonne = ?", [$portee['categorie']]];
}

// ============================================================
//  PROFILS
// ============================================================

/**
 * Profil métier de l'utilisateur : détermine QUELS blocs et DANS QUEL ORDRE.
 *
 * Les rôles absents de cette table reçoivent le profil « general », qui
 * porte la vue d'ensemble. Aucun rôle ne peut donc se retrouver devant une
 * page vide, y compris un rôle créé après ce code.
 */
function dash_profil(?array $user = null): string {
    $user = $user ?? current_user();
    return match ($user['role_slug'] ?? '') {
        'coordinateur_site'          => 'coordinateur',
        'gestionnaire_stock_bobines' => 'gsb',
        'superviseur_operation'      => 'superviseur_op',
        'gestionnaire_operation'     => 'gestionnaire_op',
        'maintenance_info',
        'superviseur_it',
        'support_it'                 => 'informatique',
        default                      => 'general',
    };
}

/**
 * Blocs de chaque profil, dans l'ordre d'affichage.
 *
 * L'ordre porte du sens : ce qui demande une action vient avant ce qui
 * informe. Le coordinateur voit d'abord ses réceptions à traiter, le GSB
 * d'abord ses commandes à servir.
 */
function dash_profils(): array {
    return [
        // Terrain : ce qui se passe sur mon site aujourd'hui.
        'coordinateur' => [
            'synthese_coord', 'points_recents', 'corrections_attente', 'receptions_site',
            'stock_conso_site', 'bobines_sites', 'equipements', 'rivets',
        ],

        // Stock bobines : la file d'attente de service.
        'gsb' => [
            'commandes_bobines', 'validations_matin', 'corrections_attente',
            'stock_bas', 'bobines_sites', 'rivets',
        ],

        // Supervision des opérations : vue complète pour le patron des opérations.
        // Ordre : action immédiate → KPIs du jour → vigilance → bilan mensuel → logistique → audit.
        'superviseur_op' => [
            'synthese_jour', 'points_attente', 'corrections_attente',
            'validations_matin', 'points_rejetes', 'bobines_sites',
            'performance_mois', 'receptions_site', 'activites',
        ],

        // Gestion opérationnelle : peu de droits, donc peu de blocs et des
        // raccourcis vers ce que ce profil a réellement le droit de faire.
        'gestionnaire_op' => [
            'raccourcis', 'points_recents', 'commandes_bobines',
        ],

        // Informatique : le parc, ses pannes et ses fins de cycle.
        'informatique' => [
            'equipements', 'fin_cycle', 'interventions', 'activites',
        ],

        // Vue d'ensemble, par défaut.
        'general' => [
            'equipements', 'sites', 'stock_bas', 'fin_cycle',
            'conso_sites', 'bobines_sites', 'rivets', 'interventions',
            'activites',
        ],
    ];
}

// ============================================================
//  VISIBILITÉ
// ============================================================

/**
 * Un bloc s'affiche-t-il pour cet utilisateur ?
 *
 * La liste du profil dit ce que le métier regarde ; les permissions
 * restreignent. Après la migration du 2026-07-30 (27 modules × 14 rôles),
 * tous les rôles ont au moins une ligne dans la table permissions :
 * dash_role_a_des_permissions() retourne true pour tous, et can()
 * s'applique systématiquement à chaque bloc.
 */
function dash_bloc_visible(array $bloc, ?array $user = null): bool {
    $user = $user ?? current_user();
    if (!$user) return false;

    $module = $bloc['module'] ?? null;
    if ($module === null) return true;              // bloc sans module dédié

    if (!dash_role_a_des_permissions($user)) return true;

    return can($module, $bloc['droit'] ?? 'can_read');
}

/** Le rôle a-t-il au moins une permission déclarée ? Résultat mémorisé. */
function dash_role_a_des_permissions(?array $user = null): bool {
    static $cache = [];
    $user = $user ?? current_user();
    $rid  = (int)($user['role_id'] ?? 0);
    if ($rid === 0) return false;
    if (!array_key_exists($rid, $cache)) {
        // admin et superadmin passent par can() sans consulter la table.
        if (in_array($user['role_slug'] ?? '', ['admin', 'superadmin'], true)) {
            $cache[$rid] = true;
        } else {
            $cache[$rid] = (int)db_fetch_value(
                "SELECT COUNT(*) FROM permissions WHERE role_id=? AND can_read=1", [$rid]
            ) > 0;
        }
    }
    return $cache[$rid];
}

/**
 * Blocs à afficher pour l'utilisateur courant, dans l'ordre du profil.
 *
 * Un identifiant de profil qui ne correspond à aucune entrée du registre
 * est ignoré silencieusement côté page mais signalé dans les journaux :
 * une faute de frappe dans une liste de profil ne doit pas casser la page,
 * ni disparaître sans laisser de trace.
 */
function dash_blocs_visibles(?array $user = null): array {
    $user     = $user ?? current_user();
    $registre = dash_registre();
    $profils  = dash_profils();
    $profil   = dash_profil($user);
    $ids      = $profils[$profil] ?? $profils['general'];

    $sortie = [];
    foreach ($ids as $id) {
        if (!isset($registre[$id])) {
            error_log("dashboard : bloc « $id » déclaré dans le profil « $profil » mais absent du registre");
            continue;
        }
        $bloc = $registre[$id] + ['id' => $id];
        if (dash_bloc_visible($bloc, $user)) $sortie[] = $bloc;
    }
    return $sortie;
}

// ============================================================
//  AIDES AU RENDU
// ============================================================

/** Ouvre une carte : titre, sous-titre, lien d'action optionnel. */
function dash_carte_debut(array $bloc): void {
    $lien = $bloc['lien'] ?? null;
    echo '<div class="card">';
    echo '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">';
    echo '<div><div class="card-ttl">' . h($bloc['titre']) . '</div>';
    if (!empty($bloc['soustitre'])) {
        echo '<div class="card-sub" style="margin-bottom:0">' . h($bloc['soustitre']) . '</div>';
    }
    echo '</div>';
    if ($lien) {
        // Le lien n'est affiché que si l'utilisateur a le droit requis sur
        // le module cible. lien_module et lien_droit permettent de cibler
        // un module ou un niveau différent de celui qui conditionne le bloc.
        $lm = $bloc['lien_module'] ?? ($bloc['module'] ?? null);
        $ld = $bloc['lien_droit']  ?? 'can_read';
        if ($lm === null || can($lm, $ld)) {
            echo '<a href="' . APP_URL . h($lien[0]) . '" style="font-size:12px;font-weight:700;color:#15568B;'
               . 'text-decoration:none;white-space:nowrap">' . h($lien[1]) . ' →</a>';
        }
    }
    echo '</div><div style="margin-top:16px">';
}

function dash_carte_fin(): void {
    echo '</div></div>';
}

/**
 * État vide. Il porte toujours une phrase qui dit ce que l'absence
 * signifie : « rien à valider » et « pas de données » ne veulent pas dire
 * la même chose pour qui consulte.
 */
function dash_vide(string $message, bool $bonne_nouvelle = false): void {
    $couleur = $bonne_nouvelle ? '#166534' : '#5a6678';
    echo '<div style="text-align:center;padding:26px 16px;font-size:13px;color:' . $couleur . '">'
       . h($message) . '</div>';
}

/** Libellé lisible d'un type de point journalier. */
function dash_type_point(string $t): string {
    return [
        'point_9h'      => '9 h',
        'point_13h'     => '13 h',
        'point_18h'     => '18 h',
        'final'         => 'Final',
        'intermediaire' => 'Intermédiaire',
    ][$t] ?? $t;
}

// ============================================================
//  LE REGISTRE
// ============================================================

/**
 * Tous les blocs disponibles, indexés par identifiant.
 *
 * Chaque entrée :
 *   titre      — en-tête de la carte
 *   soustitre  — précision de périmètre ou de période
 *   module     — module de permission ; null = toujours visible
 *   droit      — droit requis sur ce module (can_read par défaut)
 *   largeur    — 'demi' (défaut) ou 'plein'
 *   lien       — [chemin, libellé] vers la page complète
 *   donnees    — fn(array $portee): mixed
 *   rendu      — fn(mixed $donnees, array $portee): void
 *
 * Les requêtes reçoivent la portée et l'appliquent : c'est ce qui fait
 * qu'un bloc écrit une fois sert plusieurs profils.
 */
function dash_registre(): array {
    return [

    // ── Points journaliers récents ────────────────────────────────────
    'points_recents' => [
        'titre'     => 'Points journaliers récents',
        'soustitre' => 'Cinq derniers relevés',
        'module'    => 'operations',
        'lien'      => ['/pages/operations/point_journalier.php', 'Tous les points'],
        'donnees'   => function (array $p) {
            [$w, $args] = dash_filtre_site($p, 'pj.site_id');
            return db_fetch_all(
                "SELECT pj.date_point, pj.type_point, pj.statut, pj.total_engins,
                        pj.total_plaques, pj.rivets_utilises, s.nom AS site_nom
                 FROM op_points_journaliers pj
                 JOIN sites s ON s.id = pj.site_id
                 WHERE 1=1 $w
                 ORDER BY pj.date_point DESC, pj.id DESC
                 LIMIT 5", $args);
        },
        'rendu' => function (array $d, array $p) {
            if (!$d) { dash_vide('Aucun point journalier sur ce périmètre.'); return; }
            echo '<table class="ptbl"><thead><tr><th>Date</th>';
            if (!$p['site_id']) echo '<th style="text-align:left">Site</th>';
            echo '<th>Engins</th><th>Plaques</th><th>Rivets</th><th style="text-align:right">État</th>'
               . '</tr></thead><tbody>';
            foreach ($d as $r) {
                $valide = ($r['statut'] === 'valide');
                echo '<tr><td>' . h(fmt_date($r['date_point'], 'd/m'))
                   . ' <span class="card-sub" style="margin:0">' . h(dash_type_point($r['type_point'])) . '</span></td>';
                if (!$p['site_id']) echo '<td>' . h($r['site_nom']) . '</td>';
                echo '<td>' . ent((float)$r['total_engins']) . '</td>'
                   . '<td>' . ent((float)$r['total_plaques']) . '</td>'
                   . '<td>' . ent((float)$r['rivets_utilises']) . '</td>'
                   . '<td style="text-align:right"><span class="mvh ' . ($valide ? 'mvh-g' : 'mvh-o') . '">'
                   . ($valide ? 'Validé' : 'Brouillon') . '</span></td></tr>';
            }
            echo '</tbody></table>';
        },
    ],

    // ── Points en attente de validation ───────────────────────────────
    'points_attente' => [
        'titre'      => 'Points en attente de validation',
        'soustitre'  => 'Relevés encore en brouillon',
        'module'     => 'operations',
        'lien'       => ['/pages/operations/point_journalier.php', 'Valider'],
        'lien_droit' => 'can_update',
        'donnees'   => function (array $p) {
            [$w, $args] = dash_filtre_site($p, 'pj.site_id');
            return db_fetch_all(
                "SELECT pj.id, pj.date_point, pj.type_point, pj.total_engins,
                        s.nom AS site_nom,
                        CONCAT(u.prenom, ' ', u.nom) AS auteur,
                        (CURRENT_DATE - pj.date_point) AS anciennete
                 FROM op_points_journaliers pj
                 JOIN sites s ON s.id = pj.site_id
                 LEFT JOIN users u ON u.id = pj.created_by
                 WHERE pj.statut = 'en_attente_validation' $w
                 ORDER BY pj.date_point ASC
                 LIMIT 8", $args);
        },
        'rendu' => function (array $d) {
            if (!$d) { dash_vide('Aucun point en attente de validation.', true); return; }
            echo '<table class="dtbl"><thead><tr><th>Date</th><th>Site</th><th>Auteur</th>'
               . '<th style="text-align:right">Soumis</th></tr></thead><tbody>';
            foreach ($d as $r) {
                $j   = (int)$r['anciennete'];
                $cls = $j >= 2 ? 'mvh-r' : ($j >= 1 ? 'mvh-o' : 'mvh-g');
                echo '<tr><td>' . h(fmt_date($r['date_point'], 'd/m'))
                   . ' <span style="color:#5a6678">' . h(dash_type_point($r['type_point'])) . '</span></td>'
                   . '<td style="font-weight:600">' . h($r['site_nom']) . '</td>'
                   . '<td style="color:#5a6678">' . h($r['auteur'] ?? '—') . '</td>'
                   . '<td style="text-align:right"><span class="mvh ' . $cls . '">'
                   . ($j <= 0 ? "Aujourd'hui" : ent((float)$j) . ' j') . '</span></td></tr>';
            }
            echo '</tbody></table>';
        },
    ],

    // ── Corrections de saisie en attente ──────────────────────────────
    'corrections_attente' => [
        'titre'      => 'Corrections en attente',
        'soustitre'  => 'Demandes de correction de saisie',
        'module'     => 'validation_stock',
        'lien'       => ['/pages/validation_stock_matin.php', 'Traiter'],
        'lien_droit' => 'can_create',
        'donnees'   => function (array $p) {
            [$w, $args] = dash_filtre_site($p, 'pj.site_id');
            return db_fetch_all(
                "SELECT dc.id, dc.motif, dc.created_at, pj.date_point, pj.type_point,
                        s.nom AS site_nom, CONCAT(u.prenom, ' ', u.nom) AS demandeur
                 FROM demandes_correction_saisie dc
                 JOIN op_points_journaliers pj ON pj.id = dc.point_id
                 JOIN sites s ON s.id = pj.site_id
                 LEFT JOIN users u ON u.id = dc.demande_par
                 WHERE dc.statut = 'en_attente' $w
                 ORDER BY dc.created_at ASC
                 LIMIT 6", $args);
        },
        'rendu' => function (array $d) {
            if (!$d) { dash_vide('Aucune correction demandée.', true); return; }
            foreach ($d as $r) {
                echo '<div class="biz-risk-r" style="margin-bottom:9px">'
                   . '<div class="biz-risk-i"><i class="ph-duotone ph-pencil-simple"></i></div>'
                   . '<div class="biz-risk-b">'
                   . '<div class="biz-risk-t">' . h($r['site_nom']) . ' · '
                   . h(fmt_date($r['date_point'], 'd/m')) . ' ' . h(dash_type_point($r['type_point'])) . '</div>'
                   . '<div class="biz-risk-s">' . h($r['motif']) . '</div>'
                   . '<div class="biz-risk-s" style="margin-top:3px">Demandé par '
                   . h($r['demandeur'] ?? '—') . ' le ' . h(fmt_date($r['created_at'])) . '</div>'
                   . '</div></div>';
            }
        },
    ],

    // ── Validations du stock du matin ─────────────────────────────────
    'validations_matin' => [
        'titre'       => 'Validations du stock matin',
        'soustitre'   => 'Sept derniers jours',
        'module'      => 'inventaire_bobines',
        'lien'        => ['/pages/validation_stock_matin.php', 'Voir le détail'],
        'lien_module' => 'validation_stock',
        'lien_droit'  => 'can_create',
        'donnees'   => function (array $p) {
            [$w, $args] = dash_filtre_site($p, 'v.site_id');
            return db_fetch_all(
                "SELECT v.date_validation, v.statut, v.nb_ecarts, s.nom AS site_nom
                 FROM validations_stock_matin v
                 JOIN sites s ON s.id = v.site_id
                 WHERE v.date_validation >= (CURRENT_DATE - INTERVAL '7 DAY') $w
                 ORDER BY v.date_validation DESC, s.nom
                 LIMIT 10", $args);
        },
        'rendu' => function (array $d) {
            if (!$d) { dash_vide('Aucune validation enregistrée cette semaine.'); return; }
            foreach ($d as $r) {
                $ec  = (int)$r['nb_ecarts'];
                $cls = $ec === 0 ? 'hc-green' : ($ec <= 2 ? 'hc-orange' : 'hc-red');
                echo '<div class="stock-stat-row">'
                   . '<div><div style="font-weight:700;color:#06033A">' . h($r['site_nom']) . '</div>'
                   . '<div class="card-sub" style="margin:2px 0 0">' . h(fmt_date($r['date_validation']))
                   . ' · ' . h(str_replace('_', ' ', $r['statut'])) . '</div></div>'
                   . '<span class="hc-badge ' . $cls . '" style="margin:0">'
                   . ($ec === 0 ? 'Aucun écart' : ent((float)$ec) . ' écart' . ($ec > 1 ? 's' : ''))
                   . '</span></div>';
            }
        },
    ],

    // ── Commandes de bobines ──────────────────────────────────────────
    'commandes_bobines' => [
        'titre'     => 'Commandes de bobines',
        'soustitre' => 'À servir ou en cours',
        'module'    => 'commandes_bobines',
        'lien'      => ['/pages/commandes_bobines.php', 'Toutes les commandes'],
        'donnees'   => function (array $p) {
            [$w, $args] = dash_filtre_site($p, 'c.site_id');
            return db_fetch_all(
                "SELECT c.numero, c.libelle_type, c.statut, c.created_at,
                        s.nom AS site_nom, CONCAT(u.prenom, ' ', u.nom) AS demandeur
                 FROM commandes_bobines c
                 JOIN sites s ON s.id = c.site_id
                 LEFT JOIN users u ON u.id = c.demande_par
                 WHERE c.statut IN ('en_attente', 'valide') $w
                 ORDER BY c.created_at ASC
                 LIMIT 8", $args);
        },
        'rendu' => function (array $d) {
            if (!$d) { dash_vide('Aucune commande en cours.', true); return; }
            echo '<table class="dtbl"><thead><tr><th>N°</th><th>Site</th><th>Type</th>'
               . '<th style="text-align:right">État</th></tr></thead><tbody>';
            foreach ($d as $r) {
                $att = ($r['statut'] === 'en_attente');
                echo '<tr><td style="font-family:monospace">' . h($r['numero']) . '</td>'
                   . '<td style="font-weight:600">' . h($r['site_nom']) . '</td>'
                   . '<td>' . h($r['libelle_type']) . '</td>'
                   . '<td style="text-align:right"><span class="d-statut ' . ($att ? 'ds-att' : 'ds-enc') . '">'
                   . ($att ? 'À valider' : 'À servir') . '</span></td></tr>';
            }
            echo '</tbody></table>';
        },
    ],

    // ── Stock de consommables bas ─────────────────────────────────────
    'stock_bas' => [
        'titre'     => 'Stock consommables bas',
        'soustitre' => 'Sous le seuil d\'alerte',
        'module'    => 'consommables',
        'lien'      => ['/pages/consommables.php', 'Gérer'],
        'donnees'   => function (array $p) {
            // Le coordinateur raisonne sur le stock de son site, les autres
            // sur le stock global : ce ne sont pas les mêmes tables.
            if ($p['site_id']) {
                return db_fetch_all(
                    "SELECT c.libelle, c.unite, sc.quantite AS stock, c.seuil_alerte
                     FROM stock_consommables_site sc
                     JOIN consommables c ON c.id = sc.consommable_id
                     WHERE sc.site_id = ? AND sc.quantite <= c.seuil_alerte
                     ORDER BY (sc.quantite / NULLIF(c.seuil_alerte,0)) ASC
                     LIMIT 8", [$p['site_id']]);
            }
            return db_fetch_all(
                "SELECT libelle, unite, stock_global AS stock, seuil_alerte
                 FROM consommables
                 WHERE stock_global <= seuil_alerte
                 ORDER BY (stock_global / NULLIF(seuil_alerte,0)) ASC
                 LIMIT 8");
        },
        'rendu' => function (array $d) {
            if (!$d) { dash_vide('Tous les stocks sont au-dessus du seuil.', true); return; }
            foreach ($d as $r) {
                $seuil = (float)$r['seuil_alerte'];
                $pct   = $seuil > 0 ? min(100, (float)$r['stock'] / $seuil * 100) : 0;
                $bas   = $pct <= 25;
                echo '<div class="biz-cov-r">'
                   . '<div><div class="biz-cov-n">' . h($r['libelle']) . '</div>'
                   . '<div class="biz-cov-s">seuil ' . ent($seuil) . ' ' . h($r['unite']) . '</div></div>'
                   . '<div class="biz-cov-bar"><div class="biz-cov-f' . ($bas ? ' low' : '')
                   . '" style="width:' . round($pct) . '%"></div></div>'
                   . '<div class="biz-cov-d"><span class="biz-cov-num">' . ent((float)$r['stock'])
                   . '</span><span class="biz-cov-u">' . h($r['unite']) . '</span></div>'
                   . '</div>';
            }
        },
    ],

    // ── Raccourcis ────────────────────────────────────────────────────
    'raccourcis' => [
        'titre'     => 'Raccourcis',
        'soustitre' => 'Ce que votre profil peut faire',
        'module'    => null,
        'largeur'   => 'plein',
        // Chaque raccourci est conditionné par sa propre permission : la
        // liste ne propose jamais une page qui renverrait un refus.
        'donnees'   => function () {
            $tout = [
                ['commandes',          'can_read',   '/pages/commandes_bobines.php',              'Commandes de bobines',    'ph-clipboard-text'],
                ['point_emuci',        'can_create', '/pages/operations/point_journalier.php',    'Saisir un point',         'ph-note-pencil'],
                ['inventaire_bobines', 'can_read',   '/pages/validation_stock_matin.php',         'Stock du matin',          'ph-check-square-offset'],
                ['bobines',            'can_read',   '/pages/operations/bobines.php',             'Bobines',                 'ph-film-strip'],
                ['rivets',             'can_read',   '/pages/operations/rivets.php',              'Rivets',                  'ph-nut'],
                ['receptions',         'can_read',   '/pages/reception_site.php',                 'Réceptions',              'ph-package'],
                ['equipements',        'can_read',   '/pages/equipements.php',                    'Équipements',             'ph-desktop'],
                ['rapports',           'can_read',   '/pages/rapports.php',                       'Rapports',                'ph-chart-bar'],
            ];
            return array_values(array_filter($tout, fn($r) => can($r[0], $r[1])));
        },
        'rendu' => function (array $d) {
            if (!$d) { dash_vide('Aucun module accessible avec ce profil.'); return; }
            echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:11px">';
            foreach ($d as [, , $url, $libelle, $icone]) {
                echo '<a href="' . APP_URL . h($url) . '" class="biz-risk-r" '
                   . 'style="text-decoration:none;background:#f8fafc">'
                   . '<div class="biz-risk-i" style="background:#eef2ff;color:#1e40af">'
                   . '<i class="ph-duotone ' . h($icone) . '"></i></div>'
                   . '<div class="biz-risk-t">' . h($libelle) . '</div></a>';
            }
            echo '</div>';
        },
    ],

    // ── Réceptions de site ────────────────────────────────────────────
    'receptions_site' => [
        'titre'     => 'Réceptions récentes',
        'soustitre' => 'Consommables et équipements reçus',
        'module'    => 'receptions',
        'largeur'   => 'plein',
        'lien'      => ['/pages/reception_site.php', 'Toutes les réceptions'],
        'donnees'   => function (array $p) {
            [$w, $args] = dash_filtre_site($p, 'rs.site_id');
            return db_fetch_all(
                "SELECT rs.date_reception, rs.type_reception, rs.quantite, rs.statut,
                        s.nom AS site_nom, c.libelle AS conso_lib, c.unite,
                        n.libelle AS equip_type
                 FROM receptions_site rs
                 JOIN sites s ON s.id = rs.site_id
                 LEFT JOIN consommables c ON c.id = rs.consommable_id
                 LEFT JOIN equipements e ON e.id = rs.equipement_id
                 LEFT JOIN nomenclatures n ON n.id = e.nomenclature_id
                 WHERE 1=1 $w
                 ORDER BY rs.created_at DESC
                 LIMIT 10", $args);
        },
        'rendu' => function (array $d, array $p) {
            if (!$d) { dash_vide('Aucune réception enregistrée.'); return; }
            echo '<div class="table-wrap"><table class="dtbl"><thead><tr><th>Date</th>';
            if (!$p['site_id']) echo '<th>Site</th>';
            echo '<th>Article</th><th style="text-align:right">Qté</th>'
               . '<th style="text-align:right">Statut</th></tr></thead><tbody>';
            foreach ($d as $r) {
                $conso = ($r['type_reception'] === 'consommable');
                $cls   = match ($r['statut']) {
                    'receptionnee' => 'mvh-g',
                    'litige'       => 'mvh-r',
                    default        => 'mvh-o',
                };
                $lbl = match ($r['statut']) {
                    'receptionnee' => 'Reçu',
                    'litige'       => 'Litige',
                    'en_attente'   => 'En attente',
                    default        => $r['statut'],
                };
                echo '<tr><td style="color:#5a6678">' . h(fmt_date($r['date_reception'])) . '</td>';
                if (!$p['site_id']) echo '<td style="font-weight:600">' . h($r['site_nom']) . '</td>';
                echo '<td>' . h($conso ? ($r['conso_lib'] ?? '—') : ($r['equip_type'] ?? '—')) . '</td>'
                   . '<td style="text-align:right;font-weight:700">'
                   . ($r['quantite'] !== null ? ent((float)$r['quantite']) . ' ' . h($r['unite'] ?? '') : '—') . '</td>'
                   . '<td style="text-align:right"><span class="mvh ' . $cls . '">' . h($lbl) . '</span></td></tr>';
            }
            echo '</tbody></table></div>';
        },
    ],

    // ── Stock de consommables du site ─────────────────────────────────
    'stock_conso_site' => [
        'titre'     => 'Stock consommables du site',
        'soustitre' => 'Quantités et seuils',
        'module'    => 'consommables',
        'donnees'   => function (array $p) {
            if (!$p['site_id']) return [];
            return db_fetch_all(
                "SELECT c.libelle, c.unite, sc.quantite, c.seuil_alerte
                 FROM stock_consommables_site sc
                 JOIN consommables c ON c.id = sc.consommable_id
                 WHERE sc.site_id = ?
                 ORDER BY (sc.quantite / NULLIF(c.seuil_alerte,0)) ASC
                 LIMIT 10", [$p['site_id']]);
        },
        'rendu' => function (array $d) {
            if (!$d) { dash_vide('Aucun consommable suivi sur ce site.'); return; }
            foreach ($d as $r) {
                $seuil = (float)$r['seuil_alerte'];
                $bas   = $seuil > 0 && (float)$r['quantite'] <= $seuil;
                $pct   = $seuil > 0 ? min(100, (float)$r['quantite'] / $seuil * 100) : 100;
                echo '<div class="biz-cov-r">'
                   . '<div><div class="biz-cov-n">' . h($r['libelle']) . '</div>'
                   . '<div class="biz-cov-s">seuil ' . ent($seuil) . '</div></div>'
                   . '<div class="biz-cov-bar"><div class="biz-cov-f' . ($bas ? ' low' : '')
                   . '" style="width:' . round($pct) . '%"></div></div>'
                   . '<div class="biz-cov-d"><span class="biz-cov-num">' . ent((float)$r['quantite'])
                   . '</span><span class="biz-cov-u">' . h($r['unite']) . '</span></div></div>';
            }
        },
    ],

    // ── Parc d'équipements ────────────────────────────────────────────
    'equipements' => [
        'titre'     => 'Parc d\'équipements',
        'soustitre' => 'Répartition par état',
        'module'    => 'equipements',
        'lien'      => ['/pages/equipements.php', 'Le parc'],
        'donnees'   => function (array $p) {
            [$ws, $as] = dash_filtre_site($p, 'site_id');
            [$wc, $ac] = dash_filtre_categorie($p, 'categorie');
            $args  = array_merge($as, $ac);
            $total = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 $ws $wc", $args);
            $etats = db_fetch_all(
                "SELECT etat, COUNT(*) AS total FROM equipements
                 WHERE actif=1 $ws $wc GROUP BY etat ORDER BY total DESC", $args);
            return ['total' => $total, 'etats' => $etats];
        },
        'rendu' => function (array $d) {
            $total = $d['total'];
            if ($total === 0) { dash_vide('Aucun équipement sur ce périmètre.'); return; }
            $libelles = ['neuf'=>'Neuf','bon'=>'Bon état','usage'=>'Usagé',
                         'mauvais'=>'Mauvais état','hs'=>'Hors service','maintenance'=>'En maintenance'];
            $teintes  = ['neuf'=>'#06033A','bon'=>'#1e40af','usage'=>'#f59e0b',
                         'mauvais'=>'#d97706','hs'=>'#dc2626','maintenance'=>'#6d28d9'];
            $parts = pct_entiers(array_map(fn($e) => (float)$e['total'], $d['etats']), (float)$total);

            echo '<div class="eq-big-l">Total actif</div>';
            echo '<div class="eq-big">' . ent((float)$total) . '</div>';
            echo '<div class="dist-bar" style="margin-top:16px">';
            foreach ($d['etats'] as $i => $e) {
                echo '<div class="dist-seg" style="width:' . $parts[$i] . '%;background:'
                   . ($teintes[$e['etat']] ?? '#94a3b8') . '"></div>';
            }
            echo '</div>';
            foreach ($d['etats'] as $i => $e) {
                echo '<div class="eq-r"><span class="eq-sq" style="background:'
                   . ($teintes[$e['etat']] ?? '#94a3b8') . '"></span>'
                   . '<span class="eq-r-l">' . h($libelles[$e['etat']] ?? $e['etat']) . '</span>'
                   . '<span class="eq-r-n">' . ent((float)$e['total']) . '</span>'
                   . '<span class="biz-cov-u" style="min-width:34px;text-align:right">'
                   . $parts[$i] . ' %</span></div>';
            }
        },
    ],

    // ── Équipements en fin de cycle ───────────────────────────────────
    'fin_cycle' => [
        'titre'     => 'Fin de cycle',
        'soustitre' => 'Soixante prochains jours',
        'module'    => 'equipements',
        'lien'      => ['/pages/equipements.php', 'Le parc'],
        'donnees'   => function (array $p) {
            [$ws, $as] = dash_filtre_site($p, 'e.site_id');
            [$wc, $ac] = dash_filtre_categorie($p, 'e.categorie');
            return db_fetch_all(
                "SELECT e.numero_serie_interne, e.date_fin_cycle, n.libelle AS type_equip,
                        s.nom AS site_nom,
                        ((e.date_fin_cycle)::date - CURRENT_DATE) AS jours
                 FROM equipements e
                 JOIN nomenclatures n ON n.id = e.nomenclature_id
                 LEFT JOIN sites s ON s.id = e.site_id
                 WHERE e.actif=1 AND e.date_fin_cycle IS NOT NULL
                   AND e.date_fin_cycle <= (CURRENT_DATE + INTERVAL '60 DAY') $ws $wc
                 ORDER BY e.date_fin_cycle ASC
                 LIMIT 8", array_merge($as, $ac));
        },
        'rendu' => function (array $d) {
            if (!$d) { dash_vide('Aucun équipement en fin de cycle.', true); return; }
            foreach ($d as $r) {
                $j   = (int)$r['jours'];
                $cls = $j < 0 ? 'hc-red' : ($j <= 30 ? 'hc-orange' : 'hc-green');
                echo '<div class="stock-stat-row">'
                   . '<div><div style="font-weight:700;color:#06033A">' . h($r['type_equip']) . '</div>'
                   . '<div class="card-sub" style="margin:2px 0 0;font-family:monospace">'
                   . h($r['numero_serie_interne']) . ' · ' . h($r['site_nom'] ?? 'Non affecté') . '</div></div>'
                   . '<span class="hc-badge ' . $cls . '" style="margin:0">'
                   . ($j < 0 ? 'Dépassé' : ($j === 0 ? "Aujourd'hui" : 'J-' . ent((float)$j)))
                   . '</span></div>';
            }
        },
    ],

    // ── Sites par type ────────────────────────────────────────────────
    'sites' => [
        'titre'     => 'Sites actifs',
        'soustitre' => 'Répartition par type',
        'module'    => 'sites',
        'lien'      => ['/pages/admin/sites.php', 'Les sites'],
        'donnees'   => fn() => db_fetch_all(
            "SELECT type, COUNT(*) AS nb FROM sites WHERE actif=1 GROUP BY type ORDER BY nb DESC"),
        'rendu' => function (array $d) {
            if (!$d) { dash_vide('Aucun site configuré.'); return; }
            $libelles = ['pose'=>'Pose','siege'=>'Siège','mixte'=>'Mixte',
                         'entrepot'=>'Entrepôt','autre'=>'Autre'];
            $total = array_sum(array_column($d, 'nb'));
            $parts = pct_entiers(array_map(fn($r) => (float)$r['nb'], $d), (float)$total);
            echo '<div class="eq-big-l">Total</div><div class="eq-big">' . ent((float)$total) . '</div>';
            echo '<div style="margin-top:14px">';
            foreach ($d as $i => $r) {
                echo '<div class="biz-cov-r">'
                   . '<div class="biz-cov-n">' . h($libelles[$r['type']] ?? ucfirst($r['type'])) . '</div>'
                   . '<div class="biz-cov-bar"><div class="biz-cov-f" style="width:' . $parts[$i] . '%"></div></div>'
                   . '<div class="biz-cov-d"><span class="biz-cov-num">' . ent((float)$r['nb']) . '</span></div>'
                   . '</div>';
            }
            echo '</div>';
        },
    ],

    // ── Bobines actives par site ──────────────────────────────────────
    'bobines_sites' => [
        'titre'     => 'Bobines actives',
        'soustitre' => 'En stock ou en cours d\'usage',
        'module'    => 'bobines',
        'lien'      => ['/pages/operations/bobines.php', 'Les bobines'],
        'donnees'   => function (array $p) {
            [$w, $args] = dash_filtre_site($p, 's.id');
            return db_fetch_all(
                "SELECT s.nom AS site_nom, COUNT(b.id) AS nb,
                        COALESCE(SUM(b.stock_systeme),0) AS films
                 FROM sites s
                 LEFT JOIN op_bobines b ON b.site_id = s.id
                      AND b.statut IN ('en_cours','en_stock')
                 WHERE s.actif=1 $w
                 GROUP BY s.id, s.nom
                 HAVING COUNT(b.id) > 0
                 ORDER BY nb DESC
                 LIMIT 10", $args);
        },
        'rendu' => function (array $d) {
            if (!$d) { dash_vide('Aucune bobine active sur ce périmètre.'); return; }
            $max = max(array_map(fn($r) => (float)$r['nb'], $d)) ?: 1;
            foreach ($d as $r) {
                echo '<div class="biz-cov-r">'
                   . '<div><div class="biz-cov-n">' . h($r['site_nom']) . '</div>'
                   . '<div class="biz-cov-s">' . ent((float)$r['films']) . ' films restants</div></div>'
                   . '<div class="biz-cov-bar"><div class="biz-cov-f" style="width:'
                   . round((float)$r['nb'] / $max * 100) . '%"></div></div>'
                   . '<div class="biz-cov-d"><span class="biz-cov-num">' . ent((float)$r['nb'])
                   . '</span><span class="biz-cov-u">bobines</span></div></div>';
            }
        },
    ],

    // ── Rivets en stock ───────────────────────────────────────────────
    'rivets' => [
        'titre'     => 'Rivets en stock',
        'soustitre' => 'Par site et par type',
        'module'    => 'rivets',
        'lien'      => ['/pages/operations/rivets.php', 'Les rivets'],
        'donnees'   => function (array $p) {
            [$w, $args] = dash_filtre_site($p, 'sr.site_id');
            return db_fetch_all(
                "SELECT s.nom AS site_nom, COALESCE(SUM(sr.quantite),0) AS stock
                 FROM op_stock_rivets sr
                 JOIN sites s ON s.id = sr.site_id
                 WHERE s.actif=1 $w
                 GROUP BY s.id, s.nom
                 ORDER BY stock ASC
                 LIMIT 10", $args);
        },
        'rendu' => function (array $d) {
            if (!$d) { dash_vide('Aucun stock de rivets enregistré.'); return; }
            foreach ($d as $r) {
                $q = (float)$r['stock'];
                // Seuils repris de la page Rivets : sous 100 il faut
                // commander, sous 500 il faut y penser.
                $cls = $q < 100 ? 'hc-red' : ($q < 500 ? 'hc-orange' : 'hc-green');
                echo '<div class="stock-stat-row">'
                   . '<div style="font-weight:700;color:#06033A">' . h($r['site_nom']) . '</div>'
                   . '<div style="display:flex;align-items:center;gap:10px">'
                   . '<span class="stock-val">' . ent($q) . '</span>'
                   . '<span class="hc-badge ' . $cls . '" style="margin:0">'
                   . ($q < 100 ? 'À commander' : ($q < 500 ? 'À surveiller' : 'Suffisant'))
                   . '</span></div></div>';
            }
        },
    ],

    // ── Interventions du mois ─────────────────────────────────────────
    'interventions' => [
        'titre'     => 'Interventions du mois',
        'soustitre' => 'Maintenance par site',
        'module'    => 'interventions',
        'lien'      => ['/pages/interventions.php', 'Les interventions'],
        'donnees'   => function (array $p) {
            [$w, $args] = dash_filtre_site($p, 's.id');
            return db_fetch_all(
                "SELECT s.nom AS site_nom, COUNT(im.id) AS nb
                 FROM sites s
                 LEFT JOIN interventions_maintenance im ON im.site_id = s.id
                      AND EXTRACT(YEAR  FROM im.date_intervention) = EXTRACT(YEAR  FROM CURRENT_DATE)
                      AND EXTRACT(MONTH FROM im.date_intervention) = EXTRACT(MONTH FROM CURRENT_DATE)
                 WHERE s.actif=1 $w
                 GROUP BY s.id, s.nom
                 HAVING COUNT(im.id) > 0
                 ORDER BY nb DESC
                 LIMIT 10", $args);
        },
        'rendu' => function (array $d) {
            if (!$d) { dash_vide('Aucune intervention ce mois-ci.', true); return; }
            $max = max(array_map(fn($r) => (float)$r['nb'], $d)) ?: 1;
            foreach ($d as $r) {
                $n = (float)$r['nb'];
                echo '<div class="biz-cov-r">'
                   . '<div class="biz-cov-n">' . h($r['site_nom']) . '</div>'
                   . '<div class="biz-cov-bar"><div class="biz-cov-f' . ($n >= 5 ? ' low' : '')
                   . '" style="width:' . round($n / $max * 100) . '%"></div></div>'
                   . '<div class="biz-cov-d"><span class="biz-cov-num">' . ent($n) . '</span></div></div>';
            }
        },
    ],

    // ── Consommation par site ─────────────────────────────────────────
    'conso_sites' => [
        'titre'     => 'Consommation par site',
        'soustitre' => 'Douze derniers mois, en FCFA',
        'module'    => 'consommables',
        'lien'      => ['/pages/consommables.php', 'Les consommables'],
        'donnees'   => function (array $p) {
            [$w, $args] = dash_filtre_site($p, 'lc.site_id');
            return db_fetch_all(
                "SELECT s.nom AS site_nom, COALESCE(SUM(lc.prix_total),0) AS montant
                 FROM livraisons_consommables lc
                 JOIN sites s ON s.id = lc.site_id
                 WHERE lc.date_livraison >= (CURRENT_DATE - INTERVAL '12 MONTH') $w
                 GROUP BY s.id, s.nom
                 HAVING COALESCE(SUM(lc.prix_total),0) > 0
                 ORDER BY montant DESC
                 LIMIT 10", $args);
        },
        'rendu' => function (array $d) {
            if (!$d) { dash_vide('Aucune livraison valorisée sur la période.'); return; }
            $max   = max(array_map(fn($r) => (float)$r['montant'], $d)) ?: 1;
            $total = array_sum(array_map(fn($r) => (float)$r['montant'], $d));
            echo '<div class="eq-big-l">Total sur douze mois</div>';
            echo '<div class="biz-m-val" style="font-size:30px">' . ent($total)
               . '<span class="biz-m-u">FCFA</span></div><div style="margin-top:14px">';
            foreach ($d as $r) {
                echo '<div class="biz-cov-r">'
                   . '<div class="biz-cov-n">' . h($r['site_nom']) . '</div>'
                   . '<div class="biz-cov-bar"><div class="biz-cov-f" style="width:'
                   . round((float)$r['montant'] / $max * 100) . '%"></div></div>'
                   . '<div class="biz-cov-d"><span class="biz-cov-num">' . ent((float)$r['montant'])
                   . '</span></div></div>';
            }
            echo '</div>';
        },
    ],

    // ── Synthèse du jour — coordinateur de site ──────────────────────
    'synthese_coord' => [
        'titre'    => 'Synthèse du jour',
        'soustitre'=> 'Mon site — aujourd\'hui',
        'module'   => 'operations',
        'largeur'  => 'plein',
        'donnees'  => function (array $p) {
            $today = date('Y-m-d');
            $sid   = $p['site_id'];
            if (!$sid) return null;
            $plaques  = (int)db_fetch_value("SELECT COALESCE(SUM(total_plaques),0)   FROM op_points_journaliers WHERE date_point=? AND site_id=?", [$today, $sid]);
            $engins   = (int)db_fetch_value("SELECT COALESCE(SUM(total_engins),0)    FROM op_points_journaliers WHERE date_point=? AND site_id=?", [$today, $sid]);
            $rivets_j = (int)db_fetch_value("SELECT COALESCE(SUM(rivets_utilises),0) FROM op_points_journaliers WHERE date_point=? AND site_id=?", [$today, $sid]);
            $brouillon= (int)db_fetch_value("SELECT COUNT(*) FROM op_points_journaliers WHERE date_point=? AND site_id=? AND statut='brouillon'",             [$today, $sid]);
            $soumis   = (int)db_fetch_value("SELECT COUNT(*) FROM op_points_journaliers WHERE date_point=? AND site_id=? AND statut='en_attente_validation'", [$today, $sid]);
            $valide   = (int)db_fetch_value("SELECT COUNT(*) FROM op_points_journaliers WHERE date_point=? AND site_id=? AND statut='valide'",                [$today, $sid]);
            $bobines  = (int)db_fetch_value("SELECT COUNT(*) FROM op_bobines WHERE site_id=? AND statut IN ('en_cours','en_stock')", [$sid]);
            $stock_rivets = (int)db_fetch_value("SELECT COALESCE(SUM(quantite),0) FROM op_stock_rivets WHERE site_id=?", [$sid]);
            return compact('plaques','engins','rivets_j','brouillon','soumis','valide','bobines','stock_rivets');
        },
        'rendu' => function ($d) {
            if (!$d) { dash_vide('Site non configuré pour ce compte.'); return; }
            $pt_col = $d['valide'] > 0 ? '#166534' : ($d['soumis'] > 0 ? '#92400e' : '#5a6678');
            $pt_bg  = $d['valide'] > 0 ? '#dcfce7' : ($d['soumis'] > 0 ? '#fef3c7' : '#f1f5f9');
            $rv_col = $d['stock_rivets'] < 100 ? '#dc2626' : ($d['stock_rivets'] < 500 ? '#92400e' : '#166534');
            $rv_bg  = $d['stock_rivets'] < 100 ? '#fee2e2' : ($d['stock_rivets'] < 500 ? '#fef3c7' : '#dcfce7');
            $kpis = [
                ['val' => ent((float)$d['plaques']),      'lbl' => 'Plaques posées',    'col' => '#1B75BC', 'bg' => '#dbeafe'],
                ['val' => ent((float)$d['engins']),       'lbl' => 'Engins traités',    'col' => '#06033A', 'bg' => '#f0f4ff'],
                ['val' => ent((float)$d['rivets_j']),     'lbl' => 'Rivets utilisés',   'col' => '#6d28d9', 'bg' => '#f3e8ff'],
                ['val' => ent((float)$d['bobines']),      'lbl' => 'Bobines actives',   'col' => '#0369a1', 'bg' => '#e0f2fe'],
                ['val' => ent((float)$d['stock_rivets']), 'lbl' => 'Stock rivets',      'col' => $rv_col,   'bg' => $rv_bg],
                ['val' => $d['valide'] . ' / ' . ($d['brouillon'] + $d['soumis'] + $d['valide']),
                          'lbl' => 'Points validés',      'col' => $pt_col,             'bg' => $pt_bg],
            ];
            echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px">';
            foreach ($kpis as $k) {
                echo '<div style="background:' . $k['bg'] . ';border-radius:12px;padding:14px 16px;min-width:0">'
                   . '<div style="font-size:26px;font-weight:900;color:' . $k['col'] . ';line-height:1;font-variant-numeric:tabular-nums">' . $k['val'] . '</div>'
                   . '<div style="font-size:11px;font-weight:700;color:' . $k['col'] . ';opacity:.75;text-transform:uppercase;letter-spacing:.4px;margin-top:6px;line-height:1.3">' . h($k['lbl']) . '</div>'
                   . '</div>';
            }
            echo '</div>';
        },
    ],

    // ── Synthèse opérationnelle du jour ──────────────────────────────
    'synthese_jour' => [
        'titre'      => 'Synthèse du jour',
        'soustitre'  => 'Indicateurs clés — aujourd\'hui',
        'module'     => 'operations',
        'largeur'    => 'plein',
        'lien'       => ['/pages/resume_superviseur.php', 'Rapport détaillé'],
        'lien_module'=> 'rapports',
        'donnees'    => function (array $p) {
            $today = date('Y-m-d');
            [$w, $args] = dash_filtre_site($p, 'site_id');
            $plaques    = (int)db_fetch_value("SELECT COALESCE(SUM(total_plaques),0)    FROM op_points_journaliers WHERE date_point=? $w", array_merge([$today], $args));
            $engins     = (int)db_fetch_value("SELECT COALESCE(SUM(total_engins),0)     FROM op_points_journaliers WHERE date_point=? $w", array_merge([$today], $args));
            $rivets_j   = (int)db_fetch_value("SELECT COALESCE(SUM(rivets_utilises),0)  FROM op_points_journaliers WHERE date_point=? $w", array_merge([$today], $args));
            $en_attente = (int)db_fetch_value("SELECT COUNT(*) FROM op_points_journaliers WHERE date_point=? AND statut='en_attente_validation' $w", array_merge([$today], $args));
            $valides    = (int)db_fetch_value("SELECT COUNT(*) FROM op_points_journaliers WHERE date_point=? AND statut='valide' $w", array_merge([$today], $args));
            $in_use     = (int)db_fetch_value("SELECT COUNT(*) FROM import_optoplate WHERE date_import=? AND statut_plaque='in_use' $w", array_merge([$today], $args));
            return compact('plaques', 'engins', 'rivets_j', 'en_attente', 'valides', 'in_use');
        },
        'rendu' => function (array $d) {
            $ecart   = $d['in_use'] - $d['plaques'];
            $e_str   = ($ecart > 0 ? '+' : '') . $ecart;
            $e_col   = $ecart === 0 ? '#166534' : '#dc2626';
            $e_bg    = $ecart === 0 ? '#dcfce7' : '#fee2e2';
            $att_col = $d['en_attente'] > 0 ? '#92400e' : '#166534';
            $att_bg  = $d['en_attente'] > 0 ? '#fef3c7' : '#dcfce7';
            $kpis = [
                ['val' => ent((float)$d['plaques']),    'lbl' => 'Plaques posées',        'col' => '#1B75BC', 'bg' => '#dbeafe'],
                ['val' => ent((float)$d['engins']),     'lbl' => 'Engins traités',        'col' => '#06033A', 'bg' => '#f0f4ff'],
                ['val' => ent((float)$d['rivets_j']),   'lbl' => 'Rivets utilisés',       'col' => '#6d28d9', 'bg' => '#f3e8ff'],
                ['val' => ent((float)$d['in_use']),     'lbl' => 'In Use EMUCI',          'col' => '#92400e', 'bg' => '#fef3c7'],
                ['val' => $e_str,                        'lbl' => 'Écart EMUCI / PJ',      'col' => $e_col,    'bg' => $e_bg],
                ['val' => ent((float)$d['valides']),    'lbl' => 'Points validés',        'col' => '#166534', 'bg' => '#dcfce7'],
                ['val' => ent((float)$d['en_attente']), 'lbl' => 'En attente validation', 'col' => $att_col,  'bg' => $att_bg],
            ];
            echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px">';
            foreach ($kpis as $k) {
                echo '<div style="background:' . $k['bg'] . ';border-radius:12px;padding:14px 16px;min-width:0">'
                   . '<div style="font-size:26px;font-weight:900;color:' . $k['col'] . ';line-height:1;font-variant-numeric:tabular-nums">' . $k['val'] . '</div>'
                   . '<div style="font-size:11px;font-weight:700;color:' . $k['col'] . ';opacity:.75;text-transform:uppercase;letter-spacing:.4px;margin-top:6px;line-height:1.3">' . h($k['lbl']) . '</div>'
                   . '</div>';
            }
            echo '</div>';
        },
    ],

    // ── Performance mensuelle par site ────────────────────────────────
    'performance_mois' => [
        'titre'      => 'Performance du mois',
        'soustitre'  => 'Mois en cours — taux de validation par site',
        'module'     => 'operations',
        'largeur'    => 'plein',
        'lien'       => ['/pages/resume_superviseur.php?vue=mensuel', 'Rapport mensuel'],
        'lien_module'=> 'rapports',
        'donnees'    => function (array $p) {
            [$w, $args] = dash_filtre_site($p, 'p.site_id');
            return db_fetch_all(
                "SELECT s.nom AS site_nom,
                        COUNT(*) AS nb_points,
                        SUM(CASE WHEN p.statut='valide' THEN 1 ELSE 0 END) AS points_valides,
                        SUM(p.total_plaques)   AS total_plaques,
                        SUM(p.total_engins)    AS total_engins,
                        SUM(p.rivets_utilises) AS rivets_utilises,
                        AVG(p.moyenne_prod)    AS moy_vh
                 FROM op_points_journaliers p
                 JOIN sites s ON s.id = p.site_id
                 WHERE TO_CHAR(p.date_point,'YYYY-MM') = ? $w
                 GROUP BY p.site_id, s.nom
                 ORDER BY total_plaques DESC
                 LIMIT 12",
                array_merge([date('Y-m')], $args)
            );
        },
        'rendu' => function (array $d) {
            if (!$d) { dash_vide('Aucune donnée ce mois-ci.'); return; }
            echo '<div class="table-wrap"><table class="ptbl"><thead><tr>'
               . '<th style="text-align:left">Site</th>'
               . '<th style="text-align:center">Validés / Total</th>'
               . '<th style="text-align:center">Taux</th>'
               . '<th style="text-align:right">Plaques</th>'
               . '<th style="text-align:right">Engins</th>'
               . '<th style="text-align:right">Rivets</th>'
               . '<th style="text-align:right">Moy. V/H</th>'
               . '</tr></thead><tbody>';
            $tp = 0; $tv = 0; $tpl = 0; $te = 0; $tr = 0;
            foreach ($d as $r) {
                $nb   = (int)$r['nb_points'];
                $val  = (int)$r['points_valides'];
                $taux = $nb > 0 ? round($val / $nb * 100) : 0;
                $col  = $taux >= 90 ? '#166534' : ($taux >= 60 ? '#92400e' : '#dc2626');
                $bg   = $taux >= 90 ? '#dcfce7' : ($taux >= 60 ? '#fef3c7' : '#fee2e2');
                $tp  += $nb;   $tv  += $val;
                $tpl += (int)$r['total_plaques'];
                $te  += (int)$r['total_engins'];
                $tr  += (int)$r['rivets_utilises'];
                echo '<tr>'
                   . '<td style="font-weight:600;color:#06033A">' . h($r['site_nom']) . '</td>'
                   . '<td style="text-align:center">' . $val . ' / ' . $nb . '</td>'
                   . '<td style="text-align:center"><span style="background:' . $bg . ';color:' . $col . ';border-radius:20px;padding:2px 10px;font-size:12px;font-weight:700">' . $taux . ' %</span></td>'
                   . '<td style="text-align:right;font-weight:700;color:#1B75BC">' . ent((float)$r['total_plaques']) . '</td>'
                   . '<td style="text-align:right">' . ent((float)$r['total_engins']) . '</td>'
                   . '<td style="text-align:right">' . ent((float)$r['rivets_utilises']) . '</td>'
                   . '<td style="text-align:right;color:#5a6678">' . number_format((float)($r['moy_vh'] ?? 0), 1) . ' V/H</td>'
                   . '</tr>';
            }
            $tt    = $tp > 0 ? round($tv / $tp * 100) : 0;
            $col_t = $tt >= 90 ? '#166534' : ($tt >= 60 ? '#92400e' : '#dc2626');
            $bg_t  = $tt >= 90 ? '#dcfce7' : ($tt >= 60 ? '#fef3c7' : '#fee2e2');
            echo '<tr style="border-top:2px solid var(--border);background:var(--bg,#f8fafc)">'
               . '<td style="font-weight:800;color:#06033A">TOTAL</td>'
               . '<td style="text-align:center;font-weight:700">' . $tv . ' / ' . $tp . '</td>'
               . '<td style="text-align:center"><span style="background:' . $bg_t . ';color:' . $col_t . ';border-radius:20px;padding:2px 10px;font-size:12px;font-weight:800">' . $tt . ' %</span></td>'
               . '<td style="text-align:right;font-weight:800;color:#1B75BC">' . ent((float)$tpl) . '</td>'
               . '<td style="text-align:right;font-weight:700">' . ent((float)$te) . '</td>'
               . '<td style="text-align:right;font-weight:700">' . ent((float)$tr) . '</td>'
               . '<td style="text-align:right">—</td>'
               . '</tr></tbody></table></div>';
        },
    ],

    // ── Points rejetés récemment ──────────────────────────────────────
    'points_rejetes' => [
        'titre'    => 'Points rejetés',
        'soustitre'=> 'Sept derniers jours',
        'module'   => 'operations',
        'donnees'  => function (array $p) {
            [$w, $args] = dash_filtre_site($p, 'pj.site_id');
            return db_fetch_all(
                "SELECT pj.date_point, pj.type_point, pj.motif_rejet,
                        s.nom AS site_nom,
                        CONCAT(u.prenom, ' ', u.nom) AS auteur
                 FROM op_points_journaliers pj
                 JOIN sites s ON s.id = pj.site_id
                 LEFT JOIN users u ON u.id = pj.created_by
                 WHERE pj.statut = 'rejete'
                   AND pj.date_point >= (CURRENT_DATE - INTERVAL '7 DAY') $w
                 ORDER BY pj.date_point DESC
                 LIMIT 6",
                $args
            );
        },
        'rendu' => function (array $d) {
            if (!$d) { dash_vide('Aucun point rejeté cette semaine.', true); return; }
            foreach ($d as $r) {
                echo '<div class="biz-risk-r" style="margin-bottom:9px">'
                   . '<div class="biz-risk-i" style="background:#fee2e2;color:#dc2626"><i class="ph-duotone ph-x-circle"></i></div>'
                   . '<div class="biz-risk-b">'
                   . '<div class="biz-risk-t">' . h($r['site_nom']) . ' · '
                   . h(fmt_date($r['date_point'], 'd/m')) . ' ' . h(dash_type_point($r['type_point'])) . '</div>'
                   . '<div class="biz-risk-s">' . h($r['motif_rejet'] ?? 'Aucun motif indiqué') . '</div>'
                   . '<div class="biz-risk-s" style="margin-top:2px">Par ' . h($r['auteur'] ?? '—') . '</div>'
                   . '</div></div>';
            }
        },
    ],

    // ── Dernières activités ───────────────────────────────────────────
    'activites' => [
        'titre'     => 'Dernières activités',
        'soustitre' => 'Journal des opérations',
        'module'    => 'audit',
        'largeur'   => 'plein',
        'lien'      => ['/pages/admin/audit.php', 'Le journal'],
        'donnees'   => fn() => db_fetch_all(
            "SELECT al.action, al.module, al.description, al.created_at,
                    CONCAT(u.prenom, ' ', u.nom) AS user_nom
             FROM audit_log al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC
             LIMIT 10"),
        'rendu' => function (array $d) {
            if (!$d) { dash_vide('Aucune activité enregistrée.'); return; }
            echo '<div class="table-wrap"><table class="dtbl"><thead><tr><th>Action</th>'
               . '<th>Description</th><th>Par</th><th style="text-align:right">Quand</th>'
               . '</tr></thead><tbody>';
            foreach ($d as $r) {
                $cls = match ($r['action']) {
                    'CREATE'          => 'mvh-g',
                    'DELETE'          => 'mvh-r',
                    'UPDATE'          => 'mvh-o',
                    default           => 'mvh-o',
                };
                echo '<tr><td><span class="mvh ' . $cls . '">' . h($r['action']) . '</span></td>'
                   . '<td>' . h($r['description'] ?? '—') . '</td>'
                   . '<td style="color:#5a6678">' . h($r['user_nom'] ?? 'Système') . '</td>'
                   . '<td style="text-align:right;color:#5a6678;white-space:nowrap">'
                   . h(fmt_datetime($r['created_at'])) . '</td></tr>';
            }
            echo '</tbody></table></div>';
        },
    ],

    ];
}

/**
 * Affiche un bloc. Une requête ou un rendu qui échoue laisse une carte
 * explicite au lieu de faire tomber la page entière : un tableau de bord
 * amputé d'un bloc reste utilisable, une page blanche non.
 */
function dash_afficher_bloc(array $bloc, array $portee): void {
    try {
        $donnees = ($bloc['donnees'])($portee);
    } catch (Throwable $e) {
        error_log('dashboard : données du bloc ' . $bloc['id'] . ' — ' . $e->getMessage());
        dash_carte_debut($bloc);
        dash_vide('Ces données sont momentanément indisponibles.');
        dash_carte_fin();
        return;
    }

    dash_carte_debut($bloc);
    try {
        ($bloc['rendu'])($donnees, $portee);
    } catch (Throwable $e) {
        error_log('dashboard : rendu du bloc ' . $bloc['id'] . ' — ' . $e->getMessage());
        dash_vide('Affichage indisponible pour ce bloc.');
    }
    dash_carte_fin();
}
