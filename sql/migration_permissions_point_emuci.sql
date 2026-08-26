-- ============================================================
--  Permission de lecture « point_emuci » pour les rôles qui utilisent
--  déjà la page des points journaliers
-- ============================================================
--
--  Constat du 2026-07-30. Le tableau de bord par profil fait dépendre
--  l'affichage d'un bloc de can($module) — c'était l'objet du chantier :
--  l'écran des permissions doit enfin piloter quelque chose.
--
--  Or coordinateur_site et gestionnaire_operation n'avaient aucune ligne
--  pour le module « point_emuci », alors que :
--
--    - pages/operations/point_journalier.php ne pose qu'un require_auth(),
--      sans contrôle de permission, et traite explicitement le cas
--      coordinateur_site à six endroits (filtre sur son site, restriction
--      aux brouillons, etc.). Ces rôles utilisent donc bel et bien la page.
--    - aucun autre fichier du dépôt ne teste can('point_emuci'), et
--      l'entrée de menu « Point EMUCI » filtre par roles_exclude et non par
--      permission.
--
--  L'absence de ces lignes était donc un oubli de configuration, pas une
--  décision. Sans elles, le coordinateur perdait ses deux blocs (points
--  récents, corrections en attente) et le gestionnaire opération son bloc
--  de points, c'est-à-dire exactement ce que la spécification demande
--  qu'ils voient.
--
--  Portée de ce script : lecture seule, et rien d'autre. Aucun droit de
--  création, de modification ni de suppression n'est accordé. Comme aucune
--  page ne consulte cette permission, l'effet se limite au tableau de
--  bord.
--
--  Rejouable sans effet de bord.

INSERT INTO permissions (role_id, module, can_create, can_read, can_update, can_delete, can_export)
SELECT r.id, 'point_emuci', 0, 1, 0, 0, 0
FROM roles r
WHERE r.slug IN ('coordinateur_site', 'gestionnaire_operation')
  AND NOT EXISTS (
      SELECT 1 FROM permissions p
      WHERE p.role_id = r.id AND p.module = 'point_emuci'
  );

-- Une ligne déjà présente mais à can_read = 0 doit passer à 1 : le but est
-- que ces rôles puissent lire, quel que soit l'état antérieur.
UPDATE permissions p
SET can_read = 1
FROM roles r
WHERE p.role_id = r.id
  AND p.module = 'point_emuci'
  AND r.slug IN ('coordinateur_site', 'gestionnaire_operation')
  AND p.can_read <> 1;
