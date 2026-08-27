-- ============================================================
--  Lot 2 — n° 01 : le profil « Lecteur » s'appelle « PDG »
-- ============================================================
--
--  Demande de la réunion : « lecteur » ne décrit pas la fonction réelle du
--  profil, qui est celle de la direction générale.
--
--  Seul le libellé affiché change. Le slug 'lecteur' reste inchangé : il est
--  écrit en dur à onze endroits dans le code (navigation, dashboard, visa
--  Direction Générale des demandes internes) et le renommer ferait perdre
--  l'accès à tous les comptes concernés sans rien apporter à l'utilisateur.

UPDATE roles
   SET nom         = 'PDG',
       description = 'Direction générale — supervision en lecture seule et visa DG'
 WHERE slug = 'lecteur';
