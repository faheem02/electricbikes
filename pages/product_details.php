<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();

$id = $_GET['variant_id'] ?? 0;
$v = $pdo->prepare("SELECT v.*, m.name as mname, b.name as bname FROM bike_variants v JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id WHERE v.id=?");
$v->execute([$id]);
$variant = $v->fetch(PDO::FETCH_ASSOC);
if (!$variant) { echo '<div class="alert alert-danger">Product not found.</div>'; exit; }

$stock = $pdo->prepare("SELECT * FROM bike_stock WHERE variant_id=? ORDER BY id");
$stock->execute([$id]);
$stockItems = $stock->fetchAll(PDO::FETCH_ASSOC);
$inStock = count(array_filter($stockItems, fn($s) => $s['status'] === 'in_stock'));
?>
<h6 class="fw-semibold mb-2"><i class="bi bi-bicycle me-1"></i><?php echo e($variant['bname'] . ' ' . $variant['mname'] . ' - ' . $variant['name']); ?></h6>
<table class="table table-sm table-borderless mb-3">
    <tr>
        <td class="fw-semibold text-muted" style="width:130px;">Color:</td>
        <td><?php echo $variant['color'] ? e($variant['color']) : '-'; ?></td>
        <td class="fw-semibold text-muted" style="width:130px;">Purchase Price:</td>
        <td><?php echo formatMoney($variant['purchase_price']); ?></td>
    </tr>
    <tr>
        <td class="fw-semibold text-muted">Sale Price:</td>
        <td><?php echo formatMoney($variant['sale_price']); ?></td>
        <td class="fw-semibold text-muted">In Stock:</td>
        <td><span class="badge bg-success"><?php echo $inStock; ?></span> / <?php echo count($stockItems); ?> units</td>
    </tr>
</table>

<?php if ($stockItems): ?>
<table class="table table-sm table-bordered mb-0">
    <thead class="table-light">
        <tr><th>#</th><th>Chassis</th><th>Motor</th><th>Battery</th><th>Charger</th><th class="text-end">Purchase</th><th class="text-end">Sale</th><th>Status</th></tr>
    </thead>
    <tbody>
    <?php $i = 1; foreach ($stockItems as $s): ?>
        <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo e($s['chassis_no'] ?: '-'); ?></td>
            <td><?php echo e($s['motor_no'] ?: '-'); ?></td>
            <td><?php echo e($s['battery_serial'] ?: '-'); ?></td>
            <td><?php echo e($s['charger_serial'] ?: '-'); ?></td>
            <td class="text-end"><?php echo formatMoney($s['purchase_price']); ?></td>
            <td class="text-end"><?php echo formatMoney($s['sale_price']); ?></td>
            <td>
                <span class="badge bg-<?php echo $s['status']=='ordered'?'secondary':($s['status']=='in_stock'?'success':($s['status']=='sold'?'primary':($s['status']=='booked'?'warning text-dark':'danger'))); ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $s['status'])); ?>
                </span>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p class="text-muted small mb-0">No stock units for this product yet.</p>
<?php endif; ?>
