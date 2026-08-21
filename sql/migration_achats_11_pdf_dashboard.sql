-- ============================================================
--  Migration : module Achats — fiche de validation archivée,
--  seuil d'alerte de retard de validation (J9)
--  Compatible PostgreSQL / Neon — idempotente.
--  2026-08
-- ============================================================
BEGIN;

-- ══════════════════════════════════════════════════════════════
--  1. feb.fiche_validation_path — chemin du PDF de validation, généré une
--     seule fois par ach_generer_fiche_validation() au moment où ach_viser()
--     bascule la FEB en confirmee (Bloc 1). C'est la pièce opposable :
--     jamais régénérée, donc jamais recalculée depuis les données courantes
--     à chaque consultation — le chemin est la seule source de vérité une
--     fois posé.
-- ══════════════════════════════════════════════════════════════
ALTER TABLE feb ADD COLUMN IF NOT EXISTS fiche_validation_path varchar(255) DEFAULT NULL;

-- ══════════════════════════════════════════════════════════════
--  2. Seuil d'alerte "FEB en retard de validation" (Bloc 4, point 18) —
--     même principe que seuil_retard_jours (J7) : jamais codé en dur.
-- ══════════════════════════════════════════════════════════════
INSERT INTO achat_parametres (cle, valeur, libelle, type, options) VALUES
    ('seuil_retard_validation_jours', '5', 'Seuil d''alerte — FEB en attente de validation depuis (jours)', 'nombre', NULL)
ON CONFLICT (cle) DO NOTHING;

COMMIT;
