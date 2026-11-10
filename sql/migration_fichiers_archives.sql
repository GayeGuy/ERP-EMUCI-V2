-- ============================================================
--  Archivage en base des fichiers déposés — PostgreSQL
-- ============================================================
--
--  Pièces jointes des FEB, bons de livraison et de transfert des
--  réceptions, fiches signées, fiches de validation et documents
--  fournisseurs étaient rangés dans uploads/ : effacé à chaque déploiement
--  Render, et lisible sans connexion par son adresse. Ils sont désormais
--  copiés ici (includes/upload.php) et servis par pages/fichier.php.
--  Les fichiers encore sur le disque s'importent depuis Stock → Archives
--  des BL → « Importer en base » (administrateur).
--
--  Rejouable sans effet de bord.

CREATE TABLE IF NOT EXISTS fichiers_archives (
    type      varchar(20)  NOT NULL,
    nom       varchar(255) NOT NULL,
    taille    integer      NOT NULL,
    contenu   bytea        NOT NULL,
    depose_le timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (type, nom)
);
