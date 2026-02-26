<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Redirect to admin panel if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: user_dashboard.php');
        exit();
    }
}

// Redirect to user panel if admin tries to access user pages
function requireUser() {
    requireLogin();
    if (isAdmin()) {
        header('Location: admin_dashboard.php');
        exit();
    }
}

// Login function
function login($username, $password) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['balance'] = $user['balance'];
        return true;
    }
    return false;
}

// Register function
function register($username, $email, $password, $referralCode = null) {
    $pdo = getDBConnection();
    
    // Check if referral code is valid
    $referredBy = null;
    if ($referralCode) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ? AND status = 'active'");
        $stmt->execute([$referralCode]);
        $referredBy = $stmt->fetchColumn();
    }
    
    // Generate unique referral code for new user
    $userReferralCode = strtoupper(substr(md5(uniqid()), 0, 8));
    
    try {
        $pdo->beginTransaction();
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, referral_code, referred_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hashedPassword, $userReferralCode, $referredBy]);
        
        $userId = $pdo->lastInsertId();
        
        // Add welcome bonus
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + 100 WHERE id = ?");
        $stmt->execute([$userId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

// Logout function
function logout() {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Get user data
function getUserData($userId = null) {
    if (!$userId) {
        $userId = $_SESSION['user_id'];
    }
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Update user balance
function updateBalance($userId, $amount, $type = 'balance_add', $reference = null) {
    $pdo = getDBConnection();
    
    try {
        $pdo->beginTransaction();
        
        // Update user balance
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$amount, $userId]);
        
        // Add transaction record
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, reference, status) VALUES (?, ?, ?, ?, 'completed')");
        $stmt->execute([$userId, $amount, $type, $reference]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}
?>
