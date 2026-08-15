-- ============================================================
--  Migration : module Achats — référentiels initiaux (seed)
--  Compatible PostgreSQL / Neon — idempotente (ON CONFLICT DO NOTHING).
--  Dépend de migration_achats_01_schema.sql (tables créées avant).
--
--  Valeurs de démarrage à ajuster via les écrans de paramétrage — pas
--  de source métier (classeur FEB, seuils réels de paliers, libellés
--  DAF/DAI/DAH) disponible au moment d'écrire cette migration. Chaque
--  bloc ci-dessous le redit là où c'est le cas.
--  2026-08
-- ============================================================
BEGIN;

-- ══════════════════════════════════════════════════════════════
--  Paliers de validation — 3 paliers (RAF / RAF+DAF / RAF+DAF+PDG)
--  Bornes de démarrage arbitraires (aucun seuil réel fourni) : à corriger
--  dans pages/achats/param_paliers.php avant mise en service.
--  Slug technique du rôle PDG : 'lecteur' (le libellé affiché a été
--  renommé en "PDG" par migration_lot2_role_pdg.sql, pas le slug — voir
--  DESIGN.md / CLAUDE.md, piège déjà rencontré une fois sur ce projet).
-- ══════════════════════════════════════════════════════════════
INSERT INTO achat_paliers (borne_min, borne_max, libelle, signataires, ordre, actif) VALUES
    (0,       500000,  'RAF seul',              '["raf"]'::jsonb,               1, 1),
    (500001,  5000000, 'RAF + DAF',             '["raf","daf"]'::jsonb,         2, 1),
    (5000001, NULL,    'RAF + DAF + PDG',       '["raf","daf","lecteur"]'::jsonb, 3, 1)
ON CONFLICT (libelle) DO NOTHING;

-- ══════════════════════════════════════════════════════════════
--  Types d'achat — DAF / DAI / DAH
--  Libellés provisoires (le sigle exact n'est pas documenté dans la
--  spécification) : à corriger dans pages/achats/param_familles.php.
-- ══════════════════════════════════════════════════════════════
INSERT INTO achat_types (code, libelle, actif) VALUES
    ('DAF', 'Demande d''Achat Fournitures',     1),
    ('DAI', 'Demande d''Achat Immobilisation',  1),
    ('DAH', 'Demande d''Achat Hors-marché',     1)
ON CONFLICT (code) DO NOTHING;

-- ══════════════════════════════════════════════════════════════
--  Familles d'achat — génériques, en l'absence du classeur FEB source.
-- ══════════════════════════════════════════════════════════════
INSERT INTO familles_achat (code, libelle, actif) VALUES
    ('FOURN_BUR',  'Fournitures de bureau',         1),
    ('CONSO_IT',   'Consommables informatiques',    1),
    ('EQUIP',      'Équipements',                   1),
    ('PRESTA_SVC', 'Prestations de services',        1),
    ('TRANSPORT',  'Transport / Logistique',        1),
    ('MAINTENANCE','Maintenance',                   1)
ON CONFLICT (code) DO NOTHING;

-- ══════════════════════════════════════════════════════════════
--  Lignes budgétaires / codes analytiques — génériques, enveloppe à NULL
--  (non plafonnée) en l'absence du classeur FEB source. Exercice 2026.
-- ══════════════════════════════════════════════════════════════
INSERT INTO lignes_budgetaires (code_comptable, designation, exercice, enveloppe, comportement, famille_id, actif)
SELECT v.code_comptable, v.designation, 2026, NULL, 'alerte', f.id, 1
FROM (VALUES
    ('6061', 'Fournitures de bureau',      'FOURN_BUR'),
    ('6068', 'Consommables informatiques', 'CONSO_IT'),
    ('2183', 'Équipements informatiques',  'EQUIP'),
    ('6226', 'Prestations de services',    'PRESTA_SVC'),
    ('6241', 'Transport / Logistique',     'TRANSPORT'),
    ('6152', 'Maintenance',                'MAINTENANCE')
) AS v(code_comptable, designation, famille_code)
JOIN familles_achat f ON f.code = v.famille_code
ON CONFLICT (code_comptable, exercice) DO NOTHING;

-- ══════════════════════════════════════════════════════════════
--  Paramètres généraux
-- ══════════════════════════════════════════════════════════════
INSERT INTO achat_parametres (cle, valeur, libelle, type, options) VALUES
    ('devise',                       'XOF',    'Devise',                                    'texte',  NULL),
    ('comportement_budget_defaut',   'alerte', 'Comportement par défaut sur dépassement',    'liste',  'aucun,alerte,blocage'),
    ('delai_livraison_standard_jours','15',    'Délai de livraison standard (jours)',        'nombre', NULL),
    ('seuil_retard_jours',           '5',      'Seuil de retard de livraison (jours)',       'nombre', NULL),
    ('max_lignes_feb',               '14',     'Nombre maximum de lignes par FEB',           'nombre', NULL)
ON CONFLICT (cle) DO NOTHING;

COMMIT;
