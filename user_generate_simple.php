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

$success = '';
$error = '';

// Helper functions
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate - Mod APK Manager</title>
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
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
        }
        .info-card h5 {
            color: #667eea;
            margin-bottom: 1rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 1rem;
            transition: transform 0.3s;
        }
        .feature-card:hover {
            transform: translateY(-3px);
        }
        .icon-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
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
                    <a class="nav-link active" href="user_generate_simple.php">
                        <i class="fas fa-plus"></i>Generate
                    </a>
                    <a class="nav-link" href="user_balance_simple.php">
                        <i class="fas fa-wallet"></i>Balance
                    </a>
                    <a class="nav-link" href="user_transactions_simple.php">
                        <i class="fas fa-exchange-alt"></i>Transaction
                    </a>
                    <a class="nav-link" href="user_applications_simple.php">
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
                    <h2><i class="fas fa-plus me-2"></i>Generate</h2>
                    <div class="d-flex align-items-center">
                        <span class="me-3">Balance: <?php echo formatCurrency($user['balance']); ?></span>
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <span class="text-white fw-bold"><?php echo strtoupper(substr($user['username'], 0, 2)); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Information Card -->
                <div class="info-card">
                    <h5><i class="fas fa-info-circle me-2"></i>How to Get License Keys</h5>
                    <p class="mb-3">To get license keys for mod applications, you need to purchase them from the available keys section. Here's how the process works:</p>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="feature-card text-center">
                                <div class="icon-circle bg-primary text-white mx-auto">
                                    <i class="fas fa-search"></i>
                                </div>
                                <h6>Browse Available Keys</h6>
                                <p class="text-muted small">Go to "Manage Keys" to see all available license keys for different mod applications.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="feature-card text-center">
                                <div class="icon-circle bg-success text-white mx-auto">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <h6>Purchase Keys</h6>
                                <p class="text-muted small">Select the keys you want and purchase them using your account balance.</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="feature-card text-center">
                                <div class="icon-circle bg-warning text-white mx-auto">
                                    <i class="fas fa-download"></i>
                                </div>
                                <h6>Download & Use</h6>
                                <p class="text-muted small">After purchase, you can download the APK and use your license key to activate the mod.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="user_manage_keys_simple.php" class="btn btn-primary">
                            <i class="fas fa-key me-2"></i>Browse Available Keys
                        </a>
                    </div>
                </div>
                
                <!-- Account Balance Info -->
                <div class="info-card">
                    <h5><i class="fas fa-wallet me-2"></i>Account Balance</h5>
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h3 class="text-primary mb-2"><?php echo formatCurrency($user['balance']); ?></h3>
                            <p class="text-muted mb-0">Available for purchasing license keys</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <i class="fas fa-wallet fa-4x text-muted"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Referral Information -->
                <div class="info-card">
                    <h5><i class="fas fa-gift me-2"></i>Earn with Referrals</h5>
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h6>Your Referral Code: <code><?php echo htmlspecialchars($user['referral_code']); ?></code></h6>
                            <p class="text-muted mb-2">Share this code with friends to earn rewards when they register!</p>
                            <button class="btn btn-outline-primary btn-sm" 
                                    onclick="copyToClipboard('<?php echo htmlspecialchars($user['referral_code']); ?>')">
                                <i class="fas fa-copy me-1"></i>Copy Referral Code
                            </button>
                        </div>
                        <div class="col-md-4 text-center">
                            <i class="fas fa-gift fa-4x text-muted"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Referral code copied to clipboard!');
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }
    </script>
</body>
</html>