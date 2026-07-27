-- ============================================================
--  Migration : module départements (compatible PostgreSQL / Neon)
--  Exécuter une seule fois — idempotent (IF NOT EXISTS / IF NOT EXISTS)
-- ============================================================

CREATE TABLE IF NOT EXISTS departements (
    id         SERIAL PRIMARY KEY,
    code       VARCHAR(50) UNIQUE NOT NULL,
    label      VARCHAR(100) NOT NULL,
    ordre      INTEGER NOT NULL DEFAULT 0,
    actif      SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_departements (
    user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    departement_id INTEGER NOT NULL REFERENCES departements(id) ON DELETE CASCADE,
    is_n1          SMALLINT NOT NULL DEFAULT 0,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, departement_id)
);

-- Mémorise le N+1 résolu au moment de la soumission d'une demande
ALTER TABLE di_demandes ADD COLUMN IF NOT EXISTS n1_user_id INTEGER REFERENCES users(id);
