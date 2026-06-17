<?php
$secret = $_GET['secret'] ?? '';
if ($secret !== 'emuci2026import') die('Accès refusé');
require_once __DIR__ . '/includes/db.php';
$db = get_db();
$db->exec("SET FOREIGN_KEY_CHECKS=0");

$db->exec("DROP TABLE IF EXISTS `commande_lignes`");
$db->exec("CREATE TABLE `commande_lignes` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `commande_id` int(10) UNSIGNED NOT NULL,
  `article_id` int(10) UNSIGNED DEFAULT NULL,
  `distribution_id` int(10) UNSIGNED DEFAULT NULL,
  `type_article` enum('consommable','bobine','pmma','rivet','equipement','autre') NOT NULL DEFAULT 'consommable',
  `libelle` varchar(255) NOT NULL,
  `quantite` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `unite` varchar(30) DEFAULT 'unité',
  `prix_unitaire` int(11) DEFAULT 0,
  `quantite_livree` int(10) UNSIGNED DEFAULT NULL,
  `motif_ecart` text DEFAULT NULL,
  `statut_ligne` enum('en_attente','valide','modifie','rejete') NOT NULL DEFAULT 'en_attente',
  `motif_rejet` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->exec("SET FOREIGN_KEY_CHECKS=1");
echo "✅ commande_lignes recréée!";
echo "<p><b>SUPPRIMEZ ce fichier!</b></p>";
?>
