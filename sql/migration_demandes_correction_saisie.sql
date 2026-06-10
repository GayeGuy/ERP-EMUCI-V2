-- ============================================================
--  migration_demandes_correction_saisie.sql
--  À exécuter APRÈS migration_action2.sql et migration_profils_v2.sql
--
--  Crée la table qui lie un refus GSB à une demande de correction
--  précise pour chaque bobine en écart, visible par le coordinateur.
-- ============================================================

USE `stockapp`;

-- Table principale : une ligne = une bobine en écart pour laquelle
-- le GSB a demandé une correction au coordinateur.
CREATE TABLE IF NOT EXISTS `demandes_correction_saisie` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `bobine_id`       INT UNSIGNED NOT NULL,
  `site_id`         INT UNSIGNED NOT NULL,
  `gsb_id`          INT UNSIGNED NOT NULL,
  `date_cible`      DATE         NOT NULL,
  `films_pj`        INT          NOT NULL COMMENT 'Films déclarés dans le PJ coordinateur',
  `films_emuci`     INT          NOT NULL COMMENT 'Films du fichier OptoPlate/EMUCI importé',
  `ecart`           INT          NOT NULL COMMENT 'films_emuci - films_pj',
  `statut`          ENUM('en_attente','corrige','valide') NOT NULL DEFAULT 'en_attente',
  `notes_gsb`       TEXT,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_site_date`  (`site_id`, `date_cible`),
  KEY `idx_statut`     (`statut`),
  FOREIGN KEY (`bobine_id`) REFERENCES `op_bobines`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`site_id`)   REFERENCES `sites`(`id`)     ON DELETE CASCADE,
  FOREIGN KEY (`gsb_id`)    REFERENCES `users`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
