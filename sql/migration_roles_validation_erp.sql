-- ============================================================
--  Migration : validation des demandes internes pilotée par le rôle ERP
--  (Administration → Permissions), et non plus par la matrice « Rôles valideurs ».
--  Ajoute le rôle ERP « Directeur Général » (étape DG des circuits).
--  Variante MySQL — idempotent (INSERT IGNORE).
-- ============================================================

-- 1. Rôle ERP Directeur Général (incarne l'étape 'dg' des circuits de demandes)
INSERT IGNORE INTO roles (nom, slug, description, created_at)
VALUES ('Directeur General', 'directeur_general',
        'Direction Generale — validation finale des demandes internes (etape DG du circuit)', NOW());

-- 2. Permissions de base : lecture + export des rapports
INSERT IGNORE INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, 1, 0, 0, 0, 1
FROM roles r, (VALUES ROW('rapports')) AS m(module)
WHERE r.slug = 'directeur_general';

-- ------------------------------------------------------------
-- NB : plus aucune autre table à modifier. Le mapping rôle ERP → étape est en code
-- (di_user_roles() dans includes/demandes.php) :
--   raf→raf · daf→daf · gestionnaire→gestionnaire · support_it/superviseur_it/maintenance_info→it
--   directeur_general→dg · superadmin/admin→toutes · n1 = N+1 du département (user_departements).
-- La table di_user_roles reste lue en filet de sécurité (droits manuels historiques) ; pour un
-- basculement 100 % rôles ERP, exécuter :  TRUNCATE di_user_roles;
-- ------------------------------------------------------------
