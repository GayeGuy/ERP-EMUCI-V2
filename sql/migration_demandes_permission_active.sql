-- ============================================================
--  Active enfin le module `demandes` : jusqu'ici présent dans la table
--  `permissions` et dans la grille Admin → Permissions, mais vérifié
--  nulle part dans le code — désactiver "Demandes internes" pour un
--  rôle (ex. superviseur_it, 2026-08-27) n'avait donc aucun effet, le
--  menu et les 4 écrans restant pleinement accessibles.
--
--  Ajoute require_permission('demandes','can_read') sur les 4 écrans
--  du groupe (pages/demandes.php, demandes_new.php,
--  demandes_a_valider.php, demandes_it.php) en plus de leur logique
--  existante (di_peut_creer, di_user_can_traiter_it...) qui reste
--  inchangée. "Traitements IT" et "À valider" gardent leur filtrage
--  fin par rôle/circuit (di_user_roles) : can_read sur `demandes` n'est
--  qu'un interrupteur général, pas un remplacement de cette logique.
--
--  Presque tous les rôles ont déjà can_read=1/can_create=1 sur ce
--  module (posé lors d'une précédente campagne de permissions, jamais
--  branché au code) — seul superviseur_it est à 0 partout (l'action
--  de l'utilisateur ce jour), et raf/daf/directeur_general n'ont
--  carrément aucune ligne. Sans complément, ces trois derniers
--  perdraient l'accès aux Demandes internes dès l'activation du
--  contrôle, alors que rien n'indique que ce soit voulu (module
--  transversal, "tous les employés") : on les aligne sur leurs pairs.
--
--  Idempotente et strictement additive (GREATEST : ne retire jamais un
--  droit existant — superviseur_it reste volontairement à 0, c'est le
--  changement demandé).
-- ============================================================
BEGIN;

INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, 'demandes', 1, 1, 0, 0, 0
FROM roles r
WHERE r.slug IN ('raf', 'daf', 'directeur_general')
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read   = GREATEST(permissions.can_read,   EXCLUDED.can_read),
    can_create = GREATEST(permissions.can_create, EXCLUDED.can_create);

COMMIT;
