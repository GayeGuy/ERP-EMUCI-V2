<?php
// ============================================================
//  pages/pmma.php — Module PMMA
//  Support physique sur lequel on pose la bobine
//  pour produire une plaque d'immatriculation
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

require_auth();
// Permissions vérifiées via can() sur les actions

$user      = current_user();
$role_slug = $user['role_slug'] ?? '';
$page_title  = 'PMMA';
$active_page = 'pmma';
$is_coord    = $role_slug === 'coordinateur_site';
$site_force  = ($is_coord && $user['site_id']) ? (int)$user['site_id'] : 0;
$sites_list  = db_fetch_all("SELECT id,nom FROM sites WHERE actif=1 ORDER BY nom");
$can_create  = can('pmma','can_create');

// ── AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'entree') {
        $site_id  = $site_force ?: (int)($_POST['site_id'] ?? 0);
        $quantite = (int)($_POST['quantite'] ?? 0);
        $type     = trim($_POST['type_pmma'] ?? '');
        $notes    = trim($_POST['notes'] ?? '');
        if (!$site_id || $quantite <= 0) json_response(false,'Site et quantité obligatoires.');

        db_query("INSERT INTO stock_pmma (site_id,type_pmma,quantite,type_mouvement,notes,created_by) VALUES (?,?,?,'entree',?,?)",
            [$site_id,$type,$quantite,$notes,$user['id']]);
        db_query("UPDATE stock_pmma_site SET quantite=quantite+? WHERE site_id=? AND type_pmma=?",
            [$quantite,$site_id,$type]);
        $affected = db_fetch_value("SELECT ROW_COUNT()");
        if (!$affected) {
            db_query("INSERT INTO stock_pmma_site (site_id,type_pmma,quantite,seuil_alerte) VALUES (?,?,?,10)",
                [$site_id,$type,$quantite]);
        }
        audit_log($user['id'],'CREATE','pmma',null,"Entrée PMMA: $quantite ($type) site:$site_id");
        json_response(true,"Entrée de $quantite PMMA enregistrée.");
    }

    if ($action === 'sortie') {
        $site_id  = $site_force ?: (int)($_POST['site_id'] ?? 0);
        $quantite = (int)($_POST['quantite'] ?? 0);
        $type     = trim($_POST['type_pmma'] ?? '');
        $bobine_id= (int)($_POST['bobine_id'] ?? 0);
        if (!$site_id || $quantite <= 0) json_response(false,'Site et quantité obligatoires.');

        $stock_actuel = (int)db_fetch_value(
            "SELECT quantite FROM stock_pmma_site WHERE site_id=? AND type_pmma=?",[$site_id,$type]
        );
        if ($stock_actuel < $quantite) json_response(false,"Stock insuffisant. Disponible: $stock_actuel");

        db_query("INSERT INTO stock_pmma (site_id,type_pmma,quantite,type_mouvement,bobine_id,notes,created_by) VALUES (?,?,?,'sortie',?,?,?)",
            [$site_id,$type,$quantite,$bobine_id ?: null,'Utilisation production',$user['id']]);
        db_query("UPDATE stock_pmma_site SET quantite=quantite-? WHERE site_id=? AND type_pmma=?",
            [$quantite,$site_id,$type]);
        audit_log($user['id'],'UPDATE','pmma',null,"Sortie PMMA: $quantite ($type) site:$site_id");
        json_response(true,"Sortie de $quantite PMMA enregistrée.");
    }

    json_response(false,'Action inconnue.');
}

// ── DONNÉES
$f_site = $site_force ?: (int)($_GET['site'] ?? 0);

$stock_par_site = db_fetch_all(
    "SELECT sp.*, s.nom AS site_nom
     FROM stock_pmma_site sp
     JOIN sites s ON s.id=sp.site_id
     WHERE (? = 0 OR sp.site_id = ?)
     ORDER BY s.nom, sp.type_pmma",
    [$f_site ?: 0, $f_site ?: 0]
);

// Si pas de données PMMA site, afficher tous les sites
if (empty($stock_par_site) && !$f_site) {
    $stock_par_site = db_fetch_all(
        "SELECT s.id AS site_id, s.nom AS site_nom, '' AS type_pmma, 0 AS quantite, 10 AS seuil_alerte
         FROM sites s WHERE s.actif=1 ORDER BY s.nom"
    );
}

$historique = db_fetch_all(
    "SELECT m.*, s.nom AS site_nom, CONCAT(u.prenom,' ',u.nom) AS agent
     FROM stock_pmma m
     LEFT JOIN sites s ON s.id=m.site_id
     LEFT JOIN users u ON u.id=m.created_by
     WHERE (? = 0 OR m.site_id = ?)
     ORDER BY m.created_at DESC LIMIT 50",
    [$f_site ?: 0, $f_site ?: 0]
);

$bobines_actives = db_fetch_all(
    "SELECT id,numero FROM op_bobines WHERE statut='en_cours'"
    .($site_force ? " AND site_id=$site_force" : "")
    ." ORDER BY numero"
);

include __DIR__ . '/../templates/header.php';
?>
<style>
.pmma-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-bottom:24px}
.pmma-card{background:white;border-radius:14px;border:1px solid var(--border);overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.pmma-head{padding:14px 18px;background:var(--navy);display:flex;justify-content:space-between;align-items:center}
.pmma-site{color:white;font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:700}
.pmma-body{padding:16px 18px}
.pmma-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:32px;font-weight:900}
.pmma-lbl{font-size:11px;color:var(--muted);font-weight:600;text-transform:uppercase;margin-top:4px}
.pmma-alert{background:#fee2e2;color:#991b1b;padding:6px 10px;border-radius:8px;font-size:11px;margin-top:8px;font-weight:600}
</style>

<!-- TOOLBAR -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <div>
    <h2 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;color:var(--navy)">🖨️ Stock PMMA</h2>
    <p style="font-size:12px;color:var(--muted);margin-top:2px">Support d'impression des plaques d'immatriculation</p>
  </div>
  <div style="display:flex;gap:8px">
    <?php if(!$site_force): ?>
    <select onchange="location.href='?site='+this.value" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;background:white;outline:none">
      <option value="0">Tous les sites</option>
      <?php foreach($sites_list as $s): ?>
      <option value="<?= $s['id'] ?>" <?= $f_site==$s['id']?'selected':'' ?>><?= h($s['nom']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <button class="btn btn-primary" onclick="ouvrirEntree()">📥 Entrée PMMA</button>
    <button class="btn" style="background:#e8f4f9;color:var(--blue);border:1.5px solid var(--blue)" onclick="ouvrirSortie()">📤 Sortie PMMA</button>
  </div>
</div>

<!-- STOCK PAR SITE -->
<div class="pmma-grid">
  <?php
  $sites_grouped = [];
  foreach($stock_par_site as $sp) {
    $sites_grouped[$sp['site_nom']][] = $sp;
  }
  foreach($sites_grouped as $site_nom => $items): ?>
  <div class="pmma-card">
    <div class="pmma-head">
      <div class="pmma-site">📍 <?= h($site_nom) ?></div>
    </div>
    <div class="pmma-body">
      <?php foreach($items as $item): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)">
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--navy)"><?= $item['type_pmma'] ?: 'Standard' ?></div>
          <div style="font-size:11px;color:var(--muted)">Seuil alerte : <?= $item['seuil_alerte']??10 ?></div>
        </div>
        <div style="text-align:right">
          <div class="pmma-val" style="font-size:24px;color:<?= $item['quantite']<($item['seuil_alerte']??10)?'var(--danger)':'var(--blue)' ?>"><?= $item['quantite'] ?></div>
          <div style="font-size:10px;color:var(--muted)">unités</div>
        </div>
      </div>
      <?php if($item['quantite'] < ($item['seuil_alerte']??10)): ?>
      <div class="pmma-alert">⚠️ Stock bas — commander des PMMA</div>
      <?php endif; ?>
      <?php endforeach; ?>
      <?php if(empty($items)): ?>
      <div style="text-align:center;color:var(--muted);padding:20px;font-size:13px">Aucun stock enregistré</div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- HISTORIQUE -->
<div class="card">
  <div class="card-header"><h3>📋 Historique des mouvements</h3></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Site</th><th>Type PMMA</th><th style="text-align:center">Mouvement</th><th style="text-align:center">Quantité</th><th>Agent</th></tr></thead>
      <tbody>
      <?php if(empty($historique)): ?>
        <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--muted)">Aucun mouvement.</td></tr>
      <?php else: foreach($historique as $m): ?>
        <tr>
          <td style="font-size:12px"><?= fmt_datetime($m['created_at']) ?></td>
          <td><?= h($m['site_nom']??'—') ?></td>
          <td><?= h($m['type_pmma']?:'Standard') ?></td>
          <td style="text-align:center">
            <span style="padding:2px 10px;border-radius:12px;font-size:11px;font-weight:700;background:<?= $m['type_mouvement']==='entree'?'#d1fae5':'#fee2e2' ?>;color:<?= $m['type_mouvement']==='entree'?'#065f46':'#991b1b' ?>">
              <?= $m['type_mouvement']==='entree'?'📥 Entrée':'📤 Sortie' ?>
            </span>
          </td>
          <td style="text-align:center;font-weight:700;color:<?= $m['type_mouvement']==='entree'?'var(--success)':'var(--danger)' ?>">
            <?= $m['type_mouvement']==='entree'?'+':'-' ?><?= $m['quantite'] ?>
          </td>
          <td style="font-size:12px"><?= h($m['agent']??'—') ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL ENTRÉE -->
<div id="modalEntree" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:20px;padding:28px;width:460px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:var(--navy)">📥 Entrée PMMA</h3>
      <button onclick="document.getElementById('modalEntree').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer">✕</button>
    </div>
    <div id="alertEntree"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
      <?php if(!$site_force): ?>
      <div class="form-group"><label>Site *</label>
        <select class="form-control" id="eSiteId">
          <option value="">— Sélectionner —</option>
          <?php foreach($sites_list as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['nom']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <?php else: ?><input type="hidden" id="eSiteId" value="<?= $site_force ?>"><?php endif; ?>
      <div class="form-group"><label>Type PMMA</label>
        <input type="text" class="form-control" id="eTypePmma" placeholder="ex: Standard, A4, A3...">
      </div>
    </div>
    <div class="form-group" style="margin-bottom:14px"><label>Quantité *</label>
      <input type="number" class="form-control" id="eQteEntree" min="1" value="1">
    </div>
    <div class="form-group" style="margin-bottom:20px"><label>Notes</label>
      <input type="text" class="form-control" id="eNotesEntree" placeholder="Fournisseur, N° BL...">
    </div>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button class="btn btn-secondary" onclick="document.getElementById('modalEntree').style.display='none'">Annuler</button>
      <button class="btn btn-primary" onclick="enregistrerEntree()">📥 Enregistrer</button>
    </div>
  </div>
</div>

<!-- MODAL SORTIE -->
<div id="modalSortie" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:20px;padding:28px;width:460px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:var(--navy)">📤 Sortie PMMA</h3>
      <button onclick="document.getElementById('modalSortie').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer">✕</button>
    </div>
    <div id="alertSortie"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
      <?php if(!$site_force): ?>
      <div class="form-group"><label>Site *</label>
        <select class="form-control" id="sSiteId">
          <option value="">— Sélectionner —</option>
          <?php foreach($sites_list as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['nom']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <?php else: ?><input type="hidden" id="sSiteId" value="<?= $site_force ?>"><?php endif; ?>
      <div class="form-group"><label>Type PMMA</label>
        <input type="text" class="form-control" id="sTypePmma" placeholder="ex: Standard, A4...">
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
      <div class="form-group"><label>Quantité *</label>
        <input type="number" class="form-control" id="sQteSortie" min="1" value="1">
      </div>
      <div class="form-group"><label>Bobine associée</label>
        <select class="form-control" id="sBobineId">
          <option value="">— Optionnel —</option>
          <?php foreach($bobines_actives as $b): ?><option value="<?= $b['id'] ?>"><?= h($b['numero']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button class="btn btn-secondary" onclick="document.getElementById('modalSortie').style.display='none'">Annuler</button>
      <button class="btn" style="background:#e8f4f9;color:var(--blue);border:1.5px solid var(--blue)" onclick="enregistrerSortie()">📤 Enregistrer</button>
    </div>
  </div>
</div>

<script>
function ap(d){return fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(d)}).then(r=>r.json());}
function toast(m,t='success'){const el=document.createElement('div');el.style.cssText=`position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:600;background:${t==='success'?'#27ae60':'#e74c3c'};color:white`;el.textContent=m;document.body.appendChild(el);setTimeout(()=>el.remove(),3500);}
function ouvrirEntree(){document.getElementById('alertEntree').innerHTML='';document.getElementById('modalEntree').style.display='flex';}
function ouvrirSortie(){document.getElementById('alertSortie').innerHTML='';document.getElementById('modalSortie').style.display='flex';}
async function enregistrerEntree(){
  const d=await ap({action:'entree',site_id:document.getElementById('eSiteId').value,type_pmma:document.getElementById('eTypePmma').value,quantite:document.getElementById('eQteEntree').value,notes:document.getElementById('eNotesEntree').value});
  if(d.success){toast(d.message);document.getElementById('modalEntree').style.display='none';setTimeout(()=>location.reload(),800);}
  else{document.getElementById('alertEntree').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;}
}
async function enregistrerSortie(){
  const d=await ap({action:'sortie',site_id:document.getElementById('sSiteId').value,type_pmma:document.getElementById('sTypePmma').value,quantite:document.getElementById('sQteSortie').value,bobine_id:document.getElementById('sBobineId').value});
  if(d.success){toast(d.message);document.getElementById('modalSortie').style.display='none';setTimeout(()=>location.reload(),800);}
  else{document.getElementById('alertSortie').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;}
}
</script>
<?php include __DIR__ . '/../templates/footer.php'; ?>
