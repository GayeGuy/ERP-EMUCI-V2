-- ============================================================
--  Migration : fusion des départements dupliqués par la casse du code
--  Compatible PostgreSQL / Neon — idempotente.
--
--  migration_achats_08_syscohada.sql insère OPERATION/ADMINISTRATION/ACHAT
--  via ON CONFLICT (code) DO NOTHING — une garde sensible à la casse. Sur
--  toute base où ces départements existaient déjà avec un code en casse
--  différente (ex. 'ope'/'ad'/'achat', antérieurs au module Achats), la
--  garde ne matche jamais et la migration insère un doublon plutôt que de
--  réutiliser l'existant. Constaté en local (2026-08-18) : trois paires,
--  l'ancien portant les rattachements réels (membres, lignes budgétaires),
--  le nouveau vide. Probablement présent aussi sur Neon, puisque c'est la
--  migration elle-même qui a le défaut, pas une manipulation locale — à
--  vérifier et rejouer là-bas.
--
--  Stratégie : le département "canonique" par groupe est celui dont le
--  code correspond exactement (casse comprise) à une des trois valeurs
--  semées par migration_achats_08 — c'est celui que le reste du module
--  Achats (budget, SYSCOHADA, dashboards) a été écrit et vérifié contre.
--  Tout autre département de même code (casse différente) lui cède ses
--  rattachements, puis est désactivé (jamais supprimé : un id existant
--  peut déjà être référencé ailleurs par du texte libre, un export, etc.).
-- ============================================================
BEGIN;

DO $$
DECLARE
    canon RECORD;
    dup   RECORD;
BEGIN
    FOR canon IN
        SELECT id, code FROM departements
        WHERE code IN ('OPERATION', 'ADMINISTRATION', 'ACHAT')
    LOOP
        FOR dup IN
            SELECT id FROM departements
             WHERE id <> canon.id AND actif = 1 AND UPPER(code) = UPPER(canon.code)
        LOOP
            -- user_departements : PK (user_id, departement_id) — ne pas créer
            -- de doublon si l'utilisateur est déjà rattaché au canonique.
            UPDATE user_departements ud SET departement_id = canon.id
             WHERE ud.departement_id = dup.id
               AND NOT EXISTS (
                    SELECT 1 FROM user_departements ud2
                     WHERE ud2.user_id = ud.user_id AND ud2.departement_id = canon.id
               );
            DELETE FROM user_departements WHERE departement_id = dup.id;

            -- lignes_budgetaires : UNIQUE (departement_id, famille_id, exercice)
            -- — en cas de conflit avec une ligne déjà portée par le canonique,
            -- la ligne du doublon est désactivée plutôt que perdue en silence :
            -- un administrateur devra arbitrer laquelle des deux enveloppes
            -- garder.
            UPDATE lignes_budgetaires lb SET departement_id = canon.id
             WHERE lb.departement_id = dup.id
               AND NOT EXISTS (
                    SELECT 1 FROM lignes_budgetaires lb2
                     WHERE lb2.departement_id = canon.id
                       AND lb2.famille_id = lb.famille_id AND lb2.exercice = lb.exercice
               );
            UPDATE lignes_budgetaires SET actif = 0 WHERE departement_id = dup.id;

            -- budget_validations : UNIQUE (departement_id, exercice) — même
            -- logique, le statut du doublon ne doit jamais écraser un statut
            -- déjà posé sur le canonique.
            UPDATE budget_validations bv SET departement_id = canon.id
             WHERE bv.departement_id = dup.id
               AND NOT EXISTS (
                    SELECT 1 FROM budget_validations bv2
                     WHERE bv2.departement_id = canon.id AND bv2.exercice = bv.exercice
               );
            DELETE FROM budget_validations WHERE departement_id = dup.id;

            -- stock_departement : UNIQUE (article_id, departement_id) — le
            -- doublon éventuel additionne sa quantité au canonique plutôt que
            -- de la faire disparaître (c'est du stock physique réel).
            UPDATE stock_departement sd SET quantite = sd.quantite + COALESCE((
                    SELECT quantite FROM stock_departement sd2
                     WHERE sd2.article_id = sd.article_id AND sd2.departement_id = dup.id
                ), 0)
             WHERE sd.departement_id = canon.id
               AND EXISTS (SELECT 1 FROM stock_departement sd3 WHERE sd3.article_id = sd.article_id AND sd3.departement_id = dup.id);
            INSERT INTO stock_departement (article_id, departement_id, quantite)
                SELECT article_id, canon.id, quantite FROM stock_departement sd
                 WHERE sd.departement_id = dup.id
                   AND NOT EXISTS (SELECT 1 FROM stock_departement sd2 WHERE sd2.article_id = sd.article_id AND sd2.departement_id = canon.id);
            DELETE FROM stock_departement WHERE departement_id = dup.id;

            -- feb.departement_id et di_roles.departement_id : simples
            -- attributs, aucune contrainte d'unicité à respecter.
            UPDATE feb SET departement_id = canon.id WHERE departement_id = dup.id;
            UPDATE di_roles SET departement_id = canon.id WHERE departement_id = dup.id;

            UPDATE departements SET actif = 0 WHERE id = dup.id;
        END LOOP;
    END LOOP;
END $$;

COMMIT;
