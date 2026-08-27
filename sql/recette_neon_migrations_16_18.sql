-- ============================================================
--  Base de recette Achats — migrations 16 à 18
--  À coller dans l'éditeur SQL de Neon, sur la base de recette.
-- ============================================================
--
--  Complète une base déjà peuplée par recette_neon_base.sql (état du
--  18/08, migrations 1 à 15). Apporte ce que le code déployé attend :
--
--    16  feb.n1_user_id                  visa du supérieur hiérarchique
--    17  feb_lignes.nomenclature_id      pont FEB -> équipements (DAI)
--        equipements.departement_id
--        equipements.feb_ligne_id
--    18  equipement_affectations         circuit d'affectation validée
--        contrainte sur equipements.statut_stock
--
--  Les trois blocs sont idempotents (IF NOT EXISTS, gardes sur les
--  contraintes) : les rejouer ne casse rien. Chacun a sa propre
--  transaction — si l'un échoue, les précédents restent acquis.
--
--  Une requête de contrôle figure à la fin.
-- ============================================================


-- ─────────────────────────────────────────────────────────
--  migration_achats_16_visa_n1.sql
-- ─────────────────────────────────────────────────────────
-- ============================================================
--  Migration : visa du supérieur hiérarchique (N+1) sur la FEB
--  Compatible PostgreSQL / Neon — idempotente.
--
--  Le modèle papier (FEB EMUCI.xlsx, feuille FEB DEMANDEUR) porte deux
--  cases de visa : DEMANDEUR et SUPERIEUR HIERARCHIQUE. L'application
--  n'implémentait que le circuit aval, construit sur le montant seul, et
--  seize rôles sur dix-sept pouvaient engager l'enveloppe d'un département
--  sans que personne ne l'endosse.
--
--  Le N+1 est résolu au lancement de la validation, pas à la volée : le
--  circuit est figé à cet instant (RG-11), et un changement de responsable
--  en cours de route ne doit pas déplacer une signature déjà attendue.
--  C'est le même principe que workflow_snapshot, appliqué à la personne.
--
--  La désignation elle-même vit déjà dans user_departements.is_n1 : rien
--  à créer de ce côté.
-- ============================================================
BEGIN;

ALTER TABLE feb ADD COLUMN IF NOT EXISTS n1_user_id integer;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'feb_n1_user_id_fkey'
    ) THEN
        ALTER TABLE feb
            ADD CONSTRAINT feb_n1_user_id_fkey
            FOREIGN KEY (n1_user_id) REFERENCES users(id);
    END IF;
END $$;

COMMENT ON COLUMN feb.n1_user_id IS
    'Supérieur hiérarchique résolu au lancement de la validation (user_departements.is_n1 du département de la FEB). NULL si le département n''en a pas, ou si le demandeur est lui-même le N+1 : l''étape n1 est alors absente du circuit.';

COMMIT;


-- ─────────────────────────────────────────────────────────
--  migration_achats_17_equipements_dai.sql
-- ─────────────────────────────────────────────────────────
-- ============================================================
--  Migration : pont FEB -> équipements pour les lignes DAI (Étape 3)
--  Compatible PostgreSQL / Neon — idempotente.
--
--  Décision (2026-08-20) sur la question ouverte du plan : les lignes DAI
--  n'ont pas systématiquement d'article_id (achats.articles, référentiel
--  consommables) et, même quand elles en ont un, cela ne dit rien de la
--  nomenclature équipement (nomenclatures, référentiel distinct — clavier,
--  imprimante, unité centrale...) à utiliser pour créer un exemplaire.
--  Rattachement optionnel, à la réception seulement : si personne ne
--  choisit de nomenclature pour une ligne DAI, aucun exemplaire n'est créé
--  pour elle — la réception elle-même n'est jamais bloquée par ce choix.
-- ============================================================
BEGIN;

-- ══════════════════════════════════════════════════════════════
--  1. feb_lignes.nomenclature_id — rattachement optionnel, saisi au plus
--     tôt à la réception (pages/achats/receptions.php), jamais à la
--     création de la FEB. ON DELETE SET NULL : une nomenclature retirée
--     du référentiel ne doit pas faire disparaître la ligne elle-même.
-- ══════════════════════════════════════════════════════════════
ALTER TABLE feb_lignes ADD COLUMN IF NOT EXISTS nomenclature_id integer REFERENCES nomenclatures(id) ON DELETE SET NULL;

-- ══════════════════════════════════════════════════════════════
--  2. equipements.departement_id — le département qui a exprimé le
--     besoin (feb.departement_id), pour que la file d'attente
--     d'affectation (Étape 3c) et, plus tard, l'affectation elle-même
--     (Étape 4) sachent pour qui l'exemplaire a été acheté.
-- ══════════════════════════════════════════════════════════════
ALTER TABLE equipements ADD COLUMN IF NOT EXISTS departement_id integer REFERENCES departements(id) ON DELETE SET NULL;

-- ══════════════════════════════════════════════════════════════
--  3. equipements.feb_ligne_id — traçabilité vers la ligne de FEB
--     d'origine (même forme que feb_suivi.feb_ligne_id, déjà établi).
--     ON DELETE SET NULL, pas CASCADE : un exemplaire déjà réceptionné
--     est un bien physique réel, sa fiche ne doit jamais disparaître
--     parce qu'une ligne de FEB a été supprimée en amont.
-- ══════════════════════════════════════════════════════════════
ALTER TABLE equipements ADD COLUMN IF NOT EXISTS feb_ligne_id integer REFERENCES feb_lignes(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS equipements_feb_ligne_idx ON equipements(feb_ligne_id);

-- ══════════════════════════════════════════════════════════════
--  4. equipements.statut_stock — contrainte qui n'existait pas encore.
--     Quatre valeurs réellement en usage dans le code au moment de cette
--     migration : affecte / en_stock / hs (pages/equipements.php), plus
--     en_attente_affectation (nouvelle, posée par la réception d'une
--     ligne DAI rattachée à une nomenclature — Étape 3b). DO $$ ... $$ :
--     ADD CONSTRAINT n'a pas d'équivalent IF NOT EXISTS direct.
-- ══════════════════════════════════════════════════════════════
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'equipements_statut_stock_check'
    ) THEN
        ALTER TABLE equipements
            ADD CONSTRAINT equipements_statut_stock_check
            CHECK (statut_stock IN ('affecte', 'en_stock', 'hs', 'en_attente_affectation'));
    END IF;
END $$;

COMMIT;


-- ─────────────────────────────────────────────────────────
--  migration_achats_18_affectation_validee.sql
-- ─────────────────────────────────────────────────────────
-- ============================================================
--  Migration : affectation validée d'un équipement (Étape 4)
--  Compatible PostgreSQL / Neon — idempotente.
--
--  Décision retenue (2026-08-20) : pas de seconde grille de délégation —
--  réutilise les trois paliers existants (achat_paliers), déjà éprouvés
--  par le circuit de validation FEB et qui couvrent déjà nativement le
--  cas de l'immobilisation à plusieurs millions. Le montant qui détermine
--  le palier est equipements.prix_achat (posé à la réception, Étape 3b),
--  pas le montant de la FEB entière.
-- ============================================================
BEGIN;

-- ══════════════════════════════════════════════════════════════
--  1. equipement_affectations — une proposition d'affectation par
--     exemplaire, avec son propre circuit de signature (même forme que
--     feb.workflow_snapshot/signatures/historique, mais table à part :
--     un exemplaire n'est pas une FEB, et plusieurs propositions peuvent
--     se succéder sur le même exemplaire si l'une est rejetée).
-- ══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS equipement_affectations (
    id                 SERIAL PRIMARY KEY,
    equipement_id      integer NOT NULL REFERENCES equipements(id) ON DELETE CASCADE,
    site_id            integer REFERENCES sites(id) ON DELETE SET NULL,
    utilisateur_id     integer REFERENCES users(id) ON DELETE SET NULL,
    statut             varchar(20) NOT NULL DEFAULT 'en_validation'
                        CHECK (statut IN ('en_validation', 'validee', 'rejetee')),
    workflow_snapshot  jsonb NOT NULL DEFAULT '[]',
    signatures         jsonb NOT NULL DEFAULT '[]',
    historique         jsonb NOT NULL DEFAULT '[]',
    etape_actuelle     smallint NOT NULL DEFAULT 0,
    etape_rejet        smallint DEFAULT NULL,
    motif_rejet        text DEFAULT NULL,
    proposee_par       integer REFERENCES users(id) ON DELETE SET NULL,
    proposee_le        timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valide_le          timestamp DEFAULT NULL
);
CREATE INDEX IF NOT EXISTS equipement_affectations_equip_idx  ON equipement_affectations(equipement_id);
CREATE INDEX IF NOT EXISTS equipement_affectations_statut_idx ON equipement_affectations(statut);

-- ══════════════════════════════════════════════════════════════
--  2. equipements.statut_stock — cinquième valeur : un exemplaire dont
--     l'affectation est proposée n'est plus « en_attente_affectation »
--     (il ne doit pas pouvoir être proposé une seconde fois en parallèle)
--     mais n'est pas encore « affecte » (la validation n'a pas abouti).
--     DROP puis ADD : pas de ALTER TABLE ... ADD VALUE TO CHECK direct.
-- ══════════════════════════════════════════════════════════════
ALTER TABLE equipements DROP CONSTRAINT IF EXISTS equipements_statut_stock_check;
ALTER TABLE equipements
    ADD CONSTRAINT equipements_statut_stock_check
    CHECK (statut_stock IN ('affecte', 'en_stock', 'hs', 'en_attente_affectation', 'affectation_en_cours'));

COMMIT;


-- ─────────────────────────────────────────────────────────
--  Contrôle — les quatre lignes doivent afficher « ok »
-- ─────────────────────────────────────────────────────────
SELECT 'feb.n1_user_id'              AS objet,
       CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns
                          WHERE table_name='feb' AND column_name='n1_user_id')
            THEN 'ok' ELSE 'MANQUANT' END AS etat
UNION ALL
SELECT 'feb_lignes.nomenclature_id',
       CASE WHEN EXISTS (SELECT 1 FROM information_schema.columns
                          WHERE table_name='feb_lignes' AND column_name='nomenclature_id')
            THEN 'ok' ELSE 'MANQUANT' END
UNION ALL
SELECT 'equipements.departement_id + feb_ligne_id',
       CASE WHEN (SELECT count(*) FROM information_schema.columns
                   WHERE table_name='equipements'
                     AND column_name IN ('departement_id','feb_ligne_id')) = 2
            THEN 'ok' ELSE 'MANQUANT' END
UNION ALL
SELECT 'table equipement_affectations',
       CASE WHEN to_regclass('public.equipement_affectations') IS NOT NULL
            THEN 'ok' ELSE 'MANQUANT' END;


-- ═════════════════════════════════════════════════════════════
--  BLOC OPTIONNEL — désigner les supérieurs hiérarchiques
-- ═════════════════════════════════════════════════════════════
--
--  Sans au moins un N+1 désigné, l'étape d'endossement n'apparaît jamais
--  et vos testeurs ne verront rien de la nouveauté. La désignation vit
--  dans user_departements.is_n1.
--
--  Un demandeur ne s'endosse pas lui-même : si le seul membre d'un
--  département en est aussi le responsable, ses demandes partent
--  directement aux Achats. C'est pourquoi on crée ici un responsable
--  distinct pour les Opérations, dont testoperation est le demandeur.
--
--  Mot de passe du compte créé : Recette@2026 (comme les autres).

-- Responsable des Opérations, distinct du demandeur
INSERT INTO users (nom, prenom, email, password_hash, role_id, site_id, actif)
SELECT 'RESPONSABLE', 'Operations', 'resp.operations@recette.local',
       '$2y$12$ck8ok5fq4X8j5HT3FuV91.h6.a6sG491seDQsaBvdrBzwRgaIbN7i',
       r.id, s.id, 1
FROM roles r, sites s
WHERE r.slug = 'superviseur_operation' AND s.type = 'siege'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email = 'resp.operations@recette.local');

INSERT INTO user_departements (user_id, departement_id, is_n1)
SELECT u.id, d.id, 1
FROM users u, departements d
WHERE u.email = 'resp.operations@recette.local' AND d.code = 'OPERATION'
  AND NOT EXISTS (SELECT 1 FROM user_departements ud WHERE ud.user_id = u.id AND ud.departement_id = d.id);

-- Le RAF endosse pour l'Administration
UPDATE user_departements ud SET is_n1 = 1
FROM users u, departements d
WHERE ud.user_id = u.id AND ud.departement_id = d.id
  AND u.email = 'raf@recette.local' AND d.code = 'ADMINISTRATION';

-- L'acheteur endosse pour son propre service
UPDATE user_departements ud SET is_n1 = 1
FROM users u, departements d
WHERE ud.user_id = u.id AND ud.departement_id = d.id
  AND u.email = 'achat@recette.local' AND d.code = 'ACHAT';

-- Contrôle : qui endosse quoi
SELECT d.label AS departement, u.email AS responsable
FROM user_departements ud
JOIN users u ON u.id = ud.user_id
JOIN departements d ON d.id = ud.departement_id
WHERE ud.is_n1 = 1
ORDER BY d.label;
