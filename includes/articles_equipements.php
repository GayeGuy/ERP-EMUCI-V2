<?php
// ============================================================
//  includes/articles_equipements.php
//
//  Articles de la famille « Matériel et outillage industriel » : quand ils
//  arrivent sur un site (réception d'une commande, ou Distribution depuis
//  la page Articles), ils ne vont pas dans le stock d'articles du site mais
//  dans son stock d'ÉQUIPEMENTS OPÉRATIONNELS — une fiche par unité, avec
//  numéro de série, affectée au site.
//
//  Chaque article de cette famille est rattaché une fois pour toutes à un
//  type d'équipement opérationnel (articles.nomenclature_id) ; s'il n'en a
//  pas, le type est créé à la première arrivée, au nom de l'article.
// ============================================================
require_once __DIR__ . '/db.php';

const ART_FAMILLE_EQUIPEMENT_OP = 'Matériel et outillage industriel';
const ART_EQUIP_OP_DUREE_MOIS   = 60;   // OHADA, matériel et outillage : 5 ans (cf. pages/equipements.php)

/** L'article appartient-il à la famille « Matériel et outillage industriel » ? */
function art_est_equipement_op(int $article_id): bool {
    static $cache = [];
    if (!$article_id) return false;
    if (!array_key_exists($article_id, $cache)) {
        $cache[$article_id] = (bool)db_fetch_value(
            "SELECT COUNT(*) FROM articles a JOIN familles_achat f ON f.id = a.famille_id
              WHERE a.id = ? AND LOWER(TRIM(f.libelle)) = LOWER(?)",
            [$article_id, ART_FAMILLE_EQUIPEMENT_OP]);
    }
    return $cache[$article_id];
}

/** Type d'équipement opérationnel de l'article, créé au besoin. */
function art_nomenclature_op(int $article_id): int {
    // Sans la migration (articles.nomenclature_id), le type est retrouvé par
    // son nom à chaque fois : la page marche avant que Neon soit à jour.
    $avec_col = db_colonne_existe('articles', 'nomenclature_id');
    $a = db_fetch_one("SELECT id, libelle" . ($avec_col ? ", nomenclature_id" : "") . " FROM articles WHERE id=?", [$article_id]);
    if (!$a) throw new Exception("Article #$article_id introuvable.");
    if (!empty($a['nomenclature_id'])
        && db_fetch_value("SELECT COUNT(*) FROM nomenclatures WHERE id=?", [(int)$a['nomenclature_id']])) {
        return (int)$a['nomenclature_id'];
    }
    // Un type opérationnel du même nom existe déjà : on le reprend plutôt
    // que d'en créer un doublon.
    $nom_id = (int)(db_fetch_value(
        "SELECT id FROM nomenclatures WHERE categorie='operationnel' AND LOWER(TRIM(libelle)) = LOWER(TRIM(?)) ORDER BY id LIMIT 1",
        [$a['libelle']]) ?? 0);
    if (!$nom_id) {
        $base = 'OP' . str_pad((string)$article_id, 4, '0', STR_PAD_LEFT);
        $code = $base; $n = 1;
        while (db_fetch_value("SELECT COUNT(*) FROM nomenclatures WHERE code=?", [$code])) {
            $code = substr($base, 0, 8) . '-' . $n++;
        }
        db_query(
            "INSERT INTO nomenclatures (code, categorie, libelle, description, duree_amortissement_mois)
             VALUES (?, 'operationnel', ?, ?, ?)",
            [$code, mb_substr($a['libelle'], 0, 150), "Créé automatiquement depuis l'article #$article_id", ART_EQUIP_OP_DUREE_MOIS]);
        $nom_id = (int)db_last_id();
    }
    if ($avec_col) db_query("UPDATE articles SET nomenclature_id=? WHERE id=?", [$nom_id, $article_id]);
    return $nom_id;
}

/**
 * Crée $qte fiches d'équipement opérationnel affectées au site.
 * Retourne les numéros de série créés.
 */
function art_creer_equipements_op(int $article_id, int $site_id, int $qte, ?int $user_id, string $origine): array {
    if ($qte <= 0) return [];
    $nom_id = art_nomenclature_op($article_id);
    $code   = db_fetch_value("SELECT code FROM nomenclatures WHERE id=?", [$nom_id]) ?: 'EQP';
    $prix   = (float)(db_fetch_value("SELECT prix_unitaire FROM articles WHERE id=?", [$article_id]) ?? 0);
    $date   = date('Y-m-d');
    $fin    = date('Y-m-d', strtotime("$date +" . ART_EQUIP_OP_DUREE_MOIS . ' months'));

    // Numéro « CODE-EMUCI-NNNN », séquence par type (même règle que
    // _prochain_numero_serie() dans pages/equipements.php).
    $prefixe = $code . '-EMUCI-';
    $seq = 0;
    foreach (db_fetch_all("SELECT numero_serie_interne FROM equipements WHERE numero_serie_interne LIKE ?", [$prefixe . '%']) as $r) {
        $suffixe = substr($r['numero_serie_interne'], strlen($prefixe));
        if (ctype_digit($suffixe)) $seq = max($seq, (int)$suffixe);
    }
    $chrono = (int)db_fetch_value("SELECT COALESCE(MAX(numero_chrono),0) FROM equipements");

    $numeros = [];
    for ($i = 0; $i < $qte; $i++) {
        $nsi = $prefixe . str_pad((string)++$seq, 4, '0', STR_PAD_LEFT);
        db_query(
            "INSERT INTO equipements
             (numero_serie_interne, numero_chrono, nomenclature_id, categorie, site_id, etat, statut_stock,
              date_acquisition, date_fin_cycle, prix_achat, duree_amortissement_mois, observations, actif, created_by)
             VALUES (?,?,?,'operationnel',?,'neuf','affecte',?,?,?,?,?,1,?)",
            [$nsi, ++$chrono, $nom_id, $site_id, $date, $fin, $prix, ART_EQUIP_OP_DUREE_MOIS, $origine, $user_id]);
        $eid = (int)db_last_id();
        db_query(
            "INSERT INTO mouvements_equipements (equipement_id, type, site_dest_id, notes, created_by)
             VALUES (?, 'entree', ?, ?, ?)",
            [$eid, $site_id, $origine, $user_id]);
        $numeros[] = $nsi;
    }
    return $numeros;
}
