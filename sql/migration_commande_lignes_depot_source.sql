-- ============================================================
--  migration_commande_lignes_depot_source.sql  (PostgreSQL, rejouable)
--  À l'expédition d'une commande (pages/commandes.php, action
--  'emettre_livraison'), le gestionnaire choisit pour chaque ligne
--  article / PMMA / rivets le dépôt d'où elle sort (site de type
--  'entrepot' ou 'magasin') : c'est ce dépôt qui est débité. Il est
--  tracé sur la ligne.
-- ============================================================
ALTER TABLE commande_lignes ADD COLUMN IF NOT EXISTS depot_source_id integer DEFAULT NULL
    REFERENCES sites(id);
