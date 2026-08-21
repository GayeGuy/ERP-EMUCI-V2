-- ============================================================
--  Migration : module Achats — réception (J8)
--  Compatible PostgreSQL / Neon — idempotente.
--  Dépend de migration_achats_01_schema.sql (feb_receptions, feb_suivi
--  créées avant) et de migration_achats_03_permissions.sql.
--  2026-08
-- ============================================================
BEGIN;

-- ══════════════════════════════════════════════════════════════
--  1. feb_receptions.observation — commentaire libre sur l'acte de
--     réception, distinct de motif_ecart (réservé à la clôture d'un
--     reliquat, Bloc 3 : « rupture fournisseur », « commande annulée »…,
--     jamais une remarque de livraison comme « colis endommagé »).
-- ══════════════════════════════════════════════════════════════
ALTER TABLE feb_receptions ADD COLUMN IF NOT EXISTS observation text DEFAULT NULL;

-- ══════════════════════════════════════════════════════════════
--  2. feb_suivi.cloture_reliquat — une ligne partiellement reçue dont le
--     solde n'arrivera jamais (rupture fournisseur, annulation partielle)
--     est close explicitement (Bloc 3, point 16) sans jamais atteindre
--     quantite_recue = quantite_commandee. ach_statut_suivi_calcule() s'en
--     sert pour distinguer une ligne réceptionnée d'une ligne soldée de
--     force — les deux affichent "Réceptionnée", mais seule la seconde
--     porte ce drapeau (visible sur l'écart figé de sa fiche).
-- ══════════════════════════════════════════════════════════════
ALTER TABLE feb_suivi ADD COLUMN IF NOT EXISTS cloture_reliquat smallint NOT NULL DEFAULT 0;

-- ══════════════════════════════════════════════════════════════
--  3. Droit de réception pour les coordinateurs de site — migration_achats_03
--     n'avait accordé achats_suivi qu'au gestionnaire_stock (le magasin) ;
--     pages/achats/receptions.php est réservé aux deux (Bloc 1, point 3).
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, 'achats_suivi', 1, 1, 0, 0, 0
FROM roles r
WHERE r.slug = 'coordinateur_site'
ON CONFLICT (role_id, module) DO UPDATE SET can_read = 1, can_create = 1;

COMMIT;
