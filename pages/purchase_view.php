<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$result = $pdo->query("SELECT p.*, s.name as sname,
    (SELECT COALESCE(SUM(pi.qty),0) FROM purchase_items pi WHERE pi.purchase_id=p.id) as ordered_qty,
    (SELECT COUNT(*) FROM bike_stock bs WHERE bs.purchase_id=p.id) as received_qty
    FROM purchases p LEFT JOIN suppliers s ON p.supplier_id=s.id ORDER BY p.id DESC");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Purchase History</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
    </div>
    <div class="main-content">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-2"></i>Purchase History</span>
                <div>
                    <a href="purchases.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> New Purchase Order</a>
                    <a href="receive_stock.php" class="btn btn-sm btn-success"><i class="bi bi-box-seam"></i> Receive Stock</a>
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
                                <th>Total Amount</th>
                                <th>Paid</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($r = $result->fetch(PDO::FETCH_ASSOC)):
                            $pending = $r['ordered_qty'] - $r['received_qty'];
                        ?>
                            <tr>
                                <td class="fw-semibold"><?php echo e($r['invoice_no']); ?></td>
                                <td><?php echo e($r['sname'] ?? '-'); ?></td>
                                <td><?php echo formatDate($r['purchase_date']); ?></td>
                                <td><?php echo $r['ordered_qty']; ?></td>
                                <td><?php echo $r['received_qty']; ?></td>
                                <td class="fw-semibold"><?php echo formatMoney($r['total_amount']); ?></td>
                                <td><?php echo formatMoney($r['paid_amount']); ?></td>
                                <td><span class="badge bg-<?php echo $r['status']=='completed'?'success':($r['status']=='partial'?'warning text-dark':'secondary'); ?>"><?php echo ucfirst($r['status']); ?></span></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="viewDetails(<?php echo $r['id']; ?>)" title="View Details"><i class="bi bi-eye"></i></button>
                                    <?php if ($pending > 0): ?>
                                    <a href="receive_stock.php?receive=<?php echo $r['id']; ?>" class="btn btn-sm btn-success" title="Receive Stock"><i class="bi bi-box-seam"></i></a>
                                    <?php endif; ?>
                                    <a href="purchases.php?delete=<?php echo $r['id']; ?>&redirect=view" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this purchase order? All related bike stock will be restored.')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
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
