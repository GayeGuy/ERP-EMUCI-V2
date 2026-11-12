-- ============================================================
--  Notifications par mail : réglage par utilisateur (PostgreSQL / Neon).
--  users.notif_mail : 1 = reçoit les notifications par mail (défaut), 0 = les
--  a coupées (elles restent visibles dans l'application). Modifiable par
--  l'utilisateur (Mon profil) et par l'administrateur (Utilisateurs). Rejouable.
-- ============================================================
ALTER TABLE users ADD COLUMN IF NOT EXISTS notif_mail smallint NOT NULL DEFAULT 1;
