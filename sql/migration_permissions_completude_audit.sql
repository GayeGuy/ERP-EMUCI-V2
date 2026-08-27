-- ============================================================
--  Complète 3 droits manquants repérés lors de l'audit menus/permissions
--  (2026-08-27), pour des rôles déjà membres du groupe de menu concerné
--  mais bloqués en 403 faute de ligne en base. Décisions actées avec
--  l'utilisateur au cas par cas (AskUserQuestion) — les autres trous
--  trouvés par le même audit (superviseur_achat, gestionnaire_stock,
--  gestionnaire_stock_bobines sur operations/point_emuci/import_emuci/
--  interventions) sont volontairement laissés tels quels.
--
--  support_it → rapport_journalier/affectations_it VOLONTAIREMENT ABSENT
--  ici : can() pour support_it passe d'abord par _support_it_can()
--  (includes/session.php), qui restreint l'accès aux modules listés
--  dans le sous-rôle actif (support_it_roles) AVANT même de lire cette
--  table — ni 'rapport_journalier' ni 'affectations_it' ne figurent
--  dans les 3 listes existantes (maintenance/controleur_production/
--  gestionnaire_bobines). Une ligne ici serait sans effet ; le vrai
--  correctif est un choix de sous-rôle (ou un nouveau), à trancher
--  séparément.
--
--  Idempotente et strictement additive (GREATEST : ne retire jamais un
--  droit existant).
-- ============================================================
BEGIN;

-- controleur_production → commandes_bobines (groupe Bobines : le lien
-- "Commande bobines" est visible depuis l'audit précédent mais menait
-- à un 403 sans ce droit)
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT id, 'commandes_bobines', 1, 0, 0, 0, 0 FROM roles WHERE slug = 'controleur_production'
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read = GREATEST(permissions.can_read, EXCLUDED.can_read);

-- maintenance_info → rivets, affectations_it (groupes Stock/Informatique)
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, m.module, 1, 0, 0, 0, 0
FROM roles r CROSS JOIN (VALUES ('rivets'), ('affectations_it')) AS m(module)
WHERE r.slug = 'maintenance_info'
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read = GREATEST(permissions.can_read, EXCLUDED.can_read);

COMMIT;
