-- ============================================================
--  migration_equipements_statut_utilisation.sql  (PostgreSQL, rejouable)
--  Cycle de vie d'un équipement sur son site (pages/equipements.php,
--  action 'changer_statut') : deux nouveaux statuts de stock,
--  'en_utilisation' (sorti du stock, mis en service) et 'epuise'
--  (consommable terminé, retiré des comptes, fiche conservée).
-- ============================================================
ALTER TABLE equipements DROP CONSTRAINT IF EXISTS equipements_statut_stock_check;
ALTER TABLE equipements
    ADD CONSTRAINT equipements_statut_stock_check
    CHECK (statut_stock IN ('affecte','en_stock','hs','en_attente_affectation','affectation_en_cours','en_transit',
                            'en_utilisation','epuise'));
