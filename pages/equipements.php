<?php
// ============================================================
//  pages/equipements.php — Gestion Équipements
//  + Taux de pannes + Blocs résumé par type + Amortissement OHADA
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

require_auth();
// Permissions vérifiées via can() sur les actions

$user      = current_user();
$role_slug = $user['role_slug'] ?? '';
$is_coord  = ($role_slug === 'coordinateur_site');
$site_force= ($is_coord && ($user['site_id'] ?? 0)) ? (int)$user['site_id'] : 0;

$page_title  = 'Équipements';
$active_page = isset($_GET['categorie']) && $_GET['categorie']==='operationnel' ? 'equipements_op' : 'equipements_info';
$f_categorie = trim($_GET['categorie'] ?? 'informatique');
$f_site      = $site_force ?: (int)($_GET['site'] ?? 0);
$f_etat      = trim($_GET['etat'] ?? '');
$f_type      = (int)($_GET['type'] ?? 0);
$f_search    = trim($_GET['q'] ?? '');

$sites_list  = db_fetch_all("SELECT id,nom FROM sites WHERE actif=1 ORDER BY nom");
$nomenclatures     = db_fetch_all("SELECT id,libelle,categorie,duree_vie_mois FROM nomenclatures WHERE categorie=? ORDER BY libelle", [$f_categorie]);
$all_nomenclatures = db_fetch_all("SELECT id,libelle,categorie FROM nomenclatures ORDER BY categorie,libelle");
$can_create  = can('equipements','can_create');
$can_update  = can('equipements','can_update');

// ── Amortissements OHADA (durées standard en mois)
$ohada_durees = [
    'informatique'  => 36,   // Matériel informatique : 3 ans
    'mobilier'      => 120,  // Mobilier : 10 ans
    'vehicule'      => 60,   // Véhicules : 5 ans
    'operationnel'  => 60,   // Matériel et outillage : 5 ans
    'default'       => 60,
];

// ── AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'creer' || $action === 'modifier') {
        if (!$can_create && $action==='creer') json_response(false,'Accès refusé.');
        if (!$can_update && $action==='modifier') json_response(false,'Accès refusé.');

        $marque       = trim($_POST['marque']               ?? '');
        $modele       = trim($_POST['modele']               ?? '');
        $nsi          = trim($_POST['numero_serie_interne'] ?? '');
        $nse          = trim($_POST['numero_serie_externe'] ?? '');
        $nom_id       = (int)($_POST['nomenclature_id']     ?? 0);
        $site_id      = (int)($_POST['site_id']             ?? 0) ?: null;
        $etat         = trim($_POST['etat']                 ?? 'neuf');
        $date_achat   = trim($_POST['date_achat']           ?? '');
        $prix_achat   = (float)($_POST['prix_achat']        ?? 0);
        $statut_stock = trim($_POST['statut_stock']         ?? 'affecte');

        if (!$marque && !$nsi) json_response(false,'Marque ou N° série interne obligatoire.');

        // Calcul fin cycle OHADA
        $duree_mois     = $ohada_durees[$f_categorie] ?? $ohada_durees['default'];
        $date_fin_cycle = $date_achat
            ? date('Y-m-d', strtotime($date_achat . " +{$duree_mois} months"))
            : null;

        $label = ($marque ? $marque . ' ' : '') . ($modele ?: $nsi);

        if ($action === 'creer') {
            db_query(
                "INSERT INTO equipements
                 (marque,modele,categorie,nomenclature_id,numero_serie_interne,numero_serie_externe,
                  site_id,etat,statut_stock,date_acquisition,prix_achat,date_fin_cycle,duree_vie_mois,actif)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)",
                [$marque,$modele,$f_categorie,$nom_id ?: null,$nsi,$nse,
                 $site_id,$etat,$statut_stock,$date_achat ?: null,$prix_achat,$date_fin_cycle,$duree_mois]
            );
            $id = (int)db_last_id();
            audit_log($user['id'],'CREATE','equipements',$id,"Création équipement: $label");
            json_response(true,'Équipement créé.', ['id'=>$id]);
        } else {
            $id = (int)($_POST['id'] ?? 0);
            db_query(
                "UPDATE equipements
                 SET marque=?,modele=?,nomenclature_id=?,numero_serie_interne=?,numero_serie_externe=?,
                     site_id=?,etat=?,statut_stock=?,date_acquisition=?,prix_achat=?,date_fin_cycle=?,duree_vie_mois=?
                 WHERE id=?",
                [$marque,$modele,$nom_id ?: null,$nsi,$nse,
                 $site_id,$etat,$statut_stock,$date_achat ?: null,$prix_achat,$date_fin_cycle,$duree_mois,$id]
            );
            audit_log($user['id'],'UPDATE','equipements',$id,"Modification équipement: $label");
            json_response(true,'Équipement mis à jour.');
        }
    }

    json_response(false,'Action inconnue.');
}

// ── DONNÉES
$where = ["e.categorie=?","e.actif=1"]; $params = [$f_categorie];
if ($f_site)   { $where[] = "e.site_id=?";          $params[] = $f_site; }
if ($f_etat)   { $where[] = "e.etat=?";              $params[] = $f_etat; }
if ($f_type)   { $where[] = "e.nomenclature_id=?";   $params[] = $f_type; }
if ($f_search) { $where[] = "(e.numero_serie_interne LIKE ? OR e.marque LIKE ? OR e.modele LIKE ?)"; $params[] = "%$f_search%"; $params[] = "%$f_search%"; $params[] = "%$f_search%"; }

$equipements = db_fetch_all(
    "SELECT e.*, s.nom AS site_nom, n.libelle AS type_nom,
            -- Taux de pannes : interventions correctives / total interventions × 100
            (SELECT COUNT(*) FROM interventions_maintenance i WHERE i.equipement_id=e.id AND i.type_action='maintenance_corrective') AS nb_curative,
            (SELECT COUNT(*) FROM interventions_maintenance i WHERE i.equipement_id=e.id) AS nb_interventions_total,
            -- Valeur résiduelle OHADA (duree_vie_mois dans nomenclatures)
            CASE WHEN e.date_acquisition IS NOT NULL AND e.prix_achat > 0 AND COALESCE(e.duree_amortissement_mois, n.duree_vie_mois, 0) > 0
                 THEN GREATEST(0, ROUND(e.prix_achat - (e.prix_achat / COALESCE(e.duree_amortissement_mois, n.duree_vie_mois)) * TIMESTAMPDIFF(MONTH, e.date_acquisition, CURDATE()), 0))
                 ELSE NULL END AS valeur_residuelle,
            CASE WHEN e.date_acquisition IS NOT NULL AND COALESCE(e.duree_amortissement_mois, n.duree_vie_mois, 0) > 0
                 THEN ROUND(TIMESTAMPDIFF(MONTH, e.date_acquisition, CURDATE()) / COALESCE(e.duree_amortissement_mois, n.duree_vie_mois) * 100, 1)
                 ELSE NULL END AS pct_amorti
     FROM equipements e
     LEFT JOIN sites s ON s.id=e.site_id
     LEFT JOIN nomenclatures n ON n.id=e.nomenclature_id
     WHERE ".implode(' AND ',$where)."
     ORDER BY e.etat='hs' DESC, e.date_fin_cycle ASC, e.numero_serie_interne",
    $params
);

// ── Blocs résumé par type (nomenclature)
$blocs_site_cond = $site_force ? "AND e.site_id=$site_force" : "";
$blocs_type = db_fetch_all(
    "SELECT n.libelle, COUNT(e.id) AS nb_total,
            SUM(CASE WHEN e.etat IN ('neuf','bon') THEN 1 ELSE 0 END) AS nb_ok,
            SUM(CASE WHEN e.etat='hs' THEN 1 ELSE 0 END) AS nb_hs,
            SUM(CASE WHEN e.statut_stock='en_stock' THEN 1 ELSE 0 END) AS nb_stock
     FROM nomenclatures n
     LEFT JOIN equipements e ON e.nomenclature_id=n.id AND e.actif=1 AND e.categorie=? $blocs_site_cond
     WHERE n.categorie=?
     GROUP BY n.id HAVING nb_total > 0
     ORDER BY nb_total DESC",
    [$f_categorie, $f_categorie]
);

// ── KPIs
$nb_total  = count($equipements);
$nb_ok     = count(array_filter($equipements, fn($e)=>in_array($e['etat'],['neuf','bon'])));
$nb_hs     = count(array_filter($equipements, fn($e)=>$e['etat']==='hs'));
$nb_stock  = count(array_filter($equipements, fn($e)=>($e['statut_stock']??'')==='en_stock'));
$nb_fin_cycle = count(array_filter($equipements, fn($e)=>$e['date_fin_cycle'] && strtotime($e['date_fin_cycle']??'') < strtotime('+30 days')));

// ── EXPORT EXCEL
if (isset($_GET['export'])) {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) die('PhpSpreadsheet non installé.');
    require_once $autoload;
    $sp = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $ws = $sp->getActiveSheet();
    $ws->setTitle('Équipements');
    $etats = ['neuf'=>'Neuf','bon'=>'Bon état','usage'=>'Usagé','endommage'=>'Endommagé','hs'=>'Hors service'];
    $headers = ['N° Série','Type','Marque','Modèle','Site','État','Statut stock',
                'Date acq.','Prix achat','Valeur résiduelle','Amort. %',
                'Fin de cycle','Nb interventions','Taux curative %'];
    foreach ($headers as $i => $h) {
        $cell = $ws->getCell([$i+1, 1]);
        $cell->setValue($h);
        $cell->getStyle()->getFont()->setBold(true);
        $cell->getStyle()->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
             ->getStartColor()->setARGB('FF06033A');
        $cell->getStyle()->getFont()->getColor()->setARGB('FFFFFFFF');
    }
    $row = 2;
    foreach ($equipements as $e) {
        $taux_curative = $e['nb_interventions_total'] > 0
            ? round($e['nb_curative'] / $e['nb_interventions_total'] * 100, 1) : 0;
        $ws->setCellValue([1,  $row], $e['numero_serie_interne'] ?? '');
        $ws->setCellValue([2,  $row], $e['type_nom'] ?? '');
        $ws->setCellValue([3,  $row], $e['marque'] ?? '');
        $ws->setCellValue([4,  $row], $e['modele'] ?? '');
        $ws->setCellValue([5,  $row], $e['site_nom'] ?? '');
        $ws->setCellValue([6,  $row], $etats[$e['etat']] ?? $e['etat']);
        $ws->setCellValue([7,  $row], $e['statut_stock'] ?? '');
        $ws->setCellValue([8,  $row], $e['date_acquisition'] ?? '');
        $ws->setCellValue([9,  $row], $e['prix_achat'] ? (float)$e['prix_achat'] : '');
        $ws->setCellValue([10, $row], $e['valeur_residuelle'] !== null ? (float)$e['valeur_residuelle'] : '');
        $ws->setCellValue([11, $row], $e['pct_amorti'] !== null ? (float)$e['pct_amorti'] : '');
        $ws->setCellValue([12, $row], $e['date_fin_cycle'] ?? '');
        $ws->setCellValue([13, $row], (int)$e['nb_interventions_total']);
        $ws->setCellValue([14, $row], $taux_curative);
        $row++;
    }
    foreach (range(1, count($headers)) as $col)
        $ws->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
    $cat   = ucfirst($f_categorie);
    $fname = "equipements_{$f_categorie}_".date('Ymd').".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment;filename=\"$fname\"");
    header('Cache-Control: max-age=0');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sp))->save('php://output');
    exit;
}

include __DIR__ . '/../templates/header.php';
?>
<style>
.equip-kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:20px}
.ek{background:white;border-radius:13px;border:1px solid var(--border);padding:14px 16px;border-left:4px solid var(--blue)}
.ek.green{border-left-color:var(--success)} .ek.red{border-left-color:var(--danger)} .ek.orange{border-left-color:#f39c12} .ek.purple{border-left-color:#8e44ad}
.ek-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;font-weight:900;color:var(--navy)}
.ek-lbl{font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-top:3px}


.etat-badge{display:inline-block;padding:2px 9px;border-radius:12px;font-size:11px;font-weight:700}
.etat-neuf{background:#d1fae5;color:#065f46} .etat-bon{background:#dbeafe;color:#1d4ed8}
.etat-usage{background:#fef3c7;color:#92400e} .etat-endomage,.etat-endommage{background:#fee2e2;color:#991b1b}
.etat-hs{background:#1a1a1a;color:white}
.taux-ok{color:var(--success);font-weight:700} .taux-warn{color:#f39c12;font-weight:700} .taux-danger{color:var(--danger);font-weight:700}
</style>

<!-- ── LIGNE 1 : Onglets catégorie + bouton Ajouter ── -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <a href="?categorie=informatique" class="btn <?= $f_categorie==='informatique'?'btn-primary':'btn-secondary' ?>">
    <i class="ph-duotone ph-monitor"></i> Informatique
  </a>
  <a href="?categorie=operationnel" class="btn <?= $f_categorie==='operationnel'?'btn-primary':'btn-secondary' ?>">
    <i class="ph-duotone ph-wrench"></i> Opérationnel
  </a>
  <?php if($can_create): ?>
  <div style="width:1px;height:24px;background:var(--border);margin:0 4px"></div>
  <button type="button" class="btn btn-primary" onclick="ouvrirCreation()">
    <i class="ph-duotone ph-plus"></i> Ajouter
  </button>
  <?php endif; ?>
</div>

<!-- ── KPIs ── -->
<div class="equip-kpis">
  <div class="ek">          <div class="ek-val"><?= $nb_total ?></div>                                          <div class="ek-lbl">Total</div></div>
  <div class="ek green">    <div class="ek-val" style="color:var(--success)"><?= $nb_ok ?></div>                <div class="ek-lbl">✅ Opérationnels</div></div>
  <div class="ek red">      <div class="ek-val" style="color:var(--danger)"><?= $nb_hs ?></div>                 <div class="ek-lbl">❌ Hors service</div></div>
  <div class="ek purple">   <div class="ek-val" style="color:#8e44ad"><?= $nb_stock ?></div>                    <div class="ek-lbl">📦 En stock</div></div>
  <div class="ek orange">   <div class="ek-val" style="color:#f39c12"><?= $nb_fin_cycle ?></div>               <div class="ek-lbl">⚠️ Fin cycle &lt;30j</div></div>
</div>

<!-- ── LIGNE 2 : Filtres (remplace les blocs résumé) ── -->
<form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:18px">
  <input type="hidden" name="categorie" value="<?= h($f_categorie) ?>">

  <?php if($role_slug !== 'coordinateur_site'): ?>
  <select name="site" onchange="this.form.submit()" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;background:white;outline:none">
    <option value="">Tous les sites</option>
    <?php foreach($sites_list as $s): ?>
    <option value="<?= $s['id'] ?>" <?= $f_site==$s['id']?'selected':'' ?>><?= h($s['nom']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>

  <select name="etat" onchange="this.form.submit()" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;background:white;outline:none">
    <option value="">Tous états</option>
    <option value="neuf"      <?= $f_etat==='neuf'?'selected':''      ?>>Neuf</option>
    <option value="bon"       <?= $f_etat==='bon'?'selected':''       ?>>Bon état</option>
    <option value="usage"     <?= $f_etat==='usage'?'selected':''     ?>>Usagé</option>
    <option value="endommage" <?= $f_etat==='endommage'?'selected':'' ?>>Endommagé</option>
    <option value="hs"        <?= $f_etat==='hs'?'selected':''        ?>>H.S.</option>
  </select>

  <select name="type" onchange="this.form.submit()" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;background:white;outline:none">
    <option value="">Tous les types</option>
    <?php foreach($nomenclatures as $n): ?>
    <option value="<?= $n['id'] ?>" <?= $f_type===$n['id']?'selected':'' ?>><?= h($n['libelle']) ?></option>
    <?php endforeach; ?>
  </select>

  <div style="position:relative;flex:1;min-width:180px">
    <i class="ph-duotone ph-magnifying-glass" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:15px;pointer-events:none"></i>
    <input type="text" name="q" value="<?= h($f_search) ?>" placeholder="Rechercher..."
           onchange="this.form.submit()"
           style="width:100%;padding:9px 12px 9px 34px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;outline:none">
  </div>

  <?php if($f_site||$f_etat||$f_type||$f_search): ?>
  <a href="?categorie=<?= h($f_categorie) ?>" class="btn btn-secondary btn-sm" title="Réinitialiser les filtres">✕ Effacer</a>
  <?php endif; ?>

  <a href="?categorie=<?= h($f_categorie) ?>&site=<?= $f_site ?>&etat=<?= h($f_etat) ?>&type=<?= $f_type ?>&q=<?= urlencode($f_search) ?>&export=1"
     class="btn btn-secondary btn-sm">
    <i class="ph-duotone ph-file-xls"></i> Excel
  </a>
</form>

<!-- LISTE ÉQUIPEMENTS -->
<div class="card">
  <div class="card-header">
    <h3>
      <?= $f_categorie==='informatique'
        ? '<i class="ph-duotone ph-monitor" style="color:var(--primary)"></i> Équipements Informatique'
        : '<i class="ph-duotone ph-wrench" style="color:var(--primary)"></i> Équipements Opérationnel' ?>
      <span style="font-size:13px;font-weight:400;color:var(--muted)">(<?= $nb_total ?>)</span>
      <?php if($f_type): ?>
        <span style="font-size:12px;font-weight:600;color:var(--primary);background:var(--primary-l);padding:2px 10px;border-radius:20px;margin-left:8px">
          <?= h(current(array_filter($nomenclatures, fn($n)=>$n['id']===$f_type))['libelle'] ?? '') ?>
        </span>
      <?php endif; ?>
    </h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Équipement</th><th>Type</th><th>N° Série</th><th>Site</th>
        <th style="text-align:center">État</th>
        <th style="text-align:center">Taux pannes</th>
        <th style="text-align:center">Valeur résid. (FCFA)</th>
        <th style="text-align:center">Fin cycle</th>
        <th style="text-align:center">Statut stock</th>
        <?php if($can_update): ?><th style="text-align:center">Actions</th><?php endif; ?>
      </tr></thead>
      <tbody>
      <?php if(empty($equipements)): ?>
        <tr><td colspan="10" style="text-align:center;padding:40px;color:var(--muted)">Aucun équipement.</td></tr>
      <?php else: foreach($equipements as $e):
        $nb_int = (int)($e['nb_interventions_total']??0);
        $nb_cur = (int)($e['nb_curative']??0);
        $taux   = $nb_int > 0 ? round($nb_cur/$nb_int*100) : 0;
        $taux_cls = $taux >= 70 ? 'taux-danger' : ($taux >= 40 ? 'taux-warn' : 'taux-ok');
        $days_left = $e['date_fin_cycle'] ? (int)round((strtotime($e['date_fin_cycle'])-time())/86400) : null;
      ?>
        <tr style="<?= $e['etat']==='hs'?'background:#fff5f5':'' ?>">
          <td style="font-weight:600;color:var(--navy)"><?= h(($e['numero_serie_interne']??'—') . (($e['marque']??'') ? ' — '.($e['marque']??'').' '.($e['modele']??'') : '')) ?></td>
          <td style="font-size:12px"><?= h($e['type_nom']??'—') ?></td>
          <td style="font-family:monospace;font-size:12px"><?= h($e['numero_serie_interne']??'—') ?></td>
          <td><?= h($e['site_nom']??'Non affecté') ?></td>
          <td style="text-align:center"><span class="etat-badge etat-<?= $e['etat'] ?>"><?= ucfirst($e['etat']) ?></span></td>
          <td style="text-align:center">
            <?php if($nb_int > 0): ?>
            <span class="<?= $taux_cls ?>"><?= $taux ?>%</span>
            <div style="font-size:10px;color:var(--muted)"><?= $nb_cur ?>/<?= $nb_int ?> int.</div>
            <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
          </td>
          <td style="text-align:center;font-size:12px;font-weight:600">
            <?php if($e['valeur_residuelle'] !== null): ?>
            <?= fmt_number($e['valeur_residuelle']) ?> FCFA
            <div style="font-size:10px;color:var(--muted)"><?= $e['pct_amorti'] ?>% amorti</div>
            <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
          </td>
          <td style="text-align:center;font-size:12px">
            <?php if($days_left !== null): ?>
            <span style="color:<?= $days_left<0?'var(--danger)':($days_left<30?'#f39c12':'var(--success)') ?>;font-weight:700">
              <?= $days_left < 0 ? 'Dépassé' : "$days_left j" ?>
            </span>
            <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
          </td>
          <td style="text-align:center">
            <span style="padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;background:<?= ($e['statut_stock']??'')==='en_stock'?'#e8f4f9':'#f0f0f0' ?>;color:<?= ($e['statut_stock']??'')==='en_stock'?'var(--blue)':'#666' ?>">
              <?= ($e['statut_stock']??'affecte')==='en_stock'?'📦 Stock':'✅ Affecté' ?>
            </span>
          </td>
          <?php if($can_update): ?>
          <td style="text-align:center">
            <button class="btn btn-secondary btn-sm" onclick="modifierEquip(<?= htmlspecialchars(json_encode($e),ENT_QUOTES) ?>)">✏️</button>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL CRÉATION/MODIFICATION -->
<?php if($can_create || $can_update): ?>
<div id="modalEquip" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;overflow-y:auto;padding:20px">
  <div style="background:white;border-radius:20px;padding:28px;width:580px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:var(--navy)" id="titleModal">Nouvel équipement</h3>
      <button onclick="fermerModal()" style="background:none;border:none;font-size:22px;cursor:pointer">✕</button>
    </div>
    <input type="hidden" id="eId">
    <input type="hidden" id="eAction" value="creer">
    <div id="eAlert"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
      <div class="form-group">
        <label>Marque</label>
        <input type="text" class="form-control" id="eMarque" placeholder="ex: HP, OKI, Canon...">
      </div>
      <div class="form-group">
        <label>Modèle</label>
        <input type="text" class="form-control" id="eModele" placeholder="ex: LaserJet Pro, C650...">
      </div>
      <div class="form-group">
        <label>Type (nomenclature)</label>
        <select class="form-control" id="eNom_id">
          <option value="">— Sélectionner —</option>
          <?php foreach($nomenclatures as $n): ?>
          <option value="<?= $n['id'] ?>"><?= h($n['libelle']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>N° Série interne</label>
        <input type="text" class="form-control" id="eNsi">
      </div>
      <div class="form-group">
        <label>N° Série externe</label>
        <input type="text" class="form-control" id="eNse">
      </div>
      <div class="form-group">
        <label>Site</label>
        <select class="form-control" id="eSite">
          <option value="">Non affecté</option>
          <?php foreach($sites_list as $s): ?>
          <option value="<?= $s['id'] ?>"><?= h($s['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>État</label>
        <select class="form-control" id="eEtat">
          <option value="neuf">Neuf</option>
          <option value="bon">Bon état</option>
          <option value="usage">Usagé</option>
          <option value="endommage">Endommagé</option>
          <option value="hs">Hors service</option>
        </select>
      </div>
      <div class="form-group">
        <label>Statut stock</label>
        <select class="form-control" id="eStatutStock">
          <option value="affecte">Affecté (en service)</option>
          <option value="en_stock">📦 En stock (disponible)</option>
        </select>
      </div>
      <div class="form-group">
        <label>Date achat</label>
        <input type="date" class="form-control" id="eDate">
      </div>
      <div class="form-group">
        <label>Prix achat (FCFA)</label>
        <input type="number" class="form-control" id="ePrix" value="0">
      </div>
    </div>
    <div style="background:var(--lighter);border-radius:10px;padding:12px 14px;margin-bottom:16px;font-size:12px;color:var(--muted)">
      💡 <strong>Amortissement OHADA :</strong> <?= $f_categorie==='informatique'?'Matériel informatique — 3 ans (36 mois)':'Matériel opérationnel — 5 ans (60 mois)' ?>. La date de fin de cycle et la valeur résiduelle sont calculées automatiquement.
    </div>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button class="btn btn-secondary" onclick="fermerModal()">Annuler</button>
      <button class="btn btn-primary" id="btnSave" onclick="sauvegarder()">✅ Enregistrer</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function ap(d){return fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(d)}).then(r=>r.json());}
function toast(m,t='success'){const el=document.createElement('div');el.style.cssText=`position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:600;background:${t==='success'?'#27ae60':'#e74c3c'};color:white`;el.textContent=m;document.body.appendChild(el);setTimeout(()=>el.remove(),3500);}

function ouvrirCreation(){
  document.getElementById('titleModal').textContent='Nouvel équipement';
  document.getElementById('eAction').value='creer';
  document.getElementById('eId').value='';
  ['eMarque','eModele','eNsi','eNse','ePrix'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('eEtat').value='neuf';
  document.getElementById('eStatutStock').value='affecte';
  document.getElementById('eAlert').innerHTML='';
  document.getElementById('modalEquip').style.display='flex';
}
function modifierEquip(e){
  document.getElementById('titleModal').textContent='Modifier équipement';
  document.getElementById('eAction').value='modifier';
  document.getElementById('eId').value=e.id;
  document.getElementById('eMarque').value=e.marque||'';document.getElementById('eModele').value=e.modele||'';
  document.getElementById('eNom_id').value=e.nomenclature_id||'';
  document.getElementById('eNsi').value=e.numero_serie_interne||'';
  document.getElementById('eNse').value=e.numero_serie_externe||'';
  document.getElementById('eSite').value=e.site_id||'';
  document.getElementById('eEtat').value=e.etat||'bon';
  document.getElementById('eStatutStock').value=e.statut_stock||'affecte';
  document.getElementById('eDate').value=e.date_acquisition||'';
  document.getElementById('ePrix').value=e.prix_achat||0;
  document.getElementById('eAlert').innerHTML='';
  document.getElementById('modalEquip').style.display='flex';
}
function fermerModal(){document.getElementById('modalEquip').style.display='none';}
async function sauvegarder(){
  const btn = document.getElementById('btnSave');
  btn.disabled = true;
  btn.textContent = '⏳ Enregistrement…';
  try {
    const d = await ap({
      action              : document.getElementById('eAction').value,
      id                  : document.getElementById('eId').value,
      marque              : document.getElementById('eMarque').value.trim(),
      modele              : document.getElementById('eModele').value.trim(),
      nomenclature_id     : document.getElementById('eNom_id').value,
      numero_serie_interne: document.getElementById('eNsi').value.trim(),
      numero_serie_externe: document.getElementById('eNse').value.trim(),
      site_id             : document.getElementById('eSite').value,
      etat                : document.getElementById('eEtat').value,
      statut_stock        : document.getElementById('eStatutStock').value,
      date_achat          : document.getElementById('eDate').value,
      prix_achat          : document.getElementById('ePrix').value,
    });
    if (d.success) {
      toast(d.message, 'success');
      fermerModal();
      setTimeout(() => location.reload(), 800);
    } else {
      document.getElementById('eAlert').innerHTML =
        `<div class="alert alert-danger">${d.message}</div>`;
    }
  } catch(err) {
    document.getElementById('eAlert').innerHTML =
      '<div class="alert alert-danger">Erreur réseau. Réessayez.</div>';
  } finally {
    btn.disabled = false;
    btn.textContent = '✅ Enregistrer';
  }
}
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
