-- ============================================================
--  Migration : visa du supérieur hiérarchique (N+1) sur la FEB
--  Compatible PostgreSQL / Neon — idempotente.
--
--  Le modèle papier (FEB EMUCI.xlsx, feuille FEB DEMANDEUR) porte deux
--  cases de visa : DEMANDEUR et SUPERIEUR HIERARCHIQUE. L'application
--  n'implémentait que le circuit aval, construit sur le montant seul, et
--  seize rôles sur dix-sept pouvaient engager l'enveloppe d'un département
--  sans que personne ne l'endosse.
--
--  Le N+1 est résolu au lancement de la validation, pas à la volée : le
--  circuit est figé à cet instant (RG-11), et un changement de responsable
--  en cours de route ne doit pas déplacer une signature déjà attendue.
--  C'est le même principe que workflow_snapshot, appliqué à la personne.
--
--  La désignation elle-même vit déjà dans user_departements.is_n1 : rien
--  à créer de ce côté.
-- ============================================================
BEGIN;

ALTER TABLE feb ADD COLUMN IF NOT EXISTS n1_user_id integer;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'feb_n1_user_id_fkey'
    ) THEN
        ALTER TABLE feb
            ADD CONSTRAINT feb_n1_user_id_fkey
            FOREIGN KEY (n1_user_id) REFERENCES users(id);
    END IF;
END $$;

COMMENT ON COLUMN feb.n1_user_id IS
    'Supérieur hiérarchique résolu au lancement de la validation (user_departements.is_n1 du département de la FEB). NULL si le département n''en a pas, ou si le demandeur est lui-même le N+1 : l''étape n1 est alors absente du circuit.';

COMMIT;
