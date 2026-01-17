<?php
require_once "../src/init.php";
require_once "../src/config.php";

// Check for error or success messages from signup_process.php
$error = $_SESSION['flash_error'] ?? null;
$success = $_SESSION['flash_success'] ?? null;

// Clear messages after displaying
unset($_SESSION['flash_error']);
unset($_SESSION['flash_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up - WorkNest</title>

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
    }

    [data-theme="light"] {
      --bg: #eeeeee;
      --text: #031121;
      --card: #ffffff;
      --accent: #1055c9;
    }

    /* Base */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      background: linear-gradient(135deg, #0a4d68 0%, #089db8 25%, #05668d 50%, #13d3f0 75%, #0891b2 100%);
      background-size: 400% 400%;
      animation: gradientShift 15s ease infinite;
      color: var(--text);
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    @keyframes gradientShift {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }

    /* Theme Toggle Button */
    .theme-toggle-btn {
      position: fixed;
      top: 26px;
      right: 30px;
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: var(--card);
      border: 2px solid var(--accent);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 2000;
      transition: all 0.3s ease;
    }

    .theme-toggle-btn:hover {
      transform: scale(1.1);
    }

    .theme-toggle-btn i {
      font-size: 1.3rem;
      color: var(--accent);
    }

    /* Signup Container */
    .signup-container {
      background-color: var(--card);
      border-radius: 20px;
      box-shadow: 0 0 40px rgba(0,0,0,0.4);
      padding: 50px 40px;
      max-width: 500px;
      width: 100%;
      animation: fadeInUp 0.8s ease;
      border: 1px solid rgba(255,255,255,0.1);
      max-height: 90vh;
      overflow-y: auto;
    }

    [data-theme="light"] .signup-container {
      border: 1px solid rgba(0,0,0,0.1);
      box-shadow: 0 0 40px rgba(0,0,0,0.1);
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .signup-header {
      text-align: center;
      margin-bottom: 35px;
    }

    .signup-header h1 {
      color: var(--accent);
      font-weight: 700;
      font-size: 2.5rem;
      margin-bottom: 10px;
    }

    .signup-header p {
      color: var(--text);
      opacity: 0.8;
    }

    /* Alert Messages */
    .alert {
      padding: 12px 15px;
      border-radius: 10px;
      margin-bottom: 20px;
      display: none;
      animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .alert.show {
      display: block;
    }

    .alert-error {
      background: rgba(255,71,87,0.2);
      border: 1px solid #ff4757;
      color: #ff4757;
    }

    .alert-success {
      background: rgba(46,213,115,0.2);
      border: 1px solid #2ed573;
      color: #2ed573;
    }

    /* User Type Selection */
    .user-type {
      display: flex;
      gap: 15px;
      margin-bottom: 30px;
    }

    .type-option {
      flex: 1;
      padding: 15px;
      border: 2px solid rgba(255,255,255,0.2);
      border-radius: 10px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s;
      background: transparent;
    }

    [data-theme="light"] .type-option {
      border: 2px solid rgba(0,0,0,0.2);
    }

    .type-option:hover {
      border-color: var(--accent);
      transform: translateY(-2px);
    }

    .type-option.active {
      border-color: var(--accent);
      background: rgba(19,211,240,0.1);
    }

    .type-option i {
      font-size: 2rem;
      color: var(--accent);
      display: block;
      margin-bottom: 8px;
    }

    .type-option span {
      font-weight: 600;
      color: var(--text);
    }

    /* Form Elements */
    .form-row {
      display: flex;
      gap: 15px;
      margin-bottom: 20px;
    }

    .form-group {
      margin-bottom: 20px;
      flex: 1;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: var(--text);
      font-size: 0.9rem;
    }

    .input-wrapper {
      position: relative;
    }

    .input-wrapper i {
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--accent);
    }

    .form-control {
      width: 100%;
      padding: 12px 15px 12px 45px;
      border: 2px solid rgba(255,255,255,0.1);
      border-radius: 10px;
      background-color: rgba(255,255,255,0.05);
      color: var(--text);
      font-size: 1rem;
      transition: all 0.3s;
    }

    [data-theme="light"] .form-control {
      background-color: #f8f9fa;
      border: 2px solid rgba(0,0,0,0.1);
    }

    .form-control:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 10px rgba(19,211,240,0.3);
    }

    .form-control::placeholder {
      color: rgba(255,255,255,0.5);
    }

    [data-theme="light"] .form-control::placeholder {
      color: rgba(0,0,0,0.4);
    }

    .form-control.error {
      border-color: #ff4757;
    }

    /* Terms Checkbox */
    .terms {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      margin-bottom: 25px;
      font-size: 0.9rem;
    }

    .terms input[type="checkbox"] {
      width: 18px;
      height: 18px;
      cursor: pointer;
      margin-top: 2px;
    }

    .terms a {
      color: var(--accent);
      text-decoration: none;
    }

    .terms a:hover {
      text-decoration: underline;
    }

    /* Button */
    .btn-main {
      width: 100%;
      background-color: var(--accent);
      color: #ffffff;
      font-weight: 600;
      border-radius: 10px;
      padding: 14px;
      border: none;
      font-size: 1.1rem;
      cursor: pointer;
      transition: all 0.3s;
      position: relative;
    }

    .btn-main:hover:not(:disabled) {
      background-color: #0ea5c2;
      box-shadow: 0 0 20px rgba(19,211,240,0.4);
      transform: translateY(-2px);
    }

    .btn-main:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    /* Divider */
    .divider {
      text-align: center;
      margin: 25px 0;
      position: relative;
    }

    .divider::before,
    .divider::after {
      content: '';
      position: absolute;
      top: 50%;
      width: 40%;
      height: 1px;
      background: rgba(255,255,255,0.2);
    }

    [data-theme="light"] .divider::before,
    [data-theme="light"] .divider::after {
      background: rgba(0,0,0,0.2);
    }

    .divider::before {
      left: 0;
    }

    .divider::after {
      right: 0;
    }

    .divider span {
      background: var(--card);
      padding: 0 10px;
      color: var(--text);
      opacity: 0.7;
    }

    /* Social Signup */
    .social-signup {
      display: flex;
      gap: 15px;
      margin-bottom: 25px;
    }

    .social-btn {
      flex: 1;
      padding: 12px;
      border-radius: 10px;
      border: 2px solid rgba(255,255,255,0.2);
      background: transparent;
      color: var(--text);
      cursor: pointer;
      transition: all 0.3s;
      font-size: 1.1rem;
    }

    [data-theme="light"] .social-btn {
      border: 2px solid rgba(0,0,0,0.2);
    }

    .social-btn:hover {
      border-color: var(--accent);
      transform: translateY(-2px);
    }

    /* Footer Text */
    .footer-text {
      text-align: center;
      margin-top: 20px;
      color: var(--text);
      opacity: 0.8;
      font-size: 0.95rem;
    }

    .footer-text a {
      color: var(--accent);
      text-decoration: none;
      font-weight: 600;
    }

    .footer-text a:hover {
      text-decoration: underline;
    }

    /* Back to Home */
    .back-home {
      position: fixed;
      top: 26px;
      left: 30px;
      color: #ffffff;
      text-decoration: none;
      font-weight: 600;
      font-size: 1.1rem;
      transition: 0.3s;
      z-index: 2000;
    }

    .back-home:hover {
      color: var(--accent);
    }

    .back-home i {
      margin-right: 5px;
    }

    /* Scrollbar Styling */
    .signup-container::-webkit-scrollbar {
      width: 8px;
    }

    .signup-container::-webkit-scrollbar-track {
      background: rgba(255,255,255,0.05);
      border-radius: 10px;
    }

    .signup-container::-webkit-scrollbar-thumb {
      background: var(--accent);
      border-radius: 10px;
    }
  </style>
</head>

<body>

<a href="index.php" class="back-home">
  <i class="fas fa-arrow-left"></i> Back to Home
</a>

<div class="theme-toggle-btn" id="themeToggle">
  <i class="fas fa-sun"></i>
</div>

<div class="signup-container">
  <div class="signup-header">
    <h1>Join WorkNest</h1>
    <p>Create your account and start your journey</p>
  </div>

  <!-- Alert Message -->
  <?php if (isset($error)): ?>
  <div class="alert alert-error show">
    <?php echo htmlspecialchars($error); ?>
  </div>
  <?php endif; ?>

  <?php if (isset($success)): ?>
  <div class="alert alert-success show">
    <?php echo htmlspecialchars($success); ?>
  </div>
  <?php endif; ?>

  <div class="user-type">
    <div class="type-option active" data-type="freelancer">
      <i class="fas fa-user"></i>
      <span>Freelancer</span>
    </div>
    <div class="type-option" data-type="client">
      <i class="fas fa-briefcase"></i>
      <span>Client</span>
    </div>
  </div>

  <form id="signupForm" method="POST" action="actions/signup_process.php">
    <input type="hidden" name="role" id="roleInput" value="freelancer">

    <div class="form-row">
      <div class="form-group">
        <label for="firstName">First Name</label>
        <div class="input-wrapper">
          <i class="fas fa-user"></i>
          <input type="text" id="firstName" name="firstName" class="form-control" 
                 placeholder="John" value="<?php echo htmlspecialchars($_POST['firstName'] ?? ''); ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label for="lastName">Last Name</label>
        <div class="input-wrapper">
          <i class="fas fa-user"></i>
          <input type="text" id="lastName" name="lastName" class="form-control" 
                 placeholder="Doe" value="<?php echo htmlspecialchars($_POST['lastName'] ?? ''); ?>" required>
        </div>
      </div>
    </div>

    <div class="form-group">
      <label for="email">Email Address</label>
      <div class="input-wrapper">
        <i class="fas fa-envelope"></i>
        <input type="email" id="email" class="form-control" name="email" 
               placeholder="john.doe@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
      </div>
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <div class="input-wrapper">
        <i class="fas fa-lock"></i>
        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>
    </div>

    <div class="form-group">
      <label for="confirmPassword">Confirm Password</label>
      <div class="input-wrapper">
        <i class="fas fa-lock"></i>
        <input type="password" id="confirmPassword" name="confirmPassword" class="form-control" placeholder="••••••••" required>
      </div>
    </div>

    <div class="terms">
      <input type="checkbox" id="terms" required>
      <label for="terms">
        I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
      </label>
    </div>

    <button type="submit" class="btn-main" id="signupBtn">
      Create Account
    </button>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

  </form>

  <div class="divider">
    <span>OR</span>
  </div>

  <div class="social-signup">
    <button type="button" class="social-btn" onclick="alert('Google signup coming soon!')">
      <i class="fab fa-google"></i>
    </button>
    <button type="button" class="social-btn" onclick="alert('GitHub signup coming soon!')">
      <i class="fab fa-github"></i>
    </button>
    <button type="button" class="social-btn" onclick="alert('LinkedIn signup coming soon!')">
      <i class="fab fa-linkedin"></i>
    </button>
  </div>

  <div class="footer-text">
    Already have an account? <a href="login.php">Login</a>
  </div>
</div>

<script>
  // Track selected user type
  let selectedUserType = 'freelancer';

  // User Type Selection
  const typeOptions = document.querySelectorAll('.type-option');
  const roleInput = document.getElementById('roleInput');
  
  typeOptions.forEach(option => {
    option.addEventListener('click', () => {
      typeOptions.forEach(opt => opt.classList.remove('active'));
      option.classList.add('active');
      selectedUserType = option.dataset.type;
      roleInput.value = selectedUserType;
    });
  });

  // Client-side validation
  document.getElementById('signupForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const email = document.getElementById('email').value;
    
    // Email validation
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      e.preventDefault();
      alert('Please enter a valid email address');
      return false;
    }
    
    // Password validation (minimum 6 characters as per backend)
    if (password.length < 6) {
      e.preventDefault();
      alert('Password must be at least 6 characters');
      return false;
    }
    
    // Password match validation
    if (password !== confirmPassword) {
      e.preventDefault();
      alert('Passwords do not match');
      return false;
    }
  });

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