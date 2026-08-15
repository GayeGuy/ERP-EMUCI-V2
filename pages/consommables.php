<?php
// ============================================================
//  pages/consommables.php  —  Redirection vers articles.php
//  Module renommé : Consommables → Articles
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_auth();
header('Location: ' . APP_URL . '/pages/articles.php' . (!empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : ''));
exit;
/* ---- ancien code conservé ci-dessous pour référence ---- */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/upload.php';

require_auth();
require_permission('consommables', 'can_read');

$user        = current_user();
$page_title  = 'Consommables';
$active_page = 'consommables';

// ============================================================
//  AJAX
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // ── CRÉER CONSOMMABLE
    if ($action === 'create_conso') {
        require_permission('consommables', 'can_create');
        $code  = strtoupper(trim($_POST['code']  ?? ''));
        $lib   = trim($_POST['libelle']           ?? '');
        $unite = trim($_POST['unite']             ?? 'unite');
        $desc  = trim($_POST['description']       ?? '');
        $seuil = (float)($_POST['seuil_alerte']   ?? 10);
        $prix  = (float)($_POST['prix_unitaire']  ?? 0);
        if (!$code || !$lib) json_response(false, 'Code et libellé obligatoires.');
        if (db_fetch_value("SELECT COUNT(*) FROM consommables WHERE code=?", [$code]) > 0)
            json_response(false, "Le code $code existe déjà.");
        db_query("INSERT INTO consommables (code,libelle,unite,description,seuil_alerte,prix_unitaire) VALUES (?,?,?,?,?,?)",
            [$code, $lib, $unite, $desc, $seuil, $prix]);
        $id = (int)db_last_id();
        audit_log($user['id'], 'CREATE', 'consommables', $id, "Création consommable $code");
        json_response(true, 'Consommable créé.', ['id' => $id]);
    }

    // ── MODIFIER CONSOMMABLE
    if ($action === 'update_conso') {
        require_permission('consommables', 'can_update');
        $id    = (int)($_POST['id']            ?? 0);
        $lib   = trim($_POST['libelle']        ?? '');
        $unite = trim($_POST['unite']          ?? 'unite');
        $desc  = trim($_POST['description']    ?? '');
        $seuil = (float)($_POST['seuil_alerte']?? 10);
        $prix  = (float)($_POST['prix_unitaire']?? 0);
        if (!$lib) json_response(false, 'Libellé obligatoire.');
        $old = db_fetch_one("SELECT * FROM consommables WHERE id=?", [$id]);
        db_query("UPDATE consommables SET libelle=?,unite=?,description=?,seuil_alerte=?,prix_unitaire=? WHERE id=?",
            [$lib, $unite, $desc, $seuil, $prix, $id]);
        audit_log($user['id'], 'UPDATE', 'consommables', $id, "Modification {$old['code']}", $old);
        json_response(true, 'Consommable mis à jour.');
    }

    // ── RÉCEPTION (entrée en stock depuis fournisseur)
    if ($action === 'reception') {
        require_permission('consommables', 'can_create');
        $conso_id  = (int)($_POST['consommable_id'] ?? 0);
        $qte       = (float)($_POST['quantite']      ?? 0);
        $prix_unit = (float)($_POST['prix_unitaire'] ?? 0);
        $prix_tot  = round($qte * $prix_unit, 2);
        $date_rec  = trim($_POST['date_reception']   ?? date('Y-m-d'));
        $fourn     = trim($_POST['fournisseur']      ?? '');
        $bon       = trim($_POST['numero_bon']       ?? '');
        $notes     = trim($_POST['notes']            ?? '');

        if (!$conso_id || $qte <= 0)
            json_response(false, 'Consommable et quantité (> 0) obligatoires.');

        $conso = db_fetch_one("SELECT * FROM consommables WHERE id=?", [$conso_id]);
        if (!$conso) json_response(false, 'Consommable introuvable.');

        db_begin();
        try {
            // Enregistrer la réception
            db_query(
                "INSERT INTO receptions_consommables
                 (consommable_id,quantite,prix_unitaire,prix_total,date_reception,fournisseur,numero_bon,notes,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?)",
                [$conso_id, $qte, $prix_unit, $prix_tot, $date_rec, $fourn, $bon, $notes, $user['id']]
            );
            // Mettre à jour le stock global (+)
            db_query("UPDATE consommables SET stock_global = stock_global + ?, prix_unitaire = CASE WHEN ? > 0 THEN ? ELSE prix_unitaire END WHERE id=?",
                [$qte, $prix_unit, $prix_unit, $conso_id]);

            audit_log($user['id'], 'CREATE', 'consommables', $conso_id,
                "Réception $qte {$conso['unite']}(s) de {$conso['libelle']} (fournisseur: $fourn)");
            db_commit();

            $nouveau_stock = (float)db_fetch_value("SELECT stock_global FROM consommables WHERE id=?", [$conso_id]);
            json_response(true, "Réception de $qte {$conso['unite']}(s) enregistrée. Stock total : $nouveau_stock.");
        } catch (Exception $e) {
            db_rollback();
            json_response(false, 'Erreur : ' . $e->getMessage());
        }
    }

    // ── DISTRIBUTION (sortie de stock vers un site)
    if ($action === 'distribution') {
        require_permission('consommables', 'can_create');
        $conso_id = (int)($_POST['consommable_id'] ?? 0);
        $site_id  = (int)($_POST['site_id']         ?? 0);
        $qte      = (float)($_POST['quantite']       ?? 0);
        $prix_unit= (float)($_POST['prix_unitaire']  ?? 0);
        $prix_tot = round($qte * $prix_unit, 2);
        $date_liv = trim($_POST['date_livraison']    ?? date('Y-m-d'));
        $bl       = trim($_POST['bon_livraison']     ?? '');
        $notes    = trim($_POST['notes']             ?? '');

        if (!$conso_id || !$site_id || $qte <= 0)
            json_response(false, 'Consommable, site et quantité (> 0) obligatoires.');

        $conso = db_fetch_one("SELECT * FROM consommables WHERE id=?", [$conso_id]);
        if (!$conso) json_response(false, 'Consommable introuvable.');

        // Vérifier stock suffisant
        if ($conso['stock_global'] < $qte)
            json_response(false, "Stock insuffisant. Disponible : {$conso['stock_global']} {$conso['unite']}(s). Veuillez d'abord enregistrer une réception.");

        // ── UPLOAD BON DE LIVRAISON OBLIGATOIRE ──────────────
        $upload_bl = upload_document('fichier_bl', 'bl', 'bl_conso_' . $conso_id . '_site_' . $site_id, true);
        if (!$upload_bl['success']) json_response(false, $upload_bl['message']);

        // Créer aussi l'entrée de réception_site (en_attente) pour le coordinateur
        $create_reception = true;

        db_begin();
        try {
            // Enregistrer la distribution
            db_query(
                "INSERT INTO livraisons_consommables
                 (consommable_id,site_id,type_mouvement,quantite,prix_unitaire,prix_total,date_livraison,bon_livraison,fichier_bl,notes,created_by)
                 VALUES (?,?,'distribution',?,?,?,?,?,?,?,?)",
                [$conso_id, $site_id, $qte, $prix_unit, $prix_tot, $date_liv, $bl, $upload_bl['filename'], $notes, $user['id']]
            );
            $liv_id = (int)db_last_id();

            // Stock global diminue (-)
            db_query("UPDATE consommables SET stock_global = stock_global - ? WHERE id=?", [$qte, $conso_id]);
            // Stock par site augmente
            db_query(
                "INSERT INTO stock_consommables_site (consommable_id,site_id,quantite)
                 VALUES (?,?,?) ON CONFLICT (consommable_id,site_id) DO UPDATE SET quantite = stock_consommables_site.quantite + ?",
                [$conso_id, $site_id, $qte, $qte]
            );

            // Créer réception_site en_attente pour le coordinateur
            db_query(
                "INSERT INTO receptions_site (site_id,type_reception,consommable_id,quantite,livraison_ref_id,date_reception,statut,created_by)
                 VALUES (?,?,?,?,?,?,?,?)",
                [$site_id, 'consommable', $conso_id, $qte, $liv_id, $date_liv, 'en_attente', $user['id']]
            );

            audit_log($user['id'], 'CREATE', 'consommables', $conso_id,
                "Distribution $qte {$conso['unite']}(s) de {$conso['libelle']} → site:$site_id (BL:{$upload_bl['filename']})");
            db_commit();

            // Alerte stock bas
            $new_stock = (float)db_fetch_value("SELECT stock_global FROM consommables WHERE id=?", [$conso_id]);
            if ($new_stock <= $conso['seuil_alerte']) {
                notif_create('stock_bas', "⚠️ Stock bas — {$conso['libelle']}",
                    "Stock restant : <strong>$new_stock {$conso['unite']}(s)</strong> (seuil : {$conso['seuil_alerte']}).",
                    null, '/pages/consommables.php', false);
            }
            json_response(true, "Distribution de $qte {$conso['unite']}(s) enregistrée. Stock restant : $new_stock.");
        } catch (Exception $e) {
            db_rollback();
            json_response(false, 'Erreur : ' . $e->getMessage());
        }
    }

    // ── AJUSTEMENT STOCK
    if ($action === 'ajustement') {
        require_permission('consommables', 'can_update');
        $conso_id = (int)($_POST['consommable_id'] ?? 0);
        $site_id  = (int)($_POST['site_id']         ?? 0);
        $new_qte  = (float)($_POST['new_qte']        ?? 0);
        $motif    = trim($_POST['motif']             ?? '');
        if (!$conso_id || !$site_id) json_response(false, 'Consommable et site obligatoires.');
        $conso = db_fetch_one("SELECT * FROM consommables WHERE id=?", [$conso_id]);
        $old_site_qte = (float)(db_fetch_value(
            "SELECT quantite FROM stock_consommables_site WHERE consommable_id=? AND site_id=?",
            [$conso_id, $site_id]) ?? 0);
        $diff = $new_qte - $old_site_qte;
        db_begin();
        try {
            db_query("INSERT INTO stock_consommables_site (consommable_id,site_id,quantite) VALUES (?,?,?)
                      ON CONFLICT (consommable_id,site_id) DO UPDATE SET quantite=?", [$conso_id, $site_id, $new_qte, $new_qte]);
            db_query("UPDATE consommables SET stock_global = stock_global + ? WHERE id=?", [$diff, $conso_id]);
            audit_log($user['id'], 'UPDATE', 'consommables', $conso_id,
                "Ajustement {$conso['code']} site:$site_id : $old_site_qte → $new_qte ($motif)");
            db_commit();
            json_response(true, 'Stock ajusté.');
        } catch (Exception $e) { db_rollback(); json_response(false, 'Erreur : ' . $e->getMessage()); }
    }

    // ── GET ONE
    if ($action === 'get_conso') {
        $id  = (int)($_POST['id'] ?? 0);
        $row = db_fetch_one("SELECT * FROM consommables WHERE id=?", [$id]);
        if (!$row) json_response(false, 'Introuvable.');
        $row['stock_par_site'] = db_fetch_all(
            "SELECT sc.quantite, s.nom AS site_nom, s.id AS site_id
             FROM stock_consommables_site sc JOIN sites s ON s.id=sc.site_id
             WHERE sc.consommable_id=? ORDER BY s.nom", [$id]);
        $row['receptions'] = db_fetch_all(
            "SELECT r.date_reception, r.quantite, r.prix_unitaire, r.prix_total,
                    r.fournisseur, r.numero_bon, CONCAT(u.prenom,' ',u.nom) AS agent
             FROM receptions_consommables r
             LEFT JOIN users u ON u.id=r.created_by
             WHERE r.consommable_id=? ORDER BY r.date_reception DESC LIMIT 8", [$id]);
        $row['distributions'] = db_fetch_all(
            "SELECT lc.date_livraison, lc.quantite, lc.prix_total, s.nom AS site,
                    lc.bon_livraison, CONCAT(u.prenom,' ',u.nom) AS agent
             FROM livraisons_consommables lc
             JOIN sites s ON s.id=lc.site_id
             LEFT JOIN users u ON u.id=lc.created_by
             WHERE lc.consommable_id=? AND lc.type_mouvement='distribution'
             ORDER BY lc.date_livraison DESC LIMIT 8", [$id]);
        $row['conso_mensuelle'] = db_fetch_all(
            "SELECT TO_CHAR(date_livraison,'YYYY-MM') AS mois, SUM(quantite) AS total
             FROM livraisons_consommables
             WHERE consommable_id=? AND date_livraison >= (CURRENT_DATE - INTERVAL '6 MONTH')
               AND type_mouvement='distribution'
             GROUP BY mois ORDER BY mois ASC", [$id]);
        json_response(true, '', $row);
    }

    json_response(false, 'Action inconnue.');
}

// ============================================================
//  DONNÉES
// ============================================================
$role_slug_conso   = $user['role_slug'] ?? '';
$site_force_conso  = ($role_slug_conso === 'coordinateur_site' && $user['site_id']) ? (int)$user['site_id'] : 0;

$search   = trim($_GET['q']      ?? '');
$f_alerte = (int)($_GET['alerte']?? 0);
$where    = ['1=1']; $params = [];
if ($search)   { $where[] = '(c.code ILIKE ? OR c.libelle ILIKE ?)'; $s="%$search%"; $params=[$s,$s]; }
if ($f_alerte) { $where[] = 'c.stock_global <= c.seuil_alerte'; }
// Coordinateur : filtrer sur les consommables distribués sur son site
if ($site_force_conso) {
    $where[] = 'EXISTS (SELECT 1 FROM stock_consommables_site scs WHERE scs.consommable_id=c.id AND scs.site_id=?)';
    $params[] = $site_force_conso;
}
$wsql   = implode(' AND ', $where);

$consos = db_fetch_all(
    "SELECT c.*,
            COUNT(DISTINCT sc.site_id) AS nb_sites,
            COALESCE((SELECT SUM(quantite) FROM receptions_consommables r
                      WHERE r.consommable_id=c.id
                        AND r.date_reception >= (CURRENT_DATE - INTERVAL '30 DAY')),0) AS receptions_30j,
            COALESCE((SELECT SUM(quantite) FROM livraisons_consommables l
                      WHERE l.consommable_id=c.id AND l.type_mouvement='distribution'
                        AND l.date_livraison >= (CURRENT_DATE - INTERVAL '30 DAY')),0) AS distributions_30j
     FROM consommables c
     LEFT JOIN stock_consommables_site sc ON sc.consommable_id=c.id
     WHERE $wsql GROUP BY c.id ORDER BY c.libelle", $params
);

// Historique global (réceptions + distributions)
$historique = db_fetch_all(
    "SELECT 'reception' AS type_op, r.date_reception AS date_op, c.code AS conso_code,
            c.libelle AS conso_lib, c.unite, r.quantite, r.prix_unitaire, r.prix_total,
            NULL AS site_nom, r.fournisseur AS ref1, r.numero_bon AS ref2,
            CONCAT(u.prenom,' ',u.nom) AS agent
     FROM receptions_consommables r
     JOIN consommables c ON c.id=r.consommable_id
     LEFT JOIN users u ON u.id=r.created_by
     UNION ALL
     SELECT 'distribution', lc.date_livraison, c.code, c.libelle, c.unite,
            lc.quantite, COALESCE(lc.prix_unitaire,0), COALESCE(lc.prix_total,0),
            s.nom, lc.bon_livraison, NULL,
            CONCAT(u.prenom,' ',u.nom)
     FROM livraisons_consommables lc
     JOIN consommables c ON c.id=lc.consommable_id
     JOIN sites s ON s.id=lc.site_id
     LEFT JOIN users u ON u.id=lc.created_by
     WHERE lc.type_mouvement='distribution'
     ORDER BY date_op DESC LIMIT 80"
);

$sites_list = db_fetch_all("SELECT id,nom,type FROM sites WHERE actif=1 ORDER BY nom");
$unites     = ['unite'=>'Unité','kg'=>'Kilogramme','litre'=>'Litre','boite'=>'Boîte','rame'=>'Rame','paquet'=>'Paquet'];

$kpi_total_consos    = count($consos);
$kpi_alertes         = count(array_filter($consos, fn($c)=>$c['stock_global']<=$c['seuil_alerte']));
$kpi_receptions_mois = (float)db_fetch_value("SELECT COALESCE(SUM(quantite),0) FROM receptions_consommables WHERE date_reception >= date_trunc('month',CURRENT_DATE)::date");
$kpi_distrib_mois    = (float)db_fetch_value("SELECT COALESCE(SUM(quantite),0) FROM livraisons_consommables WHERE type_mouvement='distribution' AND date_livraison >= date_trunc('month',CURRENT_DATE)::date");

include __DIR__ . '/../templates/header.php';
?>
<style>
.conso-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:16px;margin-bottom:24px}
.conso-card{background:white;border:1px solid var(--border);border-radius:14px;overflow:hidden;transition:box-shadow .2s,transform .2s;cursor:pointer}
.conso-card:hover{box-shadow:0 6px 24px rgba(0,0,0,.09);transform:translateY(-2px)}
.conso-card.alerte{border-left:4px solid var(--danger)}
.conso-card.warning{border-left:4px solid var(--warning)}
.cc-header{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.cc-code{width:44px;height:44px;border-radius:10px;background:linear-gradient(135deg,var(--blue),var(--blue-mid,#1a56a0));display:flex;align-items:center;justify-content:center;font-family:'Montserrat',sans-serif;font-size:12px;font-weight:800;color:white;letter-spacing:.5px;text-align:center;padding:4px;flex-shrink:0}
.cc-info h4{font-family:'Montserrat',sans-serif;font-size:13.5px;font-weight:700;color:var(--navy);margin-bottom:2px}
.cc-info span{font-size:11.5px;color:var(--muted)}
.cc-stock{padding:12px 16px;display:flex;align-items:center;gap:12px}
.cc-stock-num{font-family:'Montserrat',sans-serif;font-size:28px;font-weight:800;color:var(--navy);line-height:1}
.stock-bar{height:6px;background:var(--border);border-radius:3px;overflow:hidden;margin-top:4px}
.stock-fill{height:100%;border-radius:3px}
.cc-footer{padding:8px 16px;background:var(--lighter);display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--border);font-size:12px}

/* Tabs */
.tabs{display:flex;border-bottom:1px solid var(--border);margin-bottom:20px;overflow-x:auto}
.tab-btn{padding:12px 20px;font-size:13.5px;font-weight:500;color:var(--muted);cursor:pointer;border-bottom:2px solid transparent;background:none;border-top:none;border-left:none;border-right:none;transition: background-color .15s, border-color .15s, color .15s, box-shadow .15s, transform .15s, opacity .15s;font-family:'DM Sans',sans-serif;white-space:nowrap}
.tab-btn.active{color:var(--blue-mid,#1a56a0);border-bottom-color:var(--blue-mid,#1a56a0)}
.tab-pane{display:none}.tab-pane.active{display:block}

/* Flux indicator */
.flux-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.flux-badge.reception{background:#e8f5e9;color:#2e7d32}
.flux-badge.distribution{background:#e3f2fd;color:#1565c0}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;z-index:500;background:rgba(10,22,40,.5);backdrop-filter:blur(4px);align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:white;border-radius:16px;max-width:95vw;max-height:92vh;overflow-y:auto;animation:mIn .25s cubic-bezier(.22,1,.36,1)}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.mhdr{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:white;z-index:10}
.mhdr h3{font-family:'Montserrat',sans-serif;font-size:17px;font-weight:700}
.mclose{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:none;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center}
.mbody{padding:24px}
.mfoot{padding:14px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;position:sticky;bottom:0;background:white}

/* Stock indicator */
.stock-indicator{display:flex;align-items:center;gap:12px;padding:14px;border-radius:10px;margin-bottom:16px}
.stock-indicator.ok{background:#e8f5e9;border:1px solid #a5d6a7}
.stock-indicator.low{background:#fff3e0;border:1px solid #ffcc80}
.stock-indicator.empty{background:#fce4ec;border:1px solid #f48fb1}
.stock-indicator .si-val{font-family:'Montserrat',sans-serif;font-size:28px;font-weight:800}
.stock-indicator.ok .si-val{color:#2e7d32}
.stock-indicator.low .si-val{color:#e65100}
.stock-indicator.empty .si-val{color:#c62828}
</style>

<!-- KPIs -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px">
  <?php foreach([
    ['<i class="ph ph-flask" aria-hidden="true"></i>','Références',         $kpi_total_consos,                        '',         'blue'],
    ['<i class="ph ph-warning" aria-hidden="true"></i>','Alertes stock',      $kpi_alertes,                             'à réappro','red'],
    ['<i class="ph ph-download-simple" aria-hidden="true"></i>','Reçu ce mois',       fmt_number($kpi_receptions_mois,1),       'en stock', 'teal'],
    ['<i class="ph ph-upload-simple" aria-hidden="true"></i>','Distribué ce mois',  fmt_number($kpi_distrib_mois,1),          'vers sites','green'],
  ] as [$ico,$lbl,$val,$sub,$col]): ?>
  <div style="background:white;border:1px solid var(--border);border-radius:14px;padding:18px 20px;display:flex;align-items:center;gap:14px;border-top:3px solid var(--<?= $col ?>)">
    <div style="width:48px;height:48px;border-radius:13px;background:var(--lighter);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0"><?= $ico ?></div>
    <div>
      <div style="font-family:'Montserrat',sans-serif;font-size:26px;font-weight:800;color:var(--navy);line-height:1"><?= $val ?></div>
      <div style="font-size:12px;color:var(--muted);margin-top:3px"><?= $lbl ?><?= $sub?" · $sub":'' ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- TABS -->
<div class="tabs" id="mainTabs">
  <button class="tab-btn active"  onclick="showTab('stock',this)"><i class="ph ph-package" aria-hidden="true"></i> Stock</button>
  <button class="tab-btn"         onclick="showTab('reception',this)"><i class="ph ph-download-simple" aria-hidden="true"></i> Réception fournisseur</button>
  <button class="tab-btn"         onclick="showTab('distribution',this)"><i class="ph ph-upload-simple" aria-hidden="true"></i> Distribution site</button>
  <button class="tab-btn"         onclick="showTab('historique',this)"><i class="ph ph-clipboard-text" aria-hidden="true"></i> Historique</button>
</div>

<!-- ═══ TAB STOCK ═══ -->
<div class="tab-pane active" id="tab-stock">
  <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
    <div style="position:relative;flex:1;min-width:200px">
      <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted)"><i class="ph ph-magnifying-glass" aria-hidden="true"></i></span>
      <input type="text" id="searchQ" value="<?= h($search) ?>" placeholder="Rechercher…" aria-label="Rechercher un consommable"
        style="width:100%;padding:10px 14px 10px 38px;border:1.5px solid var(--border);border-radius:9px;font-size:13.5px;outline:none;font-family:'DM Sans',sans-serif"
        oninput="filterConsos(this.value)">
    </div>
    <label style="display:flex;align-items:center;gap:6px;font-size:13.5px;cursor:pointer;padding:9px 14px;border:1.5px solid var(--border);border-radius:9px;background:white">
      <input type="checkbox" id="alerteOnly" <?= $f_alerte?'checked':'' ?> onchange="filterConsos()"><i class="ph ph-warning" aria-hidden="true"></i> Alertes seulement
    </label>
    <?php if(can('consommables','can_create')): ?>
    <button class="btn btn-primary" onclick="openMC()">+ Nouveau consommable</button>
    <?php endif; ?>
    <?php if(can('consommables','can_export')): ?>
    <a href="<?= APP_URL ?>/api/export.php?type=consommables" class="btn btn-secondary btn-sm"><i class="ph ph-download-simple" aria-hidden="true"></i> Excel</a>
    <?php endif; ?>
  </div>

  <div class="conso-grid" id="consoGrid">
  <?php foreach($consos as $c):
    $ratio = $c['seuil_alerte']>0 ? $c['stock_global']/$c['seuil_alerte'] : 2;
    $pct   = min(100, $ratio * 100);
    $cls   = $ratio<=0?'alerte':($ratio<=1?'warning':'');
    $fill  = $ratio<=0.5?'var(--danger)':($ratio<=1?'var(--warning)':'var(--success)');
  ?>
  <div class="conso-card <?= $cls ?>" onclick="viewConso(<?= $c['id'] ?>)"
       data-lib="<?= strtolower(h($c['libelle'])) ?>" data-alerte="<?= $cls?'1':'0' ?>">
    <div class="cc-header">
      <div class="cc-code"><?= h($c['code']) ?></div>
      <div class="cc-info">
        <h4><?= h($c['libelle']) ?></h4>
        <span><?= $unites[$c['unite']]??$c['unite'] ?> · <?= $c['nb_sites'] ?> site(s)</span>
      </div>
      <?php if($ratio<=0): ?>
        <span title="Stock épuisé" style="font-size:20px"><i class="ph ph-circle" aria-hidden="true"></i></span>
      <?php elseif($cls): ?>
        <span title="Stock bas" style="font-size:20px"><i class="ph ph-circle" aria-hidden="true"></i></span>
      <?php endif; ?>
    </div>
    <div class="cc-stock">
      <div>
        <div class="cc-stock-num" style="color:<?= $ratio<=0?'var(--danger-d)':($ratio<=1?'var(--warning-d)':'var(--navy)') ?>">
          <?= fmt_number($c['stock_global'],1) ?>
        </div>
        <div style="font-size:12px;color:var(--muted)"><?= $unites[$c['unite']]??$c['unite'] ?></div>
      </div>
      <div style="flex:1">
        <div style="display:flex;justify-content:space-between;font-size:11.5px;margin-bottom:4px">
          <span style="color:var(--muted)">Seuil : <?= $c['seuil_alerte'] ?></span>
          <span style="color:<?= $cls?'var(--danger-d)':'var(--success-d)' ?>;font-weight:600"><?= round($pct) ?>%</span>
        </div>
        <div class="stock-bar"><div class="stock-fill" style="width:<?= $pct ?>%;background:<?= $fill ?>"></div></div>
        <div style="font-size:10.5px;color:var(--muted);margin-top:4px;display:flex;gap:8px">
          <?php if($c['receptions_30j']>0): ?><span><i class="ph ph-download-simple" aria-hidden="true"></i> +<?= fmt_number($c['receptions_30j'],1) ?> reçus</span><?php endif; ?>
          <?php if($c['distributions_30j']>0): ?><span><i class="ph ph-upload-simple" aria-hidden="true"></i> -<?= fmt_number($c['distributions_30j'],1) ?> distribués</span><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="cc-footer">
      <span style="color:var(--muted)">Seuil alerte : <?= $c['seuil_alerte'] ?></span>
      <?php if(!empty($c['prix_unitaire'])&&$c['prix_unitaire']>0): ?>
      <span style="font-weight:700;color:var(--blue-mid,#1a56a0)"><i class="ph ph-currency-circle-dollar" aria-hidden="true"></i> <?= fmt_number($c['prix_unitaire'],0) ?> FCFA/<?= $c['unite'] ?></span>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if(empty($consos)): ?>
  <div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--muted)">
    <div style="font-size:48px;margin-bottom:12px"><i class="ph ph-flask" aria-hidden="true"></i></div><p>Aucun consommable.</p>
  </div>
  <?php endif; ?>
  </div>
</div>

<!-- ═══ TAB RÉCEPTION ═══ -->
<div class="tab-pane" id="tab-reception">
  <div style="max-width:600px">
    <div class="alert alert-info" style="margin-bottom:20px">
      <i class="ph ph-download-simple" aria-hidden="true"></i> <strong>Réception fournisseur</strong> — Enregistrez ici quand vous recevez une livraison du fournisseur. Le stock global augmente.
    </div>
    <div class="card">
      <div class="card-header"><h3><i class="ph ph-download-simple" aria-hidden="true"></i> Nouvelle réception</h3></div>
      <div class="card-body">
        <div id="recAlert"></div>

        <div class="form-group"><label>Consommable *</label>
          <select class="form-control" id="recConso" onchange="updStockActuel('rec')">
            <option value="">— Sélectionner —</option>
            <?php foreach($consos as $c): ?>
            <option value="<?= $c['id'] ?>"
                    data-unite="<?= $c['unite'] ?>"
                    data-stock="<?= $c['stock_global'] ?>"
                    data-prix="<?= $c['prix_unitaire']??0 ?>">
              <?= h($c['code'].' — '.$c['libelle']) ?>
              (stock actuel : <?= fmt_number($c['stock_global'],1) ?> <?= $c['unite'] ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Stock actuel indicator -->
        <div id="recStockInfo" style="display:none;margin-bottom:16px">
          <div class="stock-indicator" id="recStockIndicator">
            <div>
              <div style="font-size:12px;color:var(--muted);text-transform:uppercase;font-weight:600;margin-bottom:2px">Stock actuel</div>
              <div class="si-val" id="recStockVal">0</div>
              <div style="font-size:12px;color:var(--muted)" id="recStockUnite"></div>
            </div>
            <div style="font-size:24px"><i class="ph ph-package" aria-hidden="true"></i></div>
            <div style="flex:1;font-size:13px;color:var(--muted)" id="recStockMsg"></div>
          </div>
        </div>

        <div class="form-row cols-2">
          <div class="form-group"><label>Quantité reçue *</label>
            <input type="number" class="form-control" id="recQte" min="0.01" step="0.01" placeholder="0" oninput="calcRecTotal()">
          </div>
          <div class="form-group"><label>Prix unitaire (FCFA)</label>
            <input type="number" class="form-control" id="recPrix" min="0" step="1" placeholder="0" oninput="calcRecTotal()">
          </div>
        </div>
        <div class="form-group">
          <label>Prix total</label>
          <input type="text" class="form-control" id="recTotal" disabled style="background:var(--lighter);font-weight:700;color:var(--navy)">
        </div>
        <div class="form-row cols-2">
          <div class="form-group"><label>Date de réception</label>
            <input type="date" class="form-control" id="recDate" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group"><label>Fournisseur</label>
            <input type="text" class="form-control" id="recFourn" placeholder="Nom du fournisseur">
          </div>
        </div>
        <div class="form-group"><label>N° bon de livraison fournisseur</label>
          <input type="text" class="form-control" id="recBon" placeholder="BLF-2024-001">
        </div>
        <div class="form-group"><label>Notes</label>
          <textarea class="form-control" id="recNotes" rows="2" placeholder="Remarques…"></textarea>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <button class="btn btn-secondary" onclick="resetRec()">Réinitialiser</button>
          <button class="btn btn-primary" onclick="saveRec()"><i class="ph ph-download-simple" aria-hidden="true"></i> Valider la réception</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ TAB DISTRIBUTION ═══ -->
<div class="tab-pane" id="tab-distribution">
  <div style="max-width:600px">
    <div class="alert alert-info" style="margin-bottom:20px">
      <i class="ph ph-upload-simple" aria-hidden="true"></i> <strong>Distribution vers site</strong> — Enregistrez ici quand vous envoyez des articles vers un site. Le stock global diminue.
    </div>
    <div class="card">
      <div class="card-header"><h3><i class="ph ph-upload-simple" aria-hidden="true"></i> Nouvelle distribution</h3></div>
      <div class="card-body">
        <div id="distAlert"></div>

        <div class="form-group"><label>Consommable *</label>
          <select class="form-control" id="distConso" onchange="updStockActuel('dist')">
            <option value="">— Sélectionner —</option>
            <?php foreach($consos as $c): ?>
            <option value="<?= $c['id'] ?>"
                    data-unite="<?= $c['unite'] ?>"
                    data-stock="<?= $c['stock_global'] ?>"
                    data-prix="<?= $c['prix_unitaire']??0 ?>"
                    data-seuil="<?= $c['seuil_alerte'] ?>">
              <?= h($c['code'].' — '.$c['libelle']) ?>
              (dispo : <?= fmt_number($c['stock_global'],1) ?> <?= $c['unite'] ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Stock disponible indicator -->
        <div id="distStockInfo" style="display:none;margin-bottom:16px">
          <div class="stock-indicator" id="distStockIndicator">
            <div>
              <div style="font-size:12px;color:var(--muted);text-transform:uppercase;font-weight:600;margin-bottom:2px">Disponible en stock</div>
              <div class="si-val" id="distStockVal">0</div>
              <div style="font-size:12px;color:var(--muted)" id="distStockUnite"></div>
            </div>
            <div style="font-size:24px"><i class="ph ph-package" aria-hidden="true"></i></div>
            <div style="flex:1;font-size:13px" id="distStockMsg"></div>
          </div>
        </div>

        <div class="form-group"><label>Site destinataire *</label>
          <select class="form-control" id="distSite">
            <option value="">— Sélectionner —</option>
            <?php foreach($sites_list as $s): ?>
            <option value="<?= $s['id'] ?>"><?= h($s['nom']) ?> (<?= $s['type'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row cols-3">
          <div class="form-group"><label>Quantité *</label>
            <input type="number" class="form-control" id="distQte" min="0.01" step="0.01" placeholder="0" oninput="checkDistQte()">
          </div>
          <div class="form-group"><label>Prix unitaire (FCFA)</label>
            <input type="number" class="form-control" id="distPrix" min="0" step="1" placeholder="0" oninput="calcDistTotal()">
          </div>
          <div class="form-group"><label>Prix total</label>
            <input type="text" class="form-control" id="distTotal" disabled style="background:var(--lighter);font-weight:700;color:var(--navy)">
          </div>
        </div>
        <div id="distQteWarn" style="display:none" class="alert alert-danger"><i class="ph ph-warning" aria-hidden="true"></i> Quantité supérieure au stock disponible !</div>

        <div class="form-row cols-2">
          <div class="form-group"><label>Date</label>
            <input type="date" class="form-control" id="distDate" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group"><label>Bon de sortie</label>
            <input type="text" class="form-control" id="distBL" placeholder="BS-2024-001">
          </div>
        </div>

        <!-- BON DE LIVRAISON OBLIGATOIRE -->
        <div class="form-group" style="background:#fff8e7;border:2px dashed #f39c12;border-radius:10px;padding:14px;margin-bottom:14px">
          <label style="font-size:13px;font-weight:700;color:#b7791f;display:block;margin-bottom:6px">
            <i class="ph ph-paperclip" aria-hidden="true"></i> Bon de livraison <span style="color:var(--danger-d)">*</span>
            <span style="font-size:12px;font-weight:400;color:var(--muted)"> — PDF ou image obligatoire pour valider la livraison</span>
          </label>
          <input type="file" id="distFichierBL" accept=".pdf,.jpg,.jpeg,.png,.webp"
                 style="width:100%;padding:8px;border:1.5px solid #f39c12;border-radius:8px;font-size:13px;cursor:pointer;background:white">
          <div id="distBLPreview" style="display:none;margin-top:6px;font-size:12px;color:var(--success-d);font-weight:600"></div>
        </div>

        <div class="form-group"><label>Notes</label>
          <textarea class="form-control" id="distNotes" rows="2" placeholder="Destination, motif…"></textarea>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <button class="btn btn-secondary" onclick="resetDist()">Réinitialiser</button>
          <button class="btn btn-primary" id="btnDist" onclick="saveDist()"><i class="ph ph-upload-simple" aria-hidden="true"></i> Valider la distribution</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ TAB HISTORIQUE ═══ -->
<div class="tab-pane" id="tab-historique">
  <div style="display:flex;justify-content:flex-end;margin-bottom:14px;gap:8px">
    <?php if(can('consommables','can_export')): ?>
    <a href="<?= APP_URL ?>/api/export.php?type=livraisons" class="btn btn-secondary btn-sm"><i class="ph ph-download-simple" aria-hidden="true"></i> Excel distributions</a>
    <?php endif; ?>
  </div>
  <div class="card">
    <div class="card-header">
      <h3><i class="ph ph-clipboard-text" aria-hidden="true"></i> Historique mouvements</h3>
      <div style="display:flex;gap:6px">
        <span class="flux-badge reception"><i class="ph ph-download-simple" aria-hidden="true"></i> Réception</span>
        <span class="flux-badge distribution"><i class="ph ph-upload-simple" aria-hidden="true"></i> Distribution</span>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Date</th><th>Type</th><th>Consommable</th>
          <th style="text-align:right">Quantité</th>
          <th style="text-align:right">Prix unit.</th>
          <th style="text-align:right">Prix total</th>
          <th>Référence / Site</th><th>Agent</th>
        </tr></thead>
        <tbody>
        <?php if(empty($historique)): ?>
          <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--muted)">Aucun mouvement.</td></tr>
        <?php else: foreach($historique as $h): ?>
          <tr>
            <td style="white-space:nowrap;font-size:12.5px"><?= fmt_date($h['date_op']) ?></td>
            <td><span class="flux-badge <?= $h['type_op'] ?>"><?= $h['type_op']==='reception'?'<i class="ph ph-download-simple" aria-hidden="true"></i> Réception':'<i class="ph ph-upload-simple" aria-hidden="true"></i> Distribution' ?></span></td>
            <td><span style="font-family:monospace;font-size:11.5px;font-weight:700;color:var(--navy)"><?= h($h['conso_code']) ?></span> <?= h($h['conso_lib']) ?></td>
            <td style="text-align:right;font-family:'Montserrat',sans-serif;font-weight:800;font-size:15px;color:<?= $h['type_op']==='reception'?'var(--success-d)':'var(--blue-mid,#1a56a0)' ?>">
              <?= $h['type_op']==='reception'?'+':'-' ?><?= fmt_number($h['quantite'],1) ?>
              <span style="font-size:12px;font-weight:400;color:var(--muted)"><?= $h['unite'] ?></span>
            </td>
            <td style="text-align:right;font-size:12px;color:var(--muted)"><?= $h['prix_unitaire']>0?fmt_number($h['prix_unitaire'],0).' FCFA':'—' ?></td>
            <td style="text-align:right;font-size:12.5px;font-weight:600;color:var(--navy)"><?= $h['prix_total']>0?fmt_number($h['prix_total'],0).' FCFA':'—' ?></td>
            <td style="font-size:12px">
              <?= $h['type_op']==='reception' ? h($h['ref1']??'—').' '.h($h['ref2']??'') : h($h['site_nom']??'—') ?>
            </td>
            <td style="font-size:12.5px"><?= h($h['agent']??'—') ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- MODAL CRÉER/MODIFIER CONSOMMABLE -->
<div class="modal-overlay" id="mC">
  <div class="modal" style="width:540px">
    <div class="mhdr"><h3 id="mCT">Nouveau consommable</h3><button class="mclose" onclick="closeMC()"><i class="ph ph-x" aria-hidden="true"></i></button></div>
    <div class="mbody">
      <div id="mCAlert"></div>
      <input type="hidden" id="cId">
      <div class="form-row cols-2">
        <div class="form-group"><label>Code *</label>
          <input type="text" class="form-control" id="cCode" oninput="this.value=this.value.toUpperCase()" maxlength="30">
        </div>
        <div class="form-group"><label>Unité *</label>
          <select class="form-control" id="cUnite">
            <?php foreach($unites as $k=>$l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group"><label>Libellé *</label>
        <input type="text" class="form-control" id="cLib" placeholder="Café, Papier toilette…">
      </div>
      <div class="form-row cols-2">
        <div class="form-group"><label>Seuil d'alerte</label>
          <input type="number" class="form-control" id="cSeuil" value="10" min="0" step="0.01">
        </div>
        <div class="form-group"><label>Prix unitaire (FCFA)</label>
          <input type="number" class="form-control" id="cPrix" value="0" min="0" step="1">
        </div>
      </div>
      <div class="form-group"><label>Description</label>
        <textarea class="form-control" id="cDesc" rows="3" placeholder="Description…"></textarea>
      </div>
    </div>
    <div class="mfoot">
      <button class="btn btn-secondary" onclick="closeMC()">Annuler</button>
      <button class="btn btn-primary" id="bSC" onclick="saveConso()"><i class="ph ph-floppy-disk" aria-hidden="true"></i> Enregistrer</button>
    </div>
  </div>
</div>

<!-- MODAL DÉTAIL -->
<div class="modal-overlay" id="mCD">
  <div class="modal" style="width:660px">
    <div class="mhdr"><h3 id="cdT">Détail</h3><button class="mclose" onclick="document.getElementById('mCD').classList.remove('open')"><i class="ph ph-x" aria-hidden="true"></i></button></div>
    <div class="mbody" id="cdB"></div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
// ── TABS
function showTab(id,btn){
  document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('#mainTabs .tab-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('tab-'+id).classList.add('active'); btn.classList.add('active');
}
// ── FILTRAGE
function filterConsos(q){
  q=q||document.getElementById('searchQ').value;
  const a=document.getElementById('alerteOnly').checked;
  document.querySelectorAll('#consoGrid .conso-card').forEach(c=>{
    const mQ=!q||c.dataset.lib.includes(q.toLowerCase());
    const mA=!a||c.dataset.alerte==='1';
    c.style.display=(mQ&&mA)?'':'none';
  });
}

// ── STOCK INDICATOR
function updStockActuel(prefix){
  const sel=document.getElementById(prefix+'Conso');
  const o=sel.options[sel.selectedIndex];
  if(!o||!o.value){ document.getElementById(prefix+'StockInfo').style.display='none'; return; }
  const stock=parseFloat(o.dataset.stock||0);
  const seuil=parseFloat(o.dataset.seuil||0);
  const unite=o.dataset.unite||'';
  const prix=parseFloat(o.dataset.prix||0);
  const info=document.getElementById(prefix+'StockInfo');
  const ind=document.getElementById(prefix+'StockIndicator');
  const val=document.getElementById(prefix+'StockVal');
  const un=document.getElementById(prefix+'StockUnite');
  const msg=document.getElementById(prefix+'StockMsg');
  info.style.display='block';
  val.textContent=stock.toLocaleString('fr-FR',{minimumFractionDigits:0,maximumFractionDigits:1});
  un.textContent=unite+'(s)';
  ind.className='stock-indicator '+(stock<=0?'empty':stock<=seuil?'low':'ok');
  if(prefix==='rec'){
    msg.innerHTML=stock<=0?'<span style="color:#c62828;font-weight:600">Stock épuisé — réception urgente</span>':
                  stock<=seuil?'<span style="color:#e65100;font-weight:600">Stock bas — réapprovisionnement recommandé</span>':
                  '<span style="color:#2e7d32">Stock suffisant</span>';
    // pré-remplir prix
    if(prix>0) document.getElementById('recPrix').value=prix;
    calcRecTotal();
  } else {
    msg.innerHTML=stock<=0?'<span style="color:#c62828;font-weight:600">⚠️ Stock épuisé ! Faites une réception avant de distribuer.</span>':
                  stock<=seuil?'<span style="color:#e65100;font-weight:600">⚠️ Stock bas</span>':
                  '<span style="color:#2e7d32">✅ Stock disponible</span>';
    if(prix>0) document.getElementById('distPrix').value=prix;
    calcDistTotal();
  }
}

// ── RÉCEPTION
function calcRecTotal(){
  const q=parseFloat(document.getElementById('recQte').value)||0;
  const p=parseFloat(document.getElementById('recPrix').value)||0;
  document.getElementById('recTotal').value=q*p>0?(q*p).toLocaleString('fr-FR')+' FCFA':'';
}
function resetRec(){
  ['recConso','recFourn','recBon','recNotes'].forEach(i=>document.getElementById(i).value='');
  document.getElementById('recQte').value=''; document.getElementById('recPrix').value='';
  document.getElementById('recTotal').value=''; document.getElementById('recDate').value='<?= date('Y-m-d') ?>';
  document.getElementById('recAlert').innerHTML=''; document.getElementById('recStockInfo').style.display='none';
}
function saveRec(){
  const cid=document.getElementById('recConso').value;
  const qte=document.getElementById('recQte').value;
  if(!cid||!qte||parseFloat(qte)<=0){
    document.getElementById('recAlert').innerHTML='<div class="alert alert-danger">Consommable et quantité obligatoires.</div>'; return;
  }
  ap({action:'reception', consommable_id:cid, quantite:qte,
    prix_unitaire:document.getElementById('recPrix').value||0,
    date_reception:document.getElementById('recDate').value,
    fournisseur:document.getElementById('recFourn').value,
    numero_bon:document.getElementById('recBon').value,
    notes:document.getElementById('recNotes').value,
  }).then(d=>{
    if(d.success){toast(d.message,'success');resetRec();setTimeout(()=>location.reload(),1000);}
    else document.getElementById('recAlert').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;
  });
}

// ── DISTRIBUTION
function checkDistQte(){
  calcDistTotal();
  const sel=document.getElementById('distConso');
  const o=sel.options[sel.selectedIndex];
  const stock=o?parseFloat(o.dataset.stock||0):0;
  const qte=parseFloat(document.getElementById('distQte').value)||0;
  const warn=document.getElementById('distQteWarn');
  const btn=document.getElementById('btnDist');
  if(qte>stock&&stock>0){warn.style.display='block';btn.disabled=true;}
  else{warn.style.display='none';btn.disabled=false;}
}
function calcDistTotal(){
  const q=parseFloat(document.getElementById('distQte').value)||0;
  const p=parseFloat(document.getElementById('distPrix').value)||0;
  document.getElementById('distTotal').value=q*p>0?(q*p).toLocaleString('fr-FR')+' FCFA':'';
}
function resetDist(){
  ['distConso','distSite','distBL','distNotes'].forEach(i=>document.getElementById(i).value='');
  document.getElementById('distQte').value=''; document.getElementById('distPrix').value='';
  document.getElementById('distTotal').value=''; document.getElementById('distDate').value='<?= date('Y-m-d') ?>';
  document.getElementById('distAlert').innerHTML=''; document.getElementById('distStockInfo').style.display='none';
  document.getElementById('distQteWarn').style.display='none';
  document.getElementById('distFichierBL').value='';
  document.getElementById('distBLPreview').style.display='none';
  document.getElementById('btnDist').disabled=false;
}
function saveDist(){
  const cid=document.getElementById('distConso').value;
  const sid=document.getElementById('distSite').value;
  const qte=document.getElementById('distQte').value;
  const fichierBL=document.getElementById('distFichierBL').files[0];

  if(!cid||!sid||!qte||parseFloat(qte)<=0){
    document.getElementById('distAlert').innerHTML='<div class="alert alert-danger">Consommable, site et quantité obligatoires.</div>'; return;
  }
  if(!fichierBL){
    document.getElementById('distAlert').innerHTML='<div class="alert alert-danger"><i class="ph ph-warning" aria-hidden="true"></i> Le bon de livraison (PDF ou image) est obligatoire.</div>';
    document.getElementById('distFichierBL').focus(); return;
  }

  const btn=document.getElementById('btnDist');
  btn.disabled=true; btn.textContent='⏳ Envoi en cours…';

  const fd=new FormData();
  fd.append('action','distribution');
  fd.append('consommable_id',cid);
  fd.append('site_id',sid);
  fd.append('quantite',qte);
  fd.append('prix_unitaire',document.getElementById('distPrix').value||0);
  fd.append('date_livraison',document.getElementById('distDate').value);
  fd.append('bon_livraison',document.getElementById('distBL').value);
  fd.append('notes',document.getElementById('distNotes').value);
  fd.append('fichier_bl',fichierBL);

  fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd})
    .then(r=>r.json())
    .then(d=>{
      btn.disabled=false; btn.textContent='📤 Valider la distribution';
      if(d.success){toast(d.message,'success');resetDist();setTimeout(()=>location.reload(),1000);}
      else document.getElementById('distAlert').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;
    })
    .catch(()=>{
      btn.disabled=false; btn.textContent='📤 Valider la distribution';
      document.getElementById('distAlert').innerHTML='<div class="alert alert-danger">Erreur réseau. Réessayez.</div>';
    });
}

// Preview du fichier BL sélectionné
document.getElementById('distFichierBL').addEventListener('change',function(){
  const prev=document.getElementById('distBLPreview');
  if(this.files.length){
    prev.style.display='block';
    prev.textContent='✔ '+this.files[0].name+' ('+( this.files[0].size/1024).toFixed(1)+' Ko)';
  } else { prev.style.display='none'; }
});

// ── MODAL CONSOMMABLE
function openMC(){ resetCF(); document.getElementById('mC').classList.add('open'); }
function closeMC(){ document.getElementById('mC').classList.remove('open'); }
function resetCF(){
  ['cId','cCode','cLib','cDesc'].forEach(i=>document.getElementById(i).value='');
  document.getElementById('cSeuil').value='10'; document.getElementById('cPrix').value='0';
  document.getElementById('cUnite').value='unite'; document.getElementById('mCAlert').innerHTML='';
  document.getElementById('mCT').textContent='Nouveau consommable'; document.getElementById('cCode').disabled=false;
}
function saveConso(){
  const id=document.getElementById('cId').value, btn=document.getElementById('bSC');
  btn.disabled=true; btn.textContent='Enregistrement…';
  ap({action:id?'update_conso':'create_conso', id,
    code:document.getElementById('cCode').value, libelle:document.getElementById('cLib').value,
    unite:document.getElementById('cUnite').value, seuil_alerte:document.getElementById('cSeuil').value,
    prix_unitaire:document.getElementById('cPrix').value||0, description:document.getElementById('cDesc').value,
  }).then(d=>{
    btn.disabled=false; btn.textContent='💾 Enregistrer';
    if(d.success){toast(d.message,'success');closeMC();setTimeout(()=>location.reload(),800);}
    else document.getElementById('mCAlert').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;
  });
}

// ── DÉTAIL
function viewConso(id){
  document.getElementById('mCD').classList.add('open');
  document.getElementById('cdB').innerHTML='<div style="text-align:center;padding:40px;color:var(--muted)">Chargement…</div>';
  ap({action:'get_conso',id}).then(d=>{
    if(!d.success)return;
    const r=d.data;
    document.getElementById('cdT').textContent=r.code+' — '+r.libelle;
    const sites=r.stock_par_site.map(s=>{
      const pct=r.seuil_alerte>0?Math.min(100,s.quantite/r.seuil_alerte*100):100;
      return `<div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:3px">
          <span>${s.site_nom}</span><strong>${s.quantite} ${r.unite}</strong>
        </div>
        <div style="height:5px;background:var(--border);border-radius:3px">
          <div style="width:${pct}%;height:100%;background:${pct<=50?'var(--danger-d)':pct<=100?'var(--warning-d)':'var(--success-d)'};border-radius:3px"></div>
        </div>
      </div>`;}).join('')||'<p style="color:var(--muted);font-size:13px">Aucun stock par site.</p>';
    const recs=r.receptions.map(rc=>`
      <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);font-size:12.5px">
        <span class="flux-badge reception">📥 +${rc.quantite}</span>
        <div style="flex:1">${rc.date_reception} · ${rc.fournisseur||'—'} ${rc.numero_bon?'· '+rc.numero_bon:''}</div>
        <div style="font-weight:600">${rc.prix_total>0?rc.prix_total.toLocaleString('fr-FR')+' FCFA':''}</div>
      </div>`).join('')||'<p style="color:var(--muted);font-size:13px">Aucune réception.</p>';
    const dists=r.distributions.map(dl=>`
      <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);font-size:12.5px">
        <span class="flux-badge distribution">📤 -${dl.quantite}</span>
        <div style="flex:1">${dl.date_livraison} · ${dl.site}</div>
        <div style="font-weight:600">${dl.prix_total>0?dl.prix_total.toLocaleString('fr-FR')+' FCFA':''}</div>
      </div>`).join('')||'<p style="color:var(--muted);font-size:13px">Aucune distribution.</p>';
    document.getElementById('cdB').innerHTML=`
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 20px;margin-bottom:20px">
        ${[['Code',r.code],['Unité',r.unite],
           ['Stock global','<strong style="font-size:22px;font-family:Montserrat,sans-serif;color:var(--navy)">'+r.stock_global+' '+r.unite+'(s)</strong>'],
           ['Prix unitaire',r.prix_unitaire>0?r.prix_unitaire.toLocaleString('fr-FR')+' FCFA':'—'],
           ['Valeur stock',r.prix_unitaire>0?'<strong>'+(r.stock_global*r.prix_unitaire).toLocaleString('fr-FR')+' FCFA</strong>':'—'],
           ['Seuil alerte',r.seuil_alerte],['Description',r.description||'—']
          ].map(([l,v])=>`<div style="padding:7px 0;border-bottom:1px solid var(--border)">
            <div style="font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">${l}</div>
            <div style="font-size:13.5px">${v}</div></div>`).join('')}
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
        <div>
          <h4 style="font-family:'Montserrat',sans-serif;font-size:13px;margin-bottom:10px"><i class="ph ph-map-pin" aria-hidden="true"></i> Stock par site</h4>${sites}
          <h4 style="font-family:'Montserrat',sans-serif;font-size:13px;margin:14px 0 10px"><i class="ph ph-download-simple" aria-hidden="true"></i> Dernières réceptions</h4>${recs}
        </div>
        <div>
          <h4 style="font-family:'Montserrat',sans-serif;font-size:13px;margin-bottom:10px"><i class="ph ph-chart-line-up" aria-hidden="true"></i> Distributions 6 mois</h4>
          <canvas id="chartC${id}" height="120"></canvas>
          <h4 style="font-family:'Montserrat',sans-serif;font-size:13px;margin:14px 0 10px"><i class="ph ph-upload-simple" aria-hidden="true"></i> Dernières distributions</h4>${dists}
        </div>
      </div>`;
    const labels=r.conso_mensuelle.map(m=>m.mois), vals=r.conso_mensuelle.map(m=>m.total);
    if(labels.length) new Chart(document.getElementById('chartC'+id),{
      type:'bar',data:{labels,datasets:[{data:vals,backgroundColor:'rgba(26,86,160,.7)',borderRadius:5,label:'Distributions'}]},
      options:{responsive:true,plugins:{legend:{display:false}},scales:{x:{grid:{display:false},ticks:{font:{size:10}}},y:{ticks:{font:{size:10}}}}}
    });
    if(<?= can('consommables','can_update')?'true':'false' ?>)
      document.getElementById('cdB').insertAdjacentHTML('afterbegin',
        `<div style="text-align:right;margin-bottom:12px;display:flex;gap:8px;justify-content:flex-end">
          <button class="btn btn-secondary btn-sm" onclick="editConso(${r.id})">✏️ Modifier</button>
        </div>`);
  });
}
function editConso(id){
  ap({action:'get_conso',id}).then(d=>{
    if(!d.success)return;
    const r=d.data;
    document.getElementById('mCT').textContent='Modifier : '+r.libelle;
    document.getElementById('cId').value=r.id; document.getElementById('cCode').value=r.code;
    document.getElementById('cCode').disabled=true; document.getElementById('cLib').value=r.libelle;
    document.getElementById('cUnite').value=r.unite; document.getElementById('cSeuil').value=r.seuil_alerte;
    document.getElementById('cPrix').value=r.prix_unitaire||0; document.getElementById('cDesc').value=r.description||'';
    document.getElementById('mCD').classList.remove('open'); document.getElementById('mC').classList.add('open');
  });
}

function ap(data){
  const fd=new FormData();
  for(const[k,v]of Object.entries(data))if(v!==undefined)fd.append(k,v);
  return fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(r=>r.json());
}
['mC','mCD'].forEach(id=>document.getElementById(id).addEventListener('click',e=>{if(e.target===e.currentTarget)e.currentTarget.classList.remove('open');}));
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
