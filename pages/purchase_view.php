<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

// Receive a single bike stock (ordered → in_stock)
if (isset($_GET['receive'])) {
    $bid = intval($_GET['receive']);
    $st = $pdo->prepare("SELECT purchase_id FROM bike_stock WHERE id=?");
    $st->execute([$bid]);
    $bs = $st->fetch(PDO::FETCH_ASSOC);
    $pid = $bs ? $bs['purchase_id'] : 0;
    $pdo->prepare("UPDATE bike_stock SET status='in_stock' WHERE id=? AND status='ordered'")->execute([$bid]);
    if ($pid) {
        $total = $pdo->prepare("SELECT COUNT(*) FROM bike_stock WHERE purchase_id=?");
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
    $total = $pdo->prepare("SELECT COUNT(*) FROM bike_stock WHERE purchase_id=?");
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

$result = $pdo->query("SELECT p.*, s.name as sname,
    (SELECT COUNT(*) FROM bike_stock bs WHERE bs.purchase_id=p.id) as ordered_qty,
    (SELECT COUNT(*) FROM bike_stock bs WHERE bs.purchase_id=p.id AND bs.status='in_stock') as received_qty
    FROM purchases p LEFT JOIN suppliers s ON p.supplier_id=s.id ORDER BY p.id DESC");
$purchases = $result->fetchAll(PDO::FETCH_ASSOC);

// Print view
if (isset($_GET['print'])) {
    ?><!DOCTYPE html><html lang="en"><head><title>Purchase History</title>
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
        .bg-secondary { background:#6c757d; color:#fff; }
        .fw-semibold { font-weight:600; }
        .footer { text-align:center; margin-top:30px; color:#888; font-size:13px; border-top:1px solid #eee; padding-top:20px; }
        .no-print { text-align:center; margin-top:20px; }
        .no-print button { display:inline-block; padding:10px 24px; margin:0 5px; border-radius:4px; font-size:14px; cursor:pointer; border:none; }
        .btn-primary { background:#A04657; color:#fff; }
        .btn-secondary { background:#6c757d; color:#fff; }
        @media print { body { padding:20px; background:#fff; } .print-box { box-shadow:none; padding:20px; } .no-print { display:none; } }
    </style></head><body>
    <div class="print-box">
        <div class="header"><h1><?php echo e(getSetting($pdo, 'company_name') ?: 'Electric Bikes Showroom'); ?></h1><p>Purchase History</p></div>
        <div class="info">Total Orders: <?php echo count($purchases); ?></div>
        <table>
            <tr><th>Invoice</th><th>Supplier</th><th>Date</th><th>Ordered</th><th>Received</th><th class="text-end">Total</th><th class="text-end">Expenses</th><th class="text-end">Total Cost</th><th class="text-end">Paid</th><th>Status</th></tr>
            <?php foreach ($purchases as $r): ?>
            <tr>
                <td><strong><?php echo e($r['invoice_no']); ?></strong></td>
                <td><?php echo e($r['sname'] ?? '-'); ?></td>
                <td><?php echo formatDate($r['purchase_date']); ?></td>
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
                                <td><?php echo formatDate($r['purchase_date']); ?></td>
                                <td><?php echo $r['ordered_qty']; ?></td>
                                <td><?php echo $r['received_qty']; ?></td>
                                <td><?php echo formatMoney($r['total_amount']); ?></td>
                                <td><?php echo formatMoney($r['expenses']); ?></td>
                                <td class="fw-semibold"><?php echo formatMoney($r['total_amount'] + $r['expenses']); ?></td>
                                <td><?php echo formatMoney($r['paid_amount']); ?></td>
                                <td><span class="badge bg-<?php echo $r['status']=='completed'?'success':($r['status']=='partial'?'warning text-dark':'secondary'); ?>"><?php echo ucfirst($r['status']); ?></span></td>
                                <td style="white-space: nowrap;">
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="viewDetails(<?php echo $r['id']; ?>)" title="View Details"><i class="bi bi-eye"></i></button>
                                    <?php if ($pending > 0): ?>
                                        <a href="?receive_all=<?php echo $r['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Receive all ordered bikes?')" title="Receive All"><i class="bi bi-box-seam"></i></a>
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

<script>
function viewDetails(id) {
    var body = document.getElementById('detailsBody');
    body.innerHTML = '<div class="text-center text-muted py-3">Loading...</div>';
    var modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    modal.show();

    fetch('purchase_details.php?id=' + id)
        .then(function(r) { return r.text(); })
        .then(function(html) { body.innerHTML = html; })
        .catch(function() { body.innerHTML = '<div class="alert alert-danger">Failed to load details.</div>'; });
}
</script>
<?php require_once '../includes/footer.php'; ?>
