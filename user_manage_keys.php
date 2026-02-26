<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/database.php';

// Simple helpers
function formatCurrency($amount){
    return '₹' . number_format((float)$amount, 2);
}
function formatDate($dt){
    if(!$dt){ return '-'; }
    return date('d M Y, h:i A', strtotime($dt));
}

// Require user login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// PDO connection
try {
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    die('Database connection failed');
}

// Load current user
$stmt = $pdo->prepare('SELECT id, username, role, balance FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if(!$user){
    session_destroy();
    header('Location: login.php');
    exit;
}

$success = '';
$error = '';

// Handle key purchase with transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_key'])) {
    $keyId = (int)($_POST['key_id'] ?? 0);
    if ($keyId <= 0) {
        $error = 'Invalid key.';
    } else {
        try {
            $pdo->beginTransaction();

            // Lock key row
            $stmt = $pdo->prepare('SELECT id, mod_id, price FROM license_keys WHERE id = ? AND sold_to IS NULL LIMIT 1 FOR UPDATE');
            $stmt->execute([$keyId]);
            $key = $stmt->fetch();
            if(!$key){
                throw new Exception('This key is no longer available.');
            }

            // Refresh user balance with lock
            $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();
            $currentBalance = (float)$row['balance'];
            $price = (float)$key['price'];
            if ($currentBalance < $price) {
                throw new Exception('Insufficient balance.');
            }

            // Deduct and mark sold
            $stmt = $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
            $stmt->execute([$price, $user['id']]);

            $stmt = $pdo->prepare('UPDATE license_keys SET sold_to = ?, sold_at = NOW() WHERE id = ?');
            $stmt->execute([$user['id'], $keyId]);

            // Optional: record transaction if table exists
            try {
                $stmt = $pdo->prepare('INSERT INTO transactions (user_id, type, amount, description, created_at) VALUES (?, "debit", ?, "License key purchase", NOW())');
                $stmt->execute([$user['id'], $price]);
            } catch (Throwable $ignored) {}

            $pdo->commit();
            $success = 'License key purchased successfully!';

            // Refresh user data to update balance
            $stmt = $pdo->prepare('SELECT id, username, role, balance FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$user['id']]);
            $user = $stmt->fetch();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = $e->getMessage();
        }
    }
}

// Get filter parameters
$modId = $_GET['mod_id'] ?? '';

// Get all active mods
$mods = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM mods WHERE status = 'active' ORDER BY name");
    $mods = $stmt->fetchAll();
} catch (Throwable $e) {}

// Get available keys (unsold)
try {
    if ($modId !== '' && ctype_digit((string)$modId)) {
        $stmt = $pdo->prepare('SELECT lk.id, lk.mod_id, lk.duration, lk.duration_type, lk.price, m.name AS mod_name
                               FROM license_keys lk
                               LEFT JOIN mods m ON m.id = lk.mod_id
                               WHERE lk.sold_to IS NULL AND lk.mod_id = ?
                               ORDER BY lk.id DESC');
        $stmt->execute([$modId]);
    } else {
        $stmt = $pdo->query('SELECT lk.id, lk.mod_id, lk.duration, lk.duration_type, lk.price, m.name AS mod_name
                              FROM license_keys lk
                              LEFT JOIN mods m ON m.id = lk.mod_id
                              WHERE lk.sold_to IS NULL
                              ORDER BY lk.id DESC');
    }
    $availableKeys = $stmt->fetchAll();
} catch (Throwable $e) {
    $availableKeys = [];
}

// Get user's purchased keys
try {
    $stmt = $pdo->prepare('SELECT lk.*, m.name AS mod_name
                           FROM license_keys lk
                           LEFT JOIN mods m ON m.id = lk.mod_id
                           WHERE lk.sold_to = ?
                           ORDER BY lk.sold_at DESC');
    $stmt->execute([$user['id']]);
    $purchasedKeys = $stmt->fetchAll();
} catch (Throwable $e) {
    $purchasedKeys = [];
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
        /* Light theme to match dashboard */
        :root{
            --bg:#f3f4f6; --card:#ffffff; --text:#111827; --muted:#6b7280; --line:#e5e7eb;
            --accent:#7c3aed; --accent-600:#6d28d9; --accent-100:#efeafe;
        }
        body{background:var(--bg);overflow-x:hidden;}
        .page-header{background:transparent;border:none;padding:0;margin:.75rem 0 1.25rem 0;box-shadow:none;}
        .page-header h2{color:var(--text);font-weight:700;}
        .page-header p{color:var(--muted);}        
        .sidebar{background:var(--card);color:var(--text);border-right:1px solid var(--line);box-shadow:0 1px 0 rgba(0,0,0,.02);} 
        .sidebar .nav-link{color:var(--muted);padding:12px 16px;border-radius:12px;margin:4px 10px;transition:.2s all ease;}
        .sidebar .nav-link i{color:var(--muted);} 
        .sidebar .nav-link:hover,.sidebar .nav-link.active{background:var(--accent-100);color:var(--accent);} 
        .sidebar .nav-link.active i{color:var(--accent);} 

       
        .filter-card,.table-card,.key-card{background:var(--card);border-radius:16px;padding:1rem;border:1px solid var(--line);box-shadow:0 8px 24px rgba(17,24,39,.04);} 
        .table-card h5{color:var(--text);font-weight:600;margin-bottom:.75rem;}
        .btn-primary{background:var(--accent);border:none;border-radius:10px;padding:10px 16px;}
        .btn-primary:hover{background:var(--accent-600);} 
        .badge{border-radius:999px;padding:6px 10px;}
        .form-control{border-radius:12px;border:2px solid var(--line);padding:10px 14px;}
        .form-control:focus{border-color:var(--accent);box-shadow:0 0 0 .15rem rgba(124,58,237,.15);} 
        .license-key{font-family:'Courier New',monospace;font-size:.9em;background:#fafafa;padding:.5rem;border-radius:6px;border:1px solid var(--line);} 
        .key-card:hover{transform:translateY(-2px);} 

        /* Responsive + mobile sidebar */
        .container-fluid,.row{width:100%;margin:0;}
        @media (max-width: 991.98px){
            .main-content{margin-left:0;width:100%;padding:1rem .75rem;}
            .sidebar{width:100%;left:0;right:0;top:-105%;position:fixed;z-index:1201;border-right:none;border-bottom:1px solid var(--line);} 
            .sidebar.show{top:0;} 
            .mobile-overlay{z-index:1200;}
            .mobile-overlay.show{display:block;}
            .mobile-menu-btn{display:block;position:fixed;top:.75rem;left:.75rem;z-index:1202;background:var(--card);border:1px solid var(--line);color:var(--text);padding:.55rem .7rem;border-radius:10px;box-shadow:0 4px 14px rgba(17,24,39,.06);}            
        }
        @media (max-width: 480px){
            .page-header .d-flex{flex-direction:column;align-items:flex-start;gap:.35rem;}
            .col-md-4,.col-md-6,.col-lg-4{flex:0 0 100%;max-width:100%;}
        }
        /* Overlay base (hidden by default) */
        .mobile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);} 
    </style>
</head>
<body>
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" onclick="toggleSidebar()" style="display:none;">
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
                    <a class="nav-link" href="user_dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                    <a class="nav-link active" href="user_manage_keys.php">
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
            <div class="col-md-9 col-lg-10 main-content">
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2"><i class="fas fa-key me-2"></i>Manage Keys</h2>
                            <p class="text-muted mb-0">Browse available keys and your purchases.</p>
                        </div>
                        <div class="d-flex align-items-center">
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
                            <a href="user_manage_keys.php" class="btn btn-secondary">
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
        function toggleSidebar(){
            var sidebar = document.querySelector('.sidebar');
            var overlay = document.querySelector('.mobile-overlay');
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('License key copied to clipboard!');
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }
        // Auto-close sidebar on link click (mobile)
        document.querySelectorAll('.sidebar .nav-link').forEach(function(link){
            link.addEventListener('click', function(){
                if (window.innerWidth <= 991) { toggleSidebar(); }
            });
        });
    </script>
</body>
</html>