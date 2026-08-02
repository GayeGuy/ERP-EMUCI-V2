-- ============================================================
--  Migration : permissions module ecarts_bobines (variante MySQL)
--  2026-07-31 — Accès historique des écarts pour GSB, superviseur, PDG
--  Repose sur la clé unique permissions_uq_role_module (role_id, module).
-- ============================================================
START TRANSACTION;

INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, 'ecarts_bobines', 1, 0, 0, 0, 1
FROM roles r
WHERE r.slug IN ('gestionnaire_stock_bobines', 'superviseur_operation', 'lecteur',
                 'gestionnaire_stock', 'controleur_production')
ON DUPLICATE KEY UPDATE
    can_read   = GREATEST(can_read,   VALUES(can_read)),
    can_export = GREATEST(can_export, VALUES(can_export));

COMMIT;

-- Vérification
SELECT r.slug AS role, p.module, p.can_read, p.can_export
FROM permissions p JOIN roles r ON r.id = p.role_id
WHERE p.module = 'ecarts_bobines'
ORDER BY r.slug;
