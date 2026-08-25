-- ============================================================
--  Bon de livraison obligatoire à la confirmation de réception d'un
--  équipement (étape 3 du circuit magasin -> département, côté DAI)
-- ============================================================
--
--  Symétrique de feb_receptions.bon_livraison (consommables) et
--  feb_receptions_departement.bon_transfert : le N+1 qui confirme
--  qu'un exemplaire est bien arrivé doit joindre une pièce, pas
--  seulement cocher une case — convenu pour tout le circuit, manquait
--  encore côté équipements (ach_confirmer_reception_equipement()).

ALTER TABLE equipements ADD COLUMN IF NOT EXISTS bon_livraison VARCHAR(255) DEFAULT NULL;
