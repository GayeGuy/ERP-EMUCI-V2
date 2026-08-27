-- ============================================================
--  sql/achats_mysql.sql — Module Achats (FEB, budgets, fournisseurs,
--  affectations equipements) + tables annexes (agents, inventaire
--  corrections/sessions, stock_departement, op_pmma_utilises)
--
--  Traduit depuis le schema PostgreSQL de `main` (23 migrations
--  migration_achats_01...23.sql, jamais portees sur ce fork MySQL).
--  A charger APRES sql/stockapp.sql et sql/demandes_internes_mysql.sql,
--  meme convention qu'eux (fichier de module separe, idempotent).
--
--  mysql -u stockapp -p stockapp < sql/achats_mysql.sql
-- ============================================================

SET NAMES utf8mb4;

-- ── Referentiels sans dependance ────────────────────────────
CREATE TABLE IF NOT EXISTS achat_types (
    id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code    VARCHAR(10) NOT NULL,
    libelle VARCHAR(150) NOT NULL,
    actif   TINYINT NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY achat_types_code_key (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS achat_paliers (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    borne_min    BIGINT NOT NULL DEFAULT 0,
    borne_max    BIGINT,
    libelle      VARCHAR(150) NOT NULL,
    signataires  JSON NOT NULL DEFAULT (JSON_ARRAY()),
    ordre        INT NOT NULL DEFAULT 0,
    actif        TINYINT NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY achat_paliers_libelle_key (libelle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS achat_parametres (
    cle         VARCHAR(80) NOT NULL,
    valeur      TEXT NOT NULL,
    libelle     VARCHAR(200) NOT NULL,
    type        VARCHAR(20) NOT NULL DEFAULT 'texte',
    options     TEXT,
    modifie_par INT UNSIGNED,
    modifie_le  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (cle),
    KEY achat_parametres_modifie_par_idx (modifie_par),
    CONSTRAINT achat_parametres_modifie_par_fkey FOREIGN KEY (modifie_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agents (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    matricule   VARCHAR(50),
    nom         VARCHAR(100) NOT NULL,
    prenom      VARCHAR(100) NOT NULL DEFAULT '',
    email       VARCHAR(150),
    telephone   VARCHAR(30),
    fonction    VARCHAR(150),
    departement VARCHAR(150),
    direction   VARCHAR(150),
    site        VARCHAR(150),
    grade       VARCHAR(100),
    statut      VARCHAR(20) NOT NULL DEFAULT 'actif',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY agents_nom_idx (nom, prenom),
    -- Note : la contrainte PG etait un UNIQUE INDEX partiel (matricule IS NOT
    -- NULL AND matricule <> ''), sans equivalent direct en MySQL. Un UNIQUE
    -- classique laisserait NULL passer plusieurs fois (comportement MySQL
    -- standard) mais bloquerait plusieurs matricules vides '' — a surveiller
    -- si des agents sont crees sans matricule renseigne comme chaine vide.
    -- Inline (pas un CREATE INDEX separe) pour rester idempotent : MySQL
    -- n'a pas de CREATE INDEX IF NOT EXISTS, mais CREATE TABLE IF NOT
    -- EXISTS protege aussi les index qu'il declare.
    UNIQUE KEY agents_matricule_uidx (matricule)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS familles_achat (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code             VARCHAR(50) NOT NULL,
    libelle          VARCHAR(150) NOT NULL,
    actif            TINYINT NOT NULL DEFAULT 1,
    compte_comptable VARCHAR(10),
    PRIMARY KEY (id),
    UNIQUE KEY familles_achat_code_key (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fournisseurs (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    raison_sociale      VARCHAR(200) NOT NULL,
    contact_nom         VARCHAR(150),
    telephone           VARCHAR(30),
    email               VARCHAR(180),
    adresse             TEXT,
    conditions_paiement VARCHAR(200),
    actif               TINYINT NOT NULL DEFAULT 1,
    cree_par            INT UNSIGNED,
    cree_le             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    numero_rccm         VARCHAR(100),
    numero_dfe          VARCHAR(100),
    numero_rib          VARCHAR(100),
    coordonnees         TEXT,
    doc_rccm            VARCHAR(255),
    doc_idu             VARCHAR(255),
    doc_dfe             VARCHAR(255),
    doc_arf              VARCHAR(255),
    doc_cnps            VARCHAR(255),
    doc_rib             VARCHAR(255),
    doc_pirl            VARCHAR(255),
    PRIMARY KEY (id),
    KEY fournisseurs_cree_par_idx (cree_par),
    CONSTRAINT fournisseurs_cree_par_fkey FOREIGN KEY (cree_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Circuit FEB (Fiche d'Expression de Besoin) ──────────────
CREATE TABLE IF NOT EXISTS feb (
    id                          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    numero                      VARCHAR(30),
    exercice                    INT NOT NULL,
    demandeur_id                INT UNSIGNED,
    site_id                     INT UNSIGNED,
    departement_id              INT UNSIGNED,
    fonction                    VARCHAR(150) DEFAULT NULL,
    urgence                     TINYINT NOT NULL DEFAULT 0,
    objet                       VARCHAR(255) NOT NULL,
    statut                      VARCHAR(30) NOT NULL DEFAULT 'brouillon',
    acheteur_id                 INT UNSIGNED,
    montant_total               BIGINT NOT NULL DEFAULT 0,
    workflow_snapshot           JSON,
    date_creation               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_soumission             DATETIME,
    date_prise_charge           DATETIME,
    date_lancement_validation   DATETIME,
    date_confirmation           DATETIME,
    date_cloture                DATETIME,
    etape_actuelle              TINYINT NOT NULL DEFAULT -1,
    signatures                  JSON NOT NULL DEFAULT (JSON_ARRAY()),
    historique                  JSON NOT NULL DEFAULT (JSON_ARRAY()),
    etape_rejet                 TINYINT,
    motif_rejet                 TEXT,
    fiche_validation_path       VARCHAR(255) DEFAULT NULL,
    -- Superieur hierarchique resolu au lancement de la validation
    -- (user_departements.is_n1 du departement de la FEB). NULL si le
    -- departement n'en a pas, ou si le demandeur est lui-meme le N+1.
    n1_user_id                  INT UNSIGNED,
    PRIMARY KEY (id),
    UNIQUE KEY feb_numero_key (numero),
    KEY feb_acheteur_idx (acheteur_id),
    KEY feb_demandeur_idx (demandeur_id),
    KEY feb_statut_idx (statut),
    CONSTRAINT feb_acheteur_id_fkey     FOREIGN KEY (acheteur_id)     REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT feb_demandeur_id_fkey    FOREIGN KEY (demandeur_id)    REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT feb_departement_id_fkey  FOREIGN KEY (departement_id)  REFERENCES departements(id) ON DELETE SET NULL,
    CONSTRAINT feb_site_id_fkey         FOREIGN KEY (site_id)         REFERENCES sites(id) ON DELETE SET NULL,
    CONSTRAINT feb_n1_user_id_fkey      FOREIGN KEY (n1_user_id)      REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feb_lignes (
    id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    feb_id                 INT UNSIGNED NOT NULL,
    numero_ligne           INT NOT NULL,
    designation            VARCHAR(255) NOT NULL,
    article_id             INT UNSIGNED,
    quantite               INT NOT NULL DEFAULT 1,
    unite                  VARCHAR(30) DEFAULT NULL,
    famille_id             INT UNSIGNED,
    code_analytique        VARCHAR(50) DEFAULT NULL,
    type_achat             VARCHAR(10),
    lot                    VARCHAR(50) DEFAULT NULL,
    fournisseur_id         INT UNSIGNED,
    montant_ttc            BIGINT NOT NULL DEFAULT 0,
    observation            TEXT,
    arbitrage              VARCHAR(10) NOT NULL DEFAULT 'achat',
    fournisseur_derogation TINYINT NOT NULL DEFAULT 0,
    nomenclature_id        INT UNSIGNED,
    PRIMARY KEY (id),
    KEY feb_lignes_feb_idx (feb_id),
    CONSTRAINT feb_lignes_feb_id_fkey          FOREIGN KEY (feb_id)          REFERENCES feb(id) ON DELETE CASCADE,
    CONSTRAINT feb_lignes_article_id_fkey      FOREIGN KEY (article_id)      REFERENCES articles(id) ON DELETE SET NULL,
    CONSTRAINT feb_lignes_famille_id_fkey      FOREIGN KEY (famille_id)      REFERENCES familles_achat(id),
    CONSTRAINT feb_lignes_fournisseur_id_fkey  FOREIGN KEY (fournisseur_id)  REFERENCES fournisseurs(id),
    CONSTRAINT feb_lignes_nomenclature_id_fkey FOREIGN KEY (nomenclature_id) REFERENCES nomenclatures(id) ON DELETE SET NULL,
    CONSTRAINT feb_lignes_type_achat_fkey      FOREIGN KEY (type_achat)      REFERENCES achat_types(code),
    CONSTRAINT feb_lignes_arbitrage_check      CHECK (arbitrage IN ('achat','stock'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feb_offres (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    feb_id               INT UNSIGNED NOT NULL,
    lot                  VARCHAR(50) DEFAULT NULL,
    fournisseur_id       INT UNSIGNED,
    delai_annonce        INT,
    conditions_paiement  VARCHAR(200) DEFAULT NULL,
    montant_ttc          BIGINT NOT NULL DEFAULT 0,
    prix_initial         BIGINT,
    observation          TEXT,
    retenue              TINYINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY feb_offres_feb_lot_idx (feb_id, lot),
    CONSTRAINT feb_offres_feb_id_fkey         FOREIGN KEY (feb_id)         REFERENCES feb(id) ON DELETE CASCADE,
    CONSTRAINT feb_offres_fournisseur_id_fkey FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feb_pieces_jointes (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    feb_id       INT UNSIGNED NOT NULL,
    fichier      VARCHAR(255) NOT NULL,
    nom_origine  VARCHAR(255) NOT NULL,
    taille       INT,
    mime         VARCHAR(100),
    deposee_par  INT UNSIGNED,
    deposee_le   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY feb_pieces_jointes_feb_idx (feb_id),
    CONSTRAINT feb_pieces_jointes_feb_id_fkey  FOREIGN KEY (feb_id)      REFERENCES feb(id) ON DELETE CASCADE,
    CONSTRAINT feb_pieces_jointes_deposee_par_fkey FOREIGN KEY (deposee_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feb_suivi (
    id                                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    feb_id                              INT UNSIGNED NOT NULL,
    feb_ligne_id                        INT UNSIGNED,
    numero_da                           VARCHAR(50) DEFAULT NULL,
    date_da                             DATE,
    numero_bc                           VARCHAR(50) DEFAULT NULL,
    date_bc                             DATE,
    date_livraison_prevue               DATE,
    date_livraison_reelle               DATE,
    quantite_commandee                  INT,
    quantite_recue                      INT NOT NULL DEFAULT 0,
    statut                              VARCHAR(30) NOT NULL DEFAULT 'en_attente',
    site_id                             INT UNSIGNED,
    cloture_reliquat                    TINYINT NOT NULL DEFAULT 0,
    quantite_expediee                   INT NOT NULL DEFAULT 0,
    quantite_receptionnee_departement   INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY feb_suivi_feb_idx (feb_id),
    KEY feb_suivi_numero_da_idx (numero_da),
    KEY feb_suivi_statut_idx (statut),
    CONSTRAINT feb_suivi_feb_id_fkey      FOREIGN KEY (feb_id)      REFERENCES feb(id) ON DELETE CASCADE,
    CONSTRAINT feb_suivi_feb_ligne_id_fkey FOREIGN KEY (feb_ligne_id) REFERENCES feb_lignes(id) ON DELETE CASCADE,
    CONSTRAINT feb_suivi_site_id_fkey     FOREIGN KEY (site_id)     REFERENCES sites(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feb_expeditions (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    feb_suivi_id  INT UNSIGNED NOT NULL,
    quantite      INT NOT NULL,
    date_expedition DATE NOT NULL,
    observation   TEXT,
    expedie_par   INT UNSIGNED,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY feb_expeditions_suivi_idx (feb_suivi_id),
    CONSTRAINT feb_expeditions_feb_suivi_id_fkey FOREIGN KEY (feb_suivi_id) REFERENCES feb_suivi(id) ON DELETE CASCADE,
    CONSTRAINT feb_expeditions_expedie_par_fkey  FOREIGN KEY (expedie_par)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feb_receptions (
    id                          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    feb_suivi_id                INT UNSIGNED NOT NULL,
    reception_fournisseur_id    INT UNSIGNED,
    quantite_recue              INT NOT NULL DEFAULT 0,
    date_reception               DATE NOT NULL DEFAULT (CURRENT_DATE),
    bon_livraison               VARCHAR(100) DEFAULT NULL,
    ecart                       INT NOT NULL DEFAULT 0,
    motif_ecart                 TEXT,
    recu_par                    INT UNSIGNED,
    observation                 TEXT,
    PRIMARY KEY (id),
    KEY feb_receptions_suivi_idx (feb_suivi_id),
    CONSTRAINT feb_receptions_feb_suivi_id_fkey FOREIGN KEY (feb_suivi_id) REFERENCES feb_suivi(id) ON DELETE CASCADE,
    CONSTRAINT feb_receptions_reception_fournisseur_id_fkey FOREIGN KEY (reception_fournisseur_id) REFERENCES fournisseurs(id),
    CONSTRAINT feb_receptions_recu_par_fkey FOREIGN KEY (recu_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feb_receptions_departement (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    feb_suivi_id  INT UNSIGNED NOT NULL,
    quantite      INT NOT NULL,
    date_reception DATE NOT NULL,
    observation   TEXT,
    recu_par      INT UNSIGNED,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    bon_transfert VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY feb_receptions_departement_suivi_idx (feb_suivi_id),
    CONSTRAINT feb_receptions_departement_feb_suivi_id_fkey FOREIGN KEY (feb_suivi_id) REFERENCES feb_suivi(id) ON DELETE CASCADE,
    CONSTRAINT feb_receptions_departement_recu_par_fkey FOREIGN KEY (recu_par) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feb_compteurs (
    exercice        INT NOT NULL,
    dernier_numero  INT NOT NULL DEFAULT 0,
    PRIMARY KEY (exercice)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Budgets ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS budget_validations (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    departement_id  INT UNSIGNED NOT NULL,
    exercice        INT NOT NULL,
    statut          VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    soumis_par      INT UNSIGNED,
    soumis_le       DATETIME,
    valide_par      INT UNSIGNED,
    valide_le       DATETIME,
    motif_rejet     TEXT,
    rejete_par      INT UNSIGNED,
    rejete_le       DATETIME,
    PRIMARY KEY (id),
    UNIQUE KEY budget_validations_uk_dept_exercice (departement_id, exercice),
    CONSTRAINT budget_validations_departement_id_fkey FOREIGN KEY (departement_id) REFERENCES departements(id) ON DELETE CASCADE,
    CONSTRAINT budget_validations_soumis_par_fkey     FOREIGN KEY (soumis_par)     REFERENCES users(id),
    CONSTRAINT budget_validations_valide_par_fkey     FOREIGN KEY (valide_par)     REFERENCES users(id),
    CONSTRAINT budget_validations_rejete_par_fkey     FOREIGN KEY (rejete_par)     REFERENCES users(id),
    CONSTRAINT budget_validations_statut_check CHECK (statut IN ('brouillon','soumis','valide','rejete'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lignes_budgetaires (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code_comptable   VARCHAR(50) NOT NULL,
    designation      VARCHAR(200) NOT NULL,
    exercice         INT NOT NULL,
    enveloppe        BIGINT,
    comportement     VARCHAR(20) NOT NULL DEFAULT 'aucun',
    famille_id       INT UNSIGNED,
    actif            TINYINT NOT NULL DEFAULT 1,
    departement_id   INT UNSIGNED,
    PRIMARY KEY (id),
    UNIQUE KEY lignes_budgetaires_dept_famille_exercice_key (departement_id, famille_id, exercice),
    CONSTRAINT lignes_budgetaires_departement_id_fkey FOREIGN KEY (departement_id) REFERENCES departements(id) ON DELETE CASCADE,
    CONSTRAINT lignes_budgetaires_famille_id_fkey      FOREIGN KEY (famille_id)      REFERENCES familles_achat(id),
    CONSTRAINT lignes_budgetaires_comportement_check CHECK (comportement IN ('aucun','alerte','blocage'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Stock departemental (traçabilité "qui detient quoi") ────
CREATE TABLE IF NOT EXISTS stock_departement (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    article_id      INT UNSIGNED NOT NULL,
    departement_id  INT UNSIGNED NOT NULL,
    quantite        INT NOT NULL DEFAULT 0,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY stock_departement_uk_article_dept (article_id, departement_id),
    CONSTRAINT stock_departement_article_id_fkey     FOREIGN KEY (article_id)     REFERENCES articles(id) ON DELETE CASCADE,
    CONSTRAINT stock_departement_departement_id_fkey FOREIGN KEY (departement_id) REFERENCES departements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Affectation d'équipements (workflow de validation) ──────
CREATE TABLE IF NOT EXISTS equipement_affectations (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    equipement_id       INT UNSIGNED NOT NULL,
    site_id             INT UNSIGNED,
    utilisateur_id      INT UNSIGNED,
    statut              VARCHAR(20) NOT NULL DEFAULT 'en_validation',
    workflow_snapshot   JSON NOT NULL DEFAULT (JSON_ARRAY()),
    signatures          JSON NOT NULL DEFAULT (JSON_ARRAY()),
    historique          JSON NOT NULL DEFAULT (JSON_ARRAY()),
    etape_actuelle      TINYINT NOT NULL DEFAULT 0,
    etape_rejet         TINYINT,
    motif_rejet         TEXT,
    proposee_par        INT UNSIGNED,
    proposee_le         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    valide_le           DATETIME,
    PRIMARY KEY (id),
    KEY equipement_affectations_equip_idx (equipement_id),
    KEY equipement_affectations_statut_idx (statut),
    CONSTRAINT equipement_affectations_equipement_id_fkey  FOREIGN KEY (equipement_id)  REFERENCES equipements(id) ON DELETE CASCADE,
    CONSTRAINT equipement_affectations_site_id_fkey        FOREIGN KEY (site_id)        REFERENCES sites(id) ON DELETE SET NULL,
    CONSTRAINT equipement_affectations_utilisateur_id_fkey FOREIGN KEY (utilisateur_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT equipement_affectations_proposee_par_fkey   FOREIGN KEY (proposee_par)   REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT equipement_affectations_statut_check CHECK (statut IN ('en_validation','validee','rejetee'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Sessions d'inventaire + corrections ──────────────────────
CREATE TABLE IF NOT EXISTS inventaire_sessions (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    libelle       VARCHAR(150),
    date_debut    DATE NOT NULL,
    date_fin      DATE NOT NULL,
    statut        VARCHAR(20) NOT NULL DEFAULT 'ouverte',
    notes         TEXT,
    ouverte_par   INT UNSIGNED,
    ouverte_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cloturee_par  INT UNSIGNED,
    cloturee_at   DATETIME,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    type_periode  VARCHAR(20) NOT NULL DEFAULT 'mensuel',
    PRIMARY KEY (id),
    CONSTRAINT inventaire_sessions_ouverte_par_fkey  FOREIGN KEY (ouverte_par)  REFERENCES users(id),
    CONSTRAINT inventaire_sessions_cloturee_par_fkey FOREIGN KEY (cloturee_par) REFERENCES users(id),
    CONSTRAINT inventaire_sessions_periode_chk CHECK (date_fin >= date_debut),
    CONSTRAINT inventaire_sessions_type_periode_chk CHECK (type_periode IN ('mensuel','trimestriel','semestriel','annuel'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventaire_session_sites (
    session_id INT UNSIGNED NOT NULL,
    site_id    INT UNSIGNED NOT NULL,
    PRIMARY KEY (session_id, site_id),
    CONSTRAINT inventaire_session_sites_session_id_fkey FOREIGN KEY (session_id) REFERENCES inventaire_sessions(id) ON DELETE CASCADE,
    CONSTRAINT inventaire_session_sites_site_id_fkey    FOREIGN KEY (site_id)    REFERENCES sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Depend de op_bobines, inventaire_details_bobines, inventaires_bobines,
-- deja presentes sur ce fork MySQL (non listees comme manquantes).
CREATE TABLE IF NOT EXISTS inventaire_corrections (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    detail_id               INT UNSIGNED NOT NULL,
    inventaire_id           INT UNSIGNED NOT NULL,
    bobine_id               INT UNSIGNED NOT NULL,
    site_id                 INT UNSIGNED NOT NULL,
    stock_physique_actuel   INT NOT NULL,
    valeur_proposee         INT,
    motif                   TEXT NOT NULL,
    demandeur_id            INT UNSIGNED NOT NULL,
    statut                  VARCHAR(20) NOT NULL DEFAULT 'en_attente',
    valeur_finale           INT,
    reponse                 TEXT,
    traite_par              INT UNSIGNED,
    traite_at               DATETIME,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    type                    VARCHAR(20) NOT NULL DEFAULT 'demande_site',
    autorise_par            INT UNSIGNED,
    autorise_at             DATETIME,
    PRIMARY KEY (id),
    KEY inventaire_corrections_detail_idx (detail_id),
    KEY inventaire_corrections_site_statut_idx (site_id, statut),
    CONSTRAINT inventaire_corrections_detail_id_fkey      FOREIGN KEY (detail_id)      REFERENCES inventaire_details_bobines(id) ON DELETE CASCADE,
    CONSTRAINT inventaire_corrections_inventaire_id_fkey  FOREIGN KEY (inventaire_id)  REFERENCES inventaires_bobines(id) ON DELETE CASCADE,
    CONSTRAINT inventaire_corrections_bobine_id_fkey      FOREIGN KEY (bobine_id)      REFERENCES op_bobines(id),
    CONSTRAINT inventaire_corrections_site_id_fkey        FOREIGN KEY (site_id)        REFERENCES sites(id),
    CONSTRAINT inventaire_corrections_demandeur_id_fkey   FOREIGN KEY (demandeur_id)   REFERENCES users(id),
    CONSTRAINT inventaire_corrections_traite_par_fkey     FOREIGN KEY (traite_par)     REFERENCES users(id),
    CONSTRAINT inventaire_corrections_autorise_par_fkey   FOREIGN KEY (autorise_par)   REFERENCES users(id),
    CONSTRAINT inventaire_corrections_statut_chk CHECK (statut IN ('en_attente','autorise','refuse','traite')),
    CONSTRAINT inventaire_corrections_type_chk   CHECK (type IN ('demande_site','demande_autorisation'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PMMA (depend de op_points_journaliers, deja presente) ────
CREATE TABLE IF NOT EXISTS op_pmma_utilises (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    point_id     INT UNSIGNED NOT NULL,
    type_pmma    VARCHAR(50) NOT NULL,
    utilises     INT NOT NULL DEFAULT 0,
    endommages   INT NOT NULL DEFAULT 0,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY op_pmma_utilises_idx_point_id (point_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
