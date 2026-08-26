-- ============================================================
--  Changement de mot de passe forcé à la première connexion
-- ============================================================
--
--  Un compte créé par un admin (ou dont le mot de passe est réinitialisé
--  par un admin) reçoit un mot de passe que l'admin connaît. Sans garde-
--  fou, rien n'oblige l'utilisateur à en choisir un autre : ce mot de
--  passe "par défaut" peut rester valide indéfiniment.
--
--  smallint 0/1, pas boolean : convention déjà en place sur users.actif
--  dans ce schéma d'origine MySQL.

BEGIN;

ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password smallint NOT NULL DEFAULT 0;

COMMIT;
