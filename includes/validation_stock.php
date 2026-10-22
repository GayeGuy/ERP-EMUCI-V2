<?php
// ============================================================
//  includes/validation_stock.php
//  Logique partagée : comparaison point 18h (validé superviseur) vs
//  import EMUCI, pour la page GSB (validation_stock_matin.php),
//  l'import (import_emuci.php) et la validation du point 18h
//  (operations/point_journalier.php).
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit.php';

/** Statut du point 18h d'un site à une date, ou null si aucun point 18h n'existe. */
function vs_point18h_statut(int $site_id, string $date): ?string {
    return db_fetch_value(
        "SELECT statut FROM op_points_journaliers
         WHERE site_id=? AND date_point=? AND type_point='point_18h'
         ORDER BY id DESC LIMIT 1",
        [$site_id, $date]
    );
}

// ── Décisions GSB déjà enregistrées bobine par bobine pour un site/date.
//    Une bobine peut être traitée plusieurs fois (révision) : la ligne la plus
//    récente d'ecarts_bobines fait foi. Retourne une map bobine_id => décision.
function vs_decisions_bobines_site(int $site_id, string $date): array {
    $rows = db_fetch_all(
        "SELECT DISTINCT ON (e.bobine_id)
                e.bobine_id, e.statut, e.motif, e.stock_systeme, e.stock_physique, e.ecart, e.created_at,
                b.numero, b.type_code, b.format,
                CONCAT(u.prenom,' ',u.nom) AS par_nom
         FROM ecarts_bobines e
         JOIN op_bobines b ON b.id = e.bobine_id
         LEFT JOIN users u ON u.id = e.created_by
         WHERE e.source = 'validation_stock' AND e.date_constat = ? AND b.site_id = ?
         ORDER BY e.bobine_id, e.id DESC",
        [$date, $site_id]
    );
    $map = [];
    foreach ($rows as $r) {
        $map[(int)$r['bobine_id']] = [
            'bobine_id'      => (int)$r['bobine_id'],
            'numero'         => $r['numero'],
            'type_code'      => $r['type_code'] ?? '',
            'format'         => $r['format'] ?? '',
            'stock_systeme'  => (int)$r['stock_systeme'],
            'films_restants' => (int)$r['stock_physique'],
            'ecart'          => (int)$r['ecart'],
            'decision'       => match ($r['statut']) {
                'resolu' => 'reajuste',
                'ignore' => 'autorise_ecart',
                default  => 'refuse',
            },
            'commentaire'    => $r['motif'] ?? '',
            'par'            => trim($r['par_nom'] ?? '') ?: 'GSB',
            'at'             => $r['created_at'],
        ];
    }
    return $map;
}

// ── Écarts d'un site pour une date, pour l'affichage détaillé bobine par
//    bobine côté GSB. Source unique : le point 18h validé par le
//    superviseur. Aucun repli sur un autre point ou une autre table —
//    tant que ce n'est pas validé, il n'y a rien à comparer.
function vs_calculer_ecarts_site(int $site_id, string $date): array {
    $pj_entries = db_fetch_all(
        "SELECT fu.bobine_id,
                SUM(fu.films_utilises)   AS films_utilises,
                SUM(fu.films_endommages) AS films_endommages,
                b.numero, b.type_code, b.format, b.serie,
                b.stock_systeme, b.films_restants, b.statut,
                pj.id AS point_id, pj.type_point
         FROM op_films_utilises fu
         JOIN op_points_journaliers pj ON pj.id = fu.point_id
         JOIN op_bobines b ON b.id = fu.bobine_id
         WHERE pj.site_id=? AND pj.date_point=?
           AND pj.type_point='point_18h' AND pj.statut='valide'
         GROUP BY fu.bobine_id, b.numero, b.type_code, b.format, b.serie,
                  b.stock_systeme, b.films_restants, b.statut, pj.id, pj.type_point",
        [$site_id, $date]
    );
    $dernier_import = db_fetch_value(
        "SELECT MAX(date_import) FROM import_optotrace WHERE site_id=? AND date_import=?",
        [$site_id, $date]
    );
    $ecarts = []; $bobines_detail = []; $nb_ecarts = 0;
    foreach ($pj_entries as $b) {
        $films_utilises   = (int)$b['films_utilises'];
        $films_endommages = (int)($b['films_endommages'] ?? 0);
        $films_total_pj   = $films_utilises + $films_endommages;
        // op_bobines.films_restants est déjà mis à jour par la validation du
        // PJ → ne pas re-soustraire les films du jour.
        $films_restants = (int)($b['films_restants'] ?? $b['stock_systeme']);
        $stock_debut    = $films_restants + $films_total_pj;
        $stock_emuci = (int)($b['stock_systeme'] ?? 0);
        $ecart_val   = $dernier_import ? ($films_restants - $stock_emuci) : 0;
        $has_ecart   = $dernier_import !== null && $ecart_val !== 0;
        $ligne = [
            'bobine_id' => $b['bobine_id'], 'numero' => $b['numero'],
            'type_code' => $b['type_code'] ?? '', 'format' => $b['format'] ?? '',
            'stock_debut' => $stock_debut, 'films_utilises' => $films_utilises,
            'films_endommages' => $films_endommages, 'films_restants' => $films_restants,
            'stock_systeme' => $stock_emuci,
            'ecart' => $ecart_val, 'has_ecart' => $has_ecart,
        ];
        if ($has_ecart) {
            $ligne['causes'] = vs_causes_ecart($site_id, $date, $ligne);
            $nb_ecarts++; $ecarts[] = $ligne;
        }
        $bobines_detail[] = $ligne;
    }
    return compact('nb_ecarts', 'ecarts', 'bobines_detail', 'dernier_import');
}

// ── Causes possibles d'un écart bobine (ERP − EMUCI), pour aider le GSB à
//    trancher. Chaque cause porte, quand on sait la chiffrer, sa part signée
//    de l'écart : + quand EMUCI a retiré des films que le point ne déclare
//    pas (ou pas ce jour-là), − quand l'ERP en a retiré qu'EMUCI n'a pas
//    retirés. Indicatif : rien n'est corrigé automatiquement.
function vs_causes_ecart(int $site_id, string $date, array $l): array {
    $bobine_id = (int)$l['bobine_id'];
    $numero    = (string)$l['numero'];
    $lendemain = date('Y-m-d', strtotime($date . ' +1 day'));
    $jj        = date('d/m', strtotime($date));
    $causes    = [];

    // 1. Plaques OptoPlate imprimées ce jour sur la bobine mais posées plus
    //    tard (« reserved ») — et l'inverse : posées ce jour, imprimées avant.
    if (db_colonne_existe('import_optoplate', 'date_impression')) {
        $imp_non_posees = (int)db_fetch_value(
            "SELECT COUNT(*) FROM import_optoplate
             WHERE num_bobine=? AND date_impression=?
               AND (date_installation IS NULL OR date_installation >= ?)
               AND COALESCE(type_desactivation,'') <> 'Incident'", [$numero, $date, $lendemain]);
        if ($imp_non_posees > 0)
            $causes[] = ['films' => $imp_non_posees, 'texte' =>
                "$imp_non_posees plaque(s) imprimée(s) le $jj sur cette bobine mais posée(s) plus tard : EMUCI a déjà retiré le film. "
                . "Le coordinateur les a-t-il déclarées en films utilisés ?"];
        $posees_avant = (int)db_fetch_value(
            "SELECT COUNT(*) FROM import_optoplate
             WHERE num_bobine=? AND date_installation >= ? AND date_installation < ?
               AND date_impression < ?", [$numero, $date, $lendemain, $date]);
        if ($posees_avant > 0)
            $causes[] = ['films' => -$posees_avant, 'texte' =>
                "$posees_avant plaque(s) posée(s) le $jj mais imprimée(s) un jour précédent : EMUCI a retiré leur film ce jour-là. "
                . "Si le coordinateur les déclare aujourd'hui, l'ERP retire le film une 2e fois."];
    }

    // 2. Export OptoTrace pris après la journée : sa quantité inclut déjà la
    //    consommation des jours suivants.
    $last_use = db_fetch_value(
        "SELECT MAX(last_use) FROM import_optotrace WHERE keyname=? AND date_import=?", [$numero, $date]);
    if ($last_use && substr((string)$last_use, 0, 10) >= $lendemain)
        $causes[] = ['films' => null, 'texte' =>
            "L'export OptoTrace du $jj a été pris après la journée (dernière utilisation de la bobine le "
            . date('d/m à H:i', strtotime((string)$last_use)) . ") : la consommation de ces jours-là est déjà retirée côté EMUCI."];

    // 3. Films retirés par des points postérieurs : le restant ERP comparé est
    //    celui d'aujourd'hui, pas celui du soir de la date contrôlée.
    $regle_18h = db_colonne_existe('op_points_journaliers', 'stock_deduit');
    $apres = (int)db_fetch_value(
        "SELECT COALESCE(SUM(fu.films_utilises + fu.films_endommages),0)
         FROM op_films_utilises fu JOIN op_points_journaliers p ON p.id = fu.point_id
         WHERE fu.bobine_id=? AND p.date_point > ? AND "
        . ($regle_18h ? "p.stock_deduit >= 1" : "p.statut <> 'rejete'"), [$bobine_id, $date]);
    if ($apres > 0)
        $causes[] = ['films' => -$apres, 'texte' =>
            "$apres film(s) déjà retiré(s) de cette bobine par des points postérieurs au $jj : le stock ERP comparé est celui d'aujourd'hui."];

    // 4. Films non conformes du jour : déclarés par le coordinateur, ils ne
    //    sortent pas du stock ERP de la bobine (seuls utilisés + endommagés),
    //    alors qu'OptoTrace a pu les retirer. Non chiffré : selon la date, l'ERP
    //    les retirait encore (ancienne règle) ou non.
    if (db_colonne_existe('op_films_utilises', 'non_conformes')) {
        $nc = (int)db_fetch_value(
            "SELECT COALESCE(SUM(fu.non_conformes),0) FROM op_films_utilises fu
             JOIN op_points_journaliers p ON p.id = fu.point_id
             WHERE fu.bobine_id=? AND p.date_point=? AND p.type_point='point_18h'", [$bobine_id, $date]);
        if ($nc > 0)
            $causes[] = ['films' => null, 'texte' =>
                "$nc film(s) non conforme(s) déclaré(s) le $jj sur cette bobine : ils ne sortent pas du stock ERP. "
                . "Si OptoTrace les a retirés, ils expliquent +$nc de l'écart."];
    }

    // 5. Même écart déjà constaté avant et non réajusté : décalage ancien
    //    reporté de jour en jour. Il explique à lui seul tout l'écart.
    $prec = db_fetch_one(
        "SELECT date_constat, ecart, statut FROM ecarts_bobines
         WHERE bobine_id=? AND source='validation_stock' AND date_constat < ?
         ORDER BY date_constat DESC, id DESC LIMIT 1", [$bobine_id, $date]);
    if ($prec && (int)$prec['ecart'] === (int)$l['ecart'] && $prec['statut'] !== 'resolu')
        $causes = [['films' => (int)$l['ecart'], 'texte' =>
            "Le même écart (" . ((int)$prec['ecart'] > 0 ? '+' : '') . (int)$prec['ecart'] . ") a déjà été constaté le "
            . date('d/m', strtotime((string)$prec['date_constat'])) . " sans réajustement : décalage ancien reporté. Un réajustement le soldera."]];

    return $causes;
}

// ── Notifie le GSB qu'un site présente un écart à traiter, une seule fois
//    par site et par jour (le lien encode site+date, sert de clé anti-doublon).
function vs_notifier_gsb_ecart(int $site_id, string $date, string $site_nom, int $nb_ecarts): void {
    $lien = '/pages/validation_stock_matin.php?site=' . $site_id . '&date=' . $date;
    $deja = (int)db_fetch_value(
        "SELECT COUNT(*) FROM notifications WHERE type='stock_validation' AND lien=? AND created_at::date = CURRENT_DATE",
        [$lien]
    );
    if ($deja > 0) return;
    $gsb_users = db_fetch_all(
        "SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug IN ('gsb','admin','superadmin') AND u.actif=1"
    );
    foreach ($gsb_users as $gsb) {
        db_query("INSERT INTO notifications (user_id,type,titre,message,lien) VALUES (?,?,?,?,?)",
            [$gsb['id'], 'stock_validation', '⚠️ Écart stock détecté',
             "Site $site_nom ($date) : $nb_ecarts écart(s) ERP EMUCI / EMUCI. Point 18h validé — validation GSB requise.",
             $lien]);
    }
}

// ── Évalue si un site est prêt pour la validation stock : à appeler quand
//    le point 18h vient d'être validé par le superviseur OU quand un
//    import EMUCI vient d'arriver. Dans les deux cas, si l'autre condition
//    est déjà remplie, la comparaison se fait immédiatement — l'ordre des
//    deux événements n'a pas d'importance. Ne touche jamais un site déjà
//    tranché par une décision humaine du GSB (reajuste/refuse/valide_gsb/
//    autorise_ecart) : seule une validation encore auto (ou l'absence de
//    validation) peut être (re)calculée ici.
//    Retour : 'pas_valide' | 'deja_traite' | 'pas_import' | 'valide_auto' | 'ecart_detecte'
function vs_evaluer_site_gsb(int $site_id, string $date, int $user_id, ?array $result = null): string {
    if (vs_point18h_statut($site_id, $date) !== 'valide') return 'pas_valide';

    $exist = db_fetch_one(
        "SELECT statut FROM validations_stock_matin WHERE site_id=? AND date_validation=?",
        [$site_id, $date]
    );
    if ($exist && $exist['statut'] !== 'valide_auto') return 'deja_traite';

    // $result peut être fourni par l'appelant (déjà calculé pour l'affichage,
    // ex. la boucle de la page GSB) pour ne pas recalculer deux fois avec un
    // risque de divergence entre "notifié d'un écart" et "rien à Traiter".
    $result ??= vs_calculer_ecarts_site($site_id, $date);
    if (!$result['dernier_import']) return 'pas_import';
    if (empty($result['bobines_detail'])) return 'pas_import';

    if ($result['nb_ecarts'] === 0) {
        $snapshot = json_encode($result['bobines_detail']);
        db_query(
            "INSERT INTO validations_stock_matin (site_id,date_validation,statut,nb_ecarts,bobines_snapshot,gsb_user_id,gsb_at)
             VALUES (?,?,'valide_auto',0,?,?,NOW())
             ON CONFLICT (site_id,date_validation) DO UPDATE SET statut='valide_auto',nb_ecarts=0,bobines_snapshot=EXCLUDED.bobines_snapshot,gsb_user_id=EXCLUDED.gsb_user_id,gsb_at=NOW()
             WHERE validations_stock_matin.gsb_user_id IS NULL",
            [$site_id, $date, $snapshot, $user_id]
        );
        $coords = db_fetch_all(
            "SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='coordinateur_site' AND u.site_id=? AND u.actif=1",
            [$site_id]
        );
        foreach ($coords as $c) {
            db_query("INSERT INTO notifications (user_id,type,titre,message,lien) VALUES (?,?,?,?,?)",
                [$c['id'], 'info', '✅ Stock validé', "Votre stock bobines du $date est validé automatiquement.", '/pages/validation_stock_matin.php']);
        }
        return 'valide_auto';
    }

    $site_nom = db_fetch_value("SELECT nom FROM sites WHERE id=?", [$site_id]) ?? "site #$site_id";
    vs_notifier_gsb_ecart($site_id, $date, $site_nom, $result['nb_ecarts']);
    return 'ecart_detecte';
}

// ── Réexamine les validations auto (0 écart) d'une date — seulement si un
//    import EMUCI (OptoTrace) plus récent que la validation existe. Sans
//    nouvel import, un site valide_auto n'est jamais retouché : un point du
//    lendemain, un rejet ou une correction sur une autre bobine ne doit
//    jamais le repasser en attente à tort (c'était le bug).
function vs_reverifier_valides_auto(string $date, int $user_id): void {
    $dernier = db_fetch_one(
        "SELECT created_at FROM import_sessions_emuci
         WHERE date_import=? AND type_import='optotrace' AND statut='termine'
         ORDER BY created_at DESC LIMIT 1",
        [$date]
    );
    if (!$dernier) return;

    $valides_auto = db_fetch_all(
        "SELECT site_id FROM validations_stock_matin
         WHERE date_validation=? AND statut='valide_auto' AND (gsb_at IS NULL OR gsb_at < ?)",
        [$date, $dernier['created_at']]
    );
    foreach ($valides_auto as $sv) {
        $site_id  = (int)$sv['site_id'];
        $recheck  = vs_calculer_ecarts_site($site_id, $date);
        if ($recheck['nb_ecarts'] === 0) continue;

        db_query("DELETE FROM validations_stock_matin WHERE site_id=? AND date_validation=?", [$site_id, $date]);
        audit_log($user_id, 'UPDATE', 'validations_stock_matin', 0,
            "Validation auto annulée site:$site_id $date — écart détecté suite à un import EMUCI plus récent");
        $coords = db_fetch_all(
            "SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='coordinateur_site' AND u.site_id=? AND u.actif=1",
            [$site_id]
        );
        foreach ($coords as $c) {
            db_query("INSERT INTO notifications (user_id,type,titre,message,lien) VALUES (?,?,?,?,?)",
                [$c['id'], 'info', '⚠️ Validation annulée',
                 "Votre stock bobines du $date, validé automatiquement, présente désormais un écart après un nouvel import EMUCI. Il repasse en attente de traitement par le GSB.",
                 '/pages/validation_stock_matin.php']);
        }
    }
}
