<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'princeaalyan_gmn');
define('DB_PASS', 'princeaalyan_gmn');
define('DB_NAME', 'princeaalyan_gmn');

// Create database connection
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Initialize database tables
function initializeDatabase() {
    $pdo = getDBConnection();
    
    // Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        balance DECIMAL(10,2) DEFAULT 0.00,
        role ENUM('admin', 'user') DEFAULT 'user',
        referral_code VARCHAR(20) UNIQUE,
        referred_by VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Mods table
    $pdo->exec("CREATE TABLE IF NOT EXISTS mods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // License keys table
    $pdo->exec("CREATE TABLE IF NOT EXISTS license_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mod_id INT NOT NULL,
        license_key VARCHAR(100) UNIQUE NOT NULL,
        duration INT NOT NULL,
        duration_type ENUM('hours', 'days', 'months') DEFAULT 'days',
        price DECIMAL(10,2) NOT NULL,
        status ENUM('available', 'sold') DEFAULT 'available',
        sold_to INT NULL,
        sold_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE,
        FOREIGN KEY (sold_to) REFERENCES users(id) ON DELETE SET NULL
    )");
    
    // Mod APK files table
    $pdo->exec("CREATE TABLE IF NOT EXISTS mod_apks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mod_id INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size INT NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE
    )");
    
    // Transactions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        type ENUM('purchase', 'balance_add', 'refund') NOT NULL,
        reference VARCHAR(100),
        status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Referral codes table
    $pdo->exec("CREATE TABLE IF NOT EXISTS referral_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) UNIQUE NOT NULL,
        created_by INT NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Create default admin user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $stmt->execute();
    $adminCount = $stmt->fetchColumn();
    
    if ($adminCount == 0) {
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, balance) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', 'admin@example.com', $adminPassword, 'admin', 99999.00]);
    }
}
?>
