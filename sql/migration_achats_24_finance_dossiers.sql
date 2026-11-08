-- ============================================================
--  Migration Achats 24 — Finance : suivi des dossiers (réception → pose)
--  PostgreSQL. Rejouable (IF NOT EXISTS / ON CONFLICT).
--
--  Un dossier est suivi de son ouverture chez CIL jusqu'à la pose de ses
--  plaques. Chaque source garde ses lignes dans sa propre table (fin_src_*),
--  mises à jour à chaque import sans doublon ; fin_dossiers est la vue
--  consolidée, reconstruite après chaque import (includes/finance_dossiers.php,
--  fin_consolider()). Clé du dossier : le VIN ; à défaut le chrono CIL ou le
--  n° NSIIV, rattachés au VIN dès qu'une autre source les relie.
--
--  Sources :
--    fin_src_cil      API CIL (ControlPayement) — ouverture, paiement, montants
--    fin_src_brique   export Brique — arrivée du dossier chez EMUCI
--    fin_src_cgi      export CGI — enrôlement, type Primo/Post/Reimmat
--    fin_src_x3       export Sage X3 — pose, réconciliation, facture
--    fin_src_caisse   export Caisse — encaissement, bon de commande
--    fin_src_pose     export OptoPlate — plaques posées (avant / arrière)
-- ============================================================

BEGIN;

CREATE TABLE IF NOT EXISTS fin_src_cil (
    chrono          varchar(30)  PRIMARY KEY,
    vin             varchar(40),
    numero_recu     varchar(30),
    date_paiement   date,
    date_ouverture  date,
    type_dossier    varchar(80),
    genre           varchar(60),
    mode_paiement   varchar(20),
    type_client     varchar(40),
    client          varchar(250),
    mt_plaques      integer,
    mt_secupose     integer,
    pdf_recu        text,
    cree_le         timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    maj_le          timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_fin_src_cil_vin ON fin_src_cil (vin);
CREATE INDEX IF NOT EXISTS idx_fin_src_cil_paiement ON fin_src_cil (date_paiement);

CREATE TABLE IF NOT EXISTS fin_src_brique (
    id_brique       bigint       PRIMARY KEY,
    chrono          varchar(30),
    vin             varchar(40),
    nsiiv           varchar(20),
    source          varchar(30),
    date_creation   timestamp,
    statut          varchar(160),
    date_maj        timestamp,
    immat           varchar(20),
    type_plaque     varchar(60),
    genre           varchar(60),
    motif_rejet     varchar(250),
    type_personne   varchar(20),
    n_carte_grise   varchar(30),
    cree_le         timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    maj_le          timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_fin_src_brique_vin ON fin_src_brique (vin);
CREATE INDEX IF NOT EXISTS idx_fin_src_brique_chrono ON fin_src_brique (chrono);

CREATE TABLE IF NOT EXISTS fin_src_cgi (
    nsiiv           varchar(20)  PRIMARY KEY,
    chrono          varchar(30),
    vin             varchar(40),
    date_creation   timestamp,
    date_validation timestamp,
    immat           varchar(20),
    ancienne_immat  varchar(20),
    type_dossier    varchar(20),
    type_enrolement varchar(40),
    site_enrolement varchar(120),
    etat            varchar(40),
    genre           varchar(60),
    nb_plaques      integer,
    concessionnaire varchar(120),
    client          varchar(250),
    mode_paiement   varchar(20),
    source          varchar(30),
    supprime        boolean NOT NULL DEFAULT false,
    cree_le         timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    maj_le          timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_fin_src_cgi_vin ON fin_src_cgi (vin);
CREATE INDEX IF NOT EXISTS idx_fin_src_cgi_chrono ON fin_src_cgi (chrono);

-- Une ligne X3 par pose : clé = chrono (ou n° NSIIV) + date de pose.
CREATE TABLE IF NOT EXISTS fin_src_x3 (
    cle_ligne          varchar(60)  PRIMARY KEY,
    chrono             varchar(30),
    nsiiv              varchar(20),
    date_pose          date,
    client             varchar(250),
    genre              varchar(40),
    vin                varchar(40),
    code_rec           varchar(40),
    montant_reconcilie numeric(14,2),
    n_facture          varchar(40),
    montant_ht         numeric(14,2),
    montant_ttc        numeric(14,2),
    n_sortie           varchar(40),
    quantite           integer,
    article1           varchar(60),
    serie1             varchar(40),
    article2           varchar(60),
    serie2             varchar(40),
    -- X3 ne donne pas la date de réconciliation : première fois qu'un import
    -- a vu la ligne réconciliée (code de réconciliation renseigné).
    reconcilie_le      date,
    cree_le            timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    maj_le             timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_fin_src_x3_vin ON fin_src_x3 (vin);
CREATE INDEX IF NOT EXISTS idx_fin_src_x3_chrono ON fin_src_x3 (chrono);

CREATE TABLE IF NOT EXISTS fin_src_caisse (
    id_caisse         bigint       PRIMARY KEY,
    chrono            varchar(30),
    vin               varchar(40),
    concessionnaire   varchar(120),
    statut            varchar(30),
    mode_paiement     varchar(30),
    n_bon             varchar(60),
    montant           numeric(14,2),
    date_facturation  timestamp,
    date_encaissement timestamp,
    date_creation     timestamp,
    date_maj          timestamp,
    statut_dossier    varchar(160),
    genre             varchar(60),
    nb_plaques        integer,
    cree_le           timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    maj_le            timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_fin_src_caisse_vin ON fin_src_caisse (vin);

-- Une ligne par plaque posée (export OptoPlate) : clé = dossier + position.
CREATE TABLE IF NOT EXISTS fin_src_pose (
    cle_ligne         varchar(80)  PRIMARY KEY,
    nsiiv             varchar(20),
    vin               varchar(40),
    immat             varchar(20),
    position          varchar(10),
    num_consommable   varchar(40),
    statut_plaque     varchar(30),
    date_installation timestamp,
    site              varchar(120),
    cree_le           timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    maj_le            timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_fin_src_pose_vin ON fin_src_pose (vin);

-- Montant attendu par genre de véhicule (TTC). Vide = montant de l'API CIL.
CREATE TABLE IF NOT EXISTS fin_tarifs (
    genre       varchar(60)  PRIMARY KEY,
    montant_ttc integer,
    maj_par     integer,
    maj_le      timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Journal des imports et synchronisations, avec leur bilan.
CREATE TABLE IF NOT EXISTS fin_imports (
    id            serial       PRIMARY KEY,
    source        varchar(20)  NOT NULL,
    fichier       varchar(250),
    periode_du    date,
    periode_au    date,
    nb_lignes     integer NOT NULL DEFAULT 0,
    nb_ajoutees   integer NOT NULL DEFAULT 0,
    nb_maj        integer NOT NULL DEFAULT 0,
    nb_inchangees integer NOT NULL DEFAULT 0,
    nb_rejetees   integer NOT NULL DEFAULT 0,
    details       text,
    duree_ms      integer,
    importe_par   integer,
    cree_le       timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_fin_imports_source ON fin_imports (source, cree_le);

-- Vue consolidée : un dossier par ligne, reconstruite par fin_consolider().
CREATE TABLE IF NOT EXISTS fin_dossiers (
    cle                 varchar(60)  PRIMARY KEY,
    vin                 varchar(40),
    chrono              varchar(30),
    nsiiv               varchar(20),
    immat               varchar(20),
    canal               varchar(20),
    type_dossier        varchar(20),
    type_dossier_cil    varchar(80),
    genre               varchar(60),
    centre              varchar(120),
    client              varchar(250),
    type_client         varchar(40),
    date_ouverture      date,
    date_paiement       date,
    mode_paiement       varchar(20),
    numero_recu         varchar(30),
    pdf_recu            text,
    mt_plaques          integer,
    mt_secupose         integer,
    montant_dossier     integer,
    date_reception      timestamp,
    statut_brique       varchar(160),
    motif_rejet         varchar(250),
    date_enrolement     timestamp,
    etat_cgi            varchar(40),
    code_rec            varchar(40),
    n_facture           varchar(40),
    montant_reconcilie  numeric(14,2),
    montant_ht          numeric(14,2),
    montant_ttc         numeric(14,2),
    date_reconciliation date,
    caisse_statut       varchar(30),
    n_bon               varchar(60),
    date_installation   timestamp,
    date_pose           date,
    plaque_avant        varchar(40),
    plaque_arriere      varchar(40),
    source_pose         varchar(10),
    montant_attendu     integer,
    ecart               numeric(14,2),
    est_ouvert          boolean NOT NULL DEFAULT false,
    est_recu            boolean NOT NULL DEFAULT false,
    est_reconcilie      boolean NOT NULL DEFAULT false,
    est_pose            boolean NOT NULL DEFAULT false,
    paye_par_bon        boolean NOT NULL DEFAULT false,
    etape               varchar(12),
    a_ouvert_non_recu     boolean NOT NULL DEFAULT false,
    a_pose_non_reconcilie boolean NOT NULL DEFAULT false,
    a_ecart               boolean NOT NULL DEFAULT false,
    a_recu_sans_paiement  boolean NOT NULL DEFAULT false,
    a_rejet               boolean NOT NULL DEFAULT false,
    a_pose_sans_dossier   boolean NOT NULL DEFAULT false,
    date_ref            date,
    maj_le              timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);
-- Ajoutée après la première écriture du module : bases déjà migrées.
ALTER TABLE fin_dossiers ADD COLUMN IF NOT EXISTS date_installation timestamp;
CREATE INDEX IF NOT EXISTS idx_fin_dossiers_date_ref ON fin_dossiers (date_ref);
CREATE INDEX IF NOT EXISTS idx_fin_dossiers_etape ON fin_dossiers (etape);
CREATE INDEX IF NOT EXISTS idx_fin_dossiers_vin ON fin_dossiers (vin);
CREATE INDEX IF NOT EXISTS idx_fin_dossiers_chrono ON fin_dossiers (chrono);
CREATE INDEX IF NOT EXISTS idx_fin_dossiers_nsiiv ON fin_dossiers (nsiiv);
CREATE INDEX IF NOT EXISTS idx_fin_dossiers_immat ON fin_dossiers (immat);

-- ── Droits : module finance_dossiers
--    lecture = écran de suivi ; création = imports et synchronisation API ;
--    mise à jour = tarifs. Admin et superadmin passent par can() sans ligne.
--    RAF et DAF : tout ; PDG (lecteur) et DG : lecture.
INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, 'finance_dossiers', 1, 1, 1, 0, 1
FROM roles r
WHERE r.slug IN ('admin', 'superadmin', 'raf', 'daf')
ON CONFLICT (role_id, module) DO UPDATE SET
    can_read = 1, can_create = 1, can_update = 1, can_export = 1;

INSERT INTO permissions (role_id, module, can_read, can_create, can_update, can_delete, can_export)
SELECT r.id, 'finance_dossiers', 1, 0, 0, 0, 1
FROM roles r
WHERE r.slug IN ('lecteur', 'directeur_general')
ON CONFLICT (role_id, module) DO UPDATE SET can_read = 1, can_export = 1;

COMMIT;
