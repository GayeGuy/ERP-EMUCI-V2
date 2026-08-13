<?php
// ============================================================
//  includes/inventaire.php — Sessions & inventaire mensuel
//  Logique partagée entre l'ouverture de session (auto-provisionnement
//  de l'inventaire de chaque site, cf. pages/inventaire_sessions.php)
//  et pages/inventaire_bobines.php.
// ============================================================
require_once __DIR__ . '/db.php';

// ── Durée (en mois) de chaque type de période standard
const INV_PERIODES = ['mensuel' => 1, 'trimestriel' => 3, 'semestriel' => 6, 'annuel' => 12];

function inv_periode_label(string $p): string {
    return ['mensuel' => 'Mensuelle', 'trimestriel' => 'Trimestrielle',
            'semestriel' => 'Semestrielle', 'annuel' => 'Annuelle'][$p] ?? ucfirst($p);
}

// ── Date de fin déduite de la périodicité (jamais saisie librement)
function inv_date_fin(string $debut, string $typePeriode): string {
    $mois = INV_PERIODES[$typePeriode] ?? 1;
    return date('Y-m-d', strtotime("$debut +$mois months -1 day"));
}

// ── Libellé par défaut quand l'admin n'en saisit pas (colonne jamais vide)
function inv_libelle_auto(string $debut, string $typePeriode): string {
    $ts    = strtotime($debut);
    $annee = (int)date('Y', $ts);
    $mois  = (int)date('n', $ts);
    $mois_fr = [1=>'janvier',2=>'février',3=>'mars',4=>'avril',5=>'mai',6=>'juin',
                7=>'juillet',8=>'août',9=>'septembre',10=>'octobre',11=>'novembre',12=>'décembre'];
    switch ($typePeriode) {
        case 'mensuel':     $periode = $mois_fr[$mois] . ' ' . $annee; break;
        case 'trimestriel': $periode = 'T' . (int)ceil($mois / 3) . ' ' . $annee; break;
        case 'semestriel':  $periode = 'S' . ($mois <= 6 ? 1 : 2) . ' ' . $annee; break;
        case 'annuel':      $periode = (string)$annee; break;
        default:            $periode = $debut;
    }
    return 'Inventaire ' . mb_strtolower(inv_periode_label($typePeriode)) . ' — ' . $periode;
}

// ── Crée l'inventaire mensuel d'un site (+ ses lignes détail) et le
//    rattache à une session. Lève une Exception si impossible (déjà
//    existant, ou aucune bobine active sur le site).
function inv_creer_mensuel(int $site_id, string $date, ?int $session_id, int $user_id): array {
    $exist = db_fetch_one(
        "SELECT id FROM inventaires_bobines WHERE site_id=? AND date_inventaire=? AND type_inventaire='mensuel' AND statut!='annule'",
        [$site_id, $date]
    );
    if ($exist) throw new Exception("Un inventaire mensuel existe déjà pour ce site à cette date.");

    $bobines = db_fetch_all(
        "SELECT b.id, b.numero, b.type_code, b.serie, b.stock_systeme
         FROM op_bobines b
         WHERE b.site_id=? AND b.statut NOT IN ('retiree','epuisee')
         ORDER BY b.serie, b.type_code, b.numero",
        [$site_id]
    );
    if (empty($bobines)) throw new Exception('Aucune bobine active sur ce site.');

    $total_systeme = array_sum(array_column($bobines, 'stock_systeme'));
    try {
        $films_emuci = (int)db_fetch_value(
            "SELECT COALESCE(SUM(plaques_posees),0) FROM points_emuci WHERE site_id=? AND date_point=?",
            [$site_id, $date]
        );
    } catch (Exception $e) { $films_emuci = 0; }
    $films_digi = (int)db_fetch_value(
        "SELECT COALESCE(SUM(total_plaques),0) FROM op_points_journaliers WHERE site_id=? AND date_point=?",
        [$site_id, $date]
    );

    db_query(
        "INSERT INTO inventaires_bobines (site_id,date_inventaire,type_inventaire,statut,nb_bobines,total_films_systeme,total_films_emuci,ecart_digistock_emuci,cree_par,session_id)
         VALUES (?,?,'mensuel','brouillon',?,?,?,?,?,?)",
        [$site_id, $date, count($bobines), $total_systeme, $films_emuci, ($films_emuci - $films_digi), $user_id, $session_id]
    );
    $inv_id = (int)db_last_id();

    foreach ($bobines as $b) {
        $conso_moy = (float)db_fetch_value(
            "SELECT COALESCE(SUM(quantite)/GREATEST(((NOW())::date - (MIN(date_conso)::date)),1),0) FROM consommations_bobines WHERE bobine_id=? AND date_conso>=(CURRENT_DATE - INTERVAL '30 DAY')",
            [$b['id']]
        );
        $ecart_connu = (int)db_fetch_value("SELECT COALESCE(SUM(ecart),0) FROM ecarts_bobines WHERE bobine_id=? AND statut='ouvert'", [$b['id']]);
        db_query(
            "INSERT INTO inventaire_details_bobines (inventaire_id,bobine_id,stock_systeme,qte_temps_reel,stock_physique,ecart,ecart_connu_avant,conso_quotidienne_moy)
             VALUES (?,?,?,?,0,0,?,?)",
            [$inv_id, $b['id'], (int)$b['stock_systeme'], (int)$b['stock_systeme'], $ecart_connu, round($conso_moy, 2)]
        );
    }

    return ['id' => $inv_id, 'nb' => count($bobines)];
}
