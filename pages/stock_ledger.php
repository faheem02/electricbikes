<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$search = $_GET['search'] ?? '';

$query = "SELECT s.id, s.chassis_no, s.motor_no, s.battery_serial, s.charger_serial, s.status, s.created_at,
          v.name as vname, m.name as mname, b.name as bname,
          p.invoice_no as purchase_invoice, p.purchase_date,
          sl.invoice_no as sale_invoice, sl.sale_date
          FROM bike_stock s
          JOIN bike_variants v ON s.variant_id=v.id
          JOIN bike_models m ON v.model_id=m.id
          JOIN bike_brands b ON m.brand_id=b.id
          LEFT JOIN purchases p ON s.purchase_id=p.id
          LEFT JOIN sales sl ON s.sale_id=sl.id
          WHERE 1=1";
$params = [];
if ($search) {
    $query .= " AND (s.chassis_no LIKE ? OR s.motor_no LIKE ? OR s.battery_serial LIKE ? OR s.charger_serial LIKE ? OR v.name LIKE ? OR m.name LIKE ? OR b.name LIKE ?)";
    $params = array_fill(0, 7, "%$search%");
}
$query .= " ORDER BY s.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Print view
if (isset($_GET['print'])) {
    ?><!DOCTYPE html><html lang="en"><head><title>Stock Ledger</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family:'Poppins',sans-serif; margin:0; padding:0; box-sizing:border-box; }
        body { padding:40px; background:#f5f5f5; }
        .print-box { max-width:1200px; margin:auto; background:#fff; border-radius:8px; padding:40px; box-shadow:0 2px 10px rgba(0,0,0,.1); }
        .header { text-align:center; border-bottom:2px solid #A04657; padding-bottom:20px; margin-bottom:20px; }
        .header h1 { color:#A04657; font-size:24px; margin:0; }
        .header p { color:#888; margin:5px 0 0; font-size:13px; }
        .info { text-align:right; margin-bottom:15px; font-size:13px; }
        table { width:100%; border-collapse:collapse; font-size:12px; }
        th, td { padding:6px 10px; text-align:left; border-bottom:1px solid #ddd; }
        th { background:#A04657; color:#fff; font-weight:600; font-size:11px; text-transform:uppercase; }
        .text-end { text-align:right; }
        .text-muted { color:#888; }
        .badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; }
        .bg-secondary { background:#6c757d; color:#fff; }
        .bg-success { background:#28a745; color:#fff; }
        .bg-primary { background:#007bff; color:#fff; }
        .bg-warning { background:#ffc107; color:#333; }
        .bg-danger { background:#dc3545; color:#fff; }
        .footer { text-align:center; margin-top:30px; color:#888; font-size:13px; border-top:1px solid #eee; padding-top:20px; }
        .no-print { text-align:center; margin-top:20px; }
        .no-print button { display:inline-block; padding:10px 24px; margin:0 5px; border-radius:4px; font-size:14px; cursor:pointer; border:none; }
        .btn-primary { background:#A04657; color:#fff; }
        .btn-secondary { background:#6c757d; color:#fff; }
        @media print { body { padding:20px; background:#fff; } .print-box { box-shadow:none; padding:20px; } .no-print { display:none; } }
    </style></head><body>
    <div class="print-box">
        <div class="header"><h1><?php echo e(getSetting($pdo, 'company_name') ?: 'Electric Bikes Showroom'); ?></h1><p>Stock Ledger</p></div>
        <div class="info"><?php if ($search): ?>Search: <strong><?php echo e($search); ?></strong><?php else: ?>All Stock<?php endif; ?> | Total: <?php echo count($rows); ?> units</div>
        <table>
            <tr><th>#</th><th>Variant</th><th>Chassis</th><th>Motor</th><th>Battery</th><th>Charger</th><th>Status</th><th>Source</th><th>Sale Ref</th><th>Date</th></tr>
            <?php $i = 1; foreach ($rows as $r): ?>
            <tr>
                <td class="text-muted"><?php echo $i++; ?></td>
                <td><?php echo e($r['bname'] . ' ' . $r['mname'] . ' ' . $r['vname']); ?></td>
                <td><strong><?php echo e($r['chassis_no']); ?></strong></td>
                <td><?php echo e($r['motor_no'] ?: '-'); ?></td>
                <td><?php echo e($r['battery_serial'] ?: '-'); ?></td>
                <td><?php echo e($r['charger_serial'] ?: '-'); ?></td>
                <td><span class="badge bg-<?php echo $r['status']=='ordered'?'secondary':($r['status']=='in_stock'?'success':($r['status']=='sold'?'primary':($r['status']=='booked'?'warning':($r['status']=='damaged'?'danger':'secondary')))); ?>"><?php echo ucfirst(str_replace('_',' ',$r['status'])); ?></span></td>
                <td><?php echo $r['purchase_invoice'] ? e($r['purchase_invoice']) : 'Direct'; ?></td>
                <td><?php echo $r['sale_invoice'] ? e($r['sale_invoice']) : '-'; ?></td>
                <td><?php echo formatDate($r['created_at']); ?></td>
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
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Stock Ledger</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
    </div>
    <div class="main-content">

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="bi bi-journal-text me-2"></i>Stock Ledger</span>
                <div class="d-flex gap-2 align-items-center">
                <a href="?print=1<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="btn btn-outline-dark btn-sm" target="_blank"><i class="bi bi-printer me-1"></i>Print</a>
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search chassis/motor/battery/variant..." value="<?php echo e($search); ?>" style="width:260px;">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
                    <?php if ($search): ?>
                        <a href="stock_ledger.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </form>
            </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
    <tr>
        <th>#</th>
        <th>Variant</th>
        <th>Chassis</th>
        <th>Motor</th>
        <th>Battery</th>
        <th>Charger</th>
        <th>Status</th>
        <th>Source</th>
        <th>Sale Ref</th>
        <th>Date</th>
    </tr>
</thead>
<tbody>
<?php $i = 1; foreach ($rows as $r): ?>
    <tr>
        <td class="text-muted"><?php echo $i++; ?></td>
        <td><?php echo e($r['bname'] . ' ' . $r['mname'] . ' ' . $r['vname']); ?></td>
        <td class="fw-semibold"><?php echo e($r['chassis_no']); ?></td>
        <td><?php echo e($r['motor_no'] ?: '-'); ?></td>
        <td><?php echo e($r['battery_serial'] ?: '-'); ?></td>
        <td><?php echo e($r['charger_serial'] ?: '-'); ?></td>
        <td>
            <span class="badge bg-<?php echo $r['status']=='ordered'?'secondary':($r['status']=='in_stock'?'success':($r['status']=='sold'?'primary':($r['status']=='booked'?'warning text-dark':($r['status']=='damaged'?'danger':'secondary')))); ?>">
                <?php echo ucfirst(str_replace('_', ' ', $r['status'])); ?>
            </span>
        </td>
        <td>
            <?php if ($r['purchase_invoice']): ?>
                <span class="text-info small"><i class="bi bi-cart-plus"></i> <?php echo e($r['purchase_invoice']); ?></span>
            <?php else: ?>
                <span class="text-muted small">Direct</span>
            <?php endif; ?>
        </td>
        <td>
            <?php if ($r['sale_invoice']): ?>
                <span class="text-secondary small"><i class="bi bi-cart-check"></i> <?php echo e($r['sale_invoice']); ?></span>
            <?php else: ?>
                <span class="text-muted small">-</span>
            <?php endif; ?>
        </td>
        <td class="text-muted small"><?php echo formatDate($r['created_at']); ?></td>
    </tr>
<?php endforeach; ?>
</tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
<?php require_once '../includes/footer.php'; ?>
