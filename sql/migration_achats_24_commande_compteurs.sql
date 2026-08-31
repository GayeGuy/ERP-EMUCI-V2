BEGIN;

-- pages/commandes.php générait le numéro par tirage aléatoire
-- (rand(1,9999)) sur l'index unique commandes.numero_commande : une
-- collision fait échouer l'INSERT sur une erreur PostgreSQL brute (23505),
-- vue par l'utilisateur comme un plantage sans explication (carte Trello
-- #28, 15/08).
--
-- Même motif que feb_compteurs (migration_achats_01_schema.sql) : verrou
-- de ligne (SELECT ... FOR UPDATE) avant incrément, dans includes/achats.php
-- ach_numero_commande(). Compteur par jour plutôt que par exercice, car le
-- numéro CMD-Ymd-XXXX se remet à zéro chaque jour.
CREATE TABLE IF NOT EXISTS commande_compteurs (
    jour           date PRIMARY KEY,
    dernier_numero integer NOT NULL DEFAULT 0
);

COMMIT;
