-- ============================================================
--  Autorisation admin avant modification par le coordinateur
-- ============================================================
--
--  inventaire_corrections (migration_lot5) ne portait qu'un seul sens :
--  l'admin/responsable de session demande au site de corriger une ligne,
--  qui se déverrouille aussitôt. Le coordinateur ne doit pas disposer du
--  même raccourci — il doit d'abord demander l'autorisation de l'admin,
--  qui déverrouille la ligne seulement s'il l'accorde.
--
--  - type : 'demande_site' (admin -> site, déverrouille aussitôt, flux
--    existant) | 'demande_autorisation' (site -> admin, ne déverrouille
--    qu'une fois autorisée)
--  - autorise_par / autorise_at : qui a accordé l'autorisation et quand
--    (distinct de traite_par/traite_at qui closent définitivement la
--    demande, par réponse ou par refus)
--  - statut prend désormais aussi 'autorise' (accordée, en attente de
--    correction par le site) et 'refuse' (demande d'autorisation rejetée)
--
--  Idempotent.
-- ============================================================
BEGIN;

ALTER TABLE inventaire_corrections
  ADD COLUMN IF NOT EXISTS type varchar(20) NOT NULL DEFAULT 'demande_site';

ALTER TABLE inventaire_corrections
  ADD COLUMN IF NOT EXISTS autorise_par integer DEFAULT NULL REFERENCES users(id);

ALTER TABLE inventaire_corrections
  ADD COLUMN IF NOT EXISTS autorise_at timestamp DEFAULT NULL;

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'inventaire_corrections_type_chk'
  ) THEN
    ALTER TABLE inventaire_corrections
      ADD CONSTRAINT inventaire_corrections_type_chk
      CHECK (type IN ('demande_site','demande_autorisation'));
  END IF;
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'inventaire_corrections_statut_chk'
  ) THEN
    ALTER TABLE inventaire_corrections
      ADD CONSTRAINT inventaire_corrections_statut_chk
      CHECK (statut IN ('en_attente','autorise','refuse','traite'));
  END IF;
END $$;

COMMIT;
