<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save'])) {
    $pdo->prepare("INSERT INTO bike_variants (model_id, name, color, purchase_price, sale_price) VALUES (?,?,?,?,?)")->execute([$_POST['model_id'], $_POST['name'], $_POST['color'], $_POST['purchase_price'], $_POST['sale_price']]);
    logActivity($pdo, 'Add Variant', $_POST['name']);
    header('Location: stock_entry.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_stock'])) {
    $variantId  = intval($_POST['variant_id']);
    $purchaseP  = floatval($_POST['purchase_price'] ?? 0);
    $saleP      = floatval($_POST['sale_price'] ?? 0);
    $chassis    = trim($_POST['chassis_no'] ?? '');
    $motor      = trim($_POST['motor_no'] ?? '');
    $battery    = trim($_POST['battery_serial'] ?? '');
    $charger    = trim($_POST['charger_serial'] ?? '');

    // If prices not provided, fall back to variant prices
    if ($purchaseP <= 0 || $saleP <= 0) {
        $vp = $pdo->prepare("SELECT purchase_price, sale_price FROM bike_variants WHERE id=?");
        $vp->execute([$variantId]);
        $vRow = $vp->fetch(PDO::FETCH_ASSOC);
        if ($purchaseP <= 0) $purchaseP = floatval($vRow['purchase_price'] ?? 0);
        if ($saleP     <= 0) $saleP     = floatval($vRow['sale_price']     ?? 0);
    }

    try {
        $pdo->prepare("INSERT INTO bike_stock (variant_id, purchase_price, sale_price, chassis_no, motor_no, battery_serial, charger_serial, status, created_at) VALUES (?,?,?,NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),'in_stock',CURDATE())")
            ->execute([$variantId, $purchaseP, $saleP, $chassis, $motor, $battery, $charger]);
        logActivity($pdo, 'Add Stock', "Variant #$variantId, Chassis: " . ($chassis ?: 'N/A'));
        header('Location: stock_entry.php'); exit;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $stockErr = 'Duplicate serial number! Chassis, Motor, Battery, or Charger already exists in stock.';
        } else {
            throw $e;
        }
    }
}

$models = $pdo->query("SELECT m.*, b.name as bname FROM bike_models m JOIN bike_brands b ON m.brand_id=b.id ORDER BY b.name, m.name");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Stock Entry</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
    </div>
    <div class="main-content">
        <?php if (!empty($err)): ?>
            <div class="alert alert-danger"><?php echo e($err); ?></div>
        <?php endif; ?>
        <?php if (!empty($stockErr)): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?php echo e($stockErr); ?></div>
        <?php endif; ?>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-tag me-2"></i>Add Variant</div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <div class="mb-2">
                                <select name="model_id" class="form-select" required>
                                    <option value="">Select Model</option>
                                    <?php while ($m = $models->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo $m['id']; ?>"><?php echo e($m['bname'] . ' - ' . $m['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-2"><input type="text" name="name" class="form-control" placeholder="Variant Name (e.g. Deluxe)" required></div>
                            <div class="mb-2"><input type="text" name="color" class="form-control" placeholder="Color"></div>
                            <div class="row g-2 mb-2">
                                <div class="col-6"><input type="number" step="0.01" name="purchase_price" class="form-control" placeholder="Purchase Price"></div>
                                <div class="col-6"><input type="number" step="0.01" name="sale_price" class="form-control" placeholder="Sale Price"></div>
                            </div>
                            <button type="submit" name="save" class="btn btn-primary w-100">Add Variant</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-box-seam me-2"></i>Add Stock Unit</div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <div class="mb-2">
                                <select name="variant_id" class="form-select" id="stockVariantSel" required>
                                    <option value="">Select Variant</option>
                                    <?php
                                    $allv = $pdo->query("SELECT v.*, m.name as mname, b.name as bname FROM bike_variants v JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id ORDER BY b.name, m.name, v.name");
                                    $allvRows = $allv->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($allvRows as $v):
                                    ?>
                                        <option value="<?php echo $v['id']; ?>"
                                            data-purchase="<?php echo $v['purchase_price']; ?>"
                                            data-sale="<?php echo $v['sale_price']; ?>">
                                            <?php echo e($v['bname'] . ' ' . $v['mname'] . ' - ' . $v['name'] . ($v['color'] ? ' (' . $v['color'] . ')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6"><input type="number" step="0.01" name="purchase_price" id="stockPurchasePrice" class="form-control" placeholder="Purchase Price"></div>
                                <div class="col-6"><input type="number" step="0.01" name="sale_price" id="stockSalePrice" class="form-control" placeholder="Sale Price"></div>
                            </div>
                            <div class="mb-2"><input type="text" name="chassis_no" class="form-control" placeholder="Chassis Number *" required></div>
                            <div class="mb-2"><input type="text" name="motor_no" class="form-control" placeholder="Motor Number"></div>
                            <div class="mb-2"><input type="text" name="battery_serial" class="form-control" placeholder="Battery Serial"></div>
                            <div class="mb-2"><input type="text" name="charger_serial" class="form-control" placeholder="Charger Serial"></div>
                            <button type="submit" name="add_stock" class="btn btn-success w-100">Add to Stock</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>
document.getElementById('stockVariantSel').addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    var pp = opt.getAttribute('data-purchase') || '';
    var sp = opt.getAttribute('data-sale') || '';
    document.getElementById('stockPurchasePrice').value = pp > 0 ? pp : '';
    document.getElementById('stockSalePrice').value = sp > 0 ? sp : '';
});
</script>
<?php require_once '../includes/footer.php'; ?>
