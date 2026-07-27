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

// ── Calcul de la plage de dates selon la période choisie
$periode  = $_GET['periode'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to']   ?? '';

$today = date('Y-m-d');
if ($periode === 'today') {
    $date_from = $date_to = $today;
} elseif ($periode === 'week') {
    $date_from = date('Y-m-d', strtotime('monday this week'));
    $date_to   = $today;
} elseif ($periode === 'month') {
    $date_from = date('Y-m-01');
    $date_to   = $today;
} elseif ($periode === '3months') {
    $date_from = date('Y-m-d', strtotime('-3 months'));
    $date_to   = $today;
} elseif ($periode === 'custom') {
    // on garde date_from / date_to tels quels depuis GET
}
// Si aucun filtre : pas de restriction de date
$fFrom = $date_from !== '' ? $date_from : null;
$fTo   = $date_to   !== '' ? $date_to   : null;

$my_roles    = di_user_roles((int)$user['id']);
$a_valider   = di_a_valider($user, $fFrom, $fTo);
$deja_traite = di_deja_traite($user, $fFrom, $fTo);
$type_labels = [];
foreach (di_types_actifs() as $t) $type_labels[$t['code']] = $t['label'];

include __DIR__ . '/../templates/header.php';
?>
<style>
  .di-wrap{max-width:980px;margin:0 auto}
  .di-tbl{width:100%;border-collapse:collapse;background:var(--card,#fff);border:1.5px solid var(--border,#e2e8f0);border-radius:14px;overflow:hidden}
  .di-tbl th{text-align:left;padding:12px 16px;font-size:12px;color:var(--muted,#7f8c8d);background:var(--input,#f8fafc)}
  .di-tbl td{padding:12px 16px;border-top:1px solid var(--border,#eef2f7);font-size:14px}
  .di-tbl tr:hover td{background:var(--input,#f8fafc);cursor:pointer}
  .di-empty{text-align:center;padding:36px 20px;color:var(--muted,#7f8c8d);background:var(--card,#fff);
    border:1.5px solid var(--border,#e2e8f0);border-radius:16px}
  .di-chip{font-size:11px;font-weight:700;padding:3px 9px;border-radius:9px;background:#eef1fc;color:#3B4FBE}
  .di-chip.done{background:#eafaf1;color:#1f9d5b}
  .di-section{font-size:13px;font-weight:700;color:var(--navy,#06033A);margin:26px 0 10px;
    display:flex;align-items:center;gap:10px}
  .di-count{font-size:11px;font-weight:700;padding:2px 9px;border-radius:9px;background:#eef1fc;color:#3B4FBE}
  .di-count.done{background:#eafaf1;color:#1f9d5b}

  /* ── Filtres */
  .fbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;
    background:var(--card,#fff);border:1.5px solid var(--border,#e2e8f0);border-radius:12px;
    padding:12px 16px;margin-bottom:20px}
  .fbar-label{font-size:12px;font-weight:700;color:var(--muted,#7f8c8d);white-space:nowrap}
  .fpill{padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700;cursor:pointer;
    border:1.5px solid var(--border,#e2e8f0);background:var(--input,#f8fafc);color:var(--muted,#7f8c8d);
    text-decoration:none;transition:.15s}
  .fpill:hover,.fpill.active{background:#3B4FBE;color:#fff;border-color:#3B4FBE}
  .fdate{padding:7px 11px;border:1.5px solid var(--border,#d5dde8);border-radius:9px;
    font-size:13px;font-family:inherit;background:var(--input,#f8fafc);color:var(--text,#2c3e50)}
  .fsep{color:var(--muted,#cbd5e1);font-weight:300}
  .fbtn{padding:7px 16px;border:none;border-radius:9px;background:#3B4FBE;color:#fff;
    font-size:13px;font-weight:700;cursor:pointer;font-family:inherit}
  .fbtn-ghost{background:var(--input,#f0f4f8);color:var(--text,#2c3e50)}
</style>

<div class="di-wrap">
  <div style="margin-bottom:16px">
    <h2 style="margin:0 0 3px">Demandes à valider</h2>
    <p style="color:var(--muted,#7f8c8d);margin:0;font-size:13px">
      <?php if (!$my_roles): ?>Aucun rôle de validation ne vous est attribué.
      <?php else: ?>Circuits : <?= h(implode(', ', $my_roles)) ?>.<?php endif; ?>
    </p>
  </div>

  <!-- ── Barre de filtres -->
  <form method="get" class="fbar" id="fform">
    <span class="fbar-label">Période</span>

    <?php
    $pills = [
        ''       => 'Tout',
        'today'  => "Aujourd'hui",
        'week'   => 'Cette semaine',
        'month'  => 'Ce mois',
        '3months'=> '3 derniers mois',
        'custom' => 'Personnalisé',
    ];
    foreach ($pills as $val => $lbl):
        $active = ($periode === $val && !($val === '' && $periode !== '')) || ($val === '' && $periode === '') ? 'active' : '';
        // Pour "Tout" : actif si aucun filtre
        if ($val === '') $active = ($periode === '' || ($periode !== 'today' && $periode !== 'week' && $periode !== 'month' && $periode !== '3months' && $periode !== 'custom')) && $periode === '' ? 'active' : '';
        $active = ($periode === $val) ? 'active' : ($val === '' && $periode === '' ? 'active' : '');
    ?>
      <a href="?periode=<?= $val ?>" class="fpill <?= $active ?>"><?= $lbl ?></a>
    <?php endforeach; ?>

    <div id="custom-dates" style="display:<?= $periode === 'custom' ? 'flex' : 'none' ?>;align-items:center;gap:8px">
      <span class="fbar-label">Du</span>
      <input type="date" name="date_from" class="fdate" value="<?= h($date_from) ?>">
      <span class="fsep">au</span>
      <input type="date" name="date_to" class="fdate" value="<?= h($date_to) ?>">
      <input type="hidden" name="periode" value="custom">
      <button type="submit" class="fbtn">Appliquer</button>
      <a href="?" class="fbtn fbtn-ghost" style="text-decoration:none;padding:7px 14px">Réinitialiser</a>
    </div>
  </form>

  <?php if ($fFrom || $fTo): ?>
  <p style="font-size:12px;color:var(--muted,#7f8c8d);margin:-10px 0 16px">
    Filtré :
    <?php if ($fFrom && $fTo && $fFrom === $fTo): ?>
      <?= date('d/m/Y', strtotime($fFrom)) ?>
    <?php elseif ($fFrom && $fTo): ?>
      du <?= date('d/m/Y', strtotime($fFrom)) ?> au <?= date('d/m/Y', strtotime($fTo)) ?>
    <?php elseif ($fFrom): ?>
      à partir du <?= date('d/m/Y', strtotime($fFrom)) ?>
    <?php else: ?>
      jusqu'au <?= date('d/m/Y', strtotime($fTo)) ?>
    <?php endif; ?>
    — <a href="?" style="color:#3B4FBE">Effacer</a>
  </p>
  <?php endif; ?>

  <!-- ── En attente de mon visa -->
  <div class="di-section">
    En attente de mon visa
    <span class="di-count"><?= count($a_valider) ?></span>
  </div>

  <?php if (empty($a_valider)): ?>
    <div class="di-empty">
      <i class="ph-duotone ph-seal-check" style="font-size:40px;color:#cbd5e1"></i>
      <p style="margin:10px 0 0">Rien à valider<?= $fFrom || $fTo ? ' sur cette période' : ' pour le moment' ?>.</p>
    </div>
  <?php else: ?>
    <table class="di-tbl">
      <thead><tr><th>Référence</th><th>Type</th><th>Demandeur</th><th>Étape</th><th>Soumise le</th><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($a_valider as $d): ?>
        <tr onclick="location.href='<?= APP_URL ?>/pages/demandes.php?id=<?= (int)$d['id'] ?>'">
          <td style="font-weight:700"><?= h($d['numero']) ?></td>
          <td><?= h($type_labels[$d['type_code']] ?? $d['type_code']) ?></td>
          <td><?= h($d['_demandeur'] ?? '') ?></td>
          <td><span class="di-chip"><?= h($d['_etape_label']) ?></span></td>
          <td style="color:var(--muted,#7f8c8d);font-size:13px"><?= $d['submitted_at'] ? date('d/m/Y', strtotime($d['submitted_at'])) : '—' ?></td>
          <td><?= di_badge($d['statut']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <!-- ── Demandes où j'ai déjà visé -->
  <?php if (!empty($deja_traite)): ?>
  <div class="di-section">
    Demandes où j'ai déjà visé
    <span class="di-count done"><?= count($deja_traite) ?></span>
  </div>
  <table class="di-tbl">
    <thead><tr><th>Référence</th><th>Type</th><th>Demandeur</th><th>Étape courante</th><th>Soumise le</th><th>Statut</th></tr></thead>
    <tbody>
    <?php foreach ($deja_traite as $d): ?>
      <tr onclick="location.href='<?= APP_URL ?>/pages/demandes.php?id=<?= (int)$d['id'] ?>'">
        <td style="font-weight:700"><?= h($d['numero']) ?></td>
        <td><?= h($type_labels[$d['type_code']] ?? $d['type_code']) ?></td>
        <td><?= h($d['_demandeur'] ?? '') ?></td>
        <td><span class="di-chip done"><?= h($d['_etape_label']) ?></span></td>
        <td style="color:var(--muted,#7f8c8d);font-size:13px"><?= $d['submitted_at'] ? date('d/m/Y', strtotime($d['submitted_at'])) : '—' ?></td>
        <td><?= di_badge($d['statut']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php elseif ($fFrom || $fTo): ?>
  <div class="di-section">Demandes où j'ai déjà visé <span class="di-count done">0</span></div>
  <div class="di-empty">Aucune demande traitée sur cette période.</div>
  <?php endif; ?>

</div>

<script>
// Afficher/masquer la zone dates personnalisées
document.querySelectorAll('.fpill').forEach(function(a) {
  a.addEventListener('click', function(e) {
    if (this.getAttribute('href').includes('custom')) {
      e.preventDefault();
      document.getElementById('custom-dates').style.display = 'flex';
    } else {
      document.getElementById('custom-dates').style.display = 'none';
    }
  });
});
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
