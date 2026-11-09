-- ============================================================
--  Archivage des bons de livraison signés dans la base — PostgreSQL
-- ============================================================
--
--  Le BL signé joint par le site à la réception d'une commande était rangé
--  dans uploads/bl/. Sur Render, ce dossier est effacé à chaque déploiement :
--  les BL disparaissaient. Il est désormais rangé ici (cf.
--  includes/commande_bl.php) et sauvegardé avec la base. Les BL encore sur
--  le disque s'importent depuis Stock → Archives des BL (administrateur).
--
--  Rejouable sans effet de bord.

CREATE TABLE IF NOT EXISTS commande_bl (
    commande_id integer      PRIMARY KEY REFERENCES commandes (id) ON DELETE CASCADE,
    nom         varchar(255),
    taille      integer      NOT NULL,
    sha256      char(64)     NOT NULL,
    contenu     bytea        NOT NULL,
    depose_par  integer,
    depose_le   timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
