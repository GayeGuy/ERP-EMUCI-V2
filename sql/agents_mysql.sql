-- ============================================================
--  Module "Agents" — table des agents NSIIV
--  Alimentée par import Excel depuis pages/agents.php.
--  Utilisée par le module demandes internes pour l'autofill.
--  Variante MySQL de agents_pg.sql.
-- ============================================================

CREATE TABLE IF NOT EXISTS `agents` (
  `id`          int UNSIGNED NOT NULL AUTO_INCREMENT,
  `matricule`   varchar(50)  DEFAULT NULL,
  `nom`         varchar(100) NOT NULL,
  `prenom`      varchar(100) NOT NULL DEFAULT '',
  `email`       varchar(150) DEFAULT NULL,
  `telephone`   varchar(30)  DEFAULT NULL,
  `fonction`    varchar(150) DEFAULT NULL,
  `departement` varchar(150) DEFAULT NULL,
  `direction`   varchar(150) DEFAULT NULL,
  `site`        varchar(150) DEFAULT NULL,
  `grade`       varchar(100) DEFAULT NULL,
  `statut`      varchar(20)  NOT NULL DEFAULT 'actif',
  `created_at`  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `agents_matricule_uidx` (`matricule`),
  KEY `agents_nom_idx` (`nom`, `prenom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions initiales (admin + superadmin accès total, gsb lecture)
-- Adapter selon la structure de votre table permissions :
-- INSERT IGNORE INTO permissions (role_id, module, can_create, can_read, can_update, can_delete)
-- SELECT id, 'agents', 1, 1, 1, 1 FROM roles WHERE slug IN ('admin','superadmin');
