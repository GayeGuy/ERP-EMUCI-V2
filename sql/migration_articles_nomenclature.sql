-- ============================================================
--  migration_articles_nomenclature.sql  (PostgreSQL, rejouable)
--  Les articles de la famille « Matériel et outillage industriel »
--  deviennent des équipements opérationnels du site qui les reçoit
--  (includes/articles_equipements.php). articles.nomenclature_id garde
--  le type d'équipement utilisé, créé à la première arrivée.
-- ============================================================
ALTER TABLE articles ADD COLUMN IF NOT EXISTS nomenclature_id integer DEFAULT NULL
    REFERENCES nomenclatures(id) ON DELETE SET NULL;
