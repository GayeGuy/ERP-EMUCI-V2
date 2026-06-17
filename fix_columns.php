<?php
$secret = $_GET['secret'] ?? '';
if ($secret !== 'emuci2026import') die('Accès refusé');
require_once __DIR__ . '/includes/db.php';
$db = get_db();
$db->exec("SET FOREIGN_KEY_CHECKS=0");

// Recréer import_optotrace avec toutes les colonnes
$db->exec("DROP TABLE IF EXISTS `import_optotrace`");
$db->exec("CREATE TABLE `import_optotrace` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` int(10) UNSIGNED DEFAULT NULL,
  `site_id` int(10) UNSIGNED DEFAULT NULL,
  `site_nom_emuci` varchar(255) DEFAULT NULL,
  `date_import` date DEFAULT NULL,
  `keyname` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `state` int(11) DEFAULT NULL,
  `numero_chassis` varchar(100) DEFAULT NULL,
  `numero_plaque` varchar(50) DEFAULT NULL,
  `type_vehicule` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Recréer import_optoplate avec toutes les colonnes
$db->exec("DROP TABLE IF EXISTS `import_optoplate`");
$db->exec("CREATE TABLE `import_optoplate` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` int(10) UNSIGNED DEFAULT NULL,
  `site_id` int(10) UNSIGNED DEFAULT NULL,
  `site_nom_emuci` varchar(255) DEFAULT NULL,
  `date_import` date DEFAULT NULL,
  `statut_plaque` varchar(50) DEFAULT NULL,
  `numero_plaque` varchar(50) DEFAULT NULL,
  `numero_chassis` varchar(100) DEFAULT NULL,
  `type_vehicule` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->exec("SET FOREIGN_KEY_CHECKS=1");
echo "✅ import_optotrace et import_optoplate recréées!";
echo "<p><b>SUPPRIMEZ ce fichier!</b></p>";
?>
