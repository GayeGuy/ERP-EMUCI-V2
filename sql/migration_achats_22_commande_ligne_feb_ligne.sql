-- ============================================================
--  Traçabilité commande interne -> FEB d'origine, ligne par ligne
-- ============================================================
--
--  ach_basculer_vers_commande() (arbitrage « stock » d'une ligne FEB)
--  copiait déjà la ligne vers commande_lignes, mais sans garder de lien
--  vers la feb_lignes source — seul commandes.feb_id existait, au niveau
--  de l'en-tête. Une correction de quantité faite plus tard côté commande
--  (écart constaté à l'expédition, cf. pages/commandes.php) n'avait donc
--  aucun moyen de remonter vers la FEB, qui gardait silencieusement
--  l'ancienne quantité — discuté et décidé le 2026-08-25.

ALTER TABLE commande_lignes ADD COLUMN IF NOT EXISTS feb_ligne_id INTEGER REFERENCES feb_lignes(id) ON DELETE SET NULL;
