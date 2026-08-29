-- ============================================================
--  pages/resume_superviseur.php empilait require_permission('rapports',
--  'can_read') PUIS une liste de rôles en dur plus stricte par-dessus
--  ($roles_autorises = admin/superadmin/superviseur_operation/
--  gestionnaire_operation/lecteur). Le module 'rapports' est partagé
--  par plusieurs écrans de rapports plus larges (rapports.php, exports)
--  — beaucoup de rôles l'ont en base (coordinateur_site, daf,
--  directeur_general, gestionnaire_stock, gestionnaire_stock_bobines,
--  raf, superviseur_achat, superviseur_it) sans que ça leur donne accès
--  à CET écran précis, verrouillé par la liste en dur.
--
--  Remplacer bêtement par require_permission('rapports', ...) aurait
--  élargi l'accès à tous ces rôles d'un coup. Au lieu de ça : nouveau
--  module dédié 'resume_superviseur', seedé pour reproduire EXACTEMENT
--  l'accès actuel (les 5 rôles de $roles_autorises), pilotable ensuite
--  depuis Admin -> Permissions sans changement de comportement immédiat.
-- ============================================================
INSERT INTO permissions (role_id, module, can_read)
SELECT r.id, 'resume_superviseur', 1 FROM roles r
WHERE r.slug IN ('admin','superadmin','superviseur_operation','gestionnaire_operation','lecteur')
ON CONFLICT (role_id, module) DO UPDATE SET can_read = 1;
