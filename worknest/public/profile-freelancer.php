<?php
require_once "../src/init.php";
require_once "../src/config.php";

if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

// Get user ID - either from URL parameter (viewing someone else's profile) or session (own profile)
$profile_user_id = isset($_GET['id']) ? (int)$_GET['id'] : $_SESSION['user_id'];
$is_own_profile = ($profile_user_id === $_SESSION['user_id']);

// Fetch user profile data
$stmt = $pdo->prepare('SELECT u.id, u.email, u.role, u.created_at, u.last_login,
                              p.display_name, p.bio, p.location, p.phone, p.website,
                              p.hourly_rate, p.availability
                       FROM users u 
                       LEFT JOIN profiles p ON u.id = p.user_id 
                       WHERE u.id = ? LIMIT 1');
$stmt->execute([$profile_user_id]);
$profile_user = $stmt->fetch(PDO::FETCH_ASSOC);

// If user not found, show error
if (!$profile_user) {
    $profile_not_found = true;
} else {
    // Parse display name
    $display_name = $profile_user['display_name'] ?: explode('@', $profile_user['email'])[0];
    $name_parts = explode(' ', $display_name, 2);
    $first_name = $name_parts[0];
    $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
    
    // Create initials
    if ($first_name && $last_name) {
        $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
    } else {
        $initials = strtoupper(substr($display_name, 0, 2));
    }
    
    $user_role_display = ucfirst($profile_user['role']);
    $member_since = date('M Y', strtotime($profile_user['created_at']));
    
    // Get stats - TODO: Replace with actual database queries
    $completed_projects = 0; // Count from jobs/proposals tables
    $average_rating = 0.0; // Calculate from ratings table
    $total_earnings = 0; // Sum from transactions table
    $response_time = 'N/A';
    
    // Get user skills from user_skills and skills tables
    $skills_stmt = $pdo->prepare('SELECT s.name 
                                   FROM user_skills us 
                                   JOIN skills s ON us.skill_id = s.id 
                                   WHERE us.user_id = ?');
    $skills_stmt->execute([$profile_user_id]);
    $skills = $skills_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get reviews/ratings
    $reviews_stmt = $pdo->prepare('SELECT r.*, u.email as reviewer_email, p.display_name as reviewer_name
                                    FROM reviews r
                                    LEFT JOIN users u ON r.reviewer_id = u.id
                                    LEFT JOIN profiles p ON u.id = p.user_id
                                    WHERE r.reviewee_id = ?
                                    ORDER BY r.created_at DESC
                                    LIMIT 5');
    $reviews_stmt->execute([$profile_user_id]);
    $reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate average rating
    if (!empty($reviews)) {
        $total_rating = array_sum(array_column($reviews, 'rating'));
        $average_rating = round($total_rating / count($reviews), 1);
    }
    
    // Mock data for sections not yet in database
    $portfolio = [];
    $experience = [];
    $certifications = [];
    $languages = [
        ['name' => 'English', 'level' => 'Native']
    ];
}

// Get logged-in user info for sidebar
$logged_user_stmt = $pdo->prepare('SELECT u.email, u.role, p.display_name 
                                    FROM users u 
                                    LEFT JOIN profiles p ON u.id = p.user_id 
                                    WHERE u.id = ? LIMIT 1');
$logged_user_stmt->execute([$_SESSION['user_id']]);
$logged_user = $logged_user_stmt->fetch(PDO::FETCH_ASSOC);

$logged_display_name = $logged_user['display_name'] ?: explode('@', $logged_user['email'])[0];
$logged_name_parts = explode(' ', $logged_display_name, 2);
$logged_first_name = $logged_name_parts[0];
$logged_last_name = isset($logged_name_parts[1]) ? $logged_name_parts[1] : '';
$logged_initials = strtoupper(substr($logged_first_name, 0, 1) . ($logged_last_name ? substr($logged_last_name, 0, 1) : substr($logged_first_name, 1, 1)));
$logged_role = ucfirst($logged_user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile - WorkNest</title>

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

    .user-profile-mini {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px;
      border-radius: 10px;
      transition: 0.3s;
      cursor: pointer;
    }

    .user-profile-mini:hover {
      background-color: rgba(19,211,240,0.1);
    }

    .user-avatar-mini {
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

    .user-info-mini h4 {
      font-size: 0.95rem;
      margin-bottom: 2px;
    }

    .user-info-mini p {
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
      justify-content: flex-end;
      align-items: center;
      margin-bottom: 30px;
      gap: 15px;
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

    .btn-edit {
      background: linear-gradient(135deg, var(--accent), #0891b2);
      color: white;
      border: none;
      padding: 10px 25px;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
    }

    .btn-edit:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(19,211,240,0.3);
      color: white;
    }

    /* Profile Header */
    .profile-header {
      background: linear-gradient(135deg, var(--card), rgba(19,211,240,0.05));
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 40px;
      margin-bottom: 30px;
      animation: fadeInUp 0.6s ease;
      position: relative;
      overflow: hidden;
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

    .profile-header::before {
      content: "";
      position: absolute;
      top: -50%;
      right: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(19,211,240,0.1) 0%, transparent 70%);
      animation: floatGradient 8s ease-in-out infinite;
    }

    @keyframes floatGradient {
      0%, 100% { transform: translate(0, 0); }
      50% { transform: translate(-20px, -20px); }
    }

    .profile-content {
      display: flex;
      gap: 30px;
      align-items: flex-start;
      position: relative;
      z-index: 1;
    }

    .profile-avatar-large {
      width: 150px;
      height: 150px;
      border-radius: 20px;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 3rem;
      flex-shrink: 0;
      border: 4px solid var(--bg);
      box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }

    .profile-info {
      flex: 1;
    }

    .profile-name {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 10px;
    }

    .profile-title {
      font-size: 1.3rem;
      opacity: 0.8;
      margin-bottom: 20px;
    }

    .profile-meta {
      display: flex;
      gap: 30px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }

    .meta-item {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .meta-item i {
      color: var(--accent);
    }

    .profile-stats {
      display: flex;
      gap: 40px;
      padding-top: 20px;
      border-top: 1px solid var(--border);
    }

    .stat-item {
      text-align: center;
    }

    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      color: var(--accent);
      display: block;
      margin-bottom: 5px;
    }

    .stat-label {
      font-size: 0.9rem;
      opacity: 0.7;
    }

    /* Content Grid */
    .content-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 30px;
      margin-bottom: 30px;
    }

    .content-card {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 30px;
      animation: fadeInUp 0.8s ease;
    }

    [data-theme="light"] .content-card {
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .section-header {
      font-size: 1.3rem;
      font-weight: 700;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      position: relative;
    }

    .section-header i {
      color: var(--accent);
    }

    .btn-add-skill {
      margin-left: auto;
      width: 35px;
      height: 35px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      color: white;
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s;
      font-size: 0.9rem;
    }

    .btn-add-skill:hover {
      transform: scale(1.1);
      box-shadow: 0 5px 15px rgba(19,211,240,0.4);
    }

    /* Add Skill Modal */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.7);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 2000;
      animation: fadeIn 0.3s ease;
    }

    .modal-overlay.active {
      display: flex;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    .modal-content {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 35px;
      width: 90%;
      max-width: 600px;
      max-height: 80vh;
      overflow-y: auto;
      animation: slideUp 0.3s ease;
      position: relative;
    }

    [data-theme="light"] .modal-content {
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
      padding-bottom: 15px;
      border-bottom: 2px solid var(--border);
    }

    .modal-header h3 {
      font-size: 1.5rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .modal-header h3 i {
      color: var(--accent);
    }

    .modal-close {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      background: rgba(255,71,87,0.2);
      border: 1px solid #ff4757;
      color: #ff4757;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s;
      font-size: 1.2rem;
    }

    .modal-close:hover {
      background: rgba(255,71,87,0.3);
      transform: rotate(90deg);
    }

    .search-container {
      position: relative;
      margin-bottom: 25px;
    }

    .search-skills {
      width: 100%;
      padding: 12px 15px 12px 45px;
      border: 2px solid var(--border);
      border-radius: 10px;
      background-color: var(--bg);
      color: var(--text);
      font-size: 0.95rem;
      transition: all 0.3s;
    }

    .search-skills:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 10px rgba(19,211,240,0.2);
    }

    .search-icon {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--accent);
      pointer-events: none;
      z-index: 1;
    }

    .skills-categories {
      margin-bottom: 20px;
    }

    .category-title {
      font-size: 0.9rem;
      font-weight: 700;
      opacity: 0.7;
      margin: 15px 0 10px 0;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .skills-list {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 15px;
    }

    .skill-option {
      background: rgba(19,211,240,0.1);
      border: 2px solid rgba(19,211,240,0.3);
      color: var(--text);
      padding: 10px 15px;
      border-radius: 20px;
      font-size: 0.9rem;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .skill-option:hover {
      background: rgba(19,211,240,0.2);
      transform: translateY(-2px);
    }

    .skill-option.selected {
      background: linear-gradient(135deg, var(--accent), #0891b2);
      border-color: var(--accent);
      color: white;
    }

    .skill-option i {
      font-size: 0.8rem;
      opacity: 0;
      transition: opacity 0.3s;
    }

    .skill-option.selected i {
      opacity: 1;
    }

    .modal-footer {
      display: flex;
      gap: 15px;
      justify-content: flex-end;
      margin-top: 25px;
      padding-top: 20px;
      border-top: 1px solid var(--border);
    }

    .btn-modal {
      padding: 12px 30px;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      border: none;
      font-size: 0.95rem;
    }

    .btn-cancel {
      background: rgba(255,255,255,0.1);
      color: var(--text);
      border: 1px solid var(--border);
    }

    .btn-cancel:hover {
      background: rgba(255,255,255,0.15);
    }

    .btn-save {
      background: linear-gradient(135deg, var(--accent), #0891b2);
      color: white;
    }

    .btn-save:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(19,211,240,0.3);
    }

    /* About Section */
    .about-text {
      line-height: 1.8;
      opacity: 0.9;
    }

    /* Skills */
    .skills-grid {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .skill-tag {
      background: rgba(19,211,240,0.1);
      border: 1px solid rgba(19,211,240,0.3);
      color: var(--accent);
      padding: 10px 18px;
      border-radius: 25px;
      font-size: 0.95rem;
      font-weight: 600;
      transition: all 0.3s;
    }

    .skill-tag:hover {
      background: rgba(19,211,240,0.2);
      transform: translateY(-2px);
    }

    /* Reviews */
    .review-item {
      padding: 20px 0;
      border-bottom: 1px solid var(--border);
    }

    .review-item:last-child {
      border-bottom: none;
    }

    .review-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }

    .reviewer-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .reviewer-avatar {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
    }

    .reviewer-name {
      font-weight: 600;
    }

    .review-rating {
      color: #ffa500;
    }

    .review-text {
      opacity: 0.8;
      line-height: 1.6;
      margin-bottom: 10px;
    }

    .review-date {
      font-size: 0.85rem;
      opacity: 0.6;
    }

    /* Contact Info */
    .info-item {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 15px 0;
      border-bottom: 1px solid var(--border);
    }

    .info-item:last-child {
      border-bottom: none;
    }

    .info-icon {
      width: 45px;
      height: 45px;
      border-radius: 10px;
      background: rgba(19,211,240,0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--accent);
      font-size: 1.2rem;
    }

    .info-details {
      flex: 1;
    }

    .info-label {
      font-size: 0.85rem;
      opacity: 0.7;
      margin-bottom: 3px;
    }

    .info-value {
      font-weight: 600;
    }

    /* Languages */
    .language-item {
      display: flex;
      justify-content: space-between;
      padding: 12px 0;
      border-bottom: 1px solid var(--border);
    }

    .language-item:last-child {
      border-bottom: none;
    }

    .language-name {
      font-weight: 600;
    }

    .language-level {
      opacity: 0.7;
      font-size: 0.9rem;
    }

    .empty-state {
      text-align: center;
      padding: 40px 20px;
      opacity: 0.6;
    }

    .empty-state i {
      font-size: 3rem;
      margin-bottom: 15px;
      opacity: 0.3;
    }

    /* Responsive */
    @media (max-width: 1200px) {
      .content-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-100%);
      }

      .main-content {
        margin-left: 0;
      }

      .profile-content {
        flex-direction: column;
        align-items: center;
        text-align: center;
      }

      .profile-stats {
        justify-content: center;
        flex-wrap: wrap;
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
      <li><a href="browse-projects.php"><i class="fas fa-briefcase"></i> Browse Projects</a></li>
      <li><a href="my-projects.php"><i class="fas fa-folder"></i> My Projects</a></li>
      <li><a href="messages-freelancer.php"><i class="fas fa-comments"></i> Messages</a></li>
      <li><a href="earnings.php"><i class="fas fa-wallet"></i> Earnings</a></li>
      <li><a href="reviews-freelancer.php"><i class="fas fa-star"></i> Reviews</a></li>
      <li><a href="settings-freelancer.php"><i class="fas fa-cog"></i> Settings</a></li>
    </ul>

    <div class="sidebar-footer">
      <div class="user-profile-mini">
        <div class="user-avatar-mini"><?= htmlspecialchars($logged_initials) ?></div>
        <div class="user-info-mini">
          <h4><?= htmlspecialchars($logged_display_name) ?></h4>
          <p><?= htmlspecialchars($logged_role) ?></p>
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
      <?php if ($is_own_profile): ?>
      <a href="#" class="btn-edit" onclick="alert('Edit profile coming soon!'); return false;">
        <i class="fas fa-edit"></i> Edit Profile
      </a>
      <?php endif; ?>
      <div class="theme-toggle-btn" id="themeToggle">
        <i class="fas fa-sun"></i>
      </div>
    </div>

    <?php if (isset($profile_not_found)): ?>
      <!-- Profile Not Found -->
      <div style="text-align: center; padding: 100px 20px;">
        <i class="fas fa-user-slash" style="font-size: 5rem; opacity: 0.3; margin-bottom: 30px;"></i>
        <h2>Profile Not Found</h2>
        <p style="opacity: 0.7; margin-bottom: 30px;">This profile may have been removed or doesn't exist.</p>
        <a href="dashboard.php" class="btn-edit">
          <i class="fas fa-home"></i> Back to Dashboard
        </a>
      </div>
    <?php else: ?>

      <!-- Profile Header -->
      <div class="profile-header">
        <div class="profile-content">
          <div class="profile-avatar-large"><?= htmlspecialchars($initials) ?></div>
          
          <div class="profile-info">
            <h1 class="profile-name"><?= htmlspecialchars($display_name) ?></h1>
            <div class="profile-title"><?= htmlspecialchars($user_role_display) ?></div>
            
            <div class="profile-meta">
              <?php if ($profile_user['location']): ?>
              <div class="meta-item">
                <i class="fas fa-map-marker-alt"></i>
                <span><?= htmlspecialchars($profile_user['location']) ?></span>
              </div>
              <?php endif; ?>
              <div class="meta-item">
                <i class="fas fa-calendar"></i>
                <span>Member since <?= htmlspecialchars($member_since) ?></span>
              </div>
              <div class="meta-item">
                <i class="fas fa-check-circle" style="color: #2ed573;"></i>
                <span>Verified Profile</span>
              </div>
            </div>

            <div class="profile-stats">
              <div class="stat-item">
                <span class="stat-value"><?= $completed_projects ?></span>
                <span class="stat-label">Completed Projects</span>
              </div>
              <div class="stat-item">
                <span class="stat-value"><?= $average_rating > 0 ? number_format($average_rating, 1) : 'N/A' ?></span>
                <span class="stat-label">Average Rating</span>
              </div>
              <div class="stat-item">
                <span class="stat-value">$<?= number_format($total_earnings / 1000, 1) ?>K</span>
                <span class="stat-label">Total Earnings</span>
              </div>
              <div class="stat-item">
                <span class="stat-value"><?= htmlspecialchars($response_time) ?></span>
                <span class="stat-label">Response Time</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Grid -->
      <div class="content-grid">
        
        <!-- Left Column -->
        <div>
          <!-- About -->
          <div class="content-card">
            <div class="section-header">
              <i class="fas fa-user"></i>
              About Me
            </div>
            <?php if ($profile_user['bio']): ?>
              <div class="about-text"><?= nl2br(htmlspecialchars($profile_user['bio'])) ?></div>
            <?php else: ?>
              <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <p>No bio added yet</p>
              </div>
            <?php endif; ?>
          </div>

          <!-- Skills -->
          <div class="content-card">
            <div class="section-header">
              <i class="fas fa-code"></i>
              Skills & Expertise
              <?php if ($is_own_profile): ?>
                <button class="btn-add-skill" id="addSkillBtn" title="Add Skill">
                  <i class="fas fa-plus"></i>
                </button>
              <?php endif; ?>
            </div>
            <?php if (!empty($skills)): ?>
              <div class="skills-grid">
                <?php foreach ($skills as $skill): ?>
                  <span class="skill-tag"><?= htmlspecialchars($skill) ?></span>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <i class="fas fa-star"></i>
                <p>No skills added yet</p>
              </div>
            <?php endif; ?>
          </div>

          <!-- Reviews -->
          <div class="content-card">
            <div class="section-header">
              <i class="fas fa-star"></i>
              Client Reviews
            </div>
            <?php if (!empty($reviews)): ?>
              <?php foreach ($reviews as $review): 
                $reviewer_name = $review['reviewer_name'] ?: explode('@', $review['reviewer_email'])[0];
                $reviewer_initials = strtoupper(substr($reviewer_name, 0, 2));
                $review_date = date('M d, Y', strtotime($review['created_at']));
              ?>
                <div class="review-item">
                  <div class="review-header">
                    <div class="reviewer-info">
                      <div class="reviewer-avatar"><?= htmlspecialchars($reviewer_initials) ?></div>
                      <div>
                        <div class="reviewer-name"><?= htmlspecialchars($reviewer_name) ?></div>
                        <div class="review-rating">
                          <?php for ($i = 0; $i < $review['rating']; $i++): ?>
                            <i class="fas fa-star"></i>
                          <?php endfor; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                  <?php if ($review['comment']): ?>
                    <div class="review-text"><?= htmlspecialchars($review['comment']) ?></div>
                  <?php endif; ?>
                  <div class="review-date"><?= htmlspecialchars($review_date) ?></div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="empty-state">
                <i class="fas fa-comments"></i>
                <p>No reviews yet</p>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Right Column -->
        <div>
          <!-- Contact Info -->
          <div class="content-card">
            <div class="section-header">
              <i class="fas fa-address-card"></i>
              Contact Info
            </div>
            <div class="info-item">
              <div class="info-icon"><i class="fas fa-envelope"></i></div>
              <div class="info-details">
                <div class="info-label">Email</div>
                <div class="info-value"><?= htmlspecialchars($profile_user['email']) ?></div>
              </div>
            </div>
            <?php if ($profile_user['phone']): ?>
            <div class="info-item">
              <div class="info-icon"><i class="fas fa-phone"></i></div>
              <div class="info-details">
                <div class="info-label">Phone</div>
                <div class="info-value"><?= htmlspecialchars($profile_user['phone']) ?></div>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($profile_user['website']): ?>
            <div class="info-item">
              <div class="info-icon"><i class="fas fa-globe"></i></div>
              <div class="info-details">
                <div class="info-label">Website</div>
                <div class="info-value"><?= htmlspecialchars($profile_user['website']) ?></div>
              </div>
            </div>
            <?php endif; ?>
          </div>

          <!-- Languages -->
          <div class="content-card">
            <div class="section-header">
              <i class="fas fa-language"></i>
              Languages
            </div>
            <?php foreach ($languages as $lang): ?>
              <div class="language-item">
                <span class="language-name"><?= htmlspecialchars($lang['name']) ?></span>
                <span class="language-level"><?= htmlspecialchars($lang['level']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if (!empty($certifications)): ?>
          <!-- Certifications -->
          <div class="content-card">
            <div class="section-header">
              <i class="fas fa-certificate"></i>
              Certifications
            </div>
            <?php foreach ($certifications as $cert): ?>
              <div class="language-item">
                <span class="language-name"><?= htmlspecialchars($cert) ?></span>
                <span class="language-level"><i class="fas fa-check-circle" style="color: #2ed573;"></i></span>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

      </div>

    <?php endif; ?>

  </main>

  <!-- Add Skill Modal -->
  <div class="modal-overlay" id="skillModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fas fa-plus-circle"></i> Add Skills</h3>
        <button class="modal-close" id="closeModal">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="search-container">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="searchSkills" class="search-skills" placeholder="Search skills...">
      </div>

      <div class="skills-categories" id="skillsCategories">
        <!-- Skills will be loaded here dynamically -->
      </div>

      <div class="modal-footer">
        <button class="btn-modal btn-cancel" id="cancelBtn">Cancel</button>
        <button class="btn-modal btn-save" id="saveSkillsBtn">Save Skills</button>
      </div>
    </div>
  </div>

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

    // Add Skills Modal
    const addSkillBtn = document.getElementById('addSkillBtn');
    const skillModal = document.getElementById('skillModal');
    const closeModal = document.getElementById('closeModal');
    const cancelBtn = document.getElementById('cancelBtn');
    const saveSkillsBtn = document.getElementById('saveSkillsBtn');
    const searchSkills = document.getElementById('searchSkills');
    const skillsCategories = document.getElementById('skillsCategories');

    let selectedSkills = [];
    let allSkills = {};

    // Open modal
    if (addSkillBtn) {
      addSkillBtn.addEventListener('click', () => {
        skillModal.classList.add('active');
        loadAvailableSkills();
      });
    }

    // Close modal
    function closeSkillModal() {
      skillModal.classList.remove('active');
    }

    closeModal.addEventListener('click', closeSkillModal);
    cancelBtn.addEventListener('click', closeSkillModal);

    // Close on overlay click
    skillModal.addEventListener('click', (e) => {
      if (e.target === skillModal) {
        closeSkillModal();
      }
    });

    // Load available skills from server
    function loadAvailableSkills() {
      fetch('api/get_available_skills.php')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            allSkills = data.skills;
            renderSkills(allSkills);
          }
        })
        .catch(error => {
          console.error('Error loading skills:', error);
          // Show sample skills as fallback
          showSampleSkills();
        });
    }

    // Render skills by category
    function renderSkills(skillsByCategory) {
      skillsCategories.innerHTML = '';
      
      for (const [category, skills] of Object.entries(skillsByCategory)) {
        const categoryDiv = document.createElement('div');
        categoryDiv.innerHTML = `
          <div class="category-title">${category}</div>
          <div class="skills-list">
            ${skills.map(skill => `
              <div class="skill-option" data-skill-id="${skill.id}" data-skill-name="${skill.name}">
                <span>${skill.name}</span>
                <i class="fas fa-check"></i>
              </div>
            `).join('')}
          </div>
        `;
        skillsCategories.appendChild(categoryDiv);
      }

      // Add click handlers
      document.querySelectorAll('.skill-option').forEach(option => {
        option.addEventListener('click', () => {
          option.classList.toggle('selected');
          const skillId = option.dataset.skillId;
          const skillName = option.dataset.skillName;
          
          if (option.classList.contains('selected')) {
            if (!selectedSkills.find(s => s.id === skillId)) {
              selectedSkills.push({ id: skillId, name: skillName });
            }
          } else {
            selectedSkills = selectedSkills.filter(s => s.id !== skillId);
          }
        });
      });
    }

    // Search skills
    searchSkills.addEventListener('input', (e) => {
      const searchTerm = e.target.value.toLowerCase();
      
      if (searchTerm === '') {
        renderSkills(allSkills);
        return;
      }

      const filtered = {};
      for (const [category, skills] of Object.entries(allSkills)) {
        const matchedSkills = skills.filter(skill => 
          skill.name.toLowerCase().includes(searchTerm)
        );
        if (matchedSkills.length > 0) {
          filtered[category] = matchedSkills;
        }
      }
      renderSkills(filtered);
    });

    // Save selected skills
    saveSkillsBtn.addEventListener('click', () => {
      if (selectedSkills.length === 0) {
        alert('Please select at least one skill');
        return;
      }

      // Show loading
      saveSkillsBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
      saveSkillsBtn.disabled = true;

      fetch('api/add_user_skills.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ skills: selectedSkills })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Reload page to show new skills
          window.location.reload();
        } else {
          alert('Error saving skills: ' + data.message);
          saveSkillsBtn.innerHTML = 'Save Skills';
          saveSkillsBtn.disabled = false;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error saving skills');
        saveSkillsBtn.innerHTML = 'Save Skills';
        saveSkillsBtn.disabled = false;
      });
    });

    // Sample skills fallback
    function showSampleSkills() {
      allSkills = {
        'Programming': [
          { id: 1, name: 'PHP' },
          { id: 2, name: 'JavaScript' },
          { id: 3, name: 'Python' },
          { id: 4, name: 'Java' }
        ],
        'Web Development': [
          { id: 5, name: 'React' },
          { id: 6, name: 'Vue.js' },
          { id: 7, name: 'Laravel' },
          { id: 8, name: 'Node.js' }
        ],
        'Design': [
          { id: 9, name: 'Figma' },
          { id: 10, name: 'Photoshop' },
          { id: 11, name: 'UI/UX Design' }
        ]
      };
      renderSkills(allSkills);
    }
  </script>

</body>
</html>