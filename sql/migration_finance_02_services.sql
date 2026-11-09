-- ============================================================
--  Finance 02 : sites, services et formules de tarification (PostgreSQL)
--
--  Sites : nom libre, rattaché à un ou plusieurs codes reconnus dans les
--  données (préfixe du chrono : BKE, ABJ… ; sinon site d'enrôlement CGI).
--  Services : prix TTC par catégorie (MOTO / AUTO / SEMI) ; un service
--  « variable » (reliquat) n'a pas de prix fixe : c'est le complément
--  réconcilié au-delà des services fixes.
--  Formules : un ensemble de services, appliqué
--    · par site   (mode 'site')    : sites choisis, + canaux et catégories ;
--    · par dossier (mode 'dossier') : canaux, types et catégories, sans site.
--  Critère vide = tous. Une formule par site l'emporte sur une formule par
--  dossier, puis la plus précise.
--
--  À passer après migration_finance_01_dossiers.sql. Rejouable.
-- ============================================================

CREATE TABLE IF NOT EXISTS fin_sites (
    id       serial       PRIMARY KEY,
    nom      varchar(120) NOT NULL UNIQUE,
    maj_par  integer,
    maj_le   timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS fin_site_codes (
    code     varchar(120) PRIMARY KEY,
    site_id  integer      NOT NULL REFERENCES fin_sites (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS fin_services (
    id        serial       PRIMARY KEY,
    libelle   varchar(80)  NOT NULL UNIQUE,
    variable  boolean      NOT NULL DEFAULT false,
    actif     boolean      NOT NULL DEFAULT true,
    ordre     integer      NOT NULL DEFAULT 0,
    maj_par   integer,
    maj_le    timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS fin_service_prix (
    service_id  integer     NOT NULL REFERENCES fin_services (id) ON DELETE CASCADE,
    categorie   varchar(10) NOT NULL,
    montant_ttc integer     NOT NULL,
    PRIMARY KEY (service_id, categorie)
);

CREATE TABLE IF NOT EXISTS fin_formules (
    id         serial       PRIMARY KEY,
    libelle    varchar(120) NOT NULL,
    mode       varchar(10)  NOT NULL DEFAULT 'site' CHECK (mode IN ('site', 'dossier')),
    site_ids   integer[],
    canaux     text[],
    types      text[],
    categories text[],
    actif      boolean      NOT NULL DEFAULT true,
    maj_par    integer,
    maj_le     timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS fin_formule_services (
    formule_id integer NOT NULL REFERENCES fin_formules (id) ON DELETE CASCADE,
    service_id integer NOT NULL REFERENCES fin_services (id) ON DELETE CASCADE,
    PRIMARY KEY (formule_id, service_id)
);

ALTER TABLE fin_dossiers ADD COLUMN IF NOT EXISTS site             varchar(120);
ALTER TABLE fin_dossiers ADD COLUMN IF NOT EXISTS site_id          integer;
ALTER TABLE fin_dossiers ADD COLUMN IF NOT EXISTS formule_id       integer;
ALTER TABLE fin_dossiers ADD COLUMN IF NOT EXISTS montant_variable numeric(14,2);
CREATE INDEX IF NOT EXISTS idx_fin_dossiers_formule ON fin_dossiers (formule_id);
CREATE INDEX IF NOT EXISTS idx_fin_dossiers_site ON fin_dossiers (site_id);
