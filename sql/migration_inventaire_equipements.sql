-- ============================================================
--  Etend le mecanisme d'inventaire (sessions, comptage, validation,
--  suivi des ecarts, sous-workflow de correction) — deja en place pour
--  Bobines, Rivets, PMMA — aux Equipements.
--
--  Difference structurelle majeure : un equipement est un objet
--  individuel SANS quantite (equipements.numero_serie_interne unique,
--  pas de colonne "quantite" comme stock_pmma_site/op_stock_rivets).
--  Le modele "stock systeme vs stock physique" ne s'applique donc pas :
--  l'inventaire est ici une checklist de PRESENCE — pour chaque
--  equipement affecte au site, le compteur confirme "Trouve" ou
--  "Manquant" (colonne trouve, tri-etat NULL/0/1) plutot qu'une
--  quantite. Consequence sur la resolution des ecarts (voir
--  inv_creer_equipements() / action "valider") : un equipement retrouve
--  referme l'ecart ouvert le concernant ; un equipement manquant EN
--  CREE un (ou laisse l'existant ouvert) — contrairement a
--  Bobines/Rivets/PMMA, la validation ne "corrige" pas automatiquement
--  la fiche equipement (pas de mutation de site_id/statut_stock), un
--  manquant reste une alerte a investiguer tant qu'il n'est pas
--  retrouve lors d'un inventaire suivant.
--
--  Perimetre d'un inventaire de site : tous les equipements actifs
--  affectes au site (categorie informatique + operationnel confondues),
--  memes criteres que la liste principale de pages/equipements.php
--  (WHERE site_id=? AND actif=1).
-- ============================================================

CREATE TABLE inventaires_equipements (
    id SERIAL PRIMARY KEY,
    site_id INT REFERENCES sites(id) ON DELETE SET NULL,
    date_inventaire DATE NOT NULL,
    type_inventaire TEXT NOT NULL DEFAULT 'journalier',
    statut TEXT NOT NULL DEFAULT 'brouillon',
    nb_equipements INT DEFAULT 0,
    nb_trouves INT DEFAULT 0,
    nb_ecarts INT DEFAULT 0,
    notes TEXT,
    cree_par INT REFERENCES users(id) ON DELETE SET NULL,
    valide_par INT REFERENCES users(id) ON DELETE SET NULL,
    valide_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    session_id INT REFERENCES inventaire_sessions(id)
);
CREATE INDEX idx_inventaires_equip_site ON inventaires_equipements(site_id);
CREATE INDEX idx_inventaires_equip_statut ON inventaires_equipements(statut);
CREATE INDEX idx_inventaires_equip_date ON inventaires_equipements(date_inventaire);
CREATE INDEX idx_inventaires_equip_session ON inventaires_equipements(session_id);

CREATE TABLE inventaire_details_equipements (
    id SERIAL PRIMARY KEY,
    inventaire_id INT NOT NULL REFERENCES inventaires_equipements(id) ON DELETE CASCADE,
    equipement_id INT NOT NULL REFERENCES equipements(id) ON DELETE CASCADE,
    trouve SMALLINT DEFAULT NULL,          -- NULL=non saisi, 1=trouvé, 0=manquant
    ecart_connu_avant SMALLINT DEFAULT 0,  -- déjà signalé manquant (écart ouvert non résolu)
    notes TEXT,
    UNIQUE (inventaire_id, equipement_id)
);

CREATE TABLE ecarts_equipements (
    id SERIAL PRIMARY KEY,
    site_id INT NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    equipement_id INT NOT NULL REFERENCES equipements(id) ON DELETE CASCADE,
    date_constat DATE NOT NULL,
    motif TEXT,
    source TEXT DEFAULT 'inventaire',
    inventaire_id INT REFERENCES inventaires_equipements(id) ON DELETE SET NULL,
    statut TEXT NOT NULL DEFAULT 'ouvert',
    resolu_at TIMESTAMP,
    resolu_par INT REFERENCES users(id),
    created_by INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_ecarts_equip_site ON ecarts_equipements(site_id);
CREATE INDEX idx_ecarts_equip_statut ON ecarts_equipements(statut);
CREATE INDEX idx_ecarts_equip_date ON ecarts_equipements(date_constat);
CREATE INDEX idx_ecarts_equip_equipement ON ecarts_equipements(equipement_id);

-- Miroir de inventaire_corrections_rivets/inventaire_corrections_pmma —
-- valeur_proposee/valeur_finale portent ici l'état "trouvé" (0/1), pas
-- une quantité.
CREATE TABLE inventaire_corrections_equipements (
    id SERIAL PRIMARY KEY,
    detail_id INT NOT NULL REFERENCES inventaire_details_equipements(id) ON DELETE CASCADE,
    inventaire_id INT NOT NULL REFERENCES inventaires_equipements(id) ON DELETE CASCADE,
    equipement_id INT NOT NULL REFERENCES equipements(id),
    site_id INT NOT NULL REFERENCES sites(id),
    valeur_actuelle SMALLINT,
    valeur_proposee SMALLINT,
    motif TEXT NOT NULL,
    demandeur_id INT NOT NULL REFERENCES users(id),
    statut VARCHAR(20) NOT NULL DEFAULT 'en_attente'
        CHECK (statut IN ('en_attente','autorise','refuse','traite')),
    valeur_finale SMALLINT,
    reponse TEXT,
    traite_par INT REFERENCES users(id),
    traite_at TIMESTAMP,
    type VARCHAR(20) NOT NULL DEFAULT 'demande_site'
        CHECK (type IN ('demande_site','demande_autorisation')),
    autorise_par INT REFERENCES users(id),
    autorise_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_inv_corr_equip_detail ON inventaire_corrections_equipements(detail_id);
CREATE INDEX idx_inv_corr_equip_inventaire ON inventaire_corrections_equipements(inventaire_id);

-- ── Permissions : ecarts_equipements reprend les memes roles que
--    ecarts_bobines aujourd'hui ; inventaire_equipements reprend les
--    memes droits que inventaire_bobines (compter des equipements sur
--    site est une tache operationnelle du meme profil que compter des
--    bobines/rivets/pmma, pas la meme chose que gerer les fiches
--    equipement — d'ou une reprise de inventaire_bobines et non de
--    equipements, qui exclut coordinateur_site en creation).
INSERT INTO permissions (role_id, module, can_read)
SELECT r.id, 'ecarts_equipements', 1
FROM roles r
WHERE r.slug IN ('controleur_production','gestionnaire_stock','gestionnaire_stock_bobines','lecteur','superviseur_operation')
ON CONFLICT (role_id, module) DO NOTHING;

INSERT INTO permissions (role_id, module, can_read, can_create, can_update)
SELECT r.id, 'inventaire_equipements', p.can_read, p.can_create, p.can_update
FROM permissions p JOIN roles r ON r.id = p.role_id
WHERE p.module = 'inventaire_bobines'
ON CONFLICT (role_id, module) DO NOTHING;
