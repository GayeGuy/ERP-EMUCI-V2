-- ============================================================
--  Équipements : statut « perdu » (PostgreSQL)
-- ============================================================
--  Un équipement manquant à l'inventaire peut être déclaré perdu depuis
--  Écarts équipements : il sort du parc avec statut_stock = 'perdu'. La
--  contrainte des statuts autorisés l'ajoute. Rejouable.
-- ============================================================
ALTER TABLE equipements DROP CONSTRAINT IF EXISTS equipements_statut_stock_check;
ALTER TABLE equipements
    ADD CONSTRAINT equipements_statut_stock_check
    CHECK (statut_stock IN ('affecte','en_stock','hs','en_attente_affectation','affectation_en_cours','en_transit',
                            'en_utilisation','epuise','perdu'));
