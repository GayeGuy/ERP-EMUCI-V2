<?php
// ============================================================
//  pages/dashboard.php  —  Tableau de bord principal
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

require_auth();
// Dashboard accessible à tous les profils connectés sans restriction

$user       = current_user();
$page_title = 'Tableau de bord';
$page_subtitle = 'Vue d\'ensemble de votre inventaire';
$active_page   = 'dashboard';

// ============================================================
//  DONNÉES DU DASHBOARD
// ============================================================

require_once __DIR__ . '/../includes/upload.php';

$role_slug  = $user['role_slug'];
$site_force = ($role_slug === 'coordinateur_site' && $user['site_id']) ? (int)$user['site_id'] : 0;

// ============================================================
//  DONNÉES SELON LE PROFIL
// ============================================================

// ── COORDINATEUR DE SITE : tout filtré sur son site
if ($role_slug === 'coordinateur_site' && $site_force) {
    $sid = $site_force;
    $site_info = db_fetch_one("SELECT * FROM sites WHERE id=?", [$sid]);

    $kpi_equip_total  = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND site_id=?", [$sid]);
    $kpi_equip_info   = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND categorie='informatique' AND site_id=?", [$sid]);
    $kpi_equip_op     = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND categorie='operationnel' AND site_id=?", [$sid]);
    $kpi_equip_bon    = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND etat IN ('neuf','bon') AND site_id=?", [$sid]);
    $kpi_equip_hs     = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND etat='hs' AND site_id=?", [$sid]);
    $kpi_fin_cycle    = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND date_fin_cycle BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND site_id=?", [$sid]);
    $kpi_receptions_attente = (int)db_fetch_value("SELECT COUNT(*) FROM receptions_site WHERE site_id=? AND statut='en_attente'", [$sid]);
    $kpi_receptions_litige  = (int)db_fetch_value("SELECT COUNT(*) FROM receptions_site WHERE site_id=? AND statut='litige'", [$sid]);

    $equip_par_etat = db_fetch_all("SELECT etat, COUNT(*) AS total FROM equipements WHERE actif=1 AND site_id=? GROUP BY etat", [$sid]);
    $equip_par_type = db_fetch_all("SELECT n.libelle, COUNT(e.id) AS total FROM equipements e JOIN nomenclatures n ON n.id=e.nomenclature_id WHERE e.actif=1 AND e.site_id=? GROUP BY n.id ORDER BY total DESC LIMIT 8", [$sid]);
    $conso_fcfa_site = db_fetch_all(
        "SELECT s.nom AS site_nom, COALESCE(SUM(lc.prix_total),0) AS montant
         FROM livraisons_consommables lc
         JOIN sites s ON s.id=lc.site_id
         WHERE lc.site_id=?
         AND lc.date_livraison >= DATE_SUB(CURDATE(),INTERVAL 12 MONTH)
         GROUP BY s.id ORDER BY montant DESC", [$sid]
    );
    $fin_cycle_list = db_fetch_all("SELECT e.numero_serie_interne,e.date_fin_cycle,e.etat,n.libelle AS type_equip,DATEDIFF(e.date_fin_cycle,CURDATE()) AS jours_restants FROM equipements e JOIN nomenclatures n ON n.id=e.nomenclature_id WHERE e.actif=1 AND e.site_id=? AND e.date_fin_cycle IS NOT NULL AND e.date_fin_cycle<=DATE_ADD(CURDATE(),INTERVAL 60 DAY) ORDER BY e.date_fin_cycle ASC LIMIT 10", [$sid]);
    $receptions_recentes = db_fetch_all("SELECT rs.*,c.libelle AS conso_lib,c.unite,e.numero_serie_interne AS equip_num,n.libelle AS equip_type FROM receptions_site rs LEFT JOIN consommables c ON c.id=rs.consommable_id LEFT JOIN equipements e ON e.id=rs.equipement_id LEFT JOIN nomenclatures n ON n.id=e.nomenclature_id WHERE rs.site_id=? ORDER BY rs.created_at DESC LIMIT 8", [$sid]);
    $stock_conso_site = db_fetch_all("SELECT c.libelle,c.unite,sc.quantite,c.seuil_alerte FROM stock_consommables_site sc JOIN consommables c ON c.id=sc.consommable_id WHERE sc.site_id=? ORDER BY c.libelle", [$sid]);

    $kpi_sites_actifs = null;
    $kpi_users_actifs = null;
    $kpi_conso_alertes= (int)db_fetch_value("SELECT COUNT(*) FROM stock_consommables_site sc JOIN consommables c ON c.id=sc.consommable_id WHERE sc.site_id=? AND sc.quantite<=c.seuil_alerte", [$sid]);
    $derniers_mouvements = db_fetch_all("SELECT me.type,me.created_at,e.numero_serie_interne,n.libelle AS type_equip,s1.nom AS site_source,s2.nom AS site_dest,CONCAT(u.prenom,' ',u.nom) AS agent FROM mouvements_equipements me JOIN equipements e ON e.id=me.equipement_id JOIN nomenclatures n ON n.id=e.nomenclature_id LEFT JOIN sites s1 ON s1.id=me.site_source_id LEFT JOIN sites s2 ON s2.id=me.site_dest_id LEFT JOIN users u ON u.id=me.created_by WHERE me.site_dest_id=? OR me.site_source_id=? ORDER BY me.created_at DESC LIMIT 6", [$sid, $sid]);
    $derniers_audit = [];
    $stock_bas_list = array_filter($stock_conso_site, fn($r) => $r['quantite'] <= $r['seuil_alerte']);
    $sites_par_type  = db_fetch_all("SELECT type, COUNT(*) AS nb FROM sites WHERE actif=1 GROUP BY type ORDER BY nb DESC");
    $bobines_par_site = db_fetch_all("SELECT s.nom AS site_nom, COUNT(b.id) AS nb_bobines, COALESCE(SUM(b.stock_systeme),0) AS total_films FROM sites s LEFT JOIN op_bobines b ON b.site_id=s.id AND b.statut IN ('en_cours','en_stock') WHERE s.actif=1 AND s.id=? GROUP BY s.id", [$sid]);
    $rivets_par_site  = db_fetch_all("SELECT s.nom AS site_nom, COALESCE(sr.quantite,0) AS stock_rivets FROM sites s LEFT JOIN op_stock_rivets sr ON sr.site_id=s.id WHERE s.actif=1 AND s.id=?", [$sid]);
    $interv_par_site  = db_fetch_all("SELECT s.nom AS site_nom, COUNT(im.id) AS nb_interv FROM sites s LEFT JOIN interventions_maintenance im ON im.site_id=s.id AND YEAR(im.date_intervention)=YEAR(CURDATE()) AND MONTH(im.date_intervention)=MONTH(CURDATE()) WHERE s.actif=1 AND s.id=? GROUP BY s.id", [$sid]);

// ── MAINTENANCE INFORMATIQUE : tout filtré catégorie=informatique
} elseif ($role_slug === 'maintenance_info') {
    $kpi_equip_total  = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND categorie='informatique'");
    $kpi_equip_info   = $kpi_equip_total;
    $kpi_equip_op     = 0;
    $kpi_equip_bon    = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND categorie='informatique' AND etat IN ('neuf','bon')");
    $kpi_equip_hs     = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND categorie='informatique' AND etat='hs'");
    $kpi_fin_cycle    = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND categorie='informatique' AND date_fin_cycle BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)");
    $kpi_sites_actifs = (int)db_fetch_value("SELECT COUNT(DISTINCT site_id) FROM equipements WHERE actif=1 AND categorie='informatique'");
    $kpi_users_actifs = null;
    $kpi_conso_alertes= null;

    $equip_par_etat  = db_fetch_all("SELECT etat, COUNT(*) AS total FROM equipements WHERE actif=1 AND categorie='informatique' GROUP BY etat");
    $equip_par_type  = db_fetch_all("SELECT n.libelle, COUNT(e.id) AS total FROM equipements e JOIN nomenclatures n ON n.id=e.nomenclature_id WHERE e.actif=1 AND e.categorie='informatique' GROUP BY n.id ORDER BY total DESC LIMIT 8");
    $sites_par_type  = db_fetch_all("SELECT type, COUNT(*) AS nb FROM sites WHERE actif=1 GROUP BY type ORDER BY nb DESC");
    $fin_cycle_list  = db_fetch_all("SELECT e.numero_serie_interne,e.date_fin_cycle,e.etat,n.libelle AS type_equip,s.nom AS site_nom,DATEDIFF(e.date_fin_cycle,CURDATE()) AS jours_restants FROM equipements e JOIN nomenclatures n ON n.id=e.nomenclature_id LEFT JOIN sites s ON s.id=e.site_id WHERE e.actif=1 AND e.categorie='informatique' AND e.date_fin_cycle IS NOT NULL AND e.date_fin_cycle<=DATE_ADD(CURDATE(),INTERVAL 60 DAY) ORDER BY e.date_fin_cycle ASC LIMIT 10");
    $conso_fcfa_site = [];
    $stock_bas_list  = [];
    $derniers_audit  = db_fetch_all("SELECT al.action,al.module,al.description,al.created_at,CONCAT(u.prenom,' ',u.nom) AS user_nom FROM audit_log al LEFT JOIN users u ON u.id=al.user_id WHERE al.module='equipements' ORDER BY al.created_at DESC LIMIT 8");
    $derniers_mouvements = db_fetch_all("SELECT me.type,me.created_at,e.numero_serie_interne,n.libelle AS type_equip,s1.nom AS site_source,s2.nom AS site_dest,CONCAT(u.prenom,' ',u.nom) AS agent FROM mouvements_equipements me JOIN equipements e ON e.id=me.equipement_id JOIN nomenclatures n ON n.id=e.nomenclature_id LEFT JOIN sites s1 ON s1.id=me.site_source_id LEFT JOIN sites s2 ON s2.id=me.site_dest_id LEFT JOIN users u ON u.id=me.created_by WHERE e.categorie='informatique' ORDER BY me.created_at DESC LIMIT 6");
    $kpi_receptions_attente = null;
    $kpi_receptions_litige  = null;
    $site_info = null;
    $receptions_recentes = [];
    $stock_conso_site = [];

// ── SUPERVISEUR OPÉRATION : vue centrée sur les actions coordinateurs
} elseif ($role_slug === 'superviseur_operation') {
    $kpi_equip_total  = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1");
    $kpi_equip_info   = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND categorie='informatique'");
    $kpi_equip_op     = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND categorie='operationnel'");
    $kpi_equip_bon    = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND etat IN ('neuf','bon')");
    $kpi_equip_hs     = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND etat='hs'");
    $kpi_fin_cycle    = null;
    $kpi_sites_actifs = (int)db_fetch_value("SELECT COUNT(*) FROM sites WHERE actif=1");
    $kpi_users_actifs = (int)db_fetch_value("SELECT COUNT(*) FROM users WHERE actif=1 AND role_id=(SELECT id FROM roles WHERE slug='coordinateur_site')");
    $kpi_conso_alertes= null;
    $kpi_receptions_attente = (int)db_fetch_value("SELECT COUNT(*) FROM receptions_site WHERE statut='en_attente'");
    $kpi_receptions_litige  = (int)db_fetch_value("SELECT COUNT(*) FROM receptions_site WHERE statut='litige'");

    $receptions_recentes = db_fetch_all("SELECT rs.*,s.nom AS site_nom,c.libelle AS conso_lib,c.unite,e.numero_serie_interne AS equip_num,n.libelle AS equip_type,CONCAT(u.prenom,' ',u.nom) AS createur FROM receptions_site rs JOIN sites s ON s.id=rs.site_id LEFT JOIN consommables c ON c.id=rs.consommable_id LEFT JOIN equipements e ON e.id=rs.equipement_id LEFT JOIN nomenclatures n ON n.id=e.nomenclature_id LEFT JOIN users u ON u.id=rs.created_by ORDER BY rs.created_at DESC LIMIT 15");
    $equip_par_etat  = db_fetch_all("SELECT etat, COUNT(*) AS total FROM equipements WHERE actif=1 GROUP BY etat");
    $equip_par_type  = [];
    $sites_par_type  = db_fetch_all("SELECT type, COUNT(*) AS nb FROM sites WHERE actif=1 GROUP BY type ORDER BY nb DESC");
    $interv_par_site = db_fetch_all("SELECT s.nom AS site_nom, COUNT(im.id) AS nb_interv FROM sites s LEFT JOIN interventions_maintenance im ON im.site_id=s.id AND YEAR(im.date_intervention)=YEAR(CURDATE()) AND MONTH(im.date_intervention)=MONTH(CURDATE()) WHERE s.actif=1 GROUP BY s.id ORDER BY nb_interv DESC LIMIT 10");
    $bobines_par_site= db_fetch_all("SELECT s.nom AS site_nom, COUNT(b.id) AS nb_bobines, COALESCE(SUM(b.stock_systeme),0) AS total_films FROM sites s LEFT JOIN op_bobines b ON b.site_id=s.id AND b.statut IN ('en_cours','en_stock') WHERE s.actif=1 GROUP BY s.id ORDER BY nb_bobines DESC LIMIT 10");
    $rivets_par_site = db_fetch_all("SELECT s.nom AS site_nom, COALESCE(sr.quantite,0) AS stock_rivets FROM sites s LEFT JOIN op_stock_rivets sr ON sr.site_id=s.id WHERE s.actif=1 ORDER BY stock_rivets DESC LIMIT 10");
    $conso_fcfa_site = db_fetch_all(
        "SELECT s.nom AS site_nom, COALESCE(SUM(lc.prix_total),0) AS montant
         FROM livraisons_consommables lc
         JOIN sites s ON s.id=lc.site_id
         WHERE lc.date_livraison >= DATE_SUB(CURDATE(),INTERVAL 12 MONTH)
         GROUP BY s.id
         ORDER BY montant DESC"
    );
    $fin_cycle_list  = [];
    $stock_bas_list  = [];
    $derniers_audit  = db_fetch_all(
        "SELECT al.action,al.module,al.description,al.created_at,CONCAT(u.prenom,' ',u.nom) AS user_nom
         FROM audit_log al
         JOIN users u ON u.id=al.user_id
         JOIN roles r ON r.id=u.role_id
         WHERE r.slug='coordinateur_site'
         ORDER BY al.created_at DESC LIMIT 15"
    );
    $derniers_mouvements = [];
    $site_info = null;
    $stock_conso_site = [];

// ── ADMIN / GESTIONNAIRE / SUPERADMIN : vue globale complète
} else {
    $site_info = null;
    $kpi_receptions_attente = (int)db_fetch_value("SELECT COUNT(*) FROM receptions_site WHERE statut='en_attente'");
    $kpi_receptions_litige  = (int)db_fetch_value("SELECT COUNT(*) FROM receptions_site WHERE statut='litige'");
    $kpi_equip_total  = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1");
    $kpi_equip_info   = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND categorie='informatique'");
    $kpi_equip_op     = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND categorie='operationnel'");
    $kpi_equip_bon    = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND etat IN ('neuf','bon')");
    $kpi_equip_hs     = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND etat='hs'");
    $kpi_fin_cycle    = (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1 AND date_fin_cycle IS NOT NULL AND date_fin_cycle BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)");
    $kpi_sites_actifs = (int)db_fetch_value("SELECT COUNT(*) FROM sites WHERE actif=1");
    $kpi_users_actifs = (int)db_fetch_value("SELECT COUNT(*) FROM users WHERE actif=1");
    $kpi_conso_alertes= (int)db_fetch_value("SELECT COUNT(*) FROM consommables WHERE stock_global<=seuil_alerte");
    $bobines_par_site = db_fetch_all("SELECT s.nom AS site_nom, COUNT(b.id) AS nb_bobines, COALESCE(SUM(b.stock_systeme),0) AS total_films FROM sites s LEFT JOIN op_bobines b ON b.site_id=s.id AND b.statut IN ('en_cours','en_stock') WHERE s.actif=1 GROUP BY s.id ORDER BY nb_bobines DESC LIMIT 10");
    $rivets_par_site  = db_fetch_all("SELECT s.nom AS site_nom, COALESCE(sr.quantite,0) AS stock_rivets FROM sites s LEFT JOIN op_stock_rivets sr ON sr.site_id=s.id WHERE s.actif=1 ORDER BY stock_rivets DESC LIMIT 10");
    $interv_par_site  = db_fetch_all("SELECT s.nom AS site_nom, COUNT(im.id) AS nb_interv FROM sites s LEFT JOIN interventions_maintenance im ON im.site_id=s.id AND YEAR(im.date_intervention)=YEAR(CURDATE()) AND MONTH(im.date_intervention)=MONTH(CURDATE()) WHERE s.actif=1 GROUP BY s.id ORDER BY nb_interv DESC LIMIT 10");
    $equip_par_etat  = db_fetch_all("SELECT etat, COUNT(*) AS total FROM equipements WHERE actif=1 GROUP BY etat ORDER BY total DESC");
    $equip_par_type  = db_fetch_all("SELECT n.libelle, COUNT(e.id) AS total FROM equipements e JOIN nomenclatures n ON n.id=e.nomenclature_id WHERE e.actif=1 GROUP BY n.id ORDER BY total DESC LIMIT 8");
    $sites_par_type  = db_fetch_all("SELECT type, COUNT(*) AS nb FROM sites WHERE actif=1 GROUP BY type ORDER BY nb DESC");
    $conso_fcfa_site = db_fetch_all(
        "SELECT s.nom AS site_nom, COALESCE(SUM(lc.prix_total),0) AS montant
         FROM livraisons_consommables lc
         JOIN sites s ON s.id=lc.site_id
         WHERE lc.date_livraison >= DATE_SUB(CURDATE(),INTERVAL 12 MONTH)
         GROUP BY s.id
         ORDER BY montant DESC"
    );
    $fin_cycle_list  = db_fetch_all("SELECT e.numero_serie_interne,e.date_fin_cycle,e.etat,n.libelle AS type_equip,s.nom AS site_nom,CONCAT(u.prenom,' ',u.nom) AS utilisateur,DATEDIFF(e.date_fin_cycle,CURDATE()) AS jours_restants FROM equipements e JOIN nomenclatures n ON n.id=e.nomenclature_id LEFT JOIN sites s ON s.id=e.site_id LEFT JOIN users u ON u.id=e.utilisateur_id WHERE e.actif=1 AND e.date_fin_cycle IS NOT NULL AND e.date_fin_cycle<=DATE_ADD(CURDATE(),INTERVAL 60 DAY) ORDER BY e.date_fin_cycle ASC LIMIT 10");
    $stock_bas_list  = db_fetch_all("SELECT libelle, stock_global, seuil_alerte, unite FROM consommables WHERE stock_global<=seuil_alerte ORDER BY (stock_global/seuil_alerte) ASC LIMIT 6");
    // Gestionnaire de stock : pas d'audit dans le dashboard
    $derniers_audit  = $role_slug === 'gestionnaire_stock' ? [] : db_fetch_all("SELECT al.action,al.module,al.description,al.created_at,CONCAT(u.prenom,' ',u.nom) AS user_nom FROM audit_log al LEFT JOIN users u ON u.id=al.user_id ORDER BY al.created_at DESC LIMIT 8");
    $derniers_mouvements = db_fetch_all("SELECT me.type,me.created_at,e.numero_serie_interne,n.libelle AS type_equip,s1.nom AS site_source,s2.nom AS site_dest,CONCAT(u.prenom,' ',u.nom) AS agent FROM mouvements_equipements me JOIN equipements e ON e.id=me.equipement_id JOIN nomenclatures n ON n.id=e.nomenclature_id LEFT JOIN sites s1 ON s1.id=me.site_source_id LEFT JOIN sites s2 ON s2.id=me.site_dest_id LEFT JOIN users u ON u.id=me.created_by ORDER BY me.created_at DESC LIMIT 6");
    $receptions_recentes = [];
    $stock_conso_site = [];
}

// Préparer les données JSON pour les graphiques
$etat_labels  = json_encode(array_column($equip_par_etat, 'etat'));
$etat_values  = json_encode(array_column($equip_par_etat, 'total'));
$type_labels  = json_encode(array_column($equip_par_type, 'libelle'));
$type_values  = json_encode(array_column($equip_par_type, 'total'));
$conso_sites_json = json_encode($conso_fcfa_site ?? []);
$site_labels  = json_encode(array_column($sites_par_type ?? [], 'type'));
$site_values  = json_encode(array_column($sites_par_type ?? [], 'nb'));

include __DIR__ . '/../templates/header.php';
?>

<!-- ===== STYLES DASHBOARD ===== -->
<style>
/* KPI GRID */
.kpi-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 24px;
}
.kpi-card {
  background: white;
  border-radius: 14px;
  border: 1px solid var(--border);
  padding: 20px 22px;
  display: flex;
  align-items: center;
  gap: 16px;
  position: relative;
  overflow: hidden;
  transition: transform .2s, box-shadow .2s;
  animation: fadeUp .4s ease both;
}
.kpi-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }
.kpi-card::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 3px;
  border-radius: 14px 14px 0 0;
}
.kpi-card.blue::before   { background: linear-gradient(90deg, #2563a8, #3b82f6); }
.kpi-card.green::before  { background: linear-gradient(90deg, #27ae60, #2ecc71); }
.kpi-card.orange::before { background: linear-gradient(90deg, #1a56a0, #f39c12); }
.kpi-card.red::before    { background: linear-gradient(90deg, #e74c3c, #e91e63); }
.kpi-card.purple::before { background: linear-gradient(90deg, #8e44ad, #9b59b6); }
.kpi-card.teal::before   { background: linear-gradient(90deg, #16a085, #1abc9c); }

.kpi-icon {
  width: 52px; height: 52px;
  border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  font-size: 24px; flex-shrink: 0;
}
.kpi-card.blue   .kpi-icon { background: #eff6ff; }
.kpi-card.green  .kpi-icon { background: #f0fdf4; }
.kpi-card.orange .kpi-icon { background: #fff7ed; }
.kpi-card.red    .kpi-icon { background: #fef2f2; }
.kpi-card.purple .kpi-icon { background: #faf5ff; }
.kpi-card.teal   .kpi-icon { background: #f0fdfa; }

.kpi-info { flex: 1; }
.kpi-value {
  font-family: 'Montserrat', sans-serif;
  font-size: 30px; font-weight: 800;
  color: var(--navy); line-height: 1;
  margin-bottom: 4px;
}
.kpi-label { font-size: 12px; color: var(--muted); font-weight: 500; }
.kpi-sub   { font-size: 11px; color: var(--muted); margin-top: 6px; }

/* SECTION GRID */
.dash-grid { display: grid; gap: 20px; margin-bottom: 20px; }
.dash-grid.cols-1 { grid-template-columns: 1fr; }
.dash-grid.cols-2 { grid-template-columns: 1fr 1fr; }
.dash-grid.cols-3 { grid-template-columns: 2fr 1fr; }
.dash-grid.cols-32 { grid-template-columns: 1fr 1.6fr; }

.dash-card {
  background: white;
  border-radius: 14px;
  border: 1px solid var(--border);
  overflow: hidden;
  animation: fadeUp .5s ease both;
}
.dash-card-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
}
.dash-card-header h3 {
  font-family: 'Montserrat', sans-serif;
  font-size: 14px; font-weight: 700; color: var(--navy);
}
.dash-card-body { padding: 20px; }

/* CHART containers */
.chart-container { position: relative; width: 100%; }
.chart-container canvas { max-width: 100%; }

/* ALERTE ITEMS */
.alerte-item {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid var(--border);
}
.alerte-item:last-child { border-bottom: none; }
.alerte-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.alerte-dot.danger  { background: var(--danger); }
.alerte-dot.warning { background: var(--warning); }
.alerte-dot.ok      { background: var(--success); }
.alerte-info { flex: 1; }
.alerte-info .a-titre { font-size: 13px; font-weight: 500; }
.alerte-info .a-sub   { font-size: 11px; color: var(--muted); }
.alerte-badge { font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 6px; }
.alerte-badge.danger  { background: #fdf0ef; color: var(--danger); }
.alerte-badge.warning { background: #fef9e7; color: var(--warning); }

/* STOCK BAR */
.stock-item { margin-bottom: 14px; }
.stock-item:last-child { margin-bottom: 0; }
.stock-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
.stock-top .s-label { font-size: 13px; font-weight: 500; }
.stock-top .s-qty   { font-size: 12px; color: var(--muted); }
.stock-bar { height: 6px; background: var(--border); border-radius: 3px; overflow: hidden; }
.stock-fill { height: 100%; border-radius: 3px; transition: width .6s ease; }
.stock-fill.critical { background: var(--danger); }
.stock-fill.low      { background: var(--warning); }
.stock-fill.ok       { background: var(--success); }

/* AUDIT LIST */
.audit-item {
  display: flex; gap: 12px; align-items: flex-start;
  padding: 10px 0; border-bottom: 1px solid var(--border);
}
.audit-item:last-child { border-bottom: none; }
.audit-action-badge {
  font-size: 10px; font-weight: 700; padding: 3px 7px;
  border-radius: 5px; flex-shrink: 0; margin-top: 2px;
  text-transform: uppercase; letter-spacing: .5px;
}
.audit-action-badge.CREATE   { background: #d5f5e3; color: #1e8449; }
.audit-action-badge.UPDATE   { background: #d6eaf8; color: #1a5276; }
.audit-action-badge.DELETE   { background: #fdedec; color: #922b21; }
.audit-action-badge.LOGIN    { background: #fef9e7; color: #9a7d0a; }
.audit-action-badge.LOGOUT   { background: #eaecee; color: #424949; }
.audit-action-badge.EXPORT   { background: #faf5ff; color: #6c3483; }
.audit-action-badge.TRANSFER { background: #e8f8f5; color: #1e8449; }
.audit-desc  { font-size: 12.5px; flex: 1; }
.audit-meta  { font-size: 11px; color: var(--muted); margin-top: 2px; }

/* SITE CARDS */
.site-mini-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.site-mini {
  padding: 12px 14px;
  border: 1px solid var(--border);
  border-radius: 10px;
  transition: border-color .15s;
}
.site-mini:hover { border-color: var(--blue-mid, #1a56a0); }
.site-mini .s-nom  { font-size: 13px; font-weight: 600; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.site-mini .s-type { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; }
.site-mini .s-cnt  { font-family: 'Montserrat', sans-serif; font-size: 22px; font-weight: 800; color: var(--navy); }

/* ANIMATIONS */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}
.kpi-card:nth-child(1) { animation-delay: .05s; }
.kpi-card:nth-child(2) { animation-delay: .10s; }
.kpi-card:nth-child(3) { animation-delay: .15s; }
.kpi-card:nth-child(4) { animation-delay: .20s; }

/* WELCOME BANNER */
.welcome-banner {
  background: linear-gradient(135deg, var(--navy) 0%, #2D3E6E 60%, #3B5098 100%);
  border-radius: 20px;
  padding: 26px 30px;
  margin-bottom: 24px;
  display: flex; align-items: center; justify-content: space-between;
  color: white;
  position: relative; overflow: hidden;
  box-shadow: 0 8px 32px rgba(30,43,74,.2);
}
.welcome-banner::before {
  content: '';
  position: absolute; right: -40px; top: -40px;
  width: 200px; height: 200px; border-radius: 50%;
  border: 40px solid rgba(124,146,255,.12);
}
.welcome-banner::after {
  content: '';
  position: absolute; right: 80px; bottom: -60px;
  width: 160px; height: 160px; border-radius: 50%;
  border: 30px solid rgba(165,216,255,.08);
}
.wb-left h2 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 22px; font-weight: 800; margin-bottom: 5px;
}
.wb-left p { font-size: 13px; color: rgba(255,255,255,.6); }
.wb-right { display: flex; gap: 10px; position: relative; z-index: 1; }
.wb-btn {
  padding: 10px 20px; border-radius: 12px; font-size: 13px; font-weight: 700;
  text-decoration: none; cursor: pointer; border: none;
  transition: all .18s cubic-bezier(.4,0,.2,1);
  font-family: 'Manrope', sans-serif;
}
.wb-btn.primary { background: var(--primary); color: white; box-shadow: 0 4px 16px rgba(124,146,255,.4); }
.wb-btn.primary:hover { background: var(--primary-d); transform: translateY(-1px); }
.wb-btn.secondary { background: rgba(255,255,255,.12); color: white; border: 1.5px solid rgba(255,255,255,.2); backdrop-filter: blur(8px); }
.wb-btn.secondary:hover { background: rgba(255,255,255,.2); }

@media (max-width: 1100px) {
  .kpi-grid { grid-template-columns: repeat(2,1fr); }
  .dash-grid.cols-2, .dash-grid.cols-3, .dash-grid.cols-32 { grid-template-columns: 1fr; }
}
</style>


<!-- ===== WELCOME BANNER ===== -->
<div class="welcome-banner">
  <div class="wb-left">
    <h2>Bonjour, <?= h($user['prenom']) ?> 👋</h2>
    <p>
      <?= date('l d F Y') ?> · <?= h($user['role_nom']) ?>
      <?php if($site_force && ($site_info['nom']??'')): ?>
       · 🏢 <?= h($site_info['nom']) ?>
      <?php endif; ?>
    </p>
  </div>
  <div class="wb-right">
    <?php if(can('equipements','can_create')): ?>
    <a href="<?= APP_URL ?>/pages/equipements.php" class="wb-btn primary">+ Équipement</a>
    <?php endif; ?>
    <?php if(can('receptions','can_read') && $role_slug !== 'coordinateur_site'): ?>
    <a href="<?= APP_URL ?>/pages/reception_site.php" class="wb-btn secondary">📦 Réceptions</a>
    <?php endif; ?>
    <?php if(can('rapports','can_export') && $role_slug !== 'coordinateur_site'): ?>
    <a href="<?= APP_URL ?>/pages/rapports.php" class="wb-btn secondary">📊 Rapports</a>
    <?php endif; ?>
  </div>
</div>

<?php if($role_slug === 'coordinateur_site' && !empty($receptions_recentes)): ?>
<!-- ===== RÉCEPTIONS SITE (Coordinateur) ===== -->
<div class="dash-card" style="margin-bottom:20px">
  <div class="dash-card-header">
    <h3>📦 Mes réceptions récentes</h3>
    <a href="<?= APP_URL ?>/pages/reception_site.php" style="font-size:12px;color:var(--blue-mid,#1a56a0)">Voir tout →</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Date</th><th>Type</th><th>Article / Équipement</th>
        <th style="text-align:right">Qté</th><th>Statut</th><th>Fiche</th>
      </tr></thead>
      <tbody>
      <?php foreach($receptions_recentes as $rr): ?>
      <tr>
        <td style="font-size:12px;color:var(--muted)"><?= fmt_date($rr['date_reception']) ?></td>
        <td><span style="font-size:11px;font-weight:700;color:<?= $rr['type_reception']==='consommable'?'#27ae60':'#1a56a0' ?>"><?= $rr['type_reception']==='consommable'?'🧴 Conso':'💻 Équip' ?></span></td>
        <td style="font-size:13px">
          <?= $rr['type_reception']==='consommable' ? h($rr['conso_lib']??'—').' <small style="color:var(--muted)">'.h($rr['unite']??'').'</small>' : h($rr['equip_type']??'—').' <small style="color:var(--muted);font-family:monospace">'.h($rr['equip_num']??'').'</small>' ?>
        </td>
        <td style="text-align:right;font-weight:700"><?= $rr['quantite']?fmt_number($rr['quantite'],1):'—' ?></td>
        <td><span class="statut-badge <?= $rr['statut'] ?>" style="font-size:10px;padding:2px 7px;border-radius:5px;font-weight:700;background:<?= $rr['statut']==='en_attente'?'#fff8e7':($rr['statut']==='receptionnee'?'#eafaf1':'#fdf0ef') ?>;color:<?= $rr['statut']==='en_attente'?'#b7791f':($rr['statut']==='receptionnee'?'#1e8449':'#c0392b') ?>">
          <?= match($rr['statut']){'en_attente'=>'⏳ Attente','receptionnee'=>'✅ Reçu','litige'=>'⚠️ Litige',default=>$rr['statut']} ?>
        </span></td>
        <td><?= !empty($rr['fichier_fiche'])?'<a href="'.upload_url($rr['fichier_fiche'],'fiche').'" target="_blank" style="font-size:12px">📄 Voir</a>':'<span style="color:var(--muted);font-size:12px">—</span>' ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if($role_slug === 'superviseur_operation' && !empty($receptions_recentes)): ?>
<!-- ===== VUE SUPERVISEUR : toutes les réceptions coordinateurs ===== -->
<div class="dash-card" style="margin-bottom:20px">
  <div class="dash-card-header">
    <h3>📋 Réceptions de tous les sites</h3>
    <a href="<?= APP_URL ?>/pages/reception_site.php" style="font-size:12px;color:var(--blue-mid,#1a56a0)">Détail →</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Site</th><th>Type</th><th>Article</th><th style="text-align:right">Qté</th><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach($receptions_recentes as $rr): ?>
      <tr>
        <td style="font-size:12px;color:var(--muted)"><?= fmt_date($rr['date_reception']) ?></td>
        <td style="font-weight:600;font-size:13px"><?= h($rr['site_nom']??'—') ?></td>
        <td><span style="font-size:11px;font-weight:700;color:<?= $rr['type_reception']==='consommable'?'#27ae60':'#1a56a0' ?>"><?= $rr['type_reception']==='consommable'?'🧴':'💻' ?></span></td>
        <td style="font-size:12.5px"><?= h($rr['type_reception']==='consommable'?($rr['conso_lib']??'—'):($rr['equip_type']??'—')) ?></td>
        <td style="text-align:right"><?= $rr['quantite']?fmt_number($rr['quantite'],1):'—' ?></td>
        <td><span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:5px;background:<?= $rr['statut']==='en_attente'?'#fff8e7':($rr['statut']==='receptionnee'?'#eafaf1':'#fdf0ef') ?>;color:<?= $rr['statut']==='en_attente'?'#b7791f':($rr['statut']==='receptionnee'?'#1e8449':'#c0392b') ?>"><?= match($rr['statut']){'en_attente'=>'⏳','receptionnee'=>'✅','litige'=>'⚠️',default=>''} ?> <?= $rr['statut'] ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if($role_slug === 'coordinateur_site' && !empty($stock_conso_site)): ?>
<!-- ===== STOCK CONSOMMABLES DU SITE ===== -->
<div class="dash-card" style="margin-bottom:20px">
  <div class="dash-card-header"><h3>🧴 Stock consommables — Mon site</h3></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Article</th><th>Unité</th><th style="text-align:right">Stock</th><th style="text-align:right">Seuil</th><th>Niveau</th></tr></thead>
      <tbody>
      <?php foreach($stock_conso_site as $sc):
        $pct = $sc['seuil_alerte']>0?min(100,round($sc['quantite']/$sc['seuil_alerte']*100)):100;
        $col = $sc['quantite']<=$sc['seuil_alerte']?'var(--danger)':($pct<150?'var(--warning)':'var(--success)');
      ?>
      <tr>
        <td style="font-size:13px;font-weight:<?= $sc['quantite']<=$sc['seuil_alerte']?'700':'400' ?>;color:<?= $sc['quantite']<=$sc['seuil_alerte']?'var(--danger)':'var(--text)' ?>"><?= h($sc['libelle']) ?><?= $sc['quantite']<=$sc['seuil_alerte']?' ⚠️':'' ?></td>
        <td style="color:var(--muted);font-size:12px"><?= $sc['unite'] ?></td>
        <td style="text-align:right;font-weight:700;color:<?= $col ?>"><?= fmt_number($sc['quantite'],1) ?></td>
        <td style="text-align:right;color:var(--muted);font-size:12px"><?= fmt_number($sc['seuil_alerte'],1) ?></td>
        <td style="width:100px"><div style="height:6px;background:var(--border);border-radius:3px"><div style="width:<?= min(100,$pct) ?>%;height:100%;background:<?= $col ?>;border-radius:3px"></div></div></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ===== 1 - SITES + 2 - ÉQUIPEMENTS ===== -->
<div class="dash-grid <?= $role_slug === 'coordinateur_site' ? '' : 'cols-2' ?>" style="margin-bottom:20px">
  <!-- SITES PAR TYPE — masqué pour coordinateur -->
  <?php if($role_slug !== 'coordinateur_site'): ?>
  <div class="dash-card">
    <div class="dash-card-header">
      <h3>🏢 Sites</h3>
      <a href="<?= APP_URL ?>/pages/sites.php" style="font-size:12px;color:var(--blue-mid,#1a56a0);text-decoration:none">Voir les sites →</a>
    </div>
    <div class="dash-card-body">
      <?php
      $type_labels = ['pose'=>'Pose','siege'=>'Siège','mixte'=>'Mixte','entrepot'=>'Entrepôt','autre'=>'Autre'];
      $type_colors = ['pose'=>'var(--blue)','siege'=>'#8e44ad','mixte'=>'#10a37f','entrepot'=>'#f39c12','autre'=>'#94a3b8'];
      if(empty($sites_par_type)): ?>
        <div style="text-align:center;color:var(--muted);padding:40px 0;font-size:13px">Aucun site configuré</div>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:10px">
        <?php foreach($sites_par_type as $st):
          $lbl = $type_labels[$st['type']] ?? ucfirst($st['type']);
          $col = $type_colors[$st['type']] ?? '#94a3b8';
        ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:var(--lighter);border-radius:10px;border-left:4px solid <?= $col ?>">
          <div>
            <div style="font-size:13px;font-weight:700;color:var(--navy)"><?= $lbl ?></div>
            <div style="font-size:11px;color:var(--muted);margin-top:2px;text-transform:uppercase;letter-spacing:.5px">Type de site</div>
          </div>
          <div style="font-family:'Montserrat',sans-serif;font-size:28px;font-weight:900;color:<?= $col ?>"><?= $st['nb'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
<!-- ===== 2 - ÉQUIPEMENTS ===== -->
  <?php if($kpi_equip_total !== null): ?>
  <div style="background:white;border:1px solid var(--border);border-radius:12px;overflow:hidden">

    <!-- TITRE + TOTAL -->
    <div style="padding:18px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <div>
        <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px">Équipements</div>
        <div style="font-family:'Montserrat',sans-serif;font-size:42px;font-weight:900;color:var(--navy);line-height:1"><?= fmt_number($kpi_equip_total) ?></div>
        <div style="font-size:12px;color:var(--muted);margin-top:5px"><?= fmt_number($kpi_equip_bon??0) ?> en bon état · <?= fmt_number($kpi_equip_hs??0) ?> H.S.</div>
      </div>
      <div style="font-size:36px;opacity:.12">💻</div>
    </div>

    <!-- SÉPARATION INFO / OPÉRATIONNEL -->
    <div style="display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid var(--border)">
      <div style="padding:16px 20px;border-right:1px solid var(--border)">
        <div style="font-size:11px;font-weight:700;color:var(--blue);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">💻 Informatique</div>
        <div style="font-family:'Montserrat',sans-serif;font-size:28px;font-weight:800;color:var(--navy)"><?= fmt_number($kpi_equip_info??0) ?></div>
        <?php if($kpi_equip_total > 0): ?>
        <div style="height:4px;background:var(--border);border-radius:2px;margin-top:10px;overflow:hidden">
          <div style="width:<?= round(($kpi_equip_info??0)/$kpi_equip_total*100) ?>%;height:100%;background:var(--blue);border-radius:2px"></div>
        </div>
        <div style="font-size:11px;color:var(--muted);margin-top:4px"><?= round(($kpi_equip_info??0)/$kpi_equip_total*100) ?>% du total</div>
        <?php endif; ?>
      </div>
      <div style="padding:16px 20px">
        <div style="font-size:11px;font-weight:700;color:#10a37f;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">⚙️ Opérationnel</div>
        <div style="font-family:'Montserrat',sans-serif;font-size:28px;font-weight:800;color:var(--navy)"><?= fmt_number($kpi_equip_op??0) ?></div>
        <?php if($kpi_equip_total > 0): ?>
        <div style="height:4px;background:var(--border);border-radius:2px;margin-top:10px;overflow:hidden">
          <div style="width:<?= round(($kpi_equip_op??0)/$kpi_equip_total*100) ?>%;height:100%;background:#10a37f;border-radius:2px"></div>
        </div>
        <div style="font-size:11px;color:var(--muted);margin-top:4px"><?= round(($kpi_equip_op??0)/$kpi_equip_total*100) ?>% du total</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- DIAGRAMME EN CERCLE - ÉTATS -->
    <div style="padding:16px 20px">
      <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px">État des équipements</div>
      <div style="display:flex;align-items:center;gap:20px">
        <div style="width:100px;height:100px;flex-shrink:0">
          <canvas id="chartEtatKpi"></canvas>
        </div>
        <div style="flex:1;display:flex;flex-direction:column;gap:7px">
          <?php
          $etat_colors = ['neuf'=>'#27ae60','bon'=>'#2ecc71','usage'=>'#f39c12','mauvais'=>'#e67e22','hs'=>'#e74c3c','maintenance'=>'#3498db'];
          $etat_labels = ['neuf'=>'Neuf','bon'=>'Bon état','usage'=>'Usagé','mauvais'=>'Mauvais état','hs'=>'Hors service','maintenance'=>'En maintenance'];
          foreach($equip_par_etat as $e):
            $col = $etat_colors[$e['etat']] ?? '#94a3b8';
            $lbl = $etat_labels[$e['etat']] ?? $e['etat'];
            $pct = $kpi_equip_total > 0 ? round($e['total']/$kpi_equip_total*100) : 0;
          ?>
          <div style="display:flex;align-items:center;gap:8px;font-size:12.5px">
            <div style="width:10px;height:10px;border-radius:50%;background:<?= $col ?>;flex-shrink:0"></div>
            <span style="color:var(--text);flex:1"><?= $lbl ?></span>
            <span style="font-family:'Montserrat',sans-serif;font-weight:700;color:var(--navy)"><?= $e['total'] ?></span>
            <span style="color:var(--muted);font-size:11px;min-width:30px;text-align:right"><?= $pct ?>%</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div>
  <?php endif; ?>
</div>

<!-- ===== 3 - INTERVENTIONS + CONSO ===== -->
<div class="dash-grid <?= $role_slug === 'coordinateur_site' ? '' : 'cols-2' ?>" style="margin-bottom:20px">
  <!-- INTERVENTIONS PAR SITE -->
  <?php if(!empty($interv_par_site ?? [])): ?>
  <div style="background:white;border:1px solid var(--border);border-radius:12px;overflow:hidden">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
      <div>
        <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px">Interventions</div>
        <div style="font-size:12px;color:var(--muted)">Ce mois-ci · par site</div>
      </div>
      <a href="<?= APP_URL ?>/pages/interventions.php" style="font-size:12px;color:var(--blue);text-decoration:none;font-weight:600">Voir tout →</a>
    </div>
    <div style="padding:0">
      <?php
      $max_interv = max(array_column($interv_par_site,'nb_interv') ?: [1]);
      foreach($interv_par_site as $iv):
        $pct = $max_interv > 0 ? round($iv['nb_interv']/$max_interv*100) : 0;
        $col = $iv['nb_interv'] == 0 ? 'var(--muted)' : ($iv['nb_interv'] >= 5 ? '#e74c3c' : 'var(--blue)');
      ?>
      <div style="padding:11px 20px;border-bottom:1px solid var(--border)">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px">
          <div style="font-size:13px;font-weight:600;color:var(--navy)"><?= h($iv['site_nom']) ?></div>
          <div style="font-family:'Montserrat',sans-serif;font-size:16px;font-weight:800;color:<?= $col ?>"><?= $iv['nb_interv'] ?></div>
        </div>
        <div style="height:4px;background:var(--border);border-radius:2px;overflow:hidden">
          <div style="width:<?= $pct ?>%;height:100%;background:<?= $col ?>;border-radius:2px;transition:width .3s"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  <!-- GRAPHIQUE CONSO FCFA PAR SITE — masqué pour coordinateur -->
  <?php if($role_slug !== 'coordinateur_site'): ?>
  <div class="dash-card">
    <div class="dash-card-header">
      <h3>📊 Consommation par site</h3>
      <span style="font-size:12px;color:var(--muted)">12 derniers mois · FCFA</span>
    </div>
    <div class="dash-card-body">
      <div class="chart-container" style="height:240px">
        <canvas id="chartConso"></canvas>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ===== 4+5 - BOBINES + RIVETS ===== -->
<div class="dash-grid <?= $role_slug === 'gestionnaire_stock' ? 'cols-1' : 'cols-2' ?>" style="margin-bottom:20px">

  <!-- BOBINES PAR SITE — masqué pour gestionnaire_stock -->
  <?php if($role_slug !== 'gestionnaire_stock'): ?>
  <div class="dash-card">
    <div class="dash-card-header">
      <h3>🎞️ Bobines actives par site</h3>
      <a href="<?= APP_URL ?>/pages/operations/bobines.php" style="font-size:12px;color:var(--blue-mid,#1a56a0);text-decoration:none">Voir tout →</a>
    </div>
    <div class="dash-card-body" style="padding:0">
      <?php if(empty($bobines_par_site)): ?>
        <div style="text-align:center;color:var(--muted);padding:30px;font-size:13px">Aucune donnée</div>
      <?php else: ?>
      <?php foreach($bobines_par_site as $bs): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-bottom:1px solid var(--border)">
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--navy)"><?= h($bs['site_nom']) ?></div>
          <div style="font-size:11px;color:var(--muted);margin-top:2px"><?= fmt_number($bs['total_films']) ?> films restants</div>
        </div>
        <div style="text-align:right">
          <div style="font-family:'Montserrat',sans-serif;font-size:22px;font-weight:800;color:var(--blue)"><?= $bs['nb_bobines'] ?></div>
          <div style="font-size:10px;color:var(--muted)">bobines</div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- RIVETS PAR SITE -->
  <div class="dash-card">
    <div class="dash-card-header">
      <h3>🔩 Rivets en stock par site</h3>
      <a href="<?= APP_URL ?>/pages/operations/rivets.php" style="font-size:12px;color:var(--blue-mid,#1a56a0);text-decoration:none">Voir tout →</a>
    </div>
    <?php if($role_slug === 'gestionnaire_stock'): ?>
    <!-- Gestionnaire : affichage en grille multi-colonnes -->
    <div class="dash-card-body">
      <?php if(empty($rivets_par_site)): ?>
        <div style="text-align:center;color:var(--muted);padding:30px;font-size:13px">Aucune donnée</div>
      <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">
        <?php foreach($rivets_par_site as $rs):
          $col = $rs['stock_rivets'] < 100 ? '#e74c3c' : ($rs['stock_rivets'] < 500 ? '#f39c12' : 'var(--success)');
          $bg  = $rs['stock_rivets'] < 100 ? '#FEE2E2' : ($rs['stock_rivets'] < 500 ? '#FEF3C7' : '#D1FAE5');
        ?>
        <div style="background:<?= $bg ?>;border-radius:12px;padding:16px 18px;display:flex;align-items:center;justify-content:space-between">
          <div style="font-size:13px;font-weight:600;color:var(--navy)"><?= h($rs['site_nom']) ?></div>
          <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:800;color:<?= $col ?>"><?= fmt_number($rs['stock_rivets']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="dash-card-body" style="padding:0">
      <?php if(empty($rivets_par_site)): ?>
        <div style="text-align:center;color:var(--muted);padding:30px;font-size:13px">Aucune donnée</div>
      <?php else: ?>
      <?php foreach($rivets_par_site as $rs):
        $col = $rs['stock_rivets'] < 100 ? '#e74c3c' : ($rs['stock_rivets'] < 500 ? '#f39c12' : 'var(--success)');
      ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;border-bottom:1px solid var(--border)">
        <div style="font-size:13px;font-weight:600;color:var(--navy)"><?= h($rs['site_nom']) ?></div>
        <div style="font-family:'Montserrat',sans-serif;font-size:22px;font-weight:800;color:<?= $col ?>"><?= fmt_number($rs['stock_rivets']) ?></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- ===== 6+7 - FIN CYCLE + STOCK BAS — masqué pour coordinateur ===== -->
<?php if($role_slug !== 'coordinateur_site'): ?>
<div class="dash-grid cols-2" style="margin-bottom:20px">
  <!-- FIN DE CYCLE ALERTES -->
  <div class="dash-card">
    <div class="dash-card-header">
      <h3>⚠️ Équipements en fin de cycle</h3>
      <span style="font-size:11px;color:var(--muted)">60 prochains jours</span>
    </div>
    <div class="dash-card-body" style="padding:12px 20px">
      <?php if (empty($fin_cycle_list)): ?>
        <div style="text-align:center;color:var(--success);padding:32px 0;font-size:13px">✅ Aucun équipement en fin de cycle</div>
      <?php else: ?>
        <?php foreach($fin_cycle_list as $fc):
          $j = (int)$fc['jours_restants'];
          $cls = $j < 0 ? 'danger' : ($j <= 30 ? 'warning' : 'ok');
        ?>
        <div class="alerte-item">
          <div class="alerte-dot <?= $cls ?>"></div>
          <div class="alerte-info">
            <div class="a-titre"><?= h($fc['numero_serie_interne']) ?> — <?= h($fc['type_equip']) ?></div>
            <div class="a-sub">
              <?= h($fc['site_nom'] ?? 'Non affecté') ?>
              <?= $fc['utilisateur'] ? ' · ' . h($fc['utilisateur']) : '' ?>
            </div>
          </div>
          <div class="alerte-badge <?= $cls ?>">
            <?= $j < 0 ? 'Expiré' : ($j === 0 ? "Aujourd'hui" : "J-$j") ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <!-- STOCK BAS CONSOMMABLES -->
  <div class="dash-card">
    <div class="dash-card-header">
      <h3>🧴 Stock consommables bas</h3>
      <a href="<?= APP_URL ?>/pages/consommables.php" style="font-size:12px;color:var(--blue-mid, #1a56a0);text-decoration:none">Gérer →</a>
    </div>
    <div class="dash-card-body">
      <?php if (empty($stock_bas_list)): ?>
        <div style="text-align:center;color:var(--success);padding:32px 0;font-size:13px">✅ Tous les stocks sont suffisants</div>
      <?php else: ?>
        <?php foreach($stock_bas_list as $sb):
          $ratio = $sb['seuil_alerte'] > 0 ? min(100, ($sb['stock_global'] / $sb['seuil_alerte']) * 100) : 0;
          $fill_cls = $ratio <= 25 ? 'critical' : ($ratio <= 75 ? 'low' : 'ok');
        ?>
        <div class="stock-item">
          <div class="stock-top">
            <span class="s-label"><?= h($sb['libelle']) ?></span>
            <span class="s-qty"><?= fmt_number($sb['stock_global'], 1) ?> / <?= $sb['seuil_alerte'] ?> <?= $sb['unite'] ?></span>
          </div>
          <div class="stock-bar">
            <div class="stock-fill <?= $fill_cls ?>" style="width:<?= round($ratio) ?>%"></div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ===== 8 - DERNIÈRES ACTIVITÉS ===== -->
<!-- ===== JOURNAL D'AUDIT — masqué pour maintenance_info et gestionnaire_stock ===== -->
<?php if(!in_array($role_slug,['maintenance_info','gestionnaire_stock'])): ?>
<div class="dash-card" style="margin-bottom:20px">
  <div class="dash-card-header">
    <h3>📋 Dernières activités<?= $role_slug==='superviseur_operation' ? ' des coordinateurs' : '' ?></h3>
    <?php if(can('audit','can_read')): ?>
    <a href="<?= APP_URL ?>/pages/admin/audit.php" style="font-size:12px;color:var(--blue-mid, #1a56a0);text-decoration:none">Voir tout →</a>
    <?php endif; ?>
  </div>
  <div class="dash-card-body" style="padding:8px 20px">
    <?php if (empty($derniers_audit)): ?>
      <div style="text-align:center;color:var(--muted);padding:24px 0;font-size:13px">Aucune activité enregistrée</div>
    <?php else: ?>
      <?php foreach($derniers_audit as $a): ?>
      <div class="audit-item">
        <span class="audit-action-badge <?= $a['action'] ?>"><?= $a['action'] ?></span>
        <div>
          <div class="audit-desc"><?= h($a['description']) ?></div>
          <div class="audit-meta">
            <?= h($a['user_nom'] ?? 'Système') ?> · <?= fmt_datetime($a['created_at']) ?>
            · <em><?= h($a['module']) ?></em>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>


<!-- ===== CHART.JS ===== -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color = '#64748b';

// ── Mini donut dans la carte KPI Équipements
const ctxKpi = document.getElementById('chartEtatKpi');
if(ctxKpi) {
  new Chart(ctxKpi, {
    type: 'doughnut',
    data: {
      labels: <?= json_encode(array_column($equip_par_etat,'etat')) ?>,
      datasets: [{
        data: <?= json_encode(array_column($equip_par_etat,'total')) ?>,
        backgroundColor: ['#27ae60','#2ecc71','#f39c12','#e67e22','#e74c3c','#3498db','#94a3b8'],
        borderWidth: 0,
        hoverOffset: 4
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: true,
      cutout: '70%',
      plugins: { legend:{display:false}, tooltip:{enabled:false} }
    }
  });
}

// Palette
const PALETTE = ['#2563a8','#27ae60','#1a56a0','#e74c3c','#8e44ad','#16a085','#f39c12','#2980b9'];
const ETAT_COLORS = { neuf:'#27ae60', bon:'#2980b9', usage:'#f39c12', hs:'#e74c3c', reforme:'#95a5a6' };

// ① CONSOMMATION FCFA PAR SITE (bar horizontal)
const ctxConso = document.getElementById('chartConso');
if (ctxConso) {
  const sitesRaw = <?= $conso_sites_json ?>;
  const siteNoms  = sitesRaw.map(r => r.site_nom);
  const siteMnts  = sitesRaw.map(r => parseFloat(r.montant));
  const colors    = ['#1b75bc','#00aeef','#27ae60','#f39c12','#e74c3c','#8e44ad','#16a085','#e67e22','#94c2d4','#2c3e50'];
  new Chart(ctxConso, {
    type: 'bar',
    data: {
      labels: siteNoms,
      datasets: [{
        label: 'FCFA',
        data: siteMnts,
        backgroundColor: siteNoms.map((_,i) => colors[i % colors.length] + 'CC'),
        borderColor:     siteNoms.map((_,i) => colors[i % colors.length]),
        borderWidth: 1, borderRadius: 6
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: {
          label: ctx => ' ' + new Intl.NumberFormat('fr-FR').format(ctx.raw) + ' FCFA'
        }}
      },
      scales: {
        x: {
          grid: { color: '#f0f0f0' },
          ticks: { font: { size: 10 }, callback: v => new Intl.NumberFormat('fr-FR',{notation:'compact'}).format(v) }
        },
        y: { grid: { display: false }, ticks: { font: { size: 11 } } }
      }
    }
  });
}




</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
