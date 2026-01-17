<?php
require_once "../src/init.php";
require_once "../src/config.php";

if (!is_logged_in()) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get user info
$stmt = $pdo->prepare('SELECT u.email, u.role, u.created_at, p.display_name, p.bio, p.hourly_rate, p.skills, p.location, p.phone
                       FROM users u 
                       LEFT JOIN profiles p ON u.id = p.user_id 
                       WHERE u.id = ? LIMIT 1');
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

$display_name = $user_data['display_name'] ?: explode('@', $user_data['email'])[0];
$name_parts = explode(' ', $display_name, 2);
$first_name = $name_parts[0];
$last_name = isset($name_parts[1]) ? $name_parts[1] : '';
$initials = strtoupper(substr($first_name, 0, 1) . ($last_name ? substr($last_name, 0, 1) : substr($first_name, 1, 1)));
$user_role_display = ucfirst($user_data['role']);

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $new_display_name = trim($_POST['display_name']);
        $new_bio = trim($_POST['bio']);
        $new_phone = trim($_POST['phone']);
        $new_location = trim($_POST['location']);
        $new_hourly_rate = !empty($_POST['hourly_rate']) ? (float)$_POST['hourly_rate'] : null;
        
        try {
            // Check if profile exists
            $check = $pdo->prepare("SELECT user_id FROM profiles WHERE user_id = ?");
            $check->execute([$user_id]);
            
            if ($check->fetch()) {
                // Update existing profile
                $update = $pdo->prepare("UPDATE profiles SET display_name = ?, bio = ?, phone = ?, location = ?, hourly_rate = ? WHERE user_id = ?");
                $update->execute([$new_display_name, $new_bio, $new_phone, $new_location, $new_hourly_rate, $user_id]);
            } else {
                // Insert new profile
                $insert = $pdo->prepare("INSERT INTO profiles (user_id, display_name, bio, phone, location, hourly_rate) VALUES (?, ?, ?, ?, ?, ?)");
                $insert->execute([$user_id, $new_display_name, $new_bio, $new_phone, $new_location, $new_hourly_rate]);
            }
            
            $success_message = "Profile updated successfully!";
            
            // Refresh data
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error_message = "Error updating profile: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match!";
        } elseif (strlen($new_password) < 6) {
            $error_message = "Password must be at least 6 characters!";
        } else {
            try {
                // Verify current password
                $pwd_check = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
                $pwd_check->execute([$user_id]);
                $pwd_data = $pwd_check->fetch();
                
                if (password_verify($current_password, $pwd_data['password_hash'])) {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_pwd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $update_pwd->execute([$new_hash, $user_id]);
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Current password is incorrect!";
                }
            } catch (PDOException $e) {
                $error_message = "Error changing password: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings - WorkNest</title>
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
    .page-title h1 {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 5px;
    }
    .page-title p {
      opacity: 0.7;
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
    
    .alert {
      padding: 15px 20px;
      border-radius: 10px;
      margin-bottom: 30px;
      animation: slideDown 0.3s ease;
    }
    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .alert-success {
      background: rgba(46,213,115,0.2);
      color: #2ed573;
      border: 1px solid rgba(46,213,115,0.3);
    }
    .alert-error {
      background: rgba(255,71,87,0.2);
      color: #ff4757;
      border: 1px solid rgba(255,71,87,0.3);
    }
    
    .settings-grid {
      display: grid;
      gap: 30px;
    }
    .settings-section {
      background-color: var(--card);
      border: 1px solid var(--border);
      border-radius: 15px;
      padding: 30px;
      animation: fadeInUp 0.6s ease;
    }
    [data-theme="light"] .settings-section { box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .section-title {
      font-size: 1.3rem;
      font-weight: 700;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      padding-bottom: 15px;
      border-bottom: 2px solid var(--border);
    }
    .section-title i {
      color: var(--accent);
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      font-size: 0.95rem;
    }
    .form-input, .form-textarea {
      width: 100%;
      padding: 12px 15px;
      background: rgba(255,255,255,0.05);
      border: 2px solid var(--border);
      border-radius: 10px;
      color: var(--text);
      font-size: 0.95rem;
      font-family: 'Inter', sans-serif;
      transition: all 0.3s;
    }
    [data-theme="light"] .form-input, [data-theme="light"] .form-textarea {
      background: #f8f9fa;
    }
    .form-input:focus, .form-textarea:focus {
      outline: none;
      border-color: var(--accent);
      background: rgba(19,211,240,0.05);
    }
    .form-textarea {
      resize: vertical;
      min-height: 100px;
    }
    .btn-primary {
      background: linear-gradient(135deg, var(--accent), #0891b2);
      color: white;
      border: none;
      padding: 12px 30px;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      font-size: 0.95rem;
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(19,211,240,0.4);
    }
    .btn-danger {
      background: rgba(255,71,87,0.2);
      color: #ff4757;
      border: 2px solid #ff4757;
      padding: 12px 30px;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
      font-size: 0.95rem;
    }
    .btn-danger:hover {
      background: rgba(255,71,87,0.3);
      transform: translateY(-2px);
    }
    .account-info {
      display: grid;
      gap: 15px;
      padding: 20px;
      background: rgba(19,211,240,0.05);
      border-radius: 10px;
      margin-bottom: 20px;
    }
    .info-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 0;
      border-bottom: 1px solid var(--border);
    }
    .info-row:last-child {
      border-bottom: none;
    }
    .info-label {
      opacity: 0.7;
      font-size: 0.9rem;
    }
    .info-value {
      font-weight: 600;
    }
    
    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .main-content { margin-left: 0; }
      .settings-section { padding: 20px; }
    }
  </style>
</head>
<body>
  <aside class="sidebar">
    <a href="dashboard.php" class="sidebar-brand">WorkNest</a>
    <ul class="sidebar-menu">
      <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
      <li><a href="browse-freelancer.php"><i class="fas fa-users"></i> Browse Freelancers</a></li>
      <li><a href="post-job.php"><i class="fas fa-plus-circle"></i> Post a Job</a></li>
      <li><a href="proposals.php" class="active"><i class="fas fa-inbox"></i> Proposals</a></li>
      <li><a href="messages-client.php"><i class="fas fa-comments"></i> Messages</a></li>
      <li><a href="spending.php"><i class="fas fa-wallet"></i> Spending</a></li>
      <li><a href="reviews-client.php"><i class="fas fa-star"></i> Reviews</a></li>
      <li><a href="settings-client.php"><i class="fas fa-cog"></i> Settings</a></li>
    <div class="sidebar-footer">
      <a href="profile-client.php" class="user-profile">
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

  <main class="main-content">
    <div class="top-bar">
      <div class="page-title">
        <h1>Settings ⚙️</h1>
        <p>Manage your account and preferences</p>
      </div>
      <div class="theme-toggle-btn" id="themeToggle">
        <i class="fas fa-sun"></i>
      </div>
    </div>

    <?php if ($success_message): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
      </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
      <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
      </div>
    <?php endif; ?>

    <div class="settings-grid">
      <!-- Account Information -->
      <div class="settings-section">
        <h2 class="section-title">
          <i class="fas fa-user-circle"></i>
          Account Information
        </h2>
        <div class="account-info">
          <div class="info-row">
            <span class="info-label">Email</span>
            <span class="info-value"><?= htmlspecialchars($user_data['email']) ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">Account Type</span>
            <span class="info-value"><?= htmlspecialchars($user_role_display) ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">Member Since</span>
            <span class="info-value"><?= date('F Y', strtotime($user_data['created_at'])) ?></span>
          </div>
        </div>
      </div>

      <!-- Profile Settings -->
      <div class="settings-section">
        <h2 class="section-title">
          <i class="fas fa-id-card"></i>
          Profile Settings
        </h2>
        <form method="POST" action="">
          <div class="form-group">
            <label class="form-label">Display Name</label>
            <input type="text" name="display_name" class="form-input" value="<?= htmlspecialchars($user_data['display_name'] ?? '') ?>" required>
          </div>
          
          <div class="form-group">
            <label class="form-label">Bio</label>
            <textarea name="bio" class="form-textarea" placeholder="Tell us about yourself..."><?= htmlspecialchars($user_data['bio'] ?? '') ?></textarea>
          </div>
          
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input type="tel" name="phone" class="form-input" value="<?= htmlspecialchars($user_data['phone'] ?? '') ?>" placeholder="+1 234 567 8900">
          </div>
          
          <div class="form-group">
            <label class="form-label">Location</label>
            <input type="text" name="location" class="form-input" value="<?= htmlspecialchars($user_data['location'] ?? '') ?>" placeholder="City, Country">
          </div>
          
          <?php if ($user_data['role'] === 'freelancer'): ?>
            <div class="form-group">
              <label class="form-label">Hourly Rate ($)</label>
              <input type="number" name="hourly_rate" class="form-input" value="<?= htmlspecialchars($user_data['hourly_rate'] ?? '') ?>" placeholder="50" step="0.01" min="0">
            </div>
          <?php endif; ?>
          
          <button type="submit" name="update_profile" class="btn-primary">
            <i class="fas fa-save"></i> Save Profile
          </button>
        </form>
      </div>

      <!-- Security Settings -->
      <div class="settings-section">
        <h2 class="section-title">
          <i class="fas fa-lock"></i>
          Security Settings
        </h2>
        <form method="POST" action="">
          <div class="form-group">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-input" required>
          </div>
          
          <div class="form-group">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-input" required minlength="6">
          </div>
          
          <div class="form-group">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-input" required minlength="6">
          </div>
          
          <button type="submit" name="change_password" class="btn-primary">
            <i class="fas fa-key"></i> Change Password
          </button>
        </form>
      </div>

      <!-- Danger Zone -->
      <div class="settings-section">
        <h2 class="section-title">
          <i class="fas fa-exclamation-triangle"></i>
          Danger Zone
        </h2>
        <p style="opacity: 0.7; margin-bottom: 20px;">Once you delete your account, there is no going back. Please be certain.</p>
        <button class="btn-danger" onclick="if(confirm('Are you sure you want to delete your account? This action cannot be undone!')) alert('Account deletion feature coming soon!')">
          <i class="fas fa-trash"></i> Delete Account
        </button>
      </div>
    </div>
  </main>

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
    
    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        alert.style.animation = 'slideDown 0.3s ease reverse';
        setTimeout(() => alert.remove(), 300);
      });
    }, 5000);
  </script>
</body>
</html>