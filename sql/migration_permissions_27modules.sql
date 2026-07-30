-- ============================================================
--  Migration : permissions complètes — 27 modules × 14 rôles
--  Colonnes : can_read, can_create, can_update, can_delete, can_export (smallint 0/1)
--  Idempotente : ON CONFLICT (role_id, module) DO UPDATE
--  2026-07-30
-- ============================================================
BEGIN;

-- ══════════════════════════════════════════════════════════════
--  1. ADMIN & SUPERADMIN
--     Tous droits sur les 9 nouveaux modules
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, 1, 1, 1, 1, 1
FROM roles r
CROSS JOIN (VALUES
    ('operations'), ('validation_stock'), ('demandes'), ('commandes_bobines'),
    ('rapports_gsb'), ('stock_bobines'), ('rapport_journalier'), ('departements'), ('affectations_it')
) AS m(module)
WHERE r.slug IN ('admin', 'superadmin')
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read=1, can_create=1, can_update=1, can_delete=1, can_export=1;


-- ══════════════════════════════════════════════════════════════
--  2. GESTIONNAIRE (id=3) — rôle legacy, accès minimal
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module               r  c  u  d  e
    ('demandes',         1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'gestionnaire'
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read=EXCLUDED.can_read, can_create=EXCLUDED.can_create,
    can_update=EXCLUDED.can_update, can_delete=EXCLUDED.can_delete, can_export=EXCLUDED.can_export;


-- ══════════════════════════════════════════════════════════════
--  3. LECTEUR (id=4) — DG/PDG, lecture + export uniquement
--     Aucune modification possible sur aucun module
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module               r  c  u  d  e
    ('sites',            1, 0, 0, 0, 0),
    ('equipements',      1, 0, 0, 0, 0),
    ('bobines',          1, 0, 0, 0, 0),
    ('inventaire_bobines',1,0, 0, 0, 0),
    ('operations',       1, 0, 0, 0, 0),
    ('commandes',        1, 0, 0, 0, 0),
    ('rivets',           1, 0, 0, 0, 0),
    ('pmma',             1, 0, 0, 0, 0),
    ('consommables',     1, 0, 0, 0, 0),
    ('demandes',         1, 0, 0, 0, 0),
    ('rapports',         1, 0, 0, 0, 1),
    ('rapports_gsb',     1, 0, 0, 0, 1),
    ('stock_bobines',    1, 0, 0, 0, 0),
    ('validation_stock', 1, 0, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'lecteur'
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read=EXCLUDED.can_read, can_create=EXCLUDED.can_create,
    can_update=EXCLUDED.can_update, can_delete=EXCLUDED.can_delete, can_export=EXCLUDED.can_export;


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
    ('affectations',     1, 0, 0, 0, 0),
    ('interventions',    1, 0, 0, 0, 0),
    ('bobines',          1, 1, 1, 0, 1),
    ('inventaire_bobines',1,1, 1, 0, 0),
    ('stock_bobines',    1, 0, 0, 0, 1),
    ('commandes_bobines',1, 0, 0, 0, 0),
    ('rivets',           1, 0, 0, 0, 0),
    ('operations',       1, 0, 0, 0, 0),
    ('validation_stock', 1, 0, 0, 0, 0),
    ('demandes',         1, 1, 0, 0, 0),
    ('rapports_gsb',     1, 0, 0, 0, 1),
    ('nomenclatures',    1, 0, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'gestionnaire_stock'
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read=EXCLUDED.can_read, can_create=EXCLUDED.can_create,
    can_update=EXCLUDED.can_update, can_delete=EXCLUDED.can_delete, can_export=EXCLUDED.can_export;


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
    ('sites',            1, 0, 0, 0, 0),
    ('operations',       1, 1, 1, 0, 1),  -- point journalier : activité principale
    ('validation_stock', 1, 0, 0, 0, 0),
    ('stock_bobines',    1, 0, 0, 0, 0),
    ('commandes_bobines',1, 1, 0, 0, 0),
    ('point_emuci',      1, 0, 0, 0, 0),
    ('demandes',         1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'coordinateur_site'
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read=EXCLUDED.can_read, can_create=EXCLUDED.can_create,
    can_update=EXCLUDED.can_update, can_delete=EXCLUDED.can_delete, can_export=EXCLUDED.can_export;


-- ══════════════════════════════════════════════════════════════
--  6. MAINTENANCE INFORMATIQUE (id=7) — équipements info + rapports IT
--     Zéro ligne actuellement — tout à créer
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module                r  c  u  d  e
    ('equipements',       1, 0, 1, 0, 1),
    ('sites',             1, 0, 0, 0, 0),
    ('interventions',     1, 1, 1, 0, 1),
    ('nomenclatures',     1, 0, 0, 0, 0),
    ('affectations',      1, 0, 0, 0, 0),
    ('rapport_journalier',1, 1, 1, 0, 1),
    ('demandes',          1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'maintenance_info'
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read=EXCLUDED.can_read, can_create=EXCLUDED.can_create,
    can_update=EXCLUDED.can_update, can_delete=EXCLUDED.can_delete, can_export=EXCLUDED.can_export;


-- ══════════════════════════════════════════════════════════════
--  7. SUPERVISEUR OPÉRATION (id=8) — supervision complète coordinateurs
--     Complète avec les 9 nouveaux modules uniquement
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module                r  c  u  d  e
    ('operations',        1, 1, 1, 0, 1),
    ('validation_stock',  1, 0, 0, 0, 0),
    ('stock_bobines',     1, 0, 0, 0, 1),
    ('commandes_bobines', 1, 0, 0, 0, 0),
    ('rapports_gsb',      1, 0, 0, 0, 1),
    ('demandes',          1, 1, 1, 0, 0),
    ('rapport_journalier',0, 0, 0, 0, 0),
    ('departements',      0, 0, 0, 0, 0),
    ('affectations_it',   0, 0, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'superviseur_operation'
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read=EXCLUDED.can_read, can_create=EXCLUDED.can_create,
    can_update=EXCLUDED.can_update, can_delete=EXCLUDED.can_delete, can_export=EXCLUDED.can_export;


-- ══════════════════════════════════════════════════════════════
--  8. CONTRÔLEUR PRODUCTION (id=9) — saisie EMUCI + données ops
--     Zéro ligne actuellement — tout à créer
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module                r  c  u  d  e
    ('point_emuci',       1, 1, 0, 0, 0),
    ('import_emuci',      1, 0, 0, 0, 0),
    ('operations',        1, 1, 0, 0, 0),
    ('equipements',       1, 0, 0, 0, 0),
    ('sites',             1, 0, 0, 0, 0),
    ('bobines',           1, 0, 0, 0, 0),
    ('inventaire_bobines',1, 0, 0, 0, 0),
    ('rivets',            1, 0, 0, 0, 0),
    ('stock_bobines',     1, 0, 0, 0, 0),
    ('demandes',          1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'controleur_production'
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read=EXCLUDED.can_read, can_create=EXCLUDED.can_create,
    can_update=EXCLUDED.can_update, can_delete=EXCLUDED.can_delete, can_export=EXCLUDED.can_export;


-- ══════════════════════════════════════════════════════════════
--  9. GESTIONNAIRE STOCK BOBINES / GSB (id=13) — flux bobines complet
--     Zéro ligne actuellement — tout à créer
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module                r  c  u  d  e
    ('bobines',           1, 1, 1, 0, 1),
    ('inventaire_bobines',1, 1, 1, 0, 1),
    ('validation_stock',  1, 1, 1, 0, 1),
    ('rapports_gsb',      1, 0, 0, 0, 1),
    ('stock_bobines',     1, 0, 0, 0, 1),
    ('commandes_bobines', 1, 1, 1, 0, 0),
    ('commandes',         1, 1, 0, 0, 0),
    ('consommables',      1, 0, 0, 0, 0),
    ('equipements',       1, 0, 0, 0, 0),
    ('sites',             1, 0, 0, 0, 0),
    ('point_emuci',       1, 0, 0, 0, 0),
    ('operations',        1, 0, 0, 0, 0),
    ('pmma',              1, 0, 1, 0, 1),
    ('rivets',            1, 0, 0, 0, 0),
    ('rapports',          1, 0, 0, 0, 1),
    ('demandes',          1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'gestionnaire_stock_bobines'
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read=EXCLUDED.can_read, can_create=EXCLUDED.can_create,
    can_update=EXCLUDED.can_update, can_delete=EXCLUDED.can_delete, can_export=EXCLUDED.can_export;


-- ══════════════════════════════════════════════════════════════
--  10. GESTIONNAIRE OPÉRATION (id=14) — second superviseur + délégations
--      Complète les modules manquants (a déjà : commandes can_read)
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module                r  c  u  d  e
    ('point_emuci',       1, 0, 1, 0, 0),  -- peut corriger (can_correct dans le code)
    ('import_emuci',      1, 0, 0, 0, 0),
    ('operations',        1, 1, 1, 0, 0),
    ('equipements',       1, 0, 0, 0, 0),
    ('sites',             1, 0, 0, 0, 0),
    ('interventions',     1, 0, 0, 0, 0),
    ('bobines',           1, 0, 0, 0, 0),
    ('rivets',            1, 0, 0, 0, 0),
    ('stock_bobines',     1, 0, 0, 0, 0),
    ('validation_stock',  1, 0, 0, 0, 0),
    ('rapports',          1, 0, 0, 0, 0),
    ('demandes',          1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'gestionnaire_operation'
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read=EXCLUDED.can_read, can_create=EXCLUDED.can_create,
    can_update=EXCLUDED.can_update, can_delete=EXCLUDED.can_delete, can_export=EXCLUDED.can_export;


-- ══════════════════════════════════════════════════════════════
--  11. SUPERVISEUR IT (id=15) — supervision IT + affectations support
--      Complète les modules manquants (a déjà : pmma, commandes, interventions)
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module                r  c  u  d  e
    ('equipements',       1, 0, 1, 0, 1),
    ('sites',             1, 0, 0, 0, 0),
    ('nomenclatures',     1, 0, 0, 0, 0),
    ('affectations',      1, 1, 1, 0, 0),
    ('rapport_journalier',1, 0, 0, 0, 1),
    ('affectations_it',   1, 1, 1, 1, 0),
    ('users',             1, 0, 0, 0, 0),
    ('audit',             1, 0, 0, 0, 0),
    ('departements',      1, 0, 0, 0, 0),
    ('demandes',          1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'superviseur_it'
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read=EXCLUDED.can_read, can_create=EXCLUDED.can_create,
    can_update=EXCLUDED.can_update, can_delete=EXCLUDED.can_delete, can_export=EXCLUDED.can_export;


-- ══════════════════════════════════════════════════════════════
--  12. SUPPORT IT (id=16) — sous-rôles configurables
--      Complète les modules manquants (a déjà : interventions)
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module                r  c  u  d  e
    ('equipements',       1, 0, 0, 0, 0),
    ('sites',             1, 0, 0, 0, 0),
    ('bobines',           1, 0, 0, 0, 0),
    ('inventaire_bobines',1, 0, 0, 0, 0),
    ('rapport_journalier',1, 1, 0, 0, 0),
    ('demandes',          1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'support_it'
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read=EXCLUDED.can_read, can_create=EXCLUDED.can_create,
    can_update=EXCLUDED.can_update, can_delete=EXCLUDED.can_delete, can_export=EXCLUDED.can_export;


-- ══════════════════════════════════════════════════════════════
--  13. SUPERVISEUR ACHAT (id=17) — approvisionnement + commandes
--      Complète les modules manquants (a déjà : commandes, pmma)
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module                r  c  u  d  e
    ('consommables',      1, 0, 0, 0, 1),
    ('equipements',       1, 0, 0, 0, 0),
    ('sites',             1, 0, 0, 0, 0),
    ('receptions',        1, 0, 0, 0, 0),
    ('rapports',          1, 0, 0, 0, 1),
    ('affectations',      1, 0, 0, 0, 0),
    ('demandes',          1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug = 'superviseur_achat'
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read=EXCLUDED.can_read, can_create=EXCLUDED.can_create,
    can_update=EXCLUDED.can_update, can_delete=EXCLUDED.can_delete, can_export=EXCLUDED.can_export;


COMMIT;

-- ══════════════════════════════════════════════════════════════
--  VÉRIFICATION — nombre de permissions par rôle après migration
-- ══════════════════════════════════════════════════════════════
SELECT r.slug AS role, COUNT(*) AS nb_permissions
FROM permissions p
JOIN roles r ON r.id = p.role_id
GROUP BY r.slug
ORDER BY r.slug;
