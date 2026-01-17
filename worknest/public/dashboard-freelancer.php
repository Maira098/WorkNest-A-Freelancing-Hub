<?php
require_once "../src/init.php";
require_once "../src/config.php";

if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

// Fetch user data
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get user profile information
$stmt = $pdo->prepare('SELECT u.email, u.role, p.display_name 
                       FROM users u 
                       LEFT JOIN profiles p ON u.id = p.user_id 
                       WHERE u.id = ? LIMIT 1');
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Set user display information
$display_name = $user_data['display_name'] ?: explode('@', $user_data['email'])[0];
$name_parts = explode(' ', $display_name, 2);
$first_name = $name_parts[0];
$last_name = isset($name_parts[1]) ? $name_parts[1] : '';
$initials = strtoupper(substr($first_name, 0, 1) . ($last_name ? substr($last_name, 0, 1) : substr($first_name, 1, 1)));
$user_role_display = ucfirst($user_data['role']);

// Get freelancer stats
$my_proposals_stmt = $pdo->prepare('SELECT COUNT(*) as total FROM proposals WHERE freelancer_id = ?');
$my_proposals_stmt->execute([$user_id]);
$total_proposals = $my_proposals_stmt->fetch(PDO::FETCH_ASSOC)['total'];

$active_proposals_stmt = $pdo->prepare('SELECT COUNT(*) as total FROM proposals WHERE freelancer_id = ? AND status = "pending"');
$active_proposals_stmt->execute([$user_id]);
$active_proposals = $active_proposals_stmt->fetch(PDO::FETCH_ASSOC)['total'];

$accepted_proposals_stmt = $pdo->prepare('SELECT COUNT(*) as total FROM proposals WHERE freelancer_id = ? AND status = "accepted"');
$accepted_proposals_stmt->execute([$user_id]);
$accepted_projects = $accepted_proposals_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get available jobs/projects (for freelancers to browse)
$jobs_stmt = $pdo->prepare('SELECT j.*, u.email as client_email, p.display_name as client_name,
                                   COUNT(DISTINCT pr.id) as proposal_count
                            FROM jobs j
                            INNER JOIN users u ON j.client_id = u.id
                            LEFT JOIN profiles p ON u.id = p.user_id
                            LEFT JOIN proposals pr ON j.id = pr.job_id
                            WHERE j.status = "open"
                            GROUP BY j.id
                            ORDER BY j.created_at DESC
                            LIMIT 8');
$jobs_stmt->execute();
$available_jobs = $jobs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get freelancer's recent proposals
$recent_proposals_stmt = $pdo->prepare('SELECT p.*, j.title as job_title,
                                               u.email as client_email, prof.display_name as client_name
                                        FROM proposals p
                                        INNER JOIN jobs j ON p.job_id = j.id
                                        INNER JOIN users u ON j.client_id = u.id
                                        LEFT JOIN profiles prof ON u.id = prof.user_id
                                        WHERE p.freelancer_id = ?
                                        ORDER BY p.created_at DESC
                                        LIMIT 5');
$recent_proposals_stmt->execute([$user_id]);
$recent_proposals = $recent_proposals_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch notification count
try {
    $notif_stmt = $pdo->prepare('SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0');
    $notif_stmt->execute([$user_id]);
    $notification_count = $notif_stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $notification_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Freelancer Dashboard - WorkNest</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    /* Same theme variables */
    :root {
      --bg: #031121;
      --text: #ffffff;
      --card: #041d3a;
      --accent: #13d3f0;
      --sidebar: #020e1b;
      --border: rgba(255,255,255,0.1);
    }
    [data-theme="light"] {
      --bg: #f5f7fa;
      --text: #031121;
      --card: #ffffff;
      --accent: #1055c9;
      --sidebar: #ffffff;
      --border: rgba(0,0,0,0.1);
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      background-color: var(--bg);
      color: var(--text);
      font-family: 'Inter', sans-serif;
      transition: all 0.3s ease;
    }

    /* Sidebar */
    .sidebar {
      position: fixed;
      left: 0;
      top: 0;
      width: 280px;
      height: 100vh;
      background-color: var(--sidebar);
      border-right: 1px solid var(--border);
      padding: 30px 20px;
      overflow-y: auto;
      transition: all 0.3s;
      z-index: 1000;
    }
    .sidebar-brand {
      font-size: 1.8rem;
      font-weight: 700;
      color: var(--accent);
      margin-bottom: 40px;
      text-decoration: none;
      display: block;
    }
    .sidebar-menu { list-style: none; }
    .sidebar-menu li { margin-bottom: 10px; }
    .sidebar-menu a {
      display: flex;
      align-items: center;
      padding: 12px 15px;
      color: var(--text);
      text-decoration: none;
      border-radius: 10px;
      transition: all 0.3s;
      opacity: 0.7;
    }
    .sidebar-menu a:hover, .sidebar-menu a.active {
      background-color: rgba(19,211,240,0.1);
      opacity: 1;
      color: var(--accent);
    }
    .sidebar-menu a i { margin-right: 12px; width: 20px; font-size: 1.1rem; }
    .sidebar-footer {
      margin-top: 40px;
      padding-top: 20px;
      border-top: 1px solid var(--border);
    }
    .user-profile {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px;
      border-radius: 10px;
      transition: 0.3s;
      cursor: pointer;
      text-decoration: none;
      color: var(--text);
    }
    .user-profile:hover { background-color: rgba(19,211,240,0.1); }
    .user-avatar {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1.2rem;
    }
    .user-info h4 { font-size: 0.95rem; margin-bottom: 2px; }
    .user-info p { font-size: 0.8rem; opacity: 0.7; margin: 0; }
    .logout-btn {
      display: block;
      width: 100%;
      padding: 10px;
      margin-top: 10px;
      background: rgba(255,71,87,0.2);
      border: 1px solid #ff4757;
      color: #ff4757;
      border-radius: 8px;
      text-align: center;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s;
    }
    .logout-btn:hover {
      background: rgba(255,71,87,0.3);
      color: #ff4757;
    }

    /* Main Content */
    .main-content {
      margin-left: 280px;
      padding: 30px;
      min-height: 100vh;
    }
    .top-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 40px;
      flex-wrap: wrap;
      gap: 20px;
    }
    .welcome h1 { font-size: 2rem; font-weight: 700; margin-bottom: 5px; }
    .welcome p { opacity: 0.7; }
    .top-actions {
      display: flex;
      gap: 15px;
      align-items: center;
    }
    .theme-toggle-btn {
      width: 45px;
      height: 45px;
      border-radius: 10px;
      background: var(--card);
      border: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .theme-toggle-btn:hover {
      transform: scale(1.05);
      border-color: var(--accent);
    }
    .theme-toggle-btn i { font-size: 1.2rem; color: var(--accent); }

    /* Notification Button */
    .notification-btn {
      width: 45px;
      height: 45px;
      border-radius: 10px;
      background: var(--card);
      border: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      position: relative;
      transition: all 0.3s;
    }
    .notification-btn:hover {
      border-color: var(--accent);
      transform: scale(1.05);
    }
    .notification-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      background: #ff4757;
      color: white;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.7rem;
      font-weight: 700;
    }

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 25px;
      margin-bottom: 40px;
    }
    .stat-card {
      background: linear-gradient(135deg, var(--card), rgba(19,211,240,0.05));
      padding: 25px;
      border-radius: 15px;
      border: 1px solid var(--border);
      transition: all 0.3s;
      animation: fadeInUp 0.6s ease;
    }
    [data-theme="light"] .stat-card { box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 30px rgba(19,211,240,0.2);
    }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .stat-card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
    }
    .stat-icon {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
    }
    .stat-icon.blue {
      background: rgba(19,211,240,0.2);
      color: var(--accent);
    }
    .stat-icon.green {
      background: rgba(46,213,115,0.2);
      color: #2ed573;
    }
    .stat-icon.purple {
      background: rgba(123,97,255,0.2);
      color: #7b61ff;
    }
    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 5px;
    }
    .stat-label {
      opacity: 0.7;
      font-size: 0.9rem;
    }

    /* Section */
    .section {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 30px;
      margin-bottom: 30px;
      animation: fadeInUp 0.8s ease;
    }
    [data-theme="light"] .section { box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
    }
    .section-header h2 {
      font-size: 1.5rem;
      font-weight: 700;
    }
    .section-header a {
      color: var(--accent);
      text-decoration: none;
      font-size: 0.95rem;
      font-weight: 600;
      transition: 0.3s;
    }
    .section-header a:hover { text-decoration: underline; }

    /* Job Grid */
    .job-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 20px;
    }
    .job-card {
      background: rgba(255,255,255,0.02);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 20px;
      transition: all 0.3s;
      text-decoration: none;
      color: var(--text);
      display: block;
    }
    [data-theme="light"] .job-card { background: rgba(0,0,0,0.02); }
    .job-card:hover {
      transform: translateY(-5px);
      border-color: var(--accent);
      box-shadow: 0 10px 25px rgba(19,211,240,0.2);
      color: var(--text);
    }
    .job-title {
      font-weight: 700;
      font-size: 1.1rem;
      margin-bottom: 10px;
    }
    .job-client {
      font-size: 0.9rem;
      opacity: 0.7;
      margin-bottom: 15px;
    }
    .job-description {
      font-size: 0.9rem;
      opacity: 0.8;
      margin-bottom: 15px;
      line-height: 1.5;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .job-meta {
      display: flex;
      justify-content: space-between;
      font-size: 0.85rem;
      padding-top: 15px;
      border-top: 1px solid var(--border);
    }
    .job-budget {
      color: var(--accent);
      font-weight: 700;
      font-size: 1.1rem;
    }
    .job-proposals {
      opacity: 0.7;
    }

    /* Proposal List */
    .proposal-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px;
      background: rgba(255,255,255,0.02);
      border: 1px solid var(--border);
      border-radius: 10px;
      margin-bottom: 12px;
      transition: all 0.3s;
    }
    [data-theme="light"] .proposal-item { background: rgba(0,0,0,0.02); }
    .proposal-item:hover {
      transform: translateX(5px);
      border-color: var(--accent);
    }
    .proposal-info {
      flex: 1;
    }
    .proposal-title {
      font-weight: 600;
      margin-bottom: 5px;
    }
    .proposal-meta {
      font-size: 0.85rem;
      opacity: 0.7;
    }
    .proposal-status {
      padding: 5px 15px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
    }
    .proposal-status.pending {
      background: rgba(255,193,7,0.2);
      color: #ffc107;
    }
    .proposal-status.accepted {
      background: rgba(46,213,115,0.2);
      color: #2ed573;
    }
    .proposal-status.rejected {
      background: rgba(255,71,87,0.2);
      color: #ff4757;
    }

    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .main-content { margin-left: 0; }
      .job-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <aside class="sidebar">
    <a href="dashboard.php" class="sidebar-brand">WorkNest</a>
    <ul class="sidebar-menu">
      <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="browse.php"><i class="fas fa-briefcase"></i> Browse Projects</a></li>
      <li><a href="my-projects.php"><i class="fas fa-folder"></i> My Projects</a></li>
      <li><a href="messages-freelancer.php"><i class="fas fa-comments"></i> Messages</a></li>
      <li><a href="earnings.php"><i class="fas fa-wallet"></i> Earnings</a></li>
      <li><a href="reviews-freelancer.php"><i class="fas fa-star"></i> Reviews</a></li>
      <li><a href="settings-freelancer.php"><i class="fas fa-cog"></i> Settings</a></li>
    </ul>
    <div class="sidebar-footer">
      <a href="profile-freelancer.php" class="user-profile">
        <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
        <div class="user-info">
          <h4><?= htmlspecialchars($display_name) ?></h4>
          <p><?= htmlspecialchars($user_role_display) ?></p>
        </div>
      </a>
      <a href="actions/logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
      <div class="welcome">
        <h1>Welcome back, <?= htmlspecialchars($first_name) ?>! 👋</h1>
        <p>Find your next great project</p>
      </div>
      <div class="top-actions">
        <div class="notification-btn" id="notificationBtn">
          <i class="fas fa-bell" style="color: var(--accent);"></i>
          <?php if ($notification_count > 0): ?>
            <span class="notification-badge"><?= $notification_count ?></span>
          <?php endif; ?>
        </div>
        <div class="theme-toggle-btn" id="themeToggle">
          <i class="fas fa-sun"></i>
        </div>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-card-header">
          <div class="stat-icon blue">
            <i class="fas fa-file-alt"></i>
          </div>
        </div>
        <div class="stat-value"><?= $total_proposals ?></div>
        <div class="stat-label">Total Proposals</div>
      </div>

      <div class="stat-card">
        <div class="stat-card-header">
          <div class="stat-icon purple">
            <i class="fas fa-clock"></i>
          </div>
        </div>
        <div class="stat-value"><?= $active_proposals ?></div>
        <div class="stat-label">Pending Proposals</div>
      </div>

      <div class="stat-card">
        <div class="stat-card-header">
          <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
          </div>
        </div>
        <div class="stat-value"><?= $accepted_projects ?></div>
        <div class="stat-label">Active Projects</div>
      </div>
    </div>

    <!-- Available Jobs -->
    <div class="section">
      <div class="section-header">
        <h2><i class="fas fa-briefcase"></i> Available Projects</h2>
        <a href="browse.php">View All <i class="fas fa-arrow-right"></i></a>
      </div>
      
      <?php if (!empty($available_jobs)): ?>
        <div class="job-grid">
          <?php foreach ($available_jobs as $job): 
            $client_name = $job['client_name'] ?: explode('@', $job['client_email'])[0];
            $job_budget = isset($job['budget']) ? number_format($job['budget']) : '0';
          ?>
            <a href="job-details.php?id=<?= $job['id'] ?>" class="job-card">
              <div class="job-title"><?= htmlspecialchars($job['title']) ?></div>
              <div class="job-client">By: <?= htmlspecialchars($client_name) ?></div>
              <div class="job-description"><?= htmlspecialchars($job['description']) ?></div>
              <div class="job-meta">
                <span class="job-budget">$<?= $job_budget ?></span>
                <span class="job-proposals"><?= $job['proposal_count'] ?> proposals</span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p style="text-align: center; opacity: 0.6; padding: 40px;">No projects available yet</p>
      <?php endif; ?>
    </div>

    <!-- My Recent Proposals -->
    <?php if (!empty($recent_proposals)): ?>
    <div class="section">
      <div class="section-header">
        <h2><i class="fas fa-paper-plane"></i> My Recent Proposals</h2>
        <a href="my-projects.php">View All <i class="fas fa-arrow-right"></i></a>
      </div>
      
      <?php foreach ($recent_proposals as $proposal): 
        $client_name = $proposal['client_name'] ?: explode('@', $proposal['client_email'])[0];
      ?>
        <div class="proposal-item">
          <div class="proposal-info">
            <div class="proposal-title"><?= htmlspecialchars($proposal['job_title']) ?></div>
            <div class="proposal-meta">
              Client: <?= htmlspecialchars($client_name) ?> • 
              Amount: $<?= number_format($proposal['amount']) ?> • 
              <?= date('M d, Y', strtotime($proposal['created_at'])) ?>
            </div>
          </div>
          <span class="proposal-status <?= $proposal['status'] ?>"><?= ucfirst($proposal['status']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </main>

  <script>
    // Theme Toggle
    const toggle = document.getElementById("themeToggle");
    const icon = toggle.querySelector("i");
    const body = document.body;

    const savedTheme = localStorage.getItem("theme") || "dark";
    body.setAttribute("data-theme", savedTheme);
    icon.className = savedTheme === "light" ? "fas fa-moon" : "fas fa-sun";

    toggle.addEventListener("click", () => {
      const current = body.getAttribute("data-theme");
      const next = current === "dark" ? "light" : "dark";
      body.setAttribute("data-theme", next);
      icon.className = next === "light" ? "fas fa-moon" : "fas fa-sun";
      localStorage.setItem("theme", next);
    });

    // Notification Button
    const notifBtn = document.getElementById('notificationBtn');
    notifBtn.addEventListener('click', () => {
      // TODO: Open notification panel
      alert('Notification panel - coming soon!');
    });
  </script>
</body>
</html>