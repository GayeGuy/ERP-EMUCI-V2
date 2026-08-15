<?php
// ============================================================
//  includes/achats.php — Helpers du module Achats
// ============================================================

// ach_creer_feb() appelle audit_log() : on ne compte pas sur l'appelant
// pour l'avoir inclus, sinon tout nouveau point d'entrée (script, tâche
// planifiée, test) tombe sur un fatal « undefined function ».
require_once __DIR__ . '/audit.php';

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

// ── Qui peut déposer une FEB — même règle que di_peut_creer() pour les
//    demandes internes : le lecteur (PDG) consulte et valide, mais ne
//    dépose pas.
function ach_peut_creer(?array $user = null): bool {
    $user = $user ?? current_user();
    return ($user['role_slug'] ?? '') !== 'lecteur';
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
// S'appuie sur la transaction de l'appelant : PostgreSQL ne supporte pas
// les transactions imbriquées, et cette fonction est destinée à être
// appelée depuis ach_creer_feb() qui gère déjà sa propre transaction
// (en-tête + lignes + pièces jointes). Une transaction locale n'est ouverte
// que si aucune n'est déjà en cours, pour rester utilisable isolément
// (tests, scripts) sans jamais imbriquer de BEGIN.
// Verrou explicite (SELECT ... FOR UPDATE) : sans lui, deux FEB soumises au
// même instant peuvent lire le même dernier_numero et repartir avec un
// numéro identique.
function ach_numero_feb(int $exercice): string {
    $pdo         = get_db();
    $transaction_locale = !$pdo->inTransaction();
    if ($transaction_locale) db_begin();
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
        if ($transaction_locale) db_commit();
    } catch (Exception $e) {
        if ($transaction_locale) db_rollback();
        throw $e;
    }
    return sprintf('FEB-%d-%04d', $exercice, $suivant);
}

// ── Urgence FEB : priorité de traitement, pas une note — trois niveaux,
//    jamais un entier nu exposé à l'écran. Aligné sur le DEFAULT 0 de
//    feb.urgence (0 = Normale).
function ach_urgences(): array {
    return [
        0 => 'Normale',
        1 => 'Urgente',
        2 => 'Critique',
    ];
}

// ── Exception de validation FEB — porte le champ fautif pour que l'écran
//    place le message à côté du champ concerné, pas dans une bannière
//    générale (cf. Bloc 4 de la spécification).
class AchValidationException extends Exception {
    public string $field;
    public function __construct(string $message, string $field = '') {
        parent::__construct($message);
        $this->field = $field;
    }
}

// ── Créer ou mettre à jour le brouillon d'une FEB, et éventuellement la
//    soumettre. Sur le modèle de di_creer() : une seule transaction,
//    en-tête puis lignes puis pièces jointes ; db_rollback() sur exception.
//
//    $feb_id   : null pour une création, id existant pour mettre à jour un
//                brouillon (l'appelant a déjà vérifié la propriété et le
//                statut brouillon — cette fonction ne le refait pas).
//    $entete   : ['site_id'=>?int, 'departement_id'=>?int, 'fonction'=>?string,
//                 'urgence'=>int, 'objet'=>string]
//    $lignes   : tableau de ['designation','article_id','quantite','unite',
//                 'famille_id','code_analytique','type_achat']
//    $soumettre: false = enregistrer le brouillon (contrôles allégés),
//                true  = soumettre (contrôles complets + numérotation).
//
//    Retourne ['id'=>int, 'numero'=>?string, 'statut'=>string].
function ach_creer_feb(array $user, ?int $feb_id, array $entete, array $lignes, bool $soumettre): array {
    $uid = (int)$user['id'];

    $objet = trim($entete['objet'] ?? '');
    if ($objet === '') throw new AchValidationException("L'objet est obligatoire.", 'objet');
    if (mb_strlen($objet) > 255) throw new AchValidationException("L'objet est trop long (255 caractères maximum).", 'objet');

    $urgence = (int)($entete['urgence'] ?? 0);
    if (!array_key_exists($urgence, ach_urgences())) $urgence = 0;

    $site_id        = !empty($entete['site_id']) ? (int)$entete['site_id'] : null;
    $departement_id = !empty($entete['departement_id']) ? (int)$entete['departement_id'] : null;
    $fonction       = trim($entete['fonction'] ?? '') ?: null;

    // ── Lignes : nettoyage, puis contrôles qui s'appliquent à CHAQUE
    //    enregistrement (brouillon compris) — plafond de lignes et codes
    //    analytiques sont des limites structurelles de la FEB, pas des
    //    conditions de complétude qu'on tolère en brouillon.
    $max_lignes = (int)ach_param('max_lignes_feb', 14);
    $lignes_ok  = [];
    $codes_analytiques = [];
    $n = 0;
    foreach ($lignes as $l) {
        $designation = trim($l['designation'] ?? '');
        if ($designation === '') continue;  // ligne vide laissée de côté par l'utilisateur
        $n++;
        $quantite = (int)($l['quantite'] ?? 1);
        if ($quantite < 1) throw new AchValidationException("Quantité invalide à la ligne $n : elle doit être strictement positive.", "ligne_{$n}_quantite");

        $code_analytique = trim($l['code_analytique'] ?? '');
        if ($code_analytique !== '' && !in_array($code_analytique, $codes_analytiques, true)) {
            $codes_analytiques[] = $code_analytique;
        }

        $lignes_ok[] = [
            'designation'     => $designation,
            'article_id'      => !empty($l['article_id']) ? (int)$l['article_id'] : null,
            'quantite'        => $quantite,
            'unite'           => trim($l['unite'] ?? '') ?: null,
            'famille_id'      => !empty($l['famille_id']) ? (int)$l['famille_id'] : null,
            'code_analytique' => $code_analytique ?: null,
            'type_achat'      => trim($l['type_achat'] ?? '') ?: null,
        ];
    }
    if (count($lignes_ok) > $max_lignes) {
        throw new AchValidationException("Trop de lignes : $max_lignes maximum (paramètre « max_lignes_feb », modifiable dans Achats → Paramètres généraux).", 'lignes');
    }
    if (count($codes_analytiques) > 3) {
        throw new AchValidationException('Trois codes analytiques au maximum par FEB.', 'lignes');
    }

    // ── Contrôles supplémentaires, uniquement à la soumission — un
    //    brouillon reste volontairement tolérant sur la complétude.
    if ($soumettre) {
        if (count($lignes_ok) === 0) {
            throw new AchValidationException('Ajoutez au moins une ligne avant de soumettre.', 'lignes');
        }
        foreach ($lignes_ok as $i => $l) {
            $num = $i + 1;
            if (!$l['famille_id']) throw new AchValidationException("Famille obligatoire à la ligne $num.", "ligne_{$num}_famille");
            if (!$l['type_achat']) throw new AchValidationException("Type d'achat obligatoire à la ligne $num.", "ligne_{$num}_type");
        }
    }

    $pdo = get_db();
    $transaction_locale = !$pdo->inTransaction();
    if ($transaction_locale) db_begin();
    try {
        $now         = date('Y-m-d H:i:s');
        $exercice    = (int)date('Y');
        $nom         = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
        $creation    = ($feb_id === null);

        if ($creation) {
            db_query(
                "INSERT INTO feb (numero, exercice, demandeur_id, site_id, departement_id, fonction, urgence, objet, statut, montant_total, date_creation)
                 VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 'brouillon', 0, ?)",
                [$exercice, $uid, $site_id, $departement_id, $fonction, $urgence, $objet, $now]
            );
            $feb_id = (int) db_last_id('feb_id_seq');
            audit_log($uid, 'CREATE', 'achats', $feb_id, "Création brouillon FEB — objet : $objet");
        } else {
            db_query(
                "UPDATE feb SET site_id=?, departement_id=?, fonction=?, urgence=?, objet=? WHERE id=?",
                [$site_id, $departement_id, $fonction, $urgence, $objet, $feb_id]
            );
            // Remplacement complet des lignes plutôt qu'un diff — même
            // approche que la restauration de brouillon de
            // point_journalier.php : plus simple, et c'est justement ce
            // chemin-là qui avait un historique de bugs sur ce projet.
            db_query("DELETE FROM feb_lignes WHERE feb_id=?", [$feb_id]);
            if (!$soumettre) {
                audit_log($uid, 'UPDATE', 'achats', $feb_id, "Modification brouillon FEB — objet : $objet");
            }
        }

        $num_ligne = 0;
        foreach ($lignes_ok as $l) {
            $num_ligne++;
            db_query(
                "INSERT INTO feb_lignes (feb_id, numero_ligne, designation, article_id, quantite, unite, famille_id, code_analytique, type_achat)
                 VALUES (?,?,?,?,?,?,?,?,?)",
                [$feb_id, $num_ligne, $l['designation'], $l['article_id'], $l['quantite'],
                 $l['unite'], $l['famille_id'], $l['code_analytique'], $l['type_achat']]
            );
        }

        $statut = 'brouillon';
        $numero = null;
        if ($soumettre) {
            $numero = ach_numero_feb($exercice);
            $montant_total = (int) db_fetch_value("SELECT COALESCE(SUM(montant_ttc),0) FROM feb_lignes WHERE feb_id=?", [$feb_id]);
            db_query(
                "UPDATE feb SET numero=?, statut='soumise', date_soumission=?, montant_total=? WHERE id=?",
                [$numero, $now, $montant_total, $feb_id]
            );
            $statut = 'soumise';
            audit_log($uid, 'UPDATE', 'achats', $feb_id, "Soumission FEB $numero");

            // Notification au service Achats — type 'info', seul type
            // applicatif autorisé par la contrainte d'énumération sur
            // notifications.type (avec fin_cycle, stock_bas, alerte_conso).
            $achats = db_fetch_all(
                "SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id
                 WHERE r.slug='superviseur_achat' AND u.actif=1"
            );
            foreach ($achats as $a) {
                db_query(
                    "INSERT INTO notifications (user_id, type, titre, message, lien) VALUES (?, 'info', 'Nouvelle FEB', ?, ?)",
                    [(int)$a['id'], "FEB $numero soumise par $nom — $objet", '/pages/achats/mes_feb.php']
                );
            }
        } else {
            $numero = db_fetch_value("SELECT numero FROM feb WHERE id=?", [$feb_id]);
        }

        if ($transaction_locale) db_commit();
    } catch (Exception $e) {
        if ($transaction_locale) db_rollback();
        throw $e;
    }

    return ['id' => $feb_id, 'numero' => $numero, 'statut' => $statut];
}
