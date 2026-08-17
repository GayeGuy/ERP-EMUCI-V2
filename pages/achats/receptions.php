<?php
// ============================================================
//  pages/achats/receptions.php — Réception des lignes de suivi Sage
//  Réservé au magasin (gestionnaire_stock) et aux coordinateurs de site.
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/achats.php';
require_once __DIR__ . '/../../includes/upload.php';

require_auth();
$user = current_user();
require_permission('achats_suivi', 'can_read');
$can_receptionner = can('achats_suivi', 'can_create');
$_SESSION['groupe_actif'] = 'ACHATS';
$page_title  = 'Réceptions';
$active_page = 'achats_receptions';

// Filtré sur le site de l'utilisateur, pas sur toute la FEB (point 3) — ne
// s'applique qu'au coordinateur de site ; le magasin (gestionnaire_stock)
// et l'administration voient toutes les réceptions à traiter.
$role_slug  = $user['role_slug'] ?? '';
$site_force = ($role_slug === 'coordinateur_site' && $user['site_id']) ? (int)$user['site_id'] : 0;

// ── AJAX ────────────────────────────────────────────────────
if (is_ajax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!$can_receptionner) json_response(false, 'Action réservée.');
    $action = $_POST['action'] ?? '';

    if ($action === 'receptionner') {
        $suivi_id    = (int)($_POST['suivi_id'] ?? 0);
        $quantite    = (int)($_POST['quantite'] ?? 0);
        $date        = trim($_POST['date_reception'] ?? '') ?: date('Y-m-d');
        $observation = trim($_POST['observation'] ?? '');

        // Un coordinateur ne peut réceptionner que pour son propre site —
        // même filtre que la liste, revérifié côté serveur.
        if ($site_force) {
            $site_ligne = (int) db_fetch_value("SELECT site_id FROM feb_suivi WHERE id=?", [$suivi_id]);
            if ($site_ligne !== $site_force) json_response(false, "Cette ligne n'appartient pas à votre site.");
        }

        $bl_filename = null;
        if (!empty($_FILES['bl']['name'])) {
            $up = upload_document('bl', 'bl', 'bl_suivi_' . $suivi_id, false);
            if (!$up['success']) json_response(false, $up['message']);
            $bl_filename = $up['filename'];
        }

        try {
            $res = ach_receptionner($suivi_id, $quantite, $date, $bl_filename, $observation, $user);
            $msg = $res['solde']
                ? "Réception enregistrée — ligne soldée (cumul {$res['cumul']})."
                : "Réception enregistrée — cumul {$res['cumul']}, reste {$res['ecart']}.";
            json_response(true, $msg, $res);
        } catch (AchValidationException $e) {
            json_response(false, $e->getMessage());
        }
    }

    json_response(false, 'Action inconnue.');
}

// ── PAGE PHP ─────────────────────────────────────────────────
// Une ligne sans BC n'est pas réceptionnable (règle d'ordre J7, point 4) ;
// un reliquat clôturé de force ne l'est plus non plus.
$where  = ["fs.numero_bc IS NOT NULL", "fs.numero_bc <> ''", "fs.quantite_recue < fs.quantite_commandee", "fs.cloture_reliquat = 0"];
$params = [];
if ($site_force) { $where[] = 'fs.site_id = ?'; $params[] = $site_force; }

$lignes = db_fetch_all(
    "SELECT fs.*, f.numero AS feb_numero,
            fl.designation, fl.unite, fl.fournisseur_id,
            fo.raison_sociale AS fournisseur_nom,
            s.nom AS site_nom
     FROM feb_suivi fs
     JOIN feb f          ON f.id = fs.feb_id
     JOIN feb_lignes fl  ON fl.id = fs.feb_ligne_id
     LEFT JOIN fournisseurs fo ON fo.id = fl.fournisseur_id
     LEFT JOIN sites s         ON s.id = fs.site_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY fs.date_livraison_prevue ASC",
    $params
);

// ── Rafraîchissement live — fragment HTML seul (le tableau), cf. le même
//    mécanisme sur file_attente.php.
function ach_rc_render_zone(array $lignes, bool $can_receptionner, int $site_force): void {
    ?>
    <div class="ach-table-wrap">
      <?php if (empty($lignes)): ?>
        <div class="ach-empty">Aucune ligne à réceptionner<?= $site_force ? ' pour votre site' : '' ?>.</div>
      <?php else: ?>
      <div style="overflow-x:auto">
      <table class="ach-table">
        <thead><tr>
          <th>FEB</th><th>Article</th><th>Fournisseur</th><th>Commandée</th><th>Déjà reçue</th><th>Reste à recevoir</th>
          <th>Livraison prévue</th><?php if ($can_receptionner): ?><th>Actions</th><?php endif; ?>
        </tr></thead>
        <tbody>
          <?php foreach ($lignes as $l):
            $reste = (int)$l['quantite_commandee'] - (int)$l['quantite_recue'];
          ?>
          <tr>
            <td style="font-weight:700;color:var(--navy)"><?= h($l['feb_numero'] ?: '—') ?></td>
            <td><?= h($l['designation']) ?></td>
            <td><?= h($l['fournisseur_nom'] ?: '—') ?></td>
            <td><?= (int)$l['quantite_commandee'] ?> <?= h($l['unite'] ?: '') ?></td>
            <td><?= (int)$l['quantite_recue'] ?></td>
            <td style="font-weight:700"><?= $reste ?></td>
            <td><?= fmt_date($l['date_livraison_prevue']) ?></td>
            <?php if ($can_receptionner): ?>
            <td>
              <button type="button" class="btn btn-primary btn-sm"
                      onclick='rcOuvrir(<?= json_encode([
                        "id"=>(int)$l["id"], "feb_numero"=>$l["feb_numero"], "designation"=>$l["designation"],
                        "unite"=>$l["unite"], "reste"=>$reste, "commandee"=>(int)$l["quantite_commandee"],
                        "recue"=>(int)$l["quantite_recue"],
                      ], JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                Réceptionner
              </button>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </div>
    <?php
}

if (is_ajax() && ($_GET['fragment'] ?? '') === '1') {
    ach_rc_render_zone($lignes, $can_receptionner, $site_force);
    exit;
}
$fragmentUrl = APP_URL . '/pages/achats/receptions.php?fragment=1';

include __DIR__ . '/../../templates/header.php';
?>
<style>
.ach-table-wrap{background:white;border:1px solid var(--border);border-radius:16px;overflow:hidden}
.ach-table{width:100%;border-collapse:collapse;font-size:13px}
.ach-table th{background:#f8fafc;color:var(--muted);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:10px 14px;text-align:left;border-bottom:1px solid var(--border)}
.ach-table td{padding:10px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
.ach-table tr:last-child td{border-bottom:none}
.ach-empty{padding:40px 20px;text-align:center;color:var(--muted);font-size:13px}
.ach-modal-bg{display:none;position:fixed;inset:0;background:rgba(6,3,58,.45);z-index:2000;align-items:center;justify-content:center;padding:20px}
.ach-modal-bg.open{display:flex}
.ach-modal{background:white;border-radius:16px;padding:26px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.ach-modal h3{margin:0 0 16px;font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:var(--navy)}
.rc-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 16px;margin-bottom:16px}
.rc-lbl{font-size:11.5px;text-transform:uppercase;color:var(--muted);font-weight:700;letter-spacing:.4px;margin-bottom:2px}
.rc-val{font-size:14px;font-weight:700;color:var(--navy)}
.rc-val.big{font-size:20px;color:#B45309}
.ach-fg{margin-bottom:14px}
.ach-fg label{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px}
.ach-fg input,.ach-fg textarea{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box}
.ach-fg textarea{resize:vertical;min-height:60px}
.ach-err{color:#dc2626;font-size:12px;margin-top:4px;display:none}
.ach-modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px}
@media (max-width:768px) { .ach-fg input, .ach-fg textarea, .btn { min-height:44px; } .rc-grid { grid-template-columns:minmax(0,1fr); } }
</style>

<div id="rc-live-zone">
<?php ach_rc_render_zone($lignes, $can_receptionner, $site_force); ?>
</div>

<!-- MODALE réception -->
<div class="ach-modal-bg" id="rc-modal">
  <div class="ach-modal" role="dialog" aria-labelledby="rc-modal-title">
    <h3 id="rc-modal-title">Réception — <span id="rc-feb"></span></h3>
    <input type="hidden" id="rc-suivi-id" value="">
    <div class="rc-grid">
      <div style="grid-column:1/-1"><div class="rc-lbl">Article</div><div class="rc-val" id="rc-article"></div></div>
      <div><div class="rc-lbl">Commandée</div><div class="rc-val" id="rc-commandee"></div></div>
      <div><div class="rc-lbl">Déjà reçue</div><div class="rc-val" id="rc-recue"></div></div>
      <div><div class="rc-lbl">Reste à recevoir</div><div class="rc-val big" id="rc-reste"></div></div>
    </div>
    <div class="ach-fg">
      <label for="rc-quantite">Quantité reçue cette fois</label>
      <input type="number" id="rc-quantite" min="1" step="1" required>
    </div>
    <div class="ach-fg">
      <label for="rc-date">Date de réception</label>
      <input type="date" id="rc-date">
    </div>
    <div class="ach-fg">
      <label for="rc-bl">Bon de livraison (PDF, JPG, PNG ou WEBP — facultatif)</label>
      <input type="file" id="rc-bl" accept=".pdf,.jpg,.jpeg,.png,.webp">
    </div>
    <div class="ach-fg">
      <label for="rc-observation">Observation</label>
      <textarea id="rc-observation" placeholder="Remarque sur cette livraison (facultatif)"></textarea>
    </div>
    <div class="ach-err" id="rc-err"></div>
    <div class="ach-modal-actions">
      <button type="button" class="btn btn-secondary" onclick="rcFermer()">Annuler</button>
      <button type="button" class="btn btn-primary" onclick="rcValider()">Enregistrer la réception</button>
    </div>
  </div>
</div>

<script>
function rcOuvrir(l) {
  document.getElementById('rc-suivi-id').value = l.id;
  document.getElementById('rc-feb').textContent = l.feb_numero || '—';
  document.getElementById('rc-article').textContent = l.designation;
  document.getElementById('rc-commandee').textContent = l.commandee + ' ' + (l.unite || '');
  document.getElementById('rc-recue').textContent = l.recue + ' ' + (l.unite || '');
  document.getElementById('rc-reste').textContent = l.reste + ' ' + (l.unite || '');
  document.getElementById('rc-quantite').value = l.reste;
  document.getElementById('rc-quantite').max = l.reste;
  document.getElementById('rc-date').value = new Date().toISOString().slice(0, 10);
  document.getElementById('rc-bl').value = '';
  document.getElementById('rc-observation').value = '';
  document.getElementById('rc-err').style.display = 'none';
  document.getElementById('rc-modal').classList.add('open');
  setTimeout(() => document.getElementById('rc-quantite').focus(), 80);
}
function rcFermer() { document.getElementById('rc-modal').classList.remove('open'); }
document.getElementById('rc-modal').addEventListener('click', e => { if (e.target === e.currentTarget) rcFermer(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') rcFermer(); });

function rcValider() {
  const err = document.getElementById('rc-err');
  const quantite = parseInt(document.getElementById('rc-quantite').value, 10);
  if (!quantite || quantite < 1) { err.textContent = 'La quantité reçue doit être strictement positive.'; err.style.display = 'block'; return; }

  const fd = new FormData();
  fd.append('action', 'receptionner');
  fd.append('suivi_id', document.getElementById('rc-suivi-id').value);
  fd.append('quantite', quantite);
  fd.append('date_reception', document.getElementById('rc-date').value);
  fd.append('observation', document.getElementById('rc-observation').value.trim());
  const blFile = document.getElementById('rc-bl').files[0];
  if (blFile) fd.append('bl', blFile);

  fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
    .then(r => r.json())
    .then(res => {
      if (!res.success) { err.textContent = res.message; err.style.display = 'block'; return; }
      toast(res.message, 'success');
      rcFermer();
      setTimeout(() => location.reload(), 600);
    });
}

document.addEventListener('DOMContentLoaded', () => {
  liveRefresh({ url: <?= json_encode($fragmentUrl) ?>, container: '#rc-live-zone', interval: 10000 });
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
