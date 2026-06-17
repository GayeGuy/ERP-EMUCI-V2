<?php
$secret = $_GET['secret'] ?? '';
if ($secret !== 'emuci2026import') die('Accès refusé');
require_once __DIR__ . '/includes/db.php';
$db = get_db();
$db->exec("SET FOREIGN_KEY_CHECKS=0");

$db->exec("DROP TABLE IF EXISTS `import_optoplate`");
$db->exec("CREATE TABLE `import_optoplate` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` int(10) UNSIGNED DEFAULT NULL,
  `site_id` int(10) UNSIGNED DEFAULT NULL,
  `site_nom_emuci` varchar(255) DEFAULT NULL,
  `date_import` date DEFAULT NULL,
  `statut_plaque` varchar(50) DEFAULT NULL,
  `num_bobine` varchar(50) DEFAULT NULL,
  `numero_plaque` varchar(50) DEFAULT NULL,
  `immatriculation` varchar(100) DEFAULT NULL,
  `numero_chassis` varchar(100) DEFAULT NULL,
  `type_vehicule` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->exec("SET FOREIGN_KEY_CHECKS=1");
echo "✅ import_optoplate recréée avec num_bobine et immatriculation!";
echo "<p><b>SUPPRIMEZ ce fichier!</b></p>";
?>
