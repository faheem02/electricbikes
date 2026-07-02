<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$error = $_GET['error'] ?? '';

// Complete delivery for booking sales
if (isset($_GET['deliver'])) {
    $sid = $_GET['deliver'];
    $s = $pdo->prepare("SELECT remaining_amount FROM sales WHERE id=?");
    $s->execute([$sid]);
    $sale = $s->fetch(PDO::FETCH_ASSOC);
    if ($sale && $sale['remaining_amount'] > 0) {
        header('Location: sale_list.php?error=Collect remaining amount first'); exit;
    }
    $pdo->prepare("UPDATE bike_stock SET status='sold' WHERE sale_id=? AND status='booked'")->execute([$sid]);
    logActivity($pdo, 'Complete Delivery', "Sale #$sid delivery completed");
    header('Location: sale_list.php'); exit;
}

$result = $pdo->query("SELECT s.*, c.name as cname,
    GROUP_CONCAT(DISTINCT CONCAT(b.name, ' ', m.name, ' ', v.name, ' (', st.chassis_no, ')') SEPARATOR '<br>') as bikes,
    CASE WHEN s.sale_type='cash' THEN s.total_amount ELSE s.down_payment END as paid_amount
    FROM sales s
    LEFT JOIN customers c ON s.customer_id=c.id
    LEFT JOIN sale_items si ON si.sale_id=s.id
    LEFT JOIN bike_stock st ON si.stock_id=st.id
    LEFT JOIN bike_variants v ON st.variant_id=v.id
    LEFT JOIN bike_models m ON v.model_id=m.id
    LEFT JOIN bike_brands b ON m.brand_id=b.id
    GROUP BY s.id ORDER BY s.id DESC");
$salesList = $result->fetchAll(PDO::FETCH_ASSOC);

// Print view
if (isset($_GET['print'])) {
    ?><!DOCTYPE html><html lang="en"><head><title>Sales List</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family:'Poppins',sans-serif; margin:0; padding:0; box-sizing:border-box; }
        body { padding:40px; background:#f5f5f5; }
        .print-box { max-width:1100px; margin:auto; background:#fff; border-radius:8px; padding:40px; box-shadow:0 2px 10px rgba(0,0,0,.1); }
        .header { text-align:center; border-bottom:2px solid #A04657; padding-bottom:20px; margin-bottom:20px; }
        .header h1 { color:#A04657; font-size:24px; margin:0; }
        .header p { color:#888; margin:5px 0 0; font-size:13px; }
        .info { text-align:right; margin-bottom:15px; font-size:13px; }
        table { width:100%; border-collapse:collapse; font-size:12px; }
        th, td { padding:6px 8px; text-align:left; border-bottom:1px solid #ddd; }
        th { background:#A04657; color:#fff; font-weight:600; font-size:11px; text-transform:uppercase; }
        .text-end { text-align:right; }
        .text-muted { color:#888; }
        .badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:10px; font-weight:600; }
        .bg-success { background:#28a745; color:#fff; }
        .bg-warning { background:#ffc107; color:#333; }
        .bg-danger { background:#dc3545; color:#fff; }
        .bg-info { background:#17a2b8; color:#fff; }
        .footer { text-align:center; margin-top:30px; color:#888; font-size:13px; border-top:1px solid #eee; padding-top:20px; }
        .no-print { text-align:center; margin-top:20px; }
        .no-print button { display:inline-block; padding:10px 24px; margin:0 5px; border-radius:4px; font-size:14px; cursor:pointer; border:none; }
        .btn-primary { background:#A04657; color:#fff; }
        .btn-secondary { background:#6c757d; color:#fff; }
        @media print { body { padding:20px; background:#fff; } .print-box { box-shadow:none; padding:20px; } .no-print { display:none; } }
    </style></head><body>
    <div class="print-box">
        <div class="header"><h1><?php echo e(getSetting($pdo, 'company_name') ?: 'Electric Bikes Showroom'); ?></h1><p>Sales List</p></div>
        <div class="info">Total Sales: <?php echo count($salesList); ?></div>
        <table>
            <tr><th>Invoice</th><th>Customer</th><th>Bikes</th><th>Date</th><th>Type</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Remaining</th><th>Status</th></tr>
            <?php foreach ($salesList as $r): ?>
            <tr>
                <td><strong><?php echo e($r['invoice_no']); ?></strong></td>
                <td><?php echo e($r['cname']); ?></td>
                <td style="font-size:11px;"><?php echo $r['bikes'] ? strip_tags($r['bikes']) : '-'; ?></td>
                <td><?php echo formatDate($r['sale_date']); ?></td>
                <td><span class="badge bg-<?php echo $r['sale_type']=='cash'?'success':($r['sale_type']=='installment'?'warning':'info'); ?>"><?php echo ucfirst($r['sale_type']); ?></span></td>
                <td class="text-end"><?php echo formatMoney($r['total_amount']); ?></td>
                <td class="text-end"><?php echo formatMoney($r['paid_amount']); ?></td>
                <td class="text-end"><?php echo $r['remaining_amount'] > 0 ? formatMoney($r['remaining_amount']) : '-'; ?></td>
                <td><span class="badge bg-<?php echo $r['payment_status']=='paid'?'success':($r['payment_status']=='partial'?'warning':'danger'); ?>"><?php echo ucfirst($r['payment_status']); ?></span></td>
            </tr>
            <?php endforeach; ?>
        </table>
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
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Sale List</span></div>
        <div class="d-flex align-items-center gap-2">
            <a href="?print=1" class="btn btn-outline-dark btn-sm" target="_blank"><i class="bi bi-printer me-1"></i>Print</a>
            <span class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></span>
        </div>
    </div>
    <div class="main-content">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo e($error); ?></div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-cart-check me-2"></i>Sales History</span>
                <a href="sales.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> New Sale</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <table class="table table-hover">
                        <thead><tr><th>Invoice</th><th>Customer</th><th>Bikes</th><th>Date</th><th>Type</th><th>Amount</th><th>Paid</th><th>Remaining</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($salesList as $r):
                            $hasBooked = $pdo->prepare("SELECT COUNT(*) FROM bike_stock WHERE sale_id=? AND status='booked'");
                            $hasBooked->execute([$r['id']]);
                            $hasBooked = $hasBooked->fetchColumn() > 0;
                        ?>
                            <tr>
                                <td><?php echo e($r['invoice_no']); ?></td>
                                <td><?php echo e($r['cname']); ?></td>
                                <td style="font-size:0.85rem;"><?php echo $r['bikes'] ?: '-'; ?></td>
                                <td><?php echo formatDate($r['sale_date']); ?></td>
                                <td><span class="badge bg-<?php echo $r['sale_type']=='cash'?'success':($r['sale_type']=='installment'?'warning text-dark':'info'); ?>"><?php echo ucfirst($r['sale_type']); ?></span></td>
                                <td><?php echo formatMoney($r['total_amount']); ?></td>
                                <td><?php echo formatMoney($r['paid_amount']); ?></td>
                                <td><?php echo $r['remaining_amount'] > 0 ? formatMoney($r['remaining_amount']) : '-'; ?></td>
                                <td><span class="badge bg-<?php echo $r['payment_status']=='paid'?'success':($r['payment_status']=='partial'?'warning text-dark':'danger'); ?>"><?php echo ucfirst($r['payment_status']); ?></span></td>
<td class="text-nowrap">
    <div class="d-flex gap-1">
        <a href="sales.php?print=<?php echo $r['id']; ?>" target="_blank" class="btn btn-sm btn-outline-info" title="Print Invoice"><i class="bi bi-printer"></i></a>
        <?php if ($hasBooked): ?>
            <a href="sale_list.php?deliver=<?php echo $r['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Mark this booking as delivered?')" title="Complete Delivery"><i class="bi bi-check-circle"></i></a>
        <?php endif; ?>
        <a href="sales.php?delete=<?php echo $r['id']; ?>&redirect=list" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete sale?')" title="Delete"><i class="bi bi-trash"></i></a>
    </div>
</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php require_once '../includes/footer.php'; ?>
