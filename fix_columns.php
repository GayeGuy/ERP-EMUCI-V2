<?php
$secret = $_GET['secret'] ?? '';
if ($secret !== 'emuci2026import') die('Accès refusé');
require_once __DIR__ . '/includes/db.php';
$db = get_db();
$db->exec("SET FOREIGN_KEY_CHECKS=0");

// Recréer emuci_sites_inconnus avec toutes les colonnes
$db->exec("DROP TABLE IF EXISTS `emuci_sites_inconnus`");
$db->exec("CREATE TABLE `emuci_sites_inconnus` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom_site` varchar(255) NOT NULL,
  `session_id` int(10) UNSIGNED DEFAULT NULL,
  `statut` enum('en_attente','ignore','lie','cree') NOT NULL DEFAULT 'en_attente',
  `site_id_lie` int(10) UNSIGNED DEFAULT NULL,
  `traite_par` int(10) UNSIGNED DEFAULT NULL,
  `traite_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Ajouter colonnes manquantes à equipements
$alters_equip = [
    "ALTER TABLE `equipements` ADD COLUMN `statut` varchar(50) DEFAULT 'actif'",
    "ALTER TABLE `equipements` ADD COLUMN `statut_stock` enum('affecte','en_stock') DEFAULT 'en_stock'",
    "ALTER TABLE `equipements` ADD COLUMN `duree_amortissement_mois` int(11) DEFAULT NULL",
    "ALTER TABLE `equipements` ADD COLUMN `prix_achat` decimal(15,2) DEFAULT NULL",
    "ALTER TABLE `equipements` ADD COLUMN `numero_serie_externe` varchar(100) DEFAULT NULL",
];

$success = 0; $errors = [];
foreach ($alters_equip as $sql) {
    try {
        $db->exec($sql);
        $success++;
    } catch(PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate') !== false) $success++;
        else $errors[] = $msg;
    }
}

$db->exec("SET FOREIGN_KEY_CHECKS=1");
echo "<h2>✅ $success corrections appliquées</h2>";
if ($errors) {
    echo "<ul>";
    foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>";
    echo "</ul>";
}
echo "<p><b>SUPPRIMEZ ce fichier!</b></p>";
?>
