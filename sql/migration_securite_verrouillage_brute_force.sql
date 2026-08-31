BEGIN;

-- Aucune protection contre le brute force sur le login : auth_login()
-- journalisait les échecs (audit_log) mais ne bloquait jamais un compte,
-- même après des centaines de tentatives (plan sécurité, identifié 30/08).
--
-- Verrouillage temporaire par compte après 5 échecs consécutifs, pour
-- 15 minutes — même durée que le délai d'inactivité déjà en place
-- (INACTIVITY_TIMEOUT, includes/session.php), pour garder une seule
-- convention de durée dans l'app plutôt que d'en introduire une seconde.
--
-- Compteur simple (pas de fenêtre glissante datée par tentative) : le
-- compteur remonte à zéro à chaque connexion réussie ou dès qu'un
-- verrouillage est posé. Suffisant pour ralentir un brute force
-- automatisé, sans la complexité d'un historique par tentative.
ALTER TABLE users ADD COLUMN IF NOT EXISTS failed_login_attempts integer NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS locked_until timestamp DEFAULT NULL;

COMMIT;
