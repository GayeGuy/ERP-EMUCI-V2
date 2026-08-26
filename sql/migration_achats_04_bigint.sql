-- ============================================================
--  Migration : module Achats — bascule bigint + pièces jointes FEB
--  Compatible PostgreSQL / Neon — idempotente.
--  Dépend de migration_achats_01_schema.sql (tables créées avant).
--
--  1. Les colonnes monétaires du module Achats sont en integer, plafonnées
--     à 2 147 483 647 FCFA. Une enveloppe budgétaire annuelle peut le
--     dépasser (PostgreSQL répond alors par une erreur dure, pas un
--     dépassement silencieux). Bascule en bigint sur les sept colonnes
--     concernées, avant que des données réelles ne s'accumulent dessus —
--     dix minutes de migration maintenant plutôt qu'une reprise pénible
--     plus tard sur une table remplie.
--  2. feb.numero passe en NULLable : la spécification de l'écran de saisie
--     (Bloc 3/4) est explicite — le numéro n'est attribué qu'à la
--     soumission, jamais à la création du brouillon, pour ne pas trouer la
--     séquence avec des brouillons abandonnés. La colonne avait été posée
--     NOT NULL UNIQUE lundi, avant que cet écran ne précise la règle.
--     PostgreSQL autorise plusieurs NULL sous une contrainte UNIQUE (chaque
--     NULL est distinct) : plusieurs brouillons simultanés restent donc
--     possibles sans collision.
--  3. feb_pieces_jointes : absente des douze tables de lundi ; la FEB en
--     demande plusieurs (Bloc 1 de ce lot).
--  2026-08
-- ============================================================
BEGIN;

-- ══════════════════════════════════════════════════════════════
--  1. Colonnes monétaires : integer → bigint (7 colonnes)
--     ALTER ... TYPE bigint est un élargissement sans perte, PostgreSQL
--     le fait par cast implicite : pas de USING nécessaire.
-- ══════════════════════════════════════════════════════════════
ALTER TABLE feb              ALTER COLUMN montant_total TYPE bigint;
ALTER TABLE feb_lignes       ALTER COLUMN montant_ttc   TYPE bigint;
ALTER TABLE feb_offres       ALTER COLUMN montant_ttc   TYPE bigint;
ALTER TABLE feb_offres       ALTER COLUMN prix_initial  TYPE bigint;
ALTER TABLE lignes_budgetaires ALTER COLUMN enveloppe   TYPE bigint;
ALTER TABLE achat_paliers    ALTER COLUMN borne_min     TYPE bigint;
ALTER TABLE achat_paliers    ALTER COLUMN borne_max     TYPE bigint;

-- ══════════════════════════════════════════════════════════════
--  2. feb.numero devient NULLable
-- ══════════════════════════════════════════════════════════════
ALTER TABLE feb ALTER COLUMN numero DROP NOT NULL;

-- ══════════════════════════════════════════════════════════════
--  3. Pièces jointes FEB
-- ══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS feb_pieces_jointes (
    id           SERIAL PRIMARY KEY,
    feb_id       INTEGER NOT NULL REFERENCES feb(id) ON DELETE CASCADE,
    fichier      varchar(255) NOT NULL,   -- nom généré non devinable (cf. includes/upload.php)
    nom_origine  varchar(255) NOT NULL,   -- nom d'origine tel que déposé par l'utilisateur
    taille       integer,                 -- octets — plafonné à 10 Mo par upload_document(), tient en integer
    mime         varchar(100),
    deposee_par  INTEGER REFERENCES users(id) ON DELETE SET NULL,
    deposee_le   timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS feb_pieces_jointes_feb_idx ON feb_pieces_jointes(feb_id);

COMMIT;
