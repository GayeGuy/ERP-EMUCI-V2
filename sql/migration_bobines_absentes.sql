-- ============================================================
--  Bobines absentes à l'inventaire
--
--  Pendant l'inventaire, le coordinateur déclare « Absente » une bobine
--  que l'ERP (ou l'import BI) place sur son site mais qu'il ne trouve
--  pas. À la validation, elle part à l'entrepôt principal au statut
--  « absente », hors de tout stock disponible. Le coordinateur qui la
--  retrouve l'ajoute à l'inventaire de son site ; à la validation, elle
--  y est affectée avec la quantité comptée.
--  Cf. includes/bobines_absentes.php, includes/inventaire.php.
--
--  Idempotente : IF NOT EXISTS.
-- ============================================================
ALTER TABLE inventaire_details_bobines ADD COLUMN IF NOT EXISTS absente smallint NOT NULL DEFAULT 0;
ALTER TABLE inventaire_details_bobines ADD COLUMN IF NOT EXISTS ajoutee smallint NOT NULL DEFAULT 0;
ALTER TABLE inventaire_details_bobines ADD COLUMN IF NOT EXISTS site_origine_id integer DEFAULT NULL;

ALTER TABLE op_bobines ADD COLUMN IF NOT EXISTS absente_site_id integer DEFAULT NULL;
ALTER TABLE op_bobines ADD COLUMN IF NOT EXISTS absente_le date DEFAULT NULL;
ALTER TABLE op_bobines ADD COLUMN IF NOT EXISTS absente_inventaire_id integer DEFAULT NULL;
