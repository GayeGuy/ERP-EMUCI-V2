<?php
// ============================================================
//  pages/finance/tarifs.php — Finance : tarifs des dossiers
//  Montant attendu (TTC) par genre de véhicule. Sans tarif, le montant
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
    if (($_POST['action'] ?? '') !== 'enregistrer') json_response(false, 'Action inconnue.');
    $tarifs = json_decode((string)($_POST['tarifs'] ?? '[]'), true);
    if (!is_array($tarifs)) json_response(false, 'Données invalides.');
    session_write_close();
    $changes = [];
    db_begin();
    try {
        foreach ($tarifs as $t) {
            $g = fin_genre($t['genre'] ?? null);
            if (!$g) continue;
            $sp = ($t['champ'] ?? '') === 'speciale';
            $genre = $sp ? fin_tarif_speciale_cle($g) : $g;
            $lib = $sp ? "$g (opération spéciale)" : $g;
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
.fin-utiliser{background:none;border:none;color:#4338ca;cursor:pointer;font-size:12px;padding:0;text-decoration:underline}
</style>

<?php if (!$pret): ?>
  <div class="alert alert-warning">Le module de suivi des dossiers n'est pas encore installé sur cette base (migration finance 01).</div>
<?php else: ?>
<div class="fin-card">
  <p class="aide">Montant attendu (TTC) par genre de véhicule. Le montant réconcilié dans X3 lui est comparé, et tout écart est signalé
    dans le suivi des dossiers. Sans tarif, le montant attendu d'un dossier CIL est celui de l'API (plaques + sécurisation pose) ;
    les dossiers DGTTC, FDS et Reimmat n'ont alors pas de montant attendu.
    Les montants « habituels » sont les plus fréquents dans les données importées.
    Les dossiers de l'opération spéciale (Abobo) ont leur propre tarif ; vide, c'est la valeur par défaut affichée en grisé,
    sinon le tarif normal du genre.</p>
  <div class="table-wrap">
  <table class="fin-table">
    <thead><tr><th>Genre</th><th style="text-align:right">Dossiers</th><th style="text-align:right">Montant API habituel</th>
      <th style="text-align:right">Réconcilié X3 habituel</th><th style="text-align:right">Écarts actuels</th><th>Tarif attendu (TTC)</th><th>Opération spéciale (TTC)</th></tr></thead>
    <tbody>
    <?php if (!$lignes): ?><tr><td colspan="7" style="text-align:center;color:#64748b;padding:24px">Aucun dossier importé pour l'instant.</td></tr><?php endif; ?>
    <?php foreach ($lignes as $l): ?>
      <tr>
        <td><strong><?= h($l['genre']) ?></strong></td>
        <td class="n"><?= number_format((int)$l['nb'], 0, ',', ' ') ?></td>
        <td class="n"><?= $l['api_habituel'] !== null ? number_format((float)$l['api_habituel'], 0, ',', ' ') : '—' ?></td>
        <td class="n"><?php if ($l['x3_habituel'] !== null): ?><?= number_format((float)$l['x3_habituel'], 0, ',', ' ') ?>
          <?php if ($peut_modifier): ?><br><button type="button" class="fin-utiliser" data-v="<?= (int)$l['x3_habituel'] ?>">utiliser</button><?php endif; ?>
          <?php else: ?>—<?php endif; ?></td>
        <td class="n"><?= number_format((int)$l['nb_ecarts'], 0, ',', ' ') ?></td>
        <td><input type="text" inputmode="numeric" class="fin-tarif" data-genre="<?= h($l['genre']) ?>"
                   data-init="<?= $l['montant_ttc'] !== null ? (int)$l['montant_ttc'] : '' ?>"
                   value="<?= $l['montant_ttc'] !== null ? (int)$l['montant_ttc'] : '' ?>" placeholder="API" aria-label="Tarif attendu pour <?= h($l['genre']) ?>"
                   <?= $peut_modifier ? '' : 'disabled' ?>></td>
        <td><input type="text" inputmode="numeric" class="fin-tarif" data-champ="speciale" data-genre="<?= h($l['genre']) ?>"
                   data-init="<?= $l['montant_speciale'] !== null ? (int)$l['montant_speciale'] : '' ?>"
                   value="<?= $l['montant_speciale'] !== null ? (int)$l['montant_speciale'] : '' ?>"
                   placeholder="<?= $l['defaut_speciale'] !== null ? (int)$l['defaut_speciale'] : 'normal' ?>"
                   aria-label="Tarif opération spéciale pour <?= h($l['genre']) ?>" <?= $peut_modifier ? '' : 'disabled' ?>>
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
