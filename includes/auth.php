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

// Recalculate sale totals from its items and rebuild auto ledger entries
if (!function_exists('rebuildSaleLedger')) {
    function rebuildSaleLedger($pdo, $sid) {
        $st = $pdo->prepare("SELECT * FROM sales WHERE id=?");
        $st->execute([$sid]);
        $sale = $st->fetch(PDO::FETCH_ASSOC);
        if (!$sale) return;

        $t = $pdo->prepare("SELECT COALESCE(SUM(sale_price),0) FROM sale_items WHERE sale_id=?");
        $t->execute([$sid]);
        $total = floatval($t->fetchColumn());

        $c = $pdo->prepare("SELECT COALESCE(SUM(credit-debit),0) FROM customer_ledger WHERE customer_id=? AND description LIKE ?");
        $c->execute([$sale['customer_id'], "%(INV: {$sale['invoice_no']})%"]);
        $collected = floatval($c->fetchColumn());

        $remaining = max(0, $total - $sale['discount'] - $sale['down_payment'] - $collected);
        $payStatus = ($remaining <= 0) ? 'paid' : (($sale['down_payment'] > 0 || $collected > 0) ? 'partial' : 'unpaid');
        $pdo->prepare("UPDATE sales SET total_amount=?, remaining_amount=?, payment_status=? WHERE id=?")
            ->execute([$total, $remaining, $payStatus, $sid]);

        $del = $pdo->prepare("DELETE FROM customer_ledger WHERE sale_id=?");
        $del->execute([$sid]);
        if ($del->rowCount() == 0) {
            $d = $sale['invoice_no'];
            $pdo->prepare("DELETE FROM customer_ledger WHERE customer_id=? AND (description=? OR description LIKE ? OR description=? OR description LIKE ?)")
                ->execute([$sale['customer_id'], "Sale INV $d", "Sale INV $d %", "Payment INV $d", "Payment INV $d %"]);
        }

        $bn = $pdo->prepare("SELECT CONCAT(b.name,' ',m.name,' ',v.name,' (',s.chassis_no,')') FROM sale_items si JOIN bike_stock s ON si.stock_id=s.id JOIN bike_variants v ON s.variant_id=v.id JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id WHERE si.sale_id=?");
        $bn->execute([$sid]);
        $names = $bn->fetchAll(PDO::FETCH_COLUMN);
        $bikeDetail = !empty($names) ? ' - ' . implode(', ', $names) : '';

        $pdo->prepare("INSERT INTO customer_ledger (customer_id, sale_id, date, description, debit, credit, balance) VALUES (?,?,?,?,?,0,0)")
            ->execute([$sale['customer_id'], $sid, $sale['sale_date'], "Sale INV {$sale['invoice_no']}$bikeDetail", $total]);
        if ($sale['down_payment'] > 0) {
            $pdo->prepare("INSERT INTO customer_ledger (customer_id, sale_id, date, description, debit, credit, balance) VALUES (?,?,?,?,0,?,0)")
                ->execute([$sale['customer_id'], $sid, $sale['sale_date'], "Payment INV {$sale['invoice_no']}$bikeDetail", $sale['down_payment']]);
        }
    }
}
?>
