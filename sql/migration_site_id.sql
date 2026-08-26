-- ============================================================
--  site_id sur articles et di_demandes
-- ============================================================
--
--  Ces deux colonnes ont été ajoutées en production le 2026-07-29 par le
--  script migrate_site_id.php, exécuté une fois depuis le navigateur. Elles
--  n'ont jamais été reversées dans sql/, si bien qu'une base reconstruite à
--  partir du dépôt ne les a pas — et que di_creer() échoue alors sur
--  « column site_id of relation di_demandes does not exist », ce qui rend
--  impossible la création de toute demande interne.
--
--  Ce fichier remet le schéma du dépôt en accord avec la production.
--  Idempotent : sans effet sur une base qui a déjà reçu le script.

ALTER TABLE articles    ADD COLUMN IF NOT EXISTS site_id integer REFERENCES sites(id) ON DELETE SET NULL;
ALTER TABLE di_demandes ADD COLUMN IF NOT EXISTS site_id integer REFERENCES sites(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_articles_site_id    ON articles(site_id);
CREATE INDEX IF NOT EXISTS idx_di_demandes_site_id ON di_demandes(site_id);
