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
