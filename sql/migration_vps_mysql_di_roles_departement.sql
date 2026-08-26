-- ============================================================
--  sql/migration_vps_mysql_di_roles_departement.sql
-- ============================================================
--
--  di_roles.departement_id existe sur PostgreSQL (main) mais pas sur ce
--  fork MySQL — colonne ajoutee a main apres la derniere synchronisation
--  de sql/demandes_internes_mysql.sql. Utilisee par ach_a_viser()
--  (includes/achats.php) pour determiner si un utilisateur porte un role
--  de circuit lie a un departement.
--
--  Trouve en testant pages/pdg_overview.php contre un vrai MySQL (Phase 3
--  du rattrapage main -> vps-mysql) : "Unknown column 'dr.departement_id'".
-- ============================================================

ALTER TABLE di_roles
    ADD COLUMN departement_id INT UNSIGNED NULL AFTER ordre,
    ADD CONSTRAINT di_roles_departement_id_fkey FOREIGN KEY (departement_id) REFERENCES departements(id) ON DELETE SET NULL;
