<?php
// ============================================================
//  pages/inventaire_bobines.php — Inventaire Bobines v2
//  Traçabilité complète du film (journalier + mensuel)
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php';

require_auth();
invalidate_user_cache();

$user      = current_user();
$role_slug = $user['role_slug'] ?? '';
$is_coord  = ($role_slug === 'coordinateur_site');
$site_force = ($is_coord && $user['site_id']) ? (int)$user['site_id'] : 0;

$roles_autorises_inv = ['coordinateur_site','gestionnaire_stock_bobines','superviseur_operation','admin','superadmin'];
if (!in_array($role_slug, $roles_autorises_inv)) {
    http_response_code(403);
    include __DIR__ . '/../templates/403.php';
    exit;
}

$page_title   = 'Inventaire Bobines';
$active_page  = 'inventaire_bobines';
$sites_list   = db_fetch_all("SELECT id,nom FROM sites WHERE actif=1 ORDER BY nom");
$can_validate = in_array($role_slug, ['admin','superadmin','superviseur_operation']);
$can_create   = can('inventaire_bobines','can_create');

// ============================================================
//  AJAX
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_ajax()) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'creer') {
        require_permission('inventaire_bobines','can_create');
        $site_id = $site_force ?: ((int)($_POST['site_id'] ?? 0) ?: null);
        $date    = trim($_POST['date']  ?? date('Y-m-d'));
        $type    = in_array($_POST['type']??'', ['journalier','mensuel']) ? $_POST['type'] : 'journalier';
        $notes   = trim($_POST['notes'] ?? '');

        if (!$site_id) json_response(false,'Site obligatoire.');

        $exist = db_fetch_one(
            "SELECT id FROM inventaires_bobines WHERE site_id=? AND date_inventaire=? AND type_inventaire=? AND statut!='annule'",
            [$site_id, $date, $type]
        );
        if ($exist) json_response(false, "Un inventaire $type existe déjà pour ce site à cette date.");

        $bobines = db_fetch_all(
            "SELECT b.id, b.numero, b.type_code, b.serie, b.stock_systeme
             FROM op_bobines b
             WHERE b.site_id=? AND b.statut NOT IN ('retiree','epuisee')
             ORDER BY b.serie, b.type_code, b.numero",
            [$site_id]
        );
        if (empty($bobines)) json_response(false,'Aucune bobine active sur ce site.');

        db_begin();
        try {
            $total_systeme = array_sum(array_column($bobines,'stock_systeme'));
            $films_emuci   = (int)db_fetch_value("SELECT COALESCE(SUM(plaques_posees - poses_ex_reservees + plaques_reservees),0) FROM points_emuci WHERE site_id=? AND date_point=?", [$site_id, $date]);
            $films_digi    = (int)db_fetch_value("SELECT COALESCE(SUM(total_plaques),0) FROM op_points_journaliers WHERE site_id=? AND date_point=?", [$site_id, $date]);

            db_query(
                "INSERT INTO inventaires_bobines (site_id,date_inventaire,type_inventaire,statut,nb_bobines,notes,total_films_systeme,total_films_emuci,ecart_digistock_emuci,cree_par)
                 VALUES (?,?,?,'brouillon',?,?,?,?,?,?)",
                [$site_id,$date,$type,count($bobines),$notes,$total_systeme,$films_emuci,($films_emuci-$films_digi),$user['id']]
            );
            $inv_id = (int)db_last_id();

            foreach ($bobines as $b) {
                $conso_moy = (float)db_fetch_value(
                    "SELECT COALESCE(SUM(quantite)/GREATEST(DATEDIFF(NOW(),MIN(date_conso)),1),0) FROM consommations_bobines WHERE bobine_id=? AND date_conso>=DATE_SUB(CURDATE(),INTERVAL 30 DAY)",
                    [$b['id']]
                );
                $ecart_connu = (int)db_fetch_value("SELECT COALESCE(SUM(ecart),0) FROM ecarts_bobines WHERE bobine_id=? AND statut='ouvert'", [$b['id']]);
                db_query(
                    "INSERT INTO inventaire_details_bobines (inventaire_id,bobine_id,stock_systeme,qte_temps_reel,stock_physique,ecart,ecart_connu_avant,conso_quotidienne_moy)
                     VALUES (?,?,?,?,0,0,?,?)",
                    [$inv_id,$b['id'],(int)$b['stock_systeme'],(int)$b['stock_systeme'],$ecart_connu,round($conso_moy,2)]
                );
            }
            audit_log($user['id'],'CREATE','inventaire_bobines',$inv_id,"Création inventaire $type $date site:$site_id — ".count($bobines)." bobines");
            db_commit();
            json_response(true,'Inventaire créé.',['id'=>$inv_id,'nb'=>count($bobines),'type'=>$type]);
        } catch(Exception $e){ db_rollback(); json_response(false,'Erreur: '.$e->getMessage()); }
    }

    if ($action === 'valider') {
        if (!$can_validate) json_response(false,'Accès refusé.');
        $inv_id = (int)($_POST['inv_id'] ?? 0);
        $inv = db_fetch_one("SELECT * FROM inventaires_bobines WHERE id=?",[$inv_id]);
        if (!$inv || $inv['statut'] !== 'brouillon') json_response(false,'Inventaire introuvable ou déjà validé.');

        $lignes = db_fetch_all("SELECT * FROM inventaire_details_bobines WHERE inventaire_id=?",[$inv_id]);
        $nb_ecarts = 0; $total_physique = 0;

        db_begin();
        try {
            foreach ($lignes as $l) {
                if ($l['stock_physique'] == 0) continue;
                $total_physique += (int)$l['stock_physique'];
                // Fermer les anciens écarts ouverts
                db_query("UPDATE ecarts_bobines SET statut='resolu',resolu_at=NOW(),resolu_par=?,resolution_notes=? WHERE bobine_id=? AND statut='ouvert'",
                    [$user['id'],"Résolu par inventaire #{$inv_id}",$l['bobine_id']]);
                if ($l['ecart'] != 0) {
                    $nb_ecarts++;
                    $phy = (int)$l['stock_physique'];
                    db_query("UPDATE op_bobines SET stock_systeme=?,films_restants=?,statut=IF(?>0,IF(statut='retiree','retiree','en_cours'),'epuisee') WHERE id=?",[$phy,$phy,$phy,$l['bobine_id']]);
                    db_query("INSERT INTO mouvements_bobines (bobine_id,type,quantite,stock_avant,stock_apres,motif,ref_id,created_by) VALUES (?,?,?,?,?,?,?,?)",
                        [$l['bobine_id'],'ajustement_inventaire',$l['ecart'],$l['stock_systeme'],$phy,"Inventaire #{$inv_id} ({$inv['type_inventaire']})",$inv_id,$user['id']]);
                    db_query("INSERT INTO ecarts_bobines (bobine_id,date_constat,stock_systeme,stock_physique,ecart,motif,source,inventaire_id,statut,resolu_at,resolu_par,created_by) VALUES (?,?,?,?,?,?,?,?,'resolu',NOW(),?,?)",
                        [$l['bobine_id'],$inv['date_inventaire'],$l['stock_systeme'],$phy,$l['ecart'],$l['notes']??'','inventaire',$inv_id,$user['id'],$user['id']]);
                }
            }
            if ($inv['type_inventaire'] === 'mensuel') {
                $mois = date('Y-m', strtotime($inv['date_inventaire']));
                db_query("INSERT INTO bilans_mensuels_bobines (site_id,mois,inventaire_id,stock_fin_mois,nb_ajustements,statut,valide_par,valide_at) VALUES (?,?,?,?,?,'valide',?,NOW()) ON DUPLICATE KEY UPDATE stock_fin_mois=VALUES(stock_fin_mois),nb_ajustements=nb_ajustements+VALUES(nb_ajustements),statut='valide',valide_par=VALUES(valide_par),valide_at=NOW()",
                    [$inv['site_id'],$mois,$inv_id,$total_physique,$nb_ecarts,$user['id']]);
            }
            db_query("UPDATE inventaires_bobines SET statut='valide',nb_ecarts=?,total_films_physique=?,valide_par=?,valide_at=NOW() WHERE id=?",
                [$nb_ecarts,$total_physique,$user['id'],$inv_id]);
            audit_log($user['id'],'UPDATE','inventaire_bobines',$inv_id,"Validation inventaire #{$inv_id} ({$inv['type_inventaire']}) — {$nb_ecarts} écart(s)");
            db_commit();
            json_response(true,"Inventaire validé. {$nb_ecarts} écart(s) ajusté(s).",['nb_ecarts'=>$nb_ecarts]);
        } catch(Exception $e){ db_rollback(); json_response(false,'Erreur: '.$e->getMessage()); }
    }

    json_response(false,'Action inconnue.');
}

// ============================================================
//  DONNÉES
// ============================================================
$f_site = $site_force ?: (int)($_GET['site'] ?? 0);
$f_type = trim($_GET['type'] ?? '');
$f_mois = trim($_GET['mois'] ?? '');

$where = ['1=1']; $params = [];
if ($site_force)  { $where[] = 'i.site_id=?'; $params[] = $site_force; }
elseif ($f_site)  { $where[] = 'i.site_id=?'; $params[] = $f_site; }
if ($f_type)      { $where[] = 'i.type_inventaire=?'; $params[] = $f_type; }
if ($f_mois)      { $where[] = "DATE_FORMAT(i.date_inventaire,'%Y-%m')=?"; $params[] = $f_mois; }

$inventaires = db_fetch_all(
    "SELECT i.*, s.nom AS site_nom, CONCAT(u.prenom,' ',u.nom) AS createur
     FROM inventaires_bobines i
     LEFT JOIN sites s ON s.id=i.site_id
     LEFT JOIN users u ON u.id=i.cree_par
     WHERE " . implode(' AND ',$where) . "
     ORDER BY i.date_inventaire DESC, i.created_at DESC LIMIT 100",
    $params
);

$nb_journalier = count(array_filter($inventaires, fn($i)=>$i['type_inventaire']==='journalier'));
$nb_mensuel    = count(array_filter($inventaires, fn($i)=>$i['type_inventaire']==='mensuel'));
$nb_valides    = count(array_filter($inventaires, fn($i)=>$i['statut']==='valide'));
$nb_brouillon  = count(array_filter($inventaires, fn($i)=>$i['statut']==='brouillon'));

include __DIR__ . '/../templates/header.php';
?>
<style>
.inv-kpi{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
.ik{background:white;border:1px solid var(--border);border-radius:14px;padding:16px 20px;border-left:4px solid var(--blue)}
.ik.green{border-left-color:var(--success)} .ik.orange{border-left-color:#f39c12} .ik.purple{border-left-color:#8e44ad}
.ik-val{font-family:'Plus Jakarta Sans',sans-serif;font-size:28px;font-weight:900;color:var(--navy);line-height:1}
.ik-lbl{font-size:11px;color:var(--muted);margin-top:4px;font-weight:600;text-transform:uppercase;letter-spacing:.4px}
.type-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
.type-journalier{background:#e8f4f9;color:var(--blue)} .type-mensuel{background:#f3e8ff;color:#6b21a8}
.statut-inv{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
.statut-brouillon{background:#fef9e7;color:#9a6c00} .statut-valide{background:#d5f5e3;color:#1e7e40}
.fsel{padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;background:white;outline:none;cursor:pointer}
</style>

<div class="inv-kpi">
  <div class="ik"><div class="ik-val"><?= $nb_journalier ?></div><div class="ik-lbl">Journaliers</div></div>
  <div class="ik purple"><div class="ik-val" style="color:#6b21a8"><?= $nb_mensuel ?></div><div class="ik-lbl">Mensuels</div></div>
  <div class="ik green"><div class="ik-val" style="color:var(--success)"><?= $nb_valides ?></div><div class="ik-lbl">Validés</div></div>
  <div class="ik orange"><div class="ik-val" style="color:#f39c12"><?= $nb_brouillon ?></div><div class="ik-lbl">En cours</div></div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <?php if(!$site_force): ?>
    <select name="site" class="fsel" onchange="this.form.submit()">
      <option value="">Tous les sites</option>
      <?php foreach($sites_list as $s): ?>
      <option value="<?= $s['id'] ?>" <?= $f_site==$s['id']?'selected':'' ?>><?= h($s['nom']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <select name="type" class="fsel" onchange="this.form.submit()">
      <option value="">Tous types</option>
      <option value="journalier" <?= $f_type==='journalier'?'selected':'' ?>>📅 Journalier</option>
      <option value="mensuel"    <?= $f_type==='mensuel'?'selected':'' ?>>📆 Mensuel</option>
    </select>
    <input type="month" name="mois" value="<?= h($f_mois) ?>" onchange="this.form.submit()"
           style="padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;outline:none">
  </form>
  <?php if($can_create): ?>
  <button class="btn btn-primary" onclick="ouvrirModal()">+ Nouvel inventaire</button>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header"><h3>📋 Inventaires <span style="font-size:13px;font-weight:400;color:var(--muted)">(<?= count($inventaires) ?>)</span></h3></div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Date</th><th>Type</th><th>Site</th>
        <th style="text-align:center">Bobines</th>
        <th style="text-align:center">Stock système</th>
        <th style="text-align:center">Stock physique</th>
        <th style="text-align:center">Écarts</th>
        <th style="text-align:center">EMUCI vs DigiStock</th>
        <th>Statut</th><th>Créé par</th>
        <th style="text-align:center">Actions</th>
      </tr></thead>
      <tbody>
      <?php if(empty($inventaires)): ?>
        <tr><td colspan="11" style="text-align:center;padding:40px;color:var(--muted)">Aucun inventaire.</td></tr>
      <?php else: foreach($inventaires as $inv):
        $ecart_emuci = (int)($inv['ecart_digistock_emuci']??0);
      ?>
        <tr>
          <td style="font-weight:700;white-space:nowrap"><?= fmt_date($inv['date_inventaire'],'d/m/Y') ?></td>
          <td><span class="type-badge type-<?= $inv['type_inventaire'] ?>"><?= $inv['type_inventaire']==='mensuel'?'📆 Mensuel':'📅 Journalier' ?></span></td>
          <td style="font-weight:600"><?= h($inv['site_nom']??'Global') ?></td>
          <td style="text-align:center;font-weight:700"><?= $inv['nb_bobines'] ?></td>
          <td style="text-align:center;font-weight:700"><?= fmt_number($inv['total_films_systeme']??0) ?></td>
          <td style="text-align:center;font-weight:700;color:<?= ($inv['total_films_physique']??0)>0?'var(--success)':'var(--muted)' ?>">
            <?= ($inv['total_films_physique']??0)>0 ? fmt_number($inv['total_films_physique']) : '—' ?>
          </td>
          <td style="text-align:center">
            <?php if($inv['nb_ecarts']>0): ?>
            <span style="font-weight:800;color:#e74c3c"><?= $inv['nb_ecarts'] ?></span>
            <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
          </td>
          <td style="text-align:center">
            <?php if($ecart_emuci!=0): ?>
            <span style="font-weight:700;color:<?= $ecart_emuci>0?'#e74c3c':'#f39c12' ?>"><?= $ecart_emuci>0?'+':'' ?><?= $ecart_emuci ?> films</span>
            <?php else: ?><span class="badge badge-success" style="font-size:11px">✅ OK</span><?php endif; ?>
          </td>
          <td><span class="statut-inv statut-<?= $inv['statut'] ?>"><?= $inv['statut']==='valide'?'✅ Validé':'⏳ En cours' ?></span></td>
          <td style="font-size:12px;color:var(--muted)"><?= h($inv['createur']??'—') ?></td>
          <td style="text-align:center;white-space:nowrap;display:flex;gap:4px;justify-content:center">
            <a href="inventaire_detail.php?id=<?= $inv['id'] ?>" class="btn btn-secondary btn-sm">👁 Détail</a>
            <?php if($inv['statut']==='brouillon' && $can_validate): ?>
            <button class="btn btn-success btn-sm" onclick="valider(<?= $inv['id'] ?>)">✅ Valider</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL -->
<div id="modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center">
  <div style="background:white;border-radius:20px;padding:30px;width:520px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.25)">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:22px">
      <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-size:17px;font-weight:800;color:var(--navy)">📦 Nouvel inventaire</h3>
      <button onclick="fermer()" style="background:none;border:none;font-size:22px;cursor:pointer">✕</button>
    </div>
    <div id="mAlert"></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px">
      <button id="btnJ" class="btn btn-primary" onclick="setType('journalier')" style="justify-content:center;flex-direction:column;padding:14px;gap:4px">
        <span>📅 Journalier</span><small style="font-weight:400;opacity:.8">Suivi quotidien</small>
      </button>
      <button id="btnM" class="btn btn-secondary" onclick="setType('mensuel')" style="justify-content:center;flex-direction:column;padding:14px;gap:4px">
        <span>📆 Mensuel</span><small style="font-weight:400;opacity:.8">Bilan fin de mois</small>
      </button>
    </div>
    <input type="hidden" id="fType" value="journalier">
    <div style="display:grid;grid-template-columns:<?= $site_force?'1fr':'1fr 1fr' ?>;gap:14px;margin-bottom:14px">
      <?php if(!$site_force): ?>
      <div class="form-group">
        <label>Site *</label>
        <select class="form-control" id="fSite">
          <option value="">— Sélectionner —</option>
          <?php foreach($sites_list as $s): ?>
          <option value="<?= $s['id'] ?>"><?= h($s['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php else: ?>
      <input type="hidden" id="fSite" value="<?= $site_force ?>">
      <?php endif; ?>
      <div class="form-group"><label>Date *</label><input type="date" class="form-control" id="fDate" value="<?= date('Y-m-d') ?>"></div>
    </div>
    <div class="form-group" style="margin-bottom:20px"><label>Notes</label><textarea class="form-control" id="fNotes" rows="2"></textarea></div>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button class="btn btn-secondary" onclick="fermer()">Annuler</button>
      <button class="btn btn-primary" id="btnCreer" onclick="creer()">🚀 Créer</button>
    </div>
  </div>
</div>

<script>
function ap(d){return fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(d)}).then(r=>r.json());}
function toast(m,t='success'){const el=document.createElement('div');el.style.cssText=`position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:12px;font-size:13px;font-weight:600;background:${t==='success'?'#27ae60':'#e74c3c'};color:white`;el.textContent=m;document.body.appendChild(el);setTimeout(()=>el.remove(),3500);}
function ouvrirModal(){document.getElementById('fDate').value='<?= date('Y-m-d') ?>';document.getElementById('fNotes').value='';document.getElementById('mAlert').innerHTML='';setType('journalier');document.getElementById('modal').style.display='flex';}
function fermer(){document.getElementById('modal').style.display='none';}
document.getElementById('modal').addEventListener('click',e=>{if(e.target===document.getElementById('modal'))fermer();});
function setType(t){document.getElementById('fType').value=t;document.getElementById('btnJ').className='btn '+(t==='journalier'?'btn-primary':'btn-secondary');document.getElementById('btnM').className='btn '+(t==='mensuel'?'btn-primary':'btn-secondary');}
async function creer(){
  const site=document.getElementById('fSite').value,date=document.getElementById('fDate').value;
  if(!site||!date){document.getElementById('mAlert').innerHTML='<div class="alert alert-danger">Site et date obligatoires.</div>';return;}
  const btn=document.getElementById('btnCreer');btn.disabled=true;btn.textContent='⏳...';
  const d=await ap({action:'creer',site_id:site,date,type:document.getElementById('fType').value,notes:document.getElementById('fNotes').value});
  btn.disabled=false;btn.textContent='🚀 Créer';
  if(d.success){toast(`Inventaire créé — ${d.data.nb} bobines`);fermer();setTimeout(()=>location.href=`inventaire_detail.php?id=${d.data.id}`,600);}
  else document.getElementById('mAlert').innerHTML=`<div class="alert alert-danger">${d.message}</div>`;
}
async function valider(id){
  if(!confirm('Valider cet inventaire ? Le stock système sera ajusté avec les valeurs physiques.'))return;
  const d=await ap({action:'valider',inv_id:id});
  if(d.success){toast(d.message);setTimeout(()=>location.reload(),800);}else toast(d.message,'error');
}
</script>
<?php include __DIR__ . '/../templates/footer.php'; ?>
