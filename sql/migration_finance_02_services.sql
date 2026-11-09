-- ============================================================
--  Finance 02 : sites, services et formules de tarification (PostgreSQL)
--
--  Sites : ceux de l'ERP (table sites), de pose ou mixtes. Le code de site d'un
--  dossier est celui de la pose (OptoPlate), sinon celui de l'enrôlement
--  (préfixe du chrono : BKE, ABJ… ; sinon site CGI). Ce code est rattaché
--  automatiquement à un site ERP (nom, code, ou code « XXX-01 ») ;
--  fin_site_codes le rattache à la main à un ou plusieurs sites.
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

CREATE TABLE IF NOT EXISTS fin_site_codes (
    code     varchar(120) NOT NULL,
    site_id  integer      NOT NULL REFERENCES sites (id) ON DELETE CASCADE,
    PRIMARY KEY (code, site_id)
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
ALTER TABLE fin_dossiers ADD COLUMN IF NOT EXISTS site_ids         integer[];
ALTER TABLE fin_dossiers ADD COLUMN IF NOT EXISTS formule_id       integer;
ALTER TABLE fin_dossiers ADD COLUMN IF NOT EXISTS montant_variable numeric(14,2);
-- Plusieurs sites possibles avant la pose : la formule par site attend la pose.
ALTER TABLE fin_dossiers ADD COLUMN IF NOT EXISTS formule_attente  boolean NOT NULL DEFAULT false;
CREATE INDEX IF NOT EXISTS idx_fin_dossiers_formule ON fin_dossiers (formule_id);
CREATE INDEX IF NOT EXISTS idx_fin_dossiers_sites ON fin_dossiers USING gin (site_ids);
