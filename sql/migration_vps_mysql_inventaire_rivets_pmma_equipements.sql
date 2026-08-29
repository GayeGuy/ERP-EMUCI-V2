-- ============================================================
--  Rattrapage main -> vps-mysql (2026-08-29) : mecanisme d'inventaire
--  complet (sessions, comptage, validation, ecarts, sous-workflow de
--  correction) pour Rivets, PMMA et Equipements — deja en place pour
--  Bobines sur cette branche (inventaires_bobines / ecarts_bobines /
--  inventaire_corrections). Traduction MySQL des 4 migrations Postgres
--  equivalentes (sql/migration_inventaire_rivets.sql,
--  migration_inventaire_pmma.sql, migration_inventaire_equipements.sql,
--  migration_permission_module_inventaire.sql), memes conventions que
--  inventaires_bobines/ecarts_bobines/inventaire_corrections deja en
--  place sur cette branche (int unsigned AUTO_INCREMENT, datetime,
--  CHECK plutot que ENUM).
-- ============================================================

-- ── RIVETS ──────────────────────────────────────────────────
CREATE TABLE inventaires_rivets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED DEFAULT NULL,
    date_inventaire DATE NOT NULL,
    type_inventaire VARCHAR(20) NOT NULL DEFAULT 'journalier',
    statut VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    nb_types INT UNSIGNED DEFAULT 0,
    nb_ecarts INT UNSIGNED DEFAULT 0,
    total_quantite_systeme INT DEFAULT 0,
    total_quantite_physique INT DEFAULT 0,
    notes TEXT,
    cree_par INT UNSIGNED DEFAULT NULL,
    valide_par INT UNSIGNED DEFAULT NULL,
    valide_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    session_id INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_inventaires_rivets_site (site_id),
    KEY idx_inventaires_rivets_statut (statut),
    KEY idx_inventaires_rivets_date (date_inventaire),
    KEY idx_inventaires_rivets_session (session_id),
    CONSTRAINT inventaires_rivets_site_fkey FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL,
    CONSTRAINT inventaires_rivets_cree_par_fkey FOREIGN KEY (cree_par) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT inventaires_rivets_valide_par_fkey FOREIGN KEY (valide_par) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT inventaires_rivets_session_fkey FOREIGN KEY (session_id) REFERENCES inventaire_sessions(id),
    CONSTRAINT inventaires_rivets_type_chk CHECK (type_inventaire IN ('journalier','mensuel')),
    CONSTRAINT inventaires_rivets_statut_chk CHECK (statut IN ('brouillon','valide','annule'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventaire_details_rivets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    inventaire_id INT UNSIGNED NOT NULL,
    type_rivet VARCHAR(20) NOT NULL,
    stock_systeme INT NOT NULL,
    stock_physique INT NOT NULL DEFAULT 0,
    ecart INT NOT NULL DEFAULT 0,
    ecart_connu_avant INT DEFAULT 0,
    notes TEXT,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inv_details_rivets (inventaire_id, type_rivet),
    CONSTRAINT inv_details_rivets_inventaire_fkey FOREIGN KEY (inventaire_id) REFERENCES inventaires_rivets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ecarts_rivets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED NOT NULL,
    type_rivet VARCHAR(20) NOT NULL,
    date_constat DATE NOT NULL,
    stock_systeme INT NOT NULL,
    stock_physique INT NOT NULL,
    ecart INT NOT NULL,
    motif TEXT,
    source VARCHAR(20) DEFAULT 'inventaire',
    inventaire_id INT UNSIGNED DEFAULT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'ouvert',
    resolu_at DATETIME DEFAULT NULL,
    resolu_par INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ecarts_rivets_site (site_id),
    KEY idx_ecarts_rivets_statut (statut),
    KEY idx_ecarts_rivets_date (date_constat),
    CONSTRAINT ecarts_rivets_site_fkey FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    CONSTRAINT ecarts_rivets_inventaire_fkey FOREIGN KEY (inventaire_id) REFERENCES inventaires_rivets(id) ON DELETE SET NULL,
    CONSTRAINT ecarts_rivets_resolu_par_fkey FOREIGN KEY (resolu_par) REFERENCES users(id),
    CONSTRAINT ecarts_rivets_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventaire_corrections_rivets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    detail_id INT UNSIGNED NOT NULL,
    inventaire_id INT UNSIGNED NOT NULL,
    type_rivet VARCHAR(20) NOT NULL,
    site_id INT UNSIGNED NOT NULL,
    stock_physique_actuel INT NOT NULL,
    valeur_proposee INT DEFAULT NULL,
    motif TEXT NOT NULL,
    demandeur_id INT UNSIGNED NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'en_attente',
    valeur_finale INT DEFAULT NULL,
    reponse TEXT,
    traite_par INT UNSIGNED DEFAULT NULL,
    traite_at DATETIME DEFAULT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'demande_site',
    autorise_par INT UNSIGNED DEFAULT NULL,
    autorise_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inv_corr_rivets_detail (detail_id),
    KEY idx_inv_corr_rivets_inventaire (inventaire_id),
    CONSTRAINT inv_corr_rivets_detail_fkey FOREIGN KEY (detail_id) REFERENCES inventaire_details_rivets(id) ON DELETE CASCADE,
    CONSTRAINT inv_corr_rivets_inventaire_fkey FOREIGN KEY (inventaire_id) REFERENCES inventaires_rivets(id) ON DELETE CASCADE,
    CONSTRAINT inv_corr_rivets_site_fkey FOREIGN KEY (site_id) REFERENCES sites(id),
    CONSTRAINT inv_corr_rivets_demandeur_fkey FOREIGN KEY (demandeur_id) REFERENCES users(id),
    CONSTRAINT inv_corr_rivets_traite_par_fkey FOREIGN KEY (traite_par) REFERENCES users(id),
    CONSTRAINT inv_corr_rivets_autorise_par_fkey FOREIGN KEY (autorise_par) REFERENCES users(id),
    CONSTRAINT inv_corr_rivets_statut_chk CHECK (statut IN ('en_attente','autorise','refuse','traite')),
    CONSTRAINT inv_corr_rivets_type_chk CHECK (type IN ('demande_site','demande_autorisation'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PMMA ────────────────────────────────────────────────────
CREATE TABLE inventaires_pmma (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED DEFAULT NULL,
    date_inventaire DATE NOT NULL,
    type_inventaire VARCHAR(20) NOT NULL DEFAULT 'journalier',
    statut VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    nb_types INT UNSIGNED DEFAULT 0,
    nb_ecarts INT UNSIGNED DEFAULT 0,
    total_quantite_systeme INT DEFAULT 0,
    total_quantite_physique INT DEFAULT 0,
    notes TEXT,
    cree_par INT UNSIGNED DEFAULT NULL,
    valide_par INT UNSIGNED DEFAULT NULL,
    valide_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    session_id INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_inventaires_pmma_site (site_id),
    KEY idx_inventaires_pmma_statut (statut),
    KEY idx_inventaires_pmma_date (date_inventaire),
    KEY idx_inventaires_pmma_session (session_id),
    CONSTRAINT inventaires_pmma_site_fkey FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL,
    CONSTRAINT inventaires_pmma_cree_par_fkey FOREIGN KEY (cree_par) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT inventaires_pmma_valide_par_fkey FOREIGN KEY (valide_par) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT inventaires_pmma_session_fkey FOREIGN KEY (session_id) REFERENCES inventaire_sessions(id),
    CONSTRAINT inventaires_pmma_type_chk CHECK (type_inventaire IN ('journalier','mensuel')),
    CONSTRAINT inventaires_pmma_statut_chk CHECK (statut IN ('brouillon','valide','annule'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventaire_details_pmma (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    inventaire_id INT UNSIGNED NOT NULL,
    type_pmma VARCHAR(50) NOT NULL,
    stock_systeme INT NOT NULL,
    stock_physique INT NOT NULL DEFAULT 0,
    ecart INT NOT NULL DEFAULT 0,
    ecart_connu_avant INT DEFAULT 0,
    notes TEXT,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inv_details_pmma (inventaire_id, type_pmma),
    CONSTRAINT inv_details_pmma_inventaire_fkey FOREIGN KEY (inventaire_id) REFERENCES inventaires_pmma(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ecarts_pmma (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED NOT NULL,
    type_pmma VARCHAR(50) NOT NULL,
    date_constat DATE NOT NULL,
    stock_systeme INT NOT NULL,
    stock_physique INT NOT NULL,
    ecart INT NOT NULL,
    motif TEXT,
    source VARCHAR(20) DEFAULT 'inventaire',
    inventaire_id INT UNSIGNED DEFAULT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'ouvert',
    resolu_at DATETIME DEFAULT NULL,
    resolu_par INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ecarts_pmma_site (site_id),
    KEY idx_ecarts_pmma_statut (statut),
    KEY idx_ecarts_pmma_date (date_constat),
    CONSTRAINT ecarts_pmma_site_fkey FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    CONSTRAINT ecarts_pmma_inventaire_fkey FOREIGN KEY (inventaire_id) REFERENCES inventaires_pmma(id) ON DELETE SET NULL,
    CONSTRAINT ecarts_pmma_resolu_par_fkey FOREIGN KEY (resolu_par) REFERENCES users(id),
    CONSTRAINT ecarts_pmma_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventaire_corrections_pmma (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    detail_id INT UNSIGNED NOT NULL,
    inventaire_id INT UNSIGNED NOT NULL,
    type_pmma VARCHAR(50) NOT NULL,
    site_id INT UNSIGNED NOT NULL,
    stock_physique_actuel INT NOT NULL,
    valeur_proposee INT DEFAULT NULL,
    motif TEXT NOT NULL,
    demandeur_id INT UNSIGNED NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'en_attente',
    valeur_finale INT DEFAULT NULL,
    reponse TEXT,
    traite_par INT UNSIGNED DEFAULT NULL,
    traite_at DATETIME DEFAULT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'demande_site',
    autorise_par INT UNSIGNED DEFAULT NULL,
    autorise_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inv_corr_pmma_detail (detail_id),
    KEY idx_inv_corr_pmma_inventaire (inventaire_id),
    CONSTRAINT inv_corr_pmma_detail_fkey FOREIGN KEY (detail_id) REFERENCES inventaire_details_pmma(id) ON DELETE CASCADE,
    CONSTRAINT inv_corr_pmma_inventaire_fkey FOREIGN KEY (inventaire_id) REFERENCES inventaires_pmma(id) ON DELETE CASCADE,
    CONSTRAINT inv_corr_pmma_site_fkey FOREIGN KEY (site_id) REFERENCES sites(id),
    CONSTRAINT inv_corr_pmma_demandeur_fkey FOREIGN KEY (demandeur_id) REFERENCES users(id),
    CONSTRAINT inv_corr_pmma_traite_par_fkey FOREIGN KEY (traite_par) REFERENCES users(id),
    CONSTRAINT inv_corr_pmma_autorise_par_fkey FOREIGN KEY (autorise_par) REFERENCES users(id),
    CONSTRAINT inv_corr_pmma_statut_chk CHECK (statut IN ('en_attente','autorise','refuse','traite')),
    CONSTRAINT inv_corr_pmma_type_chk CHECK (type IN ('demande_site','demande_autorisation'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── EQUIPEMENTS ─────────────────────────────────────────────
CREATE TABLE inventaires_equipements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED DEFAULT NULL,
    date_inventaire DATE NOT NULL,
    type_inventaire VARCHAR(20) NOT NULL DEFAULT 'journalier',
    statut VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    nb_equipements INT UNSIGNED DEFAULT 0,
    nb_trouves INT UNSIGNED DEFAULT 0,
    nb_ecarts INT UNSIGNED DEFAULT 0,
    notes TEXT,
    cree_par INT UNSIGNED DEFAULT NULL,
    valide_par INT UNSIGNED DEFAULT NULL,
    valide_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    session_id INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_inventaires_equip_site (site_id),
    KEY idx_inventaires_equip_statut (statut),
    KEY idx_inventaires_equip_date (date_inventaire),
    KEY idx_inventaires_equip_session (session_id),
    CONSTRAINT inventaires_equip_site_fkey FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL,
    CONSTRAINT inventaires_equip_cree_par_fkey FOREIGN KEY (cree_par) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT inventaires_equip_valide_par_fkey FOREIGN KEY (valide_par) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT inventaires_equip_session_fkey FOREIGN KEY (session_id) REFERENCES inventaire_sessions(id),
    CONSTRAINT inventaires_equip_type_chk CHECK (type_inventaire IN ('journalier','mensuel')),
    CONSTRAINT inventaires_equip_statut_chk CHECK (statut IN ('brouillon','valide','annule'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventaire_details_equipements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    inventaire_id INT UNSIGNED NOT NULL,
    equipement_id INT UNSIGNED NOT NULL,
    trouve TINYINT DEFAULT NULL COMMENT 'NULL=non saisi, 1=trouve, 0=manquant',
    ecart_connu_avant TINYINT DEFAULT 0,
    notes TEXT,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inv_details_equip (inventaire_id, equipement_id),
    CONSTRAINT inv_details_equip_inventaire_fkey FOREIGN KEY (inventaire_id) REFERENCES inventaires_equipements(id) ON DELETE CASCADE,
    CONSTRAINT inv_details_equip_equipement_fkey FOREIGN KEY (equipement_id) REFERENCES equipements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ecarts_equipements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED NOT NULL,
    equipement_id INT UNSIGNED NOT NULL,
    date_constat DATE NOT NULL,
    motif TEXT,
    source VARCHAR(20) DEFAULT 'inventaire',
    inventaire_id INT UNSIGNED DEFAULT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'ouvert',
    resolu_at DATETIME DEFAULT NULL,
    resolu_par INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ecarts_equip_site (site_id),
    KEY idx_ecarts_equip_statut (statut),
    KEY idx_ecarts_equip_date (date_constat),
    KEY idx_ecarts_equip_equipement (equipement_id),
    CONSTRAINT ecarts_equip_site_fkey FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    CONSTRAINT ecarts_equip_equipement_fkey FOREIGN KEY (equipement_id) REFERENCES equipements(id) ON DELETE CASCADE,
    CONSTRAINT ecarts_equip_inventaire_fkey FOREIGN KEY (inventaire_id) REFERENCES inventaires_equipements(id) ON DELETE SET NULL,
    CONSTRAINT ecarts_equip_resolu_par_fkey FOREIGN KEY (resolu_par) REFERENCES users(id),
    CONSTRAINT ecarts_equip_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventaire_corrections_equipements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    detail_id INT UNSIGNED NOT NULL,
    inventaire_id INT UNSIGNED NOT NULL,
    equipement_id INT UNSIGNED NOT NULL,
    site_id INT UNSIGNED NOT NULL,
    valeur_actuelle TINYINT DEFAULT NULL,
    valeur_proposee TINYINT DEFAULT NULL,
    motif TEXT NOT NULL,
    demandeur_id INT UNSIGNED NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'en_attente',
    valeur_finale TINYINT DEFAULT NULL,
    reponse TEXT,
    traite_par INT UNSIGNED DEFAULT NULL,
    traite_at DATETIME DEFAULT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'demande_site',
    autorise_par INT UNSIGNED DEFAULT NULL,
    autorise_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inv_corr_equip_detail (detail_id),
    KEY idx_inv_corr_equip_inventaire (inventaire_id),
    CONSTRAINT inv_corr_equip_detail_fkey FOREIGN KEY (detail_id) REFERENCES inventaire_details_equipements(id) ON DELETE CASCADE,
    CONSTRAINT inv_corr_equip_inventaire_fkey FOREIGN KEY (inventaire_id) REFERENCES inventaires_equipements(id) ON DELETE CASCADE,
    CONSTRAINT inv_corr_equip_equipement_fkey FOREIGN KEY (equipement_id) REFERENCES equipements(id),
    CONSTRAINT inv_corr_equip_site_fkey FOREIGN KEY (site_id) REFERENCES sites(id),
    CONSTRAINT inv_corr_equip_demandeur_fkey FOREIGN KEY (demandeur_id) REFERENCES users(id),
    CONSTRAINT inv_corr_equip_traite_par_fkey FOREIGN KEY (traite_par) REFERENCES users(id),
    CONSTRAINT inv_corr_equip_autorise_par_fkey FOREIGN KEY (autorise_par) REFERENCES users(id),
    CONSTRAINT inv_corr_equip_statut_chk CHECK (statut IN ('en_attente','autorise','refuse','traite')),
    CONSTRAINT inv_corr_equip_type_chk CHECK (type IN ('demande_site','demande_autorisation'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PERMISSIONS ─────────────────────────────────────────────
-- ecarts_rivets/ecarts_pmma/ecarts_equipements : memes roles que
-- ecarts_bobines aujourd'hui.
INSERT INTO permissions (role_id, module, can_read)
SELECT r.id, 'ecarts_rivets', 1 FROM roles r
WHERE r.slug IN ('controleur_production','gestionnaire_stock','gestionnaire_stock_bobines','lecteur','superviseur_operation')
ON DUPLICATE KEY UPDATE can_read = can_read;

INSERT INTO permissions (role_id, module, can_read)
SELECT r.id, 'ecarts_pmma', 1 FROM roles r
WHERE r.slug IN ('controleur_production','gestionnaire_stock','gestionnaire_stock_bobines','lecteur','superviseur_operation')
ON DUPLICATE KEY UPDATE can_read = can_read;

INSERT INTO permissions (role_id, module, can_read)
SELECT r.id, 'ecarts_equipements', 1 FROM roles r
WHERE r.slug IN ('controleur_production','gestionnaire_stock','gestionnaire_stock_bobines','lecteur','superviseur_operation')
ON DUPLICATE KEY UPDATE can_read = can_read;

-- inventaire_rivets / inventaire_equipements : memes droits que
-- inventaire_bobines aujourd'hui.
INSERT INTO permissions (role_id, module, can_read, can_create, can_update)
SELECT p.role_id, 'inventaire_rivets', p.can_read, p.can_create, p.can_update
FROM permissions p WHERE p.module = 'inventaire_bobines'
ON DUPLICATE KEY UPDATE can_read=VALUES(can_read), can_create=VALUES(can_create), can_update=VALUES(can_update);

INSERT INTO permissions (role_id, module, can_read, can_create, can_update)
SELECT p.role_id, 'inventaire_equipements', p.can_read, p.can_create, p.can_update
FROM permissions p WHERE p.module = 'inventaire_bobines'
ON DUPLICATE KEY UPDATE can_read=VALUES(can_read), can_create=VALUES(can_create), can_update=VALUES(can_update);

-- inventaire_pmma : memes droits que le module pmma existant.
INSERT INTO permissions (role_id, module, can_read, can_create, can_update)
SELECT p.role_id, 'inventaire_pmma', p.can_read, p.can_create, p.can_update
FROM permissions p WHERE p.module = 'pmma'
ON DUPLICATE KEY UPDATE can_read=VALUES(can_read), can_create=VALUES(can_create), can_update=VALUES(can_update);

-- Droit 'inventaire' (visibilite du groupe de menu INVENTAIRE) : memes
-- roles qui avaient acces au groupe BOBINES avant qu'il n'en soit
-- extrait.
INSERT INTO permissions (role_id, module, can_read)
SELECT r.id, 'inventaire', 1 FROM roles r
WHERE r.slug IN ('coordinateur_site','gestionnaire_stock','superviseur_operation','controleur_production','gestionnaire_stock_bobines')
ON DUPLICATE KEY UPDATE can_read = can_read;
