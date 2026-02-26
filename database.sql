-- Mod APK Manager Database Schema
-- Create database
CREATE DATABASE IF NOT EXISTS princeaalyan_gmn;
USE princeaalyan_gmn;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    role ENUM('admin', 'user') DEFAULT 'user',
    referral_code VARCHAR(20) UNIQUE,
    referred_by VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Mods table
CREATE TABLE IF NOT EXISTS mods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- License keys table
CREATE TABLE IF NOT EXISTS license_keys (
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
);

-- Mod APK files table
CREATE TABLE IF NOT EXISTS mod_apks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mod_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE
);

-- Transactions table
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('purchase', 'balance_add', 'refund') NOT NULL,
    reference VARCHAR(100),
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Referral codes table
CREATE TABLE IF NOT EXISTS referral_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,
    created_by INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default admin user
INSERT INTO users (username, email, password, role, balance) 
VALUES ('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 99999.00)
ON DUPLICATE KEY UPDATE username = username;

-- Update admin password to 'admin123'
UPDATE users SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE username = 'admin';

-- Insert sample mods
INSERT INTO mods (name, description, status) VALUES
('ADMIN SERVER', 'Premium admin server mod with advanced features', 'active'),
('KING MOD APK', '4.0 (KUMARES) - King of mods with unlimited features', 'active'),
('TRX MOD', 'High-performance mod with TRX optimization', 'active'),
('ZERO KILL 120 FPS', '4.0 - Zero kill mod with 120 FPS support', 'inactive'),
('Zero kill mod key', '4.0 - Zero kill mod key for premium access', 'active');

-- Insert sample license keys
INSERT INTO license_keys (mod_id, license_key, duration, duration_type, price, status) VALUES
(1, 'ADMINSER-1D-A1B2C3D4E5', 1, 'days', 90.00, 'available'),
(1, 'ADMINSER-7D-F6G7H8I9J0', 7, 'days', 350.00, 'available'),
(1, 'ADMINSER-30D-K1L2M3N4O5', 30, 'days', 850.00, 'available'),
(2, 'KINGMOD-5H-P6Q7R8S9T0', 5, 'hours', 30.00, 'available'),
(2, 'KINGMOD-1D-U1V2W3X4Y5', 1, 'days', 60.00, 'available'),
(2, 'KINGMOD-3D-Z6A7B8C9D0', 3, 'days', 150.00, 'available'),
(2, 'KINGMOD-7D-E1F2G3H4I5', 7, 'days', 300.00, 'available'),
(2, 'KINGMOD-30D-J6K7L8M9N0', 30, 'days', 600.00, 'available'),
(2, 'KINGMOD-60D-O1P2Q3R4S5', 60, 'days', 1200.00, 'available'),
(3, 'TRXMOD-1D-T6U7V8W9X0', 1, 'days', 75.00, 'available'),
(3, 'TRXMOD-7D-Y1Z2A3B4C5', 7, 'days', 400.00, 'available'),
(4, 'ZEROKIL-1D-D6E7F8G9H0', 1, 'days', 50.00, 'available'),
(5, 'ZEROKIL-1D-I1J2K3L4M5', 1, 'days', 45.00, 'available');

-- Insert sample users
INSERT INTO users (username, email, password, balance, referral_code) VALUES
('testuser1', 'user1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1000.00, 'ABC12345'),
('testuser2', 'user2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 500.00, 'DEF67890'),
('testuser3', 'user3@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2500.00, 'GHI13579');

-- Insert sample transactions
INSERT INTO transactions (user_id, amount, type, reference, status) VALUES
(2, -90.00, 'purchase', 'License purchase #1', 'completed'),
(2, -350.00, 'purchase', 'License purchase #2', 'completed'),
(3, -60.00, 'purchase', 'License purchase #3', 'completed'),
(3, -150.00, 'purchase', 'License purchase #4', 'completed'),
(4, -75.00, 'purchase', 'License purchase #5', 'completed'),
(2, 100.00, 'balance_add', 'Welcome bonus', 'completed'),
(3, 100.00, 'balance_add', 'Welcome bonus', 'completed'),
(4, 100.00, 'balance_add', 'Welcome bonus', 'completed');

-- Insert sample referral codes
INSERT INTO referral_codes (code, created_by, expires_at, status) VALUES
('REF12345', 1, DATE_ADD(NOW(), INTERVAL 30 DAY), 'active'),
('REF67890', 1, DATE_ADD(NOW(), INTERVAL 30 DAY), 'active'),
('REF13579', 1, DATE_ADD(NOW(), INTERVAL 30 DAY), 'active');

-- Create indexes for better performance
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_referral_code ON users(referral_code);
CREATE INDEX idx_license_keys_mod_id ON license_keys(mod_id);
CREATE INDEX idx_license_keys_status ON license_keys(status);
CREATE INDEX idx_license_keys_sold_to ON license_keys(sold_to);
CREATE INDEX idx_transactions_user_id ON transactions(user_id);
CREATE INDEX idx_transactions_type ON transactions(type);
CREATE INDEX idx_transactions_status ON transactions(status);
CREATE INDEX idx_referral_codes_code ON referral_codes(code);
CREATE INDEX idx_referral_codes_status ON referral_codes(status);

-- Grant permissions (adjust as needed for your setup)
-- GRANT ALL PRIVILEGES ON mod_apk_manager.* TO 'your_username'@'localhost';
-- FLUSH PRIVILEGES;