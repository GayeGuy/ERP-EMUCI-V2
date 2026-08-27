-- ============================================================
--  sql/migration_vps_mysql_stock_rivets_type.sql
-- ============================================================
--
--  Sur PostgreSQL (main), op_stock_rivets porte une colonne type_rivet
--  ('gonflable'/'eclate', cf. pages/operations/rivets.php) et sa cle
--  unique porte sur (site_id, type_rivet) — un site a une ligne de stock
--  par type de rivet. Le schema MySQL de ce fork est reste a une version
--  anterieure a cette scission : ni la colonne, ni la contrainte unique
--  composite n'existent (juste site_id seul, unique).
--
--  Trouve en testant pages/pdg_overview.php contre un vrai MySQL (Phase 3
--  du rattrapage main -> vps-mysql) : "Unknown column 'sr.type_rivet'".
--
--  Les lignes existantes deviennent 'gonflable' (meme defaut que
--  PostgreSQL) — aucune perte, juste l'ajout du deuxieme type qui
--  n'existait pas encore pour ces sites.
-- ============================================================

ALTER TABLE op_stock_rivets
    ADD COLUMN type_rivet VARCHAR(20) NOT NULL DEFAULT 'gonflable' AFTER site_id;

ALTER TABLE op_stock_rivets DROP INDEX uq_site;
ALTER TABLE op_stock_rivets ADD UNIQUE KEY op_stock_rivets_uq_site_type (site_id, type_rivet);
