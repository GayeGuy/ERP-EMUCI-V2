<?php
$secret = $_GET['secret'] ?? '';
if ($secret !== 'emuci2026import') die('Accès refusé');
require_once __DIR__ . '/includes/db.php';
$db = get_db();

$sql = "CREATE TABLE IF NOT EXISTS op_bobines (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  numero varchar(50) NOT NULL,
  type_code varchar(10) NOT NULL,
  serie char(1) NOT NULL,
  type_vehicule_id int(10) UNSIGNED DEFAULT NULL,
  films_total int(10) UNSIGNED NOT NULL DEFAULT 500,
  films_utilises int(10) UNSIGNED NOT NULL DEFAULT 0,
  films_endommages int(10) UNSIGNED NOT NULL DEFAULT 0,
  films_restants int(10) UNSIGNED NOT NULL DEFAULT 500,
  site_id int(10) UNSIGNED DEFAULT NULL,
  statut enum('en_stock','en_cours','retiree','epuisee','perdue') NOT NULL DEFAULT 'en_stock',
  date_ouverture date DEFAULT NULL,
  created_by int(10) UNSIGNED DEFAULT NULL,
  created_at datetime DEFAULT current_timestamp(),
  qte_initiale int(10) UNSIGNED NOT NULL DEFAULT 500,
  stock_systeme int(10) UNSIGNED NOT NULL DEFAULT 500,
  stock_physique int(10) UNSIGNED DEFAULT NULL,
  dernier_inventaire_id int(10) UNSIGNED DEFAULT NULL,
  date_creation date DEFAULT (CURRENT_DATE),
  format varchar(50) DEFAULT NULL,
  notes_perte text DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db->exec($sql);
    echo "✅ Table op_bobines créée !";
} catch(PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage();
}
echo "<br><b>SUPPRIMEZ ce fichier!</b>";
?>
