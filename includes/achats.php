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

// ── Ancienneté en heures ouvrées, depuis une date de soumission — c'est
//    elle qui pilote la priorité dans la file d'attente Achats (Bloc 1),
//    pas la date brute : une FEB soumise vendredi 17h n'attend « que » 1h
//    ouvrée lundi 9h, pas 64h d'horloge murale.
//    Fenêtre fixe 8h-18h, du lundi au vendredi : aucun paramètre dédié
//    n'existe dans achat_parametres pour la faire varier.
function ach_anciennete_heures_ouvrees(string $depuis): float {
    $debut_h = 8;
    $fin_h   = 18;
    try {
        $debut = new DateTime($depuis);
    } catch (Exception $e) {
        return 0.0;
    }
    $fin = new DateTime();
    if ($debut >= $fin) return 0.0;

    $total = 0.0;
    $jour    = (clone $debut)->setTime(0, 0, 0);
    $dernier = (clone $fin)->setTime(0, 0, 0);
    while ($jour <= $dernier) {
        if ((int)$jour->format('N') <= 5) {  // 1 (lundi) à 5 (vendredi)
            $fenetre_debut = (clone $jour)->setTime($debut_h, 0, 0);
            $fenetre_fin   = (clone $jour)->setTime($fin_h, 0, 0);
            $bloc_debut = max($fenetre_debut, $debut);
            $bloc_fin   = min($fenetre_fin, $fin);
            if ($bloc_fin > $bloc_debut) {
                $total += ($bloc_fin->getTimestamp() - $bloc_debut->getTimestamp()) / 3600;
            }
        }
        $jour->modify('+1 day');
    }
    return round($total, 1);
}

// ── Prise en charge exclusive d'une FEB par un acheteur ──────
//    Le verrou est l'UPDATE conditionnel lui-même : WHERE acheteur_id IS
//    NULL AND statut='soumise' rend la course impossible par construction,
//    sans colonne de verrou ni verrou consultatif. Un SELECT puis un UPDATE
//    séparés laisseraient une fenêtre ouverte entre les deux — c'est
//    justement ce que ce test doit exclure (Bloc 5, point 35).
function ach_prendre_en_charge(int $feb_id, array $user): bool {
    $uid = (int)$user['id'];
    $now = date('Y-m-d H:i:s');

    $stmt = db_query(
        "UPDATE feb SET acheteur_id=?, date_prise_charge=?, statut='prise_en_charge'
          WHERE id=? AND acheteur_id IS NULL AND statut='soumise'",
        [$uid, $now, $feb_id]
    );
    if ($stmt->rowCount() === 0) return false;

    audit_log($uid, 'UPDATE', 'achats', $feb_id, 'Prise en charge de la FEB');

    $feb = db_fetch_one("SELECT numero, demandeur_id FROM feb WHERE id=?", [$feb_id]);
    if ($feb && $feb['demandeur_id']) {
        $nom = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
        db_query(
            "INSERT INTO notifications (user_id, type, titre, message, lien) VALUES (?, 'info', 'FEB prise en charge', ?, ?)",
            [(int)$feb['demandeur_id'], "Votre FEB {$feb['numero']} est prise en charge par $nom.", '/pages/achats/mes_feb.php']
        );
    }
    return true;
}

// ── Restitution : l'acheteur qui détient la FEB la relâche — retour en
//    soumise, acheteur_id et date_prise_charge effacés, pour repasser dans
//    la section « à prendre en charge » de la file d'attente.
function ach_restituer_feb(int $feb_id, array $user): bool {
    $uid = (int)$user['id'];

    $stmt = db_query(
        "UPDATE feb SET acheteur_id=NULL, date_prise_charge=NULL, statut='soumise'
          WHERE id=? AND acheteur_id=? AND statut='prise_en_charge'",
        [$feb_id, $uid]
    );
    if ($stmt->rowCount() === 0) return false;

    audit_log($uid, 'UPDATE', 'achats', $feb_id, 'Restitution de la FEB');
    return true;
}

// ── Réattribution par un administrateur d'une FEB déjà prise en charge —
//    pour les cas d'absence de l'acheteur titulaire. Réservé à l'appelant
//    (contrôle du rôle admin/superadmin fait par la page, pas ici).
function ach_reattribuer_feb(int $feb_id, int $nouvel_acheteur_id, array $user): bool {
    $uid = (int)$user['id'];
    $now = date('Y-m-d H:i:s');

    $stmt = db_query(
        "UPDATE feb SET acheteur_id=?, date_prise_charge=?
          WHERE id=? AND statut='prise_en_charge'",
        [$nouvel_acheteur_id, $now, $feb_id]
    );
    if ($stmt->rowCount() === 0) return false;

    audit_log($uid, 'UPDATE', 'achats', $feb_id, "Réattribution de la FEB à l'utilisateur #$nouvel_acheteur_id");
    return true;
}

// ── Bascule des lignes arbitrées « stock » vers une commande interne.
//    Une seule transaction : en-tête commande puis lignes, db_rollback()
//    sur exception — sur le modèle de ach_creer_feb().
//    Retourne l'id de la commande créée, ou lève une AchValidationException
//    si rien n'est basculable (droits, statut, garde-fou, aucune ligne
//    « stock »).
function ach_basculer_vers_commande(int $feb_id, array $user): ?int {
    $uid = (int)$user['id'];

    $feb = db_fetch_one("SELECT * FROM feb WHERE id=?", [$feb_id]);
    if (!$feb) throw new AchValidationException('FEB introuvable.');
    if ((int)$feb['acheteur_id'] !== $uid) throw new AchValidationException("Cette FEB n'est pas prise en charge par vous.");
    if ($feb['statut'] !== 'prise_en_charge') throw new AchValidationException("Cette FEB n'est plus en cours de traitement.");
    if (!$feb['site_id']) throw new AchValidationException('Impossible de créer la commande : aucun site renseigné sur la FEB.');

    // Garde-fou : une FEB ne peut être basculée qu'une fois — contrôle sur
    // l'existence d'une commandes portant déjà ce feb_id.
    $deja = db_fetch_value("SELECT id FROM commandes WHERE feb_id=?", [$feb_id]);
    if ($deja) throw new AchValidationException('Cette FEB a déjà été basculée vers une commande interne.');

    // article_id IS NOT NULL par sécurité redondante : une ligne en saisie
    // libre n'est jamais arbitrable côté écran, mais ne doit jamais pouvoir
    // atteindre ce point si un choix « stock » s'y glissait quand même.
    $lignes_stock = db_fetch_all(
        "SELECT * FROM feb_lignes WHERE feb_id=? AND arbitrage='stock' AND article_id IS NOT NULL ORDER BY numero_ligne",
        [$feb_id]
    );
    if (!$lignes_stock) throw new AchValidationException("Aucune ligne arbitrée « stock » à basculer.");

    $demandeur     = $feb['demandeur_id'] ? db_fetch_one("SELECT prenom, nom FROM users WHERE id=?", [$feb['demandeur_id']]) : null;
    $nom_demandeur = trim(($demandeur['prenom'] ?? '') . ' ' . ($demandeur['nom'] ?? ''));

    $pdo = get_db();
    $transaction_locale = !$pdo->inTransaction();
    if ($transaction_locale) db_begin();
    try {
        $num   = 'CMD-' . date('Ymd') . '-' . str_pad((string)rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $notes = "Issue de la FEB {$feb['numero']} — demandeur $nom_demandeur";
        db_query(
            "INSERT INTO commandes (numero_commande, site_id, statut, notes, created_by, feb_id) VALUES (?,?,'en_attente',?,?,?)",
            [$num, $feb['site_id'], $notes, $uid, $feb_id]
        );
        $cmd_id = (int) db_last_id();

        foreach ($lignes_stock as $l) {
            $article = db_fetch_one("SELECT type_article, unite FROM articles WHERE id=?", [$l['article_id']]);
            db_query(
                "INSERT INTO commande_lignes (commande_id, type_article, article_id, libelle, quantite, unite, statut_ligne)
                 VALUES (?,?,?,?,?,?,'en_attente')",
                [$cmd_id, $article['type_article'] ?? 'article', $l['article_id'], $l['designation'], $l['quantite'],
                 $l['unite'] ?: ($article['unite'] ?? 'unité')]
            );
        }

        // FEB entièrement servie sur stock ? Aucune ligne restée en 'achat'
        // → clôture ; sinon la FEB reste en prise_en_charge et poursuit son
        // chemin sur les seules lignes concernées (Bloc suivant, hors de ce
        // lot).
        $reste_achat = (int) db_fetch_value(
            "SELECT COUNT(*) FROM feb_lignes WHERE feb_id=? AND arbitrage='achat'", [$feb_id]
        );
        if ($reste_achat === 0) {
            db_query("UPDATE feb SET statut='cloturee', date_cloture=NOW() WHERE id=?", [$feb_id]);
        }

        audit_log($uid, 'UPDATE', 'achats', $feb_id, "Bascule vers commande interne $num — " . count($lignes_stock) . ' ligne(s)');

        if ($feb['demandeur_id']) {
            $msg = $reste_achat === 0
                ? "Votre FEB {$feb['numero']} est servie sur stock — commande $num."
                : "Votre FEB {$feb['numero']} est partiellement servie sur stock — commande $num.";
            db_query(
                "INSERT INTO notifications (user_id, type, titre, message, lien) VALUES (?, 'info', 'FEB servie sur stock', ?, ?)",
                [(int)$feb['demandeur_id'], $msg, '/pages/commandes.php']
            );
        }

        if ($transaction_locale) db_commit();
    } catch (Exception $e) {
        if ($transaction_locale) db_rollback();
        throw $e;
    }

    return $cmd_id;
}
