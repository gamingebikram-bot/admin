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

$success = '';
$error = '';

// Get all users for dropdown
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'user' ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Failed to fetch users: ' . $e->getMessage();
    $users = [];
}

// Pre-select user if provided in URL
$selectedUserId = $_GET['user_id'] ?? '';

// Get user statistics for dashboard
try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT 
            COUNT(*) as total_users,
            SUM(balance) as total_balance,
            COUNT(CASE WHEN balance > 0 THEN 1 END) as users_with_balance,
            AVG(balance) as avg_balance
            FROM users WHERE role = 'user'");
        $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $userStats = [
            'total_users' => 0,
            'total_balance' => 0,
            'users_with_balance' => 0,
            'avg_balance' => 0
        ];
    }
} catch (Exception $e) {
    $userStats = [
        'total_users' => 0,
        'total_balance' => 0,
        'users_with_balance' => 0,
        'avg_balance' => 0
    ];
}

if ($_POST) {
    try {
        $userId = $_POST['user_id'];
        $amount = (float)$_POST['amount'];
        $reference = trim($_POST['reference']);
        
        if (empty($userId) || $amount <= 0) {
            $error = 'Please select a user and enter a valid amount';
        } else {
            if (updateBalance($userId, $amount, 'balance_add', $reference)) {
                $success = 'Balance added successfully!';
                $selectedUserId = $userId; // Keep selected user
                
                // Refresh user stats after balance update
                $stmt = $pdo->query("SELECT 
                    COUNT(*) as total_users,
                    SUM(balance) as total_balance,
                    COUNT(CASE WHEN balance > 0 THEN 1 END) as users_with_balance,
                    AVG(balance) as avg_balance
                    FROM users WHERE role = 'user'");
                $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = 'Failed to add balance. Please try again.';
            }
        }
    } catch (Exception $e) {
        $error = 'An error occurred: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User Balance - Multi Panel</title>
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
        
        .stats-card.total-users { border-left-color: var(--purple); }
        .stats-card.total-balance { border-left-color: var(--success); }
        .stats-card.users-with-balance { border-left-color: var(--info); }
        .stats-card.avg-balance { border-left-color: var(--warning); }
        
        .form-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: var(--shadow-light);
        }
        
        .form-header {
            background: linear-gradient(135deg, var(--purple) 0%, var(--purple-dark) 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .form-control {
            border-radius: 8px;
            border: 1px solid var(--border-light);
            padding: 0.75rem 1rem;
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
        
        .alert {
            border-radius: 8px;
            border: 1px solid;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border-color: var(--success);
            color: var(--success);
        }
        
        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            border-color: var(--danger);
            color: var(--danger);
        }
        
        .user-option {
            padding: 0.5rem 1rem;
            border-bottom: 1px solid var(--border-light);
        }
        
        .user-option:last-child {
            border-bottom: none;
        }
        
        .quick-amounts {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }
        
        .quick-amount-btn {
            padding: 0.25rem 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: 6px;
            background-color: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
        }
        
        .quick-amount-btn:hover {
            background-color: var(--purple);
            color: white;
            border-color: var(--purple);
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
                    <a class="nav-link active" href="add_balance.php">
                        <i class="fas fa-wallet"></i>Add Balance
                    </a>
                    <a class="nav-link" href="transactions.php">
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
                            <h2 class="mb-2"><i class="fas fa-wallet me-2" style="color: var(--purple);"></i>Add User Balance</h2>
                            <p class="mb-0" style="color: var(--text-secondary);">Add balance to user accounts for transactions</p>
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
                        <div class="stats-card total-users">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Total Users</h6>
                                    <h3 class="mb-0"><?php echo $userStats['total_users']; ?></h3>
                                </div>
                                <div style="color: var(--purple);">
                                    <i class="fas fa-users fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card total-balance">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Total Balance</h6>
                                    <h3 class="mb-0"><?php echo formatCurrency($userStats['total_balance']); ?></h3>
                                </div>
                                <div style="color: var(--success);">
                                    <i class="fas fa-wallet fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card users-with-balance">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Active Wallets</h6>
                                    <h3 class="mb-0"><?php echo $userStats['users_with_balance']; ?></h3>
                                </div>
                                <div style="color: var(--info);">
                                    <i class="fas fa-credit-card fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card avg-balance">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 style="color: var(--text-secondary); margin-bottom: 0.5rem;">Avg Balance</h6>
                                    <h3 class="mb-0"><?php echo formatCurrency($userStats['avg_balance']); ?></h3>
                                </div>
                                <div style="color: var(--warning);">
                                    <i class="fas fa-chart-line fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="form-card">
                            <div class="form-header">
                                <h4 class="mb-1"><i class="fas fa-plus-circle me-2"></i>Add Balance</h4>
                                <p class="mb-0 opacity-75">Add balance to user accounts for purchasing license keys</p>
                            </div>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success" role="alert">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo htmlspecialchars($success); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" id="balanceForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-4">
                                            <label for="user_id" class="form-label">
                                                <i class="fas fa-user me-2"></i>Select User *
                                            </label>
                                            <select class="form-control" id="user_id" name="user_id" required>
                                                <option value="">-- Select User --</option>
                                                <?php foreach ($users as $user): ?>
                                                <option value="<?php echo $user['id']; ?>" 
                                                        data-balance="<?php echo $user['balance']; ?>"
                                                        <?php echo $selectedUserId == $user['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($user['username']); ?> 
                                                    (Current: <?php echo formatCurrency($user['balance']); ?>)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div id="currentBalance" class="mt-2" style="display: none;">
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Current Balance: <span id="balanceAmount"></span>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-4">
                                            <label for="amount" class="form-label">
                                                <i class="fas fa-rupee-sign me-2"></i>Amount (₹) *
                                            </label>
                                            <input type="number" class="form-control" id="amount" name="amount" 
                                                   step="0.01" min="0" placeholder="0.00" required>
                                            <div class="quick-amounts mt-2">
                                                <span class="small text-muted me-2">Quick amounts:</span>
                                                <button type="button" class="quick-amount-btn" onclick="setAmount(100)">₹100</button>
                                                <button type="button" class="quick-amount-btn" onclick="setAmount(500)">₹500</button>
                                                <button type="button" class="quick-amount-btn" onclick="setAmount(1000)">₹1000</button>
                                                <button type="button" class="quick-amount-btn" onclick="setAmount(2000)">₹2000</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="reference" class="form-label">
                                        <i class="fas fa-tag me-2"></i>Reference (Optional)
                                    </label>
                                    <input type="text" class="form-control" id="reference" name="reference" 
                                           placeholder="e.g. Manual balance addition, Refund, etc.">
                                    <small class="text-muted mt-1 d-block">
                                        <i class="fas fa-lightbulb me-1"></i>
                                        This will be shown in transaction history for reference
                                    </small>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-plus me-2"></i>Add Balance
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-grid">
                                            <a href="manage_users.php" class="btn btn-outline-secondary">
                                                <i class="fas fa-arrow-left me-2"></i>Back to Users
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
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
        
        // Quick Amount Selection
        function setAmount(amount) {
            document.getElementById('amount').value = amount;
            showToast(`Amount set to ₹${amount}`, 'info');
        }
        
        // Show current balance when user is selected
        function updateCurrentBalance() {
            const select = document.getElementById('user_id');
            const selectedOption = select.options[select.selectedIndex];
            const currentBalanceDiv = document.getElementById('currentBalance');
            const balanceAmount = document.getElementById('balanceAmount');
            
            if (selectedOption.value && selectedOption.dataset.balance !== undefined) {
                const balance = parseFloat(selectedOption.dataset.balance);
                balanceAmount.textContent = `₹${balance.toFixed(2)}`;
                currentBalanceDiv.style.display = 'block';
            } else {
                currentBalanceDiv.style.display = 'none';
            }
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
        
        // Form Enhancement
        function enhanceForm() {
            const form = document.getElementById('balanceForm');
            const userSelect = document.getElementById('user_id');
            const amountInput = document.getElementById('amount');
            
            // Update balance display when user changes
            userSelect.addEventListener('change', updateCurrentBalance);
            
            // Format amount input
            amountInput.addEventListener('input', function() {
                const value = parseFloat(this.value);
                if (value && value > 0) {
                    this.style.borderColor = 'var(--success)';
                } else {
                    this.style.borderColor = 'var(--border-light)';
                }
            });
            
            // Form submission enhancement
            form.addEventListener('submit', function(e) {
                const userId = userSelect.value;
                const amount = parseFloat(amountInput.value);
                
                if (!userId || amount <= 0) {
                    e.preventDefault();
                    showToast('Please select a user and enter a valid amount', 'error');
                    return;
                }
                
                showToast('Processing balance addition...', 'info');
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
            enhanceForm();
            updateCurrentBalance(); // Update if user is pre-selected
            
            setTimeout(() => {
                animateStats();
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
                showToast('Balance Addition Panel Ready', 'info');
            }, 1000);
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + D for theme toggle
            if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                e.preventDefault();
                toggleTheme();
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
        
        <?php if (isset($success)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?php echo addslashes($success); ?>', 'success');
        });
        <?php endif; ?>
    </script>
</body>
</html>