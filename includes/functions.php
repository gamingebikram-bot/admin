<?php
require_once 'config/database.php';

// Generate random license key
function generateLicenseKey($modName, $duration, $durationType) {
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $modName), 0, 8));
    $suffix = strtoupper(substr(md5(uniqid()), 0, 10));
    return $prefix . '-' . $duration . $durationType[0] . '-' . $suffix;
}

// Generate referral code
function generateReferralCode() {
    return strtoupper(substr(md5(uniqid()), 0, 8));
}

// Format currency
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

// Format date
function formatDate($date) {
    return date('d M Y H:i', strtotime($date));
}

// Get mod statistics
function getModStats() {
    $pdo = getDBConnection();
    
    $stats = [];
    
    // Total mods
    $stmt = $pdo->query("SELECT COUNT(*) FROM mods");
    $stats['total_mods'] = $stmt->fetchColumn();
    
    // Total license keys
    $stmt = $pdo->query("SELECT COUNT(*) FROM license_keys");
    $stats['total_keys'] = $stmt->fetchColumn();
    
    // Available keys
    $stmt = $pdo->query("SELECT COUNT(*) FROM license_keys WHERE status = 'available'");
    $stats['available_keys'] = $stmt->fetchColumn();
    
    // Sold keys
    $stmt = $pdo->query("SELECT COUNT(*) FROM license_keys WHERE status = 'sold'");
    $stats['sold_keys'] = $stmt->fetchColumn();
    
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
    $stats['total_users'] = $stmt->fetchColumn();
    
    return $stats;
}

// Get recent mods
function getRecentMods($limit = 5) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM mods ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get recent users
function getRecentUsers($limit = 5) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get user transactions
function getUserTransactions($userId, $limit = 50) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all transactions (admin)
function getAllTransactions($filters = []) {
    $pdo = getDBConnection();
    
    $where = ["1=1"];
    $params = [];
    
    if (!empty($filters['user_id'])) {
        $where[] = "t.user_id = ?";
        $params[] = $filters['user_id'];
    }
    
    if (!empty($filters['type'])) {
        $where[] = "t.type = ?";
        $params[] = $filters['type'];
    }
    
    if (!empty($filters['status'])) {
        $where[] = "t.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['search'])) {
        $where[] = "(u.username LIKE ? OR t.reference LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql = "SELECT t.*, u.username FROM transactions t 
            LEFT JOIN users u ON t.user_id = u.id 
            WHERE " . implode(' AND ', $where) . " 
            ORDER BY t.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get available license keys
function getAvailableKeys($modId = null) {
    $pdo = getDBConnection();
    
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
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Purchase license key
function purchaseLicenseKey($userId, $keyId) {
    $pdo = getDBConnection();
    
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
        
        // Get user balance
        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
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
        return $key;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Upload file
function uploadFile($file, $uploadDir = 'uploads/') {
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileName = time() . '_' . basename($file['name']);
    $targetPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [
            'success' => true,
            'file_name' => $fileName,
            'file_path' => $targetPath,
            'file_size' => $file['size']
        ];
    }
    
    return ['success' => false, 'error' => 'Upload failed'];
}
?>