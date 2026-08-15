<?php
// ============================================================
//  pages/achats/feb_traitement.php — Arbitrage stock/achat d'une FEB
//  Réservé à l'acheteur qui détient la FEB (acheteur_id = utilisateur
//  courant), pas seulement masqué côté écran — contrôle serveur systématique.
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/achats.php';

require_auth();
$user = current_user();
require_permission('achats', 'can_update');
$uid = (int)$user['id'];
$_SESSION['groupe_actif'] = 'ACHATS';

// ── Charge la FEB si, et seulement si, l'utilisateur courant en est
//    l'acheteur détenteur — contrôle serveur, pas un simple masquage de
//    bouton (Bloc 3, point 17).
function feb_charger_pour_traitement(int $feb_id, int $uid): ?array {
    $feb = db_fetch_one("SELECT * FROM feb WHERE id=?", [$feb_id]);
    if (!$feb) return null;
    if ((int)$feb['acheteur_id'] !== $uid || $feb['statut'] !== 'prise_en_charge') return null;
    return $feb;
}

$feb_id = (int)($_GET['id'] ?? 0);

// ── AJAX ────────────────────────────────────────────────────
if (is_ajax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $post_feb_id = (int)($_POST['feb_id'] ?? 0);
    $feb = feb_charger_pour_traitement($post_feb_id, $uid);
    if (!$feb) json_response(false, "Cette FEB n'est pas (ou plus) en cours de traitement par vous.");

    if ($action === 'arbitrer') {
        $ligne_id = (int)($_POST['ligne_id'] ?? 0);
        $choix    = $_POST['choix'] ?? '';
        if (!in_array($choix, ['achat', 'stock'], true)) json_response(false, 'Choix invalide.');

        $l = db_fetch_one("SELECT * FROM feb_lignes WHERE id=? AND feb_id=?", [$ligne_id, $post_feb_id]);
        if (!$l) json_response(false, 'Ligne introuvable.');
        if (!$l['article_id']) json_response(false, 'Une ligne en saisie libre part toujours en achat — elle n\'est pas arbitrable.');

        if ($choix === 'stock') {
            $stock_global = (int)db_fetch_value("SELECT stock_global FROM articles WHERE id=?", [$l['article_id']]);
            if ($stock_global < (int)$l['quantite']) {
                json_response(false, "Couverture insuffisante ($stock_global disponible pour {$l['quantite']} demandé) — utilisez l'arbitrage partiel.");
            }
        }

        if ($choix !== $l['arbitrage']) {
            db_query("UPDATE feb_lignes SET arbitrage=? WHERE id=?", [$choix, $ligne_id]);
            audit_log($uid, 'UPDATE', 'achats', $post_feb_id,
                "Arbitrage ligne #{$l['numero_ligne']} ({$l['designation']}) : {$l['arbitrage']} → $choix");
        }
        json_response(true, 'Arbitrage enregistré.');
    }

    if ($action === 'arbitrer_partiel') {
        $ligne_id      = (int)($_POST['ligne_id'] ?? 0);
        $quantite_stock = (int)($_POST['quantite_stock'] ?? 0);

        $l = db_fetch_one("SELECT * FROM feb_lignes WHERE id=? AND feb_id=?", [$ligne_id, $post_feb_id]);
        if (!$l) json_response(false, 'Ligne introuvable.');
        if (!$l['article_id']) json_response(false, 'Une ligne en saisie libre n\'est pas arbitrable.');
        $quantite_totale = (int)$l['quantite'];
        if ($quantite_stock < 1 || $quantite_stock >= $quantite_totale) {
            json_response(false, 'La quantité servie sur stock doit être strictement comprise entre 1 et la quantité demandée.');
        }
        $stock_global = (int)db_fetch_value("SELECT stock_global FROM articles WHERE id=?", [$l['article_id']]);
        if ($quantite_stock > $stock_global) {
            json_response(false, "Stock disponible insuffisant ($stock_global) pour servir $quantite_stock.");
        }

        db_begin();
        try {
            $quantite_achat = $quantite_totale - $quantite_stock;
            // Somme des quantités conservée : la ligne d'origine devient la
            // portion « stock » (quantité réduite), une nouvelle ligne porte
            // le reste en « achat ».
            db_query("UPDATE feb_lignes SET quantite=?, arbitrage='stock' WHERE id=?", [$quantite_stock, $ligne_id]);
            $numero_suivant = (int)db_fetch_value("SELECT COALESCE(MAX(numero_ligne),0) FROM feb_lignes WHERE feb_id=?", [$post_feb_id]) + 1;
            db_query(
                "INSERT INTO feb_lignes (feb_id, numero_ligne, designation, article_id, quantite, unite, famille_id, code_analytique, type_achat, arbitrage)
                 VALUES (?,?,?,?,?,?,?,?,?,'achat')",
                [$post_feb_id, $numero_suivant, $l['designation'], $l['article_id'], $quantite_achat, $l['unite'], $l['famille_id'], $l['code_analytique'], $l['type_achat']]
            );
            audit_log($uid, 'UPDATE', 'achats', $post_feb_id,
                "Scission ligne #{$l['numero_ligne']} ({$l['designation']}) : $quantite_stock sur stock / $quantite_achat à acheter (total $quantite_totale conservé)");
            db_commit();
        } catch (Exception $e) {
            db_rollback();
            json_response(false, $e->getMessage());
        }
        json_response(true, 'Ligne scindée.');
    }

    if ($action === 'tout_servir_stock') {
        $lignes = db_fetch_all(
            "SELECT fl.*, a.stock_global FROM feb_lignes fl
             JOIN articles a ON a.id = fl.article_id
             WHERE fl.feb_id=?", [$post_feb_id]
        );
        if (!$lignes) json_response(false, 'Aucune ligne arbitrable sur cette FEB.');
        foreach ($lignes as $l) {
            if ((int)$l['stock_global'] < (int)$l['quantite']) {
                json_response(false, "Toutes les lignes ne sont pas couvertes par le stock — « {$l['designation']} » ne l'est pas.");
            }
        }
        foreach ($lignes as $l) {
            if ($l['arbitrage'] !== 'stock') {
                db_query("UPDATE feb_lignes SET arbitrage='stock' WHERE id=?", [$l['id']]);
            }
        }
        audit_log($uid, 'UPDATE', 'achats', $post_feb_id, 'Toutes les lignes arbitrables servies sur stock');
        json_response(true, 'Toutes les lignes sont arbitrées sur stock.');
    }

    if ($action === 'basculer') {
        try {
            $cmd_id = ach_basculer_vers_commande($post_feb_id, $user);
            $numero = db_fetch_value("SELECT numero_commande FROM commandes WHERE id=?", [$cmd_id]);
            json_response(true, "Commande interne $numero créée.", ['cmd_id' => $cmd_id, 'numero' => $numero]);
        } catch (AchValidationException $e) {
            json_response(false, $e->getMessage());
        } catch (Exception $e) {
            json_response(false, $e->getMessage());
        }
    }

    json_response(false, 'Action inconnue.');
}

// ── PAGE PHP ─────────────────────────────────────────────────
$feb = feb_charger_pour_traitement($feb_id, $uid);
if (!$feb) {
    http_response_code(403);
    include __DIR__ . '/../../templates/403.php';
    exit;
}

$demandeur = db_fetch_one(
    "SELECT CONCAT(prenom,' ',nom) AS nom FROM users WHERE id=?", [$feb['demandeur_id']]
);
$site_nom = $feb['site_id'] ? db_fetch_value("SELECT nom FROM sites WHERE id=?", [$feb['site_id']]) : null;

$lignes = db_fetch_all(
    "SELECT fl.*, a.libelle AS article_libelle, a.stock_global,
            COALESCE(ss.quantite, 0) AS stock_site
     FROM feb_lignes fl
     LEFT JOIN articles a ON a.id = fl.article_id
     LEFT JOIN stock_site ss ON ss.article_id = fl.article_id AND ss.site_id = ?
     WHERE fl.feb_id = ?
     ORDER BY fl.numero_ligne",
    [$feb['site_id'] ?: 0, $feb_id]
);

$commande_liee = db_fetch_one(
    "SELECT id, numero_commande, statut FROM commandes WHERE feb_id=?", [$feb_id]
);

// Couverture individuelle des lignes arbitrables (article_id renseigné) —
// pilote l'activation du bouton « Tout servir sur stock » (point 21).
$toutes_couvertes = true;
$au_moins_une_arbitrable = false;
foreach ($lignes as $l) {
    if ($l['article_id']) {
        $au_moins_une_arbitrable = true;
        if ((int)$l['stock_global'] < (int)$l['quantite']) $toutes_couvertes = false;
    }
}

$page_title  = 'Traitement FEB ' . ($feb['numero'] ?: '');
$active_page = 'achats_file_attente';

include __DIR__ . '/../../templates/header.php';
?>
<style>
.feb-hdr-card{background:white;border:1px solid var(--border);border-radius:16px;padding:20px;margin-bottom:18px}
.feb-hdr-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px 18px}
@media(max-width:900px){.feb-hdr-grid{grid-template-columns:repeat(2,1fr)}}
.feb-hdr-lbl{font-size:11.5px;text-transform:uppercase;color:var(--muted);font-weight:700;letter-spacing:.4px;margin-bottom:3px}
.feb-hdr-val{font-size:14px;font-weight:700;color:var(--navy)}
.ach-table-wrap{background:white;border:1px solid var(--border);border-radius:16px;overflow:hidden;margin-bottom:18px}
.ach-table{width:100%;border-collapse:collapse;font-size:13px}
.ach-table th{background:#f8fafc;color:var(--muted);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:11px 14px;text-align:left;border-bottom:1px solid var(--border)}
.ach-table td{padding:11px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
.ach-table tr:last-child td{border-bottom:none}
.ach-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap}
.badge-stock{background:#D1FAE5;color:#065F46}
.badge-achat{background:#FEF3C7;color:#92400E}
.badge-libre{background:#F1F5F9;color:#475569}
.feb-actions-bar{display:flex;gap:10px;justify-content:space-between;align-items:center;flex-wrap:wrap;margin-bottom:18px}
.feb-commande-box{background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:14px 18px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.ach-modal-bg{display:none;position:fixed;inset:0;background:rgba(6,3,58,.45);z-index:2000;align-items:center;justify-content:center;padding:20px}
.ach-modal-bg.open{display:flex}
.ach-modal{background:white;border-radius:16px;padding:26px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.ach-modal h3{margin:0 0 16px;font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:var(--navy)}
.ach-fg{margin-bottom:14px}
.ach-fg label{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px}
.arb-choice{display:flex;gap:10px;margin-bottom:14px}
.arb-choice label{flex:1;border:1.5px solid var(--border);border-radius:10px;padding:10px 12px;text-align:center;cursor:pointer;font-size:13px;font-weight:700}
.arb-choice input{margin-right:6px}
.arb-choice label.disabled{opacity:.4;cursor:not-allowed}
.ach-err{color:#dc2626;font-size:12px;margin-top:4px;display:none}
.feb-hint{font-size:12px;color:var(--muted);margin-top:6px}
.ach-modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px}
</style>

<div style="margin-bottom:18px">
  <div style="font-family:'Plus Jakarta Sans',sans-serif;font-size:20px;font-weight:900;color:var(--navy)">Traitement de la FEB <?= h($feb['numero'] ?: '') ?></div>
  <div style="font-size:13px;color:var(--muted);margin-top:2px"><?= h($feb['objet']) ?></div>
</div>

<div class="feb-hdr-card">
  <div class="feb-hdr-grid">
    <div><div class="feb-hdr-lbl">Demandeur</div><div class="feb-hdr-val"><?= h($demandeur['nom'] ?? '—') ?></div></div>
    <div><div class="feb-hdr-lbl">Site</div><div class="feb-hdr-val"><?= h($site_nom ?: '—') ?></div></div>
    <div><div class="feb-hdr-lbl">Urgence</div><div class="feb-hdr-val"><?= h(ach_urgences()[(int)$feb['urgence']] ?? 'Normale') ?></div></div>
    <div><div class="feb-hdr-lbl">Nombre de lignes</div><div class="feb-hdr-val"><?= count($lignes) ?></div></div>
  </div>
</div>

<?php if ($commande_liee): ?>
<div class="feb-commande-box">
  <div>
    <div class="feb-hdr-lbl" style="color:#B45309">Commande interne liée</div>
    <div class="feb-hdr-val"><?= h($commande_liee['numero_commande']) ?></div>
  </div>
  <a href="../commandes.php" class="btn btn-secondary btn-sm">Voir dans Commandes</a>
</div>
<?php endif; ?>

<div class="feb-actions-bar">
  <div class="feb-hint" style="margin:0">L'arbitrage se décide ligne par ligne. Aucun mouvement de stock n'a lieu à ce stade.</div>
  <div style="display:flex;gap:10px">
    <?php if ($au_moins_une_arbitrable): ?>
    <button type="button" class="btn btn-secondary" id="btn-tout-stock" <?= $toutes_couvertes ? '' : 'disabled' ?> onclick="febToutStock()">
      Tout servir sur stock
    </button>
    <?php endif; ?>
    <?php if (!$commande_liee): ?>
    <button type="button" class="btn btn-primary" onclick="febBasculer()">Basculer vers la commande interne</button>
    <?php endif; ?>
  </div>
</div>

<div class="ach-table-wrap">
  <div style="overflow-x:auto">
  <table class="ach-table">
    <thead><tr>
      <th>Désignation</th><th>Qté</th><th>Unité</th><th>Stock site</th><th>Stock global</th><th>Arbitrage</th><th>Actions</th>
    </tr></thead>
    <tbody>
      <?php foreach ($lignes as $l):
        $arbitrable = (bool)$l['article_id'];
        $couverte   = $arbitrable && (int)$l['stock_global'] >= (int)$l['quantite'];
        $partiel_ok = $arbitrable && (int)$l['stock_global'] > 0 && (int)$l['stock_global'] < (int)$l['quantite'];
        $badge_class = !$arbitrable ? 'badge-libre' : ($l['arbitrage'] === 'stock' ? 'badge-stock' : 'badge-achat');
        $badge_label = !$arbitrable ? 'Saisie libre — achat direct' : ($l['arbitrage'] === 'stock' ? 'Sur stock' : 'À acheter');
      ?>
      <tr>
        <td style="font-weight:700;color:var(--navy)"><?= h($l['designation']) ?></td>
        <td><?= (int)$l['quantite'] ?></td>
        <td><?= h($l['unite'] ?: '—') ?></td>
        <td><?= $arbitrable ? (int)$l['stock_site'] : '—' ?></td>
        <td><?= $arbitrable ? (int)$l['stock_global'] : '—' ?></td>
        <td><span class="ach-badge <?= $badge_class ?>"><?= h($badge_label) ?></span></td>
        <td>
          <?php if ($arbitrable && !$commande_liee): ?>
          <button type="button" class="btn btn-secondary btn-sm"
                  onclick='febOuvrirArbitrage(<?= json_encode([
                    "id"=>(int)$l["id"], "designation"=>$l["designation"], "quantite"=>(int)$l["quantite"],
                    "stock_global"=>(int)$l["stock_global"], "arbitrage"=>$l["arbitrage"],
                    "couverte"=>$couverte, "partiel_ok"=>$partiel_ok,
                  ], JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
            Arbitrer
          </button>
          <?php else: ?>—<?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- MODALE arbitrage d'une ligne -->
<div class="ach-modal-bg" id="arb-modal">
  <div class="ach-modal" role="dialog" aria-labelledby="arb-modal-title">
    <h3 id="arb-modal-title">Arbitrer la ligne</h3>
    <input type="hidden" id="arb-ligne-id" value="">
    <div class="feb-hdr-lbl">Désignation</div>
    <div class="feb-hdr-val" id="arb-designation" style="margin-bottom:14px"></div>
    <div class="arb-choice">
      <label id="arb-lbl-achat"><input type="radio" name="arb-choix" value="achat"> Acheter</label>
      <label id="arb-lbl-stock"><input type="radio" name="arb-choix" value="stock"> Servir sur stock</label>
    </div>
    <div id="arb-partiel-box" style="display:none">
      <div class="ach-fg">
        <label for="arb-qte-stock">Quantité à servir sur stock (couverture partielle)</label>
        <input type="number" id="arb-qte-stock" min="1" step="1">
        <div class="feb-hint" id="arb-partiel-hint"></div>
      </div>
    </div>
    <div class="ach-err" id="arb-err"></div>
    <div class="ach-modal-actions">
      <button type="button" class="btn btn-secondary" onclick="febFermerArbitrage()">Annuler</button>
      <button type="button" class="btn btn-primary" onclick="febEnregistrerArbitrage()">Enregistrer</button>
    </div>
  </div>
</div>

<script>
const FEB_ID = <?= (int)$feb_id ?>;
let arbLigne = null;

function febPost(data) {
  const fd = new FormData();
  fd.append('feb_id', FEB_ID);
  Object.entries(data).forEach(([k, v]) => fd.append(k, v));
  return fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd }).then(r => r.json());
}

function febOuvrirArbitrage(l) {
  arbLigne = l;
  document.getElementById('arb-ligne-id').value = l.id;
  document.getElementById('arb-designation').textContent = l.designation + ' — qté ' + l.quantite + ' (stock global : ' + l.stock_global + ')';
  document.getElementById('arb-err').style.display = 'none';

  const lblStock = document.getElementById('arb-lbl-stock');
  const radios = document.querySelectorAll('input[name="arb-choix"]');
  radios.forEach(r => { r.checked = (r.value === l.arbitrage); r.onchange = febMajPartiel; });

  if (l.couverte) {
    lblStock.classList.remove('disabled');
    document.querySelector('input[name="arb-choix"][value="stock"]').disabled = false;
  } else {
    lblStock.classList.add('disabled');
    document.querySelector('input[name="arb-choix"][value="stock"]').disabled = true;
    if (l.arbitrage === 'stock') document.querySelector('input[name="arb-choix"][value="achat"]').checked = true;
  }
  febMajPartiel();
  document.getElementById('arb-modal').classList.add('open');
}
function febMajPartiel() {
  const box = document.getElementById('arb-partiel-box');
  if (!arbLigne || arbLigne.couverte || !arbLigne.partiel_ok) { box.style.display = 'none'; return; }
  box.style.display = '';
  const input = document.getElementById('arb-qte-stock');
  input.max = Math.min(arbLigne.stock_global, arbLigne.quantite - 1);
  document.getElementById('arb-partiel-hint').textContent =
    `Couverture insuffisante pour la totalité (stock global : ${arbLigne.stock_global} / demandé : ${arbLigne.quantite}). `
    + `Renseignez la quantité à servir sur stock (maximum ${input.max}) — le reste part en achat.`;
}
function febFermerArbitrage() { document.getElementById('arb-modal').classList.remove('open'); }
document.getElementById('arb-modal').addEventListener('click', e => { if (e.target === e.currentTarget) febFermerArbitrage(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') febFermerArbitrage(); });

function febEnregistrerArbitrage() {
  const err = document.getElementById('arb-err');
  err.style.display = 'none';
  const choix = document.querySelector('input[name="arb-choix"]:checked');
  if (!choix) { err.textContent = 'Choisissez une option.'; err.style.display = 'block'; return; }

  const partielVisible = document.getElementById('arb-partiel-box').style.display !== 'none';
  if (partielVisible && document.getElementById('arb-qte-stock').value) {
    const qte = parseInt(document.getElementById('arb-qte-stock').value, 10);
    febPost({ action: 'arbitrer_partiel', ligne_id: arbLigne.id, quantite_stock: qte }).then(res => {
      if (!res.success) { err.textContent = res.message; err.style.display = 'block'; return; }
      toast(res.message, 'success');
      setTimeout(() => location.reload(), 500);
    });
    return;
  }

  febPost({ action: 'arbitrer', ligne_id: arbLigne.id, choix: choix.value }).then(res => {
    if (!res.success) { err.textContent = res.message; err.style.display = 'block'; return; }
    toast(res.message, 'success');
    setTimeout(() => location.reload(), 500);
  });
}

function febToutStock() {
  if (!confirm('Servir toutes les lignes arbitrables sur le stock ?')) return;
  febPost({ action: 'tout_servir_stock' }).then(res => {
    toast(res.message, res.success ? 'success' : 'danger');
    if (res.success) setTimeout(() => location.reload(), 500);
  });
}

function febBasculer() {
  if (!confirm('Basculer les lignes arbitrées "stock" vers une commande interne ?')) return;
  febPost({ action: 'basculer' }).then(res => {
    toast(res.message, res.success ? 'success' : 'danger');
    if (res.success) setTimeout(() => location.reload(), 800);
  });
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
