<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        $rootPages = ['login.php', 'index.php', 'logout.php'];
        $loc = in_array(basename($_SERVER['PHP_SELF']), $rootPages) ? 'login.php' : '../login.php';
        header("Location: $loc");
        exit;
    }
}

function hasRole($allowedRoles) {
    if (!isLoggedIn()) return false;
    return in_array($_SESSION['role_name'] ?? '', (array)$allowedRoles);
}

function requireRole($allowedRoles) {
    if (!hasRole($allowedRoles)) {
        $_SESSION['error'] = 'You do not have permission to access this page.';
        $rootPages = ['login.php', 'index.php', 'logout.php'];
        $loc = in_array(basename($_SERVER['PHP_SELF']), $rootPages) ? 'index.php' : '../index.php';
        header("Location: $loc");
        exit;
    }
}

function logActivity($pdo, $action, $description = '') {
    $uid = $_SESSION['user_id'] ?? 0;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$uid, $action, $description, $ip]);
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            die('CSRF validation failed.');
        }
    }
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function formatDate($date) {
    return $date ? date('d-m-Y', strtotime($date)) : '-';
}

function formatMoney($amount) {
    return number_format($amount ?? 0, 2);
}

function getSetting($pdo, $key) {
    $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = ?");
    $stmt->execute([$key]);
    return $stmt->fetchColumn() ?: '';
}

function StrLimit($str, $limit = 50) {
    if (mb_strlen($str) <= $limit) return $str;
    return mb_substr($str, 0, $limit) . '...';
}

function nextInvoiceNo($pdo, $prefix, $table = 'sales', $col = 'invoice_no') {
    $date = date('Ymd');
    $base = $prefix . $date . '-';
    $stmt = $pdo->prepare("SELECT `$col` FROM `$table` WHERE `$col` LIKE ? ORDER BY `$col` DESC LIMIT 1");
    $stmt->execute([$base . '%']);
    $last = $stmt->fetchColumn();
    $seq = $last ? intval(substr($last, strlen($base))) + 1 : 1;
    $chk = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$col` = ?");
    while (true) {
        $cand = $base . str_pad($seq, 3, '0', STR_PAD_LEFT);
        $chk->execute([$cand]);
        if ($chk->fetchColumn() == 0) break;
        $seq++;
    }
    return $cand;
}
?>
