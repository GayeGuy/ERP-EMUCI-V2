<?php
// ============================================================
//  includes/achats.php — Helpers du module Achats
// ============================================================

// ach_creer_feb() appelle audit_log() : on ne compte pas sur l'appelant
// pour l'avoir inclus, sinon tout nouveau point d'entrée (script, tâche
// planifiée, test) tombe sur un fatal « undefined function ».
require_once __DIR__ . '/audit.php';

// Le circuit de visas FEB (ach_lancer_validation, ach_viser) réutilise tel
// quel le moteur "Demandes internes" — di_can_validate() porte déjà la
// règle du département et celle du N+1, di_next_step() l'avancement.
// Aucune règle de droits n'est réécrite ici.
require_once __DIR__ . '/demandes.php';

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

// ── RG-13 : la grille des paliers actifs doit couvrir toute la plage de
//    montants sans trou ni chevauchement, sinon ach_lancer_validation()
//    pourrait un jour tomber sur un montant que plus aucun palier ne
//    couvre. Contrôlée à l'enregistrement ET à la désactivation d'un
//    palier (pages/achats/param_paliers.php) — les deux peuvent ouvrir un
//    trou. $exclude_id : le palier en cours d'édition/désactivation, à
//    remplacer par $candidat (sa version modifiée, actif ou non) plutôt
//    que par la ligne encore en base.
function ach_paliers_couvrent_tout(?int $exclude_id = null, ?array $candidat = null): array {
    $paliers = db_fetch_all("SELECT id, borne_min, borne_max, libelle, actif FROM achat_paliers WHERE actif = 1");
    if ($exclude_id !== null) {
        $paliers = array_values(array_filter($paliers, fn($p) => (int)$p['id'] !== $exclude_id));
    }
    if ($candidat !== null && !empty($candidat['actif'])) {
        $paliers[] = $candidat;
    }
    if (!$paliers) {
        return ['ok' => false, 'message' => 'La grille des paliers actifs est vide — aucun montant ne serait couvert.'];
    }

    usort($paliers, fn($a, $b) => $a['borne_min'] <=> $b['borne_min']);

    if ((int)$paliers[0]['borne_min'] !== 0) {
        return ['ok' => false, 'message' => "La grille doit commencer à 0 (le palier le plus bas commence à {$paliers[0]['borne_min']})."];
    }
    for ($i = 1; $i < count($paliers); $i++) {
        $prec_max = $paliers[$i - 1]['borne_max'];
        $cur_min  = (int)$paliers[$i]['borne_min'];
        if ($prec_max === null) {
            return ['ok' => false, 'message' => "Le palier « {$paliers[$i-1]['libelle']} » n'a pas de plafond : aucun palier ne peut le suivre."];
        }
        $prec_max = (int)$prec_max;
        if ($cur_min > $prec_max + 1) {
            return ['ok' => false, 'message' => "Trou dans la grille entre " . ($prec_max + 1) . " et " . ($cur_min - 1) . " XOF."];
        }
        if ($cur_min <= $prec_max) {
            return ['ok' => false, 'message' => "Chevauchement entre « {$paliers[$i-1]['libelle']} » et « {$paliers[$i]['libelle']} »."];
        }
    }
    if ($paliers[count($paliers) - 1]['borne_max'] !== null) {
        return ['ok' => false, 'message' => 'Le dernier palier doit rester sans plafond (borne haute vide) pour couvrir tous les montants.'];
    }
    return ['ok' => true, 'message' => 'Grille complète, sans trou ni chevauchement.'];
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
// ── Code analytique composé — RG-05 : jamais saisi à la main, toujours
//    dérivé du site et du service portés par la FEB (RG-06) et de la
//    famille de la ligne. Chaîne vide si l'un des trois manque encore
//    (brouillon incomplet) — l'appelant décide s'il bloque ou tolère.
function ach_code_analytique(array $feb, int $famille_id): string {
    $site_id        = !empty($feb['site_id']) ? (int)$feb['site_id'] : 0;
    $departement_id = !empty($feb['departement_id']) ? (int)$feb['departement_id'] : 0;
    if (!$site_id || !$departement_id || !$famille_id) return '';

    $site_code = db_fetch_value("SELECT code FROM sites WHERE id=?", [$site_id]);
    $dept_code = db_fetch_value("SELECT code FROM departements WHERE id=?", [$departement_id]);
    $fam_code  = db_fetch_value("SELECT code FROM familles_achat WHERE id=?", [$famille_id]);
    if (!$site_code || !$dept_code || !$fam_code) return '';

    $norm = static fn(string $s): string => strtoupper(str_replace(' ', '', trim($s)));
    return $norm($site_code) . '/' . $norm($dept_code) . '/' . $norm($fam_code);
}

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
    //    Le code analytique n'est jamais lu depuis $l (RG-05) : il est
    //    recomposé ici à partir du site/service de l'en-tête et de la
    //    famille de la ligne — aucune saisie libre ne peut plus l'atteindre.
    $max_lignes = (int)ach_param('max_lignes_feb', 14);
    $lignes_ok  = [];
    $familles_distinctes = [];
    $n = 0;
    foreach ($lignes as $l) {
        $designation = trim($l['designation'] ?? '');
        if ($designation === '') continue;  // ligne vide laissée de côté par l'utilisateur
        $n++;
        $quantite = (int)($l['quantite'] ?? 1);
        if ($quantite < 1) throw new AchValidationException("Quantité invalide à la ligne $n : elle doit être strictement positive.", "ligne_{$n}_quantite");

        $famille_id = !empty($l['famille_id']) ? (int)$l['famille_id'] : null;
        if ($famille_id && !in_array($famille_id, $familles_distinctes, true)) {
            $familles_distinctes[] = $famille_id;
        }
        $code = $famille_id ? ach_code_analytique(['site_id' => $site_id, 'departement_id' => $departement_id], $famille_id) : '';

        $lignes_ok[] = [
            'designation'     => $designation,
            'article_id'      => !empty($l['article_id']) ? (int)$l['article_id'] : null,
            'quantite'        => $quantite,
            'unite'           => trim($l['unite'] ?? '') ?: null,
            'famille_id'      => $famille_id,
            'code_analytique' => $code ?: null,
            'type_achat'      => trim($l['type_achat'] ?? '') ?: null,
        ];
    }
    if (count($lignes_ok) > $max_lignes) {
        throw new AchValidationException("Trop de lignes : $max_lignes maximum (paramètre « max_lignes_feb », modifiable dans Achats → Paramètres généraux).", 'lignes');
    }
    // RG-02 : trois codes analytiques au maximum par FEB. Le site et le
    // service étant constants sur toute la FEB (RG-06), deux lignes n'ont
    // de codes différents que si leurs familles diffèrent — la limite
    // devient donc, en pratique, une limite de trois familles distinctes.
    if (count($familles_distinctes) > 3) {
        throw new AchValidationException(
            'Trois familles distinctes au maximum par FEB (donc trois codes analytiques) — créez une seconde FEB pour la suite.',
            'lignes'
        );
    }

    // ── Contrôles supplémentaires, uniquement à la soumission — un
    //    brouillon reste volontairement tolérant sur la complétude.
    if ($soumettre) {
        if (count($lignes_ok) === 0) {
            throw new AchValidationException('Ajoutez au moins une ligne avant de soumettre.', 'lignes');
        }
        if (!$site_id || !$departement_id) {
            throw new AchValidationException('Site et service sont obligatoires : ils composent le code analytique de chaque ligne.', 'entete');
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
            // lot = code_analytique (RG-08) : pas de second identifiant à
            // maintenir en parallèle, cf. ach_lots_feb().
            db_query(
                "INSERT INTO feb_lignes (feb_id, numero_ligne, designation, article_id, quantite, unite, famille_id, code_analytique, lot, type_achat)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$feb_id, $num_ligne, $l['designation'], $l['article_id'], $l['quantite'],
                 $l['unite'], $l['famille_id'], $l['code_analytique'], $l['code_analytique'], $l['type_achat']]
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

// ── Lots d'une FEB, pour le comparatif d'offres (pages/achats/feb_traitement.php).
//    Un lot = un code analytique (RG-08, cf. ach_creer_feb()) : pas de
//    second identifiant à maintenir en parallèle, le regroupement se fait
//    directement sur feb_lignes.lot.
//    Les lignes arbitrées « stock » en sortent : on ne demande pas d'offre
//    pour ce qu'on ne va pas acheter — un lot entièrement servi sur stock
//    n'apparaît donc pas dans le résultat.
function ach_lots_feb(int $feb_id): array {
    $lignes = db_fetch_all(
        "SELECT * FROM feb_lignes
          WHERE feb_id=? AND lot IS NOT NULL AND lot <> '' AND arbitrage <> 'stock'
          ORDER BY lot, numero_ligne",
        [$feb_id]
    );
    $lots = [];
    foreach ($lignes as $l) {
        $lot = $l['lot'];
        if (!isset($lots[$lot])) {
            $lots[$lot] = ['lot' => $lot, 'lignes' => [], 'nb_articles' => 0, 'somme_quantites' => 0];
        }
        $lots[$lot]['lignes'][]         = $l;
        $lots[$lot]['nb_articles']     += 1;
        $lots[$lot]['somme_quantites'] += (int)$l['quantite'];
    }
    return array_values($lots);
}

// ── Recalcule feb.montant_total à partir des lignes — appelé à chaque
//    changement d'offre retenue ou de montant de ligne (point 26).
function ach_recalculer_montant_total(int $feb_id): void {
    $total = (int) db_fetch_value("SELECT COALESCE(SUM(montant_ttc),0) FROM feb_lignes WHERE feb_id=?", [$feb_id]);
    db_query("UPDATE feb SET montant_total=? WHERE id=?", [$total, $feb_id]);
}

// ── Retient une offre pour son lot — une seule transaction :
//    1) une seule offre retenue par lot (remise à faux des autres, jamais
//       un simple UPDATE ciblé qui laisserait une ancienne retenue en place) ;
//    2) report du fournisseur retenu sur les lignes du lot, sauf celles
//       marquées en dérogation (RG-09 : le fournisseur retenu peut différer
//       d'une ligne à l'autre, une dérogation manuelle ne doit jamais être
//       écrasée par un report en masse) ;
//    3) audit de l'ancienne et de la nouvelle offre retenue (autorisé tant
//       que la FEB reste en prise_en_charge — même contrôle que le reste de
//       feb_traitement.php, refait ici pour rester utilisable isolément).
function ach_retenir_offre_lot(int $offre_id, array $user): void {
    $uid = (int)$user['id'];

    $offre = db_fetch_one("SELECT * FROM feb_offres WHERE id=?", [$offre_id]);
    if (!$offre) throw new AchValidationException('Offre introuvable.');
    $feb_id = (int)$offre['feb_id'];
    $lot    = $offre['lot'];

    $feb = db_fetch_one("SELECT * FROM feb WHERE id=?", [$feb_id]);
    if (!$feb || (int)$feb['acheteur_id'] !== $uid || $feb['statut'] !== 'prise_en_charge') {
        throw new AchValidationException("Cette FEB n'est pas (ou plus) en cours de traitement par vous.");
    }

    $ancienne = db_fetch_one("SELECT id, fournisseur_id FROM feb_offres WHERE feb_id=? AND lot=? AND retenue=1", [$feb_id, $lot]);

    $pdo = get_db();
    $transaction_locale = !$pdo->inTransaction();
    if ($transaction_locale) db_begin();
    try {
        db_query("UPDATE feb_offres SET retenue=0 WHERE feb_id=? AND lot=?", [$feb_id, $lot]);
        db_query("UPDATE feb_offres SET retenue=1 WHERE id=?", [$offre_id]);
        db_query(
            "UPDATE feb_lignes SET fournisseur_id=? WHERE feb_id=? AND lot=? AND fournisseur_derogation=0",
            [$offre['fournisseur_id'], $feb_id, $lot]
        );
        ach_recalculer_montant_total($feb_id);

        $msg = $ancienne
            ? "Changement d'offre retenue sur le lot $lot : offre #{$ancienne['id']} → offre #$offre_id"
            : "Offre retenue sur le lot $lot : offre #$offre_id";
        audit_log($uid, 'UPDATE', 'achats', $feb_id, $msg);

        if ($transaction_locale) db_commit();
    } catch (Exception $e) {
        if ($transaction_locale) db_rollback();
        throw $e;
    }
}

// ── Vérifie la cohérence du comparatif avant de poursuivre : par lot ayant
//    une offre retenue, la somme des montants de ligne doit égaler le
//    montant de l'offre (point 36) ; par ligne, un type d'achat doit être
//    renseigné (Bloc 4, point 29). Ne modifie rien — laisse l'appelant
//    décider quoi faire du résultat.
function ach_verifier_comparatif(int $feb_id): array {
    foreach (ach_lots_feb($feb_id) as $lot) {
        $offre = db_fetch_one("SELECT * FROM feb_offres WHERE feb_id=? AND lot=? AND retenue=1", [$feb_id, $lot['lot']]);
        if ($offre) {
            $somme = array_sum(array_map(fn($l) => (int)$l['montant_ttc'], $lot['lignes']));
            if ($somme !== (int)$offre['montant_ttc']) {
                return ['ok' => false, 'message' =>
                    "Lot {$lot['lot']} : la somme des lignes ($somme XOF) ne correspond pas au montant de l'offre retenue ({$offre['montant_ttc']} XOF)."];
            }
        }
        foreach ($lot['lignes'] as $l) {
            if (!$l['type_achat']) {
                return ['ok' => false, 'message' => "La ligne « {$l['designation']} » (lot {$lot['lot']}) n'a pas de type d'achat."];
            }
        }
    }
    return ['ok' => true, 'message' => 'Comparatif vérifié : tout est cohérent.'];
}

// ── Décode les colonnes JSON d'une FEB portant le circuit — même rôle que
//    di_get() pour di_demandes, nécessaire car workflow_snapshot/signatures/
//    historique reviennent en texte JSON brut de PDO (jsonb côté colonne,
//    mais PDO ne décode jamais lui-même).
function ach_feb_decode(array $feb): array {
    foreach (['workflow_snapshot', 'signatures', 'historique'] as $k) {
        $feb[$k] = json_decode($feb[$k] ?? 'null', true) ?? [];
    }
    return $feb;
}

// ── Notifie tous les porteurs d'un code d'étape — même logique que
//    di_notify_role() (rôles ERP mappés + membres du département lié dans
//    di_roles), mais avec un lien vers l'écran de visas Achats : di_notify()
//    pointe en dur vers /pages/demandes.php, impropre ici.
function ach_notifier_role(string $roleCode, string $message, ?int $febId = null): void {
    $lien = '/pages/achats/mes_visas.php';
    $notified = [];
    foreach (db_fetch_all("SELECT id FROM users WHERE actif=1") as $u) {
        $uid = (int)$u['id'];
        if (in_array($roleCode, di_user_roles($uid), true)) {
            db_query("INSERT INTO notifications (user_id,type,titre,message,lien) VALUES (?,'info','Visa FEB',?,?)", [$uid, $message, $lien]);
            $notified[] = $uid;
        }
    }
    $dept_id = db_fetch_value("SELECT departement_id FROM di_roles WHERE code=?", [$roleCode]);
    if ($dept_id) {
        foreach (db_fetch_all("SELECT user_id FROM user_departements WHERE departement_id=?", [(int)$dept_id]) as $u) {
            $uid = (int)$u['user_id'];
            if (!in_array($uid, $notified, true)) {
                db_query("INSERT INTO notifications (user_id,type,titre,message,lien) VALUES (?,'info','Visa FEB',?,?)", [$uid, $message, $lien]);
                $notified[] = $uid;
            }
        }
    }
}

// ── Comptes SYSCOHADA connus (classes 60-65 et 24) — seeded par
//    migration_achats_08_syscohada.sql. Sert uniquement à l'avertissement
//    (non bloquant) de pages/achats/param_familles.php : un compte hors
//    liste n'empêche pas l'enregistrement, il est juste signalé.
function ach_comptes_syscohada_connus(): array {
    return array_column(db_fetch_all("SELECT DISTINCT compte_comptable FROM familles_achat WHERE compte_comptable IS NOT NULL"), 'compte_comptable');
}

// ── Situation budgétaire d'un couple département/famille pour un exercice
//    (Bloc 2). feb.departement_id filtre directement : il est constant sur
//    la FEB (un seul département, celui du demandeur — Bloc 0, point 1),
//    aucune jointure supplémentaire n'est nécessaire.
//    $exclure_feb_id : ignore les lignes de cette FEB dans le total — utile
//    pour afficher « la place disponible avant cette FEB », notamment sur
//    mes_visas.php (Bloc 5) où la FEB en cours de visa ne doit pas se
//    compter elle-même dans l'engagement affiché au signataire.
function ach_budget_situation(int $departement_id, int $famille_id, int $exercice, ?int $exclure_feb_id = null): array {
    $params = [$departement_id, $famille_id, $exercice];
    $exclude_sql = '';
    if ($exclure_feb_id) { $exclude_sql = ' AND f.id != ?'; $params[] = $exclure_feb_id; }

    $row = db_fetch_one(
        "SELECT
            COALESCE(SUM(CASE WHEN f.statut='confirmee'     THEN fl.montant_ttc ELSE 0 END),0) AS engage,
            COALESCE(SUM(CASE WHEN f.statut='en_validation' THEN fl.montant_ttc ELSE 0 END),0) AS reserve
         FROM feb_lignes fl
         JOIN feb f ON f.id = fl.feb_id
         WHERE f.departement_id=? AND fl.famille_id=? AND f.exercice=? AND fl.arbitrage='achat'
           AND f.statut IN ('confirmee','en_validation') $exclude_sql",
        $params
    );
    $engage  = (int)$row['engage'];
    $reserve = (int)$row['reserve'];

    // Rejet ou réouverture (ach_reprendre_feb_rejetee / ach_admin_reouvrir_validation)
    // font sortir la FEB de ('confirmee','en_validation') : la libération est
    // gratuite, portée par ce seul filtre de statut — rien d'autre à écrire.
    $ligne = db_fetch_one(
        "SELECT * FROM lignes_budgetaires WHERE departement_id=? AND famille_id=? AND exercice=? AND actif=1",
        [$departement_id, $famille_id, $exercice]
    );
    $enveloppe = ($ligne && $ligne['enveloppe'] !== null) ? (int)$ligne['enveloppe'] : null;

    return [
        'departement_id' => $departement_id, 'famille_id' => $famille_id, 'exercice' => $exercice,
        'engage' => $engage, 'reserve' => $reserve, 'total_engage' => $engage + $reserve,
        'ligne_budgetaire' => $ligne, 'enveloppe' => $enveloppe,
        'disponible' => $enveloppe !== null ? $enveloppe - ($engage + $reserve) : null,
    ];
}

// ── Contrôle budgétaire d'une FEB, famille par famille, contre le budget
//    de SON département (Bloc 3). Appelée à deux points seulement :
//    lancement de la validation et dernière signature (point 19) — jamais
//    aux étapes intermédiaires.
//    Verrou explicite (SELECT ... FOR UPDATE) sur chaque ligne budgétaire
//    concernée : sans lui, deux FEB lancées au même instant sur la même
//    enveloppe peuvent toutes deux lire un engagement encore bas et passer
//    ensemble au-delà du plafond. Le verrou n'a d'effet que tenu dans la
//    même transaction que le changement de statut — c'est aux appelants
//    (ach_lancer_validation, ach_viser) de l'ouvrir avant d'appeler cette
//    fonction et de la fermer juste après l'UPDATE sur feb.
//    Lève une AchValidationException en cas de dépassement bloquant ;
//    retourne la liste des avertissements (dépassements en mode "alerte",
//    ou familles sans ligne budgétaire — point 17) sinon.
function ach_controle_budget(int $feb_id): array {
    $feb = db_fetch_one("SELECT departement_id, exercice, numero FROM feb WHERE id=?", [$feb_id]);
    if (!$feb || !$feb['departement_id']) return ['avertissements' => []];
    $dept_id  = (int)$feb['departement_id'];
    $exercice = (int)$feb['exercice'];

    $familles = db_fetch_all(
        "SELECT famille_id, SUM(montant_ttc) AS montant FROM feb_lignes
          WHERE feb_id=? AND arbitrage='achat' AND famille_id IS NOT NULL
          GROUP BY famille_id",
        [$feb_id]
    );

    $avertissements = [];
    foreach ($familles as $f) {
        $famille_id    = (int)$f['famille_id'];
        $montant_ligne = (int)$f['montant'];

        $ligne_budget = db_fetch_one(
            "SELECT * FROM lignes_budgetaires
              WHERE departement_id=? AND famille_id=? AND exercice=? AND actif=1
              FOR UPDATE",
            [$dept_id, $famille_id, $exercice]
        );
        if (!$ligne_budget) {
            $fam_nom = db_fetch_value("SELECT libelle FROM familles_achat WHERE id=?", [$famille_id]);
            $avertissements[] = "Aucune ligne budgétaire pour « $fam_nom » sur ce département — non contrôlé.";
            continue;
        }
        if ($ligne_budget['enveloppe'] === null || $ligne_budget['comportement'] === 'aucun') continue;

        $situation   = ach_budget_situation($dept_id, $famille_id, $exercice, $feb_id);
        $total_apres = $situation['total_engage'] + $montant_ligne;
        if ($total_apres > (int)$ligne_budget['enveloppe']) {
            $depassement = $total_apres - (int)$ligne_budget['enveloppe'];
            $dept_code = db_fetch_value("SELECT code FROM departements WHERE id=?", [$dept_id]);
            $fam_nom   = db_fetch_value("SELECT libelle FROM familles_achat WHERE id=?", [$famille_id]);
            $msg = "Département $dept_code, famille $fam_nom : dépassement de " . number_format($depassement, 0, ',', ' ') . " XOF.";
            if ($ligne_budget['comportement'] === 'blocage') {
                throw new AchValidationException($msg);
            }
            $avertissements[] = $msg;
        }
    }
    return ['avertissements' => $avertissements];
}

// ── Lance la validation d'une FEB — calcule et fige le circuit (Bloc 2).
//    Réservé à l'acheteur qui détient la FEB, comme le reste du traitement.
function ach_lancer_validation(int $feb_id, array $user): array {
    $uid = (int)$user['id'];
    $feb = db_fetch_one("SELECT * FROM feb WHERE id=?", [$feb_id]);
    if (!$feb) throw new AchValidationException('FEB introuvable.');
    if ((int)$feb['acheteur_id'] !== $uid || $feb['statut'] !== 'prise_en_charge') {
        throw new AchValidationException("Cette FEB n'est pas (ou plus) en cours de traitement par vous.");
    }

    $verif = ach_verifier_comparatif($feb_id);
    if (!$verif['ok']) throw new AchValidationException($verif['message']);

    $nb_achat = (int) db_fetch_value("SELECT COUNT(*) FROM feb_lignes WHERE feb_id=? AND arbitrage='achat'", [$feb_id]);
    if ($nb_achat === 0) throw new AchValidationException('Aucune ligne à acheter ne subsiste — rien à faire signer.');

    // RG-10 : le circuit se calcule sur le montant total de la FEB, jamais
    // par ligne ni par lot.
    $montant = (int)$feb['montant_total'];
    $palier  = ach_palier_pour_montant($montant);
    if (!$palier) {
        // Symptôme d'une grille trouée (RG-13) : le message nomme le montant
        // pour qu'on aille corriger la grille, pas deviner pourquoi ça bloque.
        throw new AchValidationException("Aucun palier ne couvre le montant de $montant XOF — vérifiez la grille des paliers de validation.");
    }
    if (!$palier['signataires']) {
        throw new AchValidationException("Le palier « {$palier['libelle']} » n'a aucun signataire configuré.");
    }

    // RG-11 : le figeage a lieu ici, au lancement — jamais à la soumission,
    // puisque le montant n'est connu qu'une fois le comparatif terminé.
    $labels = array_column(db_fetch_all("SELECT code, label FROM di_roles"), 'label', 'code');
    $workflow = array_map(fn($code) => ['role' => $code, 'label' => $labels[$code] ?? $code], $palier['signataires']);

    $now = date('Y-m-d H:i:s');
    $nom = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
    $historique = [['action' => 'lancement_validation', 'par' => $uid, 'nom' => $nom,
        'commentaire' => "Palier « {$palier['libelle']} » — $montant XOF", 'date' => $now]];

    // Bloc 3, point 19 : premier des deux points de contrôle budgétaire.
    // Le verrou posé par ach_controle_budget() (SELECT ... FOR UPDATE) doit
    // rester tenu jusqu'à l'UPDATE de statut, donc les deux dans la même
    // transaction.
    $pdo = get_db();
    $transaction_locale = !$pdo->inTransaction();
    if ($transaction_locale) db_begin();
    try {
        $controle = ach_controle_budget($feb_id);
        db_query(
            "UPDATE feb SET workflow_snapshot=?::jsonb, etape_actuelle=0, statut='en_validation',
                    date_lancement_validation=?, signatures='[]'::jsonb, historique=?::jsonb,
                    etape_rejet=NULL, motif_rejet=NULL
              WHERE id=?",
            [json_encode($workflow, JSON_UNESCAPED_UNICODE), $now, json_encode($historique, JSON_UNESCAPED_UNICODE), $feb_id]
        );
        audit_log($uid, 'UPDATE', 'achats', $feb_id, "Lancement de la validation — palier « {$palier['libelle']} », $montant XOF, " . count($workflow) . ' étape(s)');
        if ($transaction_locale) db_commit();
    } catch (Exception $e) {
        if ($transaction_locale) db_rollback();
        throw $e;
    }

    ach_notifier_role($workflow[0]['role'], "FEB {$feb['numero']} en attente de votre visa — étape : {$workflow[0]['label']}.", $feb_id);

    return ['etapes' => count($workflow), 'palier' => $palier['libelle'], 'avertissements' => $controle['avertissements']];
}

// ── Vise (accepte ou rejette) l'étape courante d'une FEB en validation.
//    Réutilise di_can_validate()/di_next_step() tels quels — aucune règle
//    de droits n'est réécrite (Bloc 3, point 16).
//    Le verrou est l'UPDATE conditionnel WHERE etape_actuelle=? (comme
//    ach_prendre_en_charge en J3) : deux signataires du même département
//    qui cliquent ensemble ne peuvent pas produire deux signatures — celui
//    qui arrive en second obtient rowCount=0 et se voit répondre false.
function ach_viser(int $feb_id, array $user, bool $accepte, string $commentaire = ''): bool {
    $uid = (int)$user['id'];
    if (!$accepte && trim($commentaire) === '') {
        throw new AchValidationException('Le motif de rejet est obligatoire.');
    }

    $row = db_fetch_one("SELECT * FROM feb WHERE id=?", [$feb_id]);
    if (!$row) throw new AchValidationException('FEB introuvable.');
    if ($row['statut'] !== 'en_validation') throw new AchValidationException("Cette FEB n'est pas en cours de validation.");
    $feb = ach_feb_decode($row);
    $cur = (int)$feb['etape_actuelle'];
    $wf  = $feb['workflow_snapshot'];

    $roles = di_user_roles($uid);
    if (!di_can_validate($roles, $uid, $wf, $cur, (int)$feb['demandeur_id'], null)) {
        throw new AchValidationException('Vous ne pouvez pas viser cette étape.');
    }

    $now = date('Y-m-d H:i:s');
    $nom = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
    $etape_label = $wf[$cur]['label'] ?? $wf[$cur]['role'];

    $signatures   = $feb['signatures'];
    $signatures[] = ['etape' => $cur, 'etape_label' => $etape_label, 'user_id' => $uid, 'nom' => $nom,
        'action' => $accepte ? 'approuve' : 'rejete', 'commentaire' => $commentaire, 'date' => $now];
    $historique   = $feb['historique'];
    $historique[] = ['action' => $accepte ? 'valide' : 'rejete', 'etape' => $etape_label, 'par' => $uid,
        'nom' => $nom, 'commentaire' => $commentaire, 'date' => $now];
    $sigJson  = json_encode($signatures, JSON_UNESCAPED_UNICODE);
    $histJson = json_encode($historique, JSON_UNESCAPED_UNICODE);

    if (!$accepte) {
        $stmt = db_query(
            "UPDATE feb SET statut='rejetee', etape_rejet=?, motif_rejet=?, signatures=?::jsonb, historique=?::jsonb
              WHERE id=? AND etape_actuelle=?",
            [$cur, $commentaire, $sigJson, $histJson, $feb_id, $cur]
        );
        if ($stmt->rowCount() === 0) return false;
        audit_log($uid, 'UPDATE', 'achats', $feb_id, "Rejet à l'étape « $etape_label » : $commentaire");
        if ($feb['demandeur_id'])  ach_notifier_demandeur_feb($feb_id, "Votre FEB {$feb['numero']} a été rejetée à l'étape « $etape_label » : $commentaire");
        if ($feb['acheteur_id'])   db_query("INSERT INTO notifications (user_id,type,titre,message,lien) VALUES (?,'info','FEB rejetée',?,?)",
            [(int)$feb['acheteur_id'], "FEB {$feb['numero']} rejetée à l'étape « $etape_label » : $commentaire", '/pages/achats/file_attente.php']);
        return true;
    }

    $next = di_next_step($wf, $cur);
    if ($next === null) {
        // Bloc 3, point 19 : second des deux points de contrôle budgétaire —
        // d'autres FEB sur la même enveloppe ont pu être confirmées entre le
        // lancement de celle-ci et sa dernière signature. Verrou et UPDATE
        // dans la même transaction, comme au lancement.
        db_begin();
        try {
            $controle = ach_controle_budget($feb_id);
            $stmt = db_query(
                "UPDATE feb SET statut='confirmee', date_confirmation=?, signatures=?::jsonb, historique=?::jsonb
                  WHERE id=? AND etape_actuelle=?",
                [$now, $sigJson, $histJson, $feb_id, $cur]
            );
            if ($stmt->rowCount() === 0) { db_rollback(); return false; }
            db_commit();
        } catch (Exception $e) {
            db_rollback();
            throw $e;
        }
        audit_log($uid, 'UPDATE', 'achats', $feb_id, "Dernière signature obtenue (étape « $etape_label ») — FEB confirmée");
        ach_notifier_demandeur_feb($feb_id, "Votre FEB {$feb['numero']} est entièrement signée.");
        if ($feb['acheteur_id']) db_query("INSERT INTO notifications (user_id,type,titre,message,lien) VALUES (?,'info','FEB confirmée',?,?)",
            [(int)$feb['acheteur_id'], "FEB {$feb['numero']} entièrement signée.", '/pages/achats/file_attente.php']);
        return true;
    }

    $stmt = db_query(
        "UPDATE feb SET etape_actuelle=?, signatures=?::jsonb, historique=?::jsonb WHERE id=? AND etape_actuelle=?",
        [$next, $sigJson, $histJson, $feb_id, $cur]
    );
    if ($stmt->rowCount() === 0) return false;
    audit_log($uid, 'UPDATE', 'achats', $feb_id, "Étape « $etape_label » signée — passage à « {$wf[$next]['label']} »");
    ach_notifier_role($wf[$next]['role'], "FEB {$feb['numero']} en attente de votre visa — étape : {$wf[$next]['label']}.", $feb_id);
    return true;
}

function ach_notifier_demandeur_feb(int $feb_id, string $message): void {
    $demandeur_id = db_fetch_value("SELECT demandeur_id FROM feb WHERE id=?", [$feb_id]);
    if (!$demandeur_id) return;
    db_query("INSERT INTO notifications (user_id,type,titre,message,lien) VALUES (?,'info','FEB',?,?)",
        [(int)$demandeur_id, $message, '/pages/achats/mes_feb.php']);
}

// ── Reprise d'une FEB rejetée par son acheteur — le rejet n'est pas une
//    mort : retour en prise_en_charge, signatures effacées, circuit à
//    recalculer (nouvel appel à ach_lancer_validation quand l'acheteur est prêt).
function ach_reprendre_feb_rejetee(int $feb_id, array $user): bool {
    $uid = (int)$user['id'];
    $stmt = db_query(
        "UPDATE feb SET statut='prise_en_charge', etape_actuelle=-1, signatures='[]'::jsonb,
                workflow_snapshot=NULL, etape_rejet=NULL, motif_rejet=NULL
          WHERE id=? AND acheteur_id=? AND statut='rejetee'",
        [$feb_id, $uid]
    );
    if ($stmt->rowCount() === 0) return false;
    audit_log($uid, 'UPDATE', 'achats', $feb_id, 'Reprise de la FEB rejetée — circuit à relancer');
    return true;
}

// ── Réouverture administrative d'une FEB en cours de validation (Bloc 5,
//    RG-12) : les offres et montants sont verrouillés dès en_validation par
//    le contrôle d'accès déjà en place sur pages/achats/feb_traitement.php
//    (acheteur_id + statut='prise_en_charge') — rien à ajouter là. Cette
//    fonction est l'unique porte de sortie, réservée à un administrateur,
//    tracée explicitement : elle annule les signatures et remet la FEB à
//    disposition de son acheteur, qui corrige puis relance
//    ach_lancer_validation() — c'est ce second appel qui recalcule le
//    palier et repart à l'étape 0.
function ach_admin_reouvrir_validation(int $feb_id, array $user): bool {
    $uid = (int)$user['id'];
    $stmt = db_query(
        "UPDATE feb SET statut='prise_en_charge', etape_actuelle=-1, signatures='[]'::jsonb,
                workflow_snapshot=NULL, etape_rejet=NULL, motif_rejet=NULL, date_lancement_validation=NULL
          WHERE id=? AND statut='en_validation'",
        [$feb_id]
    );
    if ($stmt->rowCount() === 0) return false;
    audit_log($uid, 'UPDATE', 'achats', $feb_id,
        "Réouverture administrative : signatures annulées, circuit à relancer depuis l'étape 0 (RG-12)");
    return true;
}

// ── FEB que CET utilisateur peut viser — même principe que di_a_valider().
function ach_a_viser(array $user): array {
    $uid   = (int)$user['id'];
    $roles = di_user_roles($uid);
    $has_dept_role = (bool)db_fetch_value(
        "SELECT COUNT(*) FROM user_departements ud JOIN di_roles dr ON dr.departement_id = ud.departement_id WHERE ud.user_id=?",
        [$uid]
    );
    if (!$roles && !$has_dept_role) return [];

    $febs = db_fetch_all(
        "SELECT * FROM feb WHERE statut='en_validation' AND demandeur_id <> ? ORDER BY date_lancement_validation ASC",
        [$uid]
    );
    $out = [];
    foreach ($febs as $f) {
        $f = ach_feb_decode($f);
        if (di_can_validate($roles, $uid, $f['workflow_snapshot'], (int)$f['etape_actuelle'], (int)$f['demandeur_id'], null)) {
            $out[] = $f;
        }
    }
    return $out;
}
