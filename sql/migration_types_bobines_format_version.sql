-- ============================================================
--  Scinde le libellé de op_types_bobines ("Format Auto, version Privée")
--  en deux colonnes indépendamment cherchables/filtrables : format
--  (Auto/Carré/Moto/MotoII/Réservoir/Pare-brise) et version
--  (Privée/Transport Publique/Institution Internationale/Diplomatique/
--  Gouvernementale/Temporaire). Demande de l'utilisateur, 2026-08-28.
--
--  Mapping déduit des 28 lignes existantes : le format correspond 1:1
--  à la colonne serie déjà en place, la version au dernier chiffre du
--  code (1=Privée … 6=Temporaire) — vérifié constant sur toutes les
--  séries (A/B/C/D à 6 versions, TL/WSL à 2 versions seulement).
-- ============================================================
ALTER TABLE op_types_bobines
    ADD COLUMN IF NOT EXISTS format  VARCHAR(30),
    ADD COLUMN IF NOT EXISTS version VARCHAR(40);

UPDATE op_types_bobines SET
    format = CASE TRIM(serie)
        WHEN 'A'   THEN 'Auto'
        WHEN 'B'   THEN 'Carré'
        WHEN 'C'   THEN 'Moto'
        WHEN 'D'   THEN 'MotoII'
        WHEN 'TL'  THEN 'Réservoir'
        WHEN 'WSL' THEN 'Pare-brise'
    END,
    version = CASE RIGHT(code, 1)
        WHEN '1' THEN 'Privée'
        WHEN '2' THEN 'Transport Publique'
        WHEN '3' THEN 'Institution Internationale'
        WHEN '4' THEN 'Diplomatique'
        WHEN '5' THEN 'Gouvernementale'
        WHEN '6' THEN 'Temporaire'
    END;

ALTER TABLE op_types_bobines
    ALTER COLUMN format  SET NOT NULL,
    ALTER COLUMN version SET NOT NULL;
