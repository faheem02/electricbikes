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
    $pdo->prepare("UPDATE sales SET sale_type='cash' WHERE id=? AND sale_type='booking'")->execute([$sid]);
    logActivity($pdo, 'Complete Delivery', "Sale #$sid delivery completed");
    header('Location: sale_list.php'); exit;
}

$result = $pdo->query("SELECT s.*, c.name as cname,
    GROUP_CONCAT(DISTINCT CONCAT(b.name, ' ', m.name, ' ', v.name, IF(v.color IS NULL OR v.color='', '', CONCAT(' [', v.color, ']')), ' (', st.chassis_no, ')') SEPARATOR '<br>') as bikes,
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
        .info { text-align:right; margin-bottom:15px; font-size:13px; }
        table { width:100%; border-collapse:collapse; font-size:12px; }
        th, td { padding:6px 8px; text-align:left; border-bottom:1px solid #ddd; }
        th { background:#102E68; color:#fff; font-weight:600; font-size:11px; text-transform:uppercase; }
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
        .btn-primary { background:#102E68; color:#fff; }
        .btn-secondary { background:#6c757d; color:#fff; }
        @media print { body { padding:20px; background:#fff; } .print-box { box-shadow:none; padding:20px; } .no-print { display:none; } }
    </style></head><body>
    <div class="print-box">
        <?php $pt = 'Sales List'; include '../includes/print_header.php'; ?>
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
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewSale(<?php echo $r['id']; ?>)" title="View Details"><i class="bi bi-eye"></i></button>
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

    <!-- Sale Details Modal -->
    <div class="modal fade" id="saleDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Sale Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="saleDetailsBody">
                    <div class="text-center text-muted py-3">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Sale Item Modal -->
    <div class="modal fade" id="editItemModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="sales.php">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h6 class="modal-title"><i class="bi bi-pencil-square me-1"></i>Edit Sale Bike</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="update_item" value="1">
                        <input type="hidden" name="item_id" id="ei_item_id">
                        <input type="hidden" name="sale_id" id="ei_sale_id">
                        <input type="hidden" name="back" value="sale_list.php">
                        <div class="mb-2">
                            <label class="form-label fw-semibold">Bike</label>
                            <select name="stock_id" id="ei_stock" class="form-select" required></select>
                            <div class="bike-info mt-2" style="display:none; font-size:0.82rem; background:#f8f9fa; border-radius:6px; padding:7px 10px; border:1px solid #dee2e6;">
                                <span class="text-muted">Selected:</span> <strong id="ei_label">-</strong>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold">Sale Price</label>
                            <input type="number" step="0.01" name="sale_price" id="ei_price" class="form-control" required placeholder="Enter price">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Update</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

<script>
var saleDetailsModalInstance = null;
function viewSale(id) {
    var body = document.getElementById('saleDetailsBody');
    body.innerHTML = '<div class="text-center text-muted py-3">Loading...</div>';
    saleDetailsModalInstance = new bootstrap.Modal(document.getElementById('saleDetailsModal'));
    saleDetailsModalInstance.show();
    fetch('sale_details.php?id=' + id + '&t=' + new Date().getTime())
        .then(function(r) { return r.text(); })
        .then(function(html) { body.innerHTML = html; })
        .catch(function() { body.innerHTML = '<div class="alert alert-danger">Failed to load details.</div>'; });
}

function openEditSaleItem(btn) {
    document.getElementById('ei_item_id').value = btn.getAttribute('data-item-id');
    document.getElementById('ei_sale_id').value = btn.getAttribute('data-sale-id');
    var sel = document.getElementById('ei_stock');
    var opts = JSON.parse(btn.getAttribute('data-options') || '[]');
    sel.innerHTML = '';
    opts.forEach(function(o) {
        var opt = document.createElement('option');
        opt.value = o.id;
        opt.textContent = o.label;
        opt.setAttribute('data-price', o.price);
        sel.appendChild(opt);
    });
    sel.value = btn.getAttribute('data-stock-id');
    document.getElementById('ei_price').value = btn.getAttribute('data-price');
    var m = new bootstrap.Modal(document.getElementById('editItemModal'));
    m.show();
}
document.getElementById('ei_stock').addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    var p = parseFloat(opt.getAttribute('data-price')) || 0;
    if (p > 0) { document.getElementById('ei_price').value = p; }
});
</script>
<?php require_once '../includes/footer.php'; ?>
