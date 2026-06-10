<?php
// ============================================================
//  pages/inventaire_detail.php  —  Saisie inventaire bobines
//  Page dédiée : stock physique + écart connu éditables
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/upload.php';

require_auth();
require_permission('inventaire_bobines','can_read');

$user   = current_user();
$inv_id = (int)($_GET['id'] ?? 0);
if (!$inv_id) { header('Location: inventaire_bobines.php'); exit; }

$inv = db_fetch_one(
    "SELECT i.*, s.nom AS site_nom, CONCAT(u.prenom,' ',u.nom) AS createur
     FROM inventaires_bobines i
     LEFT JOIN sites s ON s.id=i.site_id
     LEFT JOIN users u ON u.id=i.cree_par
     WHERE i.id=?", [$inv_id]
);
if (!$inv) { header('Location: inventaire_bobines.php'); exit; }

$can_edit = $inv['statut'] === 'brouillon' && can('inventaire_bobines','can_update');
$page_title  = 'Inventaire du ' . fmt_date($inv['date_inventaire']);
$active_page = 'inventaire_bobines';

// ============================================================
//  AJAX
// ============================================================
if ($_SERVER['REQUEST_METHOD']==='POST' && is_ajax()) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // ── SAUVER UNE LIGNE (stock physique + écart connu)
    if ($action==='sauver_ligne') {
        if (!$can_edit) json_response(false,'Inventaire non modifiable.');
        $detail_id      = (int)($_POST['detail_id']     ?? 0);
        $stock_physique = $_POST['stock_physique'] !== '' ? (int)$_POST['stock_physique'] : null;
        $ecart_connu    = $_POST['ecart_connu']    !== '' ? (int)$_POST['ecart_connu']    : 0;
        $notes          = trim($_POST['notes'] ?? '');

        $det = db_fetch_one(
            "SELECT d.*, b.stock_systeme FROM inventaire_details_bobines d
             JOIN op_bobines b ON b.id=d.bobine_id WHERE d.id=? AND d.inventaire_id=?",
            [$detail_id, $inv_id]
        );
        if (!$det) json_response(false,'Ligne introuvable.');

        $stock_sys   = (int)$det['stock_systeme'];
        $stock_phy   = $stock_physique !== null ? $stock_physique : (int)$det['stock_physique'];
        $ecart_mesure = $stock_phy - $stock_sys;       // écart détecté lors de l'inventaire
        $ecart_total  = $ecart_mesure + $ecart_connu;  // écart total = mesuré + connu

        // Calcul projections
        $conso_moy = (float)$det['conso_quotidienne_moy'];
        $jours_sys  = $conso_moy > 0 ? (int)ceil($stock_sys / $conso_moy) : null;
        $jours_phy  = $conso_moy > 0 ? (int)ceil($stock_phy / $conso_moy) : null;
        $date_epuis = $jours_phy && $conso_moy > 0
            ? date('Y-m-d', strtotime("+{$jours_phy} days")) : null;

        db_query(
            "UPDATE inventaire_details_bobines
             SET stock_physique=?, ecart=?, jours_restants_systeme=?,
                 jours_restants_physique=?, date_epuisement_estime=?, notes=?
             WHERE id=?",
            [$stock_phy, $ecart_mesure, $jours_sys, $jours_phy, $date_epuis, $notes, $detail_id]
        );

        // Sauver l'écart connu dans ecarts_bobines si non nul
        if ($ecart_connu != 0) {
            // Supprimer l'éventuel écart connu précédent de cet inventaire pour cette bobine
            db_query(
                "DELETE FROM ecarts_bobines
                 WHERE bobine_id=? AND source='manuel' AND inventaire_id IS NULL
                   AND DATE(created_at)=CURDATE()",
                [$det['bobine_id']]
            );
            db_query(
                "INSERT INTO ecarts_bobines (bobine_id,date_constat,stock_systeme,stock_physique,ecart,motif,source,statut,created_by)
                 VALUES (?,?,?,?,?,?,?,?,?)",
                [$det['bobine_id'], $inv['date_inventaire'], $stock_sys, $stock_phy,
                 $ecart_connu, $notes ?: 'Écart connu déclaré lors inventaire #'.$inv_id,
                 'manuel','ouvert',$user['id']]
            );
        }

        json_response(true,'Sauvegardé.',['ecart_mesure'=>$ecart_mesure,'ecart_total'=>$ecart_total,'jours_phy'=>$jours_phy,'date_epuis'=>$date_epuis]);
    }

    // ── TOUT SAUVER
    if ($action==='sauver_tout') {
        if (!$can_edit) json_response(false,'Inventaire non modifiable.');
        $lignes_data = json_decode($_POST['lignes'] ?? '[]', true);
        $saved = 0; $errors = 0;
        db_begin();
        try {
            foreach ($lignes_data as $ld) {
                $detail_id   = (int)($ld['id'] ?? 0);
                $phy_val     = $ld['phy'] !== '' ? (int)$ld['phy'] : null;
                $connu_val   = (int)($ld['connu'] ?? 0);
                $notes       = trim($ld['notes'] ?? '');
                if ($phy_val === null) continue;

                $det = db_fetch_one(
                    "SELECT d.*, b.stock_systeme FROM inventaire_details_bobines d
                     JOIN op_bobines b ON b.id=d.bobine_id WHERE d.id=? AND d.inventaire_id=?",
                    [$detail_id, $inv_id]
                );
                if (!$det) continue;

                $stock_sys  = (int)$det['stock_systeme'];
                $ecart_mes  = $phy_val - $stock_sys;
                $conso_moy  = (float)$det['conso_quotidienne_moy'];
                $jours_phy  = $conso_moy > 0 ? (int)ceil($phy_val / $conso_moy) : null;
                $date_epuis = $jours_phy ? date('Y-m-d', strtotime("+{$jours_phy} days")) : null;

                db_query(
                    "UPDATE inventaire_details_bobines SET stock_physique=?,ecart=?,jours_restants_physique=?,date_epuisement_estime=?,notes=? WHERE id=?",
                    [$phy_val,$ecart_mes,$jours_phy,$date_epuis,$notes,$detail_id]
                );

                if ($connu_val != 0) {
                    db_query(
                        "INSERT INTO ecarts_bobines (bobine_id,date_constat,stock_systeme,stock_physique,ecart,motif,source,statut,created_by)
                         VALUES (?,?,?,?,?,?,?,?,?)",
                        [$det['bobine_id'],$inv['date_inventaire'],$stock_sys,$phy_val,
                         $connu_val,'Écart connu – inventaire #'.$inv_id,'manuel','ouvert',$user['id']]
                    );
                }
                $saved++;
            }
            db_commit();
            json_response(true,"$saved ligne(s) sauvegardée(s).",['saved'=>$saved]);
        } catch(Exception $e){ db_rollback(); json_response(false,'Erreur: '.$e->getMessage()); }
    }

    // ── VALIDER L'INVENTAIRE
    if ($action==='valider') {
        if (!$can_edit) json_response(false,'Inventaire non modifiable.');
        require_permission('inventaire_bobines','can_update');
        $lignes = db_fetch_all("SELECT * FROM inventaire_details_bobines WHERE inventaire_id=?",[$inv_id]);
        $nb_ecarts = 0;
        db_begin();
        try {
            foreach ($lignes as $l) {
                if ($l['stock_physique'] == 0 && $l['ecart'] == 0) continue;

                // Fermer tous les écarts ouverts précédents de cette bobine
                db_query("UPDATE ecarts_bobines SET statut='resolu', resolu_at=NOW(), resolu_par=?
                          WHERE bobine_id=? AND statut='ouvert'",
                    [$user['id'], $l['bobine_id']]);

                if ($l['ecart'] != 0) {
                    $nb_ecarts++;
                    $phy = (int)$l['stock_physique'];
                    db_query("UPDATE op_bobines SET stock_systeme=?,films_restants=?,statut=IF(?>0,IF(statut='retiree','retiree','en_cours'),'epuisee') WHERE id=?",
                        [$phy,$phy,$phy,$l['bobine_id']]);
                    db_query("INSERT INTO mouvements_bobines (bobine_id,type,quantite,stock_avant,stock_apres,motif,ref_id,created_by) VALUES (?,?,?,?,?,?,?,?)",
                        [$l['bobine_id'],'ajustement_inventaire',$l['ecart'],(int)$l['stock_systeme'],$phy,"Inventaire #$inv_id",$inv_id,$user['id']]);
                    // Écart tracé mais immédiatement résolu car stock ajusté
                    db_query(
                        "INSERT INTO ecarts_bobines (bobine_id,date_constat,stock_systeme,stock_physique,ecart,motif,source,inventaire_id,statut,resolu_at,resolu_par,created_by)
                         VALUES (?,?,?,?,?,?,?,?,'resolu',NOW(),?,?)",
                        [$l['bobine_id'],$inv['date_inventaire'],(int)$l['stock_systeme'],$phy,
                         $l['ecart'],$l['notes']??'','inventaire',$inv_id,$user['id'],$user['id']]
                    );
                }
            }
            db_query("UPDATE inventaires_bobines SET statut='valide',nb_ecarts=?,valide_par=?,valide_at=NOW() WHERE id=?",
                [$nb_ecarts,$user['id'],$inv_id]);
            audit_log($user['id'],'UPDATE','inventaire_bobines',$inv_id,"Validation inventaire #$inv_id — $nb_ecarts écart(s)");
            db_commit();
            json_response(true,"Inventaire validé. $nb_ecarts écart(s) traité(s).");
        } catch(Exception $e){ db_rollback(); json_response(false,'Erreur: '.$e->getMessage()); }
    }

    json_response(false,'Action inconnue.');
}

// ============================================================
//  DONNÉES
// ============================================================
$lignes = db_fetch_all(
    "SELECT d.*,
            b.numero, b.type_code, b.format, b.statut AS bob_statut,
            b.stock_systeme AS stock_realtime,  -- stock actuel (temps réel)
            s.nom AS site_nom,
            -- Consommation moyenne 30j glissants (dynamique)
            COALESCE(
              (SELECT SUM(cb.quantite)/GREATEST(DATEDIFF(NOW(),MIN(cb.date_conso)),1)
               FROM consommations_bobines cb
               WHERE cb.bobine_id=b.id AND cb.date_conso>=DATE_SUB(CURDATE(),INTERVAL 30 DAY)),
            0) AS conso_moy_realtime
     FROM inventaire_details_bobines d
     JOIN op_bobines b ON b.id=d.bobine_id
     LEFT JOIN sites s ON s.id=b.site_id
     WHERE d.inventaire_id=?
     ORDER BY b.serie, b.type_code, b.numero",
    [$inv_id]
);

// Écarts connus = écarts OUVERTS liés à CET inventaire uniquement
// Après validation, tous les écarts précédents sont fermés
$bobine_ids = array_column($lignes,'bobine_id');
$ecarts_connus_map = [];
if (!empty($bobine_ids)) {
    $ph  = implode(',', array_fill(0,count($bobine_ids),'?'));
    $params_ec = array_merge($bobine_ids, [$inv_id]);
    $ecs = db_fetch_all(
        "SELECT bobine_id, SUM(ecart) AS total_ecart, COUNT(*) AS nb,
                GROUP_CONCAT(CONCAT(date_constat,': ',ecart,' films') ORDER BY date_constat DESC SEPARATOR ' | ') AS detail
         FROM ecarts_bobines
         WHERE bobine_id IN ($ph) AND statut='ouvert'
           AND inventaire_id = ?
         GROUP BY bobine_id", $params_ec
    );
    foreach ($ecs as $ec) $ecarts_connus_map[$ec['bobine_id']] = $ec;
}

// Stats
$nb_saisis    = count(array_filter($lignes, fn($l)=>$l['stock_physique']>0||$l['ecart']!=0));
$nb_ecarts    = count(array_filter($lignes, fn($l)=>$l['ecart']!=0));
$nb_non_saisi = count($lignes) - $nb_saisis;

include __DIR__ . '/../templates/header.php';
?>
<style>
.inv-toolbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px}
.inv-meta{font-size:13px;color:var(--muted)}
.inv-meta strong{color:var(--navy)}
.stat-chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
.chip{padding:5px 12px;border-radius:20px;font-size:12.5px;font-weight:600}
.chip.blue{background:#e3f2fd;color:#1565c0}
.chip.green{background:#eafaf1;color:#1e8449}
.chip.red{background:#fdf0ef;color:#c0392b}
.chip.orange{background:#fff8e7;color:#b7791f}
.chip.gray{background:#f1f5f9;color:#64748b}

input.saisie{width:80px;padding:5px 8px;border:1.5px solid #e2e8f0;border-radius:6px;text-align:right;
             font-family:'Montserrat',sans-serif;font-weight:700;font-size:13px;transition:border-color .2s}
input.saisie:focus{outline:none;border-color:var(--blue-mid,#1a56a0)}
input.saisie.ok{border-color:#27ae60;background:#f0fdf4}
input.saisie.nok{border-color:#e74c3c;background:#fff5f5}
input.connu{width:70px;padding:5px 8px;border:1.5px dashed #f39c12;border-radius:6px;text-align:right;
            font-family:'Montserrat',sans-serif;font-weight:700;font-size:13px;background:#fffbf0;transition:border-color .2s}
input.connu:focus{outline:none;border-color:#e67e22}
input.connu.filled{border-color:#e67e22;background:#fff3cd}

.ecart-val{font-family:'Montserrat',sans-serif;font-size:14px;font-weight:800}
.ecart-pos{color:#27ae60}
.ecart-neg{color:#e74c3c}
.ecart-zero{color:#27ae60}

.badge-connu{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;white-space:nowrap}
</style>

<!-- TOOLBAR -->
<div class="inv-toolbar">
  <div>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
      <a href="<?= APP_URL ?>/pages/inventaire_bobines.php" style="color:var(--muted);font-size:13px;text-decoration:none">← Inventaires</a>
      <h2 style="font-family:'Montserrat',sans-serif;font-size:18px;font-weight:800;color:var(--navy)">
        Inventaire du <?= fmt_date($inv['date_inventaire']) ?>
      </h2>
      <span style="padding:3px 10px;border-radius:6px;font-size:12px;font-weight:700;background:<?= $inv['statut']==='valide'?'#eafaf1':($inv['statut']==='brouillon'?'#fff8e7':'#fdf0ef') ?>;color:<?= $inv['statut']==='valide'?'#1e8449':($inv['statut']==='brouillon'?'#b7791f':'#c0392b') ?>">
        <?= ['brouillon'=>'⏳ Brouillon','valide'=>'✅ Validé','annule'=>'❌ Annulé'][$inv['statut']]??$inv['statut'] ?>
      </span>
    </div>
    <div class="inv-meta">
      <?= h($inv['site_nom']??'Tous les sites') ?> · <strong><?= count($lignes) ?></strong> bobines · Créé par <?= h($inv['createur']??'—') ?>
    </div>
  </div>
  <?php if($can_edit): ?>
  <div style="display:flex;gap:8px">
    <button class="btn btn-secondary" id="btnSauverTout" onclick="sauverTout()">💾 Tout sauver</button>
    <button class="btn btn-success" onclick="validerInventaire()">✅ Valider l'inventaire</button>
  </div>
  <?php endif; ?>
</div>

<!-- STATS -->
<div class="stat-chips">
  <span class="chip blue">📦 Bobines : <strong id="statTotal"><?= count($lignes) ?></strong></span>
  <span class="chip green">✅ OK : <strong id="statOk"><?= $nb_saisis - $nb_ecarts ?></strong></span>
  <span class="chip red">⚠️ Écarts : <strong id="statEcarts"><?= $nb_ecarts ?></strong></span>
  <span class="chip gray">⏳ Non saisis : <strong id="statNonSaisis"><?= $nb_non_saisi ?></strong></span>
  <?php
  $nb_ecarts_connus = count(array_filter($bobine_ids, fn($id)=>isset($ecarts_connus_map[$id])));
  if($nb_ecarts_connus>0):
  ?>
  <span class="chip orange">📋 Écarts connus : <strong><?= $nb_ecarts_connus ?></strong></span>
  <?php endif; ?>
</div>

<div id="alertZone"></div>

<!-- LÉGENDE -->
<div style="display:flex;gap:16px;font-size:12px;color:var(--muted);margin-bottom:12px;align-items:center">
  <span>📝 <strong>Films comptés</strong> : stock réel compté physiquement</span>
  <span style="border-left:1px solid var(--border);padding-left:16px">
    <span style="border-bottom:2px dashed #f39c12;padding-bottom:1px"><strong>Écart connu</strong></span> : différence connue avant l'inventaire (saisir + ou -)
  </span>
</div>

<!-- TABLEAU PRINCIPAL -->
<div style="background:#e8f4fd;border:1px solid #90caf9;border-radius:10px;padding:10px 16px;margin-bottom:14px;font-size:13px;display:flex;gap:20px;flex-wrap:wrap">
  <span>📌 <strong>Qté physique</strong> = photo figée à la date de l'inventaire</span>
  <span style="border-left:1px solid #90caf9;padding-left:20px">🔴 <strong>Qté temps réel</strong> = stock actuel mis à jour en continu par les consommations</span>
  <span style="border-left:1px solid #90caf9;padding-left:20px;border-bottom:2px dashed #f39c12;padding-bottom:1px"><strong>Écart connu</strong> = différence connue avant l'inventaire</span>
</div>

<div class="card" style="padding:0;overflow:hidden">
  <div class="table-wrap" style="overflow-x:auto">
    <table id="invTable">
      <thead>
        <tr style="background:#0d1f35;color:white">
          <th style="padding:10px 14px;text-align:left">Numéro</th>
          <th style="padding:10px 14px">Type</th>
          <th style="padding:10px 14px">Site</th>
          <th style="padding:10px 14px;text-align:right">Qté système 🖥️</th>
          <th style="padding:10px 14px;text-align:right;border-right:1px solid rgba(255,255,255,.15)">Qté physique 📌</th>
          <th style="padding:10px 14px;text-align:center">Écart connu</th>
          <th style="padding:10px 14px;text-align:center">Écart mesuré</th>
          <th style="padding:10px 14px;text-align:right;background:rgba(255,255,255,.08);border-left:2px solid #f39c12">🔴 Qté temps réel</th>
          <th style="padding:10px 14px;text-align:right;background:rgba(255,255,255,.08)">Conso/j moy</th>
          <th style="padding:10px 14px;text-align:right;background:rgba(255,255,255,.08)">Jours restants</th>
          <th style="padding:10px 14px;text-align:center;background:rgba(255,255,255,.08)">Épuisement estimé</th>
          <?php if($can_edit): ?><th style="padding:10px 14px;text-align:center">💾</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach($lignes as $l):
        // ── Données figées (photo inventaire)
        $stock_phy  = (int)$l['stock_physique'];   // figé
        $ecart_mes  = (int)$l['ecart'];            // figé
        $saisi      = $l['stock_physique'] > 0 || $ecart_mes != 0;
        $ec_connu   = $ecarts_connus_map[$l['bobine_id']] ?? null;

        // ── Données temps réel (dynamiques)
        $stock_rt   = (int)$l['stock_realtime'];          // stock actuel
        $conso_moy  = (float)$l['conso_moy_realtime'];    // conso 30j glissants
        $jours_rt   = $conso_moy > 0 && $stock_rt > 0 ? (int)ceil($stock_rt/$conso_moy) : null;
        $jours_color= $jours_rt ? ($jours_rt<30?'#e74c3c':($jours_rt<90?'#f39c12':'#27ae60')) : '#94a3b8';
        $date_epuis_rt = $jours_rt ? date('Y-m-d', strtotime("+{$jours_rt} days")) : null;

        // Couleur de fond
        $row_bg = !$saisi && $ec_connu ? '#fffbec'
                : (!$saisi             ? '#fffbf0'
                : ($ecart_mes != 0     ? '#fff5f5'
                :                        'white'));
      ?>
      <tr id="row-<?= $l['id'] ?>" style="border-bottom:1px solid #e2e8f0;background:<?= $row_bg ?>">

        <!-- Numéro -->
        <td style="padding:9px 14px;font-family:monospace;font-weight:700;color:var(--navy);font-size:13px"><?= h($l['numero']) ?></td>

        <!-- Type -->
        <td style="padding:9px 14px;text-align:center">
          <span style="background:#f1f5f9;padding:2px 7px;border-radius:4px;font-size:11px;font-weight:600"><?= h($l['type_code']) ?></span>
          <?php if($l['format']): ?><br><span style="font-size:10px;color:#94a3b8"><?= h($l['format']) ?></span><?php endif; ?>
        </td>

        <!-- Site -->
        <td style="padding:9px 14px;font-size:12px;color:var(--muted)"><?= h($l['site_nom']??'—') ?></td>

        <!-- Qté système (figée — valeur au moment de l'inventaire) -->
        <td style="padding:9px 14px;text-align:right">
          <span style="font-family:'Montserrat',sans-serif;font-weight:800;font-size:15px;color:var(--navy)">
            <?= number_format((int)$l['stock_systeme']) ?>
          </span>
        </td>

        <!-- Qté physique (figée) -->
        <td style="padding:9px 14px;text-align:right;border-right:1px solid #e2e8f0">
          <?php if($can_edit): ?>
          <input type="number" min="0"
                 class="saisie <?= $saisi?($ecart_mes!=0?'nok':'ok'):'' ?>"
                 id="phy-<?= $l['id'] ?>"
                 value="<?= $saisi ? $stock_phy : '' ?>"
                 placeholder="—"
                 data-sys="<?= $stock_rt ?>"
                 data-conso="<?= $conso_moy ?>"
                 oninput="onPhyInput(<?= $l['id'] ?>,<?= $stock_rt ?>,<?= $conso_moy ?>)">
          <?php else: ?>
          <span style="font-family:'Montserrat',sans-serif;font-weight:700;font-size:14px">
            <?= $saisi ? number_format($stock_phy) : '<span style="color:#94a3b8">—</span>' ?>
          </span>
          <?php endif; ?>
        </td>

        <!-- Écart connu (saisie libre) -->
        <td style="padding:9px 14px;text-align:center">
          <?php if($can_edit): ?>
          <input type="number"
                 class="connu <?= $ec_connu?'filled':'' ?>"
                 id="connu-<?= $l['id'] ?>"
                 value="<?= $ec_connu ? (int)$ec_connu['total_ecart'] : '' ?>"
                 placeholder="0"
                 title="<?= $ec_connu ? h($ec_connu['detail']) : 'Ex: -15 ou +3' ?>"
                 oninput="onConnuInput(<?= $l['id'] ?>)">
          <?php else: ?>
            <?php if($ec_connu): ?>
            <span style="font-family:'Montserrat',sans-serif;font-weight:800;color:<?= (int)$ec_connu['total_ecart']<0?'#e74c3c':'#27ae60' ?>">
              <?= (int)$ec_connu['total_ecart']>0?'+':'' ?><?= (int)$ec_connu['total_ecart'] ?>
            </span>
            <?php else: ?><span style="color:#94a3b8">—</span><?php endif; ?>
          <?php endif; ?>
        </td>

        <!-- Écart mesuré (figé — calculé au moment de l'inventaire) -->
        <td style="padding:9px 14px;text-align:center" id="ecart-<?= $l['id'] ?>">
          <?php if($saisi): ?>
          <span class="ecart-val <?= $ecart_mes<0?'ecart-neg':($ecart_mes>0?'ecart-pos':'ecart-zero') ?>">
            <?= $ecart_mes!=0?(($ecart_mes>0?'+':'').$ecart_mes.' films'):'✅ OK' ?>
          </span>
          <?php else: ?><span style="color:#94a3b8">—</span><?php endif; ?>
        </td>

        <!-- Qté TEMPS RÉEL (dynamique — bouge avec les consos) -->
        <td style="padding:9px 14px;text-align:right;background:#f8fafc;border-left:2px solid #f39c12">
          <?php
          $rt_pct = $stock_rt > 0 ? min(100, round($stock_rt / max(1,(int)($l['stock_systeme']??500)) * 100)) : 0;
          $rt_color = $rt_pct > 50 ? '#27ae60' : ($rt_pct > 20 ? '#f39c12' : '#e74c3c');
          ?>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:3px">
            <span style="font-family:'Montserrat',sans-serif;font-weight:800;font-size:15px;color:<?= $rt_color ?>">
              <?= number_format($stock_rt) ?>
            </span>
            <?php if($saisi && $stock_rt !== $stock_phy): ?>
            <span style="font-size:10px;color:<?= $stock_rt<$stock_phy?'#e74c3c':'#27ae60' ?>">
              <?= $stock_rt<$stock_phy ? '▼' : '▲' ?> <?= abs($stock_rt - $stock_phy) ?> depuis inventaire
            </span>
            <?php endif; ?>
          </div>
        </td>

        <!-- Conso/j moy (dynamique 30j) -->
        <td style="padding:9px 14px;text-align:right;background:#f8fafc;font-size:12px;color:#64748b">
          <?= $conso_moy > 0 ? number_format($conso_moy,1) : '—' ?>
        </td>

        <!-- Jours restants (basé sur temps réel) -->
        <td style="padding:9px 14px;text-align:right;background:#f8fafc" id="jours-<?= $l['id'] ?>">
          <?php if($jours_rt): ?>
          <span style="color:<?= $jours_color ?>;font-weight:700"><?= $jours_rt ?>j</span>
          <?php else: ?><span style="color:#94a3b8">—</span><?php endif; ?>
        </td>

        <!-- Épuisement estimé (dynamique) -->
        <td style="padding:9px 14px;text-align:center;background:#f8fafc;font-size:12px" id="epuis-<?= $l['id'] ?>">
          <?php if($date_epuis_rt): ?>
          <span style="color:<?= $jours_color ?>"><?= fmt_date($date_epuis_rt) ?></span>
          <?php else: ?><span style="color:#94a3b8">—</span><?php endif; ?>
        </td>

        <!-- Sauver -->
        <?php if($can_edit): ?>
        <td style="padding:9px 14px;text-align:center">
          <button style="background:#e3f2fd;color:#1565c0;border:1px solid #90caf9;padding:4px 8px;border-radius:6px;cursor:pointer;font-size:12px"
                  onclick="sauverLigne(<?= $l['id'] ?>)">💾</button>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if($can_edit): ?>
<div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">
  <button class="btn btn-secondary" onclick="sauverTout()">💾 Tout sauver</button>
  <button class="btn btn-success" style="font-size:14px;padding:10px 24px" onclick="validerInventaire()">✅ Valider l'inventaire</button>
</div>
<?php endif; ?>

<script>
function ap(data){ return fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data)}).then(r=>r.json()); }

function toast(msg,type='success'){
  const t=document.createElement('div');
  t.style.cssText=`position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.15);background:${type==='success'?'#27ae60':'#e74c3c'};color:white;max-width:380px`;
  t.innerHTML=msg; document.body.appendChild(t); setTimeout(()=>t.remove(),3500);
}

function onPhyInput(id, stockSys, consoMoy){
  const inp  = document.getElementById('phy-'+id);
  const val  = inp.value;
  const ecEl = document.getElementById('ecart-'+id);
  const jEl  = document.getElementById('jours-'+id);
  const eEl  = document.getElementById('epuis-'+id);
  const row  = document.getElementById('row-'+id);

  if(val === ''){
    inp.className='saisie'; ecEl.innerHTML='<span style="color:#94a3b8">—</span>';
    jEl.innerHTML='<span style="color:#94a3b8">—</span>'; eEl.innerHTML='<span style="color:#94a3b8">—</span>';
    row.style.background='#fffbf0'; return;
  }

  const phy   = parseInt(val);
  const ecart = phy - parseInt(stockSys);
  const ecColor = ecart<0?'#e74c3c':ecart>0?'#27ae60':'#27ae60';
  const ecText  = ecart!==0?(ecart>0?'+':'')+ecart+' films':'✅ OK';
  const ecClass = ecart<0?'ecart-neg':ecart>0?'ecart-pos':'ecart-zero';

  inp.className = 'saisie '+(ecart!==0?'nok':'ok');
  ecEl.innerHTML= `<span class="ecart-val ${ecClass}">${ecText}</span>`;
  row.style.background = ecart!==0?'#fff5f5':'#f0fdf4';

  if(consoMoy>0 && phy>=0){
    const jours = phy>0?Math.ceil(phy/consoMoy):0;
    const jColor= jours<30?'#e74c3c':jours<90?'#f39c12':'#27ae60';
    jEl.innerHTML = jours>0?`<span style="color:${jColor};font-weight:700">${jours}j</span>`:'<span style="color:#e74c3c;font-weight:700">Épuisée</span>';
    if(jours>0){
      const d=new Date(); d.setDate(d.getDate()+jours);
      eEl.innerHTML=`<span style="color:${jColor};font-size:12px">${d.toISOString().split('T')[0]}</span>`;
    } else eEl.innerHTML='—';
  }
  updateStats();
}

function onConnuInput(id){
  const inp = document.getElementById('connu-'+id);
  inp.classList.toggle('filled', inp.value!=='' && inp.value!=='0');
}

async function sauverLigne(id){
  const phyEl   = document.getElementById('phy-'+id);
  const connuEl = document.getElementById('connu-'+id);
  const phy   = phyEl   ? phyEl.value   : '';
  const connu = connuEl ? connuEl.value : '0';
  if(phy===''){toast('Saisissez d\'abord le stock physique.','error');return;}
  const d = await ap({action:'sauver_ligne',detail_id:id,stock_physique:phy,ecart_connu:connu||0,notes:''});
  if(d.success){
    if(phyEl) phyEl.style.borderColor='#27ae60';
    setTimeout(()=>{if(phyEl)phyEl.style.borderColor='';},2000);
    toast('Ligne sauvegardée.');
  } else toast('Erreur : '+d.message,'error');
}

async function sauverTout(){
  const btn=document.getElementById('btnSauverTout');
  if(btn){btn.disabled=true;btn.textContent='⏳ Sauvegarde…';}
  const rows=document.querySelectorAll('tr[id^="row-"]');
  const lignes=[];
  rows.forEach(row=>{
    const id=row.id.replace('row-','');
    const phyEl=document.getElementById('phy-'+id);
    const connuEl=document.getElementById('connu-'+id);
    if(phyEl&&phyEl.value!=='') lignes.push({id,phy:phyEl.value,connu:connuEl?connuEl.value||0:0,notes:''});
  });
  if(!lignes.length){toast('Aucune valeur à sauvegarder.','error');if(btn){btn.disabled=false;btn.textContent='💾 Tout sauver';}return;}
  const d=await ap({action:'sauver_tout',lignes:JSON.stringify(lignes)});
  if(btn){btn.disabled=false;btn.textContent='💾 Tout sauver';}
  if(d.success){
    toast(d.message);
    document.getElementById('alertZone').innerHTML=`<div style="background:#eafaf1;padding:10px 16px;border-radius:8px;font-size:13px;color:#1e8449;margin-bottom:12px">✅ ${d.message}</div>`;
  } else toast('Erreur : '+d.message,'error');
}

async function validerInventaire(){
  if(!confirm('Valider cet inventaire ?\nLes écarts seront appliqués au stock système.')) return;
  const d=await ap({action:'valider'});
  if(d.success){toast(d.message);setTimeout(()=>location.href='inventaire_bobines.php',1500);}
  else{document.getElementById('alertZone').innerHTML=`<div style="background:#fdf0ef;padding:10px 16px;border-radius:8px;font-size:13px;color:#c0392b;margin-bottom:12px">❌ ${d.message}</div>`;}
}

function updateStats(){
  const rows=document.querySelectorAll('tr[id^="row-"]');
  let total=rows.length, ok=0, ecarts=0, nonSaisis=0;
  rows.forEach(row=>{
    const id=row.id.replace('row-','');
    const phyEl=document.getElementById('phy-'+id);
    const ecEl=document.getElementById('ecart-'+id);
    if(!phyEl||phyEl.value===''){nonSaisis++;}
    else{
      const phy=parseInt(phyEl.value);
      const sys=parseInt(phyEl.dataset.sys||0);
      if(phy===sys) ok++; else ecarts++;
    }
  });
  const el=n=>document.getElementById(n);
  if(el('statTotal')) el('statTotal').textContent=total;
  if(el('statOk'))    el('statOk').textContent=ok;
  if(el('statEcarts'))el('statEcarts').textContent=ecarts;
  if(el('statNonSaisis'))el('statNonSaisis').textContent=nonSaisis;
}
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
