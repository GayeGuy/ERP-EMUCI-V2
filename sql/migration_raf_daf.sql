-- ============================================================
--  Migration : profils RAF et DAF (variante MySQL)
--  Exécuter une seule fois — idempotent (INSERT IGNORE).
-- ============================================================

-- 1. Ajouter les rôles ERP
INSERT IGNORE INTO roles (nom, slug, description, created_at)
VALUES
    ('Responsable Administratif et Financier', 'raf', 'Validation administrative et financière des demandes internes — étape RAF du circuit', NOW()),
    ('Directeur Administratif et Financier',   'daf', 'Validation DG des demandes financières — étape DAF du circuit', NOW());

-- 2. Permissions de base : lecture rapports + export
INSERT IGNORE INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, 1, 0, 0, 0, 1
FROM roles r, (VALUES ROW('rapports'),ROW('equipements'),ROW('consommables')) AS m(module)
WHERE r.slug IN ('raf','daf');
