-- ============================================================
--  Notifications par mail : file d'attente d'envoi (PostgreSQL / Neon).
--  notifications.mail_statut : 'attente' (à envoyer, valeur par défaut des
--  nouvelles lignes, INSERT directs compris), 'envoye', 'ignore' (déjà lue,
--  sans destinataire valide, trop ancienne), 'echec' (3 essais),
--  'historique' (notifications d'avant cette migration : jamais envoyées).
--  mail_tentatives : nombre d'essais d'envoi. Rejouable.
-- ============================================================
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = current_schema() AND table_name = 'notifications' AND column_name = 'mail_statut') THEN
        -- Ajoutée avec 'historique' (les lignes existantes ne partent pas par
        -- mail), puis la valeur par défaut passe à 'attente' pour les nouvelles.
        ALTER TABLE notifications ADD COLUMN mail_statut varchar(12) NOT NULL DEFAULT 'historique';
        ALTER TABLE notifications ADD COLUMN mail_tentatives smallint NOT NULL DEFAULT 0;
        ALTER TABLE notifications ALTER COLUMN mail_statut SET DEFAULT 'attente';
        CREATE INDEX idx_notif_mail ON notifications (mail_statut, id);
    END IF;
END $$;
