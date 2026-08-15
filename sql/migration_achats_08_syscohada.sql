-- ============================================================
--  Migration : module Achats — référentiel comptable SYSCOHADA,
--  budget par département
--  Compatible PostgreSQL / Neon — idempotente.
--  Dépend de migration_achats_01_schema.sql (familles_achat,
--  lignes_budgetaires) et de migration_departements.sql (departements).
--
--  Quatre niveaux emboîtés, chacun paramétrable sans toucher au code :
--    compte comptable (SYSCOHADA) ← famille_achat ← article
--                                         ↑
--                                 département (budget)
--
--  familles_achat.compte_comptable est une colonne NOUVELLE, distincte de
--  familles_achat.code (le mnémonique qui compose déjà le code analytique
--  des FEB, RG-05 — jamais touché ici) : plusieurs familles partagent le
--  même compte (605 sert à la fois FOURN_BUR, RESTAURATION et ENTRETIEN),
--  donc le compte ne peut pas être le code lui-même.
--
--  Les comptes ci-dessous suivent la nomenclature SYSCOHADA révisé
--  (classes 60 à 65 et 24). Seuls les 9 comptes de la table fournie sont
--  garantis exacts pour ce contexte métier ; les 26 autres sont les
--  intitulés standards de la nomenclature, à faire valider par la
--  direction financière avant mise en service — même réserve que les
--  paliers et enveloppes semés par migration_achats_02_referentiels.sql.
--  2026-08
-- ============================================================
BEGIN;

-- ══════════════════════════════════════════════════════════════
--  1. familles_achat.compte_comptable
-- ══════════════════════════════════════════════════════════════
ALTER TABLE familles_achat ADD COLUMN IF NOT EXISTS compte_comptable varchar(10);

-- Les 6 familles existantes, migrées vers leurs comptes corrigés.
UPDATE familles_achat SET compte_comptable = '605'  WHERE code = 'FOURN_BUR';
UPDATE familles_achat SET compte_comptable = '604'  WHERE code = 'CONSO_IT';
UPDATE familles_achat SET compte_comptable = '2442' WHERE code = 'EQUIP';
UPDATE familles_achat SET compte_comptable = '638'  WHERE code = 'PRESTA_SVC';
UPDATE familles_achat SET compte_comptable = '611'  WHERE code = 'TRANSPORT';
UPDATE familles_achat SET compte_comptable = '624'  WHERE code = 'MAINTENANCE';

-- 35 familles au total : 9 de la table fournie (6 corrigées ci-dessus + 3
-- nouvelles) + 26 couvrant le reste des classes 60-65 et 24.
INSERT INTO familles_achat (code, libelle, compte_comptable, actif) VALUES
    -- Nouvelles familles de la table fournie
    ('CONSO_OP',      'Consommables opérations (rivets)',            '604',  1),
    ('RESTAURATION',  'Restauration',                                 '605',  1),
    ('ENTRETIEN',     'Fournitures d''entretien',                     '605',  1),
    -- Classe 60 — Achats
    ('ACHAT_MARCH',   'Achats de marchandises',                       '601',  1),
    ('MATIERES_PREM', 'Achats de matières premières',                 '602',  1),
    ('EMBALLAGES',    'Achats d''emballages',                         '608',  1),
    -- Classe 61 — Transports
    ('TRANSPORT_VENTE','Transport sur ventes',                        '612',  1),
    ('TRANSPORT_TIERS','Transport pour compte de tiers',               '613',  1),
    ('TRANSPORT_PERSO','Transport du personnel',                      '614',  1),
    -- Classe 62 — Services extérieurs A
    ('SOUS_TRAITANCE','Sous-traitance générale',                      '621',  1),
    ('LOCATIONS',     'Locations et charges locatives',               '622',  1),
    ('ASSURANCES',    'Primes d''assurance',                          '625',  1),
    ('ETUDES_DOC',    'Études, recherches et documentation',          '626',  1),
    ('PUBLICITE',     'Publicité, publications, relations publiques', '627',  1),
    ('TELECOM',       'Frais de télécommunications',                  '628',  1),
    -- Classe 63 — Services extérieurs B
    ('FRAIS_BANCAIRES','Frais bancaires',                             '631',  1),
    ('HONORAIRES',    'Rémunérations d''intermédiaires et honoraires','632',  1),
    ('FORMATION',     'Frais de formation du personnel',              '633',  1),
    ('LICENCES',      'Redevances pour brevets, licences, logiciels', '634',  1),
    ('PERSONNEL_EXT', 'Rémunérations de personnel extérieur',         '637',  1),
    -- Classe 64 — Impôts et taxes
    ('IMPOTS_DIRECTS','Impôts et taxes directs',                      '641',  1),
    ('IMPOTS_INDIRECTS','Impôts et taxes indirects',                  '645',  1),
    ('AUTRES_IMPOTS', 'Autres impôts et taxes',                       '647',  1),
    -- Classe 65 — Autres charges
    ('CHARGES_DIVERSES','Charges diverses',                           '658',  1),
    -- Classe 24 — Matériel
    ('OUTILLAGE',     'Matériel et outillage industriel',             '241',  1),
    ('MATERIEL_BUREAU','Matériel de bureau',                          '2441', 1),
    ('MOBILIER_BUREAU','Mobilier de bureau',                          '2443', 1),
    ('VEHICULES',     'Matériel de transport',                        '245',  1),
    ('AUTRE_MATERIEL','Autres matériels',                             '248',  1)
ON CONFLICT (code) DO NOTHING;

-- ══════════════════════════════════════════════════════════════
--  2. departements — OPERATION / ADMINISTRATION / ACHAT, les seuls
--     actifs pour le budget aujourd'hui (Bloc 0, point 11). N'écrase
--     rien si déjà créés via l'écran d'administration.
-- ══════════════════════════════════════════════════════════════
INSERT INTO departements (code, label) VALUES
    ('OPERATION',     'Opérations'),
    ('ADMINISTRATION','Administration'),
    ('ACHAT',         'Achats')
ON CONFLICT (code) DO NOTHING;

-- ══════════════════════════════════════════════════════════════
--  3. lignes_budgetaires.departement_id — le budget se pilote par
--     département (Bloc 0, point 1 : un seul département par FEB, celui
--     du demandeur — feb.departement_id existe déjà depuis migration_achats_01).
--     Contrainte d'unicité recomposée sur (departement_id, famille_id,
--     exercice) : c'est cette clé à trois qui identifie une enveloppe,
--     plus le code_comptable seul.
-- ══════════════════════════════════════════════════════════════
ALTER TABLE lignes_budgetaires ADD COLUMN IF NOT EXISTS departement_id integer REFERENCES departements(id) ON DELETE CASCADE;

ALTER TABLE lignes_budgetaires DROP CONSTRAINT IF EXISTS lignes_budgetaires_code_comptable_exercice_key;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'lignes_budgetaires_dept_famille_exercice_key'
    ) THEN
        ALTER TABLE lignes_budgetaires
            ADD CONSTRAINT lignes_budgetaires_dept_famille_exercice_key
            UNIQUE (departement_id, famille_id, exercice);
    END IF;
END $$;

-- ══════════════════════════════════════════════════════════════
--  4. articles.famille_id — rattachement au référentiel achat, pour que
--     le budget puisse remonter jusqu'à l'article (Bloc 4, point 23).
--     Reclassable à tout moment (point 9) : sans effet rétroactif, les
--     lignes de FEB déjà émises portent déjà leur propre famille_id.
-- ══════════════════════════════════════════════════════════════
ALTER TABLE articles ADD COLUMN IF NOT EXISTS famille_id integer REFERENCES familles_achat(id) ON DELETE SET NULL;

-- Backfill des 7 articles existants (Bloc 1, point 8).
UPDATE articles SET famille_id = (SELECT id FROM familles_achat WHERE code = 'RESTAURATION') WHERE code IN ('CAF', 'SCR', 'THE', 'SAGE');
UPDATE articles SET famille_id = (SELECT id FROM familles_achat WHERE code = 'ENTRETIEN')    WHERE code IN ('PH', 'LT');
UPDATE articles SET famille_id = (SELECT id FROM familles_achat WHERE code = 'CONSO_OP')     WHERE code = 'RVT';

COMMIT;
