<?php
// ============================================================
//  includes/demandes.php — Moteur "Demandes internes"
//  Porté depuis emu-demandes (Node) : workflow data-driven,
//  circuits en base (di_types/di_etapes), JSON géré en PHP.
//  SQL volontairement standard => compatible MySQL 8 et PostgreSQL.
// ============================================================

require_once __DIR__ . '/db.php';

// ── Statuts
const DI_STATUTS = ['brouillon','en_attente','en_cours','approuve','approuve_traitement','rejete','a_revoir'];

// ── Libellé + couleurs d'un statut  => [label, couleur_texte, couleur_fond]
function di_statut_label(string $s): array {
    return [
        'brouillon'           => ['Brouillon','#7f8c8d','#f0f4f8'],
        'en_attente'          => ['En attente','#e67e22','#fef9e7'],
        'en_cours'            => ['En cours','#1B75BC','#eaf3fb'],
        'approuve'            => ['Approuvée','#27ae60','#eafaf1'],
        'approuve_traitement' => ['Approuvée — traitement IT','#16a085','#e8f8f5'],
        'rejete'              => ['Rejetée','#e74c3c','#fdf0ef'],
        'a_revoir'            => ['À revoir','#8e44ad','#f5eefa'],
    ][$s] ?? [$s,'#7f8c8d','#f0f4f8'];
}
function di_badge(string $s): string {
    [$lbl,$c,$bg] = di_statut_label($s);
    return '<span style="font-size:11px;font-weight:700;padding:3px 10px;border-radius:10px;color:'.$c.';background:'.$bg.'">'.h($lbl).'</span>';
}

// ── Charge le circuit (étapes ordonnées) d'un type de demande
function di_workflow(string $typeCode): array {
    $rows = db_fetch_all(
        "SELECT e.role_code, e.label, e.ordre
         FROM di_etapes e JOIN di_types t ON t.id = e.type_id
         WHERE t.code = ? ORDER BY e.ordre ASC",
        [$typeCode]
    );
    return array_map(fn($r) => ['role' => $r['role_code'], 'label' => $r['label']], $rows);
}

// ── Type de demande par code
function di_type(string $typeCode): ?array {
    return db_fetch_one("SELECT * FROM di_types WHERE code = ?", [$typeCode]);
}

// ── Plateformes NSIIV actives (pour les champs de type 'plateformes')
function di_plateformes(): array {
    return db_fetch_all("SELECT code, label FROM di_plateformes WHERE actif = 1 ORDER BY ordre ASC");
}
function di_types_actifs(): array {
    return db_fetch_all("SELECT * FROM di_types WHERE actif = 1 ORDER BY ordre ASC");
}

// ── Rôles de validation d'un utilisateur — DÉRIVÉS de son rôle ERP.
//    Source de vérité unique : Administration → Permissions (le rôle ERP = la fonction de
//    validation). Plus de matrice « Rôles valideurs » séparée. Le N+1 n'est pas ici : il est
//    résolu par le N+1 du département du demandeur (user_departements), cf. di_can_validate().
function di_user_roles(int $userId): array {
    // Rôle ERP (slug) → codes d'étape de circuit que ce rôle peut viser.
    static $map = [
        'raf'               => ['raf'],
        'daf'               => ['daf'],
        'gestionnaire'      => ['gestionnaire'],
        'support_it'        => ['it'],
        'superviseur_it'    => ['it'],
        'maintenance_info'  => ['it'],
        'directeur_general' => ['dg'],
        'lecteur'           => ['dg'],  // PDG — rôle ERP "lecteur" = visa Direction Générale
        // Administrateurs : visent toutes les étapes (jamais leur propre demande, cf. di_can_validate)
        'superadmin'        => ['n1', 'raf', 'daf', 'dg', 'it', 'gestionnaire'],
        'admin'             => ['n1', 'raf', 'daf', 'dg', 'it', 'gestionnaire'],
    ];
    $slug  = db_fetch_value("SELECT r.slug FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?", [$userId]);
    $roles = ($slug && isset($map[$slug])) ? $map[$slug] : [];

    // Filet de sécurité : anciens droits attribués manuellement (table di_user_roles, écran retiré)
    // restent honorés le temps de la transition. Peut être purgé pour un basculement 100 % rôles ERP.
    $legacy = array_column(db_fetch_all("SELECT role_code FROM di_user_roles WHERE user_id = ?", [$userId]), 'role_code');

    return array_values(array_unique(array_merge($roles, $legacy)));
}

// ── Demandes que CET utilisateur peut viser (étape courante = un de ses rôles).
//    Enrichit chaque demande de _etape_label et _demandeur.
function di_a_valider(array $user): array {
    $roles    = di_user_roles((int)$user['id']);
    // N+1 département
    $is_n1_dept = (bool)db_fetch_value(
        "SELECT COUNT(*) FROM user_departements WHERE user_id=? AND is_n1=1",
        [(int)$user['id']]
    );
    // Membre d'un département lié à un rôle di (ex : Administration → gestionnaire)
    $has_dept_role = (bool)db_fetch_value(
        "SELECT COUNT(*) FROM user_departements ud
         JOIN di_roles dr ON dr.departement_id = ud.departement_id
         WHERE ud.user_id=?",
        [(int)$user['id']]
    );
    if (!$roles && !$is_n1_dept && !$has_dept_role) return [];

    $pending = db_fetch_all(
        "SELECT id FROM di_demandes WHERE statut IN ('en_attente','en_cours') AND demandeur_id <> ? ORDER BY created_at ASC",
        [$user['id']]
    );
    $out = [];
    foreach ($pending as $p) {
        $d  = di_get((int)$p['id']);
        $wf = di_workflow_of($d);
        $n1Id = isset($d['n1_user_id']) && $d['n1_user_id'] ? (int)$d['n1_user_id'] : null;
        if (di_can_validate($roles, (int)$user['id'], $wf, (int)$d['etape_actuelle'], (int)$d['demandeur_id'], $n1Id)) {
            $d['_etape_label'] = $wf[(int)$d['etape_actuelle']]['label'] ?? '';
            $d['_demandeur']   = db_fetch_value("SELECT CONCAT(prenom,' ',nom) FROM users WHERE id=?", [$d['demandeur_id']]);
            $out[] = $d;
        }
    }
    return $out;
}

// ── Demandes déjà traitées par cet utilisateur (a signé au moins une étape)
function di_deja_traite(array $user): array {
    $uid   = (int)$user['id'];
    $param = json_encode([['user_id' => $uid]]);
    $rows  = db_fetch_all(
        "SELECT id FROM di_demandes
         WHERE demandeur_id <> ?
           AND signatures != '[]'
           AND (signatures::jsonb) @> ?::jsonb
         ORDER BY updated_at DESC LIMIT 50",
        [$uid, $param]
    );
    $out = [];
    foreach ($rows as $r) {
        $d = di_get((int)$r['id']);
        if (!$d) continue;
        $wf = di_workflow_of($d);
        $d['_etape_label'] = $wf[(int)$d['etape_actuelle']]['label'] ?? '';
        $d['_demandeur']   = db_fetch_value("SELECT CONCAT(prenom,' ',nom) FROM users WHERE id=?", [$d['demandeur_id']]);
        $out[] = $d;
    }
    return $out;
}

// ── Étape suivante (ou null si terminé) — logique identique à l'app source
function di_next_step(array $workflow, int $currentStep): ?int {
    if ($currentStep === -1) return 0;
    if ($currentStep + 1 >= count($workflow)) return null;
    return $currentStep + 1;
}

// ── Le validateur peut-il agir sur l'étape courante ?
// $n1UserId : ID du N+1 résolu au moment de la soumission (null si aucun département défini)
function di_can_validate(array $userRoles, int $userId, array $workflow, int $currentStep, int $demandeurId, ?int $n1UserId = null): bool {
    if ($currentStep < 0 || $currentStep >= count($workflow)) return false;
    if ($userId === $demandeurId) return false;
    $role = $workflow[$currentStep]['role'];
    if ($role === 'n1') {
        return $n1UserId !== null ? $userId === $n1UserId : in_array('n1', $userRoles, true);
    }
    // Si ce rôle est lié à un département, tout membre du département peut valider
    $dept_id = db_fetch_value("SELECT departement_id FROM di_roles WHERE code=?", [$role]);
    if ($dept_id) {
        return (bool)db_fetch_value(
            "SELECT COUNT(*) FROM user_departements WHERE user_id=? AND departement_id=?",
            [$userId, (int)$dept_id]
        );
    }
    return in_array($role, $userRoles, true);
}

// ── Numéro lisible : DEM-YYYYMMDD-NNN
function di_generate_numero(): string {
    $prefix = 'DEM-' . date('Ymd') . '-';
    $n = (int) db_fetch_value(
        "SELECT COUNT(*) FROM di_demandes WHERE numero LIKE ?",
        [$prefix . '%']
    );
    return $prefix . str_pad((string)($n + 1), 3, '0', STR_PAD_LEFT);
}

// ── Notifications (réutilise la table ERP `notifications`)
function di_notify(int $userId, string $message, ?int $demandeId = null): void {
    $lien = $demandeId ? '/pages/demandes.php?id=' . $demandeId : '/pages/demandes.php';
    db_query(
        "INSERT INTO notifications (user_id, type, titre, message, lien) VALUES (?, 'info', 'Demande interne', ?, ?)",
        [$userId, $message, $lien]
    );
}
// Notifier tous les porteurs d'un rôle de validation (di_user_roles + membres du département lié)
function di_notify_role(string $roleCode, string $message, ?int $demandeId = null): void {
    $notified = [];
    foreach (db_fetch_all("SELECT user_id FROM di_user_roles WHERE role_code = ?", [$roleCode]) as $u) {
        $uid = (int)$u['user_id'];
        if (!in_array($uid, $notified, true)) { di_notify($uid, $message, $demandeId); $notified[] = $uid; }
    }
    $dept_id = db_fetch_value("SELECT departement_id FROM di_roles WHERE code=?", [$roleCode]);
    if ($dept_id) {
        foreach (db_fetch_all("SELECT user_id FROM user_departements WHERE departement_id=?", [(int)$dept_id]) as $u) {
            $uid = (int)$u['user_id'];
            if (!in_array($uid, $notified, true)) { di_notify($uid, $message, $demandeId); $notified[] = $uid; }
        }
    }
}

// ── Créer / soumettre une demande. Retourne l'id créé.
function di_creer(array $user, string $typeCode, array $champs, bool $soumettre, string $priorite = 'normal'): int {
    $type = di_type($typeCode);
    if (!$type) throw new Exception('Type de demande invalide.');
    if ($typeCode === 'exceptionnel' && trim($champs['motif'] ?? '') === '') {
        throw new Exception('Le motif est obligatoire pour une demande exceptionnelle.');
    }
    $workflow = di_workflow($typeCode);
    $now = date('Y-m-d H:i:s');
    $nom = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));

    $statut  = $soumettre ? 'en_attente' : 'brouillon';
    $etape   = $soumettre ? 0 : -1;
    $hist    = [['action' => $soumettre ? 'soumis' : 'brouillon', 'par' => $user['id'], 'nom' => $nom, 'date' => $now]];

    // Résoudre le N+1 du département du demandeur au moment de la soumission
    $n1UserId = null;
    if ($soumettre) {
        $n1Row = db_fetch_one(
            "SELECT user_id FROM user_departements
             WHERE departement_id = (SELECT departement_id FROM user_departements WHERE user_id = ? LIMIT 1)
               AND is_n1 = 1 AND user_id != ?
             LIMIT 1",
            [$user['id'], $user['id']]
        );
        $n1UserId = $n1Row ? (int)$n1Row['user_id'] : null;
    }

    $numero = di_generate_numero();
    db_query(
        "INSERT INTO di_demandes
         (numero, type_code, statut, etape_actuelle, demandeur_id, n1_user_id, champs, historique, signatures,
          workflow_snapshot, priorite, submitted_at, created_at, updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
        [$numero, $typeCode, $statut, $etape, $user['id'], $n1UserId,
         json_encode($champs, JSON_UNESCAPED_UNICODE),
         json_encode($hist, JSON_UNESCAPED_UNICODE),
         '[]',
         json_encode($workflow, JSON_UNESCAPED_UNICODE),
         $priorite, $soumettre ? $now : null, $now, $now]
    );
    $id = (int) db_last_id();

    if ($soumettre && $workflow) {
        // Si la première étape est N+1 et qu'on a un N+1 résolu, notifier spécifiquement
        if ($workflow[0]['role'] === 'n1' && $n1UserId) {
            di_notify($n1UserId, "Nouvelle demande « {$type['label']} » de $nom ($numero) à valider", $id);
        } else {
            di_notify_role($workflow[0]['role'],
                "Nouvelle demande « {$type['label']} » de $nom ($numero)", $id);
        }
    }
    return $id;
}

// ── Charge une demande (décode les colonnes JSON)
function di_get(int $id): ?array {
    $d = db_fetch_one("SELECT * FROM di_demandes WHERE id = ?", [$id]);
    if (!$d) return null;
    foreach (['champs','historique','signatures','workflow_snapshot'] as $k) {
        $d[$k] = json_decode($d[$k] ?? 'null', true) ?: ($k === 'champs' ? [] : []);
    }
    return $d;
}

// ── Circuit effectif d'une demande (snapshot figé, sinon circuit courant du type)
function di_workflow_of(array $demande): array {
    return !empty($demande['workflow_snapshot']) ? $demande['workflow_snapshot'] : di_workflow($demande['type_code']);
}

// ── Valider l'étape courante
function di_valider(array $demande, array $user, string $commentaire = ''): void {
    $wf = di_workflow_of($demande);
    $cur = (int)$demande['etape_actuelle'];
    $n1Id = isset($demande['n1_user_id']) && $demande['n1_user_id'] ? (int)$demande['n1_user_id'] : null;
    if (!di_can_validate(di_user_roles((int)$user['id']), (int)$user['id'], $wf, $cur, (int)$demande['demandeur_id'], $n1Id)) {
        throw new Exception("Vous ne pouvez pas valider cette étape.");
    }
    if (!in_array($demande['statut'], ['en_attente','en_cours'], true)) {
        throw new Exception("Cette demande ne peut plus être validée.");
    }
    $type = di_type($demande['type_code']);
    $stepLabel = $wf[$cur]['label'];
    $next = di_next_step($wf, $cur);
    $now = date('Y-m-d H:i:s');
    $nom = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));

    $signatures = $demande['signatures'];
    $signatures[] = ['etape' => $cur, 'etape_label' => $stepLabel, 'user_id' => $user['id'],
        'nom' => $nom, 'action' => 'approuve', 'commentaire' => $commentaire, 'date' => $now];
    $historique = $demande['historique'];
    $historique[] = ['action' => 'valide', 'etape' => $stepLabel, 'par' => $user['id'],
        'nom' => $nom, 'commentaire' => $commentaire, 'date' => $now];

    if ($next === null) {
        $statut = !empty($type['traitement_it']) ? 'approuve_traitement' : 'approuve';
        $etape  = $cur;
    } else {
        $statut = 'en_cours';
        $etape  = $next;
    }

    db_query(
        "UPDATE di_demandes SET statut=?, etape_actuelle=?, signatures=?, historique=?, updated_at=? WHERE id=?",
        [$statut, $etape, json_encode($signatures, JSON_UNESCAPED_UNICODE),
         json_encode($historique, JSON_UNESCAPED_UNICODE), $now, $demande['id']]
    );

    if ($next === null) {
        if (!empty($type['traitement_it'])) {
            di_notify((int)$demande['demandeur_id'], "Votre demande « {$type['label']} » est approuvée et en cours de traitement IT.", (int)$demande['id']);
            di_notify_role('it', "Demande « {$type['label']} » approuvée — à traiter ({$demande['numero']})", (int)$demande['id']);
        } else {
            di_notify((int)$demande['demandeur_id'], "Votre demande « {$type['label']} » a été approuvée.", (int)$demande['id']);
        }
    } else {
        di_notify((int)$demande['demandeur_id'], "Étape « $stepLabel » validée pour votre demande « {$type['label']} ».", (int)$demande['id']);
        di_notify_role($wf[$next]['role'], "Demande « {$type['label']} » en attente — étape : {$wf[$next]['label']} ({$demande['numero']})", (int)$demande['id']);
    }
}

// ── Rejeter l'étape courante
function di_rejeter(array $demande, array $user, string $motif): void {
    $wf = di_workflow_of($demande);
    $cur = (int)$demande['etape_actuelle'];
    $n1Id = isset($demande['n1_user_id']) && $demande['n1_user_id'] ? (int)$demande['n1_user_id'] : null;
    if (!di_can_validate(di_user_roles((int)$user['id']), (int)$user['id'], $wf, $cur, (int)$demande['demandeur_id'], $n1Id)) {
        throw new Exception("Vous ne pouvez pas rejeter cette étape.");
    }
    if (trim($motif) === '') throw new Exception('Le motif de rejet est obligatoire.');
    $type = di_type($demande['type_code']);
    $stepLabel = $wf[$cur]['label'];
    $now = date('Y-m-d H:i:s');
    $nom = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));

    $signatures = $demande['signatures'];
    $signatures[] = ['etape' => $cur, 'etape_label' => $stepLabel, 'user_id' => $user['id'],
        'nom' => $nom, 'action' => 'rejete', 'motif' => $motif, 'date' => $now];
    $historique = $demande['historique'];
    $historique[] = ['action' => 'rejete', 'etape' => $stepLabel, 'par' => $user['id'],
        'nom' => $nom, 'motif' => $motif, 'date' => $now];

    db_query(
        "UPDATE di_demandes SET statut='rejete', etape_rejet=?, signatures=?, historique=?, updated_at=? WHERE id=?",
        [$cur, json_encode($signatures, JSON_UNESCAPED_UNICODE),
         json_encode($historique, JSON_UNESCAPED_UNICODE), $now, $demande['id']]
    );
    di_notify((int)$demande['demandeur_id'],
        "Votre demande « {$type['label']} » a été rejetée à l'étape « $stepLabel » : $motif", (int)$demande['id']);
}
