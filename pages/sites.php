<?php
// ============================================================
//  pages/sites.php  —  Gestion des sites + calcul capacité
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

require_auth();
require_permission('sites', 'can_read');

$user        = current_user();
$page_title  = 'Sites';
$active_page = 'sites';

// ============================================================
//  TRAITEMENT AJAX
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // ── CRÉER SITE
    if ($action === 'create') {
        require_permission('sites', 'can_create');
        $code      = strtoupper(trim($_POST['code']      ?? ''));
        $nom       = trim($_POST['nom']                  ?? '');
        $type      = trim($_POST['type']                 ?? '');
        $adresse   = trim($_POST['adresse']              ?? '');
        $ville     = trim($_POST['ville']                ?? '');
        $resp_id   = (int)($_POST['responsable_id']      ?? 0) ?: null;
        $opt_caisse= in_array($type,['entrepot','siege']) ? 0 : (int)($_POST['option_caisse'] ?? 0);

        if (!$code || !$nom || !$type) json_response(false, 'Code, nom et type sont obligatoires.');
        if (db_fetch_value("SELECT COUNT(*) FROM sites WHERE code=?", [$code]) > 0)
            json_response(false, "Le code site $code existe déjà.");

        $is_mobile   = !empty($_POST['mobile']) ? 1 : 0;
        $date_debut  = trim($_POST['date_debut_mission'] ?? '') ?: null;
        $date_fin    = trim($_POST['date_fin_mission'] ?? '') ?: null;
        $latitude    = trim($_POST['latitude'] ?? '') ?: null;
        $longitude   = trim($_POST['longitude'] ?? '') ?: null;

        db_query(
            "INSERT INTO sites (code,nom,type,option_caisse,adresse,ville,responsable_id) VALUES (?,?,?,?,?,?,?)",
            [$code, $nom, $type, $opt_caisse, $adresse, $ville, $resp_id]
        );
        $id = (int)db_last_id();
        audit_log($user['id'], 'CREATE', 'sites', $id, "Création site $code — $nom");
        json_response(true, 'Site créé.', ['id' => $id]);
    }

    // ── MODIFIER SITE
    if ($action === 'update') {
        require_permission('sites', 'can_update');
        $id      = (int)($_POST['id']              ?? 0);
        $nom     = trim($_POST['nom']              ?? '');
        $type    = trim($_POST['type']             ?? '');
        $adresse    = trim($_POST['adresse']          ?? '');
        $ville      = trim($_POST['ville']             ?? '');
        $actif      = (int)($_POST['actif']            ?? 1);
        $resp_id    = (int)($_POST['responsable_id']   ?? 0) ?: null;
        $opt_caisse = in_array($type,['entrepot','siege']) ? 0 : (int)($_POST['option_caisse'] ?? 0);
        if (!$nom || !$type) json_response(false, 'Nom et type obligatoires.');
        $old = db_fetch_one("SELECT * FROM sites WHERE id=?", [$id]);
        db_query("UPDATE sites SET nom=?,type=?,option_caisse=?,adresse=?,ville=?,responsable_id=?,actif=? WHERE id=?",
            [$nom, $type, $opt_caisse, $adresse, $ville, $resp_id, $actif, $id]);
        audit_log($user['id'], 'UPDATE', 'sites', $id, "Modification site {$old['code']}");
        json_response(true, 'Site mis à jour.');
    }

    // ── LIER / CRÉER site depuis un site EMUCI inconnu
    if ($action === 'lier_site_emuci') {
        require_permission('sites', 'can_update');
        $inconnu_id = (int)($_POST['inconnu_id'] ?? 0);
        $decision   = trim($_POST['decision']    ?? ''); // 'lier' | 'creer' | 'ignorer'
        $site_id    = (int)($_POST['site_id']    ?? 0);

        $inconnu = db_fetch_one("SELECT * FROM emuci_sites_inconnus WHERE id=?", [$inconnu_id]);
        if (!$inconnu) json_response(false,'Entrée introuvable.');

        if ($decision === 'ignorer') {
            db_query("UPDATE emuci_sites_inconnus SET statut='ignore',traite_par=?,traite_at=NOW() WHERE id=?",
                [$user['id'],$inconnu_id]);
            json_response(true,'Ignoré.');
        }

        if ($decision === 'lier') {
            if (!$site_id) json_response(false,'Site DigiStock obligatoire.');
            $nom_emuci = $inconnu['nom_emuci'];
            db_query("UPDATE sites SET nom_emuci=?, nom=? WHERE id=?", [$nom_emuci, $nom_emuci, $site_id]);
            // Marquer TOUTES les occurrences du même nom EMUCI comme liées
            db_query("UPDATE emuci_sites_inconnus SET statut='lie',site_id_lie=?,traite_par=?,traite_at=NOW() WHERE nom_emuci=?",
                [$site_id,$user['id'],$nom_emuci]);
            // Mettre à jour les imports existants qui avaient ce site sans site_id
            db_query("UPDATE import_optoplate SET site_id=? WHERE site_nom_emuci=? AND site_id IS NULL", [$site_id,$nom_emuci]);
            db_query("UPDATE import_optotrace  SET site_id=? WHERE site_nom_emuci=? AND site_id IS NULL", [$site_id,$nom_emuci]);
            // Mettre à jour les bobines op_bobines qui n'avaient pas de site_id
            db_query("UPDATE op_bobines b
                      JOIN import_optotrace ot ON ot.keyname = b.numero AND ot.site_nom_emuci = ?
                      SET b.site_id = ?
                      WHERE b.site_id IS NULL", [$nom_emuci, $site_id]);
            $nom = db_fetch_value("SELECT nom FROM sites WHERE id=?",[$site_id]);
            audit_log($user['id'],'UPDATE','sites',$site_id,"Mapping EMUCI '$nom_emuci' → '$nom'");
            json_response(true,"Site lié. Tous les imports et bobines avec '$nom_emuci' ont été mis à jour.");
        }

        if ($decision === 'creer') {
            $nom_nouveau = trim($_POST['nom_nouveau'] ?? '') ?: $inconnu['nom_emuci'];
            $type_nouveau= trim($_POST['type_nouveau']?? 'pose');
            $nom_emuci   = $inconnu['nom_emuci'];
            $code_nouveau= strtoupper(substr(preg_replace('/[^A-Z0-9]/','',$nom_emuci),0,6));
            $base = $code_nouveau; $suffix = 1;
            while (db_fetch_value("SELECT COUNT(*) FROM sites WHERE code=?",[$code_nouveau]) > 0)
                $code_nouveau = $base . $suffix++;
            db_query("INSERT INTO sites (code,nom,type,nom_emuci,actif) VALUES (?,?,?,?,1)",
                [$code_nouveau,$nom_nouveau,$type_nouveau,$nom_emuci]);
            $new_id = (int)db_last_id();
            // Marquer TOUTES les occurrences du même nom EMUCI
            db_query("UPDATE emuci_sites_inconnus SET statut='cree',site_id_lie=?,traite_par=?,traite_at=NOW() WHERE nom_emuci=?",
                [$new_id,$user['id'],$nom_emuci]);
            // Mettre à jour les imports existants
            db_query("UPDATE import_optoplate SET site_id=? WHERE site_nom_emuci=? AND site_id IS NULL", [$new_id,$nom_emuci]);
            db_query("UPDATE import_optotrace  SET site_id=? WHERE site_nom_emuci=? AND site_id IS NULL", [$new_id,$nom_emuci]);
            // Mettre à jour les bobines op_bobines qui n'avaient pas de site_id
            db_query("UPDATE op_bobines b
                      JOIN import_optotrace ot ON ot.keyname = b.numero AND ot.site_nom_emuci = ?
                      SET b.site_id = ?
                      WHERE b.site_id IS NULL", [$nom_emuci, $new_id]);
            audit_log($user['id'],'CREATE','sites',$new_id,"Création depuis EMUCI '$nom_emuci'");
            json_response(true,"Site '$nom_nouveau' créé. Tous les imports et bobines avec '$nom_emuci' ont été mis à jour.");
        }

        json_response(false,'Décision invalide.');
    }

    // ── SUPPRIMER SITE
    if ($action === 'delete') {
        require_permission('sites', 'can_delete');
        $id  = (int)($_POST['id'] ?? 0);
        $cnt = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE site_id=? AND actif=1", [$id]);
        if ($cnt > 0) json_response(false, "Impossible : $cnt équipement(s) actif(s) sur ce site.");
        $old = db_fetch_one("SELECT code,nom FROM sites WHERE id=?", [$id]);
        db_query("UPDATE sites SET actif=0 WHERE id=?", [$id]);
        audit_log($user['id'], 'DELETE', 'sites', $id, "Désactivation site {$old['code']}");
        json_response(true, 'Site désactivé.');
    }

    // ── GET ONE
    if ($action === 'get') {
        $id  = (int)($_POST['id'] ?? 0);
        $row = db_fetch_one(
            "SELECT s.*, CONCAT(u.prenom,' ',u.nom) AS responsable_nom
             FROM sites s LEFT JOIN users u ON u.id=s.responsable_id
             WHERE s.id=?", [$id]
        );
        if (!$row) json_response(false, 'Introuvable.');

        // Équipements par type sur ce site
        $row['equip_par_type'] = db_fetch_all(
            "SELECT n.code, n.libelle, COUNT(e.id) AS total, n.id AS nom_id
             FROM equipements e JOIN nomenclatures n ON n.id=e.nomenclature_id
             WHERE e.site_id=? AND e.actif=1 GROUP BY n.id ORDER BY n.libelle",
            [$id]
        );
        // Utilisateurs sur ce site
        $row['utilisateurs'] = db_fetch_all(
            "SELECT u.id, CONCAT(u.prenom,' ',u.nom) AS nom, u.email, r.nom AS role
             FROM users u JOIN roles r ON r.id=u.role_id
             WHERE u.site_id=? AND u.actif=1", [$id]
        );
        // Consommables sur ce site
        $row['consommables'] = db_fetch_all(
            "SELECT c.libelle, sc.quantite, c.unite, c.seuil_alerte
             FROM stock_consommables_site sc JOIN consommables c ON c.id=sc.consommable_id
             WHERE sc.site_id=?", [$id]
        );
        json_response(true, '', $row);
    }

    // ── CALCUL CAPACITÉ (combien de sites peut-on créer avec le stock actuel)
    if ($action === 'calc_capacite') {
        $type_site = trim($_POST['type_site'] ?? '');
        if (!$type_site) json_response(false, 'Type de site obligatoire.');

        // Besoins en équipements pour ce type de site
        $besoins = db_fetch_all(
            "SELECT cs.nomenclature_id, cs.quantite AS requis, cs.optionnel,
                    n.code, n.libelle,
                    COUNT(e.id) AS stock_disponible
             FROM configurations_site cs
             JOIN nomenclatures n ON n.id=cs.nomenclature_id
             LEFT JOIN equipements e ON e.nomenclature_id=cs.nomenclature_id
                 AND e.actif=1 AND e.site_id IS NULL AND e.etat IN ('neuf','bon')
             WHERE cs.type_site=?
             GROUP BY cs.nomenclature_id",
            [$type_site]
        );

        if (empty($besoins)) {
            // Pas de config définie : charger quand même le stock global
            json_response(false, "Aucune configuration définie pour le type « $type_site ». Veuillez configurer les besoins dans l'admin.");
        }

        // Calcul du nombre de sites possibles (min des ratios stock/besoin)
        $nb_sites = PHP_INT_MAX;
        $details  = [];
        foreach ($besoins as $b) {
            $ratio  = $b['requis'] > 0 ? floor($b['stock_disponible'] / $b['requis']) : PHP_INT_MAX;
            if (!$b['optionnel'] && $ratio < $nb_sites) $nb_sites = $ratio;
            $details[] = [
                'code'       => $b['code'],
                'libelle'    => $b['libelle'],
                'requis'     => (int)$b['requis'],
                'disponible' => (int)$b['stock_disponible'],
                'couvre'     => $ratio === PHP_INT_MAX ? '∞' : $ratio,
                'optionnel'  => (bool)$b['optionnel'],
                'ok'         => $b['stock_disponible'] >= $b['requis'],
            ];
        }
        if ($nb_sites === PHP_INT_MAX) $nb_sites = 0;

        json_response(true, '', ['nb_sites' => $nb_sites, 'details' => $details, 'type' => $type_site]);
    }

    // ── SAUVEGARDER CONFIG SITE (besoins par type)
    if ($action === 'save_config') {
        require_permission('sites', 'can_update');
        $type_site = trim($_POST['type_site'] ?? '');
        $items     = json_decode($_POST['items'] ?? '[]', true);
        if (!$type_site || !is_array($items)) json_response(false, 'Données invalides.');

        db_begin();
        try {
            db_query("DELETE FROM configurations_site WHERE type_site=?", [$type_site]);
            foreach ($items as $item) {
                $nom_id  = (int)($item['nomenclature_id'] ?? 0);
                $qte     = max(1, (int)($item['quantite'] ?? 1));
                $opt     = (int)(!empty($item['optionnel']));
                if ($nom_id) {
                    db_query("INSERT INTO configurations_site (type_site,nomenclature_id,quantite,optionnel) VALUES (?,?,?,?)",
                        [$type_site, $nom_id, $qte, $opt]);
                }
            }
            audit_log($user['id'], 'UPDATE', 'sites', null, "Configuration type site $type_site mise à jour");
            db_commit();
            json_response(true, 'Configuration enregistrée.');
        } catch (Exception $e) {
            db_rollback();
            json_response(false, 'Erreur : ' . $e->getMessage());
        }
    }

    json_response(false, 'Action inconnue.');
}

// ============================================================
//  LISTE DES SITES
// ============================================================
$sites = db_fetch_all(
    "SELECT s.*,
            CONCAT(u.prenom,' ',u.nom) AS responsable_nom,
            COUNT(DISTINCT e.id)       AS nb_equip,
            COUNT(DISTINCT us.id)      AS nb_users
     FROM sites s
     LEFT JOIN users u  ON u.id=s.responsable_id
     LEFT JOIN equipements e ON e.site_id=s.id AND e.actif=1
     LEFT JOIN users us ON us.site_id=s.id AND us.actif=1
     GROUP BY s.id ORDER BY s.actif DESC, s.nom ASC"
);

$users_list      = db_fetch_all("SELECT id,prenom,nom FROM users WHERE actif=1 ORDER BY prenom");
$nomenclatures   = db_fetch_all("SELECT id,code,libelle FROM nomenclatures ORDER BY libelle");
$types_site      = ['saisie','pose','mixte','entrepot','siege'];
$types_labels    = ['saisie'=>'Saisie','pose'=>'Pose','mixte'=>'Mixte','entrepot'=>'Entrepôt','siege'=>'Siège'];
// Note: Caissier = option sur tout type de site (option_caisse)

// Stock global disponible (non affecté, en bon état) par nomenclature
$stock_dispo = db_fetch_all(
    "SELECT n.id, n.code, n.libelle, COUNT(e.id) AS dispo
     FROM nomenclatures n
     LEFT JOIN equipements e ON e.nomenclature_id=n.id AND e.actif=1 AND e.site_id IS NULL AND e.etat IN ('neuf','bon')
     GROUP BY n.id ORDER BY n.libelle"
);

// ── Sites EMUCI inconnus en attente de traitement
$sites_inconnus_emuci = db_fetch_all(
    "SELECT * FROM emuci_sites_inconnus WHERE statut='en_attente'
     ORDER BY nb_occurrences DESC, derniere_apparition DESC"
);
$nb_inconnus = count($sites_inconnus_emuci);

$all_sites_list = db_fetch_all("SELECT id,nom FROM sites WHERE actif=1 ORDER BY nom");

include __DIR__ . '/../templates/header.php';
?>
<style>
/* LAYOUT */
.sites-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:20px;margin-bottom:28px}
.site-card{background:white;border:1px solid var(--border);border-radius:16px;overflow:hidden;transition:box-shadow .2s,transform .2s}
.site-card:hover{box-shadow:0 8px 28px rgba(6,3,58,.1);transform:translateY(-3px)}
.site-card.inactive{opacity:.5}
.sc-header{padding:20px 22px 16px;display:flex;align-items:flex-start;gap:14px}
.sc-icon{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0}
.sc-icon.saisie  {background:#e6f1fb}
.sc-icon.pose    {background:#e8f8ef}
.sc-icon.mixte   {background:#fff3e8}
.sc-icon.caissier{background:#f3eeff}
.sc-icon.entrepot{background:#e6f9f7}
.sc-icon.siege   {background:#fdeaea}
.sc-info{flex:1;min-width:0}
.sc-info h4{font-family:'Montserrat',sans-serif;font-size:15px;font-weight:800;color:var(--navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:4px}
.sc-info span{font-size:12.5px;color:var(--muted)}
.sc-type{font-size:10.5px;font-weight:700;padding:3px 10px;border-radius:20px;text-transform:uppercase;letter-spacing:.5px}
.sc-type.saisie  {background:#e6f1fb;color:var(--blue)}
.sc-type.pose    {background:#e8f8ef;color:#166534}
.sc-type.mixte   {background:#fff3e8;color:#9a3412}
.sc-type.caissier{background:#f3eeff;color:#6b21a8}
.sc-type.entrepot{background:#e6f9f7;color:#134e4a}
.sc-type.siege   {background:#fdeaea;color:#991b1b}
.sc-divider{height:1px;background:var(--border);margin:0 22px}
.sc-stats{display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;padding:16px 22px}
.sc-stat{text-align:center}
.sc-stat .sv{font-family:'Montserrat',sans-serif;font-size:24px;font-weight:900;color:var(--navy);line-height:1}
.sc-stat .sl{font-size:11px;color:var(--muted);margin-top:4px;font-weight:500}
.sc-stat-sep{width:1px;background:var(--border)}
.sc-actions{padding:12px 18px;display:flex;gap:8px;background:var(--lighter);border-top:1px solid var(--border);flex-wrap:wrap;align-items:center}

/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;z-index:500;background:rgba(13,31,53,.5);backdrop-filter:blur(4px);align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:white;border-radius:16px;max-width:95vw;max-height:92vh;overflow-y:auto;animation:mIn .25s cubic-bezier(.22,1,.36,1)}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.mhdr{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:white;z-index:10}
.mhdr h3{font-family:'Montserrat',sans-serif;font-size:17px;font-weight:700}
.mclose{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:none;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center}
.mbody{padding:24px}
.mfoot{padding:14px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;position:sticky;bottom:0;background:white}

/* CAPACITÉ */
.capa-result{text-align:center;padding:28px;background:linear-gradient(135deg,var(--navy),#1a3c5e);border-radius:12px;color:white;margin-bottom:20px}
.capa-number{font-family:'Montserrat',sans-serif;font-size:64px;font-weight:800;line-height:1}
.capa-label{font-size:14px;opacity:.75;margin-top:6px}
.capa-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)}
.capa-row:last-child{border-bottom:none}
.capa-ok{color:var(--success);font-weight:700;font-size:18px}
.capa-ko{color:var(--danger);font-weight:700;font-size:18px}
.capa-bar{flex:1;height:6px;background:var(--border);border-radius:3px;overflow:hidden}
.capa-fill{height:100%;border-radius:3px}
.capa-fill.ok{background:var(--success)}
.capa-fill.ko{background:var(--danger)}

/* CONFIG TABLE */
.cfg-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)}
.cfg-row:last-child{border-bottom:none}
.stock-dispo-badge{font-size:11px;padding:2px 8px;border-radius:10px;font-weight:600}
.stock-dispo-badge.ok{background:#d5f5e3;color:#1e8449}
.stock-dispo-badge.low{background:#fdf0ef;color:#922b21}

/* TABS */
.tabs{display:flex;border-bottom:1px solid var(--border);margin-bottom:20px}
.tab-btn{padding:12px 20px;font-size:13.5px;font-weight:500;color:var(--muted);cursor:pointer;border-bottom:2px solid transparent;background:none;border-top:none;border-left:none;border-right:none;transition:all .15s;font-family:'DM Sans',sans-serif}
.tab-btn.active{color:var(--blue-mid, #1a56a0);border-bottom-color:var(--blue-mid, #1a56a0)}
.tab-pane{display:none}
.tab-pane.active{display:block}
</style>

<!-- TOOLBAR -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <div style="font-size:13.5px;color:var(--muted)"><?= count($sites) ?> site(s) au total</div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn btn-secondary" onclick="openCapa()">🧮 Calculateur de capacité</button>
    <button class="btn btn-secondary" onclick="openConfig()">⚙️ Config types de site</button>
    <?php if(can('sites','can_create')): ?>
    <button class="btn btn-primary" onclick="openMS()">+ Nouveau site</button>
    <?php endif; ?>
  </div>
</div>

<!-- STOCK DISPONIBLE RÉSUMÉ -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header"><h3>📦 Stock disponible (non affecté, état neuf/bon)</h3></div>
  <div class="card-body" style="padding:16px 20px">
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <?php foreach($stock_dispo as $s): ?>
      <div style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid var(--border);border-radius:20px;font-size:12.5px;background:<?= $s['dispo']>0?'white':'#fdf0ef' ?>">
        <strong style="font-family:'Montserrat',sans-serif;font-size:15px;color:<?= $s['dispo']>0?'var(--navy)':'var(--danger)' ?>"><?= $s['dispo'] ?></strong>
        <span><?= h($s['libelle']) ?></span>
        <span style="font-size:10px;color:var(--muted)"><?= h($s['code']) ?></span>
      </div>
      <?php endforeach; ?>
      <?php if(empty($stock_dispo)): ?>
      <span style="color:var(--muted);font-size:13px">Aucun équipement configuré.</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- GRILLE DES SITES -->
<div class="sites-grid">
<?php foreach($sites as $s):
  $icons = ['saisie'=>'💻','pose'=>'🛠️','mixte'=>'🔀','entrepot'=>'🏭','siege'=>'🏛️'];
?>
<div class="site-card <?= $s['actif']?'':'inactive' ?>">
  <div class="sc-header">
    <div class="sc-icon <?= $s['type'] ?>" style="font-size:26px"><?= $icons[$s['type']] ?? '🏢' ?></div>
    <div class="sc-info">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px">
        <h4><?= h($s['nom']) ?></h4>
        <?php if(!$s['actif']): ?><span style="font-size:10px;color:var(--danger);font-weight:700">INACTIF</span><?php endif; ?>
      </div>
      <span style="font-size:11px;color:var(--muted)"><?= h($s['code']) ?> · <?= h($s['ville'] ?? '') ?></span>
    </div>
    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
      <span class="sc-type <?= $s['type'] ?>"><?= $types_labels[$s['type']] ?? $s['type'] ?></span>
      <?php if(!empty($s['option_caisse'])): ?><span style="font-size:10px;background:#e8f5e9;color:#2e7d32;padding:2px 7px;border-radius:8px;font-weight:600">💰 Caisse</span><?php endif; ?>
    </div>
  </div>
  <div class="sc-divider"></div>
  <div class="sc-stats">
    <div class="sc-stat">
      <div class="sv"><?= $s['nb_equip'] ?></div>
      <div class="sl">Équipements</div>
    </div>
    <div class="sc-stat" style="border-left:1px solid var(--border);border-right:1px solid var(--border)">
      <div class="sv"><?= $s['nb_users'] ?></div>
      <div class="sl">Utilisateurs</div>
    </div>
    <div class="sc-stat">
      <div class="sv" style="font-size:12px;font-weight:600;color:var(--muted);line-height:1.3"><?= h(substr($s['responsable_nom']??'—',0,14)) ?></div>
      <div class="sl">Responsable</div>
    </div>
  </div>
  <div class="sc-actions">
    <button class="btn btn-secondary btn-sm" onclick="viewS(<?= $s['id'] ?>)">Détail</button>
    <?php if(can('sites','can_update')): ?>
    <button class="btn btn-secondary btn-sm" onclick="editS(<?= $s['id'] ?>)">✏️</button>
    <?php endif; ?>
    <?php if(can('sites','can_delete') && $s['nb_equip']==0): ?>
    <button class="btn btn-danger btn-sm" onclick="delS(<?= $s['id'] ?>,'<?= h($s['nom']) ?>')">🗑</button>
    <?php endif; ?>
    <a href="equipements.php?site=<?= $s['id'] ?>" class="btn btn-primary btn-sm" style="margin-left:auto">Équipements →</a>
  </div>
</div>
<?php endforeach; ?>
<?php if(empty($sites)): ?>
<div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--muted)">
  <div style="font-size:48px;margin-bottom:12px">🏢</div>
  <p>Aucun site configuré. Créez votre premier site.</p>
</div>
<?php endif; ?>
</div>

<!-- ===== MODAL CRÉER / MODIFIER SITE ===== -->
<div class="modal-overlay" id="mS">
  <div class="modal" style="width:580px">
    <div class="mhdr"><h3 id="mST">Nouveau site</h3><button class="mclose" onclick="closeMX('mS')">✕</button></div>
    <div class="mbody">
      <div id="mSAlert"></div>
      <input type="hidden" id="sId">
      <div class="form-row cols-2">
        <div class="form-group"><label>Code * <span style="font-size:11px;color:var(--muted)">(ex: SITE-ABJ-01)</span></label>
          <input type="text" class="form-control" id="sCode" placeholder="ABJ-01" oninput="this.value=this.value.toUpperCase()">
        </div>
        <div class="form-group"><label>Type *</label>
          <select class="form-control" id="sType" onchange="toggleCaisse(this.value)">
            <?php foreach($types_labels as $k=>$l): ?>
            <option value="<?= $k ?>"><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group"><label>Nom du site *</label>
        <input type="text" class="form-control" id="sNom" placeholder="Agence Cocody">
      </div>
      <div class="form-row cols-2">
        <div class="form-group"><label>Ville</label>
          <input type="text" class="form-control" id="sVille" placeholder="Abidjan">
        </div>
        <div class="form-group"><label>Responsable</label>
          <select class="form-control" id="sResp">
            <option value="">— Non assigné —</option>
            <?php foreach($users_list as $u): ?>
            <option value="<?= $u['id'] ?>"><?= h($u['prenom'].' '.$u['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group"><label>Adresse</label>
        <textarea class="form-control" id="sAdresse" rows="2" placeholder="Adresse complète"></textarea>
      </div>
      <div class="form-group" id="sCaisseWrap" style="display:none">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:12px;background:var(--lighter);border-radius:8px;border:1px solid var(--border)">
          <input type="checkbox" id="sCaisse"> 
          <div>
            <div style="font-weight:600;font-size:13.5px">💰 Option Caisse activée</div>
            <div style="font-size:11.5px;color:var(--muted)">Ce site encaisse directement — nécessite un équipement de caisse</div>
          </div>
        </label>
      </div>
      <div class="form-group" id="sActifWrap" style="display:none">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" id="sActif" checked> Site actif
        </label>
      </div>
    </div>
    <div class="mfoot">
      <button class="btn btn-secondary" onclick="closeMX('mS')">Annuler</button>
      <button class="btn btn-primary" id="bSS" onclick="saveS()">💾 Enregistrer</button>
    </div>
  </div>
</div>

<!-- ===== MODAL DÉTAIL SITE ===== -->
<div class="modal-overlay" id="mSD">
  <div class="modal" style="width:680px">
    <div class="mhdr"><h3 id="sdT">Détail site</h3><button class="mclose" onclick="closeMX('mSD')">✕</button></div>
    <div class="mbody" id="sdB"></div>
  </div>
</div>

<!-- ===== MODAL CALCULATEUR DE CAPACITÉ ===== -->
<div class="modal-overlay" id="mCapa">
  <div class="modal" style="width:640px">
    <div class="mhdr"><h3>🧮 Calculateur de capacité</h3><button class="mclose" onclick="closeMX('mCapa')">✕</button></div>
    <div class="mbody">
      <p style="font-size:13.5px;color:var(--muted);margin-bottom:20px">
        Sélectionnez un type de site pour savoir combien de sites vous pouvez créer avec votre stock disponible.
      </p>
      <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
        <?php foreach($types_labels as $k=>$l): ?>
        <button class="btn btn-secondary" onclick="calcCapa('<?= $k ?>')" id="cb-<?= $k ?>"><?= $l ?></button>
        <?php endforeach; ?>
      </div>
      <div id="capaResult"></div>
    </div>
  </div>
</div>

<!-- ===== MODAL CONFIG TYPES DE SITE ===== -->
<div class="modal-overlay" id="mCfg">
  <div class="modal" style="width:700px">
    <div class="mhdr"><h3>⚙️ Configuration des besoins par type de site</h3><button class="mclose" onclick="closeMX('mCfg')">✕</button></div>
    <div class="mbody">
      <p style="font-size:13px;color:var(--muted);margin-bottom:16px">
        Définissez les équipements nécessaires pour chaque type de site. Ces données servent au calculateur de capacité.
      </p>
      <div class="tabs" id="cfgTabs">
        <?php foreach($types_labels as $k=>$l): ?>
        <button class="tab-btn <?= $k==='saisie'?'active':'' ?>" onclick="showCfgTab('<?= $k ?>',this)"><?= $l ?></button>
        <?php endforeach; ?>
      </div>
      <?php foreach($types_labels as $k=>$l): ?>
      <div class="tab-pane <?= $k==='saisie'?'active':'' ?>" id="cfg-<?= $k ?>">
        <div id="cfg-rows-<?= $k ?>">
          <div style="text-align:center;padding:20px;color:var(--muted);font-size:13px">Chargement…</div>
        </div>
        <div style="display:flex;gap:8px;margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
          <select id="cfg-add-nom-<?= $k ?>" class="form-control" style="flex:2">
            <option value="">Ajouter un équipement…</option>
            <?php foreach($nomenclatures as $n): ?>
            <option value="<?= $n['id'] ?>" data-code="<?= h($n['code']) ?>"><?= h($n['code']) ?> — <?= h($n['libelle']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="number" class="form-control" id="cfg-add-qte-<?= $k ?>" value="1" min="1" style="width:72px">
          <label style="display:flex;align-items:center;gap:4px;font-size:12px;white-space:nowrap">
            <input type="checkbox" id="cfg-add-opt-<?= $k ?>"> Optionnel
          </label>
          <button class="btn btn-primary btn-sm" onclick="addCfgRow('<?= $k ?>')">+ Ajouter</button>
        </div>
        <div style="margin-top:16px;display:flex;justify-content:flex-end">
          <button class="btn btn-primary" onclick="saveCfg('<?= $k ?>')">💾 Enregistrer config <?= $l ?></button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
const NOMS = <?= json_encode(array_map(fn($n)=>['id'=>$n['id'],'code'=>$n['code'],'libelle'=>$n['libelle']],$nomenclatures)) ?>;
const STOCK_DISPO = <?= json_encode(array_column($stock_dispo,'dispo','id')) ?>;

// Config en mémoire par type
const cfgData = {};
<?php foreach($types_labels as $k=>$l):
  $cfg = db_fetch_all("SELECT cs.*,n.code,n.libelle FROM configurations_site cs JOIN nomenclatures n ON n.id=cs.nomenclature_id WHERE cs.type_site=?",[$k]);
?>
cfgData['<?= $k ?>'] = <?= json_encode($cfg) ?>;
<?php endforeach; ?>

function closeMX(id){ document.getElementById(id).classList.remove('open'); }

// ── SITE FORM
function toggleCaisse(type){
  const nonCaisse=['entrepot','siege'];
  const wrap=document.getElementById('sCaisseWrap');
  wrap.style.display=nonCaisse.includes(type)?'none':'block';
  if(nonCaisse.includes(type)) document.getElementById('sCaisse').checked=false;
}
function openMS(){
  resetSF();
  // Déclencher toggleCaisse au chargement pour afficher l'option dès l'ouverture
  toggleCaisse(document.getElementById('sType').value);
  document.getElementById('mS').classList.add('open');
}
function resetSF(){
  ['sId','sCode','sNom','sVille','sAdresse'].forEach(i=>document.getElementById(i).value='');
  document.getElementById('sType').value='saisie';
  document.getElementById('sResp').value='';
  document.getElementById('sCaisse').checked=false;
  document.getElementById('sActifWrap').style.display='none';
  document.getElementById('sCode').disabled=false;
  document.getElementById('mSAlert').innerHTML='';
  document.getElementById('mST').textContent='Nouveau site';
}
function editS(id){
  ap({action:'get',id}).then(d=>{
    if(!d.success){toast(d.message,'danger');return;}
    const r=d.data;
    document.getElementById('mST').textContent='Modifier : '+r.nom;
    document.getElementById('sId').value=r.id;
    document.getElementById('sCode').value=r.code;
    document.getElementById('sCode').disabled=true;
    document.getElementById('sNom').value=r.nom;
    document.getElementById('sType').value=r.type;
    document.getElementById('sVille').value=r.ville||'';
    document.getElementById('sResp').value=r.responsable_id||'';
    document.getElementById('sAdresse').value=r.adresse||'';
    document.getElementById('sActifWrap').style.display='block';
    document.getElementById('sActif').checked=r.actif==1;
    // Charger option caisse + afficher/masquer selon le type
    document.getElementById('sCaisse').checked=r.option_caisse==1;
    toggleCaisse(r.type);
    document.getElementById('mS').classList.add('open');
  });
}
function saveS(){
  const id=document.getElementById('sId').value, btn=document.getElementById('bSS');
  btn.disabled=true; btn.textContent='Enregistrement…';
  ap({action:id?'update':'create', id,
    code:           document.getElementById('sCode').value,
    nom:            document.getElementById('sNom').value,
    type:           document.getElementById('sType').value,
    ville:          document.getElementById('sVille').value,
    adresse:        document.getElementById('sAdresse').value,
    responsable_id: document.getElementById('sResp').value,
    option_caisse:  document.getElementById('sCaisse').checked ? 1 : 0,
    actif:          document.getElementById('sActif').checked  ? 1 : 0,
  }).then(d=>{
    btn.disabled=false; btn.textContent='💾 Enregistrer';
    if(d.success){toast(d.message,'success');closeMX('mS');setTimeout(()=>location.reload(),800);}
    else document.getElementById('mSAlert').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;
  });
}
function delS(id,nom){
  if(!confirm(`Désactiver le site "${nom}" ?`))return;
  ap({action:'delete',id}).then(d=>{toast(d.message,d.success?'success':'danger');if(d.success)setTimeout(()=>location.reload(),800);});
}

// ── DÉTAIL SITE
function viewS(id){
  document.getElementById('mSD').classList.add('open');
  document.getElementById('sdB').innerHTML='<div style="text-align:center;padding:40px;color:var(--muted)">Chargement…</div>';
  ap({action:'get',id}).then(d=>{
    if(!d.success)return;
    const r=d.data;
    document.getElementById('sdT').textContent=r.code+' — '+r.nom;
    const equips=r.equip_par_type.map(e=>`
      <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">
        <span style="font-size:10px;font-weight:700;background:var(--lighter);border:1px solid var(--border);border-radius:5px;padding:2px 7px">${e.code}</span>
        <span style="flex:1;font-size:13px">${e.libelle}</span>
        <span style="font-family:'Montserrat',sans-serif;font-size:18px;font-weight:800;color:var(--navy)">${e.total}</span>
      </div>`).join('')||'<p style="color:var(--muted);font-size:13px">Aucun équipement.</p>';
    const users=r.utilisateurs.map(u=>`
      <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border)">
        <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--blue-mid, #1a56a0),var(--accent-d));display:flex;align-items:center;justify-content:center;color:white;font-size:12px;font-weight:700">${u.nom.split(' ').map(w=>w[0]).join('').substring(0,2)}</div>
        <div><div style="font-size:13px;font-weight:500">${u.nom}</div><div style="font-size:11px;color:var(--muted)">${u.email} · ${u.role}</div></div>
      </div>`).join('')||'<p style="color:var(--muted);font-size:13px">Aucun utilisateur assigné.</p>';
    const consos=r.consommables.map(c=>{
      const pct=c.seuil_alerte>0?Math.min(100,c.quantite/c.seuil_alerte*100):100;
      const cls=pct<=50?'danger':pct<=100?'warning':'success';
      return `<div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px"><span>${c.libelle}</span><span style="font-weight:600">${c.quantite} ${c.unite}</span></div>
        <div style="height:5px;background:var(--border);border-radius:3px"><div style="width:${Math.min(pct,100)}%;height:100%;background:var(--${cls});border-radius:3px"></div></div>
      </div>`;}).join('')||'<p style="color:var(--muted);font-size:13px">Aucun consommable.</p>';
    document.getElementById('sdB').innerHTML=`
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 20px;margin-bottom:20px">
        ${[['Code',r.code],['Type',r.type],['Ville',r.ville||'—'],['Responsable',r.responsable_nom||'—'],['Adresse',r.adresse||'—'],['Statut',r.actif==1?'✅ Actif':'❌ Inactif']].map(([l,v])=>`<div style="padding:7px 0;border-bottom:1px solid var(--border)"><div style="font-size:10px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">${l}</div><div style="font-size:13.5px">${v}</div></div>`).join('')}
      </div>
      <h4 style="font-family:'Montserrat',sans-serif;font-size:14px;margin-bottom:10px">💻 Équipements (${r.equip_par_type.reduce((s,e)=>s+parseInt(e.total),0)} au total)</h4>
      ${equips}
      <h4 style="font-family:'Montserrat',sans-serif;font-size:14px;margin:16px 0 10px">👥 Utilisateurs (${r.utilisateurs.length})</h4>
      ${users}
      <h4 style="font-family:'Montserrat',sans-serif;font-size:14px;margin:16px 0 10px">🧴 Consommables sur site</h4>
      ${consos}`;
  });
}

// ── CALCULATEUR DE CAPACITÉ
function openCapa(){ document.getElementById('mCapa').classList.add('open'); }
function calcCapa(type){
  document.querySelectorAll('[id^="cb-"]').forEach(b=>b.classList.remove('btn-primary'));
  document.querySelector(`#cb-${type}`).classList.add('btn-primary');
  document.getElementById('capaResult').innerHTML='<div style="text-align:center;padding:20px;color:var(--muted)">Calcul en cours…</div>';
  ap({action:'calc_capacite',type_site:type}).then(d=>{
    if(!d.success){document.getElementById('capaResult').innerHTML=`<div class="alert alert-warning">${d.message}</div>`;return;}
    const {nb_sites,details}=d.data;
    const color=nb_sites===0?'#e74c3c':nb_sites<=2?'#f39c12':'#27ae60';
    const rows=details.map(r=>{
      const pct=r.requis>0?Math.min(100,r.disponible/r.requis*100):100;
      return `<div class="capa-row">
        <span style="width:60px;font-size:11px;font-weight:700;color:var(--muted)">${r.code}</span>
        <span style="flex:1;font-size:13px">${r.libelle}${r.optionnel?' <em style="font-size:11px;color:var(--muted)">(optionnel)</em>':''}</span>
        <span style="font-size:12px;color:var(--muted);margin:0 8px">${r.disponible} / ${r.requis} requis</span>
        <div class="capa-bar"><div class="capa-fill ${r.ok?'ok':'ko'}" style="width:${Math.min(pct,100)}%"></div></div>
        <span class="${r.ok?'capa-ok':'capa-ko'}" style="margin-left:10px;width:20px;text-align:center">${r.ok?'✓':'✗'}</span>
        <span style="font-family:'Montserrat',sans-serif;font-size:14px;font-weight:800;width:32px;text-align:right;color:var(--navy)">${r.couvre}</span>
      </div>`;}).join('');
    document.getElementById('capaResult').innerHTML=`
      <div class="capa-result" style="border-top:4px solid ${color}">
        <div class="capa-number" style="color:${color}">${nb_sites}</div>
        <div class="capa-label">site(s) de type « ${type} » réalisable(s) avec le stock disponible</div>
      </div>
      <h4 style="font-family:'Montserrat',sans-serif;font-size:14px;margin-bottom:10px">Détail par équipement</h4>
      ${rows}`;
  });
}

// ── CONFIG TYPES DE SITE
function openConfig(){
  document.getElementById('mCfg').classList.add('open');
  Object.keys(cfgData).forEach(k=>renderCfg(k));
}
function showCfgTab(k,btn){
  document.querySelectorAll('#cfgTabs .tab-btn').forEach(b=>b.classList.remove('active'));
  document.querySelectorAll('[id^="cfg-"]').forEach(p=>{if(!p.id.startsWith('cfg-rows')&&!p.id.startsWith('cfg-add'))p.classList.remove('active')});
  btn.classList.add('active');
  document.getElementById('cfg-'+k).classList.add('active');
}
function renderCfg(type){
  const container=document.getElementById('cfg-rows-'+type);
  const rows=cfgData[type]||[];
  if(!rows.length){container.innerHTML='<div style="color:var(--muted);font-size:13px;padding:12px 0">Aucun équipement configuré pour ce type.</div>';return;}
  container.innerHTML=rows.map((r,i)=>{
    const dispo=STOCK_DISPO[r.nomenclature_id]||0;
    return `<div class="cfg-row" id="cfgr-${type}-${i}">
      <span style="width:60px;font-size:10px;font-weight:700;color:var(--muted)">${r.code}</span>
      <span style="flex:1;font-size:13px">${r.libelle}</span>
      <input type="number" class="form-control" style="width:72px" value="${r.quantite}" min="1" onchange="cfgData['${type}'][${i}].quantite=this.value">
      <label style="font-size:12px;display:flex;align-items:center;gap:4px;white-space:nowrap">
        <input type="checkbox" ${r.optionnel?'checked':''} onchange="cfgData['${type}'][${i}].optionnel=this.checked"> Opt.
      </label>
      <span class="stock-dispo-badge ${dispo>=r.quantite?'ok':'low'}">${dispo} dispo</span>
      <button class="btn btn-danger btn-sm" onclick="rmCfgRow('${type}',${i})">✕</button>
    </div>`;}).join('');
}
function addCfgRow(type){
  const sel=document.getElementById('cfg-add-nom-'+type);
  const id=parseInt(sel.value); if(!id){toast('Sélectionnez un équipement.','danger');return;}
  const nom=NOMS.find(n=>n.id===id);
  const qte=parseInt(document.getElementById('cfg-add-qte-'+type).value)||1;
  const opt=document.getElementById('cfg-add-opt-'+type).checked;
  if(cfgData[type].some(r=>r.nomenclature_id==id)){toast('Équipement déjà dans la configuration.','danger');return;}
  cfgData[type].push({nomenclature_id:id,code:nom.code,libelle:nom.libelle,quantite:qte,optionnel:opt?1:0});
  renderCfg(type); sel.value='';
}
function rmCfgRow(type,i){ cfgData[type].splice(i,1); renderCfg(type); }
function saveCfg(type){
  ap({action:'save_config',type_site:type,items:JSON.stringify(cfgData[type])}).then(d=>{
    toast(d.message,d.success?'success':'danger');
  });
}

function ap(data){
  const fd=new FormData();
  for(const[k,v]of Object.entries(data))if(v!==undefined)fd.append(k,v);
  return fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(r=>r.json());
}
['mS','mSD','mCapa','mCfg'].forEach(id=>{
  document.getElementById(id).addEventListener('click',e=>{if(e.target===e.currentTarget)closeMX(id);});
});
</script>

<?php if($nb_inconnus > 0): ?>
<!-- ══════════════════════════════════════════════════
     SITES EMUCI INCONNUS — à traiter
════════════════════════════════════════════════════ -->
<div class="card" style="margin-top:28px;border-top:3px solid #F59E0B">
  <div class="card-header" style="background:#FFFBEB">
    <h3 style="color:#92400E">
      <i class="ph-duotone ph-warning" style="color:#F59E0B"></i>
      Sites EMUCI non reconnus
      <span style="background:#F59E0B;color:white;padding:2px 10px;border-radius:20px;font-size:12px;margin-left:8px"><?= $nb_inconnus ?></span>
    </h3>
    <span style="font-size:12px;color:#92400E">
      Ces sites apparaissent dans vos imports OptoPlate/OptoTrace mais ne sont pas encore dans DigiStock.
    </span>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Nom dans EMUCI</th>
        <th>Source import</th>
        <th style="text-align:center">Occurrences</th>
        <th>Dernière apparition</th>
        <th style="text-align:center">Action</th>
      </tr></thead>
      <tbody>
      <?php foreach($sites_inconnus_emuci as $si): ?>
      <tr>
        <td style="font-weight:700;color:var(--navy)"><?= h($si['nom_emuci']) ?></td>
        <td>
          <span style="background:<?= $si['type_import']==='optoplate'?'#DBEAFE':'#D1FAE5' ?>;
                color:<?= $si['type_import']==='optoplate'?'#1D4ED8':'#065F46' ?>;
                padding:2px 9px;border-radius:12px;font-size:11px;font-weight:700">
            <?= strtoupper($si['type_import']) ?>
          </span>
        </td>
        <td style="text-align:center;font-weight:700"><?= $si['nb_occurrences'] ?>×</td>
        <td style="font-size:12px;color:var(--muted)"><?= fmt_datetime($si['derniere_apparition']) ?></td>
        <td style="text-align:center">
          <div style="display:flex;gap:6px;justify-content:center;flex-wrap:wrap">
            <button class="btn btn-primary btn-sm"
              onclick="ouvrirLierSite(<?= $si['id'] ?>,'<?= h($si['nom_emuci']) ?>')">
              🔗 Lier à existant
            </button>
            <button class="btn btn-secondary btn-sm"
              onclick="ouvrirCreerDepuisEmuci(<?= $si['id'] ?>,'<?= h($si['nom_emuci']) ?>')">
              ➕ Créer le site
            </button>
            <button class="btn btn-sm" style="background:#F1F5F9;color:#64748B;border:1.5px solid #E2E8F0"
              onclick="ignorerSiteEmuci(<?= $si['id'] ?>,'<?= h($si['nom_emuci']) ?>')">
              Ignorer
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Lier à existant -->
<div id="modalLierEmuci" style="display:none;position:fixed;inset:0;background:rgba(6,3,58,.55);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:18px;padding:28px;width:480px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:var(--navy);margin-bottom:16px">
      🔗 Lier site EMUCI à DigiStock
    </h3>
    <div id="alertLierEmuci"></div>
    <input type="hidden" id="lierId">
    <div style="background:#EFF6FF;border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:13px;color:#1D4ED8">
      Nom EMUCI : <strong id="lierNomEmuci"></strong>
    </div>
    <div class="form-group" style="margin-bottom:20px">
      <label style="font-size:13px;font-weight:700;color:var(--navy);display:block;margin-bottom:6px">
        Site DigiStock correspondant *
      </label>
      <select class="form-control" id="lierSiteId">
        <option value="">— Sélectionner —</option>
        <?php foreach($all_sites_list as $s): ?>
        <option value="<?= $s['id'] ?>"><?= h($s['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:16px">
      Le champ <code>nom_emuci</code> du site sélectionné sera mis à jour. Les prochains imports reconnaîtront automatiquement ce site.
    </div>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button class="btn btn-secondary" onclick="document.getElementById('modalLierEmuci').style.display='none'">Annuler</button>
      <button class="btn btn-primary" onclick="confirmerLier()">🔗 Lier</button>
    </div>
  </div>
</div>

<!-- Modal Créer depuis EMUCI -->
<div id="modalCreerEmuci" style="display:none;position:fixed;inset:0;background:rgba(6,3,58,.55);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:18px;padding:28px;width:480px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:var(--navy);margin-bottom:16px">
      ➕ Créer site depuis EMUCI
    </h3>
    <div id="alertCreerEmuci"></div>
    <input type="hidden" id="creerId">
    <div style="background:#EFF6FF;border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:13px;color:#1D4ED8">
      Nom EMUCI : <strong id="creerNomEmuci"></strong>
    </div>
    <div class="form-group" style="margin-bottom:14px">
      <label style="font-size:13px;font-weight:700;color:var(--navy);display:block;margin-bottom:6px">Nom DigiStock *</label>
      <input type="text" class="form-control" id="creerNom" placeholder="Nom du site dans DigiStock">
    </div>
    <div class="form-group" style="margin-bottom:20px">
      <label style="font-size:13px;font-weight:700;color:var(--navy);display:block;margin-bottom:6px">Type de site *</label>
      <select class="form-control" id="creerType">
        <option value="pose">Site de pose</option>
        <option value="entrepot">Entrepôt</option>
        <option value="siege">Siège</option>
        <option value="mixte">Mixte</option>
      </select>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button class="btn btn-secondary" onclick="document.getElementById('modalCreerEmuci').style.display='none'">Annuler</button>
      <button class="btn btn-primary" onclick="confirmerCreerEmuci()">➕ Créer</button>
    </div>
  </div>
</div>

<script>
function apSite(d){return fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(d)}).then(r=>r.json());}

function ouvrirLierSite(id, nom){
  document.getElementById('lierId').value=id;
  document.getElementById('lierNomEmuci').textContent=nom;
  document.getElementById('lierSiteId').value='';
  document.getElementById('alertLierEmuci').innerHTML='';
  document.getElementById('modalLierEmuci').style.display='flex';
}
async function confirmerLier(){
  const sid=document.getElementById('lierSiteId').value;
  if(!sid){document.getElementById('alertLierEmuci').innerHTML='<div class="alert alert-danger">Sélectionnez un site.</div>';return;}
  const d=await apSite({action:'lier_site_emuci',inconnu_id:document.getElementById('lierId').value,decision:'lier',site_id:sid});
  if(d.success){toast(d.message,'success');document.getElementById('modalLierEmuci').style.display='none';setTimeout(()=>location.reload(),800);}
  else document.getElementById('alertLierEmuci').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;
}

function ouvrirCreerDepuisEmuci(id, nom){
  document.getElementById('creerId').value=id;
  document.getElementById('creerNomEmuci').textContent=nom;
  document.getElementById('creerNom').value=nom;
  document.getElementById('alertCreerEmuci').innerHTML='';
  document.getElementById('modalCreerEmuci').style.display='flex';
}
async function confirmerCreerEmuci(){
  const nom=document.getElementById('creerNom').value.trim();
  if(!nom){document.getElementById('alertCreerEmuci').innerHTML='<div class="alert alert-danger">Nom obligatoire.</div>';return;}
  const d=await apSite({action:'lier_site_emuci',inconnu_id:document.getElementById('creerId').value,decision:'creer',nom_nouveau:nom,type_nouveau:document.getElementById('creerType').value});
  if(d.success){toast(d.message,'success');document.getElementById('modalCreerEmuci').style.display='none';setTimeout(()=>location.reload(),800);}
  else document.getElementById('alertCreerEmuci').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;
}

async function ignorerSiteEmuci(id, nom){
  if(!confirm(`Ignorer le site EMUCI "${nom}" ? Il ne sera plus signalé.`))return;
  const d=await apSite({action:'lier_site_emuci',inconnu_id:id,decision:'ignorer'});
  if(d.success){toast(d.message,'success');setTimeout(()=>location.reload(),800);}
  else toast(d.message,'danger');
}
</script>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
