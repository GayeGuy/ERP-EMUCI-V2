-- ============================================================
--  Migration : module Achats — dérogation fournisseur par ligne
--  Compatible PostgreSQL / Neon — idempotente.
--  Dépend de migration_achats_01_schema.sql (feb_lignes créée avant).
--
--  Quand l'offre retenue d'un lot est reportée sur ses lignes
--  (ach_retenir_offre_lot(), pages/achats/feb_traitement.php), une ligne
--  modifiée à la main par l'acheteur ne doit pas être réécrite si le lot
--  change ensuite d'offre retenue (RG-09 : le fournisseur retenu peut
--  différer d'une ligne à l'autre). fournisseur_derogation marque cette
--  exception ligne par ligne — sans elle, le report en masse écraserait
--  systématiquement tout choix manuel.
--  2026-08
-- ============================================================
BEGIN;

ALTER TABLE feb_lignes ADD COLUMN IF NOT EXISTS fournisseur_derogation smallint NOT NULL DEFAULT 0;

COMMIT;
