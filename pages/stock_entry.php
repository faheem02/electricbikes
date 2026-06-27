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
    try {
        $pdo->prepare("INSERT INTO bike_stock (variant_id, chassis_no, motor_no, battery_serial, charger_serial, status, created_at) VALUES (?,?,?,?,?,'in_stock',CURDATE())")->execute([$_POST['variant_id'], $_POST['chassis_no'], $_POST['motor_no'], $_POST['battery_serial'], $_POST['charger_serial']]);
        header('Location: stock_entry.php'); exit;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $dupErr = 'Duplicate entry! Chassis, Motor, Battery, and Charger numbers must be unique.';
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
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name']; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
    </div>
    <div class="main-content">
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
                        <?php if (!empty($dupErr)): ?>
                            <div class="alert alert-danger py-2"><?php echo e($dupErr); ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <div class="mb-2">
                                <select name="variant_id" class="form-select" required>
                                    <option value="">Select Variant</option>
                                    <?php
                                    $allv = $pdo->query("SELECT v.*, m.name as mname, b.name as bname FROM bike_variants v JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id ORDER BY b.name, m.name, v.name");
                                    while ($v = $allv->fetch(PDO::FETCH_ASSOC)):
                                    ?>
                                        <option value="<?php echo $v['id']; ?>"><?php echo e($v['bname'] . ' ' . $v['mname'] . ' - ' . $v['name'] . ' (' . $v['color'] . ')'); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-2"><input type="text" name="chassis_no" class="form-control" placeholder="Chassis Number *" required></div>
                            <div class="mb-2"><input type="text" name="motor_no" class="form-control" placeholder="Motor Number *"></div>
                            <div class="mb-2"><input type="text" name="battery_serial" class="form-control" placeholder="Battery Serial *"></div>
                            <div class="mb-2"><input type="text" name="charger_serial" class="form-control" placeholder="Charger Serial"></div>
                            <button type="submit" name="add_stock" class="btn btn-success w-100">Add to Stock</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php require_once '../includes/footer.php'; ?>
