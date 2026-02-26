<?php
require_once 'includes/auth.php';

// Check if user is already logged in
$isLoggedIn = isLoggedIn();
$isAdmin = $isLoggedIn && isAdmin();

// Get redirect URL for logged in users
$dashboardUrl = '';
if ($isLoggedIn) {
    $dashboardUrl = $isAdmin ? 'admin_dashboard.php' : 'user_dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PRINCE MULTI PANEL - Premium Mod APK Manager</title>
    <meta name="description" content="Professional Mod APK management system with secure distribution and user management.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --purple: #8b5cf6;
            --purple-light: #a78bfa;
            --purple-dark: #7c3aed;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-light: #e2e8f0;
            --shadow-light: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-medium: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-large: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            background-color: var(--bg-color);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="%23ffffff" opacity="0.1"/></pattern></defs><rect width="100" height="20" fill="url(%23dots)"/></svg>');
            opacity: 0.5;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            color: white;
        }
        
        .feature-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-large);
            transition: all 0.3s ease;
            border: 1px solid var(--border-light);
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .feature-icon {
            width: 64px;
            height: 64px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-medium);
        }
        
        .navbar-custom {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-light);
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .navbar-custom.scrolled {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: var(--shadow-medium);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stats-section {
            background: var(--card-bg);
            padding: 5rem 0;
            border-top: 1px solid var(--border-light);
        }
        
        .stat-card {
            text-align: center;
            padding: 2rem;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .btn-cta {
            background: white;
            color: var(--purple);
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-medium);
        }
        
        .btn-cta:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-large);
            color: var(--purple-dark);
        }
        
        .btn-outline-cta {
            background: transparent;
            color: white;
            border: 2px solid white;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
        }
        
        .btn-outline-cta:hover {
            background: white;
            color: var(--purple);
            transform: translateY(-1px);
        }
        
        .theme-toggle {
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 1001;
            background: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 8px;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--text-secondary);
            box-shadow: var(--shadow-medium);
        }
        
        .theme-toggle:hover {
            color: var(--purple);
            box-shadow: var(--shadow-large);
            transform: translateY(-1px);
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* Dark theme styles */
        [data-theme="dark"] {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-light: #334155;
        }
        
        [data-theme="dark"] .navbar-custom {
            background: rgba(30, 41, 59, 0.95);
        }
        
        [data-theme="dark"] .feature-card {
            background: var(--card-bg);
            color: var(--text-primary);
            border-color: var(--border-light);
        }
        
        [data-theme="dark"] .stats-section {
            background: var(--card-bg);
            border-color: var(--border-light);
        }
        
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease;
        }
        
        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 2rem 0;
            }
            
            .hero-content h1 {
                font-size: 2.5rem;
            }
            
            .feature-card {
                padding: 1.5rem;
            }
            
            .feature-icon {
                width: 56px;
                height: 56px;
                font-size: 1.3rem;
            }
            
            .stat-number {
                font-size: 2.5rem;
            }
            
            .theme-toggle {
                top: 80px;
                right: 16px;
                width: 40px;
                height: 40px;
            }
        }
    </style>
</head>
<body>
    <!-- Theme Toggle -->
    <button class="theme-toggle" onclick="toggleDarkMode()" title="Toggle Dark Mode">
        <i class="fas fa-moon" id="darkModeIcon"></i>
    </button>
    
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom" id="navbar">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-crown me-2"></i>Multi Panel
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item">
                            <a class="btn btn-primary-enhanced ms-2" href="<?php echo $dashboardUrl; ?>">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary-enhanced ms-2" href="register.php">
                                <i class="fas fa-user-plus me-2"></i>Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <h1 class="display-4 fw-bold mb-4 fade-in">
                            Premium Mod APK
                            <span class="d-block text-warning">Management System</span>
                        </h1>
                        <p class="lead mb-5 fade-in">
                            Secure, professional, and feature-rich platform for managing mod APK distribution with advanced user management and analytics.
                        </p>
                        <div class="d-flex flex-wrap gap-3 fade-in">
                            <?php if ($isLoggedIn): ?>
                                <a href="<?php echo $dashboardUrl; ?>" class="btn-cta">
                                    <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                                </a>
                            <?php else: ?>
                                <a href="register.php" class="btn-cta">
                                    <i class="fas fa-rocket me-2"></i>Get Started Free
                                </a>
                                <a href="login.php" class="btn-outline-cta">
                                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <div class="position-relative">
                            <div class="glass-card p-4 fade-in" style="max-width: 400px; margin: 0 auto;">
                                <div class="text-center">
                                    <div class="feature-icon mx-auto mb-3">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                    <h4 class="text-white mb-3">Secure & Reliable</h4>
                                    <p class="text-white-50 mb-0">Enterprise-grade security with advanced encryption and user management</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Stats Section -->
    <section class="stats-section" id="about">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card fade-in">
                        <div class="stat-number">1000+</div>
                        <h6 class="text-muted">Active Users</h6>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card fade-in">
                        <div class="stat-number">50+</div>
                        <h6 class="text-muted">Mod APKs</h6>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card fade-in">
                        <div class="stat-number">99.9%</div>
                        <h6 class="text-muted">Uptime</h6>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card fade-in">
                        <div class="stat-number">24/7</div>
                        <h6 class="text-muted">Support</h6>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="py-5" id="features">
        <div class="container">
            <div class="row mb-5">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="display-5 fw-bold mb-3 fade-in">Powerful Features</h2>
                    <p class="lead text-muted fade-in">Everything you need to manage mod APKs professionally</p>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4 mb-4">
                    <div class="feature-card fade-in">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h5 class="mb-3">User Management</h5>
                        <p class="text-muted">Complete user lifecycle management with roles, permissions, and referral system.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-card fade-in">
                        <div class="feature-icon">
                            <i class="fas fa-key"></i>
                        </div>
                        <h5 class="mb-3">License Keys</h5>
                        <p class="text-muted">Generate, manage, and distribute license keys with advanced tracking and analytics.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-card fade-in">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h5 class="mb-3">APK Management</h5>
                        <p class="text-muted">Upload, organize, and distribute mod APKs with version control and metadata.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-card fade-in">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h5 class="mb-3">Analytics</h5>
                        <p class="text-muted">Comprehensive analytics and reporting for downloads, users, and revenue.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-card fade-in">
                        <div class="feature-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <h5 class="mb-3">Payment System</h5>
                        <p class="text-muted">Integrated payment processing with balance management and transaction history.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="feature-card fade-in">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h5 class="mb-3">Security</h5>
                        <p class="text-muted">Enterprise-grade security with encryption, secure authentication, and audit logs.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-crown me-2"></i>Multi Panel</h5>
                    <p class="text-muted">Professional Mod APK Management System</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0">&copy; 2024 Multi Panel. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dark mode functionality
        function toggleDarkMode() {
            const body = document.body;
            const icon = document.getElementById('darkModeIcon');
            
            if (body.getAttribute('data-theme') === 'dark') {
                body.removeAttribute('data-theme');
                icon.className = 'fas fa-moon';
                localStorage.setItem('theme', 'light');
            } else {
                body.setAttribute('data-theme', 'dark');
                icon.className = 'fas fa-sun';
                localStorage.setItem('theme', 'dark');
            }
        }
        
        // Load saved theme
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.setAttribute('data-theme', 'dark');
            document.getElementById('darkModeIcon').className = 'fas fa-sun';
        }
        
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
        
        // Fade in animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);
        
        // Observe all fade-in elements
        document.addEventListener('DOMContentLoaded', function() {
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach(el => {
                observer.observe(el);
            });
            
            // Add some delay to hero elements
            const heroElements = document.querySelectorAll('.hero-content .fade-in');
            heroElements.forEach((el, index) => {
                setTimeout(() => {
                    el.classList.add('visible');
                }, index * 200);
            });
        });
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>