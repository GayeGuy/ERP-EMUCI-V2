-- ============================================================
--  pages/operations/bobines.php n'a jamais réellement vérifié
--  bobines.can_read en base — l'accès en lecture était piloté à 100%
--  par une liste de rôles codée en dur ($roles_autorises), avec ce
--  commentaire explicite : « gestionnaire_stock N'a PAS accès à cette
--  page ». La table permissions a pourtant bobines.can_read=1 pour
--  gestionnaire_stock (contradiction directe avec la règle), et pour
--  controleur_production/gestionnaire_operation/lecteur/superviseur_achat
--  (aucun n'est dans la liste en dur — droit sans effet). À l'inverse,
--  superviseur_it et maintenance_info SONT dans la liste en dur mais
--  ont can_read=0 en base.
--
--  Avant de faire de bobines.can_read la seule source de vérité
--  (require_permission), on aligne la base sur le comportement RÉEL
--  actuel de la page (la liste en dur), pour qu'il n'y ait aucune
--  régression ni élargissement non voulu au moment du switch.
--
--  Bonus découvert au passage : support_it n'était PAS dans la liste en
--  dur, alors que le sous-rôle 'gestionnaire_bobines' (includes/session.
--  php, _support_it_can()) liste explicitement 'bobines' parmi ses
--  modules autorisés — un support_it avec ce sous-rôle actif n'a donc
--  jamais pu ouvrir cette page malgré le sous-rôle prévu pour ça. Son
--  can_read=1 déjà en base devient enfin effectif une fois le switch
--  fait (le passage par _support_it_can() reste inchangé, toujours
--  filtré par sous-rôle).
-- ============================================================

-- Rôles qui DOIVENT avoir accès (liste $roles_autorises actuelle) mais
-- n'ont pas encore le droit en base.
INSERT INTO permissions (role_id, module, can_read)
SELECT r.id, 'bobines', 1 FROM roles r
WHERE r.slug IN ('superviseur_it','maintenance_info')
ON CONFLICT (role_id, module) DO UPDATE SET can_read = 1;

-- Rôles qui NE DOIVENT PAS avoir accès mais portent can_read=1 en base
-- (droit fantôme, sans effet aujourd'hui, mais qui prendrait effet dès
-- le switch vers require_permission() s'il n'était pas corrigé ici).
UPDATE permissions SET can_read = 0
WHERE module = 'bobines'
  AND role_id IN (
      SELECT id FROM roles
      WHERE slug IN ('gestionnaire_stock','controleur_production','gestionnaire_operation','lecteur','superviseur_achat')
  );
