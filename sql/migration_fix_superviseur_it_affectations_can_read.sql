-- ============================================================
--  Correctif : superviseur_it portait can_create=1 sur le module
--  `affectations` mais can_read=0 — incohérence trouvée lors de l'audit
--  menus/permissions (2026-08-27).
--
--  Conséquence concrète : ce rôle pouvait créer une affectation
--  (pages/affectations.php, gate can_create) mais n'avait accès ni au
--  lien ni à la page "Historique des mouvements"
--  (pages/mouvements_equipements.php, gate can_read) — un droit de
--  création sans jamais pouvoir consulter ce qu'on vient de créer.
--
--  Idempotente et strictement additive (GREATEST : ne retire jamais un
--  droit existant).
-- ============================================================
BEGIN;

UPDATE permissions
SET can_read = GREATEST(can_read, 1)
WHERE module = 'affectations'
  AND role_id = (SELECT id FROM roles WHERE slug = 'superviseur_it');

COMMIT;
