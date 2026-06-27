<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();

$id = $_GET['id'] ?? 0;
$p = $pdo->prepare("SELECT p.*, s.name as sname FROM purchases p LEFT JOIN suppliers s ON p.supplier_id=s.id WHERE p.id=?");
$p->execute([$id]);
$r = $p->fetch(PDO::FETCH_ASSOC);
if (!$r) { echo '<div class="alert alert-danger">Purchase not found.</div>'; exit; }
?>
<h6 class="fw-semibold mb-2"><i class="bi bi-receipt me-1"></i>Purchase Order Details</h6>
<table class="table table-sm table-borderless mb-3">
    <tr><td class="fw-semibold text-muted" style="width:110px;">Invoice:</td><td><?php echo e($r['invoice_no']); ?></td></tr>
    <tr><td class="fw-semibold text-muted">Supplier:</td><td><?php echo e($r['sname'] ?? '-'); ?></td></tr>
    <tr><td class="fw-semibold text-muted">Date:</td><td><?php echo formatDate($r['purchase_date']); ?></td></tr>
    <tr><td class="fw-semibold text-muted">Total Amount:</td><td><?php echo formatMoney($r['total_amount']); ?></td></tr>
    <tr><td class="fw-semibold text-muted">Expenses:</td><td><?php echo formatMoney($r['expenses']); ?></td></tr>
    <tr><td class="fw-semibold text-muted">Paid:</td><td><?php echo formatMoney($r['paid_amount']); ?></td></tr>
    <tr><td class="fw-semibold text-muted">Status:</td><td><span class="badge bg-<?php echo $r['status']=='completed'?'success':($r['status']=='partial'?'warning text-dark':'secondary'); ?>"><?php echo ucfirst($r['status']); ?></span></td></tr>
</table>

<h6 class="fw-semibold mb-2"><i class="bi bi-cart-plus me-1"></i>Ordered Items</h6>
<?php
$ordered = $pdo->prepare("SELECT pi.*, v.name as vname, m.name as mname, b.name as bname FROM purchase_items pi JOIN bike_variants v ON pi.variant_id=v.id JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id WHERE pi.purchase_id=? ORDER BY pi.id");
$ordered->execute([$id]);
$orderedItems = $ordered->fetchAll(PDO::FETCH_ASSOC);
if ($orderedItems):
?>
<table class="table table-sm table-bordered mb-4">
    <thead class="table-light">
        <tr><th>Variant</th><th>Qty</th><th>Cost Price</th><th>Total</th></tr>
    </thead>
    <tbody>
    <?php foreach ($orderedItems as $item): ?>
        <tr>
            <td><?php echo e($item['bname'] . ' ' . $item['mname'] . ' - ' . $item['vname']); ?></td>
            <td><?php echo $item['qty']; ?></td>
            <td><?php echo formatMoney($item['cost_price']); ?></td>
            <td><?php echo formatMoney($item['total']); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p class="text-muted small">No ordered items recorded.</p>
<?php endif; ?>

<h6 class="fw-semibold mb-2"><i class="bi bi-box-seam me-1"></i>Received Stock</h6>
<?php
$stock = $pdo->prepare("SELECT s.*, v.name as vname, m.name as mname, b.name as bname FROM bike_stock s JOIN bike_variants v ON s.variant_id=v.id JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id WHERE s.purchase_id=? ORDER BY s.id");
$stock->execute([$id]);
$stockItems = $stock->fetchAll(PDO::FETCH_ASSOC);
if ($stockItems):
?>
<table class="table table-sm table-bordered mb-0">
    <thead class="table-success">
        <tr><th>Variant</th><th>Chassis</th><th>Motor</th><th>Battery</th><th>Charger</th><th>Purchase Price</th><th>Sale Price</th><th>Status</th></tr>
    </thead>
    <tbody>
    <?php foreach ($stockItems as $s): ?>
        <tr>
            <td><?php echo e($s['bname'] . ' ' . $s['mname'] . ' ' . $s['vname']); ?></td>
            <td><?php echo e($s['chassis_no']); ?></td>
            <td><?php echo e($s['motor_no']); ?></td>
            <td><?php echo e($s['battery_serial']); ?></td>
            <td><?php echo e($s['charger_serial']); ?></td>
            <td><?php echo formatMoney($s['purchase_price']); ?></td>
            <td><?php echo formatMoney($s['sale_price']); ?></td>
            <td><span class="badge bg-<?php echo $s['status']=='sold'?'primary':($s['status']=='booked'?'info':'success'); ?>"><?php echo ucfirst(str_replace('_',' ',$s['status'])); ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p class="text-muted small">No stock received yet. <a href="receive_stock.php?receive=<?php echo $id; ?>">Receive stock</a></p>
<?php endif; ?>
