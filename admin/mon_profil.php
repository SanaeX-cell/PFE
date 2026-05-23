<?php
session_start();
require_once '../connexion_bd.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../connexion.php');
    exit();
}

$success_email = '';
$success_mdp   = '';
$error_email   = '';
$error_mdp     = '';

// ========== TRAITEMENT EMAIL ==========
if (isset($_POST['changer_email'])) {
    $nouvel_email = trim($_POST['nouvel_email']);
    if (!filter_var($nouvel_email, FILTER_VALIDATE_EMAIL)) {
        $error_email = "L'adresse email n'est pas valide.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $nouvel_email, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error_email = "Cet email est déjà utilisé par un autre compte.";
        } else {
            $stmt2 = $conn->prepare("UPDATE admins SET email = ? WHERE id = ?");
            $stmt2->bind_param("si", $nouvel_email, $_SESSION['user_id']);
            if ($stmt2->execute()) {
                $_SESSION['user_email'] = $nouvel_email;
                $success_email = "Email mis à jour avec succès.";
            } else {
                $error_email = "Une erreur est survenue. Veuillez réessayer.";
            }
        }
    }
}

// ========== TRAITEMENT MOT DE PASSE ==========
if (isset($_POST['changer_mdp'])) {
    $ancien_mdp     = $_POST['ancien_mdp'];
    $nouveau_mdp    = $_POST['nouveau_mdp'];
    $confirm_mdp    = $_POST['confirm_mdp'];

    $stmt = $conn->prepare("SELECT mot_de_passe FROM admins WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($hash_actuel);
    $stmt->fetch();
    $stmt->close();

    if (!password_verify($ancien_mdp, $hash_actuel)) {
        $error_mdp = "L'ancien mot de passe est incorrect.";
    } elseif (strlen($nouveau_mdp) < 6) {
        $error_mdp = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
    } elseif ($nouveau_mdp !== $confirm_mdp) {
        $error_mdp = "Les mots de passe ne correspondent pas.";
    } else {
        $new_hash = password_hash($nouveau_mdp, PASSWORD_BCRYPT);
        $stmt2 = $conn->prepare("UPDATE admins SET mot_de_passe = ? WHERE id = ?");
        $stmt2->bind_param("si", $new_hash, $_SESSION['user_id']);
        if ($stmt2->execute()) {
            $success_mdp = "Mot de passe mis à jour avec succès.";
        } else {
            $error_mdp = "Une erreur est survenue. Veuillez réessayer.";
        }
    }
}

// ========== RÉCUPÉRER LES INFOS ADMIN ==========
$stmt = $conn->prepare("SELECT email, date_creation FROM admins WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($admin_email, $admin_date);
$stmt->fetch();
$stmt->close();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ConnectAid - Mon Profil</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg: #f5f6fa;
    --sidebar-bg: #fff;
    --card-bg: #fff;
    --text-primary: #1a1d2e;
    --text-secondary: #8b8fa8;
    --text-light: #b0b3c6;
    --accent-teal: #1CB8B2;
    --accent-blue: #9FE7F5;
    --accent-gold: #F7AD19;
    --accent-orange: #F47B20;
    --green: #22c55e;
    --red: #ef4444;
    --border: #f0f1f7;
    --shadow: 0 2px 20px rgba(0,0,0,0.06);
    --radius: 18px;
    --sidebar-width: 220px;
  }

  body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text-primary); display: flex; min-height: 100vh; font-size: 14px; }

  /* ── SIDEBAR ── */
  .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); display: flex; flex-direction: column; padding: 28px 0; position: fixed; top: 0; left: 0; bottom: 0; border-right: 1px solid var(--border); z-index: 10; }
  .logo { display: flex; align-items: center; justify-content: flex-end; width: 93%; padding: 0 24px 32px 18px; }
  .logo-image { width: 60px; height: 60px; object-fit: contain; border-radius: 12px; flex-shrink: 0; }
  .logo-text { font-size: 20px; font-weight: 700; letter-spacing: -0.5px; white-space: nowrap; }
  .logo-text span:first-child { color: var(--accent-teal); }
  .logo-text span:last-child  { color: var(--accent-orange); }
  nav { flex: 1; margin-top: 8px; }
  .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 24px; color: var(--text-secondary); cursor: pointer; transition: all 0.2s; font-weight: 500; font-size: 14px; position: relative; margin: 2px 0; }
  .nav-item:hover { color: var(--text-primary); background: var(--bg); }
  .nav-item.active { color: var(--accent-teal); background: #E1F7F6; }
  .nav-item.active::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 3px; height: 60%; background: var(--accent-teal); border-radius: 0 4px 4px 0; }
  .nav-icon { width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .sidebar-bottom { padding: 0; border-top: 1px solid var(--border); padding-top: 12px; margin-top: auto; }
  .sidebar-bottom .nav-item { background: var(--accent-teal); color: white; border-radius: 40px; margin: 8px 16px; justify-content: center; }
  .sidebar-bottom .nav-item:hover { background: #138F8A; color: white; }
  .sidebar-bottom .nav-item::before { display: none; }

  /* ── MAIN ── */
  .main { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

  /* ── TOPBAR ── */
  .topbar { background: transparent; padding: 16px 32px 16px calc(32px + 15px); display: flex; align-items: center; gap: 12px; position: sticky; top: 0; z-index: 5; flex-wrap: wrap; }
  .greeting { flex: 1; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 10px 18px; box-shadow: var(--shadow); min-width: 200px; }
  .greeting h2 { font-size: 15px; font-weight: 700; margin-bottom: 4px; }
  .greeting p  { font-size: 11px; color: var(--text-secondary); font-weight: 400; margin-top: 1px; }
  .date-picker { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 10px 16px; font-size: 13px; font-weight: 500; cursor: pointer; color: var(--text-primary); box-shadow: var(--shadow); white-space: nowrap; }
  .profile-dropdown { position: relative; }
  .user-info { display: flex; align-items: center; gap: 10px; cursor: pointer; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 6px 14px 6px 6px; box-shadow: var(--shadow); }
  .avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--accent-teal); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #fff; overflow: hidden; flex-shrink: 0; }
  .user-details { display: flex; flex-direction: column; gap: 1px; }
  .user-name  { font-weight: 600; font-size: 13px; white-space: nowrap; color: var(--text-primary); }
  .user-email { font-size: 11px; color: var(--text-secondary); white-space: nowrap; }
  .dropdown-icon { font-size: 12px; transition: transform 0.2s; display: inline-flex; align-items: center; color: #8b8fa8; flex-shrink: 0; }
  .dropdown-menu { position: absolute; top: 100%; right: 0; margin-top: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); min-width: 200px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s; z-index: 100; }
  .profile-dropdown.active .dropdown-menu { opacity: 1; visibility: visible; transform: translateY(0); }
  .profile-dropdown.active .dropdown-icon { transform: rotate(180deg); }
  .dropdown-menu a { display: flex; align-items: center; gap: 10px; padding: 10px 16px; color: var(--text-secondary); text-decoration: none; font-size: 13px; transition: background 0.2s; }
  .dropdown-menu a:hover { background: var(--bg); color: var(--text-primary); }
  .dropdown-menu hr { margin: 4px 0; border: none; border-top: 1px solid var(--border); }

  /* ── CONTENT ── */
  .content { padding: 20px 32px 40px calc(32px + 15px); display: flex; flex-direction: column; gap: 24px; }

  /* ── BREADCRUMB ── */
  .breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-secondary); }
  .breadcrumb a { color: var(--accent-teal); text-decoration: none; font-weight: 500; }
  .breadcrumb a:hover { text-decoration: underline; }
  .breadcrumb span { color: var(--text-light); }

  /* ── PROFILE HERO ── */
  .profile-hero {
    background: linear-gradient(135deg, #1CB8B2 0%, #138F8A 100%);
    border-radius: var(--radius);
    padding: 32px 36px;
    display: flex;
    align-items: center;
    gap: 24px;
    color: #fff;
    position: relative;
    overflow: hidden;
  }
  /* Bulle 1 — haut droite */
  .profile-hero::before {
    content: '';
    position: absolute;
    top: 2px; right: 60px;
    width: 70px; height: 70px;
    border-radius: 50%;
    background: rgba(255,255,255,0.22);
  }
  /* Bulle 2 — bas gauche du triangle */
  .profile-hero::after {
    content: '';
    position: absolute;
    bottom: 20px; right: 140px;
    width: 55px; height: 55px;
    border-radius: 50%;
    background: rgba(255,255,255,0.18);
  }
  /* Bulle 3 — bas droite du triangle */
  .bubble3 {
    position: absolute;
    bottom: 2px; right: 5px;
    width: 40px; height: 40px;
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    z-index: 0;
  }
  .hero-avatar {
    width: 100px; height: 100px;
    border-radius: 50%;
    background: rgba(255,255,255,0.25);
    display: flex; align-items: center; justify-content: center;
    font-size: 40px; font-weight: 700;
    border: 3px solid rgba(255,255,255,0.4);
    flex-shrink: 0;
    position: relative; z-index: 1;
  }
  .hero-info { position: relative; z-index: 1; }
  .hero-info h1 { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
  .hero-info p  { font-size: 13px; opacity: 0.8; }
  .hero-badge {
    margin-left: auto;
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 30px;
    padding: 6px 16px;
    font-size: 12px;
    font-weight: 600;
    position: relative; z-index: 1;
  }

  /* ── CARDS GRID ── */
  .profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
  }

  /* ── CARD ── */
  .card { background: var(--card-bg); border-radius: var(--radius); padding: 28px 30px; box-shadow: var(--shadow); }
  .card-header-profile {
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 24px;
    padding-bottom: 18px;
    border-bottom: 1px solid var(--border);
  }
  .card-icon {
    width: 40px; height: 40px;
    border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }
  .card-icon.teal   { background: #E1F7F6; color: var(--accent-teal); }
  .card-icon.orange { background: #FFF0E6; color: var(--accent-orange); }
  .card-title-profile { font-size: 15px; font-weight: 700; }
  .card-subtitle { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }

  /* ── FORM ── */
  .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
  .form-group:last-of-type { margin-bottom: 0; }
  .form-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
  .form-input {
    width: 100%;
    padding: 11px 14px;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    font-family: inherit;
    font-size: 13px;
    color: var(--text-primary);
    background: var(--bg);
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
  }
  .form-input:focus {
    border-color: var(--accent-teal);
    box-shadow: 0 0 0 3px rgba(28,184,178,0.1);
    background: #fff;
  }
  .form-input::placeholder { color: var(--text-light); }

  .input-with-icon { position: relative; }
  .input-with-icon .form-input { padding-right: 42px; }
  .toggle-pw {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    color: var(--text-light); padding: 4px;
    display: flex; align-items: center;
    transition: color 0.2s;
  }
  .toggle-pw:hover { color: var(--accent-teal); }

  /* ── DIVIDER ── */
  .form-divider {
    display: flex; align-items: center; gap: 10px;
    margin: 20px 0;
    font-size: 11px; font-weight: 600;
    color: var(--text-light);
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .form-divider::before, .form-divider::after {
    content: ''; flex: 1;
    height: 1px; background: var(--border);
  }

  /* ── BUTTON ── */
  .btn-save {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 10px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s;
    margin-top: 22px;
  }
  .btn-save.teal   { background: var(--accent-teal); color: #fff; }
  .btn-save.teal:hover   { background: #138F8A; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(28,184,178,0.35); }
  .btn-save.orange { background: var(--accent-orange); color: #fff; }
  .btn-save.orange:hover { background: #E0691A; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(244,123,32,0.35); }

  /* ── ALERT ── */
  .alert {
    padding: 10px 14px;
    border-radius: 9px;
    font-size: 12.5px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
  }
  .alert-success { background: rgba(34,197,94,0.1); color: #16a34a; border: 1px solid rgba(34,197,94,0.25); }
  .alert-error   { background: rgba(239,68,68,0.1);  color: #dc2626; border: 1px solid rgba(239,68,68,0.25); }

  /* ── INFO ROW ── */
  .info-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 14px;
    background: var(--bg);
    border-radius: 10px;
    margin-bottom: 12px;
  }
  .info-label { font-size: 11px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
  .info-value { font-size: 13px; font-weight: 500; color: var(--text-primary); }

  @media (max-width: 900px) {
    .profile-grid { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<aside class="sidebar">
  <div class="logo">
    <img src="../images/logo.png" alt="Logo" class="logo-image">
    <div class="logo-text"><span>Connect</span><span>Aid</span></div>
  </div>
  <nav>
    <div class="nav-item" data-page="dashboard">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
      Tableau de bord
    </div>
    <div class="nav-item" data-page="demandes">
      <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m1.679-4.493-1.335 2.226a.75.75 0 0 1-1.174.144l-.774-.773a.5.5 0 0 1 .708-.708l.547.548 1.17-1.951a.5.5 0 1 1 .858.514"/><path d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6.5a.5.5 0 0 1-1 0V1H3v14h3v-2.5a.5.5 0 0 1 .5-.5H8v4H3a1 1 0 0 1-1-1z"/><path d="M4.5 2a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm-6 3a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm-6 3a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/></svg></span>
      Demandes
    </div>
    <div class="nav-item" data-page="organisations-nav">
      <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4 2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zM4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM7.5 5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM4.5 8a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/><path d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zm11 0H3v14h3v-2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V15h3z"/></svg></span>
      Organisations
    </div>
    <div class="nav-item" data-page="contributeurs">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
      Contributeurs
    </div>
  </nav>
  <div class="sidebar-bottom">
    <div class="nav-item" data-page="deconnexion">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
      Déconnexion
    </div>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <div class="greeting">
      <h2>Mon profil</h2>
      <p>Gérez vos informations et votre sécurité</p>
    </div>
    <div class="date-picker">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <?php setlocale(LC_TIME, 'fr_FR.utf8', 'fr_FR', 'fr'); echo strftime('%d %B %Y'); ?>
    </div>
    <div class="profile-dropdown" id="profileDropdown">
      <div class="user-info" onclick="toggleDropdown()">
        <div class="avatar"><?php echo strtoupper(substr($_SESSION['user_nom'], 0, 1)); ?></div>
        <div class="user-details">
          <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_nom']); ?></span>
        </div>
        <span class="dropdown-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708"/></svg>
        </span>
      </div>
      <div class="dropdown-menu">
        <a href="mon_profil.php">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Mon profil
        </a>
        <hr>
        <a href="../logout.php">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Déconnexion
        </a>
      </div>
    </div>
  </header>

  <div class="content">

    <!-- Breadcrumb -->
    <div class="breadcrumb">
      <a href="dashboard.php">Tableau de bord</a>
      <span>›</span>
      <span>Mon profil</span>
    </div>

    <!-- Hero -->
    <div class="profile-hero">
      <div class="bubble3"></div>
      <div class="hero-avatar"><?php echo strtoupper(substr($_SESSION['user_nom'], 0, 1)); ?></div>
      <div class="hero-info">
        <h1><?php echo htmlspecialchars($_SESSION['user_nom']); ?></h1>
        <p>Membre depuis le <?php echo date('d/m/Y', strtotime($admin_date)); ?></p>
      </div>
      <div class="hero-badge">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display:inline;vertical-align:middle;margin-right:5px"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Administrateur
      </div>
    </div>

    <!-- Grid -->
    <div class="profile-grid">

      <!-- ── CARD EMAIL ── -->
      <div class="card">
        <div class="card-header-profile">
          <div class="card-icon teal">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </div>
          <div>
            <div class="card-title-profile">Adresse email</div>
            <div class="card-subtitle">Modifiez votre email de connexion</div>
          </div>
        </div>

        <?php if ($success_email): ?>
          <div class="alert alert-success">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            <?php echo htmlspecialchars($success_email); ?>
          </div>
        <?php endif; ?>
        <?php if ($error_email): ?>
          <div class="alert alert-error">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?php echo htmlspecialchars($error_email); ?>
          </div>
        <?php endif; ?>

        <!-- Info actuelle -->
        <div class="info-row">
          <div>
            <div class="info-label">Email actuel</div>
            <div class="info-value"><?php echo htmlspecialchars($admin_email); ?></div>
          </div>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b0b3c6" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </div>

        <div class="form-divider">Nouveau email</div>

        <form method="POST" action="">
          <div class="form-group">
            <label class="form-label">Nouvelle adresse email</label>
            <input type="email" name="nouvel_email" class="form-input" placeholder="exemple@domaine.com" required>
          </div>
          <button type="submit" name="changer_email" class="btn-save teal">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Enregistrer l'email
          </button>
        </form>
      </div>

      <!-- ── CARD MOT DE PASSE ── -->
      <div class="card">
        <div class="card-header-profile">
          <div class="card-icon orange">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </div>
          <div>
            <div class="card-title-profile">Mot de passe</div>
            <div class="card-subtitle">Changez votre mot de passe de connexion</div>
          </div>
        </div>

        <?php if ($success_mdp): ?>
          <div class="alert alert-success">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            <?php echo htmlspecialchars($success_mdp); ?>
          </div>
        <?php endif; ?>
        <?php if ($error_mdp): ?>
          <div class="alert alert-error">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?php echo htmlspecialchars($error_mdp); ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="">
          <div class="form-group">
            <label class="form-label">Ancien mot de passe</label>
            <div class="input-with-icon">
              <input type="password" name="ancien_mdp" id="ancienMdp" class="form-input" placeholder="Votre mot de passe actuel" required>
              <button type="button" class="toggle-pw" onclick="togglePw('ancienMdp', this)" tabindex="-1">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Nouveau mot de passe</label>
            <div class="input-with-icon">
              <input type="password" name="nouveau_mdp" id="nouveauMdp" class="form-input" placeholder="Minimum 6 caractères" required>
              <button type="button" class="toggle-pw" onclick="togglePw('nouveauMdp', this)" tabindex="-1">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Confirmer le nouveau mot de passe</label>
            <div class="input-with-icon">
              <input type="password" name="confirm_mdp" id="confirmMdp" class="form-input" placeholder="Répétez le nouveau mot de passe" required>
              <button type="button" class="toggle-pw" onclick="togglePw('confirmMdp', this)" tabindex="-1">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>
          <button type="submit" name="changer_mdp" class="btn-save orange">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Enregistrer le mot de passe
          </button>
        </form>
      </div>

    </div><!-- /profile-grid -->
  </div><!-- /content -->
</div><!-- /main -->

<script>
function togglePw(fieldId, btn) {
  const input = document.getElementById(fieldId);
  const isText = input.type === 'text';
  input.type = isText ? 'password' : 'text';
  btn.innerHTML = isText
    ? '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>'
    : '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
}

function toggleDropdown() { document.getElementById('profileDropdown').classList.toggle('active'); }
document.addEventListener('click', function(e) {
  const d = document.getElementById('profileDropdown');
  if (d && !d.contains(e.target)) d.classList.remove('active');
});

document.querySelectorAll('.nav-item[data-page]').forEach(function(item) {
  item.addEventListener('click', function() {
    const page = this.getAttribute('data-page');
    if (page === 'dashboard') window.location.href = 'dashboard.php';
    else if (page === 'demandes') window.location.href = 'demandes.php';
    else if (page === 'organisations-nav') window.location.href = 'organisations.php';
    else if (page === 'contributeurs') window.location.href = 'contributeurs.php';
    else if (page === 'deconnexion') window.location.href = '../logout.php';
  });
});
</script>
</body>
</html>
