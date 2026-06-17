<?php
$secret = $_GET['secret'] ?? '';
if ($secret !== 'emuci2026import') die('Accès refusé');
require_once __DIR__ . '/includes/db.php';
$db = get_db();
$db->exec("SET FOREIGN_KEY_CHECKS=0");

// Supprimer et recréer commandes_bobines avec toutes les colonnes
$db->exec("DROP TABLE IF EXISTS `commandes_bobines`");
$db->exec("CREATE TABLE `commandes_bobines` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `numero` varchar(50) NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `type_bobine` varchar(20) DEFAULT NULL,
  `libelle_type` varchar(255) DEFAULT NULL,
  `statut` enum('en_attente','validee','livree','refusee','annulee') NOT NULL DEFAULT 'en_attente',
  `notes` text DEFAULT NULL,
  `demande_par` int(10) UNSIGNED DEFAULT NULL,
  `superviseur_id` int(10) UNSIGNED DEFAULT NULL,
  `gsb_id` int(10) UNSIGNED DEFAULT NULL,
  `traite_par` int(10) UNSIGNED DEFAULT NULL,
  `traite_at` datetime DEFAULT NULL,
  `bobine_id` int(10) UNSIGNED DEFAULT NULL,
  `motif_refus` text DEFAULT NULL,
  `checked` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$db->exec("SET FOREIGN_KEY_CHECKS=1");
echo "✅ commandes_bobines recréée avec toutes les colonnes!";
echo "<p><b>SUPPRIMEZ ce fichier!</b></p>";
?>
