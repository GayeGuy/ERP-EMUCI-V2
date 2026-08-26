-- ============================================================
--  Demandes de modification sur une ligne d'inventaire bobines
-- ============================================================
--
--  Une ligne déjà saisie (et donc verrouillée) peut faire l'objet d'une
--  contestation par quiconque a un droit d'édition sur l'inventaire
--  (typiquement la personne qui a ouvert la session, en consultant le
--  détail d'un site depuis Sessions d'inventaire). La demande est
--  renvoyée au site concerné, qui répond avec la valeur corrigée — un
--  seul aller-retour, pas de circuit à plusieurs manches comme
--  corrections_bobines (qui est un mécanisme distinct, propre au
--  point journalier).
--
--  Idempotent.
-- ============================================================
BEGIN;

CREATE TABLE IF NOT EXISTS inventaire_corrections (
    id                     SERIAL PRIMARY KEY,
    detail_id              integer NOT NULL REFERENCES inventaire_details_bobines(id) ON DELETE CASCADE,
    inventaire_id          integer NOT NULL REFERENCES inventaires_bobines(id) ON DELETE CASCADE,
    bobine_id              integer NOT NULL REFERENCES op_bobines(id),
    site_id                integer NOT NULL REFERENCES sites(id),
    stock_physique_actuel  integer NOT NULL,
    valeur_proposee        integer DEFAULT NULL,
    motif                  text NOT NULL,
    demandeur_id           integer NOT NULL REFERENCES users(id),
    statut                 varchar(20) NOT NULL DEFAULT 'en_attente', -- en_attente | traite
    valeur_finale          integer DEFAULT NULL,
    reponse                text DEFAULT NULL,
    traite_par             integer DEFAULT NULL REFERENCES users(id),
    traite_at              timestamp DEFAULT NULL,
    created_at             timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS inventaire_corrections_detail_idx ON inventaire_corrections (detail_id);
CREATE INDEX IF NOT EXISTS inventaire_corrections_site_statut_idx ON inventaire_corrections (site_id, statut);

COMMIT;
