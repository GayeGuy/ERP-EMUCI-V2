<?php
$secret = $_GET['secret'] ?? '';
if ($secret !== 'emuci2026import') die('Accès refusé');
require_once __DIR__ . '/includes/db.php';
$db = get_db();
$db->exec("SET FOREIGN_KEY_CHECKS=0");

$results = [];

// Helper : ajouter une colonne si elle n'existe pas
function add_col_if_missing(PDO $db, string $table, string $col, string $def): void {
    try {
        $db->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
    } catch (PDOException $e) {
        // 1060 = Duplicate column name — colonne déjà présente, on ignore
        if (strpos($e->getMessage(), '1060') === false) throw $e;
    }
}

// ============================================================
// Corriger import_session_id INT → VARCHAR(36) sur les deux tables
// ============================================================
foreach (['import_optotrace', 'import_optoplate'] as $tbl) {
    try {
        $db->exec("ALTER TABLE `$tbl` MODIFY COLUMN `import_session_id` varchar(36) DEFAULT NULL");
        $results[] = "✅ $tbl.import_session_id converti en VARCHAR(36)";
    } catch (PDOException $e) {
        $results[] = "ℹ️ $tbl.import_session_id : " . $e->getMessage();
    }
}

// ============================================================
// import_optotrace — colonnes manquantes
// ============================================================
$cols_optotrace = [
    'batch'       => "varchar(100) DEFAULT NULL",
    'project'     => "varchar(100) DEFAULT NULL",
    'article'     => "varchar(100) DEFAULT NULL",
    'box'         => "varchar(50)  DEFAULT NULL",
    'type_trace'  => "varchar(50)  DEFAULT NULL",
    'first_use'   => "datetime     DEFAULT NULL",
    'last_use'    => "datetime     DEFAULT NULL",
    'sended_on'   => "datetime     DEFAULT NULL",
    'received_on' => "datetime     DEFAULT NULL",
    'canceled_on' => "datetime     DEFAULT NULL",
    'importe_par' => "int(10) UNSIGNED DEFAULT NULL",
];
foreach ($cols_optotrace as $col => $def) {
    add_col_if_missing($db, 'import_optotrace', $col, $def);
}
$results[] = "✅ import_optotrace — colonnes vérifiées/ajoutées";

// ============================================================
// import_optoplate — colonnes manquantes
// ============================================================
$cols_optoplate = [
    'date_installation' => "datetime     DEFAULT NULL",
    'numero_dossier'    => "varchar(50)  DEFAULT NULL",
    'vin'               => "varchar(20)  DEFAULT NULL",
    'type_plaque'       => "varchar(60)  DEFAULT NULL",
    'position'          => "varchar(10)  DEFAULT NULL",
    'num_consommable'   => "varchar(30)  DEFAULT NULL",
    'site_id_emuci'     => "varchar(20)  DEFAULT NULL",
    'importe_par'       => "int(10) UNSIGNED DEFAULT NULL",
];
foreach ($cols_optoplate as $col => $def) {
    add_col_if_missing($db, 'import_optoplate', $col, $def);
}
$results[] = "✅ import_optoplate — colonnes vérifiées/ajoutées";

$db->exec("SET FOREIGN_KEY_CHECKS=1");

foreach ($results as $r) echo "<p>$r</p>";
echo "<p><b>SUPPRIMEZ ce fichier!</b></p>";
?>
