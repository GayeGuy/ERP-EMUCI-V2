<?php
// ============================================================
//  includes/commande_flux.php
//  Préparation par l'entrepôt et livraison par un livreur
//  (sql/migration_commandes_preparation_livreur.sql).
//
//  Une commande validée (en_attente_livraison) est préparée ligne par ligne :
//   - le gestionnaire de stock répartit (dépôt de chaque ligne, sortie
//     diverse) et prépare les lignes du magasin (ou sans stock suivi) ;
//   - le magasinier de l'entrepôt prépare les lignes de son entrepôt et peut
//     choisir les bobines (à défaut de magasinier actif, le gestionnaire) ;
//   - le coordinateur du site fournisseur prépare une sortie diverse acceptée.
//  Préparer = arrêter la quantité qui partira (quantite_livree, motif si elle
//  diffère) ; rien ne bouge en stock. Toutes les lignes préparées → statut
//  prete_enlevement ; le gestionnaire désigne le livreur, qui récupère
//  (débit des dépôts, statut en_cours_livraison) puis livre au site avec une
//  photo du BL signé (statut livree_site) ; le chef de site réceptionne.
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/commande_bobines.php';
require_once __DIR__ . '/reception_commande.php';
require_once __DIR__ . '/commande_inter_site.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/audit.php';

const CFLUX_ROLE_ENTREPOT = 'magasinier_entrepot';
const CFLUX_ROLE_LIVREUR  = 'livreur';
const CFLUX_NOTE_SERVIE   = 'Entièrement servie par sorties diverses';

function cflux_actif(): bool {
    static $a = null;
    return $a ??= db_colonne_existe('commande_lignes', 'prepare_at') && db_colonne_existe('commandes', 'livreur_id');
}

/** Utilisateurs actifs d'un rôle, éventuellement rattachés à un site. */
function cflux_users_role(string $slug, ?int $site_id = null): array {
    $sql = "SELECT u.id, CONCAT(u.prenom,' ',u.nom) AS nom FROM users u JOIN roles r ON r.id=u.role_id
            WHERE r.slug=? AND u.actif=1" . ($site_id !== null ? " AND u.site_id=?" : "") . " ORDER BY u.prenom, u.nom";
    return db_fetch_all($sql, $site_id !== null ? [$slug, $site_id] : [$slug]);
}

/** L'entrepôt a-t-il un magasinier actif ? Sinon le gestionnaire prépare ses lignes. */
function cflux_entrepot_a_magasinier(int $site_id): bool {
    static $c = [];
    return $c[$site_id] ??= (bool)cflux_users_role(CFLUX_ROLE_ENTREPOT, $site_id);
}

function cflux_type_site(int $site_id): ?string {
    static $c = [];
    if (!$site_id) return null;
    return $c[$site_id] ??= (db_fetch_value("SELECT type FROM sites WHERE id=?", [$site_id]) ?: null);
}

/** Lignes qui sortent d'un stock suivi (et donc d'un dépôt) : article du catalogue, PMMA, rivets, rouleaux. */
function cflux_ligne_a_depot(array $l): bool {
    $t = $l['type_article'] ?? '';
    return cmdbob_est_rouleau($t) || in_array($t, ['pmma', 'rivet'], true) || ($t === 'article' && !empty($l['article_id']));
}

/**
 * Dépôt d'où part la ligne : le site fournisseur d'une sortie diverse ;
 * l'entrepôt pour les rouleaux (les bobines y sont) ; sinon le dépôt choisi
 * par le gestionnaire (commande_lignes.depot_source_id).
 */
function cflux_depot_ligne(array $cmd, array $l): ?int {
    if (!empty($cmd['site_source_id'])) return (int)$cmd['site_source_id'];
    if (cmdbob_est_rouleau($l['type_article'] ?? '')) return cmdbob_entrepot_id() ?: null;
    return !empty($l['depot_source_id']) ? (int)$l['depot_source_id'] : null;
}

/**
 * Qui prépare la ligne : 'site' (sortie diverse), 'entrepot' (entrepôt avec
 * un magasinier actif), 'magasin' (le gestionnaire de stock).
 */
function cflux_preparateur(array $cmd, array $l): string {
    if (!empty($cmd['site_source_id'])) return 'site';
    $dep = cflux_depot_ligne($cmd, $l);
    if ($dep && cflux_type_site($dep) === 'entrepot' && cflux_entrepot_a_magasinier($dep)) return 'entrepot';
    return 'magasin';
}

/**
 * L'utilisateur peut-il préparer la ligne ?
 * $ctx : ['admin' => bool, 'gestionnaire' => bool, 'magasinier' => bool,
 *         'coord' => bool, 'site_id' => int].
 */
function cflux_peut_preparer(array $cmd, array $l, array $ctx): bool {
    if (($l['statut_ligne'] ?? '') !== 'valide') return false;
    if ($ctx['admin']) return true;
    switch (cflux_preparateur($cmd, $l)) {
        case 'site':
            return $ctx['coord'] && !empty($cmd['accepte_at']) && (int)$cmd['site_source_id'] === (int)$ctx['site_id'];
        case 'entrepot':
            return $ctx['magasinier'] && cflux_depot_ligne($cmd, $l) === (int)$ctx['site_id'];
        default:
            return $ctx['gestionnaire'];
    }
}

/** La ligne est-elle prête à partir ? (préparée, rejetée, ou vidée par sortie diverse) */
function cflux_ligne_prete(array $l): bool {
    return ($l['statut_ligne'] ?? '') === 'rejete' || (int)$l['quantite'] <= 0 || !empty($l['prepare_at']);
}

/**
 * Toutes les lignes prêtes → la commande passe « Prête à enlever » et le
 * gestionnaire est prévenu. Renvoie vrai si elle vient de passer prête.
 */
function cflux_verifier_prete(int $cmd_id): bool {
    $cmd = db_fetch_one("SELECT * FROM commandes WHERE id=?", [$cmd_id]);
    if (!$cmd || $cmd['statut'] !== 'en_attente_livraison') return false;
    $lignes = db_fetch_all("SELECT * FROM commande_lignes WHERE commande_id=?", [$cmd_id]);
    if (!$lignes) return false;
    foreach ($lignes as $l) if (!cflux_ligne_prete($l)) return false;
    $maj = db_query("UPDATE commandes SET statut='prete_enlevement' WHERE id=? AND statut='en_attente_livraison'", [$cmd_id]);
    if ($maj->rowCount() !== 1) return false;
    $src = (int)($cmd['site_source_id'] ?? 0);
    $ou  = $src ? ' chez ' . (db_fetch_value("SELECT nom FROM sites WHERE id=?", [$src]) ?? 'le site fournisseur') : '';
    foreach (users_fonction(FONCTION_MAGASIN) as $uid)
        notif_create('info', "🚚 Commande {$cmd['numero_commande']} prête à enlever$ou",
            "Toutes les lignes sont préparées : désignez le livreur.", (int)$uid, '/pages/commandes.php');
    return true;
}

/**
 * Répartition proposée d'une commande qui devient à préparer : rouleaux,
 * PMMA et rivets à l'entrepôt, articles au magasin (à défaut l'entrepôt).
 * Le gestionnaire la change ligne par ligne avant la préparation. Le
 * magasinier de l'entrepôt voit ainsi ses lignes tout de suite, et il est
 * prévenu.
 */
function cflux_repartition_defaut(int $cmd_id): void {
    if (!cflux_actif()) return;
    $cmd = db_fetch_one("SELECT * FROM commandes WHERE id=?", [$cmd_id]);
    if (!$cmd || !empty($cmd['site_source_id'])) return;
    $entrepot = cmdbob_entrepot_id();
    $magasin  = (int)(db_fetch_value("SELECT id FROM sites WHERE type='magasin' AND actif=1 ORDER BY id LIMIT 1") ?? 0);
    $a_prevenir = [];
    foreach (db_fetch_all("SELECT * FROM commande_lignes WHERE commande_id=? AND statut_ligne='valide' AND depot_source_id IS NULL", [$cmd_id]) as $l) {
        if (!cflux_ligne_a_depot($l)) continue;
        $dep = $l['type_article'] === 'article' ? ($magasin ?: $entrepot) : $entrepot;
        if (!$dep) continue;
        db_query("UPDATE commande_lignes SET depot_source_id=? WHERE id=?", [$dep, (int)$l['id']]);
        $l['depot_source_id'] = $dep;
        if (cflux_preparateur($cmd, $l) === 'entrepot') $a_prevenir[$dep][] = $l['libelle'];
    }
    foreach ($a_prevenir as $dep => $libs)
        foreach (cflux_users_role(CFLUX_ROLE_ENTREPOT, (int)$dep) as $u)
            notif_create('info', "📦 Commande {$cmd['numero_commande']} à préparer",
                count($libs) . " ligne(s) à préparer à l'entrepôt : " . implode(', ', $libs) . '.', (int)$u['id'], '/pages/commandes.php');
}

/** La commande concerne-t-elle l'entrepôt (une ligne y est servie) ? */
function cflux_concerne_entrepot(int $cmd_id, int $site_id): bool {
    if (!$site_id) return false;
    return (bool)db_fetch_value("SELECT COUNT(*) FROM commande_lignes WHERE commande_id=? AND depot_source_id=?", [$cmd_id, $site_id]);
}

/**
 * Enlèvement par le livreur : la commande quitte ses dépôts. Débite chaque
 * dépôt de la quantité préparée (bloquant si insuffisant), trace les bobines,
 * ouvre le bon de distribution, passe la commande « En livraison ».
 * Transaction de l'appelant. Renvoie le message à afficher.
 */
function cflux_enlever(array $cmd, array $user): string {
    $cmd_id = (int)$cmd['id'];
    $src    = (int)($cmd['site_source_id'] ?? 0);
    $src_nom = $src ? (db_fetch_value("SELECT nom FROM sites WHERE id=?", [$src]) ?? '') : '';
    $livreur = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));

    // Sous condition du statut : de deux clics, un seul débite.
    $maj = db_query("UPDATE commandes SET statut='en_cours_livraison', livraison_par=?, livraison_at=NOW() WHERE id=? AND statut='prete_enlevement'",
        [$user['id'], $cmd_id]);
    if ($maj->rowCount() !== 1) throw new Exception('Cette commande vient déjà d\'être récupérée, ou n\'est plus prête.');

    $num_dist = 'DS-' . $cmd['numero_commande'];
    db_query("INSERT INTO distributions_site (numero_distribution,commande_id,site_id,date_distribution,statut,notes,created_by,expedie_at)
              VALUES (?,?,?,?,'en_cours_livraison',?,?,NOW())",
        [$num_dist, $cmd_id, $cmd['site_id'], date('Y-m-d'), "Récupérée par $livreur", $user['id']]);
    $dist_id = (int)db_last_id();

    $depots = [];
    foreach (db_fetch_all("SELECT * FROM commande_lignes WHERE commande_id=? ORDER BY id", [$cmd_id]) as $l) {
        $q = $l['statut_ligne'] === 'rejete' ? 0 : (int)($l['quantite_livree'] ?? 0);
        if ($l['statut_ligne'] === 'rejete')
            db_query("UPDATE commande_lignes SET quantite_livree=0, motif_ecart=COALESCE(motif_ecart, ?) WHERE id=?",
                ['Ligne rejetée' . ($l['motif_rejet'] ? ' : ' . $l['motif_rejet'] : ''), (int)$l['id']]);
        if ($q <= 0) continue;
        // Une ligne issue d'une FEB suit ce qui part réellement.
        if (!empty($l['feb_ligne_id']) && $q !== (int)$l['quantite'])
            db_query("UPDATE feb_lignes SET quantite=? WHERE id=?", [$q, (int)$l['feb_ligne_id']]);
        db_query("INSERT INTO distribution_lignes (distribution_id,article_id,commande_ligne_id,libelle,quantite_envoyee,unite,statut)
                  VALUES (?,?,?,?,?,?,'en_cours_livraison')",
            [$dist_id, $l['type_article'] === 'article' ? $l['article_id'] : null, (int)$l['id'], $l['libelle'], $q, $l['unite']]);
        if (cmdbob_est_rouleau($l['type_article'])) continue;   // les rouleaux sont tracés plus bas
        $dep = cflux_depot_ligne($cmd, $l);
        if (!$dep || rcp_cle_stock($l) === null) continue;     // pas de stock suivi
        db_query("UPDATE commande_lignes SET depot_source_id=? WHERE id=?", [$dep, (int)$l['id']]);
        $dispo  = rcp_stock($l, $dep, true);
        $besoin = rcp_unites($l, $q);
        $dep_nom = db_fetch_value("SELECT nom FROM sites WHERE id=?", [$dep]) ?? '';
        if ($dispo !== null && $dispo < $besoin)
            throw new Exception("Stock insuffisant pour « {$l['libelle']} » à $dep_nom : $dispo disponible(s), $besoin requis.");
        // Stock central (articles) : seulement pour un dépôt, pas pour un site.
        rcp_mouvement($l, $dep, -$q, cis_est_depot($dep));
        $depots[$dep] = true;
    }

    foreach (cmdbob_par_ligne($cmd_id) as $_bobs) {
        foreach ($_bobs as $_b) {
            if ($src && !db_fetch_value(
                    "SELECT 1 FROM op_bobines b WHERE b.id=? AND b.site_id=? AND b.statut IN ('en_stock','en_cours')
                       AND NOT EXISTS (SELECT 1 FROM op_films_utilises fu JOIN op_points_journaliers p ON p.id = fu.point_id
                                       WHERE fu.bobine_id = b.id AND p.statut <> 'valide')", [$_b['id'], $src]))
                throw new Exception("Bobine {$_b['numero']} : plus disponible chez $src_nom (déplacée, ou saisie dans un point pas encore validé). Faites refaire le choix.");
            $st = (int)db_fetch_value("SELECT stock_systeme FROM op_bobines WHERE id=?", [$_b['id']]);
            db_query("INSERT INTO mouvements_bobines (bobine_id,type,quantite,stock_avant,stock_apres,motif,created_by) VALUES (?,?,?,?,?,?,?)",
                [$_b['id'], 'transfert', 0, $st, $st,
                 "Récupérée par $livreur " . ($src ? "chez $src_nom " : '') . "— commande {$cmd['numero_commande']} (en attente de réception)", $user['id']]);
            if (db_colonne_existe('commande_ligne_bobines', 'films_expedies'))
                db_query("UPDATE commande_ligne_bobines SET films_expedies=? WHERE commande_id=? AND bobine_id=? AND statut='assigne'",
                    [$src ? (int)db_fetch_value("SELECT films_restants FROM op_bobines WHERE id=?", [$_b['id']]) : $st, $cmd_id, $_b['id']]);
        }
    }
    db_query("UPDATE commandes SET depot_source_id=?, chauffeur=? WHERE id=?",
        [$src ?: (count($depots) === 1 ? array_key_first($depots) : null), $livreur, $cmd_id]);

    foreach (cis_coordinateurs((int)$cmd['site_id']) as $uid)
        notif_create('info', "🚚 Commande {$cmd['numero_commande']} en route",
            "$livreur a récupéré votre commande et l'achemine. Vous la réceptionnerez à son arrivée.", $uid, '/pages/commandes.php');
    audit_log($user['id'], 'UPDATE', 'commandes', $cmd_id, "Commande récupérée par le livreur $livreur — bon $num_dist" . ($src ? " — chez $src_nom" : ''));
    return 'Commande récupérée : vous pouvez l\'acheminer. Le site est prévenu.';
}
