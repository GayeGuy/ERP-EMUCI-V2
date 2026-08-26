-- ============================================================
--  Retire les contraintes FK bloquant reception/distribution pour tout
--  article cree apres la scission consommables -> articles
-- ============================================================
--
--  pages/articles.php ecrit encore, en "compat legacy", vers
--  receptions_consommables / livraisons_consommables / receptions_site /
--  stock_consommables_site avec consommable_id = l'ID de l'article —
--  mais ces quatre tables portent une FK stricte vers consommables(id),
--  jamais peuplee pour un article cree depuis la scission (create_article
--  n'insere aucune ligne consommables en contrepartie). Resultat verifie
--  en sandbox : reception et distribution echouent (violation de
--  contrainte) pour tout article cree apres la migration MySQL ->
--  PostgreSQL du 2026-07-21 — les quatre FK bloquent chacune leur tour
--  tant qu'elles ne sont pas toutes retirees.
--
--  stock_consommables_site elle-meme disparaitra completement dans une
--  migration separee, mais reste ecrite aujourd'hui par pages/articles.php
--  donc bloquante des maintenant.

ALTER TABLE livraisons_consommables DROP CONSTRAINT IF EXISTS livraisons_consommables_ibfk_1;
ALTER TABLE receptions_consommables DROP CONSTRAINT IF EXISTS receptions_consommables_ibfk_1;
ALTER TABLE receptions_site DROP CONSTRAINT IF EXISTS receptions_site_ibfk_3;
ALTER TABLE stock_consommables_site DROP CONSTRAINT IF EXISTS stock_consommables_site_ibfk_1;
