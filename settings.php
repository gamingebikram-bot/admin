<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireAdmin();

$success = '';
$error = '';

try {
    $pdo = getDBConnection();
    
    // Get current user data
    $user = getUserData();
    
    // Get account statistics
    $stmt = $pdo->prepare("SELECT 
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as logins_today,
        COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as logins_week,
        COUNT(*) as total_sessions
        FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $account_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get last login info
    $stmt = $pdo->prepare("SELECT last_login FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $last_login = $stmt->fetchColumn();
    
    if ($_POST) {
        if (isset($_POST['update_profile'])) {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            
            if (empty($username) || empty($email)) {
                $error = 'Please fill in all fields';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address';
            } else {
                // Check if username or email already exists (excluding current user)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmt->execute([$username, $email, $user['id']]);
                
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Username or email already exists';
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                    if ($stmt->execute([$username, $email, $user['id']])) {
                        $_SESSION['username'] = $username;
                        $_SESSION['email'] = $email;
                        $success = 'Profile updated successfully!';
                        $user = getUserData(); // Refresh user data
                    } else {
                        $error = 'Failed to update profile';
                    }
                }
            }
        } elseif (isset($_POST['change_password'])) {
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];
            
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                $error = 'Please fill in all password fields';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New passwords do not match';
            } elseif (strlen($newPassword) < 6) {
                $error = 'New password must be at least 6 characters long';
            } elseif (!password_verify($currentPassword, $user['password'])) {
                $error = 'Current password is incorrect';
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$hashedPassword, $user['id']])) {
                    $success = 'Password changed successfully!';
                } else {
                    $error = 'Failed to change password';
                }
            }
        }
    }
} catch (Exception $e) {
    $error = '';
    error_log('Settings error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Multi Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-purple: #8B5CF6;
            --primary-dark: #7C3AED;
            --secondary-purple: #A855F7;
            --success-green: #10B981;
            --warning-orange: #F59E0B;
            --danger-red: #EF4444;
            --info-blue: #3B82F6;
            --bg-dark: #1A202C;
            --card-dark: #2D3748;
            --card-darker: #1E2A3A;
            --text-light: #F7FAFC;
            --text-muted: #A0AEC0;
            --border-dark: #4A5568;
            --shadow-dark: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.4);
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-dark);
            color: var(--text-light);
            line-height: 1.6;
        }

        .sidebar {
            background: var(--card-dark);
            min-height: 100vh;
            box-shadow: var(--shadow-dark);
            border-right: 1px solid var(--border-dark);
        }

        .sidebar .brand {
            padding: 1.5rem 1rem;
            color: var(--text-light);
            border-bottom: 1px solid var(--border-dark);
        }

        .sidebar .brand h4 {
            font-weight: 700;
            font-size: 1.25rem;
            margin: 0;
        }

        .sidebar .nav-link {
            color: var(--text-muted);
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: var(--border-radius);
            transition: var(--transition);
            display: flex;
            align-items: center;
            text-decoration: none;
            font-weight: 500;
        }

        .sidebar .nav-link:hover {
            background-color: rgba(139, 92, 246, 0.1);
            color: var(--primary-purple);
            transform: translateX(4px);
        }

        .sidebar .nav-link.active {
            background: var(--primary-purple);
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }

        .sidebar .nav-link i {
            width: 20px;
            margin-right: 12px;
            text-align: center;
            font-size: 1rem;
        }

        .main-content {
            padding: 2rem;
            background: var(--bg-dark);
        }

        .header {
            background: var(--card-dark);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-dark);
            margin-bottom: 2rem;
            border: 1px solid var(--border-dark);
        }

        .header h2 {
            color: var(--text-light);
            margin: 0;
            font-weight: 600;
        }

        .stats-container {
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-dark);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            border: 1px solid var(--border-dark);
            transition: var(--transition);
            height: 100%;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-purple);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.purple { background: var(--primary-purple); }
        .stat-icon.green { background: var(--success-green); }
        .stat-icon.blue { background: var(--info-blue); }
        .stat-icon.orange { background: var(--warning-orange); }

        .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-light);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .form-card {
            background: var(--card-dark);
            border-radius: var(--border-radius);
            padding: 2rem;
            border: 1px solid var(--border-dark);
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }

        .form-card:hover {
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-purple);
        }

        .form-card h5 {
            color: var(--text-light);
            margin-bottom: 1.5rem;
            font-weight: 600;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
        }

        .form-card h5 i {
            color: var(--primary-purple);
        }

        .form-control {
            border: 1px solid var(--border-dark);
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            transition: var(--transition);
            background-color: var(--card-darker);
            color: var(--text-light);
        }

        .form-control:focus {
            border-color: var(--primary-purple);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
            background-color: var(--card-darker);
            color: var(--text-light);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        .form-control[readonly] {
            background-color: rgba(139, 92, 246, 0.1);
            border-color: var(--primary-purple);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-light);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .input-group-text {
            background-color: var(--card-darker);
            border: 1px solid var(--border-dark);
            color: var(--primary-purple);
        }

        .btn {
            border-radius: var(--border-radius);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: var(--transition);
            border: none;
        }

        .btn-primary {
            background: var(--primary-purple);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.3);
        }

        .btn-outline-secondary {
            border: 1px solid var(--border-dark);
            color: var(--text-muted);
            background: transparent;
        }

        .btn-outline-secondary:hover {
            background: var(--primary-purple);
            border-color: var(--primary-purple);
            color: white;
        }

        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: var(--success-green);
            color: white;
        }

        .alert-danger {
            background: var(--danger-red);
            color: white;
        }

        .mobile-menu-btn {
            display: none;
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -100%;
                top: 0;
                width: 280px;
                z-index: 1050;
                transition: var(--transition);
            }

            .sidebar.show {
                left: 0;
            }

            .mobile-menu-btn {
                display: block;
            }

            .main-content {
                padding: 1rem;
            }

            .stat-card {
                margin-bottom: 1rem;
            }
        }

        .password-strength {
            height: 4px;
            border-radius: 2px;
            transition: var(--transition);
            margin-top: 0.5rem;
            background: var(--border-dark);
        }

        .strength-weak { background: var(--danger-red); width: 25%; }
        .strength-fair { background: var(--warning-orange); width: 50%; }
        .strength-good { background: var(--info-blue); width: 75%; }
        .strength-strong { background: var(--success-green); width: 100%; }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040;
            display: none;
        }

        @media (max-width: 768px) {
            .sidebar.show ~ .sidebar-overlay {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 p-0">
                <div class="sidebar" id="sidebar">
                    <div class="brand">
                        <h4><i class="fas fa-crown me-2"></i>Multi Panel</h4>
                        <small class="text-muted">Admin Dashboard</small>
                    </div>
                    <nav class="nav flex-column px-3">
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
                        <a class="nav-link" href="transactions.php">
                            <i class="fas fa-exchange-alt"></i>Transaction
                        </a>
                        <a class="nav-link" href="referral_codes.php">
                            <i class="fas fa-tag"></i>Referral Code
                        </a>
                        <a class="nav-link active" href="settings.php">
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
                <!-- Header -->
                <div class="header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div class="d-flex align-items-center">
                            <button class="btn btn-outline-secondary mobile-menu-btn me-3" type="button" onclick="toggleSidebar()">
                                <i class="fas fa-bars"></i>
                            </button>
                            <div>
                                <h2 class="mb-0"><i class="fas fa-cog me-2 text-primary"></i>Account Settings</h2>
                                <p class="text-muted mb-0">Manage your account information and security settings</p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="d-flex align-items-center">
                                <div class="me-3 text-end">
                                    <div class="fw-bold text-light"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                                    <small class="text-muted">Administrator</small>
                                </div>
                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                    <span class="text-white fw-bold"><?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Account Statistics -->
                <div class="stats-container">
                    <div class="row">
                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="stat-card">
                                <div class="stat-icon purple">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="stat-value"><?php echo date('d M Y', strtotime($user['created_at'])); ?></div>
                                <div class="stat-label">Member Since</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="stat-card">
                                <div class="stat-icon blue">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stat-value"><?php echo $last_login ? date('d M Y', strtotime($last_login)) : 'Never'; ?></div>
                                <div class="stat-label">Last Login</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="stat-card">
                                <div class="stat-icon orange">
                                    <i class="fas fa-user-tag"></i>
                                </div>
                                <div class="stat-value"><?php echo ucfirst($user['role']); ?></div>
                                <div class="stat-label">Account Type</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="stat-card">
                                <div class="stat-icon green">
                                    <i class="fas fa-shield-check"></i>
                                </div>
                                <div class="stat-value">Active</div>
                                <div class="stat-label">Account Status</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Alerts -->
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
                
                <div class="row">
                    <!-- Account Information -->
                    <div class="col-lg-6 mb-4">
                        <div class="form-card">
                            <h5><i class="fas fa-user me-2"></i>Account Information</h5>
                            <form method="POST" id="profileForm">
                                <input type="hidden" name="update_profile" value="1">
                                
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="username" name="username" 
                                               value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-shield-alt"></i></span>
                                        <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" readonly>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Join Date</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                        <input type="text" class="form-control" value="<?php echo formatDate($user['created_at']); ?>" readonly>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Change Password -->
                    <div class="col-lg-6 mb-4">
                        <div class="form-card">
                            <h5><i class="fas fa-shield-alt me-2"></i>Change Password</h5>
                            <form method="POST" id="passwordForm">
                                <input type="hidden" name="change_password" value="1">
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-key"></i></span>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength mt-2" id="passwordStrength"></div>
                                    <small class="text-muted">Password must be at least 6 characters long</small>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-check"></i></span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-key me-2"></i>Update Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay d-md-none" onclick="toggleSidebar()"></div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }

        // Password visibility toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            strengthBar.className = 'password-strength';
            if (strength === 1) strengthBar.classList.add('strength-weak');
            else if (strength === 2) strengthBar.classList.add('strength-fair');
            else if (strength === 3) strengthBar.classList.add('strength-good');
            else if (strength >= 4) strengthBar.classList.add('strength-strong');
        });

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Form validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            
            if (!username || !email) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });

        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!currentPassword || !newPassword || !confirmPassword) {
                e.preventDefault();
                alert('Please fill in all password fields.');
                return;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('New password must be at least 6 characters long.');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match.');
                return;
            }
        });

        // Enhanced interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading states to buttons
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                        submitBtn.disabled = true;
                    }
                });
            });

            // Add smooth hover effects
            const cards = document.querySelectorAll('.stat-card, .form-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>