-- ============================================================
--  Correctif : régressions introduites par migration_permissions_27modules.sql
--  Idempotente et strictement additive (GREATEST : ne retire jamais un droit
--  existant, ne fait que compléter ce qui manque).
--  2026-07-30
-- ============================================================
--
--  Constat : migration_permissions_27modules.sql a remplacé, pour chaque
--  ligne (role_id, module) qu'elle touche, TOUTES les colonnes de droits par
--  ses propres valeurs (ON CONFLICT ... DO UPDATE SET can_read=EXCLUDED...).
--  Deux effets de bord distincts :
--
--   1. validation_stock_matin.php vérifie désormais can('validation_stock',
--      'can_create') au lieu d'une liste de rôles en dur. La migration
--      n'accorde can_create=1 sur ce module qu'à gestionnaire_stock_bobines :
--      gestionnaire_stock et superviseur_operation n'ont que can_read=1, et
--      superviseur_it n'a aucune ligne. Ces trois rôles perdent l'accès à
--      toute la page (403), alors qu'ils y accédaient via l'ancienne liste
--      $gsb_roles.
--
--   2. includes/dashboard.php masque désormais réellement les blocs par
--      permission dès qu'un rôle a au moins une ligne en base (avant, un
--      rôle à zéro ligne voyait tout, sans filtre). La migration n'accorde
--      jamais le module 'audit', et 'interventions' qu'à trois rôles : les
--      profils qui affichent les blocs "Interventions du mois" / "Dernières
--      activités" (cf. dash_profils()) perdent ces blocs pour les rôles non
--      couverts.
--
--  GREATEST() protège contre un troisième risque, plus insidieux : si un
--  rôle avait DÉJÀ un droit plus large que ce que la migration précédente a
--  écrit dessus (elle écrase sans lire l'existant), on ne veut pas l'écraser
--  une seconde fois avec une valeur encore plus basse.
-- ============================================================
BEGIN;

-- ── 1. validation_stock_matin.php : restaurer l'accès des trois rôles bloqués
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, m.r, m.c, m.u, m.d, m.e
FROM roles r
CROSS JOIN (VALUES
--  module               r  c  u  d  e
    ('validation_stock', 1, 1, 0, 0, 0)
) AS m(module, r, c, u, d, e)
WHERE r.slug IN ('gestionnaire_stock', 'superviseur_operation', 'superviseur_it')
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read   = GREATEST(permissions.can_read,   EXCLUDED.can_read),
    can_create = GREATEST(permissions.can_create, EXCLUDED.can_create);

-- ── 2. Dashboard : bloc "Dernières activités" (module audit)
--     Profils concernés : general (lecteur, controleur_production,
--     gestionnaire_stock, superviseur_achat), superviseur_op, informatique.
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, 'audit', 1, 0, 0, 0, 0
FROM roles r
WHERE r.slug IN (
    'lecteur', 'controleur_production', 'gestionnaire_stock', 'superviseur_achat',
    'maintenance_info', 'support_it', 'superviseur_operation'
)
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read = GREATEST(permissions.can_read, EXCLUDED.can_read);

-- ── 3. Dashboard : bloc "Interventions du mois" (module interventions)
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, 'interventions', 1, 0, 0, 0, 0
FROM roles r
WHERE r.slug IN (
    'lecteur', 'controleur_production', 'superviseur_achat',
    'superviseur_it', 'support_it', 'superviseur_operation'
)
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read = GREATEST(permissions.can_read, EXCLUDED.can_read);

-- ── 4. Dashboard : bloc "Bobines actives" / "Rivets en stock" pour
--     superviseur_achat (profil general), constatés absents et non
--     mentionnés comme pré-existants dans la migration précédente.
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, 1, 0, 0, 0, 0
FROM roles r
CROSS JOIN (VALUES ('bobines'), ('rivets')) AS m(module)
WHERE r.slug = 'superviseur_achat'
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read = GREATEST(permissions.can_read, EXCLUDED.can_read);

COMMIT;

-- ══════════════════════════════════════════════════════════════
--  VÉRIFICATION
-- ══════════════════════════════════════════════════════════════
SELECT r.slug AS role, p.module, p.can_read, p.can_create
FROM permissions p JOIN roles r ON r.id = p.role_id
WHERE p.module IN ('validation_stock','audit','interventions','bobines','rivets')
  AND r.slug IN ('gestionnaire_stock','superviseur_operation','superviseur_it',
                 'lecteur','controleur_production','superviseur_achat',
                 'maintenance_info','support_it')
ORDER BY r.slug, p.module;
