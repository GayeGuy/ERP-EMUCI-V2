-- ============================================================
--  OptoPlate : date d'impression des plaques (films / PMMA).
--  Idempotente. 2026-10-21
-- ============================================================
--  Une plaque « reserved » est imprimée mais pas encore posée : elle
--  consomme son film (et son PMMA) le jour de l'impression, mais ne compte
--  comme engin posé que le jour où elle passe « in_use ». L'import tenant
--  une seule ligne par film (num_consommable, mise à jour dans le temps),
--  on fige ici le jour d'impression à la première fois que la plaque est
--  vue, sans jamais le réécrire :
--   - vue en « reserved » : jour de la « Date dernière action » OptoPlate ;
--   - vue directement posée : jour de la date d'installation.
--  Tant que cette migration n'est pas passée, les films restent comptés
--  sur les poses du jour (comportement antérieur, sans erreur).
-- ============================================================

ALTER TABLE import_optoplate
    ADD COLUMN IF NOT EXISTS date_derniere_action timestamp DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS date_impression      date      DEFAULT NULL;

COMMENT ON COLUMN import_optoplate.date_derniere_action IS 'Date dernière action OptoPlate (mise à jour à chaque import).';
COMMENT ON COLUMN import_optoplate.date_impression IS 'Jour d''impression du film : fixé au premier import qui voit la plaque, jamais réécrit.';

-- Reprise de l'existant : plaques posées -> jour de pose ; plaques encore
-- « reserved » -> date de l'import qui les a rapportées.
UPDATE import_optoplate
   SET date_impression = CASE WHEN statut_plaque = 'reserved' OR date_installation IS NULL
                              THEN date_import ELSE date_installation::date END
 WHERE date_impression IS NULL;

CREATE INDEX IF NOT EXISTS idx_optoplate_site_impression
    ON import_optoplate (site_id, date_impression);
