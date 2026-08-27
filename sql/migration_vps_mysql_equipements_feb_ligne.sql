-- ============================================================
--  sql/migration_vps_mysql_equipements_feb_ligne.sql
-- ============================================================
--
--  equipements.feb_ligne_id n'existe pas sur ce fork MySQL — colonne
--  PostgreSQL (main) qui relie un equipement a la ligne FEB (DAI) dont
--  il est issu, utilisee par pages/achats/equipements_attente.php.
--
--  Trouve en testant cette page contre un vrai MySQL (Phase 3 du
--  rattrapage main -> vps-mysql) : "Unknown column 'e.feb_ligne_id'".
-- ============================================================

ALTER TABLE equipements
    ADD COLUMN feb_ligne_id INT UNSIGNED NULL AFTER nomenclature_id,
    ADD CONSTRAINT equipements_feb_ligne_id_fkey FOREIGN KEY (feb_ligne_id) REFERENCES feb_lignes(id) ON DELETE SET NULL,
    ADD KEY equipements_feb_ligne_idx (feb_ligne_id);
