-- ============================================================
--  sql/migration_vps_mysql_equipements_departement_statut.sql
-- ============================================================
--
--  Deux ecarts trouves en testant pages/achats/stock_departements.php
--  contre un vrai MySQL (Phase 3 du rattrapage main -> vps-mysql) :
--
--  1. equipements.departement_id n'existe pas sur ce fork MySQL —
--     colonne PostgreSQL (main) utilisee par le workflow d'affectation
--     d'equipements (cf. equipement_affectations, ajoutee en Phase 2).
--
--  2. equipements.statut_stock est un ENUM MySQL limite a
--     ('affecte','en_stock') alors que PostgreSQL le laisse en TEXT avec
--     un CHECK bien plus large : 'affecte','en_stock','hs',
--     'en_attente_affectation','affectation_en_cours','en_transit'
--     (le workflow d'affectation en a besoin). Meme choix que pour
--     sites.type (migration_vps_mysql_sites_type_magasin.sql) : repasser
--     en VARCHAR libre plutot qu'elargir l'ENUM, pour matcher PostgreSQL
--     et eviter de retomber dans le meme piege a la prochaine valeur
--     ajoutee cote main.
-- ============================================================

ALTER TABLE equipements
    ADD COLUMN departement_id INT UNSIGNED NULL AFTER site_id,
    ADD CONSTRAINT equipements_departement_id_fkey FOREIGN KEY (departement_id) REFERENCES departements(id) ON DELETE SET NULL;

ALTER TABLE equipements MODIFY COLUMN statut_stock VARCHAR(30) NOT NULL DEFAULT 'affecte';
