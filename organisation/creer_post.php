<?php
session_start();
require_once '../connexion_bd.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'organisation') {
    header('Location: ../connexion.php');
    exit();
}

$org_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = mysqli_real_escape_string($conn, $_POST['titre']);
    $type_demande = mysqli_real_escape_string($conn, $_POST['type_demande']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $date_evenement = mysqli_real_escape_string($conn, $_POST['date_evenement']);
    $localisation = mysqli_real_escape_string($conn, $_POST['localisation']);
    $contact = mysqli_real_escape_string($conn, $_POST['contact'] ?? '');
    $sous_type = '';
    $nb_benevoles = 'NULL';
    $montant_objectif = 'NULL';
    $besoins = 'NULL';

    if ($type_demande === 'volontariat') {
        $nb_benevoles = (int)$_POST['nb_benevoles_requis'];
    } elseif ($type_demande === 'donation') {
        $sous_type = mysqli_real_escape_string($conn, $_POST['sous_type_donation'] ?? '');
        if ($sous_type === 'argent') {
            $montant_objectif = (float)$_POST['montant_objectif'];
        } else {
            $besoins_val = mysqli_real_escape_string($conn, $_POST['besoins'] ?? '');
            $besoins = "'$besoins_val'";
        }
    }

    if (!empty($titre) && !empty($type_demande) && !empty($description) && !empty($date_evenement) && !empty($localisation)) {
        $sql_insert = "INSERT INTO posts (organisation_id, titre, type_demande, sous_type, description, date_evenement, localisation, contact, nb_benevoles_requis, montant_objectif, besoins) VALUES ($org_id, '$titre', '$type_demande', '$sous_type', '$description', '$date_evenement', '$localisation', '$contact', $nb_benevoles, $montant_objectif, $besoins)";
        if (mysqli_query($conn, $sql_insert)) {
            $success_msg = 'Publication créée avec succès !';
        } else {
            $error_msg = 'Erreur lors de la création : ' . mysqli_error($conn);
        }
    } else {
        $error_msg = 'Veuillez remplir tous les champs obligatoires.';
    }
}

// Récupérer les infos organisation
$sql = "SELECT * FROM organisations WHERE id = $org_id";
$result = mysqli_query($conn, $sql);
$org = mysqli_fetch_assoc($result);
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ConnectAid - Créer un post</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #f5f6fa; --sidebar-bg: #fff; --card-bg: #fff;
    --text-primary: #1a1d2e; --text-secondary: #8b8fa8; --text-light: #b0b3c6;
    --accent-teal: #1CB8B2; --accent-orange: #F47B20;
    --border: #f0f1f7; --shadow: 0 2px 20px rgba(0,0,0,0.06);
    --radius: 18px; --sidebar-width: 220px;
  }
  body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text-primary); display: flex; min-height: 100vh; font-size: 14px; }
  .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); display: flex; flex-direction: column; padding: 28px 0; position: fixed; top: 0; left: 0; bottom: 0; border-right: 1px solid var(--border); z-index: 10; }
  .logo { display: flex; align-items: center; justify-content: flex-end; width: 93%; padding: 0 24px 32px 18px; }
  .logo-image { width: 60px; height: 60px; object-fit: contain; border-radius: 12px; flex-shrink: 0; }
  .logo-text { font-size: 20px; font-weight: 700; letter-spacing: -0.5px; white-space: nowrap; }
  .logo-text span:first-child { color: var(--accent-teal); }
  .logo-text span:last-child { color: var(--accent-orange); }
  nav { flex: 1; margin-top: 8px; }
  .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 24px; color: var(--text-secondary); cursor: pointer; transition: all 0.2s; font-weight: 500; font-size: 14px; position: relative; margin: 2px 0; text-decoration: none; }
  .nav-item:hover { color: var(--text-primary); background: var(--bg); }
  .nav-item.active { color: var(--accent-teal); background: #E1F7F6; }
  .nav-item.active::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 3px; height: 60%; background: var(--accent-teal); border-radius: 0 4px 4px 0; }
  .nav-icon { width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .sidebar-bottom { padding: 0; border-top: 1px solid var(--border); padding-top: 12px; margin-top: auto; }
  .sidebar-bottom .nav-item { background: var(--accent-teal); color: white; border-radius: 40px; margin: 8px 16px; justify-content: center; }
  .sidebar-bottom .nav-item:hover { background: #138F8A; color: white; }
  .sidebar-bottom .nav-item::before { display: none; }
  .main { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
  .topbar { background: transparent; padding: 16px 32px; display: flex; align-items: center; gap: 12px; position: sticky; top: 0; z-index: 5; flex-wrap: wrap; }
  .greeting-bar { flex: 1; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 10px 18px; box-shadow: var(--shadow); min-width: 200px; }
  .greeting-bar h2 { font-size: 15px; font-weight: 700; margin-bottom: 2px; }
  .greeting-bar p { font-size: 11px; color: var(--text-secondary); }
  .date-badge { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 10px 16px; font-size: 13px; font-weight: 500; color: var(--text-primary); box-shadow: var(--shadow); white-space: nowrap; }
  .profile-area { position: relative; }
  .user-info { display: flex; align-items: center; gap: 10px; cursor: pointer; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 6px 14px 6px 6px; box-shadow: var(--shadow); }
  .avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--accent-teal); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #fff; overflow: hidden; flex-shrink: 0; }
  .user-name { font-weight: 600; font-size: 13px; white-space: nowrap; color: var(--text-primary); }
  .dropdown-icon { color: #8b8fa8; transition: transform 0.2s; }
  .dropdown-menu { position: absolute; top: 100%; right: 0; margin-top: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); min-width: 180px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s; z-index: 100; }
  .profile-area.active .dropdown-menu { opacity: 1; visibility: visible; transform: translateY(0); }
  .profile-area.active .dropdown-icon { transform: rotate(180deg); }
  .dropdown-menu a { display: flex; align-items: center; gap: 10px; padding: 10px 16px; color: var(--text-secondary); text-decoration: none; font-size: 13px; transition: background 0.2s; }
  .dropdown-menu a:hover { background: var(--bg); color: var(--text-primary); }
  .dropdown-menu hr { margin: 4px 0; border: none; border-top: 1px solid var(--border); }
  .content { padding: 20px 32px 40px; display: flex; flex-direction: column; gap: 20px; }

  /* ALERT */
  .alert { padding: 12px 18px; border-radius: 10px; font-size: 13px; font-weight: 500; }
  .alert-success { background: #dcfce7; color: #166534; }
  .alert-error { background: #fee2e2; color: #991b1b; }

  /* FORM CARD */
  .card { background: var(--card-bg); border-radius: var(--radius); padding: 32px; box-shadow: var(--shadow); max-width: 720px; }
  .card-title { font-size: 17px; font-weight: 700; margin-bottom: 24px; display: flex; align-items: center; gap: 8px; }

  /* FORM */
  .form-group { display: flex; flex-direction: column; gap: 6px; }
  .form-group label { font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
  .form-group label .req { color: #ef4444; margin-left: 2px; }
  .form-group input, .form-group textarea, .form-group select {
    border: 1.5px solid var(--border); border-radius: 10px; padding: 11px 14px;
    font-size: 14px; font-family: inherit; color: var(--text-primary);
    transition: border-color 0.2s; background: var(--bg);
  }
  .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
    outline: none; border-color: var(--accent-teal); background: #fff;
  }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
  .form-stack { display: flex; flex-direction: column; gap: 18px; }

  /* TYPE SELECTOR */
  .type-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 4px; }
  .type-option { position: relative; }
  .type-option input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
  .type-label {
    display: flex; align-items: center; gap: 12px; padding: 14px 18px;
    border: 2px solid var(--border); border-radius: 12px; cursor: pointer;
    transition: all 0.2s; background: var(--bg); font-weight: 600; font-size: 14px;
  }
  .type-label:hover { border-color: #c8cce0; background: #fff; }
  .type-option input:checked + .type-label { border-color: var(--accent-teal); background: #E1F7F6; color: var(--accent-teal); }
  .type-option input:checked + .type-label.orange-label { border-color: var(--accent-orange); background: #FFF0E6; color: var(--accent-orange); }
  .type-icon { width: 38px; height: 38px; border-radius: 10px; background: rgba(0,0,0,0.06); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

  /* CHECKBOX SOUS-TYPE */
  .checkbox-group { display: flex; flex-direction: column; gap: 10px; padding: 14px; background: var(--bg); border-radius: 10px; border: 1.5px solid var(--border); }
  .checkbox-option { display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 13px; font-weight: 500; }
  .checkbox-option input[type="radio"] { accent-color: var(--accent-orange); width: 16px; height: 16px; cursor: pointer; }

  /* DYNAMIC FIELDS */
  .dynamic-section { display: flex; flex-direction: column; gap: 16px; }
  .hidden { display: none !important; }

  /* PLACEHOLDER ZONE */
  .placeholder-info { display: flex; align-items: center; gap: 12px; padding: 20px 18px; background: #f8f9ff; border: 1.5px dashed #d0d3e2; border-radius: 12px; color: var(--text-secondary); font-size: 13px; }

  /* SUBMIT */
  .btn-publish { display: inline-flex; align-items: center; gap: 8px; background: var(--accent-orange); color: #fff; border: none; border-radius: 12px; padding: 13px 28px; font-size: 14px; font-weight: 700; cursor: pointer; font-family: inherit; transition: background 0.2s; }
  .btn-publish:hover { background: #E06A10; }
  .btn-publish:disabled { background: #c0c0c0; cursor: not-allowed; }
</style>
</head>
<body>

<aside class="sidebar">
  <div class="logo">
    <img src="../images/logo.png" alt="Logo" class="logo-image">
    <div class="logo-text"><span>Connect</span><span>Aid</span></div>
  </div>
  <nav>
    <a href="accueil.php" class="nav-item">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
      Accueil
    </a>
    <a href="ma_page.php" class="nav-item">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></span>
      Ma page
    </a>
    <a href="creer_post.php" class="nav-item active">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
      Créer un post
    </a>
    <a href="benevoles.php" class="nav-item">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
      Bénévoles inscrits
    </a>
    <a href="messages.php" class="nav-item">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
      Messages
    </a>
    <a href="notifications.php" class="nav-item">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></span>
      Notifications
    </a>
  </nav>
  <div class="sidebar-bottom">
    <a href="../logout.php" class="nav-item">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
      Déconnexion
    </a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <div class="greeting-bar">
      <h2>Créer un post</h2>
      <p>Publiez un appel à bénévoles ou à dons</p>
    </div>
    <div class="date-badge">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <?php setlocale(LC_TIME, 'fr_FR.utf8', 'fr_FR', 'fr'); echo strftime('%d %B %Y'); ?>
    </div>
    <div class="profile-area" id="profileArea">
      <div class="user-info" onclick="document.getElementById('profileArea').classList.toggle('active')">
        <div class="avatar">
          <?php if($org['logo']): ?>
            <img src="../<?php echo htmlspecialchars($org['logo']); ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
          <?php else: ?>
            <?php echo strtoupper(substr($org['nom_organisation'], 0, 1)); ?>
          <?php endif; ?>
        </div>
        <span class="user-name"><?php echo htmlspecialchars($org['email_connexion']); ?></span>
        <span class="dropdown-icon"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708"/></svg></span>
      </div>
      <div class="dropdown-menu">
        <a href="ma_page.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Ma page</a>
        <hr>
        <a href="../logout.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Déconnexion</a>
      </div>
    </div>
  </header>

  <div class="content">
    <?php if($success_msg): ?><div class="alert alert-success">✅ <?php echo $success_msg; ?></div><?php endif; ?>
    <?php if($error_msg): ?><div class="alert alert-error">❌ <?php echo $error_msg; ?></div><?php endif; ?>

    <div class="card">
      <div class="card-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--accent-orange)" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        Nouvelle publication
      </div>

      <form method="POST" id="postForm">
        <div class="form-stack">

          <!-- TITRE -->
          <div class="form-group">
            <label>Titre <span class="req">*</span></label>
            <input type="text" name="titre" placeholder="Ex: Recherche bénévoles pour distribution alimentaire" required value="<?php echo isset($_POST['titre'])?htmlspecialchars($_POST['titre']):''; ?>">
          </div>

          <!-- TYPE DE POST -->
          <div class="form-group">
            <label>Type de publication <span class="req">*</span></label>
            <div class="type-selector">
              <div class="type-option">
                <input type="radio" name="type_demande" id="type_volontariat" value="volontariat" onchange="handleTypeChange()" <?php echo (isset($_POST['type_demande'])&&$_POST['type_demande']==='volontariat')?'checked':''; ?>>
                <label class="type-label" for="type_volontariat">
                  <div class="type-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                  Volontariat
                </label>
              </div>
              <div class="type-option">
                <input type="radio" name="type_demande" id="type_donation" value="donation" onchange="handleTypeChange()" <?php echo (isset($_POST['type_demande'])&&$_POST['type_demande']==='donation')?'checked':''; ?>>
                <label class="type-label orange-label" for="type_donation">
                  <div class="type-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div>
                  Donation
                </label>
              </div>
            </div>
          </div>

          <!-- PLACEHOLDER AVANT SÉLECTION -->
          <div class="placeholder-info" id="placeholderInfo">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Sélectionnez un type de publication pour afficher les champs spécifiques.
          </div>

          <!-- CHAMPS DYNAMIQUES VOLONTARIAT -->
          <div class="dynamic-section hidden" id="section_volontariat">
            <div class="form-group">
              <label>Nombre de bénévoles requis <span class="req">*</span></label>
              <input type="number" name="nb_benevoles_requis" min="1" placeholder="Ex: 10" value="<?php echo isset($_POST['nb_benevoles_requis'])?htmlspecialchars($_POST['nb_benevoles_requis']):''; ?>">
            </div>
          </div>

          <!-- CHAMPS DYNAMIQUES DONATION -->
          <div class="dynamic-section hidden" id="section_donation">
            <div class="form-group">
              <label>Type de don <span class="req">*</span></label>
              <div class="checkbox-group">
                <label class="checkbox-option">
                  <input type="radio" name="sous_type_donation" value="argent" onchange="handleSousTypeChange()" <?php echo (isset($_POST['sous_type_donation'])&&$_POST['sous_type_donation']==='argent')?'checked':''; ?>>
                  💰 Don en argent
                </label>
                <label class="checkbox-option">
                  <input type="radio" name="sous_type_donation" value="autre" onchange="handleSousTypeChange()" <?php echo (isset($_POST['sous_type_donation'])&&$_POST['sous_type_donation']==='autre')?'checked':''; ?>>
                  📦 Don en nature (vêtements, livres, nourriture, etc.)
                </label>
              </div>
            </div>

            <!-- Sous-champs donation argent -->
            <div class="form-group hidden" id="section_argent">
              <label>Montant objectif (DH) <span class="req">*</span></label>
              <input type="number" name="montant_objectif" min="0" step="0.01" placeholder="Ex: 5000" value="<?php echo isset($_POST['montant_objectif'])?htmlspecialchars($_POST['montant_objectif']):''; ?>">
            </div>

            <!-- Sous-champs donation autre -->
            <div class="form-group hidden" id="section_autre">
              <label>Besoins détaillés <span class="req">*</span></label>
              <input type="text" name="besoins" placeholder="Ex: Vêtements d'hiver pour enfants, couvertures, boîtes de conserve..." value="<?php echo isset($_POST['besoins'])?htmlspecialchars($_POST['besoins']):''; ?>">
            </div>
          </div>

          <!-- DESCRIPTION -->
          <div class="form-group hidden" id="section_description">
            <label>Description <span class="req">*</span></label>
            <textarea name="description" rows="5" placeholder="Décrivez l'objectif, le contexte et les détails de votre appel..."><?php echo isset($_POST['description'])?htmlspecialchars($_POST['description']):''; ?></textarea>
          </div>

          <!-- DATE & LIEU -->
          <div class="form-row hidden" id="section_datelieu">
            <div class="form-group">
              <label>Date de l'événement <span class="req">*</span></label>
              <input type="date" name="date_evenement" value="<?php echo isset($_POST['date_evenement'])?htmlspecialchars($_POST['date_evenement']):''; ?>">
            </div>
            <div class="form-group">
              <label>Localisation <span class="req">*</span></label>
              <input type="text" name="localisation" placeholder="Ex: Tétouan, Maroc" value="<?php echo isset($_POST['localisation'])?htmlspecialchars($_POST['localisation']):''; ?>">
            </div>
          </div>

          <!-- CONTACT -->
          <div class="form-group hidden" id="section_contact">
            <label>Contact</label>
            <input type="text" name="contact" placeholder="Email ou téléphone de contact" value="<?php echo isset($_POST['contact'])?htmlspecialchars($_POST['contact']):''; ?>">
          </div>

          <!-- SUBMIT -->
          <div class="hidden" id="section_submit">
            <button type="submit" class="btn-publish">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              Publier
            </button>
          </div>

        </div>
      </form>
    </div>
  </div>
</div>

<script>
function handleTypeChange() {
  const type = document.querySelector('input[name="type_demande"]:checked')?.value;
  const placeholder = document.getElementById('placeholderInfo');
  const secVol = document.getElementById('section_volontariat');
  const secDon = document.getElementById('section_donation');
  const secDesc = document.getElementById('section_description');
  const secDateLieu = document.getElementById('section_datelieu');
  const secContact = document.getElementById('section_contact');
  const secSubmit = document.getElementById('section_submit');

  placeholder.classList.add('hidden');
  secVol.classList.add('hidden');
  secDon.classList.add('hidden');
  secDesc.classList.remove('hidden');
  secDateLieu.classList.remove('hidden');
  secContact.classList.remove('hidden');
  secSubmit.classList.remove('hidden');

  if (type === 'volontariat') {
    secVol.classList.remove('hidden');
  } else if (type === 'donation') {
    secDon.classList.remove('hidden');
    // Reset sous-type fields
    document.getElementById('section_argent').classList.add('hidden');
    document.getElementById('section_autre').classList.add('hidden');
  }
}

function handleSousTypeChange() {
  const sousType = document.querySelector('input[name="sous_type_donation"]:checked')?.value;
  document.getElementById('section_argent').classList.add('hidden');
  document.getElementById('section_autre').classList.add('hidden');
  if (sousType === 'argent') {
    document.getElementById('section_argent').classList.remove('hidden');
  } else if (sousType === 'autre') {
    document.getElementById('section_autre').classList.remove('hidden');
  }
}

// Restore state after form submit error
window.addEventListener('DOMContentLoaded', function() {
  const type = document.querySelector('input[name="type_demande"]:checked');
  if (type) {
    handleTypeChange();
    const sousType = document.querySelector('input[name="sous_type_donation"]:checked');
    if (sousType) handleSousTypeChange();
  }
});

document.addEventListener('click', function(e) {
  const area = document.getElementById('profileArea');
  if (area && !area.contains(e.target)) area.classList.remove('active');
});
</script>
</body>
</html>