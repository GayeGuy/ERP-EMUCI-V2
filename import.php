<?php
$secret = $_GET['secret'] ?? '';
if ($secret !== 'emuci2026import') die('Accès refusé');
require_once __DIR__ . '/includes/db.php';
$db = get_db();

$sqls = [];
$sqls[] = 'SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `affectations_equipements` (
  `id` int(10) UNSIGNED NOT NULL,
  `equipement_id` int(10) UNSIGNED NOT NULL,
  `site_dest_id` int(10) UNSIGNED DEFAULT NULL,
  `user_dest_id` int(10) UNSIGNED DEFAULT NULL,
  `statut` enum(\\'en_attente\\',\\'valide_n1\\',\\'recu\\',\\'refuse\\') NOT NULL DEFAULT \\'en_attente\\',
  `pdf_path` varchar(255) DEFAULT NULL COMMENT \\'PDF fiche affectation\\',
  `pdf_signe_n1` varchar(255) DEFAULT NULL COMMENT \\'PDF signé N+1\\',
  `pdf_signe_site` varchar(255) DEFAULT NULL COMMENT \\'PDF signé chef de site\\',
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `valide_n1_by` int(10) UNSIGNED DEFAULT NULL,
  `valide_n1_at` datetime DEFAULT NULL,
  `recu_by` int(10) UNSIGNED DEFAULT NULL,
  `recu_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` enum(\\'CREATE\\',\\'READ\\',\\'UPDATE\\',\\'DELETE\\',\\'LOGIN\\',\\'LOGOUT\\',\\'EXPORT\\',\\'TRANSFER\\') NOT NULL,
  `module` varchar(60) NOT NULL,
  `entite_id` int(10) UNSIGNED DEFAULT NULL,
  `description` text NOT NULL,
  `ancienne_valeur` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ancienne_valeur`)),
  `nouvelle_valeur` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`nouvelle_valeur`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bilans_mensuels_bobines` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `mois` varchar(7) NOT NULL COMMENT \\'Format YYYY-MM\\',
  `inventaire_id` int(10) UNSIGNED DEFAULT NULL COMMENT \\'Inventaire mensuel lié\\',
  `stock_debut_mois` int(11) DEFAULT 0 COMMENT \\'Stock système au 1er du mois\\',
  `stock_fin_mois` int(11) DEFAULT 0 COMMENT \\'Stock système à la fin du mois\\',
  `total_films_consommes` int(11) DEFAULT 0 COMMENT \\'Total films consommés (DigiStock)\\',
  `total_films_emuci` int(11) DEFAULT 0 COMMENT \\'Total films déduits (EMUCI)\\',
  `ecart_mois` int(11) DEFAULT 0 COMMENT \\'Écart total du mois EMUCI vs DigiStock\\',
  `nb_inventaires_journaliers` int(11) DEFAULT 0,
  `nb_ajustements` int(11) DEFAULT 0,
  `statut` enum(\\'en_cours\\',\\'valide\\') NOT NULL DEFAULT \\'en_cours\\',
  `valide_par` int(10) UNSIGNED DEFAULT NULL,
  `valide_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `commandes` (
  `id` int(10) UNSIGNED NOT NULL,
  `numero_commande` varchar(30) NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `statut` enum(\\'en_attente\\',\\'en_attente_livraison\\',\\'livre\\',\\'annule\\') NOT NULL DEFAULT \\'en_attente\\',
  `notes` text DEFAULT NULL,
  `notes_livraison` text DEFAULT NULL,
  `livraison_par` int(10) UNSIGNED DEFAULT NULL,
  `livraison_at` datetime DEFAULT NULL,
  `recu_par` int(10) UNSIGNED DEFAULT NULL,
  `recu_at` datetime DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\\'Commandes des coordinateurs\\';

CREATE TABLE `commande_lignes` (
  `id` int(10) UNSIGNED NOT NULL,
  `commande_id` int(10) UNSIGNED NOT NULL,
  `type_article` enum(\\'consommable\\',\\'bobine\\',\\'equipement\\',\\'pmma\\',\\'autre\\') NOT NULL DEFAULT \\'consommable\\',
  `article_id` int(10) UNSIGNED DEFAULT NULL COMMENT \\'ID de l article dans sa table respective\\',
  `libelle` varchar(255) NOT NULL,
  `quantite` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `unite` varchar(30) DEFAULT \\'unité\\',
  `prix_unitaire` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\\'Lignes de commandes\\';

CREATE TABLE `comparaisons_stock` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `date_comparaison` date NOT NULL,
  `films_emuci` int(11) NOT NULL DEFAULT 0 COMMENT \\'Films déduits selon EMUCI (plaques posées + réservées)\\',
  `films_digistock` int(11) NOT NULL DEFAULT 0 COMMENT \\'Films consommés selon PJ coordinateurs DigiStock\\',
  `ecart` int(11) GENERATED ALWAYS AS (`films_emuci` - `films_digistock`) STORED COMMENT \\'Écart = EMUCI - DigiStock (positif = DigiStock sous-estimé)\\',
  `statut_ecart` enum(\\'ok\\',\\'mineur\\',\\'majeur\\') GENERATED ALWAYS AS (case when abs(`films_emuci` - `films_digistock`) = 0 then \\'ok\\' when abs(`films_emuci` - `films_digistock`) <= 5 then \\'mineur\\' else \\'majeur\\' end) STORED,
  `ajuste` tinyint(1) DEFAULT 0,
  `ajuste_par` int(10) UNSIGNED DEFAULT NULL,
  `ajuste_at` datetime DEFAULT NULL,
  `notes_ajustement` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\\'Comparaison quotidienne EMUCI vs DigiStock par site\\';

CREATE TABLE `configurations_site` (
  `id` int(10) UNSIGNED NOT NULL,
  `type_site` enum(\\'saisie\\',\\'pose\\',\\'mixte\\',\\'caissier\\',\\'entrepot\\',\\'siege\\') NOT NULL,
  `nomenclature_id` int(10) UNSIGNED NOT NULL,
  `quantite` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `optionnel` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `config_postes_composants` (
  `id` int(10) UNSIGNED NOT NULL,
  `poste_id` int(10) UNSIGNED NOT NULL,
  `nomenclature_id` int(10) UNSIGNED NOT NULL,
  `quantite` int(10) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `config_postes_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `libelle` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `consommables` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `libelle` varchar(150) NOT NULL,
  `unite` enum(\\'unite\\',\\'kg\\',\\'litre\\',\\'boite\\',\\'rame\\',\\'paquet\\') NOT NULL DEFAULT \\'unite\\',
  `description` text DEFAULT NULL,
  `seuil_alerte` decimal(10,2) DEFAULT 10.00,
  `prix_unitaire` decimal(12,2) DEFAULT 0.00,
  `stock_global` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `categorie` varchar(100) DEFAULT NULL COMMENT \\'Catégorie ex: Papeterie, Informatique, Entretien...\\'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `consommations_bobines` (
  `id` int(10) UNSIGNED NOT NULL,
  `bobine_id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `date_conso` date NOT NULL,
  `quantite` int(10) UNSIGNED NOT NULL COMMENT \\'Quantité consommée ce jour (rouleaux)\\',
  `stock_avant` int(10) UNSIGNED NOT NULL COMMENT \\'Stock avant consommation\\',
  `stock_apres` int(10) UNSIGNED NOT NULL COMMENT \\'Stock après consommation\\',
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `delegations` (
  `id` int(10) UNSIGNED NOT NULL,
  `superviseur_id` int(10) UNSIGNED NOT NULL COMMENT \\'Superviseur qui délègue\\',
  `gestionnaire_id` int(10) UNSIGNED NOT NULL COMMENT \\'Gestionnaire Opération qui reçoit\\',
  `module` varchar(50) NOT NULL COMMENT \\'Module délégué ex: validation_pj, bobines, inventaire\\',
  `libelle` varchar(100) NOT NULL COMMENT \\'Label affiché\\',
  `actif` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\\'Tâches déléguées du Superviseur au Gestionnaire Opération\\';

CREATE TABLE `demandes_bobines` (
  `id` int(10) UNSIGNED NOT NULL,
  `bobine_id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `demande_par` int(10) UNSIGNED NOT NULL COMMENT \\'Coordinateur\\',
  `motif` text NOT NULL COMMENT \\'Motif de la demande\\',
  `statut` enum(\\'en_attente\\',\\'approuvee\\',\\'refusee\\') NOT NULL DEFAULT \\'en_attente\\',
  `traite_par` int(10) UNSIGNED DEFAULT NULL COMMENT \\'GSB qui a traité\\',
  `traite_at` datetime DEFAULT NULL,
  `motif_reponse` text DEFAULT NULL COMMENT \\'Motif refus ou commentaire approbation\\',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\\'Demandes utilisation bobine coordinateur → GSB\\';

CREATE TABLE `ecarts_bobines` (
  `id` int(10) UNSIGNED NOT NULL,
  `bobine_id` int(10) UNSIGNED NOT NULL,
  `date_constat` date NOT NULL COMMENT \\'Date à laquelle l écart a été constaté\\',
  `stock_systeme` int(11) NOT NULL COMMENT \\'Stock système au moment du constat\\',
  `stock_physique` int(11) NOT NULL COMMENT \\'Stock physique compté\\',
  `ecart` int(11) NOT NULL COMMENT \\'physique - système (négatif = manque)\\',
  `motif` text DEFAULT NULL COMMENT \\'Explication de l écart\\',
  `source` enum(\\'inventaire\\',\\'manuel\\',\\'import\\') NOT NULL DEFAULT \\'manuel\\',
  `inventaire_id` int(10) UNSIGNED DEFAULT NULL COMMENT \\'Lien vers la session d inventaire si source=inventaire\\',
  `statut` enum(\\'ouvert\\',\\'resolu\\',\\'ignore\\') NOT NULL DEFAULT \\'ouvert\\',
  `resolu_at` datetime DEFAULT NULL,
  `resolu_par` int(10) UNSIGNED DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `equipements` (
  `id` int(10) UNSIGNED NOT NULL,
  `numero_serie_interne` varchar(50) NOT NULL,
  `numero_serie_origine` varchar(100) DEFAULT NULL,
  `numero_chrono` int(10) UNSIGNED NOT NULL,
  `nomenclature_id` int(10) UNSIGNED NOT NULL,
  `categorie` enum(\\'informatique\\',\\'operationnel\\') NOT NULL DEFAULT \\'informatique\\' COMMENT \\'Catégorie : informatique ou opérationnel\\',
  `site_id` int(10) UNSIGNED DEFAULT NULL,
  `utilisateur_id` int(10) UNSIGNED DEFAULT NULL,
  `etat` enum(\\'neuf\\',\\'bon\\',\\'usage\\',\\'hs\\',\\'reforme\\') NOT NULL DEFAULT \\'neuf\\',
  `date_acquisition` date DEFAULT NULL,
  `date_mise_en_service` date DEFAULT NULL,
  `date_fin_cycle` date DEFAULT NULL,
  `marque` varchar(100) DEFAULT NULL,
  `modele` varchar(100) DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `duree_amortissement_mois` int(10) UNSIGNED DEFAULT NULL COMMENT \\'Durée amortissement OHADA en mois\\',
  `prix_achat` decimal(15,2) DEFAULT 0.00 COMMENT \\'Prix achat en FCFA\\',
  `statut_stock` enum(\\'affecte\\',\\'en_stock\\') NOT NULL DEFAULT \\'affecte\\' COMMENT \\'affecte=en service, en_stock=disponible non affecté\\'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `import_optoplate` (
  `id` int(10) UNSIGNED NOT NULL,
  `import_session_id` varchar(36) NOT NULL COMMENT \\'UUID de la session d import\\',
  `date_import` date NOT NULL COMMENT \\'Date du fichier importé\\',
  `date_installation` datetime DEFAULT NULL COMMENT \\'Date d installation de la plaque\\',
  `numero_dossier` varchar(50) DEFAULT NULL,
  `immatriculation` varchar(30) NOT NULL,
  `vin` varchar(20) DEFAULT NULL,
  `type_plaque` varchar(60) DEFAULT NULL,
  `statut_plaque` varchar(30) NOT NULL COMMENT \\'in_use, declared_broken, reserved, ad-print, inused_need_reprint, lost\\',
  `position` varchar(10) DEFAULT NULL COMMENT \\'front / rear\\',
  `num_consommable` varchar(30) DEFAULT NULL COMMENT \\'N° du film\\',
  `num_bobine` varchar(50) DEFAULT NULL COMMENT \\'N° de la bobine\\',
  `site_id_emuci` varchar(20) DEFAULT NULL COMMENT \\'Id site dans EMUCI\\',
  `site_nom_emuci` varchar(100) DEFAULT NULL COMMENT \\'Nom site dans EMUCI\\',
  `site_id` int(10) UNSIGNED DEFAULT NULL COMMENT \\'FK vers sites DigiStock\\',
  `importe_par` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\\'Import quotidien OptoPlate — plaques posées\\';

CREATE TABLE `import_optotrace` (
  `id` int(10) UNSIGNED NOT NULL,
  `import_session_id` varchar(36) NOT NULL,
  `date_import` date NOT NULL,
  `plate_number` varchar(30) NOT NULL COMMENT \\'Numéro de plaque\\',
  `vin` varchar(20) DEFAULT NULL,
  `case_number` varchar(30) DEFAULT NULL COMMENT \\'Numéro de dossier\\',
  `category` varchar(60) DEFAULT NULL COMMENT \\'VOITURE PARTICULIERE, CAMION...\\',
  `site_nom_emuci` varchar(100) DEFAULT NULL,
  `site_id` int(10) UNSIGNED DEFAULT NULL,
  `installation_date` datetime DEFAULT NULL,
  `is_last` tinyint(1) DEFAULT 0,
  `is_deleted` tinyint(1) DEFAULT 0,
  `importe_par` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\\'Import quotidien OptoTrace — registre plaques/véhicules\\';

CREATE TABLE `import_sessions_emuci` (
  `id` varchar(36) NOT NULL,
  `date_import` date NOT NULL,
  `type_import` enum(\\'optoplate\\',\\'optotrace\\',\\'les_deux\\') NOT NULL,
  `nb_lignes_optoplate` int(11) DEFAULT 0,
  `nb_lignes_optotrace` int(11) DEFAULT 0,
  `nb_erreurs` int(11) DEFAULT 0,
  `statut` enum(\\'en_cours\\',\\'termine\\',\\'erreur\\') DEFAULT \\'en_cours\\',
  `importe_par` int(10) UNSIGNED NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\\'Sessions d import EMUCI\\';

CREATE TABLE `interventions_maintenance` (
  `id` int(10) UNSIGNED NOT NULL,
  `technicien_id` int(10) UNSIGNED NOT NULL COMMENT \\'Utilisateur maintenance_info\\',
  `site_id` int(10) UNSIGNED NOT NULL,
  `equipement_id` int(10) UNSIGNED DEFAULT NULL,
  `date_intervention` date NOT NULL,
  `type_action` enum(\\'maintenance_preventive\\',\\'maintenance_corrective\\',\\'installation\\',\\'remplacement\\',\\'diagnostic\\',\\'formation\\',\\'autre\\') NOT NULL DEFAULT \\'maintenance_corrective\\',
  `description` text NOT NULL COMMENT \\'Description de l action effectuée\\',
  `probleme_signale` text DEFAULT NULL COMMENT \\'Problème constaté avant intervention\\',
  `solution_apportee` text DEFAULT NULL COMMENT \\'Solution mise en place\\',
  `statut_apres` enum(\\'resolu\\',\\'partiel\\',\\'en_attente\\',\\'escalade\\') NOT NULL DEFAULT \\'resolu\\',
  `duree_minutes` int(10) UNSIGNED DEFAULT NULL COMMENT \\'Durée de l intervention en minutes\\',
  `pieces_changees` text DEFAULT NULL COMMENT \\'Pièces ou équipements remplacés\\',
  `rapport_fichier` varchar(255) DEFAULT NULL COMMENT \\'Fichier rapport signé (PDF/image)\\',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventaires_bobines` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED DEFAULT NULL COMMENT \\'NULL = inventaire global\\',
  `date_inventaire` date NOT NULL,
  `statut` enum(\\'brouillon\\',\\'valide\\',\\'annule\\') NOT NULL DEFAULT \\'brouillon\\',
  `nb_bobines` int(10) UNSIGNED DEFAULT 0,
  `nb_ecarts` int(10) UNSIGNED DEFAULT 0,
  `notes` text DEFAULT NULL,
  `cree_par` int(10) UNSIGNED DEFAULT NULL,
  `valide_par` int(10) UNSIGNED DEFAULT NULL,
  `valide_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `type_inventaire` enum(\\'journalier\\',\\'mensuel\\') NOT NULL DEFAULT \\'journalier\\' COMMENT \\'Journalier = suivi quotidien, Mensuel = bilan de fin de mois\\',
  `total_films_systeme` int(11) DEFAULT 0 COMMENT \\'Total films stock système au moment de l inventaire\\',
  `total_films_physique` int(11) DEFAULT 0 COMMENT \\'Total films comptés physiquement\\',
  `total_films_emuci` int(11) DEFAULT 0 COMMENT \\'Total films déduits selon EMUCI (point contrôleur)\\',
  `ecart_digistock_emuci` int(11) DEFAULT 0 COMMENT \\'Écart entre DigiStock et EMUCI\\'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventaire_details_bobines` (
  `id` int(10) UNSIGNED NOT NULL,
  `inventaire_id` int(10) UNSIGNED NOT NULL,
  `bobine_id` int(10) UNSIGNED NOT NULL,
  `stock_systeme` int(10) UNSIGNED NOT NULL COMMENT \\'Stock système au moment de l inventaire\\',
  `stock_physique` int(10) UNSIGNED NOT NULL COMMENT \\'Stock compté physiquement\\',
  `ecart` int(11) NOT NULL COMMENT \\'physique - système (négatif = manque)\\',
  `conso_quotidienne_moy` decimal(8,2) DEFAULT NULL COMMENT \\'Consommation journalière moyenne calculée sur les 30 derniers jours\\',
  `jours_restants_systeme` int(11) DEFAULT NULL COMMENT \\'Jours avant épuisement selon stock système\\',
  `jours_restants_physique` int(11) DEFAULT NULL COMMENT \\'Jours avant épuisement selon stock physique\\',
  `date_epuisement_estime` date DEFAULT NULL COMMENT \\'Date estimée d épuisement selon stock physique\\',
  `notes` text DEFAULT NULL,
  `qte_temps_reel` int(11) DEFAULT NULL COMMENT \\'Stock temps réel au moment de l inventaire (mis à jour en continu)\\',
  `ecart_connu_avant` int(11) DEFAULT NULL COMMENT \\'Écart connu AVANT cet inventaire (depuis dernier inventaire validé)\\',
  `films_emuci_jour` int(11) DEFAULT NULL COMMENT \\'Films déduits selon point EMUCI du jour\\',
  `ecart_emuci_digi` int(11) DEFAULT NULL COMMENT \\'Écart EMUCI vs DigiStock pour ce jour\\'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `litige_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `reception_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `livraisons_consommables` (
  `id` int(10) UNSIGNED NOT NULL,
  `consommable_id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `type_mouvement` enum(\\'distribution\\',\\'retour\\') DEFAULT \\'distribution\\',
  `quantite` decimal(10,2) NOT NULL,
  `prix_unitaire` decimal(12,2) DEFAULT 0.00,
  `prix_total` decimal(12,2) DEFAULT 0.00,
  `date_livraison` date NOT NULL,
  `bon_livraison` varchar(100) DEFAULT NULL,
  `fichier_bl` varchar(255) DEFAULT NULL COMMENT \\'Fichier bon de livraison (PDF/image) — obligatoire\\',
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mouvements_bobines` (
  `id` int(10) UNSIGNED NOT NULL,
  `bobine_id` int(10) UNSIGNED NOT NULL,
  `type` enum(\\'entree\\',\\'sortie\\',\\'ajustement_inventaire\\',\\'transfert\\') NOT NULL,
  `quantite` int(11) NOT NULL COMMENT \\'Positif = entrée, Négatif = sortie\\',
  `stock_avant` int(10) UNSIGNED NOT NULL,
  `stock_apres` int(10) UNSIGNED NOT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `ref_id` int(10) UNSIGNED DEFAULT NULL COMMENT \\'ID consommation ou inventaire source\\',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mouvements_equipements` (
  `id` int(10) UNSIGNED NOT NULL,
  `equipement_id` int(10) UNSIGNED NOT NULL,
  `type` enum(\\'entree\\',\\'sortie\\',\\'transfert\\',\\'reforme\\',\\'maintenance\\') NOT NULL,
  `site_source_id` int(10) UNSIGNED DEFAULT NULL,
  `site_dest_id` int(10) UNSIGNED DEFAULT NULL,
  `user_dest_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `fichier_bl` varchar(255) DEFAULT NULL COMMENT \\'Fichier bon de livraison (PDF/image) — obligatoire pour sorties/transferts\\',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `nomenclatures` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(10) NOT NULL,
  `categorie` enum(\\'informatique\\',\\'operationnel\\') NOT NULL DEFAULT \\'informatique\\' COMMENT \\'Catégorie de la nomenclature\\',
  `libelle` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `duree_vie_mois` int(10) UNSIGNED DEFAULT NULL,
  `seuil_alerte` int(10) UNSIGNED DEFAULT 5,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `nomenclature_liens` (
  `id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED NOT NULL,
  `enfant_id` int(10) UNSIGNED NOT NULL,
  `quantite` int(10) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `type` enum(\\'fin_cycle\\',\\'stock_bas\\',\\'alerte_conso\\',\\'info\\') NOT NULL,
  `titre` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `lien` varchar(255) DEFAULT NULL,
  `lu` tinyint(1) DEFAULT 0,
  `email_envoye` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `op_bobines` (
  `id` int(10) UNSIGNED NOT NULL,
  `numero` varchar(50) NOT NULL COMMENT \\'Numéro unique ex: A001-XK7-2024\\',
  `type_code` varchar(10) NOT NULL COMMENT \\'A001..D006\\',
  `serie` char(1) NOT NULL COMMENT \\'A,B,C,D\\',
  `type_vehicule_id` int(10) UNSIGNED DEFAULT NULL,
  `films_total` int(10) UNSIGNED NOT NULL DEFAULT 500,
  `films_utilises` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `films_endommages` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `films_restants` int(10) UNSIGNED NOT NULL DEFAULT 500,
  `site_id` int(10) UNSIGNED DEFAULT NULL,
  `statut` enum(\\'en_stock\\',\\'en_cours\\',\\'retiree\\',\\'epuisee\\',\\'perdue\\') NOT NULL DEFAULT \\'en_stock\\',
  `date_ouverture` date DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `qte_initiale` int(10) UNSIGNED NOT NULL DEFAULT 500 COMMENT \\'Quantité initiale en rouleaux (toujours 500)\\',
  `stock_systeme` int(10) UNSIGNED NOT NULL DEFAULT 500 COMMENT \\'Stock calculé par le système (basé sur les consommations saisies)\\',
  `stock_physique` int(10) UNSIGNED DEFAULT NULL COMMENT \\'Dernier stock physique constaté lors d un inventaire\\',
  `dernier_inventaire_id` int(10) UNSIGNED DEFAULT NULL,
  `date_creation` date DEFAULT curdate(),
  `format` varchar(50) DEFAULT NULL COMMENT \\'Format de la bobine (ex: A4, A3, rouleau 80m...)\\',
  `notes_perte` text DEFAULT NULL COMMENT \\'Motif de la perte\\'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `op_films_utilises` (
  `id` int(10) UNSIGNED NOT NULL,
  `point_id` int(10) UNSIGNED NOT NULL,
  `bobine_id` int(10) UNSIGNED NOT NULL,
  `type_vehicule_id` int(10) UNSIGNED NOT NULL,
  `films_utilises` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `films_endommages` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `op_points_journaliers` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `date_point` date NOT NULL,
  `type_point` enum(\\'point_8h\\',\\'point_9h\\',\\'point_13h\\',\\'point_17h\\',\\'debut_activite\\',\\'mi_journee\\',\\'fin_journee\\',\\'final\\',\\'intermediaire\\') NOT NULL DEFAULT \\'point_17h\\',
  `statut` enum(\\'suivi\\',\\'brouillon\\',\\'valide\\',\\'refuse\\') NOT NULL DEFAULT \\'brouillon\\',
  `nb_vp` int(10) UNSIGNED DEFAULT 0,
  `nb_camion` int(10) UNSIGNED DEFAULT 0,
  `nb_semi` int(10) UNSIGNED DEFAULT 0,
  `nb_moto` int(10) UNSIGNED DEFAULT 0,
  `total_engins` int(10) UNSIGNED DEFAULT 0,
  `total_plaques` int(10) UNSIGNED DEFAULT 0,
  `moyenne_prod` decimal(6,2) DEFAULT 0.00,
  `rivets_utilises` int(10) UNSIGNED DEFAULT 0,
  `rivets_endommages` int(10) UNSIGNED DEFAULT 0,
  `non_poses_concessionnaires` int(10) UNSIGNED DEFAULT 0,
  `non_poses_usagers` int(10) UNSIGNED DEFAULT 0,
  `nb_heures_travail` decimal(4,1) DEFAULT 8.0,
  `observations` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `validated_by` int(10) UNSIGNED DEFAULT NULL,
  `validated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `op_stock_rivets` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `quantite` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `op_types_vehicule` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `libelle` varchar(100) NOT NULL,
  `nb_plaques` tinyint(3) UNSIGNED NOT NULL DEFAULT 2,
  `nb_rivets` tinyint(3) UNSIGNED NOT NULL DEFAULT 4,
  `serie_bobine` char(1) NOT NULL COMMENT \\'A=VP, B=Camion, C=Semi, D=Moto\\',
  `ordre` tinyint(3) UNSIGNED DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `module` varchar(60) NOT NULL,
  `can_create` tinyint(1) DEFAULT 0,
  `can_read` tinyint(1) DEFAULT 1,
  `can_update` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `can_export` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `points_emuci` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `date_point` date NOT NULL,
  `plaques_posees` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT \\'Nb plaques posées = films consommés (pose)\\',
  `plaques_reservees` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT \\'Nb plaques réservées = films consommés (réservation)\\',
  `total_films_deduits` int(10) UNSIGNED GENERATED ALWAYS AS (`plaques_posees` + `plaques_reservees`) STORED COMMENT \\'Total films déduits du stock bobine\\',
  `notes` text DEFAULT NULL,
  `statut` enum(\\'brouillon\\',\\'soumis\\',\\'valide\\') NOT NULL DEFAULT \\'brouillon\\',
  `saisi_par` int(10) UNSIGNED NOT NULL,
  `valide_par` int(10) UNSIGNED DEFAULT NULL,
  `valide_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\\'Points journaliers EMUCI saisis par le Contrôleur Production\\';

CREATE TABLE `points_journaliers_info` (
  `id` int(10) UNSIGNED NOT NULL,
  `technicien_id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `date_point` date NOT NULL,
  `nb_equip_ok` int(10) UNSIGNED DEFAULT 0,
  `nb_equip_hs` int(10) UNSIGNED DEFAULT 0,
  `nb_interventions` int(10) UNSIGNED DEFAULT 0,
  `observations` text DEFAULT NULL,
  `actions_preventives` text DEFAULT NULL,
  `statut` enum(\\'brouillon\\',\\'valide\\') NOT NULL DEFAULT \\'brouillon\\',
  `valide_par` int(10) UNSIGNED DEFAULT NULL,
  `valide_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rapports_journaliers_info` (
  `id` int(10) UNSIGNED NOT NULL,
  `technicien_id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `date_rapport` date NOT NULL,
  `nb_equip_ok` int(10) UNSIGNED DEFAULT 0,
  `nb_equip_hs` int(10) UNSIGNED DEFAULT 0,
  `nb_equip_maintenance` int(10) UNSIGNED DEFAULT 0,
  `nb_interventions` int(10) UNSIGNED DEFAULT 0,
  `observations` text DEFAULT NULL,
  `actions_preventives` text DEFAULT NULL,
  `statut` enum(\\'brouillon\\',\\'valide\\') NOT NULL DEFAULT \\'brouillon\\',
  `valide_par` int(10) UNSIGNED DEFAULT NULL,
  `valide_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `receptions_consommables` (
  `id` int(10) UNSIGNED NOT NULL,
  `consommable_id` int(10) UNSIGNED NOT NULL,
  `quantite` decimal(10,2) NOT NULL,
  `prix_unitaire` decimal(12,2) DEFAULT 0.00,
  `prix_total` decimal(12,2) DEFAULT 0.00,
  `date_reception` date NOT NULL,
  `fournisseur` varchar(150) DEFAULT NULL,
  `numero_bon` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `receptions_site` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `type_reception` enum(\\'equipement\\',\\'consommable\\') NOT NULL,
  `equipement_id` int(10) UNSIGNED DEFAULT NULL,
  `consommable_id` int(10) UNSIGNED DEFAULT NULL,
  `quantite` decimal(10,2) DEFAULT NULL,
  `livraison_ref_id` int(10) UNSIGNED DEFAULT NULL COMMENT \\'ID de la livraison d origine (livraisons_consommables)\\',
  `mouvement_ref_id` int(10) UNSIGNED DEFAULT NULL COMMENT \\'ID du mouvement d origine (mouvements_equipements)\\',
  `date_reception` date NOT NULL,
  `fichier_fiche` varchar(255) DEFAULT NULL COMMENT \\'Fiche signée obligatoire (PDF/image)\\',
  `notes` text DEFAULT NULL,
  `statut` enum(\\'en_attente\\',\\'receptionnee\\',\\'litige\\') NOT NULL DEFAULT \\'en_attente\\',
  `litige_motif` text DEFAULT NULL COMMENT \\'Motif détaillé du litige signalé par le coordinateur\\',
  `litige_traite_by` int(10) UNSIGNED DEFAULT NULL COMMENT \\'ID gestionnaire qui a traité le litige\\',
  `litige_traite_at` datetime DEFAULT NULL,
  `remplacement_id` int(10) UNSIGNED DEFAULT NULL COMMENT \\'ID de la nouvelle livraison/mouvement de remplacement\\',
  `remplacement_notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `last_activity` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sites` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `nom` varchar(150) NOT NULL,
  `type` enum(\\'saisie\\',\\'pose\\',\\'mixte\\',\\'caissier\\',\\'entrepot\\',\\'siege\\') NOT NULL DEFAULT \\'saisie\\',
  `option_caisse` tinyint(1) DEFAULT 0,
  `adresse` text DEFAULT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `pays` varchar(100) DEFAULT \\'Côte d\\'\\'Ivoire\\',
  `responsable_id` int(10) UNSIGNED DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mobile` tinyint(1) DEFAULT 0 COMMENT \\'1 = site mobile (équipe temporaire)\\',
  `date_debut_mission` date DEFAULT NULL,
  `date_fin_mission` date DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stock_consommables_site` (
  `id` int(10) UNSIGNED NOT NULL,
  `consommable_id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `quantite` decimal(10,2) DEFAULT 0.00,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stock_pmma` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `type_pmma` varchar(50) DEFAULT \\'Standard\\',
  `quantite` int(10) UNSIGNED NOT NULL,
  `type_mouvement` enum(\\'entree\\',\\'sortie\\') NOT NULL,
  `bobine_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\\'Mouvements PMMA\\';

CREATE TABLE `stock_pmma_site` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `type_pmma` varchar(50) DEFAULT \\'Standard\\',
  `quantite` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `seuil_alerte` int(10) UNSIGNED DEFAULT 10,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\\'Stock PMMA par site\\';

CREATE TABLE `support_it_roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT \\'Compte Support IT\\',
  `sous_role` enum(\\'maintenance\\',\\'controleur_production\\',\\'gestionnaire_bobines\\') NOT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `affecte_par` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\\'Sous-rôles affectés aux comptes Support IT\\';

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `support_it_sous_roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT \\'Cache sous-rôles Support IT actifs\\' CHECK (json_valid(`support_it_sous_roles`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `validations_stock_matin` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `date_validation` date NOT NULL,
  `statut` enum(\\'valide_auto\\',\\'valide_gsb\\',\\'autorise_ecart\\',\\'reajuste\\',\\'refuse\\') NOT NULL,
  `nb_ecarts` int(11) DEFAULT 0,
  `details_ecarts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT \\'Détail des écarts par bobine\\' CHECK (json_valid(`details_ecarts`)),
  `gsb_user_id` int(10) UNSIGNED DEFAULT NULL,
  `gsb_at` datetime DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=\\'Validations stock bobines matin par site\\';

CREATE TABLE `v_cout_hebdo_site` (
`site_id` int(10) unsigned
,`site_nom` varchar(150)
,`site_type` enum(\\'saisie\\',\\'pose\\',\\'mixte\\',\\'caissier\\',\\'entrepot\\',\\'siege\\')
,`annee` int(4)
,`semaine` int(2)
,`debut_semaine` date
,`cout_total` decimal(34,2)
,`qte_total` decimal(32,2)
,`nb_articles` bigint(21)
);

CREATE TABLE `v_cout_mensuel_site` (
`site_id` int(10) unsigned
,`site_nom` varchar(150)
,`site_type` enum(\\'saisie\\',\\'pose\\',\\'mixte\\',\\'caissier\\',\\'entrepot\\',\\'siege\\')
,`annee` int(4)
,`mois` int(2)
,`mois_label` varchar(7)
,`cout_total` decimal(34,2)
,`qte_total` decimal(32,2)
,`nb_articles` bigint(21)
);

ALTER TABLE `affectations_equipements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipement_id` (`equipement_id`),
  ADD KEY `site_dest_id` (`site_dest_id`),
  ADD KEY `user_dest_id` (`user_dest_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `valide_n1_by` (`valide_n1_by`);

ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_module` (`module`),
  ADD KEY `idx_date` (`created_at`);

ALTER TABLE `bilans_mensuels_bobines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_site_mois` (`site_id`,`mois`),
  ADD KEY `inventaire_id` (`inventaire_id`),
  ADD KEY `valide_par` (`valide_par`);

ALTER TABLE `commandes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_commande` (`numero_commande`),
  ADD KEY `idx_site_statut` (`site_id`,`statut`);

ALTER TABLE `commande_lignes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `commande_id` (`commande_id`);

ALTER TABLE `comparaisons_stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_site_date` (`site_id`,`date_comparaison`),
  ADD KEY `ajuste_par` (`ajuste_par`);

ALTER TABLE `configurations_site`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_type_nom` (`type_site`,`nomenclature_id`);

ALTER TABLE `config_postes_composants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_poste_nom` (`poste_id`,`nomenclature_id`),
  ADD KEY `nomenclature_id` (`nomenclature_id`);

ALTER TABLE `config_postes_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

ALTER TABLE `consommables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

ALTER TABLE `consommations_bobines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_date` (`date_conso`),
  ADD KEY `idx_bobine` (`bobine_id`),
  ADD KEY `idx_site` (`site_id`);

ALTER TABLE `delegations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sup_gest_module` (`superviseur_id`,`gestionnaire_id`,`module`),
  ADD KEY `idx_gestionnaire` (`gestionnaire_id`);

ALTER TABLE `demandes_bobines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bobine_id` (`bobine_id`),
  ADD KEY `demande_par` (`demande_par`),
  ADD KEY `traite_par` (`traite_par`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_site` (`site_id`);

ALTER TABLE `ecarts_bobines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventaire_id` (`inventaire_id`),
  ADD KEY `resolu_par` (`resolu_par`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_bobine` (`bobine_id`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_date` (`date_constat`);

ALTER TABLE `equipements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_serie_interne` (`numero_serie_interne`),
  ADD KEY `nomenclature_id` (`nomenclature_id`),
  ADD KEY `utilisateur_id` (`utilisateur_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_etat` (`etat`),
  ADD KEY `idx_date_fin_cycle` (`date_fin_cycle`),
  ADD KEY `idx_site` (`site_id`);

ALTER TABLE `import_optoplate`
  ADD PRIMARY KEY (`id`),
  ADD KEY `importe_par` (`importe_par`),
  ADD KEY `idx_session` (`import_session_id`),
  ADD KEY `idx_date` (`date_import`),
  ADD KEY `idx_statut` (`statut_plaque`),
  ADD KEY `idx_bobine` (`num_bobine`),
  ADD KEY `idx_site` (`site_id`),
  ADD KEY `idx_immat` (`immatriculation`);

ALTER TABLE `import_optotrace`
  ADD PRIMARY KEY (`id`),
  ADD KEY `importe_par` (`importe_par`),
  ADD KEY `idx_session` (`import_session_id`),
  ADD KEY `idx_date` (`date_import`),
  ADD KEY `idx_plate` (`plate_number`),
  ADD KEY `idx_site` (`site_id`);

ALTER TABLE `import_sessions_emuci`
  ADD PRIMARY KEY (`id`),
  ADD KEY `importe_par` (`importe_par`),
  ADD KEY `idx_date` (`date_import`);

ALTER TABLE `interventions_maintenance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipement_id` (`equipement_id`),
  ADD KEY `idx_date` (`date_intervention`),
  ADD KEY `idx_technicien` (`technicien_id`),
  ADD KEY `idx_site` (`site_id`);

ALTER TABLE `inventaires_bobines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `site_id` (`site_id`),
  ADD KEY `cree_par` (`cree_par`),
  ADD KEY `valide_par` (`valide_par`),
  ADD KEY `idx_date` (`date_inventaire`),
  ADD KEY `idx_statut` (`statut`);

ALTER TABLE `inventaire_details_bobines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inv_bobine` (`inventaire_id`,`bobine_id`),
  ADD KEY `bobine_id` (`bobine_id`);

ALTER TABLE `litige_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_reception` (`reception_id`);

ALTER TABLE `livraisons_consommables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consommable_id` (`consommable_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_date` (`date_livraison`),
  ADD KEY `idx_site` (`site_id`);

ALTER TABLE `mouvements_bobines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_bobine` (`bobine_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_date` (`created_at`);

ALTER TABLE `mouvements_equipements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipement_id` (`equipement_id`),
  ADD KEY `site_source_id` (`site_source_id`),
  ADD KEY `site_dest_id` (`site_dest_id`),
  ADD KEY `created_by` (`created_by`);

ALTER TABLE `nomenclatures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

ALTER TABLE `nomenclature_liens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_parent_enfant` (`parent_id`,`enfant_id`),
  ADD KEY `enfant_id` (`enfant_id`);

ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `op_bobines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero` (`numero`),
  ADD KEY `site_id` (`site_id`),
  ADD KEY `type_vehicule_id` (`type_vehicule_id`),
  ADD KEY `idx_serie` (`serie`),
  ADD KEY `idx_statut` (`statut`);

ALTER TABLE `op_films_utilises`
  ADD PRIMARY KEY (`id`),
  ADD KEY `point_id` (`point_id`),
  ADD KEY `bobine_id` (`bobine_id`),
  ADD KEY `type_vehicule_id` (`type_vehicule_id`);

ALTER TABLE `op_points_journaliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_site_date_type` (`site_id`,`date_point`,`type_point`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `validated_by` (`validated_by`);

ALTER TABLE `op_stock_rivets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_site` (`site_id`);

ALTER TABLE `op_types_vehicule`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_role_module` (`role_id`,`module`);

ALTER TABLE `points_emuci`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_site_date` (`site_id`,`date_point`),
  ADD KEY `saisi_par` (`saisi_par`),
  ADD KEY `valide_par` (`valide_par`),
  ADD KEY `idx_date` (`date_point`),
  ADD KEY `idx_statut` (`statut`);

ALTER TABLE `points_journaliers_info`
  ADD PRIMARY KEY (`id`),
  ADD KEY `valide_par` (`valide_par`),
  ADD KEY `idx_site_date` (`site_id`,`date_point`),
  ADD KEY `idx_technicien` (`technicien_id`);

ALTER TABLE `rapports_journaliers_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tech_site_date` (`technicien_id`,`site_id`,`date_rapport`),
  ADD KEY `valide_par` (`valide_par`),
  ADD KEY `idx_site_date` (`site_id`,`date_rapport`);

ALTER TABLE `receptions_consommables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consommable_id` (`consommable_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_date` (`date_reception`);

ALTER TABLE `receptions_site`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipement_id` (`equipement_id`),
  ADD KEY `consommable_id` (`consommable_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_site` (`site_id`),
  ADD KEY `idx_date` (`date_reception`),
  ADD KEY `idx_statut` (`statut`);

ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nom` (`nom`),
  ADD UNIQUE KEY `slug` (`slug`);

ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_last_activity` (`last_activity`);

ALTER TABLE `sites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `responsable_id` (`responsable_id`);

ALTER TABLE `stock_consommables_site`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_conso_site` (`consommable_id`,`site_id`),
  ADD KEY `site_id` (`site_id`);

ALTER TABLE `stock_pmma`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site_date` (`site_id`,`created_at`);

ALTER TABLE `stock_pmma_site`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_site_type` (`site_id`,`type_pmma`);

ALTER TABLE `support_it_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_sousrole` (`user_id`,`sous_role`),
  ADD KEY `affecte_par` (`affecte_par`),
  ADD KEY `idx_sous_role` (`sous_role`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

ALTER TABLE `validations_stock_matin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_site_date` (`site_id`,`date_validation`),
  ADD KEY `gsb_user_id` (`gsb_user_id`);

ALTER TABLE `affectations_equipements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=376;

ALTER TABLE `bilans_mensuels_bobines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `commandes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `commande_lignes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `comparaisons_stock`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `configurations_site`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

ALTER TABLE `config_postes_composants`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `config_postes_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `consommables`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `consommations_bobines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `delegations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `demandes_bobines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `ecarts_bobines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

ALTER TABLE `equipements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

ALTER TABLE `import_optoplate`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `import_optotrace`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `interventions_maintenance`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

ALTER TABLE `inventaires_bobines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

ALTER TABLE `inventaire_details_bobines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

ALTER TABLE `litige_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `livraisons_consommables`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

ALTER TABLE `mouvements_bobines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

ALTER TABLE `mouvements_equipements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

ALTER TABLE `nomenclatures`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

ALTER TABLE `nomenclature_liens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

ALTER TABLE `op_bobines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `op_films_utilises`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

ALTER TABLE `op_points_journaliers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

ALTER TABLE `op_stock_rivets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

ALTER TABLE `op_types_vehicule`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

ALTER TABLE `permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=579;

ALTER TABLE `points_emuci`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `points_journaliers_info`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `rapports_journaliers_info`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `receptions_consommables`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

ALTER TABLE `receptions_site`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

ALTER TABLE `sites`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

ALTER TABLE `stock_consommables_site`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

ALTER TABLE `stock_pmma`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

ALTER TABLE `stock_pmma_site`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

ALTER TABLE `support_it_roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

ALTER TABLE `validations_stock_matin`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `affectations_equipements`
  ADD CONSTRAINT `affectations_equipements_ibfk_1` FOREIGN KEY (`equipement_id`) REFERENCES `equipements` (`id`),
  ADD CONSTRAINT `affectations_equipements_ibfk_2` FOREIGN KEY (`site_dest_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `affectations_equipements_ibfk_3` FOREIGN KEY (`user_dest_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `affectations_equipements_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `affectations_equipements_ibfk_5` FOREIGN KEY (`valide_n1_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `bilans_mensuels_bobines`
  ADD CONSTRAINT `bilans_mensuels_bobines_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `bilans_mensuels_bobines_ibfk_';
$sqls[] = '2` FOREIGN KEY (`inventaire_id`) REFERENCES `inventaires_bobines` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bilans_mensuels_bobines_ibfk_3` FOREIGN KEY (`valide_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `commandes`
  ADD CONSTRAINT `commandes_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

ALTER TABLE `commande_lignes`
  ADD CONSTRAINT `commande_lignes_ibfk_1` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE;

ALTER TABLE `comparaisons_stock`
  ADD CONSTRAINT `comparaisons_stock_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `comparaisons_stock_ibfk_2` FOREIGN KEY (`ajuste_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `config_postes_composants`
  ADD CONSTRAINT `config_postes_composants_ibfk_1` FOREIGN KEY (`poste_id`) REFERENCES `config_postes_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `config_postes_composants_ibfk_2` FOREIGN KEY (`nomenclature_id`) REFERENCES `nomenclatures` (`id`);

ALTER TABLE `consommations_bobines`
  ADD CONSTRAINT `consommations_bobines_ibfk_1` FOREIGN KEY (`bobine_id`) REFERENCES `op_bobines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `consommations_bobines_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `consommations_bobines_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `delegations`
  ADD CONSTRAINT `delegations_ibfk_1` FOREIGN KEY (`superviseur_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `delegations_ibfk_2` FOREIGN KEY (`gestionnaire_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `demandes_bobines`
  ADD CONSTRAINT `demandes_bobines_ibfk_1` FOREIGN KEY (`bobine_id`) REFERENCES `op_bobines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `demandes_bobines_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `demandes_bobines_ibfk_3` FOREIGN KEY (`demande_par`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `demandes_bobines_ibfk_4` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `ecarts_bobines`
  ADD CONSTRAINT `ecarts_bobines_ibfk_1` FOREIGN KEY (`bobine_id`) REFERENCES `op_bobines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ecarts_bobines_ibfk_2` FOREIGN KEY (`inventaire_id`) REFERENCES `inventaires_bobines` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ecarts_bobines_ibfk_3` FOREIGN KEY (`resolu_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ecarts_bobines_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `equipements`
  ADD CONSTRAINT `equipements_ibfk_1` FOREIGN KEY (`nomenclature_id`) REFERENCES `nomenclatures` (`id`),
  ADD CONSTRAINT `equipements_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `equipements_ibfk_3` FOREIGN KEY (`utilisateur_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `equipements_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `import_optoplate`
  ADD CONSTRAINT `import_optoplate_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `import_optoplate_ibfk_2` FOREIGN KEY (`importe_par`) REFERENCES `users` (`id`);

ALTER TABLE `import_optotrace`
  ADD CONSTRAINT `import_optotrace_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `import_optotrace_ibfk_2` FOREIGN KEY (`importe_par`) REFERENCES `users` (`id`);

ALTER TABLE `import_sessions_emuci`
  ADD CONSTRAINT `import_sessions_emuci_ibfk_1` FOREIGN KEY (`importe_par`) REFERENCES `users` (`id`);

ALTER TABLE `interventions_maintenance`
  ADD CONSTRAINT `interventions_maintenance_ibfk_1` FOREIGN KEY (`technicien_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `interventions_maintenance_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `interventions_maintenance_ibfk_3` FOREIGN KEY (`equipement_id`) REFERENCES `equipements` (`id`) ON DELETE SET NULL;

ALTER TABLE `inventaires_bobines`
  ADD CONSTRAINT `inventaires_bobines_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventaires_bobines_ibfk_2` FOREIGN KEY (`cree_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventaires_bobines_ibfk_3` FOREIGN KEY (`valide_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `inventaire_details_bobines`
  ADD CONSTRAINT `inventaire_details_bobines_ibfk_1` FOREIGN KEY (`inventaire_id`) REFERENCES `inventaires_bobines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventaire_details_bobines_ibfk_2` FOREIGN KEY (`bobine_id`) REFERENCES `op_bobines` (`id`) ON DELETE CASCADE;

ALTER TABLE `litige_messages`
  ADD CONSTRAINT `litige_messages_ibfk_1` FOREIGN KEY (`reception_id`) REFERENCES `receptions_site` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `litige_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `livraisons_consommables`
  ADD CONSTRAINT `livraisons_consommables_ibfk_1` FOREIGN KEY (`consommable_id`) REFERENCES `consommables` (`id`),
  ADD CONSTRAINT `livraisons_consommables_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `livraisons_consommables_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `mouvements_bobines`
  ADD CONSTRAINT `mouvements_bobines_ibfk_1` FOREIGN KEY (`bobine_id`) REFERENCES `op_bobines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mouvements_bobines_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `mouvements_equipements`
  ADD CONSTRAINT `mouvements_equipements_ibfk_1` FOREIGN KEY (`equipement_id`) REFERENCES `equipements` (`id`),
  ADD CONSTRAINT `mouvements_equipements_ibfk_2` FOREIGN KEY (`site_source_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `mouvements_equipements_ibfk_3` FOREIGN KEY (`site_dest_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `mouvements_equipements_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `nomenclature_liens`
  ADD CONSTRAINT `nomenclature_liens_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `nomenclatures` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `nomenclature_liens_ibfk_2` FOREIGN KEY (`enfant_id`) REFERENCES `nomenclatures` (`id`) ON DELETE CASCADE;

ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `op_bobines`
  ADD CONSTRAINT `op_bobines_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `op_bobines_ibfk_2` FOREIGN KEY (`type_vehicule_id`) REFERENCES `op_types_vehicule` (`id`) ON DELETE SET NULL;

ALTER TABLE `op_films_utilises`
  ADD CONSTRAINT `op_films_utilises_ibfk_1` FOREIGN KEY (`point_id`) REFERENCES `op_points_journaliers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `op_films_utilises_ibfk_2` FOREIGN KEY (`bobine_id`) REFERENCES `op_bobines` (`id`),
  ADD CONSTRAINT `op_films_utilises_ibfk_3` FOREIGN KEY (`type_vehicule_id`) REFERENCES `op_types_vehicule` (`id`);

ALTER TABLE `op_points_journaliers`
  ADD CONSTRAINT `op_points_journaliers_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `op_points_journaliers_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `op_points_journaliers_ibfk_3` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `op_stock_rivets`
  ADD CONSTRAINT `op_stock_rivets_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

ALTER TABLE `permissions`
  ADD CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

ALTER TABLE `points_emuci`
  ADD CONSTRAINT `points_emuci_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `points_emuci_ibfk_2` FOREIGN KEY (`saisi_par`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `points_emuci_ibfk_3` FOREIGN KEY (`valide_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `points_journaliers_info`
  ADD CONSTRAINT `points_journaliers_info_ibfk_1` FOREIGN KEY (`technicien_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `points_journaliers_info_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `points_journaliers_info_ibfk_3` FOREIGN KEY (`valide_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `rapports_journaliers_info`
  ADD CONSTRAINT `rapports_journaliers_info_ibfk_1` FOREIGN KEY (`technicien_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rapports_journaliers_info_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `rapports_journaliers_info_ibfk_3` FOREIGN KEY (`valide_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `receptions_consommables`
  ADD CONSTRAINT `receptions_consommables_ibfk_1` FOREIGN KEY (`consommable_id`) REFERENCES `consommables` (`id`),
  ADD CONSTRAINT `receptions_consommables_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `receptions_site`
  ADD CONSTRAINT `receptions_site_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `receptions_site_ibfk_2` FOREIGN KEY (`equipement_id`) REFERENCES `equipements` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `receptions_site_ibfk_3` FOREIGN KEY (`consommable_id`) REFERENCES `consommables` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `receptions_site_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `sites`
  ADD CONSTRAINT `sites_ibfk_1` FOREIGN KEY (`responsable_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `stock_consommables_site`
  ADD CONSTRAINT `stock_consommables_site_ibfk_1` FOREIGN KEY (`consommable_id`) REFERENCES `consommables` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_consommables_site_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

ALTER TABLE `stock_pmma`
  ADD CONSTRAINT `stock_pmma_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

ALTER TABLE `stock_pmma_site`
  ADD CONSTRAINT `stock_pmma_site_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

ALTER TABLE `support_it_roles`
  ADD CONSTRAINT `support_it_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_it_roles_ibfk_2` FOREIGN KEY (`affecte_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

ALTER TABLE `validations_stock_matin`
  ADD CONSTRAINT `validations_stock_matin_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `validations_stock_matin_ibfk_2` FOREIGN KEY (`gsb_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

INSERT INTO `roles` (`id`, `nom`, `slug`, `description`, `created_at`) VALUES
(1, \\'Super Administrateur\\', \\'superadmin\\', \\'Accès total, lecture de tous les audits\\', \\'2026-04-01 11:43:26\\'),
(2, \\'Administrateur\\', \\'admin\\', \\'Gestion utilisateurs, équipements, sites\\', \\'2026-04-01 11:43:26\\'),
(3, \\'Gestionnaire\\', \\'gestionnaire\\', \\'Saisie des mouvements et livraisons\\', \\'2026-04-01 11:43:26\\'),
(4, \\'Lecteur\\', \\'lecteur\\', \\'Consultation uniquement\\', \\'2026-04-01 11:43:26\\'),
(5, \\'Gestionnaire de Stock\\', \\'gestionnaire_stock\\', \\'Gère tout le stock central : entrées, sorties, livraisons sites\\', \\'2026-04-19 07:49:10\\'),
(6, \\'Coordinateur de Site\\', \\'coordinateur_site\\', \\'Réceptionne les commandes sur son site, fait le point journalier\\', \\'2026-04-19 07:49:10\\'),
(7, \\'Maintenance Informatique\\', \\'maintenance_info\\', \\'Gère le stock équipements informatiques uniquement\\', \\'2026-04-19 07:49:10\\'),
(8, \\'Superviseur Opération\\', \\'superviseur_operation\\', \\'Supervise les actions des coordinateurs de site\\', \\'2026-04-19 07:49:10\\'),
(9, \\'Contrôleur Production\\', \\'controleur_production\\', \\'Saisie quotidienne des plaques posées et réservées par site (données EMUCI)\\', \\'2026-04-24 18:08:51\\'),
(13, \\'Gestionnaire Stock Bobines\\', \\'gestionnaire_stock_bobines\\', \\'Validation stock matin, gestion demandes bobines, réajustements\\', \\'2026-04-29 15:39:36\\'),
(14, \\'Gestionnaire Opération\\', \\'gestionnaire_operation\\', \\'Second du Superviseur Opération — reçoit les tâches déléguées\\', \\'2026-04-30 14:10:42\\'),
(15, \\'Superviseur IT\\', \\'superviseur_it\\', \\'Accès complet informatique + supervise les Support IT\\', \\'2026-04-30 14:10:42\\'),
(16, \\'Support IT\\', \\'support_it\\', \\'Profil flexible — sous-rôles affectables : Maintenance, Contrôleur Production, Gestionnaire Bobines\\', \\'2026-04-30 14:10:42\\'),
(17, \\'Superviseur Achat\\', \\'superviseur_achat\\', \\'Suivi consommables, équipements, consommation des sites\\', \\'2026-04-30 14:10:42\\');

INSERT INTO `permissions` (`id`, `role_id`, `module`, `can_create`, `can_read`, `can_update`, `can_delete`, `can_export`) VALUES
(462, 2, \\'equipements\\', 1, 1, 1, 1, 1),
(463, 1, \\'equipements\\', 1, 1, 1, 1, 1),
(464, 2, \\'sites\\', 1, 1, 1, 1, 1),
(465, 1, \\'sites\\', 1, 1, 1, 1, 1),
(466, 2, \\'affectations\\', 1, 1, 1, 1, 1),
(467, 1, \\'affectations\\', 1, 1, 1, 1, 1),
(468, 2, \\'receptions\\', 1, 1, 1, 1, 1),
(469, 1, \\'receptions\\', 1, 1, 1, 1, 1),
(470, 2, \\'bobines\\', 1, 1, 1, 1, 1),
(471, 1, \\'bobines\\', 1, 1, 1, 1, 1),
(472, 2, \\'inventaire_bobines\\', 1, 1, 1, 1, 1),
(473, 1, \\'inventaire_bobines\\', 1, 1, 1, 1, 1),
(474, 2, \\'consommables\\', 1, 1, 1, 1, 1),
(475, 1, \\'consommables\\', 1, 1, 1, 1, 1),
(476, 2, \\'rapports\\', 1, 1, 1, 1, 1),
(477, 1, \\'rapports\\', 1, 1, 1, 1, 1),
(478, 2, \\'interventions\\', 1, 1, 1, 1, 1),
(479, 1, \\'interventions\\', 1, 1, 1, 1, 1),
(480, 2, \\'point_emuci\\', 1, 1, 1, 1, 1),
(481, 1, \\'point_emuci\\', 1, 1, 1, 1, 1),
(482, 2, \\'import_emuci\\', 1, 1, 1, 1, 1),
(483, 1, \\'import_emuci\\', 1, 1, 1, 1, 1),
(484, 2, \\'nomenclatures\\', 1, 1, 1, 1, 1),
(485, 1, \\'nomenclatures\\', 1, 1, 1, 1, 1),
(486, 2, \\'users\\', 1, 1, 1, 1, 1),
(487, 1, \\'users\\', 1, 1, 1, 1, 1),
(488, 2, \\'audit\\', 1, 1, 1, 1, 1),
(489, 1, \\'audit\\', 1, 1, 1, 1, 1),
(490, 2, \\'delegations\\', 1, 1, 1, 1, 1),
(491, 1, \\'delegations\\', 1, 1, 1, 1, 1),
(492, 2, \\'rivets\\', 1, 1, 1, 1, 1),
(493, 1, \\'rivets\\', 1, 1, 1, 1, 1),
(525, 8, \\'equipements\\', 0, 1, 0, 0, 1),
(526, 8, \\'sites\\', 0, 1, 0, 0, 1),
(527, 8, \\'affectations\\', 0, 1, 0, 0, 1),
(528, 8, \\'receptions\\', 1, 1, 1, 0, 1),
(529, 8, \\'bobines\\', 1, 1, 1, 0, 1),
(530, 8, \\'inventaire_bobines\\', 1, 1, 1, 0, 1),
(531, 8, \\'consommables\\', 0, 1, 0, 0, 1),
(532, 8, \\'rapports\\', 0, 1, 0, 0, 1),
(533, 8, \\'interventions\\', 0, 1, 0, 0, 0),
(534, 8, \\'point_emuci\\', 1, 1, 1, 0, 1),
(535, 8, \\'import_emuci\\', 0, 1, 0, 0, 1),
(536, 8, \\'audit\\', 0, 1, 0, 0, 0),
(537, 8, \\'delegations\\', 1, 1, 1, 1, 0),
(538, 8, \\'rivets\\', 1, 1, 1, 0, 1),
(540, 6, \\'equipements\\', 0, 1, 0, 0, 0),
(541, 6, \\'bobines\\', 1, 1, 1, 0, 1),
(542, 6, \\'inventaire_bobines\\', 1, 1, 1, 0, 0),
(543, 6, \\'receptions\\', 1, 1, 1, 0, 1),
(544, 6, \\'consommables\\', 0, 1, 0, 0, 0),
(545, 6, \\'rapports\\', 0, 1, 0, 0, 1),
(546, 6, \\'rivets\\', 0, 1, 0, 0, 0),
(547, 5, \\'equipements\\', 1, 1, 1, 0, 1),
(548, 5, \\'consommables\\', 1, 1, 1, 0, 1),
(549, 5, \\'receptions\\', 1, 1, 1, 0, 1),
(550, 5, \\'rapports\\', 0, 1, 0, 0, 1),
(551, 5, \\'sites\\', 0, 1, 0, 0, 0),
(552, 2, \\'pmma\\', 1, 1, 1, 0, 1),
(553, 2, \\'commandes\\', 1, 1, 1, 0, 1),
(554, 1, \\'pmma\\', 1, 1, 1, 0, 1),
(555, 1, \\'commandes\\', 1, 1, 1, 0, 1),
(556, 15, \\'pmma\\', 1, 1, 1, 0, 1),
(557, 15, \\'commandes\\', 1, 1, 1, 0, 1),
(558, 8, \\'pmma\\', 1, 1, 1, 0, 1),
(559, 8, \\'commandes\\', 1, 1, 1, 0, 1),
(567, 6, \\'pmma\\', 1, 1, 1, 0, 0),
(568, 6, \\'commandes\\', 1, 1, 0, 0, 0),
(570, 5, \\'commandes\\', 0, 1, 1, 0, 1),
(571, 5, \\'pmma\\', 1, 1, 1, 0, 1),
(572, 17, \\'commandes\\', 0, 1, 1, 0, 1),
(573, 17, \\'pmma\\', 1, 1, 1, 0, 1),
(577, 15, \\'interventions\\', 1, 1, 1, 0, 1),
(578, 16, \\'interventions\\', 1, 1, 1, 0, 1);

INSERT INTO `sites` (`id`, `code`, `nom`, `type`, `option_caisse`, `adresse`, `ville`, `pays`, `responsable_id`, `actif`, `created_at`, `updated_at`, `mobile`, `date_debut_mission`, `date_fin_mission`, `latitude`, `longitude`) VALUES
(1, \\'ABJ-01\\', \\'GU ABIDJAN\\', \\'pose\\', 1, \\'Guichet unique abidjan vridi\\', \\'ABIDJAN\\', \\'Côte d\\\\'Ivoire\\', NULL, 1, \\'2026-04-02 06:06:19\\', \\'2026-04-03 05:04:00\\', 0, NULL, NULL, NULL, NULL),
(2, \\'ABJ-02\\', \\'STAR AUTO\\', \\'mixte\\', 0, \\'\\', \\'ABIDJAN\\', \\'Côte d\\\\'Ivoire\\', NULL, 1, \\'2026-04-02 06:06:49\\', \\'2026-04-02 06:06:49\\', 0, NULL, NULL, NULL, NULL),
(3, \\'ABJ-03\\', \\'CFAO\\', \\'pose\\', 0, \\'\\', \\'ABIDJAN\\', \\'Côte d\\\\'Ivoire\\', NULL, 1, \\'2026-04-02 06:07:37\\', \\'2026-04-02 06:07:37\\', 0, NULL, NULL, NULL, NULL),
(4, \\'KGO-01\\', \\'GU KORHOOGO\\', \\'pose\\', 0, \\'\\', \\'KORHOGOO\\', \\'Côte d\\\\'Ivoire\\', NULL, 1, \\'2026-04-02 06:08:13\\', \\'2026-04-02 06:08:13\\', 0, NULL, NULL, NULL, NULL),
(5, \\'BKE-01\\', \\'GU BOUAKE\\', \\'pose\\', 0, \\'\\', \\'BOUAKE\\', \\'Côte d\\\\'Ivoire\\', NULL, 1, \\'2026-04-02 06:08:34\\', \\'2026-04-02 06:08:34\\', 0, NULL, NULL, NULL, NULL),
(6, \\'ABJ-04\\', \\'SITE DE TEST\\', \\'saisie\\', 0, \\'\\', \\'ABIDJAN\\', \\'Côte d\\\\'Ivoire\\', NULL, 1, \\'2026-04-03 04:18:17\\', \\'2026-04-03 04:18:17\\', 0, NULL, NULL, NULL, NULL),
(7, \\'ABJ-05\\', \\'TEST AKOUEDO\\', \\'pose\\', 1, \\'\\', \\'ABIDJAN\\', \\'Côte d\\\\'Ivoire\\', NULL, 1, \\'2026-04-03 04:22:59\\', \\'2026-04-29 15:45:44\\', 0, NULL, NULL, NULL, NULL),
(8, \\'ENT\\', \\'ENTREPOT EMUCI\\', \\'entrepot\\', 0, \\'\\', \\'ABIDJAN\\', \\'Côte d\\\\'Ivoire\\', NULL, 1, \\'2026-04-03 04:45:47\\', \\'2026-04-03 04:45:47\\', 0, NULL, NULL, NULL, NULL),
(9, \\'ADM\\', \\'ADMINISTRATION (Siége)\\', \\'siege\\', 0, \\'\\', \\'ABIDJAN Zone4\\', \\'Côte d\\\\'Ivoire\\', NULL, 1, \\'2026-04-03 04:46:43\\', \\'2026-04-03 04:46:43\\', 0, NULL, NULL, NULL, NULL),
(10, \\'ABJ-06\\', \\'FDS AKOUEDO\\', \\'pose\\', 1, \\'ABIDJAN\\', \\'ABIDJAN\\', \\'Côte d\\\\'Ivoire\\', NULL, 1, \\'2026-04-29 15:45:19\\', \\'2026-04-29 15:45:19\\', 0, NULL, NULL, NULL, NULL);

INSERT INTO `nomenclatures` (`id`, `code`, `categorie`, `libelle`, `description`, `duree_vie_mois`, `seuil_alerte`, `created_at`) VALUES
(1, \\'CLV\\', \\'informatique\\', \\'Clavier\\', \\'Les claviers des desktop\\', 24, 2, \\'2026-04-02 06:11:05\\'),
(2, \\'IMP\\', \\'informatique\\', \\'Imprimantes\\', \\'\\', 24, 5, \\'2026-04-02 06:11:53\\'),
(3, \\'SR\\', \\'informatique\\', \\'Souris\\', \\'\\', 24, 5, \\'2026-04-02 09:16:25\\'),
(4, \\'UNT\\', \\'informatique\\', \\'Unité centrale\\', \\'\\', 24, 5, \\'2026-04-02 09:17:00\\'),
(5, \\'CLD\\', \\'informatique\\', \\'Client lourd\\', \\'\\', 24, 5, \\'2026-04-02 09:17:31\\'),
(6, \\'ECR\\', \\'informatique\\', \\'Ecran\\', \\'\\', 24, 5, \\'2026-04-02 09:18:01\\'),
(7, \\'OND\\', \\'informatique\\', \\'Onduleurs\\', \\'\\', 12, 5, \\'2026-04-02 09:18:21\\'),
(8, \\'LPT\\', \\'informatique\\', \\'Laptop\\', \\'\\', 36, 5, \\'2026-04-02 09:19:11\\'),
(9, \\'PRS\\', \\'informatique\\', \\'Perceuse\\', \\'\\', 36, 5, \\'2026-04-02 09:19:32\\');

INSERT INTO `op_types_vehicule` (`id`, `code`, `libelle`, `nb_plaques`, `nb_rivets`, `serie_bobine`, `ordre`) VALUES
(1, \\'VP\\', \\'Véhicule Particulier\\', 2, 4, \\'A\\', 1),
(2, \\'CAM\\', \\'Camion\\', 2, 4, \\'B\\', 2),
(3, \\'SEMI\\', \\'Semi-remorque\\', 1, 2, \\'C\\', 3),
(4, \\'MOTO\\', \\'Moto\\', 1, 2, \\'D\\', 4);

INSERT INTO `stock_pmma_site` (`id`, `site_id`, `type_pmma`, `quantite`, `seuil_alerte`, `updated_at`) VALUES
(1, 1, \\'Type A\\', 450, 10, \\'2026-05-28 10:02:46\\'),
(2, 3, \\'Type A\\', 500, 10, \\'2026-05-28 10:00:26\\'),
(3, 5, \\'TYPE B\\', 500, 10, \\'2026-05-28 10:00:42\\'),
(4, 1, \\'Type C\\', 500, 10, \\'2026-05-28 10:00:54\\'),
(5, 1, \\'Type D\\', 500, 10, \\'2026-05-28 10:01:04\\'),
(6, 3, \\'TYPE B\\', 500, 10, \\'2026-05-28 10:01:12\\'),
(7, 8, \\'Type D\\', 500, 10, \\'2026-05-28 10:01:57\\'),
(8, 8, \\'TYPE B\\', 500, 10, \\'2026-05-28 10:02:11\\');

INSERT INTO `op_stock_rivets` (`id`, `site_id`, `quantite`, `updated_at`) VALUES
(1, 1, 171, \\'2026-04-28 09:58:11\\'),
(2, 3, 1020, \\'2026-04-29 22:32:34\\'),
(6, 5, 500, \\'2026-04-15 09:59:34\\'),
(7, 4, 500, \\'2026-04-15 09:59:41\\'),
(8, 2, 944, \\'2026-04-15 10:00:50\\');

SET FOREIGN_KEY_CHECKS=1;
';

$full_sql = implode('', $sqls);
$db->exec("SET FOREIGN_KEY_CHECKS=0");

// Split sur les ; suivis de newline
$commands = preg_split('/;\s*\n/', $full_sql);
$success = 0; $errors = [];

foreach ($commands as $cmd) {
    $cmd = trim($cmd);
    if (empty($cmd) || $cmd[0] === '-' || $cmd[0] === '/') continue;
    try {
        $db->exec($cmd . ";");
        $success++;
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'already exists') === false && strpos($msg, "Duplicate") === false) {
            $errors[] = substr($msg, 0, 200) . ' | SQL: ' . substr($cmd, 0, 80);
        }
    }
}

$db->exec("SET FOREIGN_KEY_CHECKS=1");
echo "<h2>✅ Import terminé: $success commandes</h2>";
if ($errors) {
    echo "<p>⚠️ ".count($errors)." erreurs:</p><ul>";
    foreach(array_slice($errors,0,15) as $e) echo "<li>".htmlspecialchars($e)."</li>";
    echo "</ul>";
}
echo "<p><b>SUPPRIMEZ import.php!</b></p>";
?>