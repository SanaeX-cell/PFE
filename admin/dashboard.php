<?php
session_start();
require_once '../connexion_bd.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../connexion.php');
    exit();
}

// ========== STATISTIQUES ==========
$sql = "SELECT COUNT(*) as nb FROM organisations";
$result = mysqli_query($conn, $sql);
$total_org = mysqli_fetch_assoc($result)['nb'];

$sql = "SELECT COUNT(*) as nb FROM contributeurs";
$result = mysqli_query($conn, $sql);
$total_contrib = mysqli_fetch_assoc($result)['nb'];

$sql = "SELECT COUNT(*) as nb FROM posts";
$result = mysqli_query($conn, $sql);
$total_posts = mysqli_fetch_assoc($result)['nb'];

$sql = "SELECT COUNT(*) as nb FROM organisations WHERE statut = 'en_attente'";
$result = mysqli_query($conn, $sql);
$demandes_attente = mysqli_fetch_assoc($result)['nb'];

// ========== ÉVOLUTION DES INSCRIPTIONS ==========
$nombre_jours = date('t');
$jours_labels = [];
$contrib_data = [];
$org_data = [];

for ($i = 1; $i <= $nombre_jours; $i++) {
    $jours_labels[] = "Jour " . $i;
    $date = date('Y-m-' . str_pad($i, 2, '0', STR_PAD_LEFT));
    $sql = "SELECT COUNT(*) as nb FROM contributeurs WHERE DATE(date_inscription) = '$date'";
    $result = mysqli_query($conn, $sql);
    $contrib_data[] = mysqli_fetch_assoc($result)['nb'];
    $sql = "SELECT COUNT(*) as nb FROM organisations WHERE DATE(date_inscription) = '$date'";
    $result = mysqli_query($conn, $sql);
    $org_data[] = mysqli_fetch_assoc($result)['nb'];
}

$y_max = 35;

// ========== RÉPARTITION DES POSTS ==========
$sql = "SELECT type_demande, COUNT(*) as nb FROM posts GROUP BY type_demande";
$result = mysqli_query($conn, $sql);
$volontariat_count = 0;
$donation_count = 0;
while($row = mysqli_fetch_assoc($result)) {
    if($row['type_demande'] == 'volontariat') $volontariat_count = $row['nb'];
    else $donation_count = $row['nb'];
}
$total_posts_count = $volontariat_count + $donation_count;
$volontariat_pct = $total_posts_count > 0 ? round(($volontariat_count / $total_posts_count) * 100) : 0;
$donation_pct    = $total_posts_count > 0 ? round(($donation_count    / $total_posts_count) * 100) : 0;

// ========== TOUTES LES ORGANISATIONS (pagination côté client) ==========
$sql = "SELECT * FROM organisations ORDER BY date_inscription DESC";
$result_org = mysqli_query($conn, $sql);
$toutes_org = [];
while($row = mysqli_fetch_assoc($result_org)) $toutes_org[] = $row;

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ConnectAid - Tableau de bord Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
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
  .sidebar-bottom .nav-item svg { stroke: white; color: white; }

  .main { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

  .topbar { background: transparent; padding: 16px 32px 16px calc(32px + 15px); display: flex; align-items: center; gap: 12px; position: sticky; top: 0; z-index: 5; }

  .greeting { flex: 1; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 10px 18px; box-shadow: var(--shadow); }
  .greeting h2 { font-size: 15px; font-weight: 700; margin-bottom: 4px; }
  .greeting p  { font-size: 11px; color: var(--text-secondary); font-weight: 400; margin-top: 1px; }

  .date-picker { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 10px 16px; font-size: 13px; font-weight: 500; cursor: pointer; color: var(--text-primary); box-shadow: var(--shadow); }

  .search-bar { display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 10px 16px; width: 320px; font-size: 13px; color: var(--text-secondary); cursor: text; box-shadow: var(--shadow); }
  .search-bar input { border: none; outline: none; background: transparent; width: 100%; font-family: inherit; font-size: 13px; }

  .profile-dropdown { position: relative; }
  .user-info { display: flex; align-items: center; gap: 10px; cursor: pointer; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 6px 14px 6px 6px; box-shadow: var(--shadow); }
  .avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--accent-teal); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #fff; overflow: hidden; }
  .user-name { font-weight: 600; font-size: 13px; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .dropdown-icon { font-size: 12px; transition: transform 0.2s; display: inline-flex; align-items: center; color: #8b8fa8; }
  .dropdown-menu { position: absolute; top: 100%; right: 0; margin-top: 8px; background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); min-width: 200px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s; z-index: 100; }
  .profile-dropdown.active .dropdown-menu { opacity: 1; visibility: visible; transform: translateY(0); }
  .profile-dropdown.active .dropdown-icon { transform: rotate(180deg); }
  .dropdown-menu a { display: flex; align-items: center; gap: 10px; padding: 10px 16px; color: var(--text-secondary); text-decoration: none; font-size: 13px; transition: background 0.2s; }
  .dropdown-menu a:hover { background: var(--bg); color: var(--text-primary); }
  .dropdown-menu hr { margin: 4px 0; border: none; border-top: 1px solid var(--border); }

  .content { padding: 20px 32px 28px calc(32px + 15px); display: flex; flex-direction: column; gap: 24px; }

  .stat-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
  .stat-card { border-radius: var(--radius); padding: 22px 24px; color: #fff; min-height: 120px; display: flex; flex-direction: column; justify-content: space-between; }
  .stat-card.teal   { background: linear-gradient(135deg, #1CB8B2 0%, #138F8A 100%); }
  .stat-card.blue   { background: linear-gradient(135deg, #9FE7F5 0%, #7BC8D8 100%); }
  .stat-card.gold   { background: linear-gradient(135deg, #F7AD19 0%, #E0960A 100%); }
  .stat-card.orange { background: linear-gradient(135deg, #F47B20 0%, #E06A10 100%); }
  .stat-label { font-size: 12px; font-weight: 500; opacity: 0.85; }
  .stat-icon { width: 36px; height: 36px; background: rgba(255,255,255,0.25); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
  .stat-top { display: flex; align-items: flex-start; justify-content: space-between; }
  .stat-value { font-size: 26px; font-weight: 700; line-height: 1; }

  .mid-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }

  .card { background: var(--card-bg); border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); width: 100%; }
  .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
  .card-title { font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 6px; }

  .dropdown-btn { background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 6px 12px; font-size: 12px; font-weight: 500; color: var(--text-primary); cursor: pointer; display: flex; align-items: center; gap: 5px; font-family: inherit; }

  .chart-wrap { height: 320px; position: relative; }

  .pie-wrap { display: flex; align-items: center; justify-content: center; gap: 20px; flex-wrap: wrap; }
  .pie-chart { width: 140px; height: 140px; position: relative; flex-shrink: 0; }
  .legend-pie { display: flex; flex-direction: column; gap: 10px; flex-shrink: 0; }
  .legend-item { display: flex; align-items: center; justify-content: space-between; gap: 16px; font-size: 13px; }
  .legend-dot-label { display: flex; align-items: center; gap: 8px; }
  .legend-dot { width: 10px; height: 10px; border-radius: 50%; }
  .legend-name { color: var(--text-secondary); font-weight: 500; }
  .legend-pct { font-weight: 700; }

  .org-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
  .org-table th { text-align: left; font-size: 11px; font-weight: 600; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 0; border-bottom: 1px solid var(--border); }
  .org-table td { padding: 13px 0; border-bottom: 1px solid var(--border); vertical-align: middle; }
  .org-table tr:last-child td { border-bottom: none; }
  .org-name { display: flex; align-items: center; gap: 10px; }
  .org-avatar { width: 34px; height: 34px; border-radius: 10px; background: var(--bg); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
  .org-title { font-weight: 600; font-size: 13px; }

  .status-badge { display: inline-flex; align-items: center; padding: 5px 12px; border-radius: 30px; font-size: 12px; font-weight: 600; }
  .status-accepted { background: rgba(34, 197, 94, 0.12);  color: #22c55e; }
  .status-refused  { background: rgba(239, 68, 68, 0.12);  color: #ef4444; }
  .status-pending  { background: rgba(244, 123, 32, 0.12); color: #F47B20; }

  /* ===== PAGINATION ===== */
  .pagination-wrap { display: flex; justify-content: flex-end; margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--border); }
  .pagination-controls { display: flex; align-items: center; gap: 6px; }

  .page-btn {
    width: 32px; height: 32px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: #fff;
    color: var(--text-secondary);
    font-size: 13px; font-weight: 600;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.18s;
    font-family: inherit;
  }
  .page-btn:hover:not(:disabled):not(.active) { border-color: #F47B20; color: #F47B20; background: #fff4ee; }
  .page-btn.active { background: #F47B20; color: #fff; border-color: #F47B20; }
  .page-btn:disabled { opacity: 0.35; cursor: not-allowed; }
  .page-dots { padding: 0 4px; color: var(--text-light); font-size: 13px; }

  @media (max-width: 768px) {
    .stat-cards { grid-template-columns: repeat(2, 1fr); }
    .mid-row { grid-template-columns: 1fr; }
    .topbar { flex-wrap: wrap; }
    .search-bar { width: 100%; order: 3; margin-top: 10px; }
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
    <div class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" data-page="dashboard">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>
      Tableau de bord
    </div>
    <div class="nav-item <?php echo ($current_page == 'demandes.php') ? 'active' : ''; ?>" data-page="demandes">
      <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-building-check" viewBox="0 0 16 16"><path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m1.679-4.493-1.335 2.226a.75.75 0 0 1-1.174.144l-.774-.773a.5.5 0 0 1 .708-.708l.547.548 1.17-1.951a.5.5 0 1 1 .858.514"/><path d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6.5a.5.5 0 0 1-1 0V1H3v14h3v-2.5a.5.5 0 0 1 .5-.5H8v4H3a1 1 0 0 1-1-1z"/><path d="M4.5 2a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm-6 3a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm-6 3a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/></svg></span>
      Demandes
    </div>
    <div class="nav-item <?php echo ($current_page == 'organisations.php') ? 'active' : ''; ?>" data-page="organisations-nav">
      <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-building" viewBox="0 0 16 16"><path d="M4 2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zM4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM7.5 5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM4.5 8a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/><path d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zm11 0H3v14h3v-2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V15h3z"/></svg></span>
      Organisations
    </div>
    <div class="nav-item <?php echo ($current_page == 'contributeurs.php') ? 'active' : ''; ?>" data-page="contributeurs">
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
      <h2>Bonjour, administrateur</h2>
      <p>Bienvenue sur votre tableau de bord</p>
    </div>
    <div class="date-picker">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <?php setlocale(LC_TIME, 'fr_FR.utf8', 'fr_FR', 'fr'); echo strftime('%d %B %Y'); ?>
    </div>
    <div class="search-bar">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="searchInput" placeholder="Rechercher une organisation...">
    </div>
    <div class="profile-dropdown" id="profileDropdown">
      <div class="user-info" onclick="toggleDropdown()">
        <div class="avatar"><?php echo strtoupper(substr($_SESSION['user_nom'], 0, 1)); ?></div>
        <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_nom']); ?></span>
        <span class="dropdown-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708"/></svg>
        </span>
      </div>
      <div class="dropdown-menu">
        <a href="mon_profil.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Mon profil</a>
        <hr>
        <a href="../logout.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Déconnexion</a>
      </div>
    </div>
  </header>

  <div class="content" id="mainContent">
    <div class="stat-cards">
      <div class="stat-card teal">
        <div class="stat-top"><div><div class="stat-label">Organisations</div></div><div class="stat-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4 2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zM4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM7.5 5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM4.5 8a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/><path d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zm11 0H3v14h3v-2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V15h3z"/></svg></div></div>
        <div><div class="stat-value"><?php echo $total_org; ?></div></div>
      </div>
      <div class="stat-card blue">
        <div class="stat-top"><div><div class="stat-label">Contributeurs</div></div><div class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div></div>
        <div><div class="stat-value"><?php echo $total_contrib; ?></div></div>
      </div>
      <div class="stat-card gold">
        <div class="stat-top"><div><div class="stat-label">Publications</div></div><div class="stat-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M7 4.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0m-.861 1.542 1.33.886 1.854-1.855a.25.25 0 0 1 .289-.047l1.888.974V7.5a.5.5 0 0 1-.5.5H5a.5.5 0 0 1-.5-.5V7s1.54-1.274 1.639-1.208M5 9a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm0 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1z"/><path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1"/></svg></div></div>
        <div><div class="stat-value"><?php echo $total_posts; ?></div></div>
      </div>
      <div class="stat-card orange">
        <div class="stat-top"><div><div class="stat-label">Demandes en attente</div></div><div class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
        <div><div class="stat-value"><?php echo $demandes_attente; ?></div></div>
      </div>
    </div>

    <div class="mid-row">
      <div class="card">
        <div class="card-header">
          <div class="card-title">Évolution des inscriptions</div>
          <button class="dropdown-btn" id="periodBtn">Ce mois ▾</button>
        </div>
        <div class="chart-wrap"><canvas id="registrationsChart"></canvas></div>
      </div>
      <div class="card">
        <div class="card-header">
          <div class="card-title">Répartition des publications</div>
        </div>
        <div class="pie-wrap">
          <div class="pie-chart"><canvas id="pieChart"></canvas></div>
          <div class="legend-pie">
            <div class="legend-item"><div class="legend-dot-label"><div class="legend-dot" style="background:#F47B20"></div><span class="legend-name">Donation</span></div><span class="legend-pct"><?php echo $donation_pct; ?>%</span></div>
            <div class="legend-item"><div class="legend-dot-label"><div class="legend-dot" style="background:#4ecdc4"></div><span class="legend-name">Volontariat</span></div><span class="legend-pct"><?php echo $volontariat_pct; ?>%</span></div>
          </div>
        </div>
      </div>
    </div>

    <div class="bot-row">
      <div class="card">
        <!-- Titre seul, sans "Voir plus" -->
        <div class="card-header">
          <div class="card-title">Organisations inscrites</div>
        </div>
        <table class="org-table">
          <thead><tr><th>Organisation</th><th>Date d'inscription</th><th>Statut</th></tr></thead>
          <tbody id="orgTableBody"></tbody>
        </table>
        <!-- Pagination -->
        <div class="pagination-wrap">
          <div class="pagination-controls" id="paginationControls"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const allOrganisations = <?php echo json_encode($toutes_org); ?>;
const ITEMS_PER_PAGE = 8;
let currentPage = 1;
let filteredOrganisations = [...allOrganisations];

function formatDateFr(dateStr) {
  return new Date(dateStr).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}
function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/[&<>]/g, m => m === '&' ? '&amp;' : m === '<' ? '&lt;' : '&gt;');
}

function renderOrganisations(data, page) {
  const tbody = document.getElementById('orgTableBody');
  if (!tbody) return;
  const start = (page - 1) * ITEMS_PER_PAGE;
  const slice = data.slice(start, start + ITEMS_PER_PAGE);
  tbody.innerHTML = '';
  slice.forEach(org => {
    const row = document.createElement('tr');
    let statusClass = '', statusText = '';
    if      (org.statut === 'valide')     { statusClass = 'status-accepted'; statusText = 'Accepté'; }
    else if (org.statut === 'en_attente') { statusClass = 'status-pending';  statusText = 'En attente'; }
    else                                  { statusClass = 'status-refused';  statusText = 'Refusé'; }
    row.innerHTML = `
      <td><div class="org-name"><div class="org-avatar">🏢</div><span class="org-title">${escapeHtml(org.nom_organisation)}</span></div></td>
      <td>${formatDateFr(org.date_inscription)}</td>
      <td><span class="status-badge ${statusClass}">${statusText}</span></td>`;
    tbody.appendChild(row);
  });
  renderPagination(data.length, page);
}

function renderPagination(total, page) {
  const totalPages = Math.ceil(total / ITEMS_PER_PAGE);
  const ctrl = document.getElementById('paginationControls');
  ctrl.innerHTML = '';
  if (totalPages <= 1) return;

  // Bouton précédent
  const prev = document.createElement('button');
  prev.className = 'page-btn'; prev.textContent = '‹'; prev.disabled = page === 1;
  prev.onclick = () => goToPage(page - 1);
  ctrl.appendChild(prev);

  // Numéros
  const pages = getPageNumbers(page, totalPages);
  pages.forEach(p => {
    if (p === '...') {
      const s = document.createElement('span');
      s.className = 'page-dots'; s.textContent = '…';
      ctrl.appendChild(s);
    } else {
      const b = document.createElement('button');
      b.className = 'page-btn' + (p === page ? ' active' : '');
      b.textContent = p;
      b.onclick = () => goToPage(p);
      ctrl.appendChild(b);
    }
  });

  // Bouton suivant
  const next = document.createElement('button');
  next.className = 'page-btn'; next.textContent = '›'; next.disabled = page === totalPages;
  next.onclick = () => goToPage(page + 1);
  ctrl.appendChild(next);
}

function getPageNumbers(current, total) {
  if (total <= 7) return Array.from({length: total}, (_, i) => i + 1);
  if (current <= 3) return [1,2,3,4,5,'...',total];
  if (current >= total - 2) return [1,'...',total-4,total-3,total-2,total-1,total];
  return [1,'...',current-1,current,current+1,'...',total];
}

function goToPage(page) {
  currentPage = page;
  renderOrganisations(filteredOrganisations, currentPage);
}

renderOrganisations(filteredOrganisations, currentPage);

// Recherche
document.getElementById('searchInput').addEventListener('input', function(e) {
  const term = e.target.value.toLowerCase();
  filteredOrganisations = allOrganisations.filter(org => org.nom_organisation.toLowerCase().includes(term));
  currentPage = 1;
  renderOrganisations(filteredOrganisations, currentPage);
});

// Dropdown profil
function toggleDropdown() { document.getElementById('profileDropdown').classList.toggle('active'); }
document.addEventListener('click', e => {
  const d = document.getElementById('profileDropdown');
  if (d && !d.contains(e.target)) d.classList.remove('active');
});

// Graphiques
const joursLabels      = <?php echo json_encode($jours_labels); ?>;
const contributeursData = <?php echo json_encode($contrib_data); ?>;
const organisationsData = <?php echo json_encode($org_data); ?>;
const yMax              = <?php echo $y_max; ?>;

new Chart(document.getElementById('registrationsChart').getContext('2d'), {
  type: 'line',
  data: {
    labels: joursLabels,
    datasets: [
      { label: 'Organisations', data: organisationsData, fill: false, borderColor: '#F47B20', backgroundColor: 'transparent', borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 6, pointBackgroundColor: '#F47B20', pointBorderColor: '#fff', pointBorderWidth: 2, tension: 0.2 },
      { label: 'Contributeurs', data: contributeursData, fill: false, borderColor: '#4ecdc4', backgroundColor: 'transparent', borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 6, pointBackgroundColor: '#4ecdc4', pointBorderColor: '#fff', pointBorderWidth: 2, tension: 0.2 }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom', labels: { font: { size: 11, weight: '500' }, boxWidth: 12, usePointStyle: true, pointStyle: 'circle', padding: 15 } },
      tooltip: { backgroundColor: '#1a1d2e', titleFont: { size: 12, weight: '600' }, bodyFont: { size: 12, weight: '500' }, padding: 12, cornerRadius: 8, callbacks: { label: ctx => `${ctx.dataset.label}: ${ctx.raw} inscription${ctx.raw > 1 ? 's' : ''}` } }
    },
    scales: {
      x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 11 }, color: '#8b8fa8', maxRotation: 45, autoSkip: true, maxTicksLimit: 8 } },
      y: { min: 0, max: yMax, grid: { color: '#f0f1f7', drawBorder: false }, border: { display: false }, ticks: { font: { size: 11 }, color: '#8b8fa8', stepSize: 5 }, title: { display: true, text: "Nombre d'inscriptions", font: { size: 11, weight: '500' }, color: '#8b8fa8' } }
    },
    elements: { point: { hoverRadius: 8 } },
    interaction: { mode: 'index', intersect: false }
  }
});

new Chart(document.getElementById('pieChart').getContext('2d'), {
  type: 'pie',
  data: { datasets: [{ data: [<?php echo $donation_pct; ?>, <?php echo $volontariat_pct; ?>], backgroundColor: ['#F47B20', '#4ecdc4'], borderWidth: 0, hoverOffset: 6 }] },
  options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => `${ctx.label}: ${ctx.raw}%` } } } }
});

// Navigation sidebar
document.querySelectorAll('.nav-item[data-page]').forEach(item => {
  item.addEventListener('click', () => {
    const page = item.getAttribute('data-page');
    if      (page === 'dashboard')         window.location.href = 'dashboard.php';
    else if (page === 'demandes')          window.location.href = 'demandes.php';
    else if (page === 'organisations-nav') window.location.href = 'organisations.php';
    else if (page === 'contributeurs')     window.location.href = 'contributeurs.php';
    else if (page === 'deconnexion')       window.location.href = '../logout.php';
  });
});
</script>
</body>
</html>