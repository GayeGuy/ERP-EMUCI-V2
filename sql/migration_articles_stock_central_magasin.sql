-- ============================================================================
--  migration_articles_stock_central_magasin.sql  (PostgreSQL, rejouable)
--
--  Articles importés au magasin : report du stock magasin dans le stock
--  central (articles.stock_global). L'import posait la quantité sur le site
--  « Magasin » sans toucher au stock central : l'article affichait 0 et la
--  Distribution le refusait. Ne touche que les articles encore à 0 avec du
--  stock sur un site de type 'magasin'.
-- ============================================================================
UPDATE articles a
   SET stock_global = m.qte_magasin
  FROM (SELECT ss.article_id, SUM(ss.quantite) AS qte_magasin
          FROM stock_site ss
          JOIN sites s ON s.id = ss.site_id
         WHERE s.type = 'magasin'
         GROUP BY ss.article_id) m
 WHERE m.article_id = a.id
   AND COALESCE(a.stock_global, 0) = 0
   AND m.qte_magasin > 0;

-- receptions_site.consommable_id contient un id d'ARTICLE (pages/articles.php
-- et pages/reception_site.php le joignent à `articles`) mais sa clé étrangère
-- pointait sur l'ancienne table `consommables` : toute Distribution depuis la
-- page Articles échouait.
ALTER TABLE receptions_site DROP CONSTRAINT IF EXISTS receptions_site_ibfk_3;
