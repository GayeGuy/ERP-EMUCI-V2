-- ============================================================
--  Migration : module Achats — stock par département (traçabilité)
--  Compatible PostgreSQL / Neon — idempotente.
--  Dépend de migration_achats_01_schema.sql (feb, feb_suivi) et de la
--  table articles/departements du schéma de base.
--  2026-08
-- ============================================================
BEGIN;

-- ══════════════════════════════════════════════════════════════
--  1. stock_departement — un site (souvent le siège) héberge plusieurs
--     départements ; stock_site seul ne dit pas lequel détient quel
--     équipement reçu. Purement informatif : ne remplace ni n'influence
--     ach_stock_magasin(), qui reste la seule source de vérité pour
--     l'arbitrage stock/achat (ce stock-ci est déjà affecté à un
--     département, donc pas redisponible pour couvrir une autre demande).
--     Même forme que stock_site (article_id, clé secondaire, quantite).
-- ══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS stock_departement (
    id             serial PRIMARY KEY,
    article_id     integer NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    departement_id integer NOT NULL REFERENCES departements(id) ON DELETE CASCADE,
    quantite       integer NOT NULL DEFAULT 0,
    updated_at     timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT stock_departement_uk_article_dept UNIQUE (article_id, departement_id)
);

COMMIT;
