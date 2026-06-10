-- ============================================================
--  migration_stockapp_complet.sql
--  À importer dans phpMyAdmin après database.sql
--  Compatible MySQL 8.0 (MAMP Mac)
-- ============================================================

USE `stockapp`;
SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ============================================================
-- PARTIE 1 — MODIFICATIONS DES TABLES EXISTANTES
-- ============================================================

-- 1.1 nomenclatures : ajouter catégorie + durée amortissement
ALTER TABLE `nomenclatures`
  ADD COLUMN `categorie` enum('informatique','operationnel') NOT NULL DEFAULT 'informatique' AFTER `code`,
  ADD COLUMN `duree_amortissement_mois` int UNSIGNED DEFAULT NULL AFTER `seuil_alerte`;

-- 1.2 equipements : ajouter catégorie, amortissement, prix, statut stock
ALTER TABLE `equipements`
  ADD COLUMN `categorie` enum('informatique','operationnel') NOT NULL DEFAULT 'informatique' AFTER `nomenclature_id`,
  ADD COLUMN `duree_amortissement_mois` int UNSIGNED DEFAULT NULL COMMENT 'Durée amortissement OHADA en mois' AFTER `updated_at`,
  ADD COLUMN `prix_achat` decimal(15,2) DEFAULT 0.00 COMMENT 'Prix achat en FCFA' AFTER `duree_amortissement_mois`,
  ADD COLUMN `statut_stock` enum('affecte','en_stock') NOT NULL DEFAULT 'affecte' COMMENT 'affecte=en service, en_stock=disponible' AFTER `prix_achat`;

-- 1.3 sites : ajouter option_caisse + champs mobiles + géoloc
ALTER TABLE `sites`
  ADD COLUMN `option_caisse` tinyint(1) DEFAULT 0 AFTER `type`,
  ADD COLUMN `mobile` tinyint(1) DEFAULT 0 COMMENT '1 = site mobile (équipe temporaire)' AFTER `updated_at`,
  ADD COLUMN `date_debut_mission` date DEFAULT NULL AFTER `mobile`,
  ADD COLUMN `date_fin_mission` date DEFAULT NULL AFTER `date_debut_mission`,
  ADD COLUMN `latitude` decimal(10,7) DEFAULT NULL AFTER `date_fin_mission`,
  ADD COLUMN `longitude` decimal(10,7) DEFAULT NULL AFTER `latitude`;

-- 1.4 consommables : ajouter prix unitaire + catégorie
ALTER TABLE `consommables`
  ADD COLUMN `prix_unitaire` decimal(12,2) DEFAULT 0.00 AFTER `seuil_alerte`,
  ADD COLUMN `categorie` varchar(100) DEFAULT NULL COMMENT 'ex: Papeterie, Informatique...' AFTER `updated_at`;

-- 1.5 livraisons_consommables : ajouter type, prix et fichier BL
ALTER TABLE `livraisons_consommables`
  ADD COLUMN `type_mouvement` enum('distribution','retour') DEFAULT 'distribution' AFTER `site_id`,
  ADD COLUMN `prix_unitaire` decimal(12,2) DEFAULT 0.00 AFTER `quantite`,
  ADD COLUMN `prix_total` decimal(12,2) DEFAULT 0.00 AFTER `prix_unitaire`,
  ADD COLUMN `fichier_bl` varchar(255) DEFAULT NULL COMMENT 'Fichier bon de livraison' AFTER `bon_livraison`;

-- 1.6 mouvements_equipements : ajouter fichier BL
ALTER TABLE `mouvements_equipements`
  ADD COLUMN `fichier_bl` varchar(255) DEFAULT NULL COMMENT 'BL obligatoire pour sorties/transferts' AFTER `notes`;

-- 1.7 users : ajouter cache sous-rôles support IT
ALTER TABLE `users`
  ADD COLUMN `support_it_sous_roles` JSON DEFAULT NULL COMMENT 'Cache sous-rôles Support IT actifs' AFTER `updated_at`;

-- 1.8 notifications : étendre l'enum + ajouter ref_table / ref_id
ALTER TABLE `notifications`
  MODIFY COLUMN `type` enum('fin_cycle','stock_bas','alerte_conso','info','stock_valide','stock_validation','demande_bobine') NOT NULL,
  ADD COLUMN `ref_table` varchar(60) DEFAULT NULL AFTER `lien`,
  ADD COLUMN `ref_id` int UNSIGNED DEFAULT NULL AFTER `ref_table`;

-- ============================================================
-- PARTIE 2 — MISE À JOUR DU TRIGGER equipements
-- (version améliorée avec gestion code null)
-- ============================================================

DROP TRIGGER IF EXISTS `trg_equipement_numero_serie`;

DELIMITER $$
CREATE TRIGGER `trg_equipement_numero_serie`
BEFORE INSERT ON `equipements`
FOR EACH ROW
BEGIN
  DECLARE v_seq    INT UNSIGNED DEFAULT 1;
  DECLARE v_code   VARCHAR(10)  DEFAULT 'EQP';
  DECLARE v_chrono INT UNSIGNED DEFAULT 1;

  SELECT COALESCE(code, 'EQP') INTO v_code
  FROM nomenclatures WHERE id = NEW.nomenclature_id LIMIT 1;

  SELECT COALESCE(MAX(numero_chrono), 0) + 1 INTO v_chrono FROM equipements;
  SET NEW.numero_chrono = v_chrono;

  IF NEW.numero_serie_interne = '' OR NEW.numero_serie_interne = '0' OR NEW.numero_serie_interne IS NULL THEN
    SELECT COALESCE(
      MAX(CAST(SUBSTRING_INDEX(numero_serie_interne, '-', -1) AS UNSIGNED)), 0
    ) + 1 INTO v_seq
    FROM equipements
    WHERE nomenclature_id = NEW.nomenclature_id
      AND numero_serie_interne REGEXP CONCAT('^', v_code, '-[A-Z]+-[0-9]+$');
    SET NEW.numero_serie_interne = CONCAT(v_code, '-EMUCI-', LPAD(v_seq, 4, '0'));
  END IF;
END$$
DELIMITER ;

-- ============================================================
-- PARTIE 3 — NOUVELLES TABLES (ordre de dépendance)
-- ============================================================

-- 3.1 op_types_vehicule (référentiel VP/Camion/Semi/Moto)
CREATE TABLE IF NOT EXISTS `op_types_vehicule` (
  `id`          int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`        varchar(20)  NOT NULL,
  `libelle`     varchar(100) NOT NULL,
  `nb_plaques`  tinyint UNSIGNED NOT NULL DEFAULT 2,
  `nb_rivets`   tinyint UNSIGNED NOT NULL DEFAULT 4,
  `serie_bobine` char(1) NOT NULL COMMENT 'A=VP, B=Camion, C=Semi, D=Moto',
  `ordre`       tinyint UNSIGNED DEFAULT 1,
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `op_types_vehicule` (`id`,`code`,`libelle`,`nb_plaques`,`nb_rivets`,`serie_bobine`,`ordre`) VALUES
(1,'VP',  'Véhicule Particulier',2,4,'A',1),
(2,'CAM', 'Camion',              2,4,'B',2),
(3,'SEMI','Semi-remorque',       1,2,'C',3),
(4,'MOTO','Moto',                1,2,'D',4);

-- 3.2 config_postes_types
CREATE TABLE IF NOT EXISTS `config_postes_types` (
  `id`          int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`        varchar(30)  NOT NULL,
  `libelle`     varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.3 config_postes_composants
CREATE TABLE IF NOT EXISTS `config_postes_composants` (
  `id`              int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `poste_id`        int UNSIGNED NOT NULL,
  `nomenclature_id` int UNSIGNED NOT NULL,
  `quantite`        int UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY `uq_poste_nom` (`poste_id`,`nomenclature_id`),
  KEY `nomenclature_id` (`nomenclature_id`),
  CONSTRAINT `cpc_ibfk_1` FOREIGN KEY (`poste_id`) REFERENCES `config_postes_types`(`id`) ON DELETE CASCADE,
  CONSTRAINT `cpc_ibfk_2` FOREIGN KEY (`nomenclature_id`) REFERENCES `nomenclatures`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.4 affectations_equipements
CREATE TABLE IF NOT EXISTS `affectations_equipements` (
  `id`           int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `equipement_id` int UNSIGNED NOT NULL,
  `site_dest_id` int UNSIGNED DEFAULT NULL,
  `user_dest_id` int UNSIGNED DEFAULT NULL,
  `statut`       enum('en_attente','valide_n1','recu','refuse') NOT NULL DEFAULT 'en_attente',
  `pdf_path`     varchar(255) DEFAULT NULL,
  `pdf_signe_n1` varchar(255) DEFAULT NULL,
  `pdf_signe_site` varchar(255) DEFAULT NULL,
  `notes`        text DEFAULT NULL,
  `created_by`   int UNSIGNED DEFAULT NULL,
  `valide_n1_by` int UNSIGNED DEFAULT NULL,
  `valide_n1_at` datetime DEFAULT NULL,
  `recu_by`      int UNSIGNED DEFAULT NULL,
  `recu_at`      datetime DEFAULT NULL,
  `created_at`   datetime DEFAULT CURRENT_TIMESTAMP,
  KEY `equipement_id` (`equipement_id`),
  KEY `site_dest_id`  (`site_dest_id`),
  KEY `user_dest_id`  (`user_dest_id`),
  KEY `created_by`    (`created_by`),
  KEY `valide_n1_by`  (`valide_n1_by`),
  CONSTRAINT `ae_ibfk_1` FOREIGN KEY (`equipement_id`) REFERENCES `equipements`(`id`),
  CONSTRAINT `ae_ibfk_2` FOREIGN KEY (`site_dest_id`)  REFERENCES `sites`(`id`) ON DELETE SET NULL,
  CONSTRAINT `ae_ibfk_3` FOREIGN KEY (`user_dest_id`)  REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `ae_ibfk_4` FOREIGN KEY (`created_by`)    REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `ae_ibfk_5` FOREIGN KEY (`valide_n1_by`)  REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.5 op_bobines
CREATE TABLE IF NOT EXISTS `op_bobines` (
  `id`                  int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `numero`              varchar(50) NOT NULL,
  `type_code`           varchar(10) NOT NULL,
  `serie`               char(1)     NOT NULL,
  `type_vehicule_id`    int UNSIGNED DEFAULT NULL,
  `films_total`         int UNSIGNED NOT NULL DEFAULT 500,
  `films_utilises`      int UNSIGNED NOT NULL DEFAULT 0,
  `films_endommages`    int UNSIGNED NOT NULL DEFAULT 0,
  `films_restants`      int UNSIGNED NOT NULL DEFAULT 500,
  `site_id`             int UNSIGNED DEFAULT NULL,
  `statut`              enum('en_stock','en_cours','retiree','epuisee','perdue') NOT NULL DEFAULT 'en_stock',
  `date_ouverture`      date DEFAULT NULL,
  `created_by`          int UNSIGNED DEFAULT NULL,
  `created_at`          datetime DEFAULT CURRENT_TIMESTAMP,
  `qte_initiale`        int UNSIGNED NOT NULL DEFAULT 500 COMMENT 'Quantité initiale (toujours 500)',
  `stock_systeme`       int UNSIGNED NOT NULL DEFAULT 500 COMMENT 'Stock calculé système',
  `stock_physique`      int UNSIGNED DEFAULT NULL COMMENT 'Dernier stock physique inventaire',
  `dernier_inventaire_id` int UNSIGNED DEFAULT NULL,
  `date_creation`       date DEFAULT (CURDATE()),
  `format`              varchar(50) DEFAULT NULL COMMENT 'Format bobine ex: A4, 80m',
  `notes_perte`         text DEFAULT NULL COMMENT 'Motif de la perte',
  UNIQUE KEY `numero` (`numero`),
  KEY `site_id`         (`site_id`),
  KEY `type_vehicule_id`(`type_vehicule_id`),
  KEY `idx_serie`       (`serie`),
  KEY `idx_statut`      (`statut`),
  CONSTRAINT `ob_ibfk_1` FOREIGN KEY (`site_id`)          REFERENCES `sites`(`id`) ON DELETE SET NULL,
  CONSTRAINT `ob_ibfk_2` FOREIGN KEY (`type_vehicule_id`) REFERENCES `op_types_vehicule`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.6 mouvements_bobines (avec types étendus pour GSB + cron)
CREATE TABLE IF NOT EXISTS `mouvements_bobines` (
  `id`         int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bobine_id`  int UNSIGNED NOT NULL,
  `type`       enum('entree','sortie','ajustement_inventaire','transfert','ajustement_gsb','epuisement_auto','perte') NOT NULL,
  `quantite`   int          NOT NULL COMMENT 'Positif=entrée, Négatif=sortie',
  `stock_avant` int UNSIGNED NOT NULL,
  `stock_apres` int UNSIGNED NOT NULL,
  `motif`      varchar(255) DEFAULT NULL,
  `ref_id`     int UNSIGNED DEFAULT NULL COMMENT 'ID consommation ou inventaire source',
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  KEY `created_by` (`created_by`),
  KEY `idx_bobine` (`bobine_id`),
  KEY `idx_type`   (`type`),
  KEY `idx_date`   (`created_at`),
  CONSTRAINT `mb_ibfk_1` FOREIGN KEY (`bobine_id`)  REFERENCES `op_bobines`(`id`) ON DELETE CASCADE,
  CONSTRAINT `mb_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.7 consommations_bobines
CREATE TABLE IF NOT EXISTS `consommations_bobines` (
  `id`         int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bobine_id`  int UNSIGNED NOT NULL,
  `site_id`    int UNSIGNED NOT NULL,
  `date_conso` date         NOT NULL,
  `quantite`   int UNSIGNED NOT NULL,
  `stock_avant` int UNSIGNED NOT NULL,
  `stock_apres` int UNSIGNED NOT NULL,
  `notes`      text DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  KEY `created_by` (`created_by`),
  KEY `idx_date`   (`date_conso`),
  KEY `idx_bobine` (`bobine_id`),
  KEY `idx_site`   (`site_id`),
  CONSTRAINT `cb_ibfk_1` FOREIGN KEY (`bobine_id`)  REFERENCES `op_bobines`(`id`) ON DELETE CASCADE,
  CONSTRAINT `cb_ibfk_2` FOREIGN KEY (`site_id`)    REFERENCES `sites`(`id`),
  CONSTRAINT `cb_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.8 op_points_journaliers
CREATE TABLE IF NOT EXISTS `op_points_journaliers` (
  `id`                        int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `site_id`                   int UNSIGNED NOT NULL,
  `date_point`                date         NOT NULL,
  `type_point`                enum('point_9h','point_13h','point_18h','debut_activite','mi_journee','fin_journee','final','intermediaire') NOT NULL DEFAULT 'point_18h',
  `statut`                    enum('suivi','brouillon','valide','refuse') NOT NULL DEFAULT 'brouillon',
  `nb_vp`                     int UNSIGNED DEFAULT 0,
  `nb_camion`                 int UNSIGNED DEFAULT 0,
  `nb_semi`                   int UNSIGNED DEFAULT 0,
  `nb_moto`                   int UNSIGNED DEFAULT 0,
  `total_engins`              int UNSIGNED DEFAULT 0,
  `total_plaques`             int UNSIGNED DEFAULT 0,
  `moyenne_prod`              decimal(6,2) DEFAULT 0.00,
  `rivets_utilises`           int UNSIGNED DEFAULT 0,
  `rivets_endommages`         int UNSIGNED DEFAULT 0,
  `non_poses_concessionnaires` int UNSIGNED DEFAULT 0,
  `non_poses_usagers`         int UNSIGNED DEFAULT 0,
  `nb_heures_travail`         decimal(4,1) DEFAULT 8.0,
  `observations`              text DEFAULT NULL,
  `created_by`                int UNSIGNED DEFAULT NULL,
  `validated_by`              int UNSIGNED DEFAULT NULL,
  `validated_at`              datetime DEFAULT NULL,
  `created_at`                datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_site_date_type` (`site_id`,`date_point`,`type_point`),
  KEY `created_by`  (`created_by`),
  KEY `validated_by`(`validated_by`),
  CONSTRAINT `opj_ibfk_1` FOREIGN KEY (`site_id`)     REFERENCES `sites`(`id`),
  CONSTRAINT `opj_ibfk_2` FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `opj_ibfk_3` FOREIGN KEY (`validated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.9 op_films_utilises
CREATE TABLE IF NOT EXISTS `op_films_utilises` (
  `id`               int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `point_id`         int UNSIGNED NOT NULL,
  `bobine_id`        int UNSIGNED NOT NULL,
  `type_vehicule_id` int UNSIGNED NOT NULL,
  `films_utilises`   int UNSIGNED NOT NULL DEFAULT 0,
  `films_endommages` int UNSIGNED NOT NULL DEFAULT 0,
  KEY `point_id`        (`point_id`),
  KEY `bobine_id`       (`bobine_id`),
  KEY `type_vehicule_id`(`type_vehicule_id`),
  CONSTRAINT `ofu_ibfk_1` FOREIGN KEY (`point_id`)         REFERENCES `op_points_journaliers`(`id`) ON DELETE CASCADE,
  CONSTRAINT `ofu_ibfk_2` FOREIGN KEY (`bobine_id`)        REFERENCES `op_bobines`(`id`),
  CONSTRAINT `ofu_ibfk_3` FOREIGN KEY (`type_vehicule_id`) REFERENCES `op_types_vehicule`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.10 op_stock_rivets
CREATE TABLE IF NOT EXISTS `op_stock_rivets` (
  `id`        int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `site_id`   int UNSIGNED NOT NULL,
  `quantite`  int UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_site` (`site_id`),
  CONSTRAINT `osr_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.11 inventaires_bobines
CREATE TABLE IF NOT EXISTS `inventaires_bobines` (
  `id`                    int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `site_id`               int UNSIGNED DEFAULT NULL COMMENT 'NULL = inventaire global',
  `date_inventaire`       date NOT NULL,
  `statut`                enum('brouillon','valide','annule') NOT NULL DEFAULT 'brouillon',
  `nb_bobines`            int UNSIGNED DEFAULT 0,
  `nb_ecarts`             int UNSIGNED DEFAULT 0,
  `notes`                 text DEFAULT NULL,
  `cree_par`              int UNSIGNED DEFAULT NULL,
  `valide_par`            int UNSIGNED DEFAULT NULL,
  `valide_at`             datetime DEFAULT NULL,
  `created_at`            datetime DEFAULT CURRENT_TIMESTAMP,
  `type_inventaire`       enum('journalier','mensuel') NOT NULL DEFAULT 'journalier',
  `total_films_systeme`   int DEFAULT 0,
  `total_films_physique`  int DEFAULT 0,
  `total_films_emuci`     int DEFAULT 0,
  `ecart_digistock_emuci` int DEFAULT 0,
  KEY `site_id`   (`site_id`),
  KEY `cree_par`  (`cree_par`),
  KEY `valide_par`(`valide_par`),
  KEY `idx_date`  (`date_inventaire`),
  KEY `idx_statut`(`statut`),
  CONSTRAINT `ib_ibfk_1` FOREIGN KEY (`site_id`)   REFERENCES `sites`(`id`) ON DELETE SET NULL,
  CONSTRAINT `ib_ibfk_2` FOREIGN KEY (`cree_par`)  REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `ib_ibfk_3` FOREIGN KEY (`valide_par`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.12 inventaire_details_bobines
CREATE TABLE IF NOT EXISTS `inventaire_details_bobines` (
  `id`                     int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `inventaire_id`          int UNSIGNED NOT NULL,
  `bobine_id`              int UNSIGNED NOT NULL,
  `stock_systeme`          int UNSIGNED NOT NULL,
  `stock_physique`         int UNSIGNED NOT NULL,
  `ecart`                  int          NOT NULL,
  `conso_quotidienne_moy`  decimal(8,2) DEFAULT NULL,
  `jours_restants_systeme` int DEFAULT NULL,
  `jours_restants_physique` int DEFAULT NULL,
  `date_epuisement_estime` date DEFAULT NULL,
  `notes`                  text DEFAULT NULL,
  `qte_temps_reel`         int DEFAULT NULL,
  `ecart_connu_avant`      int DEFAULT NULL,
  `films_emuci_jour`       int DEFAULT NULL,
  `ecart_emuci_digi`       int DEFAULT NULL,
  UNIQUE KEY `uq_inv_bobine` (`inventaire_id`,`bobine_id`),
  KEY `bobine_id` (`bobine_id`),
  CONSTRAINT `idb_ibfk_1` FOREIGN KEY (`inventaire_id`) REFERENCES `inventaires_bobines`(`id`) ON DELETE CASCADE,
  CONSTRAINT `idb_ibfk_2` FOREIGN KEY (`bobine_id`)     REFERENCES `op_bobines`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.13 ecarts_bobines (FK inventaire_id après inventaires_bobines)
CREATE TABLE IF NOT EXISTS `ecarts_bobines` (
  `id`               int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bobine_id`        int UNSIGNED NOT NULL,
  `date_constat`     date         NOT NULL,
  `stock_systeme`    int          NOT NULL,
  `stock_physique`   int          NOT NULL,
  `ecart`            int          NOT NULL COMMENT 'physique - systeme (négatif = manque)',
  `motif`            text DEFAULT NULL,
  `source`           enum('inventaire','manuel','import') NOT NULL DEFAULT 'manuel',
  `inventaire_id`    int UNSIGNED DEFAULT NULL,
  `statut`           enum('ouvert','resolu','ignore') NOT NULL DEFAULT 'ouvert',
  `resolu_at`        datetime DEFAULT NULL,
  `resolu_par`       int UNSIGNED DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_by`       int UNSIGNED DEFAULT NULL,
  `created_at`       datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `inventaire_id`(`inventaire_id`),
  KEY `resolu_par`   (`resolu_par`),
  KEY `created_by`   (`created_by`),
  KEY `idx_bobine`   (`bobine_id`),
  KEY `idx_statut`   (`statut`),
  KEY `idx_date`     (`date_constat`),
  CONSTRAINT `eb_ibfk_1` FOREIGN KEY (`bobine_id`)     REFERENCES `op_bobines`(`id`) ON DELETE CASCADE,
  CONSTRAINT `eb_ibfk_2` FOREIGN KEY (`inventaire_id`) REFERENCES `inventaires_bobines`(`id`) ON DELETE SET NULL,
  CONSTRAINT `eb_ibfk_3` FOREIGN KEY (`resolu_par`)    REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT `eb_ibfk_4` FOREIGN KEY (`created_by`)    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.14 bilans_mensuels_bobines
CREATE TABLE IF NOT EXISTS `bilans_mensuels_bobines` (
  `id`                       int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `site_id`                  int UNSIGNED NOT NULL,
  `mois`                     varchar(7)   NOT NULL COMMENT 'Format YYYY-MM',
  `inventaire_id`            int UNSIGNED DEFAULT NULL,
  `stock_debut_mois`         int DEFAULT 0,
  `stock_fin_mois`           int DEFAULT 0,
  `total_films_consommes`    int DEFAULT 0,
  `total_films_emuci`        int DEFAULT 0,
  `ecart_mois`               int DEFAULT 0,
  `nb_inventaires_journaliers` int DEFAULT 0,
  `nb_ajustements`           int DEFAULT 0,
  `statut`                   enum('en_cours','valide') NOT NULL DEFAULT 'en_cours',
  `valide_par`               int UNSIGNED DEFAULT NULL,
  `valide_at`                datetime DEFAULT NULL,
  `created_at`               datetime DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_site_mois` (`site_id`,`mois`),
  KEY `inventaire_id`(`inventaire_id`),
  KEY `valide_par`   (`valide_par`),
  CONSTRAINT `bmb_ibfk_1` FOREIGN KEY (`site_id`)       REFERENCES `sites`(`id`),
  CONSTRAINT `bmb_ibfk_2` FOREIGN KEY (`inventaire_id`) REFERENCES `inventaires_bobines`(`id`) ON DELETE SET NULL,
  CONSTRAINT `bmb_ibfk_3` FOREIGN KEY (`valide_par`)    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.15 validations_stock_matin
CREATE TABLE IF NOT EXISTS `validations_stock_matin` (
  `id`              int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `site_id`         int UNSIGNED NOT NULL,
  `date_validation` date         NOT NULL,
  `statut`          enum('valide_auto','valide_gsb','autorise_ecart','reajuste','refuse') NOT NULL,
  `nb_ecarts`       int DEFAULT 0,
  `details_ecarts`  JSON DEFAULT NULL COMMENT 'Détail des écarts par bobine',
  `gsb_user_id`     int UNSIGNED DEFAULT NULL,
  `gsb_at`          datetime DEFAULT NULL,
  `commentaire`     text DEFAULT NULL,
  `created_at`      datetime DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_site_date` (`site_id`,`date_validation`),
  KEY `gsb_user_id` (`gsb_user_id`),
  CONSTRAINT `vsm_ibfk_1` FOREIGN KEY (`site_id`)     REFERENCES `sites`(`id`),
  CONSTRAINT `vsm_ibfk_2` FOREIGN KEY (`gsb_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.16 demandes_bobines
CREATE TABLE IF NOT EXISTS `demandes_bobines` (
  `id`             int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bobine_id`      int UNSIGNED NOT NULL,
  `site_id`        int UNSIGNED NOT NULL,
  `demande_par`    int UNSIGNED NOT NULL COMMENT 'Coordinateur',
  `motif`          text NOT NULL,
  `statut`         enum('en_attente','approuvee','refusee') NOT NULL DEFAULT 'en_attente',
  `traite_par`     int UNSIGNED DEFAULT NULL COMMENT 'GSB qui a traité',
  `traite_at`      datetime DEFAULT NULL,
  `motif_reponse`  text DEFAULT NULL,
  `created_at`     datetime DEFAULT CURRENT_TIMESTAMP,
  KEY `bobine_id`  (`bobine_id`),
  KEY `demande_par`(`demande_par`),
  KEY `traite_par` (`traite_par`),
  KEY `idx_statut` (`statut`),
  KEY `idx_site`   (`site_id`),
  CONSTRAINT `db_ibfk_1` FOREIGN KEY (`bobine_id`)   REFERENCES `op_bobines`(`id`) ON DELETE CASCADE,
  CONSTRAINT `db_ibfk_2` FOREIGN KEY (`site_id`)     REFERENCES `sites`(`id`),
  CONSTRAINT `db_ibfk_3` FOREIGN KEY (`demande_par`) REFERENCES `users`(`id`),
  CONSTRAINT `db_ibfk_4` FOREIGN KEY (`traite_par`)  REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Demandes utilisation bobine coordinateur → GSB';

-- 3.17 demandes_correction_saisie (créé par notre migration d'intégration)
CREATE TABLE IF NOT EXISTS `demandes_correction_saisie` (
  `id`           int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bobine_id`    int UNSIGNED NOT NULL,
  `site_id`      int UNSIGNED NOT NULL,
  `gsb_id`       int UNSIGNED NOT NULL,
  `date_cible`   date         NOT NULL,
  `films_pj`     int          NOT NULL COMMENT 'Films déclarés dans le PJ coordinateur',
  `films_emuci`  int          NOT NULL COMMENT 'Films du fichier OptoPlate importé',
  `ecart`        int          NOT NULL COMMENT 'films_emuci - films_pj',
  `statut`       enum('en_attente','corrige','valide') NOT NULL DEFAULT 'en_attente',
  `notes_gsb`    text DEFAULT NULL,
  `created_at`   datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_site_date`(`site_id`,`date_cible`),
  KEY `idx_statut`   (`statut`),
  CONSTRAINT `dcs_ibfk_1` FOREIGN KEY (`bobine_id`) REFERENCES `op_bobines`(`id`) ON DELETE CASCADE,
  CONSTRAINT `dcs_ibfk_2` FOREIGN KEY (`site_id`)   REFERENCES `sites`(`id`) ON DELETE CASCADE,
  CONSTRAINT `dcs_ibfk_3` FOREIGN KEY (`gsb_id`)    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.18 comparaisons_stock
CREATE TABLE IF NOT EXISTS `comparaisons_stock` (
  `id`                int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `site_id`           int UNSIGNED NOT NULL,
  `date_comparaison`  date         NOT NULL,
  `films_emuci`       int          NOT NULL DEFAULT 0 COMMENT 'Films selon EMUCI',
  `films_digistock`   int          NOT NULL DEFAULT 0 COMMENT 'Films selon PJ coordinateurs',
  `ecart`             int          GENERATED ALWAYS AS (`films_emuci` - `films_digistock`) STORED,
  `statut_ecart`      enum('ok','mineur','majeur') GENERATED ALWAYS AS (
    CASE WHEN ABS(`films_emuci` - `films_digistock`) = 0 THEN 'ok'
         WHEN ABS(`films_emuci` - `films_digistock`) <= 5 THEN 'mineur'
         ELSE 'majeur' END
  ) STORED,
  `ajuste`            tinyint(1) DEFAULT 0,
  `ajuste_par`        int UNSIGNED DEFAULT NULL,
  `ajuste_at`         datetime DEFAULT NULL,
  `notes_ajustement`  text DEFAULT NULL,
  `created_at`        datetime DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_site_date` (`site_id`,`date_comparaison`),
  KEY `ajuste_par` (`ajuste_par`),
  CONSTRAINT `cs_ibfk_1` FOREIGN KEY (`site_id`)   REFERENCES `sites`(`id`),
  CONSTRAINT `cs_ibfk_2` FOREIGN KEY (`ajuste_par`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.19 commandes
CREATE TABLE IF NOT EXISTS `commandes` (
  `id`               int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `numero_commande`  varchar(30)  NOT NULL,
  `site_id`          int UNSIGNED NOT NULL,
  `statut`           enum('en_attente','en_attente_livraison','livre','annule') NOT NULL DEFAULT 'en_attente',
  `notes`            text DEFAULT NULL,
  `notes_livraison`  text DEFAULT NULL,
  `livraison_par`    int UNSIGNED DEFAULT NULL,
  `livraison_at`     datetime DEFAULT NULL,
  `recu_par`         int UNSIGNED DEFAULT NULL,
  `recu_at`          datetime DEFAULT NULL,
  `created_by`       int UNSIGNED DEFAULT NULL,
  `created_at`       datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `numero_commande` (`numero_commande`),
  KEY `idx_site_statut` (`site_id`,`statut`),
  CONSTRAINT `cmd_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Commandes des coordinateurs';

-- 3.20 commande_lignes
CREATE TABLE IF NOT EXISTS `commande_lignes` (
  `id`              int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `commande_id`     int UNSIGNED NOT NULL,
  `type_article`    enum('consommable','bobine','pmma','rivet','autre') NOT NULL DEFAULT 'consommable',
  `article_id`      int UNSIGNED DEFAULT NULL,
  `libelle`         varchar(255) NOT NULL,
  `quantite`        int UNSIGNED NOT NULL DEFAULT 1,
  `unite`           varchar(30) DEFAULT 'unité',
  `prix_unitaire`   decimal(15,2) DEFAULT 0.00,
  `quantite_livree` int UNSIGNED DEFAULT NULL COMMENT 'Quantité réellement livrée',
  `motif_ecart`     text DEFAULT NULL COMMENT 'Justification si écart',
  KEY `commande_id` (`commande_id`),
  CONSTRAINT `cl_ibfk_1` FOREIGN KEY (`commande_id`) REFERENCES `commandes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lignes de commandes';

-- 3.21 stock_pmma (mouvements)
CREATE TABLE IF NOT EXISTS `stock_pmma` (
  `id`             int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `site_id`        int UNSIGNED NOT NULL,
  `type_pmma`      varchar(50) DEFAULT 'Standard',
  `quantite`       int UNSIGNED NOT NULL,
  `type_mouvement` enum('entree','sortie') NOT NULL,
  `bobine_id`      int UNSIGNED DEFAULT NULL,
  `notes`          varchar(255) DEFAULT NULL,
  `created_by`     int UNSIGNED DEFAULT NULL,
  `created_at`     datetime DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_site_date` (`site_id`,`created_at`),
  CONSTRAINT `sp_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mouvements PMMA';

-- 3.22 stock_pmma_site (stock courant par site)
CREATE TABLE IF NOT EXISTS `stock_pmma_site` (
  `id`           int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `site_id`      int UNSIGNED NOT NULL,
  `type_pmma`    varchar(50) DEFAULT 'Standard',
  `quantite`     int UNSIGNED NOT NULL DEFAULT 0,
  `seuil_alerte` int UNSIGNED DEFAULT 10,
  `updated_at`   datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_site_type` (`site_id`,`type_pmma`),
  CONSTRAINT `sps_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stock PMMA par site';

-- 3.23 delegations
CREATE TABLE IF NOT EXISTS `delegations` (
  `id`               int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `superviseur_id`   int UNSIGNED NOT NULL COMMENT 'Superviseur qui délègue',
  `gestionnaire_id`  int UNSIGNED NOT NULL COMMENT 'Gestionnaire Opération qui reçoit',
  `module`           varchar(50)  NOT NULL,
  `libelle`          varchar(100) NOT NULL,
  `actif`            tinyint(1) DEFAULT 1,
  `created_at`       datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_sup_gest_module` (`superviseur_id`,`gestionnaire_id`,`module`),
  KEY `idx_gestionnaire` (`gestionnaire_id`),
  CONSTRAINT `del_ibfk_1` FOREIGN KEY (`superviseur_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `del_ibfk_2` FOREIGN KEY (`gestionnaire_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.24 import_sessions_emuci
CREATE TABLE IF NOT EXISTS `import_sessions_emuci` (
  `id`                  varchar(36)  NOT NULL PRIMARY KEY,
  `date_import`         date         NOT NULL,
  `type_import`         enum('optoplate','optotrace','les_deux') NOT NULL,
  `nb_lignes_optoplate` int DEFAULT 0,
  `nb_lignes_optotrace` int DEFAULT 0,
  `nb_erreurs`          int DEFAULT 0,
  `statut`              enum('en_cours','termine','erreur') DEFAULT 'en_cours',
  `importe_par`         int UNSIGNED NOT NULL,
  `notes`               text DEFAULT NULL,
  `created_at`          datetime DEFAULT CURRENT_TIMESTAMP,
  KEY `importe_par` (`importe_par`),
  KEY `idx_date`    (`date_import`),
  CONSTRAINT `ise_ibfk_1` FOREIGN KEY (`importe_par`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sessions d import EMUCI';

-- 3.25 import_optoplate
CREATE TABLE IF NOT EXISTS `import_optoplate` (
  `id`                int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `import_session_id` varchar(36)  NOT NULL,
  `date_import`       date         NOT NULL,
  `date_installation` datetime DEFAULT NULL,
  `numero_dossier`    varchar(50)  DEFAULT NULL,
  `immatriculation`   varchar(30)  NOT NULL,
  `vin`               varchar(20)  DEFAULT NULL,
  `type_plaque`       varchar(60)  DEFAULT NULL,
  `statut_plaque`     varchar(30)  NOT NULL,
  `position`          varchar(10)  DEFAULT NULL,
  `num_consommable`   varchar(30)  DEFAULT NULL,
  `num_bobine`        varchar(50)  DEFAULT NULL,
  `site_id_emuci`     varchar(20)  DEFAULT NULL,
  `site_nom_emuci`    varchar(100) DEFAULT NULL,
  `site_id`           int UNSIGNED DEFAULT NULL,
  `importe_par`       int UNSIGNED NOT NULL,
  `created_at`        datetime DEFAULT CURRENT_TIMESTAMP,
  KEY `importe_par` (`importe_par`),
  KEY `idx_session` (`import_session_id`),
  KEY `idx_date`    (`date_import`),
  KEY `idx_statut`  (`statut_plaque`),
  KEY `idx_bobine`  (`num_bobine`),
  KEY `idx_site`    (`site_id`),
  KEY `idx_immat`   (`immatriculation`),
  CONSTRAINT `iop_ibfk_1` FOREIGN KEY (`site_id`)     REFERENCES `sites`(`id`) ON DELETE SET NULL,
  CONSTRAINT `iop_ibfk_2` FOREIGN KEY (`importe_par`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Import quotidien OptoPlate – plaques posées';

-- 3.26 import_optotrace
CREATE TABLE IF NOT EXISTS `import_optotrace` (
  `id`                int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `import_session_id` varchar(36)  NOT NULL,
  `date_import`       date         NOT NULL,
  `plate_number`      varchar(30)  NOT NULL,
  `vin`               varchar(20)  DEFAULT NULL,
  `case_number`       varchar(30)  DEFAULT NULL,
  `category`          varchar(60)  DEFAULT NULL,
  `site_nom_emuci`    varchar(100) DEFAULT NULL,
  `site_id`           int UNSIGNED DEFAULT NULL,
  `installation_date` datetime DEFAULT NULL,
  `is_last`           tinyint(1) DEFAULT 0,
  `is_deleted`        tinyint(1) DEFAULT 0,
  `importe_par`       int UNSIGNED NOT NULL,
  `created_at`        datetime DEFAULT CURRENT_TIMESTAMP,
  KEY `importe_par` (`importe_par`),
  KEY `idx_session` (`import_session_id`),
  KEY `idx_date`    (`date_import`),
  KEY `idx_plate`   (`plate_number`),
  KEY `idx_site`    (`site_id`),
  CONSTRAINT `iot_ibfk_1` FOREIGN KEY (`site_id`)     REFERENCES `sites`(`id`) ON DELETE SET NULL,
  CONSTRAINT `iot_ibfk_2` FOREIGN KEY (`importe_par`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Import quotidien OptoTrace – registre plaques/véhicules';

-- 3.27 interventions_maintenance
CREATE TABLE IF NOT EXISTS `interventions_maintenance` (
  `id`               int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `technicien_id`    int UNSIGNED NOT NULL,
  `site_id`          int UNSIGNED NOT NULL,
  `equipement_id`    int UNSIGNED DEFAULT NULL,
  `date_intervention` date        NOT NULL,
  `type_action`      enum('maintenance_preventive','maintenance_corrective','installation','remplacement','diagnostic','formation','autre') NOT NULL DEFAULT 'maintenance_corrective',
  `description`      text NOT NULL,
  `probleme_signale` text DEFAULT NULL,
  `solution_apportee` text DEFAULT NULL,
  `statut_apres`     enum('resolu','partiel','en_attente','escalade') NOT NULL DEFAULT 'resolu',
  `duree_minutes`    int UNSIGNED DEFAULT NULL,
  `pieces_changees`  text DEFAULT NULL,
  `rapport_fichier`  varchar(255) DEFAULT NULL,
  `created_at`       datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `equipement_id` (`equipement_id`),
  KEY `idx_date`      (`date_intervention`),
  KEY `idx_technicien`(`technicien_id`),
  KEY `idx_site`      (`site_id`),
  CONSTRAINT `im_ibfk_1` FOREIGN KEY (`technicien_id`) REFERENCES `users`(`id`),
  CONSTRAINT `im_ibfk_2` FOREIGN KEY (`site_id`)       REFERENCES `sites`(`id`),
  CONSTRAINT `im_ibfk_3` FOREIGN KEY (`equipement_id`) REFERENCES `equipements`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.28 points_emuci
CREATE TABLE IF NOT EXISTS `points_emuci` (
  `id`                  int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `site_id`             int UNSIGNED NOT NULL,
  `date_point`          date         NOT NULL,
  `plaques_posees`      int UNSIGNED NOT NULL DEFAULT 0,
  `plaques_reservees`   int UNSIGNED NOT NULL DEFAULT 0,
  `total_films_deduits` int UNSIGNED GENERATED ALWAYS AS (`plaques_posees` + `plaques_reservees`) STORED,
  `notes`               text DEFAULT NULL,
  `statut`              enum('brouillon','soumis','valide') NOT NULL DEFAULT 'brouillon',
  `saisi_par`           int UNSIGNED NOT NULL,
  `valide_par`          int UNSIGNED DEFAULT NULL,
  `valide_at`           datetime DEFAULT NULL,
  `created_at`          datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_site_date` (`site_id`,`date_point`),
  KEY `saisi_par`  (`saisi_par`),
  KEY `valide_par` (`valide_par`),
  KEY `idx_date`   (`date_point`),
  KEY `idx_statut` (`statut`),
  CONSTRAINT `pe_ibfk_1` FOREIGN KEY (`site_id`)    REFERENCES `sites`(`id`),
  CONSTRAINT `pe_ibfk_2` FOREIGN KEY (`saisi_par`)  REFERENCES `users`(`id`),
  CONSTRAINT `pe_ibfk_3` FOREIGN KEY (`valide_par`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.29 points_journaliers_info (rapport journalier technicien)
CREATE TABLE IF NOT EXISTS `points_journaliers_info` (
  `id`                  int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `technicien_id`       int UNSIGNED NOT NULL,
  `site_id`             int UNSIGNED NOT NULL,
  `date_point`          date         NOT NULL,
  `nb_equip_ok`         int UNSIGNED DEFAULT 0,
  `nb_equip_hs`         int UNSIGNED DEFAULT 0,
  `nb_interventions`    int UNSIGNED DEFAULT 0,
  `observations`        text DEFAULT NULL,
  `actions_preventives` text DEFAULT NULL,
  `statut`              enum('brouillon','valide') NOT NULL DEFAULT 'brouillon',
  `valide_par`          int UNSIGNED DEFAULT NULL,
  `valide_at`           datetime DEFAULT NULL,
  `created_at`          datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `valide_par`      (`valide_par`),
  KEY `idx_site_date`   (`site_id`,`date_point`),
  KEY `idx_technicien`  (`technicien_id`),
  CONSTRAINT `pji_ibfk_1` FOREIGN KEY (`technicien_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `pji_ibfk_2` FOREIGN KEY (`site_id`)       REFERENCES `sites`(`id`),
  CONSTRAINT `pji_ibfk_3` FOREIGN KEY (`valide_par`)    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.30 rapports_journaliers_info
CREATE TABLE IF NOT EXISTS `rapports_journaliers_info` (
  `id`                      int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `technicien_id`           int UNSIGNED NOT NULL,
  `site_id`                 int UNSIGNED NOT NULL,
  `date_rapport`            date         NOT NULL,
  `nb_equip_ok`             int UNSIGNED DEFAULT 0,
  `nb_equip_hs`             int UNSIGNED DEFAULT 0,
  `nb_equip_maintenance`    int UNSIGNED DEFAULT 0,
  `nb_interventions`        int UNSIGNED DEFAULT 0,
  `observations`            text DEFAULT NULL,
  `actions_preventives`     text DEFAULT NULL,
  `statut`                  enum('brouillon','valide') NOT NULL DEFAULT 'brouillon',
  `valide_par`              int UNSIGNED DEFAULT NULL,
  `valide_at`               datetime DEFAULT NULL,
  `created_at`              datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_tech_site_date` (`technicien_id`,`site_id`,`date_rapport`),
  KEY `valide_par`    (`valide_par`),
  KEY `idx_site_date` (`site_id`,`date_rapport`),
  CONSTRAINT `rji_ibfk_1` FOREIGN KEY (`technicien_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `rji_ibfk_2` FOREIGN KEY (`site_id`)       REFERENCES `sites`(`id`),
  CONSTRAINT `rji_ibfk_3` FOREIGN KEY (`valide_par`)    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.31 receptions_consommables
CREATE TABLE IF NOT EXISTS `receptions_consommables` (
  `id`              int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `consommable_id`  int UNSIGNED    NOT NULL,
  `quantite`        decimal(10,2)   NOT NULL,
  `prix_unitaire`   decimal(12,2)   DEFAULT 0.00,
  `prix_total`      decimal(12,2)   DEFAULT 0.00,
  `date_reception`  date            NOT NULL,
  `fournisseur`     varchar(150)    DEFAULT NULL,
  `numero_bon`      varchar(100)    DEFAULT NULL,
  `notes`           text DEFAULT NULL,
  `created_by`      int UNSIGNED    DEFAULT NULL,
  `created_at`      datetime DEFAULT CURRENT_TIMESTAMP,
  KEY `consommable_id` (`consommable_id`),
  KEY `created_by`     (`created_by`),
  KEY `idx_date`       (`date_reception`),
  CONSTRAINT `rc_ibfk_1` FOREIGN KEY (`consommable_id`) REFERENCES `consommables`(`id`),
  CONSTRAINT `rc_ibfk_2` FOREIGN KEY (`created_by`)     REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.32 receptions_site
CREATE TABLE IF NOT EXISTS `receptions_site` (
  `id`                 int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `site_id`            int UNSIGNED NOT NULL,
  `type_reception`     enum('equipement','consommable') NOT NULL,
  `equipement_id`      int UNSIGNED DEFAULT NULL,
  `consommable_id`     int UNSIGNED DEFAULT NULL,
  `quantite`           decimal(10,2) DEFAULT NULL,
  `livraison_ref_id`   int UNSIGNED DEFAULT NULL,
  `mouvement_ref_id`   int UNSIGNED DEFAULT NULL,
  `date_reception`     date         NOT NULL,
  `fichier_fiche`      varchar(255) DEFAULT NULL COMMENT 'Fiche signée obligatoire',
  `notes`              text DEFAULT NULL,
  `statut`             enum('en_attente','receptionnee','litige') NOT NULL DEFAULT 'en_attente',
  `litige_motif`       text DEFAULT NULL,
  `litige_traite_by`   int UNSIGNED DEFAULT NULL,
  `litige_traite_at`   datetime DEFAULT NULL,
  `remplacement_id`    int UNSIGNED DEFAULT NULL,
  `remplacement_notes` text DEFAULT NULL,
  `created_by`         int UNSIGNED DEFAULT NULL,
  `created_at`         datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `equipement_id`  (`equipement_id`),
  KEY `consommable_id` (`consommable_id`),
  KEY `created_by`     (`created_by`),
  KEY `idx_site`       (`site_id`),
  KEY `idx_date`       (`date_reception`),
  KEY `idx_statut`     (`statut`),
  CONSTRAINT `rs_ibfk_1` FOREIGN KEY (`site_id`)       REFERENCES `sites`(`id`),
  CONSTRAINT `rs_ibfk_2` FOREIGN KEY (`equipement_id`) REFERENCES `equipements`(`id`) ON DELETE SET NULL,
  CONSTRAINT `rs_ibfk_3` FOREIGN KEY (`consommable_id`) REFERENCES `consommables`(`id`) ON DELETE SET NULL,
  CONSTRAINT `rs_ibfk_4` FOREIGN KEY (`created_by`)    REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.33 litige_messages
CREATE TABLE IF NOT EXISTS `litige_messages` (
  `id`           int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `reception_id` int UNSIGNED NOT NULL,
  `user_id`      int UNSIGNED NOT NULL,
  `message`      text NOT NULL,
  `created_at`   datetime DEFAULT CURRENT_TIMESTAMP,
  KEY `user_id`      (`user_id`),
  KEY `idx_reception`(`reception_id`),
  CONSTRAINT `lm_ibfk_1` FOREIGN KEY (`reception_id`) REFERENCES `receptions_site`(`id`) ON DELETE CASCADE,
  CONSTRAINT `lm_ibfk_2` FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.34 support_it_roles (sous-rôles affectés aux Support IT)
CREATE TABLE IF NOT EXISTS `support_it_roles` (
  `id`          int UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     int UNSIGNED NOT NULL COMMENT 'Compte Support IT',
  `sous_role`   enum('maintenance','controleur_production','gestionnaire_bobines') NOT NULL,
  `actif`       tinyint(1) DEFAULT 1,
  `affecte_par` int UNSIGNED DEFAULT NULL,
  `created_at`  datetime DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_user_sousrole` (`user_id`,`sous_role`),
  KEY `affecte_par`  (`affecte_par`),
  KEY `idx_sous_role`(`sous_role`),
  CONSTRAINT `sir_ibfk_1` FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `sir_ibfk_2` FOREIGN KEY (`affecte_par`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- PARTIE 4 — VUES
-- ============================================================

CREATE OR REPLACE VIEW `v_cout_hebdo_site` AS
SELECT s.id AS site_id, s.nom AS site_nom, s.type AS site_type,
  YEAR(lc.date_livraison) AS annee, WEEK(lc.date_livraison,1) AS semaine,
  STR_TO_DATE(CONCAT(YEAR(lc.date_livraison),' ',WEEK(lc.date_livraison,1),' Monday'),'%X %V %W') AS debut_semaine,
  COALESCE(SUM(lc.prix_total),0) AS cout_total,
  COALESCE(SUM(lc.quantite),0) AS qte_total,
  COUNT(DISTINCT lc.consommable_id) AS nb_articles
FROM sites s
LEFT JOIN livraisons_consommables lc ON lc.site_id = s.id
WHERE s.actif = 1
GROUP BY s.id, YEAR(lc.date_livraison), WEEK(lc.date_livraison,1);

CREATE OR REPLACE VIEW `v_cout_mensuel_site` AS
SELECT s.id AS site_id, s.nom AS site_nom, s.type AS site_type,
  YEAR(lc.date_livraison) AS annee, MONTH(lc.date_livraison) AS mois,
  DATE_FORMAT(lc.date_livraison,'%Y-%m') AS mois_label,
  COALESCE(SUM(lc.prix_total),0) AS cout_total,
  COALESCE(SUM(lc.quantite),0) AS qte_total,
  COUNT(DISTINCT lc.consommable_id) AS nb_articles
FROM sites s
LEFT JOIN livraisons_consommables lc ON lc.site_id = s.id
WHERE s.actif = 1
GROUP BY s.id, YEAR(lc.date_livraison), MONTH(lc.date_livraison);

-- ============================================================
-- PARTIE 5 — NOUVEAUX RÔLES (INSERT IGNORE = sans doublon)
-- ============================================================

INSERT IGNORE INTO `roles` (`id`,`nom`,`slug`,`description`) VALUES
(5,  'Gestionnaire de Stock',    'gestionnaire_stock',    'Gère tout le stock central : entrées, sorties, livraisons sites'),
(6,  'Coordinateur de Site',     'coordinateur_site',     'Réceptionne les commandes sur son site, fait le point journalier'),
(7,  'Maintenance Informatique', 'maintenance_info',      'Gère le stock équipements informatiques uniquement'),
(8,  'Superviseur Opération',    'superviseur_operation', 'Supervise les actions des coordinateurs de site'),
(9,  'Contrôleur Production',    'controleur_production', 'Saisie quotidienne des plaques posées et réservées (données EMUCI)'),
(13, 'Gestionnaire Stock Bobines','gestionnaire_stock_bobines','Validation stock matin, demandes bobines, réajustements'),
(14, 'Gestionnaire Opération',   'gestionnaire_operation','Second du Superviseur Opération – tâches déléguées'),
(15, 'Superviseur IT',           'superviseur_it',        'Accès complet informatique + supervise les Support IT'),
(16, 'Support IT',               'support_it',            'Profil flexible – sous-rôles : Maintenance, Contrôleur, GSB'),
(17, 'Superviseur Achat',        'superviseur_achat',     'Suivi consommables, équipements, consommation des sites');

-- ============================================================
-- PARTIE 6 — PERMISSIONS POUR LES NOUVEAUX RÔLES
-- ============================================================

-- Supprimer les anciennes permissions superadmin/admin (ids 1-86 from database.sql)
-- et réinsérer avec les bons ids depuis le dump réel
INSERT IGNORE INTO `permissions` (`id`,`role_id`,`module`,`can_create`,`can_read`,`can_update`,`can_delete`,`can_export`) VALUES
-- Superadmin (role_id=1) — modules complets
(462,1,'equipements',1,1,1,1,1),(463,2,'equipements',1,1,1,1,1),
(464,1,'sites',1,1,1,1,1),(465,2,'sites',1,1,1,1,1),
(466,1,'affectations',1,1,1,1,1),(467,2,'affectations',1,1,1,1,1),
(468,1,'receptions',1,1,1,1,1),(469,2,'receptions',1,1,1,1,1),
(470,1,'bobines',1,1,1,1,1),(471,2,'bobines',1,1,1,1,1),
(472,1,'inventaire_bobines',1,1,1,1,1),(473,2,'inventaire_bobines',1,1,1,1,1),
(474,1,'consommables',1,1,1,1,1),(475,2,'consommables',1,1,1,1,1),
(476,1,'rapports',1,1,1,1,1),(477,2,'rapports',1,1,1,1,1),
(478,1,'interventions',1,1,1,1,1),(479,2,'interventions',1,1,1,1,1),
(480,1,'point_emuci',1,1,1,1,1),(481,2,'point_emuci',1,1,1,1,1),
(482,1,'import_emuci',1,1,1,1,1),(483,2,'import_emuci',1,1,1,1,1),
(484,1,'nomenclatures',1,1,1,1,1),(485,2,'nomenclatures',1,1,1,1,1),
(486,1,'users',1,1,1,1,1),(487,2,'users',1,1,1,1,1),
(488,1,'audit',1,1,1,1,1),(489,2,'audit',1,1,1,1,1),
(490,1,'delegations',1,1,1,1,1),(491,2,'delegations',1,1,1,1,1),
(492,1,'rivets',1,1,1,1,1),(493,2,'rivets',1,1,1,1,1),
(552,2,'pmma',1,1,1,0,1),(553,2,'commandes',1,1,1,0,1),
(554,1,'pmma',1,1,1,0,1),(555,1,'commandes',1,1,1,0,1),
-- Superviseur Opération (role_id=8)
(525,8,'equipements',0,1,0,0,1),(526,8,'sites',0,1,0,0,1),
(527,8,'affectations',0,1,0,0,1),(528,8,'receptions',1,1,1,0,1),
(529,8,'bobines',1,1,1,0,1),(530,8,'inventaire_bobines',1,1,1,0,1),
(531,8,'consommables',0,1,0,0,1),(532,8,'rapports',0,1,0,0,1),
(533,8,'interventions',0,1,0,0,0),(534,8,'point_emuci',1,1,1,0,1),
(535,8,'import_emuci',0,1,0,0,1),(536,8,'audit',0,1,0,0,0),
(537,8,'delegations',1,1,1,1,0),(538,8,'rivets',1,1,1,0,1),
(558,8,'pmma',1,1,1,0,1),(559,8,'commandes',1,1,1,0,1),
-- Coordinateur de Site (role_id=6)
(540,6,'equipements',0,1,0,0,0),(541,6,'bobines',1,1,1,0,1),
(542,6,'inventaire_bobines',1,1,1,0,0),(543,6,'receptions',1,1,1,0,1),
(544,6,'consommables',0,1,0,0,0),(545,6,'rapports',0,1,0,0,1),
(546,6,'rivets',0,1,0,0,0),(567,6,'pmma',1,1,1,0,0),
(568,6,'commandes',1,1,0,0,0),
-- Gestionnaire de Stock (role_id=5)
(547,5,'equipements',1,1,1,0,1),(548,5,'consommables',1,1,1,0,1),
(549,5,'receptions',1,1,1,0,1),(550,5,'rapports',0,1,0,0,1),
(551,5,'sites',0,1,0,0,0),(570,5,'commandes',0,1,1,0,1),
(571,5,'pmma',1,1,1,0,1),
-- Superviseur IT (role_id=15)
(556,15,'pmma',1,1,1,0,1),(557,15,'commandes',1,1,1,0,1),
(577,15,'interventions',1,1,1,0,1),
-- Support IT (role_id=16)
(578,16,'interventions',1,1,1,0,1),
-- Superviseur Achat (role_id=17)
(572,17,'commandes',0,1,1,0,1),(573,17,'pmma',1,1,1,0,1),
-- Gestionnaire Opération (role_id=14)
(588,14,'commandes',0,1,0,0,0);

-- ============================================================
-- PARTIE 7 — DONNÉES DE RÉFÉRENCE (configurations_site)
-- ============================================================

INSERT IGNORE INTO `configurations_site` (`id`,`type_site`,`nomenclature_id`,`quantite`,`optionnel`) VALUES
(1,'pose',1,1,0),(2,'pose',5,1,0),(3,'pose',6,1,0),(4,'pose',7,1,0),
(5,'pose',2,1,0),(6,'pose',3,1,0),(7,'pose',9,1,0),
(8,'saisie',1,1,0),(9,'saisie',6,1,0),(10,'saisie',7,1,0),
(11,'saisie',3,1,0),(12,'saisie',4,1,0),
(13,'mixte',1,1,0),(14,'mixte',5,1,0),(15,'mixte',6,1,0),
(16,'mixte',2,1,0),(17,'mixte',8,1,0),(18,'mixte',7,1,0),
(19,'mixte',9,1,0),(20,'mixte',3,1,0),(21,'mixte',4,1,0);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- FIN DE LA MIGRATION
-- ============================================================
-- Résumé :
--   • 8 tables existantes modifiées (ALTER TABLE)
--   • 1 trigger mis à jour
--   • 32 nouvelles tables créées
--   • 2 vues créées
--   • 10 nouveaux rôles ajoutés
--   • Permissions complètes pour tous les rôles
--   • Données de référence op_types_vehicule + configurations_site
-- ============================================================
