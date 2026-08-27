-- ============================================================
--  sql/migration_vps_mysql_referentiels_organisation.sql
-- ============================================================
--
--  Trouve par comparaison exhaustive du nombre de lignes entre
--  PostgreSQL (main) et ce fork MySQL, table par table (Phase 4) :
--  departements, familles_achat et lignes_budgetaires etaient vides ici
--  alors qu'elles portent les referentiels reels de l'organisation cote
--  main (pas des exemples inventes) :
--
--  - familles_achat (35 lignes) : catalogue de familles d'achat aligne
--    sur le plan comptable SYSCOHADA (compte_comptable), reference
--    comptable standard, pas une configuration ad hoc.
--  - departements (3 lignes) : structure organisationnelle reelle
--    (Operations / Administration / Achats). Cette branche VPS deploie
--    la MEME application pour la MEME organisation (EMU-CI) que main
--    (cf. DEPLOY-VPS-MYSQL.md) — ce n'est pas un produit multi-tenant
--    generique a laisser vide pour un client inconnu.
--  - lignes_budgetaires (6 lignes, exercice 2026) : lignes budgetaires
--    de depart par famille, non rattachees a un departement precis
--    (departement_id NULL sur les 6) — configuration de reference, pas
--    une donnee transactionnelle.
--
--  Sans elles, le module Achats est inutilisable : ach_creer_feb() exige
--  un departement et une famille par ligne des la soumission.
--
--  Volontairement laisse de cote (donnees transactionnelles propres a
--  chaque base, jamais a resynchroniser) : audit_log, distribution_lignes,
--  distributions_site, livraisons_consommables, receptions_consommables,
--  receptions_fournisseur, receptions_site, sessions, users.
-- ============================================================

INSERT IGNORE INTO departements (code, label, ordre, actif) VALUES
    ('OPERATION',      'Opérations',     0, 1),
    ('ADMINISTRATION', 'Administration', 0, 1),
    ('ACHAT',          'Achats',         0, 1);

INSERT IGNORE INTO familles_achat (code, libelle, actif, compte_comptable) VALUES
    ('FOURN_BUR',        'Fournitures de bureau',                        1, '605'),
    ('CONSO_IT',         'Consommables informatiques',                   1, '604'),
    ('EQUIP',            'Équipements',                                  1, '2442'),
    ('PRESTA_SVC',       'Prestations de services',                      1, '638'),
    ('TRANSPORT',        'Transport / Logistique',                       1, '611'),
    ('MAINTENANCE',      'Maintenance',                                  1, '624'),
    ('CONSO_OP',         'Consommables opérations (rivets)',             1, '604'),
    ('RESTAURATION',     'Restauration',                                 1, '605'),
    ('ENTRETIEN',        "Fournitures d'entretien",                      1, '605'),
    ('ACHAT_MARCH',      'Achats de marchandises',                       1, '601'),
    ('MATIERES_PREM',    'Achats de matières premières',                 1, '602'),
    ('EMBALLAGES',       "Achats d'emballages",                          1, '608'),
    ('TRANSPORT_VENTE',  'Transport sur ventes',                         1, '612'),
    ('TRANSPORT_TIERS',  'Transport pour compte de tiers',                1, '613'),
    ('TRANSPORT_PERSO',  'Transport du personnel',                       1, '614'),
    ('SOUS_TRAITANCE',   'Sous-traitance générale',                      1, '621'),
    ('LOCATIONS',        'Locations et charges locatives',               1, '622'),
    ('ASSURANCES',       "Primes d'assurance",                           1, '625'),
    ('ETUDES_DOC',       'Études, recherches et documentation',          1, '626'),
    ('PUBLICITE',        'Publicité, publications, relations publiques', 1, '627'),
    ('TELECOM',          'Frais de télécommunications',                  1, '628'),
    ('FRAIS_BANCAIRES',  'Frais bancaires',                              1, '631'),
    ('HONORAIRES',       "Rémunérations d'intermédiaires et honoraires", 1, '632'),
    ('FORMATION',        'Frais de formation du personnel',              1, '633'),
    ('LICENCES',         'Redevances pour brevets, licences, logiciels', 1, '634'),
    ('PERSONNEL_EXT',    'Rémunérations de personnel extérieur',         1, '637'),
    ('IMPOTS_DIRECTS',   'Impôts et taxes directs',                      1, '641'),
    ('IMPOTS_INDIRECTS', 'Impôts et taxes indirects',                    1, '645'),
    ('AUTRES_IMPOTS',    'Autres impôts et taxes',                       1, '647'),
    ('CHARGES_DIVERSES', 'Charges diverses',                             1, '658'),
    ('OUTILLAGE',        'Matériel et outillage industriel',             1, '241'),
    ('MATERIEL_BUREAU',  'Matériel de bureau',                           1, '2441'),
    ('MOBILIER_BUREAU',  'Mobilier de bureau',                           1, '2443'),
    ('VEHICULES',        'Matériel de transport',                        1, '245'),
    ('AUTRE_MATERIEL',   'Autres matériels',                             1, '248');

INSERT IGNORE INTO lignes_budgetaires (code_comptable, designation, exercice, enveloppe, comportement, famille_id, departement_id, actif)
SELECT '6061', 'Fournitures de bureau',      2026, NULL, 'alerte', id, NULL, 1 FROM familles_achat WHERE code='FOURN_BUR';
INSERT IGNORE INTO lignes_budgetaires (code_comptable, designation, exercice, enveloppe, comportement, famille_id, departement_id, actif)
SELECT '6068', 'Consommables informatiques', 2026, NULL, 'alerte', id, NULL, 1 FROM familles_achat WHERE code='CONSO_IT';
INSERT IGNORE INTO lignes_budgetaires (code_comptable, designation, exercice, enveloppe, comportement, famille_id, departement_id, actif)
SELECT '2183', 'Équipements informatiques',  2026, NULL, 'alerte', id, NULL, 1 FROM familles_achat WHERE code='EQUIP';
INSERT IGNORE INTO lignes_budgetaires (code_comptable, designation, exercice, enveloppe, comportement, famille_id, departement_id, actif)
SELECT '6226', 'Prestations de services',    2026, NULL, 'alerte', id, NULL, 1 FROM familles_achat WHERE code='PRESTA_SVC';
INSERT IGNORE INTO lignes_budgetaires (code_comptable, designation, exercice, enveloppe, comportement, famille_id, departement_id, actif)
SELECT '6241', 'Transport / Logistique',     2026, NULL, 'alerte', id, NULL, 1 FROM familles_achat WHERE code='TRANSPORT';
INSERT IGNORE INTO lignes_budgetaires (code_comptable, designation, exercice, enveloppe, comportement, famille_id, departement_id, actif)
SELECT '6152', 'Maintenance',                2026, NULL, 'alerte', id, NULL, 1 FROM familles_achat WHERE code='MAINTENANCE';
