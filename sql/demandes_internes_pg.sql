-- ============================================================
--  Module "Demandes internes" — schema PostgreSQL (branche master / Neon)
--  Porte l'app emu-demandes (Node/PG) en module PHP de l'ERP.
--  Reutilise les tables ERP existantes : users, notifications, sites.
--  Colonnes JSON stockees en TEXT (json_encode/decode cote PHP) => portable MySQL/PG.
-- ============================================================
BEGIN;

-- Roles de validation (n1, raf, daf, dg, it, gestionnaire)
CREATE TABLE IF NOT EXISTS di_roles (
    code   varchar(30) PRIMARY KEY,
    label  varchar(100) NOT NULL,
    ordre  integer NOT NULL DEFAULT 0
);

-- Roles de validation attribues aux utilisateurs ERP (un user peut en cumuler)
CREATE TABLE IF NOT EXISTS di_user_roles (
    user_id   integer NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role_code varchar(30) NOT NULL REFERENCES di_roles(code) ON DELETE CASCADE,
    PRIMARY KEY (user_id, role_code)
);

-- Types de demande
CREATE TABLE IF NOT EXISTS di_types (
    id             SERIAL PRIMARY KEY,
    code           varchar(50) UNIQUE NOT NULL,
    label          varchar(150) NOT NULL,
    description    text DEFAULT '',
    actif          smallint NOT NULL DEFAULT 1,
    traitement_it  smallint NOT NULL DEFAULT 0,
    date_auto_jours integer DEFAULT NULL,
    ordre          integer NOT NULL DEFAULT 0
);

-- Etapes de validation par type (le circuit)
CREATE TABLE IF NOT EXISTS di_etapes (
    id        SERIAL PRIMARY KEY,
    type_id   integer NOT NULL REFERENCES di_types(id) ON DELETE CASCADE,
    role_code varchar(30) NOT NULL REFERENCES di_roles(code),
    label     varchar(150) NOT NULL,
    ordre     integer NOT NULL
);
CREATE INDEX IF NOT EXISTS di_etapes_type_idx ON di_etapes (type_id, ordre);

-- Plateformes NSIIV (pour les demandes d'acces)
CREATE TABLE IF NOT EXISTS di_plateformes (
    code   varchar(50) PRIMARY KEY,
    label  varchar(100) NOT NULL,
    ordre  integer NOT NULL DEFAULT 0,
    actif  smallint NOT NULL DEFAULT 1
);

-- Demandes
CREATE TABLE IF NOT EXISTS di_demandes (
    id             SERIAL PRIMARY KEY,
    numero         varchar(30) UNIQUE NOT NULL,
    type_code      varchar(50) NOT NULL REFERENCES di_types(code),
    statut         varchar(30) NOT NULL DEFAULT 'brouillon',
    etape_actuelle integer NOT NULL DEFAULT 0,
    etape_rejet    integer DEFAULT NULL,
    demandeur_id   integer NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    champs         text NOT NULL DEFAULT '{}',   -- JSON (champs specifiques au type)
    historique     text NOT NULL DEFAULT '[]',   -- JSON (journal des actions)
    signatures     text NOT NULL DEFAULT '[]',   -- JSON (visas)
    workflow_snapshot text NOT NULL DEFAULT '[]',-- JSON (circuit fige a la soumission)
    priorite       varchar(20) NOT NULL DEFAULT 'normal',
    traite_it      smallint NOT NULL DEFAULT 0,
    traite_par     integer DEFAULT NULL REFERENCES users(id),
    traite_date    timestamp DEFAULT NULL,
    submitted_at   timestamp DEFAULT NULL,
    created_at     timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS di_demandes_demandeur_idx ON di_demandes (demandeur_id);
CREATE INDEX IF NOT EXISTS di_demandes_statut_idx ON di_demandes (statut);
CREATE INDEX IF NOT EXISTS di_demandes_type_idx ON di_demandes (type_code);

-- ============================================================
--  SEED (idempotent) — roles, plateformes, 9 types + circuits
-- ============================================================
INSERT INTO di_roles (code, label, ordre) VALUES
    ('n1','Responsable N+1',1),
    ('raf','RAF',2),
    ('daf','DAF',3),
    ('dg','Direction Generale',4),
    ('it','Responsable IT',5),
    ('gestionnaire','Service Administration',6)
ON CONFLICT (code) DO NOTHING;

INSERT INTO di_plateformes (code, label, ordre) VALUES
    ('brique_additionnelle','Brique Additionnelle',1),
    ('nsiiv_enrolement','NSIIV Enrolement',2),
    ('nsiiv_optoplate','NSIIV Optoplate',3),
    ('nsiiv_optotrace','NSIIV Optotrace',4),
    ('email_professionnel','E-mail professionnel',5)
ON CONFLICT (code) DO NOTHING;

INSERT INTO di_types (code, label, description, traitement_it, date_auto_jours, ordre) VALUES
    ('autorisation_absence','Autorisation d''absence','Demander un conge, une permission ou une absence exceptionnelle',0,NULL,0),
    ('creation_acces','Creation d''acces NSIIV','Demander la creation d''acces aux plateformes NSIIV pour un agent',1,0,1),
    ('basculement_acces','Basculement d''acces','Modifier les acces plateformes d''un agent',1,0,2),
    ('basculement_compte','Basculement de compte EMUCI','Demander le changement de poste sur un compte EMUCI existant',1,0,3),
    ('transfert_agent','Transfert d''agent','Demander le transfert d''un agent vers un autre site',1,7,4),
    ('creation_site','Creation de site','Demander l''enregistrement d''un nouveau site dans le systeme NSIIV',1,0,5),
    ('changement_geolocalisation','Changement de geolocalisation','Modifier les coordonnees GPS d''un site existant',1,0,6),
    ('imputation_courrier','Imputation courrier entrant','Imputer un courrier entrant a un service ou une personne',0,0,7),
    ('exceptionnel','Demande exceptionnelle','Demande hors cadre standard - motif obligatoire',0,NULL,8)
ON CONFLICT (code) DO NOTHING;

-- Circuits (etapes) par type
INSERT INTO di_etapes (type_id, role_code, label, ordre)
SELECT t.id, v.role_code, v.label, v.ordre
FROM (VALUES
    ('autorisation_absence','n1','Visa N+1',0),
    ('autorisation_absence','gestionnaire','Visa Administration',1),
    ('autorisation_absence','dg','Visa Direction Generale',2),
    ('creation_acces','n1','Visa N+1',0),
    ('creation_acces','it','Visa Responsable IT',1),
    ('creation_acces','dg','Visa Direction Generale',2),
    ('basculement_acces','n1','Visa N+1',0),
    ('basculement_acces','it','Visa Responsable IT',1),
    ('basculement_acces','dg','Visa Direction Generale',2),
    ('basculement_compte','n1','Visa Responsable Hierarchique',0),
    ('basculement_compte','it','Visa Service IT',1),
    ('transfert_agent','n1','Visa Responsable Hierarchique (N+1)',0),
    ('transfert_agent','raf','Visa RAF',1),
    ('transfert_agent','daf','Visa DAF',2),
    ('transfert_agent','dg','Visa Direction Generale',3),
    ('creation_site','it','Visa Responsable IT',0),
    ('creation_site','dg','Visa Direction Generale',1),
    ('changement_geolocalisation','it','Visa Responsable IT',0),
    ('changement_geolocalisation','dg','Visa Direction Generale',1),
    ('imputation_courrier','gestionnaire','Visa Service Imputant',0),
    ('imputation_courrier','dg','Validation PDG (si requise)',1),
    ('exceptionnel','n1','Visa N+1',0),
    ('exceptionnel','dg','Visa Direction Generale',1)
) AS v(type_code, role_code, label, ordre)
JOIN di_types t ON t.code = v.type_code
WHERE NOT EXISTS (
    SELECT 1 FROM di_etapes e WHERE e.type_id = t.id AND e.ordre = v.ordre
);

COMMIT;
