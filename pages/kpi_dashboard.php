<?php
// ============================================================
//  pages/kpi_dashboard.php  —  Dashboard KPI centralise
//  n° 2.2 du CR de reunion PDG.
//
//  Distinct des deux tableaux de bord existants, comme demande :
//   - dashboard.php      : operationnel, oriente action du jour
//   - pdg_overview.php   : vue executive, decisionnelle et narrative
//   - celui-ci           : lecture rapide et visuelle des indicateurs,
//                          six familles cote a cote, chacune comparee
//                          a la periode precedente
//
//  La granularite vient de includes/periode.php, partagee avec la vue
//  executive : les quatre filtres (journalier, hebdomadaire, mensuel,
//  annuel) se comportent donc exactement pareil sur les deux ecrans.
//
//  Parti pris de lecture : chaque tuile porte sa valeur, sa variation
//  et le libelle de la periode comparee. Un chiffre sans son point de
//  comparaison ne dit rien — c'est precisement ce que le CR reproche
//  aux ecrans actuels.
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/periode.php';
require_once __DIR__ . '/../includes/consommation.php';

require_auth();
require_permission('kpi_dashboard', 'can_read');

$user        = current_user();
$role_slug   = $user['role_slug'] ?? '';
$page_title  = 'Dashboard KPI';
$active_page = 'kpi_dashboard';

$is_coord   = ($role_slug === 'coordinateur_site');
$site_force = ($is_coord && $user['site_id']) ? (int)$user['site_id'] : 0;
$site_id    = $site_force ?: (int)($_GET['site'] ?? 0);

$P   = periode_contexte();
$fmt = $P['date_fmt'];
$val = $P['val'];
$prc = $P['val_prec'];

// Filtres site — interpolation sure, $site_id est un entier.
$sf_p = $site_id ? "AND p.site_id = $site_id" : "";
$sf   = $site_id ? "AND site_id = $site_id"   : "";
$sf_b = $site_id ? "AND b.site_id = $site_id" : "";

/** Deux scalaires (periode courante / precedente) en une requete. */
function kpi_paire(string $sql, array $params): array {
    $r = db_fetch_one($sql, $params);
    return [(float)($r['courant'] ?? 0), (float)($r['precedent'] ?? 0)];
}

// ── PRODUCTION
[$plaques, $plaques_p] = kpi_paire(
    "SELECT COALESCE(SUM(CASE WHEN TO_CHAR(p.date_point,'$fmt')=? THEN p.total_plaques END),0) AS courant,
            COALESCE(SUM(CASE WHEN TO_CHAR(p.date_point,'$fmt')=? THEN p.total_plaques END),0) AS precedent
       FROM op_points_journaliers p
      WHERE p.statut <> 'brouillon' $sf_p", [$val, $prc]);

[$engins, $engins_p] = kpi_paire(
    "SELECT COALESCE(SUM(CASE WHEN TO_CHAR(p.date_point,'$fmt')=? THEN p.total_engins END),0) AS courant,
            COALESCE(SUM(CASE WHEN TO_CHAR(p.date_point,'$fmt')=? THEN p.total_engins END),0) AS precedent
       FROM op_points_journaliers p
      WHERE p.statut <> 'brouillon' $sf_p", [$val, $prc]);

$heures = (float) db_fetch_value(
    "SELECT COALESCE(SUM(p.nb_heures_travail),0) FROM op_points_journaliers p
      WHERE TO_CHAR(p.date_point,'$fmt')=? AND p.statut <> 'brouillon' $sf_p", [$val]);
$prod_horaire = $heures > 0 ? $engins / $heures : 0;

// ── BOBINES
$bob = db_fetch_one(
    "SELECT COUNT(*) FILTER (WHERE b.statut IN ('en_cours','en_stock'))       AS actives,
            COUNT(*) FILTER (WHERE b.statut = 'epuisee')                      AS epuisees,
            COUNT(*) FILTER (WHERE b.statut = 'retiree')                      AS retirees,
            COALESCE(SUM(b.films_restants),0)                                 AS restants,
            COALESCE(SUM(b.films_utilises),0)                                 AS utilises,
            COALESCE(SUM(b.films_endommages),0)                               AS endommages,
            COALESCE(SUM(b.qte_initiale),0)                                   AS initial
       FROM op_bobines b WHERE 1=1 $sf_b") ?: [];
$b_init  = (float)($bob['initial'] ?? 0);
$b_util  = (float)($bob['utilises'] ?? 0);
$b_endo  = (float)($bob['endommages'] ?? 0);
$taux_util  = $b_init > 0 ? $b_util / $b_init * 100 : 0;
$taux_perte = ($b_util + $b_endo) > 0 ? $b_endo / ($b_util + $b_endo) * 100 : 0;
$conso_jour = conso_moy_site($site_id, 30);
$couverture = $conso_jour > 0 ? (int) floor((float)($bob['restants'] ?? 0) / $conso_jour) : null;

// ── PMMA
$pmma_stock = db_fetch_all(
    "SELECT sp.type_pmma, SUM(sp.quantite) AS qte,
            SUM(CASE WHEN sp.quantite < COALESCE(sp.seuil_alerte,10) THEN 1 ELSE 0 END) AS bas
       FROM stock_pmma_site sp WHERE 1=1 " . ($site_id ? "AND sp.site_id = $site_id" : "") . "
      GROUP BY sp.type_pmma ORDER BY sp.type_pmma");
$pmma_bas = 0; foreach ($pmma_stock as $x) $pmma_bas += (int)$x['bas'];
[$pmma_conso, $pmma_conso_p] = kpi_paire(
    "SELECT COALESCE(SUM(CASE WHEN TO_CHAR(p.date_point,'$fmt')=? THEN pu.utilises END),0) AS courant,
            COALESCE(SUM(CASE WHEN TO_CHAR(p.date_point,'$fmt')=? THEN pu.utilises END),0) AS precedent
       FROM op_pmma_utilises pu JOIN op_points_journaliers p ON p.id = pu.point_id
      WHERE 1=1 $sf_p", [$val, $prc]);

// ── RIVETS
$riv_stock = (int) db_fetch_value(
    "SELECT COALESCE(SUM(quantite),0) FROM op_stock_rivets WHERE 1=1 $sf");
$riv_bas = (int) db_fetch_value(
    "SELECT COUNT(*) FROM op_stock_rivets WHERE quantite < COALESCE(seuil_alerte,200) $sf");
[$riv_conso, $riv_conso_p] = kpi_paire(
    "SELECT COALESCE(SUM(CASE WHEN TO_CHAR(p.date_point,'$fmt')=? THEN p.rivets_utilises END),0) AS courant,
            COALESCE(SUM(CASE WHEN TO_CHAR(p.date_point,'$fmt')=? THEN p.rivets_utilises END),0) AS precedent
       FROM op_points_journaliers p WHERE 1=1 $sf_p", [$val, $prc]);

// ── COMMANDES
$cmd = db_fetch_one(
    "SELECT COUNT(*)                                                            AS total,
            COUNT(*) FILTER (WHERE statut IN ('livre','recu'))                  AS servies,
            COUNT(*) FILTER (WHERE statut IN ('en_attente','en_attente_livraison','en_cours_livraison')) AS en_cours,
            COALESCE(AVG(CASE WHEN livraison_at IS NOT NULL
                         THEN EXTRACT(EPOCH FROM (livraison_at - created_at))/86400 END),0) AS delai
       FROM commandes
      WHERE TO_CHAR(created_at,'$fmt')=? $sf", [$val]) ?: [];
$cmd_total = (int)($cmd['total'] ?? 0);
$taux_service = $cmd_total > 0 ? (int)$cmd['servies'] / $cmd_total * 100 : 0;

// ── ÉQUIPEMENTS
$eq = db_fetch_one(
    "SELECT COUNT(*)                                        AS total,
            COUNT(*) FILTER (WHERE etat = 'ok')             AS ok,
            COUNT(*) FILTER (WHERE etat = 'hs')             AS hs,
            COUNT(*) FILTER (WHERE statut_stock = 'affecte') AS affectes
       FROM equipements WHERE actif = 1 $sf") ?: [];
$eq_total = (int)($eq['total'] ?? 0);
$dispo    = $eq_total > 0 ? (int)$eq['ok'] / $eq_total * 100 : 0;
$interv_ouvertes = (int) db_fetch_value(
    "SELECT COUNT(*) FROM interventions_maintenance
      WHERE statut_apres <> 'resolu' " . ($site_id ? "AND site_id = $site_id" : ""));

// ── SITES — classement de productivite sur la periode
$classement = db_fetch_all(
    "SELECT s.nom,
            COALESCE(SUM(p.total_plaques),0) AS plaques,
            COALESCE(SUM(p.total_engins),0)  AS engins,
            COALESCE(SUM(p.nb_heures_travail),0) AS heures
       FROM sites s
       LEFT JOIN op_points_journaliers p
              ON p.site_id = s.id AND TO_CHAR(p.date_point,'$fmt')=? AND p.statut <> 'brouillon'
      WHERE s.actif = 1 " . ($site_id ? "AND s.id = $site_id" : "") . "
      GROUP BY s.id, s.nom
      HAVING COALESCE(SUM(p.total_plaques),0) > 0
      ORDER BY plaques DESC", [$val]);
$plaques_max = 0; foreach ($classement as $c) $plaques_max = max($plaques_max, (int)$c['plaques']);

$sites_list = db_fetch_all("SELECT id,nom FROM sites WHERE actif=1 ORDER BY nom");

/** Variation en %, ou null si la periode precedente est vide. */
function kpi_var(float $c, float $p): ?float { return $p > 0 ? ($c - $p) / $p * 100 : null; }

/**
 * Tuile d'indicateur. $sens = 'haut' quand une hausse est favorable,
 * 'bas' quand c'est une baisse qui l'est (pertes, pannes, delais) :
 * sans cela une fleche verte pourrait signaler une degradation.
 */
function kpi_tuile(string $lbl, string $valeur, ?float $var, string $sens = 'haut',
                   string $note = '', string $ton = ''): string {
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $t = '<div class="kpi-t ' . $h($ton) . '">';
    $t .= '<div class="kpi-t-l">' . $h($lbl) . '</div>';
    $t .= '<div class="kpi-t-v">' . $h($valeur) . '</div>';
    if ($var !== null) {
        $hausse = $var > 0.05;
        $baisse = $var < -0.05;
        $bon = $hausse ? ($sens === 'haut') : ($baisse ? ($sens === 'bas') : null);
        $cls = $bon === null ? 'neutre' : ($bon ? 'bon' : 'mauvais');
        $fl  = $hausse ? '▲' : ($baisse ? '▼' : '=');
        $t .= '<div class="kpi-t-d ' . $cls . '">' . $fl . ' '
            . number_format(abs($var), 1, ',', ' ') . ' %</div>';
    } elseif ($note === '') {
        $t .= '<div class="kpi-t-d neutre">— pas de comparaison</div>';
    }
    if ($note !== '') $t .= '<div class="kpi-t-n">' . $h($note) . '</div>';
    return $t . '</div>';
}

include __DIR__ . '/../templates/header.php';
?>
<style>
.kpi-bar{display:flex;justify-content:space-between;align-items:flex-end;gap:14px;flex-wrap:wrap;margin-bottom:20px}
.kpi-fam{margin-bottom:24px}
.kpi-fam-h{display:flex;align-items:baseline;gap:10px;margin-bottom:11px}
.kpi-fam-t{font-family:'Plus Jakarta Sans',sans-serif;font-size:14.5px;font-weight:800;color:var(--navy)}
.kpi-fam-s{font-size:12px;color:var(--muted)}
.kpi-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(172px,1fr));gap:12px}
.kpi-t{background:white;border:1px solid var(--border);border-radius:13px;padding:14px 16px;border-left:4px solid var(--blue)}
.kpi-t.warn{border-left-color:#f39c12}
.kpi-t.crit{border-left-color:var(--danger)}
.kpi-t.good{border-left-color:var(--success)}
.kpi-t-l{font-size:11.5px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.kpi-t-v{font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;font-weight:900;color:var(--navy);
  line-height:1.1;margin-top:6px;font-variant-numeric:tabular-nums}
.kpi-t-d{font-size:12px;font-weight:700;margin-top:5px}
.kpi-t-d.bon{color:var(--success-d,#1e8449)} .kpi-t-d.mauvais{color:var(--danger-d,#c0392b)}
.kpi-t-d.neutre{color:var(--muted);font-weight:600}
.kpi-t-n{font-size:11.5px;color:var(--muted);margin-top:5px;line-height:1.4}
.kpi-clst{background:white;border:1px solid var(--border);border-radius:13px;padding:16px 18px}
.kpi-cl{display:grid;grid-template-columns:22px 1fr 92px 62px;align-items:center;gap:10px;
  padding:8px 0;border-bottom:1px solid var(--border);font-size:13.5px}
.kpi-cl:last-child{border-bottom:none}
.kpi-cl-r{font-family:'IBM Plex Mono',monospace;font-size:12px;font-weight:700;color:var(--muted)}
.kpi-cl-n{font-weight:600;color:var(--navy)}
.kpi-cl-b{height:7px;border-radius:4px;background:var(--lighter);overflow:hidden}
.kpi-cl-b i{display:block;height:100%;background:var(--blue);border-radius:4px}
.kpi-cl-v{text-align:right;font-weight:800;color:var(--navy);font-variant-numeric:tabular-nums}
</style>

<div class="kpi-bar">
  <div>
    <h2 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;color:var(--navy)">
      <i class="ph ph-gauge" aria-hidden="true"></i> Dashboard KPI
    </h2>
    <p style="font-size:13px;color:var(--muted);margin-top:4px">
      <?= h($P['libelle']) ?> · comparaison avec <?= h($P['libelle_prec']) ?>
      <?= $site_id ? ' · un seul site' : ' · tous les sites' ?>
    </p>
  </div>
  <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <?php if(!$is_coord): ?>
    <select name="site" class="month-inp" onchange="this.form.submit()" aria-label="Filtrer par site">
      <option value="0">Tous les sites</option>
      <?php foreach($sites_list as $s): ?>
      <option value="<?= (int)$s['id'] ?>" <?= $site_id===(int)$s['id']?'selected':'' ?>><?= h($s['nom']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <?= periode_selecteur($P, $site_id ? ['site'=>$site_id] : []) ?>
  </form>
</div>

<!-- PRODUCTION -->
<div class="kpi-fam">
  <div class="kpi-fam-h"><span class="kpi-fam-t">Production</span>
    <span class="kpi-fam-s">plaques posées et engins traités sur la période</span></div>
  <div class="kpi-row">
    <?= kpi_tuile('Plaques posées', fmt_number($plaques), kpi_var($plaques, $plaques_p), 'haut',
                  'vs ' . $P['libelle_prec'] . ' : ' . fmt_number($plaques_p)) ?>
    <?= kpi_tuile('Engins traités', fmt_number($engins), kpi_var($engins, $engins_p), 'haut',
                  'vs ' . $P['libelle_prec'] . ' : ' . fmt_number($engins_p)) ?>
    <?= kpi_tuile('Heures travaillées', number_format($heures, 1, ',', ' '), null, 'haut',
                  $heures > 0 ? 'déclarées sur la période' : 'aucune heure déclarée') ?>
    <?= kpi_tuile('Productivité', number_format($prod_horaire, 2, ',', ' '), null, 'haut',
                  'engins par heure travaillée') ?>
  </div>
</div>

<!-- BOBINES -->
<div class="kpi-fam">
  <div class="kpi-fam-h"><span class="kpi-fam-t">Bobines</span>
    <span class="kpi-fam-s">état du parc — photo instantanée, hors période</span></div>
  <div class="kpi-row">
    <?= kpi_tuile('Actives', fmt_number((int)($bob['actives'] ?? 0)), null, 'haut',
                  'en stock ou en cours') ?>
    <?= kpi_tuile('Épuisées', fmt_number((int)($bob['epuisees'] ?? 0)), null, 'bas',
                  fmt_number((int)($bob['retirees'] ?? 0)) . ' retirée(s)') ?>
    <?= kpi_tuile('Films restants', fmt_number((int)($bob['restants'] ?? 0)), null, 'haut',
                  $couverture !== null
                    ? 'couverture : ' . fmt_number($couverture) . ' jours au rythme observé'
                    : 'aucune consommation sur 30 jours') ?>
    <?= kpi_tuile("Taux d'utilisation", number_format($taux_util, 1, ',', ' ') . ' %', null, 'haut',
                  'films consommés sur dotation initiale') ?>
    <?= kpi_tuile('Films endommagés', fmt_number((int)$b_endo), null, 'bas',
                  number_format($taux_perte, 2, ',', ' ') . ' % des films sortis',
                  $taux_perte > 5 ? 'crit' : ($taux_perte > 2 ? 'warn' : '')) ?>
  </div>
</div>

<!-- PMMA & RIVETS -->
<div class="kpi-fam">
  <div class="kpi-fam-h"><span class="kpi-fam-t">PMMA & rivets</span>
    <span class="kpi-fam-s">consommation sur la période, stock et alertes de seuil</span></div>
  <div class="kpi-row">
    <?= kpi_tuile('PMMA consommés', fmt_number($pmma_conso), kpi_var($pmma_conso, $pmma_conso_p), 'haut',
                  'vs ' . $P['libelle_prec'] . ' : ' . fmt_number($pmma_conso_p)) ?>
    <?= kpi_tuile('PMMA sous seuil', fmt_number($pmma_bas), null, 'bas',
                  count($pmma_stock) . ' type(s) suivi(s)',
                  $pmma_bas > 0 ? 'crit' : 'good') ?>
    <?= kpi_tuile('Rivets consommés', fmt_number($riv_conso), kpi_var($riv_conso, $riv_conso_p), 'haut',
                  'vs ' . $P['libelle_prec'] . ' : ' . fmt_number($riv_conso_p)) ?>
    <?= kpi_tuile('Rivets en stock', fmt_number($riv_stock), null, 'haut',
                  $riv_bas > 0 ? $riv_bas . ' site(s) sous le seuil' : 'aucun site sous le seuil',
                  $riv_bas > 0 ? 'crit' : 'good') ?>
  </div>
</div>

<!-- COMMANDES & ÉQUIPEMENTS -->
<div class="kpi-fam">
  <div class="kpi-fam-h"><span class="kpi-fam-t">Commandes & équipements</span>
    <span class="kpi-fam-s">commandes de la période, parc équipement en instantané</span></div>
  <div class="kpi-row">
    <?= kpi_tuile('Taux de satisfaction', $cmd_total > 0 ? number_format($taux_service, 1, ',', ' ') . ' %' : '—',
                  null, 'haut',
                  $cmd_total > 0 ? (int)$cmd['servies'] . ' servie(s) sur ' . $cmd_total : 'aucune commande sur la période',
                  $cmd_total > 0 && $taux_service < 80 ? 'warn' : '') ?>
    <?= kpi_tuile('Délai moyen', $cmd_total > 0 ? number_format((float)$cmd['delai'], 1, ',', ' ') . ' j' : '—',
                  null, 'bas', 'de la création à la livraison') ?>
    <?= kpi_tuile('Commandes en cours', fmt_number((int)($cmd['en_cours'] ?? 0)), null, 'bas',
                  'non encore livrées') ?>
    <?= kpi_tuile('Disponibilité parc', $eq_total > 0 ? number_format($dispo, 1, ',', ' ') . ' %' : '—',
                  null, 'haut',
                  $eq_total > 0 ? (int)$eq['hs'] . ' hors service sur ' . $eq_total : 'aucun équipement actif',
                  $eq_total > 0 && $dispo < 90 ? 'warn' : '') ?>
    <?= kpi_tuile('Interventions ouvertes', fmt_number($interv_ouvertes), null, 'bas',
                  'non résolues à ce jour',
                  $interv_ouvertes > 0 ? 'warn' : 'good') ?>
  </div>
</div>

<!-- CLASSEMENT SITES -->
<?php if (!$site_id): ?>
<div class="kpi-fam">
  <div class="kpi-fam-h"><span class="kpi-fam-t">Classement des sites</span>
    <span class="kpi-fam-s">plaques posées sur la période — <?= h($P['libelle']) ?></span></div>
  <div class="kpi-clst">
    <?php if (empty($classement)): ?>
      <div style="color:var(--muted);font-size:13.5px;padding:8px 0">
        Aucune production enregistrée sur cette période.
      </div>
    <?php else: $r = 0; foreach ($classement as $c): $r++;
      $pct = $plaques_max > 0 ? (int)$c['plaques'] / $plaques_max * 100 : 0;
      $vh  = (float)$c['heures'] > 0 ? (float)$c['engins'] / (float)$c['heures'] : 0; ?>
    <div class="kpi-cl">
      <span class="kpi-cl-r"><?= $r ?></span>
      <span class="kpi-cl-n"><?= h($c['nom']) ?></span>
      <span class="kpi-cl-b"><i style="width:<?= round($pct, 1) ?>%"></i></span>
      <span class="kpi-cl-v"><?= fmt_number((int)$c['plaques']) ?></span>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
