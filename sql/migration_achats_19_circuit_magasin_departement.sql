-- ============================================================
--  Circuit magasin -> département en 3 étapes (consommables + équipements)
-- ============================================================
--
--  Jusqu'ici, réceptionner une ligne de suivi (pages/achats/receptions.php)
--  créditait stock_site ET stock_departement dans le même geste : le stock
--  était compté comme « au département » alors qu'il vient tout juste
--  d'arriver au magasin, avant même d'en être physiquement sorti.
--
--  Désormais trois étapes distinctes :
--    1. Réception magasin (inchangé sur le fond) — crédite stock_site.
--    2. Expédition vers le département — débite stock_site, ne crédite
--       rien tant que le département n'a pas confirmé.
--    3. Réception département (par le N+1, même porte que la réception
--       magasin lui accorde déjà) — crédite stock_departement.
--
--  Même principe côté équipements (DAI) : la validation de l'affectation
--  (Étape 4) passait directement à 'affecte'/'en_stock'. Un palier
--  intermédiaire 'en_transit' attend désormais la confirmation du N+1
--  avant le statut final.

BEGIN;

ALTER TABLE feb_suivi ADD COLUMN IF NOT EXISTS quantite_expediee INTEGER NOT NULL DEFAULT 0;
ALTER TABLE feb_suivi ADD COLUMN IF NOT EXISTS quantite_receptionnee_departement INTEGER NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS feb_expeditions (
    id              SERIAL PRIMARY KEY,
    feb_suivi_id    INTEGER NOT NULL REFERENCES feb_suivi(id) ON DELETE CASCADE,
    quantite        INTEGER NOT NULL,
    date_expedition DATE NOT NULL,
    observation     TEXT DEFAULT NULL,
    expedie_par     INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS feb_expeditions_suivi_idx ON feb_expeditions(feb_suivi_id);

CREATE TABLE IF NOT EXISTS feb_receptions_departement (
    id              SERIAL PRIMARY KEY,
    feb_suivi_id    INTEGER NOT NULL REFERENCES feb_suivi(id) ON DELETE CASCADE,
    quantite        INTEGER NOT NULL,
    date_reception  DATE NOT NULL,
    observation     TEXT DEFAULT NULL,
    recu_par        INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS feb_receptions_departement_suivi_idx ON feb_receptions_departement(feb_suivi_id);

-- 'en_transit' : affectation validée, équipement expédié vers le site
-- destinataire, en attente de confirmation physique par le N+1 du
-- département avant de passer à 'affecte'/'en_stock'.
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'equipements_statut_stock_check'
    ) THEN
        ALTER TABLE equipements DROP CONSTRAINT equipements_statut_stock_check;
    END IF;
    ALTER TABLE equipements
        ADD CONSTRAINT equipements_statut_stock_check
        CHECK (statut_stock IN ('affecte','en_stock','hs','en_attente_affectation','affectation_en_cours','en_transit'));
END $$;

COMMIT;
