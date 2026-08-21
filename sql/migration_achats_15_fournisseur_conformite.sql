-- ============================================================
--  Migration : dossier de conformité fournisseur
--  Compatible PostgreSQL / Neon — idempotente (ADD COLUMN IF NOT EXISTS).
--
--  Ajoute au référentiel fournisseurs (pages/achats/param_fournisseurs.php) :
--    - les numéros RCCM / DFE / RIB + un champ coordonnées complémentaires,
--    - les 7 pièces justificatives du dossier fournisseur (RCCM, IDU, DFE,
--      ARF, attestation CNPS, RIB, PIRL), stockées comme les autres pièces
--      jointes du module (nom de fichier, cf. includes/upload.php).
--  2026-08
-- ============================================================
BEGIN;

ALTER TABLE fournisseurs ADD COLUMN IF NOT EXISTS numero_rccm  varchar(100) DEFAULT NULL;
ALTER TABLE fournisseurs ADD COLUMN IF NOT EXISTS numero_dfe   varchar(100) DEFAULT NULL;
ALTER TABLE fournisseurs ADD COLUMN IF NOT EXISTS numero_rib   varchar(100) DEFAULT NULL;
ALTER TABLE fournisseurs ADD COLUMN IF NOT EXISTS coordonnees  text         DEFAULT NULL;

-- Pièces jointes du dossier fournisseur — noms de fichiers, cf. upload.php
-- (uploads/fournisseurs/). RCCM, DFE, RIB et PIRL sont obligatoires côté
-- application (param_fournisseurs.php) ; IDU, ARF et attestation CNPS sont
-- facultatifs (CNPS "lorsque applicable").
ALTER TABLE fournisseurs ADD COLUMN IF NOT EXISTS doc_rccm     varchar(255) DEFAULT NULL;
ALTER TABLE fournisseurs ADD COLUMN IF NOT EXISTS doc_idu      varchar(255) DEFAULT NULL;
ALTER TABLE fournisseurs ADD COLUMN IF NOT EXISTS doc_dfe      varchar(255) DEFAULT NULL;
ALTER TABLE fournisseurs ADD COLUMN IF NOT EXISTS doc_arf      varchar(255) DEFAULT NULL;
ALTER TABLE fournisseurs ADD COLUMN IF NOT EXISTS doc_cnps     varchar(255) DEFAULT NULL;
ALTER TABLE fournisseurs ADD COLUMN IF NOT EXISTS doc_rib      varchar(255) DEFAULT NULL;
ALTER TABLE fournisseurs ADD COLUMN IF NOT EXISTS doc_pirl     varchar(255) DEFAULT NULL;

COMMIT;
