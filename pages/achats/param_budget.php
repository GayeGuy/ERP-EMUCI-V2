<?php
// ============================================================
//  pages/achats/param_budget.php — Lignes budgétaires par département
// ============================================================
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/notifications.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/achats.php';

require_auth();
$user = current_user();
require_permission('achats_param', 'can_read');
// can_update sur achats_param est désormais aussi porté par RAF/DAF (saisie
// de leur budget) — le verrou par ligne (ach_budget_editable) et le
// périmètre département (ach_perimetre_departements) sont appliqués plus
// bas, pas ici : ce flag ne dit que "peut éditer des lignes en général".
$can_edit    = can('achats_param', 'can_update');
$can_valider = ach_est_validateur_budget($user);
$is_admin    = in_array($user['role_slug'] ?? '', ['admin', 'superadmin'], true);
$_SESSION['groupe_actif'] = 'ACHATS';
$page_title  = 'Lignes budgétaires';
$active_page = 'achats_param_budget';

// ── Modèle CSV de l'import — téléchargement direct, hors flux AJAX.
if (($_GET['template'] ?? '') === 'csv') {
    if (!$can_edit) { http_response_code(403); exit; }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="modele_lignes_budgetaires.csv"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8, pour qu'Excel n'affiche pas les accents en charabia
    // Virgule, pas point-virgule : c'est le séparateur par défaut du lecteur
    // CSV de PhpSpreadsheet côté import_preview, non reconfiguré.
    $out = fopen('php://output', 'w');
    fputcsv($out, ['departement', 'famille', 'enveloppe', 'comportement']);
    fputcsv($out, ['ADMINISTRATION', 'Fournitures de bureau', '500000', 'alerte']);
    fclose($out);
    exit;
}

$COMPORTEMENTS = ['aucun' => 'Aucun contrôle', 'alerte' => 'Alerte au dépassement', 'blocage' => 'Blocage au dépassement'];
$STATUTS_BUDGET = [
    'brouillon' => ['label' => 'Brouillon',        'bg' => '#F1F5F9', 'color' => '#475569'],
    'soumis'    => ['label' => 'Soumis — en attente','bg' => '#FEF3C7','color' => '#92400E'],
    'valide'    => ['label' => 'Validé — verrouillé','bg' => '#D1FAE5','color' => '#065F46'],
    'rejete'    => ['label' => 'Rejeté',            'bg' => '#FEE2E2', 'color' => '#991B1B'],
];

// ── AJAX ────────────────────────────────────────────────────
if (is_ajax() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'get_contributions') {
        $departement_id = (int)($_POST['departement_id'] ?? 0);
        $famille_id     = (int)($_POST['famille_id'] ?? 0);
        $exercice       = (int)($_POST['exercice'] ?? 0);
        $rows = db_fetch_all(
            "SELECT f.numero, f.objet, f.statut, fl.designation, fl.montant_ttc,
                    a.libelle AS article_libelle
             FROM feb_lignes fl
             JOIN feb f ON f.id = fl.feb_id
             LEFT JOIN articles a ON a.id = fl.article_id
             WHERE f.departement_id=? AND fl.famille_id=? AND f.exercice=? AND fl.arbitrage='achat'
               AND f.statut IN ('confirmee','en_validation')
             ORDER BY f.numero",
            [$departement_id, $famille_id, $exercice]
        );
        json_response(true, '', $rows);
    }

    // ── Cycle de validation du budget (département × exercice) — actions
    //    propres (ach_soumettre_budget/ach_valider_budget/...) portant leur
    //    propre contrôle d'habilitation : placées AVANT la porte can_edit
    //    ci-dessous, sinon le PDG (lecture seule sur achats_param, jamais de
    //    can_update) ne pourrait jamais valider ni rejeter.
    if ($action === 'soumettre') {
        if (!$can_edit) json_response(false, 'Action réservée.');
        try {
            ach_soumettre_budget((int)($_POST['departement_id'] ?? 0), (int)($_POST['exercice'] ?? 0), $user);
            json_response(true, 'Budget soumis au PDG pour validation.');
        } catch (AchValidationException $e) { json_response(false, $e->getMessage()); }
    }

    if ($action === 'valider_budget') {
        try {
            ach_valider_budget((int)($_POST['departement_id'] ?? 0), (int)($_POST['exercice'] ?? 0), $user);
            json_response(true, 'Budget validé et verrouillé.');
        } catch (AchValidationException $e) { json_response(false, $e->getMessage()); }
    }

    if ($action === 'rejeter_budget') {
        try {
            ach_rejeter_budget((int)($_POST['departement_id'] ?? 0), (int)($_POST['exercice'] ?? 0), trim($_POST['motif'] ?? ''), $user);
            json_response(true, 'Budget rejeté — renvoyé en brouillon.');
        } catch (AchValidationException $e) { json_response(false, $e->getMessage()); }
    }

    if ($action === 'reouvrir_budget') {
        try {
            ach_admin_reouvrir_budget((int)($_POST['departement_id'] ?? 0), (int)($_POST['exercice'] ?? 0), $user);
            json_response(true, 'Budget rouvert — de nouveau modifiable.');
        } catch (AchValidationException $e) { json_response(false, $e->getMessage()); }
    }

    if (!$can_edit) json_response(false, 'Action réservée.');

    // Périmètre RAF/DAF — même fonction que partout ailleurs dans le module
    // (dashboard, suivi). null = portée globale (admin/superviseur_achat/
    // RAF-DAF sans département explicite).
    $perimetre = ach_perimetre_departements($user);

    if ($action === 'save') {
        $id             = (int)($_POST['id'] ?? 0);
        $departement_id = (int)($_POST['departement_id'] ?? 0);
        $famille_id     = (int)($_POST['famille_id'] ?? 0);
        $exercice       = (int)($_POST['exercice'] ?? 0);
        $envRaw         = trim($_POST['enveloppe'] ?? '');
        $env            = $envRaw === '' ? null : (int)$envRaw;
        $comport        = $_POST['comportement'] ?? 'aucun';

        if (!$departement_id || !$famille_id || !$exercice) json_response(false, 'Département, famille et exercice sont obligatoires.');
        if (!isset($COMPORTEMENTS[$comport])) json_response(false, 'Comportement invalide.');
        if (is_array($perimetre) && !in_array($departement_id, $perimetre, true)) {
            json_response(false, "Ce département n'est pas dans votre périmètre.");
        }
        if (!ach_budget_editable($departement_id, $exercice)) {
            json_response(false, 'Ce budget est soumis ou validé — il ne peut plus être modifié ' .
                ($is_admin ? "sans réouverture administrative." : "tant qu'il n'est pas rejeté ou réouvert."));
        }

        // Le sélecteur département se limite déjà aux départements actifs
        // côté écran (point 11) ; contrôle serveur redondant ici.
        $dept = db_fetch_one("SELECT * FROM departements WHERE id=? AND actif=1", [$departement_id]);
        if (!$dept) json_response(false, 'Département introuvable ou inactif.');
        $fam = db_fetch_one("SELECT * FROM familles_achat WHERE id=? AND actif=1", [$famille_id]);
        if (!$fam) json_response(false, 'Famille introuvable ou inactive.');

        // code_comptable/designation ne sont plus saisis : ils dérivent
        // toujours de la famille choisie (compte SYSCOHADA ← famille_achat),
        // jamais d'une valeur tapée indépendamment qui pourrait diverger.
        $code_comptable = $fam['compte_comptable'] ?: $fam['code'];
        $designation    = $dept['label'] . ' — ' . $fam['libelle'];

        try {
            if ($id) {
                db_query(
                    "UPDATE lignes_budgetaires SET departement_id=?, famille_id=?, exercice=?, code_comptable=?, designation=?, enveloppe=?, comportement=? WHERE id=?",
                    [$departement_id, $famille_id, $exercice, $code_comptable, $designation, $env, $comport, $id]
                );
                audit_log($user['id'], 'UPDATE', 'achats_param', $id, "Modification ligne budgétaire : {$dept['label']} / {$fam['libelle']} ($exercice)");
                json_response(true, 'Ligne mise à jour.');
            } else {
                db_query(
                    "INSERT INTO lignes_budgetaires (departement_id, famille_id, exercice, code_comptable, designation, enveloppe, comportement) VALUES (?,?,?,?,?,?,?)",
                    [$departement_id, $famille_id, $exercice, $code_comptable, $designation, $env, $comport]
                );
                $newId = (int)db_last_id('lignes_budgetaires_id_seq');
                audit_log($user['id'], 'CREATE', 'achats_param', $newId, "Création ligne budgétaire : {$dept['label']} / {$fam['libelle']} ($exercice)");
                json_response(true, 'Ligne créée.', ['id' => $newId]);
            }
        } catch (Exception $e) {
            json_response(false, 'Une ligne budgétaire existe déjà pour ce département, cette famille et cet exercice.');
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $nv = (int)($_POST['actif'] ?? 0) ? 1 : 0;
        $ligne = db_fetch_one("SELECT departement_id, exercice FROM lignes_budgetaires WHERE id=?", [$id]);
        if (!$ligne) json_response(false, 'Ligne introuvable.');
        if (is_array($perimetre) && !in_array((int)$ligne['departement_id'], $perimetre, true)) {
            json_response(false, "Ce département n'est pas dans votre périmètre.");
        }
        if (!ach_budget_editable((int)$ligne['departement_id'], (int)$ligne['exercice'])) {
            json_response(false, 'Ce budget est soumis ou validé — il ne peut plus être modifié.');
        }
        db_query("UPDATE lignes_budgetaires SET actif=? WHERE id=?", [$nv, $id]);
        audit_log($user['id'], 'UPDATE', 'achats_param', $id, $nv ? 'Réactivation ligne budgétaire' : 'Désactivation ligne budgétaire');
        json_response(true, $nv ? 'Ligne réactivée.' : 'Ligne désactivée.');
    }

    // ── Import en masse — deux temps : aperçu (résolution + contrôles,
    //    rien n'est écrit), puis confirmation (écrit ce qui a été validé à
    //    l'aperçu). Le fichier n'est jamais reparsé à la confirmation : le
    //    résultat de l'aperçu est gardé en session, pour que ce que
    //    l'utilisateur a validé soit exactement ce qui est écrit.
    if ($action === 'import_preview') {
        $imp_exercice = (int)($_POST['exercice'] ?? 0);
        if (!$imp_exercice) json_response(false, "L'exercice est obligatoire pour l'import.");
        if (empty($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
            json_response(false, 'Sélectionnez un fichier valide.');
        }
        $ext = strtolower(pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            json_response(false, 'Format invalide. Utilisez .xlsx, .xls ou .csv.');
        }

        $tmp_dest = sys_get_temp_dir() . '/budget_import_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['fichier']['tmp_name'], $tmp_dest);
        require_once __DIR__ . '/../../vendor/autoload.php';
        try {
            $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp_dest)->getActiveSheet()->toArray(null, true, true, false);
        } catch (Exception $e) {
            @unlink($tmp_dest);
            json_response(false, 'Erreur de lecture du fichier : ' . $e->getMessage());
        }
        @unlink($tmp_dest);

        if (empty($rows)) json_response(false, 'Fichier vide.');
        $headers = array_map('strtolower', array_map('trim', $rows[0]));
        $col = array_flip($headers);
        foreach (['departement', 'famille'] as $need) {
            if (!isset($col[$need])) json_response(false, "Colonne manquante : « $need ». Colonnes attendues : departement, famille, enveloppe, comportement.");
        }

        // Résolution par code OU libellé, insensible à la casse — l'utilisateur
        // ne doit pas avoir à connaître l'identifiant technique.
        $depts_all = db_fetch_all("SELECT id, code, label, actif FROM departements");
        $fams_all  = db_fetch_all("SELECT id, code, libelle, actif, compte_comptable FROM familles_achat");
        $find_dept = function (string $v) use ($depts_all) {
            $v = mb_strtolower(trim($v));
            foreach ($depts_all as $d) { if (mb_strtolower($d['code']) === $v || mb_strtolower($d['label']) === $v) return $d; }
            return null;
        };
        $find_fam = function (string $v) use ($fams_all) {
            $v = mb_strtolower(trim($v));
            foreach ($fams_all as $f) { if (mb_strtolower($f['code']) === $v || mb_strtolower($f['libelle']) === $v) return $f; }
            return null;
        };

        $editable_cache = [];
        $lignes_preview = [];
        for ($i = 1; $i < count($rows); $i++) {
            $row     = $rows[$i];
            $dep_raw = trim((string)($row[$col['departement']] ?? ''));
            $fam_raw = trim((string)($row[$col['famille']] ?? ''));
            if ($dep_raw === '' && $fam_raw === '') continue;

            $erreurs = [];
            $dept = $dep_raw !== '' ? $find_dept($dep_raw) : null;
            if (!$dept) $erreurs[] = "département « $dep_raw » introuvable";
            elseif (!$dept['actif']) $erreurs[] = "département « {$dept['label']} » inactif";
            elseif (is_array($perimetre) && !in_array((int)$dept['id'], $perimetre, true)) $erreurs[] = "département « {$dept['label']} » hors de votre périmètre";

            $fam = $fam_raw !== '' ? $find_fam($fam_raw) : null;
            if (!$fam) $erreurs[] = "famille « $fam_raw » introuvable";
            elseif (!$fam['actif']) $erreurs[] = "famille « {$fam['libelle']} » inactive";

            if ($dept && !$erreurs) {
                $dept_id = (int)$dept['id'];
                if (!array_key_exists($dept_id, $editable_cache)) $editable_cache[$dept_id] = ach_budget_editable($dept_id, $imp_exercice);
                if (!$editable_cache[$dept_id]) $erreurs[] = "budget « {$dept['label']} » soumis ou validé pour $imp_exercice — verrouillé";
            }

            $env_raw   = trim((string)($row[$col['enveloppe']] ?? ''));
            $enveloppe = null;
            if ($env_raw !== '') {
                if (is_numeric($env_raw)) $enveloppe = (int)$env_raw;
                else $erreurs[] = "enveloppe « $env_raw » non numérique";
            }

            $comport = strtolower(trim((string)($row[$col['comportement']] ?? ''))) ?: 'aucun';
            if (!isset($COMPORTEMENTS[$comport])) { $erreurs[] = "comportement « $comport » inconnu (aucun/alerte/blocage)"; $comport = 'aucun'; }

            $lignes_preview[] = [
                'ligne_fichier'  => $i + 1,
                'departement_id' => $dept['id'] ?? null,
                'departement'    => $dept['label'] ?? $dep_raw,
                'famille_id'     => $fam['id'] ?? null,
                'famille'        => $fam['libelle'] ?? $fam_raw,
                'enveloppe'      => $enveloppe,
                'comportement'   => $comport,
                'erreurs'        => $erreurs,
            ];
        }

        if (!$lignes_preview) json_response(false, 'Aucune ligne exploitable dans le fichier.');

        $_SESSION['budget_import'] = ['exercice' => $imp_exercice, 'lignes' => $lignes_preview];
        json_response(true, '', [
            'exercice' => $imp_exercice,
            'total'    => count($lignes_preview),
            'valides'  => count(array_filter($lignes_preview, fn($l) => !$l['erreurs'])),
            'lignes'   => $lignes_preview,
        ]);
    }

    if ($action === 'import_confirm') {
        $session = $_SESSION['budget_import'] ?? null;
        if (!$session) json_response(false, "Session d'import expirée — reprenez depuis le début.");
        $imp_exercice = (int)$session['exercice'];
        $creees = 0; $maj = 0; $ignorees = 0;
        foreach ($session['lignes'] as $l) {
            if ($l['erreurs']) { $ignorees++; continue; }
            $fam  = db_fetch_one("SELECT * FROM familles_achat WHERE id=?", [$l['famille_id']]);
            $dept = db_fetch_one("SELECT * FROM departements WHERE id=?", [$l['departement_id']]);
            if (!$fam || !$dept) { $ignorees++; continue; }
            $existe = db_fetch_value(
                "SELECT id FROM lignes_budgetaires WHERE departement_id=? AND famille_id=? AND exercice=?",
                [$l['departement_id'], $l['famille_id'], $imp_exercice]
            );
            db_query(
                "INSERT INTO lignes_budgetaires (departement_id, famille_id, exercice, code_comptable, designation, enveloppe, comportement)
                 VALUES (?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                    code_comptable=VALUES(code_comptable), designation=VALUES(designation),
                    enveloppe=VALUES(enveloppe), comportement=VALUES(comportement)",
                [$l['departement_id'], $l['famille_id'], $imp_exercice,
                 $fam['compte_comptable'] ?: $fam['code'], $dept['label'] . ' — ' . $fam['libelle'],
                 $l['enveloppe'], $l['comportement']]
            );
            $existe ? $maj++ : $creees++;
        }
        unset($_SESSION['budget_import']);
        audit_log($user['id'], 'CREATE', 'achats_param', null, "Import lignes budgétaires $imp_exercice : $creees créée(s), $maj mise(s) à jour, $ignorees ignorée(s)");
        json_response(true, "$creees créée(s), $maj mise(s) à jour" . ($ignorees ? ", $ignorees ignorée(s)" : '') . '.');
    }

    json_response(false, 'Action inconnue.');
}

// ── PAGE PHP ─────────────────────────────────────────────────
$exercices = db_fetch_all("SELECT DISTINCT exercice FROM lignes_budgetaires ORDER BY exercice DESC");
$exerciceActuel = (int)(date('Y'));
$exercice = (int)($_GET['exercice'] ?? ($exercices[0]['exercice'] ?? $exerciceActuel));
$f_departement = (int)($_GET['departement'] ?? 0);

// Un RAF/DAF scopé à un ou plusieurs départements ne voit (et ne peut
// soumettre) que les siens — même fonction que le reste du module.
$perimetre = ach_perimetre_departements($user);

$where  = ['lb.exercice = ?'];
$params = [$exercice];
if ($f_departement) { $where[] = 'lb.departement_id = ?'; $params[] = $f_departement; }
if (is_array($perimetre)) {
    // ach_clause_departement() renvoie " AND lb.departement_id IN (...)" (ou
    // " AND 1=0" si le périmètre est vide) — préfixe " AND " ôté ici, le
    // implode(' AND ', $where) plus bas le remet une seule fois.
    [$clausePerim, $paramsPerim] = ach_clause_departement($perimetre, 'lb');
    if ($clausePerim !== '') { $where[] = substr($clausePerim, 5); $params = array_merge($params, $paramsPerim); }
}

$lignes = db_fetch_all(
    "SELECT lb.*, d.label AS departement_label, d.code AS departement_code,
            f.libelle AS famille_libelle, f.code AS famille_code, f.compte_comptable
     FROM lignes_budgetaires lb
     LEFT JOIN departements d   ON d.id = lb.departement_id
     LEFT JOIN familles_achat f ON f.id = lb.famille_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY d.label ASC, f.libelle ASC",
    $params
);

// Situation (engagé/réservé/disponible) par ligne — Bloc 2, ach_budget_situation.
foreach ($lignes as &$l) {
    if ($l['departement_id'] && $l['famille_id']) {
        $sit = ach_budget_situation((int)$l['departement_id'], (int)$l['famille_id'], $exercice);
        $l['engage'] = $sit['engage']; $l['reserve'] = $sit['reserve'];
        $l['total_engage'] = $sit['total_engage']; $l['disponible'] = $sit['disponible'];
    } else {
        $l['engage'] = $l['reserve'] = $l['total_engage'] = 0; $l['disponible'] = null;
    }
}
unset($l);

// Groupé par département, sous-total par famille à l'intérieur (Bloc 4, point 22).
$grouped = [];
foreach ($lignes as $l) {
    $key = (int)($l['departement_id'] ?: 0);
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'label' => $l['departement_label'] ?: '— (sans département)', 'lignes' => [],
            'total_enveloppe' => 0, 'total_engage' => 0,
            'validation' => $key ? ach_budget_validation($key, $exercice) : ['statut' => 'brouillon'],
        ];
    }
    $grouped[$key]['lignes'][] = $l;
    if ($l['enveloppe'] !== null) $grouped[$key]['total_enveloppe'] += (int)$l['enveloppe'];
    $grouped[$key]['total_engage'] += (int)$l['total_engage'];
}

$dept_where = 'actif=1';
$dept_params = [];
if (is_array($perimetre)) {
    if (!$perimetre) { $dept_where .= ' AND 1=0'; }
    else { $dept_where .= ' AND id IN (' . implode(',', array_fill(0, count($perimetre), '?')) . ')'; $dept_params = $perimetre; }
}
$departements_actifs = db_fetch_all("SELECT id, code, label FROM departements WHERE $dept_where ORDER BY label ASC", $dept_params);
$familles = db_fetch_all("SELECT id, code, libelle, compte_comptable FROM familles_achat WHERE actif=1 ORDER BY libelle ASC");

// ── Rafraîchissement live — fragment HTML seul (les dept-card, pas la
//    barre exercice/département), cf. le même mécanisme sur
//    file_attente.php. isBusy par défaut couvre déjà les modales (édition,
//    drill-down) de cet écran.
function ach_pb_render_zone(array $grouped, bool $can_edit, bool $can_valider, bool $is_admin, int $exercice, array $COMPORTEMENTS, array $STATUTS_BUDGET): void {
    if (empty($grouped)): ?>
      <div class="dept-card"><div class="ach-empty">Aucune ligne budgétaire pour ces filtres.</div></div>
    <?php else: foreach ($grouped as $dept_id => $g):
      $pct = $g['total_enveloppe'] > 0 ? min(100, round($g['total_engage'] / $g['total_enveloppe'] * 100)) : 0;
      $statut_budget = $g['validation']['statut'];
      $sb = $STATUTS_BUDGET[$statut_budget] ?? $STATUTS_BUDGET['brouillon'];
    ?>
    <div class="dept-card">
      <div class="dept-hdr">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <span class="dept-nom"><?= h($g['label']) ?></span>
          <?php if ($dept_id): ?>
          <span class="ach-badge" style="background:<?= $sb['bg'] ?>;color:<?= $sb['color'] ?>"><?= h($sb['label']) ?></span>
          <?php endif; ?>
        </div>
        <span class="dept-meta">
          <?= fmt_number((float)$g['total_engage']) ?> XOF engagés
          <?= $g['total_enveloppe'] > 0 ? ' / ' . fmt_number((float)$g['total_enveloppe']) . ' XOF (' . $pct . '%)' : '' ?>
        </span>
        <?php if ($dept_id): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <?php if ($can_edit && in_array($statut_budget, ['brouillon', 'rejete'], true)): ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="pbSoumettre(<?= $dept_id ?>, <?= $exercice ?>)">
              <i class="ph ph-paper-plane-tilt" aria-hidden="true"></i> Soumettre au PDG
            </button>
          <?php endif; ?>
          <?php if ($can_valider && $statut_budget === 'soumis'): ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="pbValider(<?= $dept_id ?>, <?= $exercice ?>)">
              <i class="ph ph-check-circle" aria-hidden="true"></i> Valider
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="pbRejeter(<?= $dept_id ?>, <?= $exercice ?>)">
              <i class="ph ph-x-circle" aria-hidden="true"></i> Rejeter
            </button>
          <?php endif; ?>
          <?php if ($is_admin && $statut_budget === 'valide'): ?>
            <button type="button" class="btn btn-secondary btn-sm" onclick="pbReouvrir(<?= $dept_id ?>, <?= $exercice ?>)">
              <i class="ph ph-lock-open" aria-hidden="true"></i> Réouvrir (admin)
            </button>
          <?php endif; ?>
        </div>
        <?php if ($statut_budget === 'rejete' && !empty($g['validation']['motif_rejet'])): ?>
        <div style="width:100%;font-size:12px;color:#991B1B">Motif du rejet : <?= h($g['validation']['motif_rejet']) ?></div>
        <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php $dept_editable = $dept_id ? in_array($statut_budget, ['brouillon', 'rejete'], true) : true; ?>
      <div style="overflow-x:auto">
      <table class="ach-table">
        <thead><tr>
          <th>Famille</th><th>Compte</th><th>Enveloppe</th><th>Engagé + réservé</th><th>Disponible</th><th>Comportement</th><th>Statut</th>
          <?php if ($can_edit && $dept_editable): ?><th>Actions</th><?php endif; ?>
        </tr></thead>
        <tbody>
          <?php foreach ($g['lignes'] as $l):
            $famille_pct = $l['enveloppe'] !== null && $l['enveloppe'] > 0 ? min(100, round($l['total_engage'] / $l['enveloppe'] * 100)) : null;
            $fill_color = $famille_pct === null ? '#94a3b8' : ($famille_pct >= 100 ? '#dc2626' : ($famille_pct >= 80 ? '#d97706' : '#16a34a'));
          ?>
          <tr class="drill-row" onclick='pbDrillDown(<?= (int)$l['departement_id'] ?>, <?= (int)$l['famille_id'] ?>, <?= $exercice ?>, <?= json_encode($l['famille_libelle'], JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
            <td style="font-weight:700;color:var(--navy)"><?= h($l['famille_libelle'] ?? '—') ?></td>
            <td style="font-family:monospace"><?= h($l['compte_comptable'] ?: '—') ?></td>
            <td><?= $l['enveloppe'] !== null ? h(number_format((float)$l['enveloppe'], 0, ',', ' ')) : '<span style="color:var(--muted)">Non plafonnée</span>' ?></td>
            <td>
              <?= fmt_number((float)$l['total_engage']) ?> XOF
              <?php if ($famille_pct !== null): ?>
              <div class="budget-bar" style="margin-top:4px"><div class="budget-fill" style="width:<?= $famille_pct ?>%;background:<?= $fill_color ?>"></div></div>
              <?php endif; ?>
            </td>
            <td style="font-weight:700;color:<?= $l['disponible'] !== null && $l['disponible'] < 0 ? '#991B1B' : 'inherit' ?>">
              <?= $l['disponible'] !== null ? fmt_number((float)$l['disponible']) . ' XOF' : '—' ?>
            </td>
            <td><span class="ach-badge <?= h($l['comportement']) ?>"><?= h($COMPORTEMENTS[$l['comportement']] ?? $l['comportement']) ?></span></td>
            <td><span class="ach-badge <?= $l['actif'] ? 'on' : 'off' ?>"><?= $l['actif'] ? 'Active' : 'Inactive' ?></span></td>
            <?php if ($can_edit && $dept_editable): ?>
            <td onclick="event.stopPropagation()" style="display:flex;gap:8px">
              <button type="button" class="btn btn-secondary btn-sm" aria-label="Modifier <?= h($l['famille_libelle'] ?? '') ?>"
                      onclick='achOpenEdit(<?= json_encode($l, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                <i class="ph ph-pencil-simple" aria-hidden="true"></i>
              </button>
              <button type="button" class="btn btn-secondary btn-sm" aria-label="<?= $l['actif'] ? 'Désactiver' : 'Réactiver' ?>"
                      onclick="achToggle(<?= $l['id'] ?>, <?= $l['actif'] ? 0 : 1 ?>)">
                <i class="ph <?= $l['actif'] ? 'ph-prohibit' : 'ph-check-circle' ?>" aria-hidden="true"></i>
              </button>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
    <?php endforeach; endif;
}

if (is_ajax() && ($_GET['fragment'] ?? '') === '1') {
    ach_pb_render_zone($grouped, $can_edit, $can_valider, $is_admin, $exercice, $COMPORTEMENTS, $STATUTS_BUDGET);
    exit;
}
$fragmentQs = $_GET;
$fragmentQs['fragment'] = '1';
$fragmentUrl = APP_URL . '/pages/achats/param_budget.php?' . http_build_query($fragmentQs);

include __DIR__ . '/../../templates/header.php';
?>
<style>
.ach-toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:18px}
.ach-toolbar select{padding:9px 12px;border:1.5px solid var(--border);border-radius:9px;font-size:13px;font-family:inherit;background:white}
.dept-card{background:white;border:1px solid var(--border);border-radius:16px;margin-bottom:18px;overflow:hidden}
.dept-hdr{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:14px 18px;background:#f8fafc;border-bottom:1px solid var(--border)}
.dept-nom{font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;color:var(--navy);font-size:14px}
.dept-meta{font-size:12px;color:var(--muted)}
.ach-table{width:100%;border-collapse:collapse;font-size:13px}
.ach-table th{background:#f8fafc;color:var(--muted);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;padding:10px 16px;text-align:left;border-bottom:1px solid var(--border)}
.ach-table td{padding:10px 16px;border-bottom:1px solid var(--border);vertical-align:middle}
.ach-table tr:last-child td{border-bottom:none}
.ach-table tr.drill-row{cursor:pointer}
.ach-table tr.drill-row:hover{background:#f8fafc}
.ach-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.ach-badge.on{background:#D1FAE5;color:#065F46}
.ach-badge.off{background:#F1F5F9;color:#475569}
.ach-badge.alerte{background:#FEF3C7;color:#92400E}
.ach-badge.blocage{background:#FEE2E2;color:#991B1B}
.ach-badge.aucun{background:#F1F5F9;color:#475569}
.budget-bar{width:100px;height:6px;background:var(--border);border-radius:3px;overflow:hidden}
.budget-fill{height:100%;border-radius:3px}
.ach-empty{padding:40px 20px;text-align:center;color:var(--muted);font-size:13px}
.ach-modal-bg{display:none;position:fixed;inset:0;background:rgba(6,3,58,.45);z-index:2000;align-items:center;justify-content:center;padding:20px}
.ach-modal-bg.open{display:flex}
.ach-modal{background:white;border-radius:16px;padding:26px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.ach-modal h3{margin:0 0 16px;font-family:'Plus Jakarta Sans',sans-serif;font-size:16px;font-weight:800;color:var(--navy)}
.ach-fg{margin-bottom:14px}
.ach-fg label{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:5px}
.ach-fg input,.ach-fg select{width:100%;padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;box-sizing:border-box;background:white}
.ach-row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.ach-modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px}
.ach-err{color:#dc2626;font-size:12px;margin-top:4px;display:none}
.ach-hint{font-size:11.5px;color:var(--muted);margin-top:4px}
@media (max-width:768px) {
  .ach-toolbar select, .ach-fg input, .ach-fg select { min-height:44px; }
  .ach-row2 { grid-template-columns: minmax(0,1fr); }
}
</style>

<div class="ach-toolbar">
  <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <label for="ach-exercice" style="font-size:13px;font-weight:600;color:#374151">Exercice</label>
    <select id="ach-exercice" name="exercice" onchange="this.form.submit()">
      <?php
      // +1 : préparer le budget de l'année suivante avant qu'elle ne
      // commence (saisie RAF/DAF, soumission, validation PDG) — sans quoi
      // le filtre ne propose jamais d'exercice futur tant qu'aucune ligne
      // n'y existe.
      $exList = array_unique(array_merge(array_column($exercices, 'exercice'), [$exerciceActuel, $exerciceActuel + 1]));
      rsort($exList);
      foreach ($exList as $ex): ?>
        <option value="<?= $ex ?>" <?= $ex == $exercice ? 'selected' : '' ?>><?= $ex ?></option>
      <?php endforeach; ?>
    </select>
    <label for="ach-departement" style="font-size:13px;font-weight:600;color:#374151">Département</label>
    <select id="ach-departement" name="departement" onchange="this.form.submit()">
      <option value="0">Tous</option>
      <?php foreach ($departements_actifs as $d): ?>
        <option value="<?= $d['id'] ?>" <?= $f_departement === (int)$d['id'] ? 'selected' : '' ?>><?= h($d['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php if ($can_edit): ?>
  <div style="display:flex;gap:10px">
    <button type="button" class="btn btn-secondary" onclick="ibOpenModal()">
      <i class="ph ph-upload-simple" aria-hidden="true"></i> Importer
    </button>
    <button type="button" class="btn btn-primary" onclick="achOpenCreate()">
      <i class="ph ph-plus" aria-hidden="true"></i> Nouvelle ligne
    </button>
  </div>
  <?php endif; ?>
</div>

<div id="budget-live-zone">
<?php ach_pb_render_zone($grouped, $can_edit, $can_valider, $is_admin, $exercice, $COMPORTEMENTS, $STATUTS_BUDGET); ?>
</div>

<!-- MODALE création/édition -->
<div class="ach-modal-bg" id="ach-modal">
  <div class="ach-modal" role="dialog" aria-labelledby="ach-modal-title">
    <h3 id="ach-modal-title"><span id="ach-modal-ttl-txt">Nouvelle ligne budgétaire</span></h3>
    <input type="hidden" id="f-id" value="">
    <div class="ach-row2">
      <div class="ach-fg">
        <label for="f-departement">Département</label>
        <select id="f-departement" required>
          <option value="">— Sélectionner —</option>
          <?php foreach ($departements_actifs as $d): ?><option value="<?= $d['id'] ?>"><?= h($d['label']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="ach-fg">
        <label for="f-exercice">Exercice</label>
        <input type="number" id="f-exercice" required value="<?= $exercice ?>">
      </div>
    </div>
    <div class="ach-fg">
      <label for="f-famille">Famille</label>
      <select id="f-famille" required>
        <option value="">— Sélectionner —</option>
        <?php foreach ($familles as $f): ?>
          <option value="<?= $f['id'] ?>"><?= h($f['libelle']) ?> (<?= h($f['compte_comptable'] ?: $f['code']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <div class="ach-hint">Le compte comptable et la désignation sont dérivés automatiquement de la famille choisie.</div>
    </div>
    <div class="ach-fg">
      <label for="f-enveloppe">Enveloppe (XOF)</label>
      <input type="number" id="f-enveloppe" min="0" step="1" placeholder="vide = non plafonnée">
      <div class="ach-hint">Laisser vide pour une ligne non plafonnée.</div>
    </div>
    <div class="ach-fg">
      <label for="f-comportement">Comportement au dépassement</label>
      <select id="f-comportement">
        <?php foreach ($COMPORTEMENTS as $k => $v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="ach-err" id="ach-err"></div>
    <div class="ach-modal-actions">
      <button type="button" class="btn btn-secondary" onclick="achCloseModal()">Annuler</button>
      <button type="button" class="btn btn-primary" onclick="achSave()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- MODALE drill-down -->
<div class="ach-modal-bg" id="drill-modal">
  <div class="ach-modal" role="dialog" aria-labelledby="drill-modal-title" style="max-width:640px">
    <h3 id="drill-modal-title">FEB contribuant à <span id="drill-famille"></span></h3>
    <div style="overflow-x:auto">
    <table class="ach-table">
      <thead><tr><th>FEB</th><th>Objet</th><th>Article / désignation</th><th>Montant</th><th>Statut</th></tr></thead>
      <tbody id="drill-tbody"></tbody>
    </table>
    </div>
    <div class="ach-modal-actions">
      <button type="button" class="btn btn-secondary" onclick="document.getElementById('drill-modal').classList.remove('open')">Fermer</button>
    </div>
  </div>
</div>

<!-- MODALE import -->
<div class="ach-modal-bg" id="import-modal">
  <div class="ach-modal" role="dialog" aria-labelledby="import-modal-title" style="max-width:720px">
    <h3 id="import-modal-title">Importer des lignes budgétaires</h3>

    <div id="import-step-1">
      <div class="ach-hint" style="margin-bottom:12px">
        Fichier .xlsx, .xls ou .csv (séparateur virgule) avec les colonnes <code>departement</code>, <code>famille</code>,
        <code>enveloppe</code> (facultative) et <code>comportement</code> (aucun/alerte/blocage, facultatif) —
        le département et la famille peuvent être saisis par code ou par libellé.
        <a href="?template=csv">Télécharger le modèle</a>.
      </div>
      <div class="ach-row2">
        <div class="ach-fg">
          <label for="ib-exercice">Exercice cible</label>
          <select id="ib-exercice"></select>
        </div>
        <div class="ach-fg">
          <label for="ib-fichier">Fichier</label>
          <input type="file" id="ib-fichier" accept=".xlsx,.xls,.csv">
        </div>
      </div>
      <div class="ach-err" id="import-err"></div>
      <div class="ach-modal-actions">
        <button type="button" class="btn btn-secondary" onclick="ibCloseModal()">Annuler</button>
        <button type="button" class="btn btn-primary" onclick="ibPreview()">Analyser le fichier</button>
      </div>
    </div>

    <div id="import-step-2" style="display:none">
      <div id="import-summary" style="font-size:13px;margin-bottom:12px"></div>
      <div style="overflow-x:auto;max-height:340px;overflow-y:auto">
        <table class="ach-table">
          <thead><tr><th>Ligne</th><th>Département</th><th>Famille</th><th>Enveloppe</th><th>Comportement</th><th>Statut</th></tr></thead>
          <tbody id="import-preview-tbody"></tbody>
        </table>
      </div>
      <div class="ach-modal-actions">
        <button type="button" class="btn btn-secondary" onclick="ibRetour()">Retour</button>
        <button type="button" class="btn btn-primary" id="ib-btn-confirm" onclick="ibConfirmer()">Importer les lignes valides</button>
      </div>
    </div>
  </div>
</div>

<script>
function achOpenCreate() {
  document.getElementById('f-id').value = '';
  document.getElementById('ach-modal-ttl-txt').textContent = 'Nouvelle ligne budgétaire';
  document.getElementById('f-departement').value = '';
  document.getElementById('f-exercice').value = '<?= $exercice ?>';
  document.getElementById('f-famille').value = '';
  document.getElementById('f-enveloppe').value = '';
  document.getElementById('f-comportement').value = 'aucun';
  document.getElementById('ach-err').style.display = 'none';
  document.getElementById('ach-modal').classList.add('open');
}
function achOpenEdit(l) {
  document.getElementById('f-id').value = l.id;
  document.getElementById('ach-modal-ttl-txt').textContent = 'Modifier la ligne budgétaire';
  document.getElementById('f-departement').value = l.departement_id || '';
  document.getElementById('f-exercice').value = l.exercice;
  document.getElementById('f-famille').value = l.famille_id || '';
  document.getElementById('f-enveloppe').value = l.enveloppe ?? '';
  document.getElementById('f-comportement').value = l.comportement;
  document.getElementById('ach-err').style.display = 'none';
  document.getElementById('ach-modal').classList.add('open');
}
function achCloseModal() { document.getElementById('ach-modal').classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') { achCloseModal(); document.getElementById('drill-modal').classList.remove('open'); } });
document.getElementById('ach-modal').addEventListener('click', e => { if (e.target === e.currentTarget) achCloseModal(); });
document.getElementById('drill-modal').addEventListener('click', e => { if (e.target === e.currentTarget) e.currentTarget.classList.remove('open'); });

function achPost(data) {
  const fd = new FormData();
  Object.entries(data).forEach(([k, v]) => fd.append(k, v));
  return fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd }).then(r => r.json());
}

function achSave() {
  const err = document.getElementById('ach-err');
  const departement_id = document.getElementById('f-departement').value;
  const famille_id = document.getElementById('f-famille').value;
  const exercice = document.getElementById('f-exercice').value;
  if (!departement_id || !famille_id || !exercice) { err.textContent = 'Département, famille et exercice sont obligatoires.'; err.style.display = 'block'; return; }
  achPost({
    action: 'save',
    id: document.getElementById('f-id').value,
    departement_id, famille_id, exercice,
    enveloppe: document.getElementById('f-enveloppe').value,
    comportement: document.getElementById('f-comportement').value,
  }).then(res => {
    if (!res.success) { err.textContent = res.message; err.style.display = 'block'; return; }
    toast(res.message, 'success');
    achCloseModal();
    setTimeout(() => location.reload(), 500);
  });
}

function achToggle(id, actif) {
  if (!confirm(actif ? 'Réactiver cette ligne ?' : 'Désactiver cette ligne ?')) return;
  achPost({ action: 'toggle', id, actif }).then(res => {
    toast(res.message, res.success ? 'success' : 'danger');
    if (res.success) setTimeout(() => location.reload(), 500);
  });
}

function pbSoumettre(departement_id, exercice) {
  if (!confirm('Soumettre ce budget au PDG pour validation ? Les lignes seront verrouillées tant qu\'il n\'est pas validé ou rejeté.')) return;
  achPost({ action: 'soumettre', departement_id, exercice }).then(res => {
    toast(res.message, res.success ? 'success' : 'danger');
    if (res.success) setTimeout(() => location.reload(), 500);
  });
}
function pbValider(departement_id, exercice) {
  if (!confirm('Valider ce budget ? Il sera verrouillé et deviendra la référence du contrôle des dépenses.')) return;
  achPost({ action: 'valider_budget', departement_id, exercice }).then(res => {
    toast(res.message, res.success ? 'success' : 'danger');
    if (res.success) setTimeout(() => location.reload(), 500);
  });
}
function pbRejeter(departement_id, exercice) {
  const motif = prompt('Motif du rejet :');
  if (motif === null) return;
  if (!motif.trim()) { toast('Le motif de rejet est obligatoire.', 'danger'); return; }
  achPost({ action: 'rejeter_budget', departement_id, exercice, motif: motif.trim() }).then(res => {
    toast(res.message, res.success ? 'success' : 'danger');
    if (res.success) setTimeout(() => location.reload(), 500);
  });
}
function pbReouvrir(departement_id, exercice) {
  if (!confirm("Réouvrir ce budget validé ? Il redevient modifiable et devra être soumis puis validé à nouveau.")) return;
  achPost({ action: 'reouvrir_budget', departement_id, exercice }).then(res => {
    toast(res.message, res.success ? 'success' : 'danger');
    if (res.success) setTimeout(() => location.reload(), 500);
  });
}

const esc = s => String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
function pbDrillDown(departement_id, famille_id, exercice, familleLibelle) {
  document.getElementById('drill-famille').textContent = familleLibelle;
  document.getElementById('drill-tbody').innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:18px">Chargement…</td></tr>';
  document.getElementById('drill-modal').classList.add('open');
  achPost({ action: 'get_contributions', departement_id, famille_id, exercice }).then(res => {
    if (!res.success) { document.getElementById('drill-tbody').innerHTML = `<tr><td colspan="5" style="text-align:center;color:var(--muted)">${esc(res.message)}</td></tr>`; return; }
    const rows = res.data || [];
    document.getElementById('drill-tbody').innerHTML = rows.map(r => `
      <tr>
        <td style="font-weight:700;color:var(--navy)">${esc(r.numero)}</td>
        <td>${esc(r.objet)}</td>
        <td>${esc(r.article_libelle || r.designation)}</td>
        <td>${Number(r.montant_ttc).toLocaleString('fr-FR')} XOF</td>
        <td>${esc(r.statut)}</td>
      </tr>`).join('') || '<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:18px">Aucune FEB ne contribue à cette enveloppe pour l\'instant.</td></tr>';
  });
}

// ── Import en masse ──────────────────────────────────────────
function ibOpenModal() {
  document.getElementById('import-err').style.display = 'none';
  document.getElementById('ib-fichier').value = '';
  const sel = document.getElementById('ib-exercice');
  sel.innerHTML = '';
  <?php foreach ($exList as $ex): ?>
    sel.innerHTML += '<option value="<?= $ex ?>" <?= $ex == $exercice ? 'selected' : '' ?>><?= $ex ?></option>';
  <?php endforeach; ?>
  document.getElementById('import-step-1').style.display = '';
  document.getElementById('import-step-2').style.display = 'none';
  document.getElementById('import-modal').classList.add('open');
}
function ibCloseModal() { document.getElementById('import-modal').classList.remove('open'); }
document.getElementById('import-modal').addEventListener('click', e => { if (e.target === e.currentTarget) ibCloseModal(); });

function ibPreview() {
  const err = document.getElementById('import-err');
  err.style.display = 'none';
  const fichier = document.getElementById('ib-fichier').files[0];
  if (!fichier) { err.textContent = 'Sélectionnez un fichier.'; err.style.display = 'block'; return; }
  const fd = new FormData();
  fd.append('action', 'import_preview');
  fd.append('exercice', document.getElementById('ib-exercice').value);
  fd.append('fichier', fichier);
  fetch(window.location.href, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
    .then(r => r.json())
    .then(res => {
      if (!res.success) { err.textContent = res.message; err.style.display = 'block'; return; }
      const d = res.data;
      document.getElementById('import-summary').innerHTML =
        `<strong>${d.valides}</strong> ligne(s) valide(s) sur <strong>${d.total}</strong>, pour l'exercice <strong>${d.exercice}</strong>.`
        + (d.valides < d.total ? ' Les lignes en erreur ne seront pas importées.' : '');
      document.getElementById('import-preview-tbody').innerHTML = d.lignes.map(l => `
        <tr>
          <td>${l.ligne_fichier}</td>
          <td>${esc(l.departement)}</td>
          <td>${esc(l.famille)}</td>
          <td>${l.enveloppe !== null ? Number(l.enveloppe).toLocaleString('fr-FR') : '—'}</td>
          <td>${esc(l.comportement)}</td>
          <td>${l.erreurs.length ? `<span style="color:#991B1B">${esc(l.erreurs.join(' ; '))}</span>` : '<span style="color:#16a34a">OK</span>'}</td>
        </tr>`).join('');
      document.getElementById('ib-btn-confirm').disabled = d.valides === 0;
      document.getElementById('import-step-1').style.display = 'none';
      document.getElementById('import-step-2').style.display = '';
    });
}
function ibRetour() {
  document.getElementById('import-step-1').style.display = '';
  document.getElementById('import-step-2').style.display = 'none';
}
function ibConfirmer() {
  achPost({ action: 'import_confirm' }).then(res => {
    toast(res.message, res.success ? 'success' : 'danger');
    if (res.success) { ibCloseModal(); setTimeout(() => location.reload(), 600); }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  liveRefresh({ url: <?= json_encode($fragmentUrl) ?>, container: '#budget-live-zone', interval: 10000 });
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
