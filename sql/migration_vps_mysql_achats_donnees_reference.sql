-- ============================================================
--  sql/migration_vps_mysql_achats_donnees_reference.sql
-- ============================================================
--
--  Le schema du module Achats (sql/achats_mysql.sql, Phase 2) cree les
--  tables mais ne seme aucune donnee — trouve en testant la creation
--  d'une FEB de bout en bout (Phase 4) : sans achat_types, aucune ligne
--  ne peut porter de type_achat (FK stricte vers achat_types.code, et
--  'DAI' est teste en dur dans plusieurs endroits du code — cf.
--  includes/achats.php, pages/achats/receptions.php) ; sans achat_paliers,
--  ach_palier_pour_montant() renvoie toujours null et aucune FEB ne peut
--  jamais lancer sa validation, quel que soit le montant ; sans
--  achat_parametres, les reglages du module (max_lignes_feb, delais...)
--  retombent sur les valeurs de repli codees en PHP plutot que sur une
--  vraie configuration modifiable.
--
--  Valeurs reprises telles quelles depuis la base de production
--  PostgreSQL (main) — donnees de reference universelles au module, pas
--  specifiques a une organisation (contrairement a departements/
--  familles_achat/sites, deja geres par leurs propres ecrans admin et
--  volontairement laisses vides ici).
-- ============================================================

INSERT IGNORE INTO achat_types (code, libelle, actif) VALUES
    ('DAF', "Demande d'Achat Fournitures", 1),
    ('DAI', "Demande d'Achat Immobilisation", 1),
    ('DAH', "Demande d'Achat Hors-marche", 1);

INSERT IGNORE INTO achat_paliers (borne_min, borne_max, libelle, signataires, ordre, actif) VALUES
    (0,       500000,  'RAF seul',        '["raf"]',              1, 1),
    (500001,  5000000, 'RAF + DAF',       '["raf", "daf"]',       2, 1),
    (5000001, NULL,    'RAF + DAF + PDG', '["raf", "daf", "dg"]', 3, 1);

INSERT IGNORE INTO achat_parametres (cle, valeur, libelle, type) VALUES
    ('comportement_budget_defaut',     'alerte', 'Comportement par defaut sur depassement', 'liste'),
    ('delai_livraison_standard_jours', '15',     'Delai de livraison standard (jours)', 'nombre'),
    ('devise',                         'XOF',    'Devise', 'texte'),
    ('max_lignes_feb',                 '14',     'Nombre maximum de lignes par FEB', 'nombre'),
    ('seuil_retard_jours',             '5',      'Seuil de retard de livraison (jours)', 'nombre'),
    ('seuil_retard_validation_jours',  '5',      "Seuil d'alerte - FEB en attente de validation depuis (jours)", 'nombre');
