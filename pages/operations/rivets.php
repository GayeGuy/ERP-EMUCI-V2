<?php
// ============================================================
//  pages/operations/rivets.php  —  Stock rivets
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/notifications.php';

require_auth();
$user        = current_user();
$page_title  = 'Stock Rivets';
$active_page = 'rivets';
$role_slug_r = $user['role_slug'] ?? '';
$site_force_r = ($role_slug_r === 'coordinateur_site' && $user['site_id']) ? (int)$user['site_id'] : 0;
$sites_list  = db_fetch_all("SELECT id,nom FROM sites WHERE actif=1 ORDER BY nom");

if ($_SERVER['REQUEST_METHOD']==='POST' && is_ajax()) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action==='approvisionner') {
        $site_id = (int)($_POST['site_id'] ?? 0);
        $qte     = (int)($_POST['quantite'] ?? 0);
        $notes   = trim($_POST['notes'] ?? '');
        if (!$site_id || $qte <= 0) json_response(false,'Site et quantité obligatoires.');
        $site = db_fetch_one("SELECT nom FROM sites WHERE id=?",[$site_id]);
        db_query("INSERT INTO op_stock_rivets (site_id,quantite) VALUES (?,?)
                  ON DUPLICATE KEY UPDATE quantite=quantite+?",[$site_id,$qte,$qte]);
        audit_log($user['id'],'CREATE','operations',$site_id,"Approvisionnement $qte rivets → {$site['nom']} ($notes)");
        $nouveau = (int)db_fetch_value("SELECT quantite FROM op_stock_rivets WHERE site_id=?",[$site_id]);
        json_response(true,"$qte rivets ajoutés. Stock total : $nouveau.");
    }

    if ($action==='ajuster') {
        $site_id = (int)($_POST['site_id'] ?? 0);
        $new_qte = (int)($_POST['new_qte']  ?? 0);
        $motif   = trim($_POST['motif']      ?? '');
        $old     = (int)db_fetch_value("SELECT COALESCE(quantite,0) FROM op_stock_rivets WHERE site_id=?",[$site_id]);
        db_query("INSERT INTO op_stock_rivets (site_id,quantite) VALUES (?,?) ON DUPLICATE KEY UPDATE quantite=?",
            [$site_id,$new_qte,$new_qte]);
        audit_log($user['id'],'UPDATE','operations',$site_id,"Ajustement rivets site:$site_id : $old → $new_qte ($motif)");
        json_response(true,'Stock ajusté.');
    }

    json_response(false,'Action inconnue.');
}

$stocks = db_fetch_all(
    "SELECT s.id, s.nom, s.type,
            sr.type_rivet,
            COALESCE(sr.quantite,0) AS quantite,
            COALESCE((SELECT SUM(p.rivets_utilises+p.rivets_endommages)
                      FROM op_points_journaliers p
                      WHERE p.site_id=s.id AND DATE_FORMAT(p.date_point,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')),0) AS utilises_mois
     FROM sites s
     JOIN op_stock_rivets sr ON sr.site_id=s.id
     WHERE s.actif=1 " . ($site_force_r ? "AND s.id=$site_force_r" : "") . "
     ORDER BY s.nom, FIELD(sr.type_rivet,'gonflable','eclate')"
);

include __DIR__ . '/../../templates/header.php';
?>
<style>
.fsel{padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;background:white;cursor:pointer;outline:none;font-family:'DM Sans',sans-serif}
.modal-overlay{display:none;position:fixed;inset:0;z-index:500;background:rgba(10,22,40,.55);backdrop-filter:blur(4px);align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:white;border-radius:16px;width:480px;max-width:95vw;max-height:92vh;overflow-y:auto;animation:mIn .25s cubic-bezier(.22,1,.36,1)}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.mhdr{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:white;z-index:10}
.mhdr h3{font-family:'Montserrat',sans-serif;font-size:17px;font-weight:700}
.mclose{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:none;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center}
.mbody{padding:24px}
.mfoot{padding:14px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;position:sticky;bottom:0;background:white}
</style>

<div style="display:flex;justify-content:flex-end;margin-bottom:18px;gap:8px">
  <?php
  $role_slug_rivets = current_user()['role_slug'] ?? '';
  $can_appro = in_array($role_slug_rivets, ['admin','superadmin','gestionnaire_stock','superviseur_operation']);
  ?>
  <?php if($can_appro): ?>
  <button class="btn btn-primary" onclick="document.getElementById('mAppro').classList.add('open')">+ Approvisionner</button>
  <button class="btn btn-secondary" onclick="document.getElementById('mAjust').classList.add('open')">⚖️ Ajustement</button>
  <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
<?php foreach($stocks as $s):
  $pct   = $s['quantite']>0 ? min(100,round($s['quantite']/5000*100)) : 0;
  $color = $s['quantite']<200 ? 'var(--danger)' : ($s['quantite']<1000 ? 'var(--warning)' : 'var(--success)');
  $is_gonfl  = ($s['type_rivet'] === 'gonflable');
  $icon      = $is_gonfl ? '🔵' : '🔴';
  $type_lbl  = $is_gonfl ? 'Gonflables' : 'Éclatés';
  $bg_header = $is_gonfl ? '#e3f2fd' : '#fce4ec';
  $dispo_lbl = $is_gonfl ? 'rivets gonflables disponibles' : 'rivets éclatés disponibles';
?>
<div style="background:white;border:1px solid var(--border);border-radius:14px;overflow:hidden">
  <div style="padding:16px;border-bottom:1px solid var(--border);background:<?= $bg_header ?>;display:flex;align-items:center;gap:12px">
    <div style="font-size:28px"><?= $icon ?></div>
    <div style="flex:1">
      <div style="font-family:'Montserrat',sans-serif;font-size:14px;font-weight:700;color:var(--navy)"><?= h($s['nom']) ?></div>
      <div style="font-size:12px;color:var(--muted);font-weight:600"><?= $type_lbl ?></div>
    </div>
  </div>
  <div style="padding:16px">
    <div style="font-family:'Montserrat',sans-serif;font-size:36px;font-weight:900;color:<?= $color ?>;line-height:1"><?= fmt_number($s['quantite']) ?></div>
    <div style="font-size:12px;color:var(--muted);margin-bottom:10px"><?= $dispo_lbl ?></div>
    <div style="height:6px;background:var(--border);border-radius:3px;overflow:hidden;margin-bottom:10px">
      <div style="width:<?= $pct ?>%;height:100%;background:<?= $color ?>;border-radius:3px"></div>
    </div>
    <div style="font-size:12px;color:var(--muted)">Utilisés ce mois : <strong><?= fmt_number($s['utilises_mois']) ?></strong></div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Historique points -->
<div class="card" style="margin-top:20px">
  <div class="card-header"><h3>📋 Consommation rivets — Points journaliers récents</h3></div>
  <div class="table-wrap">
    <?php $recap = db_fetch_all(
      "SELECT p.date_point, s.nom AS site, p.total_engins, p.rivets_utilises, p.rivets_endommages,
              COALESCE(p.rivets_gonflables,0) AS rivets_gonflables,
              COALESCE(p.rivets_eclates,0)    AS rivets_eclates,
              p.rivets_utilises+p.rivets_endommages AS total_sortis
       FROM op_points_journaliers p JOIN sites s ON s.id=p.site_id
       " . ($site_force_r ? "WHERE p.site_id=$site_force_r" : "") . "
       ORDER BY p.date_point DESC LIMIT 30");
    ?>
    <table>
      <thead><tr>
        <th>Date</th>
        <?php if(!$site_force_r): ?><th>Site</th><?php endif; ?>
        <th>Engins</th>
        <th style="text-align:center">🔵 Gonfl.</th>
        <th style="text-align:center">🔴 Éclatés</th>
        <th style="text-align:center">Endommagés</th>
        <th style="text-align:center">Total sortis</th>
      </tr></thead>
      <tbody>
      <?php if(empty($recap)): ?>
        <tr><td colspan="<?= $site_force_r ? 6 : 7 ?>" style="text-align:center;padding:30px;color:var(--muted)">Aucun point journalier.</td></tr>
      <?php else: foreach($recap as $r): ?>
        <tr>
          <td><?= fmt_date($r['date_point']) ?></td>
          <?php if(!$site_force_r): ?><td><?= h($r['site']) ?></td><?php endif; ?>
          <td style="text-align:center;font-weight:700"><?= $r['total_engins'] ?></td>
          <td style="text-align:center;font-family:'Montserrat',sans-serif;font-weight:700;color:#1565c0"><?= $r['rivets_gonflables'] ?></td>
          <td style="text-align:center;font-family:'Montserrat',sans-serif;font-weight:700;color:#880e4f"><?= $r['rivets_eclates'] ?></td>
          <td style="text-align:center;color:<?= $r['rivets_endommages']>0 ? 'var(--danger)' : 'var(--muted)' ?>;font-weight:600"><?= $r['rivets_endommages']?:0 ?></td>
          <td style="text-align:center;font-family:'Montserrat',sans-serif;font-weight:800;font-size:15px"><?= $r['total_sortis'] ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL APPROVISIONNER -->
<div class="modal-overlay" id="mAppro">
  <div class="modal">
    <div class="mhdr"><h3>+ Approvisionner en rivets</h3><button class="mclose" onclick="document.getElementById('mAppro').classList.remove('open')">✕</button></div>
    <div class="mbody">
      <div id="apAlert"></div>
      <div class="form-group"><label>Site *</label>
        <select class="form-control" id="ap-site">
          <option value="">— Sélectionner —</option>
          <?php foreach($sites_list as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['nom']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Quantité de rivets *</label>
        <input type="number" class="form-control" id="ap-qte" min="1" placeholder="Ex: 5000">
      </div>
      <div class="form-group"><label>Notes</label>
        <input type="text" class="form-control" id="ap-notes" placeholder="Bon de livraison, fournisseur…">
      </div>
    </div>
    <div class="mfoot">
      <button class="btn btn-secondary" onclick="document.getElementById('mAppro').classList.remove('open')">Annuler</button>
      <button class="btn btn-primary" onclick="saveAppro()">✅ Valider</button>
    </div>
  </div>
</div>

<!-- MODAL AJUSTEMENT -->
<div class="modal-overlay" id="mAjust">
  <div class="modal">
    <div class="mhdr"><h3>⚖️ Ajustement stock rivets</h3><button class="mclose" onclick="document.getElementById('mAjust').classList.remove('open')">✕</button></div>
    <div class="mbody">
      <div id="ajAlert"></div>
      <div class="form-group"><label>Site *</label>
        <select class="form-control" id="aj-site">
          <option value="">— Sélectionner —</option>
          <?php foreach($sites_list as $s): ?><option value="<?= $s['id'] ?>"><?= h($s['nom']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Nouveau stock *</label>
        <input type="number" class="form-control" id="aj-qte" min="0">
      </div>
      <div class="form-group"><label>Motif *</label>
        <input type="text" class="form-control" id="aj-motif" placeholder="Inventaire physique…">
      </div>
    </div>
    <div class="mfoot">
      <button class="btn btn-secondary" onclick="document.getElementById('mAjust').classList.remove('open')">Annuler</button>
      <button class="btn btn-primary" onclick="saveAjust()">✅ Appliquer</button>
    </div>
  </div>
</div>

<script>
function ap(data){const fd=new FormData();for(const[k,v]of Object.entries(data))if(v!==undefined)fd.append(k,v);return fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(r=>r.json());}
function saveAppro(){
  ap({action:'approvisionner',site_id:document.getElementById('ap-site').value,quantite:document.getElementById('ap-qte').value,notes:document.getElementById('ap-notes').value})
    .then(d=>{if(d.success){toast(d.message,'success');document.getElementById('mAppro').classList.remove('open');setTimeout(()=>location.reload(),800);}else document.getElementById('apAlert').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;});
}
function saveAjust(){
  ap({action:'ajuster',site_id:document.getElementById('aj-site').value,new_qte:document.getElementById('aj-qte').value,motif:document.getElementById('aj-motif').value})
    .then(d=>{if(d.success){toast(d.message,'success');document.getElementById('mAjust').classList.remove('open');setTimeout(()=>location.reload(),800);}else document.getElementById('ajAlert').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;});
}
['mAppro','mAjust'].forEach(id=>document.getElementById(id).addEventListener('click',e=>{if(e.target===e.currentTarget)e.currentTarget.classList.remove('open');}));
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
