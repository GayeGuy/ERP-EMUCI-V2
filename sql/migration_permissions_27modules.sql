-- ============================================================
--  Migration : permissions complètes — 27 modules × 14 rôles
--  Colonnes : can_read, can_create, can_update, can_delete, can_export (smallint 0/1)
--  Idempotente : ON DUPLICATE KEY UPDATE sur la clé unique (role_id, module)
--  2026-07-30 — Variante MySQL.
-- ============================================================
START TRANSACTION;

-- ══════════════════════════════════════════════════════════════
--  1. ADMIN & SUPERADMIN
--     Tous droits sur les 9 nouveaux modules
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, 1, 1, 1, 1, 1
FROM roles r
CROSS JOIN (VALUES
    ROW('operations'), ROW('validation_stock'), ROW('demandes'), ROW('commandes_bobines'),
    ROW('rapports_gsb'), ROW('stock_bobines'), ROW('rapport_journalier'), ROW('departements'), ROW('affectations_it')
) AS m(module)
WHERE r.slug IN ('admin', 'superadmin')
ON DUPLICATE KEY UPDATE
    can_read=1, can_create=1, can_update=1, can_delete=1, can_export=1;


-- ══════════════════════════════════════════════════════════════
--  2. GESTIONNAIRE (id=3) — rôle legacy, accès minimal
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module               r  c  u  d  e
    ROW('demandes',         1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'gestionnaire'
ON DUPLICATE KEY UPDATE
    can_read=VALUES(can_read), can_create=VALUES(can_create),
    can_update=VALUES(can_update), can_delete=VALUES(can_delete), can_export=VALUES(can_export);


-- ══════════════════════════════════════════════════════════════
--  3. LECTEUR (id=4) — DG/PDG, lecture + export uniquement
--     Aucune modification possible sur aucun module
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module               r  c  u  d  e
    ROW('sites',            1, 0, 0, 0, 0),
    ROW('equipements',      1, 0, 0, 0, 0),
    ROW('bobines',          1, 0, 0, 0, 0),
    ROW('inventaire_bobines',1,0, 0, 0, 0),
    ROW('operations',       1, 0, 0, 0, 0),
    ROW('commandes',        1, 0, 0, 0, 0),
    ROW('rivets',           1, 0, 0, 0, 0),
    ROW('pmma',             1, 0, 0, 0, 0),
    ROW('consommables',     1, 0, 0, 0, 0),
    ROW('demandes',         1, 0, 0, 0, 0),
    ROW('rapports',         1, 0, 0, 0, 1),
    ROW('rapports_gsb',     1, 0, 0, 0, 1),
    ROW('stock_bobines',    1, 0, 0, 0, 0),
    ROW('validation_stock', 1, 0, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'lecteur'
ON DUPLICATE KEY UPDATE
    can_read=VALUES(can_read), can_create=VALUES(can_create),
    can_update=VALUES(can_update), can_delete=VALUES(can_delete), can_export=VALUES(can_export);


-- ══════════════════════════════════════════════════════════════
--  4. GESTIONNAIRE STOCK (id=5) — stock central, entrées/sorties/livraisons
--     Complète les modules manquants (a déjà : equipements, consommables,
--     receptions, rapports, sites, commandes, pmma)
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module               r  c  u  d  e
    ROW('affectations',     1, 0, 0, 0, 0),
    ROW('interventions',    1, 0, 0, 0, 0),
    ROW('bobines',          1, 1, 1, 0, 1),
    ROW('inventaire_bobines',1,1, 1, 0, 0),
    ROW('stock_bobines',    1, 0, 0, 0, 1),
    ROW('commandes_bobines',1, 0, 0, 0, 0),
    ROW('rivets',           1, 0, 0, 0, 0),
    ROW('operations',       1, 0, 0, 0, 0),
    ROW('validation_stock', 1, 0, 0, 0, 0),
    ROW('demandes',         1, 1, 0, 0, 0),
    ROW('rapports_gsb',     1, 0, 0, 0, 1),
    ROW('nomenclatures',    1, 0, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'gestionnaire_stock'
ON DUPLICATE KEY UPDATE
    can_read=VALUES(can_read), can_create=VALUES(can_create),
    can_update=VALUES(can_update), can_delete=VALUES(can_delete), can_export=VALUES(can_export);


-- ══════════════════════════════════════════════════════════════
--  5. COORDINATEUR DE SITE (id=6) — terrain, filtré sur son site
--     Complète les modules manquants (a déjà : equipements, bobines,
--     inventaire_bobines, receptions, consommables, rapports, rivets, pmma, commandes)
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module               r  c  u  d  e
    ROW('sites',            1, 0, 0, 0, 0),
    ROW('operations',       1, 1, 1, 0, 1),  -- point journalier : activité principale
    ROW('validation_stock', 1, 0, 0, 0, 0),
    ROW('stock_bobines',    1, 0, 0, 0, 0),
    ROW('commandes_bobines',1, 1, 0, 0, 0),
    ROW('point_emuci',      1, 0, 0, 0, 0),
    ROW('demandes',         1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'coordinateur_site'
ON DUPLICATE KEY UPDATE
    can_read=VALUES(can_read), can_create=VALUES(can_create),
    can_update=VALUES(can_update), can_delete=VALUES(can_delete), can_export=VALUES(can_export);


-- ══════════════════════════════════════════════════════════════
--  6. MAINTENANCE INFORMATIQUE (id=7) — équipements info + rapports IT
--     Zéro ligne actuellement — tout à créer
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module                r  c  u  d  e
    ROW('equipements',       1, 0, 1, 0, 1),
    ROW('sites',             1, 0, 0, 0, 0),
    ROW('interventions',     1, 1, 1, 0, 1),
    ROW('nomenclatures',     1, 0, 0, 0, 0),
    ROW('affectations',      1, 0, 0, 0, 0),
    ROW('rapport_journalier',1, 1, 1, 0, 1),
    ROW('demandes',          1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'maintenance_info'
ON DUPLICATE KEY UPDATE
    can_read=VALUES(can_read), can_create=VALUES(can_create),
    can_update=VALUES(can_update), can_delete=VALUES(can_delete), can_export=VALUES(can_export);


-- ══════════════════════════════════════════════════════════════
--  7. SUPERVISEUR OPÉRATION (id=8) — supervision complète coordinateurs
--     Complète avec les 9 nouveaux modules uniquement
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module                r  c  u  d  e
    ROW('operations',        1, 1, 1, 0, 1),
    ROW('validation_stock',  1, 0, 0, 0, 0),
    ROW('stock_bobines',     1, 0, 0, 0, 1),
    ROW('commandes_bobines', 1, 0, 0, 0, 0),
    ROW('rapports_gsb',      1, 0, 0, 0, 1),
    ROW('demandes',          1, 1, 1, 0, 0),
    ROW('rapport_journalier',0, 0, 0, 0, 0),
    ROW('departements',      0, 0, 0, 0, 0),
    ROW('affectations_it',   0, 0, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'superviseur_operation'
ON DUPLICATE KEY UPDATE
    can_read=VALUES(can_read), can_create=VALUES(can_create),
    can_update=VALUES(can_update), can_delete=VALUES(can_delete), can_export=VALUES(can_export);


-- ══════════════════════════════════════════════════════════════
--  8. CONTRÔLEUR PRODUCTION (id=9) — saisie EMUCI + données ops
--     Zéro ligne actuellement — tout à créer
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module                r  c  u  d  e
    ROW('point_emuci',       1, 1, 0, 0, 0),
    ROW('import_emuci',      1, 0, 0, 0, 0),
    ROW('operations',        1, 1, 0, 0, 0),
    ROW('equipements',       1, 0, 0, 0, 0),
    ROW('sites',             1, 0, 0, 0, 0),
    ROW('bobines',           1, 0, 0, 0, 0),
    ROW('inventaire_bobines',1, 0, 0, 0, 0),
    ROW('rivets',            1, 0, 0, 0, 0),
    ROW('stock_bobines',     1, 0, 0, 0, 0),
    ROW('demandes',          1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'controleur_production'
ON DUPLICATE KEY UPDATE
    can_read=VALUES(can_read), can_create=VALUES(can_create),
    can_update=VALUES(can_update), can_delete=VALUES(can_delete), can_export=VALUES(can_export);


-- ══════════════════════════════════════════════════════════════
--  9. GESTIONNAIRE STOCK BOBINES / GSB (id=13) — flux bobines complet
--     Zéro ligne actuellement — tout à créer
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module                r  c  u  d  e
    ROW('bobines',           1, 1, 1, 0, 1),
    ROW('inventaire_bobines',1, 1, 1, 0, 1),
    ROW('validation_stock',  1, 1, 1, 0, 1),
    ROW('rapports_gsb',      1, 0, 0, 0, 1),
    ROW('stock_bobines',     1, 0, 0, 0, 1),
    ROW('commandes_bobines', 1, 1, 1, 0, 0),
    ROW('commandes',         1, 1, 0, 0, 0),
    ROW('consommables',      1, 0, 0, 0, 0),
    ROW('equipements',       1, 0, 0, 0, 0),
    ROW('sites',             1, 0, 0, 0, 0),
    ROW('point_emuci',       1, 0, 0, 0, 0),
    ROW('operations',        1, 0, 0, 0, 0),
    ROW('pmma',              1, 0, 1, 0, 1),
    ROW('rivets',            1, 0, 0, 0, 0),
    ROW('rapports',          1, 0, 0, 0, 1),
    ROW('demandes',          1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'gestionnaire_stock_bobines'
ON DUPLICATE KEY UPDATE
    can_read=VALUES(can_read), can_create=VALUES(can_create),
    can_update=VALUES(can_update), can_delete=VALUES(can_delete), can_export=VALUES(can_export);


-- ══════════════════════════════════════════════════════════════
--  10. GESTIONNAIRE OPÉRATION (id=14) — second superviseur + délégations
--      Complète les modules manquants (a déjà : commandes can_read)
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module                r  c  u  d  e
    ROW('point_emuci',       1, 0, 1, 0, 0),  -- peut corriger (can_correct dans le code)
    ROW('import_emuci',      1, 0, 0, 0, 0),
    ROW('operations',        1, 1, 1, 0, 0),
    ROW('equipements',       1, 0, 0, 0, 0),
    ROW('sites',             1, 0, 0, 0, 0),
    ROW('interventions',     1, 0, 0, 0, 0),
    ROW('bobines',           1, 0, 0, 0, 0),
    ROW('rivets',            1, 0, 0, 0, 0),
    ROW('stock_bobines',     1, 0, 0, 0, 0),
    ROW('validation_stock',  1, 0, 0, 0, 0),
    ROW('rapports',          1, 0, 0, 0, 0),
    ROW('demandes',          1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'gestionnaire_operation'
ON DUPLICATE KEY UPDATE
    can_read=VALUES(can_read), can_create=VALUES(can_create),
    can_update=VALUES(can_update), can_delete=VALUES(can_delete), can_export=VALUES(can_export);


-- ══════════════════════════════════════════════════════════════
--  11. SUPERVISEUR IT (id=15) — supervision IT + affectations support
--      Complète les modules manquants (a déjà : pmma, commandes, interventions)
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module                r  c  u  d  e
    ROW('equipements',       1, 0, 1, 0, 1),
    ROW('sites',             1, 0, 0, 0, 0),
    ROW('nomenclatures',     1, 0, 0, 0, 0),
    ROW('affectations',      1, 1, 1, 0, 0),
    ROW('rapport_journalier',1, 0, 0, 0, 1),
    ROW('affectations_it',   1, 1, 1, 1, 0),
    ROW('users',             1, 0, 0, 0, 0),
    ROW('audit',             1, 0, 0, 0, 0),
    ROW('departements',      1, 0, 0, 0, 0),
    ROW('demandes',          1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'superviseur_it'
ON DUPLICATE KEY UPDATE
    can_read=VALUES(can_read), can_create=VALUES(can_create),
    can_update=VALUES(can_update), can_delete=VALUES(can_delete), can_export=VALUES(can_export);


-- ══════════════════════════════════════════════════════════════
--  12. SUPPORT IT (id=16) — sous-rôles configurables
--      Complète les modules manquants (a déjà : interventions)
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module                r  c  u  d  e
    ROW('equipements',       1, 0, 0, 0, 0),
    ROW('sites',             1, 0, 0, 0, 0),
    ROW('bobines',           1, 0, 0, 0, 0),
    ROW('inventaire_bobines',1, 0, 0, 0, 0),
    ROW('rapport_journalier',1, 1, 0, 0, 0),
    ROW('demandes',          1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'support_it'
ON DUPLICATE KEY UPDATE
    can_read=VALUES(can_read), can_create=VALUES(can_create),
    can_update=VALUES(can_update), can_delete=VALUES(can_delete), can_export=VALUES(can_export);


-- ══════════════════════════════════════════════════════════════
--  13. SUPERVISEUR ACHAT (id=17) — approvisionnement + commandes
--      Complète les modules manquants (a déjà : commandes, pmma)
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module                r  c  u  d  e
    ROW('consommables',      1, 0, 0, 0, 1),
    ROW('equipements',       1, 0, 0, 0, 0),
    ROW('sites',             1, 0, 0, 0, 0),
    ROW('receptions',        1, 0, 0, 0, 0),
    ROW('rapports',          1, 0, 0, 0, 1),
    ROW('affectations',      1, 0, 0, 0, 0),
    ROW('demandes',          1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'superviseur_achat'
ON DUPLICATE KEY UPDATE
    can_read=VALUES(can_read), can_create=VALUES(can_create),
    can_update=VALUES(can_update), can_delete=VALUES(can_delete), can_export=VALUES(can_export);


COMMIT;

-- ══════════════════════════════════════════════════════════════
--  VÉRIFICATION — nombre de permissions par rôle après migration
-- ══════════════════════════════════════════════════════════════
SELECT r.slug AS role, COUNT(*) AS nb_permissions
FROM permissions p
JOIN roles r ON r.id = p.role_id
GROUP BY r.slug
ORDER BY r.slug;
