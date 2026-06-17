<?php
$secret = $_GET['secret'] ?? '';
if ($secret !== 'emuci2026import') die('Accès refusé');
require_once __DIR__ . '/includes/db.php';
$db = get_db();

$json_file = __DIR__ . '/import_data.json';
if (!file_exists($json_file)) die('Fichier import_data.json introuvable');

$commands = json_decode(file_get_contents($json_file), true);
if (!$commands) die('Erreur JSON: ' . json_last_error_msg());

$db->exec("SET FOREIGN_KEY_CHECKS=0");
$success = 0; $errors = [];

foreach ($commands as $cmd) {
    $cmd = trim($cmd);
    if (empty($cmd)) continue;
    try {
        $db->exec($cmd);
        $success++;
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'already exists') === false && strpos($msg, 'Duplicate') === false) {
            $errors[] = substr($msg, 0, 150) . ' | ' . substr($cmd, 0, 60);
        }
    }
}

$db->exec("SET FOREIGN_KEY_CHECKS=1");
echo "<h2>✅ $success commandes exécutées</h2>";
if ($errors) {
    echo "<p>⚠️ ".count($errors)." erreurs:</p><ul>";
    foreach(array_slice($errors,0,15) as $e) echo "<li>".htmlspecialchars($e)."</li>";
    echo "</ul>";
}
echo "<p><b>SUPPRIMEZ import.php et import_data.json!</b></p>";
?>
