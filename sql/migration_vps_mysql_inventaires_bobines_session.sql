-- ============================================================
--  sql/migration_vps_mysql_inventaires_bobines_session.sql
-- ============================================================
--
--  inventaires_bobines.session_id n'existe pas sur ce fork MySQL — lien
--  vers inventaire_sessions (ajoutee en Phase 2, sql/achats_mysql.sql),
--  utilise par pages/inventaire_sessions.php pour rattacher un inventaire
--  a la session multi-sites qui l'a genere.
--
--  Trouve en testant cette page contre un vrai MySQL (Phase 3 du
--  rattrapage main -> vps-mysql) : "Unknown column 'ib.session_id'".
-- ============================================================

ALTER TABLE inventaires_bobines
    ADD COLUMN session_id INT UNSIGNED NULL AFTER site_id,
    ADD CONSTRAINT inventaires_bobines_session_id_fkey FOREIGN KEY (session_id) REFERENCES inventaire_sessions(id),
    ADD KEY inventaires_bobines_session_idx (session_id);
