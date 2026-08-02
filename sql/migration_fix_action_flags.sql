-- ============================================================
--  Fix : permissions d'action pour les flags restants (variante MySQL)
--  2026-07-30
-- ============================================================
--
--  Après la migration 27modules, deux flags d'action dans le code
--  utilisaient encore in_array() parce que les droits correspondants
--  étaient mal définis en base :
--
--  1. commandes_bobines : superviseur_operation pouvait approuver les
--     commandes dans le code (in_array) mais avait can_update=0 en DB.
--
--  2. pmma : GSB, gestionnaire_stock et superviseur_operation pouvaient
--     saisir des entrées/sorties (in_array $can_saisie) mais GSB avait
--     can_create=0 en DB (copié-collé de can_update lors de la migration).
--
--  Ce script corrige les deux incohérences et permet de remplacer les
--  in_array() par can() dans le code.
--
--  Idempotent.
-- ============================================================

START TRANSACTION;

-- 1. superviseur_operation → commandes_bobines : can_update = 1
UPDATE permissions p
JOIN roles r ON p.role_id = r.id
SET p.can_update = 1
WHERE p.module = 'commandes_bobines'
  AND r.slug = 'superviseur_operation'
  AND p.can_update <> 1;

-- 2. pmma saisie (can_create) pour les rôles qui saisissent des mouvements
UPDATE permissions p
JOIN roles r ON p.role_id = r.id
SET p.can_create = 1
WHERE p.module = 'pmma'
  AND r.slug IN ('gestionnaire_stock_bobines', 'gestionnaire_stock', 'superviseur_operation')
  AND p.can_create <> 1;

COMMIT;

-- Vérification
SELECT r.slug, p.module, p.can_read, p.can_create, p.can_update
FROM permissions p
JOIN roles r ON r.id = p.role_id
WHERE p.module IN ('commandes_bobines', 'pmma')
  AND r.slug IN ('superviseur_operation','gestionnaire_stock_bobines','gestionnaire_stock')
ORDER BY p.module, r.slug;
