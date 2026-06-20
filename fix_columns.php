<?php
$secret = $_GET['secret'] ?? '';
if ($secret !== 'emuci2026import') die('Accès refusé');
require_once __DIR__ . '/includes/db.php';
$db = get_db();

$results = [];

function safe_exec(PDO $db, string $sql, string &$msg): void {
    try {
        $db->exec($sql);
        $msg = "✅ OK";
    } catch (PDOException $e) {
        $code = $e->getCode();
        // 1060 = duplicate column name — ignoré
        if ($code === '42S21' || strpos($e->getMessage(),'1060') !== false) {
            $msg = "ℹ️ déjà OK";
        } else {
            $msg = "❌ " . $e->getMessage();
        }
    }
}

$db->exec("SET FOREIGN_KEY_CHECKS=0");

// ── import_session_id INT → VARCHAR(36) ───────────────────────
foreach (['import_optotrace', 'import_optoplate'] as $tbl) {
    $m = '';
    safe_exec($db, "ALTER TABLE `$tbl` MODIFY COLUMN `import_session_id` varchar(36) DEFAULT NULL", $m);
    $results[] = "$tbl.import_session_id → varchar(36) : $m";
}

// ── import_optotrace — colonnes manquantes ────────────────────
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
    $m = '';
    safe_exec($db, "ALTER TABLE `import_optotrace` ADD COLUMN `$col` $def", $m);
    $results[] = "import_optotrace.$col : $m";
}

// ── import_optoplate — colonnes manquantes ────────────────────
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
    $m = '';
    safe_exec($db, "ALTER TABLE `import_optoplate` ADD COLUMN `$col` $def", $m);
    $results[] = "import_optoplate.$col : $m";
}

// ── sites — nom_emuci ─────────────────────────────────────────
$m = '';
safe_exec($db, "ALTER TABLE `sites` ADD COLUMN `nom_emuci` varchar(150) DEFAULT NULL", $m);
$results[] = "sites.nom_emuci : $m";

// ── emuci_sites_inconnus — dédupliquer + UNIQUE sur nom_emuci ────────────────
// Garder uniquement la dernière occurrence par nom_emuci
$m = '';
safe_exec($db, "DELETE t1 FROM emuci_sites_inconnus t1
    INNER JOIN emuci_sites_inconnus t2
    WHERE t1.nom_emuci = t2.nom_emuci AND t1.id < t2.id", $m);
$results[] = "emuci_sites_inconnus dédupliqué : $m";

$m = '';
safe_exec($db, "ALTER TABLE emuci_sites_inconnus ADD UNIQUE KEY uq_nom_emuci (nom_emuci)", $m);
$results[] = "emuci_sites_inconnus UNIQUE(nom_emuci) : $m";

// ── emuci_sites_inconnus — renommer nom_site → nom_emuci + ajouter type_import ──
$m = '';
safe_exec($db, "ALTER TABLE `emuci_sites_inconnus` CHANGE COLUMN `nom_site` `nom_emuci` varchar(255) NOT NULL", $m);
$results[] = "emuci_sites_inconnus.nom_site → nom_emuci : $m";

$m = '';
safe_exec($db, "ALTER TABLE `emuci_sites_inconnus` ADD COLUMN `type_import` varchar(20) DEFAULT NULL", $m);
$results[] = "emuci_sites_inconnus.type_import : $m";

// ── Mettre à jour site_id dans op_bobines depuis import_optotrace ────────────
$m = '';
safe_exec($db,
    "UPDATE op_bobines b
     JOIN (
         SELECT ot.keyname, s.id AS site_id
         FROM import_optotrace ot
         JOIN sites s ON s.nom_emuci = ot.site_nom_emuci
         WHERE ot.site_nom_emuci IS NOT NULL AND s.nom_emuci IS NOT NULL
         GROUP BY ot.keyname, s.id
     ) mapping ON mapping.keyname = b.numero
     SET b.site_id = mapping.site_id
     WHERE b.site_id IS NULL", $m);
$results[] = "op_bobines.site_id mis à jour depuis import_optotrace : $m";

$db->exec("SET FOREIGN_KEY_CHECKS=1");

echo "<pre style='font-family:monospace;font-size:14px;padding:20px'>";
foreach ($results as $r) echo $r . "\n";

// Diagnostic : lister les colonnes réelles des tables concernées
echo "\n--- Colonnes réelles dans 'sites' ---\n";
foreach ($db->query("SHOW COLUMNS FROM `sites`") as $c) echo $c['Field'] . "\n";

echo "\n--- Colonnes réelles dans 'import_optotrace' ---\n";
foreach ($db->query("SHOW COLUMNS FROM `import_optotrace`") as $c) echo $c['Field'] . "\n";

echo "\n--- Colonnes réelles dans 'import_optoplate' ---\n";
foreach ($db->query("SHOW COLUMNS FROM `import_optoplate`") as $c) echo $c['Field'] . "\n";

echo "\n--- Colonnes réelles dans 'emuci_sites_inconnus' ---\n";
try {
    foreach ($db->query("SHOW COLUMNS FROM `emuci_sites_inconnus`") as $c) echo $c['Field'] . "\n";
} catch (PDOException $e) {
    echo "❌ Table introuvable : " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><b>SUPPRIMEZ ce fichier!</b></p>";
