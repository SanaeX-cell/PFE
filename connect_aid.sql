-- Table organisations
CREATE TABLE organisations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(150) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    nom_organisation VARCHAR(200) NOT NULL,
    description TEXT,
    adresse VARCHAR(255),
    email_public VARCHAR(150),
    telephone VARCHAR(20),
    site_web VARCHAR(255),
    region VARCHAR(100),
    statut ENUM('en_attente', 'valide', 'refuse') DEFAULT 'en_attente',
    justificatif VARCHAR(255),
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table contact_principal
CREATE TABLE contact_principal (
    id INT PRIMARY KEY AUTO_INCREMENT,
    organisation_id INT NOT NULL,
    nom VARCHAR(100) NOT NULL,
    fonction VARCHAR(100),
    email VARCHAR(150),
    telephone VARCHAR(20),
    FOREIGN KEY (organisation_id) REFERENCES organisations(id) ON DELETE CASCADE
);

-- Table contributeurs
CREATE TABLE contributeurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    telephone VARCHAR(20),
    mot_de_passe VARCHAR(255) NOT NULL,
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table admins
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(150) UNIQUE NOT NULL,
    mot_de_passe VARCHAR(255) NOT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table posts
CREATE TABLE posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    organisation_id INT NOT NULL,
    titre VARCHAR(255) NOT NULL,
    type_demande ENUM('volontariat', 'donation') NOT NULL,
    sous_type VARCHAR(50),
    description TEXT NOT NULL,
    date_evenement DATE NOT NULL,
    localisation VARCHAR(255) NOT NULL,
    contact VARCHAR(100) NOT NULL,
    statut ENUM('actif', 'termine') DEFAULT 'actif',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    nb_benevoles_requis INT,
    montant_objectif DECIMAL(10,2),
    besoins TEXT,
    FOREIGN KEY (organisation_id) REFERENCES organisations(id) ON DELETE CASCADE
);

-- Table likes
CREATE TABLE likes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    contributeur_id INT NOT NULL,
    UNIQUE KEY unique_like (post_id, contributeur_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (contributeur_id) REFERENCES contributeurs(id) ON DELETE CASCADE
);

-- Table participations
CREATE TABLE participations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    contributeur_id INT NOT NULL,
    statut ENUM('en_attente', 'accepte') DEFAULT 'en_attente',
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (contributeur_id) REFERENCES contributeurs(id) ON DELETE CASCADE
);

-- Table followers
CREATE TABLE followers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    organisation_id INT NOT NULL,
    contributeur_id INT NOT NULL,
    date_follow DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_follow (organisation_id, contributeur_id),
    FOREIGN KEY (organisation_id) REFERENCES organisations(id) ON DELETE CASCADE,
    FOREIGN KEY (contributeur_id) REFERENCES contributeurs(id) ON DELETE CASCADE
);

-- Table messages
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    expediteur_id INT NOT NULL,
    expediteur_type ENUM('contributeur', 'organisation') NOT NULL,
    destinataire_id INT NOT NULL,
    destinataire_type ENUM('contributeur', 'organisation') NOT NULL,
    message TEXT NOT NULL,
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table notifications_organisation
CREATE TABLE notifications_organisation (
    id INT PRIMARY KEY AUTO_INCREMENT,
    organisation_id INT NOT NULL,
    contributeur_id INT NOT NULL,
    post_id INT NOT NULL,
    type ENUM('volontariat', 'donation', 'follow') NOT NULL,
    message VARCHAR(255) NOT NULL,
    statut ENUM('non_lue', 'lue') DEFAULT 'non_lue',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organisation_id) REFERENCES organisations(id) ON DELETE CASCADE,
    FOREIGN KEY (contributeur_id) REFERENCES contributeurs(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);

-- Table notifications_contributeur
CREATE TABLE notifications_contributeur (
    id INT PRIMARY KEY AUTO_INCREMENT,
    contributeur_id INT NOT NULL,
    organisation_id INT NOT NULL,
    post_id INT NOT NULL,
    type ENUM('accepte', 'refuse') NOT NULL,
    message VARCHAR(255) NOT NULL,
    statut ENUM('non_lue', 'lue') DEFAULT 'non_lue',
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contributeur_id) REFERENCES contributeurs(id) ON DELETE CASCADE,
    FOREIGN KEY (organisation_id) REFERENCES organisations(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);