-- ============================================================
--  Commandes : préparation par l'entrepôt, livraison par un livreur.
--  Idempotente. 2026-11-28
-- ============================================================
--  Circuit d'une commande validée :
--   1. le gestionnaire de stock répartit les lignes (entrepôt, magasin,
--      sortie diverse) et prépare celles du magasin ;
--   2. le magasinier de l'entrepôt (profil magasinier_entrepot, rattaché à
--      son entrepôt par users.site_id) prépare les lignes de l'entrepôt et
--      peut choisir les bobines ; le site fournisseur prépare une sortie
--      diverse ;
--   3. commande prête (statut prete_enlevement) : le gestionnaire désigne
--      le livreur (profil livreur) ;
--   4. le livreur récupère la commande — le stock des dépôts est débité à ce
--      moment (statut en_cours_livraison) —, puis la livre au site avec une
--      photo du BL signé (statut livree_site) ;
--   5. le chef de site réceptionne.
--  Tant que cette migration n'est pas passée, l'ancien circuit (expédition
--  en une fois par le gestionnaire) reste en place.
-- ============================================================
INSERT INTO roles (nom, slug, description)
SELECT 'Magasinier Entrepôt', 'magasinier_entrepot', 'Prépare les lignes de commande servies par son entrepôt (rattaché à l''entrepôt par son site).'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE slug = 'magasinier_entrepot');
INSERT INTO roles (nom, slug, description)
SELECT 'Livreur', 'livreur', 'Récupère les commandes préparées, les achemine et les livre au site avec le BL signé.'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE slug = 'livreur');

INSERT INTO permissions (role_id, module, can_create, can_read, can_update, can_delete, can_export)
SELECT r.id, 'commandes', 0, 1, 0, 0, 0 FROM roles r
WHERE r.slug IN ('magasinier_entrepot', 'livreur')
  AND NOT EXISTS (SELECT 1 FROM permissions p WHERE p.role_id = r.id AND p.module = 'commandes');

-- Chaque rôle a une ligne pour la vue exécutive (PDG), ici sans accès
-- (contrôle « droit Vue exécutive (PDG) pour chaque rôle » de l'installateur).
INSERT INTO permissions (role_id, module, can_create, can_read, can_update, can_delete, can_export)
SELECT r.id, 'pdg_overview', 0, 0, 0, 0, 0 FROM roles r
WHERE r.slug IN ('magasinier_entrepot', 'livreur')
  AND NOT EXISTS (SELECT 1 FROM permissions p WHERE p.role_id = r.id AND p.module = 'pdg_overview');

ALTER TABLE commande_lignes ADD COLUMN IF NOT EXISTS prepare_par integer   DEFAULT NULL;
ALTER TABLE commande_lignes ADD COLUMN IF NOT EXISTS prepare_at  timestamp DEFAULT NULL;
ALTER TABLE commandes ADD COLUMN IF NOT EXISTS livreur_id         integer      DEFAULT NULL;
ALTER TABLE commandes ADD COLUMN IF NOT EXISTS livreur_affecte_at timestamp    DEFAULT NULL;
ALTER TABLE commandes ADD COLUMN IF NOT EXISTS livree_at          timestamp    DEFAULT NULL;
ALTER TABLE commandes ADD COLUMN IF NOT EXISTS photo_livraison    varchar(255) DEFAULT NULL;
CREATE INDEX IF NOT EXISTS idx_commandes_livreur ON commandes (livreur_id, statut);

COMMENT ON COLUMN commande_lignes.prepare_at IS 'Ligne préparée (quantité arrêtée dans quantite_livree) ; le stock est débité à l''enlèvement par le livreur.';
COMMENT ON COLUMN commandes.photo_livraison  IS 'Photo du BL signé par le site, jointe par le livreur (fichiers_archives, type bl).';
