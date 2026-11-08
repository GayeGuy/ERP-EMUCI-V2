<?php
// ============================================================
//  pages/finance/imports.php — Finance : imports des dossiers
//  Dépôt des exports (Brique, CGI, X3, Caisse, OptoPlate), reconnus à leurs
//  colonnes, et synchronisation de l'API CIL. Réimporter un fichier met les
//  lignes à jour sans doublon ; chaque import est suivi d'une consolidation
//  (fin_consolider) et laisse un bilan dans le journal.
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/finance_dossiers.php';

require_auth();
$user = current_user();
require_permission('finance_dossiers', 'can_create');
$_SESSION['groupe_actif'] = 'FINANCE';
$page_title  = 'Imports dossiers';
$active_page = 'finance_import';
$pret = fin_module_pret();

// ── AJAX ────────────────────────────────────────────────────
if (is_ajax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!$pret) json_response(false, 'Module non installé : la migration finance 01 n\'a pas été passée.');
    $action = $_POST['action'] ?? '';
    session_write_close();   // imports longs : ne pas bloquer les autres onglets
    @set_time_limit(300);

    if ($action === 'importer') {
        $fic = $_FILES['fichier'] ?? null;
        if (!$fic || ($fic['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $err = $fic['error'] ?? UPLOAD_ERR_NO_FILE;
            json_response(false, in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'Fichier trop volumineux.' : 'Aucun fichier reçu.');
        }
        $nom = basename((string)$fic['name']);
        try {
            $b = fin_importer_fichier($fic['tmp_name'], $nom, (int)$user['id']);
            $b['consolidation'] = fin_consolider();
        } catch (Throwable $e) {
            json_response(false, $nom . ' : ' . $e->getMessage());
        }
        audit_log((int)$user['id'], 'CREATE', 'finance_dossiers', $b['import_id'],
            "Import {$b['libelle']} « $nom » : {$b['ajoutees']} ajoutée(s), {$b['maj']} mise(s) à jour, {$b['rejetees']} rejetée(s)");
        json_response(true, '', $b);
    }

    if ($action === 'sync') {
        $du = trim($_POST['du'] ?? '');
        $au = trim($_POST['au'] ?? '');
        try {
            $b = fin_sync_cil($du, $au, (int)$user['id']);
            if (($_POST['consolider'] ?? '1') === '1') $b['consolidation'] = fin_consolider();
        } catch (Throwable $e) {
            json_response(false, $e->getMessage());
        }
        audit_log((int)$user['id'], 'CREATE', 'finance_dossiers', $b['import_id'],
            "Synchronisation API CIL du $du au $au : {$b['ajoutees']} ajouté(s), {$b['maj']} mis à jour");
        json_response(true, '', $b);
    }

    if ($action === 'consolider') {
        try { $c = fin_consolider(); }
        catch (Throwable $e) { json_response(false, $e->getMessage()); }
        if (($_POST['manuel'] ?? '') === '1')
            audit_log((int)$user['id'], 'UPDATE', 'finance_dossiers', null,
                "Recalcul du suivi : {$c['dossiers']} dossier(s), {$c['modifies']} modifié(s)");
        json_response(true, '', $c);
    }
    json_response(false, 'Action inconnue.');
}

$journal = $pret ? db_fetch_all(
    "SELECT i.*, CONCAT(u.prenom, ' ', u.nom) AS par FROM fin_imports i
     LEFT JOIN users u ON u.id = i.importe_par ORDER BY i.id DESC LIMIT 40") : [];
$couverture = $pret ? fin_couverture() : [];
$derniere   = $pret ? fin_derniere_sync() : null;
$max_paiement = $pret ? db_fetch_value("SELECT MAX(date_paiement) FROM fin_src_cil") : null;

include __DIR__ . '/../../templates/header.php';
?>
<style>
.fin-cols{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px}
.fin-card{background:white;border:1px solid var(--border);border-radius:16px;padding:18px 20px}
.fin-card h3{margin:0 0 4px;font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;font-weight:800;color:var(--navy)}
.fin-card p.aide{margin:0 0 14px;font-size:12.5px;color:#64748b;line-height:1.5}
.fin-drop{display:block;margin:0;border:2px dashed #cbd5e1;border-radius:14px;padding:26px 16px;text-align:center;color:#475569;font-size:13px;cursor:pointer;transition:background .15s,border-color .15s}
.fin-drop.survol{background:#eef2ff;border-color:#6366f1}
.fin-drop strong{color:var(--navy)}
.fin-fg{display:flex;flex-direction:column;gap:4px}
.fin-fg label{font-size:12px;font-weight:700;color:#374151}
.fin-fg input{padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-family:inherit}
.fin-ligne{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap}
.fin-bilan{border:1px solid var(--border);border-radius:12px;padding:10px 12px;margin-top:10px;font-size:12.5px;background:#f8fafc}
.fin-bilan.ok{background:#f0fdf4;border-color:#bbf7d0}
.fin-bilan.ko{background:#fef2f2;border-color:#fecaca;color:#991b1b}
.fin-bilan .t{font-weight:800;color:var(--navy);margin-bottom:3px}
.fin-chiffres{display:flex;gap:14px;flex-wrap:wrap}
.fin-chiffres b{font-family:'Plus Jakarta Sans',sans-serif}
.fin-barre{height:8px;border-radius:4px;background:#e2e8f0;overflow:hidden;margin-top:10px;display:none}
.fin-barre div{height:100%;width:0;background:#4f46e5;transition:width .3s}
.fin-table{width:100%;border-collapse:collapse;font-size:12.5px}
.fin-table td{padding:8px 10px;border-bottom:1px solid var(--border);vertical-align:top}
.fin-sub{font-size:11px;color:#64748b}
@media(max-width:900px){.fin-cols{grid-template-columns:1fr}}
</style>

<?php if (!$pret): ?>
  <div class="alert alert-warning">
    Le module de suivi des dossiers n'est pas encore installé sur cette base :
    la migration <code>sql/migration_finance_01_dossiers.sql</code> doit être passée par l'administrateur.
  </div>
<?php else: ?>

<div class="fin-cols">
  <div class="fin-card">
    <h3>Déposer un export</h3>
    <p class="aide">Brique, CGI, X3 (Sage), Caisse ou OptoPlate, en .xlsx ou .csv : la source est reconnue à ses colonnes.
      Réimporter un fichier met les dossiers à jour sans créer de doublon.</p>
    <label class="fin-drop" id="fin-drop" for="fin-fichier">
      <i class="ph ph-upload-simple" style="font-size:26px;display:block;margin-bottom:6px" aria-hidden="true"></i>
      <strong>Choisir un ou plusieurs fichiers</strong><br>ou les glisser ici
    </label>
    <input type="file" id="fin-fichier" accept=".xlsx,.csv" multiple style="display:none">
    <div id="fin-bilans"></div>
  </div>

  <div class="fin-card">
    <h3>API CIL</h3>
    <p class="aide">Paiements des dossiers ouverts chez CIL, par date de paiement.
      Dernière synchronisation : <strong><?= $derniere ? h(fmt_datetime($derniere)) : 'jamais' ?></strong>
      <?= $max_paiement ? '· paiements connus jusqu\'au <strong>' . h(fmt_date($max_paiement)) . '</strong>' : '' ?>.
      L'écran de suivi resynchronise les derniers jours tout seul, au plus toutes les <?= (int)FIN_SYNC_AUTO_HEURES ?> heures.</p>
    <div class="fin-ligne">
      <div class="fin-fg"><label for="s-du">Du</label><input type="date" id="s-du" value="<?= h(date('Y-m-d', strtotime('-7 days'))) ?>"></div>
      <div class="fin-fg"><label for="s-au">Au</label><input type="date" id="s-au" value="<?= h(date('Y-m-d')) ?>"></div>
      <button type="button" class="btn btn-primary" id="s-btn">Synchroniser</button>
    </div>
    <div style="border-top:1px solid var(--border);margin:16px 0 12px"></div>
    <div class="fin-ligne">
      <div class="fin-fg"><label for="h-du">Charger l'historique depuis le</label><input type="date" id="h-du" value="2025-09-01"></div>
      <button type="button" class="btn btn-secondary" id="h-btn">Charger, mois par mois</button>
    </div>
    <div class="fin-barre" id="h-barre"><div></div></div>
    <div id="s-bilans"></div>
  </div>
</div>

<div class="fin-card" style="margin-bottom:18px">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
    <div>
      <h3>Période couverte par chaque source</h3>
      <p class="aide" style="margin:0">Le suivi est recalculé après chaque import. Après une correction faite directement dans la base, relancez-le ici.</p>
    </div>
    <button type="button" class="btn btn-secondary" id="r-btn"><i class="ph ph-arrows-clockwise" aria-hidden="true"></i> Recalculer le suivi</button>
  </div>
  <div id="r-bilan" aria-live="polite"></div>
  <table class="fin-table" style="margin-top:8px">
    <thead><tr><th>Source</th><th>Lignes</th><th>Du</th><th>Au</th><th>Dernier import</th></tr></thead>
    <tbody>
    <?php foreach ($couverture as $src => $c): ?>
      <tr><td><?= h(FIN_SOURCES[$src]) ?></td><td><?= number_format((int)$c['n'], 0, ',', ' ') ?></td>
          <td><?= fmt_date($c['du']) ?></td><td><?= fmt_date($c['au']) ?></td>
          <td><?= $c['dernier'] ? h(fmt_datetime($c['dernier'])) : '—' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="fin-card">
  <h3>Journal des imports</h3>
  <div class="table-wrap">
  <table class="fin-table" style="margin-top:8px">
    <thead><tr><th>Date</th><th>Source</th><th>Fichier / période</th><th>Lignes</th><th>Ajoutées</th><th>Mises à jour</th><th>Inchangées</th><th>Rejetées</th><th>Par</th></tr></thead>
    <tbody>
    <?php if (!$journal): ?><tr><td colspan="9" style="text-align:center;color:#64748b;padding:24px">Aucun import pour l'instant.</td></tr><?php endif; ?>
    <?php foreach ($journal as $j): ?>
      <tr>
        <td><?= h(fmt_datetime($j['cree_le'])) ?><div class="fin-sub"><?= $j['duree_ms'] !== null ? h(number_format($j['duree_ms'] / 1000, 1, ',', ' ')) . ' s' : '' ?></div></td>
        <td><?= h(FIN_SOURCES[$j['source']] ?? $j['source']) ?></td>
        <td><?= $j['fichier'] ? h($j['fichier']) : h(fmt_date($j['periode_du']) . ' → ' . fmt_date($j['periode_au'])) ?></td>
        <td><?= number_format((int)$j['nb_lignes'], 0, ',', ' ') ?></td>
        <td><?= number_format((int)$j['nb_ajoutees'], 0, ',', ' ') ?></td>
        <td><?= number_format((int)$j['nb_maj'], 0, ',', ' ') ?></td>
        <td><?= number_format((int)$j['nb_inchangees'], 0, ',', ' ') ?></td>
        <td><?php if ((int)$j['nb_rejetees']): ?><details><summary><?= (int)$j['nb_rejetees'] ?></summary>
              <div class="fin-sub" style="white-space:pre-line"><?= h((string)$j['details']) ?></div></details><?php else: ?>0<?php endif; ?></td>
        <td><?= h(trim((string)$j['par']) ?: 'Automatique') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<script>
const finEsc = s => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const finNb = n => Number(n || 0).toLocaleString('fr-FR');
function finPost(fd) {
  return fetch('imports.php', {method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: fd}).then(achParseJson);
}
function finBilan(b, titre) {
  const rej = b.rejets && b.rejets.length ? `<details style="margin-top:4px"><summary>${finNb(b.rejetees)} ligne(s) rejetée(s)</summary><div class="fin-sub" style="white-space:pre-line">${finEsc(b.rejets.join('\n'))}</div></details>` : '';
  return `<div class="fin-bilan ok"><div class="t">${finEsc(titre)}</div>
    <div class="fin-chiffres"><span><b>${finNb(b.lignes)}</b> ligne(s)</span><span><b>${finNb(b.ajoutees)}</b> ajoutée(s)</span>
    <span><b>${finNb(b.maj)}</b> mise(s) à jour</span><span><b>${finNb(b.inchangees)}</b> inchangée(s)</span>
    ${b.doublons ? `<span><b>${finNb(b.doublons)}</b> doublon(s) fusionné(s)</span>` : ''}</div>${rej}
    ${b.consolidation ? `<div class="fin-sub" style="margin-top:4px">${finNb(b.consolidation.dossiers)} dossiers recalculés</div>` : ''}</div>`;
}
function finErreur(msg) { return `<div class="fin-bilan ko">${finEsc(msg)}</div>`; }

// ── Dépôt de fichiers, un à la fois (chaque import est suivi d'une consolidation)
const zone = document.getElementById('fin-drop'), entree = document.getElementById('fin-fichier'), bilans = document.getElementById('fin-bilans');
let enCours = false;
async function finImporter(fichiers) {
  if (enCours || !fichiers.length) return;
  enCours = true;
  for (const f of fichiers) {
    const id = 'b' + Math.random().toString(36).slice(2);
    bilans.insertAdjacentHTML('afterbegin', `<div class="fin-bilan" id="${id}"><div class="t">${finEsc(f.name)}</div>Import en cours…</div>`);
    const fd = new FormData(); fd.append('action', 'importer'); fd.append('fichier', f);
    try {
      const r = await finPost(fd);
      document.getElementById(id).outerHTML = r.success ? finBilan(r.data, `${f.name} · ${r.data.libelle}`) : finErreur(r.message);
    } catch (e) {
      document.getElementById(id).outerHTML = finErreur(f.name + ' : ' + e.message);
    }
  }
  enCours = false;
  entree.value = '';
}
entree.addEventListener('change', () => finImporter([...entree.files]));
['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('survol'); }));
['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.remove('survol'); }));
zone.addEventListener('drop', e => finImporter([...e.dataTransfer.files]));

// ── API CIL
const sBilans = document.getElementById('s-bilans');
document.getElementById('s-btn').addEventListener('click', async ev => {
  const b = ev.currentTarget, du = document.getElementById('s-du').value, au = document.getElementById('s-au').value;
  if (!du || !au) return;
  b.disabled = true; b.textContent = 'Synchronisation…';
  const fd = new FormData(); fd.append('action', 'sync'); fd.append('du', du); fd.append('au', au);
  try {
    const r = await finPost(fd);
    sBilans.insertAdjacentHTML('afterbegin', r.success ? finBilan(r.data, `API CIL du ${du.split('-').reverse().join('/')} au ${au.split('-').reverse().join('/')}`) : finErreur(r.message));
  } catch (e) { sBilans.insertAdjacentHTML('afterbegin', finErreur(e.message)); }
  b.disabled = false; b.textContent = 'Synchroniser';
});

// Historique : un mois par appel, consolidation une seule fois à la fin.
document.getElementById('r-btn').addEventListener('click', async ev => {
  const b = ev.currentTarget, zone = document.getElementById('r-bilan');
  b.disabled = true; const lib = b.innerHTML; b.textContent = 'Recalcul en cours…';
  const fd = new FormData(); fd.append('action', 'consolider'); fd.append('manuel', '1');
  let r;
  try { r = await finPost(fd); } catch (e) { r = {success: false, message: e.message}; }
  zone.innerHTML = r.success
    ? `<div class="fin-sub" style="margin-top:8px">${finNb(r.data.dossiers)} dossiers recalculés, ${finNb(r.data.modifies)} modifié(s), ${finNb(r.data.supprimes)} retiré(s), en ${finNb(Math.round(r.data.duree_ms / 100) / 10)} s.</div>`
    : finErreur(r.message);
  b.disabled = false; b.innerHTML = lib;
});

document.getElementById('h-btn').addEventListener('click', async ev => {
  const b = ev.currentTarget, depuis = document.getElementById('h-du').value;
  if (!depuis) return;
  const mois = [];
  const fin = new Date(); fin.setHours(0, 0, 0, 0);
  for (let d = new Date(depuis + 'T00:00:00'); d <= fin; d = new Date(d.getFullYear(), d.getMonth() + 1, 1)) {
    const f = new Date(d.getFullYear(), d.getMonth() + 1, 0);
    const iso = x => `${x.getFullYear()}-${String(x.getMonth() + 1).padStart(2, '0')}-${String(x.getDate()).padStart(2, '0')}`;
    mois.push([iso(d), iso(f > fin ? fin : f)]);
  }
  if (!confirm(`Charger ${mois.length} mois de paiements CIL ? Comptez environ 10 secondes par mois.`)) return;
  b.disabled = true;
  const barre = document.getElementById('h-barre'); barre.style.display = 'block';
  let total = {lignes: 0, ajoutees: 0, maj: 0, inchangees: 0, rejetees: 0, doublons: 0, rejets: []};
  for (let i = 0; i < mois.length; i++) {
    b.textContent = `Mois ${i + 1} / ${mois.length}…`;
    const fd = new FormData(); fd.append('action', 'sync'); fd.append('du', mois[i][0]); fd.append('au', mois[i][1]); fd.append('consolider', '0');
    let r;
    try { r = await finPost(fd); } catch (e) { r = {success: false, message: e.message}; }
    if (!r.success) { sBilans.insertAdjacentHTML('afterbegin', finErreur(`${mois[i][0]} → ${mois[i][1]} : ${r.message}`)); break; }
    ['lignes', 'ajoutees', 'maj', 'inchangees', 'rejetees', 'doublons'].forEach(k => total[k] += r.data[k] || 0);
    barre.firstElementChild.style.width = Math.round(100 * (i + 1) / mois.length) + '%';
  }
  b.textContent = 'Recalcul des dossiers…';
  const fd = new FormData(); fd.append('action', 'consolider');
  try { const c = await finPost(fd); if (c.success) total.consolidation = c.data; } catch (e) {}
  sBilans.insertAdjacentHTML('afterbegin', finBilan(total, `Historique API CIL depuis le ${depuis.split('-').reverse().join('/')}`));
  b.disabled = false; b.textContent = 'Charger, mois par mois';
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
