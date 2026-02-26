<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireUser();

$pdo = getDBConnection();
$user = getUserData();

// Get user's purchased keys with mod APK information
$stmt = $pdo->prepare("SELECT lk.*, m.name as mod_name, m.description, ma.file_name, ma.file_path, ma.uploaded_at
                      FROM license_keys lk 
                      LEFT JOIN mods m ON lk.mod_id = m.id 
                      LEFT JOIN mod_apks ma ON m.id = ma.mod_id
                      WHERE lk.sold_to = ? 
                      ORDER BY lk.sold_at DESC");
$stmt->execute([$user['id']]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - Prince Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        :root {
            --bg-color: #f8fafc;
            --sidebar-bg: #ffffff;
            --purple: #8b5cf6;
            --purple-hover: #7c3aed;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --border-light: #e2e8f0;
            --white: #ffffff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
        }
        
        * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        body {
            background-color: var(--bg-color);
            font-family: 'Inter', sans-serif;
            color: var(--text-dark);
        }
        
        .sidebar {
            background-color: var(--sidebar-bg);
            border-right: 1px solid var(--border-light);
            min-height: 100vh;
            position: fixed;
            width: 280px;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: transform 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transform: translateX(0);
        }
        
        .sidebar.hidden {
            transform: translateX(-100%);
        }
        
        .sidebar .nav-link {
            color: var(--text-light);
            padding: 12px 20px;
            margin: 2px 16px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        
        .sidebar .nav-link:hover {
            background-color: #f1f5f9;
            color: var(--purple);
        }
        
        .sidebar .nav-link.active {
            background-color: var(--purple);
            color: var(--white);
        }
        
        .sidebar .nav-link i {
            width: 22px;
            margin-right: 12px;
            font-size: 1.1em;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        .main-content.full-width {
            margin-left: 0;
        }
        
        .mobile-header {
            display: none;
            background-color: var(--white);
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 999;
            border-bottom: 1px solid var(--border-light);
        }
        
        .mobile-toggle {
            background-color: var(--purple);
            border: none;
            color: white;
            padding: 0.75rem;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .balance-badge {
            background-color: var(--purple);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .card {
            background-color: var(--white);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }
        
        .card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .page-header {
            background-color: var(--white);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--purple);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2em;
        }
        
        .app-card {
            background-color: var(--white);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .app-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background-color: var(--purple);
        }
        
        .app-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .license-key {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            background-color: #f8fafc;
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid var(--border-light);
            color: var(--text-dark);
            word-break: break-all;
        }
        
        .btn-primary {
            background-color: var(--purple);
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--purple-hover);
            transform: translateY(-1px);
        }
        
        .btn-outline-primary {
            border: 1px solid var(--purple);
            color: var(--purple);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--purple);
            border-color: var(--purple);
        }
        
        .badge {
            font-size: 0.85em;
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        
        .overlay.show {
            display: block;
        }
        
        /* Mobile Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                transform: translateX(-100%);
                background-color: var(--sidebar-bg);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .mobile-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .page-header {
                padding: 1.5rem;
                margin-bottom: 1rem;
            }
            
            .app-card {
                padding: 1rem;
            }
            
            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 1em;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 0.5rem;
            }
            
            .page-header {
                padding: 1rem;
            }
            
            .app-card {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>
    
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="d-flex align-items-center">
            <button class="mobile-toggle me-3" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h5 class="mb-0"><i class="fas fa-crown me-2" style="color: var(--purple);"></i>Prince Panel</h5>
        </div>
        <div class="d-flex align-items-center">
            <span class="balance-badge d-none d-sm-inline"><?php echo formatCurrency($user['balance']); ?></span>
            <div class="user-avatar ms-2" style="width: 35px; height: 35px; font-size: 0.9em;">
                <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
            </div>
        </div>
    </div>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <div class="p-4 border-bottom border-light">
                    <h4 class="mb-1" style="color: var(--purple); font-weight: 700;">
                        <i class="fas fa-crown me-2"></i>Prince Panel
                    </h4>
                    <p class="text-muted small mb-0">User Dashboard</p>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="user_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                    <a class="nav-link" href="user_manage_keys.php">
                        <i class="fas fa-key"></i>Manage Keys
                    </a>
                    <a class="nav-link" href="user_generate.php">
                        <i class="fas fa-plus"></i>Generate
                    </a>
                    <a class="nav-link" href="user_balance.php">
                        <i class="fas fa-wallet"></i>Balance
                    </a>
                    <a class="nav-link" href="user_transactions.php">
                        <i class="fas fa-exchange-alt"></i>Transaction
                    </a>
                    <a class="nav-link active" href="user_applications.php">
                        <i class="fas fa-mobile-alt"></i>Applications
                    </a>
                    <a class="nav-link" href="user_settings.php">
                        <i class="fas fa-cog"></i>Settings
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content" id="mainContent">
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2" style="color: var(--purple); font-weight: 600;">
                                <i class="fas fa-mobile-alt me-2"></i>My Applications
                            </h2>
                            <p class="text-muted mb-0">Manage and download your purchased mod applications.</p>
                        </div>
                        <div class="d-none d-md-flex align-items-center">
                            <div class="text-end me-3">
                                <div class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                                <small class="text-muted">Balance: <?php echo formatCurrency($user['balance']); ?></small>
                            </div>
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($applications)): ?>
                <div class="card p-5">
                    <div class="text-center">
                        <div class="mb-4">
                            <i class="fas fa-mobile-alt fa-5x text-muted opacity-50"></i>
                        </div>
                        <h4 class="text-muted mb-3">No Applications Yet</h4>
                        <p class="text-muted mb-4">You haven't purchased any mod applications yet. Browse available license keys to get started.</p>
                        <a href="user_manage_keys.php" class="btn btn-primary">
                            <i class="fas fa-shopping-cart me-2"></i>Browse Available Keys
                        </a>
                    </div>
                </div>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                    <div class="app-card">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 style="color: var(--purple); font-weight: 600; margin-bottom: 0.75rem;">
                                    <?php echo htmlspecialchars($app['mod_name']); ?>
                                </h5>
                                <p class="text-muted mb-3"><?php echo htmlspecialchars($app['description'] ?: 'No description available'); ?></p>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <strong style="color: var(--text-dark);">License Key:</strong>
                                        </div>
                                        <div class="license-key"><?php echo htmlspecialchars($app['license_key']); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <strong style="color: var(--text-dark);">Duration:</strong>
                                            <span class="badge bg-primary ms-2">
                                                <?php echo $app['duration'] . ' ' . ucfirst($app['duration_type']); ?>
                                            </span>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            Purchased: <?php echo formatDate($app['sold_at']); ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-outline-primary btn-sm" 
                                            onclick="copyToClipboard('<?php echo htmlspecialchars($app['license_key']); ?>')">
                                        <i class="fas fa-copy me-1"></i>Copy Key
                                    </button>
                                    <?php if ($app['file_name'] && file_exists($app['file_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($app['file_path']); ?>" 
                                       class="btn btn-primary btn-sm" download>
                                        <i class="fas fa-download me-1"></i>Download APK
                                    </a>
                                    <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled>
                                        <i class="fas fa-exclamation-triangle me-1"></i>APK Not Available
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="d-flex flex-column align-items-center">
                                    <?php if ($app['file_name']): ?>
                                        <div class="mb-3" style="color: var(--success);">
                                            <i class="fas fa-mobile-alt fa-4x"></i>
                                        </div>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>APK Available
                                        </span>
                                    <?php else: ?>
                                        <div class="mb-3" style="color: var(--warning);">
                                            <i class="fas fa-mobile-alt fa-4x"></i>
                                        </div>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-clock me-1"></i>APK Pending
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.overlay');
            
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }
        
        // Close sidebar when clicking on nav links on mobile
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 991.98) {
                    toggleSidebar();
                }
            });
        });
        
        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 991.98) {
                document.querySelector('.sidebar').classList.remove('show');
                document.querySelector('.overlay').classList.remove('show');
            }
        });
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Create a simple toast notification
                const toast = document.createElement('div');
                toast.className = 'alert alert-success position-fixed';
                toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 250px;';
                toast.innerHTML = '<i class="fas fa-check me-2"></i>License key copied to clipboard!';
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            }, function(err) {
                console.error('Could not copy text: ', err);
                alert('Could not copy license key. Please copy manually.');
            });
        }
    </script>
</body>
</html>