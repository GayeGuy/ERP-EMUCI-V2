-- ============================================================
--  Migration : module Achats — arbitrage stock/achat et bascule FEB
--  Compatible PostgreSQL / Neon — idempotente.
--  Dépend de migration_achats_01_schema.sql (tables feb / feb_lignes /
--  commandes créées avant).
--
--  1. commandes.feb_id : lien traçable FEB → commande interne, posé par
--     ach_basculer_vers_commande() (includes/achats.php). Nullable — la
--     grande majorité des commandes restent créées manuellement, sans FEB
--     d'origine. ON DELETE SET NULL : la suppression d'une FEB (cas rare,
--     hors flux normal) ne doit jamais entraîner la suppression d'une
--     commande déjà engagée dans son propre circuit de validation.
--  2. feb_lignes.arbitrage : décision « acheter » ou « servir sur stock »
--     prise ligne par ligne par l'acheteur (pages/achats/feb_traitement.php),
--     jamais pour la FEB entière — trois articles en stock et deux à
--     acheter est le cas normal. Défaut 'achat' : une ligne en saisie
--     libre (sans article_id) n'est jamais arbitrable et reste par nature
--     sur ce choix par défaut.
--  2026-08
-- ============================================================
BEGIN;

ALTER TABLE commandes ADD COLUMN IF NOT EXISTS feb_id integer REFERENCES feb(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS commandes_feb_idx ON commandes (feb_id);

ALTER TABLE feb_lignes ADD COLUMN IF NOT EXISTS arbitrage varchar(10) NOT NULL DEFAULT 'achat';

-- Contrainte posée séparément (ADD COLUMN ne prend pas IF NOT EXISTS sur un
-- CHECK) : ré-exécuter la migration ne doit pas échouer sur une contrainte
-- déjà présente.
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'feb_lignes_arbitrage_check'
    ) THEN
        ALTER TABLE feb_lignes
            ADD CONSTRAINT feb_lignes_arbitrage_check CHECK (arbitrage IN ('achat', 'stock'));
    END IF;
END $$;

COMMIT;
