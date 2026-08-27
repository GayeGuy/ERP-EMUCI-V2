-- ============================================================
--  Bon de transfert obligatoire à la réception département (étape 3/3)
-- ============================================================
--
--  Symétrique du bon de livraison déjà obligatoire à la réception magasin
--  (feb_receptions.bon_livraison) : la confirmation par le N+1 du
--  département destinataire doit elle aussi être appuyée d'une pièce
--  (bon de transfert interne magasin -> département), pas seulement d'une
--  déclaration sur l'honneur.

ALTER TABLE feb_receptions_departement ADD COLUMN IF NOT EXISTS bon_transfert VARCHAR(255) DEFAULT NULL;
