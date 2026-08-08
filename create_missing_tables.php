<?php
$secret = $_GET['secret'] ?? '';
if ($secret !== 'emuci2026import') die('Accès refusé');
require_once __DIR__ . '/includes/db.php';
$db = get_db();
$db->exec("SET FOREIGN_KEY_CHECKS=0");
$success = 0; $errors = [];

$tables = [
"CREATE TABLE IF NOT EXISTS commandes_bobines_lignes (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  commande_id int(10) UNSIGNED NOT NULL,
  bobine_id int(10) UNSIGNED DEFAULT NULL,
  numero_bobine varchar(50) DEFAULT NULL,
  statut enum('en_attente','attribue','livre','refuse') NOT NULL DEFAULT 'en_attente',
  created_at datetime DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS articles (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  code varchar(50) DEFAULT NULL,
  libelle varchar(255) NOT NULL,
  type_article varchar(50) DEFAULT 'consommable',
  unite varchar(30) DEFAULT 'unité',
  description text DEFAULT NULL,
  seuil_alerte int(11) DEFAULT 0,
  prix_unitaire int(11) DEFAULT 0,
  stock_global int(11) DEFAULT 0,
  categorie varchar(100) DEFAULT NULL,
  actif tinyint(1) DEFAULT 1,
  created_at datetime DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS stock_site (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  article_id int(10) UNSIGNED NOT NULL,
  site_id int(10) UNSIGNED NOT NULL,
  quantite int(11) NOT NULL DEFAULT 0,
  updated_at datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  UNIQUE KEY article_site (article_id,site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS mouvements_stock (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  article_id int(10) UNSIGNED NOT NULL,
  site_id int(10) UNSIGNED DEFAULT NULL,
  type_mouvement enum('entree','sortie','ajustement','transfert') NOT NULL,
  quantite int(11) NOT NULL,
  solde_apres int(11) DEFAULT 0,
  reference varchar(100) DEFAULT NULL,
  notes text DEFAULT NULL,
  created_by int(10) UNSIGNED DEFAULT NULL,
  created_at datetime DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS receptions_fournisseur (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  numero_reception varchar(50) NOT NULL,
  fournisseur varchar(255) DEFAULT NULL,
  date_reception date NOT NULL,
  statut enum('en_attente','recu_partiel','recu_complet','annule') NOT NULL DEFAULT 'en_attente',
  notes text DEFAULT NULL,
  created_by int(10) UNSIGNED DEFAULT NULL,
  created_at datetime DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS reception_lignes (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  reception_id int(10) UNSIGNED NOT NULL,
  article_id int(10) UNSIGNED DEFAULT NULL,
  libelle varchar(255) NOT NULL,
  quantite_attendue int(11) DEFAULT 0,
  quantite_recue int(11) DEFAULT 0,
  unite varchar(30) DEFAULT 'unité',
  prix_unitaire int(11) DEFAULT 0,
  created_at datetime DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS distributions_site (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  numero_distribution varchar(50) NOT NULL,
  commande_id int(10) UNSIGNED DEFAULT NULL,
  site_id int(10) UNSIGNED NOT NULL,
  date_distribution date NOT NULL,
  statut enum('en_preparation','expedie','recu','annule') NOT NULL DEFAULT 'en_preparation',
  notes text DEFAULT NULL,
  fichier_bl varchar(255) DEFAULT NULL,
  created_by int(10) UNSIGNED DEFAULT NULL,
  expedie_at datetime DEFAULT NULL,
  created_at datetime DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS distribution_lignes (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  distribution_id int(10) UNSIGNED NOT NULL,
  article_id int(10) UNSIGNED DEFAULT NULL,
  commande_ligne_id int(10) UNSIGNED DEFAULT NULL,
  libelle varchar(255) NOT NULL,
  quantite_envoyee int(11) DEFAULT 0,
  quantite_recue int(11) DEFAULT NULL,
  unite varchar(30) DEFAULT 'unité',
  statut enum('en_cours','recu','ecart') NOT NULL DEFAULT 'en_cours',
  motif_ecart text DEFAULT NULL,
  created_at datetime DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS demandes_correction_saisie (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  point_id int(10) UNSIGNED NOT NULL,
  demande_par int(10) UNSIGNED NOT NULL,
  motif text NOT NULL,
  statut enum('en_attente','approuvee','refusee') NOT NULL DEFAULT 'en_attente',
  traite_par int(10) UNSIGNED DEFAULT NULL,
  created_at datetime DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS emuci_sites_inconnus (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  nom_site varchar(255) NOT NULL,
  session_id int(10) UNSIGNED DEFAULT NULL,
  created_at datetime DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS op_types_bobines (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  code varchar(20) NOT NULL,
  libelle varchar(100) NOT NULL,
  serie char(1) DEFAULT NULL,
  films_total int(11) DEFAULT 500,
  actif tinyint(1) DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS stock_fin_mois (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  article_id int(10) UNSIGNED NOT NULL,
  site_id int(10) UNSIGNED DEFAULT NULL,
  annee int(11) NOT NULL,
  mois int(11) NOT NULL,
  quantite int(11) DEFAULT 0,
  created_at datetime DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

"CREATE TABLE IF NOT EXISTS corrections_bobines (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  point_id int(10) UNSIGNED NOT NULL,
  bobine_id int(10) UNSIGNED NOT NULL,
  site_id int(10) UNSIGNED NOT NULL,
  date_point date NOT NULL,
  films_original int(11) NOT NULL COMMENT 'Valeur saisie par le coordinateur',
  films_proposes int(11) NOT NULL COMMENT 'Valeur proposée par le GSB',
  motif_gsb text NOT NULL,
  gsb_id int(10) UNSIGNED NOT NULL,
  statut enum('en_attente','approuvee','contreproposee','refusee') NOT NULL DEFAULT 'en_attente',
  coord_id int(10) UNSIGNED DEFAULT NULL,
  reponse_coord text DEFAULT NULL,
  films_final int(11) DEFAULT NULL COMMENT 'Valeur définitivement appliquée',
  traite_at datetime DEFAULT NULL,
  created_at datetime DEFAULT current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Demandes de correction bobines GSB → Coordinateur'"
];

foreach ($tables as $sql) {
    try {
        $db->exec($sql);
        $success++;
    } catch(PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'already exists') !== false) {
            $success++;
        } else {
            $errors[] = $msg;
        }
    }
}

$db->exec("SET FOREIGN_KEY_CHECKS=1");
echo "<h2>✅ $success tables créées</h2>";
if ($errors) {
    echo "<ul>";
    foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>";
    echo "</ul>";
}
echo "<p><b>SUPPRIMEZ ce fichier!</b></p>";
?>
