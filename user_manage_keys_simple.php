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
$userId = $_SESSION['user_id'];

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Handle key purchase
if ($_POST && isset($_POST['purchase_key'])) {
    $keyId = (int)$_POST['key_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Get key details
        $stmt = $pdo->prepare("SELECT lk.*, m.name as mod_name FROM license_keys lk 
                              LEFT JOIN mods m ON lk.mod_id = m.id 
                              WHERE lk.id = ? AND lk.status = 'available'");
        $stmt->execute([$keyId]);
        $key = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$key) {
            throw new Exception("Key not available");
        }
        
        // Check user balance
        if ($user['balance'] < $key['price']) {
            throw new Exception("Insufficient balance");
        }
        
        // Update key status
        $stmt = $pdo->prepare("UPDATE license_keys SET status = 'sold', sold_to = ?, sold_at = NOW() WHERE id = ?");
        $stmt->execute([$userId, $keyId]);
        
        // Deduct balance
        $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->execute([$key['price'], $userId]);
        
        // Add transaction
        $reference = "License purchase #" . $keyId;
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, reference, status) VALUES (?, ?, 'purchase', ?, 'completed')");
        $stmt->execute([$userId, -$key['price'], $reference]);
        
        $pdo->commit();
        $success = 'License key purchased successfully!';
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Get filter parameters
$modId = $_GET['mod_id'] ?? '';

// Get all mods for filter dropdown
$stmt = $pdo->query("SELECT * FROM mods WHERE status = 'active' ORDER BY name");
$mods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available keys
$where = "lk.status = 'available'";
$params = [];

if ($modId) {
    $where .= " AND lk.mod_id = ?";
    $params[] = $modId;
}

$sql = "SELECT lk.*, m.name as mod_name 
        FROM license_keys lk 
        LEFT JOIN mods m ON lk.mod_id = m.id 
        WHERE $where 
        ORDER BY lk.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$availableKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's purchased keys
$stmt = $pdo->prepare("SELECT lk.*, m.name as mod_name 
                      FROM license_keys lk 
                      LEFT JOIN mods m ON lk.mod_id = m.id 
                      WHERE lk.sold_to = ? 
                      ORDER BY lk.sold_at DESC");
$stmt->execute([$userId]);
$purchasedKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Manage Keys - Mod APK Manager</title>
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
        
        .filter-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .table-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
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
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(40, 167, 69, 0.4);
        }
        
        .badge {
            font-size: 0.8em;
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            transform: translateY(-2px);
        }
        
        .license-key {
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 12px 16px;
            border-radius: 10px;
            border: 2px solid #dee2e6;
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        .key-card {
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
        
        .key-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .key-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .fade-in {
            animation: fadeIn 0.6s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .floating-animation {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
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
            
            .filter-card, .table-card {
                padding: 1rem;
                margin-bottom: 1rem;
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
            
            .license-key {
                font-size: 0.8em;
                padding: 8px 12px;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 0.5rem;
            }
            
            .page-header {
                padding: 1rem;
            }
            
            .filter-card, .table-card {
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
                    <a class="nav-link" href="user_dashboard_simple.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                    <a class="nav-link active" href="user_manage_keys_simple.php">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-key me-2"></i>Manage Keys</h2>
                    <div class="d-flex align-items-center">
                        <span class="me-3">Balance: <?php echo formatCurrency($user['balance']); ?></span>
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <span class="text-white fw-bold"><?php echo strtoupper(substr($user['username'], 0, 2)); ?></span>
                        </div>
                    </div>
                </div>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Filter -->
                <div class="filter-card">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <label for="mod_id" class="form-label">Filter by Mod:</label>
                            <select class="form-control" id="mod_id" name="mod_id">
                                <option value="">All Mods</option>
                                <?php foreach ($mods as $mod): ?>
                                <option value="<?php echo $mod['id']; ?>" 
                                        <?php echo $modId == $mod['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mod['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                            <a href="user_manage_keys_simple.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Available Keys -->
                <div class="table-card">
                    <h5><i class="fas fa-unlock me-2"></i>Available Keys</h5>
                    <div class="row">
                        <?php if (empty($availableKeys)): ?>
                        <div class="col-12 text-center text-muted py-4">
                            <i class="fas fa-key fa-3x mb-3"></i>
                            <p>No keys available for the selected mod</p>
                        </div>
                        <?php else: ?>
                            <?php foreach ($availableKeys as $key): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="key-card">
                                    <h6 class="text-primary"><?php echo htmlspecialchars($key['mod_name']); ?></h6>
                                    <div class="mb-2">
                                        <span class="badge bg-primary">
                                            <?php echo $key['duration'] . ' ' . ucfirst($key['duration_type']); ?>
                                        </span>
                                    </div>
                                    <div class="mb-2">
                                        <strong>Price: <?php echo formatCurrency($key['price']); ?></strong>
                                    </div>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                                        <button type="submit" name="purchase_key" class="btn btn-success btn-sm w-100"
                                                onclick="return confirm('Are you sure you want to purchase this key for <?php echo formatCurrency($key['price']); ?>?')">
                                            <i class="fas fa-shopping-cart me-1"></i>Purchase
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- My Purchased Keys -->
                <div class="table-card">
                    <h5><i class="fas fa-shopping-bag me-2"></i>My Purchased Keys</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Mod Name</th>
                                    <th>License Key</th>
                                    <th>Duration</th>
                                    <th>Price</th>
                                    <th>Purchased Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($purchasedKeys)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No purchased keys yet</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($purchasedKeys as $key): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($key['mod_name']); ?></td>
                                        <td>
                                            <div class="license-key"><?php echo htmlspecialchars($key['license_key']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?php echo $key['duration'] . ' ' . ucfirst($key['duration_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatCurrency($key['price']); ?></td>
                                        <td><?php echo formatDate($key['sold_at']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="copyToClipboard('<?php echo htmlspecialchars($key['license_key']); ?>')" 
                                                    title="Copy Key">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </td>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('License key copied to clipboard!');
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }
        
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