<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();

$pid = intval($_GET['purchase_id'] ?? 0);
if (!$pid) { echo '<p class="text-danger">Invalid purchase.</p>'; exit; }

$p = $pdo->prepare("SELECT * FROM purchases WHERE id=?");
$p->execute([$pid]);
$pr = $p->fetch(PDO::FETCH_ASSOC);
if (!$pr) { echo '<p class="text-danger">Purchase not found.</p>'; exit; }

$stock = $pdo->prepare("SELECT s.*, v.name as vname, m.name as mname, b.name as bname FROM bike_stock s JOIN bike_variants v ON s.variant_id=v.id JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id WHERE s.purchase_id=? AND s.status='ordered' ORDER BY s.id");
$stock->execute([$pid]);
$ordered = $stock->fetchAll(PDO::FETCH_ASSOC);

if (empty($ordered)) {
    echo '<p class="text-muted">No ordered bikes left to receive.</p>';
    exit;
}
?>
<form method="POST" action="purchase_view.php">
    <?php echo csrfField(); ?>
    <input type="hidden" name="purchase_id" value="<?php echo $pid; ?>">
    <p class="text-muted small mb-3">Enter serial numbers for each bike. Chassis No is required.</p>
    <?php $ri = 1; foreach ($ordered as $oi): ?>
    <div class="card mb-2">
        <div class="card-body py-2">
            <div class="fw-semibold mb-2 small"><?php echo $ri++; ?>. <?php echo e($oi['bname'] . ' ' . $oi['mname'] . ' ' . $oi['vname']); ?></div>
            <input type="hidden" name="receive_all_ids[]" value="<?php echo $oi['id']; ?>">
            <div class="row g-2">
                <div class="col-md-3"><input type="text" name="all_chassis_no[]" class="form-control form-control-sm" placeholder="Chassis No *" required></div>
                <div class="col-md-3"><input type="text" name="all_motor_no[]" class="form-control form-control-sm" placeholder="Motor No"></div>
                <div class="col-md-3"><input type="text" name="all_battery_serial[]" class="form-control form-control-sm" placeholder="Battery Serial"></div>
                <div class="col-md-3"><input type="text" name="all_charger_serial[]" class="form-control form-control-sm" placeholder="Charger Serial"></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="text-end mt-3">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="receive_all_submit" class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i> Receive All (<?php echo count($ordered); ?> bikes)</button>
    </div>
</form>
