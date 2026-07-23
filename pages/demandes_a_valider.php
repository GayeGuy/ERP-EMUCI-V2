<?php
// ============================================================
//  pages/demandes_a_valider.php — File d'attente du validateur
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/demandes.php';

require_auth();
$user = current_user();
$_SESSION['groupe_actif'] = 'DEMANDES';
$page_title  = 'Demandes à valider';
$active_page = 'demandes_valider';

$my_roles = di_user_roles((int)$user['id']);

// Demandes en attente que CE validateur peut viser (étape courante = un de ses rôles)
$a_valider = [];
if ($my_roles) {
    $pending = db_fetch_all(
        "SELECT id FROM di_demandes WHERE statut IN ('en_attente','en_cours') AND demandeur_id <> ? ORDER BY created_at ASC",
        [$user['id']]
    );
    foreach ($pending as $p) {
        $d = di_get((int)$p['id']);
        $wf = di_workflow_of($d);
        if (di_can_validate($my_roles, (int)$user['id'], $wf, (int)$d['etape_actuelle'], (int)$d['demandeur_id'])) {
            $d['_etape_label'] = $wf[(int)$d['etape_actuelle']]['label'] ?? '';
            $d['_demandeur']   = db_fetch_value("SELECT CONCAT(prenom,' ',nom) FROM users WHERE id=?", [$d['demandeur_id']]);
            $a_valider[] = $d;
        }
    }
}
$type_labels = [];
foreach (di_types_actifs() as $t) $type_labels[$t['code']] = $t['label'];

include __DIR__ . '/../templates/header.php';
?>
<style>
  .di-wrap{max-width:960px;margin:0 auto}
  .di-tbl{width:100%;border-collapse:collapse;background:var(--card,#fff);border:1.5px solid var(--border,#e2e8f0);border-radius:14px;overflow:hidden}
  .di-tbl th{text-align:left;padding:12px 16px;font-size:12px;color:var(--muted,#7f8c8d);background:var(--input,#f8fafc)}
  .di-tbl td{padding:13px 16px;border-top:1px solid var(--border,#eef2f7);font-size:14px}
  .di-tbl tr:hover td{background:var(--input,#f8fafc);cursor:pointer}
  .di-empty{text-align:center;padding:50px 20px;color:var(--muted,#7f8c8d);background:var(--card,#fff);
    border:1.5px solid var(--border,#e2e8f0);border-radius:16px}
  .di-chip{font-size:11px;font-weight:700;padding:3px 9px;border-radius:9px;background:#eef1fc;color:#3B4FBE}
</style>

<div class="di-wrap">
  <h2 style="margin:0 0 4px">Demandes à valider</h2>
  <p style="color:var(--muted,#7f8c8d);margin:0 0 20px">
    <?php if (!$my_roles): ?>Aucun rôle de validation ne vous est attribué.
    <?php else: ?>Étapes en attente de votre visa (<?= implode(', ', $my_roles) ?>).<?php endif; ?>
  </p>

  <?php if (empty($a_valider)): ?>
    <div class="di-empty">
      <i class="ph-duotone ph-seal-check" style="font-size:44px;color:#cbd5e1"></i>
      <p>Rien à valider pour le moment. 🎉</p>
    </div>
  <?php else: ?>
    <table class="di-tbl">
      <thead><tr><th>Référence</th><th>Type</th><th>Demandeur</th><th>Étape</th><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($a_valider as $d): ?>
        <tr onclick="location.href='<?= APP_URL ?>/pages/demandes.php?id=<?= (int)$d['id'] ?>'">
          <td style="font-weight:700"><?= h($d['numero']) ?></td>
          <td><?= h($type_labels[$d['type_code']] ?? $d['type_code']) ?></td>
          <td><?= h($d['_demandeur'] ?? '') ?></td>
          <td><span class="di-chip"><?= h($d['_etape_label']) ?></span></td>
          <td><?= di_badge($d['statut']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
