<?php
session_start();
require_once 'connexion_bd.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_organisation = trim($_POST['nom_organisation'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $adresse          = trim($_POST['adresse'] ?? '');
    $email_connexion  = trim($_POST['email_connexion'] ?? '');
    $telephone        = trim($_POST['telephone'] ?? '');
    $site_web         = trim($_POST['site_web'] ?? '');
    $region           = trim($_POST['region'] ?? '');

    $resp_nom      = trim($_POST['resp_nom'] ?? '');
    $resp_fonction = trim($_POST['resp_fonction'] ?? '');
    $resp_email    = trim($_POST['resp_email'] ?? '');
    $resp_tel      = trim($_POST['resp_tel'] ?? '');

    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $confirm_mdp  = $_POST['confirm_mdp']  ?? '';

    // ========== VALIDATIONS ==========
    // Validation téléphone (uniquement chiffres)
    if (!empty($telephone) && !preg_match('/^[0-9]+$/', $telephone)) {
        $error = "⚠️ Le numéro de téléphone ne doit contenir que des chiffres.";
    }
    // Validation nom du responsable (lettres, espaces, tirets)
    elseif (!empty($resp_nom) && !preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/', $resp_nom)) {
        $error = "⚠️ Le nom du responsable ne doit contenir que des lettres, espaces ou tirets.";
    }
    // Validation email responsable
    elseif (!empty($resp_email) && !filter_var($resp_email, FILTER_VALIDATE_EMAIL)) {
        $error = "⚠️ Veuillez entrer un email valide pour le responsable.";
    }
    // Vérification du justificatif obligatoire
    elseif (!isset($_FILES['justificatif']) || $_FILES['justificatif']['error'] !== UPLOAD_ERR_OK) {
        $error = "⚠️ Le document justificatif est obligatoire.";
    }
    // Vérifier l'extension du justificatif
    else {
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['justificatif']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $error = "⚠️ Le document justificatif doit être au format PDF, JPG ou PNG.";
        }
    }

    // Champs obligatoires étape 1
    if (empty($error) && (empty($nom_organisation) || empty($adresse) || empty($email_connexion) || empty($telephone) || empty($region))) {
        $error = "Veuillez remplir tous les champs obligatoires de l'étape 1.";
    }
    // Champs obligatoires étape 2
    elseif (empty($error) && (empty($resp_nom) || empty($resp_email))) {
        $error = "Veuillez remplir tous les champs obligatoires de l'étape 2.";
    }
    // Mots de passe
    elseif (empty($error) && (empty($mot_de_passe) || $mot_de_passe !== $confirm_mdp)) {
        $error = "Les mots de passe ne correspondent pas.";
    }

    if (empty($error)) {
        // Vérifier si l'email existe déjà
        $check = $conn->prepare("SELECT id FROM organisations WHERE email_connexion = ?");
        $check->bind_param("s", $email_connexion);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Cet email est déjà utilisé.";
        } else {
            // Upload logo
            $logo = '';
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $ext  = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $logo = $upload_dir . uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['logo']['tmp_name'], $logo);
            }

            // Upload justificatif
            $justificatif = '';
            if (isset($_FILES['justificatif']) && $_FILES['justificatif']['error'] === UPLOAD_ERR_OK) {
                $upload_dir   = 'uploads/';
                $ext          = pathinfo($_FILES['justificatif']['name'], PATHINFO_EXTENSION);
                $justificatif = $upload_dir . uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['justificatif']['tmp_name'], $justificatif);
            }

            $hashed_password = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            $statut = 'en_attente';

            $stmt = $conn->prepare("INSERT INTO organisations (nom_organisation, description, adresse, email_connexion, telephone, site_web, region, logo, justificatif, mot_de_passe, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssss", $nom_organisation, $description, $adresse, $email_connexion, $telephone, $site_web, $region, $logo, $justificatif, $hashed_password, $statut);

            if ($stmt->execute()) {
                $org_id = $stmt->insert_id;
                $stmt2  = $conn->prepare("INSERT INTO contact_principal (organisation_id, nom, fonction, email, telephone) VALUES (?, ?, ?, ?, ?)");
                $stmt2->bind_param("issss", $org_id, $resp_nom, $resp_fonction, $resp_email, $resp_tel);
                $stmt2->execute();

                $success = "Votre inscription a bien été enregistrée. Un administrateur validera votre compte sous 48h.";
                header("refresh:3;url=connexion.php");
            } else {
                $error = "Erreur lors de l'inscription. Veuillez réessayer.";
            }
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConnectAid – Inscription Organisation</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="css/inscription_organisation.css">
    <style>
        /* ===== VALIDATION EN TEMPS RÉEL ===== */
        .field-error {
            display: none;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
            padding: 7px 12px;
            background: #fff1f1;
            border: 1px solid #fecaca;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            color: #dc2626;
            animation: slideDown 0.2s ease;
        }
        .field-error.visible { display: flex; }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-4px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        input.input-invalid { border-color: #ef4444 !important; background: #fff8f8; }
        input.input-valid   { border-color: #22c55e !important; }

        /* ===== TOGGLE MOT DE PASSE ===== */
        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 44px; width: 100%; }
        .toggle-pw {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            cursor: pointer; padding: 4px;
            color: #8b8fa8;
            display: flex; align-items: center;
            transition: color 0.2s;
        }
        .toggle-pw:hover { color: #1CB8B2; }

        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear,
        input[type="password"]::-webkit-contacts-auto-fill-button,
        input[type="password"]::-webkit-credentials-auto-fill-button { display: none !important; }
        
        /* Styles de base (complément) */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --teal: #1CB8B2;
            --orange: #F47B20;
            --orange-light: #F9A05A;
            --white: #ffffff;
            --bg-light: #EFF4F6;
            --text-dark: #1E2A3A;
            --text-muted: #5A6874;
            --border: #CBD5E0;
            --shadow: 0 20px 40px rgba(0,0,0,0.08);
            --required: #e53e3e;
        }
        body {
            font-family: 'Nunito', sans-serif;
            background: var(--bg-light);
            min-height: 100vh;
        }
        .nav-wrapper {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 20px;
        }
        nav {
            background: var(--white);
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-top: 1rem;
            border-radius: 60px;
            padding: 0 28px;
        }
        .nav-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .nav-logo span {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 1.4rem;
            letter-spacing: -0.5px;
            color: var(--teal);
        }
        .nav-logo span em { color: var(--orange); font-style: normal; }
        .nav-right {
            font-size: 0.85rem;
            font-weight: 600;
        }
        .nav-right a {
            color: var(--orange);
            text-decoration: none;
        }
        .nav-right a:hover { text-decoration: underline; }
        
        main { padding: 40px 20px; }
        .form-container {
            max-width: 750px;
            margin: 0 auto;
            background: var(--white);
            border-radius: 32px;
            box-shadow: var(--shadow);
            padding: 40px;
        }
        h1 { font-size: 1.8rem; font-weight: 800; color: var(--text-dark); margin-bottom: 8px; text-align: center; }
        .subtitle { text-align: center; color: var(--text-muted); margin-bottom: 32px; }
        
        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 50px;
            position: relative;
        }
        .step {
            text-align: center;
            flex: 1;
            position: relative;
            z-index: 2;
        }
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 20px;
            left: 60%;
            width: 80%;
            height: 2px;
            background: var(--border);
            z-index: -1;
        }
        .step.completed:not(:last-child)::after {
            background: var(--orange);
        }
        .step-number {
            width: 40px;
            height: 40px;
            background: var(--border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: 800;
            color: var(--text-muted);
        }
        .step.active .step-number {
            background: var(--orange);
            color: white;
        }
        .step.completed .step-number {
            background: var(--orange-light);
            color: white;
        }
        .step-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
        }
        .step.active .step-label {
            color: var(--orange);
            font-weight: 800;
        }
        
        .step-content { display: none; }
        .step-content.active { display: block; }
        
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 700; margin-bottom: 6px; font-size: 0.85rem; }
        .required-star {
            color: var(--required);
            margin-left: 4px;
            font-size: 1rem;
        }
        input, textarea, select {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-family: inherit;
            transition: 0.2s;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--orange);
            box-shadow: 0 0 0 3px rgba(244,123,32,0.1);
        }
        .row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .btn-group {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }
        .btn-prev, .btn-next, .btn-submit {
            padding: 12px 28px;
            border-radius: 40px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.2s;
            border: none;
            font-size: 1rem;
        }
        .btn-prev {
            background: var(--border);
            color: var(--text-dark);
        }
        .btn-prev:hover {
            background: var(--teal);
            color: white;
        }
        .btn-next, .btn-submit {
            background: var(--orange);
            color: white;
        }
        .btn-next:hover, .btn-submit:hover {
            background: var(--orange-light);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .alert-error { background: #FEE2E2; color: #C53030; border: 1px solid #FECACA; }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
        
        .first-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            align-items: flex-start;
        }
        .first-row .field-name { flex: 1; }
        .logo-area {
            width: 100px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .logo-circle {
            width: 80px;
            height: 80px;
            background: var(--orange);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.2s;
            overflow: hidden;
            position: relative;
            box-shadow: 0 4px 12px rgba(244,123,32,0.3);
        }
        .logo-circle:hover { background: var(--orange-light); }
        .camera-icon { width: 32px; height: 32px; fill: white; }
        .logo-preview-img {
            display: none;
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
            border-radius: 50%;
        }
        .logo-text {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 6px;
            text-align: center;
        }
        
        .address-wrapper {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .address-wrapper input { flex: 1; }
        .map-icon {
            background: var(--orange);
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.2s;
            flex-shrink: 0;
        }
        .map-icon:hover {
            background: var(--orange-light);
            transform: scale(1.05);
        }
        .map-icon svg { width: 24px; height: 24px; fill: white; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            border-radius: 24px;
            width: 90%;
            max-width: 850px;
            padding: 20px;
            position: relative;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .close-modal {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--text-muted);
        }
        .search-bar {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
        }
        .search-bar input {
            flex: 1;
            padding: 10px 12px;
            border-radius: 40px;
            border: 1px solid var(--border);
        }
        .search-bar button {
            background: var(--orange);
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
        }
        #map {
            height: 400px;
            width: 100%;
            border-radius: 16px;
            margin-bottom: 15px;
        }
        .validate-address {
            background: var(--orange);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 700;
        }
        @media (max-width: 640px) {
            .form-container { padding: 24px; }
            .first-row { flex-direction: column; align-items: stretch; }
            .logo-area { width: 100%; margin-top: 10px; }
        }
    </style>
</head>
<body>

<div class="nav-wrapper">
    <nav>
        <a class="nav-logo" href="index.php">
            <img src="images/logo.png" alt="ConnectAid" style="height: 38px;">
            <span>Connect<em>Aid</em></span>
        </a>
        <div class="nav-right">
            Déjà inscrit ? <a href="connexion.php">Se connecter</a>
        </div>
    </nav>
</div>

<main>
    <div class="form-container">
        <h1>Inscription organisation</h1>
        <div class="subtitle">Rejoignez notre communauté solidaire</div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="multiStepForm">

            <!-- INDICATEURS D'ÉTAPES -->
            <div class="steps">
                <div class="step" id="step1Indicator">
                    <div class="step-number">1</div>
                    <div class="step-label">Organisation</div>
                </div>
                <div class="step" id="step2Indicator">
                    <div class="step-number">2</div>
                    <div class="step-label">Responsable</div>
                </div>
                <div class="step" id="step3Indicator">
                    <div class="step-number">3</div>
                    <div class="step-label">Sécurité</div>
                </div>
            </div>

            <!-- ===== ÉTAPE 1 ===== -->
            <div class="step-content" id="step1">
                <div class="first-row">
                    <div class="field-name">
                        <label>Nom de l'organisation <span class="required-star">*</span></label>
                        <input type="text" name="nom_organisation" id="nom_organisation"
                               required autocomplete="off">
                        <div class="field-error" id="err-nom-org">
                            ⚠️ Le nom ne doit contenir que des lettres, espaces ou tirets.
                        </div>
                    </div>
                    <div class="logo-area">
                        <div class="logo-circle" onclick="document.getElementById('logoInput').click()">
                            <svg class="camera-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M20 5h-2.83l-1.58-2H8.41L6.83 5H4C2.9 5 2 5.9 2 7v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm-8 13c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                            <img id="logoPreview" class="logo-preview-img">
                        </div>
                        <div class="logo-text">Ajouter logo</div>
                        <input type="file" id="logoInput" name="logo" accept="image/*"
                               style="display: none;" onchange="previewLogo(event)">
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label>Adresse <span class="required-star">*</span></label>
                    <div class="address-wrapper">
                        <input type="text" name="adresse" id="adresse" required>
                        <div class="map-icon" onclick="openMapModal()">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="row-2">
                    <div class="form-group">
                        <label>Email <span class="required-star">*</span></label>
                        <input type="text" name="email_connexion" id="email_connexion" required autocomplete="off">
                        <div class="field-error" id="err-email">
                            ⚠️ L'adresse email doit contenir @ et un domaine valide (ex: nom@exemple.com).
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Téléphone <span class="required-star">*</span></label>
                        <input type="text" name="telephone" id="telephone" required autocomplete="off">
                        <div class="field-error" id="err-telephone">
                            ⚠️ Le téléphone ne doit contenir que des chiffres. Lettres et symboles non autorisés.
                        </div>
                    </div>
                </div>

                <div class="row-2">
                    <div class="form-group">
                        <label>Site web</label>
                        <input type="url" name="site_web"
                               pattern="https?://.+"
                               title="⚠️ Entrez une URL valide (ex: https://www.example.com)"
                               placeholder="https://www.example.com">
                    </div>
                    <div class="form-group">
                        <label>Région <span class="required-star">*</span></label>
                        <select name="region" required>
                            <option value="">Sélectionnez une région</option>
                            <option value="Tanger-Tétouan-Al Hoceïma">Tanger-Tétouan-Al Hoceïma</option>
                            <option value="Oriental">Oriental</option>
                            <option value="Fès-Meknès">Fès-Meknès</option>
                            <option value="Rabat-Salé-Kénitra">Rabat-Salé-Kénitra</option>
                            <option value="Béni Mellal-Khénifra">Béni Mellal-Khénifra</option>
                            <option value="Casablanca-Settat">Casablanca-Settat</option>
                            <option value="Marrakech-Safi">Marrakech-Safi</option>
                            <option value="Drâa-Tafilalet">Drâa-Tafilalet</option>
                            <option value="Souss-Massa">Souss-Massa</option>
                            <option value="Guelmim-Oued Noun">Guelmim-Oued Noun</option>
                            <option value="Laâyoune-Sakia El Hamra">Laâyoune-Sakia El Hamra</option>
                            <option value="Dakhla-Oued Ed-Dahab">Dakhla-Oued Ed-Dahab</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Document justificatif (PDF, JPG, PNG) <span class="required-star">*</span></label>
                    <input type="file" name="justificatif" accept=".pdf,.jpg,.jpeg,.png" required>
                    <small style="color: var(--text-muted);">Ce document est obligatoire et sera vérifié par notre équipe.</small>
                </div>
            </div>

            <!-- ===== ÉTAPE 2 ===== -->
            <div class="step-content" id="step2">
                <div class="row-2">
                    <div class="form-group">
                        <label>Nom complet <span class="required-star">*</span></label>
                        <input type="text" name="resp_nom" id="resp_nom" required autocomplete="off">
                        <div class="field-error" id="err-resp-nom">
                            ⚠️ Le nom ne doit contenir que des lettres, espaces ou tirets.
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Fonction</label>
                        <input type="text" name="resp_fonction" list="fonctions-suggestions"
                               placeholder="Ex: Directeur, Président...">
                        <datalist id="fonctions-suggestions">
                            <option value="Directeur">
                            <option value="Président">
                            <option value="Coordinateur">
                            <option value="Responsable RH">
                            <option value="Responsable administratif">
                            <option value="Secrétaire général">
                            <option value="Trésorier">
                            <option value="Chargé de projet">
                        </datalist>
                        <small style="color: var(--text-muted); font-size: 0.7rem;">Suggestions : Directeur, Président, Coordinateur...</small>
                    </div>
                </div>
                <div class="row-2">
                    <div class="form-group">
                        <label>Email <span class="required-star">*</span></label>
                        <input type="text" name="resp_email" id="resp_email" required autocomplete="off">
                        <div class="field-error" id="err-resp-email">
                            ⚠️ L'adresse email doit contenir @ et un domaine valide (ex: nom@exemple.com).
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="text" name="resp_tel" id="resp_tel" autocomplete="off">
                        <div class="field-error" id="err-resp-tel">
                            ⚠️ Le téléphone ne doit contenir que des chiffres.
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== ÉTAPE 3 ===== -->
            <div class="step-content" id="step3">
                <div class="row-2">
                    <div class="form-group">
                        <label>Mot de passe <span class="required-star">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="mot_de_passe" id="mot_de_passe" required>
                            <button type="button" class="toggle-pw"
                                    onclick="togglePw('mot_de_passe', this)" tabindex="-1">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                        <div class="field-error" id="err-mdp">⚠️ Le mot de passe est obligatoire.</div>
                    </div>
                    <div class="form-group">
                        <label>Confirmer le mot de passe <span class="required-star">*</span></label>
                        <div class="password-wrapper">
                            <input type="password" name="confirm_mdp" id="confirm_mdp" required>
                            <button type="button" class="toggle-pw"
                                    onclick="togglePw('confirm_mdp', this)" tabindex="-1">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                        <div class="field-error" id="err-confirm-mdp">⚠️ Les mots de passe ne correspondent pas.</div>
                    </div>
                </div>
            </div>

            <div class="btn-group">
                <button type="button" class="btn-prev" id="prevBtn" style="display: none;">← Précédent</button>
                <button type="button" class="btn-next" id="nextBtn">Suivant →</button>
                <button type="submit" class="btn-submit" id="submitBtn" style="display: none;">S'inscrire</button>
            </div>
        </form>
    </div>
</main>

<!-- ===== MODALE CARTE ===== -->
<div id="mapModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Sélectionnez l'adresse sur la carte</h3>
            <button class="close-modal" onclick="closeMapModal()">&times;</button>
        </div>
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Nom de rue, ville, région...">
            <button onclick="searchLocation()">Rechercher</button>
        </div>
        <div id="map"></div>
        <button class="validate-address" onclick="validateAddress()">Utiliser cette adresse</button>
    </div>
</div>

<script>
// ========== TOGGLE MOT DE PASSE ==========
function togglePw(fieldId, btn) {
    const input    = document.getElementById(fieldId);
    const isHidden = input.type === 'password';
    input.type     = isHidden ? 'text' : 'password';
    btn.innerHTML  = isHidden
        ? `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
               <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
               <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
               <line x1="1" y1="1" x2="23" y2="23"/></svg>`
        : `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
               <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
               <circle cx="12" cy="12" r="3"/></svg>`;
    btn.style.color = isHidden ? '#1CB8B2' : '#8b8fa8';
}

// ========== HELPERS ==========
function showError(id)   { document.getElementById(id).classList.add('visible'); }
function hideError(id)   { document.getElementById(id).classList.remove('visible'); }
function markInvalid(el) { el.classList.add('input-invalid'); el.classList.remove('input-valid'); }
function markValid(el)   { el.classList.remove('input-invalid'); el.classList.add('input-valid'); }
function clearMark(el)   { el.classList.remove('input-invalid', 'input-valid'); }

const LETTERS_RE = /^[A-Za-zÀ-ÿ\u0600-\u06FF\s\-]+$/;
const EMAIL_RE   = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

// NOM ORGANISATION
const nomOrgInput = document.getElementById('nom_organisation');
if (nomOrgInput) {
    nomOrgInput.addEventListener('input', function () {
        const cleaned = this.value.replace(/[^A-Za-zÀ-ÿ\u0600-\u06FF\s\-]/g, '');
        if (this.value !== cleaned) {
            this.value = cleaned;
            showError('err-nom-org'); markInvalid(this);
        } else if (cleaned.length > 0) {
            hideError('err-nom-org'); markValid(this);
        } else {
            hideError('err-nom-org'); clearMark(this);
        }
    });
}

// TÉLÉPHONE ÉTAPE 1
const telInput = document.getElementById('telephone');
if (telInput) {
    telInput.addEventListener('input', function () {
        const cleaned = this.value.replace(/[^0-9]/g, '');
        if (this.value !== cleaned) {
            this.value = cleaned;
            showError('err-telephone'); markInvalid(this);
        } else if (cleaned.length > 0) {
            hideError('err-telephone'); markValid(this);
        } else {
            hideError('err-telephone'); clearMark(this);
        }
    });
}

// EMAIL ÉTAPE 1
const emailInput = document.getElementById('email_connexion');
if (emailInput) {
    emailInput.addEventListener('input', function () {
        const val = this.value.trim();
        if (!val.length)          { hideError('err-email'); clearMark(this); }
        else if (!EMAIL_RE.test(val)) { showError('err-email'); markInvalid(this); }
        else                          { hideError('err-email'); markValid(this); }
    });
}

// NOM RESPONSABLE
const nomRespInput = document.getElementById('resp_nom');
if (nomRespInput) {
    nomRespInput.addEventListener('input', function () {
        const cleaned = this.value.replace(/[^A-Za-zÀ-ÿ\u0600-\u06FF\s\-]/g, '');
        if (this.value !== cleaned) {
            this.value = cleaned;
            showError('err-resp-nom'); markInvalid(this);
        } else if (cleaned.length > 0) {
            hideError('err-resp-nom'); markValid(this);
        } else {
            hideError('err-resp-nom'); clearMark(this);
        }
    });
}

// EMAIL RESPONSABLE
const respEmailInput = document.getElementById('resp_email');
if (respEmailInput) {
    respEmailInput.addEventListener('input', function () {
        const val = this.value.trim();
        if (!val.length)               { hideError('err-resp-email'); clearMark(this); }
        else if (!EMAIL_RE.test(val))  { showError('err-resp-email'); markInvalid(this); }
        else                           { hideError('err-resp-email'); markValid(this); }
    });
}

// TÉLÉPHONE RESPONSABLE (optionnel)
const respTelInput = document.getElementById('resp_tel');
if (respTelInput) {
    respTelInput.addEventListener('input', function () {
        const cleaned = this.value.replace(/[^0-9]/g, '');
        if (this.value !== cleaned) {
            this.value = cleaned;
            showError('err-resp-tel'); markInvalid(this);
        } else if (cleaned.length > 0) {
            hideError('err-resp-tel'); markValid(this);
        } else {
            hideError('err-resp-tel'); clearMark(this);
        }
    });
}

// MOTS DE PASSE
const mdpInput     = document.getElementById('mot_de_passe');
const confirmInput = document.getElementById('confirm_mdp');

function validateMdp() {
    if (!confirmInput.value.length) { hideError('err-confirm-mdp'); clearMark(confirmInput); return; }
    if (mdpInput.value !== confirmInput.value) { showError('err-confirm-mdp'); markInvalid(confirmInput); }
    else { hideError('err-confirm-mdp'); markValid(confirmInput); }
}
if (mdpInput)     mdpInput.addEventListener('input', validateMdp);
if (confirmInput) confirmInput.addEventListener('input', validateMdp);

// BLOCAGE ENVOI
document.getElementById('multiStepForm').addEventListener('submit', function (e) {
    let hasError  = false;
    let errorStep = null;

    const checks = [
        ['nom_organisation', v => LETTERS_RE.test(v) && v.length > 0, 'err-nom-org',    1],
        ['telephone',        v => /^[0-9]+$/.test(v),                  'err-telephone',  1],
        ['email_connexion',  v => EMAIL_RE.test(v.trim()),             'err-email',      1],
        ['resp_nom',         v => LETTERS_RE.test(v) && v.length > 0, 'err-resp-nom',   2],
        ['resp_email',       v => EMAIL_RE.test(v.trim()),             'err-resp-email', 2],
    ];

    checks.forEach(([id, test, errId, step]) => {
        const el = document.getElementById(id);
        if (!el) return;
        if (!test(el.value)) {
            showError(errId); markInvalid(el); hasError = true;
            if (errorStep === null) errorStep = step;
        }
    });

    const rTel = document.getElementById('resp_tel');
    if (rTel && rTel.value.length > 0 && !/^[0-9]+$/.test(rTel.value)) {
        showError('err-resp-tel'); markInvalid(rTel); hasError = true;
        if (errorStep === null) errorStep = 2;
    }

    if (mdpInput && confirmInput && mdpInput.value !== confirmInput.value) {
        showError('err-confirm-mdp'); markInvalid(confirmInput); hasError = true;
        if (errorStep === null) errorStep = 3;
    }

    if (hasError) {
        e.preventDefault();
        if (errorStep !== null) { currentStep = errorStep; showStep(errorStep); }
    }
});

// CARTE
let map, marker, selectedAddress = '';

function openMapModal() {
    document.getElementById('mapModal').style.display = 'flex';
    setTimeout(() => {
        if (!map) {
            map = L.map('map').setView([31.7917, -7.0926], 6);
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);
            map.on('click', async function (e) {
                if (marker) map.removeLayer(marker);
                marker = L.marker([e.latlng.lat, e.latlng.lng]).addTo(map);
                const res  = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${e.latlng.lat}&lon=${e.latlng.lng}&format=json`);
                const data = await res.json();
                selectedAddress = data.display_name || `${e.latlng.lat}, ${e.latlng.lng}`;
            });
        } else { map.invalidateSize(); }
    }, 100);
}

async function searchLocation() {
    const query = document.getElementById('searchInput').value;
    if (!query) return;
    const res  = await fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(query)}&format=json&limit=1`);
    const data = await res.json();
    if (data.length > 0) {
        const { lat, lon, display_name } = data[0];
        map.setView([lat, lon], 16);
        if (marker) map.removeLayer(marker);
        marker = L.marker([lat, lon]).addTo(map);
        selectedAddress = display_name;
    } else { alert("Aucun résultat trouvé."); }
}

function closeMapModal()   { document.getElementById('mapModal').style.display = 'none'; }
function validateAddress() {
    if (selectedAddress) document.getElementById('adresse').value = selectedAddress;
    closeMapModal();
}

// LOGO PREVIEW
function previewLogo(event) {
    const preview = document.getElementById('logoPreview');
    const icon    = document.querySelector('.camera-icon');
    const file    = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (icon) icon.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }
}

// MULTI-ÉTAPES
let currentStep  = 1;
const totalSteps = 3;

function showStep(step) {
    for (let i = 1; i <= totalSteps; i++) {
        document.getElementById(`step${i}`).classList.remove('active');
        document.getElementById(`step${i}Indicator`).classList.remove('active', 'completed');
    }
    document.getElementById(`step${step}`).classList.add('active');
    document.getElementById(`step${step}Indicator`).classList.add('active');
    for (let i = 1; i < step; i++) {
        document.getElementById(`step${i}Indicator`).classList.add('completed');
    }
    document.getElementById('prevBtn').style.display   = step === 1          ? 'none'         : 'inline-block';
    document.getElementById('nextBtn').style.display   = step === totalSteps ? 'none'         : 'inline-block';
    document.getElementById('submitBtn').style.display = step === totalSteps ? 'inline-block' : 'none';
}

document.getElementById('nextBtn').addEventListener('click', () => {
    if (currentStep < totalSteps) { currentStep++; showStep(currentStep); }
});
document.getElementById('prevBtn').addEventListener('click', () => {
    if (currentStep > 1) { currentStep--; showStep(currentStep); }
});

showStep(currentStep);
</script>

</body>
</html>