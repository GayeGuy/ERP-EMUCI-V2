<?php
// ============================================================
//  pages/commandes.php — Module Commandes unifié v3
//  Workflow 5 étapes :
//  1. Coordinateur crée           → en_attente
//  2. Superviseur Op valide/rejette → en_attente_livraison | rejete
//  3. Gestionnaire prépare, expédie → en_cours_livraison
//  4. Coordinateur réceptionne    → recu
//  ⚠️ Coordinateur ne voit PAS les prix
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

require_auth();

$user      = current_user();
$role_slug = $user['role_slug'] ?? '';
$page_title  = 'Commandes';
$active_page = 'commandes';

$is_coord      = $role_slug === 'coordinateur_site';
$is_superviseur = in_array($role_slug, ['superviseur_operation','admin','superadmin']);
$is_gestionnaire = in_array($role_slug, ['admin','superadmin','gestionnaire_stock','superviseur_achat']);
$voir_prix     = !$is_coord; // coordinateur ne voit pas les prix

$site_force    = ($is_coord && $user['site_id']) ? (int)$user['site_id'] : 0;
$can_create    = can('commandes', 'can_create');
$sites_list    = db_fetch_all("SELECT id,nom FROM sites WHERE actif=1 ORDER BY nom");

// ============================================================
//  AJAX
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // ── 1. Créer commande (coordinateur ou autre profil autorisé)
    if ($action === 'creer_commande') {
        if (!$can_create) json_response(false,'Accès refusé.');
        $site_id    = $site_force ?: (int)($_POST['site_id'] ?? 0);
        $notes      = trim($_POST['notes'] ?? '');
        $lignes_raw = $_POST['lignes'] ?? '[]';
        $lignes     = json_decode($lignes_raw, true);
        if (!$site_id)     json_response(false,'Site obligatoire.');
        if (empty($lignes)) json_response(false,'Ajoutez au moins un article.');

        db_begin();
        try {
            $num = 'CMD-'.date('Ymd').'-'.str_pad(rand(1,9999),4,'0',STR_PAD_LEFT);
            db_query(
                "INSERT INTO commandes (numero_commande,site_id,statut,notes,created_by) VALUES (?,?,'en_attente',?,?)",
                [$num,$site_id,$notes,$user['id']]
            );
            $cmd_id = (int)db_last_id();
            foreach ($lignes as $l) {
                $raw_id = $l['article_id'] ?? null;
                $art_id = ($raw_id !== null && is_numeric($raw_id)) ? (int)$raw_id : null;
                db_query(
                    "INSERT INTO commande_lignes
                     (commande_id,type_article,article_id,libelle,quantite,unite,prix_unitaire,statut_ligne)
                     VALUES (?,?,?,?,?,?,?,'en_attente')",
                    [$cmd_id, $l['type']??'article', $art_id,
                     $l['libelle']??'', (int)($l['quantite']??1), $l['unite']??'unité',
                     $voir_prix ? (int)($l['prix_unitaire']??0) : 0]
                );
            }
            // Notifier superviseurs opération
            $targets = db_fetch_all(
                "SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id
                 WHERE r.slug IN ('superviseur_operation','admin','superadmin') AND u.actif=1"
            );
            $site_nom = db_fetch_value("SELECT nom FROM sites WHERE id=?",[$site_id]);
            foreach ($targets as $t) {
                db_query("INSERT INTO notifications (user_id,type,titre,message) VALUES (?,?,?,?)",
                    [$t['id'],'info',"📦 Commande $num à valider",
                     "{$user['prenom']} {$user['nom']} (Site: $site_nom) — ".count($lignes)." article(s) — En attente de votre validation."]);
            }
            audit_log($user['id'],'CREATE','commandes',$cmd_id,"Commande $num créée — ".count($lignes)." ligne(s)");
            db_commit();
            json_response(true,'Commande envoyée. En attente de validation superviseur.',['id'=>$cmd_id,'numero'=>$num]);
        } catch(Exception $e){ db_rollback(); json_response(false,$e->getMessage()); }
    }

    // ── 2. Valider commande (superviseur opération)
    if ($action === 'valider_commande') {
        if (!$is_superviseur) json_response(false,'Accès refusé — Superviseur Opération requis.');
        $cmd_id      = (int)($_POST['cmd_id'] ?? 0);
        $lignes_raw  = $_POST['lignes'] ?? '[]';
        $lignes_val  = json_decode($lignes_raw, true);

        $cmd = db_fetch_one("SELECT * FROM commandes WHERE id=? AND statut='en_attente'", [$cmd_id]);
        if (!$cmd) json_response(false,'Commande introuvable ou déjà traitée.');

        // Vérifier qu'au moins une ligne est validée
        $nb_valides = count(array_filter($lignes_val, fn($l) => ($l['statut_ligne']??'') === 'valide'));
        if ($nb_valides === 0) json_response(false,'Validez au moins une ligne.');

        db_begin();
        try {
            foreach ($lignes_val as $l) {
                $sl     = $l['statut_ligne'] ?? 'valide';
                $motif  = trim($l['motif_rejet'] ?? '');
                if ($sl === 'rejete' && !$motif) json_response(false,"Motif de rejet obligatoire pour la ligne \"{$l['libelle']}\".");
                db_query(
                    "UPDATE commande_lignes SET statut_ligne=?, motif_rejet=? WHERE id=? AND commande_id=?",
                    [$sl, $motif ?: null, (int)$l['ligne_id'], $cmd_id]
                );
            }
            db_query(
                "UPDATE commandes SET statut='en_attente_livraison',valide_par=?,valide_at=NOW() WHERE id=?",
                [$user['id'],$cmd_id]
            );
            // Notifier coordinateur + gestionnaire stock
            $coords = db_fetch_all(
                "SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id
                 WHERE r.slug='coordinateur_site' AND u.site_id=? AND u.actif=1",
                [$cmd['site_id']]
            );
            foreach ($coords as $c) {
                db_query("INSERT INTO notifications (user_id,type,titre,message) VALUES (?,?,?,?)",
                    [$c['id'],'info',"✅ Commande {$cmd['numero_commande']} validée",
                     "Votre commande a été validée par le superviseur et est en cours de préparation."]);
            }
            $gests = db_fetch_all(
                "SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id
                 WHERE r.slug IN ('gestionnaire_stock','superviseur_achat','admin','superadmin') AND u.actif=1"
            );
            foreach ($gests as $g) {
                db_query("INSERT INTO notifications (user_id,type,titre,message) VALUES (?,?,?,?)",
                    [$g['id'],'info',"📦 Commande {$cmd['numero_commande']} à préparer",
                     "Commande validée — prête pour préparation et expédition."]);
            }
            audit_log($user['id'],'UPDATE','commandes',$cmd_id,"Commande validée par superviseur — $nb_valides ligne(s)");
            db_commit();
            json_response(true,'Commande validée. Gestionnaire stock notifié.');
        } catch(Exception $e){ db_rollback(); json_response(false,$e->getMessage()); }
    }

    // ── 2b. Rejeter commande entière (superviseur)
    if ($action === 'rejeter_commande') {
        if (!$is_superviseur) json_response(false,'Accès refusé.');
        $cmd_id = (int)($_POST['cmd_id'] ?? 0);
        $motif  = trim($_POST['motif']   ?? '');
        if (!$motif) json_response(false,'Motif de rejet obligatoire.');
        $cmd = db_fetch_one("SELECT * FROM commandes WHERE id=? AND statut='en_attente'",[$cmd_id]);
        if (!$cmd) json_response(false,'Commande introuvable ou déjà traitée.');
        db_begin();
        try {
            db_query("UPDATE commandes SET statut='rejete',motif_rejet_global=?,valide_par=?,valide_at=NOW() WHERE id=?",
                [$motif,$user['id'],$cmd_id]);
            db_query("UPDATE commande_lignes SET statut_ligne='rejete',motif_rejet=? WHERE commande_id=?",[$motif,$cmd_id]);
            $coords = db_fetch_all(
                "SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id
                 WHERE r.slug='coordinateur_site' AND u.site_id=? AND u.actif=1", [$cmd['site_id']]
            );
            foreach ($coords as $c) {
                db_query("INSERT INTO notifications (user_id,type,titre,message) VALUES (?,?,?,?)",
                    [$c['id'],'info',"❌ Commande {$cmd['numero_commande']} rejetée","Motif : $motif"]);
            }
            audit_log($user['id'],'UPDATE','commandes',$cmd_id,"Commande rejetée : $motif");
            db_commit();
            json_response(true,'Commande rejetée. Coordinateur notifié.');
        } catch(Exception $e){ db_rollback(); json_response(false,$e->getMessage()); }
    }

    // ── 3. Émettre livraison (gestionnaire stock prépare + expédie)
    if ($action === 'emettre_livraison') {
        if (!$is_gestionnaire) json_response(false,'Accès refusé — Gestionnaire Stock requis.');
        $cmd_id     = (int)($_POST['cmd_id']            ?? 0);
        $notes_liv  = trim($_POST['notes_livraison']    ?? '');
        $lignes_raw = $_POST['lignes']                  ?? '[]';
        $lignes_liv = json_decode($lignes_raw, true);

        $cmd = db_fetch_one("SELECT * FROM commandes WHERE id=? AND statut='en_attente_livraison'",[$cmd_id]);
        if (!$cmd) json_response(false,'Commande introuvable ou statut incorrect.');

        foreach ($lignes_liv as $l) {
            $q_cmd = (int)($l['quantite']      ?? 0);
            $q_liv = (int)($l['quantite_livree']?? 0);
            if ($q_liv != $q_cmd && empty($l['motif_ecart'])) {
                json_response(false,"Justification obligatoire pour \"{$l['libelle']}\" (commandé: $q_cmd, livré: $q_liv).");
            }
        }

        db_begin();
        try {
            // Créer bon de distribution
            $num_dist = 'DS-'.date('Ymd').'-'.str_pad(rand(1,999),3,'0',STR_PAD_LEFT);
            db_query(
                "INSERT INTO distributions_site (numero_distribution,commande_id,site_id,date_distribution,statut,notes,created_by,expedie_at)
                 VALUES (?,?,?,?,'en_cours_livraison',?,?,NOW())",
                [$num_dist,$cmd_id,$cmd['site_id'],date('Y-m-d'),$notes_liv,$user['id']]
            );
            $dist_id = (int)db_last_id();

            foreach ($lignes_liv as $l) {
                $q_liv = (int)($l['quantite_livree'] ?? $l['quantite']);
                db_query(
                    "UPDATE commande_lignes SET quantite_livree=?,motif_ecart=? WHERE id=? AND commande_id=?",
                    [$q_liv, $l['motif_ecart']??null, (int)$l['ligne_id'], $cmd_id]
                );
                // Ligne de distribution
                if ($q_liv > 0) {
                    db_query(
                        "INSERT INTO distribution_lignes (distribution_id,article_id,commande_ligne_id,libelle,quantite_envoyee,unite,statut)
                         VALUES (?,?,?,?,?,?,'en_cours_livraison')",
                        [$dist_id, $l['article_id']??null, (int)$l['ligne_id'],
                         $l['libelle']??'', $q_liv, $l['unite']??'unité']
                    );
                    // Décrémenter stock central articles
                    if (!empty($l['article_id'])) {
                        db_query("UPDATE articles SET stock_global = GREATEST(0, stock_global - ?) WHERE id=?",
                            [$q_liv, (int)$l['article_id']]);
                    }
                }
            }

            db_query(
                "UPDATE commandes SET statut='en_cours_livraison',livraison_par=?,livraison_at=NOW(),notes_livraison=? WHERE id=?",
                [$user['id'],$notes_liv,$cmd_id]
            );

            // Notifier coordinateur
            $coords = db_fetch_all(
                "SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id
                 WHERE r.slug='coordinateur_site' AND u.site_id=? AND u.actif=1",[$cmd['site_id']]
            );
            foreach ($coords as $c) {
                db_query("INSERT INTO notifications (user_id,type,titre,message) VALUES (?,?,?,?)",
                    [$c['id'],'info',"🚚 Commande {$cmd['numero_commande']} expédiée",
                     "Votre commande est en route. Confirmez la réception à l'arrivée."]);
            }
            audit_log($user['id'],'UPDATE','commandes',$cmd_id,"Livraison émise — bon $num_dist");
            db_commit();
            json_response(true,'Livraison émise. Coordinateur notifié.');
        } catch(Exception $e){ db_rollback(); json_response(false,$e->getMessage()); }
    }

    // ── 4. Réceptionner (coordinateur confirme réception)
    if ($action === 'receptionner') {
        $cmd_id = (int)($_POST['cmd_id'] ?? 0);
        $cmd    = db_fetch_one("SELECT * FROM commandes WHERE id=? AND statut='en_cours_livraison'",[$cmd_id]);
        if (!$cmd) json_response(false,'Commande introuvable ou statut incorrect.');
        if ($is_coord && $cmd['site_id'] != $site_force) json_response(false,'Accès refusé.');
        db_begin();
        try {
            db_query("UPDATE commandes SET statut='recu',recu_par=?,recu_at=NOW() WHERE id=?",[$user['id'],$cmd_id]);
            db_query("UPDATE distribution_lignes dl JOIN distributions_site ds ON ds.id=dl.distribution_id
                      SET dl.statut='livre', ds.statut='livre', ds.recu_at=NOW(), ds.recu_par=?
                      WHERE ds.commande_id=?",[$user['id'],$cmd_id]);
            // Mettre à jour stock site — articles classiques (+)
            $lignes_rec = db_fetch_all(
                "SELECT cl.article_id, cl.quantite_livree AS qte
                 FROM commande_lignes cl
                 WHERE cl.commande_id=? AND cl.type_article != 'rivet'
                   AND cl.article_id IS NOT NULL AND cl.quantite_livree > 0",
                [$cmd_id]
            );
            foreach ($lignes_rec as $lr) {
                if ($lr['article_id'] && $lr['qte'] > 0) {
                    db_query(
                        "INSERT INTO stock_site (article_id,site_id,quantite) VALUES (?,?,?)
                         ON DUPLICATE KEY UPDATE quantite = quantite + ?",
                        [$lr['article_id'],$cmd['site_id'],$lr['qte'],$lr['qte']]
                    );
                    db_query(
                        "INSERT INTO stock_consommables_site (consommable_id,site_id,quantite) VALUES (?,?,?)
                         ON DUPLICATE KEY UPDATE quantite = quantite + ?",
                        [$lr['article_id'],$cmd['site_id'],$lr['qte'],$lr['qte']]
                    );
                }
            }

            // Mettre à jour stock rivets (+)
            $lignes_rivets = db_fetch_all(
                "SELECT cl.libelle, cl.quantite_livree AS qte
                 FROM commande_lignes cl
                 WHERE cl.commande_id=? AND cl.type_article='rivet' AND cl.quantite_livree > 0",
                [$cmd_id]
            );
            foreach ($lignes_rivets as $lr) {
                $type_rivet = (stripos($lr['libelle'], 'clat') !== false) ? 'eclate' : 'gonflable';
                db_query(
                    "INSERT INTO op_stock_rivets (site_id, type_rivet, quantite) VALUES (?,?,?)
                     ON DUPLICATE KEY UPDATE quantite = quantite + ?",
                    [$cmd['site_id'], $type_rivet, (int)$lr['qte'], (int)$lr['qte']]
                );
            }

            // Mettre à jour stock PMMA (+)
            $lignes_pmma = db_fetch_all(
                "SELECT cl.libelle, cl.quantite_livree AS qte
                 FROM commande_lignes cl
                 WHERE cl.commande_id=? AND cl.type_article='pmma' AND cl.quantite_livree > 0",
                [$cmd_id]
            );
            foreach ($lignes_pmma as $lr) {
                // libelle = 'PMMA TYPE_X' → type_pmma = 'TYPE_X'
                $type_pmma = trim(preg_replace('/^PMMA\s*/i', '', $lr['libelle']));
                if ($type_pmma) {
                    db_query(
                        "INSERT INTO stock_pmma_site (site_id, type_pmma, quantite) VALUES (?,?,?)
                         ON DUPLICATE KEY UPDATE quantite = quantite + ?",
                        [$cmd['site_id'], $type_pmma, (int)$lr['qte'], (int)$lr['qte']]
                    );
                }
            }
            audit_log($user['id'],'UPDATE','commandes',$cmd_id,"Réception confirmée par coordinateur");
            db_commit();
            $msg = sprintf(
                'Réception confirmée ✅ | Articles: %d | Rivets: %d | PMMA: %d',
                count($lignes_rec), count($lignes_rivets), count($lignes_pmma)
            );
            json_response(true, $msg);
        } catch(Exception $e){ db_rollback(); json_response(false,$e->getMessage()); }
    }

    // ── Annuler commande en_attente
    if ($action === 'annuler') {
        $cmd_id = (int)($_POST['cmd_id'] ?? 0);
        $cmd    = db_fetch_one("SELECT * FROM commandes WHERE id=? AND statut='en_attente'",[$cmd_id]);
        if (!$cmd) json_response(false,'Seules les commandes en attente peuvent être annulées.');
        if ($is_coord && $cmd['site_id'] != $site_force) json_response(false,'Accès refusé.');
        db_query("UPDATE commandes SET statut='annule' WHERE id=?",[$cmd_id]);
        audit_log($user['id'],'UPDATE','commandes',$cmd_id,"Commande annulée");
        json_response(true,'Commande annulée.');
    }

    // ── Charger lignes commande (pour modals)
    if ($action === 'get_lignes') {
        $cmd_id = (int)($_POST['cmd_id'] ?? 0);
        $lignes = db_fetch_all("SELECT cl.*, a.stock_global AS stock_central FROM commande_lignes cl
            LEFT JOIN articles a ON a.id=cl.article_id WHERE cl.commande_id=? ORDER BY cl.id", [$cmd_id]);
        json_response(true,'', $lignes);
    }

    // ── Historique commandes par article/site pour coordinateur
    if ($action === 'get_historique_article') {
        $article_id = (int)($_POST['article_id'] ?? 0);
        $s_id       = $site_force ?: (int)($_POST['site_id'] ?? 0);
        $derniere   = db_fetch_one(
            "SELECT cl.quantite, c.created_at FROM commande_lignes cl JOIN commandes c ON c.id=cl.commande_id
             WHERE cl.article_id=? AND c.site_id=? AND c.statut NOT IN ('annule','rejete')
             ORDER BY c.created_at DESC LIMIT 1",
            [$article_id, $s_id]
        );
        json_response(true,'', ['derniere_qte' => $derniere ? (int)$derniere['quantite'] : 0]);
    }

    json_response(false,'Action inconnue.');
}

// ── Export bon de commande
if (isset($_GET['export'], $_GET['id'])) {
    $cmd_id = (int)$_GET['id'];
    $format = trim($_GET['format'] ?? 'pdf');
    $cmd    = db_fetch_one(
        "SELECT c.*, s.nom AS site_nom, CONCAT(u.prenom,' ',u.nom) AS agent
         FROM commandes c LEFT JOIN sites s ON s.id=c.site_id LEFT JOIN users u ON u.id=c.created_by
         WHERE c.id=?", [$cmd_id]
    );
    $lignes = db_fetch_all("SELECT * FROM commande_lignes WHERE commande_id=? ORDER BY id", [$cmd_id]);
    if (!$cmd) { echo "Commande introuvable"; exit; }

    // Charger données contextuelles selon le type_article de chaque ligne
    $ctx = [];
    $sid = (int)$cmd['site_id'];

    foreach ($lignes as $l) {
        $type = $l['type_article'] ?? 'article';
        $lib  = $l['libelle']      ?? '';
        $aid  = (int)($l['article_id'] ?? 0);

        // Helper : dernière quantité commandée par type+libelle ou article_id
        $fn_derniere = function() use ($sid, $cmd_id, $type, $lib, $aid): int {
            if ($aid) {
                return (int)(db_fetch_value(
                    "SELECT cl.quantite FROM commande_lignes cl
                     JOIN commandes c2 ON c2.id=cl.commande_id
                     WHERE cl.article_id=? AND c2.site_id=? AND c2.statut NOT IN ('annule','rejete') AND c2.id!=?
                     ORDER BY c2.created_at DESC LIMIT 1", [$aid,$sid,$cmd_id]) ?? 0);
            }
            return (int)(db_fetch_value(
                "SELECT cl.quantite FROM commande_lignes cl
                 JOIN commandes c2 ON c2.id=cl.commande_id
                 WHERE cl.type_article=? AND cl.libelle=? AND c2.site_id=?
                   AND c2.statut NOT IN ('annule','rejete') AND c2.id!=?
                 ORDER BY c2.created_at DESC LIMIT 1", [$type,$lib,$sid,$cmd_id]) ?? 0);
        };

        // Helper : conso hebdo moyenne
        $fn_conso = function() use ($sid, $type, $lib, $aid): int {
            if ($aid) {
                return (int)(db_fetch_value(
                    "SELECT COALESCE(ROUND(SUM(COALESCE(cl.quantite_livree,cl.quantite))/4),0)
                     FROM commande_lignes cl JOIN commandes c ON c.id=cl.commande_id
                     WHERE cl.article_id=? AND c.site_id=? AND c.statut IN ('recu','livre')
                       AND c.recu_at >= DATE_SUB(CURDATE(), INTERVAL 4 WEEK)", [$aid,$sid]) ?? 0);
            }
            return (int)(db_fetch_value(
                "SELECT COALESCE(ROUND(SUM(COALESCE(cl.quantite_livree,cl.quantite))/4),0)
                 FROM commande_lignes cl JOIN commandes c ON c.id=cl.commande_id
                 WHERE cl.type_article=? AND cl.libelle=? AND c.site_id=?
                   AND c.statut IN ('recu','livre')
                   AND c.recu_at >= DATE_SUB(CURDATE(), INTERVAL 4 WEEK)", [$type,$lib,$sid]) ?? 0);
        };

        switch ($type) {

            case 'article':
            case 'consommable':
                // Si article_id absent, essayer par libelle dans articles
                if (!$aid && $lib) {
                    $aid = (int)(db_fetch_value("SELECT id FROM articles WHERE libelle=? LIMIT 1", [$lib]) ?? 0);
                }
                $ctx[$l['id']] = [
                    'stock_site' => (int)(db_fetch_value(
                        "SELECT COALESCE(ss.quantite, scs.quantite, 0)
                         FROM articles a
                         LEFT JOIN stock_site ss  ON ss.article_id=a.id AND ss.site_id=?
                         LEFT JOIN stock_consommables_site scs ON scs.consommable_id=a.id AND scs.site_id=?
                         WHERE a.id=?", [$sid,$sid,$aid]) ?? 0),
                    'derniere'   => $fn_derniere(),
                    'conso'      => $fn_conso(),
                ];
                break;

            case 'pmma':
                // Extraire le type_pmma depuis le libellé : "PMMA Type A" → "Type A"
                $type_pmma = preg_replace('/^PMMA\s*/i', '', $lib);
                $ctx[$l['id']] = [
                    'stock_site' => (int)(db_fetch_value(
                        "SELECT COALESCE(quantite,0) FROM stock_pmma_site WHERE site_id=? AND type_pmma=?",
                        [$sid, $type_pmma]) ?? 0),
                    'derniere'   => $fn_derniere(),
                    'conso'      => $fn_conso(),
                ];
                break;

            case 'rivet':
                $ctx[$l['id']] = [
                    'stock_site' => (int)(db_fetch_value(
                        "SELECT COALESCE(SUM(quantite),0) FROM op_stock_rivets WHERE site_id=?",
                        [$sid]) ?? 0),
                    'derniere'   => $fn_derniere(),
                    'conso'      => $fn_conso(),
                ];
                break;

            case 'bobine':
                // Stock = total films_restants des bobines en_stock sur ce site
                $ctx[$l['id']] = [
                    'stock_site' => (int)(db_fetch_value(
                        "SELECT COALESCE(SUM(films_restants),0) FROM op_bobines
                         WHERE statut='en_stock' AND site_id=?",
                        [$sid]) ?? 0),
                    'derniere'   => $fn_derniere(),
                    'conso'      => $fn_conso(),
                ];
                break;

            case 'equipement':
                $ctx[$l['id']] = [
                    'stock_site' => (int)(db_fetch_value(
                        "SELECT COUNT(*) FROM equipements WHERE statut_stock='en_stock' AND actif=1 AND site_id=?",
                        [$sid]) ?? 0),
                    'derniere'   => $fn_derniere(),
                    'conso'      => 0,
                ];
                break;

            default:
                $ctx[$l['id']] = ['stock_site'=>0,'derniere'=>$fn_derniere(),'conso'=>0];
        }
    }

    if ($format === 'excel') _bdc_excel($cmd, $lignes, $ctx);
    else _bdc_pdf($cmd, $lignes, $voir_prix, $ctx);
    exit;
}

// ============================================================
//  DONNÉES
// ============================================================
$f_site   = $site_force ?: (int)($_GET['site']   ?? 0);
$f_statut = trim($_GET['statut'] ?? '');
$where = ['1=1']; $params = [];
if ($site_force)  { $where[] = 'c.site_id=?'; $params[] = $site_force; }
elseif ($f_site)  { $where[] = 'c.site_id=?'; $params[] = $f_site; }
if ($f_statut)    { $where[] = 'c.statut=?';  $params[] = $f_statut; }

$commandes = db_fetch_all(
    "SELECT c.*, s.nom AS site_nom, CONCAT(u.prenom,' ',u.nom) AS agent,
            COUNT(l.id) AS nb_lignes,
            COALESCE(SUM(l.quantite*l.prix_unitaire),0) AS total_ht,
            SUM(CASE WHEN l.statut_ligne='rejete' THEN 1 ELSE 0 END) AS nb_rejets,
            SUM(CASE WHEN l.quantite_livree IS NOT NULL AND l.quantite_livree != l.quantite THEN 1 ELSE 0 END) AS nb_ecarts
     FROM commandes c
     LEFT JOIN sites s ON s.id=c.site_id
     LEFT JOIN users u ON u.id=c.created_by
     LEFT JOIN commande_lignes l ON l.commande_id=c.id
     WHERE ".implode(' AND ',$where)."
     GROUP BY c.id ORDER BY c.created_at DESC LIMIT 100",
    $params
);

// ── Articles avec données contextuelles intégrées (stock site, historique)
// Pour coordinateur : site_force > 0 → données filtrées sur leur site
// Pour autres profils : site_force = 0 → données non filtrées (stock central)
$_sid = $site_force ?: 0;

$articles_dispo = db_fetch_all(
    "SELECT a.id, a.code, a.libelle, a.unite, a.prix_unitaire, a.stock_global, a.type_article,
            -- Stock sur le site du coordinateur (ou 0 si pas de site)
            COALESCE(ss.quantite, scs.quantite, 0) AS stock_site,
            -- Dernière quantité commandée pour cet article sur ce site
            COALESCE((
                SELECT cl2.quantite FROM commande_lignes cl2
                JOIN commandes c2 ON c2.id = cl2.commande_id
                WHERE cl2.article_id = a.id
                  AND (? = 0 OR c2.site_id = ?)
                  AND c2.statut NOT IN ('annule','rejete')
                ORDER BY c2.created_at DESC LIMIT 1
            ), 0) AS derniere,
            -- Consommation hebdo moyenne (4 dernières semaines, commandes reçues)
            COALESCE((
                SELECT ROUND(SUM(COALESCE(cl3.quantite_livree, cl3.quantite)) / 4)
                FROM commande_lignes cl3
                JOIN commandes c3 ON c3.id = cl3.commande_id
                WHERE cl3.article_id = a.id
                  AND (? = 0 OR c3.site_id = ?)
                  AND c3.statut IN ('recu','livre')
                  AND c3.recu_at >= DATE_SUB(CURDATE(), INTERVAL 4 WEEK)
            ), 0) AS conso
     FROM articles a
     LEFT JOIN stock_site ss  ON ss.article_id  = a.id AND ss.site_id  = ?
     LEFT JOIN stock_consommables_site scs ON scs.consommable_id = a.id AND scs.site_id = ?
     ORDER BY a.libelle",
    [$_sid, $_sid, $_sid, $_sid, $_sid, $_sid]
);

// ── PMMA : stock + historique par type
$pmma_list = [];
foreach (db_fetch_all("SELECT DISTINCT type_pmma FROM stock_pmma_site WHERE quantite>0 ORDER BY type_pmma") as $p) {
    $tp  = $p['type_pmma'];
    $lib = 'PMMA ' . $tp;
    $pmma_list[] = [
        'id'           => $tp,
        'libelle'      => $lib,
        'unite'        => 'unité',
        'prix_unitaire'=> 0,
        'stock_global' => (int)(db_fetch_value("SELECT COALESCE(SUM(quantite),0) FROM stock_pmma_site WHERE type_pmma=?", [$tp]) ?? 0),
        'stock_site'   => $_sid ? (int)(db_fetch_value("SELECT COALESCE(quantite,0) FROM stock_pmma_site WHERE site_id=? AND type_pmma=?", [$_sid,$tp]) ?? 0) : 0,
        'derniere'     => $_sid ? (int)(db_fetch_value(
            "SELECT cl.quantite FROM commande_lignes cl JOIN commandes c ON c.id=cl.commande_id
             WHERE cl.type_article='pmma' AND cl.libelle=? AND c.site_id=? AND c.statut NOT IN ('annule','rejete')
             ORDER BY c.created_at DESC LIMIT 1", [$lib,$_sid]) ?? 0) : 0,
        'conso'        => $_sid ? (int)(db_fetch_value(
            "SELECT COALESCE(ROUND(SUM(COALESCE(cl.quantite_livree,cl.quantite))/4),0)
             FROM commande_lignes cl JOIN commandes c ON c.id=cl.commande_id
             WHERE cl.type_article='pmma' AND cl.libelle=? AND c.site_id=? AND c.statut IN ('recu','livre')
               AND c.recu_at >= DATE_SUB(CURDATE(), INTERVAL 4 WEEK)", [$lib,$_sid]) ?? 0) : 0,
    ];
}

// ── Bobines : liste des TYPES de bobines (commande par type, pas par numéro individuel)
$bobines_list = db_fetch_all(
    "SELECT tb.id,
            CONCAT(tb.code,' — ',tb.libelle) AS libelle,
            'bobine' AS unite,
            0 AS prix_unitaire,
            -- Stock disponible en entrepôt pour ce type (bobines en_stock)
            COALESCE((SELECT COUNT(*) FROM op_bobines b
                      WHERE b.type_code=tb.code AND b.statut='en_stock'),0) AS stock_global,
            0 AS stock_site,
            -- Dernière quantité commandée pour ce type sur ce site
            COALESCE((SELECT cl.quantite FROM commande_lignes cl
                      JOIN commandes c ON c.id=cl.commande_id
                      WHERE cl.type_article='bobine' AND cl.libelle LIKE CONCAT(tb.code,' %')
                        AND (? = 0 OR c.site_id=?) AND c.statut NOT IN ('annule','rejete')
                      ORDER BY c.created_at DESC LIMIT 1), 0) AS derniere,
            0 AS conso
     FROM op_types_bobines tb
     WHERE tb.actif=1
     ORDER BY tb.serie, tb.code",
    [$_sid ?: 0, $_sid ?: 0]
);

// ── Rivets : deux types (gonflable / eclate) avec stock et historique par type
$rivet_types = [
    ['key' => 'gonflable', 'libelle' => 'Rivets gonflables'],
    ['key' => 'eclate',    'libelle' => 'Rivets éclatés'],
];
$rivet_data = [];
foreach ($rivet_types as $rt) {
    $rivet_data[] = [
        'id'           => 'rivets_' . $rt['key'],
        'libelle'      => $rt['libelle'],
        'unite'        => 'unité',
        'prix_unitaire'=> 0,
        'stock_global' => (int)(db_fetch_value(
            "SELECT COALESCE(SUM(quantite),0) FROM op_stock_rivets WHERE type_rivet=?", [$rt['key']]) ?? 0),
        'stock_site'   => $_sid ? (int)(db_fetch_value(
            "SELECT COALESCE(quantite,0) FROM op_stock_rivets WHERE site_id=? AND type_rivet=?",
            [$_sid, $rt['key']]) ?? 0) : 0,
        'derniere'     => $_sid ? (int)(db_fetch_value(
            "SELECT cl.quantite FROM commande_lignes cl JOIN commandes c ON c.id=cl.commande_id
             WHERE cl.type_article='rivet' AND cl.libelle=? AND c.site_id=? AND c.statut NOT IN ('annule','rejete')
             ORDER BY c.created_at DESC LIMIT 1", [$rt['libelle'], $_sid]) ?? 0) : 0,
        'conso'        => $_sid ? (int)(db_fetch_value(
            "SELECT COALESCE(ROUND(SUM(COALESCE(cl.quantite_livree,cl.quantite))/4),0)
             FROM commande_lignes cl JOIN commandes c ON c.id=cl.commande_id
             WHERE cl.type_article='rivet' AND cl.libelle=? AND c.site_id=? AND c.statut IN ('recu','livre')
               AND c.recu_at >= DATE_SUB(CURDATE(), INTERVAL 4 WEEK)", [$rt['libelle'], $_sid]) ?? 0) : 0,
    ];
}

// ── Équipements disponibles (en_stock) avec indicateur site
$equipements_list = db_fetch_all(
    "SELECT e.id,
            CONCAT(n.libelle,' — ',e.numero_serie_interne) AS libelle,
            'unité' AS unite,
            0 AS prix_unitaire,
            1 AS stock_global,
            IF(e.site_id=?,1,0) AS stock_site,
            0 AS derniere,
            0 AS conso
     FROM equipements e
     LEFT JOIN nomenclatures n ON n.id=e.nomenclature_id
     WHERE e.actif=1 AND e.statut_stock='en_stock'
     ORDER BY n.libelle, e.numero_serie_interne LIMIT 200",
    [$_sid ?: 0]
);

$kpi = [
    'attente'        => count(array_filter($commandes,fn($c)=>$c['statut']==='en_attente')),
    'attente_liv'    => count(array_filter($commandes,fn($c)=>$c['statut']==='en_attente_livraison')),
    'en_cours'       => count(array_filter($commandes,fn($c)=>$c['statut']==='en_cours_livraison')),
    'recu'           => count(array_filter($commandes,fn($c)=>$c['statut']==='recu')),
];

// ── Export PDF
function _bdc_pdf($cmd, $lignes, $voir_prix, array $ctx = []) {
    $sl = ['en_attente'=>'En attente','en_attente_livraison'=>'Prêt livraison',
           'en_cours_livraison'=>'En livraison','recu'=>'Reçu','livre'=>'Livré',
           'rejete'=>'Rejeté','annule'=>'Annulé'];

    // Nombre de colonnes dynamiques pour le colspan du total
    $nb_cols = 6; // Article, Cmd préc., Stock site, Conso/sem., Qté demandée, Unité
    $has_livraison = in_array($cmd['statut'], ['en_attente_livraison','en_cours_livraison','recu','livre']);
    if ($has_livraison) $nb_cols += 2; // Qté livrée + Statut/Motif
    if ($voir_prix)     $nb_cols += 2; // P.U. + Total

    header('Content-Type: text/html; charset=UTF-8');

    $rows = '';
    foreach ($lignes as $l) {
        $c          = $ctx[$l['id']] ?? ['derniere'=>0,'stock_site'=>0,'conso'=>0];
        $derniere   = $c['derniere']   > 0 ? $c['derniere']   : '—';
        $stock_site = $c['stock_site'] > 0 ? $c['stock_site'] : '0';
        $conso      = $c['conso']      > 0 ? $c['conso'].'/sem' : '—';
        $qte_dem    = (int)$l['quantite'];
        $qte_liv    = $l['quantite_livree'] !== null ? (int)$l['quantite_livree'] : '—';
        $ecart_st   = ($l['quantite_livree'] !== null && $l['quantite_livree'] != $l['quantite'])
                        ? 'color:#e74c3c;font-weight:700' : '';
        $st_badges  = ['valide'=>'<span style="color:#27ae60;font-weight:700">✅</span>',
                        'rejete'=>'<span style="color:#e74c3c;font-weight:700">❌</span>',
                        'modifie'=>'<span style="color:#f59e0b;font-weight:700">⚠️</span>'];
        $st_badge   = $st_badges[$l['statut_ligne'] ?? ''] ?? '';
        $motif      = htmlspecialchars($l['motif_rejet'] ?? $l['motif_ecart'] ?? '');

        $rows .= "<tr>
            <td><strong>".htmlspecialchars($l['libelle'])."</strong>
                <div style='font-size:10px;color:#888'>".ucfirst($l['type_article']?:'article')." · ".htmlspecialchars($l['unite'])."</div>
            </td>
            <td style='text-align:center;color:#555'>$derniere</td>
            <td style='text-align:center;color:#555'>$stock_site</td>
            <td style='text-align:center;color:#555'>$conso</td>
            <td style='text-align:center;font-weight:800;font-size:15px;color:#06033A'>$qte_dem</td>";
        if ($has_livraison) {
            $rows .= "<td style='text-align:center;$ecart_st'>$qte_liv</td>
                      <td>$st_badge ".($motif?"<span style='font-size:10px;color:#666'>$motif</span>":'')."</td>";
        }
        if ($voir_prix) {
            $rows .= "<td style='text-align:right'>".number_format((float)$l['prix_unitaire'],0,',',' ')." F</td>
                      <td style='text-align:right;font-weight:700'>".number_format($qte_dem*(float)$l['prix_unitaire'],0,',',' ')." F</td>";
        }
        $rows .= "</tr>";
    }

    $total = array_sum(array_map(fn($l) => $l['quantite'] * (float)$l['prix_unitaire'], $lignes));

    // Thead dynamique
    $th_extra = '';
    if ($has_livraison) $th_extra .= "<th style='text-align:center'>Qté livrée</th><th>Statut</th>";
    if ($voir_prix)     $th_extra .= "<th style='text-align:right'>P.U. (F)</th><th style='text-align:right'>Total (F)</th>";

    $total_row = '';
    if ($voir_prix) {
        $span = $nb_cols - 2;
        $total_row = "<tr class='tot'><td colspan='$span'>TOTAL HT</td><td colspan='2' style='text-align:right'>".number_format($total,0,',',' ')." FCFA</td></tr>";
    }

    $statut_label = $sl[$cmd['statut']] ?? $cmd['statut'];

    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'>
    <title>BDC {$cmd['numero_commande']}</title>
    <style>
    *{box-sizing:border-box}
    body{font-family:'Segoe UI',Arial,sans-serif;margin:0;padding:20px;font-size:12px;color:#222}
    .hdr{background:linear-gradient(135deg,#06033A,#1B75BC);color:white;padding:22px 28px;border-radius:12px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center}
    .hdr h1{margin:0;font-size:20px;font-weight:800}.hdr .sub{color:rgba(255,255,255,.7);font-size:11px;margin-top:3px}
    .hdr .num{font-size:22px;font-weight:900;color:#00AEEF;letter-spacing:1px}
    .ig{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px}
    .ib{background:#f8f9fa;border-radius:8px;padding:11px 13px;border:1px solid #e9ecef}
    .ib label{font-size:9px;color:#888;text-transform:uppercase;font-weight:700;letter-spacing:.5px;display:block;margin-bottom:3px}
    .ib p{margin:0;font-size:13px;font-weight:700;color:#06033A}
    .statut-badge{display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#DBEAFE;color:#1D4ED8}
    table{width:100%;border-collapse:collapse;margin-bottom:8px}
    thead th{background:#06033A;color:white;padding:9px 10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;text-align:left}
    tbody td{padding:8px 10px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
    tbody tr:nth-child(even)td{background:#f8f9fa}
    tbody tr:hover td{background:#EFF6FF}
    .tot td{background:#1B75BC;color:white;font-weight:700;padding:10px}
    .chip{display:inline-block;background:#EFF6FF;color:#1D4ED8;padding:1px 7px;border-radius:10px;font-size:10px;font-weight:700}
    .noprint{display:block}
    @media print{
      .noprint{display:none!important}
      body{margin:0;padding:10px}
      .hdr{border-radius:0}
    }
    </style></head><body>

    <div class='noprint' style='margin-bottom:14px;display:flex;gap:10px'>
      <button onclick='window.print()' style='background:#1B75BC;color:white;border:none;padding:9px 22px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:700'>🖨️ Imprimer / Enregistrer PDF</button>
      <button onclick='window.close()' style='background:#f1f5f9;color:#555;border:1px solid #ddd;padding:9px 18px;border-radius:8px;cursor:pointer;font-size:13px'>✕ Fermer</button>
    </div>

    <div class='hdr'>
      <div>
        <h1>📦 Bon de Commande</h1>
        <div class='sub'>DigiStock — EMUCI</div>
      </div>
      <div class='num'>{$cmd['numero_commande']}</div>
    </div>

    <div class='ig'>
      <div class='ib'><label>Site</label><p>".htmlspecialchars($cmd['site_nom'])."</p></div>
      <div class='ib'><label>Date commande</label><p>".date('d/m/Y',strtotime($cmd['created_at']))."</p></div>
      <div class='ib'><label>Statut</label><p><span class='statut-badge'>$statut_label</span></p></div>
      <div class='ib'><label>Demandeur</label><p>".htmlspecialchars($cmd['agent'])."</p></div>
      <div class='ib'><label>Notes</label><p style='font-weight:400;font-size:12px'>".htmlspecialchars($cmd['notes']??'—')."</p></div>
      <div class='ib'><label>Nb articles</label><p>".count($lignes)."</p></div>
    </div>

    <table>
      <thead><tr>
        <th>Article</th>
        <th style='text-align:center'>Cmd précédente</th>
        <th style='text-align:center'>Stock site</th>
        <th style='text-align:center'>Conso / sem.</th>
        <th style='text-align:center'>Qté demandée</th>
        $th_extra
      </tr></thead>
      <tbody>$rows$total_row</tbody>
    </table>

    </body></html>";
}

function _bdc_excel($cmd, $lignes, array $ctx = []) {
    $autoload = __DIR__.'/../../vendor/autoload.php';
    if (!file_exists($autoload)) { header('Content-Type: text/plain'); echo 'PhpSpreadsheet non installé.'; exit; }
    require_once $autoload;
    $coord = fn(int $col, int $row): string =>
        \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;

    $sp = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sh = $sp->getActiveSheet();
    $sh->setTitle('Bon de Commande');
    $sh->mergeCells('A1:H1');
    $sh->setCellValue('A1', 'BON DE COMMANDE — '.$cmd['numero_commande'].' — '.$cmd['site_nom']);
    $sh->getStyle('A1')->getFont()->setBold(true)->setSize(13);
    $sh->setCellValue('A2', 'Date:');   $sh->setCellValue('B2', date('d/m/Y', strtotime($cmd['created_at'])));
    $sh->setCellValue('A3', 'Agent:');  $sh->setCellValue('B3', $cmd['agent']);
    $sh->setCellValue('C2', 'Site:');   $sh->setCellValue('D2', $cmd['site_nom']);
    $sh->setCellValue('C3', 'Statut:'); $sh->setCellValue('D3', $cmd['statut']);

    $row = 5;
    $headers = ['Article','Unité','Cmd précédente','Stock site','Conso / sem.','Qté demandée','Qté livrée','Statut ligne'];
    foreach ($headers as $i => $h) {
        $cell = $coord($i + 1, $row);
        $sh->setCellValue($cell, $h);
        $sh->getStyle($cell)->getFont()->setBold(true);
        $sh->getStyle($cell)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF06033A');
        $sh->getStyle($cell)->getFont()->getColor()->setARGB('FFFFFFFF');
    }

    foreach ($lignes as $i => $l) {
        $r = $row + 1 + $i;
        $c = $ctx[$l['id']] ?? ['derniere'=>0,'stock_site'=>0,'conso'=>0];
        $sh->setCellValue($coord(1,$r), $l['libelle'].' ('.ucfirst($l['type_article']?:'article').')');
        $sh->setCellValue($coord(2,$r), $l['unite']);
        $sh->setCellValue($coord(3,$r), $c['derniere'] > 0 ? (int)$c['derniere'] : '—');
        $sh->setCellValue($coord(4,$r), (int)$c['stock_site']);
        $sh->setCellValue($coord(5,$r), $c['conso'] > 0 ? (int)$c['conso'] : '—');
        $sh->setCellValue($coord(6,$r), (int)$l['quantite']);
        $sh->setCellValue($coord(7,$r), $l['quantite_livree'] !== null ? (int)$l['quantite_livree'] : '—');
        $sh->setCellValue($coord(8,$r), $l['statut_ligne'] ?? '');
        // Mettre en rouge les lignes rejetées
        if (($l['statut_ligne'] ?? '') === 'rejete') {
            $sh->getStyle($coord(1,$r).':'.$coord(8,$r))
               ->getFont()->getColor()->setARGB('FFe74c3c');
        }
    }

    foreach (range('A','H') as $col) $sh->getColumnDimension($col)->setAutoSize(true);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="BDC_'.$cmd['numero_commande'].'.xlsx"');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sp))->save('php://output');
}

include __DIR__ . '/../templates/header.php';
?>
<style>
.cmd-steps{display:flex;gap:0;margin-bottom:22px;overflow:hidden;border-radius:12px;border:1.5px solid var(--border)}
.step{flex:1;padding:14px 16px;text-align:center;cursor:pointer;transition:background .15s;border-right:1px solid var(--border)}
.step:last-child{border-right:none}
.step .s-num{font-size:22px;font-weight:900;font-family:'Plus Jakarta Sans',sans-serif;line-height:1}
.step .s-lbl{font-size:10.5px;font-weight:600;color:var(--muted);text-transform:uppercase;margin-top:2px}
.step.s-attente .s-num{color:#92400E}.step.s-attente{background:#FEF3C7}
.step.s-valide .s-num{color:#1d4ed8}.step.s-valide{background:#DBEAFE}
.step.s-livraison .s-num{color:#f39c12}.step.s-livraison{background:#FFF7ED}
.step.s-recu .s-num{color:#065f46}.step.s-recu{background:#D1FAE5}
.step:hover{filter:brightness(.96)}
.st-en_attente{background:#fef3c7;color:#92400e}
.st-en_attente_livraison{background:#dbeafe;color:#1d4ed8}
.st-en_cours_livraison{background:#fff7ed;color:#c2410c}
.st-recu{background:#d1fae5;color:#065f46}
.st-rejete{background:#fee2e2;color:#991b1b}
.st-annule{background:#f1f5f9;color:#64748b}
.st-badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
/* Tableau coordinateur */
.coord-table th{background:#06033A;color:white}
.coord-table td{vertical-align:middle}
.qte-input{width:80px;padding:6px 8px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;text-align:center;font-weight:700}
.qte-input:focus{border-color:#1B75BC;outline:none;box-shadow:0 0 0 2px rgba(27,117,188,.15)}
.hist-chip{display:inline-block;padding:2px 7px;border-radius:6px;font-size:10.5px;font-weight:700;background:var(--tertiary);color:var(--muted)}
</style>

<!-- ÉTAPES WORKFLOW (KPIs cliquables) -->
<div class="cmd-steps">
  <div class="step s-attente" onclick="filtrer('en_attente')">
    <div class="s-num"><?= $kpi['attente'] ?></div>
    <div class="s-lbl">⏳ À valider</div>
  </div>
  <div class="step s-valide" onclick="filtrer('en_attente_livraison')">
    <div class="s-num"><?= $kpi['attente_liv'] ?></div>
    <div class="s-lbl">📋 À préparer</div>
  </div>
  <div class="step s-livraison" onclick="filtrer('en_cours_livraison')">
    <div class="s-num"><?= $kpi['en_cours'] ?></div>
    <div class="s-lbl">🚚 En livraison</div>
  </div>
  <div class="step s-recu" onclick="filtrer('recu')">
    <div class="s-num"><?= $kpi['recu'] ?></div>
    <div class="s-lbl">✅ Reçu</div>
  </div>
</div>

<!-- TOOLBAR -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px">
  <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center" id="frmFiltres">
    <?php if(!$site_force): ?>
    <select name="site" onchange="this.form.submit()" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;background:white;outline:none">
      <option value="">Tous les sites</option>
      <?php foreach($sites_list as $s): ?>
      <option value="<?= $s['id'] ?>" <?= $f_site==$s['id']?'selected':'' ?>><?= h($s['nom']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <select name="statut" id="selStatut" onchange="this.form.submit()" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;background:white;outline:none">
      <option value="">Tous statuts</option>
      <option value="en_attente" <?= $f_statut==='en_attente'?'selected':'' ?>>⏳ En attente validation</option>
      <option value="en_attente_livraison" <?= $f_statut==='en_attente_livraison'?'selected':'' ?>>📋 À préparer</option>
      <option value="en_cours_livraison" <?= $f_statut==='en_cours_livraison'?'selected':'' ?>>🚚 En livraison</option>
      <option value="recu" <?= $f_statut==='recu'?'selected':'' ?>>✅ Reçu</option>
      <option value="rejete" <?= $f_statut==='rejete'?'selected':'' ?>>❌ Rejeté</option>
    </select>
  </form>
  <?php if($can_create): ?>
  <button class="btn btn-primary" onclick="ouvrirNouvelle()">
    <i class="ph-duotone ph-plus"></i> Nouvelle commande
  </button>
  <?php endif; ?>
</div>

<!-- LISTE COMMANDES -->
<div class="card">
  <div class="card-header">
    <h3><i class="ph-duotone ph-shopping-cart"></i> Commandes <span style="font-size:12px;font-weight:400;color:var(--muted)">(<?= count($commandes) ?>)</span></h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>N° Commande</th><th>Site</th><th>Date</th>
        <th style="text-align:center">Articles</th>
        <?php if($voir_prix): ?><th style="text-align:right">Total HT</th><?php endif; ?>
        <th>Statut</th><th>Agent</th>
        <th style="text-align:center">Actions</th>
      </tr></thead>
      <tbody>
      <?php if(empty($commandes)): ?>
        <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted)">Aucune commande.</td></tr>
      <?php else: foreach($commandes as $cmd): ?>
        <tr>
          <td style="font-family:monospace;font-weight:700;color:var(--primary)"><?= h($cmd['numero_commande']) ?></td>
          <td style="font-size:13px"><?= h($cmd['site_nom']??'—') ?></td>
          <td style="font-size:12px;white-space:nowrap"><?= fmt_date($cmd['created_at'],'d/m/Y') ?></td>
          <td style="text-align:center;font-weight:700">
            <?= $cmd['nb_lignes'] ?>
            <?php if($cmd['nb_rejets']>0): ?>
              <span style="background:#FEE2E2;color:#991B1B;padding:1px 6px;border-radius:8px;font-size:10px;font-weight:700">❌<?= $cmd['nb_rejets'] ?></span>
            <?php endif; ?>
          </td>
          <?php if($voir_prix): ?>
          <td style="text-align:right;font-size:13px;font-weight:700"><?= $cmd['total_ht']>0?fmt_number($cmd['total_ht']).' FCFA':'—' ?></td>
          <?php endif; ?>
          <td>
            <span class="st-badge st-<?= $cmd['statut'] ?>">
              <?= ['en_attente'=>'⏳ À valider','en_attente_livraison'=>'📋 À préparer','en_cours_livraison'=>'🚚 En livraison','recu'=>'✅ Reçu','rejete'=>'❌ Rejeté','annule'=>'Annulé'][$cmd['statut']] ?? $cmd['statut'] ?>
            </span>
          </td>
          <td style="font-size:12px"><?= h($cmd['agent']??'—') ?></td>
          <td style="text-align:center;white-space:nowrap">
            <div style="display:flex;gap:4px;justify-content:center">
            <a href="?id=<?= $cmd['id'] ?>&export=1&format=pdf" target="_blank" class="btn btn-secondary btn-sm" title="Imprimer">🖨️</a>
            <?php if($is_superviseur && $cmd['statut']==='en_attente'): ?>
              <button class="btn btn-primary btn-sm" onclick="ouvrirValidation(<?= $cmd['id'] ?>,'<?= h($cmd['numero_commande']) ?>')">✅ Valider</button>
              <button class="btn btn-danger btn-sm" onclick="ouvrirRejet(<?= $cmd['id'] ?>,'<?= h($cmd['numero_commande']) ?>')">❌ Rejeter</button>
            <?php endif; ?>
            <?php if($is_gestionnaire && $cmd['statut']==='en_attente_livraison'): ?>
              <button class="btn btn-primary btn-sm" onclick="ouvrirLivraison(<?= $cmd['id'] ?>,'<?= h($cmd['numero_commande']) ?>')">🚚 Expédier</button>
            <?php endif; ?>
            <?php if($cmd['statut']==='en_cours_livraison' && ($is_coord || $is_gestionnaire)): ?>
              <button class="btn btn-success btn-sm" onclick="receptionner(<?= $cmd['id'] ?>)">✅ Réceptionner</button>
            <?php endif; ?>
            <?php if($cmd['statut']==='en_attente' && ($is_coord || $is_superviseur || $is_gestionnaire)): ?>
              <button class="btn btn-sm" style="background:#FEE2E2;color:#991B1B;border:1.5px solid #FCA5A5" onclick="annuler(<?= $cmd['id'] ?>)">✕</button>
            <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ═══ MODAL NOUVELLE COMMANDE ═══ -->
<?php if($can_create): ?>
<div id="modalNouvelle" style="display:none;position:fixed;inset:0;background:rgba(6,3,58,.55);z-index:1000;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto">
  <div style="background:white;border-radius:18px;padding:26px;width:820px;max-width:96vw;box-shadow:0 20px 60px rgba(0,0,0,.25);margin:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:800;color:var(--navy)">
        <i class="ph-duotone ph-shopping-cart"></i> Nouvelle commande
        <?php if($site_force): ?>
          <span style="font-size:12px;font-weight:400;color:var(--muted);margin-left:8px"><?= h(db_fetch_value("SELECT nom FROM sites WHERE id=?",[$site_force])??'') ?></span>
        <?php endif; ?>
      </h3>
      <button onclick="fermer('Nouvelle')" style="background:none;border:none;font-size:22px;cursor:pointer">✕</button>
    </div>
    <div id="alertNouvelle"></div>

    <!-- Sélecteur type article -->
    <div style="background:var(--tertiary);border-radius:12px;padding:16px;margin-bottom:16px">
      <div style="font-size:13px;font-weight:700;color:var(--navy);margin-bottom:12px">Ajouter un article à commander</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px">
        <div>
          <label style="font-size:12px;font-weight:600;color:var(--navy)">Catégorie</label>
          <select class="form-control" id="aType" onchange="onTypeChange()" style="margin-top:4px">
            <option value="article">📦 Article</option>
            <option value="pmma">🖨️ PMMA</option>
            <option value="rivet">🔩 Rivets</option>
            <option value="equipement">🖥️ Équipement</option>
            <option value="autre">📝 Autre (libre)</option>
          </select>
        </div>
        <div id="grpArticle">
          <label style="font-size:12px;font-weight:600;color:var(--navy)">Article</label>
          <select class="form-control" id="aArticle" style="margin-top:4px" onchange="onArticleChange()">
            <option value="">— Sélectionner —</option>
            <?php foreach($articles_dispo as $a): ?>
            <option value="<?= $a['id'] ?>"
              data-type="article"
              data-libelle="<?= h($a['libelle']) ?>"
              data-unite="<?= h($a['unite']??'unité') ?>"
              data-prix="<?= $a['prix_unitaire']??0 ?>"
              data-stock="<?= $a['stock_global']??0 ?>"
              data-derniere="<?= (int)($a['derniere']??0) ?>"
              data-conso="<?= (int)($a['conso']??0) ?>"
              data-stock-site="<?= (int)($a['stock_site']??0) ?>">
              <?= h('['.($a['code']??'').'] '.$a['libelle']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="grpLibre" style="display:none">
          <label style="font-size:12px;font-weight:600;color:var(--navy)">Désignation</label>
          <input type="text" class="form-control" id="aLibre" style="margin-top:4px" placeholder="Décrivez l'article…">
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end">
        <div>
          <label style="font-size:12px;font-weight:600;color:var(--navy)">Quantité à commander</label>
          <input type="number" class="form-control" id="aQte" value="1" min="1" step="1" style="margin-top:4px">
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:var(--navy)">Unité</label>
          <input type="text" class="form-control" id="aUnite" value="unité" style="margin-top:4px">
        </div>
        <button type="button" class="btn btn-primary" onclick="ajouterLigne()">+ Ajouter</button>
      </div>
    </div>

    <!-- Info contexte coordinateur -->
    <div id="infoContexte" style="display:none;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:12.5px;color:#1D4ED8">
    </div>

    <!-- Tableau récapitulatif commande -->
    <div style="border:1.5px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:16px">
      <table class="coord-table" style="width:100%;border-collapse:collapse" id="tblLignes">
        <thead><tr>
          <th style="padding:10px 13px;font-size:11px;text-align:left">Article</th>
          <th style="padding:10px 13px;font-size:11px;text-align:center">Cmd précédente</th>
          <th style="padding:10px 13px;font-size:11px;text-align:center">Stock site</th>
          <th style="padding:10px 13px;font-size:11px;text-align:center">Conso / semaine</th>
          <th style="padding:10px 13px;font-size:11px;text-align:center">Qté demandée</th>
          <?= $voir_prix ? '<th style="padding:10px 13px;font-size:11px;text-align:right">P.U.</th><th style="padding:10px 13px;font-size:11px;text-align:right">Total</th>' : '' ?>
          <th style="padding:10px 13px;width:32px"></th>
        </tr></thead>
        <tbody id="lignesBody">
          <tr><td colspan="<?= $voir_prix?8:6 ?>" style="text-align:center;padding:20px;color:var(--muted);font-size:13px">Aucun article ajouté</td></tr>
        </tbody>
      </table>
      <?= $voir_prix ? '<div id="totalHT" style="padding:10px 14px;background:var(--navy);color:white;font-weight:700;text-align:right;font-size:13px">TOTAL HT : 0 FCFA</div>' : '' ?>
    </div>

    <?php if(!$site_force): ?>
    <div class="form-group" style="margin-bottom:14px">
      <label>Site *</label>
      <select class="form-control" id="cmdSite">
        <option value="">— Sélectionner —</option>
        <?php foreach($sites_list as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['nom']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="form-group" style="margin-bottom:18px">
      <label>Notes / Observations</label>
      <textarea class="form-control" id="cmdNotes" rows="2" placeholder="Motif, urgence, précisions…"></textarea>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button class="btn btn-secondary" onclick="fermer('Nouvelle')">Annuler</button>
      <button class="btn btn-primary" onclick="envoyerCommande()">
        <i class="ph-duotone ph-paper-plane-tilt"></i> Envoyer la commande
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ═══ MODAL VALIDATION SUPERVISEUR ═══ -->
<?php if($is_superviseur): ?>
<div id="modalValidation" style="display:none;position:fixed;inset:0;background:rgba(6,3,58,.55);z-index:1000;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto">
  <div style="background:white;border-radius:18px;padding:26px;width:740px;max-width:96vw;box-shadow:0 20px 60px rgba(0,0,0,.25);margin:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:800;color:var(--navy)" id="titleValidation">Valider commande</h3>
      <button onclick="fermer('Validation')" style="background:none;border:none;font-size:22px;cursor:pointer">✕</button>
    </div>
    <div id="alertValidation"></div>
    <div style="background:#EFF6FF;border-left:4px solid #3B82F6;padding:12px 14px;border-radius:8px;margin-bottom:14px;font-size:13px;color:#1D4ED8">
      Validez ou rejetez chaque ligne de commande. Une ligne rejetée nécessite un motif obligatoire.
    </div>
    <div id="lignesValidation"></div>
    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px">
      <button class="btn btn-secondary" onclick="fermer('Validation')">Annuler</button>
      <button class="btn btn-primary" onclick="confirmerValidation()">✅ Valider la commande</button>
    </div>
  </div>
</div>

<!-- Modal rejet global -->
<div id="modalRejet" style="display:none;position:fixed;inset:0;background:rgba(6,3,58,.55);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:18px;padding:26px;width:480px;max-width:96vw;box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:var(--danger);margin-bottom:16px" id="titleRejet">Rejeter la commande</h3>
    <div id="alertRejet"></div>
    <div class="form-group">
      <label>Motif de rejet <span style="color:var(--danger)">*</span></label>
      <textarea class="form-control" id="motifRejet" rows="3" placeholder="Expliquez pourquoi cette commande est rejetée…"></textarea>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:12px">
      <button class="btn btn-secondary" onclick="fermer('Rejet')">Annuler</button>
      <button class="btn btn-danger" onclick="confirmerRejet()">❌ Rejeter</button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ═══ MODAL LIVRAISON GESTIONNAIRE ═══ -->
<?php if($is_gestionnaire): ?>
<div id="modalLivraison" style="display:none;position:fixed;inset:0;background:rgba(6,3,58,.55);z-index:1000;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto">
  <div style="background:white;border-radius:18px;padding:26px;width:740px;max-width:96vw;box-shadow:0 20px 60px rgba(0,0,0,.25);margin:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:800;color:var(--navy)" id="titleLivraison">Préparer livraison</h3>
      <button onclick="fermer('Livraison')" style="background:none;border:none;font-size:22px;cursor:pointer">✕</button>
    </div>
    <div id="alertLivraison"></div>
    <div style="background:#FEF3C7;border-left:4px solid #F59E0B;padding:12px 14px;border-radius:8px;margin-bottom:14px;font-size:13px;color:#92400E">
      Si la quantité livrée diffère de la quantité commandée, une <strong>justification est obligatoire</strong>.
    </div>
    <div id="lignesLivraison"></div>
    <div class="form-group" style="margin:14px 0">
      <label>Notes de livraison</label>
      <textarea class="form-control" id="notesLivraison" rows="2" placeholder="Informations expédition, transporteur…"></textarea>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button class="btn btn-secondary" onclick="fermer('Livraison')">Annuler</button>
      <button class="btn btn-primary" onclick="confirmerLivraison()">🚚 Expédier</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// ── Data
const articlesData = <?= json_encode(array_values($articles_dispo)) ?>;
const bobinesData  = <?= json_encode(array_values($bobines_list)) ?>;
const pmmaData     = <?= json_encode(array_values($pmma_list)) ?>;
const rivetData    = <?= json_encode($rivet_data) ?>;
const equipData    = <?= json_encode(array_values($equipements_list)) ?>;
const voirPrix     = <?= $voir_prix?'true':'false' ?>;
const siteForce    = <?= $site_force ?>;

let lignes = [];
let currentCmdId = null, lignesCmd = [];
let currentRejetId = null;

function ap(d){
  const fd=new FormData();
  for(const[k,v]of Object.entries(d))if(v!==undefined)fd.append(k,String(v));
  return fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(r=>r.json());
}
function fermer(m){document.getElementById('modal'+m).style.display='none';}
function filtrer(s){document.getElementById('selStatut').value=s;document.getElementById('frmFiltres').submit();}

// ── Nouvelle commande
function onTypeChange(){
  const type=document.getElementById('aType').value;
  const sel=document.getElementById('aArticle');
  const grpA=document.getElementById('grpArticle'), grpL=document.getElementById('grpLibre');
  if(type==='autre'){grpA.style.display='none';grpL.style.display='block';return;}
  grpA.style.display='block'; grpL.style.display='none';
  sel.innerHTML='<option value="">— Sélectionner —</option>';
  const map = {article:articlesData,bobine:bobinesData,pmma:pmmaData,rivet:rivetData,equipement:equipData};
  (map[type]||[]).forEach(a=>{
    const lib=a.libelle||a.numero||'—';
    sel.innerHTML+=`<option value="${a.id||''}"
      data-type="${type}"
      data-libelle="${lib.replace(/"/g,'&quot;')}"
      data-unite="${a.unite||'unité'}"
      data-prix="${a.prix_unitaire||0}"
      data-stock="${a.stock_global||0}"
      data-derniere="${a.derniere||0}"
      data-conso="${a.conso||0}"
      data-stock-site="${a.stock_site||0}">${lib}</option>`;
  });
}
function onArticleChange(){
  const opt=document.getElementById('aArticle').options[document.getElementById('aArticle').selectedIndex];
  if(!opt||!opt.value){document.getElementById('infoContexte').style.display='none';return;}
  document.getElementById('aUnite').value=opt.dataset.unite||'unité';
  if(!voirPrix) {
    const derniere=parseInt(opt.dataset.derniere)||0;
    const stockSite=parseInt(opt.dataset.stockSite)||0;
    const conso=parseInt(opt.dataset.conso)||0;
    const ctx=document.getElementById('infoContexte');
    ctx.style.display='block';
    ctx.innerHTML=`<strong>${opt.dataset.libelle||''}</strong> &nbsp;|&nbsp;
      Cmd précédente : <strong>${derniere ?? 0}</strong> &nbsp;|&nbsp;
      Stock site actuel : <strong>${stockSite}</strong> &nbsp;|&nbsp;
      Conso hebdo moy. : <strong>${conso}</strong>`;
    if(derniere>0) document.getElementById('aQte').value=derniere;
  }
}
function ajouterLigne(){
  const type=document.getElementById('aType').value;
  const qte=parseInt(document.getElementById('aQte').value)||1;
  const unite=document.getElementById('aUnite').value||'unité';
  let libelle='',articleId=null,prix=0,derniere=0,conso=0,stockSite=0;
  if(type==='autre'){
    libelle=document.getElementById('aLibre').value.trim();
    if(!libelle){alert('Désignation obligatoire');return;}
  } else {
    const sel=document.getElementById('aArticle');
    const opt=sel.options[sel.selectedIndex];
    if(!opt||!opt.value){alert('Sélectionner un article');return;}
    articleId=opt.value; libelle=opt.dataset.libelle||opt.text;
    prix=parseInt(opt.dataset.prix)||0;
    derniere=parseInt(opt.dataset.derniere)||0;
    conso=parseInt(opt.dataset.conso)||0;
    stockSite=parseInt(opt.dataset.stockSite)||0;
  }
  // Vérifier doublon
  if(lignes.some(l=>l.article_id==articleId&&articleId)){
    if(!confirm(`"${libelle}" est déjà dans la commande. Ajouter quand même ?`))return;
  }
  lignes.push({type,article_id:articleId,libelle,quantite:qte,unite,prix_unitaire:prix,derniere,conso,stockSite});
  renderLignes();
}
function renderLignes(){
  const tbody=document.getElementById('lignesBody');
  const cols=voirPrix?8:6;
  if(!lignes.length){
    tbody.innerHTML=`<tr><td colspan="${cols}" style="text-align:center;padding:20px;color:var(--muted);font-size:13px">Aucun article ajouté</td></tr>`;
    if(voirPrix) document.getElementById('totalHT').textContent='TOTAL HT : 0 FCFA';
    return;
  }
  let total=0;
  tbody.innerHTML=lignes.map((l,i)=>{
    const lt=l.quantite*(l.prix_unitaire||0); total+=lt;
    return `<tr style="background:${i%2?'#f8f9fa':'white'}">
      <td style="padding:9px 13px;font-size:13px;font-weight:600">${l.libelle}</td>
      <td style="padding:9px 13px;text-align:center"><span class="hist-chip">${l.derniere ?? 0}</span></td>
      <td style="padding:9px 13px;text-align:center"><span class="hist-chip">${l.stockSite ?? 0}</span></td>
      <td style="padding:9px 13px;text-align:center"><span class="hist-chip">${l.conso ?? 0}/sem</span></td>
      <td style="padding:9px 13px;text-align:center;font-weight:800;font-size:15px;color:var(--primary-d)">${l.quantite} <span style="font-size:11px;color:var(--muted)">${l.unite}</span></td>
      ${voirPrix?`<td style="padding:9px 13px;text-align:right;font-size:12px">${(l.prix_unitaire||0).toLocaleString()} FCFA</td>
        <td style="padding:9px 13px;text-align:right;font-weight:700;font-size:12px">${lt.toLocaleString()} FCFA</td>`:''}
      <td style="padding:4px;text-align:center"><button onclick="suppLigne(${i})" style="background:#FEE2E2;color:#991B1B;border:none;border-radius:6px;padding:3px 8px;cursor:pointer;font-size:12px">✕</button></td>
    </tr>`;
  }).join('');
  if(voirPrix) document.getElementById('totalHT').textContent=`TOTAL HT : ${total.toLocaleString()} FCFA`;
}
function suppLigne(i){lignes.splice(i,1);renderLignes();}
function ouvrirNouvelle(){
  lignes=[];renderLignes();
  document.getElementById('cmdNotes').value='';
  document.getElementById('alertNouvelle').innerHTML='';
  document.getElementById('infoContexte').style.display='none';
  document.getElementById('modalNouvelle').style.display='flex';
}
async function envoyerCommande(){
  if(!lignes.length){document.getElementById('alertNouvelle').innerHTML='<div class="alert alert-danger">Ajoutez au moins un article.</div>';return;}
  const siteId=document.getElementById('cmdSite')?.value||String(siteForce);
  if(!siteId||siteId==='0'){document.getElementById('alertNouvelle').innerHTML='<div class="alert alert-danger">Sélectionnez un site.</div>';return;}
  const d=await ap({action:'creer_commande',lignes:JSON.stringify(lignes),notes:document.getElementById('cmdNotes').value,site_id:siteId});
  if(d.success){toast(d.message,'success');fermer('Nouvelle');setTimeout(()=>location.reload(),800);}
  else document.getElementById('alertNouvelle').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;
}

// ── Validation superviseur
async function ouvrirValidation(cmdId,numCmd){
  currentCmdId=cmdId;
  document.getElementById('titleValidation').textContent=`✅ Valider — ${numCmd}`;
  document.getElementById('alertValidation').innerHTML='';
  const d=await ap({action:'get_lignes',cmd_id:cmdId});
  lignesCmd=d.data||[];
  const container=document.getElementById('lignesValidation');
  container.innerHTML=lignesCmd.map((l,i)=>`
    <div style="background:#f8f9fa;border-radius:10px;padding:13px;margin-bottom:8px">
      <div style="display:grid;grid-template-columns:1fr 130px 1fr;gap:12px;align-items:start">
        <div>
          <div style="font-size:13.5px;font-weight:700;color:var(--navy)">${l.libelle}</div>
          <div style="font-size:11px;color:var(--muted)">${l.type_article||'article'} · Qté : <strong>${l.quantite} ${l.unite||''}</strong></div>
        </div>
        <div>
          <label style="font-size:11px;font-weight:700;color:var(--navy);display:block;margin-bottom:4px">Décision</label>
          <select class="form-control statut-ligne" data-idx="${i}" onchange="checkMotifRejet(${i})"
            style="padding:7px;border-radius:8px;font-size:13px">
            <option value="valide">✅ Valider</option>
            <option value="rejete">❌ Rejeter</option>
          </select>
        </div>
        <div id="motifLigne${i}" style="display:none">
          <label style="font-size:11px;font-weight:700;color:var(--danger);display:block;margin-bottom:4px">Motif rejet *</label>
          <input type="text" class="form-control motif-ligne" data-idx="${i}"
            placeholder="Raison du rejet…" style="padding:7px;border-radius:8px;font-size:12px;border-color:var(--danger)">
        </div>
      </div>
    </div>`).join('');
  document.getElementById('modalValidation').style.display='flex';
}
function checkMotifRejet(i){
  const sel=document.querySelector(`.statut-ligne[data-idx="${i}"]`);
  document.getElementById(`motifLigne${i}`).style.display=sel.value==='rejete'?'block':'none';
}
async function confirmerValidation(){
  const lignesData=lignesCmd.map((l,i)=>{
    const sl=document.querySelector(`.statut-ligne[data-idx="${i}"]`)?.value||'valide';
    const motif=document.querySelector(`.motif-ligne[data-idx="${i}"]`)?.value.trim()||'';
    return{ligne_id:l.id,libelle:l.libelle,statut_ligne:sl,motif_rejet:motif};
  });
  const d=await ap({action:'valider_commande',cmd_id:currentCmdId,lignes:JSON.stringify(lignesData)});
  if(d.success){toast(d.message,'success');fermer('Validation');setTimeout(()=>location.reload(),800);}
  else document.getElementById('alertValidation').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;
}

// ── Rejet global
function ouvrirRejet(cmdId,numCmd){
  currentRejetId=cmdId;
  document.getElementById('titleRejet').textContent=`❌ Rejeter — ${numCmd}`;
  document.getElementById('motifRejet').value='';
  document.getElementById('alertRejet').innerHTML='';
  document.getElementById('modalRejet').style.display='flex';
}
async function confirmerRejet(){
  const motif=document.getElementById('motifRejet').value.trim();
  if(!motif){document.getElementById('alertRejet').innerHTML='<div class="alert alert-danger">Motif obligatoire.</div>';return;}
  const d=await ap({action:'rejeter_commande',cmd_id:currentRejetId,motif});
  if(d.success){toast(d.message,'success');fermer('Rejet');setTimeout(()=>location.reload(),800);}
  else document.getElementById('alertRejet').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;
}

// ── Livraison gestionnaire
async function ouvrirLivraison(cmdId,numCmd){
  currentCmdId=cmdId;
  document.getElementById('titleLivraison').textContent=`🚚 Expédier — ${numCmd}`;
  document.getElementById('alertLivraison').innerHTML='';
  document.getElementById('notesLivraison').value='';
  const d=await ap({action:'get_lignes',cmd_id:cmdId});
  lignesCmd=d.data||[];
  document.getElementById('lignesLivraison').innerHTML=lignesCmd.map((l,i)=>`
    <div style="background:#f8f9fa;border-radius:10px;padding:13px;margin-bottom:8px">
      <div style="display:grid;grid-template-columns:1fr 110px 1fr;gap:12px;align-items:end">
        <div>
          <div style="font-size:13.5px;font-weight:700;color:var(--navy)">${l.libelle}</div>
          <div style="font-size:11px;color:var(--muted)">Commandé : <strong>${l.quantite}</strong> ${l.unite||''} · ${l.type_article||'article'}</div>
          ${l.statut_ligne==='rejete'?'<span style="background:#FEE2E2;color:#991B1B;padding:2px 7px;border-radius:8px;font-size:10.5px;font-weight:700">❌ Ligne rejetée — '+l.motif_rejet+'</span>':''}
        </div>
        <div>
          <label style="font-size:11px;font-weight:700;color:var(--navy);display:block;margin-bottom:4px">Qté livrée *</label>
          <input type="number" class="form-control qte-liv"
            data-idx="${i}" data-qte="${l.quantite}" data-libelle="${l.libelle}"
            value="${l.quantite}" min="0" step="1" onchange="checkEcart(${i})"
            style="border-radius:8px;text-align:center;font-weight:800"
            ${l.statut_ligne==='rejete'?'disabled value="0"':''}>
        </div>
        <div id="motifGrp${i}" style="display:none">
          <label style="font-size:11px;font-weight:700;color:var(--warning);display:block;margin-bottom:4px">Motif écart *</label>
          <input type="text" class="form-control motif-ecart" data-idx="${i}"
            placeholder="Justification…" style="border-radius:8px;font-size:12px;border-color:var(--warning)">
        </div>
        <div id="okLabel${i}" style="display:none;font-size:13px;color:var(--success);font-weight:600;padding-top:20px">✅ OK</div>
      </div>
    </div>`).join('');
  document.getElementById('modalLivraison').style.display='flex';
}
function checkEcart(i){
  const qteInput=document.querySelector(`.qte-liv[data-idx="${i}"]`);
  const qteLiv=parseInt(qteInput.value)||0;
  const qteCmd=parseInt(qteInput.dataset.qte)||0;
  const motifGrp=document.getElementById(`motifGrp${i}`);
  const okLabel=document.getElementById(`okLabel${i}`);
  if(qteLiv!==qteCmd){motifGrp.style.display='block';okLabel.style.display='none';}
  else{motifGrp.style.display='none';okLabel.style.display='block';}
}
async function confirmerLivraison(){
  const qteInputs=document.querySelectorAll('.qte-liv');
  const lignesData=[]; let valid=true;
  qteInputs.forEach(inp=>{
    const i=parseInt(inp.dataset.idx);
    const qteLiv=parseInt(inp.value)||0;
    const qteCmd=parseInt(inp.dataset.qte)||0;
    const motifEl=document.querySelector(`.motif-ecart[data-idx="${i}"]`);
    const motif=motifEl?motifEl.value.trim():'';
    if(qteLiv!==qteCmd&&!motif){inp.style.borderColor='var(--danger)';valid=false;}
    lignesData.push({
      ligne_id:lignesCmd[i]?.id,
      article_id:lignesCmd[i]?.article_id||null,
      libelle:inp.dataset.libelle,
      quantite:qteCmd,
      unite:lignesCmd[i]?.unite||'unité',
      quantite_livree:qteLiv,
      motif_ecart:motif||null
    });
  });
  if(!valid){document.getElementById('alertLivraison').innerHTML='<div class="alert alert-danger">Justification obligatoire pour les écarts.</div>';return;}
  const d=await ap({action:'emettre_livraison',cmd_id:currentCmdId,notes_livraison:document.getElementById('notesLivraison').value,lignes:JSON.stringify(lignesData)});
  if(d.success){toast(d.message,'success');fermer('Livraison');setTimeout(()=>location.reload(),800);}
  else document.getElementById('alertLivraison').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;
}
async function receptionner(id){if(!confirm('Confirmer la réception de cette commande et mettre à jour le stock site ?'))return;const d=await ap({action:'receptionner',cmd_id:id});if(d.success){alert('✅ '+d.message);location.reload();}else alert('❌ '+d.message);}
async function annuler(id){if(!confirm('Annuler cette commande ?'))return;const d=await ap({action:'annuler',cmd_id:id});if(d.success){toast(d.message,'success');setTimeout(()=>location.reload(),800);}else toast(d.message,'danger');}
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
