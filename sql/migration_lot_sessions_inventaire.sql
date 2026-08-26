-- ============================================================
--  n° 19 réunion ERP — Sessions d'inventaire (mensuel)
-- ============================================================
--
--  Une session encadre une campagne d'inventaire mensuel : un responsable
--  l'ouvre pour une période et une liste de sites, les chefs de site
--  saisissent pendant cette fenêtre, le responsable suit l'avancement site
--  par site puis clôture une fois tout validé.
--
--  L'inventaire journalier n'est pas concerné : il reste libre, comme
--  aujourd'hui. Seul l'inventaire mensuel passe désormais par une session.
--
--  Idempotent.
-- ============================================================
BEGIN;

CREATE TABLE IF NOT EXISTS inventaire_sessions (
    id           SERIAL PRIMARY KEY,
    libelle      varchar(150),
    date_debut   date NOT NULL,
    date_fin     date NOT NULL,
    statut       varchar(20) NOT NULL DEFAULT 'ouverte',  -- 'ouverte' | 'cloturee'
    notes        text,
    ouverte_par  integer REFERENCES users(id),
    ouverte_at   timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cloturee_par integer REFERENCES users(id),
    cloturee_at  timestamp,
    created_at   timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT inventaire_sessions_periode_chk CHECK (date_fin >= date_debut)
);

-- Sites concernés par une session — un site peut appartenir à plusieurs
-- sessions dans le temps, mais pas à deux sessions OUVERTES en même temps
-- (contrôlé côté applicatif : une contrainte SQL ne sait pas exclure
-- seulement les lignes 'ouverte').
CREATE TABLE IF NOT EXISTS inventaire_session_sites (
    session_id integer NOT NULL REFERENCES inventaire_sessions(id) ON DELETE CASCADE,
    site_id    integer NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
    PRIMARY KEY (session_id, site_id)
);

-- Rattache chaque inventaire mensuel à la session qui l'a autorisé. NULL
-- pour les inventaires journaliers (jamais rattachés) et pour les
-- inventaires mensuels créés avant cette migration (historique préservé).
ALTER TABLE inventaires_bobines ADD COLUMN IF NOT EXISTS session_id integer REFERENCES inventaire_sessions(id);

CREATE INDEX IF NOT EXISTS inventaires_bobines_session_idx ON inventaires_bobines (session_id);

COMMIT;
