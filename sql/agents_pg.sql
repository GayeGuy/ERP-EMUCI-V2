-- ============================================================
--  Module "Agents" — table des agents NSIIV
--  Alimentée par import Excel depuis pages/agents.php.
--  Utilisée par le module demandes internes pour l'autofill.
-- ============================================================
BEGIN;

CREATE TABLE IF NOT EXISTS agents (
    id          SERIAL PRIMARY KEY,
    matricule   varchar(50),
    nom         varchar(100) NOT NULL,
    prenom      varchar(100) NOT NULL DEFAULT '',
    email       varchar(150),
    telephone   varchar(30),
    fonction    varchar(150),
    departement varchar(150),
    direction   varchar(150),
    site        varchar(150),
    grade       varchar(100),
    statut      varchar(20) NOT NULL DEFAULT 'actif',
    created_at  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Unicité sur matricule non vide (NULL autorisé en double)
CREATE UNIQUE INDEX IF NOT EXISTS agents_matricule_uidx
    ON agents (matricule)
    WHERE matricule IS NOT NULL AND matricule <> '';

CREATE INDEX IF NOT EXISTS agents_nom_idx ON agents (nom, prenom);

-- Permissions initiales (admin + superadmin accès total, gsb lecture)
-- Adapter selon la structure de votre table permissions :
-- INSERT INTO permissions (role_slug, module, can_create, can_read, can_update, can_delete)
-- VALUES
--     ('admin',      'agents', 1, 1, 1, 1),
--     ('superadmin', 'agents', 1, 1, 1, 1),
--     ('gsb',        'agents', 0, 1, 0, 0)
-- ON CONFLICT (role_slug, module) DO NOTHING;

COMMIT;
