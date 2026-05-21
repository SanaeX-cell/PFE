<?php
session_start();
// Si l'utilisateur est déjà connecté, on le redirige vers son espace
if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'organisation') {
        header('Location: organisation/accueil.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'contributeur') {
        header('Location: contributeur/accueil.php');
        exit();
    } elseif ($_SESSION['user_type'] === 'admin') {
        header('Location: admin/dashboard.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConnectAid – Choisir son profil</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/choix_inscription.css">
</head>
<body>

<div class="nav-wrapper">
    <nav>
        <a class="nav-logo" href="#">
            <img src="images/logo.png" alt="ConnectAid" style="height: 38px;">
            <span>Connect<em>Aid</em></span>
        </a>
        <div class="nav-actions">
            <a href="connexion.php" class="btn-outline">Se connecter</a>
            <a href="choix_inscription.php" class="btn-solid">S'inscrire</a>
        </div>
    </nav>
</div>

<main>
    <div class="card">
        <h1>Créer un compte</h1>
        <p class="sub">Choisissez le type de profil qui vous correspond</p>

        <div class="choice-buttons">
            <a href="inscription_organisation.php" class="choice-btn btn-org">
                Organisation
            </a>
            <a href="inscription_contributeur.php" class="choice-btn btn-contrib">
                Contributeur
            </a>
        </div>

        <a href="connexion.php" class="back-link">← Retour à la connexion</a>
    </div>
</main>

<footer>
    <div class="footer-brand">
        ConnectAid
        <small>© 2026 ConnectAid Platform. Empowering communities through structured empathy.</small>
    </div>
    <div class="footer-links">
        <a href="#">À propos</a>
        <a href="#">Contact</a>
        <a href="#">Confidentialité</a>
        <a href="#">Conditions</a>
    </div>
</footer>

</body>
</html>