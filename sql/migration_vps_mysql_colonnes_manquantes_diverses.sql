-- ============================================================
--  sql/migration_vps_mysql_colonnes_manquantes_diverses.sql
-- ============================================================
--
--  Trouve par comparaison exhaustive information_schema.columns entre
--  PostgreSQL (main) et ce fork MySQL (Phase 3 du rattrapage main ->
--  vps-mysql) — 14 colonnes existant sur main mais absentes ici, sur des
--  fonctionnalites independantes du module Achats (integration commandes
--  internes <-> FEB, correction GSB des points journaliers, changement de
--  mot de passe force, ticket GLPI, bon de livraison equipement).
-- ============================================================

ALTER TABLE users
    ADD COLUMN must_change_password TINYINT NOT NULL DEFAULT 0 AFTER password_hash;

ALTER TABLE articles
    ADD COLUMN famille_id INT UNSIGNED NULL,
    ADD COLUMN site_id    INT UNSIGNED NULL,
    ADD CONSTRAINT articles_famille_id_fkey FOREIGN KEY (famille_id) REFERENCES familles_achat(id),
    ADD CONSTRAINT articles_site_id_fkey    FOREIGN KEY (site_id)    REFERENCES sites(id);

ALTER TABLE commande_lignes
    ADD COLUMN feb_ligne_id       INT UNSIGNED NULL,
    ADD COLUMN motif_modification TEXT NULL,
    ADD CONSTRAINT commande_lignes_feb_ligne_id_fkey FOREIGN KEY (feb_ligne_id) REFERENCES feb_lignes(id);

ALTER TABLE commandes
    ADD COLUMN feb_id INT UNSIGNED NULL,
    ADD CONSTRAINT commandes_feb_id_fkey FOREIGN KEY (feb_id) REFERENCES feb(id);

ALTER TABLE di_demandes
    ADD COLUMN site_id     INT UNSIGNED NULL,
    ADD COLUMN ticket_glpi VARCHAR(50) NULL,
    ADD CONSTRAINT di_demandes_site_id_fkey FOREIGN KEY (site_id) REFERENCES sites(id);

ALTER TABLE equipements
    ADD COLUMN bon_livraison VARCHAR(255) NULL DEFAULT NULL;

ALTER TABLE op_points_journaliers
    ADD COLUMN corrected_at        DATETIME NULL,
    ADD COLUMN corrected_by_gp     INT UNSIGNED NULL,
    ADD COLUMN correction_gp       INT UNSIGNED NULL,
    ADD COLUMN motif_correction_gp VARCHAR(500) NULL DEFAULT NULL,
    ADD COLUMN motif_rejet         TEXT NULL;
