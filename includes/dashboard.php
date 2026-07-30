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
            'synthese_terrain',
            'points_recents', 'corrections_attente', 'evolution_engins',
            'receptions_site', 'stock_conso_site', 'equipements', 'rivets',
        ],

        // Stock bobines : la file d'attente de service.
        'gsb' => [
            'synthese_stock',
            'commandes_bobines', 'validations_matin', 'bobines_sites',
            'stock_bas', 'corrections_attente', 'rivets',
        ],

        // Supervision des opérations : ce qui attend une validation.
        'superviseur_op' => [
            'synthese_supervision',
            'points_attente', 'corrections_attente',
            'evolution_engins', 'repartition_parc',
            'perf_sites',
            'validations_matin', 'points_recents', 'receptions_site',
            'bobines_sites', 'interventions', 'activites',
        ],

        // Gestion opérationnelle : peu de droits, donc peu de blocs et des
        // raccourcis vers ce que ce profil a réellement le droit de faire.
        'gestionnaire_op' => [
            'synthese_supervision',
            'raccourcis', 'points_recents', 'evolution_engins',
            'commandes_bobines',
        ],

        // Informatique : le parc, ses pannes et ses fins de cycle.
        'informatique' => [
            'synthese_parc',
            'repartition_parc', 'fin_cycle',
            'equipements', 'interventions', 'activites',
        ],

        // Vue d'ensemble, par défaut.
        'general' => [
            'synthese_supervision',
            'evolution_engins', 'repartition_parc',
            'perf_sites',
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
 * restreignent. Mais elles ne restreignent QUE si elles ont été renseignées
 * pour ce rôle : au 2026-07-30, six rôles sur dix-sept n'ont aucune ligne
 * dans la table permissions (dont lecteur et gestionnaire_stock_bobines).
 * Ce n'est pas une politique, c'est un écran d'administration jamais
 * rempli. Traiter cette absence comme un refus afficherait une page vide à
 * ces profils — donc on ne le fait pas, et on le signale plutôt.
 *
 * Dès que les permissions d'un rôle sont renseignées, elles reprennent la
 * main sans qu'il y ait à toucher à ce code.
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
    echo '<div class="dash-tete">';
    echo '<div><div class="card-ttl">' . h($bloc['titre']) . '</div>';
    if (!empty($bloc['soustitre'])) {
        echo '<div class="card-sub" style="margin-bottom:0">' . h($bloc['soustitre']) . '</div>';
    }
    echo '</div>';
    if ($lien) {
        echo '<a class="dash-lien" href="' . APP_URL . h($lien[0]) . '">' . h($lien[1]) . ' →</a>';
    }
    // dash-corps porte le défilement horizontal : la carte est en
    // overflow:hidden (feuille globale du site), donc un tableau plus large
    // qu'elle était coupé sans recours — sur téléphone, deux colonnes de
    // quatre disparaissaient purement et simplement.
    echo '</div><div class="dash-corps">';
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

/**
 * Le mois que la synthèse doit montrer.
 *
 * Le mois civil est l'unité dans laquelle l'activité se raconte, donc c'est
 * lui qu'on affiche — tant qu'il porte quelque chose. Le premier du mois, et
 * pendant toute une période creuse, il ne porte rien : le chiffre de tête
 * serait alors un grand zéro exact et inutile, qui est le pire accueil
 * possible pour une page dont le rôle est de situer.
 *
 * On retombe donc sur le dernier mois ayant des relevés validés, et le
 * libellé le nomme. Mieux vaut un chiffre daté qu'un zéro ambigu.
 *
 * @return array{mois:string, libelle:string, courant:bool}
 */
function dash_periode(array $portee): array {
    [$w, $args] = dash_filtre_site($portee, 'pj.site_id');
    $courant = date('Y-m');

    $dernier = (string)db_fetch_value(
        "SELECT TO_CHAR(MAX(pj.date_point),'YYYY-MM') FROM op_points_journaliers pj
         WHERE pj.statut = 'valide' $w", $args);

    $mois = ($dernier !== '' && $dernier < $courant) ? $dernier : $courant;

    $noms = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
             'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $t = strtotime($mois . '-01');

    return [
        'mois'    => $mois,
        'libelle' => $noms[(int)date('n', $t)] . ' ' . date('Y', $t),
        'courant' => $mois === $courant,
    ];
}

/**
 * Bandeau de synthèse : le chiffre qui situe, sa répartition, quatre appuis.
 *
 * C'est la structure de tête de pages/pdg_overview.php, rendue réutilisable.
 * Elle répond à « où j'en suis » avant que la page ne descende dans le
 * détail — sans elle, on ouvrait directement sur un tableau de lignes, sans
 * repère pour les juger.
 *
 * La barre segmentée n'est pas décorative : elle porte toujours un partage
 * fait / reste à faire, c'est-à-dire la seule chose qu'on veut voir sans
 * lire. Les parts sont réparties au plus fort reste, sinon quatre arrondis
 * séparés donnent des totaux à 101 %.
 *
 * @param array $hero      ['lbl','val','unite','note']
 * @param array $segments  [['lbl','val','ton' => 'ok'|'ko'], …]
 * @param array $metriques [['lbl','val','unite','sub'], …] — quatre au plus
 */
function dash_bandeau(array $hero, array $segments, array $metriques): void {
    echo '<div class="biz"><div class="biz-lead">';

    // ── Le chiffre de tête
    echo '<div class="biz-hero">';
    echo '<div class="biz-hero-lbl">' . h($hero['lbl']) . '</div>';
    echo '<div class="biz-hero-val">' . $hero['val'];
    if (!empty($hero['unite'])) echo '<span class="biz-hero-u">' . h($hero['unite']) . '</span>';
    echo '</div>';
    if (!empty($hero['note'])) echo '<div class="biz-hero-note">' . $hero['note'] . '</div>';

    $total = 0;
    foreach ($segments as $s) $total += max(0, (int)$s['val']);

    if ($total > 0) {
        $parts = pct_entiers(array_map(fn($s) => max(0, (int)$s['val']), $segments), $total);
        echo '<div class="biz-dem">';
        foreach ($segments as $i => $s) {
            if ($parts[$i] <= 0) continue;
            $ton = ($s['ton'] ?? 'ok') === 'ko' ? 'biz-dem-ko' : 'biz-dem-ok';
            // Le libellé n'est écrit dans le segment que s'il a la place. Le
            // seuil en pourcentage ne suffit pas : 20 % d'une barre de 180 px
            // ne fait que 36 px, où « En attente » se coupe au milieu d'un
            // mot. La classe biz-dem-lbl le fait disparaître sous 700 px,
            // où la légende juste dessous porte déjà le sens.
            $texte = $parts[$i] >= 18 ? '<span class="biz-dem-lbl">' . h($s['lbl']) . '</span>' : '';
            echo '<div class="biz-dem-s ' . $ton . '" style="width:' . $parts[$i] . '%">' . $texte . '</div>';
        }
        echo '</div>';

        // La légende répète les chiffres : la barre donne la proportion, elle
        // donne la valeur. Elle porte aussi le sens quand la couleur ne suffit
        // pas à distinguer deux segments voisins.
        echo '<div class="biz-dem-key">';
        foreach ($segments as $i => $s) {
            $pt = ($s['ton'] ?? 'ok') === 'ko' ? '#fca5a5' : '#86efac';
            echo '<span><span class="biz-dot" style="background:' . $pt . '"></span>'
               . h($s['lbl']) . ' <b>' . ent((float)$s['val']) . '</b></span>';
        }
        echo '</div>';
    }
    echo '</div>';   // .biz-hero

    // ── Les quatre appuis
    echo '<div class="biz-mx">';
    foreach (array_slice($metriques, 0, 4) as $m) {
        echo '<div class="biz-m"><div class="biz-m-hd"><div>';
        echo '<div class="biz-m-lbl">' . h($m['lbl']) . '</div>';
        echo '<div class="biz-m-val">' . $m['val'];
        if (!empty($m['unite'])) echo '<span class="biz-m-u">' . h($m['unite']) . '</span>';
        echo '</div></div></div>';
        if (!empty($m['sub'])) echo '<div class="biz-m-sub">' . h($m['sub']) . '</div>';
        echo '</div>';
    }
    echo '</div></div></div>';
}

/**
 * Canvas de graphe. Les séries voyagent en attributs plutôt qu'en variables
 * JavaScript : le contenu échangé par templates/dash_anim.php arrive alors
 * avec ses propres chiffres, sans script à réexécuter.
 */
function dash_graphe(string $type, array $valeurs, array $options = []): void {
    // Un canvas est un rectangle vide pour un lecteur d'écran : sans
    // équivalent textuel, l'information n'existe tout simplement pas. Le
    // résumé est construit à partir des mêmes chiffres que le tracé, donc il
    // ne peut pas diverger de ce qui est dessiné.
    $resume = $options['alt'] ?? dash_resume_graphe($type, $valeurs, $options['libelles'] ?? []);

    $attrs = 'data-graphe="' . h($type) . '"'
           . ' role="img" aria-label="' . h($resume) . '"'
           . " data-valeurs='" . h(json_encode(array_values($valeurs))) . "'";
    if (!empty($options['libelles'])) {
        $attrs .= " data-libelles='" . h(json_encode(array_values($options['libelles']))) . "'";
    }
    if (!empty($options['couleurs'])) {
        $attrs .= " data-couleurs='" . h(json_encode(array_values($options['couleurs']))) . "'";
    }
    if (!empty($options['couleur'])) $attrs .= ' data-couleur="' . h($options['couleur']) . '"';
    if (!empty($options['taille']))  $attrs .= ' width="' . (int)$options['taille'] . '"';
    if (!empty($options['hauteur'])) $attrs .= ' height="' . (int)$options['hauteur'] . '"';
    echo '<canvas ' . $attrs . '></canvas>';
}

/**
 * Résumé parlé d'un graphe. Il ne récite pas toute la série — une courbe de
 * douze mois lue point par point est inexploitable à l'oreille — mais donne
 * ce qu'on retient en regardant : le total, les extrêmes, la tendance.
 */
function dash_resume_graphe(string $type, array $valeurs, array $libelles): string {
    if (!$valeurs) return 'Graphique sans donnée.';

    $total = array_sum($valeurs);
    $iMax  = array_search(max($valeurs), $valeurs, true);
    $nomMax = $libelles[$iMax] ?? null;

    if ($type === 'courbe') {
        // La tendance se lit sur les périodes qui portent quelque chose. La
        // calculer sur le premier et le dernier point annonçait « stable »
        // dès que la série commençait et finissait à zéro, ce qui est exact
        // et ne dit rien.
        $portantes = array_values(array_filter($valeurs, fn($v) => $v > 0));
        $sens = '';
        if (count($portantes) >= 2) {
            $a = (float)$portantes[count($portantes) - 2];
            $b = (float)$portantes[count($portantes) - 1];
            $sens = $b > $a ? ', en hausse sur la fin' : ($b < $a ? ', en baisse sur la fin' : '');
        }
        return sprintf(
            'Courbe sur %d périodes, total %s%s. Maximum de %s%s.',
            count($valeurs), ent((float)$total), $sens, ent((float)max($valeurs)),
            $nomMax ? ' en ' . $nomMax : '');
    }

    if ($type === 'anneau') {
        return sprintf('Répartition de %s au total, en %d parts. Part la plus grande : %s.',
            ent((float)$total), count($valeurs), ent((float)max($valeurs)));
    }

    // Barres : le classement est ce qui compte, donc on nomme la tête.
    $tete = [];
    $ordre = $valeurs;
    arsort($ordre);
    foreach (array_slice($ordre, 0, 3, true) as $i => $v) {
        $tete[] = ($libelles[$i] ?? 'sans nom') . ' ' . ent((float)$v);
    }
    return sprintf('Classement de %d entrées, total %s. En tête : %s.',
        count($valeurs), ent((float)$total), implode(', ', $tete));
}

/** Légende d'un anneau : pastille, libellé, valeur. */
function dash_legende(array $entrees): void {
    echo '<div class="leg-list">';
    foreach ($entrees as $e) {
        echo '<div class="leg-item"><span class="leg-dot" style="background:' . h($e['couleur']) . '"></span>'
           . h($e['lbl']) . '<span class="leg-val">' . ent((float)$e['val']) . '</span></div>';
    }
    echo '</div>';
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
    $mois = date('Y-m');

    return [

    // ══════════════════════════════════════════════════════════════════
    //  BANDEAUX DE SYNTHÈSE
    //
    //  Un par métier, et c'est volontaire : le bandeau répond à « où j'en
    //  suis », ce qui n'a pas le même sens pour un coordinateur et pour un
    //  gestionnaire de stock. Ils restent nommés par ce qu'ils montrent, pas
    //  par qui les voit ; c'est la liste du profil qui fait le routage, comme
    //  pour tous les autres blocs.
    // ══════════════════════════════════════════════════════════════════

    // ── Supervision : ce qui attend une validation ────────────────────
    'synthese_supervision' => [
        'titre'   => 'Synthèse',
        'largeur' => 'plein',
        'nu'      => true,
        'module'  => 'point_emuci',
        'donnees' => function (array $p) {
            $per = dash_periode($p);
            [$w, $args] = dash_filtre_site($p, 'pj.site_id');
            $pts = db_fetch_one(
                "SELECT COALESCE(SUM(CASE WHEN pj.statut='valide' THEN pj.total_engins END),0)  AS engins,
                        COALESCE(SUM(CASE WHEN pj.statut='valide' THEN pj.total_plaques END),0) AS plaques,
                        COUNT(*) FILTER (WHERE pj.statut='valide')    AS nb_valides,
                        COUNT(*) FILTER (WHERE pj.statut='brouillon') AS nb_attente
                 FROM op_points_journaliers pj
                 WHERE TO_CHAR(pj.date_point,'YYYY-MM') = ? $w",
                array_merge([$per['mois']], $args)) ?: [];

            [$wc, $ac] = dash_filtre_site($p, 'pj.site_id');
            $corr = (int)db_fetch_value(
                "SELECT COUNT(*) FROM demandes_correction_saisie dc
                 JOIN op_points_journaliers pj ON pj.id = dc.point_id
                 WHERE dc.statut = 'en_attente' $wc", $ac);

            [$wv, $av] = dash_filtre_site($p, 'v.site_id');
            $valid = (int)db_fetch_value(
                "SELECT COUNT(DISTINCT v.site_id) FROM validations_stock_matin v
                 WHERE v.date_validation = CURRENT_DATE $wv", $av);

            $sites = (int)db_fetch_value(
                $p['site_id'] ? "SELECT 1" : "SELECT COUNT(*) FROM sites WHERE actif=1");

            return ['pts' => $pts, 'corrections' => $corr, 'valides_matin' => $valid,
                    'sites' => $sites, 'periode' => $per];
        },
        'rendu' => function (array $d, array $p) {
            $pts = $d['pts'];
            $per = $d['periode'];
            $att = (int)($pts['nb_attente'] ?? 0);

            if (!$per['courant']) {
                $note = 'Aucun relevé validé ce mois-ci. Ces chiffres sont ceux de <b>'
                      . h($per['libelle']) . '</b>, le dernier mois avec de l\'activité.';
            } elseif ($att > 0) {
                $note = 'Il reste <b>' . $att . '</b> point' . ($att > 1 ? 's' : '')
                      . ' à valider. Tant qu\'ils sont en brouillon, leurs chiffres ne comptent pas ici.';
            } else {
                $note = 'Tous les points du mois sont validés.';
            }

            dash_bandeau(
                [
                    'lbl'  => 'Engins posés · ' . h($per['libelle'])
                              . ($p['site_id'] ? ' · ' . h($p['site_nom']) : ''),
                    'val'  => ent((float)($pts['engins'] ?? 0)),
                    'note' => $note,
                ],
                [
                    ['lbl' => 'Validés',    'val' => (int)($pts['nb_valides'] ?? 0), 'ton' => 'ok'],
                    ['lbl' => 'En attente', 'val' => $att,                           'ton' => 'ko'],
                ],
                [
                    ['lbl' => 'Plaques posées', 'val' => ent((float)($pts['plaques'] ?? 0)),
                     'sub' => 'Cumul sur ' . $per['libelle']],
                    ['lbl' => 'Corrections',    'val' => ent((float)$d['corrections']),
                     'sub' => $d['corrections'] > 0 ? 'Demandes à traiter' : 'Rien à traiter'],
                    ['lbl' => 'Stock validé',   'val' => ent((float)$d['valides_matin']),
                     'unite' => '/' . $d['sites'], 'sub' => 'Sites ayant validé ce matin'],
                    ['lbl' => 'Relevés saisis', 'val' => ent((float)(($pts['nb_valides'] ?? 0) + $att)),
                     'sub' => 'Tous statuts, sur ' . $per['libelle']],
                ]
            );
        },
    ],

    // ── Terrain : mon site aujourd'hui ────────────────────────────────
    'synthese_terrain' => [
        'titre'   => 'Synthèse',
        'largeur' => 'plein',
        'nu'      => true,
        'module'  => 'point_emuci',
        'donnees' => function (array $p) {
            $per = dash_periode($p);
            [$w, $args] = dash_filtre_site($p, 'pj.site_id');
            $pts = db_fetch_one(
                "SELECT COALESCE(SUM(CASE WHEN pj.statut='valide' THEN pj.total_engins END),0) AS engins,
                        COALESCE(SUM(pj.rivets_utilises),0)          AS rivets,
                        COUNT(*) FILTER (WHERE pj.statut='valide')    AS nb_valides,
                        COUNT(*) FILTER (WHERE pj.statut='brouillon') AS nb_attente
                 FROM op_points_journaliers pj
                 WHERE TO_CHAR(pj.date_point,'YYYY-MM') = ? $w",
                array_merge([$per['mois']], $args)) ?: [];

            [$wb, $ab] = dash_filtre_site($p, 'b.site_id');
            $films = (int)db_fetch_value(
                "SELECT COALESCE(SUM(b.films_restants),0) FROM op_bobines b
                 WHERE b.statut = 'en_cours' $wb", $ab);

            [$wr, $ar] = dash_filtre_site($p, 'sr.site_id');
            $riv = (int)db_fetch_value(
                "SELECT COALESCE(SUM(sr.quantite),0) FROM op_stock_rivets sr WHERE 1=1 $wr", $ar);

            [$wrc, $arc] = dash_filtre_site($p, 'rs.site_id');
            $rec = (int)db_fetch_value(
                "SELECT COUNT(*) FROM receptions_site rs
                 WHERE TO_CHAR(rs.created_at,'YYYY-MM') = ? $wrc",
                array_merge([$per['mois']], $arc));

            return ['pts' => $pts, 'films' => $films, 'rivets' => $riv,
                    'receptions' => $rec, 'periode' => $per];
        },
        'rendu' => function (array $d, array $p) {
            $pts = $d['pts'];
            $per = $d['periode'];
            $att = (int)($pts['nb_attente'] ?? 0);

            if (!$per['courant']) {
                $note = 'Aucun relevé validé ce mois-ci. Ces chiffres sont ceux de <b>'
                      . h($per['libelle']) . '</b>, votre dernier mois d\'activité.';
            } elseif ($att > 0) {
                $note = 'Vous avez <b>' . $att . '</b> relevé' . ($att > 1 ? 's' : '')
                      . ' encore en brouillon. Un brouillon n\'est pas transmis au superviseur.';
            } else {
                $note = 'Tous vos relevés du mois sont transmis.';
            }

            dash_bandeau(
                [
                    'lbl'  => 'Engins posés · ' . h($per['libelle']),
                    'val'  => ent((float)($pts['engins'] ?? 0)),
                    'note' => $note,
                ],
                [
                    ['lbl' => 'Transmis',  'val' => (int)($pts['nb_valides'] ?? 0), 'ton' => 'ok'],
                    ['lbl' => 'Brouillon', 'val' => $att,                           'ton' => 'ko'],
                ],
                [
                    ['lbl' => 'Films restants', 'val' => ent((float)$d['films']),
                     'sub' => 'Bobines en cours sur le site'],
                    ['lbl' => 'Rivets en stock', 'val' => ent((float)$d['rivets']),
                     'sub' => $d['rivets'] < 200 ? 'Sous le seuil de 200' : 'Au-dessus du seuil'],
                    ['lbl' => 'Rivets posés',   'val' => ent((float)($pts['rivets'] ?? 0)),
                     'sub' => 'Cumul sur ' . $per['libelle']],
                    ['lbl' => 'Réceptions',     'val' => ent((float)$d['receptions']),
                     'sub' => 'Enregistrées sur ' . $per['libelle']],
                ]
            );
        },
    ],

    // ── Stock bobines : la file d'attente de service ──────────────────
    'synthese_stock' => [
        'titre'   => 'Synthèse',
        'largeur' => 'plein',
        'nu'      => true,
        'module'  => 'bobines',
        'donnees' => function (array $p) use ($mois) {
            [$wb, $ab] = dash_filtre_site($p, 'b.site_id');
            $bob = db_fetch_one(
                "SELECT COALESCE(SUM(CASE WHEN b.statut='en_cours' THEN b.films_restants END),0) AS restants,
                        COALESCE(SUM(b.films_utilises),0)          AS utilises,
                        COUNT(*) FILTER (WHERE b.statut='en_cours') AS en_cours
                 FROM op_bobines b WHERE 1=1 $wb", $ab) ?: [];

            [$wc, $ac] = dash_filtre_site($p, 'c.site_id');
            $cmd = db_fetch_one(
                "SELECT COUNT(*) FILTER (WHERE c.statut IN ('en_attente','valide'))       AS a_servir,
                        COUNT(*) FILTER (WHERE c.statut IN ('livre','recu','servie'))     AS servies
                 FROM commandes_bobines c WHERE 1=1 $wc", $ac) ?: [];

            [$wr, $ar] = dash_filtre_site($p, 'sr.site_id');
            $bas = (int)db_fetch_value(
                "SELECT COUNT(DISTINCT sr.site_id) FROM op_stock_rivets sr
                 WHERE sr.quantite < 200 $wr", $ar);

            return ['bob' => $bob, 'cmd' => $cmd, 'sites_bas' => $bas];
        },
        'rendu' => function (array $d) {
            $srv = (int)($d['cmd']['a_servir'] ?? 0);
            dash_bandeau(
                [
                    'lbl'  => 'Films restants',
                    'val'  => ent((float)($d['bob']['restants'] ?? 0)),
                    'note' => $srv > 0
                        ? 'Il y a <b>' . $srv . '</b> commande' . ($srv > 1 ? 's' : '')
                          . ' en attente de service.'
                        : 'Aucune commande en attente.',
                ],
                [
                    ['lbl' => 'Servies',    'val' => (int)($d['cmd']['servies'] ?? 0), 'ton' => 'ok'],
                    ['lbl' => 'À servir',   'val' => $srv,                             'ton' => 'ko'],
                ],
                [
                    ['lbl' => 'Bobines en cours', 'val' => ent((float)($d['bob']['en_cours'] ?? 0)),
                     'sub' => 'Ouvertes sur le terrain'],
                    ['lbl' => 'Films utilisés',   'val' => ent((float)($d['bob']['utilises'] ?? 0)),
                     'sub' => 'Depuis l\'ouverture des bobines'],
                    ['lbl' => 'Sites sous seuil', 'val' => ent((float)$d['sites_bas']),
                     'sub' => 'Moins de 200 rivets'],
                    ['lbl' => 'À servir',         'val' => ent((float)$srv),
                     'sub' => $srv > 0 ? 'Commandes en attente' : 'File vide'],
                ]
            );
        },
    ],

    // ── Parc informatique : l'état du matériel ────────────────────────
    'synthese_parc' => [
        'titre'   => 'Synthèse',
        'largeur' => 'plein',
        'nu'      => true,
        'module'  => 'equipements',
        'donnees' => function (array $p) use ($mois) {
            [$ws, $as] = dash_filtre_site($p, 'e.site_id');
            [$wc, $ac] = dash_filtre_categorie($p, 'e.categorie');
            $eq = db_fetch_one(
                "SELECT COUNT(*)                                          AS total,
                        COUNT(*) FILTER (WHERE e.etat IN ('neuf','bon'))  AS sains,
                        COUNT(*) FILTER (WHERE e.etat IN ('usage','reforme')) AS a_traiter,
                        COUNT(*) FILTER (WHERE e.date_fin_cycle IS NOT NULL
                                           AND e.date_fin_cycle <= CURRENT_DATE + 60) AS fin_cycle
                 FROM equipements e WHERE e.actif = 1 $ws $wc",
                array_merge($as, $ac)) ?: [];

            [$wi, $ai] = dash_filtre_site($p, 'i.site_id');
            $inter = (int)db_fetch_value(
                "SELECT COUNT(*) FROM interventions_maintenance i
                 WHERE TO_CHAR(i.date_intervention,'YYYY-MM') = ? $wi",
                array_merge([$mois], $ai));

            return ['eq' => $eq, 'interventions' => $inter];
        },
        'rendu' => function (array $d) {
            $aTraiter = (int)($d['eq']['a_traiter'] ?? 0);
            $fin      = (int)($d['eq']['fin_cycle'] ?? 0);
            dash_bandeau(
                [
                    'lbl'  => 'Équipements actifs',
                    'val'  => ent((float)($d['eq']['total'] ?? 0)),
                    'note' => $fin > 0
                        ? '<b>' . $fin . '</b> arrive' . ($fin > 1 ? 'nt' : '')
                          . ' en fin de cycle dans les soixante jours.'
                        : 'Aucune fin de cycle dans les soixante jours.',
                ],
                [
                    ['lbl' => 'Neuf ou bon', 'val' => (int)($d['eq']['sains'] ?? 0), 'ton' => 'ok'],
                    ['lbl' => 'À traiter',   'val' => $aTraiter,                     'ton' => 'ko'],
                ],
                [
                    ['lbl' => 'Interventions', 'val' => ent((float)$d['interventions']),
                     'sub' => 'Enregistrées ce mois'],
                    ['lbl' => 'Fin de cycle',  'val' => ent((float)$fin),
                     'sub' => 'Dans les soixante jours'],
                    ['lbl' => 'À traiter',     'val' => ent((float)$aTraiter),
                     'sub' => 'Usagés ou réformés'],
                    ['lbl' => 'En service',    'val' => ent((float)($d['eq']['sains'] ?? 0)),
                     'sub' => 'État neuf ou bon'],
                ]
            );
        },
    ],

    // ══════════════════════════════════════════════════════════════════
    //  GRAPHES
    // ══════════════════════════════════════════════════════════════════

    // ── Évolution des engins posés ────────────────────────────────────
    'evolution_engins' => [
        'titre'     => 'Évolution des engins posés',
        'soustitre' => 'Douze derniers mois',
        'module'    => 'point_emuci',
        'donnees'   => function (array $p) {
            [$w, $args] = dash_filtre_site($p, 'pj.site_id');
            $lignes = db_fetch_all(
                "SELECT TO_CHAR(pj.date_point,'YYYY-MM') AS m,
                        COALESCE(SUM(pj.total_engins),0) AS v
                 FROM op_points_journaliers pj
                 WHERE pj.statut = 'valide'
                   AND pj.date_point >= DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '11 months' $w
                 GROUP BY 1 ORDER BY 1", $args);

            // Les mois sans relevé doivent apparaître à zéro : une série
            // trouée décale les points et fait mentir la pente.
            $par_mois = [];
            foreach ($lignes as $l) $par_mois[$l['m']] = (float)$l['v'];

            $noms = ['', 'janv', 'févr', 'mars', 'avr', 'mai', 'juin',
                     'juil', 'août', 'sept', 'oct', 'nov', 'déc'];
            $valeurs = $libelles = [];
            for ($i = 11; $i >= 0; $i--) {
                $t = strtotime("-$i months", strtotime(date('Y-m-01')));
                $cle = date('Y-m', $t);
                $valeurs[]  = $par_mois[$cle] ?? 0;
                $libelles[] = $noms[(int)date('n', $t)];
            }
            return ['valeurs' => $valeurs, 'libelles' => $libelles];
        },
        'rendu' => function (array $d) {
            if (array_sum($d['valeurs']) <= 0) {
                dash_vide('Aucun point validé sur les douze derniers mois.');
                return;
            }
            dash_graphe('courbe', $d['valeurs'], ['libelles' => $d['libelles'], 'hauteur' => 180]);
        },
    ],

    // ── Répartition du parc ───────────────────────────────────────────
    'repartition_parc' => [
        'titre'     => 'État du parc',
        'soustitre' => 'Équipements actifs par état',
        'module'    => 'equipements',
        'lien'      => ['/pages/equipements.php', 'Le parc'],
        'donnees'   => function (array $p) {
            [$ws, $as] = dash_filtre_site($p, 'e.site_id');
            [$wc, $ac] = dash_filtre_categorie($p, 'e.categorie');
            return db_fetch_all(
                "SELECT e.etat, COUNT(*) AS n FROM equipements e
                 WHERE e.actif = 1 $ws $wc GROUP BY e.etat ORDER BY n DESC",
                array_merge($as, $ac));
        },
        'rendu' => function (array $d) {
            if (!$d) { dash_vide('Aucun équipement actif sur ce périmètre.'); return; }
            $teintes = ['neuf' => '#1B75BC', 'bon' => '#16a34a',
                        'usage' => '#d97706', 'reforme' => '#dc2626'];
            $noms    = ['neuf' => 'Neuf', 'bon' => 'Bon',
                        'usage' => 'Usagé', 'reforme' => 'Réformé'];
            $val = $coul = []; $leg = [];
            foreach ($d as $r) {
                $c = $teintes[$r['etat']] ?? '#6d28d9';
                $val[]  = (int)$r['n'];
                $coul[] = $c;
                $leg[]  = ['lbl' => $noms[$r['etat']] ?? ucfirst($r['etat']),
                           'val' => (int)$r['n'], 'couleur' => $c];
            }
            $parle = [];
            foreach ($leg as $e) $parle[] = $e['lbl'] . ' ' . (int)$e['val'];

            echo '<div class="donut-wrap">';
            dash_graphe('anneau', $val, [
                'couleurs' => $coul,
                'taille'   => 120,
                'alt'      => 'État du parc, ' . array_sum($val) . ' équipements actifs : '
                              . implode(', ', $parle) . '.',
            ]);
            dash_legende($leg);
            echo '</div>';
        },
    ],

    // ── Engins posés par site ─────────────────────────────────────────
    'perf_sites' => [
        'titre'     => 'Engins posés par site',
        'soustitre' => 'Points validés',
        'module'    => 'point_emuci',
        'largeur'   => 'plein',
        'donnees'   => function (array $p) {
            // Même période que le bandeau, pour que les deux racontent le
            // même mois : un classement par site sur un mois vide, juste
            // sous une synthèse qui en montre un autre, se contredirait.
            $per = dash_periode($p);
            [$w, $args] = dash_filtre_site($p, 'pj.site_id');
            return [
                'periode' => $per,
                'lignes'  => db_fetch_all(
                    "SELECT s.nom, COALESCE(SUM(pj.total_engins),0) AS v
                     FROM op_points_journaliers pj
                     JOIN sites s ON s.id = pj.site_id
                     WHERE pj.statut = 'valide'
                       AND TO_CHAR(pj.date_point,'YYYY-MM') = ? $w
                     GROUP BY s.nom HAVING SUM(pj.total_engins) > 0
                     ORDER BY 2 DESC LIMIT 12",
                    array_merge([$per['mois']], $args)),
            ];
        },
        'rendu' => function (array $d) {
            if (!$d['lignes']) { dash_vide('Aucun engin posé sur ce périmètre.'); return; }
            echo '<div class="card-sub" style="margin:-8px 0 14px">'
               . h(ucfirst($d['periode']['libelle'])) . '</div>';
            dash_graphe('barres',
                array_map(fn($r) => (float)$r['v'], $d['lignes']),
                ['libelles' => array_map(fn($r) => $r['nom'], $d['lignes'])]);
        },
    ],

    // ── Points journaliers récents ────────────────────────────────────
    'points_recents' => [
        'titre'     => 'Points journaliers récents',
        'soustitre' => 'Cinq derniers relevés',
        'module'    => 'point_emuci',
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
        'titre'     => 'Points en attente de validation',
        'soustitre' => 'Relevés encore en brouillon',
        'module'    => 'point_emuci',
        'lien'      => ['/pages/operations/point_journalier.php', 'Valider'],
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
                 WHERE pj.statut = 'brouillon' $w
                 ORDER BY pj.date_point ASC
                 LIMIT 8", $args);
        },
        'rendu' => function (array $d) {
            if (!$d) { dash_vide('Aucun point en attente. Tout est validé.', true); return; }
            echo '<table class="dtbl"><thead><tr><th>Date</th><th>Site</th><th>Auteur</th>'
               . '<th style="text-align:right">Attente</th></tr></thead><tbody>';
            foreach ($d as $r) {
                $j = (int)$r['anciennete'];
                // Un brouillon de la veille est normal ; au-delà de trois
                // jours il est probablement oublié.
                $cls = $j >= 3 ? 'mvh-r' : ($j >= 1 ? 'mvh-o' : 'mvh-g');
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
        'titre'     => 'Corrections en attente',
        'soustitre' => 'Demandes de correction de saisie',
        'module'    => 'point_emuci',
        'lien'      => ['/pages/validation_stock_matin.php', 'Traiter'],
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
        'titre'     => 'Validations du stock matin',
        'soustitre' => 'Sept derniers jours',
        'module'    => 'inventaire_bobines',
        'lien'      => ['/pages/validation_stock_matin.php', 'Voir le détail'],
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
        'module'    => 'commandes',
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
    // Un bloc « nu » porte sa propre structure — le bandeau de synthèse est
    // fait de plusieurs cartes, l'envelopper dans une carte de plus donnerait
    // des cartes imbriquées. En cas d'erreur il retombe malgré tout dans une
    // carte, seul contenant qui sait afficher un message d'indisponibilité.
    $nu = !empty($bloc['nu']);

    try {
        $donnees = ($bloc['donnees'])($portee);
    } catch (Throwable $e) {
        error_log('dashboard : données du bloc ' . $bloc['id'] . ' — ' . $e->getMessage());
        dash_carte_debut($bloc);
        dash_vide('Ces données sont momentanément indisponibles.');
        dash_carte_fin();
        return;
    }

    if (!$nu) dash_carte_debut($bloc);
    try {
        ($bloc['rendu'])($donnees, $portee);
    } catch (Throwable $e) {
        error_log('dashboard : rendu du bloc ' . $bloc['id'] . ' — ' . $e->getMessage());
        if ($nu) dash_carte_debut($bloc);
        dash_vide('Affichage indisponible pour ce bloc.');
        if ($nu) dash_carte_fin();
    }
    if (!$nu) dash_carte_fin();
}
