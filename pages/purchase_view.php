<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';
$err = '';

// Receive single bike with serial numbers (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_single'])) {
    $bid = intval($_POST['receive_id']);
    $chassis = trim($_POST['chassis_no'] ?? '');
    $motor = trim($_POST['motor_no'] ?? '');
    $battery = trim($_POST['battery_serial'] ?? '');
    $charger = trim($_POST['charger_serial'] ?? '');

    if ($chassis === '') {
        $err = 'Chassis number is required.';
    } else {
        try {
            $st = $pdo->prepare("SELECT purchase_id FROM bike_stock WHERE id=? AND status='ordered'");
            $st->execute([$bid]);
            $bs = $st->fetch(PDO::FETCH_ASSOC);
            $pid = $bs ? $bs['purchase_id'] : 0;

            $pdo->prepare("UPDATE bike_stock SET chassis_no=?, motor_no=NULLIF(?,''), battery_serial=NULLIF(?,''), charger_serial=NULLIF(?,''), status='in_stock' WHERE id=? AND status='ordered'")
                ->execute([$chassis, $motor, $battery, $charger, $bid]);

            if ($pid) {
                $total = $pdo->prepare("SELECT COUNT(*) FROM bike_stock WHERE purchase_id=? AND status IN ('ordered','in_stock')");
                $total->execute([$pid]);
                $total = $total->fetchColumn();
                $received = $pdo->prepare("SELECT COUNT(*) FROM bike_stock WHERE purchase_id=? AND status='in_stock'");
                $received->execute([$pid]);
                $received = $received->fetchColumn();
                $newStatus = $received >= $total ? 'completed' : 'partial';
                $pdo->prepare("UPDATE purchases SET status=? WHERE id=?")->execute([$newStatus, $pid]);
                logActivity($pdo, 'Receive Stock', "Purchase #$pid, Received bike_stock #$bid, Chassis: $chassis");
            }
            header('Location: purchase_view.php'); exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $err = 'Duplicate serial number! Chassis, Motor, Battery, or Charger already exists.';
            } else {
                throw $e;
            }
        }
    }
}

// Receive all bikes with serial numbers (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_all_submit'])) {
    $pid = intval($_POST['purchase_id']);
    $ids = $_POST['receive_all_ids'] ?? [];
    $chassisList = $_POST['all_chassis_no'] ?? [];
    $motorList = $_POST['all_motor_no'] ?? [];
    $batteryList = $_POST['all_battery_serial'] ?? [];
    $chargerList = $_POST['all_charger_serial'] ?? [];

    $valid = true;
    foreach ($chassisList as $ch) {
        if (trim($ch) === '') { $valid = false; break; }
    }

    if (!$valid || empty($ids)) {
        $err = 'Chassis number is required for every bike.';
    } else {
        try {
            $upd = $pdo->prepare("UPDATE bike_stock SET chassis_no=?, motor_no=NULLIF(?,''), battery_serial=NULLIF(?,''), charger_serial=NULLIF(?,''), status='in_stock' WHERE id=? AND status='ordered'");
            foreach ($ids as $i => $bid) {
                $upd->execute([
                    $chassisList[$i],
                    $motorList[$i] ?? '',
                    $batteryList[$i] ?? '',
                    $chargerList[$i] ?? '',
                    $bid
                ]);
            }

            $total = $pdo->prepare("SELECT COUNT(*) FROM bike_stock WHERE purchase_id=? AND status IN ('ordered','in_stock')");
            $total->execute([$pid]);
            $total = $total->fetchColumn();
            $received = $pdo->prepare("SELECT COUNT(*) FROM bike_stock WHERE purchase_id=? AND status='in_stock'");
            $received->execute([$pid]);
            $received = $received->fetchColumn();
            $newStatus = $received >= $total ? 'completed' : 'partial';
            $pdo->prepare("UPDATE purchases SET status=? WHERE id=?")->execute([$newStatus, $pid]);
            logActivity($pdo, 'Receive Stock', "Purchase #$pid, Received " . count($ids) . " bikes");
            header('Location: purchase_view.php'); exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $err = 'Duplicate serial number! One or more Chassis, Motor, Battery, or Charger numbers already exist.';
            } else {
                throw $e;
            }
        }
    }
}

// Receive a single bike stock (GET - legacy fallback)
if (isset($_GET['receive'])) {
    $bid = intval($_GET['receive']);
    $st = $pdo->prepare("SELECT purchase_id FROM bike_stock WHERE id=?");
    $st->execute([$bid]);
    $bs = $st->fetch(PDO::FETCH_ASSOC);
    $pid = $bs ? $bs['purchase_id'] : 0;
    $pdo->prepare("UPDATE bike_stock SET status='in_stock' WHERE id=? AND status='ordered'")->execute([$bid]);
    if ($pid) {
        $total = $pdo->prepare("SELECT COUNT(*) FROM bike_stock WHERE purchase_id=? AND status IN ('ordered','in_stock')");
        $total->execute([$pid]);
        $total = $total->fetchColumn();
        $received = $pdo->prepare("SELECT COUNT(*) FROM bike_stock WHERE purchase_id=? AND status='in_stock'");
        $received->execute([$pid]);
        $received = $received->fetchColumn();
        $newStatus = $received >= $total ? 'completed' : 'partial';
        $pdo->prepare("UPDATE purchases SET status=? WHERE id=?")->execute([$newStatus, $pid]);
        logActivity($pdo, 'Receive Stock', "Purchase #$pid, Received bike_stock #$bid");
    }
    header('Location: purchase_view.php'); exit;
}

// Receive all ordered bikes for a purchase
if (isset($_GET['receive_all'])) {
    $pid = intval($_GET['receive_all']);
    $pdo->prepare("UPDATE bike_stock SET status='in_stock' WHERE purchase_id=? AND status='ordered'")->execute([$pid]);
    // Update purchase status
    $total = $pdo->prepare("SELECT COUNT(*) FROM bike_stock WHERE purchase_id=? AND status IN ('ordered','in_stock')");
    $total->execute([$pid]);
    $total = $total->fetchColumn();
    $received = $pdo->prepare("SELECT COUNT(*) FROM bike_stock WHERE purchase_id=? AND status='in_stock'");
    $received->execute([$pid]);
    $received = $received->fetchColumn();
    $newStatus = $received >= $total ? 'completed' : 'partial';
    $pdo->prepare("UPDATE purchases SET status=? WHERE id=?")->execute([$newStatus, $pid]);
    logActivity($pdo, 'Receive Stock', "Purchase #$pid, Received all");
    header('Location: purchase_view.php'); exit;
}

// Print a single purchase order with per-bike details
if (isset($_GET['print_order'])) {
    $oid = intval($_GET['print_order']);
    require_once __DIR__ . '/../includes/company.php';
    $p = $pdo->prepare("SELECT p.*, s.name as sname, s.phone as sphone, s.address as saddress FROM purchases p LEFT JOIN suppliers s ON p.supplier_id=s.id WHERE p.id=?");
    $p->execute([$oid]);
    $po = $p->fetch(PDO::FETCH_ASSOC);
    if (!$po) { header('Location: purchase_view.php'); exit; }
    $stock = $pdo->prepare("SELECT s.*, v.name as vname, v.color, m.name as mname, b.name as bname FROM bike_stock s JOIN bike_variants v ON s.variant_id=v.id JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id WHERE s.purchase_id=? ORDER BY s.id");
    $stock->execute([$oid]);
    $stockItems = $stock->fetchAll(PDO::FETCH_ASSOC);
    $poCost = $po['total_amount'] + $po['expenses'];
    $poDue = max(0, $poCost - $po['paid_amount']);
    ?>
    <!DOCTYPE html><html lang="en"><head><title>Purchase Order <?php echo e($po['invoice_no']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { font-family: 'Poppins', sans-serif; margin:0; padding:0; box-sizing:border-box; }
        body { background:#f0f0f0; padding:25px; }
        .inv { max-width:760px; margin:auto; background:#fff; box-shadow:0 2px 12px rgba(0,0,0,.12); }
        .inv-inner { padding:28px 32px; }
        .brand { text-align:center; }
        .brand h1 { color:#095D3B; font-size:30px; font-weight:700; letter-spacing:1px; margin:0; }
        .brand .tagline { color:#095D3B; font-weight:600; font-size:11px; letter-spacing:3px; text-transform:uppercase; margin-top:3px; }
        .brand .meta { color:#666; font-size:11px; margin-top:7px; line-height:1.6; }
        .brand .meta .meta-line { margin:2px 0; }
        .brand .meta .meta-line i { color:#095D3B; margin-right:5px; width:14px; text-align:center; }
        .title-row { border-bottom:3px solid #095D3B; margin-top:14px; padding-bottom:7px; text-align:center; }
        .title-row h2 { color:#095D3B; font-size:19px; font-weight:700; letter-spacing:5px; margin:0; text-transform:uppercase; }
        .inv-meta { display:flex; justify-content:space-between; font-size:11px; margin-top:10px; color:#333; }
        .inv-meta b { font-weight:600; color:#095D3B; }
        .sec { margin-top:15px; }
        .sec-bar { background:#095D3B; color:#fff; padding:5px 10px; font-size:10.5px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; }
        .sec-body { border:1px solid #e4e4e4; border-top:none; padding:10px; }
        .grid2 { display:grid; grid-template-columns:1fr 1fr; gap:7px 18px; }
        .grid2 .f span { color:#888; font-size:9px; text-transform:uppercase; letter-spacing:.5px; display:block; }
        .grid2 .f { color:#222; font-size:12px; }
        .full { grid-column:1 / -1; }
        table.items { width:100%; border-collapse:collapse; }
        table.items th, table.items td { padding:6px 7px; font-size:10.5px; text-align:left; border-bottom:1px solid #eee; }
        table.items th { background:#095D3B; color:#fff; font-weight:600; font-size:10px; text-transform:uppercase; letter-spacing:.4px; }
        .text-end { text-align:right; }
        .bike-name { font-weight:600; color:#095D3B; }
        .st { display:inline-block; padding:2px 8px; border-radius:10px; font-size:9px; font-weight:600; text-transform:uppercase; }
        .st-ordered { background:#6c757d; color:#fff; }
        .st-in_stock { background:#28a745; color:#fff; }
        .st-sold { background:#0d6efd; color:#fff; }
        .st-booked { background:#ffc107; color:#333; }
        .st-damaged { background:#dc3545; color:#fff; }
        .sum .row { display:flex; justify-content:space-between; align-items:center; padding:4px 0; font-size:12px; color:#333; }
        .sum .row span:first-child { color:#666; }
        .sum .net { border-top:2px solid #095D3B; font-weight:700; font-size:14px; margin-top:3px; padding-top:7px; }
        .sum .net span:first-child { color:#095D3B; }
        .sum .paid { color:#1d8a4e; font-weight:700; }
        .sum .due { color:#d62839; font-weight:700; }
        .pay-badge { display:inline-block; background:#e8f3ee; color:#095D3B; padding:2px 10px; border-radius:10px; font-size:10px; font-weight:600; text-transform:uppercase; }
        .foot { background:#095D3B; color:#fff; text-align:center; padding:11px; font-size:11px; font-weight:600; letter-spacing:.5px; }
        .no-print { text-align:center; margin-top:18px; }
        .no-print button { display:inline-block; padding:10px 24px; margin:0 5px; border-radius:4px; font-size:14px; cursor:pointer; border:none; }
        .btn-primary { background:#095D3B; color:#fff; }
        .btn-secondary { background:#6c757d; color:#fff; }
        @media print { body { padding:0; background:#fff; } .inv { box-shadow:none; } .no-print { display:none; } }
    </style>
    </head><body>
    <div class="inv">
        <div class="inv-inner">
            <div class="brand">
                <img src="../pic/alhafiz.jpeg" alt="Logo" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin-bottom:10px;border:2px solid #095D3B;">
                <h1><?php echo COMPANY_NAME; ?></h1>
                <div class="tagline">EV Scooties &amp; Electric Motorcycles</div>
                <div class="meta">
                    <div class="meta-line"><i class="fas fa-map-marker-alt"></i> <?php echo COMPANY_ADDRESS; ?></div>
                    <div class="meta-line"><?php echo COMPANY_TAGLINE; ?> | <?php echo COMPANY_LINE3; ?></div>
                    <div class="meta-line"><i class="fab fa-whatsapp"></i> <?php echo COMPANY_PHONES; ?></div>
                    <div class="meta-line"><i class="fas fa-envelope"></i> <?php echo COMPANY_EMAIL; ?></div>
                </div>
            </div>
            <div class="title-row"><h2>Purchase Order</h2></div>
            <div class="inv-meta">
                <div><b>Invoice No:</b> <?php echo e($po['invoice_no']); ?></div>
                <div><b>Date:</b> <?php echo formatDate($po['purchase_date']); ?></div>
            </div>

            <div class="sec">
                <div class="sec-bar">Supplier Details</div>
                <div class="sec-body">
                    <div class="grid2">
                        <div class="f"><span>Supplier Name</span><?php echo e($po['sname'] ?? '-'); ?></div>
                        <div class="f"><span>Contact No</span><?php echo e($po['sphone'] ?: '-'); ?></div>
                        <div class="f full"><span>Address</span><?php echo e($po['saddress'] ?: '-'); ?></div>
                    </div>
                </div>
            </div>

            <div class="sec">
                <div class="sec-bar">Bike Details</div>
                <div class="sec-body" style="padding:0;">
                    <table class="items">
                        <tr>
                            <th>#</th><th>Bike</th><th>Chassis No</th><th>Motor No</th><th>Battery No</th><th>Charger No</th><th class="text-end">Purchase</th><th class="text-end">Sale</th><th>Status</th>
                        </tr>
                        <?php $i = 1; foreach ($stockItems as $bs): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td class="bike-name"><?php echo e(trim($bs['bname'] . ' ' . $bs['mname'] . ' ' . $bs['vname'] . ($bs['color'] ? ' [' . $bs['color'] . ']' : ''))); ?></td>
                            <td><?php echo e($bs['chassis_no'] ?: '-'); ?></td>
                            <td><?php echo e($bs['motor_no'] ?: '-'); ?></td>
                            <td><?php echo e($bs['battery_serial'] ?: '-'); ?></td>
                            <td><?php echo e($bs['charger_serial'] ?: '-'); ?></td>
                            <td class="text-end"><?php echo formatMoney($bs['purchase_price']); ?></td>
                            <td class="text-end"><?php echo formatMoney($bs['sale_price']); ?></td>
                            <td><span class="st st-<?php echo $bs['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $bs['status'])); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <div class="sec">
                <div class="sec-bar">Order Summary</div>
                <div class="sec-body">
                    <div class="sum">
                        <div class="row"><span>Total Purchase (<?php echo count($stockItems); ?> bikes)</span><span><?php echo formatMoney($po['total_amount']); ?></span></div>
                        <div class="row"><span>Expenses</span><span><?php echo formatMoney($po['expenses']); ?></span></div>
                        <div class="row net"><span>Total Cost</span><span><?php echo formatMoney($poCost); ?></span></div>
                        <div class="row"><span>Amount Paid</span><span class="paid"><?php echo formatMoney($po['paid_amount']); ?></span></div>
                        <div class="row"><span>Balance Due</span><span class="<?php echo $poDue > 0 ? 'due' : 'paid'; ?>"><?php echo formatMoney($poDue); ?></span></div>
                        <div class="row"><span>Payment Status</span><span class="pay-badge"><?php echo ucfirst($po['payment_status']); ?></span></div>
                        <div class="row"><span>Order Status</span><span class="pay-badge"><?php echo ucfirst($po['status']); ?></span></div>
                    </div>
                </div>
            </div>

            <?php if (!empty($po['notes'])): ?>
            <div class="sec">
                <div class="sec-bar">Notes</div>
                <div class="sec-body"><div class="notes" style="font-size:10.5px;color:#444;white-space:pre-line;"><?php echo e($po['notes']); ?></div></div>
            </div>
            <?php endif; ?>
        </div>
        <div class="foot"><?php echo COMPANY_NAME; ?> — Purchase Order <?php echo e($po['invoice_no']); ?></div>
    </div>
    <div class="no-print">
        <button onclick="window.print()" class="btn-primary"><i class="bi bi-printer"></i> Print</button>
        <button onclick="window.close()" class="btn-secondary">Close</button>
    </div>
    </body></html>
    <?php exit;
}

$result = $pdo->query("SELECT p.*, s.name as sname,
    (SELECT COUNT(*) FROM bike_stock bs WHERE bs.purchase_id=p.id AND bs.status='ordered') as ordered_qty,
    (SELECT COUNT(*) FROM bike_stock bs WHERE bs.purchase_id=p.id AND bs.status='in_stock') as received_qty,
    (SELECT GROUP_CONCAT(DISTINCT CONCAT(b.name, ' ', m.name, ' ', v.name, IF(bs.chassis_no IS NULL OR bs.chassis_no='', '', CONCAT(' (', bs.chassis_no, ')'))) SEPARATOR '<br>')
     FROM bike_stock bs JOIN bike_variants v ON bs.variant_id=v.id JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id
     WHERE bs.purchase_id=p.id) as bikes
    FROM purchases p LEFT JOIN suppliers s ON p.supplier_id=s.id ORDER BY p.id DESC");
$purchases = $result->fetchAll(PDO::FETCH_ASSOC);

// Print view
if (isset($_GET['print'])) {
    ?><!DOCTYPE html><html lang="en"><head><title>Purchase History</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { font-family:'Poppins',sans-serif; margin:0; padding:0; box-sizing:border-box; }
        body { padding:40px; background:#f5f5f5; }
        .print-box { max-width:1100px; margin:auto; background:#fff; border-radius:8px; padding:40px; box-shadow:0 2px 10px rgba(0,0,0,.1); }
        .header { text-align:center; border-bottom:2px solid #095D3B; padding-bottom:12px; margin-bottom:12px; }
        .header h1 { color:#095D3B; font-size:20px; margin:0; }
        .header p { color:#888; margin:4px 0 0; font-size:11px; }
        .header .meta-line { margin:2px 0; font-size:11px; color:#666; }
        .header .meta-line i { color:#095D3B; margin-right:4px; width:12px; text-align:center; }
        .info { text-align:right; margin-bottom:10px; font-size:11px; color:#555; }
        table { width:100%; border-collapse:collapse; font-size:10px; }
        th, td { padding:5px 6px; text-align:left; border-bottom:1px solid #ddd; vertical-align:top; }
        th { background:#095D3B; color:#fff; font-weight:600; font-size:9px; text-transform:uppercase; letter-spacing:.3px; }
        .text-end { text-align:right; }
        .text-muted { color:#888; }
        .fw-semibold { font-weight:600; }
        .bikes-cell { font-size:9px; line-height:1.5; color:#333; }
        .badge { display:inline-block; padding:1px 6px; border-radius:8px; font-size:8px; font-weight:600; }
        .bg-success { background:#28a745; color:#fff; }
        .bg-warning { background:#ffc107; color:#333; }
        .bg-secondary { background:#6c757d; color:#fff; }
        .footer { text-align:center; margin-top:15px; color:#888; font-size:10px; border-top:1px solid #eee; padding-top:10px; }
        .no-print { text-align:center; margin-top:15px; }
        .no-print button { display:inline-block; padding:8px 20px; margin:0 5px; border-radius:4px; font-size:13px; cursor:pointer; border:none; }
        .btn-primary { background:#095D3B; color:#fff; }
        .btn-secondary { background:#6c757d; color:#fff; }
        @media print { body { padding:0; background:#fff; } .print-box { box-shadow:none; } .no-print { display:none; } }
    </style></head><body>
    <div class="print-box">
        <?php $pt = 'Purchase History'; include '../includes/print_header.php'; ?>
        <div class="info">Total Orders: <?php echo count($purchases); ?></div>
        <table>
            <tr><th>#</th><th>Invoice</th><th>Supplier</th><th>Date</th><th>Purchased Bikes</th><th>Ord</th><th>Rec</th><th class="text-end">Total</th><th class="text-end">Exp</th><th class="text-end">Cost</th><th class="text-end">Paid</th><th>Status</th></tr>
            <?php $i = 1; foreach ($purchases as $r): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td class="fw-semibold"><?php echo e($r['invoice_no']); ?></td>
                <td><?php echo e($r['sname'] ?? '-'); ?></td>
                <td><?php echo formatDate($r['purchase_date']); ?></td>
                <td class="bikes-cell"><?php echo $r['bikes'] ?: '-'; ?></td>
                <td><?php echo $r['ordered_qty']; ?></td>
                <td><?php echo $r['received_qty']; ?></td>
                <td class="text-end"><?php echo formatMoney($r['total_amount']); ?></td>
                <td class="text-end"><?php echo formatMoney($r['expenses']); ?></td>
                <td class="text-end fw-semibold"><?php echo formatMoney($r['total_amount'] + $r['expenses']); ?></td>
                <td class="text-end"><?php echo formatMoney($r['paid_amount']); ?></td>
                <td><span class="badge bg-<?php echo $r['status']=='completed'?'success':($r['status']=='partial'?'warning':'secondary'); ?>"><?php echo ucfirst($r['status']); ?></span></td>
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
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Purchase History</span></div>
        <span class="user-info">
            <i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button>
        </span>
    </div>
    <div class="main-content">
        <?php if (!empty($err)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo e($err); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-2"></i>Purchase History</span>
                <div class="d-flex gap-2">
                    <a href="?print=1" class="btn btn-outline-dark btn-sm" target="_blank"><i class="bi bi-printer me-1"></i>Print</a>
                    <a href="purchases.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> New Purchase Order</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <table class="table table-hover" id="purchaseTable">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Supplier</th>
                                <th>Purchased Bikes</th>
                                <th>Date</th>
                                <th>Ordered</th>
                                <th>Received</th>
                                <th>Total Amt</th>
                                <th>Expenses</th>
                                <th>Total Cost</th>
                                <th>Paid</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($purchases as $r):
                            $pending = $r['ordered_qty'] - $r['received_qty'];
                        ?>
                            <tr>
                                <td class="fw-semibold"><?php echo e($r['invoice_no']); ?></td>
                                <td><?php echo e($r['sname'] ?? '-'); ?></td>
                                <td style="font-size:0.85rem;"><?php echo $r['bikes'] ?: '-'; ?></td>
                                <td><?php echo formatDate($r['purchase_date']); ?></td>
                                <td><?php echo $r['ordered_qty']; ?></td>
                                <td><?php echo $r['received_qty']; ?></td>
                                <td><?php echo formatMoney($r['total_amount']); ?></td>
                                <td><?php echo formatMoney($r['expenses']); ?></td>
                                <td class="fw-semibold"><?php echo formatMoney($r['total_amount'] + $r['expenses']); ?></td>
                                <td><?php echo formatMoney($r['paid_amount']); ?></td>
                                <td><span class="badge bg-<?php echo $r['status']=='completed'?'success':($r['status']=='partial'?'warning text-dark':'secondary'); ?>"><?php echo ucfirst($r['status']); ?></span></td>
                                <td style="white-space: nowrap;">
                                    <a href="?print_order=<?php echo $r['id']; ?>" target="_blank" class="btn btn-sm btn-outline-info" title="Print Order"><i class="bi bi-printer"></i></a>
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="viewDetails(<?php echo $r['id']; ?>)" title="View Details"><i class="bi bi-eye"></i></button>
                                    <?php if ($pending > 0): ?>
                                        <button type="button" class="btn btn-sm btn-success" onclick="viewDetails(<?php echo $r['id']; ?>)" title="Receive Bikes"><i class="bi bi-box-seam"></i></button>
                                    <?php endif; ?>
                                    <a href="purchases.php?delete=<?php echo $r['id']; ?>&redirect=view" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this purchase order? All related bike stock will be deleted.')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Purchase Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsBody">
                    <div class="text-center text-muted py-3">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Receive Single Bike Modal (top-level, not nested) -->
    <div class="modal fade" id="receiveModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="purchase_view.php">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h6 class="modal-title"><i class="bi bi-box-seam me-1"></i>Receive Bike</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="receive_id" id="receive_id">
                        <p class="mb-3"><strong id="receiveBikeName"></strong></p>
                        <div class="mb-2">
                            <label class="form-label fw-semibold">Chassis No <span class="text-danger">*</span></label>
                            <input type="text" name="chassis_no" class="form-control" required placeholder="Enter chassis number">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold">Motor No</label>
                            <input type="text" name="motor_no" class="form-control" placeholder="Enter motor number">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold">Battery Serial</label>
                            <input type="text" name="battery_serial" class="form-control" placeholder="Enter battery serial">
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold">Charger Serial</label>
                            <input type="text" name="charger_serial" class="form-control" placeholder="Enter charger serial">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="receive_single" class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i> Receive</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Receive All Modal (top-level, not nested) -->
    <div class="modal fade" id="receiveAllModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h6 class="modal-title"><i class="bi bi-box-seam me-1"></i>Receive All Bikes</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="receiveAllBody">
                    <div class="text-center text-muted py-3">Loading...</div>
                </div>
            </div>
        </div>
    </div>

<script>
var detailsModalInstance = null;

function viewDetails(id) {
    var body = document.getElementById('detailsBody');
    body.innerHTML = '<div class="text-center text-muted py-3">Loading...</div>';
    detailsModalInstance = new bootstrap.Modal(document.getElementById('detailsModal'));
    detailsModalInstance.show();

    fetch('purchase_details.php?id=' + id)
        .then(function(r) { return r.text(); })
        .then(function(html) { body.innerHTML = html; })
        .catch(function() { body.innerHTML = '<div class="alert alert-danger">Failed to load details.</div>'; });
}

function openReceiveModal(id, name) {
    if (detailsModalInstance) { detailsModalInstance.hide(); }
    document.getElementById('receive_id').value = id;
    document.getElementById('receiveBikeName').textContent = name;
    var m = new bootstrap.Modal(document.getElementById('receiveModal'));
    m.show();
}

function openReceiveAllModal(purchaseId) {
    if (detailsModalInstance) { detailsModalInstance.hide(); }
    var body = document.getElementById('receiveAllBody');
    body.innerHTML = '<div class="text-center text-muted py-3">Loading...</div>';
    var m = new bootstrap.Modal(document.getElementById('receiveAllModal'));
    m.show();
    fetch('receive_all_form.php?purchase_id=' + purchaseId)
        .then(function(r) { return r.text(); })
        .then(function(html) { body.innerHTML = html; })
        .catch(function() { body.innerHTML = '<div class="alert alert-danger">Failed to load form.</div>'; });
}
</script>
<?php require_once '../includes/footer.php'; ?>
