<?php
// ============================================================
//  pages/affectations.php  —  Affectations & mouvements
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/upload.php';

require_auth();
require_permission('affectations', 'can_read');

$user        = current_user();
$page_title  = 'Affectations';
$active_page = 'affectations';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // ── AFFECTER / TRANSFÉRER UN ÉQUIPEMENT
    if ($action === 'affecter') {
        require_permission('affectations', 'can_create');
        $equip_id   = (int)($_POST['equipement_id'] ?? 0);
        $site_id    = (int)($_POST['site_id']        ?? 0) ?: null;
        $user_id    = (int)($_POST['utilisateur_id'] ?? 0) ?: null;
        $notes      = trim($_POST['notes']            ?? '');
        $type_mouv  = trim($_POST['type_mouvement']   ?? 'transfert');

        if (!$equip_id) json_response(false, 'Équipement obligatoire.');

        $equip = db_fetch_one("SELECT * FROM equipements WHERE id=? AND actif=1", [$equip_id]);
        if (!$equip) json_response(false, 'Équipement introuvable ou inactif.');

        // ── UPLOAD BON DE LIVRAISON OBLIGATOIRE pour transfert/sortie vers un site
        $fichier_bl = null;
        if ($site_id && in_array($type_mouv, ['transfert', 'sortie'])) {
            $upload_bl = upload_document('fichier_bl', 'bl', 'bl_equip_' . $equip_id, true);
            if (!$upload_bl['success']) json_response(false, $upload_bl['message']);
            $fichier_bl = $upload_bl['filename'];
        }

        db_begin();
        try {
            // Enregistrer le mouvement
            db_query(
                "INSERT INTO mouvements_equipements
                 (equipement_id,type,site_source_id,site_dest_id,user_dest_id,notes,fichier_bl,created_by)
                 VALUES (?,?,?,?,?,?,?,?)",
                [$equip_id, $type_mouv, $equip['site_id'], $site_id, $user_id, $notes, $fichier_bl, $user['id']]
            );
            // Mettre à jour l'équipement
            db_query(
                "UPDATE equipements SET site_id=?, utilisateur_id=? WHERE id=?",
                [$site_id, $user_id, $equip_id]
            );

            // Créer réception_site en_attente si livraison vers un site
            if ($site_id && $type_mouv === 'transfert') {
                db_query(
                    "INSERT INTO receptions_site (site_id,type_reception,equipement_id,mouvement_ref_id,date_reception,statut,created_by)
                     VALUES (?,?,?,?,CURRENT_DATE,?,?)",
                    [$site_id, 'equipement', $equip_id, db_last_id(), 'en_attente', $user['id']]
                );
            }

            audit_log($user['id'], 'TRANSFER', 'affectations', $equip_id,
                "Affectation {$equip['numero_serie_interne']} → site:$site_id user:$user_id" . ($fichier_bl ? " (BL:$fichier_bl)" : ''));
            db_commit();
            json_response(true, 'Affectation enregistrée.');
        } catch (Exception $e) {
            db_rollback();
            json_response(false, 'Erreur : ' . $e->getMessage());
        }
    }

    // ── RETOUR EN STOCK (désaffecter)
    if ($action === 'retour') {
        require_permission('affectations', 'can_update');
        $equip_id = (int)($_POST['equipement_id'] ?? 0);
        $notes    = trim($_POST['notes'] ?? '');
        $equip    = db_fetch_one("SELECT * FROM equipements WHERE id=? AND actif=1", [$equip_id]);
        if (!$equip) json_response(false, 'Équipement introuvable.');

        db_begin();
        try {
            db_query(
                "INSERT INTO mouvements_equipements (equipement_id,type,site_source_id,notes,created_by)
                 VALUES (?,?,?,?,?)",
                [$equip_id, 'sortie', $equip['site_id'], $notes ?: 'Retour en stock', $user['id']]
            );
            db_query("UPDATE equipements SET site_id=NULL, utilisateur_id=NULL WHERE id=?", [$equip_id]);
            audit_log($user['id'], 'UPDATE', 'affectations', $equip_id,
                "Retour stock {$equip['numero_serie_interne']}");
            db_commit();
            json_response(true, 'Équipement remis en stock.');
        } catch (Exception $e) {
            db_rollback();
            json_response(false, 'Erreur : ' . $e->getMessage());
        }
    }

    // ── RECHERCHER ÉQUIPEMENTS (pour autocomplete)
    if ($action === 'search_equip') {
        $q  = trim($_POST['q'] ?? '');
        if (strlen($q) < 2) json_response(true, '', ['data' => []]);
        $rows = db_fetch_all(
            "SELECT e.id, e.numero_serie_interne, e.etat, COALESCE(n.libelle,'—') AS type,
                    s.nom AS site_actuel, CONCAT(u.prenom,' ',u.nom) AS user_actuel
             FROM equipements e
             LEFT JOIN nomenclatures n ON n.id=e.nomenclature_id
             LEFT JOIN sites s ON s.id=e.site_id
             LEFT JOIN users u ON u.id=e.utilisateur_id
             WHERE e.actif=1 AND (e.numero_serie_interne ILIKE ? OR e.numero_serie_origine ILIKE ?)
             LIMIT 10",
            ["%$q%", "%$q%"]
        );
        json_response(true, '', ['data' => $rows]);
    }

    json_response(false, 'Action inconnue.');
}

// ============================================================
//  LISTE DES MOUVEMENTS RÉCENTS
// ============================================================
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 30;
$f_site   = (int)($_GET['site']   ?? 0);
$f_type   = trim($_GET['type_mouv'] ?? '');

$where  = ['1=1'];
$params = [];
if ($f_site)  { $where[] = '(me.site_source_id=? OR me.site_dest_id=?)'; $params[] = $f_site; $params[] = $f_site; }
if ($f_type)  { $where[] = 'me.type=?'; $params[] = $f_type; }

$wsql   = implode(' AND ', $where);
$total  = (int)db_fetch_value("SELECT COUNT(*) FROM mouvements_equipements me WHERE $wsql", $params);
$offset = ($page - 1) * $per_page;

$mouvements = db_fetch_all(
    "SELECT me.*, e.numero_serie_interne, COALESCE(n.libelle,'—') AS type_equip,
            s1.nom AS site_source, s2.nom AS site_dest,
            CONCAT(u.prenom,' ',u.nom) AS agent,
            CONCAT(ud.prenom,' ',ud.nom) AS user_dest
     FROM mouvements_equipements me
     JOIN equipements e ON e.id=me.equipement_id
     LEFT JOIN nomenclatures n ON n.id=e.nomenclature_id
     LEFT JOIN sites s1 ON s1.id=me.site_source_id
     LEFT JOIN sites s2 ON s2.id=me.site_dest_id
     LEFT JOIN users u  ON u.id=me.created_by
     LEFT JOIN users ud ON ud.id=me.user_dest_id
     WHERE $wsql
     ORDER BY me.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$per_page, $offset])
);

// Équipements affectés (avec site)
$affectes = db_fetch_all(
    "SELECT e.id, e.numero_serie_interne, e.etat, COALESCE(n.libelle,'—') AS type_equip,
            s.nom AS site_nom, s.id AS site_id,
            CONCAT(u.prenom,' ',u.nom) AS utilisateur
     FROM equipements e
     LEFT JOIN nomenclatures n ON n.id=e.nomenclature_id
     JOIN sites s ON s.id=e.site_id
     LEFT JOIN users u ON u.id=e.utilisateur_id
     WHERE e.actif=1 AND e.site_id IS NOT NULL
     ORDER BY s.nom, n.libelle
     LIMIT 200"
);

// Équipements non affectés
$non_affectes_count = (int)db_fetch_value(
    "SELECT COUNT(*) FROM equipements WHERE actif=1 AND site_id IS NULL AND etat IN ('neuf','bon')"
);

$sites_list = db_fetch_all("SELECT id,nom,type FROM sites WHERE actif=1 ORDER BY nom");
$users_list = db_fetch_all("SELECT id,prenom,nom FROM users WHERE actif=1 ORDER BY prenom");

include __DIR__ . '/../templates/header.php';
?>
<style>
.aff-layout{display:grid;grid-template-columns:1fr 1.8fr;gap:20px;align-items:start}
.mouv-type{font-size:10px;font-weight:700;padding:3px 8px;border-radius:5px;text-transform:uppercase;letter-spacing:.5px}
.mouv-type.entree    {background:#d5f5e3;color:#1e8449}
.mouv-type.sortie    {background:#fef9e7;color:#9a7d0a}
.mouv-type.transfert {background:#d6eaf8;color:#1a5276}
.mouv-type.reforme   {background:#fdedec;color:#922b21}
.mouv-type.maintenance{background:#faf5ff;color:#6b21a8}
.mouv-row{display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid var(--border)}
.mouv-row:last-child{border-bottom:none}
.mouv-info{flex:1}
.mouv-equip{font-family:monospace;font-weight:700;font-size:13px;color:var(--navy)}
.mouv-detail{font-size:12px;color:var(--muted);margin-top:2px}
.mouv-date{font-size:11px;color:var(--muted);white-space:nowrap}

.affecte-group{margin-bottom:16px}
.affecte-site-title{font-family:'Montserrat',sans-serif;font-size:13px;font-weight:700;color:var(--navy);padding:8px 12px;background:var(--lighter);border-radius:8px;margin-bottom:8px;display:flex;align-items:center;justify-content:space-between}
.affecte-item{display:flex;align-items:center;gap:10px;padding:8px 12px;border:1px solid var(--border);border-radius:8px;background:white;margin-bottom:6px;font-size:13px}
.affecte-item:hover{border-color:var(--blue-mid, #1a56a0)}

.autocomplete-wrap{position:relative}
.autocomplete-results{position:absolute;top:100%;left:0;right:0;background:white;border:1px solid var(--border);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.1);z-index:100;max-height:200px;overflow-y:auto;display:none}
.ac-item{padding:10px 14px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--border)}
.ac-item:last-child{border-bottom:none}
.ac-item:hover{background:var(--lighter)}
.ac-num{font-family:monospace;font-weight:700;color:var(--navy)}
.ac-meta{font-size:11px;color:var(--muted);margin-top:2px}

.modal-overlay{display:none;position:fixed;inset:0;z-index:500;background:rgba(13,31,53,.5);backdrop-filter:blur(4px);align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:white;border-radius:16px;width:560px;max-width:95vw;max-height:92vh;overflow-y:auto;animation:mIn .25s cubic-bezier(.22,1,.36,1)}
@keyframes mIn{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.mhdr{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:white;z-index:10}
.mhdr h3{font-family:'Montserrat',sans-serif;font-size:17px;font-weight:700}
.mclose{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:none;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center}
.mbody{padding:24px}
.mfoot{padding:14px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;position:sticky;bottom:0;background:white}

@media(max-width:1000px){.aff-layout{grid-template-columns:1fr}}
</style>

<div class="aff-layout">

  <!-- COLONNE GAUCHE : FORMULAIRE + AFFECTÉS -->
  <div>
    <!-- FORMULAIRE AFFECTATION -->
    <div class="card" style="margin-bottom:20px">
      <div class="card-header">
        <h3>🔗 Nouvelle affectation / Transfert</h3>
      </div>
      <div class="card-body">
        <div id="affAlert"></div>

        <!-- Recherche équipement -->
        <div class="form-group">
          <label>Équipement * <span style="font-size:11px;color:var(--muted)">(tapez le N° série pour rechercher)</span></label>
          <div class="autocomplete-wrap">
            <input type="text" class="form-control" id="affSearch" placeholder="Tapez le numéro série…" autocomplete="off" oninput="searchEquip(this.value)">
            <div class="autocomplete-results" id="affResults"></div>
          </div>
          <input type="hidden" id="affEquipId">
          <div id="affEquipInfo" style="margin-top:8px;display:none">
            <div style="padding:10px;background:var(--lighter);border-radius:8px;font-size:13px" id="affEquipInfoContent"></div>
          </div>
        </div>

        <div class="form-row cols-2">
          <div class="form-group"><label>Type de mouvement</label>
            <select class="form-control" id="affTypeMouv">
              <option value="entree">Entrée en stock</option>
              <option value="transfert" selected>Transfert / Affectation</option>
              <option value="maintenance">Maintenance</option>
            </select>
          </div>
          <div class="form-group"><label>Site de destination</label>
            <select class="form-control" id="affSite">
              <option value="">— Stock central —</option>
              <?php foreach($sites_list as $s): ?>
              <option value="<?= $s['id'] ?>"><?= h($s['nom']) ?> (<?= $s['type'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group"><label>Utilisateur assigné</label>
          <select class="form-control" id="affUser">
            <option value="">— Non assigné —</option>
            <?php foreach($users_list as $u): ?>
            <option value="<?= $u['id'] ?>"><?= h($u['prenom'].' '.$u['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group"><label>Notes</label>
          <textarea class="form-control" id="affNotes" rows="2" placeholder="Raison du transfert, remarques…"></textarea>
        </div>

        <!-- BON DE LIVRAISON OBLIGATOIRE pour transfert vers site -->
        <div id="affBLWrap" style="display:none;background:#fff8e7;border:2px dashed #f39c12;border-radius:10px;padding:14px;margin-bottom:14px">
          <label style="font-size:13px;font-weight:700;color:#b7791f;display:block;margin-bottom:6px">
            📎 Bon de livraison <span style="color:var(--danger)">*</span>
            <span style="font-size:11px;font-weight:400;color:var(--muted)"> — obligatoire pour tout transfert vers un site</span>
          </label>
          <input type="file" id="affFichierBL" accept=".pdf,.jpg,.jpeg,.png,.webp"
                 style="width:100%;padding:8px;border:1.5px solid #f39c12;border-radius:8px;font-size:13px;background:white">
          <div id="affBLPreview" style="display:none;margin-top:6px;font-size:12px;color:var(--success);font-weight:600"></div>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end">
          <button class="btn btn-secondary" onclick="resetAff()">Réinitialiser</button>
          <button class="btn btn-primary" id="btnAff" onclick="saveAff()">✅ Valider l'affectation</button>
        </div>
      </div>
    </div>

    <!-- STATS RAPIDES -->
    <div class="card">
      <div class="card-header"><h3>📊 Résumé des affectations</h3></div>
      <div class="card-body" style="padding:16px">
        <?php
        $stats_sites = db_fetch_all(
            "SELECT s.nom, COUNT(e.id) AS nb
             FROM sites s JOIN equipements e ON e.site_id=s.id AND e.actif=1
             GROUP BY s.id ORDER BY nb DESC LIMIT 8"
        );
        foreach($stats_sites as $st): $pct=min(100,round($st['nb']/max(1,$kpi_equip_total??1)*100)); ?>
        <div style="margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:3px">
            <span><?= h($st['nom']) ?></span>
            <strong><?= $st['nb'] ?></strong>
          </div>
          <div style="height:5px;background:var(--border);border-radius:3px">
            <div style="width:<?= $pct ?>%;height:100%;background:var(--blue-mid, #1a56a0);border-radius:3px"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);font-size:13px;color:var(--muted)">
          ✅ <strong><?= $non_affectes_count ?></strong> équipement(s) disponible(s) en stock
        </div>
      </div>
    </div>
  </div>

  <!-- COLONNE DROITE : MOUVEMENTS + AFFECTÉS -->
  <div>
    <!-- FILTRES MOUVEMENTS -->
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
      <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;flex:1">
        <select name="site" class="fsel" onchange="this.form.submit()" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;background:white;cursor:pointer;outline:none;font-family:'DM Sans',sans-serif">
          <option value="">Tous les sites</option>
          <?php foreach($sites_list as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $f_site===$s['id']?'selected':'' ?>><?= h($s['nom']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="type_mouv" class="fsel" onchange="this.form.submit()" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;background:white;cursor:pointer;outline:none;font-family:'DM Sans',sans-serif">
          <option value="">Tous types</option>
          <option value="entree"    <?= $f_type==='entree'?'selected':'' ?>>Entrée</option>
          <option value="sortie"    <?= $f_type==='sortie'?'selected':'' ?>>Sortie</option>
          <option value="transfert" <?= $f_type==='transfert'?'selected':'' ?>>Transfert</option>
          <option value="reforme"   <?= $f_type==='reforme'?'selected':'' ?>>Réforme</option>
          <option value="maintenance" <?= $f_type==='maintenance'?'selected':'' ?>>Maintenance</option>
        </select>
        <?php if($f_site||$f_type): ?>
        <a href="affectations.php" style="padding:9px 14px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;background:white;text-decoration:none;color:var(--text)">✕ Reset</a>
        <?php endif; ?>
      </form>
      <?php if(can('affectations','can_export')): ?>
      <a href="<?= APP_URL ?>/api/export.php?type=mouvements" class="btn btn-secondary btn-sm">📥 Excel</a>
      <?php endif; ?>
    </div>

    <!-- HISTORIQUE DES MOUVEMENTS -->
    <div class="card" style="margin-bottom:20px">
      <div class="card-header">
        <h3>📋 Historique des mouvements <span style="font-size:13px;font-weight:400;color:var(--muted)">(<?= fmt_number($total) ?>)</span></h3>
      </div>
      <div style="padding:4px 20px">
        <?php if(empty($mouvements)): ?>
        <div style="text-align:center;padding:40px;color:var(--muted)">Aucun mouvement enregistré.</div>
        <?php else: foreach($mouvements as $m): ?>
        <div class="mouv-row">
          <span class="mouv-type <?= $m['type'] ?>"><?= $m['type'] ?></span>
          <div class="mouv-info">
            <div class="mouv-equip"><?= h($m['numero_serie_interne']) ?> <span style="font-family:'DM Sans';font-size:12px;font-weight:400;color:var(--muted)">· <?= h($m['type_equip']) ?></span></div>
            <div class="mouv-detail">
              <?php if($m['site_source']): ?>De : <strong><?= h($m['site_source']) ?></strong><?php endif; ?>
              <?php if($m['site_dest']): ?> → <strong><?= h($m['site_dest']) ?></strong><?php endif; ?>
              <?php if($m['user_dest']): ?> · 👤 <?= h($m['user_dest']) ?><?php endif; ?>
              <?php if($m['notes']): ?><br><em><?= h($m['notes']) ?></em><?php endif; ?>
              <?php if(!empty($m['fichier_bl'])): ?>
              <br><?= upload_link($m['fichier_bl'],'bl','📎 Bon de livraison') ?>
              <?php endif; ?>
            </div>
            <div class="mouv-detail">par <?= h($m['agent'] ?? 'Système') ?></div>
          </div>
          <div class="mouv-date"><?= fmt_datetime($m['created_at']) ?></div>
          <?php if(can('affectations','can_update') && in_array($m['type'],['entree','transfert'])): ?>
          <button class="btn btn-secondary btn-sm" onclick="openRetour(<?= $m['equipement_id'] ?>,'<?= h($m['numero_serie_interne']) ?>')" title="Retour stock">↩</button>
          <?php endif; ?>
        </div>
        <?php endforeach; endif; ?>
      </div>
      <?= pagination($total,$per_page,$page,'affectations.php?'.http_build_query(['site'=>$f_site,'type_mouv'=>$f_type])) ?>
    </div>
  </div>
</div>

<!-- MODAL RETOUR STOCK -->
<div class="modal-overlay" id="mRetour">
  <div class="modal">
    <div class="mhdr"><h3>↩ Retour en stock</h3><button class="mclose" onclick="document.getElementById('mRetour').classList.remove('open')">✕</button></div>
    <div class="mbody">
      <input type="hidden" id="rEquipId">
      <div class="alert alert-warning">L'équipement <strong id="rEquipNum"></strong> sera retiré de son site et remis en stock central.</div>
      <div class="form-group" style="margin-top:16px"><label>Motif du retour</label>
        <textarea class="form-control" id="rNotes" rows="3" placeholder="Raison du retour…"></textarea>
      </div>
    </div>
    <div class="mfoot">
      <button class="btn btn-secondary" onclick="document.getElementById('mRetour').classList.remove('open')">Annuler</button>
      <button class="btn btn-primary" onclick="saveRetour()">↩ Confirmer le retour</button>
    </div>
  </div>
</div>

<script>
let searchTimer;
function searchEquip(q){
  clearTimeout(searchTimer);
  if(q.length<2){document.getElementById('affResults').style.display='none';return;}
  searchTimer=setTimeout(()=>{
    ap({action:'search_equip',q}).then(d=>{
      const res=document.getElementById('affResults');
      if(!d.success||!d.data.length){res.style.display='none';return;}
      res.innerHTML=d.data.map(r=>`
        <div class="ac-item" onclick="selectEquip(${r.id},'${r.numero_serie_interne}','${r.type}','${r.site_actuel||'Stock'}','${r.user_actuel||'—'}','${r.etat}')">
          <div class="ac-num">${r.numero_serie_interne} <span style="font-weight:400;color:var(--muted)">· ${r.type}</span></div>
          <div class="ac-meta">📍 ${r.site_actuel||'Stock'} · 👤 ${r.user_actuel||'Non assigné'} · ${r.etat}</div>
        </div>`).join('');
      res.style.display='block';
    });
  },300);
}

function selectEquip(id,num,type,site,user,etat){
  document.getElementById('affEquipId').value=id;
  document.getElementById('affSearch').value=num;
  document.getElementById('affResults').style.display='none';
  document.getElementById('affEquipInfo').style.display='block';
  document.getElementById('affEquipInfoContent').innerHTML=`
    <strong>${num}</strong> · ${type}<br>
    📍 Actuellement : <strong>${site}</strong> · 👤 ${user} · État : <strong>${etat}</strong>`;
}

function resetAff(){
  document.getElementById('affSearch').value=''; document.getElementById('affEquipId').value='';
  document.getElementById('affEquipInfo').style.display='none'; document.getElementById('affResults').style.display='none';
  document.getElementById('affSite').value=''; document.getElementById('affUser').value='';
  document.getElementById('affNotes').value=''; document.getElementById('affTypeMouv').value='transfert';
  document.getElementById('affAlert').innerHTML='';
  document.getElementById('affFichierBL').value='';
  document.getElementById('affBLPreview').style.display='none';
  document.getElementById('affBLWrap').style.display='none';
  document.getElementById('btnAff').disabled=false;
}

// Afficher/masquer le champ BL selon le type de mouvement et le site
function toggleBLField(){
  const type = document.getElementById('affTypeMouv').value;
  const site = document.getElementById('affSite').value;
  const wrap = document.getElementById('affBLWrap');
  wrap.style.display = (site && (type==='transfert'||type==='sortie')) ? 'block' : 'none';
}
document.getElementById('affTypeMouv').addEventListener('change', toggleBLField);
document.getElementById('affSite').addEventListener('change', toggleBLField);

document.getElementById('affFichierBL').addEventListener('change',function(){
  const prev=document.getElementById('affBLPreview');
  if(this.files.length){
    prev.style.display='block';
    prev.textContent='✔ '+this.files[0].name+' ('+(this.files[0].size/1024).toFixed(1)+' Ko)';
  } else { prev.style.display='none'; }
});

async function saveAff(){
  const eid  = document.getElementById('affEquipId').value;
  const site = document.getElementById('affSite').value;
  const type = document.getElementById('affTypeMouv').value;
  if(!eid){document.getElementById('affAlert').innerHTML='<div class="alert alert-danger">Sélectionnez un équipement.</div>';return;}

  // Vérifier BL si transfert vers site
  const fichierBL = document.getElementById('affFichierBL').files[0];
  if(site && (type==='transfert'||type==='sortie') && !fichierBL){
    document.getElementById('affAlert').innerHTML='<div class="alert alert-danger">⚠️ Le bon de livraison est obligatoire pour un transfert vers un site.</div>';
    document.getElementById('affFichierBL').focus(); return;
  }

  const btn = document.getElementById('btnAff');
  btn.disabled=true; btn.textContent='⏳ En cours…';

  const fd = new FormData();
  fd.append('action','affecter');
  fd.append('equipement_id',eid);
  fd.append('site_id',site);
  fd.append('utilisateur_id',document.getElementById('affUser').value);
  fd.append('notes',document.getElementById('affNotes').value);
  fd.append('type_mouvement',type);
  if(fichierBL) fd.append('fichier_bl',fichierBL);

  try {
    const r = await fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
    const d = await r.json();
    btn.disabled=false; btn.textContent='✅ Valider l\'affectation';
    if(d.success){toast(d.message,'success');resetAff();setTimeout(()=>location.reload(),1000);}
    else document.getElementById('affAlert').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;
  } catch(e){
    btn.disabled=false; btn.textContent='✅ Valider l\'affectation';
    document.getElementById('affAlert').innerHTML='<div class="alert alert-danger">Erreur réseau.</div>';
  }
}

function openRetour(id,num){
  document.getElementById('rEquipId').value=id;
  document.getElementById('rEquipNum').textContent=num;
  document.getElementById('rNotes').value='';
  document.getElementById('mRetour').classList.add('open');
}
function saveRetour(){
  ap({action:'retour',equipement_id:document.getElementById('rEquipId').value,notes:document.getElementById('rNotes').value})
    .then(d=>{toast(d.message,d.success?'success':'danger');if(d.success){document.getElementById('mRetour').classList.remove('open');setTimeout(()=>location.reload(),900);}});
}

// Fermer dropdown au clic dehors
document.addEventListener('click',e=>{
  if(!e.target.closest('.autocomplete-wrap'))document.getElementById('affResults').style.display='none';
});

function ap(data){
  const fd=new FormData();
  for(const[k,v]of Object.entries(data))if(v!==undefined)fd.append(k,v);
  return fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(r=>r.json());
}
document.getElementById('mRetour').addEventListener('click',e=>{if(e.target===e.currentTarget)e.currentTarget.classList.remove('open');});
</script>

<?php
// KPI pour les stats (si pas encore défini)
$kpi_equip_total = $kpi_equip_total ?? (int)db_fetch_value("SELECT COUNT(*) FROM equipements WHERE actif=1");
include __DIR__ . '/../templates/footer.php';
?>
