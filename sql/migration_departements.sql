-- ============================================================
--  Migration : module départements (variante MySQL)
--  Exécuter une seule fois. Le rejeu peut produire des erreurs
--  "objet déjà existant" (table/contrainte) — sans risque, à ignorer.
-- ============================================================

CREATE TABLE IF NOT EXISTS departements (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code       VARCHAR(50) UNIQUE NOT NULL,
    label      VARCHAR(100) NOT NULL,
    ordre      INT NOT NULL DEFAULT 0,
    actif      TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_departements (
    user_id        INT UNSIGNED NOT NULL,
    departement_id INT UNSIGNED NOT NULL,
    is_n1          TINYINT(1) NOT NULL DEFAULT 0,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, departement_id),
    CONSTRAINT fk_user_departements_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_departements_dept FOREIGN KEY (departement_id) REFERENCES departements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- di_demandes.n1_user_id existe déjà dans le schéma MySQL natif
-- (sql/demandes_internes_mysql.sql) : rien à ajouter ici, contrairement
-- à la variante PostgreSQL qui la porte séparément.
