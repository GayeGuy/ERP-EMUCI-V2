<?php
$secret = $_GET['secret'] ?? '';
if ($secret !== 'emuci2026import') die('Accès refusé');
require_once __DIR__ . '/includes/db.php';
$db = get_db();

$alters = [
    "ALTER TABLE `commandes_bobines` ADD COLUMN IF NOT EXISTS `gsb_id` int(10) UNSIGNED DEFAULT NULL",
    "ALTER TABLE `commandes_bobines` ADD COLUMN IF NOT EXISTS `superviseur_id` int(10) UNSIGNED DEFAULT NULL",
    "ALTER TABLE `commandes_bobines` ADD COLUMN IF NOT EXISTS `checked` tinyint(1) DEFAULT 0",
    "ALTER TABLE `commandes_bobines` ADD COLUMN IF NOT EXISTS `type_bobine` varchar(20) DEFAULT NULL",
    "ALTER TABLE `commandes_bobines` ADD COLUMN IF NOT EXISTS `libelle_type` varchar(255) DEFAULT NULL",
    "ALTER TABLE `commandes_bobines` ADD COLUMN IF NOT EXISTS `traite_par` int(10) UNSIGNED DEFAULT NULL",
    "ALTER TABLE `commandes_bobines` ADD COLUMN IF NOT EXISTS `traite_at` datetime DEFAULT NULL",
    "ALTER TABLE `commandes_bobines` ADD COLUMN IF NOT EXISTS `bobine_id` int(10) UNSIGNED DEFAULT NULL",
    "ALTER TABLE `commandes_bobines` ADD COLUMN IF NOT EXISTS `motif_refus` text DEFAULT NULL",
];

$success = 0; $errors = [];
foreach ($alters as $sql) {
    try {
        $db->exec($sql);
        $success++;
    } catch(PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate') === false) $errors[] = $msg;
        else $success++;
    }
}

echo "<h2>✅ $success colonnes ajoutées</h2>";
if ($errors) {
    echo "<ul>";
    foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>";
    echo "</ul>";
}
echo "<p><b>SUPPRIMEZ ce fichier!</b></p>";
?>
