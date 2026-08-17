-- ============================================================
--  Migration : module Achats — validation du budget par département
--  Compatible PostgreSQL / Neon — idempotente.
--  Dépend de migration_achats_01_schema.sql (lignes_budgetaires) et de
--  migration_achats_03_permissions.sql.
--
--  Retour observations spec (2026-08-17) : le contrôle des dépenses vs
--  budget disponible existait déjà (ach_controle_budget). Manquaient la
--  validation du budget par service en amont, sa clôture, et son usage
--  comme référence figée pour le contrôle. Décision retenue avec
--  l'utilisateur : RAF/DAF saisissent (brouillon) et soumettent, le PDG
--  valide — la validation EST la clôture, un seul geste verrouille.
--  2026-08
-- ============================================================
BEGIN;

-- ══════════════════════════════════════════════════════════════
--  1. budget_validations — statut par département × exercice, gouverne
--     TOUTES les lignes budgétaires de ce couple d'un coup (pas de statut
--     ligne par ligne à resynchroniser). Absence de ligne = brouillon
--     implicite (cf. ach_budget_validation() dans includes/achats.php).
-- ══════════════════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS budget_validations (
    id             serial PRIMARY KEY,
    departement_id integer NOT NULL REFERENCES departements(id) ON DELETE CASCADE,
    exercice       integer NOT NULL,
    statut         character varying(20) NOT NULL DEFAULT 'brouillon',
    soumis_par     integer REFERENCES users(id),
    soumis_le      timestamp,
    valide_par     integer REFERENCES users(id),
    valide_le      timestamp,
    motif_rejet    text,
    rejete_par     integer REFERENCES users(id),
    rejete_le      timestamp,
    CONSTRAINT budget_validations_uk_dept_exercice UNIQUE (departement_id, exercice),
    CONSTRAINT budget_validations_statut_check CHECK (statut IN ('brouillon','soumis','valide','rejete'))
);

-- ══════════════════════════════════════════════════════════════
--  2. Droits — RAF/DAF n'avaient aucun accès à achats_param (le
--     paramétrage leur était explicitement fermé, migration_achats_03).
--     Ils gagnent ici la lecture ET l'écriture, mais seulement pour
--     saisir/soumettre leur propre budget — la portée (leur département)
--     et le verrou une fois validé sont appliqués côté code
--     (ach_perimetre_departements + ach_budget_editable), pas ici.
--     Le PDG (lecteur) gagne la lecture seule : il consulte et valide via
--     une règle métier dédiée (ach_est_validateur_budget), jamais un
--     droit d'écriture générique — un lecteur ne dépose jamais rien
--     (cf. ach_peut_creer()).
-- ══════════════════════════════════════════════════════════════
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, 'achats_param', 1, 0, 1, 0, 0
FROM roles r
WHERE r.slug IN ('raf', 'daf')
ON CONFLICT (role_id, module) DO UPDATE SET can_read = 1, can_update = 1;

INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, 'achats_param', 1, 0, 0, 0, 0
FROM roles r
WHERE r.slug = 'lecteur'
ON CONFLICT (role_id, module) DO UPDATE SET can_read = 1;

-- ══════════════════════════════════════════════════════════════
--  3. Bascule des budgets déjà actifs — décision explicite de
--     l'utilisateur (2026-08-17) : sans ce lot, ach_controle_budget()
--     traiterait toute ligne budgétaire déjà en place comme "brouillon"
--     (non contrôlée) tant que personne ne l'a validée à la main, ce qui
--     éteindrait silencieusement le contrôle alerte/blocage déjà en
--     service. On considère qu'un département/exercice qui porte déjà au
--     moins une ligne active est de facto validé — valide_par NULL marque
--     explicitement que ce n'est pas un vrai geste du PDG, juste un
--     bootstrap de migration (traçable, distinct d'une validation réelle).
-- ══════════════════════════════════════════════════════════════
INSERT INTO budget_validations (departement_id, exercice, statut, valide_le)
SELECT DISTINCT lb.departement_id, lb.exercice, 'valide', CURRENT_TIMESTAMP
FROM lignes_budgetaires lb
WHERE lb.actif = 1 AND lb.departement_id IS NOT NULL
ON CONFLICT (departement_id, exercice) DO NOTHING;

COMMIT;
