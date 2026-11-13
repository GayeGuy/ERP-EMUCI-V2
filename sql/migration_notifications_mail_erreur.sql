-- ============================================================
--  Notifications par mail : dernier message d'erreur et date d'envoi.
--  notifications.mail_erreur : motif du dernier refus SMTP, ou raison pour
--  laquelle le mail n'est pas parti (statut 'ignore').
--  notifications.mail_envoye_le : date d'envoi effective.
--  Affichés par la page Administration > Mails. Rejouable.
-- ============================================================
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS mail_erreur varchar(255);
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS mail_envoye_le timestamp;
