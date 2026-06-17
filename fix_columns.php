<?php
$secret = $_GET['secret'] ?? '';
if ($secret !== 'emuci2026import') die('Accès refusé');
require_once __DIR__ . '/includes/db.php';
$db = get_db();

// Ajouter la colonne nom_emuci à sites
$alters = [
    "ALTER TABLE `sites` ADD COLUMN `nom_emuci` varchar(255) DEFAULT NULL AFTER `nom`",
    "ALTER TABLE `sites` ADD COLUMN `mobile` tinyint(1) DEFAULT 0",
    "ALTER TABLE `sites` ADD COLUMN `latitude` decimal(10,8) DEFAULT NULL",
    "ALTER TABLE `sites` ADD COLUMN `longitude` decimal(11,8) DEFAULT NULL",
    "ALTER TABLE `sites` ADD COLUMN `date_debut_mission` date DEFAULT NULL",
    "ALTER TABLE `sites` ADD COLUMN `date_fin_mission` date DEFAULT NULL",
    "ALTER TABLE `sites` ADD COLUMN `option_caisse` tinyint(1) DEFAULT 0",
];

$success = 0; $errors = [];
foreach ($alters as $sql) {
    try {
        $db->exec($sql);
        $success++;
    } catch(PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate') !== false || strpos($msg, 'already exists') !== false) {
            $success++;
        } else {
            $errors[] = $msg;
        }
    }
}

echo "<h2>✅ $success colonnes ajoutées à sites</h2>";
if ($errors) {
    echo "<ul>";
    foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>";
    echo "</ul>";
}
echo "<p><b>SUPPRIMEZ ce fichier!</b></p>";
?>
