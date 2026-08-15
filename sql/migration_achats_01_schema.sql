-- ============================================================
--  Migration : module Achats — schéma (référentiels + flux FEB)
--  Compatible PostgreSQL / Neon — idempotente (CREATE ... IF NOT EXISTS).
--  Ne modifie aucune table existante. Les seeds (paliers, types, familles,
--  paramètres) sont dans migration_achats_02_referentiels.sql, séparé.
--  2026-08
-- ============================================================
BEGIN;

-- ══════════════════════════════════════════════════════════════
--  RÉFÉRENTIELS
-- ══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS fournisseurs (
    id                  SERIAL PRIMARY KEY,
    raison_sociale      varchar(200) NOT NULL,
    contact_nom         varchar(150) DEFAULT NULL,
    telephone           varchar(30)  DEFAULT NULL,
    email               varchar(180) DEFAULT NULL,
    adresse             text         DEFAULT NULL,
    conditions_paiement varchar(200) DEFAULT NULL,
    actif               smallint     NOT NULL DEFAULT 1,
    cree_par            integer      REFERENCES users(id) ON DELETE SET NULL,
    cree_le             timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le          timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS familles_achat (
    id      SERIAL PRIMARY KEY,
    code    varchar(50) UNIQUE NOT NULL,
    libelle varchar(150) NOT NULL,
    actif   smallint NOT NULL DEFAULT 1
);

-- Table en plus par rapport à la spécification d'origine : le type d'achat
-- (DAF/DAI/DAH) est porté par chaque ligne de FEB et doit rester
-- modifiable (renommage, désactivation) sans toucher au code — il lui faut
-- donc son propre référentiel plutôt qu'une colonne à valeurs figées.
CREATE TABLE IF NOT EXISTS achat_types (
    id      SERIAL PRIMARY KEY,
    code    varchar(10) UNIQUE NOT NULL,
    libelle varchar(150) NOT NULL,
    actif   smallint NOT NULL DEFAULT 1
);

-- signataires en jsonb plutôt qu'une table fille : même forme que
-- workflow_snapshot sur feb, la copie au lancement de validation (l'instantané
-- du circuit applicable à ce palier, figé pour ne plus bouger si le palier
-- change ensuite) devient une simple copie de valeur, pas une jointure.
CREATE TABLE IF NOT EXISTS achat_paliers (
    id          SERIAL PRIMARY KEY,
    borne_min   integer NOT NULL DEFAULT 0,
    borne_max   integer DEFAULT NULL,  -- NULL = pas de plafond
    libelle     varchar(150) NOT NULL UNIQUE,
    signataires jsonb NOT NULL DEFAULT '[]',  -- liste ordonnée de slugs de rôles
    ordre       integer NOT NULL DEFAULT 0,
    actif       smallint NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS lignes_budgetaires (
    id             SERIAL PRIMARY KEY,
    code_comptable varchar(50) NOT NULL,
    designation    varchar(200) NOT NULL,
    exercice       integer NOT NULL,
    enveloppe      integer DEFAULT NULL,  -- NULL = non plafonnée
    comportement   varchar(20) NOT NULL DEFAULT 'aucun'
                   CHECK (comportement IN ('aucun','alerte','blocage')),
    famille_id     integer REFERENCES familles_achat(id),
    actif          smallint NOT NULL DEFAULT 1,
    UNIQUE (code_comptable, exercice)
);

CREATE TABLE IF NOT EXISTS achat_parametres (
    cle         varchar(80) PRIMARY KEY,
    valeur      text NOT NULL,
    libelle     varchar(200) NOT NULL,
    type        varchar(20) NOT NULL DEFAULT 'texte',  -- pilote le controle de saisie cote ecran
    options     text DEFAULT NULL,
    modifie_par integer REFERENCES users(id) ON DELETE SET NULL,
    modifie_le  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ══════════════════════════════════════════════════════════════
--  FLUX — Fiche d'Expression de Besoin (FEB)
-- ══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS feb (
    id                          SERIAL PRIMARY KEY,
    numero                      varchar(30) UNIQUE NOT NULL,
    exercice                    integer NOT NULL,
    demandeur_id                integer REFERENCES users(id) ON DELETE SET NULL,
    site_id                     integer REFERENCES sites(id) ON DELETE SET NULL,
    departement_id              integer REFERENCES departements(id) ON DELETE SET NULL,
    fonction                    varchar(150) DEFAULT NULL,
    urgence                     smallint NOT NULL DEFAULT 0,
    objet                       varchar(255) NOT NULL,
    statut                      varchar(30) NOT NULL DEFAULT 'brouillon',
    acheteur_id                 integer REFERENCES users(id) ON DELETE SET NULL,
    montant_total                integer NOT NULL DEFAULT 0,
    workflow_snapshot           jsonb DEFAULT NULL,  -- circuit/palier figes au lancement de validation
    date_creation               timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_soumission             timestamp DEFAULT NULL,
    date_prise_charge           timestamp DEFAULT NULL,
    date_lancement_validation   timestamp DEFAULT NULL,
    date_confirmation           timestamp DEFAULT NULL,
    date_cloture                timestamp DEFAULT NULL
);
CREATE INDEX IF NOT EXISTS feb_statut_idx      ON feb (statut);
CREATE INDEX IF NOT EXISTS feb_demandeur_idx   ON feb (demandeur_id);
CREATE INDEX IF NOT EXISTS feb_acheteur_idx    ON feb (acheteur_id);

CREATE TABLE IF NOT EXISTS feb_lignes (
    id             SERIAL PRIMARY KEY,
    feb_id         integer NOT NULL REFERENCES feb(id) ON DELETE CASCADE,
    numero_ligne   integer NOT NULL,
    designation    varchar(255) NOT NULL,
    article_id     integer REFERENCES articles(id) ON DELETE SET NULL,
    quantite       integer NOT NULL DEFAULT 1,
    unite          varchar(30) DEFAULT NULL,
    famille_id     integer REFERENCES familles_achat(id),
    code_analytique varchar(50) DEFAULT NULL,  -- rapprochement informatif avec lignes_budgetaires.code_comptable, non contraint en base
    type_achat     varchar(10) REFERENCES achat_types(code),
    lot            varchar(50) DEFAULT NULL,
    fournisseur_id integer REFERENCES fournisseurs(id),
    montant_ttc    integer NOT NULL DEFAULT 0,
    observation    text DEFAULT NULL
);
CREATE INDEX IF NOT EXISTS feb_lignes_feb_idx ON feb_lignes (feb_id);

CREATE TABLE IF NOT EXISTS feb_offres (
    id                  SERIAL PRIMARY KEY,
    feb_id              integer NOT NULL REFERENCES feb(id) ON DELETE CASCADE,
    lot                 varchar(50) DEFAULT NULL,
    fournisseur_id      integer REFERENCES fournisseurs(id),
    delai_annonce       integer DEFAULT NULL,  -- jours
    conditions_paiement varchar(200) DEFAULT NULL,
    montant_ttc         integer NOT NULL DEFAULT 0,
    prix_initial        integer DEFAULT NULL,
    observation         text DEFAULT NULL,
    retenue             smallint NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS feb_offres_feb_lot_idx ON feb_offres (feb_id, lot);

-- numero_da/date_da/numero_bc/date_bc volontairement nullables et
-- indépendants les uns des autres : le suivi commence souvent avant que le
-- BC existe (la DA peut être émise seule), et rien n'impose l'ordre strict
-- DA → BC dans tous les circuits fournisseurs.
CREATE TABLE IF NOT EXISTS feb_suivi (
    id                     SERIAL PRIMARY KEY,
    feb_id                 integer NOT NULL REFERENCES feb(id) ON DELETE CASCADE,
    feb_ligne_id           integer REFERENCES feb_lignes(id) ON DELETE CASCADE,
    numero_da              varchar(50) DEFAULT NULL,
    date_da                date DEFAULT NULL,
    numero_bc              varchar(50) DEFAULT NULL,
    date_bc                date DEFAULT NULL,
    date_livraison_prevue  date DEFAULT NULL,
    date_livraison_reelle  date DEFAULT NULL,
    quantite_commandee     integer DEFAULT NULL,
    quantite_recue         integer NOT NULL DEFAULT 0,
    statut                 varchar(30) NOT NULL DEFAULT 'en_attente',
    site_id                integer REFERENCES sites(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS feb_suivi_feb_idx      ON feb_suivi (feb_id);
CREATE INDEX IF NOT EXISTS feb_suivi_statut_idx   ON feb_suivi (statut);
CREATE INDEX IF NOT EXISTS feb_suivi_numero_da_idx ON feb_suivi (numero_da);

CREATE TABLE IF NOT EXISTS feb_receptions (
    id                     SERIAL PRIMARY KEY,
    feb_suivi_id           integer NOT NULL REFERENCES feb_suivi(id) ON DELETE CASCADE,
    reception_fournisseur_id integer REFERENCES fournisseurs(id),
    quantite_recue         integer NOT NULL DEFAULT 0,
    date_reception          date NOT NULL DEFAULT CURRENT_DATE,
    bon_livraison           varchar(100) DEFAULT NULL,
    ecart                   integer NOT NULL DEFAULT 0,
    motif_ecart             text DEFAULT NULL,
    recu_par                integer REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS feb_receptions_suivi_idx ON feb_receptions (feb_suivi_id);

-- Verrou de ligne à la numérotation (SELECT ... FOR UPDATE côté PHP avant
-- l'incrément) : sans lui, deux FEB soumises au même instant peuvent lire
-- le même dernier_numero et repartir avec un numéro identique.
CREATE TABLE IF NOT EXISTS feb_compteurs (
    exercice      integer PRIMARY KEY,
    dernier_numero integer NOT NULL DEFAULT 0
);

-- NB : la limite de 14 lignes par FEB est une règle applicative (vérifiée
-- côté PHP à l'ajout d'une ligne), volontairement non écrite en contrainte
-- SQL — elle est susceptible de changer sans migration.

COMMIT;
