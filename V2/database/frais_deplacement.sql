-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : mar. 09 déc. 2025 à 19:18
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `frais_deplacement`
--

-- --------------------------------------------------------

--
-- Structure de la table `categories_frais`
--

CREATE TABLE `categories_frais` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Déchargement des données de la table `categories_frais`
--

INSERT INTO `categories_frais` (`id`, `nom`) VALUES
(1, 'Transport'),
(2, 'Hébergement'),
(3, 'Restauration'),
(4, 'Parking'),
(5, 'Péage'),
(6, 'Autre'),
(7, 'ff');

-- --------------------------------------------------------

--
-- Structure de la table `demande_frais`
--

CREATE TABLE `demande_frais` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `objectif_mission` text NOT NULL,
  `lieu_mission` varchar(255) NOT NULL,
  `date_mission` date NOT NULL,
  `statut` enum('soumis','valide_manager','rejete_manager','valide_admin','rejete_admin','rembourse') DEFAULT 'soumis',
  `date_soumission` datetime DEFAULT current_timestamp(),
  `justificatif_principal` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Déchargement des données de la table `demande_frais`
--

INSERT INTO `demande_frais` (`id`, `user_id`, `objectif_mission`, `lieu_mission`, `date_mission`, `statut`, `date_soumission`, `justificatif_principal`) VALUES
(1, 2, 'Formation PHP avancée', 'Paris', '2025-12-01', 'valide_manager', '2025-11-23 17:47:33', NULL),
(2, 2, 'formation', 'casasss', '2025-11-22', 'rejete_manager', '2025-11-23 18:26:07', 'justif_692343afcc8267.77360679.pdf'),
(3, 2, 'yyyyy', 'paris', '2025-11-23', 'rembourse', '2025-11-23 19:43:39', 'justif_692355db9c13b1.59042519.pdf'),
(4, 2, 'aa', 'aa', '2025-11-25', 'soumis', '2025-11-26 19:36:21', NULL),
(5, 2, 'aa', 'aaa', '2025-11-27', 'valide_manager', '2025-11-26 20:35:33', 'justif_692756856a8ef4.22459784.pdf'),
(6, 8, 'aaa', 'aaa', '2025-11-28', 'rembourse', '2025-11-26 20:37:31', 'justif_692756fb0f4162.61975838.pdf'),
(7, 2, 'MESSION', 'casasss', '2025-11-26', 'rembourse', '2025-11-26 21:45:56', NULL),
(8, 2, 'o', 'u', '2025-11-12', 'valide_manager', '2025-11-26 22:47:29', NULL),
(9, 2, 'r', 'd', '2025-11-15', 'rembourse', '2025-11-26 22:54:26', NULL),
(10, 8, '11', 'cad', '2025-11-25', 'valide_manager', '2025-11-26 22:57:32', NULL),
(11, 8, 'aaaa', 'aaaa', '2025-11-28', 'rembourse', '2025-11-27 12:25:36', 'justif_69283530e5ca77.58139954.pdf'),
(12, 2, 'zaza', 'zaza', '2025-11-26', 'rembourse', '2025-11-27 21:28:52', 'justif_6928b484a72e43.21004919.pdf'),
(13, 8, 'kk', 'kk', '2025-11-20', 'rejete_admin', '2025-11-27 21:42:14', NULL),
(14, 2, 'formation java', 'Tanger', '2025-11-27', 'rembourse', '2025-12-01 20:16:19', 'justif_692de983c200d8.57754378.pdf'),
(15, 2, 'zaza', 'casa', '2025-12-01', 'rembourse', '2025-12-01 21:00:53', 'justif_692df3f570d446.04593867.pdf'),
(16, 2, 'zezezeze', 'casa', '2025-12-01', 'rembourse', '2025-12-01 21:47:59', 'justif_692dfeff220400.72718922.pdf'),
(17, 8, 'fff', 'ddd', '2025-12-02', 'soumis', '2025-12-01 22:20:39', 'justif_692e06a7c93595.64163033.png'),
(18, 8, 'fff', 'ddd', '2025-12-02', 'rembourse', '2025-12-01 22:29:27', 'justif_692e08b7791f94.00219785.png'),
(19, 8, 'nb', 'nb', '2025-12-01', 'soumis', '2025-12-02 11:21:13', 'justif_692ebd9960e292.30930166.pdf'),
(20, 8, 'ddd', 'ddd', '2025-12-07', 'soumis', '2025-12-07 13:36:58', 'justif_693574ea795f73.07972110.jpg'),
(21, 8, 'hhh', 'hhh', '2025-12-07', 'soumis', '2025-12-07 13:41:51', 'justif_6935760f6db178.25137039.jpg'),
(22, 8, 'kk', 'll', '2025-12-07', 'soumis', '2025-12-07 13:44:28', 'justif_693576ac07d130.15487024.jpg'),
(23, 8, 'kkkk', 'lllllll', '2025-12-07', 'soumis', '2025-12-07 15:22:15', 'justif_69358d9795b2a2.37107214.jpg'),
(24, 8, 'hh', 'hhh', '2025-12-07', 'soumis', '2025-12-07 16:01:03', 'justif_693596af57dd85.13654890.jpg'),
(25, 8, 'mm', 'nn', '2025-12-07', 'rembourse', '2025-12-07 16:09:00', 'justif_6935988c75ab79.35085866.jpg'),
(26, 8, 'ddddd', 'dd', '2025-12-07', 'soumis', '2025-12-07 16:11:50', 'justif_69359936d0ba51.23329788.jpg'),
(27, 8, 'sss', 'ssss', '2025-12-07', 'soumis', '2025-12-07 16:15:05', 'justif_693599f9c5c8d5.07792330.jpg'),
(28, 8, 'lllll', 'mmmmm', '2025-12-07', 'soumis', '2025-12-07 17:54:49', 'justif_6935b159dcd039.09902747.jpg'),
(29, 8, 'mm', 'nn', '2025-12-08', 'soumis', '2025-12-08 01:43:26', 'justif_69361f2ecec018.60973508.png'),
(30, 8, 'kkk', 'mmmm', '2025-12-09', 'soumis', '2025-12-09 16:10:15', 'justif_69383bd7140d97.78660609.png'),
(31, 8, 'hh', 'hh', '2025-12-09', 'valide_manager', '2025-12-09 17:42:08', 'justif_69385160494d72.10322581.png'),
(32, 8, 'lll', 'mm', '2025-12-09', 'soumis', '2025-12-09 18:36:24', 'justif_69385e186cf593.73593405.png'),
(33, 8, 'mmm', 'jjj', '2025-12-09', 'soumis', '2025-12-09 18:52:47', 'justif_693861eff0eac6.17482754.png'),
(34, 8, 'mm', 'nn', '2025-12-09', 'soumis', '2025-12-09 19:02:16', 'justif_6938642848c4b0.68523390.png'),
(35, 8, 'm', 'm', '2025-12-09', 'soumis', '2025-12-09 19:09:29', 'justif_693865d955cac0.00558869.png');

-- --------------------------------------------------------

--
-- Structure de la table `details_frais`
--

CREATE TABLE `details_frais` (
  `id` int(11) NOT NULL,
  `demande_id` int(11) NOT NULL,
  `categorie_id` int(11) NOT NULL,
  `moyen_transport` varchar(50) DEFAULT NULL,
  `date_depense` date NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'MAD',
  `description` text DEFAULT NULL,
  `point_depart` varchar(255) DEFAULT NULL,
  `lat_depart` decimal(10,8) DEFAULT NULL,
  `lng_depart` decimal(10,8) DEFAULT NULL,
  `point_arrivee` varchar(255) DEFAULT NULL,
  `lat_arrivee` decimal(10,8) DEFAULT NULL,
  `lng_arrivee` decimal(10,8) DEFAULT NULL,
  `justificatif` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Déchargement des données de la table `details_frais`
--

INSERT INTO `details_frais` (`id`, `demande_id`, `categorie_id`, `moyen_transport`, `date_depense`, `montant`, `currency`, `description`, `point_depart`, `lat_depart`, `lng_depart`, `point_arrivee`, `lat_arrivee`, `lng_arrivee`, `justificatif`) VALUES
(1, 1, 1, NULL, '2025-12-01', 45.50, 'MAD', 'Train aller-retour', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 1, 3, NULL, '2025-12-01', 25.00, 'MAD', 'Déjeuner', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 1, 1, NULL, '2025-12-01', 12.50, 'MAD', 'Métro', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 2, 1, NULL, '2025-11-23', 12.00, 'MAD', 'ddddd', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 2, 4, NULL, '2025-11-24', 74.00, 'MAD', '&\"\"\"\"', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 3, 2, NULL, '2025-11-24', 24.00, 'MAD', 'aaaaaaaaaaaaaaaaaaaaaaaa', NULL, NULL, NULL, NULL, NULL, NULL, 'detail_692355db9ce095.64384291.pdf'),
(8, 4, 2, NULL, '2025-11-05', 12.00, 'MAD', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 5, 2, NULL, '2025-11-28', 12.00, 'MAD', '', NULL, NULL, NULL, NULL, NULL, NULL, 'detail_692756856c49a4.07145608.pdf'),
(10, 6, 2, NULL, '2025-11-28', 14.00, 'MAD', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 7, 2, NULL, '2025-11-20', 122.00, 'MAD', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(12, 8, 7, NULL, '2025-11-27', 122.00, 'MAD', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(13, 9, 2, NULL, '2025-11-27', 11.00, 'MAD', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(14, 10, 4, NULL, '2025-11-25', 12.00, 'MAD', '', NULL, NULL, NULL, NULL, NULL, NULL, 'detail_692777cc021071.28993157.pdf'),
(15, 11, 2, NULL, '2025-11-27', 12.00, 'MAD', '', NULL, NULL, NULL, NULL, NULL, NULL, 'detail_69283530e8e435.79655877.pdf'),
(18, 12, 1, NULL, '2025-11-26', 33260.40, 'MAD', 'Distance calculée : 277.17 km (aller-retour : 554.34 km) – Montant estimé : 33260.4 DH', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, 13, 1, NULL, '2025-11-20', 72787.20, 'MAD', 'Distance calculée : 606.56 km (aller-retour : 1213.12 km) – Montant estimé : 72787.2 DH', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(21, 14, 1, NULL, '2025-11-27', 413.03, 'MAD', 'Distance calculée : 344.19 km (aller-retour : 688.38 km) – Montant estimé : 413.03 DH', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, 14, 1, NULL, '2025-11-28', 500.00, 'MAD', '', NULL, NULL, NULL, NULL, NULL, NULL, 'detail_692de983c54846.96428683.pdf'),
(23, 14, 4, NULL, '2025-11-28', 20.00, 'MAD', '', NULL, NULL, NULL, NULL, NULL, NULL, 'detail_692de983c753b0.29228442.pdf'),
(24, 15, 4, NULL, '2025-12-01', 50.00, 'MAD', '', NULL, NULL, NULL, NULL, NULL, NULL, 'detail_692df3f5716315.57311801.pdf'),
(25, 16, 2, NULL, '2025-12-01', 500.00, 'MAD', '', NULL, NULL, NULL, NULL, NULL, NULL, 'detail_692dfeff22f4a0.41379783.pdf'),
(26, 17, 1, NULL, '2025-12-02', 120.00, 'MAD', '', NULL, NULL, NULL, NULL, NULL, NULL, 'detail_692e06a7c9f6e2.37800275.pdf'),
(27, 18, 1, NULL, '2025-12-02', 364.51, 'MAD', 'Distance calculée : 303.76 km (aller-retour : 607.52 km) – Montant estimé : 364.51 DH', NULL, NULL, NULL, NULL, NULL, NULL, 'detail_692e08b779a5b4.78497290.png'),
(31, 19, 1, NULL, '2025-12-01', 24.49, 'MAD', 'Distance calculée : 20.41 km (aller-retour : 40.82 km) – Montant estimé : 24.49 DH', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(42, 20, 2, NULL, '2025-12-07', 770.00, 'EUR', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(43, 20, 1, 'voiture', '2025-12-07', 4.68, 'MAD', '', 'D 139, Couzon, Moulins', 46.65119635, 3.11929054, 'D 13, Couzon, Moulins', 46.65464245, 3.13324760, NULL),
(51, 22, 1, 'voiture', '2025-12-07', 1194.82, 'MAD', 'Distance calculée : 497.84 km (aller-retour : 995.68 km) – Montant estimé : 1194.82 DH', 'Ruffigné, Châteaubriant-Ancenis', 47.76356077, -1.49879479, 'Route d\'Auxerre, Chamoy, Troyes', 48.11682954, 3.95238815, NULL),
(52, 23, 1, 'voiture', '2025-12-07', 905.78, 'MAD', 'Distance calculée : 377.41 km (aller-retour : 754.82 km) – Montant estimé : 905.78 DH', 'Quai de la Loire, Savigny-en-Véron, Chinon', 47.21420466, 0.08380671, 'Joux-la-Ville, Avallon', 47.62444646, 3.82050470, NULL),
(62, 21, 1, 'voiture', '2025-12-07', 1591.42, 'MAD', 'Distance calculée : 663.09 km (aller-retour : 1326.18 km) – Montant estimé : 1591.42 DH\r\nDépart : Voie Verte La Flèche - Baugé, La Flèche\r\nArrivée : Chemin de la Nolle, Saint-Dié-des-Vosges', 'Voie Verte La Flèche - Baugé, La Flèche', 47.66006917, -0.04807675, 'Chemin de la Nolle, Saint-Dié-des-Vosges', 48.27793796, 6.94174654, NULL),
(63, 21, 3, NULL, '2025-12-07', 770.00, 'EUR', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(64, 24, 1, 'voiture', '2025-12-07', 2292.53, 'MAD', 'Distance calculée : 955.22 km (aller-retour : 1910.44 km) – Montant estimé : 2292.53 DH\r\nDépart : Lieu sélectionné (offline) - 47.497024, 0.765205\r\nArrivée : Lieu sélectionné (offline) - 49.680958, 10.453325', 'Lieu sélectionné (offline) - 47.497024, 0.765205', 47.49702417, 0.76520458, 'Lieu sélectionné (offline) - 49.680958, 10.453325', 49.68095833, 10.45332507, NULL),
(65, 25, 4, NULL, '2025-12-07', 880.00, 'EUR', '', NULL, NULL, NULL, NULL, NULL, NULL, 'detail_6935988c767184.53269259.jpg'),
(68, 27, 1, 'voiture', '2025-12-07', 1322.52, 'MAD', 'Distance calculée : 551.05 km (aller-retour : 1102.1 km) – Montant estimé : 1322.52 DH\r\nDépart : Lieu sélectionné (offline) - 47.822607, 6.084504\r\nArrivée : Lieu sélectionné (offline) - 47.822607, 0.215690', 'Lieu sélectionné (offline) - 47.822607, 6.084504', 47.82260656, 6.08450406, 'Lieu sélectionné (offline) - 47.822607, 0.215690', 47.82260656, 0.21569017, NULL),
(69, 26, 1, 'voiture', '2025-12-07', 191.26, 'MAD', 'Distance calculée : 79.69 km (aller-retour : 159.38 km) – Montant estimé : 191.26 DH\r\nDépart : C 3, Soudan, Châteaubriant-Ancenis\r\nArrivée : Bouère, Château-Gontier', 'C 3, Soudan, Châteaubriant-Ancenis', 47.71019470, -1.25848070, 'Bouère, Château-Gontier', 47.83067401, -0.49317334, NULL),
(70, 28, 1, 'voiture', '2025-12-07', 1446.72, 'MAD', 'Distance calculée : 602.8 km (aller-retour : 1205.6 km) – Montant estimé : 1446.72 DH', 'La Lande-Chasles, Saumur', 47.45246923, -0.09203790, 'Chemin de Dombasle, Rouvres-en-Xaintois, Neufchâteau', 48.29255906, 6.01856234, NULL),
(71, 29, 1, 'voiture', '2025-12-08', 1269.94, 'MAD', 'Distance calculée : 529.14 km (aller-retour : 1058.28 km) – Montant estimé : 1269.94 DH', 'Chemin du Bois d\'Amont, La Roche-Blanche, Châteaubriant-Ancenis', 47.45246923, -1.12512499, 'Bagneux-la-Fosse, Troyes', 47.99336814, 4.28209680, NULL),
(74, 31, 1, 'voiture', '2025-12-09', 20.54, 'MAD', 'Distance calculée : 8.56 km (aller-retour : 17.12 km) – Montant estimé : 20.54 DH\r\nDépart : D 266, Neuilly-l\'Évêque, Langres\r\nArrivée : D 280, Plesnoy, Langres', 'D 266, Neuilly-l\'Évêque, Langres', 47.90711744, 5.44266600, 'D 280, Plesnoy, Langres', 47.88861430, 5.50260380, NULL),
(76, 30, 1, 'voiture', '2025-12-09', 700.20, 'MAD', 'Distance calculée : 291.75 km (aller-retour : 583.5 km) – Montant estimé : 700.2 DH\r\nDépart : D 41, Ardentes, Châteauroux\r\nArrivée : Chemin des Lilas, Charrecey, Chalon-sur-Saône', 'D 41, Ardentes, Châteauroux', 46.70432114, 1.86423340, 'Chemin des Lilas, Charrecey, Chalon-sur-Saône', 46.84604501, 4.66851102, NULL),
(80, 32, 1, 'voiture', '2025-12-09', 1407.34, 'MAD', 'Distance calculée : 586.39 km (aller-retour : 1172.78 km) – Montant estimé : 1407.34 DH\r\nDépart : Lieu sélectionné (offline) - 47.660069, -2.531882\r\nArrivée : Lieu sélectionné (offline) - 47.999341, 3.600699', 'Lieu sélectionné (offline) - 47.660069, -2.531882', 47.66006917, -2.53188188, 'Lieu sélectionné (offline) - 47.999341, 3.600699', 47.99934149, 3.60069893, NULL),
(83, 33, 1, 'voiture', '2025-12-09', 788.11, 'MAD', 'Distance calculée : 328.38 km (aller-retour : 656.76 km) – Montant estimé : 788.11 DH\r\nDépart : Ain Mediouna, Caïdat d’Ain mediouna\r\nArrivée : RP4301, Brachoua', 'Ain Mediouna, Caïdat d’Ain mediouna', 34.47939197, -4.52683448, 'RP4301, Brachoua', 33.69635141, -6.68579610, NULL),
(84, 34, 1, 'voiture', '2025-12-09', 1644.94, 'MAD', 'Distance calculée : 685.39 km (aller-retour : 1370.78 km) – Montant estimé : 1644.94 DH', 'Chémery, Romorantin-Lanthenay', 47.34836075, 1.46858302, 'Sägegrabenweg, Geschwend, Todtnau', 47.80785140, 7.95285306, NULL),
(86, 35, 1, 'voiture', '2025-12-09', 1490.42, 'MAD', 'Distance calculée : 621.01 km (aller-retour : 1242.02 km) – Montant estimé : 1490.42 DH\r\nDépart : Prahecq, Niort\r\nArrivée : Chemin de Marcevaux, Is-sur-Tille, Dijon', 'Prahecq, Niort', 46.25038665, -0.33382424, 'Chemin de Marcevaux, Is-sur-Tille, Dijon', 47.54154135, 5.07339755, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `historique_statuts`
--

CREATE TABLE `historique_statuts` (
  `id` int(11) NOT NULL,
  `demande_id` int(11) NOT NULL,
  `statut` enum('soumis','valide_manager','rejete_manager','valide_admin','rejete_admin','rembourse') NOT NULL,
  `user_id` int(11) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `date_changement` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Déchargement des données de la table `historique_statuts`
--

INSERT INTO `historique_statuts` (`id`, `demande_id`, `statut`, `user_id`, `commentaire`, `date_changement`) VALUES
(1, 2, 'soumis', 2, 'Demande créée et soumise', '2025-11-23 18:26:07'),
(2, 2, 'rejete_manager', 3, 'jjjj', '2025-11-23 18:41:04'),
(3, 3, 'soumis', 2, 'Demande créée et soumise', '2025-11-23 19:43:39'),
(4, 3, 'valide_manager', 3, 'Demande validée par le manager', '2025-11-23 20:07:25'),
(5, 3, 'rembourse', 7, 'Statut modifié par l\'administrateur', '2025-11-23 21:06:10'),
(6, 1, 'valide_manager', 3, 'Demande validée par le manager', '2025-11-23 22:03:13'),
(7, 4, 'soumis', 2, 'Demande créée et soumise', '2025-11-26 19:36:21'),
(8, 5, 'soumis', 2, 'Demande créée et soumise', '2025-11-26 20:35:33'),
(9, 6, 'soumis', 8, 'Demande créée et soumise', '2025-11-26 20:37:31'),
(10, 6, 'valide_manager', 3, 'Demande validée par le manager', '2025-11-26 20:38:32'),
(11, 6, 'rembourse', 7, 'Statut modifié par l\'administrateur', '2025-11-26 20:42:20'),
(12, 7, 'soumis', 2, 'Demande créée et soumise', '2025-11-26 21:45:56'),
(13, 7, 'valide_manager', 3, 'Demande validée par le manager', '2025-11-26 21:47:11'),
(14, 5, 'valide_manager', 3, 'Demande validée par le manager', '2025-11-26 21:54:43'),
(15, 7, 'rembourse', 7, 'Statut modifié par l\'administrateur', '2025-11-26 22:29:38'),
(16, 8, 'soumis', 2, 'Demande créée et soumise', '2025-11-26 22:47:29'),
(17, 8, 'valide_manager', 3, 'Demande validée par le manager', '2025-11-26 22:49:19'),
(18, 9, 'soumis', 2, 'Demande créée et soumise', '2025-11-26 22:54:26'),
(19, 9, 'valide_manager', 3, 'Demande validée par le manager', '2025-11-26 22:54:55'),
(20, 9, 'rembourse', 7, 'Statut modifié par l\'administrateur', '2025-11-26 22:55:41'),
(21, 10, 'soumis', 8, 'Demande créée et soumise', '2025-11-26 22:57:32'),
(22, 10, 'valide_manager', 3, 'Demande validée par le manager', '2025-11-26 22:58:12'),
(23, 11, 'soumis', 8, 'Demande créée et soumise', '2025-11-27 12:25:36'),
(24, 11, 'valide_manager', 3, 'Demande validée par le manager', '2025-11-27 12:26:20'),
(25, 11, 'rembourse', 7, 'Statut modifié par l\'administrateur', '2025-11-27 12:28:11'),
(26, 11, 'rejete_admin', 7, 'Statut modifié par l\'administrateur', '2025-11-27 12:30:22'),
(27, 11, 'rembourse', 7, 'Statut modifié par l\'administrateur', '2025-11-27 12:56:13'),
(28, 11, 'rejete_admin', 7, 'Statut modifié par l\'administrateur', '2025-11-27 13:07:44'),
(29, 11, 'rembourse', 7, 'Statut modifié par l\'administrateur', '2025-11-27 20:36:35'),
(30, 11, 'rejete_admin', 7, 'Statut modifié par l\'administrateur', '2025-11-27 21:00:55'),
(31, 11, 'rembourse', 7, 'Statut modifié par l\'administrateur', '2025-11-27 21:05:46'),
(32, 12, 'soumis', 2, 'Demande créée et soumise', '2025-11-27 21:28:52'),
(33, 13, 'soumis', 8, 'Demande créée et soumise', '2025-11-27 21:42:14'),
(34, 14, 'soumis', 2, 'Demande créée et soumise', '2025-12-01 20:16:19'),
(35, 14, 'valide_manager', 3, 'Demande validée par le manager', '2025-12-01 20:23:04'),
(36, 14, 'rembourse', 7, 'Statut modifié par l\'administrateur', '2025-12-01 20:26:08'),
(37, 15, 'soumis', 2, 'Demande créée et soumise', '2025-12-01 21:00:53'),
(38, 15, 'valide_manager', 3, 'Demande validée par le manager', '2025-12-01 21:01:13'),
(39, 13, 'valide_manager', 3, 'Demande validée par le manager', '2025-12-01 21:35:39'),
(40, 16, 'soumis', 2, 'Demande créée et soumise', '2025-12-01 21:47:59'),
(41, 16, 'valide_manager', 3, 'Demande validée par le manager', '2025-12-01 21:48:12'),
(42, 16, 'rembourse', 7, 'Demande approuvée et remboursée par l\'administrateur', '2025-12-01 21:48:51'),
(43, 12, 'valide_manager', 3, 'Demande validée par le manager', '2025-12-01 21:51:15'),
(44, 12, 'rembourse', 7, 'Demande approuvée et remboursée par l\'administrateur', '2025-12-01 22:09:06'),
(45, 15, 'rembourse', 7, 'Demande approuvée et remboursée par l\'administrateur', '2025-12-01 22:10:19'),
(46, 13, 'rejete_admin', 7, 'Demande rejetée définitivement par l\'administrateur', '2025-12-01 22:10:36'),
(47, 17, 'soumis', 8, 'Demande créée et soumise', '2025-12-01 22:20:39'),
(48, 18, 'soumis', 8, 'Demande créée et soumise', '2025-12-01 22:29:27'),
(49, 18, 'valide_manager', 3, 'Demande validée par le manager', '2025-12-01 22:31:21'),
(50, 18, 'rembourse', 7, 'Demande approuvée et remboursée par l\'administrateur', '2025-12-01 22:31:44'),
(51, 19, 'soumis', 8, 'Demande créée et soumise', '2025-12-02 11:21:13'),
(52, 20, 'soumis', 8, 'Demande créée et soumise', '2025-12-07 13:36:58'),
(53, 21, 'soumis', 8, 'Demande créée et soumise', '2025-12-07 13:41:51'),
(54, 22, 'soumis', 8, 'Demande créée et soumise', '2025-12-07 13:44:28'),
(55, 23, 'soumis', 8, 'Demande créée et soumise', '2025-12-07 15:22:15'),
(56, 24, 'soumis', 8, 'Demande créée et soumise', '2025-12-07 16:01:03'),
(57, 25, 'soumis', 8, 'Demande créée et soumise', '2025-12-07 16:09:00'),
(58, 25, 'valide_manager', 3, 'Demande validée par le manager', '2025-12-07 16:09:18'),
(59, 25, 'rembourse', 7, 'Demande approuvée et remboursée par l\'administrateur', '2025-12-07 16:09:44'),
(60, 26, 'soumis', 8, 'Demande créée et soumise', '2025-12-07 16:11:50'),
(61, 27, 'soumis', 8, 'Demande créée et soumise', '2025-12-07 16:15:05'),
(62, 28, 'soumis', 8, 'Demande créée et soumise', '2025-12-07 17:54:49'),
(63, 29, 'soumis', 8, 'Demande créée et soumise', '2025-12-08 01:43:26'),
(64, 30, 'soumis', 8, 'Demande créée et soumise', '2025-12-09 16:10:15'),
(65, 31, 'soumis', 8, 'Demande créée et soumise', '2025-12-09 17:42:08'),
(66, 31, 'valide_manager', 3, 'Demande validée par le manager', '2025-12-09 18:29:46'),
(67, 32, 'soumis', 8, 'Demande créée et soumise', '2025-12-09 18:36:24'),
(68, 33, 'soumis', 8, 'Demande créée et soumise', '2025-12-09 18:52:47'),
(69, 34, 'soumis', 8, 'Demande créée et soumise', '2025-12-09 19:02:16'),
(70, 35, 'soumis', 8, 'Demande créée et soumise', '2025-12-09 19:09:29');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('employe','manager','admin') NOT NULL,
  `manager_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `nom`, `email`, `mot_de_passe`, `role`, `manager_id`) VALUES
(2, 'Jean Dupont', 'employe@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employe', 3),
(3, 'Jean Dupont', 'manager@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', NULL),
(4, 'Marie Martin', 'marie.martin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employe', 3),
(5, 'Pierre Durand', 'pierre.durand@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employe', 3),
(7, 'Admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL),
(8, 'Anwar Zahoui', 'anwar512zahoui@gmail.com', '$2y$10$D9WqwInYyFbsSguHqWnbjeOG2obGJXovcAkD7HwZidO9wCt.CGaO2', 'employe', 3),
(9, 'Zahoui Anwar', 'haha@haha.com', '$2y$10$xgLhYSuHjd9UnlZQ9q33BOH8nmiZA6bKF9kextqlM2yOMET1b13vK', 'employe', 3),
(10, 'Zahoui Anwar', 'haha1@haha.com', '$2y$10$FGMpk1C1ZlCQ.crPMyWGK.E9wwk58wsm4iu0jNBUlb2PHwZ6ZK2JK', 'employe', 3);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `categories_frais`
--
ALTER TABLE `categories_frais`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `demande_frais`
--
ALTER TABLE `demande_frais`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `details_frais`
--
ALTER TABLE `details_frais`
  ADD PRIMARY KEY (`id`),
  ADD KEY `demande_id` (`demande_id`),
  ADD KEY `categorie_id` (`categorie_id`);

--
-- Index pour la table `historique_statuts`
--
ALTER TABLE `historique_statuts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `demande_id` (`demande_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `manager_id` (`manager_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `categories_frais`
--
ALTER TABLE `categories_frais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `demande_frais`
--
ALTER TABLE `demande_frais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT pour la table `details_frais`
--
ALTER TABLE `details_frais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT pour la table `historique_statuts`
--
ALTER TABLE `historique_statuts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `demande_frais`
--
ALTER TABLE `demande_frais`
  ADD CONSTRAINT `demande_frais_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `details_frais`
--
ALTER TABLE `details_frais`
  ADD CONSTRAINT `details_frais_ibfk_1` FOREIGN KEY (`demande_id`) REFERENCES `demande_frais` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `details_frais_ibfk_2` FOREIGN KEY (`categorie_id`) REFERENCES `categories_frais` (`id`);

--
-- Contraintes pour la table `historique_statuts`
--
ALTER TABLE `historique_statuts`
  ADD CONSTRAINT `historique_statuts_ibfk_1` FOREIGN KEY (`demande_id`) REFERENCES `demande_frais` (`id`),
  ADD CONSTRAINT `historique_statuts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
