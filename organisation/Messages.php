<?php
session_start();
require_once '../connexion_bd.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'organisation') {
    header('Location: ../connexion.php');
    exit();
}

$org_id = $_SESSION['user_id'];

// Envoyer un message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'], $_POST['destinataire_id'])) {
    $dest_id = (int)$_POST['destinataire_id'];
    $msg = mysqli_real_escape_string($conn, trim($_POST['message']));
    if (!empty($msg)) {
        mysqli_query($conn, "INSERT INTO messages (expediteur_id, expediteur_type, destinataire_id, destinataire_type, message) VALUES ($org_id, 'organisation', $dest_id, 'contributeur', '$msg')");
    }
    header("Location: messages.php?dest=$dest_id");
    exit();
}

// Récupérer les contributeurs avec qui l'organisation a échangé ou qui sont bénévoles
$sql_contacts = "
  SELECT DISTINCT c.id, c.nom, c.prenom, c.email
  FROM contributeurs c
  WHERE c.id IN (
    SELECT m.expediteur_id FROM messages m WHERE m.expediteur_type='contributeur' AND m.destinataire_id=$org_id AND m.destinataire_type='organisation'
    UNION
    SELECT m.destinataire_id FROM messages m WHERE m.expediteur_id=$org_id AND m.expediteur_type='organisation' AND m.destinataire_type='contributeur'
    UNION
    SELECT p.contributeur_id FROM participations p JOIN posts po ON po.id=p.post_id WHERE po.organisation_id=$org_id
  )
  ORDER BY c.nom, c.prenom
";
$result_contacts = mysqli_query($conn, $sql_contacts);
$contacts = [];
while($row = mysqli_fetch_assoc($result_contacts)) {
    $contacts[] = $row;
}

// Destinataire actif
$dest_id = isset($_GET['dest']) ? (int)$_GET['dest'] : (count($contacts) > 0 ? $contacts[0]['id'] : 0);
$dest = null;
foreach($contacts as $c) { if($c['id'] == $dest_id) { $dest = $c; break; } }

// Messages de la conversation
$messages_conv = [];
if ($dest_id > 0) {
    $sql_msgs = "SELECT * FROM messages WHERE
      (expediteur_id=$org_id AND expediteur_type='organisation' AND destinataire_id=$dest_id AND destinataire_type='contributeur')
      OR
      (expediteur_id=$dest_id AND expediteur_type='contributeur' AND destinataire_id=$org_id AND destinataire_type='organisation')
      ORDER BY date_envoi ASC";
    $result_msgs = mysqli_query($conn, $sql_msgs);
    while($row = mysqli_fetch_assoc($result_msgs)) {
        $messages_conv[] = $row;
    }
}

// Infos organisation
$sql_org = "SELECT * FROM organisations WHERE id = $org_id";
$org = mysqli_fetch_assoc(mysqli_query($conn, $sql_org));
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ConnectAid - Messages</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #f5f6fa; --sidebar-bg: #fff; --card-bg: #fff;
    --text-primary: #1a1d2e; --text-secondary: #8b8fa8;
    --accent-teal: #1CB8B2; --accent-orange: #F47B20;
    --border: #f0f1f7; --shadow: 0 2px 20px rgba(0,0,0,0.06);
    --radius: 18px; --sidebar-width: 220px;
  }
  body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text-primary); display: flex; min-height: 100vh; font-size: 14px; }
  .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); display: flex; flex-direction: column; padding: 28px 0; position: fixed; top: 0; left: 0; bottom: 0; border-right: 1px solid var(--border); z-index: 10; }
  .logo { display: flex; align-items: center; justify-content: flex-end; width: 93%; padding: 0 24px 32px 18px; }
  .logo-image { width: 60px; height: 60px; object-fit: contain; border-radius: 12px; }
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
  .greeting-bar { flex: 1; background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 10px 18px; box-shadow: var(--shadow); }
  .greeting-bar h2 { font-size: 15px; font-weight: 700; margin-bottom: 2px; }
  .greeting-bar p { font-size: 11px; color: var(--text-secondary); }
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

  /* CHAT LAYOUT */
  .chat-wrapper { display: grid; grid-template-columns: 280px 1fr; gap: 0; margin: 0 32px 32px; height: calc(100vh - 100px); background: #fff; border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
  .contacts-panel { border-right: 1px solid var(--border); display: flex; flex-direction: column; }
  .contacts-header { padding: 18px 18px 14px; border-bottom: 1px solid var(--border); }
  .contacts-header h3 { font-size: 14px; font-weight: 700; }
  .contacts-list { flex: 1; overflow-y: auto; }
  .contact-item { display: flex; align-items: center; gap: 10px; padding: 13px 18px; cursor: pointer; border-bottom: 1px solid var(--border); transition: background 0.15s; text-decoration: none; color: inherit; }
  .contact-item:hover { background: var(--bg); }
  .contact-item.active { background: #E1F7F6; }
  .contact-avatar { width: 38px; height: 38px; border-radius: 50%; background: var(--accent-teal); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; flex-shrink: 0; }
  .contact-name { font-weight: 600; font-size: 13px; }
  .contact-email { font-size: 11px; color: var(--text-secondary); }
  .no-contacts { padding: 24px 18px; color: var(--text-secondary); font-size: 13px; text-align: center; }

  /* CHAT AREA */
  .chat-area { display: flex; flex-direction: column; }
  .chat-header { padding: 16px 22px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 12px; }
  .chat-header .avatar-lg { width: 42px; height: 42px; border-radius: 50%; background: var(--accent-teal); color: #fff; font-size: 16px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .chat-header h4 { font-size: 14px; font-weight: 700; }
  .chat-header p { font-size: 11px; color: var(--text-secondary); }
  .chat-messages { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 12px; }
  .bubble { max-width: 68%; padding: 10px 14px; border-radius: 16px; font-size: 13px; line-height: 1.5; }
  .bubble.sent { background: var(--accent-teal); color: #fff; align-self: flex-end; border-bottom-right-radius: 4px; }
  .bubble.received { background: var(--bg); color: var(--text-primary); align-self: flex-start; border-bottom-left-radius: 4px; }
  .bubble-time { font-size: 10px; opacity: 0.65; margin-top: 4px; text-align: right; }
  .chat-input { padding: 14px 16px; border-top: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
  .chat-input input { flex: 1; border: 1.5px solid var(--border); border-radius: 24px; padding: 10px 16px; font-size: 13px; font-family: inherit; outline: none; transition: border-color 0.2s; }
  .chat-input input:focus { border-color: var(--accent-teal); }
  .btn-send { width: 40px; height: 40px; border-radius: 50%; background: var(--accent-teal); border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; flex-shrink: 0; }
  .btn-send:hover { background: #138F8A; }
  .no-conv { flex: 1; display: flex; align-items: center; justify-content: center; color: var(--text-secondary); font-size: 13px; flex-direction: column; gap: 12px; }
</style>
</head>
<body>

<aside class="sidebar">
  <div class="logo">
    <img src="../images/logo.png" alt="Logo" class="logo-image">
    <div class="logo-text"><span>Connect</span><span>Aid</span></div>
  </div>
  <nav>
    <a href="accueil.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></span>Accueil</a>
    <a href="ma_page.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></span>Ma page</a>
    <a href="creer_post.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>Créer un post</a>
    <a href="benevoles.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></span>Bénévoles inscrits</a>
    <a href="messages.php" class="nav-item active"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>Messages</a>
    <a href="notifications.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></span>Notifications</a>
  </nav>
  <div class="sidebar-bottom">
    <a href="../logout.php" class="nav-item"><span class="nav-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></span>Déconnexion</a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <div class="greeting-bar">
      <h2>Messages</h2>
      <p>Échangez avec vos bénévoles</p>
    </div>
    <div class="profile-area" id="profileArea">
      <div class="user-info" onclick="document.getElementById('profileArea').classList.toggle('active')">
        <div class="avatar"><?php if($org['logo']): ?><img src="../<?php echo htmlspecialchars($org['logo']); ?>" style="width:100%;height:100%;object-fit:cover;" alt=""><?php else: echo strtoupper(substr($org['nom_organisation'], 0, 1)); endif; ?></div>
        <span class="user-name"><?php echo htmlspecialchars($org['email_connexion']); ?></span>
        <span class="dropdown-icon"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708"/></svg></span>
      </div>
      <div class="dropdown-menu">
        <a href="ma_page.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Ma page</a>
        <hr><a href="../logout.php"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Déconnexion</a>
      </div>
    </div>
  </header>

  <div class="chat-wrapper">
    <!-- CONTACTS -->
    <div class="contacts-panel">
      <div class="contacts-header"><h3>Conversations</h3></div>
      <div class="contacts-list">
        <?php if(empty($contacts)): ?>
          <div class="no-contacts">Aucune conversation pour l'instant.</div>
        <?php else: ?>
          <?php foreach($contacts as $c): ?>
          <a href="?dest=<?php echo $c['id']; ?>" class="contact-item <?php echo $c['id']==$dest_id?'active':''; ?>">
            <div class="contact-avatar"><?php echo strtoupper(substr($c['nom'], 0, 1)); ?></div>
            <div>
              <div class="contact-name"><?php echo htmlspecialchars($c['nom'].' '.$c['prenom']); ?></div>
              <div class="contact-email"><?php echo htmlspecialchars($c['email']); ?></div>
            </div>
          </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- CHAT AREA -->
    <div class="chat-area">
      <?php if($dest): ?>
        <div class="chat-header">
          <div class="avatar-lg"><?php echo strtoupper(substr($dest['nom'], 0, 1)); ?></div>
          <div>
            <h4><?php echo htmlspecialchars($dest['nom'].' '.$dest['prenom']); ?></h4>
            <p><?php echo htmlspecialchars($dest['email']); ?></p>
          </div>
        </div>
        <div class="chat-messages" id="chatMessages">
          <?php if(empty($messages_conv)): ?>
            <div style="color:var(--text-secondary);font-size:13px;text-align:center;margin-top:40px;">Aucun message. Commencez la conversation.</div>
          <?php else: ?>
            <?php foreach($messages_conv as $msg): ?>
              <?php $isSent = $msg['expediteur_type']==='organisation' && $msg['expediteur_id']==$org_id; ?>
              <div class="bubble <?php echo $isSent?'sent':'received'; ?>">
                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                <div class="bubble-time"><?php echo date('H:i', strtotime($msg['date_envoi'])); ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <form method="POST" class="chat-input">
          <input type="hidden" name="destinataire_id" value="<?php echo $dest_id; ?>">
          <input type="text" name="message" placeholder="Écrire un message..." autocomplete="off" required>
          <button type="submit" class="btn-send">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          </button>
        </form>
      <?php else: ?>
        <div class="no-conv">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" opacity="0.3"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          Sélectionnez une conversation
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
const chatMessages = document.getElementById('chatMessages');
if (chatMessages) chatMessages.scrollTop = chatMessages.scrollHeight;
document.addEventListener('click', function(e) {
  const area = document.getElementById('profileArea');
  if (area && !area.contains(e.target)) area.classList.remove('active');
});
</script>
</body>
</html>