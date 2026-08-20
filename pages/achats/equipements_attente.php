<?php
// ============================================================
//  pages/achats/equipements_attente.php — File d'attente d'affectation
//  Exemplaires equipements créés à la réception d'une ligne DAI rattachée
//  à une nomenclature (Étape 3c). Écran de consultation seulement — la
//  validation de l'affectation elle-même est l'Étape 4, pas encore
//  construite : aucune action ici, juste la visibilité sur ce qui attend.
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/achats.php';

require_auth();
$user = current_user();
require_permission('achats_suivi', 'can_read');
$_SESSION['groupe_actif'] = 'ACHATS';
$page_title  = "File d'attente d'affectation";
$active_page = 'achats_equipements_attente';

$f_departement = (int)($_GET['departement'] ?? 0);
$where  = ["e.statut_stock = 'en_attente_affectation'"];
$params = [];
if ($f_departement) { $where[] = 'e.departement_id = ?'; $params[] = $f_departement; }

$equipements = db_fetch_all(
    "SELECT e.id, e.numero_serie_interne, e.prix_achat, e.date_acquisition,
            n.libelle AS nomenclature_libelle, n.categorie,
            d.label AS departement_label,
            f.numero AS feb_numero, fl.designation AS ligne_designation
     FROM equipements e
     LEFT JOIN nomenclatures n  ON n.id = e.nomenclature_id
     LEFT JOIN departements d   ON d.id = e.departement_id
     LEFT JOIN feb_lignes fl    ON fl.id = e.feb_ligne_id
     LEFT JOIN feb f            ON f.id = fl.feb_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY e.date_acquisition DESC, e.id DESC",
    $params
);
$total_valorise = array_sum(array_column($equipements, 'prix_achat'));

$departements_actifs = db_fetch_all("SELECT id, label FROM departements WHERE actif=1 ORDER BY label");

include __DIR__ . '/../../templates/header.php';
?>
<style>
.ach-toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:18px}
.ach-toolbar select{padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-family:inherit;background:white}
.ach-table-wrap{background:white;border:1px solid var(--border);border-radius:16px;overflow:hidden}
.ach-table{width:100%;border-collapse:collapse;font-size:13px}
.ach-table th{background:#f8fafc;color:var(--muted);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:10px 16px;text-align:left;border-bottom:1px solid var(--border)}
.ach-table td{padding:10px 16px;border-bottom:1px solid var(--border);vertical-align:middle}
.ach-table tr:last-child td{border-bottom:none}
.ach-empty{padding:40px 20px;text-align:center;color:var(--muted);font-size:13px}
.ach-summary{font-size:13px;color:var(--muted)}
@media (max-width:768px) { .ach-toolbar select { min-height:44px; } }
</style>

<div class="ach-toolbar">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <label for="ach-departement" style="font-size:13px;font-weight:600;color:#374151">Département</label>
    <select id="ach-departement" name="departement" onchange="this.form.submit()">
      <option value="0">Tous</option>
      <?php foreach ($departements_actifs as $d): ?>
        <option value="<?= $d['id'] ?>" <?= $f_departement === (int)$d['id'] ? 'selected' : '' ?>><?= h($d['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <div class="ach-summary"><?= count($equipements) ?> exemplaire(s) en attente — <?= fmt_number((float)$total_valorise) ?> XOF valorisés</div>
</div>

<div class="ach-table-wrap">
  <?php if (empty($equipements)): ?>
    <div class="ach-empty">Aucun exemplaire en attente d'affectation<?= $f_departement ? ' pour ce département' : '' ?>.</div>
  <?php else: ?>
  <div style="overflow-x:auto">
  <table class="ach-table">
    <thead><tr>
      <th>N° série (provisoire)</th><th>Nomenclature</th><th>Département</th><th>FEB d'origine</th><th>Valeur</th><th>Date de réception</th>
    </tr></thead>
    <tbody>
      <?php foreach ($equipements as $e): ?>
      <tr>
        <td style="font-family:monospace;font-size:12px"><?= h($e['numero_serie_interne']) ?></td>
        <td><?= h($e['nomenclature_libelle'] ?: '—') ?></td>
        <td><?= h($e['departement_label'] ?: '—') ?></td>
        <td>
          <?php if ($e['feb_numero']): ?>
            <span style="font-weight:700;color:var(--navy)"><?= h($e['feb_numero']) ?></span>
            <div style="font-size:11.5px;color:var(--muted)"><?= h($e['ligne_designation'] ?: '') ?></div>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td><?= fmt_number((float)$e['prix_achat']) ?> XOF</td>
        <td><?= fmt_date($e['date_acquisition']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
