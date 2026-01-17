<?php
require_once "../src/init.php";
require_once "../src/config.php";

if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get job ID from URL
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$job_id) {
    header("Location: browse-projects.php");
    exit;
}

// Fetch job details
$stmt = $pdo->prepare('SELECT j.*, u.email as client_email, p.display_name as client_name,
                              (SELECT COUNT(*) FROM proposals WHERE job_id = j.id) as proposal_count
                       FROM jobs j
                       LEFT JOIN users u ON j.client_id = u.id
                       LEFT JOIN profiles p ON u.id = p.user_id
                       WHERE j.id = ? LIMIT 1');
$stmt->execute([$job_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

// If job not found
if (!$job) {
    $job_not_found = true;
} else {
    // Format job data
    $client_name = $job['client_name'] ?: explode('@', $job['client_email'])[0];
    $client_initials = strtoupper(substr($client_name, 0, 2));
    
    // Format category
    $category_display = ucwords(str_replace(['_', '-'], ' ', $job['category']));
    
    // Calculate time since posted
    $posted_timestamp = strtotime($job['created_at']);
    $time_diff = time() - $posted_timestamp;
    
    if ($time_diff < 3600) {
        $posted_text = floor($time_diff / 60) . ' minutes ago';
    } elseif ($time_diff < 86400) {
        $posted_text = floor($time_diff / 3600) . ' hours ago';
    } else {
        $days = floor($time_diff / 86400);
        $posted_text = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
    
    // Mock skills - TODO: Get from database
    $skills = [];
    if (stripos($job['category'], 'web') !== false || stripos($job['category'], 'development') !== false) {
        $skills = ['React.js', 'Node.js', 'Express.js', 'MongoDB', 'REST API', 'Payment Integration', 'Git'];
    } elseif (stripos($job['category'], 'design') !== false) {
        $skills = ['Figma', 'Photoshop', 'UI/UX', 'Illustrator', 'InDesign'];
    } else {
        $skills = ['Communication', 'Time Management', 'Problem Solving'];
    }
    
    // Mock requirements
    $requirements = [
        '3+ years of experience in the relevant field',
        'Strong portfolio demonstrating previous work',
        'Excellent communication skills',
        'Ability to work independently and meet deadlines',
        'Available to start immediately'
    ];
    
    // Get similar jobs
    $similar_stmt = $pdo->prepare('SELECT j.*, 
                                          (SELECT COUNT(*) FROM proposals WHERE job_id = j.id) as proposal_count
                                   FROM jobs j
                                   WHERE j.category = ? 
                                   AND j.id != ? 
                                   AND j.status = "open"
                                   ORDER BY j.created_at DESC
                                   LIMIT 3');
    $similar_stmt->execute([$job['category'], $job_id]);
    $similar_jobs = $similar_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if user already applied
    $applied_stmt = $pdo->prepare('SELECT id FROM proposals WHERE job_id = ? AND freelancer_id = ?');
    $applied_stmt->execute([$job_id, $user_id]);
    $already_applied = $applied_stmt->fetch() ? true : false;
}

// Get logged-in user info
$user_stmt = $pdo->prepare('SELECT u.email, u.role, p.display_name 
                            FROM users u 
                            LEFT JOIN profiles p ON u.id = p.user_id 
                            WHERE u.id = ? LIMIT 1');
$user_stmt->execute([$user_id]);
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);

$display_name = $user_data['display_name'] ?: explode('@', $user_data['email'])[0];
$name_parts = explode(' ', $display_name, 2);
$first_name = $name_parts[0];
$last_name = isset($name_parts[1]) ? $name_parts[1] : '';
$initials = strtoupper(substr($first_name, 0, 1) . ($last_name ? substr($last_name, 0, 1) : substr($first_name, 1, 1)));
$user_role_display = ucfirst($user_data['role']);

// Handle proposal submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_proposal'])) {
    $bid_amount = isset($_POST['bid_amount']) ? (float)$_POST['bid_amount'] : 0;
    $delivery_time = isset($_POST['delivery_time']) ? trim($_POST['delivery_time']) : '';
    $cover_letter = isset($_POST['cover_letter']) ? trim($_POST['cover_letter']) : '';
    
    $errors = [];
    
    if ($bid_amount <= 0) {
        $errors[] = 'Please enter a valid bid amount';
    }
    if (empty($delivery_time)) {
        $errors[] = 'Please specify delivery time';
    }
    if (strlen($cover_letter) < 100) {
        $errors[] = 'Cover letter must be at least 100 characters long';
    }
    
    if (empty($errors)) {
        try {
            $insert_stmt = $pdo->prepare('INSERT INTO proposals (job_id, freelancer_id, bid_amount, delivery_time, cover_letter, created_at)
                                          VALUES (?, ?, ?, ?, ?, NOW())');
            $insert_stmt->execute([$job_id, $user_id, $bid_amount, $delivery_time, $cover_letter]);
            
            $_SESSION['flash_success'] = 'Proposal submitted successfully!';
            header("Location: job-details.php?id=$job_id");
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Failed to submit proposal. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Details - WorkNest</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
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
    .main-content {
      margin-left: 280px;
      padding: 30px;
      min-height: 100vh;
    }
    .top-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      flex-wrap: wrap;
      gap: 20px;
    }
    .breadcrumb-nav {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 0.9rem;
      opacity: 0.7;
    }
    .breadcrumb-nav a {
      color: var(--text);
      text-decoration: none;
      transition: 0.3s;
    }
    .breadcrumb-nav a:hover { color: var(--accent); }
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
    .content-wrapper {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 30px;
    }
    .job-card {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 35px;
      animation: fadeInUp 0.6s ease;
    }
    [data-theme="light"] .job-card { box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .job-header {
      display: flex;
      justify-content: space-between;
      align-items: start;
      margin-bottom: 25px;
      gap: 20px;
    }
    .job-title-section h1 {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 15px;
      line-height: 1.3;
    }
    .job-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      font-size: 0.9rem;
      opacity: 0.8;
    }
    .job-meta-item {
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .job-meta-item i { color: var(--accent); }
    .job-status {
      padding: 8px 20px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      background: rgba(46,213,115,0.2);
      color: #2ed573;
      border: 1px solid rgba(46,213,115,0.3);
    }
    .section-title {
      font-size: 1.3rem;
      font-weight: 700;
      margin: 30px 0 20px 0;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .section-title i { color: var(--accent); }
    .job-description {
      line-height: 1.8;
      opacity: 0.9;
      margin-bottom: 20px;
      white-space: pre-wrap;
    }
    .skills-container {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 20px;
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
    .requirements-list {
      list-style: none;
      padding: 0;
    }
    .requirements-list li {
      padding: 12px 0;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: start;
      gap: 12px;
    }
    .requirements-list li:last-child { border-bottom: none; }
    .requirements-list i {
      color: var(--accent);
      margin-top: 3px;
      flex-shrink: 0;
    }
    .info-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
      margin: 25px 0;
    }
    .info-item {
      padding: 20px;
      background: rgba(255,255,255,0.03);
      border: 1px solid var(--border);
      border-radius: 10px;
    }
    [data-theme="light"] .info-item { background: rgba(0,0,0,0.02); }
    .info-label {
      font-size: 0.85rem;
      opacity: 0.7;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .info-value {
      font-size: 1.1rem;
      font-weight: 600;
    }
    .sidebar-card {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 25px;
      animation: fadeInUp 0.8s ease;
      position: sticky;
      top: 30px;
    }
    [data-theme="light"] .sidebar-card { box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .budget-display {
      text-align: center;
      padding: 25px;
      background: linear-gradient(135deg, rgba(19,211,240,0.1), rgba(8,145,178,0.1));
      border-radius: 10px;
      margin-bottom: 25px;
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
      margin-bottom: 5px;
    }
    .budget-type {
      font-size: 0.85rem;
      opacity: 0.7;
    }
    .btn-apply {
      width: 100%;
      background: linear-gradient(135deg, var(--accent), #0891b2);
      color: white;
      border: none;
      padding: 15px;
      border-radius: 10px;
      font-weight: 600;
      font-size: 1.05rem;
      cursor: pointer;
      transition: all 0.3s;
      margin-bottom: 15px;
    }
    .btn-apply:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(19,211,240,0.4);
    }
    .btn-apply:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
    .btn-secondary {
      width: 100%;
      background: transparent;
      color: var(--text);
      border: 2px solid var(--border);
      padding: 12px;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      margin-bottom: 10px;
    }
    .btn-secondary:hover {
      border-color: var(--accent);
      color: var(--accent);
    }
    .client-info {
      margin-top: 25px;
      padding-top: 25px;
      border-top: 1px solid var(--border);
    }
    .client-header {
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
    .client-details h3 { font-size: 1.1rem; margin-bottom: 5px; }
    .client-rating {
      display: flex;
      align-items: center;
      gap: 5px;
      font-size: 0.9rem;
    }
    .client-rating i { color: #ffa502; }
    .client-stats {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 15px;
      margin-top: 15px;
    }
    .client-stat {
      text-align: center;
      padding: 12px;
      background: rgba(255,255,255,0.03);
      border-radius: 8px;
    }
    [data-theme="light"] .client-stat { background: rgba(0,0,0,0.02); }
    .stat-value {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--accent);
      margin-bottom: 3px;
    }
    .stat-label {
      font-size: 0.8rem;
      opacity: 0.7;
    }
    .similar-jobs {
      margin-top: 40px;
    }
    .job-card-small {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 25px;
      margin-bottom: 20px;
      transition: all 0.3s;
      cursor: pointer;
      text-decoration: none;
      color: var(--text);
      display: block;
    }
    .job-card-small:hover {
      border-color: var(--accent);
      transform: translateY(-2px);
      color: var(--text);
    }
    .job-card-small h3 {
      font-size: 1.1rem;
      margin-bottom: 10px;
    }
    .job-card-small p {
      font-size: 0.9rem;
      opacity: 0.7;
      margin-bottom: 15px;
    }
    .job-card-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.85rem;
    }
    .job-card-budget {
      color: var(--accent);
      font-weight: 600;
    }
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.7);
      z-index: 2000;
      align-items: center;
      justify-content: center;
    }
    .modal.show { display: flex; }
    .modal-content {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 35px;
      max-width: 600px;
      width: 90%;
      max-height: 90vh;
      overflow-y: auto;
      animation: slideUp 0.3s ease;
    }
    @keyframes slideUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
    }
    .modal-header h2 { font-size: 1.5rem; margin: 0; }
    .modal-close {
      background: none;
      border: none;
      font-size: 1.5rem;
      color: var(--text);
      cursor: pointer;
      opacity: 0.7;
      transition: 0.3s;
    }
    .modal-close:hover { opacity: 1; color: var(--accent); }
    .form-group { margin-bottom: 20px; }
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      font-size: 0.95rem;
    }
    .form-control {
      width: 100%;
      padding: 12px 15px;
      border: 2px solid var(--border);
      border-radius: 10px;
      background-color: rgba(255,255,255,0.05);
      color: var(--text);
      font-size: 1rem;
      transition: all 0.3s;
      font-family: 'Inter', sans-serif;
    }
    [data-theme="light"] .form-control { background-color: #f8f9fa; }
    .form-control:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 10px rgba(19,211,240,0.2);
    }
    .form-textarea {
      min-height: 150px;
      resize: vertical;
    }
    .alert {
      padding: 15px 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      animation: slideDown 0.3s ease;
    }
    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .alert-success {
      background: rgba(46,213,115,0.2);
      border: 1px solid #2ed573;
      color: #2ed573;
    }
    .alert-error {
      background: rgba(255,71,87,0.2);
      border: 1px solid #ff4757;
      color: #ff4757;
    }
    @media (max-width: 1200px) {
      .content-wrapper { grid-template-columns: 1fr; }
      .sidebar-card { position: relative; top: 0; }
    }
    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .main-content { margin-left: 0; }
      .info-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
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

  <main class="main-content">
    <div class="top-bar">
      <div class="breadcrumb-nav">
        <a href="browse-projects.php"><i class="fas fa-briefcase"></i> Browse Jobs</a>
        <span>/</span>
        <span>Job Details</span>
      </div>
      <div class="theme-toggle-btn" id="themeToggle">
        <i class="fas fa-sun"></i>
      </div>
    </div>

    <?php if (isset($job_not_found)): ?>
      <div style="text-align: center; padding: 100px 20px;">
        <i class="fas fa-exclamation-circle" style="font-size: 5rem; opacity: 0.3; margin-bottom: 30px;"></i>
        <h2>Job Not Found</h2>
        <p style="opacity: 0.7; margin-bottom: 30px;">This job may have been removed or doesn't exist.</p>
        <a href="browse-projects.php" class="btn-apply" style="max-width: 250px; margin: 0 auto; display: inline-block; text-decoration: none;">
          <i class="fas fa-arrow-left"></i> Back to Browse
        </a>
      </div>
    <?php else: ?>

      <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success">
          <?= htmlspecialchars($_SESSION['flash_success']) ?>
          <?php unset($_SESSION['flash_success']); ?>
        </div>
      <?php endif; ?>

    <div class="content-wrapper">
      <div>
        <div class="job-card">
          <div class="job-header">
            <div class="job-title-section">
              <h1><?= htmlspecialchars($job['title']) ?></h1>
              <div class="job-meta">
                <div class="job-meta-item">
                  <i class="fas fa-clock"></i>
                  <span>Posted <?= $posted_text ?></span>
                </div>
                <div class="job-meta-item">
                  <i class="fas fa-map-marker-alt"></i>
                  <span>Remote</span>
                </div>
                <div class="job-meta-item">
                  <i class="fas fa-users"></i>
                  <span><?= (int)$job['proposal_count'] ?> proposals</span>
                </div>
              </div>
            </div>
            <div class="job-status"><?= ucfirst(htmlspecialchars($job['status'])) ?></div>
          </div>

          <div class="section-title">
            <i class="fas fa-file-alt"></i>
            Project Description
          </div>
          <div class="job-description"><?= nl2br(htmlspecialchars($job['description'])) ?></div>

          <div class="section-title">
            <i class="fas fa-code"></i>
            Required Skills
          </div>
          <div class="skills-container">
            <?php foreach ($skills as $skill): ?>
              <div class="skill-tag"><?= htmlspecialchars($skill) ?></div>
            <?php endforeach; ?>
          </div>

          <div class="info-grid">
            <div class="info-item">
              <div class="info-label">
                <i class="fas fa-tag"></i>
                Category
              </div>
              <div class="info-value"><?= htmlspecialchars($category_display) ?></div>
            </div>
            <div class="info-item">
              <div class="info-label">
                <i class="fas fa-briefcase"></i>
                Status
              </div>
              <div class="info-value"><?= ucfirst(htmlspecialchars($job['status'])) ?></div>
            </div>
          </div>

          <div class="section-title">
            <i class="fas fa-clipboard-check"></i>
            Requirements
          </div>
          <ul class="requirements-list">
            <?php foreach ($requirements as $req): ?>
              <li>
                <i class="fas fa-check-circle"></i>
                <span><?= htmlspecialchars($req) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <?php if (!empty($similar_jobs)): ?>
        <div class="similar-jobs">
          <div class="section-title">
            <i class="fas fa-fire"></i>
            Similar Jobs You Might Like
          </div>
          <?php foreach ($similar_jobs as $similar): 
            $similar_category = ucwords(str_replace(['_', '-'], ' ', $similar['category']));
          ?>
            <a href="job-details.php?id=<?= $similar['id'] ?>" class="job-card-small">
              <h3><?= htmlspecialchars($similar['title']) ?></h3>
              <p><?= htmlspecialchars(substr($similar['description'], 0, 100)) ?>...</p>
              <div class="job-card-footer">
                <div class="job-card-budget">$<?= number_format($similar['budget_min']) ?> - $<?= number_format($similar['budget_max']) ?></div>
                <div><i class="fas fa-file-alt"></i> <?= (int)$similar['proposal_count'] ?> proposals</div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div>
        <div class="sidebar-card">
          <div class="budget-display">
            <div class="budget-label">Project Budget</div>
            <div class="budget-amount">$<?= number_format($job['budget_min']) ?>-$<?= number_format($job['budget_max']) ?></div>
            <div class="budget-type">Fixed Price Range</div>
          </div>

          <?php if ($already_applied): ?>
            <button class="btn-apply" disabled>
              <i class="fas fa-check-circle"></i> Already Applied
            </button>
          <?php else: ?>
            <button class="btn-apply" onclick="openApplyModal()">
              <i class="fas fa-paper-plane"></i> Submit Proposal
            </button>
          <?php endif; ?>
          
          <button class="btn-secondary" onclick="alert('Save feature coming soon!')">
            <i class="fas fa-bookmark"></i> Save Job
          </button>
          <button class="btn-secondary" onclick="shareJob()">
            <i class="fas fa-share-alt"></i> Share Job
          </button>

          <div class="client-info">
            <h3 style="font-size: 1rem; opacity: 0.7; margin-bottom: 15px;">About the Client</h3>
            <div class="client-header">
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
              <div class="client-stat">
                <div class="stat-value">1</div>
                <div class="stat-label">Jobs Posted</div>
              </div>
              <div class="client-stat">
                <div class="stat-value">100%</div>
                <div class="stat-label">Hire Rate</div>
              </div>
              <div class="client-stat">
                <div class="stat-value">2025</div>
                <div class="stat-label">Member Since</div>
              </div>
              <div class="client-stat">
                <div class="stat-value">0</div>
                <div class="stat-label">Total Spent</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php endif; ?>
  </main>

  <!-- Apply Modal -->
  <div class="modal" id="applyModal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Submit Your Proposal</h2>
        <button class="modal-close" onclick="closeApplyModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
          <?php foreach ($errors as $error): ?>
            <div><?= htmlspecialchars($error) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      
      <form method="POST">
        <div class="form-group">
          <label for="bid_amount">Your Bid Amount ($) <span style="color: #ff4757;">*</span></label>
          <input type="number" id="bid_amount" name="bid_amount" class="form-control" placeholder="Enter your bid amount" min="1" step="0.01" required>
        </div>
        <div class="form-group">
          <label for="delivery_time">Delivery Time <span style="color: #ff4757;">*</span></label>
          <input type="text" id="delivery_time" name="delivery_time" class="form-control" placeholder="e.g., 2 weeks" required>
        </div>
        <div class="form-group">
          <label for="cover_letter">Cover Letter <span style="color: #ff4757;">*</span></label>
          <textarea id="cover_letter" name="cover_letter" class="form-control form-textarea" placeholder="Explain why you're the best fit for this project. Include relevant experience and your approach..." required></textarea>
          <small style="opacity: 0.7; font-size: 0.85rem;">Minimum 100 characters</small>
        </div>
        <button type="submit" name="submit_proposal" class="btn-apply">
          <i class="fas fa-paper-plane"></i> Submit Proposal
        </button>
      </form>
    </div>
  </div>

  <script>
    // Theme toggle
    const themeToggle = document.getElementById('themeToggle');
    const html = document.documentElement;
    
    const savedTheme = localStorage.getItem('theme') || 'dark';
    html.setAttribute('data-theme', savedTheme);
    themeToggle.querySelector('i').className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';

    themeToggle.addEventListener('click', () => {
      const current = html.getAttribute('data-theme');
      const newTheme = current === 'dark' ? 'light' : 'dark';
      html.setAttribute('data-theme', newTheme);
      localStorage.setItem('theme', newTheme);
      themeToggle.querySelector('i').className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    });

    // Modal functions
    function openApplyModal() {
      document.getElementById('applyModal').classList.add('show');
      document.body.style.overflow = 'hidden';
    }

    function closeApplyModal() {
      document.getElementById('applyModal').classList.remove('show');
      document.body.style.overflow = 'auto';
    }

    // Close modal on outside click
    document.getElementById('applyModal').addEventListener('click', (e) => {
      if (e.target.id === 'applyModal') {
        closeApplyModal();
      }
    });

    // Share job
    function shareJob() {
      if (navigator.share) {
        navigator.share({
          title: '<?= addslashes($job['title'] ?? 'Job') ?>',
          url: window.location.href
        });
      } else {
        navigator.clipboard.writeText(window.location.href);
        alert('Job link copied to clipboard!');
      }
    }

    <?php if (!empty($errors)): ?>
      // Auto-open modal if there are errors
      openApplyModal();
    <?php endif; ?>
  </script>
</body>
</html>