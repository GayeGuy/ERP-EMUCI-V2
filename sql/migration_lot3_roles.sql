-- ============================================================
--  Lot 3 — n° 02 et 03 : nettoyage des profils
-- ============================================================
--
--  n° 02  Le profil « Gestionnaire » (slug 'gestionnaire', id 3) fait doublon
--         avec les autres profils : il est supprimé. Ses comptes basculent
--         vers « Gestionnaire de Stock », dont la description d'origine
--         — « saisie des mouvements et livraisons » — recouvre son périmètre.
--
--  n° 03  « Maintenance Informatique » est rattachée à « Support IT ».
--         Support IT est déjà un profil à sous-rôles : les comptes basculent
--         donc vers support_it avec le sous-rôle « maintenance » actif, qui
--         leur rend exactement les mêmes modules (interventions, équipements,
--         sites). La ligne de rôle 'maintenance_info' est conservée, sans
--         compte rattaché : le journal d'audit et les traces existantes y
--         font référence, et la supprimer les rendrait illisibles.
--
--  Idempotent : rejouer ce fichier ne change rien de plus.

BEGIN;

-- ── n° 02 — bascule des comptes puis suppression du rôle ────────────────
UPDATE users
   SET role_id = (SELECT id FROM roles WHERE slug = 'gestionnaire_stock')
 WHERE role_id = (SELECT id FROM roles WHERE slug = 'gestionnaire');

DELETE FROM permissions
 WHERE role_id = (SELECT id FROM roles WHERE slug = 'gestionnaire');

DELETE FROM roles WHERE slug = 'gestionnaire';

-- ── n° 03 — bascule des comptes vers Support IT ─────────────────────────
--  Le sous-rôle est créé avant le changement de rôle : si la migration est
--  interrompue, un compte se retrouve support_it sans sous-rôle, donc sans
--  aucun accès — l'inverse d'un compte encore maintenance_info, qui marche.
INSERT INTO support_it_roles (user_id, sous_role, actif)
SELECT u.id, 'maintenance', 1
  FROM users u
  JOIN roles r ON r.id = u.role_id
 WHERE r.slug = 'maintenance_info'
   AND NOT EXISTS (
        SELECT 1 FROM support_it_roles s
         WHERE s.user_id = u.id AND s.sous_role = 'maintenance'
   );

UPDATE support_it_roles
   SET actif = 1
 WHERE sous_role = 'maintenance'
   AND user_id IN (SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
                    WHERE r.slug = 'maintenance_info');

UPDATE users
   SET role_id = (SELECT id FROM roles WHERE slug = 'support_it')
 WHERE role_id = (SELECT id FROM roles WHERE slug = 'maintenance_info');

UPDATE roles
   SET description = 'Rattaché à Support IT (sous-rôle Maintenance) — conservé pour l''historique d''audit'
 WHERE slug = 'maintenance_info';

COMMIT;
