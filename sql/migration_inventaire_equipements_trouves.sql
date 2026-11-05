-- ============================================================
--  migration_inventaire_equipements_trouves.sql  (PostgreSQL, rejouable)
--  Pendant un inventaire d'équipements, le coordinateur déclare un
--  équipement présent sur son site mais absent du système. La
--  déclaration attend la décision du gestionnaire de stock avant la validation : créer la
--  fiche (accepte), transférer l'équipement connu d'un autre site
--  (transfere), ou refuser (refuse, motif obligatoire).
--  Cf. includes/equipements_trouves.php.
-- ============================================================
CREATE TABLE IF NOT EXISTS inventaire_equipements_trouves (
    id SERIAL PRIMARY KEY,
    inventaire_id integer NOT NULL REFERENCES inventaires_equipements(id) ON DELETE CASCADE,
    nomenclature_id integer NOT NULL REFERENCES nomenclatures(id),
    marque varchar(100) DEFAULT NULL,
    modele varchar(100) DEFAULT NULL,
    numero_serie_origine varchar(100) DEFAULT NULL,
    etat varchar(50) NOT NULL DEFAULT 'bon',
    emplacement varchar(150) DEFAULT NULL,
    photo varchar(255) DEFAULT NULL,
    notes text,
    equipement_existant_id integer DEFAULT NULL REFERENCES equipements(id) ON DELETE SET NULL,
    statut varchar(20) NOT NULL DEFAULT 'en_attente'
        CHECK (statut IN ('en_attente','accepte','transfere','refuse')),
    motif_refus text,
    equipement_id integer DEFAULT NULL REFERENCES equipements(id) ON DELETE SET NULL,
    declare_par integer DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
    declare_at timestamp DEFAULT CURRENT_TIMESTAMP,
    traite_par integer DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
    traite_at timestamp DEFAULT NULL
);
CREATE INDEX IF NOT EXISTS idx_inv_equip_trouves_inv ON inventaire_equipements_trouves(inventaire_id);
CREATE INDEX IF NOT EXISTS idx_inv_equip_trouves_statut ON inventaire_equipements_trouves(statut);
