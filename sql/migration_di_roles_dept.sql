-- ============================================================
--  Migration : département lié à un rôle de validation demandes
--  Compatible PostgreSQL (Neon/Render) — idempotent
-- ============================================================

-- Ajout de la colonne departement_id sur di_roles
ALTER TABLE di_roles ADD COLUMN IF NOT EXISTS departement_id INTEGER REFERENCES departements(id) ON DELETE SET NULL;
