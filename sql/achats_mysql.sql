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

-- Même motif que feb_compteurs, compteur par jour plutôt que par exercice
-- (numéro CMD-Ymd-XXXX) — includes/achats.php ach_numero_commande(),
-- remplace le tirage aléatoire de pages/commandes.php (rand(1,9999) sur
-- l'index unique commandes.numero_commande, collisions possibles).
CREATE TABLE IF NOT EXISTS commande_compteurs (
    jour            DATE NOT NULL,
    dernier_numero  INT NOT NULL DEFAULT 0,
    PRIMARY KEY (jour)
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

-- ============================================================
-- Données de référence Achats (types, paliers, paramètres) —
-- sql/migration_vps_mysql_achats_donnees_reference.sql existait
-- déjà (rattrapage du 29/08) mais n'était référencé nulle part.
-- Sans achat_paliers, aucune FEB ne peut jamais lancer sa
-- validation (ach_palier_pour_montant() renvoie toujours null).
-- INSERT IGNORE : idempotent tel quel.
-- ============================================================
--  familles_achat/sites, deja geres par leurs propres ecrans admin et
--  volontairement laisses vides ici).
-- ============================================================

INSERT IGNORE INTO achat_types (code, libelle, actif) VALUES
    ('DAF', "Demande d'Achat Fournitures", 1),
    ('DAI', "Demande d'Achat Immobilisation", 1),
    ('DAH', "Demande d'Achat Hors-marche", 1);

INSERT IGNORE INTO achat_paliers (borne_min, borne_max, libelle, signataires, ordre, actif) VALUES
    (0,       500000,  'RAF seul',        '["raf"]',              1, 1),
    (500001,  5000000, 'RAF + DAF',       '["raf", "daf"]',       2, 1),
    (5000001, NULL,    'RAF + DAF + PDG', '["raf", "daf", "dg"]', 3, 1);

INSERT IGNORE INTO achat_parametres (cle, valeur, libelle, type) VALUES
    ('comportement_budget_defaut',     'alerte', 'Comportement par defaut sur depassement', 'liste'),
    ('delai_livraison_standard_jours', '15',     'Delai de livraison standard (jours)', 'nombre'),
    ('devise',                         'XOF',    'Devise', 'texte'),
    ('max_lignes_feb',                 '14',     'Nombre maximum de lignes par FEB', 'nombre'),
    ('seuil_retard_jours',             '5',      'Seuil de retard de livraison (jours)', 'nombre'),
    ('seuil_retard_validation_jours',  '5',      "Seuil d'alerte - FEB en attente de validation depuis (jours)", 'nombre');

-- ============================================================
-- 3 colonnes de sql/migration_vps_mysql_colonnes_manquantes_diverses.sql
-- déplacées ici depuis sql/stockapp.sql : leurs FK visent familles_achat/
-- feb/feb_lignes, qui n'existent que dans CE fichier, chargé après
-- stockapp.sql (docker/initdb.sh) — une FK vers une table pas encore
-- créée y faisait échouer tout le reste du patch de stockapp.sql.
-- ============================================================
DROP PROCEDURE IF EXISTS _add_col_fk3;
DELIMITER $$
CREATE PROCEDURE _add_col_fk3(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl VARCHAR(255))
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = tbl)
     AND NOT EXISTS (SELECT 1 FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = tbl AND column_name = col) THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$
DELIMITER ;

CALL _add_col_fk3('articles','famille_id',
  "ADD COLUMN famille_id INT UNSIGNED NULL, ADD CONSTRAINT articles_famille_id_fkey FOREIGN KEY (famille_id) REFERENCES familles_achat(id)");
CALL _add_col_fk3('commande_lignes','feb_ligne_id',
  "ADD COLUMN feb_ligne_id INT UNSIGNED NULL, ADD CONSTRAINT commande_lignes_feb_ligne_id_fkey FOREIGN KEY (feb_ligne_id) REFERENCES feb_lignes(id)");
CALL _add_col_fk3('commandes','feb_id',
  "ADD COLUMN feb_id INT UNSIGNED NULL, ADD CONSTRAINT commandes_feb_id_fkey FOREIGN KEY (feb_id) REFERENCES feb(id)");
DROP PROCEDURE IF EXISTS _add_col_fk3;

-- ============================================================
-- 4 colonnes supplémentaires déplacées depuis sql/stockapp.sql :
-- leurs FK visent departements (demandes_internes_mysql.sql, mais
-- chargé avant celui-ci), feb_lignes et inventaire_sessions (ce
-- fichier, définies plus haut) — toutes existent à ce point.
-- Fichiers d'origine (rattrapage du 29/08, jamais référencés) :
-- sql/migration_vps_mysql_{di_roles_departement,equipements_
-- departement_statut,equipements_feb_ligne,inventaires_bobines_
-- session}.sql.
-- ============================================================
DROP PROCEDURE IF EXISTS _add_col_fk4;
DELIMITER $$
CREATE PROCEDURE _add_col_fk4(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl VARCHAR(255))
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = tbl)
     AND NOT EXISTS (SELECT 1 FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = tbl AND column_name = col) THEN
    SET @s = CONCAT('ALTER TABLE `', tbl, '` ', ddl);
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END IF;
END$$
DELIMITER ;

CALL _add_col_fk4('di_roles','departement_id',
  "ADD COLUMN departement_id INT UNSIGNED NULL AFTER ordre, ADD CONSTRAINT di_roles_departement_id_fkey FOREIGN KEY (departement_id) REFERENCES departements(id) ON DELETE SET NULL");
CALL _add_col_fk4('equipements','departement_id',
  "ADD COLUMN departement_id INT UNSIGNED NULL AFTER site_id, ADD CONSTRAINT equipements_departement_id_fkey FOREIGN KEY (departement_id) REFERENCES departements(id) ON DELETE SET NULL");
CALL _add_col_fk4('equipements','feb_ligne_id',
  "ADD COLUMN feb_ligne_id INT UNSIGNED NULL AFTER nomenclature_id, ADD CONSTRAINT equipements_feb_ligne_id_fkey FOREIGN KEY (feb_ligne_id) REFERENCES feb_lignes(id) ON DELETE SET NULL, ADD KEY equipements_feb_ligne_idx (feb_ligne_id)");
CALL _add_col_fk4('inventaires_bobines','session_id',
  "ADD COLUMN session_id INT UNSIGNED NULL AFTER site_id, ADD CONSTRAINT inventaires_bobines_session_id_fkey FOREIGN KEY (session_id) REFERENCES inventaire_sessions(id), ADD KEY inventaires_bobines_session_idx (session_id)");
DROP PROCEDURE IF EXISTS _add_col_fk4;

-- ============================================================
-- Référentiels organisation (départements, familles d'achat,
-- lignes budgétaires) — sql/migration_vps_mysql_referentiels_
-- organisation.sql existait déjà (rattrapage du 29/08) mais
-- n'était référencé nulle part. INSERT IGNORE : idempotent tel quel.
-- ============================================================
--
--  Sans elles, le module Achats est inutilisable : ach_creer_feb() exige
--  un departement et une famille par ligne des la soumission.
--
--  Volontairement laisse de cote (donnees transactionnelles propres a
--  chaque base, jamais a resynchroniser) : audit_log, distribution_lignes,
--  distributions_site, livraisons_consommables, receptions_consommables,
--  receptions_fournisseur, receptions_site, sessions, users.
-- ============================================================

INSERT IGNORE INTO departements (code, label, ordre, actif) VALUES
    ('OPERATION',      'Opérations',     0, 1),
    ('ADMINISTRATION', 'Administration', 0, 1),
    ('ACHAT',          'Achats',         0, 1);

INSERT IGNORE INTO familles_achat (code, libelle, actif, compte_comptable) VALUES
    ('FOURN_BUR',        'Fournitures de bureau',                        1, '605'),
    ('CONSO_IT',         'Consommables informatiques',                   1, '604'),
    ('EQUIP',            'Équipements',                                  1, '2442'),
    ('PRESTA_SVC',       'Prestations de services',                      1, '638'),
    ('TRANSPORT',        'Transport / Logistique',                       1, '611'),
    ('MAINTENANCE',      'Maintenance',                                  1, '624'),
    ('CONSO_OP',         'Consommables opérations (rivets)',             1, '604'),
    ('RESTAURATION',     'Restauration',                                 1, '605'),
    ('ENTRETIEN',        "Fournitures d'entretien",                      1, '605'),
    ('ACHAT_MARCH',      'Achats de marchandises',                       1, '601'),
    ('MATIERES_PREM',    'Achats de matières premières',                 1, '602'),
    ('EMBALLAGES',       "Achats d'emballages",                          1, '608'),
    ('TRANSPORT_VENTE',  'Transport sur ventes',                         1, '612'),
    ('TRANSPORT_TIERS',  'Transport pour compte de tiers',                1, '613'),
    ('TRANSPORT_PERSO',  'Transport du personnel',                       1, '614'),
    ('SOUS_TRAITANCE',   'Sous-traitance générale',                      1, '621'),
    ('LOCATIONS',        'Locations et charges locatives',               1, '622'),
    ('ASSURANCES',       "Primes d'assurance",                           1, '625'),
    ('ETUDES_DOC',       'Études, recherches et documentation',          1, '626'),
    ('PUBLICITE',        'Publicité, publications, relations publiques', 1, '627'),
    ('TELECOM',          'Frais de télécommunications',                  1, '628'),
    ('FRAIS_BANCAIRES',  'Frais bancaires',                              1, '631'),
    ('HONORAIRES',       "Rémunérations d'intermédiaires et honoraires", 1, '632'),
    ('FORMATION',        'Frais de formation du personnel',              1, '633'),
    ('LICENCES',         'Redevances pour brevets, licences, logiciels', 1, '634'),
    ('PERSONNEL_EXT',    'Rémunérations de personnel extérieur',         1, '637'),
    ('IMPOTS_DIRECTS',   'Impôts et taxes directs',                      1, '641'),
    ('IMPOTS_INDIRECTS', 'Impôts et taxes indirects',                    1, '645'),
    ('AUTRES_IMPOTS',    'Autres impôts et taxes',                       1, '647'),
    ('CHARGES_DIVERSES', 'Charges diverses',                             1, '658'),
    ('OUTILLAGE',        'Matériel et outillage industriel',             1, '241'),
    ('MATERIEL_BUREAU',  'Matériel de bureau',                           1, '2441'),
    ('MOBILIER_BUREAU',  'Mobilier de bureau',                           1, '2443'),
    ('VEHICULES',        'Matériel de transport',                        1, '245'),
    ('AUTRE_MATERIEL',   'Autres matériels',                             1, '248');

INSERT IGNORE INTO lignes_budgetaires (code_comptable, designation, exercice, enveloppe, comportement, famille_id, departement_id, actif)
SELECT '6061', 'Fournitures de bureau',      2026, NULL, 'alerte', id, NULL, 1 FROM familles_achat WHERE code='FOURN_BUR';
INSERT IGNORE INTO lignes_budgetaires (code_comptable, designation, exercice, enveloppe, comportement, famille_id, departement_id, actif)
SELECT '6068', 'Consommables informatiques', 2026, NULL, 'alerte', id, NULL, 1 FROM familles_achat WHERE code='CONSO_IT';
INSERT IGNORE INTO lignes_budgetaires (code_comptable, designation, exercice, enveloppe, comportement, famille_id, departement_id, actif)
SELECT '2183', 'Équipements informatiques',  2026, NULL, 'alerte', id, NULL, 1 FROM familles_achat WHERE code='EQUIP';
INSERT IGNORE INTO lignes_budgetaires (code_comptable, designation, exercice, enveloppe, comportement, famille_id, departement_id, actif)
SELECT '6226', 'Prestations de services',    2026, NULL, 'alerte', id, NULL, 1 FROM familles_achat WHERE code='PRESTA_SVC';
INSERT IGNORE INTO lignes_budgetaires (code_comptable, designation, exercice, enveloppe, comportement, famille_id, departement_id, actif)
SELECT '6241', 'Transport / Logistique',     2026, NULL, 'alerte', id, NULL, 1 FROM familles_achat WHERE code='TRANSPORT';
INSERT IGNORE INTO lignes_budgetaires (code_comptable, designation, exercice, enveloppe, comportement, famille_id, departement_id, actif)
SELECT '6152', 'Maintenance',                2026, NULL, 'alerte', id, NULL, 1 FROM familles_achat WHERE code='MAINTENANCE';

-- ============================================================
-- Inventaire Rivets/PMMA/Équipements (tables + permissions) —
-- sql/migration_vps_mysql_inventaire_rivets_pmma_equipements.sql
-- existait déjà (rattrapage du 29/08) mais n'était référencé nulle
-- part. CREATE TABLE passés en IF NOT EXISTS pour rester
-- réexécutables comme le reste de ce patch (le fichier d'origine
-- ne l'était pas).
-- ============================================================
-- ============================================================
--  Rattrapage main -> vps-mysql (2026-08-29) : mecanisme d'inventaire
--  complet (sessions, comptage, validation, ecarts, sous-workflow de
--  correction) pour Rivets, PMMA et Equipements — deja en place pour
--  Bobines sur cette branche (inventaires_bobines / ecarts_bobines /
--  inventaire_corrections). Traduction MySQL des 4 migrations Postgres
--  equivalentes (sql/migration_inventaire_rivets.sql,
--  migration_inventaire_pmma.sql, migration_inventaire_equipements.sql,
--  migration_permission_module_inventaire.sql), memes conventions que
--  inventaires_bobines/ecarts_bobines/inventaire_corrections deja en
--  place sur cette branche (int unsigned AUTO_INCREMENT, datetime,
--  CHECK plutot que ENUM).
-- ============================================================

-- ── RIVETS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inventaires_rivets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED DEFAULT NULL,
    date_inventaire DATE NOT NULL,
    type_inventaire VARCHAR(20) NOT NULL DEFAULT 'journalier',
    statut VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    nb_types INT UNSIGNED DEFAULT 0,
    nb_ecarts INT UNSIGNED DEFAULT 0,
    total_quantite_systeme INT DEFAULT 0,
    total_quantite_physique INT DEFAULT 0,
    notes TEXT,
    cree_par INT UNSIGNED DEFAULT NULL,
    valide_par INT UNSIGNED DEFAULT NULL,
    valide_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    session_id INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_inventaires_rivets_site (site_id),
    KEY idx_inventaires_rivets_statut (statut),
    KEY idx_inventaires_rivets_date (date_inventaire),
    KEY idx_inventaires_rivets_session (session_id),
    CONSTRAINT inventaires_rivets_site_fkey FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL,
    CONSTRAINT inventaires_rivets_cree_par_fkey FOREIGN KEY (cree_par) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT inventaires_rivets_valide_par_fkey FOREIGN KEY (valide_par) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT inventaires_rivets_session_fkey FOREIGN KEY (session_id) REFERENCES inventaire_sessions(id),
    CONSTRAINT inventaires_rivets_type_chk CHECK (type_inventaire IN ('journalier','mensuel')),
    CONSTRAINT inventaires_rivets_statut_chk CHECK (statut IN ('brouillon','valide','annule'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventaire_details_rivets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    inventaire_id INT UNSIGNED NOT NULL,
    type_rivet VARCHAR(20) NOT NULL,
    stock_systeme INT NOT NULL,
    stock_physique INT NOT NULL DEFAULT 0,
    ecart INT NOT NULL DEFAULT 0,
    ecart_connu_avant INT DEFAULT 0,
    notes TEXT,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inv_details_rivets (inventaire_id, type_rivet),
    CONSTRAINT inv_details_rivets_inventaire_fkey FOREIGN KEY (inventaire_id) REFERENCES inventaires_rivets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ecarts_rivets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED NOT NULL,
    type_rivet VARCHAR(20) NOT NULL,
    date_constat DATE NOT NULL,
    stock_systeme INT NOT NULL,
    stock_physique INT NOT NULL,
    ecart INT NOT NULL,
    motif TEXT,
    source VARCHAR(20) DEFAULT 'inventaire',
    inventaire_id INT UNSIGNED DEFAULT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'ouvert',
    resolu_at DATETIME DEFAULT NULL,
    resolu_par INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ecarts_rivets_site (site_id),
    KEY idx_ecarts_rivets_statut (statut),
    KEY idx_ecarts_rivets_date (date_constat),
    CONSTRAINT ecarts_rivets_site_fkey FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    CONSTRAINT ecarts_rivets_inventaire_fkey FOREIGN KEY (inventaire_id) REFERENCES inventaires_rivets(id) ON DELETE SET NULL,
    CONSTRAINT ecarts_rivets_resolu_par_fkey FOREIGN KEY (resolu_par) REFERENCES users(id),
    CONSTRAINT ecarts_rivets_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventaire_corrections_rivets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    detail_id INT UNSIGNED NOT NULL,
    inventaire_id INT UNSIGNED NOT NULL,
    type_rivet VARCHAR(20) NOT NULL,
    site_id INT UNSIGNED NOT NULL,
    stock_physique_actuel INT NOT NULL,
    valeur_proposee INT DEFAULT NULL,
    motif TEXT NOT NULL,
    demandeur_id INT UNSIGNED NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'en_attente',
    valeur_finale INT DEFAULT NULL,
    reponse TEXT,
    traite_par INT UNSIGNED DEFAULT NULL,
    traite_at DATETIME DEFAULT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'demande_site',
    autorise_par INT UNSIGNED DEFAULT NULL,
    autorise_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inv_corr_rivets_detail (detail_id),
    KEY idx_inv_corr_rivets_inventaire (inventaire_id),
    CONSTRAINT inv_corr_rivets_detail_fkey FOREIGN KEY (detail_id) REFERENCES inventaire_details_rivets(id) ON DELETE CASCADE,
    CONSTRAINT inv_corr_rivets_inventaire_fkey FOREIGN KEY (inventaire_id) REFERENCES inventaires_rivets(id) ON DELETE CASCADE,
    CONSTRAINT inv_corr_rivets_site_fkey FOREIGN KEY (site_id) REFERENCES sites(id),
    CONSTRAINT inv_corr_rivets_demandeur_fkey FOREIGN KEY (demandeur_id) REFERENCES users(id),
    CONSTRAINT inv_corr_rivets_traite_par_fkey FOREIGN KEY (traite_par) REFERENCES users(id),
    CONSTRAINT inv_corr_rivets_autorise_par_fkey FOREIGN KEY (autorise_par) REFERENCES users(id),
    CONSTRAINT inv_corr_rivets_statut_chk CHECK (statut IN ('en_attente','autorise','refuse','traite')),
    CONSTRAINT inv_corr_rivets_type_chk CHECK (type IN ('demande_site','demande_autorisation'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PMMA ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inventaires_pmma (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED DEFAULT NULL,
    date_inventaire DATE NOT NULL,
    type_inventaire VARCHAR(20) NOT NULL DEFAULT 'journalier',
    statut VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    nb_types INT UNSIGNED DEFAULT 0,
    nb_ecarts INT UNSIGNED DEFAULT 0,
    total_quantite_systeme INT DEFAULT 0,
    total_quantite_physique INT DEFAULT 0,
    notes TEXT,
    cree_par INT UNSIGNED DEFAULT NULL,
    valide_par INT UNSIGNED DEFAULT NULL,
    valide_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    session_id INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_inventaires_pmma_site (site_id),
    KEY idx_inventaires_pmma_statut (statut),
    KEY idx_inventaires_pmma_date (date_inventaire),
    KEY idx_inventaires_pmma_session (session_id),
    CONSTRAINT inventaires_pmma_site_fkey FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL,
    CONSTRAINT inventaires_pmma_cree_par_fkey FOREIGN KEY (cree_par) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT inventaires_pmma_valide_par_fkey FOREIGN KEY (valide_par) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT inventaires_pmma_session_fkey FOREIGN KEY (session_id) REFERENCES inventaire_sessions(id),
    CONSTRAINT inventaires_pmma_type_chk CHECK (type_inventaire IN ('journalier','mensuel')),
    CONSTRAINT inventaires_pmma_statut_chk CHECK (statut IN ('brouillon','valide','annule'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventaire_details_pmma (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    inventaire_id INT UNSIGNED NOT NULL,
    type_pmma VARCHAR(50) NOT NULL,
    stock_systeme INT NOT NULL,
    stock_physique INT NOT NULL DEFAULT 0,
    ecart INT NOT NULL DEFAULT 0,
    ecart_connu_avant INT DEFAULT 0,
    notes TEXT,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inv_details_pmma (inventaire_id, type_pmma),
    CONSTRAINT inv_details_pmma_inventaire_fkey FOREIGN KEY (inventaire_id) REFERENCES inventaires_pmma(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ecarts_pmma (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED NOT NULL,
    type_pmma VARCHAR(50) NOT NULL,
    date_constat DATE NOT NULL,
    stock_systeme INT NOT NULL,
    stock_physique INT NOT NULL,
    ecart INT NOT NULL,
    motif TEXT,
    source VARCHAR(20) DEFAULT 'inventaire',
    inventaire_id INT UNSIGNED DEFAULT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'ouvert',
    resolu_at DATETIME DEFAULT NULL,
    resolu_par INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ecarts_pmma_site (site_id),
    KEY idx_ecarts_pmma_statut (statut),
    KEY idx_ecarts_pmma_date (date_constat),
    CONSTRAINT ecarts_pmma_site_fkey FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    CONSTRAINT ecarts_pmma_inventaire_fkey FOREIGN KEY (inventaire_id) REFERENCES inventaires_pmma(id) ON DELETE SET NULL,
    CONSTRAINT ecarts_pmma_resolu_par_fkey FOREIGN KEY (resolu_par) REFERENCES users(id),
    CONSTRAINT ecarts_pmma_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventaire_corrections_pmma (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    detail_id INT UNSIGNED NOT NULL,
    inventaire_id INT UNSIGNED NOT NULL,
    type_pmma VARCHAR(50) NOT NULL,
    site_id INT UNSIGNED NOT NULL,
    stock_physique_actuel INT NOT NULL,
    valeur_proposee INT DEFAULT NULL,
    motif TEXT NOT NULL,
    demandeur_id INT UNSIGNED NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'en_attente',
    valeur_finale INT DEFAULT NULL,
    reponse TEXT,
    traite_par INT UNSIGNED DEFAULT NULL,
    traite_at DATETIME DEFAULT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'demande_site',
    autorise_par INT UNSIGNED DEFAULT NULL,
    autorise_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inv_corr_pmma_detail (detail_id),
    KEY idx_inv_corr_pmma_inventaire (inventaire_id),
    CONSTRAINT inv_corr_pmma_detail_fkey FOREIGN KEY (detail_id) REFERENCES inventaire_details_pmma(id) ON DELETE CASCADE,
    CONSTRAINT inv_corr_pmma_inventaire_fkey FOREIGN KEY (inventaire_id) REFERENCES inventaires_pmma(id) ON DELETE CASCADE,
    CONSTRAINT inv_corr_pmma_site_fkey FOREIGN KEY (site_id) REFERENCES sites(id),
    CONSTRAINT inv_corr_pmma_demandeur_fkey FOREIGN KEY (demandeur_id) REFERENCES users(id),
    CONSTRAINT inv_corr_pmma_traite_par_fkey FOREIGN KEY (traite_par) REFERENCES users(id),
    CONSTRAINT inv_corr_pmma_autorise_par_fkey FOREIGN KEY (autorise_par) REFERENCES users(id),
    CONSTRAINT inv_corr_pmma_statut_chk CHECK (statut IN ('en_attente','autorise','refuse','traite')),
    CONSTRAINT inv_corr_pmma_type_chk CHECK (type IN ('demande_site','demande_autorisation'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── EQUIPEMENTS ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inventaires_equipements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED DEFAULT NULL,
    date_inventaire DATE NOT NULL,
    type_inventaire VARCHAR(20) NOT NULL DEFAULT 'journalier',
    statut VARCHAR(20) NOT NULL DEFAULT 'brouillon',
    nb_equipements INT UNSIGNED DEFAULT 0,
    nb_trouves INT UNSIGNED DEFAULT 0,
    nb_ecarts INT UNSIGNED DEFAULT 0,
    notes TEXT,
    cree_par INT UNSIGNED DEFAULT NULL,
    valide_par INT UNSIGNED DEFAULT NULL,
    valide_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    session_id INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_inventaires_equip_site (site_id),
    KEY idx_inventaires_equip_statut (statut),
    KEY idx_inventaires_equip_date (date_inventaire),
    KEY idx_inventaires_equip_session (session_id),
    CONSTRAINT inventaires_equip_site_fkey FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL,
    CONSTRAINT inventaires_equip_cree_par_fkey FOREIGN KEY (cree_par) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT inventaires_equip_valide_par_fkey FOREIGN KEY (valide_par) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT inventaires_equip_session_fkey FOREIGN KEY (session_id) REFERENCES inventaire_sessions(id),
    CONSTRAINT inventaires_equip_type_chk CHECK (type_inventaire IN ('journalier','mensuel')),
    CONSTRAINT inventaires_equip_statut_chk CHECK (statut IN ('brouillon','valide','annule'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventaire_details_equipements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    inventaire_id INT UNSIGNED NOT NULL,
    equipement_id INT UNSIGNED NOT NULL,
    trouve TINYINT DEFAULT NULL COMMENT 'NULL=non saisi, 1=trouve, 0=manquant',
    ecart_connu_avant TINYINT DEFAULT 0,
    notes TEXT,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inv_details_equip (inventaire_id, equipement_id),
    CONSTRAINT inv_details_equip_inventaire_fkey FOREIGN KEY (inventaire_id) REFERENCES inventaires_equipements(id) ON DELETE CASCADE,
    CONSTRAINT inv_details_equip_equipement_fkey FOREIGN KEY (equipement_id) REFERENCES equipements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ecarts_equipements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id INT UNSIGNED NOT NULL,
    equipement_id INT UNSIGNED NOT NULL,
    date_constat DATE NOT NULL,
    motif TEXT,
    source VARCHAR(20) DEFAULT 'inventaire',
    inventaire_id INT UNSIGNED DEFAULT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'ouvert',
    resolu_at DATETIME DEFAULT NULL,
    resolu_par INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ecarts_equip_site (site_id),
    KEY idx_ecarts_equip_statut (statut),
    KEY idx_ecarts_equip_date (date_constat),
    KEY idx_ecarts_equip_equipement (equipement_id),
    CONSTRAINT ecarts_equip_site_fkey FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    CONSTRAINT ecarts_equip_equipement_fkey FOREIGN KEY (equipement_id) REFERENCES equipements(id) ON DELETE CASCADE,
    CONSTRAINT ecarts_equip_inventaire_fkey FOREIGN KEY (inventaire_id) REFERENCES inventaires_equipements(id) ON DELETE SET NULL,
    CONSTRAINT ecarts_equip_resolu_par_fkey FOREIGN KEY (resolu_par) REFERENCES users(id),
    CONSTRAINT ecarts_equip_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventaire_corrections_equipements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    detail_id INT UNSIGNED NOT NULL,
    inventaire_id INT UNSIGNED NOT NULL,
    equipement_id INT UNSIGNED NOT NULL,
    site_id INT UNSIGNED NOT NULL,
    valeur_actuelle TINYINT DEFAULT NULL,
    valeur_proposee TINYINT DEFAULT NULL,
    motif TEXT NOT NULL,
    demandeur_id INT UNSIGNED NOT NULL,
    statut VARCHAR(20) NOT NULL DEFAULT 'en_attente',
    valeur_finale TINYINT DEFAULT NULL,
    reponse TEXT,
    traite_par INT UNSIGNED DEFAULT NULL,
    traite_at DATETIME DEFAULT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'demande_site',
    autorise_par INT UNSIGNED DEFAULT NULL,
    autorise_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_inv_corr_equip_detail (detail_id),
    KEY idx_inv_corr_equip_inventaire (inventaire_id),
    CONSTRAINT inv_corr_equip_detail_fkey FOREIGN KEY (detail_id) REFERENCES inventaire_details_equipements(id) ON DELETE CASCADE,
    CONSTRAINT inv_corr_equip_inventaire_fkey FOREIGN KEY (inventaire_id) REFERENCES inventaires_equipements(id) ON DELETE CASCADE,
    CONSTRAINT inv_corr_equip_equipement_fkey FOREIGN KEY (equipement_id) REFERENCES equipements(id),
    CONSTRAINT inv_corr_equip_site_fkey FOREIGN KEY (site_id) REFERENCES sites(id),
    CONSTRAINT inv_corr_equip_demandeur_fkey FOREIGN KEY (demandeur_id) REFERENCES users(id),
    CONSTRAINT inv_corr_equip_traite_par_fkey FOREIGN KEY (traite_par) REFERENCES users(id),
    CONSTRAINT inv_corr_equip_autorise_par_fkey FOREIGN KEY (autorise_par) REFERENCES users(id),
    CONSTRAINT inv_corr_equip_statut_chk CHECK (statut IN ('en_attente','autorise','refuse','traite')),
    CONSTRAINT inv_corr_equip_type_chk CHECK (type IN ('demande_site','demande_autorisation'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PERMISSIONS ─────────────────────────────────────────────
-- ecarts_rivets/ecarts_pmma/ecarts_equipements : memes roles que
-- ecarts_bobines aujourd'hui.
INSERT INTO permissions (role_id, module, can_read)
SELECT r.id, 'ecarts_rivets', 1 FROM roles r
WHERE r.slug IN ('controleur_production','gestionnaire_stock','gestionnaire_stock_bobines','lecteur','superviseur_operation')
ON DUPLICATE KEY UPDATE can_read = can_read;

INSERT INTO permissions (role_id, module, can_read)
SELECT r.id, 'ecarts_pmma', 1 FROM roles r
WHERE r.slug IN ('controleur_production','gestionnaire_stock','gestionnaire_stock_bobines','lecteur','superviseur_operation')
ON DUPLICATE KEY UPDATE can_read = can_read;

INSERT INTO permissions (role_id, module, can_read)
SELECT r.id, 'ecarts_equipements', 1 FROM roles r
WHERE r.slug IN ('controleur_production','gestionnaire_stock','gestionnaire_stock_bobines','lecteur','superviseur_operation')
ON DUPLICATE KEY UPDATE can_read = can_read;

-- inventaire_rivets / inventaire_equipements : memes droits que
-- inventaire_bobines aujourd'hui.
INSERT INTO permissions (role_id, module, can_read, can_create, can_update)
SELECT p.role_id, 'inventaire_rivets', p.can_read, p.can_create, p.can_update
FROM permissions p WHERE p.module = 'inventaire_bobines'
ON DUPLICATE KEY UPDATE can_read=VALUES(can_read), can_create=VALUES(can_create), can_update=VALUES(can_update);

INSERT INTO permissions (role_id, module, can_read, can_create, can_update)
SELECT p.role_id, 'inventaire_equipements', p.can_read, p.can_create, p.can_update
FROM permissions p WHERE p.module = 'inventaire_bobines'
ON DUPLICATE KEY UPDATE can_read=VALUES(can_read), can_create=VALUES(can_create), can_update=VALUES(can_update);

-- inventaire_pmma : memes droits que le module pmma existant.
INSERT INTO permissions (role_id, module, can_read, can_create, can_update)
SELECT p.role_id, 'inventaire_pmma', p.can_read, p.can_create, p.can_update
FROM permissions p WHERE p.module = 'pmma'
ON DUPLICATE KEY UPDATE can_read=VALUES(can_read), can_create=VALUES(can_create), can_update=VALUES(can_update);

-- Droit 'inventaire' (visibilite du groupe de menu INVENTAIRE) : memes
-- roles qui avaient acces au groupe BOBINES avant qu'il n'en soit
-- extrait.
INSERT INTO permissions (role_id, module, can_read)
SELECT r.id, 'inventaire', 1 FROM roles r
WHERE r.slug IN ('coordinateur_site','gestionnaire_stock','superviseur_operation','controleur_production','gestionnaire_stock_bobines')
ON DUPLICATE KEY UPDATE can_read = can_read;

