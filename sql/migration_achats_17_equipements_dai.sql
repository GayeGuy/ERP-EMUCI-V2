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
