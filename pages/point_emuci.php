<?php
// ============================================================
//  pages/point_emuci.php
//  Point journalier EMUCI — Contrôleur Production
//  Saisie des plaques posées + réservées par site
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

require_auth();

$user      = current_user();
$role_slug = $user['role_slug'] ?? '';
$page_title  = 'Point EMUCI';
$active_page = 'point_emuci';

// Accès : controleur_production, superviseur_operation, admin, superadmin
if (!in_array($role_slug, ['controleur_production','superviseur_operation','admin','superadmin'])) {
    http_response_code(403);
    echo '<p style="padding:40px;color:red">Accès refusé.</p>';
    exit;
}

$sites_list = db_fetch_all("SELECT id, nom FROM sites WHERE actif=1 ORDER BY nom");

// ============================================================
//  AJAX
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // ── SAUVEGARDER POINT
    if ($action === 'sauver') {
        $site_id    = (int)($_POST['site_id']    ?? 0);
        $date_point = trim($_POST['date_point']  ?? date('Y-m-d'));
        $posees     = max(0,(int)($_POST['plaques_posees']    ?? 0));
        $ex_res     = max(0,(int)($_POST['poses_ex_reservees']?? 0));
        $reservees  = max(0,(int)($_POST['plaques_reservees'] ?? 0));
        $notes      = trim($_POST['notes'] ?? '');
        $statut     = $_POST['statut'] === 'soumis' ? 'soumis' : 'brouillon';

        // Validation
        if ($ex_res > $posees)
            json_response(false, 'Les poses issues de réservations ne peuvent pas dépasser le total des poses.');

        if (!$site_id || !$date_point)
            json_response(false, 'Site et date obligatoires.');

        // Vérifier si un point existe déjà
        $exist = db_fetch_one(
            "SELECT id, statut FROM points_emuci WHERE site_id=? AND date_point=?",
            [$site_id, $date_point]
        );

        if ($exist) {
            if ($exist['statut'] === 'valide')
                json_response(false, 'Ce point est déjà validé, impossible de modifier.');

            db_query(
                "UPDATE points_emuci SET plaques_posees=?, poses_ex_reservees=?, plaques_reservees=?, notes=?, statut=?, updated_at=NOW() WHERE id=?",
                [$posees, $ex_res, $reservees, $notes, $statut, $exist['id']]
            );
            $point_id = $exist['id'];
            $mode = 'update';
        } else {
            db_query(
                "INSERT INTO points_emuci (site_id, date_point, plaques_posees, poses_ex_reservees, plaques_reservees, notes, statut, saisi_par)
                 VALUES (?,?,?,?,?,?,?,?)",
                [$site_id, $date_point, $posees, $ex_res, $reservees, $notes, $statut, $user['id']]
            );
            $point_id = (int)db_last_id();
            $mode = 'create';
        }

        // Recalculer la comparaison avec DigiStock
        _recalcule_comparaison($site_id, $date_point);

        audit_log($user['id'], $mode==='create'?'CREATE':'UPDATE', 'point_emuci', $point_id,
            "Point EMUCI site:$site_id $date_point — posées:$posees réservées:$reservees");

        json_response(true, $mode==='create' ? 'Point enregistré.' : 'Point mis à jour.', [
            'id'    => $point_id,
            'mode'  => $mode,
            'total' => $posees + $reservees
        ]);
    }

    // ── VALIDER UN POINT (admin/superviseur)
    if ($action === 'valider') {
        if (!in_array($role_slug, ['admin','superadmin','superviseur_operation']))
            json_response(false, 'Accès refusé.');

        $id = (int)($_POST['point_id'] ?? 0);
        db_query("UPDATE points_emuci SET statut='valide', valide_par=?, valide_at=NOW() WHERE id=?",
            [$user['id'], $id]);
        audit_log($user['id'],'UPDATE','point_emuci',$id,"Validation point EMUCI #$id");
        json_response(true, 'Point validé.');
    }

    // ── CHARGER UN POINT EXISTANT
    if ($action === 'charger') {
        $site_id    = (int)($_POST['site_id']    ?? 0);
        $date_point = trim($_POST['date_point']  ?? date('Y-m-d'));

        $point = db_fetch_one(
            "SELECT pe.*, CONCAT(u.prenom,' ',u.nom) AS saisi_nom
             FROM points_emuci pe LEFT JOIN users u ON u.id=pe.saisi_par
             WHERE pe.site_id=? AND pe.date_point=?",
            [$site_id, $date_point]
        );

        // Stock bobines actuel du site
        $stock_bobines = db_fetch_one(
            "SELECT COUNT(*) AS nb_bobines, COALESCE(SUM(stock_systeme),0) AS total_films
             FROM op_bobines WHERE site_id=? AND statut IN ('en_cours','en_stock')",
            [$site_id]
        );

        // Point DigiStock du même jour (PJ coordinateurs)
        $pj_digistock = db_fetch_one(
            "SELECT COALESCE(SUM(total_plaques),0) AS plaques_pj, COALESCE(SUM(rivets_utilises),0) AS rivets_pj
             FROM op_points_journaliers WHERE site_id=? AND date_point=?",
            [$site_id, $date_point]
        );

        // Comparaison existante
        $comp = db_fetch_one(
            "SELECT * FROM comparaisons_stock WHERE site_id=? AND date_comparaison=?",
            [$site_id, $date_point]
        );

        json_response(true, '', [
            'point'        => $point,
            'stock_bobines'=> $stock_bobines,
            'pj_digistock' => $pj_digistock,
            'comparaison'  => $comp
        ]);
    }

    // ── AJUSTER LE STOCK (admin/superviseur uniquement)
    if ($action === 'ajuster') {
        if (!in_array($role_slug, ['admin','superadmin','superviseur_operation']))
            json_response(false, 'Accès refusé.');

        $site_id = (int)($_POST['site_id'] ?? 0);
        $date    = trim($_POST['date_point'] ?? '');
        $notes   = trim($_POST['notes_ajustement'] ?? '');

        if (!$site_id || !$date)
            json_response(false, 'Site et date obligatoires.');

        $comp = db_fetch_one(
            "SELECT * FROM comparaisons_stock WHERE site_id=? AND date_comparaison=?",
            [$site_id, $date]
        );
        if (!$comp) json_response(false, 'Aucune comparaison trouvée.');
        if ($comp['ajuste']) json_response(false, 'Déjà ajusté.');

        $ecart = (int)$comp['ecart'];

        // Ajuster les bobines actives du site
        // On prend l'écart et on l'applique sur le stock système
        if ($ecart !== 0) {
            $bobines = db_fetch_all(
                "SELECT id, stock_systeme FROM op_bobines WHERE site_id=? AND statut IN ('en_cours','en_stock')",
                [$site_id]
            );

            // Répartir l'écart sur les bobines actives proportionnellement
            $nb = count($bobines);
            if ($nb > 0) {
                $ecart_par_bobine = intdiv(abs($ecart), $nb);
                $reste = abs($ecart) - ($ecart_par_bobine * $nb);

                foreach ($bobines as $i => $b) {
                    $ajust = $ecart_par_bobine + ($i === 0 ? $reste : 0);
                    $nouveau_stock = max(0, $b['stock_systeme'] + ($ecart > 0 ? -$ajust : $ajust));
                    db_query("UPDATE op_bobines SET stock_systeme=? WHERE id=?", [$nouveau_stock, $b['id']]);

                    // Tracer le mouvement
                    db_query(
                        "INSERT INTO mouvements_bobines (bobine_id, type_mouvement, quantite, notes, created_by)
                         VALUES (?, 'ajustement_emuci', ?, ?, ?)",
                        [$b['id'], ($ecart > 0 ? -$ajust : $ajust), "Ajustement EMUCI $date — $notes", $user['id']]
                    );
                }
            }
        }

        // Marquer la comparaison comme ajustée
        db_query(
            "UPDATE comparaisons_stock SET ajuste=1, ajuste_par=?, ajuste_at=NOW(), notes_ajustement=? WHERE id=?",
            [$user['id'], $notes, $comp['id']]
        );

        audit_log($user['id'],'UPDATE','point_emuci',$comp['id'],
            "Ajustement stock site:$site_id $date écart:$ecart films");

        json_response(true, "Stock ajusté (écart de $ecart films corrigé).");
    }

    json_response(false, 'Action inconnue.');
}

// Fonction interne : recalculer la comparaison EMUCI vs DigiStock
function _recalcule_comparaison(int $site_id, string $date): void {
    // Films EMUCI du jour
    $emuci = (int)db_fetch_value(
        "SELECT COALESCE(plaques_posees - poses_ex_reservees + plaques_reservees,0) FROM points_emuci WHERE site_id=? AND date_point=?",
        [$site_id, $date]
    );
    // Films DigiStock du jour (PJ coordinateurs)
    $digi = (int)db_fetch_value(
        "SELECT COALESCE(SUM(total_plaques),0) FROM op_points_journaliers WHERE site_id=? AND date_point=?",
        [$site_id, $date]
    );

    $exist = db_fetch_one(
        "SELECT id FROM comparaisons_stock WHERE site_id=? AND date_comparaison=?",
        [$site_id, $date]
    );

    if ($exist) {
        db_query(
            "UPDATE comparaisons_stock SET films_emuci=?, films_digistock=? WHERE id=?",
            [$emuci, $digi, $exist['id']]
        );
    } else {
        db_query(
            "INSERT INTO comparaisons_stock (site_id, date_comparaison, films_emuci, films_digistock)
             VALUES (?,?,?,?)",
            [$site_id, $date, $emuci, $digi]
        );
    }
}

// ============================================================
//  DONNÉES LISTE
// ============================================================
$f_site  = (int)($_GET['site'] ?? 0);
$f_mois  = trim($_GET['mois'] ?? date('Y-m'));
$f_statut= trim($_GET['statut'] ?? '');

$where = ["DATE_FORMAT(pe.date_point,'%Y-%m')=?"];
$params = [$f_mois];
if ($f_site)   { $where[] = 'pe.site_id=?';  $params[] = $f_site; }
if ($f_statut) { $where[] = 'pe.statut=?';   $params[] = $f_statut; }

$points = db_fetch_all(
    "SELECT pe.*, s.nom AS site_nom,
            CONCAT(u.prenom,' ',u.nom) AS saisi_nom,
            cs.ecart, cs.statut_ecart, cs.ajuste,
            dj.total_plaques AS pj_digistock
     FROM points_emuci pe
     JOIN sites s ON s.id=pe.site_id
     LEFT JOIN users u ON u.id=pe.saisi_par
     LEFT JOIN comparaisons_stock cs ON cs.site_id=pe.site_id AND cs.date_comparaison=pe.date_point
     LEFT JOIN (SELECT site_id, date_point, SUM(total_plaques) AS total_plaques FROM op_points_journaliers GROUP BY site_id, date_point) dj
          ON dj.site_id=pe.site_id AND dj.date_point=pe.date_point
     WHERE " . implode(' AND ', $where) . "
     ORDER BY pe.date_point DESC, s.nom ASC",
    $params
);

// KPIs du mois
$kpi_total  = count($points);
$kpi_posees = array_sum(array_column($points, 'plaques_posees'));
$kpi_reserv = array_sum(array_column($points, 'plaques_reservees'));
$kpi_ecarts = count(array_filter($points, fn($p) => ($p['ecart'] ?? 0) != 0 && !$p['ajuste']));

include __DIR__ . '/../templates/header.php';
?>
<style>
.kpi-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
.kpi-c{background:white;border:1px solid var(--border);border-radius:14px;padding:16px 20px;border-left:4px solid var(--blue)}
.kpi-c.warn{border-left-color:#f39c12}
.kpi-c.danger{border-left-color:#e74c3c}
.kpi-c.ok{border-left-color:var(--success)}
.kpi-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:28px;font-weight:900;color:var(--navy);line-height:1}
.kpi-lbl{font-size:11px;color:var(--muted);margin-top:5px;font-weight:600;text-transform:uppercase;letter-spacing:.4px}
.fsel{padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;background:white;outline:none;color:var(--text);cursor:pointer}
.ecart-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.ecart-ok{background:#d5f5e3;color:#1e7e40}
.ecart-min{background:#fff8e7;color:#9a6c00}
.ecart-maj{background:#fde8e8;color:#c0392b}
.statut-pill{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
.statut-pill.brouillon{background:#f1f5f9;color:#64748b}
.statut-pill.soumis{background:#e8f4f9;color:var(--blue)}
.statut-pill.valide{background:#d5f5e3;color:#1e7e40}
</style>

<!-- KPIs -->
<div class="kpi-row">
  <div class="kpi-c">
    <div class="kpi-val"><?= $kpi_total ?></div>
    <div class="kpi-lbl">Points ce mois</div>
  </div>
  <div class="kpi-c ok">
    <div class="kpi-val" style="color:var(--success)"><?= fmt_number($kpi_posees) ?></div>
    <div class="kpi-lbl">Plaques posées</div>
  </div>
  <div class="kpi-c warn">
    <div class="kpi-val" style="color:#f39c12"><?= fmt_number($kpi_reserv) ?></div>
    <div class="kpi-lbl">Plaques réservées</div>
  </div>
  <div class="kpi-c <?= $kpi_ecarts>0?'danger':'ok' ?>">
    <div class="kpi-val" style="color:<?= $kpi_ecarts>0?'#e74c3c':'var(--success)' ?>"><?= $kpi_ecarts ?></div>
    <div class="kpi-lbl">Écarts non ajustés</div>
  </div>
</div>

<!-- TOOLBAR -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <select name="site" class="fsel" onchange="this.form.submit()">
      <option value="0">Tous les sites</option>
      <?php foreach($sites_list as $s): ?>
      <option value="<?= $s['id'] ?>" <?= $f_site===$s['id']?'selected':'' ?>><?= h($s['nom']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="month" name="mois" value="<?= h($f_mois) ?>" onchange="this.form.submit()"
           style="padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;outline:none">
    <select name="statut" class="fsel" onchange="this.form.submit()">
      <option value="">Tous statuts</option>
      <option value="brouillon" <?= $f_statut==='brouillon'?'selected':'' ?>>Brouillon</option>
      <option value="soumis"    <?= $f_statut==='soumis'?'selected':'' ?>>Soumis</option>
      <option value="valide"    <?= $f_statut==='valide'?'selected':'' ?>>Validé</option>
    </select>
  </form>
  <?php if(in_array($role_slug,['controleur_production','admin','superadmin'])): ?>
  <button class="btn btn-primary" onclick="ouvrirForm()">+ Nouveau point EMUCI</button>
  <?php endif; ?>
</div>

<!-- LISTE -->
<div class="card">
  <div class="card-header">
    <h3>📋 Points EMUCI <span style="font-size:13px;font-weight:400;color:var(--muted)">(<?= count($points) ?>)</span></h3>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Date</th>
        <th>Site</th>
        <th style="text-align:center">Plaques posées</th>
        <th style="text-align:center">Plaques réservées</th>
        <th style="text-align:center">Total films déduits</th>
        <th style="text-align:center">PJ DigiStock</th>
        <th style="text-align:center">Écart</th>
        <th>Statut</th>
        <th>Saisi par</th>
        <th style="text-align:center">Actions</th>
      </tr></thead>
      <tbody>
      <?php if(empty($points)): ?>
        <tr><td colspan="10" style="text-align:center;padding:40px;color:var(--muted)">Aucun point ce mois.</td></tr>
      <?php else: foreach($points as $p):
        $total = $p['plaques_posees'] + $p['plaques_reservees'];
        $ecart = $p['ecart'] ?? null;
        $ecart_cls = $ecart===null?'':($ecart==0?'ecart-ok':($p['statut_ecart']==='mineur'?'ecart-min':'ecart-maj'));
      ?>
        <tr>
          <td style="font-weight:700;white-space:nowrap"><?= fmt_date($p['date_point'],'d/m/Y') ?></td>
          <td style="font-weight:600;color:var(--navy)"><?= h($p['site_nom']) ?></td>
          <td style="text-align:center;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;color:var(--success)"><?= $p['plaques_posees'] ?></td>
          <td style="text-align:center;font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;color:#f39c12"><?= $p['plaques_reservees'] ?></td>
          <td style="text-align:center;font-family:'Plus Jakarta Sans',sans-serif;font-weight:900;color:var(--navy)"><?= $total ?></td>
          <td style="text-align:center;color:var(--muted)"><?= $p['pj_digistock'] ?? '—' ?></td>
          <td style="text-align:center">
            <?php if($ecart !== null): ?>
            <span class="ecart-badge <?= $ecart_cls ?>">
              <?= $ecart > 0 ? "+$ecart" : $ecart ?> films
              <?= $p['ajuste'] ? ' ✓' : '' ?>
            </span>
            <?php else: ?>
            <span style="color:var(--muted)">—</span>
            <?php endif; ?>
          </td>
          <td><span class="statut-pill <?= $p['statut'] ?>"><?= match($p['statut']){'brouillon'=>'⏳ Brouillon','soumis'=>'📤 Soumis','valide'=>'✅ Validé',default=>$p['statut']} ?></span></td>
          <td style="font-size:12px;color:var(--muted)"><?= h($p['saisi_nom']??'—') ?></td>
          <td style="text-align:center;white-space:nowrap;display:flex;gap:4px;justify-content:center;padding:12px 14px">
            <?php if(in_array($role_slug,['controleur_production','admin','superadmin']) && $p['statut']!=='valide'): ?>
            <button class="btn btn-secondary btn-sm" onclick="editer(<?= $p['id'] ?>,<?= $p['site_id'] ?>,'<?= $p['date_point'] ?>')">✏️</button>
            <?php endif; ?>
            <?php if($p['statut']==='soumis' && in_array($role_slug,['admin','superadmin','superviseur_operation'])): ?>
            <button class="btn btn-success btn-sm" onclick="valider(<?= $p['id'] ?>)">✅ Valider</button>
            <?php endif; ?>
            <?php if($ecart && $ecart!=0 && !$p['ajuste'] && in_array($role_slug,['admin','superadmin','superviseur_operation'])): ?>
            <button class="btn btn-sm" style="background:#fff3e0;color:#e65100;border:1px solid #ffcc80"
                    onclick="ajuster(<?= $p['site_id'] ?>,'<?= $p['date_point'] ?>')">⚖️ Ajuster</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL SAISIE -->
<div id="modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:flex-start;justify-content:center;padding:30px;overflow-y:auto">
  <div style="background:white;border-radius:20px;padding:30px;width:580px;max-width:95vw;margin:auto;box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:22px">
      <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:800;color:var(--navy)">📋 Point EMUCI</h3>
      <button onclick="fermer()" style="background:none;border:none;font-size:22px;cursor:pointer;color:var(--muted)">✕</button>
    </div>

    <div id="mAlert"></div>

    <!-- Infos stock bobines du site -->
    <div id="infoBox" style="display:none;background:var(--lighter);border-radius:12px;padding:14px 18px;margin-bottom:18px;border-left:4px solid var(--blue)">
      <div style="font-size:12px;font-weight:700;color:var(--navy);margin-bottom:8px">📊 État actuel du site</div>
      <div style="display:flex;gap:24px;font-size:13px">
        <span>🎞️ Bobines actives : <strong id="iNbBob">—</strong></span>
        <span>📽️ Films restants : <strong id="iFilms">—</strong></span>
        <span>📝 PJ DigiStock : <strong id="iPJ">—</strong></span>
      </div>
    </div>

    <input type="hidden" id="fId">
    <input type="hidden" id="fSiteId">

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:18px">
      <div class="form-group" style="grid-column:span 1">
        <label>Site *</label>
        <select class="form-control" id="fSite" onchange="onSiteChange()">
          <option value="">— Sélectionner —</option>
          <?php foreach($sites_list as $s): ?>
          <option value="<?= $s['id'] ?>"><?= h($s['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Date *</label>
        <input type="date" class="form-control" id="fDate" value="<?= date('Y-m-d') ?>" onchange="charger()">
      </div>
      <div class="form-group">
        <label>Statut</label>
        <select class="form-control" id="fStatut">
          <option value="brouillon">Brouillon</option>
          <option value="soumis">Soumettre</option>
        </select>
      </div>
    </div>

    <!-- Saisie principale -->
    <div style="background:var(--lighter);border-radius:14px;padding:20px;margin-bottom:18px">
      <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:16px">Données EMUCI du jour</div>

      <!-- Ligne 1 : Plaques posées + dont ex-réservées -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div class="form-group">
          <label style="color:var(--success)">✅ Plaques posées (total)</label>
          <input type="number" class="form-control" id="fPosees" min="0" value="0"
                 oninput="calcTotal()"
                 style="font-size:22px;font-weight:800;text-align:center;border-color:var(--success)">
          <div style="font-size:11px;color:var(--muted);margin-top:4px;text-align:center">Toutes les poses du jour</div>
        </div>
        <div class="form-group">
          <label style="color:#8e44ad">↩️ Dont issues de réservations</label>
          <input type="number" class="form-control" id="fPosesExRes" min="0" value="0"
                 oninput="calcTotal()"
                 style="font-size:22px;font-weight:800;text-align:center;border-color:#8e44ad">
          <div style="font-size:11px;color:var(--muted);margin-top:4px;text-align:center">Déjà déduits → pas de double déduction</div>
        </div>
      </div>

      <!-- Ligne 2 : Nouvelles réservées -->
      <div class="form-group" style="margin-bottom:16px">
        <label style="color:#f39c12">📌 Nouvelles plaques réservées</label>
        <input type="number" class="form-control" id="fReservees" min="0" value="0"
               oninput="calcTotal()"
               style="font-size:22px;font-weight:800;text-align:center;border-color:#f39c12">
        <div style="font-size:11px;color:var(--muted);margin-top:4px;text-align:center">Films bloqués — seront posées prochainement</div>
      </div>

      <!-- Détail du calcul -->
      <div style="background:rgba(0,0,0,.04);border-radius:10px;padding:12px 16px;margin-bottom:14px;font-size:12.5px;color:var(--muted)">
        <div style="display:flex;justify-content:space-between;margin-bottom:4px">
          <span>Posées nouvelles <span style="color:var(--muted);font-size:11px">(posées − ex-réservées)</span></span>
          <strong id="detailNouvelles" style="color:var(--success)">0</strong>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:4px">
          <span>+ Nouvelles réservées</span>
          <strong id="detailReservees" style="color:#f39c12">0</strong>
        </div>
        <div style="height:1px;background:var(--border);margin:8px 0"></div>
        <div style="display:flex;justify-content:space-between">
          <span style="font-weight:700;color:var(--navy)">= Films déduits du stock</span>
          <strong id="detailTotal" style="color:var(--navy);font-size:15px">0</strong>
        </div>
      </div>

      <!-- Total calculé -->
      <div style="padding:14px;background:var(--navy);border-radius:12px;text-align:center">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:rgba(255,255,255,.5);margin-bottom:4px">Films déduits du stock aujourd'hui</div>
        <div id="totalFilms" style="font-family:'Plus Jakarta Sans',sans-serif;font-size:36px;font-weight:900;color:white">0</div>
      </div>
    </div>

    <!-- Comparaison temps réel -->
    <div id="compBox" style="display:none;border-radius:12px;padding:14px 18px;margin-bottom:18px;border:1.5px solid var(--border)">
      <div style="font-size:12px;font-weight:700;color:var(--navy);margin-bottom:10px">⚖️ Comparaison vs DigiStock</div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;text-align:center">
        <div>
          <div style="font-size:11px;color:var(--muted);font-weight:600;margin-bottom:4px">EMUCI</div>
          <div id="compEmuci" style="font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:800;color:var(--navy)">0</div>
        </div>
        <div>
          <div style="font-size:11px;color:var(--muted);font-weight:600;margin-bottom:4px">DigiStock (PJ)</div>
          <div id="compDigi" style="font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:800;color:var(--blue)">—</div>
        </div>
        <div>
          <div style="font-size:11px;color:var(--muted);font-weight:600;margin-bottom:4px">Écart</div>
          <div id="compEcart" style="font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:800;color:var(--navy)">—</div>
        </div>
      </div>
    </div>

    <div class="form-group" style="margin-bottom:20px">
      <label>Notes / Observations</label>
      <textarea class="form-control" id="fNotes" rows="2" placeholder="Observations, anomalies..."></textarea>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button class="btn btn-secondary" onclick="fermer()">Annuler</button>
      <button class="btn btn-secondary" id="btnBrouillon" onclick="sauver('brouillon')">💾 Brouillon</button>
      <button class="btn btn-primary"   id="btnSoumettre" onclick="sauver('soumis')">📤 Soumettre</button>
    </div>
  </div>
</div>

<!-- MODAL AJUSTEMENT -->
<div id="modalAjust" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:20px;padding:30px;width:480px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:800;color:var(--navy);margin-bottom:18px">⚖️ Ajustement du stock</h3>
    <div id="ajustInfo" style="background:var(--lighter);border-radius:12px;padding:14px;margin-bottom:18px;font-size:13px"></div>
    <input type="hidden" id="ajSiteId">
    <input type="hidden" id="ajDate">
    <div class="form-group" style="margin-bottom:20px">
      <label>Motif de l'ajustement *</label>
      <textarea class="form-control" id="ajNotes" rows="3" placeholder="Expliquer la raison de l'ajustement..."></textarea>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button class="btn btn-secondary" onclick="document.getElementById('modalAjust').style.display='none'">Annuler</button>
      <button class="btn btn-primary" onclick="confirmerAjust()">✅ Confirmer l'ajustement</button>
    </div>
  </div>
</div>

<script>
function ap(data){return fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(data)}).then(r=>r.json());}
function toast(msg,type='success'){const t=document.createElement('div');t.style.cssText=`position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.15);background:${type==='success'?'#27ae60':'#e74c3c'};color:white;max-width:380px`;t.textContent=msg;document.body.appendChild(t);setTimeout(()=>t.remove(),3500);}

let pjDigi = 0;

function ouvrirForm(){
  document.getElementById('fId').value='';
  document.getElementById('fSite').value='';
  document.getElementById('fDate').value='<?= date('Y-m-d') ?>';
  document.getElementById('fPosees').value='0';
  document.getElementById('fPosesExRes').value='0';
  document.getElementById('fReservees').value='0';
  document.getElementById('fNotes').value='';
  document.getElementById('fStatut').value='brouillon';
  document.getElementById('infoBox').style.display='none';
  document.getElementById('compBox').style.display='none';
  document.getElementById('mAlert').innerHTML='';
  calcTotal();
  document.getElementById('modal').style.display='flex';
}

function editer(id,siteId,date){
  document.getElementById('fId').value=id;
  document.getElementById('fSite').value=siteId;
  document.getElementById('fDate').value=date;
  document.getElementById('mAlert').innerHTML='';
  charger();
  document.getElementById('modal').style.display='flex';
}

function fermer(){document.getElementById('modal').style.display='none';}
document.getElementById('modal').addEventListener('click',function(e){if(e.target===this)fermer();});

function calcTotal(){
  const posees   = parseInt(document.getElementById('fPosees').value)||0;
  const exRes    = parseInt(document.getElementById('fPosesExRes').value)||0;
  const reservees= parseInt(document.getElementById('fReservees').value)||0;
  const nouvelles= Math.max(0, posees - exRes);
  const total    = nouvelles + reservees;

  document.getElementById('detailNouvelles').textContent = nouvelles;
  document.getElementById('detailReservees').textContent = reservees;
  document.getElementById('detailTotal').textContent     = total;
  document.getElementById('totalFilms').textContent      = total;

  // Alerte si ex-réservées > posées
  const mAlert = document.getElementById('mAlert');
  if(exRes > posees){
    mAlert.innerHTML='<div class="alert alert-warning">⚠️ Les poses issues de réservations ne peuvent pas dépasser le total des poses.</div>';
  } else {
    if(mAlert.innerHTML.includes('réservations')) mAlert.innerHTML='';
  }

  // Mise à jour comparaison temps réel
  document.getElementById('compEmuci').textContent=total;
  if(pjDigi!==null && document.getElementById('compBox').style.display!=='none'){
    const ec=total-pjDigi;
    const ecEl=document.getElementById('compEcart');
    ecEl.textContent=(ec>0?'+':'')+ec+' films';
    ecEl.style.color=ec===0?'var(--success)':(Math.abs(ec)<=5?'#f39c12':'#e74c3c');
  }
}

function onSiteChange(){ charger(); }

async function charger(){
  const site=document.getElementById('fSite').value;
  const date=document.getElementById('fDate').value;
  if(!site||!date) return;

  const d=await ap({action:'charger',site_id:site,date_point:date});
  if(!d.success) return;

  // Infos stock
  if(d.data.stock_bobines){
    document.getElementById('iNbBob').textContent=d.data.stock_bobines.nb_bobines||0;
    document.getElementById('iFilms').textContent=(d.data.stock_bobines.total_films||0).toLocaleString('fr-FR');
    document.getElementById('infoBox').style.display='block';
  }

  // PJ DigiStock
  pjDigi = parseInt(d.data.pj_digistock?.plaques_pj)||0;
  document.getElementById('iPJ').textContent=pjDigi;
  document.getElementById('compDigi').textContent=pjDigi;
  document.getElementById('compBox').style.display='block';

  // Pré-remplir si point existant
  if(d.data.point){
    document.getElementById('fId').value=d.data.point.id;
    document.getElementById('fPosees').value=d.data.point.plaques_posees||0;
    document.getElementById('fPosesExRes').value=d.data.point.poses_ex_reservees||0;
    document.getElementById('fReservees').value=d.data.point.plaques_reservees||0;
    document.getElementById('fNotes').value=d.data.point.notes||'';
    document.getElementById('fStatut').value=d.data.point.statut==='valide'?'soumis':d.data.point.statut;
  } else {
    document.getElementById('fPosees').value='0';
    document.getElementById('fPosesExRes').value='0';
    document.getElementById('fReservees').value='0';
  }
  calcTotal();
}

async function sauver(statut){
  const site=document.getElementById('fSite').value;
  const date=document.getElementById('fDate').value;
  if(!site||!date){document.getElementById('mAlert').innerHTML='<div class="alert alert-danger">Site et date obligatoires.</div>';return;}
  const btn=statut==='soumis'?document.getElementById('btnSoumettre'):document.getElementById('btnBrouillon');
  const lblOrig=statut==='soumis'?'📤 Soumettre':'💾 Brouillon';
  btn.disabled=true;btn.textContent='⏳...';
  try {
    const d=await ap({
      action:'sauver',site_id:site,date_point:date,statut,
      plaques_posees:document.getElementById('fPosees').value||0,
      poses_ex_reservees:document.getElementById('fPosesExRes').value||0,
      plaques_reservees:document.getElementById('fReservees').value||0,
      notes:document.getElementById('fNotes').value
    });
    if(d.success){toast(d.message);fermer();setTimeout(()=>location.reload(),900);}
    else document.getElementById('mAlert').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;
  } catch(e) {
    document.getElementById('mAlert').innerHTML='<div class="alert alert-danger">Erreur réseau — réessayez.</div>';
  } finally {
    btn.disabled=false;btn.textContent=lblOrig;
  }
}

async function valider(id){
  if(!confirm('Valider ce point EMUCI ?')) return;
  const d=await ap({action:'valider',point_id:id});
  if(d.success){toast(d.message);setTimeout(()=>location.reload(),800);}
  else alert('Erreur : '+d.message);
}

function ajuster(siteId,date){
  document.getElementById('ajSiteId').value=siteId;
  document.getElementById('ajDate').value=date;
  document.getElementById('ajNotes').value='';
  document.getElementById('ajustInfo').innerHTML=`
    <strong>⚠️ Attention :</strong> Cette action va corriger le stock système des bobines du site
    pour la date <strong>${date}</strong> en appliquant l'écart EMUCI/DigiStock.<br>
    Un mouvement correctif sera tracé dans l'historique.`;
  document.getElementById('modalAjust').style.display='flex';
}

async function confirmerAjust(){
  const notes=document.getElementById('ajNotes').value.trim();
  if(!notes){alert('Le motif est obligatoire.');return;}
  const d=await ap({action:'ajuster',site_id:document.getElementById('ajSiteId').value,date_point:document.getElementById('ajDate').value,notes_ajustement:notes});
  document.getElementById('modalAjust').style.display='none';
  if(d.success){toast(d.message);setTimeout(()=>location.reload(),900);}
  else toast(d.message,'error');
}
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
