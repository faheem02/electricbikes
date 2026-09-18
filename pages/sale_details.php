<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
header('Cache-Control: no-store, max-age=0');

$id = $_GET['id'] ?? 0;
if (!$id) { header('Location: sale_list.php'); exit; }
$s = $pdo->prepare("SELECT s.*, c.name as cname, c.father_name, c.cnic, c.mobile, c.address, c.city FROM sales s LEFT JOIN customers c ON s.customer_id=c.id WHERE s.id=?");
$s->execute([$id]);
$r = $s->fetch(PDO::FETCH_ASSOC);
if (!$r) { echo '<div class="alert alert-danger mb-0">Sale not found.</div>'; exit; }

$items = $pdo->prepare("SELECT si.*, st.variant_id, st.chassis_no, st.motor_no, st.battery_serial, st.charger_serial, v.name as vname, v.color, m.name as mname, b.name as bname FROM sale_items si LEFT JOIN bike_stock st ON si.stock_id=st.id LEFT JOIN bike_variants v ON st.variant_id=v.id LEFT JOIN bike_models m ON v.model_id=m.id LEFT JOIN bike_brands b ON m.brand_id=b.id WHERE si.sale_id=? ORDER BY si.id");
$items->execute([$id]);
$items = $items->fetchAll(PDO::FETCH_ASSOC);
$netAmount = $r['total_amount'] - $r['discount'];
?>

<h6 class="fw-semibold mb-2"><i class="bi bi-person me-1"></i>Customer Details</h6>
<table class="table table-sm table-borderless mb-3">
    <tr><td class="fw-semibold text-muted" style="width:130px;">Customer Name:</td><td><?php echo e($r['cname'] ?? '-'); ?></td></tr>
    <tr><td class="fw-semibold text-muted">Father / Husband:</td><td><?php echo e($r['father_name'] ?: '-'); ?></td></tr>
    <tr><td class="fw-semibold text-muted">CNIC No:</td><td><?php echo e($r['cnic'] ?: '-'); ?></td></tr>
    <tr><td class="fw-semibold text-muted">Contact No:</td><td><?php echo e($r['mobile'] ?: '-'); ?></td></tr>
    <tr><td class="fw-semibold text-muted">Address:</td><td><?php echo e(trim(($r['address'] ?: '') . ($r['city'] ? ', ' . $r['city'] : ''), ', ') ?: '-'); ?></td></tr>
</table>

<h6 class="fw-semibold mb-2"><i class="bi bi-receipt me-1"></i>Sale Details</h6>
<table class="table table-sm table-borderless mb-3">
    <tr><td class="fw-semibold text-muted" style="width:130px;">Invoice No:</td><td class="fw-bold"><?php echo e($r['invoice_no']); ?></td></tr>
    <tr><td class="fw-semibold text-muted">Date:</td><td><?php echo formatDate($r['sale_date']); ?></td></tr>
    <tr><td class="fw-semibold text-muted">Sale Type:</td><td><span class="badge bg-<?php echo $r['sale_type']=='cash'?'success':($r['sale_type']=='installment'?'warning text-dark':'info'); ?>"><?php echo ucfirst($r['sale_type']); ?></span></td></tr>
    <tr><td class="fw-semibold text-muted">Payment Method:</td><td><?php echo e(ucfirst($r['payment_method'] ?: 'cash')); ?></td></tr>
</table>

<h6 class="fw-semibold mb-2"><i class="bi bi-bicycle me-1"></i>Bikes</h6>
<?php if ($items): ?>
<table class="table table-sm table-bordered mb-3">
    <thead class="table-light">
        <tr><th>#</th><th>Bike</th><th>Chassis</th><th>Motor</th><th>Battery</th><th>Charger</th><th class="text-end">Price</th><th style="width:90px;">Action</th></tr>
    </thead>
    <tbody>
    <?php $i=1; foreach ($items as $it):
        $opts = [];
        if (!empty($it['variant_id'])) {
            $oq = $pdo->prepare("SELECT s.id, s.chassis_no, s.sale_price FROM bike_stock s WHERE s.variant_id=? AND (s.status='in_stock' OR s.id=?) ORDER BY (s.id=?) DESC, s.id");
            $oq->execute([$it['variant_id'], $it['stock_id'], $it['stock_id']]);
            foreach ($oq->fetchAll(PDO::FETCH_ASSOC) as $o) {
                $opts[] = ['id' => (int)$o['id'], 'label' => 'Chassis: ' . ($o['chassis_no'] ?: 'No Chassis') . ($o['id'] == $it['stock_id'] ? ' (current)' : ''), 'price' => floatval($o['sale_price'])];
            }
        }
    ?>
        <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo e(trim(($it['bname'] ?? '') . ' ' . ($it['mname'] ?? '') . ' ' . ($it['vname'] ?? '')) ?: '-'); ?><?php if (!empty($it['color'])): ?> <span class="badge bg-light text-muted border ms-1"><?php echo e($it['color']); ?></span><?php endif; ?></td>
            <td><?php echo e($it['chassis_no'] ?: '-'); ?></td>
            <td><?php echo e($it['motor_no'] ?: '-'); ?></td>
            <td><?php echo e($it['battery_serial'] ?: '-'); ?></td>
            <td><?php echo e($it['charger_serial'] ?: '-'); ?></td>
            <td class="text-end"><?php echo formatMoney($it['sale_price']); ?></td>
            <td class="text-nowrap">
                <?php if (!empty($it['stock_id'])): ?>
                <button type="button" class="btn btn-sm btn-outline-primary" title="Edit Bike"
                    onclick="openEditSaleItem(this)"
                    data-item-id="<?php echo $it['id']; ?>"
                    data-sale-id="<?php echo $r['id']; ?>"
                    data-stock-id="<?php echo $it['stock_id']; ?>"
                    data-price="<?php echo $it['sale_price'] + 0; ?>"
                    data-options="<?php echo e(json_encode($opts)); ?>"><i class="bi bi-pencil"></i></button>
                <a href="sales.php?delete_item=<?php echo $it['id']; ?>&back=sale_list.php" class="btn btn-sm btn-outline-danger" title="Remove Bike"
                   onclick="return confirm('Remove this bike from the sale? It will be returned to stock and sale total updated.')"><i class="bi bi-trash"></i></a>
                <?php else: ?>
                <span class="text-muted small">--</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p class="text-muted small">No bikes recorded for this sale.</p>
<?php endif; ?>

<h6 class="fw-semibold mb-2"><i class="bi bi-calculator me-1"></i>Payment Summary</h6>
<table class="table table-sm table-borderless mb-0">
    <tr><td class="fw-semibold text-muted" style="width:130px;">Total Amount:</td><td><?php echo formatMoney($r['total_amount']); ?></td></tr>
    <?php if ($r['discount'] > 0): ?>
    <tr><td class="fw-semibold text-muted">Discount:</td><td>-<?php echo formatMoney($r['discount']); ?></td></tr>
    <?php endif; ?>
    <tr><td class="fw-semibold text-muted">Net Amount:</td><td class="fw-bold"><?php echo formatMoney($netAmount); ?></td></tr>
    <tr><td class="fw-semibold text-muted">Amount Paid:</td><td class="text-success fw-bold"><?php echo formatMoney($r['down_payment']); ?></td></tr>
    <tr><td class="fw-semibold text-muted">Remaining:</td><td class="<?php echo $r['remaining_amount'] > 0 ? 'text-danger fw-bold' : ''; ?>"><?php echo formatMoney($r['remaining_amount']); ?></td></tr>
    <tr><td class="fw-semibold text-muted">Payment Status:</td><td><span class="badge bg-<?php echo $r['payment_status']=='paid'?'success':($r['payment_status']=='partial'?'warning text-dark':'danger'); ?>"><?php echo ucfirst($r['payment_status']); ?></span></td></tr>
</table>
