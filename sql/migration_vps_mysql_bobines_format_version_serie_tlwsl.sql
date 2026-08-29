-- ============================================================
--  Rattrapage main -> vps-mysql (2026-08-29) : scinde le libelle des
--  types de bobines en format/version (colonnes independantes,
--  cherchables/filtrables), et corrige les bobines mal etiquetees
--  'T'/'W' au lieu de 'TL'/'WSL' — meme bug de derivation retrouve
--  cote MySQL (op_bobines.serie char(1) : 28 lignes 'T', 139 lignes
--  'W', toutes avec un type_code TL%/WSL%), memes correctifs que la
--  migration Postgres equivalente (sql/migration_types_bobines_format_
--  version.sql + sql/migration_op_bobines_serie_tl_wsl.sql).
-- ============================================================

-- 1. format/version sur op_types_bobines (serie deja char(4), assez
--    large pour 'TL'/'WSL' — pas de ALTER necessaire sur cette table).
ALTER TABLE op_types_bobines
    ADD COLUMN format  VARCHAR(30) NULL AFTER serie,
    ADD COLUMN version VARCHAR(40) NULL AFTER format;

UPDATE op_types_bobines SET
    format = CASE TRIM(serie)
        WHEN 'A'   THEN 'Auto'
        WHEN 'B'   THEN 'Carre'
        WHEN 'C'   THEN 'Moto'
        WHEN 'D'   THEN 'MotoII'
        WHEN 'TL'  THEN 'Reservoir'
        WHEN 'WSL' THEN 'Pare-brise'
    END,
    version = CASE RIGHT(code, 1)
        WHEN '1' THEN 'Privee'
        WHEN '2' THEN 'Transport Publique'
        WHEN '3' THEN 'Institution Internationale'
        WHEN '4' THEN 'Diplomatique'
        WHEN '5' THEN 'Gouvernementale'
        WHEN '6' THEN 'Temporaire'
    END;

ALTER TABLE op_types_bobines
    MODIFY COLUMN format  VARCHAR(30) NOT NULL,
    MODIFY COLUMN version VARCHAR(40) NOT NULL;

-- 2. op_bobines.serie elargi (char(1) -> varchar(4)) + correction des
--    167 lignes mal etiquetees.
ALTER TABLE op_bobines MODIFY COLUMN serie VARCHAR(4) NOT NULL;

UPDATE op_bobines SET serie = 'TL'  WHERE serie = 'T' AND type_code LIKE 'TL%';
UPDATE op_bobines SET serie = 'WSL' WHERE serie = 'W' AND type_code LIKE 'WSL%';
