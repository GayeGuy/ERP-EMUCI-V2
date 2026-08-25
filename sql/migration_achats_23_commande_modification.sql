-- ============================================================
--  Demande de modification sur une ligne de commande interne
-- ============================================================
--
--  Troisième décision possible du superviseur opération (aux côtés de
--  Valider/Rejeter) : demander au coordinateur de corriger la quantité
--  d'une ligne avant de poursuivre, plutôt que de rejeter la ligne
--  entière. Colonne dédiée pour ne pas mélanger ce commentaire avec
--  motif_rejet (sémantique différente : « à corriger », pas « refusé »).

ALTER TABLE commande_lignes ADD COLUMN IF NOT EXISTS motif_modification TEXT DEFAULT NULL;
