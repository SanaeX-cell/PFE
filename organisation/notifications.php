<?php
session_start();
require_once '../connexion_bd.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'organisation') {
    header('Location: ../connexion.php');
    exit();
}

$org_id = $_SESSION['user_id'];

// =====================================================================
// GESTION DES ACTIONS
// =====================================================================

// --- Accepter une participation (volontariat ou donation) ---
if (isset($_GET['accepter_participation'])) {
    $participation_id = (int)$_GET['accepter_participation'];
    $notif_id = (int)($_GET['notif_id'] ?? 0);

    // Récupérer les infos de la participation
    $sql_part = "SELECT p.*, po.titre, po.type_demande FROM participations p
                 JOIN posts po ON po.id = p.post_id
                 WHERE p.id = $participation_id AND po.organisation_id = $org_id";
    $result_part = mysqli_query($conn, $sql_part);
    if ($row_part = mysqli_fetch_assoc($result_part)) {
        $contrib_id   = $row_part['contributeur_id'];
        $post_id      = $row_part['post_id'];
        $post_titre   = mysqli_real_escape_string($conn, $row_part['titre']);
        $type_demande = $row_part['type_demande'];

        // Mettre à jour le statut participation → accepté
        mysqli_query($conn, "UPDATE participations SET statut='accepte' WHERE id=$participation_id");

        // Envoyer une notification au contributeur
        $msg_notif = mysqli_real_escape_string($conn, "Votre demande pour \"$post_titre\" a été acceptée !");
        mysqli_query($conn, "INSERT INTO notifications_contributeur (contributeur_id, organisation_id, post_id, type, message, statut)
                              VALUES ($contrib_id, $org_id, $post_id, 'accepte', '$msg_notif', 'non_lue')");

        // Marquer la notification organisation comme lue
        if ($notif_id > 0) {
            mysqli_query($conn, "UPDATE notifications_organisation SET statut='lue' WHERE id=$notif_id");
        }
    }
    header('Location: notifications.php?action_done=accepte');
    exit();
}

// --- Refuser une participation ---
if (isset($_GET['refuser_participation'])) {
    $participation_id = (int)$_GET['refuser_participation'];
    $notif_id = (int)($_GET['notif_id'] ?? 0);

    $sql_part = "SELECT p.*, po.titre FROM participations p
                 JOIN posts po ON po.id = p.post_id
                 WHERE p.id = $participation_id AND po.organisation_id = $org_id";
    $result_part = mysqli_query($conn, $sql_part);
    if ($row_part = mysqli_fetch_assoc($result_part)) {
        $contrib_id = $row_part['contributeur_id'];
        $post_id    = $row_part['post_id'];
        $post_titre = mysqli_real_escape_string($conn, $row_part['titre']);

        // Supprimer la participation
        mysqli_query($conn, "DELETE FROM participations WHERE id=$participation_id");

        // Notifier le contributeur du refus
        $msg_notif = mysqli_real_escape_string($conn, "Votre demande pour \"$post_titre\" n'a pas été retenue.");
        mysqli_query($conn, "INSERT INTO notifications_contributeur (contributeur_id, organisation_id, post_id, type, message, statut)
                              VALUES ($contrib_id, $org_id, $post_id, 'refuse', '$msg_notif', 'non_lue')");

        if ($notif_id > 0) {
            mysqli_query($conn, "UPDATE notifications_organisation SET statut='lue' WHERE id=$notif_id");
        }
    }
    header('Location: notifications.php?action_done=refuse');
    exit();
}

// --- Marquer toutes comme lues ---
if (isset($_GET['tout_lire'])) {
    mysqli_query($conn, "UPDATE notifications_organisation SET statut='lue' WHERE organisation_id=$org_id");
    header('Location: notifications.php');
    exit();
}

// --- Marquer une seule comme lue ---
if (isset($_GET['lire'])) {
    $notif_id = (int)$_GET['lire'];
    mysqli_query($conn, "UPDATE notifications_organisation SET statut='lue' WHERE id=$notif_id AND organisation_id=$org_id");
    header('Location: notifications.php');
    exit();
}

// =====================================================================
// RÉCUPÉRER LES NOTIFICATIONS
// =====================================================================
/*
  Types dans notifications_organisation :
    - 'follow'      → un contributeur suit l'organisation
    - 'volontariat' → un contributeur demande à rejoindre un post volontariat
    - 'donation'    → un contributeur propose un don sur un post donation

  La table notifications_organisation pointe vers :
    contributeur_id, post_id (pour follow, post_id peut être 0 ou un post fictif)
*/

$sql_notifs = "
  SELECT n.*,
         c.nom AS c_nom, c.prenom AS c_prenom, c.email AS c_email, c.telephone AS c_tel,
         po.titre AS post_titre, po.type_demande, po.sous_type,
         (SELECT p.id FROM participations p WHERE p.contributeur_id = n.contributeur_id AND p.post_id = n.post_id LIMIT 1) AS participation_id
  FROM notifications_organisation n
  LEFT JOIN contributeurs c ON c.id = n.contributeur_id
  LEFT JOIN posts po ON po.id = n.post_id
  WHERE n.organisation_id = $org_id
  ORDER BY n.statut ASC, n.date_creation DESC
";
$result_notifs = mysqli_query($conn, $sql_notifs);
$notifications = [];
while ($row = mysqli_fetch_assoc($result_notifs)) {
    $notifications[] = $row;
}

$nb_non_lues = count(array_filter($notifications, fn($n) => $n['statut'] === 'non_lue'));

// Infos organisation
$org = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM organisations WHERE id = $org_id"));
$current_page = basename($_SERVER['PHP_SELF']);

// Message flash
$flash = $_GET['action_done'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ConnectAid - Notifications</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #f5f6fa; --sidebar-bg: #fff; --card-bg: #fff;
    --text-primary: #1a1d2e; --text-secondary: #8b8fa8; --text-light: #b0b3c6;
    --accent-teal: #1CB8B2; --accent-orange: #F47B20;
    --border: #f0f1f7; --shadow: 0 2px 20px rgba(0,0,0,0.06);
    --radius: 18px; --sidebar-width: 220px;
    --green: #16a34a; --red: #dc2626;
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
  .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 24px; color: var(--text-secondary); transition: all 0.2s; font-weight: 500; font-size: 14px; position: relative; margin: 2px 0; text-decoration: none; }
  .nav-item:hover { color: var(--text-primary); background: var(--bg); }
  .nav-item.active { color: var(--accent-teal); background: #E1F7F6; }
  .nav-item.active::before { content:''; position:absolute; left:0; top:50%; transform:translateY(-50%); width:3px; height:60%; background:var(--accent-teal); border-radius:0 4px 4px 0; }
  .nav-icon { width:22px; height:22px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .nav-badge { margin-left:auto; background:var(--accent-orange); color:#fff; font-size:10px; font-weight:700; border-radius:20px; padding:2px 7px; }
  .sidebar-bottom { padding:0; border-top:1px solid var(--border); padding-top:12px; margin-top:auto; }
  .sidebar-bottom .nav-item { background:var(--accent-teal); color:#fff; border-radius:40px; margin:8px 16px; justify-content:center; }
  .sidebar-bottom .nav-item:hover { background:#138F8A; }
  .sidebar-bottom .nav-item::before { display:none; }

  /* ── MAIN ── */
  .main { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; }
  .topbar { background:transparent; padding:16px 32px; display:flex; align-items:center; gap:12px; position:sticky; top:0; z-index:5; flex-wrap:wrap; }
  .greeting-bar { flex:1; background:#fff; border:1px solid var(--border); border-radius:12px; padding:10px 18px; box-shadow:var(--shadow); min-width:200px; }
  .greeting-bar h2 { font-size:15px; font-weight:700; margin-bottom:2px; }
  .greeting-bar p  { font-size:11px; color:var(--text-secondary); }
  .profile-area { position:relative; }
  .user-info { display:flex; align-items:center; gap:10px; cursor:pointer; background:#fff; border:1px solid var(--border); border-radius:12px; padding:6px 14px 6px 6px; box-shadow:var(--shadow); }
  .avatar { width:36px; height:36px; border-radius:50%; background:var(--accent-teal); display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; color:#fff; overflow:hidden; flex-shrink:0; }
  .user-name { font-weight:600; font-size:13px; white-space:nowrap; color:var(--text-primary); }
  .dropdown-icon { color:#8b8fa8; transition:transform 0.2s; }
  .dropdown-menu { position:absolute; top:100%; right:0; margin-top:8px; background:#fff; border:1px solid var(--border); border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.1); min-width:180px; opacity:0; visibility:hidden; transform:translateY(-10px); transition:all 0.2s; z-index:100; }
  .profile-area.active .dropdown-menu { opacity:1; visibility:visible; transform:translateY(0); }
  .profile-area.active .dropdown-icon { transform:rotate(180deg); }
  .dropdown-menu a { display:flex; align-items:center; gap:10px; padding:10px 16px; color:var(--text-secondary); text-decoration:none; font-size:13px; transition:background 0.2s; }
  .dropdown-menu a:hover { background:var(--bg); color:var(--text-primary); }
  .dropdown-menu hr { margin:4px 0; border:none; border-top:1px solid var(--border); }

  /* ── CONTENT ── */
  .content { padding: 16px 32px 40px; display:flex; flex-direction:column; gap:16px; }

  /* ── FLASH ── */
  .flash { padding:12px 18px; border-radius:10px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:8px; }
  .flash-ok  { background:#dcfce7; color:#166534; }
  .flash-ko  { background:#fee2e2; color:#991b1b; }

  /* ── HEADER ROW ── */
  .notif-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
  .notif-title  { font-size:17px; font-weight:700; display:flex; align-items:center; gap:10px; }
  .badge-count  { background:var(--accent-orange); color:#fff; font-size:11px; font-weight:700; border-radius:20px; padding:3px 10px; }
  .btn-read-all { display:inline-flex; align-items:center; gap:6px; background:transparent; border:1.5px solid var(--border); border-radius:9px; padding:8px 14px; font-size:12px; font-weight:600; color:var(--text-secondary); cursor:pointer; font-family:inherit; transition:all 0.2s; text-decoration:none; }
  .btn-read-all:hover { border-color:var(--accent-teal); color:var(--accent-teal); }

  /* ── NOTIFICATION CARDS ── */
  .notif-list { display:flex; flex-direction:column; gap:10px; }
  .notif-card { background:#fff; border-radius:14px; padding:18px 20px; box-shadow:var(--shadow); display:flex; align-items:flex-start; gap:14px; border-left:4px solid transparent; transition:all 0.2s; position:relative; }
  .notif-card.non-lue { border-left-color:var(--accent-teal); background:#fafffe; }
  .notif-card.non-lue::after { content:''; width:8px; height:8px; background:var(--accent-teal); border-radius:50%; position:absolute; top:18px; right:18px; }

  /* Couleurs par type */
  .notif-card.type-follow     .notif-icon-wrap { background:#E1F7F6; color:var(--accent-teal); }
  .notif-card.type-volontariat .notif-icon-wrap { background:#EFF6FF; color:#2563eb; }
  .notif-card.type-donation    .notif-icon-wrap { background:#FFF0E6; color:var(--accent-orange); }

  .notif-icon-wrap { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
  .notif-body  { flex:1; min-width:0; }
  .notif-text  { font-size:13.5px; line-height:1.55; margin-bottom:6px; }
  .notif-text strong { color:var(--text-primary); font-weight:700; }
  .notif-meta  { font-size:11px; color:var(--text-light); margin-bottom:12px; }
  .notif-post-tag { display:inline-flex; align-items:center; gap:4px; font-size:11px; background:var(--bg); border:1px solid var(--border); border-radius:6px; padding:2px 8px; color:var(--text-secondary); margin-bottom:10px; }

  /* ── ACTION BUTTONS ── */
  .notif-actions { display:flex; align-items:center; gap:7px; flex-wrap:wrap; }
  .btn-a { display:inline-flex; align-items:center; gap:5px; border:none; border-radius:8px; padding:7px 13px; font-size:12px; font-weight:600; cursor:pointer; font-family:inherit; transition:all 0.2s; text-decoration:none; white-space:nowrap; }
  .btn-contact  { background:#EFF6FF; color:#2563eb; }
  .btn-contact:hover { background:#dbeafe; }
  .btn-accept   { background:#dcfce7; color:var(--green); }
  .btn-accept:hover  { background:#bbf7d0; }
  .btn-refuse   { background:#fee2e2; color:var(--red); }
  .btn-refuse:hover  { background:#fecaca; }
  .btn-done-tag { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:600; color:var(--green); background:#f0fdf4; border:1px solid #bbf7d0; border-radius:7px; padding:5px 10px; }
  .btn-refused-tag { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:600; color:#6b7280; background:#f9fafb; border:1px solid var(--border); border-radius:7px; padding:5px 10px; }

  /* ── EMPTY ── */
  .empty-notifs { text-align:center; padding:60px 20px; color:var(--text-secondary); }
  .empty-notifs svg { opacity:0.2; margin-bottom:14px; }
  .empty-notifs p { font-size:14px; }

  /* ── SEPARATOR ── */
  .section-label { font-size:11px; font-weight:700; color:var(--text-light); text-transform:uppercase; letter-spacing:0.7px; padding:4px 0; }
</style>
</head>
<body>

<!-- ═══════════════════ SIDEBAR ═══════════════════ -->
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
    <a href="creer_post.php" class="nav-item">
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
    <a href="notifications.php" class="nav-item active">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></span>
      Notifications
      <?php if ($nb_non_lues > 0): ?>
        <span class="nav-badge"><?php echo $nb_non_lues; ?></span>
      <?php endif; ?>
    </a>
  </nav>
  <div class="sidebar-bottom">
    <a href="../logout.php" class="nav-item">
      <span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>
      Déconnexion
    </a>
  </div>
</aside>

<!-- ═══════════════════ MAIN ═══════════════════ -->
<div class="main">
  <header class="topbar">
    <div class="greeting-bar">
      <h2>Notifications</h2>
      <p>Suivez les interactions de votre communauté</p>
    </div>
    <div class="profile-area" id="profileArea">
      <div class="user-info" onclick="document.getElementById('profileArea').classList.toggle('active')">
        <div class="avatar">
          <?php if ($org['logo']): ?>
            <img src="../<?php echo htmlspecialchars($org['logo']); ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
          <?php else: ?>
            <?php echo strtoupper(substr($org['nom_organisation'], 0, 1)); ?>
          <?php endif; ?>
        </div>
        <span class="user-name"><?php echo htmlspecialchars($org['email_connexion']); ?></span>
        <span class="dropdown-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708"/></svg>
        </span>
      </div>
      <div class="dropdown-menu">
        <a href="ma_page.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Ma page</a>
        <hr>
        <a href="../logout.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Déconnexion</a>
      </div>
    </div>
  </header>

  <div class="content">

    <!-- FLASH MESSAGE -->
    <?php if ($flash === 'accepte'): ?>
      <div class="flash flash-ok">✅ Demande acceptée — le bénévole a été notifié.</div>
    <?php elseif ($flash === 'refuse'): ?>
      <div class="flash flash-ko">❌ Demande refusée — le bénévole a été notifié.</div>
    <?php endif; ?>

    <!-- HEADER -->
    <div class="notif-header">
      <div class="notif-title">
        Toutes les notifications
        <?php if ($nb_non_lues > 0): ?>
          <span class="badge-count"><?php echo $nb_non_lues; ?> non lue<?php echo $nb_non_lues > 1 ? 's' : ''; ?></span>
        <?php endif; ?>
      </div>
      <?php if ($nb_non_lues > 0): ?>
        <a href="?tout_lire=1" class="btn-read-all">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          Tout marquer comme lu
        </a>
      <?php endif; ?>
    </div>

    <!-- NOTIFICATIONS LIST -->
    <div class="notif-list">
      <?php if (empty($notifications)): ?>
        <div class="empty-notifs">
          <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <p>Aucune notification pour le moment.</p>
        </div>
      <?php else: ?>

        <?php
        // Séparer non-lues / lues pour l'affichage
        $non_lues = array_filter($notifications, fn($n) => $n['statut'] === 'non_lue');
        $lues     = array_filter($notifications, fn($n) => $n['statut'] === 'lue');

        // Fonction helper : temps relatif
        function tempsRelatif($date_str) {
            $diff = time() - strtotime($date_str);
            if ($diff < 60)           return "il y a quelques secondes";
            if ($diff < 3600)         return "il y a " . floor($diff/60) . " min";
            if ($diff < 86400)        return "il y a " . floor($diff/3600) . " h";
            if ($diff < 604800)       return "il y a " . floor($diff/86400) . " j";
            return date('d/m/Y', strtotime($date_str));
        }

        // Fonction helper : rendu d'une notification
        function renderNotification($n, $org_id) {
            $c_nom_complet = htmlspecialchars(trim($n['c_nom'] . ' ' . $n['c_prenom']));
            $contrib_id    = (int)$n['contributeur_id'];
            $notif_id      = (int)$n['id'];
            $temps         = tempsRelatif($n['date_creation']);
            $statut_card   = $n['statut'] === 'non_lue' ? 'non-lue' : '';
            $type_css      = 'type-' . $n['type'];
            $part_id       = (int)$n['participation_id'];

            // Vérifier si déjà traité (participation acceptée ou supprimée)
            $deja_accepte = false;
            if ($part_id > 0) {
                global $conn;
                $check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT statut FROM participations WHERE id=$part_id"));
                $deja_accepte = ($check && $check['statut'] === 'accepte');
            }
            $part_supprimee = ($n['type'] !== 'follow' && $part_id === 0);

            ob_start();
            ?>
            <div class="notif-card <?php echo $statut_card; ?> <?php echo $type_css; ?>">

              <!-- ICÔNE -->
              <div class="notif-icon-wrap">
                <?php if ($n['type'] === 'follow'): ?>
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                <?php elseif ($n['type'] === 'volontariat'): ?>
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                <?php else: ?>
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <?php endif; ?>
              </div>

              <div class="notif-body">

                <!-- TEXTE SELON TYPE -->
                <?php if ($n['type'] === 'follow'): ?>
                  <div class="notif-text">
                    <strong><?php echo $c_nom_complet; ?></strong><br>
                    suit maintenant votre page.
                  </div>

                <?php elseif ($n['type'] === 'volontariat'): ?>
                  <div class="notif-text">
                    <strong><?php echo $c_nom_complet; ?></strong>
                    a demandé de rejoindre.
                  </div>

                <?php elseif ($n['type'] === 'donation'): ?>
                  <?php
                    // Préciser le type de don dans le message
                    $detail_don = '';
                    if ($n['sous_type'] === 'argent')  $detail_don = 'un don en argent';
                    elseif ($n['sous_type'])            $detail_don = 'un don en nature';
                    else                                $detail_don = 'un don';
                  ?>
                  <div class="notif-text">
                    <strong><?php echo $c_nom_complet; ?></strong>
                    a proposé <?php echo $detail_don; ?>.
                  </div>
                <?php endif; ?>

                <!-- POST LIÉ -->
                <?php if (!empty($n['post_titre'])): ?>
                  <div class="notif-post-tag">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
                    <?php echo htmlspecialchars($n['post_titre']); ?>
                  </div>
                <?php endif; ?>

                <!-- TEMPS -->
                <div class="notif-meta"><?php echo $temps; ?></div>

                <!-- BOUTONS D'ACTION -->
                <div class="notif-actions">

                  <!-- Bouton Contacter (tous les types) -->
                  <a href="messages.php?dest=<?php echo $contrib_id; ?>"
                     class="btn-a btn-contact"
                     onclick="marquerLue(<?php echo $notif_id; ?>)">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Contacter
                  </a>

                  <?php if ($n['type'] !== 'follow'): ?>
                    <?php if ($deja_accepte): ?>
                      <!-- Déjà accepté -->
                      <span class="btn-done-tag">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Accepté
                      </span>
                    <?php elseif ($part_supprimee): ?>
                      <!-- Participation supprimée = refusée -->
                      <span class="btn-refused-tag">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        Refusé
                      </span>
                    <?php else: ?>
                      <!-- Boutons Accepter / Refuser -->
                      <a href="?accepter_participation=<?php echo $part_id; ?>&notif_id=<?php echo $notif_id; ?>"
                         class="btn-a btn-accept"
                         onclick="return confirm('Accepter la demande de <?php echo addslashes($c_nom_complet); ?> ?')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        Accepter
                      </a>
                      <a href="?refuser_participation=<?php echo $part_id; ?>&notif_id=<?php echo $notif_id; ?>"
                         class="btn-a btn-refuse"
                         onclick="return confirm('Refuser la demande de <?php echo addslashes($c_nom_complet); ?> ?')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        Refuser
                      </a>
                    <?php endif; ?>
                  <?php endif; ?>

                </div><!-- /notif-actions -->
              </div><!-- /notif-body -->
            </div>
            <?php
            return ob_get_clean();
        }
        ?>

        <!-- NON LUES -->
        <?php if (!empty($non_lues)): ?>
          <div class="section-label">Nouvelles</div>
          <?php foreach ($non_lues as $n): echo renderNotification($n, $org_id); endforeach; ?>
        <?php endif; ?>

        <!-- LUES -->
        <?php if (!empty($lues)): ?>
          <?php if (!empty($non_lues)): ?>
            <div class="section-label" style="margin-top:8px;">Déjà lues</div>
          <?php endif; ?>
          <?php foreach ($lues as $n): echo renderNotification($n, $org_id); endforeach; ?>
        <?php endif; ?>

      <?php endif; ?>
    </div><!-- /notif-list -->
  </div><!-- /content -->
</div><!-- /main -->

<script>
function marquerLue(notifId) {
  // Appel silencieux pour marquer comme lue avant de naviguer
  fetch('notifications.php?lire=' + notifId);
}

document.addEventListener('click', function(e) {
  const area = document.getElementById('profileArea');
  if (area && !area.contains(e.target)) area.classList.remove('active');
});

// Auto-dismiss flash après 4s
const flash = document.querySelector('.flash');
if (flash) setTimeout(() => flash.style.opacity = '0', 4000);
</script>
</body>
</html>