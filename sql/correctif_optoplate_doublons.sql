-- ============================================================
--  Correctif OptoPlate : doublons de N°Consommable + index unique
--  PostgreSQL (Neon). Rejouable. 2026-10-16
-- ============================================================
--  Symptôme : l'import OptoPlate s'arrête avec
--    « there is no unique or exclusion constraint matching the ON CONFLICT
--      specification » (SQLSTATE 42P10), ou avec le message « migration
--    incomplète » de l'écran Import EMUCI.
--
--  Cause : migration_optoplate_desactivations.sql a bien ajouté les colonnes,
--  mais son CREATE UNIQUE INDEX idx_optoplate_num_consommable a échoué, parce
--  que import_optoplate contenait déjà des plaques en double (même
--  N°Consommable, importées dans des fichiers de jours différents, du temps où
--  chaque import effaçait/réinsérait seulement sa propre journée).
--
--  Ce script : (1) garde, pour chaque N°Consommable, la ligne la plus récente
--  (date_import puis id les plus grands) ; (2) copie les autres dans
--  import_optoplate_doublons_sauvegarde avant de les supprimer ; (3) crée
--  l'index. Le tout dans UNE transaction : si la création de l'index échoue,
--  rien n'est supprimé.
--
--  ÉTAPE 0 — À LIRE AVANT (lecture seule), pour savoir ce qui sera supprimé :
--
--    SELECT COUNT(*) AS plaques_en_double
--      FROM (SELECT num_consommable FROM import_optoplate
--             WHERE num_consommable IS NOT NULL
--             GROUP BY num_consommable HAVING COUNT(*) > 1) d;
--
--    SELECT indexdef FROM pg_indexes
--     WHERE tablename = 'import_optoplate' AND indexname = 'idx_optoplate_num_consommable';
--      -- aucune ligne = l'index manque bien
-- ============================================================

BEGIN;

-- Copie de sécurité des lignes qui vont disparaître (structure identique à la table)
CREATE TABLE IF NOT EXISTS import_optoplate_doublons_sauvegarde (LIKE import_optoplate);

CREATE TEMP TABLE _optoplate_a_supprimer ON COMMIT DROP AS
SELECT id
  FROM (
        SELECT id,
               ROW_NUMBER() OVER (PARTITION BY num_consommable
                                  ORDER BY date_import DESC, id DESC) AS rang
          FROM import_optoplate
         WHERE num_consommable IS NOT NULL
       ) t
 WHERE rang > 1;

INSERT INTO import_optoplate_doublons_sauvegarde
SELECT o.* FROM import_optoplate o JOIN _optoplate_a_supprimer s ON s.id = o.id;

DELETE FROM import_optoplate o USING _optoplate_a_supprimer s WHERE s.id = o.id;

CREATE UNIQUE INDEX IF NOT EXISTS idx_optoplate_num_consommable
    ON import_optoplate (num_consommable)
    WHERE num_consommable IS NOT NULL;

COMMIT;

-- Contrôle après coup (doit renvoyer une ligne, avec CREATE UNIQUE INDEX) :
--   SELECT indexdef FROM pg_indexes WHERE indexname = 'idx_optoplate_num_consommable';
