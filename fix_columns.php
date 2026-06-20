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

// ── Corriger statuts bobines selon quantité réelle ───────────────
// en_cours = partiellement consommée (films_restants < qte_initiale)
// en_stock = bobine pleine (films_restants >= qte_initiale)
// epuisee  = vide (films_restants = 0)
$m = '';
safe_exec($db,
    "UPDATE op_bobines
     SET statut = CASE
         WHEN films_restants = 0 THEN 'epuisee'
         WHEN films_restants < COALESCE(qte_initiale, 500) THEN 'en_cours'
         ELSE 'en_stock'
     END
     WHERE site_id IS NOT NULL
       AND statut NOT IN ('retiree','perdue')", $m);
$results[] = "op_bobines statuts recalculés (en_stock/en_cours/epuisee) : $m";

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

// ── op_types_bobines — créer si absente ───────────────────────
$m = '';
safe_exec($db, "CREATE TABLE IF NOT EXISTS `op_types_bobines` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `libelle` varchar(150) NOT NULL,
  `serie` char(4) NOT NULL,
  `actif` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $m);
$results[] = "op_types_bobines (CREATE IF NOT EXISTS) : $m";

// ── Dédupliquer op_types_bobines (garder le plus petit id par code) ──
$m = '';
safe_exec($db, "DELETE t1 FROM op_types_bobines t1
    JOIN op_types_bobines t2 ON t1.code = t2.code AND t1.id > t2.id", $m);
$results[] = "op_types_bobines dédupliqué : $m";

// ── Ajouter UNIQUE KEY sur code ────────────────────────────────
$m = '';
safe_exec($db, "ALTER TABLE op_types_bobines ADD UNIQUE KEY uq_type_code (code)", $m);
$results[] = "op_types_bobines UNIQUE(code) : $m";

$types_data = [
    ['A001','Format Auto, version Privee','A'],
    ['A002','Format Auto, version Transport Publique','A'],
    ['A003','Format Auto, version Institution Internationale','A'],
    ['A004','Format Auto, version Diplomatique','A'],
    ['A005','Format Auto, version Gouvernementale','A'],
    ['A006','Format Auto, version Temporaire','A'],
    ['B001','Format Carre, version Privee','B'],
    ['B002','Format Carre, version Transport Publique','B'],
    ['B003','Format Carre, version Institution Internationale','B'],
    ['B004','Format Carre, version Diplomatique','B'],
    ['B005','Format Carre, version Gouvernementale','B'],
    ['B006','Format Carre, version Temporaire','B'],
    ['C001','Format Moto, version Privee','C'],
    ['C002','Format Moto, version Transport Publique','C'],
    ['C003','Format Moto, version Institution Internationale','C'],
    ['C004','Format Moto, version Diplomatique','C'],
    ['C005','Format Moto, version Gouvernementale','C'],
    ['C006','Format Moto, version Temporaire','C'],
    ['D001','Format MotoII, version Privee','D'],
    ['D002','Format MotoII, version Transport Publique','D'],
    ['D003','Format MotoII, version Institution Internationale','D'],
    ['D004','Format MotoII, version Diplomatique','D'],
    ['D005','Format MotoII, version Gouvernementale','D'],
    ['D006','Format MotoII, version Temporaire','D'],
    ['WSL001','Version Pare-brise - Privee','WSL'],
    ['WSL002','Version Pare-brise - Transport Publique','WSL'],
    ['TL001','Version Reservoir - Privee','TL'],
    ['TL002','Version Reservoir - Transport Publique','TL'],
];
$nb_types = 0;
foreach ($types_data as [$code,$libelle,$serie]) {
    $stmt = $db->prepare("INSERT IGNORE INTO op_types_bobines (code,libelle,serie,actif) VALUES (?,?,?,1)");
    $stmt->execute([$code,$libelle,$serie]);
    $nb_types += $stmt->rowCount();
}
$results[] = "op_types_bobines — $nb_types types insérés (INSERT IGNORE)";

// ── op_stock_rivets — ajouter type_rivet ─────────────────────
$m = '';
safe_exec($db, "ALTER TABLE `op_stock_rivets` ADD COLUMN `type_rivet` varchar(20) NOT NULL DEFAULT 'gonflable'", $m);
$results[] = "op_stock_rivets.type_rivet : $m";

// Ajouter UNIQUE KEY (site_id, type_rivet) pour ON DUPLICATE KEY UPDATE
$m = '';
safe_exec($db, "ALTER TABLE `op_stock_rivets` ADD UNIQUE KEY uq_site_type (site_id, type_rivet)", $m);
$results[] = "op_stock_rivets UNIQUE(site_id,type_rivet) : $m";

// Créer une ligne 'eclate' pour chaque site qui a déjà une ligne 'gonflable'
$m = '';
safe_exec($db,
    "INSERT IGNORE INTO op_stock_rivets (site_id, quantite, type_rivet)
     SELECT site_id, 0, 'eclate' FROM op_stock_rivets WHERE type_rivet='gonflable'", $m);
$results[] = "op_stock_rivets lignes éclatés créées : $m";

// ── demandes_bobines — créer si absente ───────────────────────
$m = '';
safe_exec($db, "CREATE TABLE IF NOT EXISTS `demandes_bobines` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `bobine_id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `demande_par` int(10) UNSIGNED NOT NULL,
  `motif` text NOT NULL,
  `statut` enum('en_attente','approuvee','refusee') NOT NULL DEFAULT 'en_attente',
  `traite_par` int(10) UNSIGNED DEFAULT NULL,
  `traite_at` datetime DEFAULT NULL,
  `motif_reponse` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bobine` (`bobine_id`),
  KEY `idx_site` (`site_id`),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci", $m);
$results[] = "demandes_bobines (CREATE IF NOT EXISTS) : $m";

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

echo "\n--- op_types_bobines : contenu ---\n";
try {
    $rows = $db->query("SELECT id,code,libelle,serie,actif FROM op_types_bobines ORDER BY serie,code")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "⚠️ TABLE VIDE — aucun type trouvé\n";
    } else {
        foreach ($rows as $r) echo "{$r['id']} | {$r['code']} | {$r['libelle']} | série={$r['serie']} | actif={$r['actif']}\n";
    }
} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}

echo "\n--- op_bobines : répartition des statuts ---\n";
try {
    foreach ($db->query("SELECT statut, COUNT(*) AS n FROM op_bobines GROUP BY statut ORDER BY statut") as $r)
        echo "{$r['statut']} : {$r['n']}\n";
} catch (PDOException $e) {
    echo "❌ " . $e->getMessage() . "\n";
}

echo "\n--- op_bobines : en_stock vs en_cours vs pleine ---\n";
try {
    $r = $db->query("SELECT
        COUNT(*) AS total,
        SUM(films_restants = 0) AS vides,
        SUM(films_restants > 0 AND films_restants < COALESCE(qte_initiale,500)) AS partielles,
        SUM(films_restants >= COALESCE(qte_initiale,500)) AS pleines
    FROM op_bobines WHERE site_id IS NOT NULL AND statut NOT IN ('retiree','perdue')")->fetch(PDO::FETCH_ASSOC);
    echo "Total avec site : {$r['total']} | Vides(épuisées): {$r['vides']} | Partielles(en_cours): {$r['partielles']} | Pleines(en_stock): {$r['pleines']}\n";
} catch (PDOException $e) {
    echo "❌ " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><b>SUPPRIMEZ ce fichier!</b></p>";
