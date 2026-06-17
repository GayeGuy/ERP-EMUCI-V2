<?php
// Script d'import de base de données - À SUPPRIMER APRÈS USAGE
// Accès protégé
$secret = $_GET['secret'] ?? '';
if ($secret !== 'emuci2026import') {
    die('Accès refusé');
}

require_once __DIR__ . '/includes/db.php';

$db = get_db();

// Créer la base railway si nécessaire
$sql_commands = <<<'SQLEOF'
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : jeu. 28 mai 2026 à 12:37
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Base de données : `stockapp`
--

-- --------------------------------------------------------

--
-- Structure de la table `affectations_equipements`
--

CREATE TABLE `affectations_equipements` (
  `id` int(10) UNSIGNED NOT NULL,
  `equipement_id` int(10) UNSIGNED NOT NULL,
  `site_dest_id` int(10) UNSIGNED DEFAULT NULL,
  `user_dest_id` int(10) UNSIGNED DEFAULT NULL,
  `statut` enum('en_attente','valide_n1','recu','refuse') NOT NULL DEFAULT 'en_attente',
  `pdf_path` varchar(255) DEFAULT NULL COMMENT 'PDF fiche affectation',
  `pdf_signe_n1` varchar(255) DEFAULT NULL COMMENT 'PDF signé N+1',
  `pdf_signe_site` varchar(255) DEFAULT NULL COMMENT 'PDF signé chef de site',
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `valide_n1_by` int(10) UNSIGNED DEFAULT NULL,
  `valide_n1_at` datetime DEFAULT NULL,
  `recu_by` int(10) UNSIGNED DEFAULT NULL,
  `recu_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` enum('CREATE','READ','UPDATE','DELETE','LOGIN','LOGOUT','EXPORT','TRANSFER') NOT NULL,
  `module` varchar(60) NOT NULL,
  `entite_id` int(10) UNSIGNED DEFAULT NULL,
  `description` text NOT NULL,
  `ancienne_valeur` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ancienne_valeur`)),
  `nouvelle_valeur` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`nouvelle_valeur`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `module`, `entite_id`, `description`, `ancienne_valeur`, `nouvelle_valeur`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 11:51:27'),
(2, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 11:54:03'),
(3, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 11:55:11'),
(4, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 12:02:40'),
(5, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 12:03:12'),
(6, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 12:13:52'),
(7, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 12:18:14'),
(8, 1, 'UPDATE', 'auth', 1, 'Demande de réinitialisation de mot de passe', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 12:35:18'),
(9, 1, 'UPDATE', 'auth', 1, 'Demande de réinitialisation de mot de passe', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 12:35:27'),
(10, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 12:36:54'),
(11, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 12:37:21'),
(12, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 12:37:55'),
(13, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 13:26:01'),
(14, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 13:26:08'),
(15, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 13:40:30'),
(16, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : kri@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 13:53:14'),
(17, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockap.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 14:00:02'),
(18, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stock.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-04-01 14:10:01'),
(19, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 15:16:16'),
(20, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 15:18:45'),
(21, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 15:34:25'),
(22, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 05:26:14'),
(23, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 05:35:03'),
(24, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 05:37:36'),
(25, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 05:38:51'),
(26, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 05:48:34'),
(27, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 05:49:47'),
(28, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : admin@stockapp.local', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 05:51:26'),
(29, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 06:00:44'),
(30, 3, 'CREATE', 'sites', 1, 'Création site ABJ-01 — GU ABIDJAN', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 06:06:19'),
(31, 3, 'CREATE', 'sites', 2, 'Création site ABJ-02 — STAR AUTO', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 06:06:49'),
(32, 3, 'CREATE', 'sites', 3, 'Création site ABJ-03 — CFAO', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 06:07:37'),
(33, 3, 'CREATE', 'sites', 4, 'Création site KGO-01 — GU KORHOOGO', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 06:08:13'),
(34, 3, 'CREATE', 'sites', 5, 'Création site BKE-01 — GU BOUAKE', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 06:08:34'),
(35, 3, 'CREATE', 'nomenclatures', 1, 'Création nomenclature CLV', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 06:11:05'),
(36, 3, 'CREATE', 'nomenclatures', 2, 'Création nomenclature IMP', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 06:11:53'),
(37, 3, 'CREATE', 'equipements', 1, 'Création équipement 0', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 06:13:31'),
(38, 3, 'CREATE', 'consommables', 1, 'Création consommable CAF', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 06:17:16'),
(39, 3, 'TRANSFER', 'affectations', 1, 'Affectation 0 → site:1 user:', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 09:08:54'),
(40, 3, 'CREATE', 'nomenclatures', 3, 'Création nomenclature SR', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 09:16:25'),
(41, 3, 'CREATE', 'nomenclatures', 4, 'Création nomenclature UNT', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 09:17:00'),
(42, 3, 'CREATE', 'nomenclatures', 5, 'Création nomenclature CLD', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 09:17:31'),
(43, 3, 'CREATE', 'nomenclatures', 6, 'Création nomenclature ECR', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 09:18:01'),
(44, 3, 'CREATE', 'nomenclatures', 7, 'Création nomenclature OND', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 09:18:21'),
(45, 3, 'CREATE', 'nomenclatures', 8, 'Création nomenclature LPT', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 09:19:11'),
(46, 3, 'CREATE', 'nomenclatures', 9, 'Création nomenclature PRS', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 09:19:32'),
(47, 3, 'CREATE', 'consommables', 2, 'Création consommable SCR', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 09:22:25'),
(48, 3, 'CREATE', 'equipements', 6, 'Création équipement SR-EMUCI-0001', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 10:56:16'),
(49, 3, 'CREATE', 'equipements', 7, 'Création équipement IMP-EMUCI-0002', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 10:57:27'),
(50, 3, 'CREATE', 'equipements', 8, 'Création équipement CLV-EMUCI-0001', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 10:58:28'),
(51, 3, 'CREATE', 'equipements', 9, 'Création équipement CLD-EMUCI-0001', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 10:59:35'),
(52, 3, 'CREATE', 'equipements', 10, 'Création équipement LPT-EMUCI-0001', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 11:20:32'),
(53, 3, 'UPDATE', 'equipements', 10, 'Modification LPT-EMUCI-0001', '{\"id\":10,\"numero_serie_interne\":\"LPT-EMUCI-0001\",\"numero_serie_origine\":\"HP25687\",\"numero_chrono\":6,\"nomenclature_id\":8,\"site_id\":null,\"utilisateur_id\":null,\"etat\":\"neuf\",\"date_acquisition\":\"2026-04-02\",\"date_mise_en_service\":null,\"date_fin_cycle\":null,\"marque\":\"HP\",\"modele\":\"\",\"observations\":\"\",\"actif\":1,\"created_by\":3,\"created_at\":\"2026-04-02 11:20:32\",\"updated_at\":\"2026-04-02 11:20:32\"}', '{\"numero_serie_origine\":\"HP25687\",\"marque\":\"HP\",\"modele\":\"\",\"etat\":\"neuf\",\"site_id\":null,\"utilisateur_id\":null,\"date_acquisition\":\"2026-04-02\",\"date_mise_en_service\":null,\"date_fin_cycle\":null,\"observations\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 11:20:59'),
(54, 3, 'CREATE', 'equipements', 11, 'Création équipement CLV-EMUCI-0002', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 11:21:10'),
(55, 3, 'CREATE', 'equipements', 12, 'Création équipement CLD-EMUCI-0002', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 11:21:29'),
(56, 3, 'CREATE', 'equipements', 13, 'Création équipement ECR-EMUCI-0001', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 11:21:50'),
(57, 3, 'CREATE', 'equipements', 14, 'Création équipement IMP-EMUCI-0003', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 11:22:02'),
(58, 3, 'CREATE', 'equipements', 15, 'Création équipement OND-EMUCI-0001', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 11:22:15'),
(59, 3, 'CREATE', 'equipements', 16, 'Création équipement PRS-EMUCI-0001', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 11:22:31'),
(60, 3, 'CREATE', 'equipements', 17, 'Création équipement SR-EMUCI-0002', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 11:22:42'),
(61, 3, 'UPDATE', 'sites', NULL, 'Configuration type site pose mise à jour', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 11:24:04'),
(62, 3, 'UPDATE', 'sites', NULL, 'Configuration type site saisie mise à jour', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 11:25:09'),
(63, 3, 'CREATE', 'users', 4, 'Création de l\'utilisateur BERTRAND KOUASSI (bertrand@gmail.com)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 11:27:44'),
(64, 3, 'LOGOUT', 'auth', 3, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 11:29:25'),
(65, 4, 'LOGIN', 'auth', 4, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 11:29:31'),
(66, 4, 'LOGOUT', 'auth', 4, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 12:04:12'),
(67, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 12:04:27'),
(68, 3, 'CREATE', 'consommables', 2, 'Livraison 1 boite(s) de Sucre → site:5', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 14:18:16'),
(69, 3, 'CREATE', 'consommables', 3, 'Création consommable RVT', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 14:19:54'),
(70, 3, 'UPDATE', 'consommables', 2, 'Ajustement stock SCR site:3 : 0 → 2 (un paquet déjà présent n\'a pas été comptabilisé)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 14:21:26'),
(71, 3, 'CREATE', 'equipements', 18, 'Création équipement ECR-EMUCI-0002', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 15:35:16'),
(72, 3, 'DELETE', 'equipements', 13, 'Réforme ECR-EMUCI-0001', '{\"id\":13,\"numero_serie_interne\":\"ECR-EMUCI-0001\",\"numero_serie_origine\":\"EC25642\",\"numero_chrono\":9,\"nomenclature_id\":6,\"site_id\":null,\"utilisateur_id\":null,\"etat\":\"neuf\",\"date_acquisition\":null,\"date_mise_en_service\":null,\"date_fin_cycle\":null,\"marque\":\"\",\"modele\":\"\",\"observations\":\"\",\"actif\":1,\"created_by\":3,\"created_at\":\"2026-04-02 11:21:50\",\"updated_at\":\"2026-04-02 11:21:50\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 15:35:26'),
(73, 4, 'LOGIN', 'auth', 4, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:11:25'),
(74, 4, 'LOGOUT', 'auth', 4, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:12:03'),
(75, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:12:24'),
(76, 3, 'CREATE', 'sites', 6, 'Création site ABJ-04 — SITE DE TEST', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:18:17'),
(77, 3, 'CREATE', 'sites', 7, 'Création site ABJ-05 — TEST AKOUEDO', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:22:59'),
(78, 3, 'DELETE', 'equipements', 16, 'Réforme PRS-EMUCI-0001', '{\"id\":16,\"numero_serie_interne\":\"PRS-EMUCI-0001\",\"numero_serie_origine\":\"PERS25005821\",\"numero_chrono\":12,\"nomenclature_id\":9,\"site_id\":null,\"utilisateur_id\":null,\"etat\":\"neuf\",\"date_acquisition\":null,\"date_mise_en_service\":null,\"date_fin_cycle\":null,\"marque\":\"\",\"modele\":\"\",\"observations\":\"\",\"actif\":1,\"created_by\":3,\"created_at\":\"2026-04-02 11:22:31\",\"updated_at\":\"2026-04-02 11:22:31\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:24:15'),
(79, 3, 'DELETE', 'equipements', 17, 'Réforme SR-EMUCI-0002', '{\"id\":17,\"numero_serie_interne\":\"SR-EMUCI-0002\",\"numero_serie_origine\":\"SR55225647\",\"numero_chrono\":13,\"nomenclature_id\":3,\"site_id\":null,\"utilisateur_id\":null,\"etat\":\"neuf\",\"date_acquisition\":null,\"date_mise_en_service\":null,\"date_fin_cycle\":null,\"marque\":\"\",\"modele\":\"\",\"observations\":\"\",\"actif\":1,\"created_by\":3,\"created_at\":\"2026-04-02 11:22:42\",\"updated_at\":\"2026-04-02 11:22:42\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:28:06'),
(80, 3, 'CREATE', 'consommables', 4, 'Création consommable THE', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:29:52'),
(81, 3, 'CREATE', 'consommables', 5, 'Création consommable PH', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:37:52'),
(82, 3, 'CREATE', 'consommables', 5, 'Livraison 2 paquet(s) de Papier toilette → site:3', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:38:56'),
(83, 3, 'CREATE', 'sites', 8, 'Création site ENT — ENTREPOT EMUCI', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:45:47'),
(84, 3, 'CREATE', 'sites', 9, 'Création site ADM — ADMINISTRATION (Siége)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:46:43'),
(85, 3, 'CREATE', 'equipements', 19, 'Création équipement PRS-EMUCI-0002', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:48:28'),
(86, 3, 'CREATE', 'consommables', 6, 'Création consommable LT', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:56:14'),
(87, 3, 'CREATE', 'consommables', 6, 'Réception 20 boite(s) de Lotus (fournisseur: SUP BON PRIX ZONE4)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:57:05'),
(88, 3, 'CREATE', 'consommables', 6, 'Distribution 5 boite(s) de Lotus → site:3', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 04:57:38'),
(89, 3, 'CREATE', 'consommables', 2, 'Réception 10 boite(s) de Sucre (fournisseur: SUP AUCHAN)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:00:06'),
(90, 3, 'CREATE', 'consommables', 4, 'Réception 5 boite(s) de LIPTON (fournisseur: SUP BON PRIX)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:00:46'),
(91, 3, 'CREATE', 'consommables', 3, 'Réception 1 paquet(s) de rivets (fournisseur: ENTREPRISE GARDE)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:01:29'),
(92, 3, 'CREATE', 'consommables', 1, 'Réception 2 boite(s) de Café Moulu (fournisseur: SUP BON PRIX)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:01:57'),
(93, 3, 'CREATE', 'consommables', 5, 'Distribution 1 paquet(s) de Papier toilette → site:7', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:02:41'),
(94, 3, 'UPDATE', 'sites', 1, 'Modification site ABJ-01', '{\"id\":1,\"code\":\"ABJ-01\",\"nom\":\"GU ABIDJAN\",\"type\":\"pose\",\"option_caisse\":0,\"adresse\":\"Guichet unique abidjan vridi\",\"ville\":\"ABIDJAN\",\"pays\":\"Côte d\'Ivoire\",\"responsable_id\":null,\"actif\":1,\"created_at\":\"2026-04-02 06:06:19\",\"updated_at\":\"2026-04-02 06:06:19\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:04:00'),
(95, 3, 'UPDATE', 'sites', NULL, 'Configuration type site mixte mise à jour', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:05:27'),
(96, 3, 'CREATE', 'equipements', 20, 'Création équipement ECR-EMUCI-0003', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:06:51'),
(97, 3, 'UPDATE', 'equipements', 20, 'Modification ECR-EMUCI-0003', '{\"id\":20,\"numero_serie_interne\":\"ECR-EMUCI-0003\",\"numero_serie_origine\":\"ECR25005821\",\"numero_chrono\":16,\"nomenclature_id\":6,\"site_id\":null,\"utilisateur_id\":null,\"etat\":\"neuf\",\"date_acquisition\":null,\"date_mise_en_service\":null,\"date_fin_cycle\":null,\"marque\":\"HP\",\"modele\":\"\",\"observations\":\"\",\"actif\":1,\"created_by\":3,\"created_at\":\"2026-04-03 05:06:51\",\"updated_at\":\"2026-04-03 05:06:51\"}', '{\"numero_serie_origine\":\"ECR25005821\",\"marque\":\"HP\",\"modele\":\"\",\"etat\":\"neuf\",\"site_id\":8,\"utilisateur_id\":null,\"date_acquisition\":null,\"date_mise_en_service\":null,\"date_fin_cycle\":null,\"observations\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:07:05'),
(98, 3, 'TRANSFER', 'affectations', 20, 'Affectation ECR-EMUCI-0003 → site:7 user:', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:09:26'),
(99, 3, 'TRANSFER', 'affectations', 7, 'Affectation IMP-EMUCI-0002 → site:9 user:', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:11:00'),
(100, 3, 'CREATE', 'equipements', 21, 'Création équipement CLD-EMUCI-0003', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:11:46'),
(101, 3, 'TRANSFER', 'affectations', 21, 'Affectation CLD-EMUCI-0003 → site:4 user:', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:12:24'),
(102, 3, 'TRANSFER', 'affectations', 20, 'Affectation ECR-EMUCI-0003 → site:3 user:', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-03 05:12:49'),
(103, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 18:28:32'),
(104, 3, 'LOGOUT', 'auth', 3, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 18:49:58'),
(105, 4, 'LOGIN', 'auth', 4, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 18:50:01'),
(106, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : donruthaxelle01@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-14 14:43:03'),
(107, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : donruthaxelle01@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-14 14:45:40'),
(108, 4, 'LOGIN', 'auth', 4, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-14 14:49:06'),
(109, 4, 'LOGOUT', 'auth', 4, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-14 14:49:25'),
(110, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-14 14:49:37'),
(111, 3, 'CREATE', 'operations', 1, 'Création bobine HP000000HG45 (A001)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-14 15:09:15'),
(112, 3, 'CREATE', 'operations', 2, 'Création bobine HP000000HG23 (B001)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-14 15:25:42'),
(113, 3, 'CREATE', 'operations', 3, 'Création bobine HP000000HG25 (C004)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-14 15:26:05'),
(114, 3, 'CREATE', 'operations', 4, 'Création bobine HP000000HG28 (C006)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-14 15:26:50'),
(115, 3, 'CREATE', 'operations', 1, 'Approvisionnement 500 rivets → GU ABIDJAN (Bl-452)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-14 15:27:31'),
(116, 3, 'CREATE', 'operations', 3, 'Approvisionnement 500 rivets → CFAO (Bl-451)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-14 15:27:53'),
(117, 3, 'CREATE', 'operations', 1, 'Point journalier final — Site:1 — 2026-04-14 — 144 engins', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-14 15:30:38'),
(118, 3, 'CREATE', 'operations', 1, 'Point journalier final — Site:1 — 2026-04-14 — 6 engins', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-14 15:33:30'),
(119, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 09:28:30'),
(120, 3, 'CREATE', 'equipements', 22, 'Création équipement SR-EMUCI-0003', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 09:30:04'),
(121, 3, 'TRANSFER', 'affectations', 22, 'Affectation SR-EMUCI-0003 → site:1 user:', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 09:31:37'),
(122, 3, 'CREATE', 'operations', 3, 'Approvisionnement 500 rivets → CFAO ()', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 09:59:26'),
(123, 3, 'CREATE', 'operations', 5, 'Approvisionnement 500 rivets → GU BOUAKE ()', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 09:59:34'),
(124, 3, 'CREATE', 'operations', 4, 'Approvisionnement 500 rivets → GU KORHOOGO ()', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 09:59:41'),
(125, 3, 'CREATE', 'operations', 2, 'Approvisionnement 1000 rivets → STAR AUTO ()', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 09:59:50'),
(126, 3, 'CREATE', 'operations', 2, 'Point journalier intermediaire — Site:2 — 2026-04-15 — 20 engins', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 10:00:50'),
(127, 3, 'CREATE', 'operations', 1, 'Approvisionnement 500 rivets → GU ABIDJAN ()', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 10:15:18'),
(128, 3, 'CREATE', 'operations', 3, 'Point journalier intermediaire — Site:1 — 2026-04-15 — 13 engins', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 10:15:58'),
(129, 3, 'UPDATE', 'operations', 1, 'Validation point journalier #1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 10:17:01'),
(130, 3, 'TRANSFER', 'affectations', 20, 'Affectation ECR-EMUCI-0003 → site:5 user:', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 11:43:02'),
(131, 3, 'CREATE', 'operations', 3, 'Point journalier intermediaire — Site:1 — 2026-04-15 — 2 engins', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 11:44:12'),
(132, 3, 'CREATE', 'operations', 4, 'Point journalier final — Site:1 — 2026-04-15 — 11 engins', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 11:46:21'),
(133, 3, 'CREATE', 'consommables', 1, 'Réception 10 boite(s) de Café Moulu (fournisseur: )', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 22:37:28'),
(134, 3, 'CREATE', 'consommables', 5, 'Réception 19.98 paquet(s) de Papier toilette (fournisseur: )', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 22:37:46'),
(135, 3, 'CREATE', 'consommables', 3, 'Réception 2000 paquet(s) de rivets (fournisseur: )', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 22:38:08'),
(136, 3, 'CREATE', 'consommables', 3, 'Distribution 5 paquet(s) de rivets → site:1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 22:38:28'),
(137, 3, 'CREATE', 'consommables', 1, 'Réception 1.99 boite(s) de Café Moulu (fournisseur: )', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 22:38:45'),
(138, 3, 'CREATE', 'consommables', 1, 'Distribution 4 boite(s) de Café Moulu → site:3', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-15 22:39:31'),
(139, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 06:15:58'),
(140, 3, 'CREATE', 'equipements', 23, 'Création équipement PRS-EMUCI-0003', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 07:55:21'),
(141, 3, 'TRANSFER', 'affectations', 9, 'Affectation CLD-EMUCI-0001 → site:2 user: (BL:bl_equip_9_20260419_075731_4b2615e9.pdf)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 07:57:31'),
(142, 3, 'UPDATE', 'affectations', 20, 'Retour stock ECR-EMUCI-0003', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 07:58:27'),
(143, 3, 'CREATE', 'users', 5, 'Création de l\'utilisateur test operation (testoperation@gmail.com)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:04:30'),
(144, 3, 'CREATE', 'users', 6, 'Création de l\'utilisateur test info (testinfo@gmail.com)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:05:15'),
(145, 3, 'UPDATE', 'users', 2, 'Désactivation utilisateur kri@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:05:33'),
(146, 3, 'CREATE', 'users', 7, 'Création de l\'utilisateur test cordo (testcordo@gmail.com)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:06:45'),
(147, 3, 'CREATE', 'users', 8, 'Création de l\'utilisateur test stock (teststock@gmail.com)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:07:28'),
(148, 6, 'LOGIN', 'auth', 6, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:08:26'),
(149, 3, 'UPDATE', 'users', 7, 'Modification permissions rôle ID:7', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:10:29'),
(150, 6, 'LOGOUT', 'auth', 6, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:15:15'),
(151, 6, 'LOGIN', 'auth', 6, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:16:22'),
(152, 6, 'CREATE', 'equipements', 24, 'Création équipement CLD-EMUCI-0004', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:17:35'),
(153, 6, 'LOGOUT', 'auth', 6, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:19:06'),
(154, 7, 'LOGIN', 'auth', 7, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:19:37'),
(155, 3, 'TRANSFER', 'affectations', 23, 'Affectation PRS-EMUCI-0003 → site:1 user:7 (BL:bl_equip_23_20260419_082711_e0c6a57b.pdf)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:27:11'),
(156, 7, 'UPDATE', 'receptions', 2, 'Réception validée avec fiche signée — site_id:1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:29:37'),
(157, 3, 'TRANSFER', 'affectations', 20, 'Affectation ECR-EMUCI-0003 → site:1 user:7 (BL:bl_equip_20_20260419_083021_6f88163d.pdf)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:30:21'),
(158, 7, 'UPDATE', 'receptions', 3, 'Litige signalé : le format nest pas correcte', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:30:44'),
(159, 7, 'LOGOUT', 'auth', 7, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:32:08'),
(160, 8, 'LOGIN', 'auth', 8, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:32:17'),
(161, 8, 'LOGOUT', 'auth', 8, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:35:05'),
(162, 5, 'LOGIN', 'auth', 5, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 08:35:19'),
(163, 5, 'LOGOUT', 'auth', 5, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 09:19:43'),
(164, 7, 'LOGIN', 'auth', 7, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 09:20:06'),
(165, 3, 'TRANSFER', 'affectations', 22, 'Affectation SR-EMUCI-0003 → site:1 user:7 (BL:bl_equip_22_20260419_092051_f0f06df8.pdf)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 09:20:51'),
(166, 7, 'LOGOUT', 'auth', 7, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 09:25:20'),
(167, 6, 'LOGIN', 'auth', 6, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 09:25:34'),
(168, 6, 'CREATE', 'interventions', 1, 'Intervention site_id:1 type:installation', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 09:26:58'),
(169, 6, 'CREATE', 'interventions', 2, 'Intervention site_id:3 type:diagnostic', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 09:27:42'),
(170, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 19:57:10'),
(171, 6, 'LOGIN', 'auth', 6, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 19:59:13'),
(172, 6, 'CREATE', 'interventions', 3, 'Intervention site_id:3 type:remplacement', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 20:00:41'),
(173, 6, 'LOGOUT', 'auth', 6, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 20:02:35'),
(174, 7, 'LOGIN', 'auth', 7, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 20:02:43'),
(175, 7, 'UPDATE', 'receptions', 4, 'Litige signalé : format non correct', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 20:10:48'),
(176, 7, 'LOGOUT', 'auth', 7, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 20:11:05'),
(177, 8, 'LOGIN', 'auth', 8, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 20:11:13'),
(178, 3, 'UPDATE', 'operations', 1, 'Consommation HP000000HG45: -8 (stock: 444 -> 436)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 20:59:57'),
(179, 3, 'UPDATE', 'operations', 4, 'Consommation HP000000HG28: -452 (stock: 455 -> 3)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 21:00:37'),
(180, 3, 'CREATE', 'inventaire_bobines', 1, 'Création inventaire 2026-04-19 — 3 bobines', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 22:02:13'),
(181, 3, 'UPDATE', 'inventaire_bobines', 1, 'Validation inventaire #1 — 2 écart(s)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 22:03:17'),
(182, 3, 'CREATE', 'inventaire_bobines', 2, 'Création inventaire 2026-04-19 — 3 bobines', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-19 23:18:22'),
(183, 3, 'CREATE', 'inventaire_bobines', 3, 'Création inventaire 2026-04-20 — 3 bobines', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 05:47:08'),
(184, 3, 'UPDATE', 'inventaire_bobines', 3, 'Validation inventaire #3 — 1 écart(s)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 05:48:01'),
(185, 3, 'CREATE', 'inventaire_bobines', 4, 'Création inventaire 2026-04-20 — 3 bobines', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 06:15:31'),
(186, 3, 'UPDATE', 'inventaire_bobines', 4, 'Validation inventaire #4 — 2 écart(s)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 06:17:06'),
(187, 3, 'CREATE', 'operations', 5, 'Point journalier intermediaire — Site:1 — 2026-04-20 — 27 engins', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 06:20:20'),
(188, 3, 'UPDATE', 'receptions', 4, 'Litige traité (renvoi) : jgvbn,', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 06:34:58'),
(189, 3, 'UPDATE', 'operations', 1, 'Consommation HP000000HG45: -45 (stock: 440 -> 395)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 06:44:31'),
(190, 3, 'CREATE', 'operations', 5, 'Création bobine H00000PKHG45 (C002) — stock initial: 500', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 06:45:08'),
(191, 3, 'UPDATE', 'users', 2, 'Modification permissions rôle ID:2', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 06:46:51'),
(192, 8, 'LOGOUT', 'auth', 8, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 08:55:18'),
(193, 6, 'LOGIN', 'auth', 6, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 08:55:39'),
(194, 6, 'CREATE', 'interventions', 4, 'Intervention site_id:1 type:diagnostic', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 08:57:44'),
(195, 6, 'LOGOUT', 'auth', 6, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 08:58:50'),
(196, 8, 'LOGIN', 'auth', 8, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 08:58:57'),
(197, 8, 'CREATE', 'consommables', 4, 'Réception 2 boite(s) de LIPTON (fournisseur: )', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 09:00:27'),
(198, 8, 'CREATE', 'consommables', 4, 'Distribution 1 boite(s) de LIPTON → site:1 (BL:bl_conso_4_site_1_20260420_122141_127e0da0.pdf)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 12:21:41'),
(199, 8, 'TRANSFER', 'affectations', 21, 'Affectation CLD-EMUCI-0003 → site:1 user:7 (BL:bl_equip_21_20260420_122241_f3bb34b4.pdf)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 12:22:41');
INSERT INTO `audit_log` (`id`, `user_id`, `action`, `module`, `entite_id`, `description`, `ancienne_valeur`, `nouvelle_valeur`, `ip_address`, `user_agent`, `created_at`) VALUES
(200, 8, 'LOGOUT', 'auth', 8, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 12:23:34'),
(201, 7, 'LOGIN', 'auth', 7, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 12:23:41'),
(202, 7, 'LOGOUT', 'auth', 7, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 13:05:04'),
(203, 6, 'LOGIN', 'auth', 6, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 13:05:17'),
(204, 6, 'LOGOUT', 'auth', 6, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 13:12:21'),
(205, 8, 'LOGIN', 'auth', 8, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 13:12:42'),
(206, 8, 'LOGOUT', 'auth', 8, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 13:17:15'),
(207, 5, 'LOGIN', 'auth', 5, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 13:17:23'),
(208, 5, 'LOGOUT', 'auth', 5, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 13:21:08'),
(209, 8, 'LOGIN', 'auth', 8, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 13:21:23'),
(210, 3, 'CREATE', 'inventaire_bobines', 5, 'Création inventaire 2026-04-20 — 4 bobines', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 14:36:10'),
(211, 8, 'LOGOUT', 'auth', 8, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 14:38:26'),
(212, 6, 'LOGIN', 'auth', 6, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 14:38:48'),
(213, 3, 'CREATE', 'inventaire_bobines', 6, 'Création inventaire 2026-04-20 — 1 bobines', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 14:42:46'),
(214, 3, 'UPDATE', 'inventaire_bobines', 6, 'Validation inventaire #6 — 1 écart(s)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 14:43:04'),
(215, 3, 'UPDATE', 'operations', 2, 'Consommation HP000000HG23: -50 (stock: 436 -> 386)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 15:49:32'),
(216, 3, 'CREATE', 'operations', 6, 'Création bobine HEERTY00000052 (A002) — stock initial: 500', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 15:50:06'),
(217, 3, 'CREATE', 'inventaire_bobines', 7, 'Création inventaire 2026-04-20 — 6 bobines', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 15:51:25'),
(218, 3, 'CREATE', 'interventions', 5, 'Intervention site_id:2 type:formation', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-20 16:34:35'),
(219, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 12:34:22'),
(220, 3, 'CREATE', 'inventaire_bobines', 8, 'Création inventaire 2026-04-21 — 5 bobines', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 12:37:36'),
(221, 7, 'LOGIN', 'auth', 7, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 12:47:29'),
(222, 7, 'UPDATE', 'receptions', 6, 'Réception validée avec fiche signée — site_id:1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 12:48:15'),
(223, 7, 'UPDATE', 'receptions', 5, 'Litige signalé : nombre pas exacte', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 12:48:28'),
(224, 7, 'CREATE', 'operations', 6, 'Point journalier final — Site:1 — 2026-04-21 — 30 engins', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 12:50:04'),
(225, 7, 'LOGOUT', 'auth', 7, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 12:50:48'),
(226, 8, 'LOGIN', 'auth', 8, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 12:50:56'),
(227, 8, 'LOGOUT', 'auth', 8, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 12:52:06'),
(228, 5, 'LOGIN', 'auth', 5, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-21 12:52:14'),
(229, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : donruthaxelle01@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 10:36:06'),
(230, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 10:36:16'),
(231, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 22:03:41'),
(232, 4, 'LOGIN', 'auth', 4, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 22:28:40'),
(233, 4, 'LOGOUT', 'auth', 4, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 22:29:49'),
(234, 8, 'LOGIN', 'auth', 8, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-23 22:29:58'),
(235, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 08:08:12'),
(236, 8, 'LOGIN', 'auth', 8, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 08:09:52'),
(237, 8, 'LOGOUT', 'auth', 8, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 09:42:09'),
(238, 7, 'LOGIN', 'auth', 7, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 09:42:20'),
(239, 7, 'LOGIN', 'auth', 7, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 10:03:53'),
(240, 7, 'CREATE', 'operations', 7, 'Point journalier intermediaire — Site:1 — 2026-04-24 — 9 engins', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 13:58:22'),
(241, 7, 'LOGOUT', 'auth', 7, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 13:59:28'),
(242, 6, 'LOGIN', 'auth', 6, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 13:59:35'),
(243, 6, 'CREATE', 'equipements', 25, 'Création équipement CLV-EMUCI-0003', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 14:00:46'),
(244, 3, 'CREATE', 'inventaire_bobines', 9, 'Création inventaire 2026-04-24 — 5 bobines', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 14:12:08'),
(245, 3, 'CREATE', 'users', 9, 'Création de l\'utilisateur Pose gestion (gestionpose@gmail.com)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 18:11:36'),
(246, 9, 'LOGIN', 'auth', 9, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', '2026-04-24 18:12:24'),
(247, 7, 'LOGIN', 'auth', 7, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 18:16:26'),
(248, 6, 'LOGOUT', 'auth', 6, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 18:16:56'),
(249, 9, 'LOGIN', 'auth', 9, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 18:17:15'),
(250, 9, 'CREATE', 'point_emuci', 1, 'Point EMUCI site:1 2026-04-24 — posées:13 réservées:2', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 18:21:55'),
(251, 7, 'CREATE', 'operations', 8, 'Point journalier final — Site:1 — 2026-04-24 — 9 engins', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 18:24:09'),
(252, 9, 'UPDATE', 'point_emuci', 1, 'Point EMUCI site:1 2026-04-24 — posées:25 réservées:0', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-24 18:25:12'),
(253, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : donruthaxelle01@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:31:17'),
(254, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:31:23'),
(255, 6, 'LOGIN', 'auth', 6, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:32:17'),
(256, 6, 'LOGOUT', 'auth', 6, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:32:46'),
(257, 7, 'LOGIN', 'auth', 7, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:33:11'),
(258, 7, 'LOGOUT', 'auth', 7, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:34:07'),
(259, 8, 'LOGIN', 'auth', 8, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:34:19'),
(260, 8, 'CREATE', 'consommables', 3, 'Distribution 200 paquet(s) de rivets → site:1 (BL:bl_conso_3_site_1_20260427_143527_f75426e8.pdf)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:35:27'),
(261, 8, 'LOGOUT', 'auth', 8, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:35:31'),
(262, 7, 'LOGIN', 'auth', 7, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:38:50'),
(263, 7, 'UPDATE', 'receptions', 7, 'Réception validée avec fiche signée — site_id:1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:39:21'),
(264, 7, 'LOGOUT', 'auth', 7, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:41:07'),
(265, 8, 'LOGIN', 'auth', 8, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:41:18'),
(266, 8, 'LOGOUT', 'auth', 8, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:41:31'),
(267, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:41:42'),
(268, 3, 'CREATE', 'operations', 1, 'Approvisionnement 200 rivets → GU ABIDJAN (Bl-453)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:42:06'),
(269, 3, 'LOGOUT', 'auth', 3, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:42:15'),
(270, 8, 'LOGIN', 'auth', 8, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:42:27'),
(271, 8, 'LOGOUT', 'auth', 8, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:42:50'),
(272, 7, 'LOGIN', 'auth', 7, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:42:56'),
(273, 7, 'CREATE', 'operations', 9, 'Point journalier intermediaire — Site:1 — 2026-04-27 — 12 engins', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:43:14'),
(274, 7, 'LOGOUT', 'auth', 7, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:43:20'),
(275, 9, 'LOGIN', 'auth', 9, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:43:31'),
(276, 9, 'LOGOUT', 'auth', 9, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:44:31'),
(277, 7, 'LOGIN', 'auth', 7, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:44:41'),
(278, 7, 'CREATE', 'operations', 9, 'Point journalier intermediaire — Site:1 — 2026-04-27 — 8 engins', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:49:03'),
(279, 7, 'LOGOUT', 'auth', 7, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:49:19'),
(280, 5, 'LOGIN', 'auth', 5, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:49:32'),
(281, 5, 'UPDATE', 'operations', 3, 'Validation point journalier #3', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:50:13'),
(282, 5, 'UPDATE', 'operations', 2, 'Validation point journalier #2', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:50:16'),
(283, 5, 'UPDATE', 'operations', 4, 'Validation point journalier #4', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:50:19'),
(284, 5, 'UPDATE', 'operations', 5, 'Validation point journalier #5', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:50:21'),
(285, 5, 'UPDATE', 'operations', 9, 'Validation point journalier #9', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:50:32'),
(286, 5, 'LOGOUT', 'auth', 5, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:50:39'),
(287, 9, 'LOGIN', 'auth', 9, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:50:50'),
(288, 9, 'CREATE', 'point_emuci', 2, 'Point EMUCI site:1 2026-04-27 — posées:12 réservées:4', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:53:07'),
(289, 9, 'LOGOUT', 'auth', 9, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:53:49'),
(290, 7, 'LOGIN', 'auth', 7, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:54:02'),
(291, 7, 'CREATE', 'operations', 10, 'Point journalier final — Site:1 — 2026-04-27 — 10 engins', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:54:22'),
(292, 7, 'LOGOUT', 'auth', 7, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:54:32'),
(293, 5, 'LOGIN', 'auth', 5, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:54:40'),
(294, 5, 'UPDATE', 'operations', 10, 'Validation point journalier #10', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:54:53'),
(295, 5, 'LOGOUT', 'auth', 5, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:56:19'),
(296, 9, 'LOGIN', 'auth', 9, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:56:38'),
(297, 9, 'LOGOUT', 'auth', 9, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:57:25'),
(298, 7, 'LOGIN', 'auth', 7, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:57:33'),
(299, 7, 'LOGOUT', 'auth', 7, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:58:03'),
(300, 9, 'LOGIN', 'auth', 9, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:58:16'),
(301, 9, 'UPDATE', 'point_emuci', 2, 'Point EMUCI site:1 2026-04-27 — posées:16 réservées:0', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 14:59:53'),
(302, 9, 'UPDATE', 'point_emuci', 2, 'Point EMUCI site:1 2026-04-27 — posées:36 réservées:0', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:01:16'),
(303, 9, 'LOGOUT', 'auth', 9, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:01:23'),
(304, 5, 'LOGIN', 'auth', 5, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:01:35'),
(305, 5, 'LOGOUT', 'auth', 5, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:03:03'),
(306, 8, 'LOGIN', 'auth', 8, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:03:10'),
(307, 8, 'LOGOUT', 'auth', 8, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:03:30'),
(308, 4, 'LOGIN', 'auth', 4, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:03:41'),
(309, 4, 'LOGOUT', 'auth', 4, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:03:56'),
(310, 5, 'LOGIN', 'auth', 5, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:06:00'),
(311, 3, 'UPDATE', 'users', 8, 'Modification permissions rôle ID:8', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:06:38'),
(312, 3, 'UPDATE', 'users', 6, 'Modification permissions rôle ID:6', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:06:42'),
(313, 5, 'LOGOUT', 'auth', 5, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:06:57'),
(314, 5, 'LOGIN', 'auth', 5, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:07:04'),
(315, 3, 'UPDATE', 'users', 8, 'Modification permissions rôle ID:8', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:07:43'),
(316, 3, 'UPDATE', 'users', 8, 'Modification permissions rôle ID:8', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:07:49'),
(317, 3, 'UPDATE', 'users', 6, 'Modification permissions rôle ID:6', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:07:59'),
(318, 3, 'CREATE', 'inventaire_bobines', 10, 'Création inventaire 2026-04-27 — 5 bobines', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:09:41'),
(319, 3, 'UPDATE', 'inventaire_bobines', 10, 'Validation inventaire #10 — 3 écart(s)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:13:41'),
(320, 5, 'LOGOUT', 'auth', 5, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:18:30'),
(321, 5, 'LOGIN', 'auth', 5, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:18:52'),
(322, 3, 'UPDATE', 'users', 8, 'Modification permissions rôle ID:8', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:19:28'),
(323, 3, 'UPDATE', 'users', 8, 'Modification permissions rôle ID:8', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:19:32'),
(324, 3, 'UPDATE', 'users', 8, 'Modification permissions rôle ID:8', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:19:34'),
(325, 5, 'LOGOUT', 'auth', 5, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:22:54'),
(326, 5, 'LOGIN', 'auth', 5, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:23:14'),
(327, 5, 'CREATE', 'inventaire_bobines', 11, 'Création inventaire 2026-04-27 — 5 bobines', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:41:42'),
(328, 5, 'UPDATE', 'inventaire_bobines', 11, 'Validation inventaire #11 — 1 écart(s)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:43:12'),
(329, 5, 'LOGOUT', 'auth', 5, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:52:53'),
(330, 7, 'LOGIN', 'auth', 7, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:53:08'),
(331, 3, 'UPDATE', 'users', 6, 'Modification permissions rôle ID:6', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:54:04'),
(332, 3, 'UPDATE', 'users', 6, 'Modification permissions rôle ID:6', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:54:07'),
(333, 3, 'UPDATE', 'users', 6, 'Modification permissions rôle ID:6', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 15:56:28'),
(334, 7, 'LOGOUT', 'auth', 7, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 17:03:28'),
(335, 3, 'LOGOUT', 'auth', 3, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 17:03:41'),
(336, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 17:03:56'),
(337, 3, 'UPDATE', 'users', 5, 'Modification permissions rôle ID:5', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 17:05:19'),
(338, 5, 'LOGIN', 'auth', 5, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 17:06:20'),
(339, 5, 'CREATE', 'operations', 1, 'Approvisionnement 100 rivets → GU ABIDJAN ()', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 17:07:56'),
(340, 5, 'LOGOUT', 'auth', 5, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 17:08:20'),
(341, 7, 'LOGIN', 'auth', 7, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 17:08:39'),
(342, 7, 'LOGOUT', 'auth', 7, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 17:17:46'),
(343, 5, 'LOGIN', 'auth', 5, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 17:18:08'),
(344, 5, 'LOGOUT', 'auth', 5, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 17:19:56'),
(345, 9, 'LOGIN', 'auth', 9, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 17:20:11'),
(346, 9, 'LOGOUT', 'auth', 9, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 17:31:59'),
(347, 5, 'LOGIN', 'auth', 5, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-27 17:35:22'),
(348, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 09:32:56'),
(349, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 09:36:43'),
(350, 3, 'LOGOUT', 'auth', 3, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 09:54:53'),
(351, 7, 'LOGIN', 'auth', 7, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 09:55:07'),
(352, 7, 'CREATE', 'operations', 11, 'Point journalier intermediaire — Site:1 — 2026-04-28 — 4 engins', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 09:58:11'),
(353, 7, 'LOGOUT', 'auth', 7, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 10:25:27'),
(354, 5, 'LOGIN', 'auth', 5, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 10:25:41'),
(355, 5, 'LOGOUT', 'auth', 5, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 16:54:53'),
(356, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-28 16:55:16'),
(357, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : donruthaxelle01@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 15:42:43'),
(358, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 15:42:51'),
(359, 3, 'CREATE', 'sites', 10, 'Création site ABJ-06 — FDS AKOUEDO', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 15:45:19'),
(360, 3, 'UPDATE', 'sites', 7, 'Modification site ABJ-05', '{\"id\":7,\"code\":\"ABJ-05\",\"nom\":\"TEST AKOUEDO\",\"type\":\"mixte\",\"option_caisse\":1,\"adresse\":\"\",\"ville\":\"ABIDJAN\",\"pays\":\"Côte d\'Ivoire\",\"responsable_id\":null,\"actif\":1,\"created_at\":\"2026-04-03 04:22:59\",\"updated_at\":\"2026-04-03 04:22:59\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 15:45:44'),
(361, 3, 'CREATE', 'equipements', 26, 'Création équipement PRS-EMUCI-0004', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 22:26:33'),
(362, 3, 'CREATE', 'equipements', 27, 'Création équipement SR-EMUCI-0004', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 22:27:10'),
(363, 3, 'UPDATE', 'equipements', 26, 'Modification PRS-EMUCI-0004', '{\"id\":26,\"numero_serie_interne\":\"PRS-EMUCI-0004\",\"numero_serie_origine\":\"BW250065522\",\"numero_chrono\":22,\"nomenclature_id\":9,\"categorie\":\"informatique\",\"site_id\":null,\"utilisateur_id\":null,\"etat\":\"neuf\",\"date_acquisition\":null,\"date_mise_en_service\":null,\"date_fin_cycle\":null,\"marque\":\"hp\",\"modele\":\"oiuytfg\",\"observations\":\"\",\"actif\":1,\"created_by\":3,\"created_at\":\"2026-04-29 22:26:33\",\"updated_at\":\"2026-04-29 22:26:33\"}', '{\"numero_serie_origine\":\"BW250065522\",\"marque\":\"hp\",\"modele\":\"oiuytfg\",\"etat\":\"neuf\",\"site_id\":null,\"utilisateur_id\":null,\"date_acquisition\":null,\"date_mise_en_service\":null,\"date_fin_cycle\":null,\"observations\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 22:27:31'),
(364, 3, 'UPDATE', 'equipements', 27, 'Modification SR-EMUCI-0004', '{\"id\":27,\"numero_serie_interne\":\"SR-EMUCI-0004\",\"numero_serie_origine\":\"ECR25005821\",\"numero_chrono\":23,\"nomenclature_id\":3,\"categorie\":\"informatique\",\"site_id\":null,\"utilisateur_id\":null,\"etat\":\"neuf\",\"date_acquisition\":null,\"date_mise_en_service\":null,\"date_fin_cycle\":null,\"marque\":\"lkjhg\",\"modele\":\"\",\"observations\":\"\",\"actif\":1,\"created_by\":3,\"created_at\":\"2026-04-29 22:27:10\",\"updated_at\":\"2026-04-29 22:27:10\"}', '{\"numero_serie_origine\":\"ECR25005821\",\"marque\":\"lkjhg\",\"modele\":\"\",\"etat\":\"neuf\",\"site_id\":null,\"utilisateur_id\":null,\"date_acquisition\":null,\"date_mise_en_service\":null,\"date_fin_cycle\":null,\"observations\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 22:28:01'),
(365, 3, 'UPDATE', 'equipements', 26, 'Modification PRS-EMUCI-0004', '{\"id\":26,\"numero_serie_interne\":\"PRS-EMUCI-0004\",\"numero_serie_origine\":\"BW250065522\",\"numero_chrono\":22,\"nomenclature_id\":9,\"categorie\":\"informatique\",\"site_id\":null,\"utilisateur_id\":null,\"etat\":\"neuf\",\"date_acquisition\":null,\"date_mise_en_service\":null,\"date_fin_cycle\":null,\"marque\":\"hp\",\"modele\":\"oiuytfg\",\"observations\":\"\",\"actif\":1,\"created_by\":3,\"created_at\":\"2026-04-29 22:26:33\",\"updated_at\":\"2026-04-29 22:26:33\"}', '{\"numero_serie_origine\":\"BW250065522\",\"marque\":\"hp\",\"modele\":\"oiuytfg\",\"etat\":\"neuf\",\"site_id\":null,\"utilisateur_id\":null,\"date_acquisition\":null,\"date_mise_en_service\":null,\"date_fin_cycle\":null,\"observations\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 22:28:16'),
(366, 3, 'UPDATE', 'equipements', 27, 'Modification SR-EMUCI-0004', '{\"id\":27,\"numero_serie_interne\":\"SR-EMUCI-0004\",\"numero_serie_origine\":\"ECR25005821\",\"numero_chrono\":23,\"nomenclature_id\":3,\"categorie\":\"informatique\",\"site_id\":null,\"utilisateur_id\":null,\"etat\":\"neuf\",\"date_acquisition\":null,\"date_mise_en_service\":null,\"date_fin_cycle\":null,\"marque\":\"lkjhg\",\"modele\":\"\",\"observations\":\"\",\"actif\":1,\"created_by\":3,\"created_at\":\"2026-04-29 22:27:10\",\"updated_at\":\"2026-04-29 22:27:10\"}', '{\"numero_serie_origine\":\"ECR25005821\",\"marque\":\"lkjhg\",\"modele\":\"\",\"etat\":\"neuf\",\"site_id\":null,\"utilisateur_id\":null,\"date_acquisition\":null,\"date_mise_en_service\":null,\"date_fin_cycle\":null,\"observations\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 22:28:25'),
(367, 3, 'CREATE', 'operations', 3, 'Approvisionnement 20 rivets → CFAO ()', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 22:32:34'),
(368, 3, 'UPDATE', 'operations', 11, 'Validation point journalier #11', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-29 22:33:16'),
(369, 4, 'LOGIN', 'auth', 4, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 14:24:23'),
(370, 4, 'LOGIN', 'auth', 4, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 14:44:50'),
(371, 4, 'LOGOUT', 'auth', 4, 'Déconnexion', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 15:41:45'),
(372, NULL, 'LOGIN', 'auth', NULL, 'Tentative de connexion échouée pour : donruthaxelle01@gmail.com', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 15:45:05'),
(373, 3, 'LOGIN', 'auth', 3, 'Connexion réussie — IP: ::1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 15:45:14'),
(374, 3, 'CREATE', 'inventaire_bobines', 12, 'Inventaire mensuel 2026-04 site:1', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 18:20:26'),
(375, 3, 'UPDATE', 'inventaire_bobines', 12, 'Validation inventaire #12 — 0 écart(s)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-04-30 18:22:48');

-- --------------------------------------------------------

--
-- Structure de la table `bilans_mensuels_bobines`
--

CREATE TABLE `bilans_mensuels_bobines` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `mois` varchar(7) NOT NULL COMMENT 'Format YYYY-MM',
  `inventaire_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Inventaire mensuel lié',
  `stock_debut_mois` int(11) DEFAULT 0 COMMENT 'Stock système au 1er du mois',
  `stock_fin_mois` int(11) DEFAULT 0 COMMENT 'Stock système à la fin du mois',
  `total_films_consommes` int(11) DEFAULT 0 COMMENT 'Total films consommés (DigiStock)',
  `total_films_emuci` int(11) DEFAULT 0 COMMENT 'Total films déduits (EMUCI)',
  `ecart_mois` int(11) DEFAULT 0 COMMENT 'Écart total du mois EMUCI vs DigiStock',
  `nb_inventaires_journaliers` int(11) DEFAULT 0,
  `nb_ajustements` int(11) DEFAULT 0,
  `statut` enum('en_cours','valide') NOT NULL DEFAULT 'en_cours',
  `valide_par` int(10) UNSIGNED DEFAULT NULL,
  `valide_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commandes`
--

CREATE TABLE `commandes` (
  `id` int(10) UNSIGNED NOT NULL,
  `numero_commande` varchar(30) NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `statut` enum('en_attente','en_attente_livraison','livre','annule') NOT NULL DEFAULT 'en_attente',
  `notes` text DEFAULT NULL,
  `notes_livraison` text DEFAULT NULL,
  `livraison_par` int(10) UNSIGNED DEFAULT NULL,
  `livraison_at` datetime DEFAULT NULL,
  `recu_par` int(10) UNSIGNED DEFAULT NULL,
  `recu_at` datetime DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Commandes des coordinateurs';

-- --------------------------------------------------------

--
-- Structure de la table `commande_lignes`
--

CREATE TABLE `commande_lignes` (
  `id` int(10) UNSIGNED NOT NULL,
  `commande_id` int(10) UNSIGNED NOT NULL,
  `type_article` enum('consommable','bobine','equipement','pmma','autre') NOT NULL DEFAULT 'consommable',
  `article_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID de l article dans sa table respective',
  `libelle` varchar(255) NOT NULL,
  `quantite` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `unite` varchar(30) DEFAULT 'unité',
  `prix_unitaire` decimal(15,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lignes de commandes';

-- --------------------------------------------------------

--
-- Structure de la table `comparaisons_stock`
--

CREATE TABLE `comparaisons_stock` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `date_comparaison` date NOT NULL,
  `films_emuci` int(11) NOT NULL DEFAULT 0 COMMENT 'Films déduits selon EMUCI (plaques posées + réservées)',
  `films_digistock` int(11) NOT NULL DEFAULT 0 COMMENT 'Films consommés selon PJ coordinateurs DigiStock',
  `ecart` int(11) GENERATED ALWAYS AS (`films_emuci` - `films_digistock`) STORED COMMENT 'Écart = EMUCI - DigiStock (positif = DigiStock sous-estimé)',
  `statut_ecart` enum('ok','mineur','majeur') GENERATED ALWAYS AS (case when abs(`films_emuci` - `films_digistock`) = 0 then 'ok' when abs(`films_emuci` - `films_digistock`) <= 5 then 'mineur' else 'majeur' end) STORED,
  `ajuste` tinyint(1) DEFAULT 0,
  `ajuste_par` int(10) UNSIGNED DEFAULT NULL,
  `ajuste_at` datetime DEFAULT NULL,
  `notes_ajustement` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Comparaison quotidienne EMUCI vs DigiStock par site';

--
-- Déchargement des données de la table `comparaisons_stock`
--

INSERT INTO `comparaisons_stock` (`id`, `site_id`, `date_comparaison`, `films_emuci`, `films_digistock`, `ajuste`, `ajuste_par`, `ajuste_at`, `notes_ajustement`, `created_at`) VALUES
(1, 1, '2026-04-24', 25, 25, 0, NULL, NULL, NULL, '2026-04-24 18:21:55'),
(2, 1, '2026-04-27', 36, 36, 0, NULL, NULL, NULL, '2026-04-27 14:53:07');

-- --------------------------------------------------------

--
-- Structure de la table `configurations_site`
--

CREATE TABLE `configurations_site` (
  `id` int(10) UNSIGNED NOT NULL,
  `type_site` enum('saisie','pose','mixte','caissier','entrepot','siege') NOT NULL,
  `nomenclature_id` int(10) UNSIGNED NOT NULL,
  `quantite` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `optionnel` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `configurations_site`
--

INSERT INTO `configurations_site` (`id`, `type_site`, `nomenclature_id`, `quantite`, `optionnel`) VALUES
(1, 'pose', 1, 1, 0),
(2, 'pose', 5, 1, 0),
(3, 'pose', 6, 1, 0),
(4, 'pose', 7, 1, 0),
(5, 'pose', 2, 1, 0),
(6, 'pose', 3, 1, 0),
(7, 'pose', 9, 1, 0),
(8, 'saisie', 1, 1, 0),
(9, 'saisie', 6, 1, 0),
(10, 'saisie', 7, 1, 0),
(11, 'saisie', 3, 1, 0),
(12, 'saisie', 4, 1, 0),
(13, 'mixte', 1, 1, 0),
(14, 'mixte', 5, 1, 0),
(15, 'mixte', 6, 1, 0),
(16, 'mixte', 2, 1, 0),
(17, 'mixte', 8, 1, 0),
(18, 'mixte', 7, 1, 0),
(19, 'mixte', 9, 1, 0),
(20, 'mixte', 3, 1, 0),
(21, 'mixte', 4, 1, 0);

-- --------------------------------------------------------

--
-- Structure de la table `config_postes_composants`
--

CREATE TABLE `config_postes_composants` (
  `id` int(10) UNSIGNED NOT NULL,
  `poste_id` int(10) UNSIGNED NOT NULL,
  `nomenclature_id` int(10) UNSIGNED NOT NULL,
  `quantite` int(10) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `config_postes_types`
--

CREATE TABLE `config_postes_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `libelle` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `consommables`
--

CREATE TABLE `consommables` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `libelle` varchar(150) NOT NULL,
  `unite` enum('unite','kg','litre','boite','rame','paquet') NOT NULL DEFAULT 'unite',
  `description` text DEFAULT NULL,
  `seuil_alerte` decimal(10,2) DEFAULT 10.00,
  `prix_unitaire` decimal(12,2) DEFAULT 0.00,
  `stock_global` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `categorie` varchar(100) DEFAULT NULL COMMENT 'Catégorie ex: Papeterie, Informatique, Entretien...'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `consommables`
--

INSERT INTO `consommables` (`id`, `code`, `libelle`, `unite`, `description`, `seuil_alerte`, `prix_unitaire`, `stock_global`, `created_at`, `updated_at`, `categorie`) VALUES
(1, 'CAF', 'Café Moulu', 'boite', 'nescafé', 2.00, 562.00, 7.99, '2026-04-02 06:17:16', '2026-05-28 10:17:20', NULL),
(2, 'SCR', 'Sucre', 'boite', 'Sucre pour le café ou le thé', 3.00, 3250.00, 13.00, '2026-04-02 09:22:25', '2026-04-03 05:00:06', NULL),
(3, 'RVT', 'rivets', 'paquet', 'rivets gonflabe', 2.00, 4500.00, 1796.00, '2026-04-02 14:19:54', '2026-04-27 14:35:27', NULL),
(4, 'THE', 'LIPTON', 'boite', '', 2.00, 1325.00, 6.00, '2026-04-03 04:29:52', '2026-04-20 12:21:41', NULL),
(5, 'PH', 'Papier toilette', 'paquet', 'Papiers toilette', 5.00, 2000.00, 20.98, '2026-04-03 04:37:52', '2026-04-15 22:37:46', NULL),
(6, 'LT', 'Lotus', 'boite', '', 5.00, 1500.00, 15.00, '2026-04-03 04:56:14', '2026-04-03 04:57:38', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `consommations_bobines`
--

CREATE TABLE `consommations_bobines` (
  `id` int(10) UNSIGNED NOT NULL,
  `bobine_id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `date_conso` date NOT NULL,
  `quantite` int(10) UNSIGNED NOT NULL COMMENT 'Quantité consommée ce jour (rouleaux)',
  `stock_avant` int(10) UNSIGNED NOT NULL COMMENT 'Stock avant consommation',
  `stock_apres` int(10) UNSIGNED NOT NULL COMMENT 'Stock après consommation',
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `consommations_bobines`
--

INSERT INTO `consommations_bobines` (`id`, `bobine_id`, `site_id`, `date_conso`, `quantite`, `stock_avant`, `stock_apres`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 1, '2026-04-19', 8, 444, 436, 'mlkjhn,', 3, '2026-04-19 20:59:57'),
(2, 4, 2, '2026-04-19', 452, 455, 3, '', 3, '2026-04-19 21:00:37'),
(3, 1, 1, '2026-04-20', 45, 440, 395, '', 3, '2026-04-20 06:44:31'),
(4, 2, 1, '2026-04-20', 50, 436, 386, '', 3, '2026-04-20 15:49:32');

-- --------------------------------------------------------

--
-- Structure de la table `delegations`
--

CREATE TABLE `delegations` (
  `id` int(10) UNSIGNED NOT NULL,
  `superviseur_id` int(10) UNSIGNED NOT NULL COMMENT 'Superviseur qui délègue',
  `gestionnaire_id` int(10) UNSIGNED NOT NULL COMMENT 'Gestionnaire Opération qui reçoit',
  `module` varchar(50) NOT NULL COMMENT 'Module délégué ex: validation_pj, bobines, inventaire',
  `libelle` varchar(100) NOT NULL COMMENT 'Label affiché',
  `actif` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tâches déléguées du Superviseur au Gestionnaire Opération';

-- --------------------------------------------------------

--
-- Structure de la table `demandes_bobines`
--

CREATE TABLE `demandes_bobines` (
  `id` int(10) UNSIGNED NOT NULL,
  `bobine_id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `demande_par` int(10) UNSIGNED NOT NULL COMMENT 'Coordinateur',
  `motif` text NOT NULL COMMENT 'Motif de la demande',
  `statut` enum('en_attente','approuvee','refusee') NOT NULL DEFAULT 'en_attente',
  `traite_par` int(10) UNSIGNED DEFAULT NULL COMMENT 'GSB qui a traité',
  `traite_at` datetime DEFAULT NULL,
  `motif_reponse` text DEFAULT NULL COMMENT 'Motif refus ou commentaire approbation',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Demandes utilisation bobine coordinateur → GSB';

-- --------------------------------------------------------

--
-- Structure de la table `ecarts_bobines`
--

CREATE TABLE `ecarts_bobines` (
  `id` int(10) UNSIGNED NOT NULL,
  `bobine_id` int(10) UNSIGNED NOT NULL,
  `date_constat` date NOT NULL COMMENT 'Date à laquelle l écart a été constaté',
  `stock_systeme` int(11) NOT NULL COMMENT 'Stock système au moment du constat',
  `stock_physique` int(11) NOT NULL COMMENT 'Stock physique compté',
  `ecart` int(11) NOT NULL COMMENT 'physique - système (négatif = manque)',
  `motif` text DEFAULT NULL COMMENT 'Explication de l écart',
  `source` enum('inventaire','manuel','import') NOT NULL DEFAULT 'manuel',
  `inventaire_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Lien vers la session d inventaire si source=inventaire',
  `statut` enum('ouvert','resolu','ignore') NOT NULL DEFAULT 'ouvert',
  `resolu_at` datetime DEFAULT NULL,
  `resolu_par` int(10) UNSIGNED DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ecarts_bobines`
--

INSERT INTO `ecarts_bobines` (`id`, `bobine_id`, `date_constat`, `stock_systeme`, `stock_physique`, `ecart`, `motif`, `source`, `inventaire_id`, `statut`, `resolu_at`, `resolu_par`, `resolution_notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-04-20', 445, 444, -1, '', 'inventaire', 3, 'resolu', '2026-04-20 05:48:01', 3, NULL, 3, '2026-04-20 05:48:01', '2026-04-27 15:41:00'),
(2, 1, '2026-04-20', 444, 440, -1, 'Écart connu – inventaire #4', 'manuel', NULL, 'resolu', '2026-04-27 15:43:12', 5, NULL, 3, '2026-04-20 06:16:44', '2026-04-27 15:43:12'),
(3, 2, '2026-04-20', 436, 436, -2, 'Écart connu – inventaire #4', 'manuel', NULL, 'ouvert', NULL, NULL, NULL, 3, '2026-04-20 06:16:44', '2026-04-20 06:16:44'),
(4, 3, '2026-04-20', 488, 495, 3, 'Écart connu – inventaire #4', 'manuel', NULL, 'ouvert', NULL, NULL, NULL, 3, '2026-04-20 06:16:44', '2026-04-20 06:16:44'),
(5, 1, '2026-04-20', 444, 440, -4, '', 'inventaire', 4, 'resolu', '2026-04-20 06:17:06', 3, NULL, 3, '2026-04-20 06:17:06', '2026-04-27 15:41:00'),
(6, 3, '2026-04-20', 488, 495, 7, '', 'inventaire', 4, 'resolu', '2026-04-20 06:17:06', 3, NULL, 3, '2026-04-20 06:17:06', '2026-04-27 15:41:00'),
(7, 4, '2026-04-20', 3, 2, -1, '', 'inventaire', 6, 'resolu', '2026-04-20 14:43:04', 3, NULL, 3, '2026-04-20 14:43:04', '2026-04-27 15:41:00'),
(8, 1, '2026-04-20', 395, 392, -6, 'Écart connu déclaré lors inventaire #5', 'manuel', NULL, 'resolu', '2026-04-27 15:43:12', 5, NULL, 3, '2026-04-21 12:42:01', '2026-04-27 15:43:12'),
(9, 2, '2026-04-27', 386, 388, -2, 'Écart connu déclaré lors inventaire #10', 'manuel', NULL, 'ouvert', NULL, NULL, NULL, 3, '2026-04-27 15:13:26', '2026-04-27 15:13:26'),
(10, 3, '2026-04-27', 495, 500, 10, 'Écart connu déclaré lors inventaire #10', 'manuel', NULL, 'ouvert', NULL, NULL, NULL, 3, '2026-04-27 15:13:28', '2026-04-27 15:13:28'),
(13, 2, '2026-04-27', 386, 388, -2, 'Écart connu – inventaire #10', 'manuel', NULL, 'ouvert', NULL, NULL, NULL, 3, '2026-04-27 15:13:31', '2026-04-27 15:13:31'),
(14, 3, '2026-04-27', 495, 500, 10, 'Écart connu – inventaire #10', 'manuel', NULL, 'ouvert', NULL, NULL, NULL, 3, '2026-04-27 15:13:31', '2026-04-27 15:13:31'),
(15, 2, '2026-04-27', 386, 388, 2, '', 'inventaire', 10, 'resolu', '2026-04-27 15:13:41', 3, NULL, 3, '2026-04-27 15:13:41', '2026-04-27 15:41:00'),
(16, 3, '2026-04-27', 495, 500, 5, '', 'inventaire', 10, 'resolu', '2026-04-27 15:13:41', 3, NULL, 3, '2026-04-27 15:13:41', '2026-04-27 15:41:00'),
(17, 6, '2026-04-27', 500, 498, -2, '', 'inventaire', 10, 'resolu', '2026-04-27 15:13:41', 3, NULL, 3, '2026-04-27 15:13:41', '2026-04-27 15:41:00'),
(18, 1, '2026-04-27', 395, 364, -31, 'Écart connu déclaré lors inventaire #11', 'manuel', NULL, 'resolu', '2026-04-27 15:43:12', 5, NULL, 5, '2026-04-27 15:43:06', '2026-04-27 15:43:12'),
(19, 1, '2026-04-27', 395, 364, -31, '', 'inventaire', 11, 'resolu', '2026-04-27 15:43:12', 5, NULL, 5, '2026-04-27 15:43:12', '2026-04-27 15:43:12');

-- --------------------------------------------------------

--
-- Structure de la table `equipements`
--

CREATE TABLE `equipements` (
  `id` int(10) UNSIGNED NOT NULL,
  `numero_serie_interne` varchar(50) NOT NULL,
  `numero_serie_origine` varchar(100) DEFAULT NULL,
  `numero_chrono` int(10) UNSIGNED NOT NULL,
  `nomenclature_id` int(10) UNSIGNED NOT NULL,
  `categorie` enum('informatique','operationnel') NOT NULL DEFAULT 'informatique' COMMENT 'Catégorie : informatique ou opérationnel',
  `site_id` int(10) UNSIGNED DEFAULT NULL,
  `utilisateur_id` int(10) UNSIGNED DEFAULT NULL,
  `etat` enum('neuf','bon','usage','hs','reforme') NOT NULL DEFAULT 'neuf',
  `date_acquisition` date DEFAULT NULL,
  `date_mise_en_service` date DEFAULT NULL,
  `date_fin_cycle` date DEFAULT NULL,
  `marque` varchar(100) DEFAULT NULL,
  `modele` varchar(100) DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `duree_amortissement_mois` int(10) UNSIGNED DEFAULT NULL COMMENT 'Durée amortissement OHADA en mois',
  `prix_achat` decimal(15,2) DEFAULT 0.00 COMMENT 'Prix achat en FCFA',
  `statut_stock` enum('affecte','en_stock') NOT NULL DEFAULT 'affecte' COMMENT 'affecte=en service, en_stock=disponible non affecté'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `equipements`
--

INSERT INTO `equipements` (`id`, `numero_serie_interne`, `numero_serie_origine`, `numero_chrono`, `nomenclature_id`, `categorie`, `site_id`, `utilisateur_id`, `etat`, `date_acquisition`, `date_mise_en_service`, `date_fin_cycle`, `marque`, `modele`, `observations`, `actif`, `created_by`, `created_at`, `updated_at`, `duree_amortissement_mois`, `prix_achat`, `statut_stock`) VALUES
(1, 'IMP-EMUCI-0001', 'BW25005821', 1, 2, 'informatique', 1, NULL, 'neuf', '2026-03-30', '2026-04-02', '2028-04-02', 'OKI', '', 'Pour l\'imprimeur 1 de CFAO', 1, 3, '2026-04-02 06:13:31', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(6, 'SR-EMUCI-0001', 'SR55225687', 2, 3, 'informatique', 3, NULL, 'bon', '2025-12-03', '2026-01-08', '2028-01-08', 'HP', 'sans fil', 'Souri pour l\'ordinateur de l\'imprimeur.', 1, 3, '2026-04-02 10:56:16', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(7, 'IMP-EMUCI-0002', 'BW2500552', 3, 2, 'informatique', 9, NULL, 'usage', '2023-10-28', '2023-11-02', '2025-11-02', '', '', '', 1, 3, '2026-04-02 10:57:27', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(8, 'CLV-EMUCI-0001', 'CL25688', 4, 1, 'informatique', 1, NULL, 'bon', '2025-12-03', '2025-12-10', '2027-12-10', 'HP', '', '', 1, 3, '2026-04-02 10:58:28', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(9, 'CLD-EMUCI-0001', 'CL25687', 5, 5, 'informatique', 2, NULL, 'bon', '2025-10-30', '2026-04-03', '2028-04-03', 'HP', '', '', 1, 3, '2026-04-02 10:59:35', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(10, 'LPT-EMUCI-0001', 'HP25687', 6, 8, 'informatique', NULL, NULL, 'neuf', '2026-04-02', NULL, NULL, 'HP', '', '', 1, 3, '2026-04-02 11:20:32', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(11, 'CLV-EMUCI-0002', 'CL25658', 7, 1, 'informatique', NULL, NULL, 'neuf', NULL, NULL, NULL, '', '', '', 1, 3, '2026-04-02 11:21:10', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(12, 'CLD-EMUCI-0002', 'CD25675', 8, 5, 'informatique', NULL, NULL, 'neuf', NULL, NULL, NULL, '', '', '', 1, 3, '2026-04-02 11:21:29', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(13, 'ECR-EMUCI-0001', 'EC25642', 9, 6, 'informatique', NULL, NULL, 'reforme', NULL, NULL, NULL, '', '', '', 0, 3, '2026-04-02 11:21:50', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(14, 'IMP-EMUCI-0003', 'BW25045552', 10, 2, 'informatique', NULL, NULL, 'neuf', NULL, NULL, NULL, 'OKI', '', '', 1, 3, '2026-04-02 11:22:02', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(15, 'OND-EMUCI-0001', 'HP25687', 11, 7, 'informatique', NULL, NULL, 'neuf', NULL, NULL, NULL, '', '', '', 1, 3, '2026-04-02 11:22:15', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(16, 'PRS-EMUCI-0001', 'PERS25005821', 12, 9, 'informatique', NULL, NULL, 'reforme', NULL, NULL, NULL, '', '', '', 0, 3, '2026-04-02 11:22:31', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(17, 'SR-EMUCI-0002', 'SR55225647', 13, 3, 'informatique', NULL, NULL, 'reforme', NULL, NULL, NULL, '', '', '', 0, 3, '2026-04-02 11:22:42', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(18, 'ECR-EMUCI-0002', 'OKI25687', 14, 6, 'informatique', NULL, NULL, 'neuf', NULL, NULL, NULL, 'HP', 'sans fil', '', 1, 3, '2026-04-02 15:35:16', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(19, 'PRS-EMUCI-0002', 'PERS25687', 15, 9, 'informatique', 9, NULL, 'neuf', '2026-04-03', NULL, NULL, 'HP', '', '', 1, 3, '2026-04-03 04:48:28', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(20, 'ECR-EMUCI-0003', 'ECR25005821', 16, 6, 'informatique', 1, 7, 'neuf', NULL, NULL, NULL, 'HP', '', '', 1, 3, '2026-04-03 05:06:51', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(21, 'CLD-EMUCI-0003', 'CL25745', 17, 5, 'informatique', 1, 7, 'neuf', '2026-04-03', NULL, NULL, 'HP', '', '', 1, 3, '2026-04-03 05:11:46', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(22, 'SR-EMUCI-0003', 'SR05654444G', 18, 3, 'informatique', 1, 7, 'bon', '2026-04-15', '2026-04-15', '2028-04-15', 'DELL', 'SANS FIL', '', 1, 3, '2026-04-15 09:30:04', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(23, 'PRS-EMUCI-0003', 'SR05654444L', 19, 9, 'operationnel', 1, 7, 'neuf', '2026-04-19', '2026-04-19', '2029-04-19', 'DELL', 'JYT', '', 1, 3, '2026-04-19 07:55:21', '2026-05-28 08:57:41', 60, 0.00, 'affecte'),
(24, 'CLD-EMUCI-0004', 'kjhbnklm', 20, 5, 'informatique', 9, NULL, 'neuf', '2026-04-19', '2026-04-20', '2028-04-20', 'mojn,l', 'mlkjnbn,', '', 1, 6, '2026-04-19 08:17:35', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(25, 'CLV-EMUCI-0003', 'azertyui', 21, 1, 'informatique', NULL, NULL, 'bon', '2026-04-24', NULL, NULL, 'dell', 'fghj', '', 1, 6, '2026-04-24 14:00:46', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(26, 'PRS-EMUCI-0004', 'BW250065522', 22, 9, 'informatique', NULL, NULL, 'neuf', NULL, NULL, NULL, 'hp', 'oiuytfg', '', 1, 3, '2026-04-29 22:26:33', '2026-05-28 08:57:41', 36, 0.00, 'affecte'),
(27, 'SR-EMUCI-0004', 'ECR25005821', 23, 3, 'informatique', NULL, NULL, 'neuf', NULL, NULL, NULL, 'lkjhg', '', '', 1, 3, '2026-04-29 22:27:10', '2026-05-28 08:57:41', 36, 0.00, 'affecte');

--
-- Déclencheurs `equipements`
--

-- --------------------------------------------------------

--
-- Structure de la table `import_optoplate`
--

CREATE TABLE `import_optoplate` (
  `id` int(10) UNSIGNED NOT NULL,
  `import_session_id` varchar(36) NOT NULL COMMENT 'UUID de la session d import',
  `date_import` date NOT NULL COMMENT 'Date du fichier importé',
  `date_installation` datetime DEFAULT NULL COMMENT 'Date d installation de la plaque',
  `numero_dossier` varchar(50) DEFAULT NULL,
  `immatriculation` varchar(30) NOT NULL,
  `vin` varchar(20) DEFAULT NULL,
  `type_plaque` varchar(60) DEFAULT NULL,
  `statut_plaque` varchar(30) NOT NULL COMMENT 'in_use, declared_broken, reserved, ad-print, inused_need_reprint, lost',
  `position` varchar(10) DEFAULT NULL COMMENT 'front / rear',
  `num_consommable` varchar(30) DEFAULT NULL COMMENT 'N° du film',
  `num_bobine` varchar(50) DEFAULT NULL COMMENT 'N° de la bobine',
  `site_id_emuci` varchar(20) DEFAULT NULL COMMENT 'Id site dans EMUCI',
  `site_nom_emuci` varchar(100) DEFAULT NULL COMMENT 'Nom site dans EMUCI',
  `site_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK vers sites DigiStock',
  `importe_par` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Import quotidien OptoPlate — plaques posées';

-- --------------------------------------------------------

--
-- Structure de la table `import_optotrace`
--

CREATE TABLE `import_optotrace` (
  `id` int(10) UNSIGNED NOT NULL,
  `import_session_id` varchar(36) NOT NULL,
  `date_import` date NOT NULL,
  `plate_number` varchar(30) NOT NULL COMMENT 'Numéro de plaque',
  `vin` varchar(20) DEFAULT NULL,
  `case_number` varchar(30) DEFAULT NULL COMMENT 'Numéro de dossier',
  `category` varchar(60) DEFAULT NULL COMMENT 'VOITURE PARTICULIERE, CAMION...',
  `site_nom_emuci` varchar(100) DEFAULT NULL,
  `site_id` int(10) UNSIGNED DEFAULT NULL,
  `installation_date` datetime DEFAULT NULL,
  `is_last` tinyint(1) DEFAULT 0,
  `is_deleted` tinyint(1) DEFAULT 0,
  `importe_par` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Import quotidien OptoTrace — registre plaques/véhicules';

-- --------------------------------------------------------

--
-- Structure de la table `import_sessions_emuci`
--

CREATE TABLE `import_sessions_emuci` (
  `id` varchar(36) NOT NULL,
  `date_import` date NOT NULL,
  `type_import` enum('optoplate','optotrace','les_deux') NOT NULL,
  `nb_lignes_optoplate` int(11) DEFAULT 0,
  `nb_lignes_optotrace` int(11) DEFAULT 0,
  `nb_erreurs` int(11) DEFAULT 0,
  `statut` enum('en_cours','termine','erreur') DEFAULT 'en_cours',
  `importe_par` int(10) UNSIGNED NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sessions d import EMUCI';

-- --------------------------------------------------------

--
-- Structure de la table `interventions_maintenance`
--

CREATE TABLE `interventions_maintenance` (
  `id` int(10) UNSIGNED NOT NULL,
  `technicien_id` int(10) UNSIGNED NOT NULL COMMENT 'Utilisateur maintenance_info',
  `site_id` int(10) UNSIGNED NOT NULL,
  `equipement_id` int(10) UNSIGNED DEFAULT NULL,
  `date_intervention` date NOT NULL,
  `type_action` enum('maintenance_preventive','maintenance_corrective','installation','remplacement','diagnostic','formation','autre') NOT NULL DEFAULT 'maintenance_corrective',
  `description` text NOT NULL COMMENT 'Description de l action effectuée',
  `probleme_signale` text DEFAULT NULL COMMENT 'Problème constaté avant intervention',
  `solution_apportee` text DEFAULT NULL COMMENT 'Solution mise en place',
  `statut_apres` enum('resolu','partiel','en_attente','escalade') NOT NULL DEFAULT 'resolu',
  `duree_minutes` int(10) UNSIGNED DEFAULT NULL COMMENT 'Durée de l intervention en minutes',
  `pieces_changees` text DEFAULT NULL COMMENT 'Pièces ou équipements remplacés',
  `rapport_fichier` varchar(255) DEFAULT NULL COMMENT 'Fichier rapport signé (PDF/image)',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `interventions_maintenance`
--

INSERT INTO `interventions_maintenance` (`id`, `technicien_id`, `site_id`, `equipement_id`, `date_intervention`, `type_action`, `description`, `probleme_signale`, `solution_apportee`, `statut_apres`, `duree_minutes`, `pieces_changees`, `rapport_fichier`, `created_at`, `updated_at`) VALUES
(1, 6, 1, 9, '2026-04-19', 'installation', 'jjàohkmlkjhgf', 'mlkuytdvbn,;', 'oè-resxcvbjkl', 'en_attente', 60, 'miuyfcvbn', 'rapport_int_20260419_20260419_092658_18e564cf.pdf', '2026-04-19 09:26:58', '2026-04-19 09:26:58'),
(2, 6, 3, 8, '2026-04-19', 'diagnostic', '$^poiuytdfgvbn,;:', '$pç_è-rdfghjklmlnbvbn,;', 'pç_è-(edfghjk', 'resolu', NULL, 'oiuytfv', 'rapport_int_20260419_20260419_092742_92b3fc16.pdf', '2026-04-19 09:27:42', '2026-04-19 09:27:42'),
(3, 6, 3, 11, '2026-04-19', 'remplacement', 'ç_è-redfghjklm', 'mlkjhgfdswxcvbn', 'azertyuio', 'partiel', 30, 'nhgrttyuio', 'rapport_int_20260419_20260419_200041_2f99525c.pdf', '2026-04-19 20:00:41', '2026-04-19 20:00:41'),
(4, 6, 1, 6, '2026-04-20', 'diagnostic', 'poiuytrsdfghjklm', 'oiuytrsxcvbn,;:', 'poiuytrdcvbn,;', 'partiel', 30, 'SOURIS', 'rapport_int_20260420_20260420_085744_57cad487.pdf', '2026-04-20 08:57:44', '2026-04-20 08:57:44'),
(5, 3, 2, 11, '2026-04-20', 'formation', 'azertyuiop', '^qsdfghjklmù', 'qsdfghjklmù', 'resolu', 20, 'ecran', 'rapport_int_20260420_20260420_163435_96614f22.pdf', '2026-04-20 16:34:35', '2026-04-20 16:34:35');

-- --------------------------------------------------------

--
-- Structure de la table `inventaires_bobines`
--

CREATE TABLE `inventaires_bobines` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL = inventaire global',
  `date_inventaire` date NOT NULL,
  `statut` enum('brouillon','valide','annule') NOT NULL DEFAULT 'brouillon',
  `nb_bobines` int(10) UNSIGNED DEFAULT 0,
  `nb_ecarts` int(10) UNSIGNED DEFAULT 0,
  `notes` text DEFAULT NULL,
  `cree_par` int(10) UNSIGNED DEFAULT NULL,
  `valide_par` int(10) UNSIGNED DEFAULT NULL,
  `valide_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `type_inventaire` enum('journalier','mensuel') NOT NULL DEFAULT 'journalier' COMMENT 'Journalier = suivi quotidien, Mensuel = bilan de fin de mois',
  `total_films_systeme` int(11) DEFAULT 0 COMMENT 'Total films stock système au moment de l inventaire',
  `total_films_physique` int(11) DEFAULT 0 COMMENT 'Total films comptés physiquement',
  `total_films_emuci` int(11) DEFAULT 0 COMMENT 'Total films déduits selon EMUCI (point contrôleur)',
  `ecart_digistock_emuci` int(11) DEFAULT 0 COMMENT 'Écart entre DigiStock et EMUCI'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `inventaires_bobines`
--

INSERT INTO `inventaires_bobines` (`id`, `site_id`, `date_inventaire`, `statut`, `nb_bobines`, `nb_ecarts`, `notes`, `cree_par`, `valide_par`, `valide_at`, `created_at`, `type_inventaire`, `total_films_systeme`, `total_films_physique`, `total_films_emuci`, `ecart_digistock_emuci`) VALUES
(1, 1, '2026-04-19', 'valide', 3, 2, 'OIJN', 3, 3, '2026-04-19 22:03:17', '2026-04-19 22:02:13', 'journalier', 0, 0, 0, 0),
(2, 1, '2026-04-19', 'brouillon', 3, 0, 'hrezsdfn', 3, NULL, NULL, '2026-04-19 23:18:22', 'journalier', 0, 0, 0, 0),
(3, 1, '2026-04-20', 'valide', 3, 1, 'lkjhg', 3, 3, '2026-04-20 05:48:01', '2026-04-20 05:47:08', 'journalier', 0, 0, 0, 0),
(4, 1, '2026-04-20', 'valide', 3, 2, '', 3, 3, '2026-04-20 06:17:06', '2026-04-20 06:15:31', 'journalier', 0, 0, 0, 0),
(5, 1, '2026-04-20', 'brouillon', 4, 0, 'ertyui', 3, NULL, NULL, '2026-04-20 14:36:10', 'journalier', 0, 0, 0, 0),
(6, 2, '2026-04-20', 'valide', 1, 1, 'DFGHJKIOL', 3, 3, '2026-04-20 14:43:04', '2026-04-20 14:42:45', 'journalier', 0, 0, 0, 0),
(7, NULL, '2026-04-20', 'brouillon', 6, 0, 'DFGHJKL', 3, NULL, NULL, '2026-04-20 15:51:25', 'journalier', 0, 0, 0, 0),
(8, 1, '2026-04-21', 'brouillon', 5, 0, 'dfghjk', 3, NULL, NULL, '2026-04-21 12:37:36', 'journalier', 0, 0, 0, 0),
(9, 1, '2026-04-24', 'brouillon', 5, 0, '', 3, NULL, NULL, '2026-04-24 14:12:08', 'journalier', 0, 0, 0, 0),
(10, 1, '2026-04-27', 'valide', 5, 3, '', 3, 3, '2026-04-27 15:13:41', '2026-04-27 15:09:41', 'journalier', 0, 0, 0, 0),
(11, 1, '2026-04-27', 'valide', 5, 1, '', 5, 5, '2026-04-27 15:43:12', '2026-04-27 15:41:42', 'journalier', 0, 0, 0, 0),
(12, 1, '2026-04-01', 'valide', 5, 0, '', 3, 3, '2026-04-30 18:22:48', '2026-04-30 18:20:26', 'mensuel', 136, 0, 0, -136);

-- --------------------------------------------------------

--
-- Structure de la table `inventaire_details_bobines`
--

CREATE TABLE `inventaire_details_bobines` (
  `id` int(10) UNSIGNED NOT NULL,
  `inventaire_id` int(10) UNSIGNED NOT NULL,
  `bobine_id` int(10) UNSIGNED NOT NULL,
  `stock_systeme` int(10) UNSIGNED NOT NULL COMMENT 'Stock système au moment de l inventaire',
  `stock_physique` int(10) UNSIGNED NOT NULL COMMENT 'Stock compté physiquement',
  `ecart` int(11) NOT NULL COMMENT 'physique - système (négatif = manque)',
  `conso_quotidienne_moy` decimal(8,2) DEFAULT NULL COMMENT 'Consommation journalière moyenne calculée sur les 30 derniers jours',
  `jours_restants_systeme` int(11) DEFAULT NULL COMMENT 'Jours avant épuisement selon stock système',
  `jours_restants_physique` int(11) DEFAULT NULL COMMENT 'Jours avant épuisement selon stock physique',
  `date_epuisement_estime` date DEFAULT NULL COMMENT 'Date estimée d épuisement selon stock physique',
  `notes` text DEFAULT NULL,
  `qte_temps_reel` int(11) DEFAULT NULL COMMENT 'Stock temps réel au moment de l inventaire (mis à jour en continu)',
  `ecart_connu_avant` int(11) DEFAULT NULL COMMENT 'Écart connu AVANT cet inventaire (depuis dernier inventaire validé)',
  `films_emuci_jour` int(11) DEFAULT NULL COMMENT 'Films déduits selon point EMUCI du jour',
  `ecart_emuci_digi` int(11) DEFAULT NULL COMMENT 'Écart EMUCI vs DigiStock pour ce jour'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `inventaire_details_bobines`
--

INSERT INTO `inventaire_details_bobines` (`id`, `inventaire_id`, `bobine_id`, `stock_systeme`, `stock_physique`, `ecart`, `conso_quotidienne_moy`, `jours_restants_systeme`, `jours_restants_physique`, `date_epuisement_estime`, `notes`, `qte_temps_reel`, `ecart_connu_avant`, `films_emuci_jour`, `ecart_emuci_digi`) VALUES
(1, 1, 1, 436, 445, 9, 8.00, 55, 56, '2026-06-14', '', NULL, NULL, NULL, NULL),
(2, 1, 2, 436, 436, 0, 0.00, NULL, NULL, NULL, '', NULL, NULL, NULL, NULL),
(3, 1, 3, 500, 488, -12, 0.00, NULL, NULL, NULL, '', NULL, NULL, NULL, NULL),
(4, 2, 1, 445, 0, 0, 8.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 2, 2, 436, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 2, 3, 488, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 3, 1, 445, 444, -1, 8.00, 56, 56, '2026-06-15', '', NULL, NULL, NULL, NULL),
(8, 3, 2, 436, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 3, 3, 488, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, 4, 1, 444, 440, -4, 8.00, NULL, 55, '2026-06-14', '', NULL, NULL, NULL, NULL),
(11, 4, 2, 436, 436, 0, 0.00, NULL, NULL, NULL, '', NULL, NULL, NULL, NULL),
(12, 4, 3, 488, 495, 7, 0.00, NULL, NULL, NULL, '', NULL, NULL, NULL, NULL),
(13, 5, 1, 395, 392, -3, 53.00, 8, 8, '2026-04-29', '', NULL, NULL, NULL, NULL),
(14, 5, 2, 436, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 5, 5, 500, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(16, 5, 3, 495, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, 6, 4, 3, 2, -1, 452.00, NULL, 1, '2026-04-21', '', NULL, NULL, NULL, NULL),
(18, 7, 1, 395, 0, 0, 53.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 7, 6, 500, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, 7, 2, 386, 0, 0, 50.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(21, 7, 5, 500, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, 7, 3, 495, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, 7, 4, 2, 0, 0, 452.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(24, 8, 1, 395, 0, 0, 26.50, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(25, 8, 6, 500, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(26, 8, 2, 386, 0, 0, 50.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(27, 8, 5, 500, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(28, 8, 3, 495, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(29, 9, 1, 395, 0, 0, 10.60, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(30, 9, 6, 500, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(31, 9, 2, 386, 0, 0, 12.50, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(32, 9, 5, 500, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(33, 9, 3, 495, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(34, 10, 1, 395, 395, 0, 6.63, 60, 60, '2026-06-26', '', NULL, NULL, NULL, NULL),
(35, 10, 6, 500, 498, -2, 0.00, NULL, NULL, NULL, '', NULL, NULL, NULL, NULL),
(36, 10, 2, 386, 388, 2, 7.14, 55, 55, '2026-06-21', '', NULL, NULL, NULL, NULL),
(37, 10, 5, 500, 500, 0, 0.00, NULL, NULL, NULL, '', NULL, NULL, NULL, NULL),
(38, 10, 3, 495, 500, 5, 0.00, NULL, NULL, NULL, '', NULL, NULL, NULL, NULL),
(39, 11, 1, 395, 364, -31, 6.63, 60, 55, '2026-06-21', '', NULL, NULL, NULL, NULL),
(40, 11, 6, 498, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(41, 11, 2, 388, 0, 0, 7.14, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(42, 11, 5, 500, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(43, 11, 3, 500, 0, 0, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(44, 12, 1, 364, 0, 0, NULL, NULL, NULL, NULL, NULL, 364, 0, NULL, NULL),
(45, 12, 2, 388, 0, 0, NULL, NULL, NULL, NULL, NULL, 388, -6, NULL, NULL),
(46, 12, 3, 500, 0, 0, NULL, NULL, NULL, NULL, NULL, 500, 23, NULL, NULL),
(47, 12, 5, 500, 0, 0, NULL, NULL, NULL, NULL, NULL, 500, 0, NULL, NULL),
(48, 12, 6, 498, 0, 0, NULL, NULL, NULL, NULL, NULL, 498, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `litige_messages`
--

CREATE TABLE `litige_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `reception_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `litige_messages`
--

INSERT INTO `litige_messages` (`id`, `reception_id`, `user_id`, `message`, `created_at`) VALUES
(1, 4, 7, '⚠️ Litige signalé par test cordo : format non correct', '2026-04-19 20:10:48'),
(2, 4, 3, '🔄 Litige traité par Axelle DON : commande renvoyée. jgvbn,', '2026-04-20 06:34:58'),
(3, 5, 7, '⚠️ Litige signalé par test cordo : nombre pas exacte', '2026-04-21 12:48:28');

-- --------------------------------------------------------

--
-- Structure de la table `livraisons_consommables`
--

CREATE TABLE `livraisons_consommables` (
  `id` int(10) UNSIGNED NOT NULL,
  `consommable_id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `type_mouvement` enum('distribution','retour') DEFAULT 'distribution',
  `quantite` decimal(10,2) NOT NULL,
  `prix_unitaire` decimal(12,2) DEFAULT 0.00,
  `prix_total` decimal(12,2) DEFAULT 0.00,
  `date_livraison` date NOT NULL,
  `bon_livraison` varchar(100) DEFAULT NULL,
  `fichier_bl` varchar(255) DEFAULT NULL COMMENT 'Fichier bon de livraison (PDF/image) — obligatoire',
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `livraisons_consommables`
--

INSERT INTO `livraisons_consommables` (`id`, `consommable_id`, `site_id`, `type_mouvement`, `quantite`, `prix_unitaire`, `prix_total`, `date_livraison`, `bon_livraison`, `fichier_bl`, `notes`, `created_by`, `created_at`) VALUES
(1, 2, 5, 'distribution', 1.00, 0.00, 0.00, '2026-04-02', 'BL-02-04-2026-1', NULL, 'Approvisionnement', 3, '2026-04-02 14:18:16'),
(2, 5, 3, 'distribution', 2.00, 2000.00, 4000.00, '2026-04-03', 'BL-2026-04-03', NULL, '', 3, '2026-04-03 04:38:56'),
(3, 6, 3, 'distribution', 5.00, 1500.00, 7500.00, '2026-04-03', '', NULL, '', 3, '2026-04-03 04:57:38'),
(4, 5, 7, 'distribution', 1.00, 2000.00, 2000.00, '2026-04-03', '', NULL, '', 3, '2026-04-03 05:02:41'),
(5, 3, 1, 'distribution', 5.00, 4500.00, 22500.00, '2026-04-15', '', NULL, '', 3, '2026-04-15 22:38:28'),
(6, 1, 3, 'distribution', 4.00, 562.00, 2248.00, '2026-04-15', '', NULL, '', 3, '2026-04-15 22:39:31'),
(9, 4, 1, 'distribution', 1.00, 1325.00, 1325.00, '2026-04-20', 'BS2026-04-20', 'bl_conso_4_site_1_20260420_122141_127e0da0.pdf', '', 8, '2026-04-20 12:21:41'),
(10, 3, 1, 'distribution', 200.00, 4500.00, 900000.00, '2026-04-27', '', 'bl_conso_3_site_1_20260427_143527_f75426e8.pdf', '', 8, '2026-04-27 14:35:27'),
(11, 1, 1, 'distribution', 2.00, 562.00, 1124.00, '2026-05-28', '', 'bl_conso_1_site_1_20260528_101720_0dc3afb2.pdf', '', 3, '2026-05-28 10:17:20');

-- --------------------------------------------------------

--
-- Structure de la table `mouvements_bobines`
--

CREATE TABLE `mouvements_bobines` (
  `id` int(10) UNSIGNED NOT NULL,
  `bobine_id` int(10) UNSIGNED NOT NULL,
  `type` enum('entree','sortie','ajustement_inventaire','transfert') NOT NULL,
  `quantite` int(11) NOT NULL COMMENT 'Positif = entrée, Négatif = sortie',
  `stock_avant` int(10) UNSIGNED NOT NULL,
  `stock_apres` int(10) UNSIGNED NOT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `ref_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID consommation ou inventaire source',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `mouvements_bobines`
--

INSERT INTO `mouvements_bobines` (`id`, `bobine_id`, `type`, `quantite`, `stock_avant`, `stock_apres`, `motif`, `ref_id`, `created_by`, `created_at`) VALUES
(1, 1, 'sortie', -8, 444, 436, 'Consommation du 2026-04-19', 1, 3, '2026-04-19 20:59:57'),
(2, 4, 'sortie', -452, 455, 3, 'Consommation du 2026-04-19', 2, 3, '2026-04-19 21:00:37'),
(3, 1, 'ajustement_inventaire', 9, 436, 445, 'Inventaire #1 — ajustement', 1, 3, '2026-04-19 22:03:17'),
(4, 3, 'ajustement_inventaire', -12, 500, 488, 'Inventaire #1 — ajustement', 1, 3, '2026-04-19 22:03:17'),
(5, 1, 'ajustement_inventaire', -1, 445, 444, 'Inventaire #3 — ajustement', 3, 3, '2026-04-20 05:48:01'),
(6, 1, 'ajustement_inventaire', -4, 444, 440, 'Inventaire #4', 4, 3, '2026-04-20 06:17:06'),
(7, 3, 'ajustement_inventaire', 7, 488, 495, 'Inventaire #4', 4, 3, '2026-04-20 06:17:06'),
(8, 1, 'sortie', -45, 440, 395, 'Consommation du 2026-04-20', 3, 3, '2026-04-20 06:44:31'),
(9, 5, 'entree', 500, 0, 500, 'Création bobine H00000PKHG45', NULL, 3, '2026-04-20 06:45:08'),
(10, 4, 'ajustement_inventaire', -1, 3, 2, 'Inventaire #6', 6, 3, '2026-04-20 14:43:04'),
(11, 2, 'sortie', -50, 436, 386, 'Consommation du 2026-04-20', 4, 3, '2026-04-20 15:49:32'),
(12, 6, 'entree', 500, 0, 500, 'Création bobine HEERTY00000052', NULL, 3, '2026-04-20 15:50:06'),
(13, 2, 'ajustement_inventaire', 2, 386, 388, 'Inventaire #10', 10, 3, '2026-04-27 15:13:41'),
(14, 3, 'ajustement_inventaire', 5, 495, 500, 'Inventaire #10', 10, 3, '2026-04-27 15:13:41'),
(15, 6, 'ajustement_inventaire', -2, 500, 498, 'Inventaire #10', 10, 3, '2026-04-27 15:13:41'),
(16, 1, 'ajustement_inventaire', -31, 395, 364, 'Inventaire #11', 11, 5, '2026-04-27 15:43:12');

-- --------------------------------------------------------

--
-- Structure de la table `mouvements_equipements`
--

CREATE TABLE `mouvements_equipements` (
  `id` int(10) UNSIGNED NOT NULL,
  `equipement_id` int(10) UNSIGNED NOT NULL,
  `type` enum('entree','sortie','transfert','reforme','maintenance') NOT NULL,
  `site_source_id` int(10) UNSIGNED DEFAULT NULL,
  `site_dest_id` int(10) UNSIGNED DEFAULT NULL,
  `user_dest_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `fichier_bl` varchar(255) DEFAULT NULL COMMENT 'Fichier bon de livraison (PDF/image) — obligatoire pour sorties/transferts',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `mouvements_equipements`
--

INSERT INTO `mouvements_equipements` (`id`, `equipement_id`, `type`, `site_source_id`, `site_dest_id`, `user_dest_id`, `notes`, `fichier_bl`, `created_by`, `created_at`) VALUES
(1, 1, 'entree', NULL, 3, NULL, NULL, NULL, 3, '2026-04-02 06:13:31'),
(2, 1, 'transfert', 3, 1, NULL, 'Transfert sur le site de CFAO parce l\'imprimante pricipale de CFAO est en panne', NULL, 3, '2026-04-02 09:08:53'),
(3, 6, 'entree', NULL, 3, NULL, NULL, NULL, 3, '2026-04-02 10:56:16'),
(4, 7, 'entree', NULL, 1, NULL, NULL, NULL, 3, '2026-04-02 10:57:27'),
(5, 8, 'entree', NULL, 1, NULL, NULL, NULL, 3, '2026-04-02 10:58:28'),
(6, 9, 'entree', NULL, 5, NULL, NULL, NULL, 3, '2026-04-02 10:59:35'),
(7, 13, 'reforme', NULL, NULL, NULL, 'Réformé via interface', NULL, 3, '2026-04-02 15:35:26'),
(8, 16, 'reforme', NULL, NULL, NULL, 'Réformé via interface', NULL, 3, '2026-04-03 04:24:15'),
(9, 17, 'reforme', NULL, NULL, NULL, 'Réformé via interface', NULL, 3, '2026-04-03 04:28:06'),
(10, 19, 'entree', NULL, 9, NULL, NULL, NULL, 3, '2026-04-03 04:48:28'),
(11, 20, 'transfert', NULL, 8, NULL, NULL, NULL, 3, '2026-04-03 05:07:05'),
(12, 20, 'transfert', 8, 7, NULL, 'OUVERTURE DU SITE', NULL, 3, '2026-04-03 05:09:26'),
(13, 7, 'maintenance', 1, 9, NULL, '', NULL, 3, '2026-04-03 05:11:00'),
(14, 21, 'entree', NULL, 8, NULL, NULL, NULL, 3, '2026-04-03 05:11:46'),
(15, 21, 'transfert', 8, 4, NULL, '', NULL, 3, '2026-04-03 05:12:24'),
(16, 20, 'transfert', 7, 3, NULL, '', NULL, 3, '2026-04-03 05:12:49'),
(17, 22, 'entree', NULL, 8, NULL, NULL, NULL, 3, '2026-04-15 09:30:04'),
(18, 22, 'transfert', 8, 1, NULL, 'POUR USAGE', NULL, 3, '2026-04-15 09:31:37'),
(19, 20, 'transfert', 3, 5, NULL, '', NULL, 3, '2026-04-15 11:43:02'),
(20, 23, 'entree', NULL, 5, NULL, NULL, NULL, 3, '2026-04-19 07:55:21'),
(21, 9, 'transfert', 5, 2, NULL, '', 'bl_equip_9_20260419_075731_4b2615e9.pdf', 3, '2026-04-19 07:57:31'),
(22, 20, 'sortie', 5, NULL, NULL, 'NON UTILIS2 SUR LE SITE', NULL, 3, '2026-04-19 07:58:27'),
(23, 24, 'entree', NULL, 9, NULL, NULL, NULL, 6, '2026-04-19 08:17:35'),
(24, 23, 'transfert', 5, 1, 7, 'ùml', 'bl_equip_23_20260419_082711_e0c6a57b.pdf', 3, '2026-04-19 08:27:11'),
(25, 20, 'transfert', NULL, 1, 7, 'ml,pàçuy', 'bl_equip_20_20260419_083021_6f88163d.pdf', 3, '2026-04-19 08:30:21'),
(26, 22, 'transfert', 1, 1, 7, 'jhgfd', 'bl_equip_22_20260419_092051_f0f06df8.pdf', 3, '2026-04-19 09:20:51'),
(27, 21, 'transfert', 4, 1, 7, 'SDFGHJKLM', 'bl_equip_21_20260420_122241_f3bb34b4.pdf', 8, '2026-04-20 12:22:41');

-- --------------------------------------------------------

--
-- Structure de la table `nomenclatures`
--

CREATE TABLE `nomenclatures` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(10) NOT NULL,
  `categorie` enum('informatique','operationnel') NOT NULL DEFAULT 'informatique' COMMENT 'Catégorie de la nomenclature',
  `libelle` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `duree_vie_mois` int(10) UNSIGNED DEFAULT NULL,
  `seuil_alerte` int(10) UNSIGNED DEFAULT 5,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `nomenclatures`
--

INSERT INTO `nomenclatures` (`id`, `code`, `categorie`, `libelle`, `description`, `duree_vie_mois`, `seuil_alerte`, `created_at`) VALUES
(1, 'CLV', 'informatique', 'Clavier', 'Les claviers des desktop', 24, 2, '2026-04-02 06:11:05'),
(2, 'IMP', 'informatique', 'Imprimantes', '', 24, 5, '2026-04-02 06:11:53'),
(3, 'SR', 'informatique', 'Souris', '', 24, 5, '2026-04-02 09:16:25'),
(4, 'UNT', 'informatique', 'Unité centrale', '', 24, 5, '2026-04-02 09:17:00'),
(5, 'CLD', 'informatique', 'Client lourd', '', 24, 5, '2026-04-02 09:17:31'),
(6, 'ECR', 'informatique', 'Ecran', '', 24, 5, '2026-04-02 09:18:01'),
(7, 'OND', 'informatique', 'Onduleurs', '', 12, 5, '2026-04-02 09:18:21'),
(8, 'LPT', 'informatique', 'Laptop', '', 36, 5, '2026-04-02 09:19:11'),
(9, 'PRS', 'informatique', 'Perceuse', '', 36, 5, '2026-04-02 09:19:32');

-- --------------------------------------------------------

--
-- Structure de la table `nomenclature_liens`
--

CREATE TABLE `nomenclature_liens` (
  `id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED NOT NULL,
  `enfant_id` int(10) UNSIGNED NOT NULL,
  `quantite` int(10) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `type` enum('fin_cycle','stock_bas','alerte_conso','info') NOT NULL,
  `titre` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `lien` varchar(255) DEFAULT NULL,
  `lu` tinyint(1) DEFAULT 0,
  `email_envoye` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `titre`, `message`, `lien`, `lu`, `email_envoye`, `created_at`) VALUES
(1, NULL, 'stock_bas', '⚠️ Stock bas — Sucre', 'Le stock de <strong>Sucre</strong> est à 1 boite(s) (seuil : 3.00).', '/pages/consommables.php', 1, 0, '2026-04-02 14:18:16'),
(2, NULL, 'stock_bas', '⚠️ Stock bas — Papier toilette', 'Le stock de <strong>Papier toilette</strong> est à 2 paquet(s) (seuil : 5.00).', '/pages/consommables.php', 1, 0, '2026-04-03 04:38:56'),
(3, NULL, 'stock_bas', '⚠️ Stock bas — Papier toilette', 'Stock restant : <strong>1 paquet(s)</strong> (seuil : 5.00).', '/pages/consommables.php', 1, 0, '2026-04-03 05:02:41'),
(4, 1, 'alerte_conso', '⚠️ Litige réception — GU ABIDJAN : SR-EMUCI-0003', 'Le coordinateur du site <strong>GU ABIDJAN</strong> a signalé un litige sur la réception de <strong>SR-EMUCI-0003</strong>.<br><br><strong>Motif :</strong> format non correct<br><br>Veuillez traiter ce litige et renvoyer la commande si nécessaire.', '/pages/reception_site.php?statut=litige', 0, 0, '2026-04-19 20:10:48'),
(5, 3, 'alerte_conso', '⚠️ Litige réception — GU ABIDJAN : SR-EMUCI-0003', 'Le coordinateur du site <strong>GU ABIDJAN</strong> a signalé un litige sur la réception de <strong>SR-EMUCI-0003</strong>.<br><br><strong>Motif :</strong> format non correct<br><br>Veuillez traiter ce litige et renvoyer la commande si nécessaire.', '/pages/reception_site.php?statut=litige', 0, 0, '2026-04-19 20:10:48'),
(6, 8, 'alerte_conso', '⚠️ Litige réception — GU ABIDJAN : SR-EMUCI-0003', 'Le coordinateur du site <strong>GU ABIDJAN</strong> a signalé un litige sur la réception de <strong>SR-EMUCI-0003</strong>.<br><br><strong>Motif :</strong> format non correct<br><br>Veuillez traiter ce litige et renvoyer la commande si nécessaire.', '/pages/reception_site.php?statut=litige', 1, 0, '2026-04-19 20:10:48'),
(7, 7, 'info', '🔄 Commande renvoyée — GU ABIDJAN', '🔄 Litige traité par Axelle DON : commande renvoyée. jgvbn,', '/pages/reception_site.php', 0, 0, '2026-04-20 06:34:58'),
(8, 1, 'alerte_conso', '⚠️ Litige réception — GU ABIDJAN : LIPTON', 'Le coordinateur du site <strong>GU ABIDJAN</strong> a signalé un litige sur la réception de <strong>LIPTON</strong>.<br><br><strong>Motif :</strong> nombre pas exacte<br><br>Veuillez traiter ce litige et renvoyer la commande si nécessaire.', '/pages/reception_site.php?statut=litige', 0, 0, '2026-04-21 12:48:28'),
(9, 3, 'alerte_conso', '⚠️ Litige réception — GU ABIDJAN : LIPTON', 'Le coordinateur du site <strong>GU ABIDJAN</strong> a signalé un litige sur la réception de <strong>LIPTON</strong>.<br><br><strong>Motif :</strong> nombre pas exacte<br><br>Veuillez traiter ce litige et renvoyer la commande si nécessaire.', '/pages/reception_site.php?statut=litige', 1, 0, '2026-04-21 12:48:28'),
(10, 8, 'alerte_conso', '⚠️ Litige réception — GU ABIDJAN : LIPTON', 'Le coordinateur du site <strong>GU ABIDJAN</strong> a signalé un litige sur la réception de <strong>LIPTON</strong>.<br><br><strong>Motif :</strong> nombre pas exacte<br><br>Veuillez traiter ce litige et renvoyer la commande si nécessaire.', '/pages/reception_site.php?statut=litige', 0, 0, '2026-04-21 12:48:28');

-- --------------------------------------------------------

--
-- Structure de la table `op_bobines`
--

CREATE TABLE `op_bobines` (
  `id` int(10) UNSIGNED NOT NULL,
  `numero` varchar(50) NOT NULL COMMENT 'Numéro unique ex: A001-XK7-2024',
  `type_code` varchar(10) NOT NULL COMMENT 'A001..D006',
  `serie` char(1) NOT NULL COMMENT 'A,B,C,D',
  `type_vehicule_id` int(10) UNSIGNED DEFAULT NULL,
  `films_total` int(10) UNSIGNED NOT NULL DEFAULT 500,
  `films_utilises` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `films_endommages` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `films_restants` int(10) UNSIGNED NOT NULL DEFAULT 500,
  `site_id` int(10) UNSIGNED DEFAULT NULL,
  `statut` enum('en_stock','en_cours','retiree','epuisee','perdue') NOT NULL DEFAULT 'en_stock',
  `date_ouverture` date DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `qte_initiale` int(10) UNSIGNED NOT NULL DEFAULT 500 COMMENT 'Quantité initiale en rouleaux (toujours 500)',
  `stock_systeme` int(10) UNSIGNED NOT NULL DEFAULT 500 COMMENT 'Stock calculé par le système (basé sur les consommations saisies)',
  `stock_physique` int(10) UNSIGNED DEFAULT NULL COMMENT 'Dernier stock physique constaté lors d un inventaire',
  `dernier_inventaire_id` int(10) UNSIGNED DEFAULT NULL,
  `date_creation` date DEFAULT curdate(),
  `format` varchar(50) DEFAULT NULL COMMENT 'Format de la bobine (ex: A4, A3, rouleau 80m...)',
  `notes_perte` text DEFAULT NULL COMMENT 'Motif de la perte'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `op_bobines`
--

INSERT INTO `op_bobines` (`id`, `numero`, `type_code`, `serie`, `type_vehicule_id`, `films_total`, `films_utilises`, `films_endommages`, `films_restants`, `site_id`, `statut`, `date_ouverture`, `created_by`, `created_at`, `qte_initiale`, `stock_systeme`, `stock_physique`, `dernier_inventaire_id`, `date_creation`, `format`, `notes_perte`) VALUES
(1, 'HP000000HG45', 'A001', 'A', 1, 500, 121, 21, 362, 1, 'en_cours', '2026-04-14', NULL, '2026-04-14 15:09:15', 500, 364, NULL, NULL, '2026-04-19', NULL, NULL),
(2, 'HP000000HG23', 'B001', 'B', 2, 500, 95, 14, 386, 1, 'en_cours', '2026-04-14', NULL, '2026-04-14 15:25:42', 500, 388, NULL, NULL, '2026-04-19', NULL, NULL),
(3, 'HP000000HG25', 'C004', 'C', 3, 500, 8, 0, 500, 1, 'en_cours', '2026-04-24', NULL, '2026-04-14 15:26:05', 500, 500, NULL, NULL, '2026-04-19', NULL, NULL),
(4, 'HP000000HG28', 'C006', 'C', 3, 500, 0, 45, 2, 2, 'en_cours', '2026-04-15', NULL, '2026-04-14 15:26:50', 500, 2, NULL, NULL, '2026-04-19', NULL, NULL),
(5, 'H00000PKHG45', 'C002', 'C', 3, 500, 0, 0, 500, 1, 'en_stock', NULL, NULL, '2026-04-20 06:45:08', 500, 500, NULL, NULL, '2026-04-20', 'MOTO', NULL),
(6, 'HEERTY00000052', 'A002', 'A', 1, 500, 32, 0, 498, 1, 'en_cours', '2026-04-21', NULL, '2026-04-20 15:50:06', 500, 498, NULL, NULL, '2026-04-20', 'A04', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `op_films_utilises`
--

CREATE TABLE `op_films_utilises` (
  `id` int(10) UNSIGNED NOT NULL,
  `point_id` int(10) UNSIGNED NOT NULL,
  `bobine_id` int(10) UNSIGNED NOT NULL,
  `type_vehicule_id` int(10) UNSIGNED NOT NULL,
  `films_utilises` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `films_endommages` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `op_films_utilises`
--

INSERT INTO `op_films_utilises` (`id`, `point_id`, `bobine_id`, `type_vehicule_id`, `films_utilises`, `films_endommages`) VALUES
(2, 1, 1, 1, 4, 0),
(3, 1, 2, 2, 4, 0),
(4, 2, 4, 3, 0, 45),
(6, 3, 1, 1, 2, 0),
(7, 4, 1, 1, 22, 2),
(8, 5, 1, 1, 20, 19),
(9, 5, 2, 2, 7, 14),
(10, 6, 1, 1, 10, 0),
(11, 6, 2, 2, 20, 0),
(12, 6, 6, 1, 10, 0),
(13, 7, 1, 1, 2, 0),
(14, 7, 2, 2, 2, 0),
(15, 7, 3, 3, 3, 0),
(16, 7, 6, 1, 22, 0),
(17, 8, 1, 1, 3, 0),
(18, 8, 3, 3, 5, 0),
(20, 9, 1, 1, 8, 0),
(21, 10, 1, 1, 10, 0),
(22, 11, 1, 1, 2, 0),
(23, 11, 2, 2, 2, 0);

-- --------------------------------------------------------

--
-- Structure de la table `op_points_journaliers`
--

CREATE TABLE `op_points_journaliers` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `date_point` date NOT NULL,
  `type_point` enum('point_8h','point_9h','point_13h','point_17h','debut_activite','mi_journee','fin_journee','final','intermediaire') NOT NULL DEFAULT 'point_17h',
  `statut` enum('suivi','brouillon','valide','refuse') NOT NULL DEFAULT 'brouillon',
  `nb_vp` int(10) UNSIGNED DEFAULT 0,
  `nb_camion` int(10) UNSIGNED DEFAULT 0,
  `nb_semi` int(10) UNSIGNED DEFAULT 0,
  `nb_moto` int(10) UNSIGNED DEFAULT 0,
  `total_engins` int(10) UNSIGNED DEFAULT 0,
  `total_plaques` int(10) UNSIGNED DEFAULT 0,
  `moyenne_prod` decimal(6,2) DEFAULT 0.00,
  `rivets_utilises` int(10) UNSIGNED DEFAULT 0,
  `rivets_endommages` int(10) UNSIGNED DEFAULT 0,
  `non_poses_concessionnaires` int(10) UNSIGNED DEFAULT 0,
  `non_poses_usagers` int(10) UNSIGNED DEFAULT 0,
  `nb_heures_travail` decimal(4,1) DEFAULT 8.0,
  `observations` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `validated_by` int(10) UNSIGNED DEFAULT NULL,
  `validated_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `op_points_journaliers`
--

INSERT INTO `op_points_journaliers` (`id`, `site_id`, `date_point`, `type_point`, `statut`, `nb_vp`, `nb_camion`, `nb_semi`, `nb_moto`, `total_engins`, `total_plaques`, `moyenne_prod`, `rivets_utilises`, `rivets_endommages`, `non_poses_concessionnaires`, `non_poses_usagers`, `nb_heures_travail`, `observations`, `created_by`, `validated_by`, `validated_at`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-04-14', 'final', 'valide', 2, 4, 0, 0, 6, 12, 0.80, 24, 0, 0, 0, 8.0, '', 3, 3, '2026-04-15 10:17:01', '2026-04-14 15:30:38', '2026-04-15 10:17:01'),
(2, 2, '2026-04-15', 'intermediaire', 'valide', 4, 4, 4, 8, 20, 28, 2.50, 56, 0, 0, 0, 8.0, '', 3, 5, '2026-04-27 14:50:16', '2026-04-15 10:00:50', '2026-04-27 14:50:16'),
(3, 1, '2026-04-15', 'intermediaire', 'valide', 2, 0, 0, 0, 2, 4, 0.30, 8, 3, 0, 0, 8.0, '', 3, 5, '2026-04-27 14:50:13', '2026-04-15 10:15:58', '2026-04-27 14:50:13'),
(4, 1, '2026-04-15', 'final', 'valide', 1, 10, 0, 0, 11, 22, 1.40, 44, 0, 0, 0, 8.0, '', 3, 5, '2026-04-27 14:50:19', '2026-04-15 11:46:21', '2026-04-27 14:50:19'),
(5, 1, '2026-04-20', 'intermediaire', 'valide', 20, 7, 0, 0, 27, 54, 3.40, 108, 108, 0, 0, 8.0, '', 3, 5, '2026-04-27 14:50:21', '2026-04-20 06:20:20', '2026-04-27 14:50:21'),
(6, 1, '2026-04-21', 'final', 'brouillon', 10, 20, 0, 0, 30, 60, 3.80, 120, 0, 0, 0, 8.0, '', 7, NULL, NULL, '2026-04-21 12:50:04', '2026-04-21 12:50:04'),
(7, 1, '2026-04-24', 'intermediaire', 'brouillon', 2, 2, 3, 2, 9, 13, 1.10, 26, 0, 0, 0, 8.0, '', 7, NULL, NULL, '2026-04-24 13:58:22', '2026-04-24 13:58:22'),
(8, 1, '2026-04-24', 'final', 'brouillon', 3, 0, 5, 1, 9, 12, 1.10, 24, 0, 0, 0, 8.0, '', 7, NULL, NULL, '2026-04-24 18:24:09', '2026-04-24 18:24:09'),
(9, 1, '2026-04-27', 'intermediaire', 'valide', 8, 0, 0, 0, 8, 16, 1.00, 32, 0, 0, 0, 8.0, '', 7, 5, '2026-04-27 14:50:32', '2026-04-27 14:43:14', '2026-04-27 14:50:32'),
(10, 1, '2026-04-27', 'final', 'valide', 10, 0, 0, 0, 10, 20, 1.30, 40, 0, 0, 0, 8.0, '', 7, 5, '2026-04-27 14:54:53', '2026-04-27 14:54:22', '2026-04-27 14:54:53'),
(11, 1, '2026-04-28', 'intermediaire', 'valide', 2, 2, 0, 0, 4, 8, 0.50, 16, 0, 0, 0, 8.0, '', 7, 3, '2026-04-29 22:33:16', '2026-04-28 09:58:11', '2026-04-29 22:33:16');

-- --------------------------------------------------------

--
-- Structure de la table `op_stock_rivets`
--

CREATE TABLE `op_stock_rivets` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `quantite` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `op_stock_rivets`
--

INSERT INTO `op_stock_rivets` (`id`, `site_id`, `quantite`, `updated_at`) VALUES
(1, 1, 171, '2026-04-28 09:58:11'),
(2, 3, 1020, '2026-04-29 22:32:34'),
(6, 5, 500, '2026-04-15 09:59:34'),
(7, 4, 500, '2026-04-15 09:59:41'),
(8, 2, 944, '2026-04-15 10:00:50');

-- --------------------------------------------------------

--
-- Structure de la table `op_types_vehicule`
--

CREATE TABLE `op_types_vehicule` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `libelle` varchar(100) NOT NULL,
  `nb_plaques` tinyint(3) UNSIGNED NOT NULL DEFAULT 2,
  `nb_rivets` tinyint(3) UNSIGNED NOT NULL DEFAULT 4,
  `serie_bobine` char(1) NOT NULL COMMENT 'A=VP, B=Camion, C=Semi, D=Moto',
  `ordre` tinyint(3) UNSIGNED DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `op_types_vehicule`
--

INSERT INTO `op_types_vehicule` (`id`, `code`, `libelle`, `nb_plaques`, `nb_rivets`, `serie_bobine`, `ordre`) VALUES
(1, 'VP', 'Véhicule Particulier', 2, 4, 'A', 1),
(2, 'CAM', 'Camion', 2, 4, 'B', 2),
(3, 'SEMI', 'Semi-remorque', 1, 2, 'C', 3),
(4, 'MOTO', 'Moto', 1, 2, 'D', 4);

-- --------------------------------------------------------

--
-- Structure de la table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `module` varchar(60) NOT NULL,
  `can_create` tinyint(1) DEFAULT 0,
  `can_read` tinyint(1) DEFAULT 1,
  `can_update` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `can_export` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `permissions`
--

INSERT INTO `permissions` (`id`, `role_id`, `module`, `can_create`, `can_read`, `can_update`, `can_delete`, `can_export`) VALUES
(462, 2, 'equipements', 1, 1, 1, 1, 1),
(463, 1, 'equipements', 1, 1, 1, 1, 1),
(464, 2, 'sites', 1, 1, 1, 1, 1),
(465, 1, 'sites', 1, 1, 1, 1, 1),
(466, 2, 'affectations', 1, 1, 1, 1, 1),
(467, 1, 'affectations', 1, 1, 1, 1, 1),
(468, 2, 'receptions', 1, 1, 1, 1, 1),
(469, 1, 'receptions', 1, 1, 1, 1, 1),
(470, 2, 'bobines', 1, 1, 1, 1, 1),
(471, 1, 'bobines', 1, 1, 1, 1, 1),
(472, 2, 'inventaire_bobines', 1, 1, 1, 1, 1),
(473, 1, 'inventaire_bobines', 1, 1, 1, 1, 1),
(474, 2, 'consommables', 1, 1, 1, 1, 1),
(475, 1, 'consommables', 1, 1, 1, 1, 1),
(476, 2, 'rapports', 1, 1, 1, 1, 1),
(477, 1, 'rapports', 1, 1, 1, 1, 1),
(478, 2, 'interventions', 1, 1, 1, 1, 1),
(479, 1, 'interventions', 1, 1, 1, 1, 1),
(480, 2, 'point_emuci', 1, 1, 1, 1, 1),
(481, 1, 'point_emuci', 1, 1, 1, 1, 1),
(482, 2, 'import_emuci', 1, 1, 1, 1, 1),
(483, 1, 'import_emuci', 1, 1, 1, 1, 1),
(484, 2, 'nomenclatures', 1, 1, 1, 1, 1),
(485, 1, 'nomenclatures', 1, 1, 1, 1, 1),
(486, 2, 'users', 1, 1, 1, 1, 1),
(487, 1, 'users', 1, 1, 1, 1, 1),
(488, 2, 'audit', 1, 1, 1, 1, 1),
(489, 1, 'audit', 1, 1, 1, 1, 1),
(490, 2, 'delegations', 1, 1, 1, 1, 1),
(491, 1, 'delegations', 1, 1, 1, 1, 1),
(492, 2, 'rivets', 1, 1, 1, 1, 1),
(493, 1, 'rivets', 1, 1, 1, 1, 1),
(525, 8, 'equipements', 0, 1, 0, 0, 1),
(526, 8, 'sites', 0, 1, 0, 0, 1),
(527, 8, 'affectations', 0, 1, 0, 0, 1),
(528, 8, 'receptions', 1, 1, 1, 0, 1),
(529, 8, 'bobines', 1, 1, 1, 0, 1),
(530, 8, 'inventaire_bobines', 1, 1, 1, 0, 1),
(531, 8, 'consommables', 0, 1, 0, 0, 1),
(532, 8, 'rapports', 0, 1, 0, 0, 1),
(533, 8, 'interventions', 0, 1, 0, 0, 0),
(534, 8, 'point_emuci', 1, 1, 1, 0, 1),
(535, 8, 'import_emuci', 0, 1, 0, 0, 1),
(536, 8, 'audit', 0, 1, 0, 0, 0),
(537, 8, 'delegations', 1, 1, 1, 1, 0),
(538, 8, 'rivets', 1, 1, 1, 0, 1),
(540, 6, 'equipements', 0, 1, 0, 0, 0),
(541, 6, 'bobines', 1, 1, 1, 0, 1),
(542, 6, 'inventaire_bobines', 1, 1, 1, 0, 0),
(543, 6, 'receptions', 1, 1, 1, 0, 1),
(544, 6, 'consommables', 0, 1, 0, 0, 0),
(545, 6, 'rapports', 0, 1, 0, 0, 1),
(546, 6, 'rivets', 0, 1, 0, 0, 0),
(547, 5, 'equipements', 1, 1, 1, 0, 1),
(548, 5, 'consommables', 1, 1, 1, 0, 1),
(549, 5, 'receptions', 1, 1, 1, 0, 1),
(550, 5, 'rapports', 0, 1, 0, 0, 1),
(551, 5, 'sites', 0, 1, 0, 0, 0),
(552, 2, 'pmma', 1, 1, 1, 0, 1),
(553, 2, 'commandes', 1, 1, 1, 0, 1),
(554, 1, 'pmma', 1, 1, 1, 0, 1),
(555, 1, 'commandes', 1, 1, 1, 0, 1),
(556, 15, 'pmma', 1, 1, 1, 0, 1),
(557, 15, 'commandes', 1, 1, 1, 0, 1),
(558, 8, 'pmma', 1, 1, 1, 0, 1),
(559, 8, 'commandes', 1, 1, 1, 0, 1),
(567, 6, 'pmma', 1, 1, 1, 0, 0),
(568, 6, 'commandes', 1, 1, 0, 0, 0),
(570, 5, 'commandes', 0, 1, 1, 0, 1),
(571, 5, 'pmma', 1, 1, 1, 0, 1),
(572, 17, 'commandes', 0, 1, 1, 0, 1),
(573, 17, 'pmma', 1, 1, 1, 0, 1),
(577, 15, 'interventions', 1, 1, 1, 0, 1),
(578, 16, 'interventions', 1, 1, 1, 0, 1);

-- --------------------------------------------------------

--
-- Structure de la table `points_emuci`
--

CREATE TABLE `points_emuci` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `date_point` date NOT NULL,
  `plaques_posees` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Nb plaques posées = films consommés (pose)',
  `plaques_reservees` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Nb plaques réservées = films consommés (réservation)',
  `total_films_deduits` int(10) UNSIGNED GENERATED ALWAYS AS (`plaques_posees` + `plaques_reservees`) STORED COMMENT 'Total films déduits du stock bobine',
  `notes` text DEFAULT NULL,
  `statut` enum('brouillon','soumis','valide') NOT NULL DEFAULT 'brouillon',
  `saisi_par` int(10) UNSIGNED NOT NULL,
  `valide_par` int(10) UNSIGNED DEFAULT NULL,
  `valide_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Points journaliers EMUCI saisis par le Contrôleur Production';

--
-- Déchargement des données de la table `points_emuci`
--

INSERT INTO `points_emuci` (`id`, `site_id`, `date_point`, `plaques_posees`, `plaques_reservees`, `notes`, `statut`, `saisi_par`, `valide_par`, `valide_at`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-04-24', 25, 0, '', 'soumis', 9, NULL, NULL, '2026-04-24 18:21:55', '2026-04-24 18:25:12'),
(2, 1, '2026-04-27', 36, 0, '', 'soumis', 9, NULL, NULL, '2026-04-27 14:53:07', '2026-04-27 15:01:16');

-- --------------------------------------------------------

--
-- Structure de la table `points_journaliers_info`
--

CREATE TABLE `points_journaliers_info` (
  `id` int(10) UNSIGNED NOT NULL,
  `technicien_id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `date_point` date NOT NULL,
  `nb_equip_ok` int(10) UNSIGNED DEFAULT 0,
  `nb_equip_hs` int(10) UNSIGNED DEFAULT 0,
  `nb_interventions` int(10) UNSIGNED DEFAULT 0,
  `observations` text DEFAULT NULL,
  `actions_preventives` text DEFAULT NULL,
  `statut` enum('brouillon','valide') NOT NULL DEFAULT 'brouillon',
  `valide_par` int(10) UNSIGNED DEFAULT NULL,
  `valide_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `rapports_journaliers_info`
--

CREATE TABLE `rapports_journaliers_info` (
  `id` int(10) UNSIGNED NOT NULL,
  `technicien_id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `date_rapport` date NOT NULL,
  `nb_equip_ok` int(10) UNSIGNED DEFAULT 0,
  `nb_equip_hs` int(10) UNSIGNED DEFAULT 0,
  `nb_equip_maintenance` int(10) UNSIGNED DEFAULT 0,
  `nb_interventions` int(10) UNSIGNED DEFAULT 0,
  `observations` text DEFAULT NULL,
  `actions_preventives` text DEFAULT NULL,
  `statut` enum('brouillon','valide') NOT NULL DEFAULT 'brouillon',
  `valide_par` int(10) UNSIGNED DEFAULT NULL,
  `valide_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `receptions_consommables`
--

CREATE TABLE `receptions_consommables` (
  `id` int(10) UNSIGNED NOT NULL,
  `consommable_id` int(10) UNSIGNED NOT NULL,
  `quantite` decimal(10,2) NOT NULL,
  `prix_unitaire` decimal(12,2) DEFAULT 0.00,
  `prix_total` decimal(12,2) DEFAULT 0.00,
  `date_reception` date NOT NULL,
  `fournisseur` varchar(150) DEFAULT NULL,
  `numero_bon` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `receptions_consommables`
--

INSERT INTO `receptions_consommables` (`id`, `consommable_id`, `quantite`, `prix_unitaire`, `prix_total`, `date_reception`, `fournisseur`, `numero_bon`, `notes`, `created_by`, `created_at`) VALUES
(1, 6, 20.00, 1500.00, 30000.00, '2026-04-03', 'SUP BON PRIX ZONE4', 'BLF-2026', '', 3, '2026-04-03 04:57:05'),
(2, 2, 10.00, 3250.00, 32500.00, '2026-04-03', 'SUP AUCHAN', '', '', 3, '2026-04-03 05:00:06'),
(3, 4, 5.00, 1325.00, 6625.00, '2026-04-03', 'SUP BON PRIX', '', '', 3, '2026-04-03 05:00:46'),
(4, 3, 1.00, 4500.00, 4500.00, '2026-04-03', 'ENTREPRISE GARDE', '', '', 3, '2026-04-03 05:01:29'),
(5, 1, 2.00, 562.00, 1124.00, '2026-04-03', 'SUP BON PRIX', '', '', 3, '2026-04-03 05:01:57'),
(6, 1, 10.00, 562.00, 5620.00, '2026-04-15', '', '', '', 3, '2026-04-15 22:37:28'),
(7, 5, 19.98, 2000.00, 39960.00, '2026-04-15', '', '', '', 3, '2026-04-15 22:37:46'),
(8, 3, 2000.00, 4500.00, 9000000.00, '2026-04-15', '', '', '', 3, '2026-04-15 22:38:08'),
(9, 1, 1.99, 562.00, 1118.38, '2026-04-15', '', '', '', 3, '2026-04-15 22:38:45'),
(10, 4, 2.00, 1325.00, 2650.00, '2026-04-20', '', 'kjhgfdrtyuioighj', '', 8, '2026-04-20 09:00:27');

-- --------------------------------------------------------

--
-- Structure de la table `receptions_site`
--

CREATE TABLE `receptions_site` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `type_reception` enum('equipement','consommable') NOT NULL,
  `equipement_id` int(10) UNSIGNED DEFAULT NULL,
  `consommable_id` int(10) UNSIGNED DEFAULT NULL,
  `quantite` decimal(10,2) DEFAULT NULL,
  `livraison_ref_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID de la livraison d origine (livraisons_consommables)',
  `mouvement_ref_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID du mouvement d origine (mouvements_equipements)',
  `date_reception` date NOT NULL,
  `fichier_fiche` varchar(255) DEFAULT NULL COMMENT 'Fiche signée obligatoire (PDF/image)',
  `notes` text DEFAULT NULL,
  `statut` enum('en_attente','receptionnee','litige') NOT NULL DEFAULT 'en_attente',
  `litige_motif` text DEFAULT NULL COMMENT 'Motif détaillé du litige signalé par le coordinateur',
  `litige_traite_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID gestionnaire qui a traité le litige',
  `litige_traite_at` datetime DEFAULT NULL,
  `remplacement_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID de la nouvelle livraison/mouvement de remplacement',
  `remplacement_notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `receptions_site`
--

INSERT INTO `receptions_site` (`id`, `site_id`, `type_reception`, `equipement_id`, `consommable_id`, `quantite`, `livraison_ref_id`, `mouvement_ref_id`, `date_reception`, `fichier_fiche`, `notes`, `statut`, `litige_motif`, `litige_traite_by`, `litige_traite_at`, `remplacement_id`, `remplacement_notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 2, 'equipement', 9, NULL, NULL, NULL, 0, '2026-04-19', NULL, NULL, 'en_attente', NULL, NULL, NULL, NULL, NULL, 3, '2026-04-19 07:57:31', '2026-04-19 07:57:31'),
(2, 1, 'equipement', 23, NULL, NULL, NULL, 0, '2026-04-19', 'fiche_2_20260419_082937_eca067f9.pdf', '', 'receptionnee', NULL, NULL, NULL, NULL, NULL, 3, '2026-04-19 08:27:11', '2026-04-19 08:29:37'),
(3, 1, 'equipement', 20, NULL, NULL, NULL, 0, '2026-04-19', NULL, 'le format nest pas correcte', 'litige', NULL, NULL, NULL, NULL, NULL, 3, '2026-04-19 08:30:21', '2026-04-19 08:30:44'),
(4, 1, 'equipement', 22, NULL, NULL, NULL, 0, '2026-04-19', NULL, 'format non correct', 'en_attente', 'format non correct', 3, '2026-04-20 06:34:58', NULL, 'jgvbn,', 3, '2026-04-19 09:20:51', '2026-04-20 06:34:58'),
(5, 1, 'consommable', NULL, 4, 1.00, 9, NULL, '2026-04-20', NULL, 'nombre pas exacte', 'litige', 'nombre pas exacte', NULL, NULL, NULL, NULL, 8, '2026-04-20 12:21:41', '2026-04-21 12:48:28'),
(6, 1, 'equipement', 21, NULL, NULL, NULL, 0, '2026-04-20', 'fiche_6_20260421_124815_4058a68f.pdf', '', 'receptionnee', NULL, NULL, NULL, NULL, NULL, 8, '2026-04-20 12:22:41', '2026-04-21 12:48:15'),
(7, 1, 'consommable', NULL, 3, 200.00, 10, NULL, '2026-04-27', 'fiche_7_20260427_143921_4cc8c4aa.pdf', '', 'receptionnee', NULL, NULL, NULL, NULL, NULL, 8, '2026-04-27 14:35:27', '2026-04-27 14:39:21'),
(8, 1, 'consommable', NULL, 1, 2.00, 11, NULL, '2026-05-28', NULL, NULL, 'en_attente', NULL, NULL, NULL, NULL, NULL, 3, '2026-05-28 10:17:20', '2026-05-28 10:17:20');

-- --------------------------------------------------------

--
-- Structure de la table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `roles`
--

INSERT INTO `roles` (`id`, `nom`, `slug`, `description`, `created_at`) VALUES
(1, 'Super Administrateur', 'superadmin', 'Accès total, lecture de tous les audits', '2026-04-01 11:43:26'),
(2, 'Administrateur', 'admin', 'Gestion utilisateurs, équipements, sites', '2026-04-01 11:43:26'),
(3, 'Gestionnaire', 'gestionnaire', 'Saisie des mouvements et livraisons', '2026-04-01 11:43:26'),
(4, 'Lecteur', 'lecteur', 'Consultation uniquement', '2026-04-01 11:43:26'),
(5, 'Gestionnaire de Stock', 'gestionnaire_stock', 'Gère tout le stock central : entrées, sorties, livraisons sites', '2026-04-19 07:49:10'),
(6, 'Coordinateur de Site', 'coordinateur_site', 'Réceptionne les commandes sur son site, fait le point journalier', '2026-04-19 07:49:10'),
(7, 'Maintenance Informatique', 'maintenance_info', 'Gère le stock équipements informatiques uniquement', '2026-04-19 07:49:10'),
(8, 'Superviseur Opération', 'superviseur_operation', 'Supervise les actions des coordinateurs de site', '2026-04-19 07:49:10'),
(9, 'Contrôleur Production', 'controleur_production', 'Saisie quotidienne des plaques posées et réservées par site (données EMUCI)', '2026-04-24 18:08:51'),
(13, 'Gestionnaire Stock Bobines', 'gestionnaire_stock_bobines', 'Validation stock matin, gestion demandes bobines, réajustements', '2026-04-29 15:39:36'),
(14, 'Gestionnaire Opération', 'gestionnaire_operation', 'Second du Superviseur Opération — reçoit les tâches déléguées', '2026-04-30 14:10:42'),
(15, 'Superviseur IT', 'superviseur_it', 'Accès complet informatique + supervise les Support IT', '2026-04-30 14:10:42'),
(16, 'Support IT', 'support_it', 'Profil flexible — sous-rôles affectables : Maintenance, Contrôleur Production, Gestionnaire Bobines', '2026-04-30 14:10:42'),
(17, 'Superviseur Achat', 'superviseur_achat', 'Suivi consommables, équipements, consommation des sites', '2026-04-30 14:10:42');

-- --------------------------------------------------------

--
-- Structure de la table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `last_activity` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('15v96d2lkm64hnmtle0p14a05n', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777395993),
('1r507qqogp11srvobq0adc2vgp', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1776247105),
('4romb1undjldme74uj0lf288e2', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, 1775191175),
('5vujk0m3arf9qamo5s14vcr0ih', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777563914),
('7tnjmnn1ds5vjqdvk5n1gbhmd8', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, 1775132114),
('806jgj2q3pkv577ouis6ihhva0', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777023798),
('89dl0ivqrqmh9vv9s5n3fed6n2', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1776579435),
('8ur815vctvsr1ujq96od83r9q7', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777372065),
('a7i824g8tsqck72hhep2bi4i19', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1776629721),
('asd7nq22a48lvepq688rebtbq8', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1776695938),
('bps9plgo35m8n5ug8u66uq1l36', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, 1775130238),
('c3ors8if9t308t3p5l4dc5adqb', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1776942208),
('c7t8c028ns5dof155tac8if6ni', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777305298),
('chft5nhrao0c7vdp67ds1bd3id', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777055113),
('d0fron4dnjpelqv3r2gmqj2t4q', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777477992),
('d1mp0etmj4h0nipioeak4rjelj', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, 1779959100),
('da05tde89uthve1itpa9c6ucc1', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1776689500),
('dpc786hk753gvpbaqtgsdg3gkd', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1776776130),
('e0j8et7bouabpqva3fevltq7g1', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777309707),
('eqpkho2pcooee7r4vgfqmcvdpf', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1776590914),
('f72458fj4jlle4eu2v4k9t550n', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777559062),
('fmqf4o8bs9qgcca51tni27gedo', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1776179355),
('hcr46uqjmfsi94heob483q8gco', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, 1779958991),
('hr396oq9bbib7dmr163mt50mn3', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777026831),
('hrouf1cpvkm7n7sld2eefeb8u4', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777311418),
('ia0pedc8pkmnqugvs9jne1r3vl', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1776983406),
('ia8ntvk5g6q68aglda1uq9nvdj', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777560475),
('ih0fufqtrli5v40o4745pcohuk', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777039743),
('iqirksbecr4734an9dhc3qjj3l', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1776691284),
('ivlmlct7vtsdefpfsg4sj7l5qf', 9, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36 Edg/147.0.0.0', NULL, 1777054558),
('j9eubdoier7sv5q9d5j5162j1l', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1776677072),
('kha1aoa1va1i3562h4lomedako', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777368778),
('mr56uppb9i83jscb5mqtn265ch', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1776630038),
('nie02haggbqrhbv2d9tcs7gfqq', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777055050),
('o0rqc7nj3rjk3pan4hhgeq919i', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1776775935),
('oo9fvgg8887pl1nm5gnuhr3t2v', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777300314),
('ou4h23bdt2iodo1cvk74lbgtmc', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, 1775110678),
('pl37j6sngb81lq02s16l218pcl', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1776587738),
('qkka2a5klqt933fdu8ni5i19bb', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777371907),
('t64ikdlrcj6ihruiq3uj8jd000', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, 1779960005),
('tgesf45bbda80puumtotquuhch', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777018306),
('tme6e8756cbq6cnveejv3pjco6', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1776983572),
('u7fpj3rkk4i1gf0bm7edc1nou0', 8, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', NULL, 1777018540),
('vapncjrml2p59cgnkh89gk0q39', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, 1775587841);

-- --------------------------------------------------------

--
-- Structure de la table `sites`
--

CREATE TABLE `sites` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `nom` varchar(150) NOT NULL,
  `type` enum('saisie','pose','mixte','caissier','entrepot','siege') NOT NULL DEFAULT 'saisie',
  `option_caisse` tinyint(1) DEFAULT 0,
  `adresse` text DEFAULT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `pays` varchar(100) DEFAULT 'Côte d''Ivoire',
  `responsable_id` int(10) UNSIGNED DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mobile` tinyint(1) DEFAULT 0 COMMENT '1 = site mobile (équipe temporaire)',
  `date_debut_mission` date DEFAULT NULL,
  `date_fin_mission` date DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `sites`
--

INSERT INTO `sites` (`id`, `code`, `nom`, `type`, `option_caisse`, `adresse`, `ville`, `pays`, `responsable_id`, `actif`, `created_at`, `updated_at`, `mobile`, `date_debut_mission`, `date_fin_mission`, `latitude`, `longitude`) VALUES
(1, 'ABJ-01', 'GU ABIDJAN', 'pose', 1, 'Guichet unique abidjan vridi', 'ABIDJAN', 'Côte d\'Ivoire', NULL, 1, '2026-04-02 06:06:19', '2026-04-03 05:04:00', 0, NULL, NULL, NULL, NULL),
(2, 'ABJ-02', 'STAR AUTO', 'mixte', 0, '', 'ABIDJAN', 'Côte d\'Ivoire', NULL, 1, '2026-04-02 06:06:49', '2026-04-02 06:06:49', 0, NULL, NULL, NULL, NULL),
(3, 'ABJ-03', 'CFAO', 'pose', 0, '', 'ABIDJAN', 'Côte d\'Ivoire', NULL, 1, '2026-04-02 06:07:37', '2026-04-02 06:07:37', 0, NULL, NULL, NULL, NULL),
(4, 'KGO-01', 'GU KORHOOGO', 'pose', 0, '', 'KORHOGOO', 'Côte d\'Ivoire', NULL, 1, '2026-04-02 06:08:13', '2026-04-02 06:08:13', 0, NULL, NULL, NULL, NULL),
(5, 'BKE-01', 'GU BOUAKE', 'pose', 0, '', 'BOUAKE', 'Côte d\'Ivoire', NULL, 1, '2026-04-02 06:08:34', '2026-04-02 06:08:34', 0, NULL, NULL, NULL, NULL),
(6, 'ABJ-04', 'SITE DE TEST', 'saisie', 0, '', 'ABIDJAN', 'Côte d\'Ivoire', NULL, 1, '2026-04-03 04:18:17', '2026-04-03 04:18:17', 0, NULL, NULL, NULL, NULL),
(7, 'ABJ-05', 'TEST AKOUEDO', 'pose', 1, '', 'ABIDJAN', 'Côte d\'Ivoire', NULL, 1, '2026-04-03 04:22:59', '2026-04-29 15:45:44', 0, NULL, NULL, NULL, NULL),
(8, 'ENT', 'ENTREPOT EMUCI', 'entrepot', 0, '', 'ABIDJAN', 'Côte d\'Ivoire', NULL, 1, '2026-04-03 04:45:47', '2026-04-03 04:45:47', 0, NULL, NULL, NULL, NULL),
(9, 'ADM', 'ADMINISTRATION (Siége)', 'siege', 0, '', 'ABIDJAN Zone4', 'Côte d\'Ivoire', NULL, 1, '2026-04-03 04:46:43', '2026-04-03 04:46:43', 0, NULL, NULL, NULL, NULL),
(10, 'ABJ-06', 'FDS AKOUEDO', 'pose', 1, 'ABIDJAN', 'ABIDJAN', 'Côte d\'Ivoire', NULL, 1, '2026-04-29 15:45:19', '2026-04-29 15:45:19', 0, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `stock_consommables_site`
--

CREATE TABLE `stock_consommables_site` (
  `id` int(10) UNSIGNED NOT NULL,
  `consommable_id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `quantite` decimal(10,2) DEFAULT 0.00,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `stock_consommables_site`
--

INSERT INTO `stock_consommables_site` (`id`, `consommable_id`, `site_id`, `quantite`, `updated_at`) VALUES
(1, 2, 5, 1.00, '2026-04-02 14:18:16'),
(2, 2, 3, 2.00, '2026-04-02 14:21:26'),
(3, 5, 3, 2.00, '2026-04-03 04:38:56'),
(4, 6, 3, 5.00, '2026-04-03 04:57:38'),
(5, 5, 7, 1.00, '2026-04-03 05:02:41'),
(6, 3, 1, 205.00, '2026-04-27 14:35:27'),
(7, 1, 3, 4.00, '2026-04-15 22:39:31'),
(8, 4, 1, 1.00, '2026-04-20 12:21:41'),
(10, 1, 1, 2.00, '2026-05-28 10:17:20');

-- --------------------------------------------------------

--
-- Structure de la table `stock_pmma`
--

CREATE TABLE `stock_pmma` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `type_pmma` varchar(50) DEFAULT 'Standard',
  `quantite` int(10) UNSIGNED NOT NULL,
  `type_mouvement` enum('entree','sortie') NOT NULL,
  `bobine_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mouvements PMMA';

--
-- Déchargement des données de la table `stock_pmma`
--

INSERT INTO `stock_pmma` (`id`, `site_id`, `type_pmma`, `quantite`, `type_mouvement`, `bobine_id`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 'Type A', 500, 'entree', NULL, 'EMUCI', 3, '2026-05-28 10:00:11'),
(2, 3, 'Type A', 500, 'entree', NULL, '', 3, '2026-05-28 10:00:26'),
(3, 5, 'TYPE B', 500, 'entree', NULL, '', 3, '2026-05-28 10:00:42'),
(4, 1, 'Type C', 500, 'entree', NULL, '', 3, '2026-05-28 10:00:54'),
(5, 1, 'Type D', 500, 'entree', NULL, '', 3, '2026-05-28 10:01:04'),
(6, 3, 'TYPE B', 500, 'entree', NULL, '', 3, '2026-05-28 10:01:12'),
(7, 8, 'Type D', 500, 'entree', NULL, '', 3, '2026-05-28 10:01:57'),
(8, 8, 'TYPE B', 500, 'entree', NULL, '', 3, '2026-05-28 10:02:11'),
(9, 1, 'TYPE A', 50, 'sortie', NULL, 'Utilisation production', 3, '2026-05-28 10:02:46');

-- --------------------------------------------------------

--
-- Structure de la table `stock_pmma_site`
--

CREATE TABLE `stock_pmma_site` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `type_pmma` varchar(50) DEFAULT 'Standard',
  `quantite` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `seuil_alerte` int(10) UNSIGNED DEFAULT 10,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stock PMMA par site';

--
-- Déchargement des données de la table `stock_pmma_site`
--

INSERT INTO `stock_pmma_site` (`id`, `site_id`, `type_pmma`, `quantite`, `seuil_alerte`, `updated_at`) VALUES
(1, 1, 'Type A', 450, 10, '2026-05-28 10:02:46'),
(2, 3, 'Type A', 500, 10, '2026-05-28 10:00:26'),
(3, 5, 'TYPE B', 500, 10, '2026-05-28 10:00:42'),
(4, 1, 'Type C', 500, 10, '2026-05-28 10:00:54'),
(5, 1, 'Type D', 500, 10, '2026-05-28 10:01:04'),
(6, 3, 'TYPE B', 500, 10, '2026-05-28 10:01:12'),
(7, 8, 'Type D', 500, 10, '2026-05-28 10:01:57'),
(8, 8, 'TYPE B', 500, 10, '2026-05-28 10:02:11');

-- --------------------------------------------------------

--
-- Structure de la table `support_it_roles`
--

CREATE TABLE `support_it_roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'Compte Support IT',
  `sous_role` enum('maintenance','controleur_production','gestionnaire_bobines') NOT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `affecte_par` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sous-rôles affectés aux comptes Support IT';

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `support_it_sous_roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Cache sous-rôles Support IT actifs' CHECK (json_valid(`support_it_sous_roles`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `nom`, `prenom`, `email`, `password_hash`, `role_id`, `site_id`, `avatar`, `telephone`, `actif`, `last_login`, `reset_token`, `reset_token_expiry`, `created_at`, `updated_at`, `support_it_sous_roles`) VALUES
(1, 'Admin', 'Super', 'admin@stockapp.local', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NULL, NULL, NULL, 1, NULL, '3ab6396e0e7db7593d436c5342a1060ca1963089fabbe18d8d46c79dbd5a9684', '2026-04-01 13:35:27', '2026-04-01 11:43:26', '2026-04-01 12:35:27', NULL),
(2, 'KOUASSI', 'RITA', 'kri@gmail.com', 'K123456@', 2, 1, NULL, '0789745649', 0, NULL, NULL, NULL, '2026-04-01 13:52:31', '2026-04-19 08:05:33', NULL),
(3, 'DON', 'Axelle', 'donruthaxelle01@gmail.com', '$2y$12$dmrSBuzKHQfIcQUCjtzXfu4tbNuIcAD7V8wkgxD7yzDEmSeqzk5jO', 1, NULL, NULL, NULL, 1, '2026-05-28 09:20:04', NULL, NULL, '2026-04-02 06:00:26', '2026-05-28 09:20:04', NULL),
(4, 'KOUASSI', 'BERTRAND', 'bertrand@gmail.com', '$2y$12$TkZzIyHtOtnG2sL3gAmvCeddGgz19pPuWINvgGP4zNpCrlpD//FOq', 3, NULL, NULL, '', 1, '2026-05-28 09:05:00', NULL, NULL, '2026-04-02 11:27:44', '2026-05-28 09:05:00', NULL),
(5, 'operation', 'test', 'testoperation@gmail.com', '$2y$12$NcU/9ovo.9v25tXZ4tpXP.Y8PkFfnyL4Tlph1N9N0voXF2gXScA6C', 8, 9, NULL, '+22507897462222', 1, '2026-05-28 09:03:11', NULL, NULL, '2026-04-19 08:04:30', '2026-05-28 09:03:11', NULL),
(6, 'info', 'test', 'testinfo@gmail.com', '$2y$12$UNNWiFEvahXoNqyWkUEzTuCU4J6sjraLAbhzWR..k8p1Gme9021iO', 7, 9, NULL, '+22507897462223', 1, '2026-04-27 14:32:17', NULL, NULL, '2026-04-19 08:05:15', '2026-04-27 14:32:17', NULL),
(7, 'cordo', 'test', 'testcordo@gmail.com', '$2y$12$GV5Tzh5b9YMLbBSaRwXwwOYmiRdN3VZqUbGH5J9GVRAfENqB97j5K', 6, 1, NULL, '+22507897462222', 1, '2026-04-28 09:55:07', NULL, NULL, '2026-04-19 08:06:45', '2026-04-28 09:55:07', NULL),
(8, 'stock', 'test', 'teststock@gmail.com', '$2y$12$60fUp.WG6nrAJZE9Y3QmKuQp2ka.RvkMqQ13patVe9ukwZy0/l08W', 5, NULL, NULL, '+22507897462222', 1, '2026-04-27 15:03:10', NULL, NULL, '2026-04-19 08:07:28', '2026-04-27 15:03:10', NULL),
(9, 'gestion', 'Pose', 'gestionpose@gmail.com', '$2y$12$QmKvVxRqxYJNKbCjSkx1MuSUFeGCEbNYsbj3gFX0A62VzVKMyupl.', 9, NULL, NULL, '+22507897462222', 1, '2026-04-27 17:20:11', NULL, NULL, '2026-04-24 18:11:36', '2026-04-27 17:20:11', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `validations_stock_matin`
--

CREATE TABLE `validations_stock_matin` (
  `id` int(10) UNSIGNED NOT NULL,
  `site_id` int(10) UNSIGNED NOT NULL,
  `date_validation` date NOT NULL,
  `statut` enum('valide_auto','valide_gsb','autorise_ecart','reajuste','refuse') NOT NULL,
  `nb_ecarts` int(11) DEFAULT 0,
  `details_ecarts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Détail des écarts par bobine' CHECK (json_valid(`details_ecarts`)),
  `gsb_user_id` int(10) UNSIGNED DEFAULT NULL,
  `gsb_at` datetime DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Validations stock bobines matin par site';

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_cout_hebdo_site`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `v_cout_hebdo_site` (
`site_id` int(10) unsigned
,`site_nom` varchar(150)
,`site_type` enum('saisie','pose','mixte','caissier','entrepot','siege')
,`annee` int(4)
,`semaine` int(2)
,`debut_semaine` date
,`cout_total` decimal(34,2)
,`qte_total` decimal(32,2)
,`nb_articles` bigint(21)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_cout_mensuel_site`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `v_cout_mensuel_site` (
`site_id` int(10) unsigned
,`site_nom` varchar(150)
,`site_type` enum('saisie','pose','mixte','caissier','entrepot','siege')
,`annee` int(4)
,`mois` int(2)
,`mois_label` varchar(7)
,`cout_total` decimal(34,2)
,`qte_total` decimal(32,2)
,`nb_articles` bigint(21)
);

-- --------------------------------------------------------

--
-- Structure de la vue `v_cout_hebdo_site`
--
DROP TABLE IF EXISTS `v_cout_hebdo_site`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_cout_hebdo_site`  AS SELECT `s`.`id` AS `site_id`, `s`.`nom` AS `site_nom`, `s`.`type` AS `site_type`, year(`lc`.`date_livraison`) AS `annee`, week(`lc`.`date_livraison`,1) AS `semaine`, str_to_date(concat(year(`lc`.`date_livraison`),' ',week(`lc`.`date_livraison`,1),' Monday'),'%X %V %W') AS `debut_semaine`, coalesce(sum(`lc`.`prix_total`),0) AS `cout_total`, coalesce(sum(`lc`.`quantite`),0) AS `qte_total`, count(distinct `lc`.`consommable_id`) AS `nb_articles` FROM (`sites` `s` left join `livraisons_consommables` `lc` on(`lc`.`site_id` = `s`.`id`)) WHERE `s`.`actif` = 1 GROUP BY `s`.`id`, year(`lc`.`date_livraison`), week(`lc`.`date_livraison`,1) ;

-- --------------------------------------------------------

--
-- Structure de la vue `v_cout_mensuel_site`
--
DROP TABLE IF EXISTS `v_cout_mensuel_site`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_cout_mensuel_site`  AS SELECT `s`.`id` AS `site_id`, `s`.`nom` AS `site_nom`, `s`.`type` AS `site_type`, year(`lc`.`date_livraison`) AS `annee`, month(`lc`.`date_livraison`) AS `mois`, date_format(`lc`.`date_livraison`,'%Y-%m') AS `mois_label`, coalesce(sum(`lc`.`prix_total`),0) AS `cout_total`, coalesce(sum(`lc`.`quantite`),0) AS `qte_total`, count(distinct `lc`.`consommable_id`) AS `nb_articles` FROM (`sites` `s` left join `livraisons_consommables` `lc` on(`lc`.`site_id` = `s`.`id`)) WHERE `s`.`actif` = 1 GROUP BY `s`.`id`, year(`lc`.`date_livraison`), month(`lc`.`date_livraison`) ;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `affectations_equipements`
--
ALTER TABLE `affectations_equipements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipement_id` (`equipement_id`),
  ADD KEY `site_dest_id` (`site_dest_id`),
  ADD KEY `user_dest_id` (`user_dest_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `valide_n1_by` (`valide_n1_by`);

--
-- Index pour la table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_module` (`module`),
  ADD KEY `idx_date` (`created_at`);

--
-- Index pour la table `bilans_mensuels_bobines`
--
ALTER TABLE `bilans_mensuels_bobines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_site_mois` (`site_id`,`mois`),
  ADD KEY `inventaire_id` (`inventaire_id`),
  ADD KEY `valide_par` (`valide_par`);

--
-- Index pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_commande` (`numero_commande`),
  ADD KEY `idx_site_statut` (`site_id`,`statut`);

--
-- Index pour la table `commande_lignes`
--
ALTER TABLE `commande_lignes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `commande_id` (`commande_id`);

--
-- Index pour la table `comparaisons_stock`
--
ALTER TABLE `comparaisons_stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_site_date` (`site_id`,`date_comparaison`),
  ADD KEY `ajuste_par` (`ajuste_par`);

--
-- Index pour la table `configurations_site`
--
ALTER TABLE `configurations_site`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_type_nom` (`type_site`,`nomenclature_id`);

--
-- Index pour la table `config_postes_composants`
--
ALTER TABLE `config_postes_composants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_poste_nom` (`poste_id`,`nomenclature_id`),
  ADD KEY `nomenclature_id` (`nomenclature_id`);

--
-- Index pour la table `config_postes_types`
--
ALTER TABLE `config_postes_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Index pour la table `consommables`
--
ALTER TABLE `consommables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Index pour la table `consommations_bobines`
--
ALTER TABLE `consommations_bobines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_date` (`date_conso`),
  ADD KEY `idx_bobine` (`bobine_id`),
  ADD KEY `idx_site` (`site_id`);

--
-- Index pour la table `delegations`
--
ALTER TABLE `delegations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sup_gest_module` (`superviseur_id`,`gestionnaire_id`,`module`),
  ADD KEY `idx_gestionnaire` (`gestionnaire_id`);

--
-- Index pour la table `demandes_bobines`
--
ALTER TABLE `demandes_bobines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bobine_id` (`bobine_id`),
  ADD KEY `demande_par` (`demande_par`),
  ADD KEY `traite_par` (`traite_par`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_site` (`site_id`);

--
-- Index pour la table `ecarts_bobines`
--
ALTER TABLE `ecarts_bobines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventaire_id` (`inventaire_id`),
  ADD KEY `resolu_par` (`resolu_par`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_bobine` (`bobine_id`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_date` (`date_constat`);

--
-- Index pour la table `equipements`
--
ALTER TABLE `equipements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_serie_interne` (`numero_serie_interne`),
  ADD KEY `nomenclature_id` (`nomenclature_id`),
  ADD KEY `utilisateur_id` (`utilisateur_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_etat` (`etat`),
  ADD KEY `idx_date_fin_cycle` (`date_fin_cycle`),
  ADD KEY `idx_site` (`site_id`);

--
-- Index pour la table `import_optoplate`
--
ALTER TABLE `import_optoplate`
  ADD PRIMARY KEY (`id`),
  ADD KEY `importe_par` (`importe_par`),
  ADD KEY `idx_session` (`import_session_id`),
  ADD KEY `idx_date` (`date_import`),
  ADD KEY `idx_statut` (`statut_plaque`),
  ADD KEY `idx_bobine` (`num_bobine`),
  ADD KEY `idx_site` (`site_id`),
  ADD KEY `idx_immat` (`immatriculation`);

--
-- Index pour la table `import_optotrace`
--
ALTER TABLE `import_optotrace`
  ADD PRIMARY KEY (`id`),
  ADD KEY `importe_par` (`importe_par`),
  ADD KEY `idx_session` (`import_session_id`),
  ADD KEY `idx_date` (`date_import`),
  ADD KEY `idx_plate` (`plate_number`),
  ADD KEY `idx_site` (`site_id`);

--
-- Index pour la table `import_sessions_emuci`
--
ALTER TABLE `import_sessions_emuci`
  ADD PRIMARY KEY (`id`),
  ADD KEY `importe_par` (`importe_par`),
  ADD KEY `idx_date` (`date_import`);

--
-- Index pour la table `interventions_maintenance`
--
ALTER TABLE `interventions_maintenance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipement_id` (`equipement_id`),
  ADD KEY `idx_date` (`date_intervention`),
  ADD KEY `idx_technicien` (`technicien_id`),
  ADD KEY `idx_site` (`site_id`);

--
-- Index pour la table `inventaires_bobines`
--
ALTER TABLE `inventaires_bobines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `site_id` (`site_id`),
  ADD KEY `cree_par` (`cree_par`),
  ADD KEY `valide_par` (`valide_par`),
  ADD KEY `idx_date` (`date_inventaire`),
  ADD KEY `idx_statut` (`statut`);

--
-- Index pour la table `inventaire_details_bobines`
--
ALTER TABLE `inventaire_details_bobines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inv_bobine` (`inventaire_id`,`bobine_id`),
  ADD KEY `bobine_id` (`bobine_id`);

--
-- Index pour la table `litige_messages`
--
ALTER TABLE `litige_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_reception` (`reception_id`);

--
-- Index pour la table `livraisons_consommables`
--
ALTER TABLE `livraisons_consommables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consommable_id` (`consommable_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_date` (`date_livraison`),
  ADD KEY `idx_site` (`site_id`);

--
-- Index pour la table `mouvements_bobines`
--
ALTER TABLE `mouvements_bobines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_bobine` (`bobine_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_date` (`created_at`);

--
-- Index pour la table `mouvements_equipements`
--
ALTER TABLE `mouvements_equipements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipement_id` (`equipement_id`),
  ADD KEY `site_source_id` (`site_source_id`),
  ADD KEY `site_dest_id` (`site_dest_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Index pour la table `nomenclatures`
--
ALTER TABLE `nomenclatures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Index pour la table `nomenclature_liens`
--
ALTER TABLE `nomenclature_liens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_parent_enfant` (`parent_id`,`enfant_id`),
  ADD KEY `enfant_id` (`enfant_id`);

--
-- Index pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `op_bobines`
--
ALTER TABLE `op_bobines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero` (`numero`),
  ADD KEY `site_id` (`site_id`),
  ADD KEY `type_vehicule_id` (`type_vehicule_id`),
  ADD KEY `idx_serie` (`serie`),
  ADD KEY `idx_statut` (`statut`);

--
-- Index pour la table `op_films_utilises`
--
ALTER TABLE `op_films_utilises`
  ADD PRIMARY KEY (`id`),
  ADD KEY `point_id` (`point_id`),
  ADD KEY `bobine_id` (`bobine_id`),
  ADD KEY `type_vehicule_id` (`type_vehicule_id`);

--
-- Index pour la table `op_points_journaliers`
--
ALTER TABLE `op_points_journaliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_site_date_type` (`site_id`,`date_point`,`type_point`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `validated_by` (`validated_by`);

--
-- Index pour la table `op_stock_rivets`
--
ALTER TABLE `op_stock_rivets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_site` (`site_id`);

--
-- Index pour la table `op_types_vehicule`
--
ALTER TABLE `op_types_vehicule`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Index pour la table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_role_module` (`role_id`,`module`);

--
-- Index pour la table `points_emuci`
--
ALTER TABLE `points_emuci`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_site_date` (`site_id`,`date_point`),
  ADD KEY `saisi_par` (`saisi_par`),
  ADD KEY `valide_par` (`valide_par`),
  ADD KEY `idx_date` (`date_point`),
  ADD KEY `idx_statut` (`statut`);

--
-- Index pour la table `points_journaliers_info`
--
ALTER TABLE `points_journaliers_info`
  ADD PRIMARY KEY (`id`),
  ADD KEY `valide_par` (`valide_par`),
  ADD KEY `idx_site_date` (`site_id`,`date_point`),
  ADD KEY `idx_technicien` (`technicien_id`);

--
-- Index pour la table `rapports_journaliers_info`
--
ALTER TABLE `rapports_journaliers_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tech_site_date` (`technicien_id`,`site_id`,`date_rapport`),
  ADD KEY `valide_par` (`valide_par`),
  ADD KEY `idx_site_date` (`site_id`,`date_rapport`);

--
-- Index pour la table `receptions_consommables`
--
ALTER TABLE `receptions_consommables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consommable_id` (`consommable_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_date` (`date_reception`);

--
-- Index pour la table `receptions_site`
--
ALTER TABLE `receptions_site`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipement_id` (`equipement_id`),
  ADD KEY `consommable_id` (`consommable_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_site` (`site_id`),
  ADD KEY `idx_date` (`date_reception`),
  ADD KEY `idx_statut` (`statut`);

--
-- Index pour la table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nom` (`nom`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Index pour la table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_last_activity` (`last_activity`);

--
-- Index pour la table `sites`
--
ALTER TABLE `sites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `responsable_id` (`responsable_id`);

--
-- Index pour la table `stock_consommables_site`
--
ALTER TABLE `stock_consommables_site`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_conso_site` (`consommable_id`,`site_id`),
  ADD KEY `site_id` (`site_id`);

--
-- Index pour la table `stock_pmma`
--
ALTER TABLE `stock_pmma`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site_date` (`site_id`,`created_at`);

--
-- Index pour la table `stock_pmma_site`
--
ALTER TABLE `stock_pmma_site`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_site_type` (`site_id`,`type_pmma`);

--
-- Index pour la table `support_it_roles`
--
ALTER TABLE `support_it_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_sousrole` (`user_id`,`sous_role`),
  ADD KEY `affecte_par` (`affecte_par`),
  ADD KEY `idx_sous_role` (`sous_role`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- Index pour la table `validations_stock_matin`
--
ALTER TABLE `validations_stock_matin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_site_date` (`site_id`,`date_validation`),
  ADD KEY `gsb_user_id` (`gsb_user_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `affectations_equipements`
--
ALTER TABLE `affectations_equipements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=376;

--
-- AUTO_INCREMENT pour la table `bilans_mensuels_bobines`
--
ALTER TABLE `bilans_mensuels_bobines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `commandes`
--
ALTER TABLE `commandes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `commande_lignes`
--
ALTER TABLE `commande_lignes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `comparaisons_stock`
--
ALTER TABLE `comparaisons_stock`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `configurations_site`
--
ALTER TABLE `configurations_site`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT pour la table `config_postes_composants`
--
ALTER TABLE `config_postes_composants`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `config_postes_types`
--
ALTER TABLE `config_postes_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `consommables`
--
ALTER TABLE `consommables`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `consommations_bobines`
--
ALTER TABLE `consommations_bobines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `delegations`
--
ALTER TABLE `delegations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `demandes_bobines`
--
ALTER TABLE `demandes_bobines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `ecarts_bobines`
--
ALTER TABLE `ecarts_bobines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT pour la table `equipements`
--
ALTER TABLE `equipements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT pour la table `import_optoplate`
--
ALTER TABLE `import_optoplate`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `import_optotrace`
--
ALTER TABLE `import_optotrace`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `interventions_maintenance`
--
ALTER TABLE `interventions_maintenance`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `inventaires_bobines`
--
ALTER TABLE `inventaires_bobines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `inventaire_details_bobines`
--
ALTER TABLE `inventaire_details_bobines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT pour la table `litige_messages`
--
ALTER TABLE `litige_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `livraisons_consommables`
--
ALTER TABLE `livraisons_consommables`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `mouvements_bobines`
--
ALTER TABLE `mouvements_bobines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `mouvements_equipements`
--
ALTER TABLE `mouvements_equipements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT pour la table `nomenclatures`
--
ALTER TABLE `nomenclatures`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `nomenclature_liens`
--
ALTER TABLE `nomenclature_liens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `op_bobines`
--
ALTER TABLE `op_bobines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `op_films_utilises`
--
ALTER TABLE `op_films_utilises`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT pour la table `op_points_journaliers`
--
ALTER TABLE `op_points_journaliers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pour la table `op_stock_rivets`
--
ALTER TABLE `op_stock_rivets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT pour la table `op_types_vehicule`
--
ALTER TABLE `op_types_vehicule`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=579;

--
-- AUTO_INCREMENT pour la table `points_emuci`
--
ALTER TABLE `points_emuci`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `points_journaliers_info`
--
ALTER TABLE `points_journaliers_info`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `rapports_journaliers_info`
--
ALTER TABLE `rapports_journaliers_info`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `receptions_consommables`
--
ALTER TABLE `receptions_consommables`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `receptions_site`
--
ALTER TABLE `receptions_site`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT pour la table `sites`
--
ALTER TABLE `sites`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `stock_consommables_site`
--
ALTER TABLE `stock_consommables_site`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `stock_pmma`
--
ALTER TABLE `stock_pmma`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `stock_pmma_site`
--
ALTER TABLE `stock_pmma_site`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `support_it_roles`
--
ALTER TABLE `support_it_roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `validations_stock_matin`
--
ALTER TABLE `validations_stock_matin`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `affectations_equipements`
--
ALTER TABLE `affectations_equipements`
  ADD CONSTRAINT `affectations_equipements_ibfk_1` FOREIGN KEY (`equipement_id`) REFERENCES `equipements` (`id`),
  ADD CONSTRAINT `affectations_equipements_ibfk_2` FOREIGN KEY (`site_dest_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `affectations_equipements_ibfk_3` FOREIGN KEY (`user_dest_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `affectations_equipements_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `affectations_equipements_ibfk_5` FOREIGN KEY (`valide_n1_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `bilans_mensuels_bobines`
--
ALTER TABLE `bilans_mensuels_bobines`
  ADD CONSTRAINT `bilans_mensuels_bobines_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `bilans_mensuels_bobines_ibfk_2` FOREIGN KEY (`inventaire_id`) REFERENCES `inventaires_bobines` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bilans_mensuels_bobines_ibfk_3` FOREIGN KEY (`valide_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD CONSTRAINT `commandes_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `commande_lignes`
--
ALTER TABLE `commande_lignes`
  ADD CONSTRAINT `commande_lignes_ibfk_1` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `comparaisons_stock`
--
ALTER TABLE `comparaisons_stock`
  ADD CONSTRAINT `comparaisons_stock_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `comparaisons_stock_ibfk_2` FOREIGN KEY (`ajuste_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `config_postes_composants`
--
ALTER TABLE `config_postes_composants`
  ADD CONSTRAINT `config_postes_composants_ibfk_1` FOREIGN KEY (`poste_id`) REFERENCES `config_postes_types` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `config_postes_composants_ibfk_2` FOREIGN KEY (`nomenclature_id`) REFERENCES `nomenclatures` (`id`);

--
-- Contraintes pour la table `consommations_bobines`
--
ALTER TABLE `consommations_bobines`
  ADD CONSTRAINT `consommations_bobines_ibfk_1` FOREIGN KEY (`bobine_id`) REFERENCES `op_bobines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `consommations_bobines_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `consommations_bobines_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `delegations`
--
ALTER TABLE `delegations`
  ADD CONSTRAINT `delegations_ibfk_1` FOREIGN KEY (`superviseur_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `delegations_ibfk_2` FOREIGN KEY (`gestionnaire_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `demandes_bobines`
--
ALTER TABLE `demandes_bobines`
  ADD CONSTRAINT `demandes_bobines_ibfk_1` FOREIGN KEY (`bobine_id`) REFERENCES `op_bobines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `demandes_bobines_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `demandes_bobines_ibfk_3` FOREIGN KEY (`demande_par`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `demandes_bobines_ibfk_4` FOREIGN KEY (`traite_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `ecarts_bobines`
--
ALTER TABLE `ecarts_bobines`
  ADD CONSTRAINT `ecarts_bobines_ibfk_1` FOREIGN KEY (`bobine_id`) REFERENCES `op_bobines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ecarts_bobines_ibfk_2` FOREIGN KEY (`inventaire_id`) REFERENCES `inventaires_bobines` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ecarts_bobines_ibfk_3` FOREIGN KEY (`resolu_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ecarts_bobines_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `equipements`
--
ALTER TABLE `equipements`
  ADD CONSTRAINT `equipements_ibfk_1` FOREIGN KEY (`nomenclature_id`) REFERENCES `nomenclatures` (`id`),
  ADD CONSTRAINT `equipements_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `equipements_ibfk_3` FOREIGN KEY (`utilisateur_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `equipements_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `import_optoplate`
--
ALTER TABLE `import_optoplate`
  ADD CONSTRAINT `import_optoplate_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `import_optoplate_ibfk_2` FOREIGN KEY (`importe_par`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `import_optotrace`
--
ALTER TABLE `import_optotrace`
  ADD CONSTRAINT `import_optotrace_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `import_optotrace_ibfk_2` FOREIGN KEY (`importe_par`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `import_sessions_emuci`
--
ALTER TABLE `import_sessions_emuci`
  ADD CONSTRAINT `import_sessions_emuci_ibfk_1` FOREIGN KEY (`importe_par`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `interventions_maintenance`
--
ALTER TABLE `interventions_maintenance`
  ADD CONSTRAINT `interventions_maintenance_ibfk_1` FOREIGN KEY (`technicien_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `interventions_maintenance_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `interventions_maintenance_ibfk_3` FOREIGN KEY (`equipement_id`) REFERENCES `equipements` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `inventaires_bobines`
--
ALTER TABLE `inventaires_bobines`
  ADD CONSTRAINT `inventaires_bobines_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventaires_bobines_ibfk_2` FOREIGN KEY (`cree_par`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventaires_bobines_ibfk_3` FOREIGN KEY (`valide_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `inventaire_details_bobines`
--
ALTER TABLE `inventaire_details_bobines`
  ADD CONSTRAINT `inventaire_details_bobines_ibfk_1` FOREIGN KEY (`inventaire_id`) REFERENCES `inventaires_bobines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventaire_details_bobines_ibfk_2` FOREIGN KEY (`bobine_id`) REFERENCES `op_bobines` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `litige_messages`
--
ALTER TABLE `litige_messages`
  ADD CONSTRAINT `litige_messages_ibfk_1` FOREIGN KEY (`reception_id`) REFERENCES `receptions_site` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `litige_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `livraisons_consommables`
--
ALTER TABLE `livraisons_consommables`
  ADD CONSTRAINT `livraisons_consommables_ibfk_1` FOREIGN KEY (`consommable_id`) REFERENCES `consommables` (`id`),
  ADD CONSTRAINT `livraisons_consommables_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `livraisons_consommables_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `mouvements_bobines`
--
ALTER TABLE `mouvements_bobines`
  ADD CONSTRAINT `mouvements_bobines_ibfk_1` FOREIGN KEY (`bobine_id`) REFERENCES `op_bobines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mouvements_bobines_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `mouvements_equipements`
--
ALTER TABLE `mouvements_equipements`
  ADD CONSTRAINT `mouvements_equipements_ibfk_1` FOREIGN KEY (`equipement_id`) REFERENCES `equipements` (`id`),
  ADD CONSTRAINT `mouvements_equipements_ibfk_2` FOREIGN KEY (`site_source_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `mouvements_equipements_ibfk_3` FOREIGN KEY (`site_dest_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `mouvements_equipements_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `nomenclature_liens`
--
ALTER TABLE `nomenclature_liens`
  ADD CONSTRAINT `nomenclature_liens_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `nomenclatures` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `nomenclature_liens_ibfk_2` FOREIGN KEY (`enfant_id`) REFERENCES `nomenclatures` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `op_bobines`
--
ALTER TABLE `op_bobines`
  ADD CONSTRAINT `op_bobines_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `op_bobines_ibfk_2` FOREIGN KEY (`type_vehicule_id`) REFERENCES `op_types_vehicule` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `op_films_utilises`
--
ALTER TABLE `op_films_utilises`
  ADD CONSTRAINT `op_films_utilises_ibfk_1` FOREIGN KEY (`point_id`) REFERENCES `op_points_journaliers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `op_films_utilises_ibfk_2` FOREIGN KEY (`bobine_id`) REFERENCES `op_bobines` (`id`),
  ADD CONSTRAINT `op_films_utilises_ibfk_3` FOREIGN KEY (`type_vehicule_id`) REFERENCES `op_types_vehicule` (`id`);

--
-- Contraintes pour la table `op_points_journaliers`
--
ALTER TABLE `op_points_journaliers`
  ADD CONSTRAINT `op_points_journaliers_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `op_points_journaliers_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `op_points_journaliers_ibfk_3` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `op_stock_rivets`
--
ALTER TABLE `op_stock_rivets`
  ADD CONSTRAINT `op_stock_rivets_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `permissions`
--
ALTER TABLE `permissions`
  ADD CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `points_emuci`
--
ALTER TABLE `points_emuci`
  ADD CONSTRAINT `points_emuci_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `points_emuci_ibfk_2` FOREIGN KEY (`saisi_par`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `points_emuci_ibfk_3` FOREIGN KEY (`valide_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `points_journaliers_info`
--
ALTER TABLE `points_journaliers_info`
  ADD CONSTRAINT `points_journaliers_info_ibfk_1` FOREIGN KEY (`technicien_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `points_journaliers_info_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `points_journaliers_info_ibfk_3` FOREIGN KEY (`valide_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `rapports_journaliers_info`
--
ALTER TABLE `rapports_journaliers_info`
  ADD CONSTRAINT `rapports_journaliers_info_ibfk_1` FOREIGN KEY (`technicien_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rapports_journaliers_info_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `rapports_journaliers_info_ibfk_3` FOREIGN KEY (`valide_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `receptions_consommables`
--
ALTER TABLE `receptions_consommables`
  ADD CONSTRAINT `receptions_consommables_ibfk_1` FOREIGN KEY (`consommable_id`) REFERENCES `consommables` (`id`),
  ADD CONSTRAINT `receptions_consommables_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `receptions_site`
--
ALTER TABLE `receptions_site`
  ADD CONSTRAINT `receptions_site_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `receptions_site_ibfk_2` FOREIGN KEY (`equipement_id`) REFERENCES `equipements` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `receptions_site_ibfk_3` FOREIGN KEY (`consommable_id`) REFERENCES `consommables` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `receptions_site_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `sites`
--
ALTER TABLE `sites`
  ADD CONSTRAINT `sites_ibfk_1` FOREIGN KEY (`responsable_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `stock_consommables_site`
--
ALTER TABLE `stock_consommables_site`
  ADD CONSTRAINT `stock_consommables_site_ibfk_1` FOREIGN KEY (`consommable_id`) REFERENCES `consommables` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_consommables_site_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `stock_pmma`
--
ALTER TABLE `stock_pmma`
  ADD CONSTRAINT `stock_pmma_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `stock_pmma_site`
--
ALTER TABLE `stock_pmma_site`
  ADD CONSTRAINT `stock_pmma_site_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `support_it_roles`
--
ALTER TABLE `support_it_roles`
  ADD CONSTRAINT `support_it_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_it_roles_ibfk_2` FOREIGN KEY (`affecte_par`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Contraintes pour la table `validations_stock_matin`
--
ALTER TABLE `validations_stock_matin`
  ADD CONSTRAINT `validations_stock_matin_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`),
  ADD CONSTRAINT `validations_stock_matin_ibfk_2` FOREIGN KEY (`gsb_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;


SQLEOF;

// Diviser en commandes individuelles
$commands = array_filter(array_map('trim', explode(';', $sql_commands)));
$success = 0;
$errors = [];

foreach ($commands as $cmd) {
    if (empty($cmd) || $cmd === '\n') continue;
    try {
        $db->exec($cmd);
        $success++;
    } catch (PDOException $e) {
        $errors[] = $e->getMessage() . ' — SQL: ' . substr($cmd, 0, 100);
    }
}

echo "<h2>Import terminé</h2>";
echo "<p>✅ $success commandes exécutées</p>";
if ($errors) {
    echo "<p>⚠️ " . count($errors) . " erreurs :</p><ul>";
    foreach (array_slice($errors, 0, 20) as $err) {
        echo "<li>" . htmlspecialchars($err) . "</li>";
    }
    echo "</ul>";
}
echo "<p><strong>SUPPRIMEZ CE FICHIER MAINTENANT : import.php</strong></p>";
?>
