<?php
// ============================================================
//  seed_demo_business.php — Jeu de données de démonstration
//  pour le bandeau « Performance business » de la vue PDG.
//
//  Usage :  /seed_demo_business.php?secret=emuci2026import
//  Purge :  /seed_demo_business.php?secret=emuci2026import&purge=1
//
//  Toutes les lignes créées sont marquées [DEMO] et ne peuvent
//  être supprimées que par ce marqueur : la purge ne touche
//  jamais aux données réelles.
// ============================================================

$secret = $_GET['secret'] ?? '';
if ($secret !== 'emuci2026import') { http_response_code(403); die('Accès refusé'); }

require_once __DIR__ . '/includes/db.php';

const TAG        = '[DEMO]';
const NB_JOURS   = 120;   // profondeur d'historique générée
const MAX_SITES  = 6;     // nombre de sites alimentés

$purge = !empty($_GET['purge']);
$log   = [];
$err   = [];

function step(string $msg): void { global $log; $log[] = $msg; }
function oops(string $msg): void { global $err; $err[] = $msg; }

// ── PURGE ────────────────────────────────────────────────────
function purge_demo(): void {
    // L'ordre respecte les dépendances : lignes filles d'abord.
    db_query("DELETE FROM op_films_utilises WHERE point_id IN
                (SELECT id FROM op_points_journaliers WHERE observations LIKE '" . TAG . "%')");
    step('op_films_utilises purgés');

    db_query("DELETE FROM op_points_journaliers WHERE observations LIKE '" . TAG . "%'");
    step('op_points_journaliers purgés');

    db_query("DELETE FROM comparaisons_stock WHERE notes_ajustement LIKE '" . TAG . "%'");
    step('comparaisons_stock purgées');

    db_query("DELETE FROM ecarts_bobines WHERE motif LIKE '" . TAG . "%'");
    step('ecarts_bobines purgés');

    db_query("DELETE FROM op_bobines WHERE notes_perte LIKE '" . TAG . "%' OR numero LIKE 'DEMO-%'");
    step('op_bobines de démo purgées');
}

try {
    if ($purge) {
        purge_demo();
    } else {
        // Repartir d'une base propre : la démo est rejouable à l'identique.
        purge_demo();

        // ── 1. Référentiel types de véhicule ─────────────────
        $types = db_fetch_all("SELECT id, serie_bobine FROM op_types_vehicule ORDER BY ordre, id");
        if (empty($types)) {
            foreach ([['VP','Véhicule particulier',2,4,'A',1],
                      ['CAM','Camion',2,4,'B',2],
                      ['SEMI','Semi-remorque',2,4,'C',3],
                      ['MOTO','Moto',1,2,'D',4]] as $t) {
                db_query("INSERT INTO op_types_vehicule (code, libelle, nb_plaques, nb_rivets, serie_bobine, ordre)
                          VALUES (?,?,?,?,?,?)", $t);
            }
            $types = db_fetch_all("SELECT id, serie_bobine FROM op_types_vehicule ORDER BY ordre, id");
            step('Référentiel op_types_vehicule créé (4 séries)');
        }
        $type_par_serie = [];
        foreach ($types as $t) {
            $s = strtoupper(trim((string)$t['serie_bobine']));
            if (!isset($type_par_serie[$s])) $type_par_serie[$s] = (int)$t['id'];
        }
        foreach (['A','B','C','D'] as $s) {
            if (!isset($type_par_serie[$s])) $type_par_serie[$s] = (int)$types[0]['id'];
        }

        // ── 2. Sites ─────────────────────────────────────────
        $sites = db_fetch_all("SELECT id, nom FROM sites WHERE actif = 1 ORDER BY id LIMIT " . MAX_SITES);
        if (empty($sites)) { oops('Aucun site actif : créez au moins un site avant de lancer la démo.'); throw new RuntimeException('no-site'); }
        step(count($sites) . ' site(s) alimenté(s)');

        // ── 3. Un utilisateur pour created_by ────────────────
        $uid = db_fetch_value("SELECT id FROM users ORDER BY id LIMIT 1");
        if (!$uid) { oops('Aucun utilisateur en base.'); throw new RuntimeException('no-user'); }

        // ── 4. Bobines de démo, une par série et par site ────
        $bobines = [];   // site_id => [serie => bobine_id]
        foreach ($sites as $si => $s) {
            foreach (['A','B','C','D'] as $k => $serie) {
                // Stock volontairement contrasté : le dernier site sera en tension.
                $restants = ($si === count($sites) - 1) ? random_int(40, 120) : random_int(180, 460);
                db_query(
                    "INSERT INTO op_bobines
                       (numero, type_code, serie, type_vehicule_id, films_total, films_utilises,
                        films_endommages, films_restants, site_id, statut, date_ouverture,
                        created_by, qte_initiale, stock_systeme)
                     VALUES (?,?,?,?,500,?,?,?,?,'en_cours',CURRENT_DATE - INTERVAL '90 days',?,500,?)",
                    [
                        sprintf('DEMO-%s%03d-%d', $serie, $si + 1, $k + 1),
                        $serie . sprintf('%03d', $k + 1),
                        $serie,
                        $type_par_serie[$serie],
                        500 - $restants,
                        random_int(2, 14),
                        $restants,
                        (int)$s['id'],
                        (int)$uid,
                        $restants,
                    ]
                );
                $bobines[(int)$s['id']][$serie] = (int)db_fetch_value("SELECT MAX(id) FROM op_bobines");
            }
        }
        step(count($sites) * 4 . ' bobines de démo créées');

        // Deux bobines déclarées perdues : alimente « Fiabilité du stock ».
        for ($i = 0; $i < 2; $i++) {
            $s = $sites[$i % count($sites)];
            db_query(
                "INSERT INTO op_bobines
                   (numero, type_code, serie, type_vehicule_id, films_total, films_utilises,
                    films_endommages, films_restants, site_id, statut, date_ouverture,
                    created_by, qte_initiale, stock_systeme, notes_perte)
                 VALUES (?,?,?,?,500,?,0,?,?,'perdue',CURRENT_DATE - INTERVAL '60 days',?,500,?,?)",
                [
                    sprintf('DEMO-PERTE-%02d', $i + 1), 'A001', 'A', $type_par_serie['A'],
                    random_int(120, 300), $r = random_int(80, 220), (int)$s['id'], (int)$uid, $r,
                    TAG . ' Bobine égarée lors du transfert inter-sites',
                ]
            );
        }
        step('2 bobines perdues créées');

        // ── 5. Points journaliers + consommation film ────────
        $nb_points = 0; $nb_lignes = 0;
        for ($d = NB_JOURS; $d >= 0; $d--) {
            $date = date('Y-m-d', strtotime("-$d days"));
            if ((int)date('N', strtotime($date)) === 7) continue;   // pas d'activité le dimanche

            foreach ($sites as $si => $s) {
                if (random_int(1, 100) > 92) continue;              // quelques jours sans saisie

                $vp   = random_int(18, 72);
                $cam  = random_int(2, 12);
                $semi = random_int(1, 6);
                $moto = random_int(5, 30);
                $engins = $vp + $cam + $semi + $moto;

                // Théorique = 2 plaques par engin sauf moto (1). L'écart réel oscille de -3 % à +2 %.
                $theo_plq = $vp * 2 + $cam * 2 + $semi * 2 + $moto;
                $plaques  = max(1, (int)round($theo_plq * (1 + random_int(-30, 20) / 1000)));

                $theo_riv = $vp * 4 + $cam * 4 + $semi * 4 + $moto * 2;
                $riv_ok   = max(1, (int)round($theo_riv * (1 + random_int(-20, 25) / 1000)));
                $riv_ko   = (int)round($riv_ok * random_int(8, 42) / 1000);      // 0,8 % à 4,2 %

                $heures   = random_int(70, 95) / 10;                              // 7,0 à 9,5 h
                $np_conc  = random_int(0, 100) > 62 ? random_int(1, 9)  : 0;
                $np_usag  = random_int(0, 100) > 48 ? random_int(1, 14) : 0;

                db_query(
                    "INSERT INTO op_points_journaliers
                       (site_id, date_point, type_point, statut, nb_vp, nb_camion, nb_semi, nb_moto,
                        total_engins, total_plaques, moyenne_prod, rivets_utilises, rivets_endommages,
                        non_poses_concessionnaires, non_poses_usagers, nb_heures_travail,
                        observations, created_by, validated_by, validated_at)
                     VALUES (?,?,'fin_journee','valide',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    [
                        (int)$s['id'], $date, $vp, $cam, $semi, $moto, $engins, $plaques,
                        round($engins / max(0.1, $heures), 2), $riv_ok, $riv_ko,
                        $np_conc, $np_usag, $heures,
                        TAG . ' Journée générée pour la démonstration',
                        (int)$uid, (int)$uid, $date . ' 18:30:00',
                    ]
                );
                $pid = (int)db_fetch_value("SELECT MAX(id) FROM op_points_journaliers");
                $nb_points++;

                // Consommation film ventilée par série
                foreach ([['A', $vp * 2], ['B', $cam * 2], ['C', $semi * 2], ['D', $moto]] as [$serie, $qte]) {
                    if ($qte <= 0) continue;
                    $bid = $bobines[(int)$s['id']][$serie] ?? null;
                    if (!$bid) continue;
                    db_query(
                        "INSERT INTO op_films_utilises
                           (point_id, bobine_id, type_vehicule_id, films_utilises, films_endommages)
                         VALUES (?,?,?,?,?)",
                        [$pid, $bid, $type_par_serie[$serie], $qte, (int)round($qte * random_int(3, 28) / 1000)]
                    );
                    $nb_lignes++;
                }
            }
        }
        step("$nb_points points journaliers et $nb_lignes lignes de consommation générés");

        // ── 6. Écarts d'inventaire ouverts ───────────────────
        $n_ec = 0;
        foreach ($sites as $si => $s) {
            if ($si % 2 !== 0) continue;
            $bid = $bobines[(int)$s['id']]['A'] ?? null;
            if (!$bid) continue;
            $sys = random_int(200, 400);
            $phy = $sys - random_int(4, 26);
            db_query(
                "INSERT INTO ecarts_bobines
                   (bobine_id, date_constat, stock_systeme, stock_physique, ecart, motif,
                    source, statut, created_by)
                 VALUES (?, CURRENT_DATE - INTERVAL '9 days', ?, ?, ?, ?, 'inventaire', 'ouvert', ?)",
                [$bid, $sys, $phy, $phy - $sys, TAG . " Écart constaté lors de l'inventaire mensuel", (int)$uid]
            );
            $n_ec++;
        }
        step("$n_ec écart(s) d'inventaire ouverts créés");

        // ── 7. Réconciliation EMUCI ──────────────────────────
        $n_cmp = 0;
        for ($d = 40; $d >= 0; $d -= 2) {
            $date = date('Y-m-d', strtotime("-$d days"));
            foreach ($sites as $s) {
                $digistock = random_int(120, 420);
                // 78 % alignés, 14 % d'écart mineur (≤ 5), 8 % d'écart majeur
                $tirage = random_int(1, 100);
                $delta  = $tirage <= 78 ? 0 : ($tirage <= 92 ? random_int(-5, 5) : random_int(6, 34) * (random_int(0,1) ? 1 : -1));
                $ajuste = ($tirage > 92 && random_int(1, 100) <= 45) ? 1 : 0;
                db_query(
                    "INSERT INTO comparaisons_stock
                       (site_id, date_comparaison, films_emuci, films_digistock, ajuste, notes_ajustement)
                     VALUES (?,?,?,?,?,?)",
                    [(int)$s['id'], $date, $digistock + $delta, $digistock, $ajuste,
                     TAG . ' Comparaison générée pour la démonstration']
                );
                $n_cmp++;
            }
        }
        step("$n_cmp comparaisons EMUCI générées");
    }
} catch (Throwable $e) {
    oops($e->getMessage());
}

// ── Rendu ────────────────────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="fr"><head><meta charset="utf-8"><title>Seed démo — Performance business</title>
<style>
  body{font-family:'Manrope',system-ui,sans-serif;background:#DDE5DC;color:#191A17;margin:0;padding:40px 20px;line-height:1.6}
  .box{max-width:660px;margin:0 auto;background:#fff;border-radius:26px;padding:32px}
  h1{font-size:24px;letter-spacing:-.02em;margin:0 0 6px}
  p.sub{color:#5D6459;font-size:14px;margin:0 0 24px}
  ul{list-style:none;padding:0;margin:0}
  li{padding:11px 0;border-bottom:1px solid rgba(25,26,23,.1);font-size:14px;display:flex;gap:10px}
  li:last-child{border-bottom:none}
  .ok::before{content:'✓';color:#2F7D22;font-weight:800}
  .ko::before{content:'✕';color:#A32116;font-weight:800}
  .actions{margin-top:26px;display:flex;gap:10px;flex-wrap:wrap}
  a.btn{display:inline-block;padding:11px 20px;border-radius:99px;background:#191A17;color:#F4F5F1;
        text-decoration:none;font-size:13.5px;font-weight:700}
  a.btn.alt{background:transparent;color:#191A17;border:1px solid rgba(25,26,23,.2)}
</style></head><body>
<div class="box">
  <h1><?= $purge ? 'Purge terminée' : 'Jeu de démonstration installé' ?></h1>
  <p class="sub">
    <?= $purge
      ? 'Toutes les lignes marquées [DEMO] ont été retirées. Les données réelles sont intactes.'
      : 'Les lignes créées portent le marqueur [DEMO] et se retirent d\'un clic.' ?>
  </p>
  <ul>
    <?php foreach ($log as $l): ?><li class="ok"><span><?= htmlspecialchars($l) ?></span></li><?php endforeach; ?>
    <?php foreach ($err as $e): ?><li class="ko"><span><?= htmlspecialchars($e) ?></span></li><?php endforeach; ?>
  </ul>
  <div class="actions">
    <a class="btn" href="index.php?page=pdg_overview">Ouvrir la vue PDG</a>
    <?php if (!$purge): ?>
      <a class="btn alt" href="?secret=<?= htmlspecialchars($secret) ?>&amp;purge=1">Retirer le jeu de démo</a>
    <?php endif; ?>
  </div>
</div>
</body></html>
