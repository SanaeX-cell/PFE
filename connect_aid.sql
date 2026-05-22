-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : sam. 23 mai 2026 à 01:21
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
-- Base de données : `connect_aid`
--

-- --------------------------------------------------------

--
-- Structure de la table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `admins`
--

INSERT INTO `admins` (`id`, `email`, `mot_de_passe`, `date_creation`) VALUES
(3, 'admin@connectaid.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-05-16 01:23:18');

-- --------------------------------------------------------

--
-- Structure de la table `contact_principal`
--

CREATE TABLE `contact_principal` (
  `id` int(11) NOT NULL,
  `organisation_id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `fonction` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `contact_principal`
--

INSERT INTO `contact_principal` (`id`, `organisation_id`, `nom`, `fonction`, `email`, `telephone`) VALUES
(1, 1, 'yassmine', 'Président', 'yassmineelouahabi96@gmail.com', '0603284021'),
(2, 4, 'sanae', 'Coordinateur', 'sanae@gmail.com', '0789543265'),
(3, 5, 'Ahmed', 'Directeur', 'ahmed@gmail.com', '0600000000'),
(4, 6, 'Samir el khaldi', 'Directeur', 'elkhaldi@gmail.com', '0701010100'),
(5, 7, 'salma', 'Responsable RH', 'salma@gmail.com', '');

-- --------------------------------------------------------

--
-- Structure de la table `contributeurs`
--

CREATE TABLE `contributeurs` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `date_inscription` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `contributeurs`
--

INSERT INTO `contributeurs` (`id`, `nom`, `prenom`, `email`, `telephone`, `mot_de_passe`, `date_inscription`) VALUES
(2, 'Ahmed', 'chakroun', 'ahmed_chakroun54@gmail.com', '0600000000', '$2y$10$JKUBrVZG72OVcsxXSgPJI.duZpvuKuUPTJ.aQuI8usNbplL8cZvNS', '2026-05-22 14:19:28'),
(3, 'salman', 'ibrahimi', 'ibrahimi@gmail.com', '0798765435', '$2y$10$ZKK/0gxXzmYEalWEb5vV2uzK5.CFFvLJ2qjSC9F.o.RNHWaZbYqfK', '2026-05-23 00:09:20');

-- --------------------------------------------------------

--
-- Structure de la table `followers`
--

CREATE TABLE `followers` (
  `id` int(11) NOT NULL,
  `organisation_id` int(11) NOT NULL,
  `contributeur_id` int(11) NOT NULL,
  `date_follow` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `likes`
--

CREATE TABLE `likes` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `contributeur_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `expediteur_id` int(11) NOT NULL,
  `expediteur_type` enum('contributeur','organisation') NOT NULL,
  `destinataire_id` int(11) NOT NULL,
  `destinataire_type` enum('contributeur','organisation') NOT NULL,
  `message` text NOT NULL,
  `date_envoi` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications_contributeur`
--

CREATE TABLE `notifications_contributeur` (
  `id` int(11) NOT NULL,
  `contributeur_id` int(11) NOT NULL,
  `organisation_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `type` enum('accepte','refuse') NOT NULL,
  `message` varchar(255) NOT NULL,
  `statut` enum('non_lue','lue') DEFAULT 'non_lue',
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications_organisation`
--

CREATE TABLE `notifications_organisation` (
  `id` int(11) NOT NULL,
  `organisation_id` int(11) NOT NULL,
  `contributeur_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `type` enum('volontariat','donation','follow') NOT NULL,
  `message` varchar(255) NOT NULL,
  `statut` enum('non_lue','lue') DEFAULT 'non_lue',
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `organisations`
--

CREATE TABLE `organisations` (
  `id` int(11) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `nom_organisation` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `site_web` varchar(255) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `statut` enum('en_attente','valide','refuse') DEFAULT 'en_attente',
  `justificatif` varchar(255) DEFAULT NULL,
  `date_inscription` datetime DEFAULT current_timestamp(),
  `email_connexion` varchar(150) NOT NULL,
  `logo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `organisations`
--

INSERT INTO `organisations` (`id`, `mot_de_passe`, `nom_organisation`, `description`, `adresse`, `telephone`, `site_web`, `region`, `statut`, `justificatif`, `date_inscription`, `email_connexion`, `logo`) VALUES
(1, '$2y$10$OKWwh9mG1dQB5jOeuo.HluQGh8ftH0AqWnKOS1lwRFV3XaVxNMjma', 'Organisation Dar al Aytam', 'une organisation qui aide les orphelines', 'Tétouan, Pachalik de Tétouan, Province de Tétouan, Tanger-Tétouan-Al Hoceïma, Maroc', '0503284021', '', 'Tanger-Tétouan-Al Hoceïma', 'valide', 'uploads/6a0e43081babc.jpg', '2026-05-21 00:26:00', 'yassmineelouahabi15@gmail.com', 'uploads/6a0e43081a904.png'),
(4, '$2y$10$urQoFlnNrzUUEIsyKk41euBEAxSkvz3iQVPrqEiWSDkJIS/R3T7oW', 'دار المسنين', '', 'Avenue Mauritanie, Bario, Médina, Tétouan, Pachalik de Tétouan, Province de Tétouan, Tanger-Tétouan-Al Hoceïma, 93020, Maroc', '0598760932', '', 'Tanger-Tétouan-Al Hoceïma', 'en_attente', 'uploads/6a0fa512d77a5.pdf', '2026-05-22 01:36:35', 'association@gmail.com', ''),
(5, '$2y$10$.lBjMCZZKixcsIQ.w1601utuKy6saRho18Q3oJ54tZQYDSfWbsN2u', 'Association al kayr', '', 'Fès, Pachalik du Fes, Préfecture de Fès, Fès-Meknès, Maroc', '0500000000', '', 'Fès-Meknès', 'en_attente', 'uploads/6a0fbec317d72.pdf', '2026-05-22 03:26:11', 'alkayr@gmail.ma', ''),
(6, '$2y$10$HT69nAEZe9bZUAo6jOR2Ieb..1ahBIxRYOhaRcdqek38d5qXrs3ri', 'جمعية السلام', '', 'Ain Johra-Sidi Boukhalkhal, caïdat de Ain Johra-Boukhalkhal, cercle de Tiflet, Province de Khémisset, Rabat-Salé-Kénitra, Maroc', '0500000000', '', 'Rabat-Salé-Kénitra', 'en_attente', 'uploads/6a10588dcdc8d.jpg', '2026-05-22 14:22:21', 'assalam@gmail.ma', ''),
(7, '$2y$10$a9VlcHwt4NapAb5yNHMEyuIMpetWcssPM1Ed0/3hv6/NERkBuaXxK', 'جمعية الوحدة', '', 'Bni Quolla, Caïdat de Bni Quolla, Cercle de Ouazzane, Province de Ouezzane, Tanger-Tétouan-Al Hoceïma, 16200, Maroc', '0567894321', '', 'Tanger-Tétouan-Al Hoceïma', 'en_attente', 'uploads/6a10e48b28114.pdf', '2026-05-23 00:19:39', 'alwahda@gmail.com', '');

-- --------------------------------------------------------

--
-- Structure de la table `participations`
--

CREATE TABLE `participations` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `contributeur_id` int(11) NOT NULL,
  `statut` enum('en_attente','accepte') DEFAULT 'en_attente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `organisation_id` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `type_demande` enum('volontariat','donation') NOT NULL,
  `sous_type` varchar(50) DEFAULT NULL,
  `description` text NOT NULL,
  `date_evenement` date NOT NULL,
  `localisation` varchar(255) NOT NULL,
  `contact` varchar(100) NOT NULL,
  `statut` enum('actif','termine') DEFAULT 'actif',
  `date_creation` datetime DEFAULT current_timestamp(),
  `nb_benevoles_requis` int(11) DEFAULT NULL,
  `montant_objectif` decimal(10,2) DEFAULT NULL,
  `besoins` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `contact_principal`
--
ALTER TABLE `contact_principal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `organisation_id` (`organisation_id`);

--
-- Index pour la table `contributeurs`
--
ALTER TABLE `contributeurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `followers`
--
ALTER TABLE `followers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_follow` (`organisation_id`,`contributeur_id`),
  ADD KEY `contributeur_id` (`contributeur_id`);

--
-- Index pour la table `likes`
--
ALTER TABLE `likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_like` (`post_id`,`contributeur_id`),
  ADD KEY `contributeur_id` (`contributeur_id`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `notifications_contributeur`
--
ALTER TABLE `notifications_contributeur`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contributeur_id` (`contributeur_id`),
  ADD KEY `organisation_id` (`organisation_id`),
  ADD KEY `post_id` (`post_id`);

--
-- Index pour la table `notifications_organisation`
--
ALTER TABLE `notifications_organisation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `organisation_id` (`organisation_id`),
  ADD KEY `contributeur_id` (`contributeur_id`),
  ADD KEY `post_id` (`post_id`);

--
-- Index pour la table `organisations`
--
ALTER TABLE `organisations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_connexion` (`email_connexion`),
  ADD UNIQUE KEY `email_connexion_2` (`email_connexion`);

--
-- Index pour la table `participations`
--
ALTER TABLE `participations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `contributeur_id` (`contributeur_id`);

--
-- Index pour la table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `organisation_id` (`organisation_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `contact_principal`
--
ALTER TABLE `contact_principal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `contributeurs`
--
ALTER TABLE `contributeurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `followers`
--
ALTER TABLE `followers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `likes`
--
ALTER TABLE `likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notifications_contributeur`
--
ALTER TABLE `notifications_contributeur`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notifications_organisation`
--
ALTER TABLE `notifications_organisation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `organisations`
--
ALTER TABLE `organisations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `participations`
--
ALTER TABLE `participations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `contact_principal`
--
ALTER TABLE `contact_principal`
  ADD CONSTRAINT `contact_principal_ibfk_1` FOREIGN KEY (`organisation_id`) REFERENCES `organisations` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `followers`
--
ALTER TABLE `followers`
  ADD CONSTRAINT `followers_ibfk_1` FOREIGN KEY (`organisation_id`) REFERENCES `organisations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `followers_ibfk_2` FOREIGN KEY (`contributeur_id`) REFERENCES `contributeurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `likes`
--
ALTER TABLE `likes`
  ADD CONSTRAINT `likes_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `likes_ibfk_2` FOREIGN KEY (`contributeur_id`) REFERENCES `contributeurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `notifications_contributeur`
--
ALTER TABLE `notifications_contributeur`
  ADD CONSTRAINT `notifications_contributeur_ibfk_1` FOREIGN KEY (`contributeur_id`) REFERENCES `contributeurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_contributeur_ibfk_2` FOREIGN KEY (`organisation_id`) REFERENCES `organisations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_contributeur_ibfk_3` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `notifications_organisation`
--
ALTER TABLE `notifications_organisation`
  ADD CONSTRAINT `notifications_organisation_ibfk_1` FOREIGN KEY (`organisation_id`) REFERENCES `organisations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_organisation_ibfk_2` FOREIGN KEY (`contributeur_id`) REFERENCES `contributeurs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_organisation_ibfk_3` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `participations`
--
ALTER TABLE `participations`
  ADD CONSTRAINT `participations_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `participations_ibfk_2` FOREIGN KEY (`contributeur_id`) REFERENCES `contributeurs` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`organisation_id`) REFERENCES `organisations` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
