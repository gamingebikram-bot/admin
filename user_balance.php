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

// Get user transactions
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$userId]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper functions
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

function formatDate($date) {
    return date('d M Y H:i', strtotime($date));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance - Prince Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <style>
        /* Clean modern dashboard design matching the screenshot */
        :root {
            --bg-color: #f8fafc;
            --sidebar-bg: #ffffff;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border-light: #e2e8f0;
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --purple: #8b5cf6;
            --blue: #3b82f6;
            --green: #10b981;
            --orange: #f59e0b;
            --red: #ef4444;
        }
        
        body {
            background-color: var(--bg-color);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: var(--text-dark);
        }
        
        .sidebar {
            background: var(--sidebar-bg);
            min-height: 100vh;
            position: fixed;
            width: 280px;
            z-index: 1000;
            border-right: 1px solid var(--border-light);
            box-shadow: var(--shadow);
        }
        
        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid var(--border-light);
        }
        
        .sidebar-header h4 {
            color: var(--text-dark);
            font-weight: 600;
            margin: 0;
            font-size: 1.1rem;
        }
        
        .sidebar .nav-link {
            color: var(--text-muted);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin: 0.25rem 1rem;
            transition: all 0.2s ease;
            font-weight: 500;
            border: none;
        }
        
        .sidebar .nav-link:hover {
            background-color: #f1f5f9;
            color: var(--text-dark);
        }
        
        .sidebar .nav-link.active {
            background-color: #ede9fe;
            color: var(--purple);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 1rem;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-header h2 {
            color: var(--text-dark);
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: var(--text-muted);
            font-size: 1rem;
        }
        
        .user-info {
            background: var(--card-bg);
            border-radius: 50px;
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--purple);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .balance-card {
            background: linear-gradient(135deg, var(--purple), var(--blue));
            color: white;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            position: relative;
        }
        
        .balance-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: rgba(255,255,255,0.3);
            border-radius: 16px 16px 0 0;
        }
        
        .balance-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .table-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .table-card h5 {
            color: var(--text-dark);
            font-weight: 600;
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .table {
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table thead th {
            background: #fafafa;
            color: var(--text-muted);
            border: none;
            font-weight: 600;
            padding: 1rem;
            font-size: 0.9rem;
        }
        
        .table tbody tr {
            transition: all 0.2s ease;
            background: var(--card-bg);
        }
        
        .table tbody tr:hover {
            background: #fafafa;
        }
        
        .table tbody td {
            padding: 1rem;
            border: none;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-dark);
        }
        
        .badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
            border-radius: 999px;
            font-weight: 500;
        }
        
        /* Mobile Responsiveness */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
            background: var(--card-bg);
            border: 1px solid var(--border-light);
            color: var(--text-dark);
            padding: 10px 12px;
            border-radius: 10px;
            box-shadow: var(--shadow);
        }
        
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 999;
        }
        
        @media (max-width: 991.98px) {
            .sidebar {
                position: fixed;
                top: -100%;
                left: 0;
                right: 0;
                width: 100%;
                transition: top 0.3s ease;
                z-index: 1000;
            }
            
            .sidebar.show {
                top: 0;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
                margin-top: 3rem;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .mobile-overlay.show {
                display: block;
            }
            
            .balance-card {
                padding: 2rem 1.5rem;
            }
            
            .table-card {
                padding: 1.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 0.75rem;
            }
            
            .balance-card {
                padding: 1.5rem 1rem;
            }
            
            .page-header h2 {
                font-size: 1.5rem;
            }
            
            .user-avatar {
                width: 35px;
                height: 35px;
                font-size: 0.8rem;
            }
            
            .table-card {
                padding: 1rem;
            }
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
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <div class="sidebar-header">
                    <h4><i class="fas fa-user me-2"></i>User Panel</h4>
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
                    <a class="nav-link active" href="user_balance.php">
                        <i class="fas fa-wallet"></i>Balance
                    </a>
                    <a class="nav-link" href="user_transactions.php">
                        <i class="fas fa-exchange-alt"></i>Transaction
                    </a>
                    <a class="nav-link" href="user_applications.php">
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
                <!-- Page Header -->
                <div class="page-header d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-wallet me-2"></i>Balance</h2>
                        <p class="page-subtitle">Monitor your account balance and transaction history</p>
                    </div>
                    <div class="user-info d-flex align-items-center">
                        <div class="text-end me-3 d-none d-md-block">
                            <div class="fw-semibold"><?php echo htmlspecialchars($user['username']); ?></div>
                            <small class="text-muted">User Account</small>
                        </div>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Balance Card -->
                <div class="balance-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center mb-3">
                                <div class="balance-icon me-3">
                                    <i class="fas fa-wallet"></i>
                                </div>
                                <div>
                                    <h4 class="mb-1">Current Balance</h4>
                                    <p class="mb-0 opacity-75">Available for purchases</p>
                                </div>
                            </div>
                            <h1 class="display-4 mb-0 fw-bold"><?php echo formatCurrency($user['balance']); ?></h1>
                            <div class="mt-3">
                                <small class="opacity-75">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Your wallet balance updates in real-time
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4 text-center d-none d-md-block">
                            <div class="position-relative">
                                <i class="fas fa-coins fa-4x opacity-25"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Transaction History -->
                <div class="table-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Transaction History</h5>
                        <div class="text-muted small">
                            <i class="fas fa-clock me-1"></i>Last 50 transactions
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th class="d-none d-md-table-cell">Reference</th>
                                    <th>Status</th>
                                    <th class="d-none d-sm-table-cell">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <div class="mb-3">
                                            <i class="fas fa-receipt fa-3x opacity-50"></i>
                                        </div>
                                        <div class="h5 mb-2">No Transactions Yet</div>
                                        <p class="mb-0">Your transaction history will appear here once you make purchases or receive credits.</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-semibold">#<?php echo $transaction['id']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $transaction['type'] === 'purchase' ? 'primary' : ($transaction['type'] === 'balance_add' ? 'success' : 'warning'); ?>">
                                                <i class="fas fa-<?php echo $transaction['type'] === 'purchase' ? 'shopping-cart' : ($transaction['type'] === 'balance_add' ? 'plus' : 'exchange-alt'); ?> me-1"></i>
                                                <?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold <?php echo $transaction['amount'] < 0 ? 'text-danger' : 'text-success'; ?>">
                                                <?php if ($transaction['amount'] < 0): ?>
                                                    <i class="fas fa-minus me-1"></i><?php echo formatCurrency(abs($transaction['amount'])); ?>
                                                <?php else: ?>
                                                    <i class="fas fa-plus me-1"></i><?php echo formatCurrency($transaction['amount']); ?>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td class="d-none d-md-table-cell">
                                            <span class="text-muted"><?php echo htmlspecialchars($transaction['reference'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $transaction['status'] === 'completed' ? 'success' : ($transaction['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                <i class="fas fa-<?php echo $transaction['status'] === 'completed' ? 'check' : ($transaction['status'] === 'pending' ? 'clock' : 'times'); ?> me-1"></i>
                                                <?php echo ucfirst($transaction['status']); ?>
                                            </span>
                                        </td>
                                        <td class="d-none d-sm-table-cell">
                                            <span class="text-muted"><?php echo formatDate($transaction['created_at']); ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (!empty($transactions)): ?>
                    <div class="mt-3 text-center">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Showing last 50 transactions. For complete history, contact support.
                        </small>
                    </div>
                    <?php endif; ?>
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
                if (window.innerWidth <= 991.98) {
                    toggleSidebar();
                }
            });
        });
        
        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 991.98) {
                document.querySelector('.sidebar').classList.remove('show');
                document.querySelector('.mobile-overlay').classList.remove('show');
            }
        });
    </script>
</body>
</html>