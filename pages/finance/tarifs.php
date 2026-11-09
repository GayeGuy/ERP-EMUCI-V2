<?php
// ============================================================
//  pages/finance/tarifs.php — Finance : tarifs des dossiers
//  Montant attendu (TTC) par catégorie de véhicule (Moto, Auto, Semi-remorque). Sans tarif, le montant
//  attendu d'un dossier CIL est celui de l'API (plaques + sécurisation pose).
//  Le montant réconcilié dans X3 est comparé au montant attendu : tout écart
//  est signalé dans le suivi des dossiers.
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/finance_dossiers.php';

require_auth();
$user = current_user();
require_permission('finance_dossiers', 'can_read');
$_SESSION['groupe_actif'] = 'FINANCE';
$page_title  = 'Tarifs dossiers';
$active_page = 'finance_tarifs';
$pret = fin_module_pret();
$peut_modifier = can('finance_dossiers', 'can_update');

if (is_ajax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!$pret) json_response(false, 'Module non installé.');
    if (!$peut_modifier) json_response(false, 'Action réservée.');
    $action = (string)($_POST['action'] ?? '');
    if (preg_match('/^(site|service|formule)_/', $action)) {
        if (!fin_services_pret()) json_response(false, 'Services non installés (migration finance 02).');
        session_write_close();
        try {
            [$msg, $recalcul] = fin_tarifs_action_services($action, $_POST, (int)$user['id']);
        } catch (Throwable $e) {
            if (get_db()->inTransaction()) db_rollback();
            json_response(false, $e instanceof RuntimeException ? $e->getMessage() : 'Enregistrement impossible.');
        }
        if ($recalcul) {
            try { fin_consolider(); } catch (Throwable $e) { json_response(false, "$msg Recalcul impossible, relancez « Recalculer le suivi »."); }
            $msg .= ' Montants attendus recalculés.';
        }
        json_response(true, $msg);
    }
    if ($action !== 'enregistrer') json_response(false, 'Action inconnue.');
    $tarifs = json_decode((string)($_POST['tarifs'] ?? '[]'), true);
    if (!is_array($tarifs)) json_response(false, 'Données invalides.');
    session_write_close();
    $changes = [];
    db_begin();
    try {
        foreach ($tarifs as $t) {
            $g = (string)($t['genre'] ?? '');
            if (!isset(FIN_CATEGORIES[$g])) continue;
            $sp = ($t['champ'] ?? '') === 'speciale';
            $genre = $sp ? fin_tarif_speciale_cle($g) : $g;
            $lib = FIN_CATEGORIES[$g] . ($sp ? ' (opération spéciale)' : '');
            $brut = trim((string)($t['montant'] ?? ''));
            if ($brut === '') {
                if (db_query("DELETE FROM fin_tarifs WHERE genre = ?", [$genre])->rowCount()) $changes[] = "$lib : tarif retiré";
                continue;
            }
            $m = fin_entier($brut);
            if ($m === null || $m < 0 || $m > 10000000) throw new RuntimeException("Montant invalide pour $lib.");
            $avant = db_fetch_value("SELECT montant_ttc FROM fin_tarifs WHERE genre = ?", [$genre]);
            if ($avant !== null && (int)$avant === $m) continue;
            db_query("INSERT INTO fin_tarifs (genre, montant_ttc, maj_par, maj_le) VALUES (?,?,?,CURRENT_TIMESTAMP)
                      ON CONFLICT (genre) DO UPDATE SET montant_ttc = EXCLUDED.montant_ttc, maj_par = EXCLUDED.maj_par, maj_le = CURRENT_TIMESTAMP",
                     [$genre, $m, (int)$user['id']]);
            $changes[] = "$lib : " . ($avant === null ? 'aucun' : number_format((int)$avant, 0, ',', ' ')) . ' → ' . number_format($m, 0, ',', ' ');
        }
        db_commit();
    } catch (Throwable $e) {
        db_rollback();
        json_response(false, $e->getMessage());
    }
    if (!$changes) json_response(true, 'Aucun changement.');
    foreach ($changes as $c) audit_log((int)$user['id'], 'UPDATE', 'finance_dossiers', null, 'Tarif dossiers — ' . $c);
    try { fin_consolider(); } catch (Throwable $e) { json_response(false, 'Tarifs enregistrés, mais recalcul impossible : ' . $e->getMessage()); }
    json_response(true, count($changes) . ' tarif(s) enregistré(s), écarts recalculés.');
}

$lignes = $pret ? fin_tarifs_observes() : [];
$avec_services = $pret && fin_services_pret();
$services = $avec_services ? fin_services_liste() : [];
$formules = $avec_services ? fin_formules_liste() : [];
$sites    = $avec_services ? fin_sites_liste() : [];
$codes_site = $avec_services ? fin_codes_site() : [];
include __DIR__ . '/../../templates/header.php';
?>
<style>
.fin-card{background:white;border:1px solid var(--border);border-radius:16px;padding:18px 20px}
.fin-card p.aide{margin:0 0 14px;font-size:12.5px;color:#64748b;line-height:1.55;max-width:75ch}
.fin-table{width:100%;border-collapse:collapse;font-size:13px}
.fin-table td{padding:9px 10px;border-bottom:1px solid var(--border);vertical-align:middle}
.fin-table td.n{text-align:right;font-variant-numeric:tabular-nums}
.fin-table input{width:130px;padding:7px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;text-align:right;font-family:inherit}
.fin-table input.modifie{border-color:#6366f1;background:#eef2ff}
.fin-entete{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:6px}
.fin-entete h3,.fin-modal h3{margin:0;font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:var(--navy)}
.fin-table .srv-lib{width:200px;text-align:left}
.fin-table .srv-prix{width:100px}
.fin-table input:disabled{background:#f8fafc;color:#94a3b8}
.fin-table input[type=checkbox]{width:auto}
.fin-note{font-size:11.5px;color:#64748b}
.fin-crit{font-size:12px;line-height:1.6}.fin-crit span{color:#64748b;display:inline-block;min-width:74px}
.fin-actions{white-space:nowrap;text-align:right}
tr.fin-inactif td{opacity:.55}
.fin-modal-bg{display:none;position:fixed;inset:0;background:rgba(6,3,58,.45);z-index:2000;align-items:flex-start;justify-content:center;padding:24px 16px;overflow-y:auto}
.fin-modal-bg.open{display:flex}
.fin-modal{background:white;border-radius:16px;padding:24px;width:100%;max-width:640px;box-shadow:0 20px 60px rgba(0,0,0,.2);display:flex;flex-direction:column;gap:14px}
.fin-modal fieldset{border:1px solid var(--border);border-radius:10px;padding:10px 14px;margin:0}
.fin-modal legend{font-size:12.5px;font-weight:700;color:var(--navy);padding:0 4px}.fin-modal legend small,.fin-choix small{font-weight:400;color:#64748b}
.fin-choix{display:flex;flex-wrap:wrap;gap:6px 18px;font-size:13px;max-height:180px;overflow-y:auto}
.fin-choix label{display:flex;align-items:center;gap:6px;cursor:pointer}
.fin-champ{display:flex;flex-direction:column;gap:5px;font-size:12.5px;font-weight:700;color:var(--navy)}
.fin-champ input[type=text]{padding:8px 11px;border:1.5px solid var(--border);border-radius:8px;font-size:13.5px;font-family:inherit;font-weight:400}
.fin-champ.fin-ligne{flex-direction:row;align-items:center;font-weight:400}
.fin-pied{display:flex;gap:10px;align-items:center}
.fin-utiliser{background:none;border:none;color:#4338ca;cursor:pointer;font-size:12px;padding:0;text-decoration:underline}
</style>

<?php if (!$pret): ?>
  <div class="alert alert-warning">Le module de suivi des dossiers n'est pas encore installé sur cette base (migration finance 01).</div>
<?php else: ?>
<div class="fin-card">
  <p class="aide">Montant attendu (TTC) par catégorie de véhicule, pour les dossiers sans formule de services (voir plus bas). Le montant réconcilié dans X3 lui est comparé, et tout écart est signalé
    dans le suivi des dossiers. Sans tarif, le montant attendu d'un dossier CIL est celui de l'API (plaques + sécurisation pose) ;
    les dossiers DGTTC, FDS et Reimmat n'ont alors pas de montant attendu.
    Les montants « habituels » sont les plus fréquents dans les données importées.
    Les dossiers de l'opération spéciale (Abobo) ont leur propre tarif ; vide, c'est la valeur par défaut affichée en grisé,
    sinon le tarif normal de la catégorie.</p>
  <div class="table-wrap">
  <table class="fin-table">
    <thead><tr><th>Catégorie</th><th style="text-align:right">Dossiers</th><th style="text-align:right">Montant API habituel</th>
      <th style="text-align:right">Réconcilié X3 habituel</th><th style="text-align:right">Écarts actuels</th><th>Tarif attendu (TTC)</th><th>Opération spéciale (TTC)</th></tr></thead>
    <tbody>
    <?php if (!$lignes): ?><tr><td colspan="7" style="text-align:center;color:#64748b;padding:24px">Aucun dossier importé pour l'instant.</td></tr><?php endif; ?>
    <?php foreach ($lignes as $l): ?>
      <tr>
        <td><strong><?= h(FIN_CATEGORIES[$l['genre']]) ?></strong></td>
        <td class="n"><?= number_format((int)$l['nb'], 0, ',', ' ') ?></td>
        <td class="n"><?= $l['api_habituel'] !== null ? number_format((float)$l['api_habituel'], 0, ',', ' ') : '—' ?></td>
        <td class="n"><?php if ($l['x3_habituel'] !== null): ?><?= number_format((float)$l['x3_habituel'], 0, ',', ' ') ?>
          <?php if ($peut_modifier): ?><br><button type="button" class="fin-utiliser" data-v="<?= (int)$l['x3_habituel'] ?>">utiliser</button><?php endif; ?>
          <?php else: ?>—<?php endif; ?></td>
        <td class="n"><?= number_format((int)$l['nb_ecarts'], 0, ',', ' ') ?></td>
        <td><input type="text" inputmode="numeric" class="fin-tarif" data-genre="<?= h($l['genre']) ?>"
                   data-init="<?= $l['montant_ttc'] !== null ? (int)$l['montant_ttc'] : '' ?>"
                   value="<?= $l['montant_ttc'] !== null ? (int)$l['montant_ttc'] : '' ?>" placeholder="API" aria-label="Tarif attendu pour <?= h(FIN_CATEGORIES[$l['genre']]) ?>"
                   <?= $peut_modifier ? '' : 'disabled' ?>></td>
        <td><input type="text" inputmode="numeric" class="fin-tarif" data-champ="speciale" data-genre="<?= h($l['genre']) ?>"
                   data-init="<?= $l['montant_speciale'] !== null ? (int)$l['montant_speciale'] : '' ?>"
                   value="<?= $l['montant_speciale'] !== null ? (int)$l['montant_speciale'] : '' ?>"
                   placeholder="<?= $l['defaut_speciale'] !== null ? (int)$l['defaut_speciale'] : 'normal' ?>"
                   aria-label="Tarif opération spéciale pour <?= h(FIN_CATEGORIES[$l['genre']]) ?>" <?= $peut_modifier ? '' : 'disabled' ?>>
          <?php if ((int)$l['nb_speciale'] > 0): ?><div style="font-size:11.5px;color:#64748b;margin-top:3px"><?= number_format((int)$l['nb_speciale'], 0, ',', ' ') ?> dossier(s)</div><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if ($peut_modifier && $lignes): ?>
  <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px">
    <button type="button" class="btn btn-primary" id="fin-enregistrer">Enregistrer les tarifs</button>
  </div>
  <?php endif; ?>
</div>

<?php if (!$avec_services): ?>
  <div class="alert alert-warning" style="margin-top:16px">Les services et formules ne sont pas encore installés sur cette base (migration finance 02).</div>
<?php else: ?>
<div class="fin-card" style="margin-top:16px">
  <div class="fin-entete"><h3>Sites</h3>
    <?php if ($peut_modifier): ?><button type="button" class="btn btn-secondary btn-sm" id="site-ajouter">Nouveau site</button><?php endif; ?></div>
  <p class="aide">Nom libre, rattaché à un ou plusieurs codes reconnus dans les données : préfixe du chrono (BKE, ABJ, KGO…) ou,
    sans chrono, site d'enrôlement du CGI. Un code n'appartient qu'à un site.</p>
  <div class="table-wrap">
  <table class="fin-table">
    <thead><tr><th>Site</th><th>Codes rattachés</th><th style="text-align:right">Dossiers</th><th style="text-align:right">Formules</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($sites as $s): ?>
      <tr>
        <td><strong><?= h($s['nom']) ?></strong></td>
        <td><?= h(implode(', ', $s['codes'])) ?></td>
        <td class="n"><?= number_format((int)$s['nb_dossiers'], 0, ',', ' ') ?></td>
        <td class="n"><?= (int)$s['nb_formules'] ?></td>
        <td class="fin-actions"><?php if ($peut_modifier): ?><button type="button" class="btn btn-secondary btn-sm site-modif" data-id="<?= (int)$s['id'] ?>">Modifier</button><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$sites): ?><tr><td colspan="5" style="text-align:center;color:#64748b;padding:20px">Aucun site nommé.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
  <?php $libres = array_filter($codes_site, fn($c) => $c['site_id'] === null); ?>
  <?php if ($libres): ?><p class="fin-note" style="margin:10px 0 0">Codes sans site : <?= h(implode(', ', array_map(fn($c) => $c['code'] . ' (' . number_format((int)$c['nb'], 0, ',', ' ') . ')', $libres))) ?></p><?php endif; ?>
</div>

<div class="fin-card" style="margin-top:16px">
  <div class="fin-entete"><h3>Services</h3>
    <?php if ($peut_modifier): ?><button type="button" class="btn btn-secondary btn-sm" id="srv-ajouter">Ajouter un service</button><?php endif; ?></div>
  <p class="aide">Prix TTC de chaque service par catégorie. Un service « variable » (reliquat) n'a pas de prix : c'est le complément
    réconcilié au-delà des services fixes de la formule, accepté sans écart.</p>
  <div class="table-wrap">
  <table class="fin-table" id="srv-table">
    <thead><tr><th>Service</th><?php foreach (FIN_CATEGORIES as $libcat): ?><th><?= h($libcat) ?></th><?php endforeach; ?>
      <th>Variable</th><th>Actif</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($services as $s): ?>
      <tr data-id="<?= (int)$s['id'] ?>">
        <td><input type="text" class="srv-lib" value="<?= h($s['libelle']) ?>" maxlength="80" aria-label="Libellé du service" <?= $peut_modifier ? '' : 'disabled' ?>>
          <?php if ((int)$s['nb_formules']): ?><div class="fin-note"><?= (int)$s['nb_formules'] ?> formule(s)</div><?php endif; ?></td>
        <?php foreach (FIN_CATEGORIES as $cat => $libcat): ?>
        <td><input type="text" inputmode="numeric" class="srv-prix" data-cat="<?= h($cat) ?>" value="<?= isset($s['prix'][$cat]) ? (int)$s['prix'][$cat] : '' ?>"
                   aria-label="Prix <?= h($libcat) ?>" <?= $peut_modifier && !$s['variable'] ? '' : 'disabled' ?>></td>
        <?php endforeach; ?>
        <td><input type="checkbox" class="srv-var" <?= $s['variable'] ? 'checked' : '' ?> aria-label="Montant variable" <?= $peut_modifier ? '' : 'disabled' ?>></td>
        <td><input type="checkbox" class="srv-actif" <?= $s['actif'] ? 'checked' : '' ?> aria-label="Service actif" <?= $peut_modifier ? '' : 'disabled' ?>></td>
        <td class="fin-actions"><?php if ($peut_modifier): ?><button type="button" class="btn btn-primary btn-sm srv-ok">Enregistrer</button>
          <button type="button" class="btn btn-danger btn-sm srv-suppr">Supprimer</button><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$services): ?><tr class="srv-vide"><td colspan="<?= count(FIN_CATEGORIES) + 4 ?>" style="text-align:center;color:#64748b;padding:20px">Aucun service. Ajoutez-en un (plaque, sécurisation, pose, service mobile, délocalisée, reliquat…).</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="fin-card" style="margin-top:16px">
  <div class="fin-entete"><h3>Formules</h3>
    <?php if ($peut_modifier): ?><button type="button" class="btn btn-secondary btn-sm" id="frm-ajouter" <?= $services ? '' : 'disabled' ?>>Nouvelle formule</button><?php endif; ?></div>
  <p class="aide">Une formule regroupe les services payés par un dossier. <strong>Par site</strong> : elle s'applique aux dossiers des sites choisis
    (affinée par canal et catégorie). <strong>Par dossier</strong> : elle s'applique selon le canal, le type et la catégorie du dossier, quel que soit
    son site. Un critère vide vaut pour tous. Une formule par site l'emporte sur une formule par dossier, puis la plus précise.
    Montant attendu = somme des services fixes. Un dossier sans formule garde le tarif par catégorie ci-dessus.</p>
  <div class="table-wrap">
  <table class="fin-table">
    <thead><tr><th>Formule</th><th>Critères</th><th>Services</th>
      <?php foreach (FIN_CATEGORIES as $libcat): ?><th style="text-align:right"><?= h($libcat) ?></th><?php endforeach; ?>
      <th style="text-align:right">Dossiers</th><th></th></tr></thead>
    <tbody>
    <?php $nom_srv = array_column($services, 'libelle', 'id'); $nom_site = array_column($sites, 'nom', 'id'); ?>
    <?php foreach ($formules as $f): ?>
      <tr class="<?= $f['actif'] ? '' : 'fin-inactif' ?>">
        <td><strong><?= h($f['libelle']) ?></strong><?= $f['actif'] ? '' : '<div class="fin-note">inactive</div>' ?></td>
        <td class="fin-crit">
          <div><span>Mode</span> <?= h(FIN_MODES_FORMULE[$f['mode']] ?? $f['mode']) ?></div>
          <?php if ($f['mode'] === 'site'): ?>
          <div><span>Sites</span> <?= $f['site_ids'] ? h(implode(', ', array_map(fn($i) => $nom_site[$i] ?? '?', $f['site_ids']))) : '<em>aucun : formule sans effet</em>' ?></div>
          <?php else: ?>
          <div><span>Types</span> <?= $f['types'] ? h(implode(', ', $f['types'])) : 'tous' ?></div>
          <?php endif; ?>
          <div><span>Canaux</span> <?= $f['canaux'] ? h(implode(', ', array_map(fn($c) => FIN_CANAUX[$c] ?? $c, $f['canaux']))) : 'tous' ?></div>
          <div><span>Catégories</span> <?= $f['categories'] ? h(implode(', ', array_map(fn($c) => FIN_CATEGORIES[$c] ?? $c, $f['categories']))) : 'toutes' ?></div></td>
        <td><?= h(implode(' + ', array_map(fn($sid) => $nom_srv[$sid] ?? '?', $f['services']))) ?></td>
        <?php foreach (FIN_CATEGORIES as $cat => $_): ?>
        <td class="n"><?php if ($f['categories'] && !in_array($cat, $f['categories'], true)): ?><span class="fin-note">—</span>
          <?php elseif ($f['montants'][$cat] === null): ?><span class="fin-note" title="Un service fixe n'a pas de prix pour cette catégorie">prix manquant</span>
          <?php else: ?><?= number_format($f['montants'][$cat], 0, ',', ' ') ?><?= $f['variable'] ? ' <span class="fin-note">+ variable</span>' : '' ?><?php endif; ?></td>
        <?php endforeach; ?>
        <td class="n"><?= number_format((int)$f['nb_dossiers'], 0, ',', ' ') ?></td>
        <td class="fin-actions"><?php if ($peut_modifier): ?><button type="button" class="btn btn-secondary btn-sm frm-modif" data-id="<?= (int)$f['id'] ?>">Modifier</button><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$formules): ?><tr><td colspan="<?= count(FIN_CATEGORIES) + 5 ?>" style="text-align:center;color:#64748b;padding:20px">Aucune formule : tous les dossiers suivent le tarif par catégorie.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<?php if ($peut_modifier): ?>
<div class="fin-modal-bg" id="frm-modal">
  <div class="fin-modal" role="dialog" aria-modal="true" aria-labelledby="frm-titre">
    <h3 id="frm-titre">Formule</h3>
    <label class="fin-champ">Libellé<input type="text" id="frm-lib" maxlength="120" placeholder="ex. Bouaké moto"></label>
    <fieldset><legend>Paramétrage</legend><div class="fin-choix">
      <?php foreach (FIN_MODES_FORMULE as $k => $m): ?><label><input type="radio" name="mode" value="<?= h($k) ?>"> <?= h($m) ?></label><?php endforeach; ?>
    </div></fieldset>
    <fieldset data-mode="site"><legend>Sites <small>(au moins un)</small></legend><div class="fin-choix">
      <?php foreach ($sites as $s): ?><label><input type="checkbox" name="sites" value="<?= (int)$s['id'] ?>"> <?= h($s['nom']) ?></label><?php endforeach; ?>
      <?php if (!$sites): ?><span class="fin-note">Aucun site nommé : créez-en dans la section Sites.</span><?php endif; ?>
    </div></fieldset>
    <fieldset data-mode="dossier"><legend>Types de dossier <small>(aucun coché = tous)</small></legend><div class="fin-choix">
      <?php foreach (FIN_TYPES as $t): ?><label><input type="checkbox" name="types" value="<?= h($t) ?>"> <?= h($t) ?></label><?php endforeach; ?>
    </div></fieldset>
    <fieldset><legend>Canaux <small>(aucun coché = tous)</small></legend><div class="fin-choix">
      <?php foreach (FIN_CANAUX as $k => $c): ?><label><input type="checkbox" name="canaux" value="<?= h($k) ?>"> <?= h($c) ?></label><?php endforeach; ?>
    </div></fieldset>
    <fieldset><legend>Catégories <small>(aucune cochée = toutes)</small></legend><div class="fin-choix">
      <?php foreach (FIN_CATEGORIES as $k => $c): ?><label><input type="checkbox" name="categories" value="<?= h($k) ?>"> <?= h($c) ?></label><?php endforeach; ?>
    </div></fieldset>
    <fieldset><legend>Services payés</legend><div class="fin-choix">
      <?php foreach ($services as $s): ?><label><input type="checkbox" name="services" value="<?= (int)$s['id'] ?>"> <?= h($s['libelle']) ?><?= $s['variable'] ? ' <small>variable</small>' : '' ?><?= $s['actif'] ? '' : ' <small>inactif</small>' ?></label><?php endforeach; ?>
    </div></fieldset>
    <label class="fin-champ fin-ligne"><input type="checkbox" id="frm-actif" checked> Formule active</label>
    <div class="fin-pied">
      <button type="button" class="btn btn-danger btn-sm" id="frm-suppr">Supprimer</button>
      <span style="flex:1"></span>
      <button type="button" class="btn btn-secondary" id="frm-annuler">Annuler</button>
      <button type="button" class="btn btn-primary" id="frm-ok">Enregistrer</button>
    </div>
  </div>
</div>
<div class="fin-modal-bg" id="site-modal">
  <div class="fin-modal" role="dialog" aria-modal="true" aria-labelledby="site-titre">
    <h3 id="site-titre">Site</h3>
    <label class="fin-champ">Nom du site<input type="text" id="site-nom" maxlength="120" placeholder="ex. Bouaké"></label>
    <fieldset><legend>Codes rattachés <small>(nombre de dossiers ; un code déjà pris sera déplacé)</small></legend><div class="fin-choix">
      <?php foreach ($codes_site as $c): ?><label><input type="checkbox" name="codes" value="<?= h($c['code']) ?>"> <?= h($c['code']) ?>
        <small><?= number_format((int)$c['nb'], 0, ',', ' ') ?><?= $c['site_id'] !== null ? ' · ' . h($nom_site[$c['site_id']] ?? '') : '' ?></small></label><?php endforeach; ?>
    </div></fieldset>
    <div class="fin-pied">
      <button type="button" class="btn btn-danger btn-sm" id="site-suppr">Supprimer</button>
      <span style="flex:1"></span>
      <button type="button" class="btn btn-secondary fin-annuler">Annuler</button>
      <button type="button" class="btn btn-primary" id="site-ok">Enregistrer</button>
    </div>
  </div>
</div>
<?php endif; ?>
<script>
const FIN_FORMULES = <?= json_encode(array_map(fn($f) => ['id' => (int)$f['id'], 'libelle' => $f['libelle'], 'actif' => (bool)$f['actif'],
    'mode' => $f['mode'], 'sites' => $f['site_ids'], 'types' => $f['types'], 'canaux' => $f['canaux'], 'categories' => $f['categories'],
    'services' => $f['services']], $formules), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
const FIN_SITES = <?= json_encode(array_map(fn($s) => ['id' => (int)$s['id'], 'nom' => $s['nom'], 'codes' => $s['codes']], $sites),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
async function finPost(data, bouton) {
  const fd = new FormData(); Object.entries(data).forEach(([k, v]) => fd.append(k, v));
  const texte = bouton ? bouton.textContent : '';
  if (bouton) { bouton.disabled = true; bouton.textContent = 'Enregistrement…'; }
  try {
    const r = await fetch('tarifs.php', {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: fd}).then(achParseJson);
    toast(r.message, r.success ? 'success' : 'danger');
    if (r.success) { setTimeout(() => location.reload(), 900); return; }
  } catch (e) { toast(e.message, 'danger'); }
  if (bouton) { bouton.disabled = false; bouton.textContent = texte; }
}
const srvTable = document.getElementById('srv-table');
function srvLier(tr) {
  const v = tr.querySelector('.srv-var');
  if (v) v.addEventListener('change', () => tr.querySelectorAll('.srv-prix').forEach(i => { i.disabled = v.checked; if (v.checked) i.value = ''; }));
  const ok = tr.querySelector('.srv-ok');
  if (ok) ok.addEventListener('click', () => {
    const prix = {}; tr.querySelectorAll('.srv-prix').forEach(i => prix[i.dataset.cat] = i.value.replace(/\s/g, ''));
    finPost({action: 'service_enregistrer', id: tr.dataset.id || 0, libelle: tr.querySelector('.srv-lib').value,
             variable: v.checked ? 1 : 0, actif: tr.querySelector('.srv-actif').checked ? 1 : 0, prix: JSON.stringify(prix)}, ok);
  });
  const sup = tr.querySelector('.srv-suppr');
  if (sup) sup.addEventListener('click', () => {
    if (!tr.dataset.id) { tr.remove(); return; }
    const lib = tr.querySelector('.srv-lib').value;
    if (confirm(`Supprimer le service « ${lib} » ? Il sera retiré des formules qui l'utilisent.`)) finPost({action: 'service_supprimer', id: tr.dataset.id}, sup);
  });
}
if (srvTable) srvTable.querySelectorAll('tbody tr[data-id]').forEach(srvLier);
const srvAjout = document.getElementById('srv-ajouter');
if (srvAjout) srvAjout.addEventListener('click', () => {
  const vide = srvTable.querySelector('.srv-vide'); if (vide) vide.remove();
  const tr = document.createElement('tr'); tr.dataset.id = '';
  tr.innerHTML = `<td><input type="text" class="srv-lib" maxlength="80" placeholder="ex. Service mobile" aria-label="Libellé du service"></td>`
    + <?= json_encode(array_keys(FIN_CATEGORIES)) ?>.map(c => `<td><input type="text" inputmode="numeric" class="srv-prix" data-cat="${c}" aria-label="Prix ${c}"></td>`).join('')
    + `<td><input type="checkbox" class="srv-var" aria-label="Montant variable"></td><td><input type="checkbox" class="srv-actif" checked aria-label="Service actif"></td>`
    + `<td class="fin-actions"><button type="button" class="btn btn-primary btn-sm srv-ok">Enregistrer</button> <button type="button" class="btn btn-danger btn-sm srv-suppr">Retirer</button></td>`;
  srvTable.querySelector('tbody').appendChild(tr); srvLier(tr); tr.querySelector('.srv-lib').focus();
});
const frmModal = document.getElementById('frm-modal');
let frmId = 0;
function frmOuvrir(f) {
  frmId = f ? f.id : 0;
  document.getElementById('frm-titre').textContent = f ? 'Modifier la formule' : 'Nouvelle formule';
  document.getElementById('frm-lib').value = f ? f.libelle : '';
  document.getElementById('frm-actif').checked = f ? f.actif : true;
  document.getElementById('frm-suppr').style.display = f ? '' : 'none';
  ['sites', 'types', 'canaux', 'categories', 'services'].forEach(n => frmModal.querySelectorAll(`input[name="${n}"]`)
    .forEach(i => i.checked = !!f && f[n].map(String).includes(i.value)));
  const mode = f ? f.mode : (FIN_SITES.length ? 'site' : 'dossier');
  frmModal.querySelectorAll('input[name="mode"]').forEach(i => i.checked = i.value === mode);
  frmMode();
  frmModal.classList.add('open'); document.getElementById('frm-lib').focus();
}
// Sites visibles en mode « par site », types en mode « par dossier ».
function frmMode() {
  const m = (frmModal.querySelector('input[name="mode"]:checked') || {}).value;
  frmModal.querySelectorAll('fieldset[data-mode]').forEach(fs => fs.hidden = fs.dataset.mode !== m);
}
function finModalLier(modal) {
  const fermer = () => modal.classList.remove('open');
  modal.querySelectorAll('.fin-annuler, #frm-annuler').forEach(b => b.addEventListener('click', fermer));
  modal.addEventListener('click', e => { if (e.target === modal) fermer(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('open')) fermer(); });
}
const coches = (modal, n) => JSON.stringify([...modal.querySelectorAll(`input[name="${n}"]:checked`)].map(i => i.value));
if (frmModal) {
  finModalLier(frmModal);
  frmModal.querySelectorAll('input[name="mode"]').forEach(i => i.addEventListener('change', frmMode));
  document.getElementById('frm-ajouter').addEventListener('click', () => frmOuvrir(null));
  document.querySelectorAll('.frm-modif').forEach(b => b.addEventListener('click', () => frmOuvrir(FIN_FORMULES.find(f => f.id === Number(b.dataset.id)))));
  document.getElementById('frm-ok').addEventListener('click', e => {
    const mode = (frmModal.querySelector('input[name="mode"]:checked') || {}).value || 'site';
    finPost({action: 'formule_enregistrer', id: frmId, libelle: document.getElementById('frm-lib').value, mode,
      sites: mode === 'site' ? coches(frmModal, 'sites') : '[]', types: mode === 'dossier' ? coches(frmModal, 'types') : '[]',
      canaux: coches(frmModal, 'canaux'), categories: coches(frmModal, 'categories'), services: coches(frmModal, 'services'),
      actif: document.getElementById('frm-actif').checked ? 1 : 0}, e.currentTarget);
  });
  document.getElementById('frm-suppr').addEventListener('click', e => {
    if (confirm('Supprimer cette formule ? Ses dossiers reviendront au tarif par catégorie.')) finPost({action: 'formule_supprimer', id: frmId}, e.currentTarget);
  });
}
const siteModal = document.getElementById('site-modal');
let siteId = 0;
function siteOuvrir(s) {
  siteId = s ? s.id : 0;
  document.getElementById('site-titre').textContent = s ? 'Modifier le site' : 'Nouveau site';
  document.getElementById('site-nom').value = s ? s.nom : '';
  document.getElementById('site-suppr').style.display = s ? '' : 'none';
  siteModal.querySelectorAll('input[name="codes"]').forEach(i => i.checked = !!s && s.codes.includes(i.value));
  siteModal.classList.add('open'); document.getElementById('site-nom').focus();
}
if (siteModal) {
  finModalLier(siteModal);
  document.getElementById('site-ajouter').addEventListener('click', () => siteOuvrir(null));
  document.querySelectorAll('.site-modif').forEach(b => b.addEventListener('click', () => siteOuvrir(FIN_SITES.find(s => s.id === Number(b.dataset.id)))));
  document.getElementById('site-ok').addEventListener('click', e => finPost({action: 'site_enregistrer', id: siteId,
    nom: document.getElementById('site-nom').value, codes: coches(siteModal, 'codes')}, e.currentTarget));
  document.getElementById('site-suppr').addEventListener('click', e => {
    if (confirm('Supprimer ce site ? Il sera retiré des formules par site qui l\'utilisent.')) finPost({action: 'site_supprimer', id: siteId}, e.currentTarget);
  });
}
</script>
<?php endif; ?>

<script>
document.querySelectorAll('.fin-tarif').forEach(i => i.addEventListener('input', () => i.classList.toggle('modifie', i.value.trim() !== i.dataset.init)));
document.querySelectorAll('.fin-utiliser').forEach(b => b.addEventListener('click', () => {
  const i = b.closest('tr').querySelector('.fin-tarif:not([data-champ])'); i.value = b.dataset.v; i.dispatchEvent(new Event('input'));
}));
const btn = document.getElementById('fin-enregistrer');
if (btn) btn.addEventListener('click', async () => {
  const tarifs = [...document.querySelectorAll('.fin-tarif')].filter(i => i.value.trim() !== i.dataset.init)
    .map(i => ({genre: i.dataset.genre, champ: i.dataset.champ || 'normal', montant: i.value.replace(/\s/g, '')}));
  if (!tarifs.length) { toast('Aucun changement.', 'info'); return; }
  btn.disabled = true; btn.textContent = 'Enregistrement et recalcul…';
  const fd = new FormData(); fd.append('action', 'enregistrer'); fd.append('tarifs', JSON.stringify(tarifs));
  try {
    const r = await fetch('tarifs.php', {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: fd}).then(achParseJson);
    toast(r.message, r.success ? 'success' : 'danger');
    if (r.success) setTimeout(() => location.reload(), 900);
  } catch (e) { toast(e.message, 'danger'); }
  btn.disabled = false; btn.textContent = 'Enregistrer les tarifs';
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
