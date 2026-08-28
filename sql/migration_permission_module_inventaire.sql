-- ============================================================
--  Le groupe de menu INVENTAIRE (sorti de BOBINES le 2026-08-28) était
--  jusqu'ici visible selon une liste de rôles codée en dur dans
--  get_groupes_pour_role() (includes/groupes_config.php). Demande
--  utilisateur : en faire un droit géré depuis Admin → Permissions,
--  comme les autres modules — pas besoin d'une modification de code
--  pour changer qui voit le groupe.
--
--  Seed initial = exactement les rôles qui avaient accès au groupe
--  avant ce changement (coordinateur_site, gestionnaire_stock,
--  superviseur_operation, controleur_production,
--  gestionnaire_stock_bobines) : aucune régression, juste rendu
--  éditable. admin/superadmin bypassent déjà can() sans permission DB.
--
--  Ce droit ne fait que gouverner la VISIBILITÉ du groupe de menu — les
--  écrans individuels (inventaire_bobines, ecarts_rivets, etc.) gardent
--  leurs propres droits déjà en place, inchangés.
-- ============================================================
INSERT INTO permissions (role_id, module, can_read)
SELECT r.id, 'inventaire', 1
FROM roles r
WHERE r.slug IN ('coordinateur_site','gestionnaire_stock','superviseur_operation','controleur_production','gestionnaire_stock_bobines')
ON CONFLICT (role_id, module) DO NOTHING;
