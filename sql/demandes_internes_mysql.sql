-- ============================================================
--  Module "Demandes internes" — schema MySQL 8 (branche vps-mysql / VPS)
--  Equivalent MySQL de demandes_internes_pg.sql + migrations
--  (departements, n1_user_id, roles RAF/DAF/Directeur General).
--  A charger APRES sql/stockapp.sql (reutilise users, roles, permissions,
--  notifications, sites). Colonnes JSON stockees en TEXT (json_encode/decode PHP).
--  Idempotent : CREATE TABLE IF NOT EXISTS + INSERT IGNORE.
-- ============================================================

-- Roles de validation (n1, raf, daf, dg, it, gestionnaire)
CREATE TABLE IF NOT EXISTS `di_roles` (
  `code`  varchar(30) NOT NULL,
  `label` varchar(100) NOT NULL,
  `ordre` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles de validation attribues manuellement (legacy ; la validation derive surtout du role ERP)
CREATE TABLE IF NOT EXISTS `di_user_roles` (
  `user_id`   int(10) UNSIGNED NOT NULL,
  `role_code` varchar(30) NOT NULL,
  PRIMARY KEY (`user_id`,`role_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Types de demande
CREATE TABLE IF NOT EXISTS `di_types` (
  `id`              int UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`            varchar(50) NOT NULL,
  `label`           varchar(150) NOT NULL,
  `description`     text DEFAULT NULL,
  `actif`           tinyint(1) NOT NULL DEFAULT 1,
  `traitement_it`   tinyint(1) NOT NULL DEFAULT 0,
  `date_auto_jours` int DEFAULT NULL,
  `ordre`           int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Etapes de validation par type (le circuit)
CREATE TABLE IF NOT EXISTS `di_etapes` (
  `id`        int UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_id`   int UNSIGNED NOT NULL,
  `role_code` varchar(30) NOT NULL,
  `label`     varchar(150) NOT NULL,
  `ordre`     int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_type_ordre` (`type_id`,`ordre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plateformes NSIIV (pour les demandes d'acces)
CREATE TABLE IF NOT EXISTS `di_plateformes` (
  `code`  varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `ordre` int NOT NULL DEFAULT 0,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Demandes
CREATE TABLE IF NOT EXISTS `di_demandes` (
  `id`                int UNSIGNED NOT NULL AUTO_INCREMENT,
  `numero`            varchar(30) NOT NULL,
  `type_code`         varchar(50) NOT NULL,
  `statut`            varchar(30) NOT NULL DEFAULT 'brouillon',
  `etape_actuelle`    int NOT NULL DEFAULT 0,
  `etape_rejet`       int DEFAULT NULL,
  `demandeur_id`      int(10) UNSIGNED NOT NULL,
  `n1_user_id`        int(10) UNSIGNED DEFAULT NULL,
  `champs`            text DEFAULT NULL,
  `historique`        text DEFAULT NULL,
  `signatures`        text DEFAULT NULL,
  `workflow_snapshot` text DEFAULT NULL,
  `priorite`          varchar(20) NOT NULL DEFAULT 'normal',
  `traite_it`         tinyint(1) NOT NULL DEFAULT 0,
  `traite_par`        int(10) UNSIGNED DEFAULT NULL,
  `traite_date`       datetime DEFAULT NULL,
  `submitted_at`      datetime DEFAULT NULL,
  `created_at`        datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `di_demandes_demandeur_idx` (`demandeur_id`),
  KEY `di_demandes_statut_idx` (`statut`),
  KEY `di_demandes_type_idx` (`type_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Departements + rattachement N+1 (circuit N+1 par departement)
CREATE TABLE IF NOT EXISTS `departements` (
  `id`         int UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`       varchar(50) NOT NULL,
  `label`      varchar(100) NOT NULL,
  `ordre`      int NOT NULL DEFAULT 0,
  `actif`      tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_departements` (
  `user_id`        int(10) UNSIGNED NOT NULL,
  `departement_id` int UNSIGNED NOT NULL,
  `is_n1`          tinyint(1) NOT NULL DEFAULT 0,
  `created_at`     datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`departement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SEED (idempotent)
-- ============================================================
INSERT IGNORE INTO `di_roles` (`code`,`label`,`ordre`) VALUES
  ('n1','Responsable N+1',1),
  ('raf','RAF',2),
  ('daf','DAF',3),
  ('dg','Direction Generale',4),
  ('it','Responsable IT',5),
  ('gestionnaire','Service Administration',6);

INSERT IGNORE INTO `di_plateformes` (`code`,`label`,`ordre`) VALUES
  ('brique_additionnelle','Brique Additionnelle',1),
  ('nsiiv_enrolement','NSIIV Enrolement',2),
  ('nsiiv_optoplate','NSIIV Optoplate',3),
  ('nsiiv_optotrace','NSIIV Optotrace',4),
  ('email_professionnel','E-mail professionnel',5);

INSERT IGNORE INTO `di_types` (`code`,`label`,`description`,`traitement_it`,`date_auto_jours`,`ordre`) VALUES
  ('autorisation_absence','Autorisation d''absence','Demander un conge, une permission ou une absence exceptionnelle',0,NULL,0),
  ('creation_acces','Creation d''acces NSIIV','Demander la creation d''acces aux plateformes NSIIV pour un agent',1,0,1),
  ('basculement_acces','Basculement d''acces','Modifier les acces plateformes d''un agent',1,0,2),
  ('basculement_compte','Basculement de compte EMUCI','Demander le changement de poste sur un compte EMUCI existant',1,0,3),
  ('transfert_agent','Transfert d''agent','Demander le transfert d''un agent vers un autre site',1,7,4),
  ('creation_site','Creation de site','Demander l''enregistrement d''un nouveau site dans le systeme NSIIV',1,0,5),
  ('changement_geolocalisation','Changement de geolocalisation','Modifier les coordonnees GPS d''un site existant',1,0,6),
  ('imputation_courrier','Imputation courrier entrant','Imputer un courrier entrant a un service ou une personne',0,0,7),
  ('exceptionnel','Demande exceptionnelle','Demande hors cadre standard - motif obligatoire',0,NULL,8);

-- Circuits (etapes) par type ; INSERT IGNORE + UNIQUE(type_id,ordre) => idempotent
INSERT IGNORE INTO `di_etapes` (`type_id`,`role_code`,`label`,`ordre`)
SELECT t.`id`, v.role_code, v.label, v.ordre
FROM (
      SELECT 'autorisation_absence' AS type_code,'n1' AS role_code,'Visa N+1' AS label,0 AS ordre
  UNION ALL SELECT 'autorisation_absence','gestionnaire','Visa Administration',1
  UNION ALL SELECT 'autorisation_absence','dg','Visa Direction Generale',2
  UNION ALL SELECT 'creation_acces','n1','Visa N+1',0
  UNION ALL SELECT 'creation_acces','it','Visa Responsable IT',1
  UNION ALL SELECT 'creation_acces','dg','Visa Direction Generale',2
  UNION ALL SELECT 'basculement_acces','n1','Visa N+1',0
  UNION ALL SELECT 'basculement_acces','it','Visa Responsable IT',1
  UNION ALL SELECT 'basculement_acces','dg','Visa Direction Generale',2
  UNION ALL SELECT 'basculement_compte','n1','Visa Responsable Hierarchique',0
  UNION ALL SELECT 'basculement_compte','it','Visa Service IT',1
  UNION ALL SELECT 'transfert_agent','n1','Visa Responsable Hierarchique (N+1)',0
  UNION ALL SELECT 'transfert_agent','raf','Visa RAF',1
  UNION ALL SELECT 'transfert_agent','daf','Visa DAF',2
  UNION ALL SELECT 'transfert_agent','dg','Visa Direction Generale',3
  UNION ALL SELECT 'creation_site','it','Visa Responsable IT',0
  UNION ALL SELECT 'creation_site','dg','Visa Direction Generale',1
  UNION ALL SELECT 'changement_geolocalisation','it','Visa Responsable IT',0
  UNION ALL SELECT 'changement_geolocalisation','dg','Visa Direction Generale',1
  UNION ALL SELECT 'imputation_courrier','gestionnaire','Visa Service Imputant',0
  UNION ALL SELECT 'imputation_courrier','dg','Validation PDG (si requise)',1
  UNION ALL SELECT 'exceptionnel','n1','Visa N+1',0
  UNION ALL SELECT 'exceptionnel','dg','Visa Direction Generale',1
) AS v
JOIN `di_types` t ON t.`code` = v.type_code;

-- ============================================================
--  Roles ERP pilotant la validation (Administration -> Permissions)
--  raf, daf, directeur_general. Voir includes/demandes.php di_user_roles().
-- ============================================================
INSERT IGNORE INTO `roles` (`nom`,`slug`,`description`,`created_at`) VALUES
  ('Responsable Administratif et Financier','raf','Validation administrative et financiere des demandes internes (etape RAF)',NOW()),
  ('Directeur Administratif et Financier','daf','Validation DG des demandes financieres (etape DAF)',NOW()),
  ('Directeur General','directeur_general','Direction Generale - validation finale des demandes internes (etape DG)',NOW());

-- Permissions de base : lecture + export
INSERT IGNORE INTO `permissions` (`role_id`,`module`,`can_read`,`can_create`,`can_update`,`can_delete`,`can_export`)
SELECT r.`id`, m.module, 1, 0, 0, 0, 1
FROM `roles` r
JOIN (SELECT 'rapports' AS module UNION ALL SELECT 'equipements' UNION ALL SELECT 'consommables') m
WHERE r.`slug` IN ('raf','daf');

INSERT IGNORE INTO `permissions` (`role_id`,`module`,`can_read`,`can_create`,`can_update`,`can_delete`,`can_export`)
SELECT r.`id`, 'rapports', 1, 0, 0, 0, 1
FROM `roles` r WHERE r.`slug` = 'directeur_general';
