<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

// Check if user is logged in and is admin
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: user_dashboard.php');
    exit();
}

$pdo = getDBConnection();
$success = '';
$error = '';

// Get all mods for dropdown
$mods = [];
try {
    $stmt = $pdo->query("SELECT * FROM mods WHERE status = 'active' ORDER BY name");
    $mods = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Failed to load mods';
}

// Get uploaded APKs
$uploadedApks = [];
try {
    $stmt = $pdo->query("SELECT ma.*, m.name as mod_name FROM mod_apks ma 
                        LEFT JOIN mods m ON ma.mod_id = m.id 
                        ORDER BY ma.uploaded_at DESC");
    $uploadedApks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore errors for uploaded APKs
}

// Handle file upload
if ($_POST && isset($_FILES['apk_file'])) {
    $modId = $_POST['mod_id'];
    $file = $_FILES['apk_file'];
    
    if (empty($modId)) {
        $error = 'Please select a mod';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload error: ' . $file['error'];
    } elseif ($file['size'] > 100 * 1024 * 1024) { // 100MB limit
        $error = 'File size too large. Maximum 100MB allowed.';
    } else {
        // Check file extension
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($fileExtension !== 'apk') {
            $error = 'Please upload a valid APK file (.apk extension required)';
        } else {
            // Create uploads directory if it doesn't exist
            $uploadDir = 'uploads/apks/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Generate unique filename
            $fileName = uniqid() . '_' . $file['name'];
            $filePath = $uploadDir . $fileName;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO mod_apks (mod_id, file_name, file_path, file_size) VALUES (?, ?, ?, ?)");
                    if ($stmt->execute([$modId, $file['name'], $filePath, $file['size']])) {
                        $success = 'APK uploaded successfully!';
                        // Refresh the uploaded APKs list
                        $stmt = $pdo->query("SELECT ma.*, m.name as mod_name FROM mod_apks ma 
                                            LEFT JOIN mods m ON ma.mod_id = m.id 
                                            ORDER BY ma.uploaded_at DESC");
                        $uploadedApks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $error = 'Failed to save APK information to database';
                        unlink($filePath); // Delete uploaded file
                    }
                } catch (Exception $e) {
                    $error = 'Database error: ' . $e->getMessage();
                    unlink($filePath); // Delete uploaded file
                }
            } else {
                $error = 'Failed to upload file. Please check directory permissions.';
            }
        }
    }
}

// Helper function
function formatDate($date) {
    if (empty($date)) return 'N/A';
    $timestamp = strtotime($date);
    if ($timestamp === false) return 'Invalid Date';
    return date('d M Y H:i', $timestamp);
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Mod APK - Multi Panel</title>
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
        
        .form-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            margin-bottom: 1.5rem;
        }
        
        .table-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
        }
        
        .form-control {
            border-radius: 8px;
            border: 1px solid var(--border-light);
            padding: 10px 12px;
            transition: border-color 0.2s ease;
            background-color: var(--card-bg);
            color: var(--text-primary);
        }
        
        .form-control:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
            outline: none;
        }
        
        .btn-primary {
            background-color: var(--purple);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 500;
            transition: background-color 0.2s ease;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--purple-dark);
            color: white;
        }
        
        .file-upload {
            border: 2px dashed var(--border-light);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.2s ease;
            background-color: var(--card-bg);
        }
        
        .file-upload:hover {
            border-color: var(--purple-light);
        }
        
        .file-upload.dragover {
            border-color: var(--purple);
            background-color: rgba(139, 92, 246, 0.05);
        }
        
        .file-upload.has-file {
            border-color: #10b981;
            background-color: rgba(16, 185, 129, 0.05);
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            padding: 12px 16px;
        }
        
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-danger {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
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
            
            .page-header {
                padding: 1.5rem;
                margin-bottom: 1rem;
            }
            
            .page-header h2 {
                font-size: 1.5rem;
            }
            
            .form-card, .table-card {
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
            
            .file-upload {
                padding: 2rem 1rem;
            }
            
            .table-responsive {
                font-size: 0.9em;
            }
            
            .table thead th,
            .table tbody td {
                padding: 0.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 0.5rem;
            }
            
            .page-header {
                padding: 1rem;
            }
            
            .form-card, .table-card {
                padding: 0.5rem;
            }
            
            .file-upload {
                padding: 1.5rem 0.5rem;
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
                    <a class="nav-link active" href="upload_mod.php">
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
                            <h2 class="mb-2"><i class="fas fa-upload me-2" style="color: var(--purple);"></i>Upload Mod APK</h2>
                            <p class="mb-0" style="color: var(--text-secondary);">Upload APK files for your mods. Maximum file size: 100MB</p>
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
                
                <!-- Upload Form -->
                <div class="form-card">
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
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="mod_id" class="form-label fw-bold">
                                        <i class="fas fa-list me-2" style="color: var(--purple);"></i>Select Mod *
                                    </label>
                                    <select class="form-control" id="mod_id" name="mod_id" required>
                                        <option value="">-- Select Mod --</option>
                                        <?php foreach ($mods as $mod): ?>
                                        <option value="<?php echo $mod['id']; ?>">
                                            <?php echo htmlspecialchars($mod['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="apk_file" class="form-label fw-bold">
                                        <i class="fas fa-file me-2" style="color: var(--purple);"></i>Select .apk file *
                                    </label>
                                    <div class="file-upload" id="fileUpload">
                                        <i class="fas fa-cloud-upload-alt fa-2x mb-3" style="color: var(--text-secondary);"></i>
                                        <p class="mb-2 fw-bold">Drag & drop your APK file here</p>
                                        <p class="mb-3" style="color: var(--text-secondary);">or click to browse</p>
                                        <input type="file" class="form-control" id="apk_file" name="apk_file" 
                                               accept=".apk" required style="display: none;">
                                        <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('apk_file').click()">
                                            <i class="fas fa-folder-open me-2"></i>Choose File
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-upload me-2"></i>Upload Mod APK
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Uploaded APKs -->
                <div class="table-card">
                    <h5 class="mb-3"><i class="fas fa-list me-2" style="color: var(--purple);"></i>Uploaded Mod APKs</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-tag me-2"></i>Mod Name</th>
                                    <th><i class="fas fa-file me-2"></i>APK File</th>
                                    <th><i class="fas fa-weight me-2"></i>Size</th>
                                    <th><i class="fas fa-calendar me-2"></i>Upload Date</th>
                                    <th><i class="fas fa-cog me-2"></i>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($uploadedApks)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4" style="color: var(--text-secondary);">
                                        <i class="fas fa-inbox fa-2x mb-2"></i><br>
                                        No mod APKs found
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($uploadedApks as $apk): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($apk['mod_name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <i class="fas fa-file me-1"></i>
                                                <?php echo htmlspecialchars($apk['file_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="color: var(--text-secondary);">
                                                <?php echo formatFileSize($apk['file_size']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="color: var(--text-secondary);">
                                                <?php echo formatDate($apk['uploaded_at']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (file_exists($apk['file_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($apk['file_path']); ?>" 
                                                   class="btn btn-sm btn-success" download>
                                                    <i class="fas fa-download me-1"></i>Download
                                                </a>
                                            <?php else: ?>
                                                <span class="text-danger">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>File not found
                                                </span>
                                            <?php endif; ?>
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
        // Theme functionality
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
            const themeIcon = document.getElementById('theme-icon');
            themeIcon.className = theme === 'dark' ? 'fas fa-moon' : 'fas fa-sun';
        }
        
        // Initialize theme on page load
        document.addEventListener('DOMContentLoaded', initTheme);
        
        // Mobile menu functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.mobile-overlay');
            
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }
        
        // Close sidebar when clicking on nav links on mobile
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= 768) {
                        toggleSidebar();
                    }
                });
            });
        });
        
        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                document.getElementById('sidebar').classList.remove('show');
                document.querySelector('.mobile-overlay').classList.remove('show');
            }
        });
        
        // File upload drag and drop
        const fileUpload = document.getElementById('fileUpload');
        const fileInput = document.getElementById('apk_file');
        
        fileUpload.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUpload.classList.add('dragover');
        });
        
        fileUpload.addEventListener('dragleave', () => {
            fileUpload.classList.remove('dragover');
        });
        
        fileUpload.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUpload.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const file = files[0];
                if (file.name.toLowerCase().endsWith('.apk')) {
                    fileInput.files = files;
                    updateFileDisplay();
                } else {
                    alert('Please select a valid APK file (.apk extension required)');
                }
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file && !file.name.toLowerCase().endsWith('.apk')) {
                alert('Please select a valid APK file (.apk extension required)');
                e.target.value = '';
                updateFileDisplay();
                return;
            }
            updateFileDisplay();
        });
        
        function updateFileDisplay() {
            const file = fileInput.files[0];
            if (file) {
                fileUpload.classList.add('has-file');
                fileUpload.innerHTML = `
                    <i class="fas fa-file fa-2x text-success mb-3"></i>
                    <p class="mb-2 fw-bold text-success">${file.name}</p>
                    <p class="mb-3" style="color: var(--text-secondary);">${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                    <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('apk_file').click()">
                        <i class="fas fa-edit me-2"></i>Change File
                    </button>
                `;
            } else {
                fileUpload.classList.remove('has-file');
                fileUpload.innerHTML = `
                    <i class="fas fa-cloud-upload-alt fa-2x mb-3" style="color: var(--text-secondary);"></i>
                    <p class="mb-2 fw-bold">Drag & drop your APK file here</p>
                    <p class="mb-3" style="color: var(--text-secondary);">or click to browse</p>
                    <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('apk_file').click()">
                        <i class="fas fa-folder-open me-2"></i>Choose File
                    </button>
                `;
            }
        }
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const modId = document.getElementById('mod_id').value;
            const file = document.getElementById('apk_file').files[0];
            
            if (!modId) {
                e.preventDefault();
                alert('Please select a mod');
                return;
            }
            
            if (!file) {
                e.preventDefault();
                alert('Please select an APK file');
                return;
            }
            
            if (file.size > 100 * 1024 * 1024) {
                e.preventDefault();
                alert('File size too large. Maximum 100MB allowed.');
                return;
            }
        });
    </script>
</body>
</html>