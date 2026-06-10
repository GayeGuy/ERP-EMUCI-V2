-- ============================================================
--  migration_articles_commandes.sql — v2 (compatible MariaDB 10.4)
--  Exécuter une seule fois sur la base stockapp
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────
--  1. TABLE articles (créée à partir de consommables)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `articles` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`         VARCHAR(30)  NOT NULL,
  `libelle`      VARCHAR(200) NOT NULL,
  `type_article` ENUM('papeterie','encre','materiel','pieces','fournitures','autre') DEFAULT 'autre',
  `unite`        VARCHAR(20)  DEFAULT 'unite',
  `description`  TEXT,
  `seuil_alerte` INT          DEFAULT 10,
  `prix_unitaire` INT         DEFAULT 0,
  `stock_global` INT          DEFAULT 0,
  `actif`        TINYINT(1)   DEFAULT 1,
  `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_article_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrer les données de consommables → articles
INSERT IGNORE INTO `articles`
  (id, code, libelle, unite, description, seuil_alerte, prix_unitaire, stock_global, actif, created_at)
SELECT
  id,
  code,
  libelle,
  CAST(unite AS CHAR),
  description,
  ROUND(COALESCE(seuil_alerte, 10)),
  ROUND(COALESCE(prix_unitaire, 0)),
  ROUND(COALESCE(stock_global, 0)),
  1,
  created_at
FROM `consommables`;

-- ─────────────────────────────────────────────────────────────
--  2. TABLE stock_site
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `stock_site` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id` INT UNSIGNED NOT NULL,
  `site_id`    INT UNSIGNED NOT NULL,
  `quantite`   INT          DEFAULT 0,
  `updated_at` DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_article_site` (`article_id`, `site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrer stock_consommables_site → stock_site
INSERT IGNORE INTO `stock_site` (article_id, site_id, quantite)
SELECT consommable_id, site_id, ROUND(COALESCE(quantite, 0))
FROM `stock_consommables_site`;

-- ─────────────────────────────────────────────────────────────
--  3. TABLE mouvements_stock
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `mouvements_stock` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `article_id`     INT UNSIGNED,
  `site_id`        INT UNSIGNED,
  `type_mouvement` ENUM('entree','sortie','ajustement','distribution','reception_fourn','reception_site') NOT NULL,
  `quantite`       INT         NOT NULL,
  `solde_apres`    INT         DEFAULT 0,
  `reference`      VARCHAR(100),
  `notes`          TEXT,
  `created_by`     INT UNSIGNED,
  `created_at`     DATETIME    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_article` (`article_id`),
  KEY `idx_site`    (`site_id`),
  KEY `idx_date`    (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
--  4. TABLE receptions_fournisseur
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `receptions_fournisseur` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `numero_reception` VARCHAR(50)  NOT NULL,
  `fournisseur`      VARCHAR(200),
  `date_reception`   DATE         NOT NULL,
  `statut`           ENUM('en_attente','recu_partiel','recu_complet') DEFAULT 'en_attente',
  `notes`            TEXT,
  `fichier_bl`       VARCHAR(255),
  `created_by`       INT UNSIGNED,
  `created_at`       DATETIME     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_num_reception` (`numero_reception`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
--  5. TABLE reception_lignes
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `reception_lignes` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reception_id`      INT UNSIGNED NOT NULL,
  `article_id`        INT UNSIGNED,
  `libelle`           VARCHAR(200),
  `quantite_attendue` INT          DEFAULT 0,
  `quantite_recue`    INT          DEFAULT 0,
  `unite`             VARCHAR(20)  DEFAULT 'unite',
  `prix_unitaire`     INT          DEFAULT 0,
  `created_at`        DATETIME     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reception` (`reception_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
--  6. TABLE distributions_site
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `distributions_site` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `numero_distribution` VARCHAR(50)  NOT NULL,
  `commande_id`         INT UNSIGNED,
  `site_id`             INT UNSIGNED NOT NULL,
  `date_distribution`   DATE         NOT NULL,
  `statut`              ENUM('en_cours_livraison','livre','annule') DEFAULT 'en_cours_livraison',
  `notes`               TEXT,
  `fichier_bl`          VARCHAR(255),
  `created_by`          INT UNSIGNED,
  `expedie_at`          DATETIME     NULL,
  `recu_at`             DATETIME     NULL,
  `recu_par`            INT UNSIGNED,
  `created_at`          DATETIME     DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_num_distribution` (`numero_distribution`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
--  7. TABLE distribution_lignes
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `distribution_lignes` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `distribution_id`   INT UNSIGNED NOT NULL,
  `article_id`        INT UNSIGNED,
  `commande_ligne_id` INT UNSIGNED,
  `libelle`           VARCHAR(200),
  `quantite_envoyee`  INT          DEFAULT 0,
  `quantite_recue`    INT          DEFAULT 0,
  `unite`             VARCHAR(20)  DEFAULT 'unite',
  `statut`            ENUM('en_cours_livraison','livre','litige') DEFAULT 'en_cours_livraison',
  PRIMARY KEY (`id`),
  KEY `idx_distribution` (`distribution_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
--  8. ALTER TABLE commandes — ajout des nouveaux statuts
--     On garde 'livre' pour la compatibilité des données existantes
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `commandes`
  MODIFY COLUMN `statut` ENUM(
    'en_attente',
    'en_attente_livraison',
    'en_cours_livraison',
    'livre',
    'recu',
    'rejete',
    'annule'
  ) NOT NULL DEFAULT 'en_attente';

ALTER TABLE `commandes`
  ADD COLUMN IF NOT EXISTS `valide_par`          INT UNSIGNED  DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `valide_at`           DATETIME      NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `motif_rejet_global`  TEXT          DEFAULT NULL;

-- ─────────────────────────────────────────────────────────────
--  9. ALTER TABLE commande_lignes — statut par ligne
-- ─────────────────────────────────────────────────────────────
ALTER TABLE `commande_lignes`
  ADD COLUMN IF NOT EXISTS `statut_ligne` ENUM('en_attente','valide','modifie','rejete') NOT NULL DEFAULT 'en_attente',
  ADD COLUMN IF NOT EXISTS `motif_rejet`  TEXT DEFAULT NULL;

-- Mettre à jour type_article 'consommable' → 'article' pour les lignes existantes
-- (on modifie d'abord l'ENUM pour accepter 'article')
ALTER TABLE `commande_lignes`
  MODIFY COLUMN `type_article` ENUM('article','consommable','bobine','pmma','rivet','equipement','autre') NOT NULL DEFAULT 'article';

UPDATE `commande_lignes` SET `type_article` = 'article' WHERE `type_article` = 'consommable';

-- Mettre toutes les lignes existantes comme validées (workflow rétroactif)
UPDATE `commande_lignes` SET `statut_ligne` = 'valide' WHERE `statut_ligne` = 'en_attente';

SET FOREIGN_KEY_CHECKS = 1;
