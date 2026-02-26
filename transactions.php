<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

// Debug: Check if we reach this point
if (!isset($_SESSION)) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    // If not logged in, redirect instead of showing white screen
    header('Location: login.php');
    exit();
}

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    $error = 'Database connection failed: ' . $e->getMessage();
    $pdo = null;
}

// Get filter parameters
$filters = [
    'user_id' => $_GET['user_id'] ?? '',
    'type' => $_GET['type'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Get all users for filter dropdown
try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT id, username FROM users WHERE role = 'user' ORDER BY username");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $users = [];
    }
} catch (Exception $e) {
    $users = [];
    $error = 'Failed to fetch users: ' . $e->getMessage();
}

// Get transactions
try {
    if ($pdo) {
        $transactions = getAllTransactions($filters);
    } else {
        $transactions = [];
    }
} catch (Exception $e) {
    $transactions = [];
    $error = 'Failed to fetch transactions: ' . $e->getMessage();
}

// Get transaction statistics
try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT 
            COUNT(*) as total_transactions,
            SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_income,
            SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_expenses,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_transactions,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_transactions,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_transactions
            FROM transactions");
        $transactionStats = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $transactionStats = [
            'total_transactions' => 0,
            'total_income' => 0,
            'total_expenses' => 0,
            'completed_transactions' => 0,
            'pending_transactions' => 0,
            'failed_transactions' => 0
        ];
    }
} catch (Exception $e) {
    $transactionStats = [
        'total_transactions' => 0,
        'total_income' => 0,
        'total_expenses' => 0,
        'completed_transactions' => 0,
        'pending_transactions' => 0,
        'failed_transactions' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - Multi Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --purple: #8b5cf6;
            --purple-light: #a78bfa;
            --purple-dark: #7c3aed;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-light: #e2e8f0;
            --shadow-light: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-medium: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
        }
        
        [data-theme="dark"] {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-light: #334155;
        }
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        .sidebar {
            background-color: var(--card-bg);
            border-right: 1px solid var(--border-light);
            min-height: 100vh;
            position: fixed;
            width: 280px;
            z-index: 1000;
            box-shadow: var(--shadow-light);
        }
        
        .sidebar .nav-link {
            color: var(--text-secondary);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 16px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .sidebar .nav-link:hover {
            background-color: var(--purple);
            color: white;
        }
        
        .sidebar .nav-link.active {
            background-color: var(--purple);
            color: white;
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 1em;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }
        
        .page-header {
            background-color: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-light);
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--purple);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1em;
        }
        
        .stats-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            border-left: 4px solid;
            margin-bottom: 1.5rem;
            transition: transform 0.2s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-card.total-transactions { border-left-color: var(--purple); }
        .stats-card.total-income { border-left-color: var(--success); }
        .stats-card.total-expenses { border-left-color: var(--danger); }
        .stats-card.completed-transactions { border-left-color: var(--info); }
        
        .filter-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            margin-bottom: 1.5rem;
        }
        
        .filter-header {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .transactions-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
        }
        
        .transactions-header {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .table {
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border-light);
        }
        
        .table thead th {
            background-color: var(--purple);
            color: white;
            border: none;
            font-weight: 600;
            padding: 12px;
        }
        
        .table tbody tr {
            transition: background-color 0.2s ease;
        }
        
        .table tbody tr:hover {
            background-color: rgba(139, 92, 246, 0.05);
        }
        
        .table tbody td {
            padding: 12px;
            border: none;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-primary);
            vertical-align: middle;
        }
        
        .form-control {
            border-radius: 8px;
            border: 1px solid var(--border-light);
            padding: 0.75rem;
            transition: all 0.2s ease;
            color: var(--text-primary);
            background-color: var(--card-bg);
        }
        
        .form-control:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 0.2rem rgba(139, 92, 246, 0.25);
            background-color: var(--card-bg);
            color: var(--text-primary);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .btn {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
            padding: 0.75rem 1.5rem;
        }
        
        .btn-primary {
            background-color: var(--purple);
            border-color: var(--purple);
        }
        
        .btn-primary:hover {
            background-color: var(--purple-dark);
            border-color: var(--purple-dark);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background-color: var(--text-secondary);
            border-color: var(--text-secondary);
        }
        
        .btn-outline-secondary {
            color: var(--text-secondary);
            border-color: var(--border-light);
        }
        
        .badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
        }
        
        .transaction-type {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .transaction-amount {
            font-weight: 600;
            font-size: 1.1em;
        }
        
        .amount-positive {
            color: var(--success);
        }
        
        .amount-negative {
            color: var(--danger);
        }
        
        /* Theme toggle button */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
            background-color: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-light);
        }
        
        .theme-toggle:hover {
            transform: scale(1.1);
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
            
            .mobile-menu-btn {
                display: block;
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 10000;
                background-color: var(--purple);
                border: none;
                color: white;
                padding: 12px;
                border-radius: 8px;
                box-shadow: var(--shadow-light);
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
    <!-- Theme Toggle Button -->
    <div class="theme-toggle" onclick="toggleTheme()" title="Toggle Dark Mode">
        <i class="fas fa-sun" id="theme-icon"></i>
    </div>
    
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
                <div class="position-sticky">
                    <h4 class="text-center py-3 border-bottom" style="border-color: var(--border-light) !important; color: var(--purple); font-weight: 600;">
                        <i class="fas fa-shield-alt me-2"></i>Multi Panel
                    </h4>
                <nav class="nav flex-column p-3">
                    <a class="nav-link" href="admin_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                    <a class="nav-link" href="add_mod.php">
                        <i class="fas fa-plus"></i>Add Mod Name
                    </a>
                    <a class="nav-link" href="manage_mods.php">
                        <i class="fas fa-edit"></i>Manage Mods
                    </a>
                    <a class="nav-link" href="upload_mod.php">
                        <i class="fas fa-upload"></i>Upload Mod APK
                    </a>
                    <a class="nav-link" href="mod_list.php">
                        <i class="fas fa-list"></i>Mod APK List
                    </a>
                    <a class="nav-link" href="add_license.php">
                        <i class="fas fa-key"></i>Add License Key
                    </a>
                    <a class="nav-link" href="licence_key_list.php">
                        <i class="fas fa-key"></i>License Key List
                    </a>
                    <a class="nav-link" href="available_keys.php">
                        <i class="fas fa-key"></i>Available Keys
                    </a>
                    <a class="nav-link" href="manage_users.php">
                        <i class="fas fa-users"></i>Manage Users
                    </a>
                    <a class="nav-link" href="add_balance.php">
                        <i class="fas fa-wallet"></i>Add Balance
                    </a>
                    <a class="nav-link active" href="transactions.php">
                        <i class="fas fa-exchange-alt"></i>Transaction
                    </a>
                    <a class="nav-link" href="referral_codes.php">
                        <i class="fas fa-tag"></i>Referral Code
                    </a>
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog"></i>Settings
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a>
                </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2"><i class="fas fa-exchange-alt me-2" style="color: var(--purple);"></i>Transaction History</h2>
                            <p class="mb-0" style="color: var(--text-secondary);">Monitor all transactions and financial activities</p>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="text-end me-3">
                                <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                                <small style="color: var(--text-secondary);">Admin Account</small>
                            </div>
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stats-card total-transactions">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Total Transactions</h6>
                                    <h3 class="mb-0"><?php echo $transactionStats['total_transactions']; ?></h3>
                                </div>
                                <div style="color: var(--purple);">
                                    <i class="fas fa-exchange-alt fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card total-income">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Total Income</h6>
                                    <h3 class="mb-0"><?php echo formatCurrency($transactionStats['total_income']); ?></h3>
                                </div>
                                <div style="color: var(--success);">
                                    <i class="fas fa-arrow-up fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card total-expenses">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Total Expenses</h6>
                                    <h3 class="mb-0"><?php echo formatCurrency($transactionStats['total_expenses']); ?></h3>
                                </div>
                                <div style="color: var(--danger);">
                                    <i class="fas fa-arrow-down fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card completed-transactions">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Completed</h6>
                                    <h3 class="mb-0"><?php echo $transactionStats['completed_transactions']; ?></h3>
                                    <small style="color: var(--text-secondary);">Pending: <?php echo $transactionStats['pending_transactions']; ?></small>
                                </div>
                                <div style="color: var(--info);">
                                    <i class="fas fa-check-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filter-card">
                    <div class="filter-header">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Transaction Filters</h5>
                        <small>Filter transactions by user, type, status, or search terms</small>
                    </div>
                    
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="user_id" class="form-label"><i class="fas fa-user me-2"></i>Filter by User:</label>
                            <select class="form-control" id="user_id" name="user_id">
                                <option value="">All Users</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                        <?php echo $filters['user_id'] == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="type" class="form-label"><i class="fas fa-tag me-2"></i>Filter by Type:</label>
                            <select class="form-control" id="type" name="type">
                                <option value="">All Types</option>
                                <option value="purchase" <?php echo $filters['type'] === 'purchase' ? 'selected' : ''; ?>>
                                    <i class="fas fa-shopping-cart me-2"></i>Purchase
                                </option>
                                <option value="balance_add" <?php echo $filters['type'] === 'balance_add' ? 'selected' : ''; ?>>
                                    <i class="fas fa-plus me-2"></i>Balance Add
                                </option>
                                <option value="refund" <?php echo $filters['type'] === 'refund' ? 'selected' : ''; ?>>
                                    <i class="fas fa-undo me-2"></i>Refund
                                </option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label"><i class="fas fa-info-circle me-2"></i>Filter by Status:</label>
                            <select class="form-control" id="status" name="status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>
                                    <i class="fas fa-clock me-2"></i>Pending
                                </option>
                                <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>
                                    <i class="fas fa-check me-2"></i>Completed
                                </option>
                                <option value="failed" <?php echo $filters['status'] === 'failed' ? 'selected' : ''; ?>>
                                    <i class="fas fa-times me-2"></i>Failed
                                </option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="search" class="form-label"><i class="fas fa-search me-2"></i>Search Reference or User</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($filters['search']); ?>" 
                                       placeholder="Search transactions...">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-2"></i>Apply Filters
                                </button>
                                <a href="transactions.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Reset Filters
                                </a>
                                <button type="button" class="btn btn-outline-primary" onclick="exportTransactions()">
                                    <i class="fas fa-download me-2"></i>Export
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Transactions Table -->
                <div class="transactions-card">
                    <div class="transactions-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Transaction History</h5>
                                <small>Complete transaction log with filtering options</small>
                            </div>
                            <div>
                                <span class="badge bg-light text-dark"><?php echo count($transactions); ?> Records</span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (empty($transactions)): ?>
                        <div class="empty-state">
                            <i class="fas fa-exchange-alt fa-4x mb-3" style="color: var(--text-secondary);"></i>
                            <h5>No Transactions Found</h5>
                            <p>No transactions match your current filter criteria.</p>
                            <a href="transactions.php" class="btn btn-primary">
                                <i class="fas fa-refresh me-2"></i>View All Transactions
                            </a>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag me-2"></i>ID</th>
                                    <th><i class="fas fa-user me-2"></i>User</th>
                                    <th><i class="fas fa-rupee-sign me-2"></i>Amount</th>
                                    <th><i class="fas fa-tag me-2"></i>Type</th>
                                    <th><i class="fas fa-sticky-note me-2"></i>Reference</th>
                                    <th><i class="fas fa-info-circle me-2"></i>Status</th>
                                    <th><i class="fas fa-calendar me-2"></i>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold"><?php echo $transaction['id']; ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-2" style="width: 32px; height: 32px; border-radius: 50%; background: var(--purple); display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8em; font-weight: 600;">
                                                <?php echo strtoupper(substr($transaction['username'] ?? 'N', 0, 2)); ?>
                                            </div>
                                            <span><?php echo htmlspecialchars($transaction['username'] ?? 'N/A'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="transaction-amount <?php echo $transaction['amount'] < 0 ? 'amount-negative' : 'amount-positive'; ?>">
                                            <?php echo $transaction['amount'] < 0 ? '-' : '+'; ?><?php echo formatCurrency(abs($transaction['amount'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="transaction-type">
                                            <?php 
                                            $typeIcons = [
                                                'purchase' => 'fa-shopping-cart',
                                                'balance_add' => 'fa-plus-circle',
                                                'refund' => 'fa-undo'
                                            ];
                                            $typeColors = [
                                                'purchase' => 'primary',
                                                'balance_add' => 'success',
                                                'refund' => 'warning'
                                            ];
                                            $icon = $typeIcons[$transaction['type']] ?? 'fa-exchange-alt';
                                            $color = $typeColors[$transaction['type']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $color; ?>">
                                                <i class="fas <?php echo $icon; ?> me-1"></i>
                                                <?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="color: var(--text-secondary);">
                                            <?php echo htmlspecialchars($transaction['reference'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $statusColors = [
                                            'completed' => 'success',
                                            'pending' => 'warning',
                                            'failed' => 'danger'
                                        ];
                                        $statusIcons = [
                                            'completed' => 'fa-check-circle',
                                            'pending' => 'fa-clock',
                                            'failed' => 'fa-times-circle'
                                        ];
                                        $color = $statusColors[$transaction['status']] ?? 'secondary';
                                        $icon = $statusIcons[$transaction['status']] ?? 'fa-question-circle';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>">
                                            <i class="fas <?php echo $icon; ?> me-1"></i>
                                            <?php echo ucfirst($transaction['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: var(--text-secondary);">
                                            <?php echo formatDate($transaction['created_at']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Theme Management
        function initTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            updateThemeIcon(savedTheme);
        }
        
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        }
        
        function updateThemeIcon(theme) {
            const icon = document.getElementById('theme-icon');
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
        
        // Mobile Sidebar Management
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.mobile-overlay');
            
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }
        
        // Export Transactions
        function exportTransactions() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            
            showToast('Preparing transaction export...', 'info');
            
            // Create a temporary link for download
            const exportUrl = 'export_transactions.php?' + params.toString();
            const link = document.createElement('a');
            link.href = exportUrl;
            link.download = 'transactions.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            setTimeout(() => {
                showToast('Transaction export ready for download', 'success');
            }, 1000);
        }
        
        // Toast Notification System
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas ${getToastIcon(type)}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            const style = document.createElement('style');
            if (!document.getElementById('toast-styles')) {
                style.id = 'toast-styles';
                style.textContent = `
                    .toast-notification {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        padding: 12px 20px;
                        border-radius: 8px;
                        box-shadow: var(--shadow-medium);
                        z-index: 10001;
                        transform: translateX(100%);
                        transition: all 0.3s ease;
                        max-width: 300px;
                    }
                    
                    .toast-notification.show {
                        transform: translateX(0);
                    }
                    
                    .toast-success {
                        background: #10b981;
                        color: white;
                    }
                    
                    .toast-error {
                        background: #ef4444;
                        color: white;
                    }
                    
                    .toast-warning {
                        background: #f59e0b;
                        color: white;
                    }
                    
                    .toast-info {
                        background: var(--purple);
                        color: white;
                    }
                    
                    .toast-content {
                        display: flex;
                        align-items: center;
                        gap: 8px;
                    }
                `;
                document.head.appendChild(style);
            }
            
            document.body.appendChild(toast);
            
            setTimeout(() => toast.classList.add('show'), 100);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        function getToastIcon(type) {
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            return icons[type] || icons.info;
        }
        
        // Enhanced Statistics Animation
        function animateStats() {
            const statsCards = document.querySelectorAll('.stats-card');
            statsCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }
        
        // Enhanced Filter Management
        function enhanceFilters() {
            const form = document.querySelector('.filter-card form');
            const inputs = form.querySelectorAll('select, input');
            
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    this.style.borderColor = 'var(--purple)';
                    setTimeout(() => {
                        this.style.borderColor = 'var(--border-light)';
                    }, 1000);
                });
            });
        }
        
        // Transaction Row Interactions
        function enhanceTransactionRows() {
            const rows = document.querySelectorAll('.table tbody tr');
            rows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.01)';
                    this.style.zIndex = '1';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                    this.style.zIndex = 'auto';
                });
            });
        }
        
        // Responsive Behavior Enhancement
        function handleResize() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.mobile-overlay');
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }
        }
        
        // Initialize everything when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initTheme();
            enhanceFilters();
            
            setTimeout(() => {
                animateStats();
                enhanceTransactionRows();
            }, 500);
            
            // Add resize event listener
            window.addEventListener('resize', handleResize);
            
            // Close sidebar when clicking on a nav link (mobile)
            const navLinks = document.querySelectorAll('.sidebar .nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        toggleSidebar();
                    }
                });
            });
            
            // Welcome message
            setTimeout(() => {
                showToast('Transaction Management Panel Ready', 'info');
            }, 1000);
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + D for theme toggle
            if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                e.preventDefault();
                toggleTheme();
            }
            
            // Ctrl/Cmd + E for export
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                exportTransactions();
            }
            
            // Escape to close mobile sidebar
            if (e.key === 'Escape') {
                const sidebar = document.getElementById('sidebar');
                if (sidebar.classList.contains('show')) {
                    toggleSidebar();
                }
            }
        });
        
        // Enhanced Error Handling Display
        <?php if (isset($error)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo addslashes($error); ?>', 'error');
        });
        <?php endif; ?>
    </script>
</body>
</html>