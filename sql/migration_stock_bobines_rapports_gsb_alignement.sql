-- ============================================================
--  Audit permissions du 2026-08-29 — items #3 et #4.
--
--  pages/stock_bobines_vue.php n'a toujours eu que require_permission(
--  'stock_bobines','can_read') comme gate. Le menu masquait pourtant
--  coordinateur_site via roles_exclude, sans que ce rôle ait jamais
--  can_read=0 en base — un coordinateur_site pouvait donc déjà accéder à
--  cette page en tapant l'URL directement, malgré l'exclusion voulue au
--  menu (couvre plusieurs sites, pas la vue d'un coordinateur). On corrige
--  la base pour que le passage à un gate perm-only (roles_exclude retiré du
--  menu, cf. includes/groupes_config.php) ne rouvre pas cet accès.
--
--  pages/rapports_gsb.php : aucune correction nécessaire. La liste
--  roles_include actuelle (admin, superadmin, gestionnaire_stock_bobines,
--  gestionnaire_stock, superviseur_operation) correspond déjà exactement
--  aux rôles ayant rapports_gsb.can_read=1 en base — le switch vers perm
--  ne change donc rien en pratique.
-- ============================================================

UPDATE permissions SET can_read = 0
WHERE module = 'stock_bobines'
  AND role_id = (SELECT id FROM roles WHERE slug = 'coordinateur_site');
