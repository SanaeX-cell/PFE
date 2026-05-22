<?php
session_start();
/*
// Redirige si déjà connecté
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
*/
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConnectAid – Plateforme de solidarité</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/index.css">
</head>
<body>

<nav>
    <div class="nav-inner">
        <a class="nav-logo" href="#">
            <div class="logo-icon">
                <img src="images/logo.png" alt="ConnectAid">
            </div>
            <span class="logo-text">Connect<em>Aid</em></span>
        </a>
        <ul class="nav-links">
            <li><a href="#what-is">À propos</a></li>
            <li><a href="#how-it-works">Fonctionnement</a></li>
            <li><a href="#who-for">Public cible</a></li>
            <li><a href="connexion.php" class="btn-nav-login">Se connecter</a></li>
            <li><a href="choix_inscription.php" class="btn-nav-register">S'inscrire</a></li>
        </ul>
    </div>
</nav>

<section class="hero">
    <div class="hero-inner">
        <h1>
            Connecter les <span class="highlight">organisations</span><br>
            et les <span class="accent">contributeurs</span><br>
            pour faire la différence
        </h1>
        <p class="hero-desc">
            ConnectAid connecte les organisations aux contributeurs qui veulent agir concrètement.<br>
            Inscrivez-vous en quelques clics et devenez acteur du changement près de chez vous.
        </p>
        <div class="hero-cta">
            <a href="choix_inscription.php" class="btn-primary">S'inscrire</a>
            <a href="connexion.php" class="btn-secondary">Se connecter</a>
        </div>
    </div>
</section>

<footer>
    <p>© 2026 ConnectAid</p>
</footer>

</body>
</html>