-- ============================================================
--  Étend le mécanisme d'inventaire (sessions, comptage, validation,
--  résolution d'écarts, sous-workflow de correction) déjà en place pour
--  les Bobines aux Rivets. Rivets = stock agrégé par (site_id,type_rivet)
--  dans op_stock_rivets, pas d'objet individuel : le détail d'inventaire
--  clé donc sur type_rivet plutôt que sur un id de bobine.
--
--  Décision utilisateur (2026-08-28) : parité complète avec Bobines dès
--  cette première itération, y compris le sous-workflow de correction
--  (inventaire_corrections) — ne peut pas être réutilisé tel quel (FK
--  strictes vers bobine_id/inventaires_bobines/inventaire_details_bobines,
--  confirmé par \d inventaire_corrections), d'où inventaire_corrections_rivets
--  en miroir.
-- ============================================================

CREATE TABLE inventaires_rivets (
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
CREATE INDEX idx_inventaires_rivets_site ON inventaires_rivets(site_id);
CREATE INDEX idx_inventaires_rivets_statut ON inventaires_rivets(statut);
CREATE INDEX idx_inventaires_rivets_date ON inventaires_rivets(date_inventaire);
CREATE INDEX idx_inventaires_rivets_session ON inventaires_rivets(session_id);

CREATE TABLE inventaire_details_rivets (
    id SERIAL PRIMARY KEY,
    inventaire_id INT NOT NULL REFERENCES inventaires_rivets(id) ON DELETE CASCADE,
    type_rivet VARCHAR(20) NOT NULL,
    stock_systeme INT NOT NULL,
    stock_physique INT NOT NULL DEFAULT 0,
    ecart INT NOT NULL DEFAULT 0,
    ecart_connu_avant INT DEFAULT 0,
    notes TEXT,
    UNIQUE (inventaire_id, type_rivet)
);

CREATE TABLE ecarts_rivets (
    id SERIAL PRIMARY KEY,
    site_id INT NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    type_rivet VARCHAR(20) NOT NULL,
    date_constat DATE NOT NULL,
    stock_systeme INT NOT NULL,
    stock_physique INT NOT NULL,
    ecart INT NOT NULL,
    motif TEXT,
    source TEXT DEFAULT 'inventaire',
    inventaire_id INT REFERENCES inventaires_rivets(id) ON DELETE SET NULL,
    statut TEXT NOT NULL DEFAULT 'ouvert',
    resolu_at TIMESTAMP,
    resolu_par INT REFERENCES users(id),
    created_by INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_ecarts_rivets_site ON ecarts_rivets(site_id);
CREATE INDEX idx_ecarts_rivets_statut ON ecarts_rivets(statut);
CREATE INDEX idx_ecarts_rivets_date ON ecarts_rivets(date_constat);

-- Miroir de inventaire_corrections, FK vers les tables rivets au lieu de bobines.
CREATE TABLE inventaire_corrections_rivets (
    id SERIAL PRIMARY KEY,
    detail_id INT NOT NULL REFERENCES inventaire_details_rivets(id) ON DELETE CASCADE,
    inventaire_id INT NOT NULL REFERENCES inventaires_rivets(id) ON DELETE CASCADE,
    type_rivet VARCHAR(20) NOT NULL,
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
CREATE INDEX idx_inv_corr_rivets_detail ON inventaire_corrections_rivets(detail_id);
CREATE INDEX idx_inv_corr_rivets_inventaire ON inventaire_corrections_rivets(inventaire_id);

-- ── Permissions : mêmes rôles que ecarts_bobines/inventaire_bobines aujourd'hui.
INSERT INTO permissions (role_id, module, can_read)
SELECT r.id, 'ecarts_rivets', 1
FROM roles r
WHERE r.slug IN ('controleur_production','gestionnaire_stock','gestionnaire_stock_bobines','lecteur','superviseur_operation')
ON CONFLICT (role_id, module) DO NOTHING;

INSERT INTO permissions (role_id, module, can_read, can_create, can_update)
SELECT r.id, 'inventaire_rivets', p.can_read, p.can_create, p.can_update
FROM permissions p JOIN roles r ON r.id = p.role_id
WHERE p.module = 'inventaire_bobines'
ON CONFLICT (role_id, module) DO NOTHING;
