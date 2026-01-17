<?php
require_once "../src/init.php";
require_once "../src/config.php";

if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

// Get project ID from URL
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$project_id) {
    header("Location: browse-projects.php");
    exit;
}

// Fetch project details
$stmt = $pdo->prepare('SELECT j.*, u.email as client_email, p.display_name as client_name,
                              (SELECT COUNT(*) FROM proposals WHERE job_id = j.id) as proposal_count
                       FROM jobs j
                       LEFT JOIN users u ON j.client_id = u.id
                       LEFT JOIN profiles p ON u.id = p.user_id
                       WHERE j.id = ? LIMIT 1');
$stmt->execute([$project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

// If project not found
if (!$project) {
    $project_not_found = true;
} else {
    // Format project data
    $client_name = $project['client_name'] ?: explode('@', $project['client_email'])[0];
    $client_initials = strtoupper(substr($client_name, 0, 2));
    
    // Format category
    $category_display = ucwords(str_replace(['_', '-'], ' ', $project['category']));
    
    // Calculate days since posted
    $posted_timestamp = strtotime($project['created_at']);
    $days_ago = floor((time() - $posted_timestamp) / 86400);
    if ($days_ago == 0) {
        $posted_text = 'Today';
    } elseif ($days_ago == 1) {
        $posted_text = 'Yesterday';
    } elseif ($days_ago < 7) {
        $posted_text = $days_ago . ' days ago';
    } else {
        $weeks_ago = floor($days_ago / 7);
        $posted_text = $weeks_ago . ' week' . ($weeks_ago > 1 ? 's' : '') . ' ago';
    }
    
    // Mock skills (TODO: Fetch from database if you have a job_skills table)
    $skills = [];
    if (stripos($project['category'], 'web') !== false || stripos($project['category'], 'development') !== false) {
        $skills = ['HTML', 'CSS', 'JavaScript', 'PHP', 'MySQL'];
    } elseif (stripos($project['category'], 'design') !== false) {
        $skills = ['Figma', 'Photoshop', 'UI/UX', 'Illustrator', 'InDesign'];
    } else {
        $skills = ['Communication', 'Time Management', 'Problem Solving'];
    }
    
    // Mock client stats (TODO: Fetch from database)
    $client_projects_posted = 1; // Count from jobs table
    $client_hire_rate = 100;
}

// Get logged-in user info for sidebar
$user_stmt = $pdo->prepare('SELECT u.email, u.role, p.display_name 
                            FROM users u 
                            LEFT JOIN profiles p ON u.id = p.user_id 
                            WHERE u.id = ? LIMIT 1');
$user_stmt->execute([$_SESSION['user_id']]);
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

$display_name = $user_data['display_name'] ?: explode('@', $user_data['email'])[0];
$name_parts = explode(' ', $display_name, 2);
$first_name = $name_parts[0];
$last_name = isset($name_parts[1]) ? $name_parts[1] : '';
$initials = strtoupper(substr($first_name, 0, 1) . ($last_name ? substr($last_name, 0, 1) : substr($first_name, 1, 1)));
$user_role_display = ucfirst($user_data['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Project Details - WorkNest</title>

  <!-- Libraries -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

  <style>
    /* ===== THEME VARIABLES ===== */
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

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      background-color: var(--bg);
      color: var(--text);
      font-family: 'Inter', sans-serif;
      transition: all 0.3s ease;
    }

    /* ===== SIDEBAR ===== */
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

    .sidebar-menu {
      list-style: none;
    }

    .sidebar-menu li {
      margin-bottom: 10px;
    }

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

    .sidebar-menu a:hover,
    .sidebar-menu a.active {
      background-color: rgba(19,211,240,0.1);
      opacity: 1;
      color: var(--accent);
    }

    .sidebar-menu a i {
      margin-right: 12px;
      width: 20px;
      font-size: 1.1rem;
    }

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
    }

    .user-profile:hover {
      background-color: rgba(19,211,240,0.1);
    }

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

    .user-info h4 {
      font-size: 0.95rem;
      margin-bottom: 2px;
    }

    .user-info p {
      font-size: 0.8rem;
      opacity: 0.7;
      margin: 0;
    }

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

    /* ===== MAIN CONTENT ===== */
    .main-content {
      margin-left: 280px;
      padding: 30px;
      min-height: 100vh;
    }

    /* Top Bar */
    .top-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      flex-wrap: wrap;
      gap: 20px;
    }

    .back-button {
      display: flex;
      align-items: center;
      gap: 10px;
      color: var(--text);
      text-decoration: none;
      font-weight: 600;
      transition: 0.3s;
      opacity: 0.8;
    }

    .back-button:hover {
      color: var(--accent);
      opacity: 1;
    }

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

    .theme-toggle-btn i {
      font-size: 1.2rem;
      color: var(--accent);
    }

    .action-btn {
      padding: 10px 15px;
      border-radius: 10px;
      border: 1px solid var(--border);
      background: var(--card);
      color: var(--text);
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .action-btn:hover {
      border-color: var(--accent);
      color: var(--accent);
      transform: translateY(-2px);
    }

    /* Content Layout */
    .content-layout {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 30px;
      animation: fadeInUp 0.6s ease;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Main Section */
    .main-section {
      display: flex;
      flex-direction: column;
      gap: 25px;
    }

    .content-card {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 30px;
    }

    [data-theme="light"] .content-card {
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    /* Project Header */
    .project-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 20px;
    }

    .project-category {
      background: rgba(19,211,240,0.2);
      color: var(--accent);
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 0.9rem;
      font-weight: 600;
    }

    .bookmark-btn {
      width: 45px;
      height: 45px;
      border-radius: 10px;
      border: 1px solid var(--border);
      background: transparent;
      color: var(--text);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: 0.3s;
      font-size: 1.2rem;
    }

    .bookmark-btn:hover {
      background: rgba(19,211,240,0.1);
      border-color: var(--accent);
      color: var(--accent);
    }

    .project-title {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 15px;
      line-height: 1.3;
    }

    .project-meta {
      display: flex;
      gap: 25px;
      flex-wrap: wrap;
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 1px solid var(--border);
    }

    .meta-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.95rem;
      opacity: 0.8;
    }

    .meta-item i {
      color: var(--accent);
    }

    /* Section Headers */
    .section-header {
      font-size: 1.3rem;
      font-weight: 700;
      margin-bottom: 15px;
      color: var(--text);
    }

    .section-content {
      line-height: 1.8;
      opacity: 0.9;
      white-space: pre-wrap;
    }

    /* Skills Tags */
    .skills-grid {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .skill-tag {
      background: rgba(19,211,240,0.1);
      border: 1px solid rgba(19,211,240,0.3);
      color: var(--accent);
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 0.9rem;
      font-weight: 600;
    }

    /* Sidebar Section */
    .sidebar-section {
      display: flex;
      flex-direction: column;
      gap: 25px;
    }

    .sticky-card {
      position: sticky;
      top: 30px;
    }

    /* Budget Card */
    .budget-card {
      text-align: center;
      padding: 30px;
    }

    .budget-label {
      font-size: 0.9rem;
      opacity: 0.7;
      margin-bottom: 10px;
    }

    .budget-amount {
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--accent);
      margin-bottom: 20px;
    }

    .btn-apply {
      width: 100%;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      color: white;
      border: none;
      padding: 15px;
      border-radius: 10px;
      font-weight: 600;
      font-size: 1.1rem;
      cursor: pointer;
      transition: all 0.3s;
      margin-bottom: 15px;
      text-decoration: none;
      display: block;
      text-align: center;
    }

    .btn-apply:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(19,211,240,0.4);
      color: white;
    }

    .btn-save {
      width: 100%;
      background: transparent;
      color: var(--text);
      border: 2px solid var(--border);
      padding: 12px;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
    }

    .btn-save:hover {
      border-color: var(--accent);
      color: var(--accent);
    }

    /* Info Items */
    .info-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 0;
      border-bottom: 1px solid var(--border);
    }

    .info-item:last-child {
      border-bottom: none;
    }

    .info-label {
      opacity: 0.7;
      font-size: 0.9rem;
    }

    .info-value {
      font-weight: 600;
    }

    /* Client Card */
    .client-info {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 20px;
    }

    .client-avatar {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1.5rem;
    }

    .client-details h3 {
      font-size: 1.2rem;
      margin-bottom: 5px;
    }

    .client-rating {
      color: #ffa500;
      font-size: 0.9rem;
    }

    .client-stats {
      display: flex;
      gap: 20px;
      padding-top: 15px;
      border-top: 1px solid var(--border);
    }

    .stat {
      flex: 1;
      text-align: center;
    }

    .stat-value {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--accent);
      display: block;
      margin-bottom: 5px;
    }

    .stat-label {
      font-size: 0.8rem;
      opacity: 0.7;
    }

    .btn-contact {
      width: 100%;
      background: transparent;
      color: var(--accent);
      border: 2px solid var(--accent);
      padding: 12px;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      margin-top: 15px;
      text-decoration: none;
      display: block;
      text-align: center;
    }

    .btn-contact:hover {
      background: var(--accent);
      color: white;
    }

    /* Proposals */
    .proposals-badge {
      background: rgba(255,159,67,0.2);
      color: #ff9f43;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
    }

    /* Responsive */
    @media (max-width: 1024px) {
      .content-layout {
        grid-template-columns: 1fr;
      }

      .sticky-card {
        position: relative;
        top: 0;
      }
    }

    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-100%);
      }

      .main-content {
        margin-left: 0;
      }
    }
  </style>
</head>

<body>

  <!-- Sidebar -->
  <aside class="sidebar">
    <a href="dashboard.php" class="sidebar-brand">WorkNest</a>

    <ul class="sidebar-menu">
      <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="browse-projects.php" class="active"><i class="fas fa-briefcase"></i> Browse Projects</a></li>
      <li><a href="my-projects.php"><i class="fas fa-folder"></i> My Projects</a></li>
      <li><a href="messages-freelancer.php"><i class="fas fa-comments"></i> Messages</a></li>
      <li><a href="earnings.php"><i class="fas fa-wallet"></i> Earnings</a></li>
      <li><a href="reviews-freelancer.php"><i class="fas fa-star"></i> Reviews</a></li>
      <li><a href="settings-freelancer.php"><i class="fas fa-cog"></i> Settings</a></li>
    </ul>

    <div class="sidebar-footer">
      <div class="user-profile">
        <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
        <div class="user-info">
          <h4><?= htmlspecialchars($display_name) ?></h4>
          <p><?= htmlspecialchars($user_role_display) ?></p>
        </div>
      </div>
      <a href="actions/logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
      <a href="browse-projects.php" class="back-button">
        <i class="fas fa-arrow-left"></i>
        Back to Browse
      </a>

      <div class="top-actions">
        <button class="action-btn" onclick="shareProject()">
          <i class="fas fa-share-alt"></i>
          Share
        </button>

        <button class="action-btn" onclick="alert('Report feature coming soon!')">
          <i class="fas fa-flag"></i>
          Report
        </button>

        <div class="theme-toggle-btn" id="themeToggle">
          <i class="fas fa-sun"></i>
        </div>
      </div>
    </div>

    <?php if (isset($project_not_found)): ?>
      <!-- Project Not Found -->
      <div style="text-align: center; padding: 100px 20px;">
        <i class="fas fa-exclamation-circle" style="font-size: 5rem; opacity: 0.3; margin-bottom: 30px;"></i>
        <h2>Project Not Found</h2>
        <p style="opacity: 0.7; margin-bottom: 30px;">This project may have been removed or doesn't exist.</p>
        <a href="browse-projects.php" class="btn-apply" style="max-width: 250px; margin: 0 auto;">
          <i class="fas fa-arrow-left"></i> Back to Browse
        </a>
      </div>
    <?php else: ?>

    <!-- Content Layout -->
    <div class="content-layout">
      
      <!-- Main Section -->
      <div class="main-section">
        
        <!-- Project Overview -->
        <div class="content-card">
          <div class="project-header">
            <span class="project-category"><?= htmlspecialchars($category_display) ?></span>
            <button class="bookmark-btn" onclick="alert('Bookmark feature coming soon!')">
              <i class="far fa-bookmark"></i>
            </button>
          </div>

          <h1 class="project-title"><?= htmlspecialchars($project['title']) ?></h1>

          <div class="project-meta">
            <div class="meta-item">
              <i class="fas fa-clock"></i>
              <span>Posted <?= htmlspecialchars($posted_text) ?></span>
            </div>
            <div class="meta-item">
              <i class="fas fa-map-marker-alt"></i>
              <span>Remote</span>
            </div>
            <div class="meta-item">
              <span class="proposals-badge"><?= (int)$project['proposal_count'] ?> proposals</span>
            </div>
          </div>

          <div class="section-header">Project Description</div>
          <div class="section-content"><?= nl2br(htmlspecialchars($project['description'])) ?></div>
        </div>

        <!-- Skills Required -->
        <div class="content-card">
          <div class="section-header">Skills Required</div>
          <div class="skills-grid">
            <?php foreach ($skills as $skill): ?>
              <span class="skill-tag"><?= htmlspecialchars($skill) ?></span>
            <?php endforeach; ?>
          </div>
        </div>

      </div>

      <!-- Sidebar Section -->
      <div class="sidebar-section">
        
        <!-- Budget & Apply -->
        <div class="content-card sticky-card budget-card">
          <div class="budget-label">Project Budget</div>
          <div class="budget-amount">$<?= number_format($project['budget_min']) ?> - $<?= number_format($project['budget_max']) ?></div>
          
          <a href="#" class="btn-apply" onclick="alert('Apply feature coming soon! Project ID: <?= $project['id'] ?>'); return false;">
            <i class="fas fa-paper-plane"></i> Submit Proposal
          </a>
          <button class="btn-save" onclick="alert('Save feature coming soon!')">
            <i class="far fa-bookmark"></i> Save for Later
          </button>
        </div>

        <!-- Project Details -->
        <div class="content-card">
          <div class="section-header">Project Details</div>
          
          <div class="info-item">
            <span class="info-label">Status</span>
            <span class="info-value"><?= ucfirst(htmlspecialchars($project['status'])) ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Category</span>
            <span class="info-value"><?= htmlspecialchars($category_display) ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Project ID</span>
            <span class="info-value">#<?= $project['id'] ?></span>
          </div>
        </div>

        <!-- Client Info -->
        <div class="content-card">
          <div class="section-header">About the Client</div>
          
          <div class="client-info">
            <div class="client-avatar"><?= htmlspecialchars($client_initials) ?></div>
            <div class="client-details">
              <h3><?= htmlspecialchars($client_name) ?></h3>
              <div class="client-rating">
                <i class="fas fa-star"></i>
                <span>New Client</span>
              </div>
            </div>
          </div>

          <div class="client-stats">
            <div class="stat">
              <span class="stat-value"><?= $client_projects_posted ?></span>
              <span class="stat-label">Projects Posted</span>
            </div>
            <div class="stat">
              <span class="stat-value"><?= $client_hire_rate ?>%</span>
              <span class="stat-label">Hire Rate</span>
            </div>
          </div>

          <a href="profile.php?id=<?= $project['client_id'] ?>" class="btn-contact">
            <i class="fas fa-user"></i> View Profile
          </a>
        </div>

      </div>

    </div>

    <?php endif; ?>

  </main>

  <script>
    // Share Project
    function shareProject() {
      if (navigator.share) {
        navigator.share({
          title: '<?= addslashes($project['title'] ?? 'Project') ?>',
          url: window.location.href
        });
      } else {
        navigator.clipboard.writeText(window.location.href);
        alert('Project link copied to clipboard!');
      }
    }

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
  </script>

</body>
</html>