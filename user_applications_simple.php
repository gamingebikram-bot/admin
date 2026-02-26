<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's purchased keys with mod APK information
$stmt = $pdo->prepare("SELECT lk.*, m.name as mod_name, m.description, ma.file_name, ma.file_path, ma.uploaded_at
                      FROM license_keys lk 
                      LEFT JOIN mods m ON lk.mod_id = m.id 
                      LEFT JOIN mod_apks ma ON m.id = ma.mod_id
                      WHERE lk.sold_to = ? 
                      ORDER BY lk.sold_at DESC");
$stmt->execute([$userId]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper functions
function formatDate($date) {
    return date('d M Y H:i', strtotime($date));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - Mod APK Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 0;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        .main-content {
            padding: 2rem;
        }
        .app-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            transition: transform 0.3s;
        }
        .app-card:hover {
            transform: translateY(-3px);
        }
        .badge {
            font-size: 0.8em;
        }
        .license-key {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="p-3">
                    <h4><i class="fas fa-user me-2"></i>User Panel</h4>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="user_dashboard_simple.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                    <a class="nav-link" href="user_manage_keys_simple.php">
                        <i class="fas fa-key"></i>Manage Keys
                    </a>
                    <a class="nav-link" href="user_generate_simple.php">
                        <i class="fas fa-plus"></i>Generate
                    </a>
                    <a class="nav-link" href="user_balance_simple.php">
                        <i class="fas fa-wallet"></i>Balance
                    </a>
                    <a class="nav-link" href="user_transactions_simple.php">
                        <i class="fas fa-exchange-alt"></i>Transaction
                    </a>
                    <a class="nav-link active" href="user_applications_simple.php">
                        <i class="fas fa-mobile-alt"></i>Applications
                    </a>
                    <a class="nav-link" href="user_settings_simple.php">
                        <i class="fas fa-cog"></i>Settings
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-mobile-alt me-2"></i>My Applications</h2>
                    <div class="d-flex align-items-center">
                        <span class="me-3">Welcome, <?php echo htmlspecialchars($user['username']); ?></span>
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <span class="text-white fw-bold"><?php echo strtoupper(substr($user['username'], 0, 2)); ?></span>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($applications)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-mobile-alt fa-5x text-muted mb-4"></i>
                    <h4 class="text-muted">No Applications Yet</h4>
                    <p class="text-muted">You haven't purchased any mod applications yet.</p>
                    <a href="user_manage_keys_simple.php" class="btn btn-primary">
                        <i class="fas fa-shopping-cart me-2"></i>Browse Available Keys
                    </a>
                </div>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                    <div class="app-card">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="text-primary mb-2"><?php echo htmlspecialchars($app['mod_name']); ?></h5>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars($app['description'] ?: 'No description available'); ?></p>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>License Key:</strong>
                                        <div class="license-key mt-1"><?php echo htmlspecialchars($app['license_key']); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Duration:</strong>
                                        <span class="badge bg-primary">
                                            <?php echo $app['duration'] . ' ' . ucfirst($app['duration_type']); ?>
                                        </span>
                                        <br>
                                        <small class="text-muted">Purchased: <?php echo formatDate($app['sold_at']); ?></small>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
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
                                <i class="fas fa-mobile-alt fa-4x text-muted"></i>
                                <?php if ($app['file_name']): ?>
                                <p class="text-muted mt-2">APK Available</p>
                                <?php else: ?>
                                <p class="text-warning mt-2">APK Pending</p>
                                <?php endif; ?>
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
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('License key copied to clipboard!');
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }
    </script>
</body>
</html>