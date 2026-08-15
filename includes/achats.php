<?php
// ============================================================
//  includes/achats.php — Helpers du module Achats
// ============================================================

// ── Libellés et couleurs de statuts ──────────────────────────
// Préparés pour les écrans FEB (lot suivant, pas encore créés) — aucun
// écran de ce lot n'en dépend, mais les futurs écrans réutiliseront ces
// tableaux plutôt que de redéfinir des libellés/couleurs par page.
function ach_statuts_feb(): array {
    return [
        'brouillon'         => ['label' => 'Brouillon',              'bg' => '#F1F5F9', 'color' => '#475569'],
        'soumise'           => ['label' => 'Soumise',                 'bg' => '#DBEAFE', 'color' => '#1D4ED8'],
        'prise_en_charge'   => ['label' => 'Prise en charge',         'bg' => '#E0E7FF', 'color' => '#3730A3'],
        'en_validation'     => ['label' => 'En validation',           'bg' => '#FEF3C7', 'color' => '#92400E'],
        'confirmee'         => ['label' => 'Confirmée',               'bg' => '#D1FAE5', 'color' => '#065F46'],
        'cloturee'          => ['label' => 'Clôturée',                'bg' => '#F1F5F9', 'color' => '#475569'],
        'rejetee'           => ['label' => 'Rejetée',                 'bg' => '#FEE2E2', 'color' => '#991B1B'],
    ];
}

function ach_statuts_suivi(): array {
    return [
        'en_attente'   => ['label' => 'En attente',   'bg' => '#F1F5F9', 'color' => '#475569'],
        'commande'     => ['label' => 'Commandé',     'bg' => '#DBEAFE', 'color' => '#1D4ED8'],
        'en_retard'    => ['label' => 'En retard',    'bg' => '#FEE2E2', 'color' => '#991B1B'],
        'livree'       => ['label' => 'Livrée',       'bg' => '#FEF3C7', 'color' => '#92400E'],
        'receptionnee' => ['label' => 'Réceptionnée', 'bg' => '#D1FAE5', 'color' => '#065F46'],
    ];
}

// ── Lecture d'un paramètre achat_parametres, avec valeur par défaut ──
function ach_param(string $cle, $defaut = null) {
    $v = db_fetch_value("SELECT valeur FROM achat_parametres WHERE cle = ?", [$cle]);
    return $v !== null && $v !== false ? $v : $defaut;
}

// ── Palier de validation applicable à un montant ─────────────
// Retourne la ligne complète (bornes, libellé, signataires déjà décodés)
// ou null si aucun palier actif ne couvre ce montant. borne_max NULL =
// pas de plafond, donc dernier palier trouvé par bornes croissantes.
function ach_palier_pour_montant(int $montant): ?array {
    $row = db_fetch_one(
        "SELECT * FROM achat_paliers
          WHERE actif = 1
            AND borne_min <= ?
            AND (borne_max IS NULL OR borne_max >= ?)
          ORDER BY ordre
          LIMIT 1",
        [$montant, $montant]
    );
    if (!$row) return null;
    $row['signataires'] = json_decode($row['signataires'], true) ?? [];
    return $row;
}

// ── Numéro de FEB suivant pour un exercice donné ─────────────
// Gère elle-même sa transaction (BEGIN/COMMIT internes) : à appeler HORS
// de toute transaction déjà ouverte par l'appelant — PostgreSQL ne supporte
// pas les transactions imbriquées, un appel depuis une transaction en cours
// romprait le verrou de ligne recherché ici.
// Verrou explicite (SELECT ... FOR UPDATE) : sans lui, deux FEB soumises au
// même instant peuvent lire le même dernier_numero et repartir avec un
// numéro identique.
function ach_numero_feb(int $exercice): string {
    db_begin();
    try {
        db_query(
            "INSERT INTO feb_compteurs (exercice, dernier_numero) VALUES (?, 0)
             ON CONFLICT (exercice) DO NOTHING",
            [$exercice]
        );
        $row = db_fetch_one(
            "SELECT dernier_numero FROM feb_compteurs WHERE exercice = ? FOR UPDATE",
            [$exercice]
        );
        $suivant = (int)($row['dernier_numero'] ?? 0) + 1;
        db_query(
            "UPDATE feb_compteurs SET dernier_numero = ? WHERE exercice = ?",
            [$suivant, $exercice]
        );
        db_commit();
    } catch (Exception $e) {
        db_rollback();
        throw $e;
    }
    return sprintf('FEB-%d-%04d', $exercice, $suivant);
}
