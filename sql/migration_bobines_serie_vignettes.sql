-- ============================================================
--  migration_bobines_serie_vignettes.sql  (PostgreSQL, rejouable)
--  Les imports (import_bobines.php, import_emuci.php) créaient les
--  vignettes avec op_bobines.serie = 'T' ou 'W' (premier caractère du
--  format), alors que la page Vignettes et les commandes filtrent sur
--  'TL' / 'WSL' : ces rouleaux n'apparaissaient nulle part.
--  On réaligne la série sur le catalogue op_types_bobines.
-- ============================================================
-- Colonne déclarée character(1) sur les anciennes bases : trop courte pour
-- 'TL' / 'WSL' (même correctif que migration_op_bobines_serie_tl_wsl.sql).
ALTER TABLE op_bobines ALTER COLUMN serie TYPE VARCHAR(4);

UPDATE op_bobines b
   SET serie = TRIM(tb.serie)
  FROM op_types_bobines tb
 WHERE tb.code = b.type_code
   AND b.serie <> TRIM(tb.serie);

-- Repli pour un type absent du catalogue.
UPDATE op_bobines SET serie = 'WSL' WHERE serie = 'W' AND UPPER(type_code) LIKE 'WSL%';
UPDATE op_bobines SET serie = 'TL'  WHERE serie = 'T' AND UPPER(type_code) LIKE 'TL%';
