-- ============================================================
--  Migration : profils RAF et DAF (compatible PostgreSQL / Neon)
--  Exécuter une seule fois — idempotent
-- ============================================================

-- 1. Ajouter les rôles ERP
INSERT INTO roles (nom, slug, description, created_at)
VALUES
    ('Responsable Administratif et Financier', 'raf', 'Validation administrative et financière des demandes internes — étape RAF du circuit', NOW()),
    ('Directeur Administratif et Financier',   'daf', 'Validation DG des demandes financières — étape DAF du circuit', NOW())
ON CONFLICT (slug) DO NOTHING;

-- 2. Permissions de base : lecture rapports + export
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, 1, 0, 0, 0, 1
FROM roles r, (VALUES ('rapports'),('equipements'),('consommables')) AS m(module)
WHERE r.slug IN ('raf','daf')
ON CONFLICT (role_id, module) DO NOTHING;
