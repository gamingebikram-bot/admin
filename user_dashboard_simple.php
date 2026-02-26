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

// Check if user is admin (redirect to admin panel)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit();
}

$pdo = getDBConnection();

// Get user data
$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user statistics
$userStats = [
    'total_purchases' => 0,
    'total_spent' => 0
];

try {
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_purchases,
        SUM(ABS(amount)) as total_spent
        FROM transactions 
        WHERE user_id = ? AND type = 'purchase' AND status = 'completed'");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $userStats = $stats ?: $userStats;
} catch (Exception $e) {
    // Ignore errors
}

// Get recent transactions
$recentTransactions = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore errors
}

// Get available mods
$mods = [];
try {
    $stmt = $pdo->query("SELECT * FROM mods WHERE status = 'active' ORDER BY name");
    $mods = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore errors
}

// Helper functions
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

function formatDate($date) {
    if (empty($date)) return 'N/A';
    $timestamp = strtotime($date);
    if ($timestamp === false) return 'Invalid Date';
    return date('d M Y H:i', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Mod APK Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
            position: fixed;
            width: 280px;
            z-index: 1000;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 15px 25px;
            border-radius: 12px;
            margin: 4px 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s;
        }
        
        .sidebar .nav-link:hover::before {
            left: 100%;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
        }
        
        .stats-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .stats-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .stats-card.balance::before { background: linear-gradient(90deg, #28a745, #20c997); }
        .stats-card.purchases::before { background: linear-gradient(90deg, #17a2b8, #6f42c1); }
        .stats-card.spent::before { background: linear-gradient(90deg, #ffc107, #fd7e14); }
        
        .table-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-top: 2rem;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .table-card h5 {
            color: #667eea;
            margin-bottom: 1.5rem;
            font-weight: 600;
            font-size: 1.3em;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .badge {
            font-size: 0.8em;
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .mod-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255,255,255,0.2);
            position: relative;
            overflow: hidden;
        }
        
        .mod-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .mod-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .page-header {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2em;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8em;
            margin-bottom: 1rem;
        }
        
        .stats-icon.balance { background: linear-gradient(135deg, #28a745, #20c997); }
        .stats-icon.purchases { background: linear-gradient(135deg, #17a2b8, #6f42c1); }
        .stats-icon.spent { background: linear-gradient(135deg, #ffc107, #fd7e14); }
        
        .floating-animation {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .fade-in {
            animation: fadeIn 0.6s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .table {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
            transform: scale(1.01);
        }
        
        .table tbody td {
            padding: 1rem;
            border: none;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: fixed;
                top: -100%;
                left: 0;
                transition: top 0.3s ease;
                z-index: 9999;
            }
            
            .sidebar.show {
                top: 0;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .page-header {
                padding: 1.5rem;
                margin-bottom: 1rem;
            }
            
            .page-header h2 {
                font-size: 1.5rem;
            }
            
            .stats-card {
                padding: 1.5rem;
                margin-bottom: 1rem;
            }
            
            .stats-icon {
                width: 50px;
                height: 50px;
                font-size: 1.5em;
            }
            
            .table-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .table-card h5 {
                font-size: 1.1em;
            }
            
            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 1em;
            }
            
            .mobile-menu-btn {
                display: block;
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 10000;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border: none;
                color: white;
                padding: 12px;
                border-radius: 50%;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            }
            
            .mobile-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 9998;
            }
            
            .mobile-overlay.show {
                display: block;
            }
            
            .table-responsive {
                font-size: 0.9em;
            }
            
            .table thead th,
            .table tbody td {
                padding: 0.5rem;
            }
            
            .btn {
                padding: 8px 16px;
                font-size: 0.9em;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 0.5rem;
            }
            
            .page-header {
                padding: 1rem;
            }
            
            .stats-card {
                padding: 1rem;
            }
            
            .stats-card h2 {
                font-size: 1.5rem;
            }
            
            .table-card {
                padding: 0.5rem;
            }
            
            .table {
                font-size: 0.8em;
            }
            
            .table thead th,
            .table tbody td {
                padding: 0.3rem;
            }
        }
        
        .mobile-menu-btn {
            display: none;
        }
        
        .mobile-overlay {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" onclick="toggleSidebar()"></div>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="p-3">
                    <h4><i class="fas fa-user me-2"></i>User Panel</h4>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="user_dashboard_simple.php">
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
                <div class="page-header fade-in">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h2>
                            <p class="text-muted mb-0">Welcome back! Here's what's happening with your account.</p>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="text-end me-3">
                                <div class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                                <small class="text-muted">User Account</small>
                            </div>
                            <div class="user-avatar floating-animation">
                                <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row mb-4 fade-in">
                    <div class="col-md-4 mb-3">
                        <div class="stats-card balance">
                            <div class="text-center">
                                <div class="stats-icon balance mx-auto">
                                    <i class="fas fa-wallet text-white"></i>
                                </div>
                                <h6 class="text-muted mb-2">Current Balance</h6>
                                <h2 class="mb-0 fw-bold"><?php echo formatCurrency($user['balance']); ?></h2>
                                <small class="text-success">
                                    <i class="fas fa-arrow-up me-1"></i>Available for purchases
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stats-card purchases">
                            <div class="text-center">
                                <div class="stats-icon purchases mx-auto">
                                    <i class="fas fa-shopping-cart text-white"></i>
                                </div>
                                <h6 class="text-muted mb-2">Total Purchases</h6>
                                <h2 class="mb-0 fw-bold"><?php echo $userStats['total_purchases'] ?: 0; ?></h2>
                                <small class="text-info">
                                    <i class="fas fa-shopping-bag me-1"></i>License keys bought
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stats-card spent">
                            <div class="text-center">
                                <div class="stats-icon spent mx-auto">
                                    <i class="fas fa-rupee-sign text-white"></i>
                                </div>
                                <h6 class="text-muted mb-2">Total Spent</h6>
                                <h2 class="mb-0 fw-bold"><?php echo formatCurrency($userStats['total_spent'] ?: 0); ?></h2>
                                <small class="text-warning">
                                    <i class="fas fa-chart-line me-1"></i>All time spending
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Available Mods -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="table-card">
                            <h5><i class="fas fa-mobile-alt me-2"></i>Available Mods</h5>
                            <div class="row">
                                <?php foreach ($mods as $mod): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="mod-card">
                                        <h6 class="text-primary"><?php echo htmlspecialchars($mod['name']); ?></h6>
                                        <p class="text-muted mb-2"><?php echo htmlspecialchars($mod['description'] ?: 'No description available'); ?></p>
                                        <a href="user_manage_keys_simple.php?mod_id=<?php echo $mod['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-key me-1"></i>View Keys
                                        </a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="table-card">
                            <h5><i class="fas fa-history me-2"></i>Recent Transactions</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentTransactions)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center text-muted">No transactions yet</td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentTransactions as $transaction): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-<?php echo $transaction['type'] === 'purchase' ? 'primary' : 'success'; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?>
                                                    </span>
                                                </td>
                                                <td class="<?php echo $transaction['amount'] < 0 ? 'text-danger' : 'text-success'; ?>">
                                                    <?php echo $transaction['amount'] < 0 ? formatCurrency(abs($transaction['amount'])) : formatCurrency($transaction['amount']); ?>
                                                </td>
                                                <td><?php echo formatDate($transaction['created_at']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.mobile-overlay');
            
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }
        
        // Close sidebar when clicking on nav links on mobile
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    toggleSidebar();
                }
            });
        });
        
        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                document.querySelector('.sidebar').classList.remove('show');
                document.querySelector('.mobile-overlay').classList.remove('show');
            }
        });
    </script>
</body>
</html>