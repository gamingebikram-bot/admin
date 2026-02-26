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
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 100");
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
    <title>Transactions - Prince Panel</title>
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
        
        [data-theme="dark"] {
            --bg-primary: #1a202c;
            --bg-secondary: #2d3748;
            --text-primary: #f7fafc;
            --text-secondary: #e2e8f0;
            --text-muted: #a0aec0;
            --border-color: #4a5568;
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
        
        .table {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border-light);
        }
        
        .table thead th {
            background-color: var(--purple);
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
        }
        
        .table tbody tr {
            background-color: var(--white);
            transition: all 0.2s ease;
        }
        
        .table tbody tr:hover {
            background-color: #f8fafc;
        }
        
        .table tbody td {
            padding: 1rem;
            border: none;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-dark);
        }
        
        .badge {
            font-size: 0.85em;
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .dark-mode-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
            background-color: var(--white);
            border: 1px solid var(--border-light);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--text-dark);
        }
        
        .dark-mode-toggle:hover {
            background-color: var(--purple);
            color: white;
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
        

        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background-color: var(--white);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .stat-card.total::before { background-color: var(--purple); }
        .stat-card.completed::before { background-color: var(--success); }
        .stat-card.pending::before { background-color: var(--warning); }
        .stat-card.amount::before { background-color: var(--info); }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.2rem;
        }
        
        .stat-icon.total { background-color: var(--purple); }
        .stat-icon.completed { background-color: var(--success); }
        .stat-icon.pending { background-color: var(--warning); }
        .stat-icon.amount { background-color: var(--info); }
        
        .stat-title {
            color: var(--text-light);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .stat-value {
            color: var(--text-dark);
            font-size: 1.5rem;
            font-weight: 700;
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
            
            .card {
                padding: 1rem;
            }
            
            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 1em;
            }
            
            .dark-mode-toggle {
                top: 15px;
                right: 15px;
                width: 45px;
                height: 45px;
            }
            
            .table-responsive {
                font-size: 0.9em;
            }
            
            .table thead th,
            .table tbody td {
                padding: 0.75rem 0.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
            }
            
            .stat-card {
                padding: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 0.5rem;
            }
            
            .page-header {
                padding: 1rem;
            }
            
            .card {
                padding: 0.75rem;
            }
            
            .table {
                font-size: 0.8em;
            }
            
            .table thead th,
            .table tbody td {
                padding: 0.5rem 0.25rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Dark Mode Toggle -->
    <button class="dark-mode-toggle" onclick="toggleDarkMode()" title="Toggle Dark Mode">
        <i class="fas fa-moon" id="darkModeIcon"></i>
    </button>
    
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
                    <a class="nav-link active" href="user_transactions.php">
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
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2" style="color: var(--purple); font-weight: 600;">
                                <i class="fas fa-exchange-alt me-2"></i>Transaction History
                            </h2>
                            <p class="text-muted mb-0">Complete history of all your transactions and activities.</p>
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
                
                <?php
                // Calculate transaction statistics
                $totalTransactions = count($transactions);
                $completedTransactions = 0;
                $pendingTransactions = 0;
                $totalAmount = 0;
                
                foreach ($transactions as $transaction) {
                    if ($transaction['status'] === 'completed') {
                        $completedTransactions++;
                        $totalAmount += $transaction['amount'];
                    } elseif ($transaction['status'] === 'pending') {
                        $pendingTransactions++;
                    }
                }
                ?>
                
                <!-- Transaction Statistics -->
                <div class="stats-grid">
                    <div class="stat-card total">
                        <div class="stat-icon total">
                            <i class="fas fa-list"></i>
                        </div>
                        <div class="stat-title">Total Transactions</div>
                        <div class="stat-value"><?php echo $totalTransactions; ?></div>
                    </div>
                    <div class="stat-card completed">
                        <div class="stat-icon completed">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="stat-title">Completed</div>
                        <div class="stat-value text-success"><?php echo $completedTransactions; ?></div>
                    </div>
                    <div class="stat-card pending">
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-title">Pending</div>
                        <div class="stat-value text-warning"><?php echo $pendingTransactions; ?></div>
                    </div>
                    <div class="stat-card amount">
                        <div class="stat-icon amount">
                            <i class="fas fa-rupee-sign"></i>
                        </div>
                        <div class="stat-title">Net Amount</div>
                        <div class="stat-value <?php echo $totalAmount >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo formatCurrency($totalAmount); ?>
                        </div>
                    </div>
                </div>
                
                <div class="card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="mb-0" style="color: var(--purple); font-weight: 600;">
                            <i class="fas fa-list me-2"></i>All Transactions
                        </h5>
                        <div class="text-muted small">
                            <i class="fas fa-info-circle me-1"></i>Last 100 transactions
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
                                            <i class="fas fa-exchange-alt fa-3x opacity-50"></i>
                                        </div>
                                        <div class="h5 mb-2">No Transactions Found</div>
                                        <p class="mb-0">You haven't made any transactions yet. Start by purchasing license keys or adding balance to your account.</p>
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
                                            <span class="text-muted small"><?php echo htmlspecialchars($transaction['reference'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $transaction['status'] === 'completed' ? 'success' : ($transaction['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                <i class="fas fa-<?php echo $transaction['status'] === 'completed' ? 'check' : ($transaction['status'] === 'pending' ? 'clock' : 'times'); ?> me-1"></i>
                                                <?php echo ucfirst($transaction['status']); ?>
                                            </span>
                                        </td>
                                        <td class="d-none d-sm-table-cell">
                                            <span class="text-muted small"><?php echo formatDate($transaction['created_at']); ?></span>
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
                            Showing last 100 transactions. For older records, contact support.
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
    </script>
</body>
</html>