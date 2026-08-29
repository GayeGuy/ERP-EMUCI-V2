-- ============================================================
--  Etend le mecanisme d'inventaire (sessions, comptage, validation,
--  resolution d'ecarts, sous-workflow de correction) — deja en place
--  pour Bobines puis Rivets — a PMMA. PMMA = stock agrege par
--  (site_id,type_pmma) dans stock_pmma_site, comme les rivets, mais
--  type_pmma est un texte libre (pas 2 valeurs fixes) : la liste des
--  types d'un inventaire depend de ce qui existe en stock sur le site
--  au moment de la creation, exactement comme inv_creer_rivets().
-- ============================================================

CREATE TABLE inventaires_pmma (
    id SERIAL PRIMARY KEY,
    site_id INT REFERENCES sites(id) ON DELETE SET NULL,
    date_inventaire DATE NOT NULL,
    type_inventaire TEXT NOT NULL DEFAULT 'journalier',
    statut TEXT NOT NULL DEFAULT 'brouillon',
    nb_types INT DEFAULT 0,
    nb_ecarts INT DEFAULT 0,
    total_quantite_systeme INT DEFAULT 0,
    total_quantite_physique INT DEFAULT 0,
    notes TEXT,
    cree_par INT REFERENCES users(id) ON DELETE SET NULL,
    valide_par INT REFERENCES users(id) ON DELETE SET NULL,
    valide_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    session_id INT REFERENCES inventaire_sessions(id)
);
CREATE INDEX idx_inventaires_pmma_site ON inventaires_pmma(site_id);
CREATE INDEX idx_inventaires_pmma_statut ON inventaires_pmma(statut);
CREATE INDEX idx_inventaires_pmma_date ON inventaires_pmma(date_inventaire);
CREATE INDEX idx_inventaires_pmma_session ON inventaires_pmma(session_id);

CREATE TABLE inventaire_details_pmma (
    id SERIAL PRIMARY KEY,
    inventaire_id INT NOT NULL REFERENCES inventaires_pmma(id) ON DELETE CASCADE,
    type_pmma VARCHAR(50) NOT NULL,
    stock_systeme INT NOT NULL,
    stock_physique INT NOT NULL DEFAULT 0,
    ecart INT NOT NULL DEFAULT 0,
    ecart_connu_avant INT DEFAULT 0,
    notes TEXT,
    UNIQUE (inventaire_id, type_pmma)
);

CREATE TABLE ecarts_pmma (
    id SERIAL PRIMARY KEY,
    site_id INT NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    type_pmma VARCHAR(50) NOT NULL,
    date_constat DATE NOT NULL,
    stock_systeme INT NOT NULL,
    stock_physique INT NOT NULL,
    ecart INT NOT NULL,
    motif TEXT,
    source TEXT DEFAULT 'inventaire',
    inventaire_id INT REFERENCES inventaires_pmma(id) ON DELETE SET NULL,
    statut TEXT NOT NULL DEFAULT 'ouvert',
    resolu_at TIMESTAMP,
    resolu_par INT REFERENCES users(id),
    created_by INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_ecarts_pmma_site ON ecarts_pmma(site_id);
CREATE INDEX idx_ecarts_pmma_statut ON ecarts_pmma(statut);
CREATE INDEX idx_ecarts_pmma_date ON ecarts_pmma(date_constat);

-- Miroir de inventaire_corrections / inventaire_corrections_rivets.
CREATE TABLE inventaire_corrections_pmma (
    id SERIAL PRIMARY KEY,
    detail_id INT NOT NULL REFERENCES inventaire_details_pmma(id) ON DELETE CASCADE,
    inventaire_id INT NOT NULL REFERENCES inventaires_pmma(id) ON DELETE CASCADE,
    type_pmma VARCHAR(50) NOT NULL,
    site_id INT NOT NULL REFERENCES sites(id),
    stock_physique_actuel INT NOT NULL,
    valeur_proposee INT,
    motif TEXT NOT NULL,
    demandeur_id INT NOT NULL REFERENCES users(id),
    statut VARCHAR(20) NOT NULL DEFAULT 'en_attente'
        CHECK (statut IN ('en_attente','autorise','refuse','traite')),
    valeur_finale INT,
    reponse TEXT,
    traite_par INT REFERENCES users(id),
    traite_at TIMESTAMP,
    type VARCHAR(20) NOT NULL DEFAULT 'demande_site'
        CHECK (type IN ('demande_site','demande_autorisation')),
    autorise_par INT REFERENCES users(id),
    autorise_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_inv_corr_pmma_detail ON inventaire_corrections_pmma(detail_id);
CREATE INDEX idx_inv_corr_pmma_inventaire ON inventaire_corrections_pmma(inventaire_id);

-- ── Permissions : ecarts_pmma reprend les memes roles que ecarts_bobines
--    aujourd'hui ; inventaire_pmma reprend les memes droits que le module
--    pmma existant (memes profils qui gerent deja le stock PMMA au quotidien).
INSERT INTO permissions (role_id, module, can_read)
SELECT r.id, 'ecarts_pmma', 1
FROM roles r
WHERE r.slug IN ('controleur_production','gestionnaire_stock','gestionnaire_stock_bobines','lecteur','superviseur_operation')
ON CONFLICT (role_id, module) DO NOTHING;

INSERT INTO permissions (role_id, module, can_read, can_create, can_update)
SELECT r.id, 'inventaire_pmma', p.can_read, p.can_create, p.can_update
FROM permissions p JOIN roles r ON r.id = p.role_id
WHERE p.module = 'pmma'
ON CONFLICT (role_id, module) DO NOTHING;
