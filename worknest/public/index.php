<?php
require_once "../src/init.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WorkNest – Smart Freelancing & Collaboration Hub</title>

  <!-- Libraries -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">

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
    body {
      background-color: var(--bg);
      color: var(--text);
      font-family: 'Inter', sans-serif;
      overflow-x: hidden;
      scroll-behavior: smooth;
      transition: background-color 0.3s ease, color 0.3s ease;
    }

    /* Navbar */
    .navbar {
      background: rgba(3,17,33,0.8);
      backdrop-filter: blur(6px);
      border-bottom: 1px solid rgba(255,255,255,0.1);
      transition: background 0.3s ease;
    }
    [data-theme="light"] .navbar {
      background: rgba(255,255,255,0.9);
      border-bottom: 1px solid rgba(0,0,0,0.1);
    }
    .navbar-brand {
      color: var(--accent) !important;
      font-weight: 700;
      letter-spacing: 1px;
    }
    .nav-link {
      color: #ffffff !important;
      transition: 0.3s;
    }
    [data-theme="light"] .nav-link {
      color: #031121 !important;
    }
    .nav-link:hover {
      color: var(--accent) !important;
    }

    /* Hero */
    .hero {
      background: linear-gradient(135deg, #0a4d68 0%, #089db8 25%, #05668d 50%, #13d3f0 75%, #0891b2 100%);
      background-size: 400% 400%;
      animation: gradientShift 15s ease infinite;
      border-bottom: 3px solid rgba(19, 211, 240, 0.4);
      border-top: 3px solid rgba(19, 211, 240, 0.4);
      box-shadow: 0 0 30px rgba(19,211,240,0.3);
      padding: 120px 0;
      position: relative;
      overflow: hidden;
    }

    @keyframes gradientShift {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }

    .hero::after {
      content: "";
      position: absolute;
      top: 0;
      left: -200px;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
      animation: floatLight 6s ease-in-out infinite;
    }

    @keyframes floatLight {
      0%, 100% { transform: translateY(0px) translateX(0px); }
      50% { transform: translateY(-30px) translateX(30px); }
    }

    .hero-content {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      justify-content: space-between;
    }

    .hero-text {
      flex: 1;
      min-width: 300px;
      animation: slideInLeft 1.2s ease forwards;
    }
    @keyframes slideInLeft {
      from {opacity: 0; transform: translateX(-80px);}
      to {opacity: 1; transform: translateX(0);}
    }

    .hero-text h1 {
      font-weight: 700;
      font-size: 3rem;
      color: #fff;
    }
    .hero-text p {
      font-size: 1.1rem;
      margin-bottom: 30px;
      color: #e5faff;
    }

    .hero-img {
      flex: 1;
      text-align: center;
      min-width: 300px;
      animation: slideInRight 1.2s ease forwards;
    }
    @keyframes slideInRight {
      from {opacity: 0; transform: translateX(80px);}
      to {opacity: 1; transform: translateX(0);}
    }

    .hero-img img {
      max-width: 90%;
      border-radius: 20px;
      box-shadow: 0 0 25px rgba(0,0,0,0.3);
    }

    /* Buttons */
    .btn-main {
      background-color: var(--accent);
      color: #ffffff !important;
      font-weight: 600;
      border-radius: 50px;
      padding: 10px 28px;
      border: none;
      transition: all 0.3s;
    }
    .btn-main:hover {
      background-color: #ffffff;
      color: #031121 !important;
      box-shadow: 0 0 10px var(--accent), 0 0 20px var(--accent);
    }
    .btn-outline-light {
      border: 1px solid white;
      border-radius: 50px;
      color: white !important;
      transition: 0.3s;
    }
    .btn-outline-light:hover {
      background-color: white;
      color: #031121 !important;
    }

    /* Sections */
    section {
      padding: 80px 0;
    }
    .section-title {
      font-weight: 700;
      text-align: center;
      margin-bottom: 50px;
      color: var(--accent);
    }

    .card {
      background-color: var(--card);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 12px;
      box-shadow: 0 0 15px rgba(0,0,0,0.3);
      transition: 0.3s;
      color: var(--text);
    }
    [data-theme="light"] .card {
      border: 1px solid rgba(0,0,0,0.1);
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
    }
    .card:hover {
      transform: translateY(-8px);
      box-shadow: 0 0 20px rgba(19,211,240,0.3);
    }
    .card i {
      font-size: 2rem;
      color: var(--accent);
      margin-bottom: 15px;
    }

    /* CTA */
    #cta {
      background: linear-gradient(135deg, #089db8, #13d3f0);
      box-shadow: 0 0 40px rgba(19,211,240,0.4);
    }

    /* Footer */
    footer {
      background-color: #010a16;
      color: #ffffff;
      text-align: center;
      padding: 40px 0;
      font-size: 0.9rem;
      border-top: 1px solid rgba(255,255,255,0.1);
    }
    [data-theme="light"] footer {
      background-color: #f8f9fa;
      color: #031121;
    }
    footer a {
      color: #13d3f0;
      text-decoration: none;
    }
    footer a:hover {
      text-decoration: underline;
    }

    /* ===== THEME TOGGLE BUTTON ===== */
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

    /* Scroll Button */
    #scrollTopBtn {
      position: fixed;
      bottom: 35px;
      right: 35px;
      background-color: #13d3f0;
      color: #031121;
      border: none;
      border-radius: 50%;
      width: 45px;
      height: 45px;
      font-size: 20px;
      display: none;
      cursor: pointer;
      box-shadow: 0 0 15px rgba(19,211,240,0.3);
      transition: 0.3s;
      z-index: 1000;
    }
    #scrollTopBtn:hover {
      background-color: #fff;
      color: #031121;
    }
  </style>
</head>

<body>

<div class="theme-toggle-btn" id="themeToggle">
  <i class="fas fa-sun"></i>
</div>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top py-3">
  <div class="container">
    <a class="navbar-brand" href="index.php">WorkNest</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
        <li class="nav-item"><a class="nav-link" href="#how">How It Works</a></li>
        <li class="nav-item"><a class="nav-link" href="#categories">Categories</a></li>
        <?php if (is_logged_in()): ?>
          <li class="nav-item"><a class="nav-link btn btn-main text-white ms-lg-3" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
          <li class="nav-item"><a class="nav-link btn btn-main text-white ms-lg-3" href="signup.php">Sign Up Free</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- Hero -->
<section class="hero" id="home">
  <div class="container hero-content">
    <div class="hero-text">
      <h1>Find Work. Build Skills. Collaborate Smart.</h1>
      <p>Join WorkNest — where students, freelancers, and startups connect in one creative digital workspace with real-time notifications and seamless collaboration.</p>
      <?php if (is_logged_in()): ?>
        <a href="dashboard.php" class="btn btn-main me-2"><i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard</a>
        <a href="browse.php" class="btn btn-outline-light"><i class="fas fa-search me-2"></i>
          <?php echo ($_SESSION['user_role'] === 'client') ? 'Browse Freelancers' : 'Browse Projects'; ?>
        </a>
      <?php else: ?>
        <a href="signup.php" class="btn btn-main me-2"><i class="fas fa-rocket me-2"></i>Get Started Free</a>
        <a href="login.php" class="btn btn-outline-light"><i class="fas fa-sign-in-alt me-2"></i>Login</a>
      <?php endif; ?>
    </div>
   <div class="hero-img">
  <img src="collab.gif" alt="Collaboration Illustration">
</div>
  </div>
</section>

<!-- About -->
<section id="about" data-aos="fade-up">
  <div class="container text-center">
    <h2 class="section-title">About WorkNest</h2>
    <p class="w-75 mx-auto">WorkNest simplifies freelancing and collaboration for students and startups by providing a transparent, affordable, and growth-oriented digital workspace. It's where innovation meets opportunity.</p>
  </div>
</section>

<!-- Features -->
<section id="features" data-aos="fade-up" style="background: rgba(19,211,240,0.05); padding: 80px 0;">
  <div class="container text-center">
    <h2 class="section-title">Platform Features</h2>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="card p-4">
          <i class="fas fa-bell"></i>
          <h5>Real-Time Notifications</h5>
          <p>Get instant notifications for messages, proposals, and project updates.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-4">
          <i class="fas fa-users"></i>
          <h5>Browse Talent</h5>
          <p>Clients can browse top-rated freelancers with ratings, reviews, and portfolios.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-4">
          <i class="fas fa-comments"></i>
          <h5>Seamless Messaging</h5>
          <p>Chat in real-time with file attachments and conversation history.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-4">
          <i class="fas fa-star"></i>
          <h5>Reviews & Ratings</h5>
          <p>Build reputation through verified client reviews and 5-star ratings.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-4">
          <i class="fas fa-shield-alt"></i>
          <h5>Secure Payments</h5>
          <p>Track earnings and spending with transparent transaction history.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-4">
          <i class="fas fa-briefcase"></i>
          <h5>Project Management</h5>
          <p>Manage proposals, track progress, and collaborate efficiently.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- How It Works -->
<section id="how" data-aos="fade-up">
  <div class="container text-center">
    <h2 class="section-title">How It Works</h2>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="card p-4">
          <i class="fa-solid fa-user-plus"></i>
          <h5>1. Create Your Profile</h5>
          <p>Sign up in seconds, showcase your skills with a professional profile, and get discovered by clients worldwide.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-4">
          <i class="fa-solid fa-search"></i>
          <h5>2. Find Perfect Matches</h5>
          <p>Freelancers browse projects, clients browse talent. Our smart matching helps you find the right fit fast.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-4">
          <i class="fa-solid fa-handshake"></i>
          <h5>3. Submit Proposals</h5>
          <p>Apply with custom proposals, negotiate terms, and get instant notifications when clients respond.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-4">
          <i class="fa-solid fa-comments"></i>
          <h5>4. Collaborate Seamlessly</h5>
          <p>Message in real-time, share files, and keep all project communication in one place.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-4">
          <i class="fa-solid fa-check-circle"></i>
          <h5>5. Deliver & Get Paid</h5>
          <p>Complete projects, receive payment securely, and track your earnings in one dashboard.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card p-4">
          <i class="fa-solid fa-trophy"></i>
          <h5>6. Build Your Reputation</h5>
          <p>Earn 5-star reviews, grow your portfolio, and become a top-rated freelancer on WorkNest.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Categories -->
<section id="categories" data-aos="fade-up">
  <div class="container text-center">
    <h2 class="section-title">Popular Categories</h2>
    <div class="row g-4">
      <div class="col-md-3 col-sm-6">
        <div class="card p-4"><i class="fa-solid fa-code"></i><h6>Web Development</h6></div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card p-4"><i class="fa-solid fa-pen-nib"></i><h6>Graphic Design</h6></div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card p-4"><i class="fa-solid fa-file-lines"></i><h6>Content Writing</h6></div>
      </div>
      <div class="col-md-3 col-sm-6">
        <div class="card p-4"><i class="fa-solid fa-bullhorn"></i><h6>Marketing & SEO</h6></div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section id="cta" class="text-center text-white py-5" data-aos="fade-up">
  <div class="container">
    <h2 class="fw-bold mb-3">Ready to Transform Your Freelancing Journey?</h2>
    <p class="mb-4" style="font-size: 1.1rem;">Join thousands of freelancers and clients building their future on WorkNest</p>
    <?php if (is_logged_in()): ?>
      <a href="browse.php" class="btn btn-main me-2"><i class="fas fa-search me-2"></i>
        <?php echo ($_SESSION['user_role'] === 'client') ? 'Browse Freelancers' : 'Browse Projects'; ?>
      </a>
      <a href="dashboard.php" class="btn btn-outline-light"><i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard</a>
    <?php else: ?>
      <a href="signup.php" class="btn btn-main me-2"><i class="fas fa-rocket me-2"></i>Join WorkNest Free</a>
      <a href="login.php" class="btn btn-outline-light"><i class="fas fa-sign-in-alt me-2"></i>Login</a>
    <?php endif; ?>
  </div>
</section>

<!-- Footer -->
<footer>
  <div class="container">
    <p>© 2025 WorkNest. Empowering Freelancers, Students, and Startups.</p>
    <div>
      <a href="#"><i class="fab fa-linkedin fa-lg me-2"></i></a>
      <a href="#"><i class="fab fa-twitter fa-lg me-2"></i></a>
      <a href="#"><i class="fab fa-instagram fa-lg"></i></a>
    </div>
  </div>
</footer>

<!-- Scroll Button -->
<button id="scrollTopBtn"><i class="fas fa-arrow-up"></i></button>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
<script>
  AOS.init({ duration: 1000, once: true });

  // Scroll to top button
  const btn = document.getElementById("scrollTopBtn");
  window.addEventListener("scroll", () => {
    btn.style.display = window.scrollY > 300 ? "block" : "none";
  });
  btn.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));

  // Theme toggle
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