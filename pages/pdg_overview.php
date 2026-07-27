<?php
// ============================================================
//  pages/pdg_overview.php — Vue exécutive PDG
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

require_auth();
$user = current_user();

$page_title  = 'Vue PDG';
$active_page = 'pdg_overview';

$mois  = trim($_GET['mois'] ?? date('Y-m'));
$annee = substr($mois, 0, 4);
$vue   = trim($_GET['vue'] ?? 'mois');  // 'mois' ou 'annee'
$mc = ['01'=>'Jan','02'=>'Fév','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Juin',
       '07'=>'Juil','08'=>'Aoû','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Déc'];
$ml = ['01'=>'Janvier','02'=>'Février','03'=>'Mars','04'=>'Avril','05'=>'Mai','06'=>'Juin',
       '07'=>'Juillet','08'=>'Août','09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Décembre'];

// Filtre DATE selon la vue
if ($vue === 'annee') {
    $date_filter  = "TO_CHAR(date_point,'YYYY')=?";
    $date_param   = $annee;
    $mois_display = "Année $annee";
    $prev_param   = (string)((int)$annee - 1);
    $prev_display = "Année $prev_param";
    $prev_short   = $prev_param;
    $cur_short    = $annee;
    $cmd_filter   = "TO_CHAR(created_at,'YYYY')=?";
} else {
    $date_filter  = "TO_CHAR(date_point,'YYYY-MM')=?";
    $date_param   = $mois;
    $mois_display = ($ml[substr($mois,5,2)] ?? '') . ' ' . $annee;
    $prev_param   = date('Y-m', strtotime($mois . '-01 -1 month'));
    $prev_display = ($ml[substr($prev_param,5,2)] ?? '') . ' ' . substr($prev_param,0,4);
    $prev_short   = ($mc[substr($prev_param,5,2)] ?? '') . ' ' . substr($prev_param,2,2);
    $cur_short    = ($mc[substr($mois,5,2)] ?? '') . ' ' . substr($mois,2,2);
    $cmd_filter   = "TO_CHAR(created_at,'YYYY-MM')=?";
}
// même filtre, table préfixée (pour les jointures)
$date_filter_p = str_replace('date_point', 'p.date_point', $date_filter);

// ── OPÉRATIONS
$ops = db_fetch_one(
    "SELECT COUNT(*) AS total_points,
            SUM(CASE WHEN statut='valide' THEN 1 ELSE 0 END) AS points_valides,
            SUM(CASE WHEN statut='en_attente_validation' THEN 1 ELSE 0 END) AS points_attente,
            SUM(CASE WHEN statut='rejete' THEN 1 ELSE 0 END) AS points_rejetes,
            COALESCE(SUM(total_engins),0) AS total_engins,
            COALESCE(SUM(total_plaques),0) AS total_plaques,
            COALESCE(SUM(rivets_utilises),0) AS rivets_utilises,
            COALESCE(ROUND(AVG(NULLIF(moyenne_prod,0)),1),0) AS moy_prod
     FROM op_points_journaliers
     WHERE $date_filter AND statut != 'brouillon'",
    [$date_param]
);

// ── PÉRIODE PRÉCÉDENTE (comparaison)
$prev = db_fetch_one(
    "SELECT COUNT(*) AS total_points,
            COALESCE(SUM(total_engins),0) AS total_engins,
            COALESCE(SUM(total_plaques),0) AS total_plaques,
            COALESCE(SUM(rivets_utilises),0) AS rivets_utilises,
            COALESCE(ROUND(AVG(NULLIF(moyenne_prod,0)),1),0) AS moy_prod
     FROM op_points_journaliers
     WHERE $date_filter AND statut != 'brouillon'",
    [$prev_param]
);

/** Variation % entre période courante et précédente (null si pas de base). */
function pdg_delta($cur, $prev) {
    $cur = (float)$cur; $prev = (float)$prev;
    if ($prev <= 0) return null;
    return round(($cur - $prev) / $prev * 100, 1);
}
$d_engins  = pdg_delta($ops['total_engins']  ?? 0, $prev['total_engins']  ?? 0);
$d_plaques = pdg_delta($ops['total_plaques'] ?? 0, $prev['total_plaques'] ?? 0);
$d_prod    = pdg_delta($ops['moy_prod']      ?? 0, $prev['moy_prod']      ?? 0);
$d_rivets  = pdg_delta($ops['rivets_utilises'] ?? 0, $prev['rivets_utilises'] ?? 0);

// ── ACTIVITÉ : jours actifs, meilleur jour
$jours_actifs = (int)db_fetch_value(
    "SELECT COUNT(DISTINCT date_point) FROM op_points_journaliers
     WHERE $date_filter AND statut != 'brouillon'",
    [$date_param]
);
$best_day = db_fetch_one(
    "SELECT date_point, COALESCE(SUM(total_engins),0) AS engins
     FROM op_points_journaliers
     WHERE $date_filter AND statut != 'brouillon'
     GROUP BY date_point ORDER BY engins DESC LIMIT 1",
    [$date_param]
);

$prod_par_site = db_fetch_all(
    "SELECT s.nom, s.type,
            COUNT(p.id) AS nb_points,
            COALESCE(SUM(p.total_engins),0) AS engins,
            COALESCE(SUM(p.total_plaques),0) AS plaques,
            COALESCE(ROUND(AVG(NULLIF(p.moyenne_prod,0)),1),0) AS moy_vh,
            SUM(CASE WHEN p.statut='en_attente_validation' THEN 1 ELSE 0 END) AS en_attente
     FROM sites s
     LEFT JOIN op_points_journaliers p ON p.site_id=s.id
                AND $date_filter_p AND p.statut != 'brouillon'
     WHERE s.actif=1
     GROUP BY s.id, s.nom, s.type ORDER BY engins DESC",
    [$date_param]
);
$top_site   = $prod_par_site[0] ?? null;
$sites_actifs = count(array_filter($prod_par_site, fn($s) => (int)$s['nb_points'] > 0));

// ── BOBINES
$bobines_stats = db_fetch_one(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN statut='en_cours' THEN 1 ELSE 0 END) AS en_cours,
            SUM(CASE WHEN statut='en_stock' THEN 1 ELSE 0 END) AS en_stock,
            SUM(CASE WHEN statut='epuisee' THEN 1 ELSE 0 END) AS epuisees,
            COALESCE(SUM(films_restants),0) AS films_restants
     FROM op_bobines"
);

$films_mois = (int)db_fetch_value(
    "SELECT COALESCE(SUM(fu.films_utilises),0)
     FROM op_films_utilises fu
     JOIN op_points_journaliers p ON p.id=fu.point_id
     WHERE $date_filter_p",
    [$date_param]
);

$films_detail_raw = db_fetch_all(
    "SELECT s.nom AS site, b.type_code, COALESCE(SUM(fu.films_utilises),0) AS films
     FROM sites s
     JOIN op_points_journaliers p ON p.site_id=s.id AND $date_filter_p
     JOIN op_films_utilises fu ON fu.point_id=p.id
     JOIN op_bobines b ON b.id=fu.bobine_id
     WHERE s.actif=1
     GROUP BY s.id, s.nom, b.type_code ORDER BY s.nom, b.type_code",
    [$date_param]
);
$films_par_type = [];
$films_by_type  = [];
foreach ($films_detail_raw as $d) {
    $films_par_type[$d['type_code']] = ($films_par_type[$d['type_code']] ?? 0) + (int)$d['films'];
    $films_by_type[$d['type_code']][] = ['site'=>$d['site'],'films'=>(int)$d['films']];
}
arsort($films_par_type);
$js_films_detail = json_encode(array_values(array_map(fn($t)=>$films_by_type[$t]??[], array_keys($films_par_type))));

// Autonomie films : jours de stock au rythme de consommation observé
$films_restants = (int)($bobines_stats['films_restants'] ?? 0);
$conso_jour     = $jours_actifs > 0 ? $films_mois / $jours_actifs : 0;
$autonomie      = $conso_jour > 0 ? (int)floor($films_restants / $conso_jour) : null;

// ── COMMANDES
$cmd_stats = db_fetch_one(
    "SELECT SUM(CASE WHEN statut='en_attente' THEN 1 ELSE 0 END) AS en_attente,
            SUM(CASE WHEN statut='en_attente_livraison' THEN 1 ELSE 0 END) AS a_livrer,
            SUM(CASE WHEN statut='en_cours_livraison' THEN 1 ELSE 0 END) AS en_route,
            SUM(CASE WHEN statut='livre' AND $cmd_filter THEN 1 ELSE 0 END) AS livrees_mois
     FROM commandes",
    [$date_param]
);

// ── RIVETS
$rivets = db_fetch_all(
    "SELECT s.nom, sr.type_rivet, COALESCE(sr.quantite,0) AS quantite
     FROM sites s JOIN op_stock_rivets sr ON sr.site_id=s.id
     WHERE s.actif=1
     ORDER BY s.nom, array_position(ARRAY['gonflable','eclate']::text[], (sr.type_rivet)::text)"
);
$rps = [];
foreach ($rivets as $r) $rps[$r['nom']][$r['type_rivet']] = (int)$r['quantite'];

// ── PMMA
$pmma_par_type = db_fetch_all(
    "SELECT sp.type_pmma,
            COALESCE(SUM(sp.quantite),0) AS total,
            COUNT(CASE WHEN sp.quantite < sp.seuil_alerte THEN 1 END) AS nb_bas
     FROM stock_pmma_site sp
     JOIN sites s ON s.id=sp.site_id
     WHERE s.actif=1
     GROUP BY sp.type_pmma ORDER BY total DESC"
);
$pmma_detail = db_fetch_all(
    "SELECT s.nom, sp.type_pmma, COALESCE(sp.quantite,0) AS quantite, COALESCE(sp.seuil_alerte,10) AS seuil
     FROM sites s
     JOIN stock_pmma_site sp ON sp.site_id=s.id
     WHERE s.actif=1 ORDER BY sp.type_pmma, s.nom"
);

// ── ALERTES
$alertes_stock  = (int)db_fetch_value("SELECT COUNT(*) FROM articles WHERE stock_global <= seuil_alerte AND seuil_alerte > 0");
$rivets_bas     = (int)db_fetch_value("SELECT COUNT(*) FROM op_stock_rivets WHERE quantite < 200");
$points_attente = (int)($ops['points_attente'] ?? 0);
$cmd_en_attente = (int)($cmd_stats['en_attente'] ?? 0);
$total_alertes  = $points_attente + $cmd_en_attente + $alertes_stock + $rivets_bas;

// ── ÉVOLUTION 6 MOIS
$evol = db_fetch_all(
    "SELECT TO_CHAR(date_point,'YYYY-MM') AS mois,
            SUM(total_engins) AS engins,
            SUM(total_plaques) AS plaques
     FROM op_points_journaliers
     WHERE date_point >= (CURRENT_DATE - INTERVAL '6 MONTH') AND statut != 'brouillon'
     GROUP BY TO_CHAR(date_point,'YYYY-MM') ORDER BY mois ASC"
);
$evol_films = db_fetch_all(
    "SELECT TO_CHAR(p.date_point,'YYYY-MM') AS mois, COALESCE(SUM(fu.films_utilises),0) AS films
     FROM op_points_journaliers p
     JOIN op_films_utilises fu ON fu.point_id=p.id
     WHERE p.date_point >= (CURRENT_DATE - INTERVAL '6 MONTH') AND p.statut != 'brouillon'
     GROUP BY TO_CHAR(p.date_point,'YYYY-MM') ORDER BY mois ASC"
);

// ── POINTS EN ATTENTE
$pts_attente = db_fetch_all(
    "SELECT p.date_point, p.type_point, s.nom AS site, CONCAT(u.prenom,' ',u.nom) AS coord
     FROM op_points_journaliers p
     JOIN sites s ON s.id=p.site_id
     LEFT JOIN users u ON u.id=p.created_by
     WHERE p.statut='en_attente_validation'
     ORDER BY p.date_point DESC LIMIT 10"
);

// ── INDICATEURS DÉRIVÉS
$total_points   = (int)($ops['total_points'] ?? 0);
$points_valides = (int)($ops['points_valides'] ?? 0);
$taux_valid     = $total_points > 0 ? round($points_valides / $total_points * 100) : 0;
$eng            = (int)($ops['total_engins'] ?? 0);
$plq            = (int)($ops['total_plaques'] ?? 0);
$riv            = (int)($ops['rivets_utilises'] ?? 0);
$ratio_plaques  = $eng > 0 ? round($plq / $eng, 2) : 0;
$ratio_rivets   = $eng > 0 ? round($riv / $eng, 1) : 0;
$moy_jour       = $jours_actifs > 0 ? round($eng / $jours_actifs) : 0;
$best_engins    = (int)($best_day['engins'] ?? 0);
// Jauge « couverture films » : part du stock restant sur 60 jours d'autonomie cible
$cible_autonomie = 60;
$taux_films      = $autonomie === null ? 0 : min(100, (int)round($autonomie / $cible_autonomie * 100));
// Jauge « productivité » : moyenne V/H sur une cible de 15 véhicules/heure
$cible_vh   = 15;
$moy_vh_cur = (float)($ops['moy_prod'] ?? 0);
$taux_prod  = $cible_vh > 0 ? min(100, (int)round($moy_vh_cur / $cible_vh * 100)) : 0;

// ══════════════════════════════════════════════════════════
//  KPI BUSINESS — service, gâche matière, écart de consommation,
//  couverture de stock, fiabilité, productivité, mix produit
// ══════════════════════════════════════════════════════════

// ── Agrégats du point journalier (mix, non-posés, rivets, heures)
$biz = db_fetch_one(
    "SELECT COALESCE(SUM(total_plaques),0)              AS posees,
            COALESCE(SUM(non_poses_concessionnaires),0) AS np_conc,
            COALESCE(SUM(non_poses_usagers),0)          AS np_usag,
            COALESCE(SUM(nb_vp),0)                      AS nb_vp,
            COALESCE(SUM(nb_camion),0)                  AS nb_camion,
            COALESCE(SUM(nb_semi),0)                    AS nb_semi,
            COALESCE(SUM(nb_moto),0)                    AS nb_moto,
            COALESCE(SUM(rivets_utilises),0)            AS riv_ok,
            COALESCE(SUM(rivets_endommages),0)          AS riv_ko,
            COALESCE(SUM(nb_heures_travail),0)          AS heures
     FROM op_points_journaliers
     WHERE $date_filter AND statut <> 'brouillon'",
    [$date_param]
);

// 1 ── Taux de service : part de la demande réellement servie
$posees   = (int)($biz['posees'] ?? 0);
$np_conc  = (int)($biz['np_conc'] ?? 0);
$np_usag  = (int)($biz['np_usag'] ?? 0);
$np_total = $np_conc + $np_usag;
$demande  = $posees + $np_total;
$taux_service = $demande > 0 ? round($posees / $demande * 100, 1) : null;

// 2 ── Rendement matière : part de film et de rivets détruits
$gache = db_fetch_one(
    "SELECT COALESCE(SUM(fu.films_utilises),0)   AS ok,
            COALESCE(SUM(fu.films_endommages),0) AS ko
     FROM op_films_utilises fu
     JOIN op_points_journaliers p ON p.id = fu.point_id
     WHERE $date_filter_p AND p.statut <> 'brouillon'",
    [$date_param]
);
$f_ok = (int)($gache['ok'] ?? 0);
$f_ko = (int)($gache['ko'] ?? 0);
$taux_gache     = ($f_ok + $f_ko) > 0 ? round($f_ko / ($f_ok + $f_ko) * 100, 2) : null;
$riv_ok         = (int)($biz['riv_ok'] ?? 0);
$riv_ko         = (int)($biz['riv_ko'] ?? 0);
$taux_gache_riv = ($riv_ok + $riv_ko) > 0 ? round($riv_ko / ($riv_ok + $riv_ko) * 100, 2) : null;

// 3 ── Écart consommation théorique vs réelle
//     Théorique = Σ (engins du type × plaques/rivets par engin), par série A/B/C/D.
$nb_vp = (int)($biz['nb_vp'] ?? 0); $nb_cam = (int)($biz['nb_camion'] ?? 0);
$nb_smi = (int)($biz['nb_semi'] ?? 0); $nb_mot = (int)($biz['nb_moto'] ?? 0);
$bareme_plq = ['A'=>2,'B'=>2,'C'=>2,'D'=>1];   // valeurs de repli si la table est vide
$bareme_riv = ['A'=>4,'B'=>4,'C'=>4,'D'=>2];
foreach (db_fetch_all(
    "SELECT serie_bobine,
            ROUND(AVG(nb_plaques))::int AS nb_plaques,
            ROUND(AVG(nb_rivets))::int  AS nb_rivets
     FROM op_types_vehicule GROUP BY serie_bobine") as $t) {
    $s = strtoupper(trim((string)$t['serie_bobine']));
    if (isset($bareme_plq[$s])) {
        $bareme_plq[$s] = (int)$t['nb_plaques'];
        $bareme_riv[$s] = (int)$t['nb_rivets'];
    }
}
$theo_plq = $nb_vp*$bareme_plq['A'] + $nb_cam*$bareme_plq['B'] + $nb_smi*$bareme_plq['C'] + $nb_mot*$bareme_plq['D'];
$theo_riv = $nb_vp*$bareme_riv['A'] + $nb_cam*$bareme_riv['B'] + $nb_smi*$bareme_riv['C'] + $nb_mot*$bareme_riv['D'];
$ecart_plq     = $theo_plq > 0 ? $posees - $theo_plq : null;
$ecart_plq_pct = $theo_plq > 0 ? round(($posees - $theo_plq) / $theo_plq * 100, 1) : null;
$ecart_riv     = $theo_riv > 0 ? $riv_ok - $theo_riv : null;
$ecart_riv_pct = $theo_riv > 0 ? round(($riv_ok - $theo_riv) / $theo_riv * 100, 1) : null;

// 4 ── Couverture de stock, en jours, site par site
$couv_raw = db_fetch_all(
    "SELECT s.id, s.nom,
            COALESCE(st.restants,0) AS restants,
            COALESCE(c.films,0)     AS films,
            COALESCE(c.jours,0)     AS jours
     FROM sites s
     LEFT JOIN (
        SELECT p.site_id,
               SUM(fu.films_utilises)       AS films,
               COUNT(DISTINCT p.date_point) AS jours
        FROM op_points_journaliers p
        JOIN op_films_utilises fu ON fu.point_id = p.id
        WHERE $date_filter_p AND p.statut <> 'brouillon'
        GROUP BY p.site_id
     ) c ON c.site_id = s.id
     LEFT JOIN (
        SELECT site_id, SUM(films_restants) AS restants
        FROM op_bobines WHERE statut IN ('en_stock','en_cours') GROUP BY site_id
     ) st ON st.site_id = s.id
     WHERE s.actif = 1
     ORDER BY s.nom",
    [$date_param]
);
$couverture = [];
foreach ($couv_raw as $r) {
    $j  = (int)$r['jours'];
    $cj = $j > 0 ? (float)$r['films'] / $j : 0.0;          // consommation moyenne / jour
    $couverture[] = [
        'nom'       => $r['nom'],
        'restants'  => (int)$r['restants'],
        'conso_j'   => $cj,
        // null = pas de consommation observée, donc pas de couverture calculable
        'jours'     => $cj > 0 ? (int)floor((int)$r['restants'] / $cj) : null,
    ];
}
// Tri : les sites les plus tendus d'abord, les non calculables en fin de liste
usort($couverture, fn($a,$b) => [$a['jours']===null?1:0, $a['jours']??0] <=> [$b['jours']===null?1:0, $b['jours']??0]);
$seuil_couv_bas = 15;   // jours : en deçà, réapprovisionnement à déclencher
$couv_critiques = count(array_filter($couverture, fn($c) => $c['jours'] !== null && $c['jours'] < $seuil_couv_bas));

// 5 ── Fiabilité du stock : écarts d'inventaire + réconciliation EMUCI
$fiab = db_fetch_one(
    "SELECT (SELECT COUNT(*)                      FROM ecarts_bobines WHERE statut = 'ouvert') AS ec_ouverts,
            (SELECT COALESCE(SUM(ABS(ecart)),0)   FROM ecarts_bobines WHERE statut = 'ouvert') AS ec_films,
            (SELECT COUNT(*)                      FROM op_bobines     WHERE statut = 'perdue') AS bob_perdues,
            (SELECT COALESCE(SUM(films_restants),0) FROM op_bobines   WHERE statut = 'perdue') AS bob_perdues_films"
);
$recon = db_fetch_one(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN statut_ecart = 'majeur' THEN 1 ELSE 0 END),0)              AS majeurs,
            COALESCE(SUM(CASE WHEN statut_ecart = 'majeur' AND ajuste = 0 THEN 1 ELSE 0 END),0) AS majeurs_ouverts,
            COALESCE(SUM(ABS(ecart)),0) AS ecart_films
     FROM comparaisons_stock
     WHERE " . str_replace('date_point', 'date_comparaison', $date_filter),
    [$date_param]
);
$rec_total    = (int)($recon['total'] ?? 0);
$rec_majeurs  = (int)($recon['majeurs'] ?? 0);
$rec_ouverts  = (int)($recon['majeurs_ouverts'] ?? 0);
$taux_recon   = $rec_total > 0 ? round(($rec_total - $rec_majeurs) / $rec_total * 100, 1) : null;
$ec_ouverts   = (int)($fiab['ec_ouverts'] ?? 0);
$bob_perdues  = (int)($fiab['bob_perdues'] ?? 0);

// 6 ── Productivité réelle : plaques posées par heure travaillée
$heures      = (float)($biz['heures'] ?? 0);
$prod_reelle = $heures > 0 ? round($posees / $heures, 1) : null;

// 7 ── Mix produit
$mix_total = $nb_vp + $nb_cam + $nb_smi + $nb_mot;
// 'k' = série A/B/C/D ; les couleurs vivent en CSS pour suivre le thème clair/sombre.
$mix = [
    ['lbl'=>'Véhicules particuliers','court'=>'VP',    'n'=>$nb_vp, 'k'=>'a'],
    ['lbl'=>'Camions',               'court'=>'Camion','n'=>$nb_cam,'k'=>'b'],
    ['lbl'=>'Semi-remorques',        'court'=>'Semi',  'n'=>$nb_smi,'k'=>'c'],
    ['lbl'=>'Motos',                 'court'=>'Moto',  'n'=>$nb_mot,'k'=>'d'],
];
foreach ($mix as &$m) { $m['pct'] = $mix_total > 0 ? round($m['n'] / $mix_total * 100, 1) : 0; }
unset($m);

// ── JS DATA
$js_evol_labels  = json_encode(array_map(fn($r) => ($mc[substr($r['mois'],5,2)]??'').' '.substr($r['mois'],2,2), $evol));
$js_evol_engins  = json_encode(array_map(fn($r) => (int)$r['engins'], $evol));
$js_evol_plaques = json_encode(array_map(fn($r) => (int)$r['plaques'], $evol));
$js_evol_films   = json_encode(array_map(fn($r) => (int)$r['films'], $evol_films));
$js_statuts      = json_encode([(int)($ops['points_valides']??0),(int)($ops['points_attente']??0),(int)($ops['points_rejetes']??0)]);
$js_films_labels = json_encode(array_keys($films_par_type));
$js_films_values = json_encode(array_values($films_par_type));
$js_bobines      = json_encode([(int)($bobines_stats['en_cours']??0),(int)($bobines_stats['en_stock']??0),(int)($bobines_stats['epuisees']??0)]);
$js_cmds         = json_encode([(int)($cmd_stats['en_attente']??0),(int)(($cmd_stats['a_livrer']??0)+($cmd_stats['en_route']??0)),(int)($cmd_stats['livrees_mois']??0)]);
$max_engins      = empty($prod_par_site) ? 1 : max(1, ...array_column($prod_par_site, 'engins'));
// Barres de comparaison période N-1 / N
$js_compare = json_encode([
    'prev' => ['label'=>$prev_short, 'engins'=>(int)($prev['total_engins']??0), 'plaques'=>(int)($prev['total_plaques']??0)],
    'cur'  => ['label'=>$cur_short,  'engins'=>$eng,                            'plaques'=>$plq],
]);
// Jauges (flèches ← → pour naviguer)
$js_gauges = json_encode([
    ['key'=>'valid','titre'=>'Taux de validation','valeur'=>$points_valides.'/'.$total_points,
     'pct'=>$taux_valid,'sous'=>'Points validés sur '.$total_points.' saisis','unite'=>'%'],
    ['key'=>'prod','titre'=>'Productivité terrain','valeur'=>number_format($moy_vh_cur,1,',',' ').' V/H',
     'pct'=>$taux_prod,'sous'=>'Cible : '.$cible_vh.' véhicules / heure','unite'=>'%'],
    ['key'=>'films','titre'=>'Autonomie films','valeur'=>($autonomie===null?'—':$autonomie.' j'),
     'pct'=>$taux_films,'sous'=>'Cible : '.$cible_autonomie.' jours de stock','unite'=>'%'],
]);
// PMMA + Rivets charts
$pmma_by_type = [];
foreach ($pmma_detail as $d) $pmma_by_type[$d['type_pmma']][] = ['site'=>$d['nom'],'qty'=>(int)$d['quantite'],'seuil'=>(int)$d['seuil']];
$js_pmma_labels = json_encode(array_map(fn($r)=>$r['type_pmma'], $pmma_par_type));
$js_pmma_totals = json_encode(array_map(fn($r)=>(int)$r['total'], $pmma_par_type));
$js_pmma_bas    = json_encode(array_map(fn($r)=>(int)$r['nb_bas'], $pmma_par_type));
$js_pmma_detail = json_encode(array_values(array_map(fn($r)=>$pmma_by_type[$r['type_pmma']]??[], $pmma_par_type)));
$riv_type_detail = [[], []];
$riv_total_gonfl = 0; $riv_total_eclat = 0;
foreach ($rps as $site => $types) {
    $g = (int)($types['gonflable'] ?? 0);
    $e = (int)($types['eclate'] ?? 0);
    $riv_total_gonfl += $g; $riv_total_eclat += $e;
    $riv_type_detail[0][] = ['site' => $site, 'qty' => $g];
    $riv_type_detail[1][] = ['site' => $site, 'qty' => $e];
}
$js_riv_labels  = json_encode(['Gonflable', 'Éclaté']);
$js_riv_totals  = json_encode([$riv_total_gonfl, $riv_total_eclat]);
$js_riv_detail  = json_encode($riv_type_detail);
$pmma_ch_h      = max(160, count($pmma_par_type) * 46);
$riv_ch_h       = 120;
$films_ch_h     = max(160, count($films_par_type) * 46);

/** Rendu d'un badge de variation ↗ / ↘. */
function pdg_trend($d) {
    if ($d === null) return '<span class="px-trend px-flat">nouveau</span>';
    if ($d >= 0)     return '<span class="px-trend px-up">↗ +'.number_format($d,1,',',' ').'%</span>';
    return '<span class="px-trend px-down">↘ '.number_format($d,1,',',' ').'%</span>';
}

include __DIR__ . '/../templates/header.php';
?>
<style>
/* ══════════════════════════════════════════════════════════
   VUE PDG — design system local (préfixe .px- / #pdgx)
   ══════════════════════════════════════════════════════════ */
#pdgx{
  --px-bg:#DDE5DC;          /* fond sauge */
  --px-ink:#191A17;         /* encre quasi-noire */
  --px-ink-soft:#5D6459;    /* texte secondaire */
  --px-card:#FFFFFF;
  --px-card-2:#F1F2EE;      /* carte sourde */
  --px-mint:#A9F5A1;        /* accent vert */
  --px-mint-d:#7ADE70;
  --px-lilac:#BFA8FB;       /* accent lavande */
  --px-line:rgba(25,26,23,.10);
  --px-r-xl:30px;
  --px-r-lg:22px;
  --px-r-md:16px;
  --px-r-sm:12px;

  /* pleine largeur : annule le padding de .page-content */
  margin:-28px;
  padding:28px;
  background:var(--px-bg);
  min-height:calc(100vh - var(--topbar-h,64px));
  font-family:'Manrope',sans-serif;
  color:var(--px-ink);
}
/* .page-content garde un padding de 28px à toutes les tailles : on conserve la
   marge négative telle quelle pour rester pleine largeur, et on réduit seulement
   l'espacement intérieur sur petit écran. */
@media(max-width:640px){ #pdgx{padding:18px} }

[data-theme="dark"] #pdgx{
  --px-bg:#12160F;
  --px-ink:#EDEFE9;
  --px-ink-soft:#9BA396;
  --px-card:#1C211A;
  --px-card-2:#232920;
  --px-line:rgba(237,239,233,.12);
}

#pdgx *{box-sizing:border-box}
#pdgx .px-num{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;letter-spacing:-.03em;font-variant-numeric:tabular-nums}

/* ── EN-TÊTE ─────────────────────────────────────────── */
.px-top{display:flex;justify-content:space-between;align-items:flex-end;gap:18px;flex-wrap:wrap;margin-bottom:22px}
.px-h1{font-family:'Plus Jakarta Sans',sans-serif;font-size:clamp(26px,3.4vw,38px);font-weight:800;letter-spacing:-.03em;line-height:1.05}
.px-h1-sub{font-size:13px;color:var(--px-ink-soft);margin-top:6px}
.px-tools{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.px-select{appearance:none;background:var(--px-card);border:1px solid var(--px-line);color:var(--px-ink);
  padding:10px 34px 10px 16px;border-radius:99px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;outline:none;
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'><path d='M1 1l5 5 5-5' fill='none' stroke='%23191A17' stroke-width='1.8' stroke-linecap='round'/></svg>");
  background-repeat:no-repeat;background-position:right 14px center}
[data-theme="dark"] #pdgx .px-select{background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'><path d='M1 1l5 5 5-5' fill='none' stroke='%23EDEFE9' stroke-width='1.8' stroke-linecap='round'/></svg>")}
.px-select:focus-visible,.px-ico-btn:focus-visible,.px-tab:focus-visible{outline:2px solid var(--px-ink);outline-offset:2px}
.px-month{background:var(--px-card);border:1px solid var(--px-line);color:var(--px-ink);padding:10px 16px;border-radius:99px;
  font-family:inherit;font-size:13px;font-weight:600;outline:none;cursor:pointer}

/* ── GRILLES ─────────────────────────────────────────── */
.px-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.08fr);gap:18px;align-items:start}
@media(max-width:1080px){.px-grid{grid-template-columns:minmax(0,1fr)}}
.px-col{display:flex;flex-direction:column;gap:18px;min-width:0}

/* ── CARTES ──────────────────────────────────────────── */
.px-card{background:var(--px-card);border-radius:var(--px-r-xl);padding:26px;min-width:0}
.px-card-dark{background:#191A17;color:#F4F5F1;border-radius:var(--px-r-xl);padding:26px;min-width:0}
[data-theme="dark"] #pdgx .px-card-dark{background:#000200}
.px-card-soft{background:var(--px-card-2);border-radius:var(--px-r-xl);padding:26px;min-width:0}
.px-card-hdr{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px}
.px-card-ttl{font-family:'Plus Jakarta Sans',sans-serif;font-size:19px;font-weight:800;letter-spacing:-.02em;line-height:1.2}
.px-card-sub{font-size:12px;color:var(--px-ink-soft);margin-top:4px}
.px-card-dark .px-card-sub{color:rgba(244,245,241,.55)}

.px-ico{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.px-ico-ink{background:#191A17;color:var(--px-mint)}
.px-ico-mint{background:var(--px-mint);color:#191A17}
.px-ico-lilac{background:var(--px-lilac);color:#191A17}
.px-ico-btn{width:38px;height:38px;border-radius:50%;border:1px solid var(--px-line);background:transparent;color:var(--px-ink);
  display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:16px;transition:background .15s,transform .15s}
.px-ico-btn:hover{background:var(--px-ink);color:var(--px-card)}
.px-ico-btn:active{transform:scale(.94)}

/* ── BADGES / TENDANCES ──────────────────────────────── */
.px-trend{display:inline-flex;align-items:center;gap:3px;padding:4px 10px;border-radius:99px;font-size:11.5px;font-weight:700;white-space:nowrap}
.px-up{background:var(--px-mint);color:#14320F}
.px-down{background:#FFD9D2;color:#7A2114}
.px-flat{background:rgba(25,26,23,.07);color:var(--px-ink-soft)}
[data-theme="dark"] #pdgx .px-flat{background:rgba(237,239,233,.10)}
.px-chip{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:99px;font-size:12px;font-weight:700;
  background:var(--px-card);border:1px solid var(--px-line);color:var(--px-ink)}
.px-chip-alert{background:#FFE9E3;border-color:transparent;color:#7A2114}
[data-theme="dark"] #pdgx .px-chip-alert{background:#3A1710;color:#FFC4B5}
.px-chip-ok{background:var(--px-mint);border-color:transparent;color:#14320F}

/* ── CARTE PRODUCTION (sombre) ───────────────────────── */
.px-prod-body{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,190px);gap:18px;align-items:end}
@media(max-width:520px){.px-prod-body{grid-template-columns:minmax(0,1fr)}}
.px-prod-lbl{font-size:12px;color:rgba(244,245,241,.55);display:flex;align-items:center;gap:8px}
.px-prod-val{font-size:clamp(38px,5.6vw,58px);line-height:1;margin-top:10px}
.px-legend{display:flex;gap:14px;flex-wrap:wrap;margin-top:16px}
.px-leg{display:flex;align-items:center;gap:6px;font-size:11.5px;color:rgba(244,245,241,.62)}
.px-leg-d{width:10px;height:10px;border-radius:3px;flex-shrink:0}
.px-prod-foot{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:22px;padding-top:20px;border-top:1px solid rgba(244,245,241,.12)}
.px-pf-v{font-size:21px;line-height:1}
.px-pf-l{font-size:10.5px;color:rgba(244,245,241,.5);margin-top:5px;text-transform:uppercase;letter-spacing:.06em;font-weight:700}

/* ── JAUGE ───────────────────────────────────────────── */
.px-gauge-panel{background:var(--px-mint);border-radius:var(--px-r-lg);padding:22px;margin-top:16px;position:relative}
.px-gauge-top{display:flex;align-items:center;justify-content:space-between;gap:12px}
.px-gauge-badge{width:42px;height:42px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;color:#191A17}
.px-gauge-wrap{position:relative;margin:6px auto 0;max-width:300px}
.px-gauge-foot{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;margin-top:-6px}
.px-gauge-pct{font-size:26px;line-height:1;color:#14320F}
.px-gauge-note{font-size:11px;color:#3B5236;margin-top:5px}
.px-gauge-val{font-size:clamp(24px,3.4vw,34px);line-height:1;color:#14320F;text-align:right}
.px-dots{display:flex;gap:6px;justify-content:center;margin-top:14px}
.px-dot{width:7px;height:7px;border-radius:50%;background:var(--px-line);border:none;padding:0;cursor:pointer;transition:width .2s,background .2s}
.px-dot.on{width:22px;border-radius:99px;background:var(--px-ink)}

/* ── ALERTES (carte pilule) ──────────────────────────── */
.px-alert-row{display:flex;align-items:center;gap:12px;background:var(--px-card-2);border-radius:var(--px-r-md);padding:13px 16px;margin-top:9px}
.px-alert-ico{width:32px;height:32px;border-radius:9px;background:var(--px-card);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.px-alert-txt{font-size:13px;font-weight:600;flex:1;min-width:0}
.px-alert-n{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:16px;font-variant-numeric:tabular-nums}
.px-count{min-width:46px;height:46px;padding:0 12px;border-radius:99px;background:#191A17;color:var(--px-mint);
  display:flex;align-items:center;justify-content:center;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:17px;flex-shrink:0}

/* ── TIMELINE + MINI-CARTES ──────────────────────────── */
.px-split{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px}
@media(max-width:760px){.px-split{grid-template-columns:minmax(0,1fr)}}
.px-tl{list-style:none;margin:16px 0 0;padding:0;position:relative}
.px-tl:before{content:'';position:absolute;left:6px;top:8px;bottom:8px;width:2px;background:var(--px-line)}
.px-tl li{position:relative;padding:0 0 20px 26px}
.px-tl li:last-child{padding-bottom:0}
.px-tl li:after{content:'';position:absolute;left:0;top:4px;width:14px;height:14px;border-radius:50%;
  background:var(--px-card);border:2px solid var(--px-line)}
.px-tl li.on:after{background:var(--px-ink);border-color:var(--px-ink)}
.px-tl-m{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:14.5px;letter-spacing:-.01em}
.px-tl-d{font-size:11.5px;color:var(--px-ink-soft);margin-top:3px;line-height:1.5}
.px-mini{border-radius:var(--px-r-lg);padding:20px;display:flex;flex-direction:column;min-height:0}
.px-mini-mint{background:var(--px-mint);color:#14320F}
.px-mini-lilac{background:var(--px-lilac);color:#241645}
.px-mini-hdr{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}
.px-mini-l{font-size:12px;font-weight:700;opacity:.75}
.px-mini-v{font-size:clamp(24px,3vw,31px);line-height:1;margin-top:8px}
.px-mini-arrow{width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.5);display:flex;align-items:center;
  justify-content:center;font-size:13px;flex-shrink:0}
.px-bar{height:22px;border-radius:99px;background:rgba(255,255,255,.55);overflow:hidden;position:relative;margin-top:14px}
.px-bar-f{height:100%;border-radius:99px;background:#191A17}
.px-bar-dot{position:absolute;top:50%;width:13px;height:13px;border-radius:50%;background:var(--px-lilac);
  transform:translate(-50%,-50%);border:2px solid #191A17}

/* ── SECTION DÉTAILS ─────────────────────────────────── */
.px-sec{margin-top:18px}
.px-sec-hdr{display:flex;align-items:center;gap:12px;margin:30px 4px 14px}
.px-sec-ttl{font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;font-weight:800;letter-spacing:-.01em}
.px-sec-line{flex:1;height:1px;background:var(--px-line)}
.px-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}
.px-kpi{background:var(--px-card);border-radius:var(--px-r-lg);padding:18px 20px}
.px-kpi-v{font-size:27px;line-height:1}
.px-kpi-l{font-size:10.5px;color:var(--px-ink-soft);text-transform:uppercase;letter-spacing:.06em;font-weight:700;margin-top:7px}
.px-kpi-s{font-size:11.5px;color:var(--px-ink-soft);margin-top:5px}

/* ── TABLE SITES ─────────────────────────────────────── */
.px-tbl-wrap{overflow-x:auto;border-radius:var(--px-r-lg)}
#pdgx table.px-tbl{width:100%;border-collapse:collapse;font-size:13px;min-width:620px}
#pdgx .px-tbl th{background:transparent!important;font-size:10px;font-weight:800;color:var(--px-ink-soft)!important;
  text-transform:uppercase;letter-spacing:.07em;padding:0 14px 12px;border-bottom:1px solid var(--px-line);text-align:left;white-space:nowrap;font-family:'Manrope',sans-serif}
#pdgx .px-tbl td{padding:13px 14px;border-bottom:1px solid var(--px-line);color:var(--px-ink);vertical-align:middle}
#pdgx .px-tbl tr:last-child td{border-bottom:none}
#pdgx .px-tbl tr:hover td{background:var(--px-card-2)}
.px-track{background:var(--px-card-2);border-radius:99px;height:8px;overflow:hidden;min-width:56px;flex:1}
.px-track-f{height:100%;border-radius:99px;background:#191A17}
[data-theme="dark"] #pdgx .px-track-f{background:var(--px-mint)}
.px-tag{display:inline-block;padding:3px 11px;border-radius:99px;font-size:11px;font-weight:700}
.px-tag-ok{background:var(--px-mint);color:#14320F}
.px-tag-warn{background:#FFE9E3;color:#7A2114}
.px-tag-none{background:var(--px-card-2);color:var(--px-ink-soft)}

/* ── GRAPHES ─────────────────────────────────────────── */
.px-chart{width:100%;position:relative}
.px-chart canvas{display:block;width:100%!important}
.px-donut-row{display:flex;align-items:center;gap:22px;flex-wrap:wrap}
.px-lg{display:flex;flex-direction:column;gap:9px}
.px-lg-i{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--px-ink-soft)}
.px-lg-d{width:10px;height:10px;border-radius:3px;flex-shrink:0}
.px-hint{font-size:11px;color:var(--px-ink-soft);font-weight:400}

/* ── MODALE ──────────────────────────────────────────── */
.px-modal{display:none;position:fixed;inset:0;z-index:2000;background:rgba(25,26,23,.55);
  align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(3px)}
.px-modal-box{background:var(--px-card);border-radius:var(--px-r-xl);width:100%;max-width:700px;max-height:88vh;
  display:flex;flex-direction:column;overflow:hidden;color:var(--px-ink)}
.px-modal-hdr{display:flex;align-items:center;gap:14px;padding:22px 26px;border-bottom:1px solid var(--px-line);flex-shrink:0}

#pdg-tip{display:none;position:fixed;z-index:3000;background:#191A17;color:#F4F5F1;border-radius:14px;padding:13px 16px;
  box-shadow:0 12px 34px rgba(0,0,0,.28);pointer-events:none;min-width:180px;font-size:12px;line-height:1.7;font-family:'Manrope',sans-serif}

/* ══════════════════════════════════════════════════════════
   BANDEAU BUSINESS — service, gâche, écarts, couverture
   ══════════════════════════════════════════════════════════ */
.px-biz{display:flex;flex-direction:column;gap:18px;margin-bottom:26px}

/* ── Ligne 1 : service (hero) + 3 métriques ─────────────── */
.px-biz-lead{display:grid;grid-template-columns:minmax(0,1.32fr) minmax(0,1fr);gap:18px;align-items:stretch}
@media(max-width:980px){.px-biz-lead{grid-template-columns:minmax(0,1fr)}}

.px-hero{background:#191A17;color:#F4F5F1;border-radius:var(--px-r-xl);padding:28px;display:flex;flex-direction:column;min-width:0}
[data-theme="dark"] #pdgx .px-hero{background:#000200}
.px-hero-lbl{font-size:12px;color:rgba(244,245,241,.62);display:flex;align-items:center;gap:8px}
.px-hero-val{font-size:clamp(46px,7vw,74px);line-height:.94;margin:12px 0 2px;display:flex;align-items:baseline;gap:10px;flex-wrap:wrap}
.px-hero-unit{font-size:clamp(20px,2.4vw,26px);color:rgba(244,245,241,.45);font-weight:800}
.px-hero-note{font-size:12.5px;color:rgba(244,245,241,.62);line-height:1.6;max-width:46ch}
.px-hero-note b{color:#F4F5F1;font-weight:700}

/* Composition de la demande : servi / non servi */
.px-demand{margin-top:auto;padding-top:22px}
.px-demand-bar{display:flex;height:34px;border-radius:12px;overflow:hidden;background:rgba(244,245,241,.12)}
.px-demand-seg{display:flex;align-items:center;padding:0 12px;font-size:12px;font-weight:800;white-space:nowrap;
  font-family:'Plus Jakarta Sans',sans-serif;font-variant-numeric:tabular-nums;min-width:0;overflow:hidden}
.px-demand-served{background:var(--px-mint);color:#14320F}
.px-demand-lost{background:#FFC4B5;color:#5C1A0F}
.px-demand-key{display:flex;gap:18px;flex-wrap:wrap;margin-top:12px}
.px-demand-k{display:flex;align-items:center;gap:7px;font-size:11.5px;color:rgba(244,245,241,.62)}
.px-demand-k b{color:#F4F5F1;font-weight:700;font-variant-numeric:tabular-nums}

/* Métriques compactes à droite du hero */
.px-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;min-width:0}
@media(max-width:520px){.px-metrics{grid-template-columns:minmax(0,1fr)}}
.px-metric{background:var(--px-card);border-radius:var(--px-r-lg);padding:19px 20px;display:flex;flex-direction:column;min-width:0}
.px-metric-soft{background:var(--px-card-2)}
/* Hauteur fixe : que le libellé tienne sur une ou deux lignes, les quatre
   valeurs restent alignées sur la même ligne de base. */
.px-metric-hd{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;min-height:34px}
.px-metric-l{font-size:11px;font-weight:700;color:var(--px-ink-soft);text-transform:uppercase;letter-spacing:.06em;line-height:1.4}
.px-metric-v{font-size:clamp(25px,3vw,31px);line-height:1;margin-top:11px;display:flex;align-items:baseline;gap:5px}
.px-metric-u{font-size:14px;color:var(--px-ink-soft);font-weight:800}
.px-metric-s{font-size:11.5px;color:var(--px-ink-soft);margin-top:7px;line-height:1.5}
.px-metric-na{color:var(--px-ink-soft);font-size:22px;font-weight:800}

/* ── Ligne 2 : mix produit ──────────────────────────────── */
/* Une teinte par série A/B/C/D. La série A est la dominante : elle prend
   l'encre en clair, une sauge moyenne en sombre où le quasi-noir
   se confondrait avec le fond de carte. */
#pdgx{
  --px-mix-a:#191A17;--px-mix-a-ink:#F4F5F1;
  --px-mix-b:#A9F5A1;--px-mix-b-ink:#14320F;
  --px-mix-c:#BFA8FB;--px-mix-c-ink:#241645;
  --px-mix-d:#FFC4B5;--px-mix-d-ink:#5C1A0F;
}
[data-theme="dark"] #pdgx{--px-mix-a:#8B9784;--px-mix-a-ink:#0B0F09}
.px-mix-bar{display:flex;height:44px;border-radius:14px;overflow:hidden;background:var(--px-card-2);margin-top:4px}
.px-mix-seg{display:flex;align-items:center;justify-content:center;font-family:'Plus Jakarta Sans',sans-serif;
  font-size:12.5px;font-weight:800;font-variant-numeric:tabular-nums;min-width:0;overflow:hidden;white-space:nowrap;padding:0 8px}
.px-mix-a{background:var(--px-mix-a);color:var(--px-mix-a-ink)}
.px-mix-b{background:var(--px-mix-b);color:var(--px-mix-b-ink)}
.px-mix-c{background:var(--px-mix-c);color:var(--px-mix-c-ink)}
.px-mix-d{background:var(--px-mix-d);color:var(--px-mix-d-ink)}
/* Sous 700px les segments deviennent trop étroits pour porter un pourcentage
   lisible : la légende juste dessous donne déjà chaque valeur. */
@media(max-width:700px){.px-mix-pct,.px-demand-lbl{display:none}}
.px-dot-a{background:var(--px-mix-a)}
.px-dot-b{background:var(--px-mix-b)}
.px-dot-c{background:var(--px-mix-c)}
.px-dot-d{background:var(--px-mix-d)}
.px-mix-key{display:grid;grid-template-columns:repeat(auto-fit,minmax(132px,1fr));gap:12px;margin-top:16px}
.px-mix-k{display:flex;flex-direction:column;gap:3px;min-width:0}
.px-mix-k-top{display:flex;align-items:center;gap:7px;font-size:11.5px;color:var(--px-ink-soft);font-weight:600}
.px-mix-k-v{font-size:19px;line-height:1.1}
.px-mix-k-p{font-size:11.5px;color:var(--px-ink-soft);font-variant-numeric:tabular-nums}

/* ── Ligne 3 : couverture + fiabilité ───────────────────── */
.px-biz-split{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:18px;align-items:start}
@media(max-width:980px){.px-biz-split{grid-template-columns:minmax(0,1fr)}}

.px-cov{display:flex;flex-direction:column;gap:2px;margin-top:6px}
.px-cov-r{display:grid;grid-template-columns:minmax(96px,1.5fr) minmax(0,2fr) auto;gap:14px;align-items:center;
  padding:11px 0;border-bottom:1px solid var(--px-line)}
.px-cov-r:last-child{border-bottom:none}
.px-cov-n{font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.px-cov-sub{font-size:11px;color:var(--px-ink-soft);margin-top:2px;font-variant-numeric:tabular-nums}
.px-cov-track{background:var(--px-card-2);border-radius:99px;height:9px;overflow:hidden;position:relative}
.px-cov-fill{height:100%;border-radius:99px;background:var(--px-ink);transition:width .4s cubic-bezier(.22,1,.36,1)}
[data-theme="dark"] #pdgx .px-cov-fill{background:var(--px-mint)}
.px-cov-fill.low{background:#E4674A}
[data-theme="dark"] #pdgx .px-cov-fill.low{background:#FF8E70}
.px-cov-mark{position:absolute;top:-3px;bottom:-3px;width:2px;background:var(--px-ink-soft);opacity:.5}
.px-cov-d{display:flex;align-items:center;gap:8px;justify-self:end;white-space:nowrap}
.px-cov-num{font-size:16px;line-height:1;font-variant-numeric:tabular-nums}
.px-cov-unit{font-size:11px;color:var(--px-ink-soft)}

.px-risk{display:flex;flex-direction:column;gap:9px;margin-top:6px}
.px-risk-r{display:flex;align-items:center;gap:13px;background:var(--px-card-2);border-radius:var(--px-r-md);padding:13px 15px}
.px-risk-ico{width:34px;height:34px;border-radius:10px;background:var(--px-card);display:flex;align-items:center;
  justify-content:center;font-size:16px;flex-shrink:0}
.px-risk-b{flex:1;min-width:0}
.px-risk-t{font-size:13px;font-weight:600;line-height:1.35}
.px-risk-s{font-size:11px;color:var(--px-ink-soft);margin-top:3px;line-height:1.45}
.px-risk-n{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;font-size:19px;font-variant-numeric:tabular-nums;flex-shrink:0}
/* Fond mint fixe dans les deux thèmes : l'encre y est forcée en sombre. */
.px-risk-clear{display:flex;align-items:center;gap:11px;background:var(--px-mint);color:#14320F;border-radius:var(--px-r-md);padding:15px}
.px-risk-clear b{font-weight:800}
.px-risk-clear .px-risk-ico{background:rgba(255,255,255,.55);color:#14320F}
.px-risk-clear .px-risk-t{color:#14320F}
.px-risk-clear .px-risk-s{color:#2F4A2A}

@media(prefers-reduced-motion:reduce){#pdgx *{transition:none!important;animation:none!important}}
</style>

<div id="pdgx">

  <!-- ═══════════════════ EN-TÊTE ═══════════════════ -->
  <div class="px-top">
    <div>
      <h1 class="px-h1">Vue exécutive</h1>
      <div class="px-h1-sub">Express Multiservices CI — DigiStock · <?= h($mois_display) ?></div>
    </div>
    <form method="get" class="px-tools">
      <?php if($total_alertes > 0): ?>
        <span class="px-chip px-chip-alert"><i class="ph-duotone ph-warning-circle"></i> <?= $total_alertes ?> alerte<?= $total_alertes>1?'s':'' ?></span>
      <?php else: ?>
        <span class="px-chip px-chip-ok"><i class="ph-duotone ph-check-circle"></i> Aucune alerte</span>
      <?php endif; ?>
      <select name="vue" class="px-select" onchange="this.form.submit()" aria-label="Type de vue">
        <option value="mois"  <?= $vue==='mois' ?'selected':'' ?>>Mensuel</option>
        <option value="annee" <?= $vue==='annee'?'selected':'' ?>>Annuel</option>
      </select>
      <?php if($vue==='mois'): ?>
        <input type="month" name="mois" value="<?= h($mois) ?>" class="px-month" onchange="this.form.submit()" aria-label="Mois">
      <?php else: ?>
        <select name="annee" class="px-select" aria-label="Année"
                onchange="document.querySelector('input[name=mois]').value=this.value+'-01';this.form.submit()">
          <?php for ($y = 2020; $y <= (int)date('Y'); $y++): ?>
            <option value="<?= $y ?>" <?= (int)$annee===$y?'selected':'' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
        <input type="hidden" name="mois" value="<?= h($mois) ?>">
      <?php endif; ?>
    </form>
  </div>

  <!-- ═══════════════════ PERFORMANCE BUSINESS ═══════════════════ -->
  <div class="px-biz">

    <!-- ── Taux de service + métriques de rendement ── -->
    <div class="px-biz-lead">

      <section class="px-hero">
        <div class="px-hero-lbl">
          <i class="ph-duotone ph-target"></i> Taux de service · demande servie
        </div>

        <?php if ($taux_service === null): ?>
          <div class="px-num px-hero-val" style="font-size:clamp(30px,4vw,42px)">—</div>
          <div class="px-hero-note">Aucun point journalier saisi sur la période. Le taux de service se calcule dès la première saisie.</div>
        <?php else: ?>
          <div class="px-num px-hero-val">
            <?= number_format($taux_service, 1, ',', ' ') ?><span class="px-hero-unit">%</span>
          </div>
          <div class="px-hero-note">
            <b><?= fmt_number($posees) ?></b> plaques posées sur <b><?= fmt_number($demande) ?></b> demandées.
            <?php if ($np_total > 0): ?>
              <b><?= fmt_number($np_total) ?></b> non posées, dont <?= fmt_number($np_conc) ?> côté concessionnaires
              et <?= fmt_number($np_usag) ?> côté usagers.
            <?php else: ?>
              Aucune plaque restée non posée sur la période.
            <?php endif; ?>
          </div>

          <div class="px-demand">
            <?php
              $pc_serv = $demande > 0 ? $posees / $demande * 100 : 0;
              $pc_lost = 100 - $pc_serv;
            ?>
            <div class="px-demand-bar" role="img"
                 aria-label="Répartition de la demande : <?= fmt_number($posees) ?> plaques posées, <?= fmt_number($np_total) ?> non posées">
              <?php if ($pc_serv > 0): ?>
                <div class="px-demand-seg px-demand-served" style="width:<?= round($pc_serv,2) ?>%">
                  <?php if ($pc_serv >= 26): ?><span class="px-demand-lbl"><?= fmt_number($posees) ?> posées</span><?php endif; ?>
                </div>
              <?php endif; ?>
              <?php if ($pc_lost > 0): ?>
                <div class="px-demand-seg px-demand-lost" style="width:<?= round($pc_lost,2) ?>%">
                  <?php if ($pc_lost >= 26): ?><span class="px-demand-lbl"><?= fmt_number($np_total) ?> non posées</span><?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="px-demand-key">
              <span class="px-demand-k"><span class="px-leg-d px-dot-b"></span>Posées <b><?= fmt_number($posees) ?></b></span>
              <span class="px-demand-k"><span class="px-leg-d px-dot-d"></span>Non posées <b><?= fmt_number($np_total) ?></b></span>
              <span class="px-demand-k">Manque à gagner <b><?= $demande>0 ? number_format($pc_lost,1,',',' ') : '0' ?> %</b></span>
            </div>
          </div>
        <?php endif; ?>
      </section>

      <div class="px-metrics">

        <!-- Gâche film -->
        <div class="px-metric">
          <div class="px-metric-hd">
            <div class="px-metric-l">Gâche film</div>
            <?php if ($taux_gache !== null): ?>
              <span class="px-tag <?= $taux_gache > 3 ? 'px-tag-warn' : 'px-tag-ok' ?>">
                <?= $taux_gache > 3 ? 'à surveiller' : 'maîtrisée' ?>
              </span>
            <?php endif; ?>
          </div>
          <?php if ($taux_gache === null): ?>
            <div class="px-metric-na" style="margin-top:11px">—</div>
            <div class="px-metric-s">Aucun film consommé sur la période.</div>
          <?php else: ?>
            <div class="px-num px-metric-v"><?= number_format($taux_gache,2,',',' ') ?><span class="px-metric-u">%</span></div>
            <div class="px-metric-s"><?= fmt_number($f_ko) ?> films détruits sur <?= fmt_number($f_ok + $f_ko) ?> engagés</div>
          <?php endif; ?>
        </div>

        <!-- Gâche rivets -->
        <div class="px-metric">
          <div class="px-metric-hd">
            <div class="px-metric-l">Casse rivets</div>
            <?php if ($taux_gache_riv !== null): ?>
              <span class="px-tag <?= $taux_gache_riv > 5 ? 'px-tag-warn' : 'px-tag-ok' ?>">
                <?= $taux_gache_riv > 5 ? 'à surveiller' : 'maîtrisée' ?>
              </span>
            <?php endif; ?>
          </div>
          <?php if ($taux_gache_riv === null): ?>
            <div class="px-metric-na" style="margin-top:11px">—</div>
            <div class="px-metric-s">Aucun rivet consommé sur la période.</div>
          <?php else: ?>
            <div class="px-num px-metric-v"><?= number_format($taux_gache_riv,2,',',' ') ?><span class="px-metric-u">%</span></div>
            <div class="px-metric-s"><?= fmt_number($riv_ko) ?> rivets cassés sur <?= fmt_number($riv_ok + $riv_ko) ?> posés</div>
          <?php endif; ?>
        </div>

        <!-- Productivité réelle -->
        <div class="px-metric px-metric-soft">
          <div class="px-metric-hd"><div class="px-metric-l">Productivité réelle</div></div>
          <?php if ($prod_reelle === null): ?>
            <div class="px-metric-na" style="margin-top:11px">—</div>
            <div class="px-metric-s">Heures travaillées non renseignées.</div>
          <?php else: ?>
            <div class="px-num px-metric-v"><?= number_format($prod_reelle,1,',',' ') ?><span class="px-metric-u">pl./h</span></div>
            <div class="px-metric-s"><?= fmt_number($posees) ?> plaques sur <?= number_format($heures,0,',',' ') ?> h travaillées</div>
          <?php endif; ?>
        </div>

        <!-- Écart consommation théorique vs réelle -->
        <div class="px-metric px-metric-soft">
          <div class="px-metric-hd">
            <div class="px-metric-l">Écart conso.</div>
            <?php if ($ecart_plq_pct !== null && abs($ecart_plq_pct) > 5): ?>
              <span class="px-tag px-tag-warn">écart</span>
            <?php endif; ?>
          </div>
          <?php if ($ecart_plq_pct === null): ?>
            <div class="px-metric-na" style="margin-top:11px">—</div>
            <div class="px-metric-s">Aucun engin déclaré sur la période.</div>
          <?php else: ?>
            <div class="px-num px-metric-v">
              <?= $ecart_plq_pct > 0 ? '+' : '' ?><?= number_format($ecart_plq_pct,1,',',' ') ?><span class="px-metric-u">%</span>
            </div>
            <div class="px-metric-s">
              <?= fmt_number($posees) ?> posées vs <?= fmt_number($theo_plq) ?> attendues d'après le mix
            </div>
          <?php endif; ?>
        </div>

      </div>
    </div>

    <!-- ── Mix produit ── -->
    <?php if ($mix_total > 0): ?>
    <section class="px-card">
      <div class="px-card-hdr" style="margin-bottom:10px">
        <div>
          <div class="px-card-ttl">Mix produit</div>
          <div class="px-card-sub"><?= fmt_number($mix_total) ?> engins traités · pilote la demande en séries de bobines</div>
        </div>
      </div>
      <div class="px-mix-bar" role="img" aria-label="Répartition des engins par type : <?php
        echo h(implode(', ', array_map(fn($m) => $m['court'].' '.number_format($m['pct'],1,',',' ').' %',
                                       array_filter($mix, fn($m) => $m['n'] > 0)))); ?>">
        <?php foreach ($mix as $m): if ($m['n'] <= 0) continue; ?>
          <div class="px-mix-seg px-mix-<?= $m['k'] ?>" style="width:<?= round($m['pct'],2) ?>%">
            <?php if ($m['pct'] >= 11): ?><span class="px-mix-pct"><?= number_format($m['pct'],1,',',' ') ?> %</span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="px-mix-key">
        <?php foreach ($mix as $m): ?>
          <div class="px-mix-k">
            <div class="px-mix-k-top"><span class="px-leg-d px-dot-<?= $m['k'] ?>"></span><?= h($m['lbl']) ?></div>
            <div class="px-num px-mix-k-v"><?= fmt_number($m['n']) ?></div>
            <div class="px-mix-k-p"><?= number_format($m['pct'],1,',',' ') ?> % du volume</div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- ── Couverture de stock + fiabilité ── -->
    <div class="px-biz-split">

      <!-- Couverture par site -->
      <section class="px-card">
        <div class="px-card-hdr">
          <div>
            <div class="px-card-ttl">Couverture de stock</div>
            <div class="px-card-sub">Jours de film restants au rythme observé · cible <?= $cible_autonomie ?> j</div>
          </div>
          <?php if ($couv_critiques > 0): ?>
            <span class="px-chip px-chip-alert"><i class="ph-duotone ph-warning-circle"></i> <?= $couv_critiques ?> site<?= $couv_critiques>1?'s':'' ?> sous <?= $seuil_couv_bas ?> j</span>
          <?php else: ?>
            <span class="px-chip px-chip-ok"><i class="ph-duotone ph-check-circle"></i> Rien sous <?= $seuil_couv_bas ?> j</span>
          <?php endif; ?>
        </div>

        <?php if (empty($couverture)): ?>
          <div class="px-metric-s">Aucun site actif à afficher.</div>
        <?php else: ?>
          <div class="px-cov">
            <?php foreach ($couverture as $c):
              $j    = $c['jours'];
              $pct  = $j === null ? 0 : min(100, $j / max(1,$cible_autonomie) * 100);
              $low  = $j !== null && $j < $seuil_couv_bas;
            ?>
              <div class="px-cov-r">
                <div style="min-width:0">
                  <div class="px-cov-n"><?= h($c['nom']) ?></div>
                  <div class="px-cov-sub">
                    <?= fmt_number($c['restants']) ?> films
                    <?= $c['conso_j'] > 0 ? '· '.number_format($c['conso_j'],1,',',' ').' /j' : '' ?>
                  </div>
                </div>
                <div class="px-cov-track">
                  <div class="px-cov-fill<?= $low ? ' low' : '' ?>" style="width:<?= round($pct,1) ?>%"></div>
                  <div class="px-cov-mark" style="left:<?= round($seuil_couv_bas / max(1,$cible_autonomie) * 100, 1) ?>%"></div>
                </div>
                <div class="px-cov-d">
                  <?php if ($j === null): ?>
                    <span class="px-tag px-tag-none">pas de conso.</span>
                  <?php else: ?>
                    <span class="px-num px-cov-num"><?= $j ?></span><span class="px-cov-unit">j</span>
                    <?php if ($low): ?><span class="px-tag px-tag-warn">réappro</span><?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <!-- Fiabilité du stock -->
      <section class="px-card">
        <div class="px-card-hdr">
          <div>
            <div class="px-card-ttl">Fiabilité du stock</div>
            <div class="px-card-sub">Écarts d'inventaire et réconciliation EMUCI</div>
          </div>
        </div>

        <?php
          $has_risk = $ec_ouverts > 0 || $bob_perdues > 0 || $rec_ouverts > 0;
        ?>
        <div class="px-risk">

          <?php if ($ec_ouverts > 0): ?>
            <div class="px-risk-r">
              <div class="px-risk-ico"><i class="ph-duotone ph-scales"></i></div>
              <div class="px-risk-b">
                <div class="px-risk-t">Écarts d'inventaire non résolus</div>
                <div class="px-risk-s"><?= fmt_number((int)($fiab['ec_films'] ?? 0)) ?> films d'écart cumulé, en attente d'arbitrage</div>
              </div>
              <div class="px-risk-n"><?= $ec_ouverts ?></div>
            </div>
          <?php endif; ?>

          <?php if ($bob_perdues > 0): ?>
            <div class="px-risk-r">
              <div class="px-risk-ico"><i class="ph-duotone ph-warning-octagon"></i></div>
              <div class="px-risk-b">
                <div class="px-risk-t">Bobines déclarées perdues</div>
                <div class="px-risk-s"><?= fmt_number((int)($fiab['bob_perdues_films'] ?? 0)) ?> films non consommés, sortis du stock</div>
              </div>
              <div class="px-risk-n"><?= $bob_perdues ?></div>
            </div>
          <?php endif; ?>

          <?php if ($rec_ouverts > 0): ?>
            <div class="px-risk-r">
              <div class="px-risk-ico"><i class="ph-duotone ph-arrows-left-right"></i></div>
              <div class="px-risk-b">
                <div class="px-risk-t">Écarts EMUCI majeurs non ajustés</div>
                <div class="px-risk-s">Plus de 5 films d'écart entre EMUCI et DigiStock, sans ajustement</div>
              </div>
              <div class="px-risk-n"><?= $rec_ouverts ?></div>
            </div>
          <?php endif; ?>

          <?php if (!$has_risk): ?>
            <div class="px-risk-clear">
              <div class="px-risk-ico"><i class="ph-duotone ph-check-circle"></i></div>
              <div class="px-risk-b">
                <div class="px-risk-t">Aucun écart ouvert</div>
                <div class="px-risk-s">Inventaires et réconciliation EMUCI à jour.</div>
              </div>
            </div>
          <?php endif; ?>

          <!-- Taux de concordance EMUCI -->
          <div class="px-risk-r" style="background:transparent;padding:14px 0 0;border-top:1px solid var(--px-line);border-radius:0">
            <div class="px-risk-b">
              <div class="px-risk-t">Concordance EMUCI ↔ DigiStock</div>
              <div class="px-risk-s">
                <?php if ($taux_recon === null): ?>
                  Aucune comparaison enregistrée sur la période.
                <?php else: ?>
                  <?= fmt_number($rec_total - $rec_majeurs) ?> comparaisons alignées sur <?= fmt_number($rec_total) ?>
                <?php endif; ?>
              </div>
            </div>
            <div class="px-risk-n">
              <?= $taux_recon === null ? '—' : number_format($taux_recon,1,',',' ').' %' ?>
            </div>
          </div>

        </div>
      </section>

    </div>
  </div>

  <!-- ═══════════════════ GRILLE PRINCIPALE ═══════════════════ -->
  <div class="px-grid">

    <!-- ─────────── COLONNE GAUCHE ─────────── -->
    <div class="px-col">

      <!-- Production (carte sombre) -->
      <section class="px-card-dark">
        <div class="px-card-hdr">
          <div>
            <div class="px-card-ttl">Production</div>
            <div class="px-card-sub">Volume posé · vs <?= h($prev_display) ?></div>
          </div>
          <?= pdg_trend($d_engins) ?>
        </div>

        <div class="px-prod-body">
          <div>
            <div class="px-prod-lbl">Engins posés <span class="px-ico" style="width:22px;height:22px;font-size:11px;background:var(--px-mint);color:#191A17">↗</span></div>
            <div class="px-num px-prod-val"><?= fmt_number($eng) ?></div>
            <div class="px-legend">
              <span class="px-leg"><span class="px-leg-d" style="background:#A9F5A1"></span>Engins</span>
              <span class="px-leg"><span class="px-leg-d" style="background:#BFA8FB"></span>Plaques</span>
            </div>
          </div>
          <div class="px-chart"><canvas id="cCompare" height="188" aria-label="Comparaison des volumes posés"></canvas></div>
        </div>

        <div class="px-prod-foot">
          <div>
            <div class="px-num px-pf-v"><?= fmt_number($plq) ?></div>
            <div class="px-pf-l">Plaques posées</div>
          </div>
          <div>
            <div class="px-num px-pf-v"><?= number_format($moy_vh_cur,1,',',' ') ?></div>
            <div class="px-pf-l">Moy. véhic./h</div>
          </div>
          <div>
            <div class="px-num px-pf-v"><?= fmt_number($moy_jour) ?></div>
            <div class="px-pf-l">Engins / jour</div>
          </div>
        </div>
      </section>

      <!-- Jauges pilotables -->
      <section class="px-card">
        <div class="px-card-hdr" style="align-items:center">
          <div>
            <div class="px-card-ttl" id="gTitre">Taux de validation</div>
            <div class="px-card-sub" id="gSous">Points validés</div>
          </div>
          <div style="display:flex;gap:8px">
            <button class="px-ico-btn" type="button" onclick="gaugeStep(-1)" aria-label="Indicateur précédent">←</button>
            <button class="px-ico-btn" type="button" onclick="gaugeStep(1)"  aria-label="Indicateur suivant">→</button>
          </div>
        </div>

        <div class="px-gauge-panel">
          <div class="px-gauge-top">
            <div class="px-gauge-badge"><i class="ph-duotone ph-chart-line-up"></i></div>
            <span class="px-trend px-up" id="gCible">objectif</span>
          </div>
          <div class="px-gauge-wrap"><canvas id="cGauge" height="150" aria-label="Jauge d'indicateur"></canvas></div>
          <div class="px-gauge-foot">
            <div>
              <div class="px-num px-gauge-pct"><span id="gPct">0</span>%</div>
              <div class="px-gauge-note" id="gNote">—</div>
            </div>
            <div class="px-num px-gauge-val" id="gVal">—</div>
          </div>
        </div>
        <div class="px-dots" id="gDots"></div>
      </section>

    </div>

    <!-- ─────────── COLONNE DROITE ─────────── -->
    <div class="px-col">

      <!-- Alertes / actions requises -->
      <section class="px-card-soft" style="padding:20px">
        <div style="display:flex;align-items:center;gap:14px">
          <div class="px-ico px-ico-ink"><i class="ph-duotone ph-bell-ringing"></i></div>
          <div style="flex:1;min-width:0">
            <div class="px-card-ttl" style="font-size:17px">Actions requises</div>
            <div class="px-card-sub">Points de décision en attente</div>
          </div>
          <div class="px-num px-count"><?= $total_alertes ?></div>
        </div>

        <?php if($total_alertes === 0): ?>
          <div class="px-alert-row">
            <div class="px-alert-ico" style="color:#2F7A22"><i class="ph-duotone ph-check-circle"></i></div>
            <div class="px-alert-txt">Rien à traiter — tous les indicateurs sont au vert</div>
          </div>
        <?php else: ?>
          <?php if($points_attente > 0): ?>
          <div class="px-alert-row">
            <div class="px-alert-ico"><i class="ph-duotone ph-clock"></i></div>
            <div class="px-alert-txt">Points journaliers à valider</div>
            <div class="px-num px-alert-n"><?= $points_attente ?></div>
          </div>
          <?php endif; ?>
          <?php if($cmd_en_attente > 0): ?>
          <div class="px-alert-row">
            <div class="px-alert-ico"><i class="ph-duotone ph-shopping-cart"></i></div>
            <div class="px-alert-txt">Commandes en attente de traitement</div>
            <div class="px-num px-alert-n"><?= $cmd_en_attente ?></div>
          </div>
          <?php endif; ?>
          <?php if($alertes_stock > 0): ?>
          <div class="px-alert-row">
            <div class="px-alert-ico"><i class="ph-duotone ph-package"></i></div>
            <div class="px-alert-txt">Articles sous le seuil d'alerte</div>
            <div class="px-num px-alert-n"><?= $alertes_stock ?></div>
          </div>
          <?php endif; ?>
          <?php if($rivets_bas > 0): ?>
          <div class="px-alert-row">
            <div class="px-alert-ico"><i class="ph-duotone ph-nut"></i></div>
            <div class="px-alert-txt">Stocks rivets sous 200 unités</div>
            <div class="px-num px-alert-n"><?= $rivets_bas ?></div>
          </div>
          <?php endif; ?>
        <?php endif; ?>
      </section>

      <!-- Évolution + mini-cartes -->
      <section class="px-card">
        <div class="px-split">
          <div>
            <div style="display:flex;align-items:center;gap:12px">
              <div class="px-ico px-ico-mint"><i class="ph-duotone ph-trend-up"></i></div>
              <div class="px-card-ttl" style="font-size:17px;line-height:1.15">Évolution<br>6 mois</div>
            </div>
            <ul class="px-tl">
              <?php if(empty($evol)): ?>
                <li class="on"><div class="px-tl-m">Aucune donnée</div><div class="px-tl-d">Pas de point saisi sur la période</div></li>
              <?php else:
                $evol_tl = array_slice($evol, -5);
                $last_i  = count($evol_tl) - 1;
                foreach($evol_tl as $i => $e):
                  $lbl = ($ml[substr($e['mois'],5,2)] ?? '') . ' ' . substr($e['mois'],0,4);
              ?>
                <li class="<?= $i===$last_i ? 'on' : '' ?>">
                  <div class="px-tl-m"><?= h($lbl) ?></div>
                  <div class="px-tl-d"><?= fmt_number($e['engins']) ?> engins · <?= fmt_number($e['plaques']) ?> plaques</div>
                </li>
              <?php endforeach; endif; ?>
            </ul>
          </div>

          <div style="display:flex;flex-direction:column;gap:14px">
            <!-- Meilleure journée -->
            <div class="px-mini px-mini-mint">
              <div class="px-mini-hdr">
                <div class="px-mini-l">Meilleure journée</div>
                <div class="px-mini-arrow">↗</div>
              </div>
              <div class="px-num px-mini-v"><?= fmt_number($best_engins) ?></div>
              <div style="font-size:11.5px;opacity:.75;margin-top:5px">
                <?= $best_day ? h(fmt_date($best_day['date_point'])) : '—' ?> · engins posés
              </div>
              <div class="px-bar">
                <?php $bar_pct = $best_engins > 0 && $moy_jour > 0 ? min(100, round($moy_jour / $best_engins * 100)) : 0; ?>
                <div class="px-bar-f" style="width:<?= $bar_pct ?>%"></div>
                <div class="px-bar-dot" style="left:<?= $bar_pct ?>%"></div>
              </div>
              <div style="font-size:11px;opacity:.72;margin-top:7px">Moyenne quotidienne à <?= $bar_pct ?>% du pic</div>
            </div>

            <!-- Consommation films -->
            <div class="px-mini px-mini-lilac">
              <div class="px-mini-hdr">
                <div class="px-mini-l">Films consommés</div>
                <div class="px-mini-arrow">↗</div>
              </div>
              <div class="px-num px-mini-v"><?= fmt_number($films_mois) ?></div>
              <div style="font-size:11.5px;opacity:.75;margin-top:5px">
                <?= $autonomie === null ? 'Autonomie non calculable' : 'Autonomie ≈ '.$autonomie.' jours' ?>
              </div>
              <div class="px-chart" style="margin-top:10px"><canvas id="cSpark" height="66" aria-label="Consommation de films sur 6 mois"></canvas></div>
            </div>
          </div>
        </div>
      </section>

    </div>
  </div>

  <!-- ═══════════════════ INDICATEURS CLÉS ═══════════════════ -->
  <div class="px-sec-hdr">
    <div class="px-sec-ttl">Indicateurs de la période</div>
    <div class="px-sec-line"></div>
  </div>
  <div class="px-kpis">
    <div class="px-kpi">
      <div class="px-num px-kpi-v"><?= fmt_number($riv) ?></div>
      <div class="px-kpi-l">Rivets posés</div>
      <div class="px-kpi-s"><?= $ratio_rivets ?> par engin · <?= strip_tags(pdg_trend($d_rivets)) ?></div>
    </div>
    <div class="px-kpi">
      <div class="px-num px-kpi-v"><?= $ratio_plaques ?></div>
      <div class="px-kpi-l">Plaques / engin</div>
      <div class="px-kpi-s"><?= strip_tags(pdg_trend($d_plaques)) ?> sur les plaques</div>
    </div>
    <div class="px-kpi">
      <div class="px-num px-kpi-v"><?= $jours_actifs ?></div>
      <div class="px-kpi-l">Jours d'activité</div>
      <div class="px-kpi-s"><?= $total_points ?> point<?= $total_points>1?'s':'' ?> saisi<?= $total_points>1?'s':'' ?></div>
    </div>
    <div class="px-kpi">
      <div class="px-num px-kpi-v"><?= $sites_actifs ?>/<?= count($prod_par_site) ?></div>
      <div class="px-kpi-l">Sites actifs</div>
      <div class="px-kpi-s"><?= $top_site ? 'Top : '.h($top_site['nom']) : 'Aucun site actif' ?></div>
    </div>
    <div class="px-kpi">
      <div class="px-num px-kpi-v"><?= $bobines_stats['en_cours']??0 ?></div>
      <div class="px-kpi-l">Bobines actives</div>
      <div class="px-kpi-s"><?= $bobines_stats['en_stock']??0 ?> en stock · <?= fmt_number($films_restants) ?> films</div>
    </div>
    <div class="px-kpi">
      <div class="px-num px-kpi-v"><?= ($cmd_stats['a_livrer']??0)+($cmd_stats['en_route']??0) ?></div>
      <div class="px-kpi-l">Cmd. en livraison</div>
      <div class="px-kpi-s"><?= $cmd_stats['livrees_mois']??0 ?> livrée<?= ($cmd_stats['livrees_mois']??0)>1?'s':'' ?> sur la période</div>
    </div>
  </div>

  <!-- ═══════════════════ PERFORMANCE PAR SITE ═══════════════════ -->
  <div class="px-sec-hdr">
    <div class="px-sec-ttl">Performance par site</div>
    <div class="px-sec-line"></div>
  </div>
  <section class="px-card">
    <div class="px-tbl-wrap">
      <table class="px-tbl">
        <thead><tr>
          <th>Site</th>
          <th style="min-width:120px">Part du volume</th>
          <th style="text-align:right">Engins</th>
          <th style="text-align:right">Plaques</th>
          <th style="text-align:right">Moy V/H</th>
          <th style="text-align:right">Points</th>
          <th style="text-align:center">Statut</th>
        </tr></thead>
        <tbody>
        <?php if(empty($prod_par_site)): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--px-ink-soft);padding:28px">Aucun site actif</td></tr>
        <?php else: foreach($prod_par_site as $s):
          $pct = $max_engins > 0 ? round($s['engins'] / $max_engins * 100) : 0;
        ?>
        <tr>
          <td>
            <div style="font-weight:700"><?= h($s['nom']) ?></div>
            <div style="font-size:11px;color:var(--px-ink-soft)"><?= h($s['type']??'') ?></div>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="px-track"><div class="px-track-f" style="width:<?= $pct ?>%"></div></div>
              <span style="font-size:11px;color:var(--px-ink-soft);min-width:32px"><?= $pct ?>%</span>
            </div>
          </td>
          <td style="text-align:right" class="px-num"><?= fmt_number($s['engins']) ?></td>
          <td style="text-align:right;font-weight:700"><?= fmt_number($s['plaques']) ?></td>
          <td style="text-align:right;font-weight:700"><?= $s['moy_vh'] ?></td>
          <td style="text-align:right;color:var(--px-ink-soft)"><?= $s['nb_points'] ?></td>
          <td style="text-align:center">
            <?php if($s['en_attente']>0): ?>
              <span class="px-tag px-tag-warn"><?= $s['en_attente'] ?> en attente</span>
            <?php elseif($s['nb_points']>0): ?>
              <span class="px-tag px-tag-ok">À jour</span>
            <?php else: ?>
              <span class="px-tag px-tag-none">Inactif</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- ═══════════════════ STOCKS ═══════════════════ -->
  <div class="px-sec-hdr">
    <div class="px-sec-ttl">Stocks matériel</div>
    <div class="px-sec-line"></div>
    <button class="px-chip" type="button" onclick="openFilmsModal()" style="cursor:pointer">
      <i class="ph-duotone ph-film-strip"></i> Films par site
    </button>
  </div>
  <div class="px-split">
    <section class="px-card">
      <div class="px-card-hdr">
        <div>
          <div class="px-card-ttl" style="font-size:17px">PMMA par type</div>
          <div class="px-card-sub">Survoler pour le détail par site</div>
        </div>
        <div class="px-ico px-ico-mint"><i class="ph-duotone ph-printer"></i></div>
      </div>
      <div class="px-chart"><canvas id="cPmma" height="<?= $pmma_ch_h ?>"></canvas></div>
    </section>
    <section class="px-card">
      <div class="px-card-hdr">
        <div>
          <div class="px-card-ttl" style="font-size:17px">Rivets par type</div>
          <div class="px-card-sub">Seuil d'alerte : 200 unités</div>
        </div>
        <div class="px-ico px-ico-lilac"><i class="ph-duotone ph-nut"></i></div>
      </div>
      <div class="px-chart"><canvas id="cRivets" height="<?= $riv_ch_h ?>"></canvas></div>
    </section>
  </div>

  <!-- ═══════════════════ RÉPARTITIONS ═══════════════════ -->
  <div class="px-sec-hdr">
    <div class="px-sec-ttl">Répartitions</div>
    <div class="px-sec-line"></div>
  </div>
  <div class="px-split">
    <section class="px-card">
      <div class="px-card-ttl" style="font-size:17px;margin-bottom:18px">Statuts des points</div>
      <div class="px-donut-row">
        <canvas id="cStatuts" width="150" height="150" style="width:150px!important;flex-shrink:0"></canvas>
        <div class="px-lg">
          <div class="px-lg-i"><span class="px-lg-d" style="background:#A9F5A1"></span>Validés (<?= $points_valides ?>)</div>
          <div class="px-lg-i"><span class="px-lg-d" style="background:#BFA8FB"></span>En attente (<?= $points_attente ?>)</div>
          <div class="px-lg-i"><span class="px-lg-d" style="background:#F2A08D"></span>Rejetés (<?= $ops['points_rejetes']??0 ?>)</div>
        </div>
      </div>
    </section>
    <section class="px-card">
      <div class="px-card-ttl" style="font-size:17px;margin-bottom:18px">Bobines &amp; commandes</div>
      <div class="px-donut-row">
        <canvas id="cBobines" width="130" height="130" style="width:130px!important;flex-shrink:0"></canvas>
        <div class="px-lg">
          <div class="px-lg-i"><span class="px-lg-d" style="background:#BFA8FB"></span>En cours (<?= $bobines_stats['en_cours']??0 ?>)</div>
          <div class="px-lg-i"><span class="px-lg-d" style="background:#A9F5A1"></span>En stock (<?= $bobines_stats['en_stock']??0 ?>)</div>
          <div class="px-lg-i"><span class="px-lg-d" style="background:#C8CCC2"></span>Épuisées (<?= $bobines_stats['epuisees']??0 ?>)</div>
        </div>
        <canvas id="cCmds" width="130" height="130" style="width:130px!important;flex-shrink:0"></canvas>
        <div class="px-lg">
          <div class="px-lg-i"><span class="px-lg-d" style="background:#F2C88D"></span>Cmd. en attente (<?= $cmd_stats['en_attente']??0 ?>)</div>
          <div class="px-lg-i"><span class="px-lg-d" style="background:#BFA8FB"></span>En livraison (<?= ($cmd_stats['a_livrer']??0)+($cmd_stats['en_route']??0) ?>)</div>
          <div class="px-lg-i"><span class="px-lg-d" style="background:#A9F5A1"></span>Livrées (<?= $cmd_stats['livrees_mois']??0 ?>)</div>
        </div>
      </div>
    </section>
  </div>

  <!-- ═══════════════════ POINTS EN ATTENTE ═══════════════════ -->
  <?php if(!empty($pts_attente)): ?>
  <div class="px-sec-hdr">
    <div class="px-sec-ttl">Points en attente de validation</div>
    <div class="px-sec-line"></div>
  </div>
  <section class="px-card">
    <div class="px-tbl-wrap">
      <table class="px-tbl" style="min-width:520px">
        <thead><tr><th>Site</th><th>Date</th><th>Type</th><th>Coordinateur</th></tr></thead>
        <tbody>
        <?php foreach($pts_attente as $pt): ?>
        <tr>
          <td style="font-weight:700"><?= h($pt['site']) ?></td>
          <td><?= fmt_date($pt['date_point']) ?></td>
          <td><span class="px-tag px-tag-warn"><?= ['point_9h'=>'9h','point_13h'=>'13h','point_18h'=>'18h'][$pt['type_point']] ?? h($pt['type_point']) ?></span></td>
          <td style="color:var(--px-ink-soft)"><?= h($pt['coord']??'—') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php endif; ?>

  <!-- ═══════════════════ MODALE FILMS ═══════════════════ -->
  <div id="modal-films" class="px-modal" onclick="if(event.target===this)this.style.display='none'">
    <div class="px-modal-box">
      <div class="px-modal-hdr">
        <div class="px-ico px-ico-mint"><i class="ph-duotone ph-film-strip"></i></div>
        <div style="flex:1">
          <div class="px-card-ttl" style="font-size:17px">Films utilisés par site</div>
          <div class="px-card-sub"><?= h($mois_display) ?></div>
        </div>
        <button class="px-ico-btn" type="button" onclick="document.getElementById('modal-films').style.display='none'" aria-label="Fermer">&times;</button>
      </div>
      <div style="overflow:auto;padding:24px">
        <div class="px-chart"><canvas id="cFilms" height="<?= $films_ch_h ?>"></canvas></div>
      </div>
    </div>
  </div>

  <div id="pdg-tip" role="status"></div>
</div>

<script>
const evolLabels  = <?= $js_evol_labels ?>;
const evolEngins  = <?= $js_evol_engins ?>;
const evolFilms   = <?= $js_evol_films ?>;
const filmsLabels = <?= $js_films_labels ?>;
const filmsValues = <?= $js_films_values ?>;
const filmsDetail = <?= $js_films_detail ?>;
const statutsData = <?= $js_statuts ?>;
const bobinesData = <?= $js_bobines ?>;
const cmdsData    = <?= $js_cmds ?>;
const pmmaLabels  = <?= $js_pmma_labels ?>;
const pmmaTotals  = <?= $js_pmma_totals ?>;
const pmmaBas     = <?= $js_pmma_bas ?>;
const pmmaDetail  = <?= $js_pmma_detail ?>;
const rivLabels   = <?= $js_riv_labels ?>;
const rivTotals   = <?= $js_riv_totals ?>;
const rivDetail   = <?= $js_riv_detail ?>;
const compareData = <?= $js_compare ?>;
const gaugeData   = <?= $js_gauges ?>;

const INK = '#191A17', MINT = '#A9F5A1', LILAC = '#BFA8FB', CORAL = '#F2A08D', SAND = '#F2C88D', GREY = '#C8CCC2';
const DPR = window.devicePixelRatio || 1;

function initCv(id) {
    const el = document.getElementById(id);
    if (!el) return null;
    const w = el.parentElement.getBoundingClientRect().width || 400;
    const h = parseInt(el.getAttribute('height') || 200);
    el.width  = Math.round(w * DPR);
    el.height = Math.round(h * DPR);
    el.style.width  = Math.round(w) + 'px';
    el.style.height = h + 'px';
    const ctx = el.getContext('2d');
    ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    return {ctx, w: Math.round(w), h};
}

function rrect(ctx, x, y, w, h, r) {
    r = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y,     x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x,     y + h, r);
    ctx.arcTo(x,     y + h, x,     y,     r);
    ctx.arcTo(x,     y,     x + w, y,     r);
    ctx.closePath();
}

/** Motif hachuré (portions « période précédente » / « restant »). */
function hatch(ctx, stroke, bg) {
    const p = document.createElement('canvas');
    p.width = p.height = 8;
    const c = p.getContext('2d');
    if (bg) { c.fillStyle = bg; c.fillRect(0, 0, 8, 8); }
    c.strokeStyle = stroke; c.lineWidth = 2.2;
    c.beginPath(); c.moveTo(-2, 10); c.lineTo(10, -2); c.stroke();
    c.beginPath(); c.moveTo(2, 14);  c.lineTo(14, 2);  c.stroke();
    return ctx.createPattern(p, 'repeat');
}

function fmtN(n) { return Number(n).toLocaleString('fr-FR'); }

/* ── BARRES DE COMPARAISON (carte Production) ───────────── */
function drawCompare() {
    const c = initCv('cCompare'); if (!c) return;
    const {ctx, w, h} = c;
    const cols = [compareData.prev, compareData.cur];
    const totals = cols.map(d => d.engins + d.plaques);
    const max = Math.max(...totals, 1);
    const labelH = 22, padB = 4;
    const areaH = h - labelH - padB;
    const gap = 14;
    const bw = Math.min(88, (w - gap) / 2);
    const startX = (w - (bw * 2 + gap)) / 2;
    const hp = hatch(ctx, 'rgba(244,245,241,.30)', 'rgba(244,245,241,.07)');

    cols.forEach((d, i) => {
        const total = d.engins + d.plaques;
        const bh = Math.max(26, (total / max) * areaH);
        const x  = startX + i * (bw + gap);
        const y  = labelH + areaH - bh;

        ctx.fillStyle = 'rgba(244,245,241,.55)';
        ctx.font = '600 11px Manrope, sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'top';
        ctx.fillText(d.label || '', x + bw / 2, 2);

        if (i === 0) {                       // période précédente : hachurée
            rrect(ctx, x, y, bw, bh, 16);
            ctx.fillStyle = hp; ctx.fill();
            ctx.strokeStyle = 'rgba(244,245,241,.22)'; ctx.lineWidth = 1; ctx.stroke();
        } else {                             // période courante : mint + lilas
            const pH = total > 0 ? (d.plaques / total) * bh : 0;
            ctx.save();
            rrect(ctx, x, y, bw, bh, 16); ctx.clip();
            ctx.fillStyle = MINT;  ctx.fillRect(x, y, bw, bh - pH);
            ctx.fillStyle = LILAC; ctx.fillRect(x, y + bh - pH, bw, pH);
            ctx.restore();
        }
    });

    if (max <= 1) {
        ctx.fillStyle = 'rgba(244,245,241,.45)';
        ctx.font = '12px Manrope, sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText('Aucune donnée', w / 2, h / 2);
    }
}

/* ── JAUGE (arc semi-circulaire) ─────────────────────────── */
let gaugeIdx = 0;
function drawGauge(pct) {
    const c = initCv('cGauge'); if (!c) return;
    const {ctx, w, h} = c;
    const cx = w / 2, cy = h - 8;
    const R  = Math.min(w / 2 - 16, h - 26);
    const lw = Math.max(16, Math.min(26, R * 0.24));
    const frac = Math.max(0, Math.min(100, pct)) / 100;
    const a0 = Math.PI, a1 = Math.PI + frac * Math.PI;

    ctx.lineCap = 'round';
    ctx.lineWidth = lw;
    // reste : hachuré
    ctx.strokeStyle = hatch(ctx, 'rgba(20,50,15,.55)', 'rgba(255,255,255,.35)');
    ctx.beginPath(); ctx.arc(cx, cy, R, a0, Math.PI * 2); ctx.stroke();
    // atteint : encre
    if (frac > 0.005) {
        ctx.strokeStyle = INK;
        ctx.beginPath(); ctx.arc(cx, cy, R, a0, a1); ctx.stroke();
    }
    // curseur
    const mx = cx + Math.cos(a1) * R, my = cy + Math.sin(a1) * R;
    ctx.fillStyle = LILAC;
    ctx.beginPath(); ctx.arc(mx, my, lw * 0.32, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = INK; ctx.lineWidth = 2;
    ctx.beginPath(); ctx.arc(mx, my, lw * 0.32, 0, Math.PI * 2); ctx.stroke();
}

function renderGauge() {
    const g = gaugeData[gaugeIdx]; if (!g) return;
    document.getElementById('gTitre').textContent = g.titre;
    document.getElementById('gSous').textContent  = g.sous;
    document.getElementById('gPct').textContent   = g.pct;
    document.getElementById('gVal').textContent   = g.valeur;
    document.getElementById('gNote').textContent  = g.sous;
    document.getElementById('gCible').textContent = g.pct >= 80 ? 'objectif atteint' : (g.pct >= 50 ? 'en progression' : 'sous objectif');
    document.querySelectorAll('#gDots .px-dot').forEach((d, i) => d.classList.toggle('on', i === gaugeIdx));
    drawGauge(g.pct);
}
function gaugeStep(dir) {
    gaugeIdx = (gaugeIdx + dir + gaugeData.length) % gaugeData.length;
    renderGauge();
}
function buildDots() {
    const box = document.getElementById('gDots');
    if (!box) return;
    box.innerHTML = '';
    gaugeData.forEach((g, i) => {
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'px-dot' + (i === 0 ? ' on' : '');
        b.setAttribute('aria-label', g.titre);
        b.onclick = () => { gaugeIdx = i; renderGauge(); };
        box.appendChild(b);
    });
}

/* ── SPARKLINE (carte films) ─────────────────────────────── */
function drawSpark() {
    const c = initCv('cSpark'); if (!c) return;
    const {ctx, w, h} = c;
    const v = evolFilms.length ? evolFilms : [0];
    if (v.length < 2) {
        ctx.fillStyle = 'rgba(36,22,69,.5)';
        ctx.font = '11px Manrope, sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText('Historique insuffisant', w / 2, h / 2);
        return;
    }
    const max = Math.max(...v, 1), min = Math.min(...v, 0);
    const span = (max - min) || 1;
    const pad = 8;
    const pt = i => [ (i / (v.length - 1)) * (w - 8) + 4,
                      pad + (1 - (v[i] - min) / span) * (h - pad * 2) ];

    ctx.beginPath();
    ctx.moveTo(...pt(0));
    for (let i = 1; i < v.length; i++) ctx.lineTo(...pt(i));
    const [lx, ly] = pt(v.length - 1);
    ctx.lineTo(lx, h); ctx.lineTo(4, h); ctx.closePath();
    ctx.fillStyle = 'rgba(25,26,23,.14)'; ctx.fill();

    ctx.beginPath();
    ctx.moveTo(...pt(0));
    for (let i = 1; i < v.length; i++) ctx.lineTo(...pt(i));
    ctx.strokeStyle = INK; ctx.lineWidth = 2.4; ctx.lineJoin = 'round'; ctx.lineCap = 'round';
    ctx.stroke();

    ctx.fillStyle = MINT;
    ctx.beginPath(); ctx.arc(lx, ly, 5, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = INK; ctx.lineWidth = 2;
    ctx.beginPath(); ctx.arc(lx, ly, 5, 0, Math.PI * 2); ctx.stroke();
}

/* ── DONUT ───────────────────────────────────────────────── */
function drawDonut(id, values, colors) {
    const el = document.getElementById(id); if (!el) return;
    const sz = parseInt(el.getAttribute('width') || 140);
    el.width = sz * DPR; el.height = sz * DPR;
    el.style.width = sz + 'px'; el.style.height = sz + 'px';
    const ctx = el.getContext('2d');
    ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    const cx = sz / 2, cy = sz / 2, R = sz / 2 - 4, r = R * 0.62;
    const total = values.reduce((a, b) => a + b, 0);
    const bg = getComputedStyle(document.getElementById('pdgx')).getPropertyValue('--px-card').trim() || '#fff';
    const ink = getComputedStyle(document.getElementById('pdgx')).getPropertyValue('--px-ink').trim() || INK;

    if (!total) {
        ctx.fillStyle = GREY;
        ctx.beginPath(); ctx.arc(cx, cy, R, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = bg;
        ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = ink; ctx.font = '800 15px "Plus Jakarta Sans", sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText('0', cx, cy);
        return;
    }
    let angle = -Math.PI / 2;
    values.forEach((v, i) => {
        if (!v) return;
        const slice = (v / total) * Math.PI * 2;
        ctx.fillStyle = colors[i];
        ctx.beginPath(); ctx.moveTo(cx, cy); ctx.arc(cx, cy, R, angle, angle + slice); ctx.closePath(); ctx.fill();
        angle += slice;
    });
    ctx.fillStyle = bg;
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = ink; ctx.font = '800 17px "Plus Jakarta Sans", sans-serif';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(fmtN(total), cx, cy);
}

/* ── BARRES HORIZONTALES ─────────────────────────────────── */
function drawHBar(id, labels, values, color) {
    const c = initCv(id); if (!c) return;
    const {ctx, w, h} = c;
    const ink = getComputedStyle(document.getElementById('pdgx')).getPropertyValue('--px-ink').trim() || INK;
    const soft = getComputedStyle(document.getElementById('pdgx')).getPropertyValue('--px-ink-soft').trim() || '#5D6459';
    if (!labels.length) {
        ctx.fillStyle = soft; ctx.font = '12px Manrope, sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText('Aucune donnée', w / 2, h / 2); return;
    }
    const lw = Math.min(120, w * 0.32), rw = 44;
    const bArea = Math.max(20, w - lw - rw);
    const rh = h / labels.length;
    const bh = Math.min(rh * 0.5, 22);
    const max = Math.max(...values, 1);
    labels.forEach((lbl, i) => {
        const y  = i * rh;
        const by = y + (rh - bh) / 2;
        const bw = Math.max((values[i] / max) * bArea, values[i] > 0 ? 6 : 0);
        ctx.fillStyle = ink; ctx.font = '600 11.5px Manrope, sans-serif';
        ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
        ctx.fillText(lbl.length > 15 ? lbl.substring(0, 15) + '…' : lbl, lw - 10, y + rh / 2);
        if (bw > 0) { ctx.fillStyle = color; rrect(ctx, lw, by, bw, bh, bh / 2); ctx.fill(); }
        ctx.fillStyle = soft; ctx.font = '600 11px Manrope, sans-serif';
        ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
        ctx.fillText(values[i] > 0 ? fmtN(values[i]) : '—', lw + bw + 8, y + rh / 2);
    });
}

function drawPmma() {
    const lows = pmmaBas.map(n => n > 0);
    const c = initCv('cPmma'); if (!c) return;
    const {ctx, w, h} = c;
    const ink = getComputedStyle(document.getElementById('pdgx')).getPropertyValue('--px-ink').trim() || INK;
    const soft = getComputedStyle(document.getElementById('pdgx')).getPropertyValue('--px-ink-soft').trim() || '#5D6459';
    if (!pmmaLabels.length) {
        ctx.fillStyle = soft; ctx.font = '12px Manrope, sans-serif';
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText('Aucun stock PMMA', w / 2, h / 2); return;
    }
    const lw = Math.min(110, w * 0.32), rw = 44;
    const bArea = Math.max(20, w - lw - rw);
    const rh = h / pmmaLabels.length;
    const max = Math.max(...pmmaTotals, 1);
    pmmaLabels.forEach((lbl, i) => {
        const y = i * rh, bh = Math.min(rh * 0.5, 22), by = y + (rh - bh) / 2;
        const tot = pmmaTotals[i];
        const bw = Math.max((tot / max) * bArea, tot > 0 ? 6 : 0);
        ctx.fillStyle = ink; ctx.font = '600 11.5px Manrope, sans-serif';
        ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
        ctx.fillText(lbl.length > 13 ? lbl.substring(0, 13) + '…' : lbl, lw - 10, y + rh / 2);
        if (bw > 0) { ctx.fillStyle = lows[i] ? CORAL : MINT; rrect(ctx, lw, by, bw, bh, bh / 2); ctx.fill(); }
        ctx.fillStyle = soft; ctx.font = '600 11px Manrope, sans-serif';
        ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
        ctx.fillText(fmtN(tot), lw + bw + 8, y + rh / 2);
    });
}

function drawRivets() {
    const c = initCv('cRivets'); if (!c) return;
    const {ctx, w, h} = c;
    const ink = getComputedStyle(document.getElementById('pdgx')).getPropertyValue('--px-ink').trim() || INK;
    const soft = getComputedStyle(document.getElementById('pdgx')).getPropertyValue('--px-ink-soft').trim() || '#5D6459';
    const lw = Math.min(110, w * 0.32), rw = 44;
    const bArea = Math.max(20, w - lw - rw);
    const rh = h / (rivLabels.length || 1);
    const max = Math.max(...rivTotals, 1);
    rivLabels.forEach((lbl, i) => {
        const y = i * rh, bh = Math.min(rh * 0.5, 22), by = y + (rh - bh) / 2;
        const tot = rivTotals[i];
        const bw = Math.max((tot / max) * bArea, tot > 0 ? 6 : 0);
        const bas = (rivDetail[i] || []).filter(s => s.qty > 0 && s.qty < 200).length;
        ctx.fillStyle = ink; ctx.font = '600 11.5px Manrope, sans-serif';
        ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
        ctx.fillText(lbl, lw - 10, y + rh / 2);
        if (tot > 0) {
            ctx.fillStyle = bas > 0 ? CORAL : (i === 0 ? MINT : LILAC);
            rrect(ctx, lw, by, bw, bh, bh / 2); ctx.fill();
            ctx.fillStyle = soft; ctx.font = '600 11px Manrope, sans-serif';
            ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
            ctx.fillText(fmtN(tot), lw + bw + 8, y + rh / 2);
        } else {
            ctx.fillStyle = soft; ctx.font = '11px Manrope, sans-serif';
            ctx.textAlign = 'left'; ctx.textBaseline = 'middle';
            ctx.fillText('—', lw + 8, y + rh / 2);
        }
    });
}

/* ── INFOBULLES ──────────────────────────────────────────── */
function showTip(e, html) {
    const tip = document.getElementById('pdg-tip');
    tip.innerHTML = html;
    tip.style.display = 'block';
    const tw = tip.offsetWidth, th = tip.offsetHeight;
    const x = e.clientX + 16, y = e.clientY - 10;
    tip.style.left = (x + tw > window.innerWidth - 8 ? x - tw - 32 : x) + 'px';
    tip.style.top  = (y + th > window.innerHeight - 8 ? y - th + 20 : y) + 'px';
}
function hideTip() { document.getElementById('pdg-tip').style.display = 'none'; }

function bindTip(canvasId, labels, rowsFor) {
    const el = document.getElementById(canvasId);
    if (!el || !labels.length) return;
    el.style.cursor = 'crosshair';
    el.addEventListener('mousemove', e => {
        const rect = el.getBoundingClientRect();
        const idx = Math.floor((e.clientY - rect.top) / (rect.height / labels.length));
        if (idx >= 0 && idx < labels.length) showTip(e, rowsFor(idx));
        else hideTip();
    });
    el.addEventListener('mouseleave', hideTip);
}

function tipRow(left, right, alert) {
    return `<div style="display:flex;justify-content:space-between;gap:18px;color:${alert ? '#FFB4A2' : 'rgba(244,245,241,.86)'}">
        <span>${left}</span><span style="font-weight:700">${right}</span></div>`;
}

function setupHover() {
    bindTip('cFilms', filmsLabels, i => {
        const d = filmsDetail[i] || [];
        let html = `<div style="font-weight:800;font-size:13px;margin-bottom:7px">${filmsLabels[i]}</div>`;
        d.forEach(s => html += tipRow(s.site, `${fmtN(s.films)} <span style="font-weight:400;opacity:.55">/ ${fmtN(filmsValues[i])}</span>`, false));
        if (!d.length) html += `<div style="opacity:.55">Aucune donnée</div>`;
        return html;
    });
    bindTip('cPmma', pmmaLabels, i => {
        const d = pmmaDetail[i] || [];
        let html = `<div style="font-weight:800;font-size:13px;margin-bottom:7px">${pmmaLabels[i]}</div>`;
        d.forEach(s => html += tipRow(s.site || '—', `${fmtN(s.qty)} <span style="font-weight:400;opacity:.55">/ ${fmtN(pmmaTotals[i])}</span>`, s.qty < s.seuil));
        if (!d.length) html += `<div style="opacity:.55">Aucun stock</div>`;
        if (pmmaBas[i] > 0) html += `<div style="margin-top:7px;color:#FFB4A2;font-size:11px;font-weight:700">⚠ ${pmmaBas[i]} site(s) sous seuil</div>`;
        return html;
    });
    bindTip('cRivets', rivLabels, i => {
        const d = rivDetail[i] || [];
        let html = `<div style="font-weight:800;font-size:13px;margin-bottom:7px">${rivLabels[i]}</div>`;
        d.forEach(s => html += tipRow(s.site, `${fmtN(s.qty)} <span style="font-weight:400;opacity:.55">/ ${fmtN(rivTotals[i])}</span>`, s.qty < 200));
        const bas = d.filter(s => s.qty < 200).length;
        if (bas > 0) html += `<div style="margin-top:7px;color:#FFB4A2;font-size:11px;font-weight:700">⚠ ${bas} site(s) sous seuil (200)</div>`;
        return html;
    });
}

function openFilmsModal() {
    document.getElementById('modal-films').style.display = 'flex';
    setTimeout(() => drawHBar('cFilms', filmsLabels, filmsValues, LILAC), 40);
}

function render() {
    drawCompare();
    drawSpark();
    renderGauge();
    drawDonut('cStatuts', statutsData, [MINT, LILAC, CORAL]);
    drawDonut('cBobines', bobinesData, [LILAC, MINT, GREY]);
    drawDonut('cCmds',    cmdsData,    [SAND, LILAC, MINT]);
    drawPmma();
    drawRivets();
    if (document.getElementById('modal-films').style.display === 'flex') {
        drawHBar('cFilms', filmsLabels, filmsValues, LILAC);
    }
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.getElementById('modal-films').style.display = 'none';
});

window.addEventListener('load', () => { buildDots(); setTimeout(render, 60); setTimeout(setupHover, 120); });
let _rt; window.addEventListener('resize', () => { clearTimeout(_rt); _rt = setTimeout(render, 200); });
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
