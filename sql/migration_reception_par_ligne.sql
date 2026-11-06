-- ============================================================
--  Réception d'une commande ligne par ligne
--
--  À la réception, le coordinateur indique pour chaque ligne ce qu'il a
--  réellement reçu (totalité, partiel, rien) et, pour les bobines, coche
--  les numéros arrivés. Le site n'est crédité que du reçu ; chaque manque
--  ouvre un écart que le gestionnaire de stock tranche : remise au stock
--  d'origine, renvoi (nouvelle commande à expédier) ou perte.
--  Cf. includes/reception_commande.php, pages/commandes.php.
--
--  Idempotente : IF NOT EXISTS.
-- ============================================================
ALTER TABLE commande_lignes ADD COLUMN IF NOT EXISTS quantite_recue integer DEFAULT NULL;
ALTER TABLE commande_lignes ADD COLUMN IF NOT EXISTS motif_reception text;

CREATE TABLE IF NOT EXISTS commande_ecarts_reception (
    id serial PRIMARY KEY,
    commande_id integer NOT NULL REFERENCES commandes(id) ON DELETE CASCADE,
    commande_ligne_id integer NOT NULL REFERENCES commande_lignes(id) ON DELETE CASCADE,
    bobine_id integer DEFAULT NULL,
    quantite integer NOT NULL,
    motif text,
    statut varchar(20) NOT NULL DEFAULT 'ouvert'
        CHECK (statut IN ('ouvert','restitue','renvoye','perte')),
    declare_par integer DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
    declare_at timestamp DEFAULT CURRENT_TIMESTAMP,
    traite_par integer DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
    traite_at timestamp DEFAULT NULL,
    notes_traitement text,
    commande_renvoi_id integer DEFAULT NULL REFERENCES commandes(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_cmd_ecarts_rec_cmd ON commande_ecarts_reception(commande_id);
CREATE INDEX IF NOT EXISTS idx_cmd_ecarts_rec_statut ON commande_ecarts_reception(statut);
