<?php
$secret = $_GET['secret'] ?? '';
if ($secret !== 'emuci2026import') die('Accès refusé');
require_once __DIR__ . '/includes/db.php';
$db = get_db();
$db->exec("SET FOREIGN_KEY_CHECKS=0");

$db->exec("DROP TABLE IF EXISTS `emuci_sites_inconnus`");
$db->exec("CREATE TABLE `emuci_sites_inconnus` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom_site` varchar(255) NOT NULL,
  `session_id` int(10) UNSIGNED DEFAULT NULL,
  `nb_occurrences` int(11) NOT NULL DEFAULT 1,
  `derniere_apparition` datetime DEFAULT current_timestamp(),
  `statut` enum('en_attente','ignore','lie','cree') NOT NULL DEFAULT 'en_attente',
  `site_id_lie` int(10) UNSIGNED DEFAULT NULL,
  `traite_par` int(10) UNSIGNED DEFAULT NULL,
  `traite_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->exec("SET FOREIGN_KEY_CHECKS=1");
echo "✅ emuci_sites_inconnus recréée avec nb_occurrences!";
echo "<p><b>SUPPRIMEZ ce fichier!</b></p>";
?>
