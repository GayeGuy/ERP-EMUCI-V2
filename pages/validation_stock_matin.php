<?php
// ============================================================
//  pages/validation_stock_matin.php
//  Validation stock bobines matin — GSB / Admin / Superviseur
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

require_auth();


$user      = current_user();
$role_slug = $user['role_slug'] ?? '';
$page_title  = 'Validation Stock Jour';
$active_page = 'validation_stock_matin';

$is_coord   = ($role_slug === 'coordinateur_site');
$site_force = ($is_coord && ($user['site_id'] ?? 0)) ? (int)$user['site_id'] : 0;

$gsb_roles   = ['admin','superadmin','superviseur_it','gestionnaire_stock_bobines','gestionnaire_stock','superviseur_operation'];
$can_valider = in_array($role_slug, $gsb_roles) || is_support_it_with('gestionnaire_bobines');

if (!$can_valider && !$is_coord) {
    http_response_code(403);
    echo '<p style="padding:40px;color:red">Accès refusé.</p>';
    exit;
}

$sites_list = db_fetch_all("SELECT id,nom FROM sites WHERE actif=1 ORDER BY nom");

// ============================================================
// AJAX
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // ── CALCULER ÉCARTS D'UN SITE
    if ($action === 'calculer_ecarts') {
        try {
            $site_id = (int)($_POST['site_id'] ?? 0);
            $date    = trim($_POST['date'] ?? date('Y-m-d'));
            if (!$site_id) json_response(false, 'Site obligatoire.');

            // ── Source : Point 18h validé par le superviseur site
            // C'est ce point qui résume la journée et sert de référence pour la comparaison
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
                 WHERE pj.site_id=?
                   AND pj.date_point=?
                   AND pj.type_point='point_18h'
                   AND pj.statut IN ('valide','en_attente_validation','suivi','rejete')
                 GROUP BY fu.bobine_id, b.numero, b.type_code, b.format, b.serie,
                          b.stock_systeme, b.films_restants, b.statut, pj.id, pj.type_point",
                [$site_id, $date]
            );

            // Fallback 1 : si pas de point_18h, prendre n'importe quel point soumis du jour
            if (empty($pj_entries)) {
                $pj_entries = db_fetch_all(
                    "SELECT fu.bobine_id,
                            SUM(fu.films_utilises)   AS films_utilises,
                            SUM(fu.films_endommages) AS films_endommages,
                            b.numero, b.type_code, b.format, b.serie,
                            b.stock_systeme, b.films_restants, b.statut,
                            ANY_VALUE(pj.id) AS point_id, ANY_VALUE(pj.type_point) AS type_point
                     FROM op_films_utilises fu
                     JOIN op_points_journaliers pj ON pj.id = fu.point_id
                     JOIN op_bobines b ON b.id = fu.bobine_id
                     WHERE pj.site_id=? AND pj.date_point=?
                       AND pj.statut IN ('valide','en_attente_validation','suivi','rejete')
                     GROUP BY fu.bobine_id, b.numero, b.type_code, b.format, b.serie,
                              b.stock_systeme, b.films_restants, b.statut",
                    [$site_id, $date]
                );
            }

            // Fallback 2 : consommations_bobines si aucun point validé
            if (empty($pj_entries)) {
                $pj_entries = db_fetch_all(
                    "SELECT cb.bobine_id,
                            SUM(cb.quantite) AS films_utilises,
                            0 AS films_endommages,
                            b.numero, b.type_code, b.format, b.serie,
                            b.stock_systeme, b.films_restants, b.statut,
                            MAX(cb.stock_avant) AS stock_avant
                     FROM consommations_bobines cb
                     JOIN op_bobines b ON b.id = cb.bobine_id
                     WHERE cb.site_id=? AND cb.date_conso=?
                     GROUP BY cb.bobine_id, b.numero, b.type_code, b.format, b.serie,
                              b.stock_systeme, b.films_restants, b.statut",
                    [$site_id, $date]
                );
            }

            // ── Dernier import OptoPlate pour ce site
            $dernier_import = db_fetch_value(
                "SELECT MAX(date_import) FROM import_optoplate WHERE site_id=?", [$site_id]
            );

            $ecarts        = [];
            $bobines_detail = [];
            $nb_ecarts     = 0;

            foreach ($pj_entries as $b) {
                $films_utilises  = (int)$b['films_utilises'];
                $films_endommages= (int)($b['films_endommages'] ?? 0);
                $films_total_pj  = $films_utilises + $films_endommages;

                // Stock début de journée = stock actuel + films consommés aujourd'hui
                $stock_debut = isset($b['stock_avant'])
                    ? (int)$b['stock_avant']
                    : (int)$b['stock_systeme'] + $films_utilises;

                $films_restants  = $stock_debut - $films_total_pj;

                // Films selon OptoPlate
                $films_optoplate = $dernier_import
                    ? (int)db_fetch_value(
                        "SELECT COUNT(*) FROM import_optoplate
                         WHERE num_bobine=? AND statut_plaque='in_use' AND date_import=?",
                        [$b['numero'], $dernier_import])
                    : null;

                $ecart_val = $films_optoplate !== null
                    ? ($films_optoplate - $films_utilises)
                    : 0;
                $has_ecart = $films_optoplate !== null && $ecart_val !== 0;

                $ligne = [
                    'bobine_id'        => $b['bobine_id'],
                    'numero'           => $b['numero'],
                    'type_code'        => $b['type_code'] ?? '',
                    'format'           => $b['format'] ?? '',
                    'stock_debut'      => $stock_debut,
                    'films_utilises'   => $films_utilises,
                    'films_endommages' => $films_endommages,
                    'films_restants'   => $films_restants,
                    'films_optoplate'  => $films_optoplate,
                    'stock_systeme'    => (int)($b['stock_systeme'] ?? 0),
                    'ecart'            => $ecart_val,
                    'has_ecart'        => $has_ecart,
                ];

                $bobines_detail[] = $ligne;
                if ($has_ecart) { $nb_ecarts++; $ecarts[] = $ligne; }
            }

            // ── Validation existante
            $exist = db_fetch_one(
                "SELECT * FROM validations_stock_matin WHERE site_id=? AND date_validation=?",
                [$site_id, $date]
            );

            // Fallback historique : si données live absentes mais snapshot sauvegardé
            if (empty($bobines_detail) && !empty($exist['bobines_snapshot'])) {
                $bobines_detail = json_decode($exist['bobines_snapshot'], true) ?: [];
                $nb_ecarts      = 0;
                foreach ($bobines_detail as $bl) {
                    if (!empty($bl['has_ecart'])) $nb_ecarts++;
                }
            }

            json_response(true, '', [
                'ecarts'         => $ecarts,
                'bobines_detail' => $bobines_detail,
                'nb_ecarts'      => $nb_ecarts,
                'nb_bobines'     => count($pj_entries) ?: count($bobines_detail),
                'dernier_import' => $dernier_import,
                'validation'     => $exist,
            ]);
        } catch (Exception $ex) {
            json_response(false, 'Erreur serveur : ' . $ex->getMessage());
        }
    }

    // ── VALIDER AUTO (écart = 0)
    if ($action === 'valider_auto') {
        $site_id = (int)($_POST['site_id'] ?? 0);
        $date    = trim($_POST['date'] ?? date('Y-m-d'));
        $snap    = _calculer_ecarts_site($site_id, $date);
        $snapshot= json_encode($snap['bobines_detail'] ?: []);
        db_query(
            "INSERT INTO validations_stock_matin (site_id,date_validation,statut,nb_ecarts,bobines_snapshot,gsb_user_id,gsb_at)
             VALUES (?,?,'valide_auto',0,?,?,NOW())
             ON DUPLICATE KEY UPDATE statut='valide_auto',nb_ecarts=0,bobines_snapshot=VALUES(bobines_snapshot),gsb_user_id=VALUES(gsb_user_id),gsb_at=NOW()",
            [$site_id,$date,$snapshot,$user['id']]
        );
        // Notifier coordinateurs du site
        $coords = db_fetch_all("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='coordinateur_site' AND u.site_id=? AND u.actif=1",[$site_id]);
        foreach($coords as $c) {
            db_query("INSERT INTO notifications (user_id,type,titre,message,lien) VALUES (?,?,?,?,?)",
                [$c['id'],'stock_valide','✅ Stock validé',"Votre stock bobines du $date est validé. Vous pouvez commencer votre activité.",
                 '/pages/validation_stock_matin.php']);
        }
        audit_log($user['id'],'CREATE','validations_stock_matin',0,"Validation auto stock site:$site_id $date");
        json_response(true,'Stock validé automatiquement.');
    }

    // ── DÉCISION GSB (autoriser / réajuster / refuser)
    if ($action === 'decision_gsb') {
        if (!$can_valider) json_response(false,'Accès refusé.');
        $site_id    = (int)($_POST['site_id'] ?? 0);
        $date       = trim($_POST['date'] ?? date('Y-m-d'));
        $decision   = trim($_POST['decision'] ?? '');
        $commentaire= trim($_POST['commentaire'] ?? '');
        $ecarts_json= $_POST['ecarts_json'] ?? '[]';

        if (!in_array($decision,['autorise_ecart','reajuste','refuse']))
            json_response(false,'Décision invalide.');
        if (!$commentaire) json_response(false,'Le commentaire est obligatoire.');

        $ecarts      = json_decode($ecarts_json, true) ?: [];
        $nb_ecarts   = count($ecarts);
        $bobines_json= $_POST['bobines_json'] ?? '[]';

        db_begin();
        try {
            // Si réajustement : corriger les stocks
            if ($decision === 'reajuste') {
                foreach ($ecarts as $e) {
                    $nouveau = max(0, (int)$e['stock_systeme'] - (int)$e['ecart']);
                    db_query("UPDATE op_bobines SET stock_systeme=?,films_restants=? WHERE id=?",
                        [$nouveau,$nouveau,$e['bobine_id']]);
                    db_query("INSERT INTO mouvements_bobines (bobine_id,type,quantite,stock_avant,stock_apres,motif,created_by)
                              VALUES (?,?,?,?,?,?,?)",
                        [$e['bobine_id'],'ajustement_gsb',-$e['ecart'],$e['stock_systeme'],$nouveau,"Réajustement GSB matin $date : $commentaire",$user['id']]);
                }
            }

            // Si refus : créer une demande de correction pour chaque bobine en écart
            if ($decision === 'refuse') {
                // Annuler les demandes de correction précédentes encore en attente pour ce site/date
                db_query(
                    "UPDATE demandes_correction_saisie SET statut='valide'
                     WHERE site_id=? AND date_cible=? AND statut='en_attente'",
                    [$site_id, $date]
                );
                foreach ($ecarts as $e) {
                    db_query(
                        "INSERT INTO demandes_correction_saisie
                         (bobine_id, site_id, gsb_id, date_cible, films_pj, films_emuci, ecart, notes_gsb)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $e['bobine_id'],
                            $site_id,
                            $user['id'],
                            $date,
                            (int)$e['films_pj'],
                            (int)$e['films_optoplate'],
                            (int)$e['ecart'],
                            $commentaire,
                        ]
                    );
                }
            }

            db_query(
                "INSERT INTO validations_stock_matin (site_id,date_validation,statut,nb_ecarts,details_ecarts,bobines_snapshot,gsb_user_id,gsb_at,commentaire)
                 VALUES (?,?,?,?,?,?,?,NOW(),?)
                 ON DUPLICATE KEY UPDATE statut=VALUES(statut),nb_ecarts=VALUES(nb_ecarts),
                 details_ecarts=VALUES(details_ecarts),bobines_snapshot=VALUES(bobines_snapshot),
                 gsb_user_id=VALUES(gsb_user_id),gsb_at=NOW(),commentaire=VALUES(commentaire)",
                [$site_id,$date,$decision,$nb_ecarts,$ecarts_json,$bobines_json,$user['id'],$commentaire]
            );

            // Notifier coordinateurs
            $coords = db_fetch_all("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='coordinateur_site' AND u.site_id=? AND u.actif=1",[$site_id]);
            $msg_map = [
                'autorise_ecart' => "⚠️ Votre stock du $date présente des écarts mais vous êtes autorisé à travailler. Commentaire GSB : $commentaire",
                'reajuste'       => "🔄 Votre stock du $date a été réajusté par le gestionnaire. Commentaire : $commentaire",
                'refuse'         => "❌ Votre activité du $date est bloquée par le gestionnaire. Commentaire : $commentaire",
            ];
            $titre_map = [
                'autorise_ecart' => '⚠️ Écart autorisé',
                'reajuste'       => '🔄 Stock réajusté',
                'refuse'         => '❌ Activité bloquée',
            ];
            foreach($coords as $c) {
                db_query("INSERT INTO notifications (user_id,type,titre,message,lien) VALUES (?,?,?,?,?)",
                    [$c['id'],'stock_validation',$titre_map[$decision],$msg_map[$decision],
                     '/pages/validation_stock_matin.php']);
            }

            audit_log($user['id'],'UPDATE','validations_stock_matin',0,"$decision stock site:$site_id $date — $commentaire");
            db_commit();
            json_response(true,match($decision){
                'autorise_ecart'=>'Écart autorisé. Coordinateur peut travailler.',
                'reajuste'=>'Stock réajusté et coordinateur notifié.',
                'refuse'=>'Coordinateur bloqué et notifié.',
            });
        } catch(Exception $e){ db_rollback(); json_response(false,'Erreur: '.$e->getMessage()); }
    }

    // ── LISTE COMPLÈTE DES BOBINES D'UN SITE (pour détail)
    if ($action === 'get_bobines_detail') {
        $site_id = (int)($_POST['site_id'] ?? 0);
        if (!$site_id) json_response(false,'Site obligatoire.');
        $bobines = db_fetch_all(
            "SELECT b.id, b.numero, b.type_code, b.serie,
                    b.films_restants, b.stock_systeme, b.statut, b.format
             FROM op_bobines b
             WHERE b.site_id=? AND b.statut IN ('en_cours','en_stock')
             ORDER BY b.serie, b.numero",
            [$site_id]
        );
        json_response(true, '', $bobines);
    }

    // ── DEMANDE DE CORRECTION PAR BOBINE (Option 2 — accord coordinateur)
    if ($action === 'demander_correction_bobine') {
        if (!$can_valider) json_response(false, 'Accès refusé.');
        $bobine_id      = (int)($_POST['bobine_id']      ?? 0);
        $site_id        = (int)($_POST['site_id']        ?? 0);
        $date           = trim($_POST['date']            ?? date('Y-m-d'));
        $notes          = trim($_POST['notes_gsb']       ?? '');
        $films_pj       = (int)($_POST['films_pj']       ?? 0);
        $films_proposes = (int)($_POST['films_proposes'] ?? 0);
        if (!$bobine_id || !$site_id) json_response(false, 'Données manquantes.');
        if (!$notes)         json_response(false, 'Le motif est obligatoire.');
        try {
            // Retrouver le point journalier et vérifier qu'il a les données bobine
            $point_id = (int)db_fetch_value(
                "SELECT pj.id FROM op_points_journaliers pj
                 JOIN op_films_utilises fu ON fu.point_id = pj.id
                 WHERE fu.bobine_id = ? AND pj.site_id = ? AND pj.date_point = ?
                 ORDER BY pj.id DESC LIMIT 1",
                [$bobine_id, $site_id, $date]
            );
            if (!$point_id) json_response(false, 'Point journalier introuvable pour cette bobine à cette date.');

            // Vérifier qu'il n'y a pas déjà une correction en attente pour cette bobine/date
            $existing = db_fetch_value(
                "SELECT id FROM corrections_bobines WHERE bobine_id=? AND site_id=? AND date_point=? AND statut='en_attente'",
                [$bobine_id, $site_id, $date]
            );
            if ($existing) json_response(false, 'Une demande de correction est déjà en attente pour cette bobine.');

            // Insérer la correction dans la table dédiée
            db_query(
                "INSERT INTO corrections_bobines
                 (point_id, bobine_id, site_id, date_point, films_original, films_proposes, motif_gsb, gsb_id, statut)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'en_attente')",
                [$point_id, $bobine_id, $site_id, $date, $films_pj, $films_proposes, $notes, $user['id']]
            );
            $correction_id = (int)db_last_id();

            // Notifier le(s) coordinateur(s) du site
            $gsb_nom = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
            $bobine_num = db_fetch_value("SELECT numero FROM op_bobines WHERE id=?", [$bobine_id]) ?? "bobine #$bobine_id";
            $coords = db_fetch_all(
                "SELECT u.id FROM users u
                 JOIN roles r ON r.id = u.role_id
                 WHERE r.slug = 'coordinateur_site' AND u.site_id = ? AND u.actif = 1",
                [$site_id]
            );
            $notif_sent = 0;
            foreach ($coords as $c) {
                db_query(
                    "INSERT INTO notifications (user_id, type, titre, message, lien) VALUES (?,?,?,?,?)",
                    [
                        $c['id'], 'info',
                        '✏️ Correction de saisie demandée',
                        "Le GSB $gsb_nom demande une correction sur la bobine $bobine_num (saisie du $date). Valeur actuelle : $films_pj films → proposition : $films_proposes films. Motif : $notes",
                        '/pages/operations/point_journalier.php',
                    ]
                );
                $notif_sent++;
            }
            audit_log($user['id'], 'CREATE', 'corrections_bobines', $correction_id,
                "Correction demandée bobine:$bobine_id site:$site_id date:$date original:$films_pj proposes:$films_proposes");
            $msg = $notif_sent > 0
                ? 'Demande envoyée. Le coordinateur doit confirmer la correction.'
                : 'Demande enregistrée. Aucun coordinateur actif trouvé pour ce site.';
            json_response(true, $msg);
        } catch (Exception $ex) {
            json_response(false, $ex->getMessage());
        }
    }

    json_response(false,'Action inconnue.');
}

// ============================================================
// DONNÉES
// ============================================================
$f_date = trim($_GET['date'] ?? date('Y-m-d'));
$f_site = (int)($_GET['site'] ?? 0);

// ── Fonction réutilisable : calculer les écarts d'un site pour une date
function _calculer_ecarts_site(int $site_id, string $date): array {
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
    if (empty($pj_entries)) {
        $pj_entries = db_fetch_all(
            "SELECT fu.bobine_id,
                    SUM(fu.films_utilises)   AS films_utilises,
                    SUM(fu.films_endommages) AS films_endommages,
                    b.numero, b.type_code, b.format, b.serie,
                    b.stock_systeme, b.films_restants, b.statut,
                    ANY_VALUE(pj.id) AS point_id, ANY_VALUE(pj.type_point) AS type_point
             FROM op_films_utilises fu
             JOIN op_points_journaliers pj ON pj.id = fu.point_id
             JOIN op_bobines b ON b.id = fu.bobine_id
             WHERE pj.site_id=? AND pj.date_point=?
               AND pj.statut IN ('valide','en_attente_validation','suivi','rejete')
             GROUP BY fu.bobine_id, b.numero, b.type_code, b.format, b.serie,
                      b.stock_systeme, b.films_restants, b.statut",
            [$site_id, $date]
        );
    }
    $dernier_import = db_fetch_value(
        "SELECT MAX(date_import) FROM import_optoplate WHERE site_id=?", [$site_id]
    );
    $ecarts = []; $bobines_detail = []; $nb_ecarts = 0;
    foreach ($pj_entries as $b) {
        $films_utilises   = (int)$b['films_utilises'];
        $films_endommages = (int)($b['films_endommages'] ?? 0);
        $films_total_pj   = $films_utilises + $films_endommages;
        $stock_debut      = isset($b['stock_avant']) ? (int)$b['stock_avant'] : (int)$b['stock_systeme'] + $films_utilises;
        $films_restants   = $stock_debut - $films_total_pj;
        $films_optoplate  = $dernier_import
            ? (int)db_fetch_value("SELECT COUNT(*) FROM import_optoplate WHERE num_bobine=? AND statut_plaque='in_use' AND date_import=?", [$b['numero'], $dernier_import])
            : null;
        $ecart_val = $films_optoplate !== null ? ($films_optoplate - $films_utilises) : 0;
        $has_ecart = $films_optoplate !== null && $ecart_val !== 0;
        $ligne = [
            'bobine_id' => $b['bobine_id'], 'numero' => $b['numero'],
            'type_code' => $b['type_code'] ?? '', 'format' => $b['format'] ?? '',
            'stock_debut' => $stock_debut, 'films_utilises' => $films_utilises,
            'films_endommages' => $films_endommages, 'films_restants' => $films_restants,
            'films_optoplate' => $films_optoplate, 'stock_systeme' => (int)($b['stock_systeme'] ?? 0),
            'ecart' => $ecart_val, 'has_ecart' => $has_ecart,
        ];
        $bobines_detail[] = $ligne;
        if ($has_ecart) { $nb_ecarts++; $ecarts[] = $ligne; }
    }
    return compact('nb_ecarts','ecarts','bobines_detail','dernier_import');
}

// ── Auto-traitement batch : valider silencieusement tous les sites sans écart
$sites_non_valides = [];
if ($can_valider) {
    $sites_non_valides = db_fetch_all(
        "SELECT s.id, s.nom, COUNT(b.id) AS nb_bobines
         FROM sites s
         LEFT JOIN op_bobines b ON b.site_id=s.id AND b.statut IN ('en_cours','en_stock')
         WHERE s.actif=1
           AND s.id NOT IN (SELECT site_id FROM validations_stock_matin WHERE date_validation=?)
         GROUP BY s.id HAVING nb_bobines > 0
         ORDER BY s.nom",
        [$f_date]
    );
    // Pour chaque site non encore validé, calculer les écarts et auto-valider si 0 écart
    foreach ($sites_non_valides as &$s) {
        $result = _calculer_ecarts_site((int)$s['id'], $f_date);
        $s['nb_ecarts']     = $result['nb_ecarts'];
        $s['ecarts']        = $result['ecarts'];
        $s['bobines_detail']= $result['bobines_detail'];
        $s['dernier_import']= $result['dernier_import'];
        if ($result['nb_ecarts'] === 0 && !empty($result['bobines_detail'])) {
            // Auto-valider avec snapshot
            $snapshot = json_encode($result['bobines_detail']);
            db_query(
                "INSERT INTO validations_stock_matin (site_id,date_validation,statut,nb_ecarts,bobines_snapshot,gsb_user_id,gsb_at)
                 VALUES (?,?,'valide_auto',0,?,?,NOW())
                 ON DUPLICATE KEY UPDATE statut='valide_auto',nb_ecarts=0,bobines_snapshot=VALUES(bobines_snapshot),gsb_user_id=VALUES(gsb_user_id),gsb_at=NOW()",
                [(int)$s['id'], $f_date, $snapshot, $user['id']]
            );
            $coords = db_fetch_all("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug='coordinateur_site' AND u.site_id=? AND u.actif=1", [(int)$s['id']]);
            foreach ($coords as $c) {
                db_query("INSERT INTO notifications (user_id,type,titre,message,lien) VALUES (?,?,?,?,?)",
                    [$c['id'],'stock_valide','✅ Stock validé',"Votre stock bobines du $f_date est validé. Vous pouvez commencer votre activité.",'/pages/validation_stock_matin.php']);
            }
            $s['auto_valide'] = true;
        } else {
            $s['auto_valide'] = false;
        }
    }
    unset($s);
    // Séparer : sites avec écarts (action GSB) vs sites auto-validés maintenant
    $sites_avec_ecarts  = array_values(array_filter($sites_non_valides, fn($s) => !$s['auto_valide'] && $s['nb_ecarts'] > 0));
    $sites_sans_point   = array_values(array_filter($sites_non_valides, fn($s) => !$s['auto_valide'] && $s['nb_ecarts'] === 0 && empty($s['bobines_detail'])));
    $sites_non_valides  = $sites_avec_ecarts; // compatibilité JS
}

// Vue d'ensemble des validations du jour
$coord_where = $is_coord && $site_force ? "AND v.site_id=$site_force" : '';
$validations_jour = db_fetch_all(
    "SELECT v.*, s.nom AS site_nom,
            CONCAT(u.prenom,' ',u.nom) AS gsb_nom,
            (SELECT COUNT(*) FROM op_bobines b WHERE b.site_id=v.site_id AND b.statut IN ('en_cours','en_stock')) AS nb_bobines_actives
     FROM validations_stock_matin v
     JOIN sites s ON s.id=v.site_id
     LEFT JOIN users u ON u.id=v.gsb_user_id
     WHERE v.date_validation=? $coord_where
     ORDER BY v.statut='refuse' DESC, v.created_at DESC",
    [$f_date]
);

// ── Rapport journalier GSB : sites auto-validés + réajustés
$rapport_journalier = [];
if ($can_valider) {
    $rapport_journalier = db_fetch_all(
        "SELECT v.*, s.nom AS site_nom,
                CONCAT(u.prenom,' ',u.nom) AS gsb_nom,
                (SELECT COUNT(*) FROM op_bobines b WHERE b.site_id=v.site_id AND b.statut IN ('en_cours','en_stock')) AS nb_bobines
         FROM validations_stock_matin v
         JOIN sites s ON s.id=v.site_id
         LEFT JOIN users u ON u.id=v.gsb_user_id
         WHERE v.date_validation=? AND v.statut IN ('valide_auto','reajuste','autorise_ecart')
         ORDER BY v.statut='reajuste' DESC, v.statut='autorise_ecart' DESC, s.nom ASC",
        [$f_date]
    );
}

// ── Export Excel rapport journalier
if (isset($_GET['export_rapport']) && $can_valider) {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        $coord = fn(int $col, int $row): string =>
            \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;

        $sp = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $sp->getActiveSheet()->setTitle('Validation Stock');
        $sh->mergeCells('A1:G1');
        $sh->setCellValue('A1', 'RAPPORT VALIDATION STOCK JOURNALIER — ' . date('d/m/Y', strtotime($f_date)));
        $sh->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sh->getStyle('A1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF06033A');
        $sh->getStyle('A1')->getFont()->getColor()->setARGB('FFFFFFFF');

        $row = 3;
        $headers = ['Site', 'Statut', 'Nb bobines actives', 'Nb écarts', 'Validé/Traité par', 'Heure', 'Commentaire'];
        foreach ($headers as $i => $h) {
            $cell = $coord($i + 1, $row);
            $sh->setCellValue($cell, $h);
            $sh->getStyle($cell)->getFont()->setBold(true);
            $sh->getStyle($cell)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF1B75BC');
            $sh->getStyle($cell)->getFont()->getColor()->setARGB('FFFFFFFF');
        }

        $statut_labels = [
            'valide_auto'    => 'Validé automatiquement',
            'reajuste'       => 'Réajusté par GSB',
            'autorise_ecart' => 'Écart autorisé',
        ];
        foreach ($rapport_journalier as $i => $r) {
            $dr = $row + 1 + $i;
            $sh->setCellValue($coord(1,$dr), $r['site_nom']);
            $sh->setCellValue($coord(2,$dr), $statut_labels[$r['statut']] ?? $r['statut']);
            $sh->setCellValue($coord(3,$dr), (int)$r['nb_bobines']);
            $sh->setCellValue($coord(4,$dr), (int)$r['nb_ecarts']);
            $sh->setCellValue($coord(5,$dr), $r['gsb_nom'] ?: 'Auto');
            $sh->setCellValue($coord(6,$dr), $r['gsb_at'] ? date('H:i', strtotime($r['gsb_at'])) : '—');
            $sh->setCellValue($coord(7,$dr), $r['commentaire'] ?? '');

            // Couleur selon statut
            $bgColor = match($r['statut']) {
                'valide_auto'    => 'FFD1FAE5',
                'reajuste'       => 'FFDBEAFE',
                'autorise_ecart' => 'FFFEF3C7',
                default          => 'FFFFFFFF',
            };
            $sh->getStyle($coord(1,$dr).':'.$coord(7,$dr))->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB($bgColor);
        }

        foreach (range('A','G') as $col) $sh->getColumnDimension($col)->setAutoSize(true);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="ValidationStock_' . $f_date . '.xlsx"');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sp))->save('php://output');
        exit;
    }
}

// Historique validations (30 derniers jours) — GSB seulement
$historique_validations = [];
if ($can_valider) {
    $historique_validations = db_fetch_all(
        "SELECT v.*, s.nom AS site_nom,
                CONCAT(u.prenom,' ',u.nom) AS gsb_nom
         FROM validations_stock_matin v
         JOIN sites s ON s.id=v.site_id
         LEFT JOIN users u ON u.id=v.gsb_user_id
         WHERE v.date_validation >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         ORDER BY v.date_validation DESC, s.nom",
        []
    );
}
$sites_list_filter = $can_valider ? db_fetch_all("SELECT id,nom FROM sites WHERE actif=1 ORDER BY nom") : [];

// Vue coordinateur : son propre statut de validation pour aujourd'hui
$coord_validation = null;
if ($is_coord && $site_force) {
    $coord_validation = db_fetch_one(
        "SELECT v.*, s.nom AS site_nom, CONCAT(u.prenom,' ',u.nom) AS gsb_nom
         FROM validations_stock_matin v
         JOIN sites s ON s.id=v.site_id
         LEFT JOIN users u ON u.id=v.gsb_user_id
         WHERE v.site_id=? AND v.date_validation=?",
        [$site_force, $f_date]
    );
}

include __DIR__ . '/../templates/header.php';
?>
<?php
$nb_en_attente  = count($sites_non_valides ?? []);
$nb_valides_jour= count($validations_jour);
$nb_avec_ecart  = count(array_filter($validations_jour, fn($v) => (int)$v['nb_ecarts'] > 0)) + $nb_en_attente;
?>
<style>
.vsm-banner{background:linear-gradient(135deg,#06033A 0%,#1B75BC 100%);border-radius:16px;padding:22px 28px;margin-bottom:24px;color:white;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.vsm-banner h2{font-family:'Plus Jakarta Sans',sans-serif;font-size:19px;font-weight:800;margin:0;color:white}
.vsm-banner p{font-size:13px;opacity:.8;margin:4px 0 0;color:white}
.vsm-kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px}
.vsm-kpi{background:white;border-radius:14px;border:1px solid var(--border);padding:20px 24px}
.vsm-kpi-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:36px;font-weight:900;line-height:1}
.vsm-kpi-label{font-size:11px;color:var(--muted);font-weight:700;margin-top:6px;text-transform:uppercase;letter-spacing:.06em}
.vsm-section{background:white;border-radius:14px;border:1px solid var(--border);margin-bottom:24px;overflow:hidden}
.vsm-section-hdr{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:#fafbfd}
.vsm-section-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:14px;font-weight:800;color:var(--navy);display:flex;align-items:center;gap:8px}
.vsm-cnt{background:#e8edf8;color:var(--navy);border-radius:20px;padding:2px 10px;font-size:12px;font-weight:700}
.vsm-tbl{width:100%;border-collapse:collapse}
.vsm-tbl thead th{background:#06033A;color:white;padding:11px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;text-align:left;white-space:nowrap}
.vsm-tbl thead th.tc{text-align:center}
.vsm-tbl tbody td{padding:13px 16px;border-bottom:1px solid #f1f5f9;font-size:13px;color:var(--navy);vertical-align:middle}
.vsm-tbl tbody tr:last-child td{border-bottom:none}
.vsm-tbl tbody tr:hover td{background:#f8faff}
.vsm-tbl tbody td.tc{text-align:center}
.vsm-site-name{font-weight:800;color:var(--navy);font-size:14px}
.vsm-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:20px;font-size:11.5px;font-weight:700}
.vsm-badge.valide_auto,.vsm-badge.valide_gsb{background:#d1fae5;color:#065f46}
.vsm-badge.autorise_ecart{background:#fef3c7;color:#92400e}
.vsm-badge.reajuste{background:#dbeafe;color:#1d4ed8}
.vsm-badge.refuse{background:#fee2e2;color:#991b1b}
.vsm-badge.en_attente{background:#fff3e0;color:#e65100}
.vsm-ecart-chip{background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:8px;font-size:11.5px;font-weight:700;display:inline-block}
.vsm-ok-chip{color:#065f46;font-weight:700;font-size:14px}
.vsm-actions{display:flex;gap:6px;align-items:center;justify-content:center;flex-wrap:nowrap}
.btn-traiter{background:#DC2626;color:white;border:none;padding:7px 14px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px;white-space:nowrap;transition:background .15s}
.btn-traiter:hover{background:#b91c1c}
.btn-vsm-detail{background:white;color:var(--navy);border:1.5px solid var(--border);padding:6px 12px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:5px;white-space:nowrap;transition:all .15s}
.btn-vsm-detail:hover{background:#f0f4ff;border-color:#1B75BC;color:#1B75BC}
.btn-vsm-revise{background:#1B75BC;color:white;border:none;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:5px;white-space:nowrap}
.btn-vsm-revise:hover{background:#1565a8}
.detail-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid var(--border);background:white;color:var(--navy);transition:all .15s;text-decoration:none}
.detail-btn:hover{background:var(--tertiary);border-color:var(--primary);color:var(--primary)}
/* ── ONGLETS ── */
.vsm-tabs{display:flex;gap:0;border-bottom:2px solid var(--border);margin-bottom:20px}
.vsm-tab{padding:10px 22px;font-size:13px;font-weight:700;color:var(--muted);cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;display:flex;align-items:center;gap:7px;transition:color .15s,border-color .15s;background:none;border-top:none;border-left:none;border-right:none}
.vsm-tab:hover{color:var(--navy)}
.vsm-tab.active{color:#1B75BC;border-bottom-color:#1B75BC}
.vsm-tab-badge{background:#e8edf8;color:var(--navy);border-radius:20px;padding:1px 8px;font-size:11px;font-weight:700}
.vsm-tab.active .vsm-tab-badge{background:#dbeafe;color:#1d4ed8}
/* ── BANDEAU INFO ── */
.vsm-info-banner{background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:12px;padding:12px 18px;margin-bottom:18px;display:flex;align-items:center;gap:12px;font-size:13px;color:#1d4ed8}
/* ── FILTRES ── */
.vsm-filters{background:white;border:1px solid var(--border);border-radius:12px;padding:14px 18px;margin-bottom:18px;display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.vsm-filters input,.vsm-filters select{padding:7px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;outline:none;color:var(--navy);background:white;transition:border-color .15s}
.vsm-filters input:focus,.vsm-filters select:focus{border-color:#1B75BC}
.vsm-filters label{font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;margin-right:4px}
/* ── LÉGENDE ── */
.vsm-legend{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:18px}
.vsm-legend-title{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}
.vsm-legend-chip{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:11.5px;font-weight:700}
</style>

<!-- BANNIÈRE -->
<div class="vsm-banner">
  <div>
    <h2>☀️ Validation Stock Jour</h2>
    <p><?= $is_coord ? 'Statut du stock bobines de votre site pour la journée' : 'Vérification et validation des stocks bobines avant démarrage' ?></p>
  </div>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <input type="date" id="fDate" value="<?= h($f_date) ?>" onchange="location.href='?date='+this.value"
           style="padding:8px 14px;border:1.5px solid rgba(255,255,255,.35);background:rgba(255,255,255,.15);border-radius:10px;font-size:13px;outline:none;color:white;cursor:pointer">
    <?php if($can_valider): ?>
    <button class="btn btn-secondary" onclick="verifierTousSites()"
            style="background:rgba(255,255,255,.15);border-color:rgba(255,255,255,.35);color:white">
      🔄 Vérifier tous les sites
    </button>
    <?php endif; ?>
  </div>
</div>

<?php if($is_coord): ?>
<!-- ── VUE COORDINATEUR ── -->
<?php
$statut_colors = [
  'valide_auto'   => ['bg'=>'#D1FAE5','border'=>'#34D399','icon'=>'✅','label'=>'Validé automatiquement',    'color'=>'#065F46'],
  'valide_gsb'    => ['bg'=>'#D1FAE5','border'=>'#34D399','icon'=>'✅','label'=>'Validé par le gestionnaire','color'=>'#065F46'],
  'autorise_ecart'=> ['bg'=>'#FEF3C7','border'=>'#F59E0B','icon'=>'⚠️','label'=>'Écart autorisé — vous pouvez travailler','color'=>'#92400E'],
  'reajuste'      => ['bg'=>'#DBEAFE','border'=>'#3B82F6','icon'=>'🔄','label'=>'Stock réajusté',            'color'=>'#1D4ED8'],
  'refuse'        => ['bg'=>'#FEE2E2','border'=>'#F87171','icon'=>'❌','label'=>'Activité bloquée',          'color'=>'#991B1B'],
];
?>
<?php if($coord_validation): ?>
  <?php $sv = $statut_colors[$coord_validation['statut']] ?? ['bg'=>'#F1F5F9','border'=>'#CBD5E1','icon'=>'❓','label'=>$coord_validation['statut'],'color'=>'#64748B']; ?>
  <div style="background:<?= $sv['bg'] ?>;border:2px solid <?= $sv['border'] ?>;border-radius:18px;padding:24px 28px;margin-bottom:20px">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
      <div style="font-size:36px"><?= $sv['icon'] ?></div>
      <div>
        <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;color:<?= $sv['color'] ?>"><?= $sv['label'] ?></div>
        <div style="font-size:12.5px;color:<?= $sv['color'] ?>;opacity:.8;margin-top:3px">
          Validé le <?= fmt_date($f_date,'d/m/Y') ?>
          <?= $coord_validation['gsb_nom'] ? ' par '.$coord_validation['gsb_nom'] : ' automatiquement' ?>
        </div>
      </div>
    </div>
    <?php if($coord_validation['commentaire']): ?>
    <div style="background:rgba(0,0,0,.06);border-radius:10px;padding:10px 14px;font-size:13px;color:<?= $sv['color'] ?>;margin-bottom:14px">
      💬 <?= h($coord_validation['commentaire']) ?>
    </div>
    <?php endif; ?>
    <div style="display:flex;gap:10px">
      <button class="detail-btn"
        onclick="voirDetails(<?= $coord_validation['site_id'] ?>,'<?= h($coord_validation['site_nom']) ?>',<?= (int)$coord_validation['nb_ecarts'] ?>,<?= htmlspecialchars(json_encode($coord_validation['details_ecarts']??'[]'),ENT_QUOTES) ?>,'<?= h($coord_validation['statut']) ?>','<?= h($coord_validation['commentaire']??'') ?>','<?= h($coord_validation['gsb_nom']??'Auto') ?>','<?= h(fmt_datetime($coord_validation['gsb_at'])) ?>','<?= h($coord_validation['date_validation']) ?>')">
        <i class="ph-duotone ph-eye"></i> Voir le détail des bobines
      </button>
    </div>
  </div>

<?php else: ?>
  <!-- Pas encore validé ce jour -->
  <div style="background:#FEF3C7;border:2px solid #F59E0B;border-radius:18px;padding:28px;text-align:center;margin-bottom:20px">
    <div style="font-size:40px;margin-bottom:12px">⏳</div>
    <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:800;color:#92400E">Stock en attente de validation</div>
    <div style="font-size:13px;color:#92400E;margin-top:6px">
      Le gestionnaire stock n'a pas encore validé votre stock pour le <?= fmt_date($f_date,'d/m/Y') ?>.
    </div>
    <div style="font-size:12px;color:#B45309;margin-top:10px">
      Vous serez notifié dès que la validation est effectuée.
    </div>
  </div>
<?php endif; ?>

<?php else: ?>
<!-- ── VUE GSB / ADMIN ── -->

<!-- BANDEAU INFO -->
<div class="vsm-info-banner">
  <i class="ph-duotone ph-info" style="font-size:20px;flex-shrink:0"></i>
  <div>
    <strong>Comment ça marche ?</strong>
    Vérifiez chaque site, comparez les stocks avec l'import EMUCI, puis validez ou traitez les écarts.
    Les coordinateurs sont notifiés automatiquement après chaque décision.
  </div>
</div>

<!-- ONGLETS -->
<div class="vsm-tabs">
  <button class="vsm-tab active" id="tab-btn-encours" onclick="switchTab('encours')">
    <i class="ph-duotone ph-clock"></i> Validation en cours
    <span class="vsm-tab-badge"><?= $nb_en_attente + $nb_valides_jour ?></span>
  </button>
  <button class="vsm-tab" id="tab-btn-historique" onclick="switchTab('historique')">
    <i class="ph-duotone ph-clock-counter-clockwise"></i> Historique des validations
    <span class="vsm-tab-badge"><?= count($historique_validations) ?></span>
  </button>
</div>

<!-- TAB : VALIDATION EN COURS -->
<div id="tab-encours">

<!-- LÉGENDE -->
<div class="vsm-legend">
  <span class="vsm-legend-title">Légende :</span>
  <span class="vsm-legend-chip" style="background:#d1fae5;color:#065f46">✅ Conforme</span>
  <span class="vsm-legend-chip" style="background:#fef3c7;color:#92400e">⚠️ Avec écart</span>
  <span class="vsm-legend-chip" style="background:#dbeafe;color:#1d4ed8">🔄 Réajusté</span>
  <span class="vsm-legend-chip" style="background:#fee2e2;color:#991b1b">❌ Bloqué</span>
  <span class="vsm-legend-chip" style="background:#fff3e0;color:#e65100">⏳ En attente</span>
</div>

<!-- KPIs -->
<?php if($can_valider): ?>
<div class="vsm-kpis">
  <div class="vsm-kpi">
    <div class="vsm-kpi-val" style="color:#e65100"><?= $nb_en_attente ?></div>
    <div class="vsm-kpi-label">En attente</div>
  </div>
  <div class="vsm-kpi">
    <div class="vsm-kpi-val" style="color:#065f46"><?= $nb_valides_jour ?></div>
    <div class="vsm-kpi-label">Validés aujourd'hui</div>
  </div>
  <div class="vsm-kpi">
    <div class="vsm-kpi-val" style="color:<?= $nb_avec_ecart > 0 ? '#991b1b' : '#065f46' ?>"><?= $nb_avec_ecart ?></div>
    <div class="vsm-kpi-label">Avec écart</div>
  </div>
</div>
<?php endif; ?>

<!-- Sites sans point journalier -->
<?php if(!empty($sites_sans_point) && $can_valider): ?>
<div style="background:#F0F9FF;border:1.5px solid #BAE6FD;border-radius:12px;padding:13px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
  <i class="ph-duotone ph-info" style="color:#0369A1;font-size:20px;flex-shrink:0"></i>
  <span style="font-weight:700;color:#0369A1;font-size:13px"><?= count($sites_sans_point) ?> site(s) sans point journalier</span>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <?php foreach($sites_sans_point as $s): ?>
    <span style="background:#E0F2FE;color:#0369A1;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600"><?= h($s['nom']) ?></span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- TABLE 1 : Sites en attente de validation -->
<div class="vsm-section">
  <div class="vsm-section-hdr">
    <div class="vsm-section-title">
      <i class="ph-duotone ph-warning-circle" style="color:#e65100;font-size:17px"></i>
      Sites en attente de validation
      <span class="vsm-cnt"><?= $nb_en_attente ?></span>
    </div>
  </div>
  <?php if(empty($sites_non_valides)): ?>
  <div style="padding:36px;text-align:center;color:var(--muted)">
    <i class="ph-duotone ph-check-circle" style="font-size:36px;color:#34d399;display:block;margin-bottom:10px"></i>
    Tous les sites ont été traités pour le <?= fmt_date($f_date,'d/m/Y') ?>.
  </div>
  <?php else: ?>
  <table class="vsm-tbl">
    <thead><tr>
      <th>Site</th>
      <th class="tc">Bobines actives</th>
      <th class="tc">Écarts</th>
      <th class="tc">Statut</th>
      <th class="tc">Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach($sites_non_valides as $s): ?>
    <tr>
      <td><div class="vsm-site-name"><?= h($s['nom']) ?></div></td>
      <td class="tc" style="font-weight:700"><?= $s['nb_bobines'] ?></td>
      <td class="tc"><span class="vsm-ecart-chip">⚠️ <?= $s['nb_ecarts'] ?> écart(s)</span></td>
      <td class="tc"><span class="vsm-badge en_attente">En attente</span></td>
      <td class="tc">
        <div class="vsm-actions">
          <button class="btn-traiter" data-site-id="<?= $s['id'] ?>"
                  onclick="verifierSite(<?= $s['id'] ?>,'<?= h($s['nom']) ?>')">
            <i class="ph-duotone ph-magnifying-glass"></i> Traiter
          </button>
          <button class="btn-vsm-detail"
                  onclick="voirDetails(<?= $s['id'] ?>,'<?= h($s['nom']) ?>',<?= $s['nb_ecarts'] ?>,'[]','en_attente','','','','<?= h($f_date) ?>')">
            <i class="ph-duotone ph-eye"></i> Détails
          </button>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- TABLE 2 : Sites validés du jour -->
<div class="vsm-section">
  <div class="vsm-section-hdr">
    <div class="vsm-section-title">
      <i class="ph-duotone ph-check-circle" style="color:#065f46;font-size:17px"></i>
      Sites validés du jour
      <span class="vsm-cnt"><?= $nb_valides_jour ?></span>
    </div>
    <?php if($can_valider && !empty($rapport_journalier)): ?>
    <a href="?date=<?= h($f_date) ?>&export_rapport=1" class="btn btn-secondary btn-sm">
      <i class="ph-duotone ph-file-xls"></i> Export Excel
    </a>
    <?php endif; ?>
  </div>
  <?php if(empty($validations_jour)): ?>
  <div style="padding:36px;text-align:center;color:var(--muted)">
    Aucune validation enregistrée pour le <?= fmt_date($f_date,'d/m/Y') ?>.
  </div>
  <?php else: ?>
  <table class="vsm-tbl">
    <thead><tr>
      <th>Site</th>
      <th class="tc">Date validation</th>
      <th class="tc">Statut</th>
      <th class="tc">Bobines</th>
      <th>Traité par</th>
      <th>Commentaire</th>
      <th class="tc">Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach($validations_jour as $v):
      // Colonne "Statut" : ce qui s'est passé sur les écarts
      if ($v['statut'] === 'refuse') {
        $statut_badge = '<span class="vsm-badge refuse">❌ Bloqué</span>';
      } elseif ($v['statut'] === 'reajuste') {
        $statut_badge = '<span class="vsm-badge reajuste">🔄 Réajusté</span>';
      } elseif ($v['statut'] === 'autorise_ecart') {
        $statut_badge = '<span class="vsm-badge autorise_ecart">⚠️ Avec écart</span>';
      } elseif ((int)$v['nb_ecarts'] > 0) {
        $statut_badge = '<span class="vsm-badge autorise_ecart">⚠️ Avec écart</span>';
      } else {
        $statut_badge = '<span class="vsm-badge valide_auto">✅ Conforme</span>';
      }
      // Colonne "Date validation" : badge Validée + date
      $date_label = in_array($v['statut'], ['refuse']) ? '❌ Bloquée' : '✅ Validée';
      $date_col_bg = $v['statut'] === 'refuse' ? '#fee2e2' : '#d1fae5';
      $date_col_color = $v['statut'] === 'refuse' ? '#991b1b' : '#065f46';
    ?>
    <tr>
      <td><div class="vsm-site-name"><?= h($v['site_nom']) ?></div></td>
      <td class="tc">
        <div style="display:inline-flex;flex-direction:column;align-items:center;gap:3px">
          <span style="background:<?= $date_col_bg ?>;color:<?= $date_col_color ?>;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700"><?= $date_label ?></span>
          <span style="font-size:12px;color:var(--muted);font-weight:600"><?= fmt_date($v['date_validation'],'d/m/Y') ?></span>
          <?php if($v['gsb_at']): ?>
          <span style="font-size:11px;color:var(--muted)">à <?= date('H:i', strtotime($v['gsb_at'])) ?></span>
          <?php endif; ?>
        </div>
      </td>
      <td class="tc"><?= $statut_badge ?></td>
      <td class="tc" style="font-weight:700"><?= $v['nb_bobines_actives'] ?></td>
      <td style="font-size:12.5px"><?= $v['gsb_nom'] ? h($v['gsb_nom']) : '<span style="color:var(--muted)">Automatique</span>' ?></td>
      <td style="font-size:12px;color:var(--muted);max-width:180px"><?= $v['commentaire'] ? h($v['commentaire']) : '<span style="color:var(--border)">—</span>' ?></td>
      <td class="tc">
        <div class="vsm-actions">
          <button class="btn-vsm-detail"
            onclick="voirDetails(<?= $v['site_id'] ?>,'<?= h($v['site_nom']) ?>',<?= (int)$v['nb_ecarts'] ?>,<?= htmlspecialchars(json_encode($v['details_ecarts'] ?? '[]'), ENT_QUOTES) ?>,'<?= h($v['statut']) ?>','<?= h($v['commentaire']??'') ?>','<?= h($v['gsb_nom']??'Auto') ?>','<?= h(fmt_datetime($v['gsb_at'])) ?>','<?= h($v['date_validation']) ?>')">
            <i class="ph-duotone ph-eye"></i> Détails
          </button>
          <?php if($can_valider && in_array($v['statut'],['autorise_ecart','reajuste','refuse'])): ?>
          <button class="btn-vsm-revise" onclick="verifierSite(<?= $v['site_id'] ?>,'<?= h($v['site_nom']) ?>')">
            <i class="ph-duotone ph-arrow-counter-clockwise"></i> Réviser
          </button>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

</div><!-- fin tab-encours -->

<!-- TAB : HISTORIQUE -->
<div id="tab-historique" style="display:none">

<!-- FILTRES HISTORIQUE -->
<div class="vsm-filters">
  <div style="display:flex;align-items:center;gap:6px;flex:1;min-width:200px">
    <label>Recherche</label>
    <input type="text" id="hist-search" placeholder="Nom du site…" oninput="filtrerHistorique()" style="flex:1">
  </div>
  <div style="display:flex;align-items:center;gap:6px">
    <label>Du</label>
    <input type="date" id="hist-date-from" value="<?= date('Y-m-d', strtotime('-30 days')) ?>" onchange="filtrerHistorique()">
  </div>
  <div style="display:flex;align-items:center;gap:6px">
    <label>Au</label>
    <input type="date" id="hist-date-to" value="<?= date('Y-m-d') ?>" onchange="filtrerHistorique()">
  </div>
  <div style="display:flex;align-items:center;gap:6px">
    <label>Site</label>
    <select id="hist-site" onchange="filtrerHistorique()">
      <option value="">Tous</option>
      <?php foreach($sites_list_filter as $sl): ?>
      <option value="<?= h($sl['nom']) ?>"><?= h($sl['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div style="display:flex;align-items:center;gap:6px">
    <label>Statut</label>
    <select id="hist-statut" onchange="filtrerHistorique()">
      <option value="">Tous</option>
      <option value="conforme">✅ Conforme</option>
      <option value="avec_ecart">⚠️ Avec écart</option>
      <option value="reajuste">🔄 Réajusté</option>
      <option value="refuse">❌ Bloqué</option>
    </select>
  </div>
  <button onclick="resetFiltresHistorique()" style="padding:7px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;background:white;color:var(--muted)">↺ Réinitialiser</button>
</div>

<!-- LÉGENDE -->
<div class="vsm-legend">
  <span class="vsm-legend-title">Légende :</span>
  <span class="vsm-legend-chip" style="background:#d1fae5;color:#065f46">✅ Conforme</span>
  <span class="vsm-legend-chip" style="background:#fef3c7;color:#92400e">⚠️ Avec écart</span>
  <span class="vsm-legend-chip" style="background:#dbeafe;color:#1d4ed8">🔄 Réajusté</span>
  <span class="vsm-legend-chip" style="background:#fee2e2;color:#991b1b">❌ Bloqué</span>
</div>

<div class="vsm-section">
  <div class="vsm-section-hdr">
    <div class="vsm-section-title">
      <i class="ph-duotone ph-clock-counter-clockwise" style="font-size:17px"></i>
      Historique des 30 derniers jours
      <span class="vsm-cnt" id="hist-count"><?= count($historique_validations) ?></span>
    </div>
    <span style="font-size:12px;color:var(--muted)" id="hist-filter-info"></span>
  </div>
  <table class="vsm-tbl" id="hist-table">
    <thead><tr>
      <th>Site</th>
      <th class="tc">Date validation</th>
      <th class="tc">Statut</th>
      <th class="tc">Bobines</th>
      <th>Traité par</th>
      <th>Commentaire</th>
      <th class="tc">Détails</th>
    </tr></thead>
    <tbody id="hist-tbody">
    <?php foreach($historique_validations as $v):
      if ($v['statut'] === 'refuse') {
        $hbadge = '<span class="vsm-legend-chip" style="background:#fee2e2;color:#991b1b">❌ Bloqué</span>';
        $hcat   = 'refuse';
      } elseif ($v['statut'] === 'reajuste') {
        $hbadge = '<span class="vsm-legend-chip" style="background:#dbeafe;color:#1d4ed8">🔄 Réajusté</span>';
        $hcat   = 'reajuste';
      } elseif ($v['statut'] === 'autorise_ecart' || (int)$v['nb_ecarts'] > 0) {
        $hbadge = '<span class="vsm-legend-chip" style="background:#fef3c7;color:#92400e">⚠️ Avec écart</span>';
        $hcat   = 'avec_ecart';
      } else {
        $hbadge = '<span class="vsm-legend-chip" style="background:#d1fae5;color:#065f46">✅ Conforme</span>';
        $hcat   = 'conforme';
      }
    ?>
    <tr data-site="<?= h($v['site_nom']) ?>" data-date="<?= h($v['date_validation']) ?>" data-cat="<?= $hcat ?>">
      <td><div class="vsm-site-name"><?= h($v['site_nom']) ?></div></td>
      <td class="tc">
        <div style="display:inline-flex;flex-direction:column;align-items:center;gap:2px">
          <span style="font-size:12.5px;font-weight:700;color:var(--navy)"><?= fmt_date($v['date_validation'],'d/m/Y') ?></span>
          <?php if($v['gsb_at']): ?>
          <span style="font-size:11px;color:var(--muted)">à <?= date('H:i', strtotime($v['gsb_at'])) ?></span>
          <?php endif; ?>
        </div>
      </td>
      <td class="tc"><?= $hbadge ?></td>
      <td class="tc" style="font-weight:700"><?= $v['nb_bobines_actives'] ?? '—' ?></td>
      <td style="font-size:12.5px"><?= $v['gsb_nom'] ? h($v['gsb_nom']) : '<span style="color:var(--muted)">Automatique</span>' ?></td>
      <td style="font-size:12px;color:var(--muted);max-width:160px"><?= $v['commentaire'] ? h($v['commentaire']) : '<span style="color:var(--border)">—</span>' ?></td>
      <td class="tc">
        <button class="btn-vsm-detail"
          onclick="voirDetails(<?= $v['site_id'] ?>,'<?= h($v['site_nom']) ?>',<?= (int)$v['nb_ecarts'] ?>,<?= htmlspecialchars(json_encode($v['details_ecarts'] ?? '[]'), ENT_QUOTES) ?>,'<?= h($v['statut']) ?>','<?= h($v['commentaire']??'') ?>','<?= h($v['gsb_nom']??'Auto') ?>','<?= h(fmt_datetime($v['gsb_at'])) ?>','<?= h($v['date_validation']) ?>')">
          <i class="ph-duotone ph-eye"></i> Voir
        </button>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($historique_validations)): ?>
    <tr><td colspan="7" style="text-align:center;padding:36px;color:var(--muted)">Aucune validation dans les 30 derniers jours.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

</div><!-- fin tab-historique -->

<?php endif; // fin else GSB/Admin ?>

<!-- MINI-MODAL DEMANDE DE CORRECTION PAR BOBINE -->
<div id="miniModalCorr" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1200;align-items:center;justify-content:center">
  <div style="background:white;border-radius:16px;width:500px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.3);padding:28px">
    <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:var(--navy);margin-bottom:4px">
      ✏️ Demander une correction
    </div>
    <div style="font-size:13px;color:var(--muted);margin-bottom:16px">
      Bobine : <strong id="miniCorrBobineNum" style="color:var(--navy)"></strong>
    </div>

    <!-- Comparatif Films PJ vs EMUCI -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px">
      <div style="background:#f0f4ff;border-radius:10px;padding:12px;text-align:center">
        <div style="font-size:10px;font-weight:700;color:#5b76ff;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Films saisie PJ</div>
        <div id="miniCorrFilmsPj" style="font-size:22px;font-weight:800;color:#1e2b4a">—</div>
      </div>
      <div style="background:#fff3e0;border-radius:10px;padding:12px;text-align:center">
        <div style="font-size:10px;font-weight:700;color:#e65100;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Films EMUCI</div>
        <div id="miniCorrFilmsEmuci" style="font-size:22px;font-weight:800;color:#1e2b4a">—</div>
      </div>
    </div>

    <!-- Films proposés -->
    <div style="margin-bottom:14px">
      <label style="font-size:13px;font-weight:700;color:var(--navy);display:block;margin-bottom:6px">
        Films proposés <span style="color:#e74c3c">*</span>
        <span style="font-size:11px;font-weight:400;color:var(--muted);margin-left:6px">Valeur que vous proposez au coordinateur</span>
      </label>
      <input type="number" id="miniCorrFilmsProposes" min="0" class="form-control"
        style="text-align:center;font-size:20px;font-weight:800;color:var(--navy)">
    </div>

    <label style="font-size:13px;font-weight:700;color:var(--navy);display:block;margin-bottom:6px">
      Motif <span style="color:#e74c3c">*</span>
    </label>
    <textarea id="miniCorrNotes" rows="2" class="form-control"
      placeholder="Expliquez la raison de la correction…"
      style="border-radius:10px;width:100%;box-sizing:border-box;margin-bottom:20px"></textarea>

    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button onclick="document.getElementById('miniModalCorr').style.display='none'"
              class="btn btn-secondary">Annuler</button>
      <button onclick="submitCorrectionBobine()" class="btn btn-primary">
        <i class="ph-duotone ph-paper-plane-tilt"></i> Envoyer au coordinateur
      </button>
    </div>
  </div>
</div>

<!-- MODAL DÉTAILS (lecture seule) -->
<div id="modalDetails" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:flex-start;justify-content:center;padding:30px;overflow-y:auto">
  <div style="background:white;border-radius:20px;width:980px;max-width:96vw;margin:auto;box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <div style="padding:22px 28px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <div>
        <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:800;color:var(--navy)" id="detailTitle">Détails validation</h3>
        <div id="detailMeta" style="font-size:12px;color:var(--muted);margin-top:3px"></div>
      </div>
      <button onclick="document.getElementById('modalDetails').style.display='none'"
              style="background:none;border:none;font-size:22px;cursor:pointer">✕</button>
    </div>
    <div id="detailBody" style="padding:24px 28px;max-height:70vh;overflow-y:auto"></div>
  </div>
</div>

<!-- MODAL VÉRIFICATION -->
<div id="modalVSM" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:flex-start;justify-content:center;padding:30px;overflow-y:auto">
  <div style="background:white;border-radius:20px;width:620px;max-width:95vw;margin:auto;box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <div style="padding:24px 28px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:800;color:var(--navy)" id="vsmTitle">Vérification stock</h3>
      <button onclick="fermerVSM()" style="background:none;border:none;font-size:22px;cursor:pointer">✕</button>
    </div>
    <div id="vsmBody" style="padding:24px 28px">
      <div style="text-align:center;padding:40px;color:var(--muted)">⏳ Chargement...</div>
    </div>
  </div>
</div>

<script>
function ap(d){return fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(d)}).then(r=>r.json());}
function toast(m,t='success'){const bg={success:'#27ae60',error:'#e74c3c',info:'#1B75BC'}[t]||'#27ae60';const el=document.createElement('div');el.style.cssText=`position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:600;background:${bg};color:white;max-width:320px`;el.textContent=m;document.body.appendChild(el);setTimeout(()=>el.remove(),4000);}

// ── ONGLETS
function switchTab(tab) {
  document.getElementById('tab-encours').style.display   = tab==='encours'    ? 'block' : 'none';
  document.getElementById('tab-historique').style.display= tab==='historique' ? 'block' : 'none';
  document.getElementById('tab-btn-encours').classList.toggle('active',    tab==='encours');
  document.getElementById('tab-btn-historique').classList.toggle('active', tab==='historique');
}

// ── FILTRES HISTORIQUE
function filtrerHistorique() {
  const search  = (document.getElementById('hist-search')?.value  || '').toLowerCase();
  const dateFrom= document.getElementById('hist-date-from')?.value || '';
  const dateTo  = document.getElementById('hist-date-to')?.value   || '';
  const site    = (document.getElementById('hist-site')?.value     || '').toLowerCase();
  const statut  = document.getElementById('hist-statut')?.value    || '';

  const rows = document.querySelectorAll('#hist-tbody tr[data-site]');
  let visible = 0;
  rows.forEach(row => {
    const rowSite = (row.dataset.site || '').toLowerCase();
    const rowDate = row.dataset.date || '';
    const rowCat  = row.dataset.cat  || '';
    const show = (!search  || rowSite.includes(search))
              && (!dateFrom|| rowDate >= dateFrom)
              && (!dateTo  || rowDate <= dateTo)
              && (!site    || rowSite.includes(site))
              && (!statut  || rowCat === statut);
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  document.getElementById('hist-count').textContent = visible;
  const info = document.getElementById('hist-filter-info');
  if (info) info.textContent = visible < rows.length ? `${visible} / ${rows.length} résultats` : '';
}
function resetFiltresHistorique() {
  document.getElementById('hist-search').value    = '';
  document.getElementById('hist-date-from').value = '<?= date('Y-m-d', strtotime('-30 days')) ?>';
  document.getElementById('hist-date-to').value   = '<?= date('Y-m-d') ?>';
  document.getElementById('hist-site').value      = '';
  document.getElementById('hist-statut').value    = '';
  filtrerHistorique();
}

let currentSiteId=null, currentEcarts=[], currentBobinesDetail=[];

async function verifierSite(siteId, siteNom) {
  currentSiteId = siteId;
  const canValider = <?= $can_valider ? 'true' : 'false' ?>;

  // Spinner discret (pas de modal encore — on attend le résultat)
  toast(`⏳ Vérification ${siteNom}…`, 'info');

  let d;
  try {
    const r = await ap({action:'calculer_ecarts', site_id:siteId, date:'<?= h($f_date) ?>'});
    if (!r.success) { toast(`❌ ${r.message}`, 'error'); return; }
    d = r.data;
  } catch(err) {
    toast('❌ Erreur réseau. Réessayez.', 'error'); return;
  }

  // ── 0 écart + GSB non encore validé → validation automatique silencieuse
  if (canValider && !d.validation && d.nb_ecarts === 0) {
    const v = await ap({action:'valider_auto', site_id:siteId, date:'<?= h($f_date) ?>'});
    if (v.success) {
      toast(`✅ ${siteNom} — Stock conforme, validé automatiquement`, 'success');
      // Supprimer la carte du site de la liste "en attente"
      const card = document.querySelector(`[data-site-id="${siteId}"]`);
      if (card) card.remove();
    } else {
      toast(`❌ Erreur validation : ${v.message}`, 'error');
    }
    return;
  }

  // ── Écarts détectés (ou déjà validé) → ouvrir le modal
  currentEcarts       = d.ecarts || [];
  currentBobinesDetail= d.bobines_detail || [];
  const bobines       = currentBobinesDetail;
  document.getElementById('vsmTitle').textContent = `🔍 ${siteNom}`;
  document.getElementById('modalVSM').style.display = 'flex';

  // ── KPIs
  let html = `
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:18px">
      <div style="text-align:center;padding:12px;background:#D1FAE5;border-radius:10px">
        <div style="font-size:24px;font-weight:900;color:#065F46">${d.nb_bobines}</div>
        <div style="font-size:11px;color:#065F46;font-weight:700;text-transform:uppercase">Bobines vérifiées</div>
      </div>
      <div style="text-align:center;padding:12px;background:${d.nb_ecarts>0?'#FEE2E2':'#D1FAE5'};border-radius:10px">
        <div style="font-size:24px;font-weight:900;color:${d.nb_ecarts>0?'#991B1B':'#065F46'}">${d.nb_ecarts}</div>
        <div style="font-size:11px;color:${d.nb_ecarts>0?'#991B1B':'#065F46'};font-weight:700;text-transform:uppercase">Écarts</div>
      </div>
      <div style="text-align:center;padding:12px;background:#F0F4FF;border-radius:10px">
        <div style="font-size:12.5px;font-weight:700;color:var(--navy)">${d.dernier_import ? (() => { const dt=new Date(d.dernier_import.replace(' ','T')); return dt.toLocaleDateString('fr-FR',{day:'2-digit',month:'2-digit',year:'numeric'})+' à '+dt.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'}); })() : '—'}</div>
        <div style="font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase">Dernier import EMUCI</div>
      </div>
    </div>`;

  // ── Tableau toutes bobines
  html += `
    <div style="border:1.5px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:16px">
      <table style="width:100%;border-collapse:collapse">
        <thead><tr style="background:#06033A">
          <th style="padding:9px 12px;color:white;font-size:11px;text-align:left">N° Bobine</th>
          <th style="padding:9px 12px;color:white;font-size:11px;text-align:left">Type</th>
          <th style="padding:9px 12px;color:white;font-size:11px;text-align:center">PJ Coord.</th>
          ${d.dernier_import?`<th style="padding:9px 12px;color:white;font-size:11px;text-align:center">EMUCI</th>`:''}
          <th style="padding:9px 12px;color:white;font-size:11px;text-align:center">Stock sys.</th>
          <th style="padding:9px 12px;color:white;font-size:11px;text-align:center">Statut</th>
        </tr></thead>
        <tbody>`;

  if (bobines.length === 0) {
    html += `<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--muted)">Aucune bobine active sur ce site</td></tr>`;
  } else {
    bobines.forEach((b, i) => {
      const bg = b.has_ecart ? '#FFF7ED' : (i%2===0 ? 'white' : '#F8FAFC');
      const ecartColor = b.ecart > 0 ? '#DC2626' : '#D97706';
      html += `<tr style="background:${bg}">
        <td style="padding:9px 12px;font-family:monospace;font-weight:800;color:#06033A">${b.numero}</td>
        <td style="padding:9px 12px;font-size:12px;color:var(--muted)">${b.type_code||b.format||'—'}</td>
        <td style="padding:9px 12px;text-align:center;font-weight:600">${b.films_utilises??'—'}</td>
        ${d.dernier_import ? `<td style="padding:9px 12px;text-align:center;font-weight:600">${b.films_optoplate!==null?b.films_optoplate:'—'}</td>` : ''}
        <td style="padding:9px 12px;text-align:center;font-size:12px;color:var(--muted)">${b.stock_systeme}</td>
        <td style="padding:9px 12px;text-align:center">
          ${b.has_ecart
            ? `<span style="background:#FEE2E2;color:#991B1B;padding:3px 9px;border-radius:8px;font-size:11px;font-weight:700">
                ⚠️ Écart ${b.ecart>0?'+':''}${b.ecart}
               </span>`
            : `<span style="background:#D1FAE5;color:#065F46;padding:3px 9px;border-radius:8px;font-size:11px;font-weight:700">✅ OK</span>`}
        </td>
      </tr>`;
    });
  }
  html += `</tbody></table></div>`;

  // ── Zone de décision (si GSB et écarts détectés — le cas 0 écart est auto-validé avant l'ouverture du modal)
  if (canValider && !d.validation && d.nb_ecarts > 0) {
    {
      html += `
        <div style="background:#FEF3C7;border-left:4px solid #F59E0B;padding:12px 16px;border-radius:8px;margin-bottom:14px;font-size:13px;color:#92400E">
          <strong>⚠️ ${d.nb_ecarts} écart(s) détecté(s).</strong> Saisissez un commentaire et choisissez votre décision.
        </div>
        <div class="form-group" style="margin-bottom:14px">
          <label style="font-size:13px;font-weight:700;color:var(--navy);display:block;margin-bottom:6px">
            Commentaire / Motif <span style="color:var(--danger)">*</span>
          </label>
          <textarea class="form-control" id="vsmCommentaire" rows="2"
            placeholder="Expliquez la cause de l'écart ou la décision prise…"
            style="border-radius:10px"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
          <button class="btn btn-success" onclick="decisionGSB('autorise_ecart')" style="font-size:12.5px">
            ⚠️ Autoriser l'écart
          </button>
          <button class="btn btn-primary" onclick="decisionGSB('reajuste')" style="font-size:12.5px">
            🔄 Réajuster le stock
          </button>
          <button class="btn btn-danger" onclick="decisionGSB('refuse')" style="font-size:12.5px">
            ❌ Refuser / Bloquer
          </button>
        </div>`;
    }
  } else if (d.validation) {
    // Déjà validé — afficher le récapitulatif
    const vStatuts = {
      valide_auto:'✅ Validé automatiquement', valide_gsb:'✅ Validé par GSB',
      autorise_ecart:'⚠️ Écart autorisé', reajuste:'🔄 Stock réajusté', refuse:'❌ Bloqué'
    };
    html += `
      <div style="background:#F0F9FF;border-radius:10px;padding:14px 18px;font-size:13px">
        <strong>${vStatuts[d.validation.statut]||d.validation.statut}</strong>
        ${d.validation.commentaire ? `<div style="color:var(--muted);margin-top:4px">💬 ${d.validation.commentaire}</div>` : ''}
      </div>`;
    if (canValider) {
      html += `<div style="margin-top:12px;text-align:right">
        <button class="btn btn-secondary btn-sm" onclick="verifierSite(${siteId},'${siteNom}')">🔄 Réviser</button>
      </div>`;
    }
  }

  document.getElementById('vsmBody').innerHTML = html;
}

async function validerAuto(){
  const d=await ap({action:'valider_auto',site_id:currentSiteId,date:'<?= h($f_date) ?>'});
  if(d.success){toast(d.message);fermerVSM();setTimeout(()=>location.reload(),800);}
  else toast(d.message,'error');
}

async function decisionGSB(decision){
  const commentaire=document.getElementById('vsmCommentaire')?.value?.trim();
  if(!commentaire){alert('Le commentaire est obligatoire.');return;}
  const d=await ap({
    action:'decision_gsb',site_id:currentSiteId,date:'<?= h($f_date) ?>',
    decision,commentaire,ecarts_json:JSON.stringify(currentEcarts),
    bobines_json:JSON.stringify(currentBobinesDetail)
  });
  if(d.success){toast(d.message);fermerVSM();setTimeout(()=>location.reload(),900);}
  else toast(d.message,'error');
}

async function verifierTousSites(){
  const sites=[...document.querySelectorAll('[onclick^="verifierSite"]')].map(b=>b.getAttribute('onclick').match(/\d+/)?.[0]);
  if(!sites.length){alert('Tous les sites sont déjà vérifiés.');return;}
  // Auto-vérifier le premier site non validé
  const first=document.querySelector('[onclick^="verifierSite"]');
  if(first) first.click();
}

function fermerVSM(){document.getElementById('modalVSM').style.display='none';}
document.getElementById('modalVSM').addEventListener('click',e=>{if(e.target===document.getElementById('modalVSM'))fermerVSM();});
document.getElementById('modalDetails').addEventListener('click',e=>{if(e.target===document.getElementById('modalDetails'))e.target.style.display='none';});
document.getElementById('miniModalCorr').addEventListener('click',e=>{if(e.target===document.getElementById('miniModalCorr'))e.target.style.display='none';});

function demanderModifBobine(bobineId, bobineNum, siteId, date, filmsPj, filmsEmuci, ecart) {
  const m = document.getElementById('miniModalCorr');
  document.getElementById('miniCorrBobineNum').textContent      = bobineNum;
  document.getElementById('miniCorrNotes').value                = '';
  document.getElementById('miniCorrFilmsPj').textContent        = filmsPj;
  document.getElementById('miniCorrFilmsEmuci').textContent     = filmsEmuci !== null && filmsEmuci !== undefined ? filmsEmuci : '—';
  document.getElementById('miniCorrFilmsProposes').value        = filmsEmuci !== null && filmsEmuci !== undefined ? filmsEmuci : filmsPj;
  m.dataset.bobineId   = bobineId;
  m.dataset.siteId     = siteId;
  m.dataset.date       = date;
  m.dataset.filmsPj    = filmsPj;
  m.dataset.filmsEmuci = filmsEmuci;
  m.dataset.ecart      = ecart;
  m.style.display = 'flex';
}

async function submitCorrectionBobine() {
  const m             = document.getElementById('miniModalCorr');
  const notes         = document.getElementById('miniCorrNotes').value.trim();
  const filmsProposes = document.getElementById('miniCorrFilmsProposes').value.trim();
  if (!notes)         { alert('Le motif est obligatoire.'); return; }
  if (filmsProposes === '' || isNaN(parseInt(filmsProposes))) {
    alert('Veuillez saisir le nombre de films proposé.'); return;
  }
  try {
    const d = await ap({
      action:          'demander_correction_bobine',
      bobine_id:       m.dataset.bobineId,
      site_id:         m.dataset.siteId,
      date:            m.dataset.date,
      notes_gsb:       notes,
      films_pj:        m.dataset.filmsPj,
      films_proposes:  filmsProposes,
      films_emuci:     m.dataset.filmsEmuci,
      ecart:           m.dataset.ecart,
    });
    if (d.success) {
      toast('✅ Demande envoyée. Le coordinateur a été notifié.', 'success');
      m.style.display = 'none';
    } else {
      toast('❌ ' + d.message, 'danger');
    }
  } catch(err) {
    toast('❌ Erreur réseau. Réessayez.', 'danger');
  }
}

// ── Voir détails d'une validation (lecture seule)
async function voirDetails(siteId, siteNom, nbEcarts, detailsJson, statut, commentaire, gsbNom, gsbAt, dateVal) {
  document.getElementById('detailTitle').textContent = `🔍 ${siteNom}`;
  document.getElementById('detailMeta').textContent  = `Validé le <?= h($f_date) ?> par ${gsbNom} — ${gsbAt}`;
  document.getElementById('detailBody').innerHTML    = '<div style="text-align:center;padding:30px;color:var(--muted)">⏳ Chargement...</div>';
  document.getElementById('modalDetails').style.display = 'flex';

  // Charger la liste complète des bobines vérifiées via AJAX
  const _r = await ap({action:'calculer_ecarts', site_id:siteId, date:'<?= h($f_date) ?>'});
  if(!_r.success){document.getElementById('detailBody').innerHTML='<div class="alert alert-danger">'+(_r.message||'Erreur')+'</div>';return;}
  const d = _r.data || {};

  const nb_bobines  = d.nb_bobines  ?? 0;
  const nb_ecarts   = d.nb_ecarts   ?? 0;
  const fmtImport = d.dernier_import
    ? (() => { const dt = new Date(d.dernier_import.replace(' ','T')); return dt.toLocaleDateString('fr-FR',{day:'2-digit',month:'2-digit',year:'numeric'}) + ' à ' + dt.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'}); })()
    : '—';

  const statutLabels = {
    valide_auto:'✅ Validé automatiquement', valide_gsb:'✅ Validé par GSB',
    autorise_ecart:'⚠️ Écart autorisé', reajuste:'🔄 Stock réajusté', refuse:'❌ Bloqué'
  };
  const statutColors = {
    valide_auto:'#d1fae5', valide_gsb:'#d1fae5',
    autorise_ecart:'#fef3c7', reajuste:'#dbeafe', refuse:'#fee2e2'
  };
  const statutTextColors = {
    valide_auto:'#065f46', valide_gsb:'#065f46',
    autorise_ecart:'#92400e', reajuste:'#1d4ed8', refuse:'#991b1b'
  };

  // En-tête statut
  let html = `
    <div style="background:${statutColors[statut]||'#f8fafc'};border-radius:12px;padding:14px 18px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between">
      <div>
        <div style="font-weight:800;font-size:15px;color:${statutTextColors[statut]||'var(--navy)'}">${statutLabels[statut]||statut}</div>
        ${commentaire?`<div style="font-size:12.5px;margin-top:4px;color:${statutTextColors[statut]||'var(--muted)'}">💬 ${commentaire}</div>`:''}
      </div>
      <div style="text-align:right;font-size:12px;color:var(--muted)">
        <div>${gsbNom}</div><div>${gsbAt}</div>
      </div>
    </div>`;

  // Stats
  html += `
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:18px">
      <div style="text-align:center;padding:12px;background:var(--tertiary);border-radius:10px">
        <div style="font-size:22px;font-weight:900;color:var(--navy)">${nb_bobines}</div>
        <div style="font-size:11px;color:var(--muted);font-weight:600">Bobines vérifiées</div>
      </div>
      <div style="text-align:center;padding:12px;background:${nb_ecarts>0?'#fee2e2':'#d1fae5'};border-radius:10px">
        <div style="font-size:22px;font-weight:900;color:${nb_ecarts>0?'#991b1b':'#065f46'}">${nb_ecarts}</div>
        <div style="font-size:11px;color:${nb_ecarts>0?'#991b1b':'#065f46'};font-weight:600">Écarts</div>
      </div>
      <div style="text-align:center;padding:12px;background:var(--tertiary);border-radius:10px">
        <div style="font-size:12.5px;font-weight:700;color:var(--navy)">${fmtImport}</div>
        <div style="font-size:11px;color:var(--muted);font-weight:600">Dernier import EMUCI</div>
      </div>
    </div>`;

  // ── Tableau détaillé : une ligne par bobine saisie dans le PJ
  const bobinesDetail = d.bobines_detail || [];
  const hasImport = !!d.dernier_import;

  let tableTitle = bobinesDetail.length > 0
    ? `🎞️ Détail par bobine saisie dans le Point Journalier (${bobinesDetail.length} bobine(s))`
    : '🎞️ Détail des bobines';

  html += `<div style="font-size:13px;font-weight:700;color:var(--navy);margin-bottom:10px">${tableTitle}</div>`;

  if (bobinesDetail.length === 0) {
    // Si la validation existe déjà (auto ou manuelle), le point a été vérifié même sans détail lisible ici
    const msgValide = ['valide_auto','valide_gsb'].includes(statut);
    html += msgValide
      ? `<div style="background:#D1FAE5;border-radius:10px;padding:16px;font-size:13px;color:#065F46">
           ✅ Le stock a été validé pour cette date. Les données détaillées ne sont plus disponibles pour relecture.
         </div>`
      : `<div style="background:#FEF3C7;border-radius:10px;padding:16px;font-size:13px;color:#92400E">
           ⚠️ Aucune donnée bobine trouvée pour cette date. La comparaison avec EMUCI n'est pas possible.
         </div>`;
  } else {
    html += `
    <div style="border:1.5px solid var(--border);border-radius:10px;overflow:hidden">
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr style="background:#06033A">
            <th style="padding:9px 12px;color:white;font-size:10.5px;text-align:left">N° Bobine</th>
            <th style="padding:9px 12px;color:white;font-size:10.5px;text-align:left">Type</th>
            <th style="padding:9px 12px;color:white;font-size:10.5px;text-align:center">Stock départ</th>
            <th style="padding:9px 12px;color:white;font-size:10.5px;text-align:center">Utilisé (PJ)</th>
            <th style="padding:9px 12px;color:white;font-size:10.5px;text-align:center">Endommagé</th>
            <th style="padding:9px 12px;color:white;font-size:10.5px;text-align:center">Restant</th>
            ${hasImport ? '<th style="padding:9px 12px;color:white;font-size:10.5px;text-align:center">EMUCI</th><th style="padding:9px 12px;color:white;font-size:10.5px;text-align:center">Écart</th>' : ''}
            <th style="padding:9px 12px;color:white;font-size:10.5px;text-align:center">Statut</th>
            ${<?= $can_valider ? 'true' : 'false' ?> ? '<th style="padding:9px 12px;color:white;font-size:10.5px;text-align:center">Action</th>' : ''}
          </tr>
        </thead>
        <tbody>`;

    bobinesDetail.forEach((b, i) => {
      const hasEcart = b.has_ecart;
      const rowBg = hasEcart ? '#FFF7ED' : (i%2===0 ? 'white' : '#F8FAFC');
      const ecartColor = b.ecart > 0 ? '#DC2626' : '#D97706';

      html += `<tr style="background:${rowBg}">
        <td style="padding:9px 12px;font-family:monospace;font-weight:800;color:#06033A">${b.numero}</td>
        <td style="padding:9px 12px;font-size:11.5px;color:var(--muted)">${b.type_code||b.format||'—'}</td>
        <td style="padding:9px 12px;text-align:center;font-weight:600">${b.stock_debut}</td>
        <td style="padding:9px 12px;text-align:center;font-weight:800;color:#1B75BC;font-size:15px">${b.films_utilises}</td>
        <td style="padding:9px 12px;text-align:center;color:${b.films_endommages>0?'#DC2626':'var(--muted)'};font-weight:${b.films_endommages>0?'700':'400'}">${b.films_endommages>0?b.films_endommages:'—'}</td>
        <td style="padding:9px 12px;text-align:center;font-weight:700;color:${b.films_restants<=0?'#DC2626':b.films_restants<50?'#D97706':'#065F46'}">${b.films_restants}</td>
        ${hasImport ? `
        <td style="padding:9px 12px;text-align:center;font-weight:600">${b.films_optoplate !== null ? b.films_optoplate : '<span style="color:var(--muted)">—</span>'}</td>
        <td style="padding:9px 12px;text-align:center;font-weight:800;color:${hasEcart?ecartColor:'var(--success)'}">
          ${hasEcart ? (b.ecart>0?'+':'')+b.ecart : '✓'}
        </td>` : ''}
        <td style="padding:9px 12px;text-align:center">
          ${hasEcart
            ? `<span style="background:#FEE2E2;color:#991B1B;padding:3px 9px;border-radius:8px;font-size:10.5px;font-weight:700">⚠️ Écart ${b.ecart>0?'+':''}${b.ecart}</span>`
            : '<span style="background:#D1FAE5;color:#065F46;padding:3px 9px;border-radius:8px;font-size:10.5px;font-weight:700">✅ OK</span>'}
        </td>
        ${<?= $can_valider ? 'true' : 'false' ?> ? `<td style="padding:7px 12px;text-align:center">
          <button onclick="demanderModifBobine(${b.bobine_id},'${b.numero}',${siteId},'<?= h($f_date) ?>',${b.films_utilises||0},${b.films_optoplate!==null?b.films_optoplate:0},${b.ecart||0})"
            style="background:#fff7ed;color:#c2410c;border:1.5px solid #fed7aa;padding:4px 9px;border-radius:7px;font-size:10.5px;font-weight:700;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:4px">
            ✏️ Demander modif
          </button>
        </td>` : '<td></td>'}
      </tr>`;
    });

    html += `</tbody></table></div>`;

    // Note si pas d'import EMUCI disponible
    if (!hasImport) {
      html += `<div style="margin-top:10px;font-size:12px;color:var(--muted);background:var(--tertiary);padding:8px 12px;border-radius:8px">
        ℹ️ Aucun import OptoPlate disponible — la comparaison EMUCI n'est pas affichée.
      </div>`;
    }
  }

  html += `</div>`;
  document.getElementById('detailBody').innerHTML = html;
}
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
