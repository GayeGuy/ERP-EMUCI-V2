-- ============================================================
--  Finance 02 : services et formules de tarification (PostgreSQL)
--
--  Un dossier paie un ou plusieurs services (plaque, sécurisation, pose,
--  service mobile, délocalisée, reliquat…). Chaque service a un prix TTC par
--  catégorie (MOTO / AUTO / SEMI) ; un service « variable » (reliquat) n'a pas
--  de prix fixe : c'est le complément réconcilié au-delà des services fixes.
--  Une formule regroupe des services et s'applique aux dossiers selon le site
--  (préfixe du chrono, sinon site d'enrôlement CGI), le canal et la catégorie ;
--  critère vide = tous. La formule la plus précise l'emporte.
--
--  À passer après migration_finance_01_dossiers.sql. Rejouable.
-- ============================================================

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
    sites      text[],
    canaux     text[],
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
ALTER TABLE fin_dossiers ADD COLUMN IF NOT EXISTS formule_id       integer;
ALTER TABLE fin_dossiers ADD COLUMN IF NOT EXISTS montant_variable numeric(14,2);
CREATE INDEX IF NOT EXISTS idx_fin_dossiers_formule ON fin_dossiers (formule_id);
