<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$from = $_GET['from_date'] ?? date('Y-m-01');
$to = $_GET['to_date'] ?? date('Y-m-t');
$type = $_GET['type'] ?? 'daily_sales';

$result = null;
$title = '';

switch ($type) {
    case 'daily_sales':
        $title = 'Daily Sales Report';
        $result = $pdo->prepare("SELECT sale_date, COUNT(*) as invoices, SUM(total_amount) as total, SUM(discount) as discounts FROM sales WHERE sale_date BETWEEN ? AND ? GROUP BY sale_date ORDER BY sale_date DESC");
        $result->execute([$from, $to]);
        break;
    case 'purchase':
        $title = 'Purchase Report';
        $result = $pdo->prepare("SELECT p.purchase_date, p.invoice_no, s.name as supplier, p.total_amount, p.paid_amount, p.payment_status FROM purchases p LEFT JOIN suppliers s ON p.supplier_id=s.id WHERE p.purchase_date BETWEEN ? AND ? ORDER BY p.purchase_date DESC");
        $result->execute([$from, $to]);
        break;
    case 'stock':
        $title = 'Stock Report';
        $result = $pdo->query("SELECT v.name as variant, m.name as model, b.name as brand, v.color, v.purchase_price, v.sale_price, (SELECT COUNT(*) FROM bike_stock WHERE variant_id=v.id AND status='in_stock') as in_stock, (SELECT COUNT(*) FROM bike_stock WHERE variant_id=v.id AND status='sold') as sold FROM bike_variants v JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id ORDER BY b.name, m.name, v.name");
        break;
    case 'expense':
        $title = 'Expense Report';
        $result = $pdo->prepare("SELECT category, SUM(amount) as total, COUNT(*) as count FROM expenses WHERE date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC");
        $result->execute([$from, $to]);
        break;
    case 'profit':
        $title = 'Profit Report';
        $salesTotal = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE sale_date BETWEEN ? AND ?");
        $salesTotal->execute([$from, $to]);
        $purchaseTotal = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM purchases WHERE purchase_date BETWEEN ? AND ?");
        $purchaseTotal->execute([$from, $to]);
        $expenseTotal = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE date BETWEEN ? AND ?");
        $expenseTotal->execute([$from, $to]);
        $salesTotal = $salesTotal->fetchColumn();
        $purchaseTotal = $purchaseTotal->fetchColumn();
        $expenseTotal = $expenseTotal->fetchColumn();
        $profit = $salesTotal - $purchaseTotal - $expenseTotal;
        break;
}

// Print view
if (isset($_GET['print'])) {
    require_once __DIR__ . '/../includes/company.php';
    ?>
    <!DOCTYPE html><html lang="en"><head><title><?php echo e($title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { font-family:'Poppins',sans-serif; margin:0; padding:0; box-sizing:border-box; }
        body { background:#fff; }
        .print-box { width:100%; }
        .header { text-align:center; border-bottom:2px solid #095D3B; padding-bottom:12px; margin-bottom:12px; }
        .header h1 { color:#095D3B; font-size:20px; margin:0; }
        .header .meta-line { margin:2px 0; font-size:11px; color:#666; }
        .header .meta-line i { color:#095D3B; margin-right:4px; width:12px; text-align:center; }
        .report-title { text-align:center; font-size:14px; font-weight:700; color:#333; margin:10px 0 5px; }
        .date-range { text-align:center; font-size:11px; color:#888; margin-bottom:12px; }
        table { width:100%; border-collapse:collapse; font-size:10px; }
        th, td { padding:5px 6px; text-align:left; border-bottom:1px solid #ddd; }
        th { background:#095D3B; color:#fff; font-weight:600; font-size:9px; text-transform:uppercase; }
        .text-end { text-align:right; }
        .fw-semibold { font-weight:600; }
        .profit-card { display:flex; gap:15px; justify-content:center; margin:15px 0; }
        .profit-card .card { flex:1; text-align:center; padding:12px; border:1px solid #ddd; border-radius:6px; }
        .profit-card .card .amount { font-size:16px; font-weight:700; }
        .profit-card .card .label { font-size:10px; color:#888; margin-top:3px; }
        .footer { text-align:center; margin-top:15px; color:#888; font-size:10px; border-top:1px solid #eee; padding-top:10px; }
        .no-print { text-align:center; margin-top:15px; }
        .no-print button { display:inline-block; padding:8px 20px; margin:0 5px; border-radius:4px; font-size:13px; cursor:pointer; border:none; }
        .btn-primary { background:#095D3B; color:#fff; }
        .btn-secondary { background:#6c757d; color:#fff; }
        @media print { body { padding:0; background:#fff; } .print-box { box-shadow:none; } .no-print { display:none; } }
    </style></head><body>
    <div class="print-box">
        <?php $pt = $title; include '../includes/print_header.php'; ?>
        <div class="date-range">From: <?php echo formatDate($from); ?> — To: <?php echo formatDate($to); ?></div>
        <?php if ($type == 'profit'): ?>
            <div class="profit-card">
                <div class="card"><div class="amount" style="color:#28a745;"><?php echo formatMoney($salesTotal); ?></div><div class="label">Total Sales</div></div>
                <div class="card"><div class="amount" style="color:#dc3545;"><?php echo formatMoney($purchaseTotal); ?></div><div class="label">Total Purchases</div></div>
                <div class="card"><div class="amount" style="color:#ffc107;"><?php echo formatMoney($expenseTotal); ?></div><div class="label">Total Expenses</div></div>
                <div class="card"><div class="amount" style="color:<?php echo $profit>=0?'#28a745':'#dc3545'; ?>;"><?php echo formatMoney($profit); ?></div><div class="label">Net Profit</div></div>
            </div>
        <?php elseif ($type == 'stock' && $result): ?>
            <table>
                <thead><tr><th>Brand</th><th>Model</th><th>Variant</th><th>Color</th><th class="text-end">Purchase</th><th class="text-end">Sale</th><th class="text-center">In Stock</th><th class="text-center">Sold</th></tr></thead>
                <tbody><?php while ($r = $result->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr><td><?php echo e($r['brand']); ?></td><td><?php echo e($r['model']); ?></td><td><?php echo e($r['variant']); ?></td><td><?php echo e($r['color']); ?></td><td class="text-end"><?php echo formatMoney($r['purchase_price']); ?></td><td class="text-end"><?php echo formatMoney($r['sale_price']); ?></td><td class="text-center"><?php echo $r['in_stock']; ?></td><td class="text-center"><?php echo $r['sold']; ?></td></tr>
                <?php endwhile; ?></tbody>
            </table>
        <?php elseif ($type == 'expense' && $result): ?>
            <table>
                <thead><tr><th>Category</th><th>Count</th><th class="text-end">Total</th></tr></thead>
                <tbody><?php while ($r = $result->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr><td><?php echo e($r['category']); ?></td><td><?php echo $r['count']; ?></td><td class="text-end fw-semibold"><?php echo formatMoney($r['total']); ?></td></tr>
                <?php endwhile; ?></tbody>
            </table>
        <?php elseif ($result): ?>
            <?php $first = $result->fetch(PDO::FETCH_ASSOC); $cols = $first ? array_keys($first) : []; $result->execute(); ?>
            <table>
                <thead><tr><?php foreach ($cols as $col): ?><th><?php echo ucfirst(str_replace('_',' ',$col)); ?></th><?php endforeach; ?></tr></thead>
                <tbody><?php while ($r = $result->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr><?php foreach ($r as $v): ?><td><?php echo is_numeric($v)&&strpos((string)$v,'.')!==false?formatMoney($v):e($v); ?></td><?php endforeach; ?></tr>
                <?php endwhile; ?></tbody>
            </table>
        <?php endif; ?>
        <div class="footer">Generated on <?php echo date('d-m-Y H:i'); ?></div>
        <div class="no-print"><button onclick="window.print()" class="btn-primary"><i class="bi bi-printer"></i> Print</button> <button onclick="window.close()" class="btn-secondary">Close</button></div>
    </div>
    </body></html>
    <?php exit;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Reports</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?></div>
    </div>
    <div class="main-content">
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <select name="type" class="form-select">
                            <option value="daily_sales" <?php echo $type=='daily_sales'?'selected':''; ?>>Daily Sales</option>
                            <option value="purchase" <?php echo $type=='purchase'?'selected':''; ?>>Purchase</option>
                            <option value="stock" <?php echo $type=='stock'?'selected':''; ?>>Stock</option>
                            <option value="expense" <?php echo $type=='expense'?'selected':''; ?>>Expense</option>
                            <option value="profit" <?php echo $type=='profit'?'selected':''; ?>>Profit & Loss</option>
                        </select>
                    </div>
                    <div class="col-md-2"><input type="date" name="from_date" class="form-control" value="<?php echo $from; ?>"></div>
                    <div class="col-md-2"><input type="date" name="to_date" class="form-control" value="<?php echo $to; ?>"></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary">Generate</button></div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><?php echo $title; ?></span>
                <a href="?print=1&type=<?php echo urlencode($type); ?>&from_date=<?php echo urlencode($from); ?>&to_date=<?php echo urlencode($to); ?>" class="btn btn-outline-dark btn-sm" target="_blank"><i class="bi bi-printer me-1"></i>Print</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <?php if ($type == 'profit'): ?>
                        <div class="row g-4 text-center">
                            <div class="col-md-3"><div class="card p-4 border-success"><div class="text-success fw-bold fs-4"><?php echo formatMoney($salesTotal); ?></div><div class="text-muted">Total Sales</div></div></div>
                            <div class="col-md-3"><div class="card p-4 border-danger"><div class="text-danger fw-bold fs-4"><?php echo formatMoney($purchaseTotal); ?></div><div class="text-muted">Total Purchases</div></div></div>
                            <div class="col-md-3"><div class="card p-4 border-warning"><div class="text-warning fw-bold fs-4"><?php echo formatMoney($expenseTotal); ?></div><div class="text-muted">Total Expenses</div></div></div>
                            <div class="col-md-3"><div class="card p-4 border-<?php echo $profit >= 0 ? 'success' : 'danger'; ?>"><div class="<?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?> fw-bold fs-4"><?php echo formatMoney($profit); ?></div><div class="text-muted">Net Profit</div></div></div>
                        </div>
                    <?php elseif ($type == 'stock' && $result): ?>
                        <table class="table table-hover">
                            <thead><tr><th>Brand</th><th>Model</th><th>Variant</th><th>Color</th><th>Purchase Price</th><th>Sale Price</th><th>In Stock</th><th>Sold</th></tr></thead>
                            <tbody>
                            <?php while ($r = $result->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr><td><?php echo e($r['brand']); ?></td><td><?php echo e($r['model']); ?></td><td><?php echo e($r['variant']); ?></td><td><?php echo e($r['color']); ?></td><td><?php echo formatMoney($r['purchase_price']); ?></td><td><?php echo formatMoney($r['sale_price']); ?></td><td><span class="badge bg-success"><?php echo $r['in_stock']; ?></span></td><td><span class="badge bg-secondary"><?php echo $r['sold']; ?></span></td></tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php elseif ($type == 'expense' && $result): ?>
                        <table class="table table-hover">
                            <thead><tr><th>Category</th><th>Count</th><th>Total</th></tr></thead>
                            <tbody>
                            <?php while ($r = $result->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr><td><span class="badge bg-secondary"><?php echo e($r['category']); ?></span></td><td><?php echo $r['count']; ?></td><td class="text-danger fw-semibold"><?php echo formatMoney($r['total']); ?></td></tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php elseif ($result): ?>
                        <?php $first = $result->fetch(PDO::FETCH_ASSOC); $cols = $first ? array_keys($first) : []; $result->execute(); ?>
                        <table class="table table-hover">
                            <thead><tr><?php foreach ($cols as $col): ?><th><?php echo ucfirst(str_replace('_', ' ', $col)); ?></th><?php endforeach; ?></tr></thead>
                            <tbody>
                            <?php while ($r = $result->fetch(PDO::FETCH_ASSOC)): ?>
                                <tr><?php foreach ($r as $v): ?><td><?php echo is_numeric($v) && strpos((string)$v, '.') !== false ? formatMoney($v) : e($v); ?></td><?php endforeach; ?></tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php require_once '../includes/footer.php'; ?>
