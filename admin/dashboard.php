<?php
session_start();
require_once '../connexion_bd.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../connexion.php');
    exit();
}

// Récupérer l'email de l'admin depuis la session ou la base
if (!isset($_SESSION['user_email'])) {
    $admin_id = $_SESSION['user_id'];
    $sql_email = "SELECT email FROM admins WHERE id = $admin_id";
    $result_email = mysqli_query($conn, $sql_email);
    if ($row = mysqli_fetch_assoc($result_email)) {
        $_SESSION['user_email'] = $row['email'];
    }
}

// ========== STATISTIQUES ==========
$sql = "SELECT COUNT(*) as nb FROM organisations WHERE statut = 'valide'";
$result = mysqli_query($conn, $sql);
$total_org_validees = mysqli_fetch_assoc($result)['nb'];  // ← MODIFIÉ : organisations validées uniquement

$sql = "SELECT COUNT(*) as nb FROM contributeurs";
$result = mysqli_query($conn, $sql);
$total_contrib = mysqli_fetch_assoc($result)['nb'];

$sql = "SELECT COUNT(*) as nb FROM posts";
$result = mysqli_query($conn, $sql);
$total_posts = mysqli_fetch_assoc($result)['nb'];

$sql = "SELECT COUNT(*) as nb FROM organisations WHERE statut = 'en_attente'";
$result = mysqli_query($conn, $sql);
$demandes_attente = mysqli_fetch_assoc($result)['nb'];

// ========== ÉVOLUTION DES INSCRIPTIONS - DONNÉES PAR DÉFAUT (CE MOIS) ==========
$mois_actuel = date('m');
$annee_actuelle = date('Y');
$mois_dernier = date('m', strtotime('-1 month'));
$annee_dernier = date('Y', strtotime('-1 month'));

// Données pour ce mois
$nombre_jours = date('t');
$jours_labels = [];
$contrib_data = [];
$org_data = [];

for ($i = 1; $i <= $nombre_jours; $i++) {
    $jours_labels[] = "Jour " . $i;
    $date = date('Y-m-' . str_pad($i, 2, '0', STR_PAD_LEFT));
    $sql = "SELECT COUNT(*) as nb FROM contributeurs WHERE DATE(date_inscription) = '$date' AND MONTH(date_inscription) = '$mois_actuel' AND YEAR(date_inscription) = '$annee_actuelle'";
    $result = mysqli_query($conn, $sql);
    $contrib_data[] = mysqli_fetch_assoc($result)['nb'];
    $sql = "SELECT COUNT(*) as nb FROM organisations WHERE DATE(date_inscription) = '$date' AND MONTH(date_inscription) = '$mois_actuel' AND YEAR(date_inscription) = '$annee_actuelle'";
    $result = mysqli_query($conn, $sql);
    $org_data[] = mysqli_fetch_assoc($result)['nb'];
}

// Données pour le mois dernier
$jours_labels_dernier = [];
$contrib_data_dernier = [];
$org_data_dernier = [];
$nombre_jours_dernier = date('t', strtotime('last month'));

for ($i = 1; $i <= $nombre_jours_dernier; $i++) {
    $jours_labels_dernier[] = "Jour " . $i;
    $date = date('Y-m-' . str_pad($i, 2, '0', STR_PAD_LEFT), strtotime('last month'));
    $sql = "SELECT COUNT(*) as nb FROM contributeurs WHERE DATE(date_inscription) = '$date' AND MONTH(date_inscription) = '$mois_dernier' AND YEAR(date_inscription) = '$annee_dernier'";
    $result = mysqli_query($conn, $sql);
    $contrib_data_dernier[] = mysqli_fetch_assoc($result)['nb'];
    $sql = "SELECT COUNT(*) as nb FROM organisations WHERE DATE(date_inscription) = '$date' AND MONTH(date_inscription) = '$mois_dernier' AND YEAR(date_inscription) = '$annee_dernier'";
    $result = mysqli_query($conn, $sql);
    $org_data_dernier[] = mysqli_fetch_assoc($result)['nb'];
}

// Axe Y fixé de 0 à 35
$y_max = 35;

// ========== RÉPARTITION DES POSTS ==========
function getPostsStats($conn, $mois = null, $annee = null) {
    if ($mois && $annee) {
        $sql = "SELECT type_demande, COUNT(*) as nb FROM posts WHERE MONTH(date_creation) = '$mois' AND YEAR(date_creation) = '$annee' GROUP BY type_demande";
    } else {
        $sql = "SELECT type_demande, COUNT(*) as nb FROM posts GROUP BY type_demande";
    }
    $result = mysqli_query($conn, $sql);
    $volontariat_count = 0;
    $donation_count = 0;
    while($row = mysqli_fetch_assoc($result)) {
        if($row['type_demande'] == 'volontariat') $volontariat_count = $row['nb'];
        else if($row['type_demande'] == 'donation') $donation_count = $row['nb'];
    }
    $total = $volontariat_count + $donation_count;
    return [
        'volontariat' => $volontariat_count,
        'donation' => $donation_count,
        'volontariat_pct' => $total > 0 ? round(($volontariat_count / $total) * 100) : 0,
        'donation_pct' => $total > 0 ? round(($donation_count / $total) * 100) : 0
    ];
}

$posts_stats = getPostsStats($conn);
$volontariat_pct = $posts_stats['volontariat_pct'];
$donation_pct = $posts_stats['donation_pct'];

// Statistiques pour ce mois
$posts_stats_mois = getPostsStats($conn, $mois_actuel, $annee_actuelle);
// Statistiques pour le mois dernier
$posts_stats_dernier = getPostsStats($conn, $mois_dernier, $annee_dernier);

// ========== TOUTES LES ORGANISATIONS ==========
$sql = "SELECT id, nom_organisation, date_inscription, statut, logo FROM organisations ORDER BY date_inscription DESC";
$result_org = mysqli_query($conn, $sql);
$toutes_org = [];
while($row = mysqli_fetch_assoc($result_org)) {
    if($row['logo'] && strpos($row['logo'], 'uploads/') === 0) {
        $row['logo'] = substr($row['logo'], 8);
    }
    $toutes_org[] = $row;
}

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

  .topbar {
    background: transparent;
    padding: 16px 32px 16px calc(32px + 15px);
    display: flex;
    align-items: center;
    gap: 12px;
    position: sticky;
    top: 0;
    z-index: 5;
    flex-wrap: wrap;
  }

  .greeting {
    flex: 1;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 10px 18px;
    box-shadow: var(--shadow);
    min-width: 200px;
  }
  .greeting h2 { font-size: 15px; font-weight: 700; margin-bottom: 4px; }
  .greeting p  { font-size: 11px; color: var(--text-secondary); font-weight: 400; margin-top: 1px; }

  .search-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 10px 16px;
    width: 280px;
    font-size: 13px;
    color: var(--text-secondary);
    cursor: text;
    box-shadow: var(--shadow);
  }
  .search-bar input { border: none; outline: none; background: transparent; width: 100%; font-family: inherit; font-size: 13px; }

  .date-picker {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 10px 16px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    color: var(--text-primary);
    box-shadow: var(--shadow);
    white-space: nowrap;
  }

  .profile-dropdown { position: relative; }
  .user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 6px 14px 6px 6px;
    box-shadow: var(--shadow);
  }
  .avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--accent-teal);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    color: #fff;
    overflow: hidden;
    flex-shrink: 0;
  }
  .user-details { display: flex; flex-direction: column; gap: 1px; }
  .user-name  { font-weight: 600; font-size: 13px; white-space: nowrap; color: var(--text-primary); }

  .dropdown-icon { font-size: 12px; transition: transform 0.2s; display: inline-flex; align-items: center; color: #8b8fa8; flex-shrink: 0; }
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
  .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
  .card-title { font-size: 15px; font-weight: 700; display: flex; align-items: center; gap: 6px; }

  .dropdown-btn {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 500;
    color: var(--text-primary);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
    font-family: inherit;
    position: relative;
  }
  
  .dropdown-menu-custom {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 5px;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    min-width: 140px;
    z-index: 100;
    display: none;
  }
  .dropdown-menu-custom.show {
    display: block;
  }
  .dropdown-menu-custom div {
    padding: 8px 12px;
    cursor: pointer;
    font-size: 12px;
    color: var(--text-secondary);
    transition: all 0.2s;
  }
  .dropdown-menu-custom div:hover {
    background: var(--bg);
    color: var(--text-primary);
  }

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

  .org-avatar {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--bg);
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    font-weight: 700;
    color: var(--accent-teal);
    flex-shrink: 0;
    overflow: hidden;
  }
  .org-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
  .org-title { font-weight: 600; font-size: 13px; }

  .status-badge { 
    display: inline-flex; 
    align-items: center; 
    padding: 5px 12px; 
    border-radius: 30px; 
    font-size: 12px; 
    font-weight: 600; 
  }
  .status-accepted { 
    background: rgba(20, 184, 166, 0.12);  
    color: #14B8A6; 
  }
  .status-refused  { 
    background: rgba(220, 38, 38, 0.12);  
    color: #DC2626; 
  }
  .status-pending  { 
    background: rgba(245, 223, 76, 0.15); 
    color: #B8860B; 
  }

  .pagination-wrap { display: flex; justify-content: flex-end; margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--border); }
  .pagination-controls { display: flex; align-items: center; gap: 6px; }
  .page-btn { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border); background: #fff; color: var(--text-secondary); font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.18s; font-family: inherit; }
  .page-btn:hover:not(:disabled):not(.active) { border-color: #F47B20; color: #F47B20; background: #fff4ee; }
  .page-btn.active { background: #F47B20; color: #fff; border-color: #F47B20; }
  .page-btn:disabled { opacity: 0.35; cursor: not-allowed; }
  .page-dots { padding: 0 4px; color: var(--text-light); font-size: 13px; }

  @media (max-width: 768px) {
    .stat-cards { grid-template-columns: repeat(2, 1fr); }
    .mid-row { grid-template-columns: 1fr; }
    .search-bar { width: 100%; }
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
    <div class="nav-item" data-page="demandes">
      <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-building-check" viewBox="0 0 16 16"><path d="M12.5 16a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7m1.679-4.493-1.335 2.226a.75.75 0 0 1-1.174.144l-.774-.773a.5.5 0 0 1 .708-.708l.547.548 1.17-1.951a.5.5 0 1 1 .858.514"/><path d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6.5a.5.5 0 0 1-1 0V1H3v14h3v-2.5a.5.5 0 0 1 .5-.5H8v4H3a1 1 0 0 1-1-1z"/><path d="M4.5 2a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm-6 3a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm-6 3a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm3 0a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/></svg></span>
      Demandes
    </div>
    <div class="nav-item" data-page="organisations-nav">
      <span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-building" viewBox="0 0 16 16"><path d="M4 2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zM4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM7.5 5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM4.5 8a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/><path d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zm11 0H3v14h3v-2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V15h3z"/></svg></span>
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
      <h2>Bonjour, administrateur</h2>
      <p>Bienvenue sur votre espace</p>
    </div>

    <div class="search-bar">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="searchInput" placeholder="Rechercher une organisation...">
    </div>

    <div class="date-picker">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <?php setlocale(LC_TIME, 'fr_FR.utf8', 'fr_FR', 'fr'); echo strftime('%d %B %Y'); ?>
    </div>

    <div class="profile-dropdown" id="profileDropdown">
      <div class="user-info" onclick="toggleDropdown()">
        <div class="avatar"><?php echo strtoupper(substr($_SESSION['user_nom'], 0, 1)); ?></div>
        <div class="user-details">
          <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'admin@connectaid.com'); ?></span>
        </div>
        <span class="dropdown-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708"/></svg>
        </span>
      </div>
      <div class="dropdown-menu">
        <a href="mon_profil.php?from=dashboard">
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

  <div class="content" id="mainContent">
    <div class="stat-cards">
      <div class="stat-card teal">
        <div class="stat-top"><div><div class="stat-label">Organisations</div></div><div class="stat-icon"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4 2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zM4 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM7.5 5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zM4.5 8a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm2.5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5z"/><path d="M2 1a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1zm11 0H3v14h3v-2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V15h3z"/></svg></div></div>
        <div><div class="stat-value"><?php echo $total_org_validees; ?></div></div>
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
          <div class="dropdown-btn" id="periodBtn">
            Ce mois <span>▾</span>
            <div class="dropdown-menu-custom" id="periodMenu">
              <div data-period="current">Ce mois</div>
              <div data-period="last">Mois dernier</div>
            </div>
          </div>
        </div>
        <div class="chart-wrap"><canvas id="registrationsChart"></canvas></div>
      </div>
      <div class="card">
        <div class="card-header">
          <div class="card-title">Répartition des publications</div>
          <div class="dropdown-btn" id="postsPeriodBtn">
            Toutes <span>▾</span>
            <div class="dropdown-menu-custom" id="postsPeriodMenu">
              <div data-period="all">Toutes</div>
              <div data-period="current">Ce mois</div>
              <div data-period="last">Mois dernier</div>
            </div>
          </div>
        </div>
        <div class="pie-wrap">
          <div class="pie-chart"><canvas id="pieChart"></canvas></div>
          <div class="legend-pie">
            <div class="legend-item"><div class="legend-dot-label"><div class="legend-dot" style="background:#F47B20"></div><span class="legend-name">Donation</span></div><span class="legend-pct" id="donationPct"><?php echo $donation_pct; ?>%</span></div>
            <div class="legend-item"><div class="legend-dot-label"><div class="legend-dot" style="background:#4ecdc4"></div><span class="legend-name">Volontariat</span></div><span class="legend-pct" id="volontariatPct"><?php echo $volontariat_pct; ?>%</span></div>
          </div>
        </div>
      </div>
    </div>

    <div class="bot-row">
      <div class="card">
        <div class="card-header">
          <div class="card-title">Organisations inscrites</div>
        </div>
        <table class="org-table">
          <thead>
            <tr><th>Organisation</th><th>Date d'inscription</th><th>Statut</th></tr>
          </thead>
          <tbody id="orgTableBody"></tbody>
        </table>
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

const joursLabels = <?php echo json_encode($jours_labels); ?>;
const contributeursData = <?php echo json_encode($contrib_data); ?>;
const organisationsData = <?php echo json_encode($org_data); ?>;

const joursLabelsDernier = <?php echo json_encode($jours_labels_dernier); ?>;
const contributeursDataDernier = <?php echo json_encode($contrib_data_dernier); ?>;
const organisationsDataDernier = <?php echo json_encode($org_data_dernier); ?>;

const postsStatsAll = { donation: <?php echo $donation_pct; ?>, volontariat: <?php echo $volontariat_pct; ?> };
const postsStatsCurrent = { donation: <?php echo $posts_stats_mois['donation_pct']; ?>, volontariat: <?php echo $posts_stats_mois['volontariat_pct']; ?> };
const postsStatsLast = { donation: <?php echo $posts_stats_dernier['donation_pct']; ?>, volontariat: <?php echo $posts_stats_dernier['volontariat_pct']; ?> };

let registrationsChart = null;
let pieChart = null;

function formatDateFr(dateStr) {
  const date = new Date(dateStr);
  return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}

function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/[&<>'"]/g, function(m) {
    if (m === '&') return '&amp;';
    if (m === '<') return '&lt;';
    if (m === '>') return '&gt;';
    if (m === "'") return '&#39;';
    if (m === '"') return '&quot;';
    return m;
  });
}

function buildAvatar(org) {
  const logo = org.logo;
  if (logo && logo.trim() !== '') {
    const src = escapeHtml('../uploads/' + logo);
    return `<div class="org-avatar"><img src="${src}" alt="Logo" onerror="this.parentElement.innerHTML='${escapeHtml(org.nom_organisation ? org.nom_organisation.charAt(0).toUpperCase() : '?')}'"></div>`;
  }
  const initiale = org.nom_organisation ? org.nom_organisation.charAt(0).toUpperCase() : '?';
  return `<div class="org-avatar" style="background:#E1F7F6; color:var(--accent-teal); font-weight:700;">${initiale}</div>`;
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
    if (org.statut === 'valide') { statusClass = 'status-accepted'; statusText = 'Accepté'; }
    else if (org.statut === 'en_attente') { statusClass = 'status-pending'; statusText = 'En attente'; }
    else { statusClass = 'status-refused'; statusText = 'Refusé'; }
    row.innerHTML = `
      <td><div class="org-name">${buildAvatar(org)}<span class="org-title">${escapeHtml(org.nom_organisation)}</span></div></td>
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
  const prev = document.createElement('button');
  prev.className = 'page-btn'; prev.textContent = '‹'; prev.disabled = page === 1;
  prev.onclick = () => goToPage(page - 1);
  ctrl.appendChild(prev);
  const maxVisible = 5;
  let startPage = Math.max(1, page - Math.floor(maxVisible / 2));
  let endPage = Math.min(totalPages, startPage + maxVisible - 1);
  if (endPage - startPage + 1 < maxVisible) {
    startPage = Math.max(1, endPage - maxVisible + 1);
  }
  if (startPage > 1) {
    const firstBtn = document.createElement('button');
    firstBtn.className = 'page-btn'; firstBtn.textContent = '1'; firstBtn.onclick = () => goToPage(1);
    ctrl.appendChild(firstBtn);
    if (startPage > 2) {
      const dots = document.createElement('span'); dots.className = 'page-dots'; dots.textContent = '…'; ctrl.appendChild(dots);
    }
  }
  for (let i = startPage; i <= endPage; i++) {
    const btn = document.createElement('button');
    btn.className = 'page-btn' + (i === page ? ' active' : '');
    btn.textContent = i; btn.onclick = () => goToPage(i);
    ctrl.appendChild(btn);
  }
  if (endPage < totalPages) {
    if (endPage < totalPages - 1) {
      const dots = document.createElement('span'); dots.className = 'page-dots'; dots.textContent = '…'; ctrl.appendChild(dots);
    }
    const lastBtn = document.createElement('button');
    lastBtn.className = 'page-btn'; lastBtn.textContent = totalPages; lastBtn.onclick = () => goToPage(totalPages);
    ctrl.appendChild(lastBtn);
  }
  const next = document.createElement('button');
  next.className = 'page-btn'; next.textContent = '›'; next.disabled = page === totalPages;
  next.onclick = () => goToPage(page + 1);
  ctrl.appendChild(next);
}

function goToPage(page) { currentPage = page; renderOrganisations(filteredOrganisations, currentPage); }

renderOrganisations(filteredOrganisations, currentPage);

document.getElementById('searchInput').addEventListener('input', function(e) {
  const term = e.target.value.toLowerCase();
  filteredOrganisations = allOrganisations.filter(org => org.nom_organisation.toLowerCase().includes(term));
  currentPage = 1;
  renderOrganisations(filteredOrganisations, currentPage);
});

function updateRegistrationsChart(period) {
  let labels, contribData, orgData;
  if (period === 'current') {
    labels = joursLabels;
    contribData = contributeursData;
    orgData = organisationsData;
  } else {
    labels = joursLabelsDernier;
    contribData = contributeursDataDernier;
    orgData = organisationsDataDernier;
  }
  
  if (registrationsChart) {
    registrationsChart.destroy();
  }
  
  const ctx = document.getElementById('registrationsChart').getContext('2d');
  registrationsChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        { label: 'Organisations', data: orgData, fill: false, borderColor: '#F47B20', backgroundColor: 'transparent', borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 6, pointBackgroundColor: '#F47B20', pointBorderColor: '#fff', pointBorderWidth: 2, tension: 0.2 },
        { label: 'Contributeurs', data: contribData, fill: false, borderColor: '#4ecdc4', backgroundColor: 'transparent', borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 6, pointBackgroundColor: '#4ecdc4', pointBorderColor: '#fff', pointBorderWidth: 2, tension: 0.2 }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom', labels: { font: { size: 11, weight: '500' }, boxWidth: 12, usePointStyle: true, pointStyle: 'circle', padding: 15 } },
        tooltip: { backgroundColor: '#1a1d2e', titleFont: { size: 12, weight: '600' }, bodyFont: { size: 12, weight: '500' }, padding: 12, cornerRadius: 8, callbacks: { label: function(ctx) { return ctx.dataset.label + ': ' + ctx.raw + ' inscription' + (ctx.raw > 1 ? 's' : ''); } } }
      },
      scales: {
        x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 11 }, color: '#8b8fa8', maxRotation: 45, autoSkip: true, maxTicksLimit: 8 } },
        y: { 
          min: 0, 
          max: 35,
          grid: { color: '#f0f1f7', drawBorder: false }, 
          border: { display: false }, 
          ticks: { font: { size: 11 }, color: '#8b8fa8', stepSize: 5 }, 
          title: { display: true, text: "Nombre d'inscriptions", font: { size: 11, weight: '500' }, color: '#8b8fa8' } 
        }
      }
    }
  });
}

function updatePieChart(period) {
  let stats;
  if (period === 'all') {
    stats = postsStatsAll;
  } else if (period === 'current') {
    stats = postsStatsCurrent;
  } else {
    stats = postsStatsLast;
  }
  
  document.getElementById('donationPct').innerText = stats.donation + '%';
  document.getElementById('volontariatPct').innerText = stats.volontariat + '%';
  
  if (pieChart) {
    pieChart.destroy();
  }
  
  const ctx = document.getElementById('pieChart').getContext('2d');
  pieChart = new Chart(ctx, {
    type: 'pie',
    data: { datasets: [{ data: [stats.donation, stats.volontariat], backgroundColor: ['#F47B20', '#4ecdc4'], borderWidth: 0, hoverOffset: 6 }] },
    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(ctx) { return ctx.label + ': ' + ctx.raw + '%'; } } } } }
  });
}

updateRegistrationsChart('current');
updatePieChart('all');

function setupDropdown(btnId, menuId, onSelect) {
  const btn = document.getElementById(btnId);
  const menu = document.getElementById(menuId);
  if (!btn || !menu) return;
  
  btn.addEventListener('click', function(e) {
    e.stopPropagation();
    menu.classList.toggle('show');
  });
  
  menu.querySelectorAll('[data-period]').forEach(function(item) {
    item.addEventListener('click', function() {
      const period = this.getAttribute('data-period');
      const text = this.innerText;
      btn.childNodes[0].nodeValue = text + ' ';
      menu.classList.remove('show');
      onSelect(period);
    });
  });
  
  document.addEventListener('click', function() {
    menu.classList.remove('show');
  });
}

setupDropdown('periodBtn', 'periodMenu', updateRegistrationsChart);
setupDropdown('postsPeriodBtn', 'postsPeriodMenu', updatePieChart);

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