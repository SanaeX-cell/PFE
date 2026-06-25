<?php
session_start();
require_once '../connexion_bd.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'organisation') {
    header('Location: ../connexion.php');
    exit();
}

$org_id = $_SESSION['user_id'];

$sql = "SELECT * FROM organisations WHERE id = $org_id";
$result = mysqli_query($conn, $sql);
$org = mysqli_fetch_assoc($result);

$sql_benevoles = "SELECT COUNT(*) as nb FROM participations p JOIN posts po ON p.post_id = po.id WHERE po.organisation_id = $org_id AND p.statut = 'accepte'";
$result_b = mysqli_query($conn, $sql_benevoles);
$total_benevoles = mysqli_fetch_assoc($result_b)['nb'];

$sql_posts = "SELECT COUNT(*) as nb FROM posts WHERE organisation_id = $org_id";
$result_p = mysqli_query($conn, $sql_posts);
$total_posts = mysqli_fetch_assoc($result_p)['nb'];

$sql_notifs = "SELECT COUNT(*) as nb FROM notifications_organisation WHERE organisation_id = $org_id AND statut = 'non_lue'";
$result_n = mysqli_query($conn, $sql_notifs);
$total_notifs = mysqli_fetch_assoc($result_n)['nb'];

$period_days = 7;
$dates = [];
$likes_data = [];
$participations_data = [];

for ($i = $period_days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[] = date('d M', strtotime($date));
    $sql_likes = "SELECT COUNT(*) as nb FROM likes l JOIN posts p ON l.post_id = p.id WHERE p.organisation_id = $org_id AND DATE(l.date_like) = '$date'";
    $result_likes = mysqli_query($conn, $sql_likes);
    $likes_data[] = mysqli_fetch_assoc($result_likes)['nb'];
    $sql_participations = "SELECT COUNT(*) as nb FROM participations part JOIN posts p ON part.post_id = p.id WHERE p.organisation_id = $org_id AND DATE(part.date_participation) = '$date'";
    $result_participations = mysqli_query($conn, $sql_participations);
    $participations_data[] = mysqli_fetch_assoc($result_participations)['nb'];
}

$sql_derniers = "SELECT * FROM posts WHERE organisation_id = $org_id ORDER BY date_creation DESC LIMIT 4";
$result_derniers = mysqli_query($conn, $sql_derniers);
$derniers_posts = [];
while($row = mysqli_fetch_assoc($result_derniers)) {
    $derniers_posts[] = $row;
}

$current_page = basename($_SERVER['PHP_SELF']);

$words = explode(' ', trim($org['nom_organisation']));
$initials = '';
foreach (array_slice($words, 0, 2) as $word) {
    $initials .= mb_strtoupper(mb_substr($word, 0, 1));
}
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
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #f5f6fa;
    --sidebar-bg: #fff;
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
    --header-height: 80px;
  }
  body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text-primary); display: flex; min-height: 100vh; font-size: 14px; }

  /* ===== SIDEBAR ===== */
  .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); display: flex; flex-direction: column; padding: 28px 0; position: fixed; top: 0; left: 0; bottom: 0; border-right: 1px solid var(--border); z-index: 20; }
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

  /* ===== STICKY HEADER ===== */
  .sticky-header {
    position: fixed;
    top: 0;
    left: var(--sidebar-width);
    right: 0;
    height: var(--header-height);
    background: rgba(245,246,250,0.92);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    z-index: 15;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 32px;
    gap: 16px;
    box-shadow: 0 2px 16px rgba(0,0,0,0.05);
    transition: box-shadow 0.3s;
  }
  .sticky-header.scrolled {
    box-shadow: 0 4px 24px rgba(0,0,0,0.10);
  }
  .greeting-box { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 10px 24px; box-shadow: var(--shadow); flex: 0 1 auto; min-width: 200px; display: flex; align-items: center; gap: 12px; }
  .greeting-box h1 { font-size: 18px; font-weight: 700; color: var(--text-primary); margin: 0; }
  .greeting-box .separator { color: var(--text-light); font-weight: 300; }
  .greeting-box .sub { font-size: 13px; color: var(--text-secondary); font-weight: 500; }
  .header-right { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
  .notif-icon { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; box-shadow: var(--shadow); position: relative; text-decoration: none; color: var(--text-secondary); transition: all 0.2s; }
  .notif-icon:hover { border-color: var(--accent-orange-light); color: var(--accent-orange); }
  .notif-count-header { position: absolute; top: -4px; right: -4px; background: #EF4444; color: #fff; border-radius: 50%; width: 20px; height: 20px; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; }

  /* ===== ORG DROPDOWN ===== */
  .org-name-box { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 6px 14px 6px 6px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 10px; cursor: pointer; transition: all 0.2s; position: relative; min-width: 220px; max-width: 320px; }
  .org-name-box:hover { border-color: var(--accent-orange-light); }
  .org-avatar { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, #F47B20, #F7AD19); color: #fff; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; letter-spacing: 0.5px; overflow: hidden; }
  .org-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }
  .org-name-box .name { font-weight: 600; font-size: 14px; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
  .org-name-box .chevron { color: var(--text-secondary); transition: transform 0.2s; font-size: 12px; display: flex; align-items: center; flex-shrink: 0; }
  .org-name-box.active .chevron { transform: rotate(180deg); }
  .dropdown-menu-header { position: absolute; top: calc(100% + 8px); right: 0; background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); min-width: 190px; opacity: 0; visibility: hidden; transform: translateY(-8px); transition: all 0.2s; z-index: 100; }
  .org-name-box.active .dropdown-menu-header { opacity: 1; visibility: visible; transform: translateY(0); }
  .dropdown-org-info { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-bottom: 1px solid var(--border); }
  .dropdown-org-info .d-avatar { width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, #F47B20, #F7AD19); color: #fff; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; }
  .dropdown-org-info .d-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }
  .dropdown-org-info .d-name { font-size: 13px; font-weight: 600; color: var(--text-primary); }
  .dropdown-org-info .d-role { font-size: 11px; color: var(--text-secondary); }
  .dropdown-menu-header a { display: flex; align-items: center; gap: 10px; padding: 10px 16px; color: var(--text-secondary); text-decoration: none; font-size: 13px; transition: background 0.2s; }
  .dropdown-menu-header a:hover { background: var(--bg); color: var(--text-primary); }
  .dropdown-menu-header hr { margin: 4px 0; border: none; border-top: 1px solid var(--border); }

  /* ===== MAIN ===== */
  .main { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; min-height: 100vh; padding: 24px 32px 40px; padding-top: calc(var(--header-height) + 24px); }

  /* ===== WELCOME BOX ===== */
  .welcome-box { background: linear-gradient(135deg, #F47B20 0%, #F7AD19 50%, #FFD966 100%); border-radius: var(--radius); padding: 32px 40px; color: #fff; margin-bottom: 28px; min-height: 100px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .welcome-box h2 { font-size: 28px; font-weight: 700; margin: 0; white-space: nowrap; }
  .welcome-box p { font-size: 15px; opacity: 0.92; line-height: 1.6; margin: 0; display: inline; }

  /* ===== TITRES DE SECTION ===== */
  .section-title { display: flex; align-items: center; gap: 10px; font-size: 18px; font-weight: 700; color: var(--text-primary); }
  .section-title .icon { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: #FFF0E6; border-radius: 10px; color: var(--accent-orange); }
  .section-title-standalone { margin-bottom: 18px; }

  /* ===== RANGÉE TITRES GRILLE ===== */
  .stats-titles-row { display: grid; grid-template-columns: 1fr 2fr; gap: 24px; margin-bottom: 18px; }

  /* ===== STATS + GRAPHIQUE ===== */
  .stats-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 24px; margin-bottom: 28px; }
  .stats-cards { display: grid; grid-template-columns: 1fr; gap: 16px; }
  .stat-card { background: #fff; border-radius: var(--radius); padding: 16px 20px; box-shadow: var(--shadow); border: 1px solid var(--border); display: flex; align-items: center; gap: 16px; transition: transform 0.2s, box-shadow 0.2s; min-height: 80px; }
  .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(0,0,0,0.08); }
  .stat-card .icon-box { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .stat-card .icon-box.teal { background: #E1F7F6; color: var(--accent-teal); }
  .stat-card .icon-box.orange { background: #FFF0E6; color: var(--accent-orange); }
  .stat-card .info .number { font-size: 26px; font-weight: 700; line-height: 1.2; color: var(--text-primary); }
  .stat-card .info .label { font-size: 13px; color: var(--text-secondary); font-weight: 500; }
  .chart-container { background: #fff; border-radius: var(--radius); padding: 16px 20px 12px; box-shadow: var(--shadow); border: 1px solid var(--border); height: 240px; display: flex; flex-direction: column; overflow: hidden; }
  .chart-container .chart-wrapper { flex: 1; min-height: 0; position: relative; width: 100%; }
  .chart-container canvas { position: absolute; inset: 0; width: 100% !important; height: 100% !important; }

  /* ===== ACCÈS RAPIDES ===== */
  .quick-access { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
  .quick-btn { background: #fff; border-radius: var(--radius); padding: 22px 16px; box-shadow: var(--shadow); border: 1px solid var(--border); text-decoration: none; color: var(--text-primary); display: flex; flex-direction: column; align-items: center; gap: 12px; transition: all 0.3s; text-align: center; }
  .quick-btn:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.1); border-color: var(--accent-orange-light); }
  .quick-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; }
  .quick-icon.orange { background: #FFF0E6; color: var(--accent-orange); }
  .quick-icon.teal { background: #E1F7F6; color: var(--accent-teal); }
  .quick-icon.yellow { background: #FFF8E0; color: var(--accent-yellow); }
  .quick-icon.pink { background: #FDE8E8; color: #E87171; }
  .quick-btn span { font-weight: 600; font-size: 14px; }
  .quick-btn small { font-size: 12px; color: var(--text-secondary); }

  /* ===== DERNIERS POSTS — 2 colonnes ===== */
  .posts-section { margin-bottom: 8px; }
  .posts-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; }
  .carousel-nav { display: flex; gap: 8px; }
  .carousel-btn { width: 38px; height: 38px; border-radius: 12px; border: 1px solid var(--border); background: #fff; color: var(--text-secondary); font-size: 18px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; box-shadow: var(--shadow); line-height: 1; }
  .carousel-btn:hover { background: var(--accent-orange); border-color: var(--accent-orange); color: #fff; box-shadow: 0 4px 14px rgba(244,123,32,0.3); }
  .carousel-btn:disabled { opacity: 0.35; cursor: not-allowed; pointer-events: none; }
  .carousel-viewport { overflow: hidden; border-radius: var(--radius); }
  .carousel-track { display: flex; gap: 20px; transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94); will-change: transform; }

  /* Chaque carte = exactement 50% de la zone visible */
  .post-card {
    flex: 0 0 calc(50% - 10px);
    min-width: 0;
    background: #fff;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
  }
  .post-card:hover { transform: translateY(-4px); box-shadow: 0 10px 32px rgba(0,0,0,0.09); }
  .post-card-banner { height: 6px; background: linear-gradient(90deg, #F47B20, #F7AD19); flex-shrink: 0; }
  .post-card-banner.donation { background: linear-gradient(90deg, #1CB8B2, #0E9E98); }
  .post-card-body { padding: 20px 22px 18px; flex: 1; display: flex; flex-direction: column; gap: 10px; }
  .post-badge { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; width: fit-content; }
  .post-badge.volontariat { background: #FFF0E6; color: var(--accent-orange); }
  .post-badge.donation { background: #E1F7F6; color: var(--accent-teal); }
  .post-card-title { font-size: 16px; font-weight: 700; color: var(--text-primary); line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .post-card-desc { font-size: 13px; color: var(--text-secondary); line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; flex: 1; }
  .post-card-footer { padding: 12px 22px 16px; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
  .post-meta-item { display: flex; align-items: center; gap: 5px; font-size: 12px; color: var(--text-light); font-weight: 500; }
  .post-meta-item svg { flex-shrink: 0; }
  .carousel-dots { display: flex; justify-content: center; gap: 6px; margin-top: 16px; }
  .carousel-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--border); border: none; cursor: pointer; transition: all 0.2s; padding: 0; }
  .carousel-dot.active { background: var(--accent-orange); width: 20px; border-radius: 4px; }
  .posts-empty { text-align: center; padding: 48px 20px; color: var(--text-secondary); background: #fff; border-radius: var(--radius); border: 1px solid var(--border); font-size: 14px; }
  .posts-empty .empty-icon { font-size: 36px; margin-bottom: 12px; }

  @media (max-width: 992px) {
    .sticky-header { left: var(--sidebar-width); }
    .stats-titles-row { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: 1fr; }
    .stats-cards { grid-template-columns: 1fr 1fr; }
    .quick-access { grid-template-columns: repeat(2, 1fr); }
    .post-card { flex: 0 0 calc(50% - 10px); }
  }
  @media (max-width: 768px) {
    .sticky-header { left: 0; padding: 0 16px; }
    .main { margin-left: 0; padding: 16px; padding-top: calc(var(--header-height) + 16px); }
    .header { flex-direction: column; align-items: stretch; }
    .greeting-box { justify-content: center; flex-wrap: wrap; }
    .header-right { justify-content: center; flex-wrap: wrap; }
    .stats-cards { grid-template-columns: 1fr; }
    .quick-access { grid-template-columns: 1fr 1fr; }
    .welcome-box { padding: 20px 24px; text-align: center; justify-content: center; flex-direction: column; gap: 4px; }
    .welcome-box h2 { font-size: 22px; white-space: normal; }
    .org-name-box { max-width: 100%; min-width: auto; }
    .post-card { flex: 0 0 calc(100% - 0px); }
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

<!-- ===== STICKY HEADER ===== -->
<header class="sticky-header" id="stickyHeader">
  <div class="greeting-box">
    <h1>Accueil</h1>
    <span class="separator">·</span>
    <span class="sub">Espace organisation</span>
  </div>
  <div class="header-right">
    <a href="notifications.php" class="notif-icon">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <?php if($total_notifs > 0): ?>
        <span class="notif-count-header"><?php echo $total_notifs; ?></span>
      <?php endif; ?>
    </a>
    <div class="org-name-box" id="orgDropdown">
      <div class="org-avatar">
        <?php if (!empty($org['logo'])): ?>
          <img src="../<?php echo htmlspecialchars($org['logo']); ?>" alt="Logo">
        <?php else: ?>
          <?php echo htmlspecialchars($initials); ?>
        <?php endif; ?>
      </div>
      <span class="name"><?php echo htmlspecialchars($org['nom_organisation']); ?></span>
      <span class="chevron">▾</span>
      <div class="dropdown-menu-header">
        <div class="dropdown-org-info">
          <div class="d-avatar">
            <?php if (!empty($org['logo'])): ?>
              <img src="../<?php echo htmlspecialchars($org['logo']); ?>" alt="Logo">
            <?php else: ?>
              <?php echo htmlspecialchars($initials); ?>
            <?php endif; ?>
          </div>
          <div>
            <div class="d-name"><?php echo htmlspecialchars($org['nom_organisation']); ?></div>
            <div class="d-role">Organisation</div>
          </div>
        </div>
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
</header>

<!-- ===== MAIN ===== -->
<div class="main">

  <!-- WELCOME -->
  <div class="welcome-box">
    <h2>Bienvenue, <?php echo htmlspecialchars($org['nom_organisation']); ?> !</h2>
    <p>Content de vous voir ! Votre plateforme vous attend pour continuer à faire la différence.</p>
  </div>

  <!-- TITRES GRILLE -->
  <div class="stats-titles-row">
    <div class="section-title">
      <span class="icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12v-2a5 5 0 0 0-5-5H8a5 5 0 0 0-5 5v2"/><circle cx="12" cy="16" r="5"/><path d="M12 11v5"/><path d="M9 16h6"/></svg>
      </span>
      Statistiques
    </div>
    <div class="section-title">
      <span class="icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      </span>
      Évolution des interactions (7 derniers jours)
    </div>
  </div>

  <!-- STATS + GRAPHIQUE -->
  <div class="stats-grid">
    <div class="stats-cards">
      <div class="stat-card">
        <div class="icon-box teal">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="info">
          <div class="number"><?php echo $total_benevoles; ?></div>
          <div class="label">Bénévoles acceptés</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="icon-box orange">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M7 8h10"/><path d="M7 12h6"/><path d="M7 16h4"/></svg>
        </div>
        <div class="info">
          <div class="number"><?php echo $total_posts; ?></div>
          <div class="label">Posts publiés</div>
        </div>
      </div>
    </div>
    <div class="chart-container">
      <div class="chart-wrapper">
        <canvas id="interactionsChart"></canvas>
      </div>
    </div>
  </div>

  <!-- ACCÈS RAPIDES -->
  <div class="section-title section-title-standalone">
    <span class="icon">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
    </span>
    Accès rapides
  </div>
  <div class="quick-access">
    <!-- Nouveau post -->
    <a href="creer_post.php" class="quick-btn">
      <div class="quick-icon orange">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
        </svg>
      </div>
      <span>Nouveau post</span>
      <small>Appel à bénévoles ou dons</small>
    </a>
    <!-- Bénévoles -->
    <a href="benevoles.php" class="quick-btn">
      <div class="quick-icon teal">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </div>
      <span>Bénévoles</span>
      <small>Gérer vos volontaires</small>
    </a>
    <!-- Ma page -->
    <a href="ma_page.php" class="quick-btn">
      <div class="quick-icon yellow">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
        </svg>
      </div>
      <span>Ma page</span>
      <small>Voir votre profil public</small>
    </a>
    <!-- Messages -->
    <a href="messages.php" class="quick-btn">
      <div class="quick-icon pink">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
      </div>
      <span>Messages</span>
      <small>Consulter vos conversations</small>
    </a>
  </div>

  <!-- ===== DERNIERS POSTS ===== -->
  <div class="posts-section">
    <div class="posts-header">
      <div class="section-title">
        <span class="icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
          </svg>
        </span>
        Derniers posts
      </div>
      <?php if(count($derniers_posts) > 2): ?>
      <div class="carousel-nav">
        <button class="carousel-btn" id="prevBtn" disabled>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <button class="carousel-btn" id="nextBtn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
      </div>
      <?php endif; ?>
    </div>

    <?php if(count($derniers_posts) > 0): ?>
      <div class="carousel-viewport">
        <div class="carousel-track" id="carouselTrack">
          <?php foreach($derniers_posts as $post): ?>
            <?php $isDonation = $post['type_demande'] === 'donation'; ?>
            <div class="post-card">
              <div class="post-card-banner <?php echo $isDonation ? 'donation' : ''; ?>"></div>
              <div class="post-card-body">
                <span class="post-badge <?php echo $isDonation ? 'donation' : 'volontariat'; ?>">
                  <?php if($isDonation): ?>
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
                    Donation
                  <?php else: ?>
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                    Volontariat
                  <?php endif; ?>
                </span>
                <div class="post-card-title"><?php echo htmlspecialchars($post['titre']); ?></div>
                <div class="post-card-desc"><?php echo htmlspecialchars($post['description']); ?></div>
              </div>
              <div class="post-card-footer">
                <span class="post-meta-item">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                  <?php echo date('d/m/Y', strtotime($post['date_creation'])); ?>
                </span>
                <span class="post-meta-item">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                  <?php echo htmlspecialchars($post['localisation']); ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if(count($derniers_posts) > 2): ?>
      <div class="carousel-dots" id="carouselDots">
        <?php
          $pages = ceil(count($derniers_posts) / 2);
          for($d = 0; $d < $pages; $d++):
        ?>
          <button class="carousel-dot <?php echo $d===0 ? 'active' : ''; ?>" data-index="<?php echo $d; ?>"></button>
        <?php endfor; ?>
      </div>
      <?php endif; ?>

    <?php else: ?>
      <div class="posts-empty">
        <div class="empty-icon">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#b0b3c6" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        Aucun post publié pour le moment.
      </div>
    <?php endif; ?>
  </div>

</div>

<script>
  // Dropdown profil
  document.getElementById('orgDropdown').addEventListener('click', function(e) {
    e.stopPropagation();
    this.classList.toggle('active');
  });
  document.addEventListener('click', function() {
    document.getElementById('orgDropdown').classList.remove('active');
  });

  // Header scroll shadow
  window.addEventListener('scroll', function() {
    const header = document.getElementById('stickyHeader');
    if (window.scrollY > 10) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
  });

  // Graphique
  new Chart(document.getElementById('interactionsChart').getContext('2d'), {
    type: 'line',
    data: {
      labels: <?php echo json_encode($dates); ?>,
      datasets: [
        {
          label: '❤️ Likes',
          data: <?php echo json_encode($likes_data); ?>,
          borderColor: '#F47B20', backgroundColor: 'rgba(244,123,32,0.1)',
          fill: true, tension: 0.3, pointBackgroundColor: '#F47B20',
          pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6, borderWidth: 2.5,
        },
        {
          label: '📩 Demandes de participation',
          data: <?php echo json_encode($participations_data); ?>,
          borderColor: '#1CB8B2', backgroundColor: 'rgba(28,184,178,0.1)',
          fill: true, tension: 0.3, pointBackgroundColor: '#1CB8B2',
          pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6, borderWidth: 2.5,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      layout: {
        padding: { top: 4, right: 8, bottom: 0, left: 0 }
      },
      plugins: {
        legend: { position: 'bottom', labels: { font: { size: 10, weight: '500' }, boxWidth: 10, usePointStyle: true, pointStyle: 'circle', padding: 10 } },
        tooltip: { backgroundColor: '#1a1d2e', titleFont: { size: 12, weight: '600' }, bodyFont: { size: 11 }, padding: 10, cornerRadius: 8,
          callbacks: { label: ctx => ctx.dataset.label + ': ' + ctx.raw + ' interaction' + (ctx.raw > 1 ? 's' : '') }
        }
      },
      scales: {
        y: {
          min: 0,
          max: 21,
          grid: { color: '#f0f1f7', drawBorder: false },
          ticks: { stepSize: 3, font: { size: 10 }, color: '#8b8fa8', padding: 6 },
          title: {
            display: true,
            text: 'Nombre d\'interactions',
            font: { size: 10, weight: '600' },
            color: '#8b8fa8',
            padding: { right: 8 }
          }
        },
        x: {
          grid: { display: false, drawBorder: false },
          ticks: { font: { size: 10 }, color: '#8b8fa8', padding: 4, maxRotation: 0 },
          title: {
            display: true,
            text: 'Date',
            font: { size: 10, weight: '600' },
            color: '#8b8fa8',
            padding: { top: 6 }
          }
        }
      },
      interaction: { intersect: false, mode: 'index' }
    }
  });

  // ===== CARROUSEL (2 cartes visibles) =====
  (function() {
    const track   = document.getElementById('carouselTrack');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const dotsEl  = document.getElementById('carouselDots');

    if (!track) return;

    const cards   = track.querySelectorAll('.post-card');
    const total   = cards.length;
    const VISIBLE = 2; // toujours 2 cartes visibles
    let current   = 0;

    function getCardWidth() {
      const gap = 20;
      const viewportW = track.parentElement.offsetWidth;
      return (viewportW - gap * (VISIBLE - 1)) / VISIBLE;
    }

    function updateCarousel() {
      const cardW    = getCardWidth();
      const gap      = 20;
      const maxIndex = Math.max(0, total - VISIBLE);

      if (current > maxIndex) current = maxIndex;

      cards.forEach(c => { c.style.flex = `0 0 ${cardW}px`; });

      const offset = current * (cardW + gap);
      track.style.transform = `translateX(-${offset}px)`;

      if (prevBtn) prevBtn.disabled = current === 0;
      if (nextBtn) nextBtn.disabled = current >= maxIndex;

      if (dotsEl) {
        // dots par page de 2
        const pageIndex = Math.floor(current / VISIBLE);
        dotsEl.querySelectorAll('.carousel-dot').forEach((dot, i) => {
          dot.classList.toggle('active', i === pageIndex);
        });
      }
    }

    if (prevBtn) prevBtn.addEventListener('click', () => { if (current > 0) { current--; updateCarousel(); } });
    if (nextBtn) nextBtn.addEventListener('click', () => {
      const maxIndex = Math.max(0, total - VISIBLE);
      if (current < maxIndex) { current++; updateCarousel(); }
    });

    if (dotsEl) {
      dotsEl.querySelectorAll('.carousel-dot').forEach(dot => {
        dot.addEventListener('click', () => {
          current = parseInt(dot.dataset.index) * VISIBLE;
          updateCarousel();
        });
      });
    }

    window.addEventListener('resize', updateCarousel);
    updateCarousel();
  })();
</script>

</body>
</html>
