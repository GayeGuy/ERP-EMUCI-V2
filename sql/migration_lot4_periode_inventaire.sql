-- ============================================================
--  Type de période sur les sessions d'inventaire
-- ============================================================
--
--  Une session d'inventaire suit toujours l'une de quatre périodicités
--  standard : mensuelle, trimestrielle, semestrielle, annuelle. Jusqu'ici
--  seules des dates de début/fin libres existaient (migration_lot_sessions_
--  inventaire.sql) — l'admin pouvait saisir n'importe quel intervalle. La
--  périodicité est maintenant un choix explicite, et la date de fin s'en
--  déduit côté applicatif (elle n'est plus saisie librement).
--
--  Idempotent.
-- ============================================================
BEGIN;

ALTER TABLE inventaire_sessions
  ADD COLUMN IF NOT EXISTS type_periode varchar(20) NOT NULL DEFAULT 'mensuel';

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'inventaire_sessions_type_periode_chk'
  ) THEN
    ALTER TABLE inventaire_sessions
      ADD CONSTRAINT inventaire_sessions_type_periode_chk
      CHECK (type_periode IN ('mensuel','trimestriel','semestriel','annuel'));
  END IF;
END $$;

COMMIT;
