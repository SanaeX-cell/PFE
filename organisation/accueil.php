<?php
session_start();
require_once '../connexion_bd.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'organisation') {
    header('Location: ../connexion.php');
    exit();
}

$org_id = $_SESSION['user_id'];

// Récupérer les infos de l'organisation
$sql = "SELECT * FROM organisations WHERE id = $org_id";
$result = mysqli_query($conn, $sql);
$org = mysqli_fetch_assoc($result);

// Statistiques
$sql_benevoles = "SELECT COUNT(*) as nb FROM participations p JOIN posts po ON p.post_id = po.id WHERE po.organisation_id = $org_id AND p.statut = 'accepte'";
$result_b = mysqli_query($conn, $sql_benevoles);
$total_benevoles = mysqli_fetch_assoc($result_b)['nb'];

$sql_posts = "SELECT COUNT(*) as nb FROM posts WHERE organisation_id = $org_id";
$result_p = mysqli_query($conn, $sql_posts);
$total_posts = mysqli_fetch_assoc($result_p)['nb'];

$sql_notifs = "SELECT COUNT(*) as nb FROM notifications_organisation WHERE organisation_id = $org_id AND statut = 'non_lue'";
$result_n = mysqli_query($conn, $sql_notifs);
$total_notifs = mysqli_fetch_assoc($result_n)['nb'];

// Derniers posts (3)
$sql_derniers = "SELECT * FROM posts WHERE organisation_id = $org_id ORDER BY date_creation DESC LIMIT 3";
$result_derniers = mysqli_query($conn, $sql_derniers);
$derniers_posts = [];
while($row = mysqli_fetch_assoc($result_derniers)) {
    $derniers_posts[] = $row;
}

// Données pour le graphique : répartition des posts par type (volontariat / donation)
$sql_types = "SELECT type_demande, COUNT(*) as nb FROM posts WHERE organisation_id = $org_id GROUP BY type_demande";
$result_types = mysqli_query($conn, $sql_types);
$types_data = ['volontariat' => 0, 'donation' => 0];
while($row = mysqli_fetch_assoc($result_types)) {
    $types_data[$row['type_demande']] = (int)$row['nb'];
}

// Pour le graphique en barres (évolution mensuelle) : on prend les 6 derniers mois
$mois_labels = [];
$mois_data = [];
for ($i = 5; $i >= 0; $i--) {
    $mois = date('Y-m', strtotime("-$i months"));
    $mois_labels[] = date('M Y', strtotime($mois . '-01'));
    $sql_mois = "SELECT COUNT(*) as nb FROM posts WHERE organisation_id = $org_id AND DATE_FORMAT(date_creation, '%Y-%m') = '$mois'";
    $res_mois = mysqli_query($conn, $sql_mois);
    $mois_data[] = mysqli_fetch_assoc($res_mois)['nb'];
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ConnectAid - Accueil Organisation</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
  /* ===== RESET & VARIABLES ===== */
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #f5f6fa;
    --sidebar-bg: #fff;
    --card-bg: #fff;
    --text-primary: #1a1d2e;
    --text-secondary: #8b8fa8;
    --text-light: #b0b3c6;
    --accent-teal: #1CB8B2;
    --accent-orange: #F47B20;
    --accent-orange-dark: #D95C10;
    --accent-orange-light: #FF9A4D;
    --accent-yellow: #F7AD19;
    --border: #f0f1f7;
    --shadow: 0 2px 20px rgba(0,0,0,0.06);
    --radius: 18px;
    --sidebar-width: 220px;
  }
  body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text-primary); display: flex; min-height: 100vh; font-size: 14px; }

  /* ===== SIDEBAR ===== */
  .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); display: flex; flex-direction: column; padding: 28px 0; position: fixed; top: 0; left: 0; bottom: 0; border-right: 1px solid var(--border); z-index: 10; }
  .logo { display: flex; align-items: center; justify-content: flex-end; width: 93%; padding: 0 24px 32px 18px; }
  .logo-image { width: 60px; height: 60px; object-fit: contain; border-radius: 12px; flex-shrink: 0; }
  .logo-text { font-size: 20px; font-weight: 700; letter-spacing: -0.5px; white-space: nowrap; }
  .logo-text span:first-child { color: var(--accent-teal); }
  .logo-text span:last-child { color: var(--accent-orange); }
  nav { flex: 1; margin-top: 8px; }
  .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 24px; color: var(--text-secondary); cursor: pointer; transition: all 0.2s; font-weight: 500; font-size: 14px; position: relative; margin: 2px 0; text-decoration: none; }
  .nav-item:hover { color: var(--text-primary); background: var(--bg); }
  .nav-item.active { color: var(--accent-orange); background: #FFF0E6; }
  .nav-item.active::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 3px; height: 60%; background: var(--accent-orange); border-radius: 0 4px 4px 0; }
  .nav-icon { width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .sidebar-bottom { padding: 0; border-top: 1px solid var(--border); padding-top: 12px; margin-top: auto; }
  .sidebar-bottom .nav-item { background: var(--accent-orange); color: white; border-radius: 40px; margin: 8px 16px; justify-content: center; }
  .sidebar-bottom .nav-item:hover { background: var(--accent-orange-dark); color: white; }
  .sidebar-bottom .nav-item::before { display: none; }

  /* ===== MAIN ===== */
  .main { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; min-height: 100vh; padding: 24px 32px 40px; }

  /* ===== HEADER ===== */
  .header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 28px;
    flex-wrap: wrap;
  }
  .greeting-box {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 10px 24px;
    box-shadow: var(--shadow);
    flex: 0 1 auto;
    min-width: 200px;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .greeting-box h1 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
  }
  .greeting-box .separator {
    color: var(--text-light);
    font-weight: 300;
  }
  .greeting-box .sub {
    font-size: 13px;
    color: var(--text-secondary);
    font-weight: 500;
  }

  .header-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }
  .org-name-box {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 10px 16px 10px 18px;
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    min-width: 200px;
    max-width: 300px;
  }
  .org-name-box:hover { border-color: var(--accent-orange-light); }
  .org-name-box .name {
    font-weight: 600;
    font-size: 14px;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
  }
  .org-name-box .chevron {
    color: var(--text-secondary);
    transition: transform 0.2s;
    font-size: 12px;
    display: flex;
    align-items: center;
    flex-shrink: 0;
  }
  .org-name-box.active .chevron { transform: rotate(180deg); }

  .dropdown-menu-header {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    min-width: 180px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px);
    transition: all 0.2s;
    z-index: 100;
  }
  .org-name-box.active .dropdown-menu-header {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
  }
  .dropdown-menu-header a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 13px;
    transition: background 0.2s;
  }
  .dropdown-menu-header a:hover { background: var(--bg); color: var(--text-primary); }
  .dropdown-menu-header hr { margin: 4px 0; border: none; border-top: 1px solid var(--border); }

  .notif-icon {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--shadow);
    position: relative;
    text-decoration: none;
    color: var(--text-secondary);
    transition: all 0.2s;
  }
  .notif-icon:hover { border-color: var(--accent-orange-light); color: var(--accent-orange); }
  .notif-count-header {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #EF4444;
    color: #fff;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 11px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .search-box {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 0 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: var(--shadow);
    height: 44px;
  }
  .search-box input {
    border: none;
    outline: none;
    background: transparent;
    font-family: inherit;
    font-size: 13px;
    width: 180px;
    color: var(--text-primary);
  }
  .search-box input::placeholder { color: var(--text-light); }

  /* ===== WELCOME BOX ===== */
  .welcome-box {
    background: linear-gradient(135deg, #F47B20 0%, #F7AD19 50%, #FFD966 100%);
    border-radius: var(--radius);
    padding: 32px 40px;
    color: #fff;
    margin-bottom: 28px;
    min-height: 100px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }
  .welcome-box h2 {
    font-size: 28px;
    font-weight: 700;
    margin: 0;
    white-space: nowrap;
  }
  .welcome-box p {
    font-size: 15px;
    opacity: 0.92;
    line-height: 1.6;
    margin: 0;
    display: inline;
  }

  /* ===== STATISTIQUES ===== */
  .section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 18px;
    color: var(--text-primary);
  }
  .section-title .icon {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #FFF0E6;
    border-radius: 10px;
    color: var(--accent-orange);
  }

  .stats-grid {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 24px;
    margin-bottom: 28px;
  }
  .stats-cards {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
  }
  .stat-card {
    background: #fff;
    border-radius: var(--radius);
    padding: 16px 20px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: transform 0.2s, box-shadow 0.2s;
    min-height: 80px;
  }
  .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(0,0,0,0.08); }
  .stat-card .icon-box {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .stat-card .icon-box.teal { background: linear-gradient(135deg, #1CB8B2, #0E9E98); color: #fff; }
  .stat-card .icon-box.orange { background: linear-gradient(135deg, #F47B20, #D95C10); color: #fff; }
  .stat-card .info { flex: 1; }
  .stat-card .info .number {
    font-size: 26px;
    font-weight: 700;
    line-height: 1.2;
    color: var(--text-primary);
  }
  .stat-card .info .label {
    font-size: 13px;
    color: var(--text-secondary);
    font-weight: 500;
  }

  .chart-container {
    background: #fff;
    border-radius: var(--radius);
    padding: 20px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    height: 100%;
    min-height: 200px;
    display: flex;
    flex-direction: column;
  }
  .chart-container .chart-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    text-align: center;
    margin-bottom: 10px;
    flex-shrink: 0;
  }
  .chart-container .chart-wrapper {
    flex: 1;
    min-height: 0;
  }
  .chart-container canvas { 
    width: 100% !important;
    height: 100% !important;
    max-height: 180px;
  }

  /* ===== ACCÈS RAPIDES ===== */
  .quick-access {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 28px;
  }
  .quick-btn {
    background: #fff;
    border-radius: var(--radius);
    padding: 20px 16px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    text-decoration: none;
    color: var(--text-primary);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    transition: all 0.3s;
    text-align: center;
  }
  .quick-btn:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.1); border-color: var(--accent-orange-light); }
  .quick-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
  }
  .quick-icon.orange { background: #FFF0E6; color: var(--accent-orange); }
  .quick-icon.teal { background: #E1F7F6; color: var(--accent-teal); }
  .quick-icon.yellow { background: #FFF8E0; color: var(--accent-yellow); }
  .quick-icon.pink { background: #FDE8E8; color: #E87171; }
  .quick-btn span { font-weight: 600; font-size: 14px; }
  .quick-btn small { font-size: 12px; color: var(--text-secondary); }

  /* ===== DERNIERS POSTS ===== */
  .posts-slider-wrapper {
    background: #fff;
    border-radius: var(--radius);
    padding: 24px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
  }
  .slider-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
  }
  .slider-header h3 {
    font-size: 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .slider-nav {
    display: flex;
    gap: 8px;
  }
  .slider-nav button {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: #fff;
    color: var(--text-secondary);
    font-size: 16px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .slider-nav button:hover { background: var(--bg); border-color: var(--accent-orange); color: var(--accent-orange); }
  .slider-container {
    overflow: hidden;
    position: relative;
  }
  .slider-track {
    display: flex;
    gap: 20px;
    transition: transform 0.3s ease;
  }
  .post-card {
    flex: 0 0 calc(50% - 10px);
    min-width: 0;
    background: var(--bg);
    border-radius: 14px;
    padding: 18px 20px;
    border: 1px solid var(--border);
  }
  .post-card .post-title {
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 6px;
  }
  .post-card .post-desc {
    font-size: 13px;
    color: var(--text-secondary);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 10px;
  }
  .post-card .post-meta {
    display: flex;
    gap: 12px;
    font-size: 12px;
    color: var(--text-light);
  }
  .post-card .post-meta span { display: flex; align-items: center; gap: 4px; }

  @media (max-width: 992px) {
    .stats-grid { grid-template-columns: 1fr; }
    .stats-cards { grid-template-columns: 1fr 1fr; }
    .quick-access { grid-template-columns: repeat(2, 1fr); }
    .post-card { flex: 0 0 100%; }
  }
  @media (max-width: 768px) {
    .main { padding: 16px; }
    .header { flex-direction: column; align-items: stretch; }
    .greeting-box { justify-content: center; flex-wrap: wrap; }
    .header-right { justify-content: center; flex-wrap: wrap; }
    .search-box input { width: 120px; }
    .stats-cards { grid-template-columns: 1fr; }
    .quick-access { grid-template-columns: 1fr 1fr; }
    .welcome-box { 
      padding: 20px 24px; 
      text-align: center; 
      justify-content: center;
      flex-direction: column;
      gap: 4px;
    }
    .welcome-box h2 { 
      font-size: 22px; 
      white-space: normal;
    }
    .welcome-box p { 
      max-width: 100%; 
      display: block;
    }
    .org-name-box { max-width: 100%; min-width: auto; }
  }
</style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar">
  <div class="logo">
    <img src="../images/logo.png" alt="Logo" class="logo-image">
    <div class="logo-text"><span>Connect</span><span>Aid</span></div>
  </div>
  <nav>
    <a href="accueil.php" class="nav-item <?php echo ($current_page=='accueil.php')?'active':''; ?>">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
      Accueil
    </a>
    <a href="ma_page.php" class="nav-item <?php echo ($current_page=='ma_page.php')?'active':''; ?>">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></span>
      Ma page
    </a>
    <a href="creer_post.php" class="nav-item <?php echo ($current_page=='creer_post.php')?'active':''; ?>">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
      Créer un post
    </a>
    <a href="benevoles.php" class="nav-item <?php echo ($current_page=='benevoles.php')?'active':''; ?>">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
      Bénévoles
    </a>
    <a href="messages.php" class="nav-item <?php echo ($current_page=='messages.php')?'active':''; ?>">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
      Messages
    </a>
    <a href="notifications.php" class="nav-item <?php echo ($current_page=='notifications.php')?'active':''; ?>">
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

<!-- ===== MAIN CONTENT ===== -->
<div class="main">

  <!-- ===== HEADER ===== -->
  <div class="header">
    <div class="greeting-box">
      <h1>Accueil</h1>
      <span class="separator">·</span>
      <span class="sub">Espace organisation</span>
    </div>

    <div class="header-right">
      <!-- Barre de recherche -->
      <div class="search-box">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" placeholder="Rechercher...">
      </div>

      <!-- Notifications -->
      <a href="notifications.php" class="notif-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <?php if($total_notifs > 0): ?>
          <span class="notif-count-header"><?php echo $total_notifs; ?></span>
        <?php endif; ?>
      </a>

      <!-- Profil avec nom de l'organisation -->
      <div class="org-name-box" id="orgDropdown">
        <span class="name"><?php echo htmlspecialchars($org['nom_organisation']); ?></span>
        <span class="chevron">▾</span>
        <div class="dropdown-menu-header">
          <a href="ma_page.php">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Ma page
          </a>
          <hr>
          <a href="../logout.php">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Déconnexion
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== WELCOME BOX ===== -->
  <div class="welcome-box">
    <h2>Bienvenue, <?php echo htmlspecialchars($org['nom_organisation']); ?> !</h2>
    <p>Content de vous voir ! Votre plateforme vous attend pour continuer à faire la différence.</p>
  </div>

  <!-- ===== STATISTIQUES ===== -->
  <div class="section-title">
    <span class="icon">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12v-2a5 5 0 0 0-5-5H8a5 5 0 0 0-5 5v2"/><circle cx="12" cy="16" r="5"/><path d="M12 11v5"/><path d="M9 16h6"/></svg>
    </span>
    Statistiques
  </div>

  <div class="stats-grid">
    <!-- Cartes verticales -->
    <div class="stats-cards">
      <div class="stat-card">
        <div class="icon-box teal">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="info">
          <div class="number"><?php echo $total_benevoles; ?></div>
          <div class="label">Bénévoles acceptés</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="icon-box orange">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        </div>
        <div class="info">
          <div class="number"><?php echo $total_posts; ?></div>
          <div class="label">Posts publiés</div>
        </div>
      </div>
    </div>

    <!-- Graphique -->
    <div class="chart-container">
      <div class="chart-title">📊 Évolution des publications (6 derniers mois)</div>
      <div class="chart-wrapper">
        <canvas id="statsChart"></canvas>
      </div>
    </div>
  </div>

  <!-- ===== ACCÈS RAPIDES ===== -->
  <div class="section-title">
    <span class="icon">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
    </span>
    Accès rapides
  </div>

  <div class="quick-access">
    <a href="creer_post.php" class="quick-btn">
      <div class="quick-icon orange">📝</div>
      <span>Nouveau post</span>
      <small>Appel à bénévoles ou dons</small>
    </a>
    <a href="benevoles.php" class="quick-btn">
      <div class="quick-icon teal">👥</div>
      <span>Bénévoles</span>
      <small>Gérer vos volontaires</small>
    </a>
    <a href="ma_page.php" class="quick-btn">
      <div class="quick-icon yellow">🏠</div>
      <span>Ma page</span>
      <small>Voir votre profil public</small>
    </a>
    <a href="messages.php" class="quick-btn">
      <div class="quick-icon pink">💬</div>
      <span>Messages</span>
      <small>Consulter vos conversations</small>
    </a>
  </div>

  <!-- ===== DERNIERS POSTS ===== -->
  <div class="posts-slider-wrapper">
    <div class="slider-header">
      <h3>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="12" y2="16"/></svg>
        Derniers posts
      </h3>
      <div class="slider-nav">
        <button id="prevPost">‹</button>
        <button id="nextPost">›</button>
      </div>
    </div>
    <div class="slider-container">
      <div class="slider-track" id="sliderTrack">
        <?php if(count($derniers_posts) > 0): ?>
          <?php foreach($derniers_posts as $post): ?>
            <div class="post-card">
              <div class="post-title"><?php echo htmlspecialchars($post['titre']); ?></div>
              <div class="post-desc"><?php echo htmlspecialchars(substr($post['description'], 0, 80)) . (strlen($post['description']) > 80 ? '...' : ''); ?></div>
              <div class="post-meta">
                <span>📅 <?php echo date('d/m/Y', strtotime($post['date_creation'])); ?></span>
                <span>📍 <?php echo htmlspecialchars($post['localisation']); ?></span>
                <span><?php echo $post['type_demande'] === 'volontariat' ? '🤝 Volontariat' : '🎁 Donation'; ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div style="padding:20px;text-align:center;color:var(--text-secondary);width:100%;">Aucun post publié pour le moment.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<script>
  // ===== DROPDOWN PROFIL =====
  document.getElementById('orgDropdown').addEventListener('click', function(e) {
    e.stopPropagation();
    this.classList.toggle('active');
  });
  document.addEventListener('click', function() {
    document.getElementById('orgDropdown').classList.remove('active');
  });

  // ===== GRAPHIQUE STATISTIQUES =====
  const ctx = document.getElementById('statsChart').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?php echo json_encode($mois_labels); ?>,
      datasets: [{
        label: 'Posts publiés',
        data: <?php echo json_encode($mois_data); ?>,
        backgroundColor: 'rgba(244, 123, 32, 0.7)',
        borderColor: '#F47B20',
        borderWidth: 2,
        borderRadius: 6,
        barPercentage: 0.6,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: '#1a1d2e', titleFont: { weight: '600' }, bodyFont: { size: 12 } }
      },
      scales: {
        y: { beginAtZero: true, grid: { color: '#f0f1f7' }, ticks: { stepSize: 1, font: { size: 11 } } },
        x: { grid: { display: false }, ticks: { font: { size: 11 } } }
      }
    }
  });

  // ===== SLIDER DERNIERS POSTS =====
  const track = document.getElementById('sliderTrack');
  const prevBtn = document.getElementById('prevPost');
  const nextBtn = document.getElementById('nextPost');
  let currentIndex = 0;
  const cards = track.querySelectorAll('.post-card');
  const totalCards = cards.length;
  let visibleCards = window.innerWidth <= 992 ? 1 : 2;

  function updateSlider() {
    if (totalCards === 0) return;
    const maxIndex = Math.max(0, totalCards - visibleCards);
    if (currentIndex > maxIndex) currentIndex = maxIndex;
    const offset = currentIndex * (100 / visibleCards);
    track.style.transform = `translateX(-${offset}%)`;
  }

  window.addEventListener('resize', function() {
    visibleCards = window.innerWidth <= 992 ? 1 : 2;
    updateSlider();
  });

  prevBtn.addEventListener('click', function() {
    if (currentIndex > 0) { currentIndex--; updateSlider(); }
  });
  nextBtn.addEventListener('click', function() {
    const maxIndex = Math.max(0, totalCards - visibleCards);
    if (currentIndex < maxIndex) { currentIndex++; updateSlider(); }
  });

  // Initialisation
  setTimeout(updateSlider, 100);
</script>

</body>
</html>