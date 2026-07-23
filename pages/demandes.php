<?php
// ============================================================
//  pages/demandes.php — Mes demandes / Fiche détail / Validation / PDF
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/demandes.php';
require_once __DIR__ . '/../includes/demandes_champs.php';

require_auth();
$user = current_user();
$_SESSION['groupe_actif'] = 'DEMANDES';
$page_title  = 'Demandes internes';
$active_page = 'demandes';

$my_roles = di_user_roles((int)$user['id']);
$is_admin = in_array(($user['role_slug'] ?? ''), ['admin','superadmin'], true) || in_array('dg', $my_roles, true);

// ── EXPORT PDF ?export=pdf&id=X
if (($_GET['export'] ?? '') === 'pdf' && !empty($_GET['id'])) {
    $d = di_get((int)$_GET['id']);
    if (!$d) { http_response_code(404); exit('Demande introuvable.'); }
    $owner = (int)$d['demandeur_id'] === (int)$user['id'];
    $wf = di_workflow_of($d);
    $isValidator = false; foreach ($wf as $st) if (in_array($st['role'],$my_roles,true)) $isValidator=true;
    if (!$owner && !$isValidator && !$is_admin) { http_response_code(403); exit('Accès refusé.'); }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) { header('Content-Type:text/plain'); exit('Dompdf non installé.'); }
    require_once $autoload;
    $html = di_pdf_html($d);
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('demande_'.$d['numero'].'.pdf', ['Attachment' => false]);
    exit;
}

// ── AJAX : valider / rejeter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $d = di_get($id);
    if (!$d) json_response(false, 'Demande introuvable.');
    try {
        if ($action === 'valider') {
            di_valider($d, $user, trim($_POST['commentaire'] ?? ''));
            json_response(true, 'Étape validée.');
        } elseif ($action === 'rejeter') {
            di_rejeter($d, $user, trim($_POST['motif'] ?? ''));
            json_response(true, 'Demande rejetée.');
        }
        json_response(false, 'Action inconnue.');
    } catch (Exception $e) {
        json_response(false, $e->getMessage());
    }
}

// ── Fonction : HTML du PDF (reproduit la fiche de l'app source, moteur DomPDF)
function di_pdf_html(array $d): string {
    $type = di_type($d['type_code']);
    $demandeur = db_fetch_one("SELECT prenom, nom, email FROM users WHERE id=?", [$d['demandeur_id']]);
    $dnom = trim(($demandeur['prenom'] ?? '').' '.($demandeur['nom'] ?? ''));
    $wf   = di_workflow_of($d);
    $champs = $d['champs'];
    $fields = di_champs_of($d['type_code']);
    [$slbl,$sc] = di_statut_label($d['statut']);

    $rows = '';
    foreach ($fields as $f) {
        $val = $champs[$f['key']] ?? '';
        if (di_value_empty($val)) continue;
        $rows .= '<tr><td class="k">'.h($f['label']).'</td><td class="v">'.di_display_value($f, $val).'</td></tr>';
    }
    $sigs = '';
    foreach ($d['signatures'] as $s) {
        $act = ($s['action'] ?? '') === 'rejete' ? '<span style="color:#e74c3c">✗ Rejeté</span>' : '<span style="color:#27ae60">✓ Approuvé</span>';
        $note = h($s['commentaire'] ?? $s['motif'] ?? '');
        $sigs .= '<tr><td>'.h($s['etape_label'] ?? '').'</td><td>'.h($s['nom'] ?? '').'</td><td>'.$act.'</td><td>'.$note.'</td></tr>';
    }
    if ($sigs === '') $sigs = '<tr><td colspan="4" style="text-align:center;color:#999">Aucun visa pour le moment</td></tr>';

    return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
      body{font-family:"DejaVu Sans",sans-serif;color:#2c3e50;font-size:12px;margin:0}
      .hd{background:#06033A;color:#fff;padding:18px 26px}
      .hd h1{margin:0;font-size:18px}.hd .sub{font-size:11px;opacity:.8;margin-top:3px}
      .wrap{padding:22px 26px}
      .sec{font-size:12px;font-weight:700;color:#3B4FBE;text-transform:uppercase;letter-spacing:.5px;
        border-bottom:2px solid #e2e8f0;padding-bottom:5px;margin:20px 0 10px}
      table{width:100%;border-collapse:collapse}
      .info td{padding:5px 8px;font-size:12px}.info td.k{color:#7f8c8d;width:38%}.info td.v{font-weight:600}
      .badge{display:inline-block;padding:3px 12px;border-radius:10px;color:#fff;font-size:11px;font-weight:700;background:'.$sc.'}
      .sig{width:100%;border-collapse:collapse;margin-top:6px}
      .sig th{background:#f0f4f8;text-align:left;padding:6px 8px;font-size:11px;color:#555}
      .sig td{padding:6px 8px;border-bottom:1px solid #eee;font-size:11px}
      .ft{margin-top:26px;text-align:center;color:#999;font-size:10px;border-top:1px solid #eee;padding-top:8px}
    </style></head><body>
    <div class="hd"><h1>'.h($type['label']).'</h1><div class="sub">EMU-CI · Réf. '.h($d['numero']).' · '.date('d/m/Y', strtotime($d['created_at'])).'</div></div>
    <div class="wrap">
      <div class="sec">Demandeur</div>
      <table class="info"><tr><td class="k">Nom</td><td class="v">'.h($dnom).'</td></tr>
        <tr><td class="k">Email</td><td class="v">'.h($demandeur['email'] ?? '').'</td></tr>
        <tr><td class="k">Statut</td><td><span class="badge">'.h($slbl).'</span></td></tr></table>
      <div class="sec">Détails de la demande</div>
      <table class="info">'.$rows.'</table>
      <div class="sec">Validations</div>
      <table class="sig"><tr><th>Étape</th><th>Visa par</th><th>Décision</th><th>Note</th></tr>'.$sigs.'</table>
      <div class="ft">Document généré automatiquement par EMU-CI — Réf. '.h($d['numero']).' — '.date('d/m/Y H:i').'</div>
    </div></body></html>';
}

// ── Vue DÉTAIL (?id=)
$detail = null;
if (!empty($_GET['id'])) {
    $detail = di_get((int)$_GET['id']);
    if ($detail) {
        $owner = (int)$detail['demandeur_id'] === (int)$user['id'];
        $wf = di_workflow_of($detail);
        $isValidator = false;
        foreach ($wf as $st) if (in_array($st['role'], $my_roles, true)) $isValidator = true;
        // N+1 département : accès si l'utilisateur est le N+1 résolu de cette demande
        if (!$isValidator && isset($detail['n1_user_id']) && (int)$detail['n1_user_id'] === (int)$user['id']) {
            $isValidator = true;
        }
        if (!$owner && !$isValidator && !$is_admin) { $detail = null; }
    }
}

// ── Liste « Mes demandes »
$mes = $detail ? [] : db_fetch_all(
    "SELECT id, numero, type_code, statut, created_at FROM di_demandes WHERE demandeur_id=? ORDER BY created_at DESC",
    [$user['id']]
);
$type_labels = [];
foreach (di_types_actifs() as $t) $type_labels[$t['code']] = $t['label'];

// ── Mini-dashboard (vue liste uniquement)
$di_stats = ['en_attente'=>0,'en_cours'=>0,'approuve'=>0,'rejete'=>0,'brouillon'=>0];
$nb_a_valider = 0;
if (!$detail) {
    foreach (db_fetch_all("SELECT statut, COUNT(*) AS n FROM di_demandes WHERE demandeur_id=? GROUP BY statut", [$user['id']]) as $r) {
        $k = $r['statut'] === 'approuve_traitement' ? 'approuve' : $r['statut'];
        if (isset($di_stats[$k])) $di_stats[$k] += (int)$r['n'];
    }
    $nb_a_valider = count(di_a_valider($user));
}

include __DIR__ . '/../templates/header.php';
?>
<style>
  .di-wrap{max-width:960px;margin:0 auto}
  .di-topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
  .di-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;margin-bottom:20px}
  .di-stat{background:var(--card,#fff);border:1.5px solid var(--border,#e2e8f0);border-radius:14px;padding:16px 18px;text-decoration:none}
  .di-stat .n{font-size:26px;font-weight:800;line-height:1;color:var(--text,#2c3e50)}
  .di-stat .l{font-size:12px;color:var(--muted,#7f8c8d);margin-top:5px}
  .di-stat-action{background:linear-gradient(135deg,#3B4FBE,#7C92FF);border-color:transparent;color:#fff;transition:.15s}
  .di-stat-action:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(59,79,190,.28)}
  .di-stat-action .n,.di-stat-action .l{color:#fff}
  .di-btn{padding:10px 18px;border:none;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer;text-decoration:none;
    display:inline-flex;align-items:center;gap:7px;font-family:inherit}
  .di-btn-primary{background:linear-gradient(135deg,#3B4FBE,#7C92FF);color:#fff}
  .di-btn-ghost{background:var(--input,#f0f4f8);color:var(--text,#2c3e50)}
  .di-btn-danger{background:#fdf0ef;color:#e74c3c}
  .di-tbl{width:100%;border-collapse:collapse;background:var(--card,#fff);border:1.5px solid var(--border,#e2e8f0);border-radius:14px;overflow:hidden}
  .di-tbl th{text-align:left;padding:12px 16px;font-size:12px;color:var(--muted,#7f8c8d);background:var(--input,#f8fafc)}
  .di-tbl td{padding:13px 16px;border-top:1px solid var(--border,#eef2f7);font-size:14px}
  .di-tbl tr:hover td{background:var(--input,#f8fafc)}
  .di-empty{text-align:center;padding:50px 20px;color:var(--muted,#7f8c8d)}
  .di-card{background:var(--card,#fff);border:1.5px solid var(--border,#e2e8f0);border-radius:16px;padding:24px;margin-bottom:18px}
  .di-steps{display:flex;gap:8px;flex-wrap:wrap;margin:6px 0}
  .di-step{flex:1;min-width:120px;padding:10px 12px;border-radius:10px;border:1.5px solid var(--border,#e2e8f0);font-size:12px;text-align:center}
  .di-step.done{border-color:#27ae60;background:#eafaf1;color:#1e7e46}
  .di-step.current{border-color:#3B4FBE;background:#eef1fc;color:#3B4FBE;font-weight:700}
  .di-kv{display:grid;grid-template-columns:220px 1fr;gap:8px 16px;font-size:14px}
  .di-kv .k{color:var(--muted,#7f8c8d)}
  .di-alert{display:none;padding:11px 15px;border-radius:9px;font-size:13px;margin-bottom:14px}
  .di-alert.err{display:block;background:#fdf0ef;color:#e74c3c;border-left:3px solid #e74c3c}
  .di-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999;align-items:center;justify-content:center}
  .di-modal.open{display:flex}
  .di-modal .box{background:#fff;border-radius:14px;padding:24px;max-width:440px;width:92%}
  .di-modal textarea{width:100%;border:1.5px solid #d5dde8;border-radius:9px;padding:10px;font-family:inherit;font-size:14px}
</style>

<div class="di-wrap">
<?php if ($detail):
    $type = di_type($detail['type_code']);
    $wf   = di_workflow_of($detail);
    $cur  = (int)$detail['etape_actuelle'];
    $n1Id = isset($detail['n1_user_id']) && $detail['n1_user_id'] ? (int)$detail['n1_user_id'] : null;
    $canV = di_can_validate($my_roles, (int)$user['id'], $wf, $cur, (int)$detail['demandeur_id'], $n1Id)
            && in_array($detail['statut'], ['en_attente','en_cours'], true);
    $fields = di_champs_of($detail['type_code']);
    $demandeur = db_fetch_one("SELECT prenom, nom FROM users WHERE id=?", [$detail['demandeur_id']]);
?>
  <div class="di-topbar">
    <a href="<?= APP_URL ?>/pages/demandes.php" class="di-btn di-btn-ghost">← Mes demandes</a>
    <a href="<?= APP_URL ?>/pages/demandes.php?export=pdf&id=<?= (int)$detail['id'] ?>" target="_blank" class="di-btn di-btn-ghost"><i class="ph-duotone ph-file-pdf"></i> PDF</a>
  </div>

  <div class="di-card">
    <div style="display:flex;justify-content:space-between;align-items:start;gap:12px">
      <div>
        <h2 style="margin:0 0 4px"><?= h($type['label']) ?></h2>
        <div style="font-size:13px;color:var(--muted,#7f8c8d)">
          Réf. <?= h($detail['numero']) ?> · Demandeur : <?= h(trim(($demandeur['prenom']??'').' '.($demandeur['nom']??''))) ?>
          · <?= date('d/m/Y', strtotime($detail['created_at'])) ?>
        </div>
      </div>
      <?= di_badge($detail['statut']) ?>
    </div>

    <h4 style="margin:20px 0 6px;font-size:13px;color:var(--muted,#7f8c8d)">CIRCUIT DE VALIDATION</h4>
    <div class="di-steps">
      <?php foreach ($wf as $i => $st):
        $cls = $i < $cur ? 'done' : ($i === $cur && in_array($detail['statut'],['en_attente','en_cours'],true) ? 'current' : '');
        if ($detail['statut']==='approuve' || $detail['statut']==='approuve_traitement') $cls = 'done';
      ?>
        <div class="di-step <?= $cls ?>"><?= h($st['label']) ?></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="di-card">
    <h4 style="margin:0 0 14px">Détails de la demande</h4>
    <div class="di-kv">
      <?php foreach ($fields as $f): $v = $detail['champs'][$f['key']] ?? ''; if (di_value_empty($v)) continue; ?>
        <div class="k"><?= h($f['label']) ?></div><div><?= nl2br(di_display_value($f, $v)) ?></div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!empty($detail['signatures'])): ?>
  <div class="di-card">
    <h4 style="margin:0 0 12px">Historique des visas</h4>
    <?php foreach ($detail['signatures'] as $s): ?>
      <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid var(--border,#eef2f7);font-size:13px">
        <span style="color:<?= ($s['action']??'')==='rejete'?'#e74c3c':'#27ae60' ?>;font-weight:700">
          <?= ($s['action']??'')==='rejete'?'✗':'✓' ?></span>
        <div><strong><?= h($s['etape_label']??'') ?></strong> — <?= h($s['nom']??'') ?>
          <?php $note = $s['commentaire'] ?? $s['motif'] ?? ''; if ($note): ?><div style="color:var(--muted,#7f8c8d)"><?= h($note) ?></div><?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($canV): ?>
  <div class="di-card">
    <div class="di-alert" id="di-err"></div>
    <h4 style="margin:0 0 12px">Votre visa — étape « <?= h($wf[$cur]['label']) ?> »</h4>
    <div style="display:flex;gap:12px">
      <button class="di-btn di-btn-primary" onclick="diValider()"><i class="ph-duotone ph-check"></i> Valider</button>
      <button class="di-btn di-btn-danger" onclick="document.getElementById('di-reject').classList.add('open')"><i class="ph-duotone ph-x"></i> Rejeter</button>
    </div>
  </div>
  <?php endif; ?>

<?php else: ?>
  <div class="di-topbar">
    <h2 style="margin:0">Mes demandes</h2>
    <a href="<?= APP_URL ?>/pages/demandes_new.php" class="di-btn di-btn-primary"><i class="ph-duotone ph-plus"></i> Nouvelle demande</a>
  </div>

  <div class="di-stats">
    <?php if ($nb_a_valider > 0): ?>
      <a href="<?= APP_URL ?>/pages/demandes_a_valider.php" class="di-stat di-stat-action">
        <div class="n"><?= $nb_a_valider ?></div><div class="l">À valider par vous</div></a>
    <?php endif; ?>
    <div class="di-stat"><div class="n" style="color:#e67e22"><?= $di_stats['en_attente'] + $di_stats['en_cours'] ?></div><div class="l">En cours</div></div>
    <div class="di-stat"><div class="n" style="color:#27ae60"><?= $di_stats['approuve'] ?></div><div class="l">Approuvées</div></div>
    <div class="di-stat"><div class="n" style="color:#e74c3c"><?= $di_stats['rejete'] ?></div><div class="l">Rejetées</div></div>
    <div class="di-stat"><div class="n" style="color:#7f8c8d"><?= $di_stats['brouillon'] ?></div><div class="l">Brouillons</div></div>
  </div>

  <?php if (empty($mes)): ?>
    <div class="di-card di-empty">
      <i class="ph-duotone ph-tray" style="font-size:44px;color:#cbd5e1"></i>
      <p>Vous n'avez pas encore de demande.</p>
      <a href="<?= APP_URL ?>/pages/demandes_new.php" class="di-btn di-btn-primary" style="margin-top:8px">Créer ma première demande</a>
    </div>
  <?php else: ?>
    <table class="di-tbl">
      <thead><tr><th>Référence</th><th>Type</th><th>Date</th><th>Statut</th></tr></thead>
      <tbody>
      <?php foreach ($mes as $m): ?>
        <tr style="cursor:pointer" onclick="location.href='<?= APP_URL ?>/pages/demandes.php?id=<?= (int)$m['id'] ?>'">
          <td style="font-weight:700"><?= h($m['numero']) ?></td>
          <td><?= h($type_labels[$m['type_code']] ?? $m['type_code']) ?></td>
          <td><?= date('d/m/Y', strtotime($m['created_at'])) ?></td>
          <td><?= di_badge($m['statut']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
<?php endif; ?>
</div>

<!-- Modal rejet -->
<div class="di-modal" id="di-reject">
  <div class="box">
    <h3 style="margin:0 0 12px">Motif de rejet</h3>
    <textarea id="di-motif" rows="3" placeholder="Expliquez la raison du rejet…"></textarea>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
      <button class="di-btn di-btn-ghost" onclick="document.getElementById('di-reject').classList.remove('open')">Annuler</button>
      <button class="di-btn di-btn-danger" onclick="diRejeter()">Confirmer le rejet</button>
    </div>
  </div>
</div>

<script>
const DI_ID = <?= $detail ? (int)$detail['id'] : 0 ?>;
function diPost(fd, ok){
  fetch('<?= APP_URL ?>/pages/demandes.php', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd})
    .then(r=>r.json()).then(d=>{ if(d.success){ location.reload(); }
      else { const e=document.getElementById('di-err'); if(e){e.classList.add('err');e.textContent='⚠️ '+d.message;} else alert(d.message); } });
}
function diValider(){ const fd=new FormData(); fd.append('action','valider'); fd.append('id',DI_ID); diPost(fd); }
function diRejeter(){ const m=document.getElementById('di-motif').value.trim();
  if(!m){ alert('Le motif est obligatoire.'); return; }
  const fd=new FormData(); fd.append('action','rejeter'); fd.append('id',DI_ID); fd.append('motif',m); diPost(fd); }
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
