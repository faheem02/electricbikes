<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

// Receive stock against a purchase order
if (isset($_GET['receive'])) {
    $pid = $_GET['receive'];
    $purchase = $pdo->prepare("SELECT p.*, s.name as sname FROM purchases p LEFT JOIN suppliers s ON p.supplier_id=s.id WHERE p.id=?");
    $purchase->execute([$pid]);
    $purchase = $purchase->fetch(PDO::FETCH_ASSOC);
    if (!$purchase) { header('Location: receive_stock.php'); exit; }

    $orderedItems = $pdo->prepare("SELECT pi.*, v.name as vname, m.name as mname, b.name as bname FROM purchase_items pi JOIN bike_variants v ON pi.variant_id=v.id JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id WHERE pi.purchase_id=? ORDER BY pi.id");
    $orderedItems->execute([$pid]);
    $orderedItems = $orderedItems->fetchAll(PDO::FETCH_ASSOC);

    $variants = $pdo->prepare("SELECT v.id, v.name, v.purchase_price, v.sale_price, m.name as mname, b.name as bname FROM purchase_items pi JOIN bike_variants v ON pi.variant_id=v.id JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id WHERE pi.purchase_id=? GROUP BY v.id ORDER BY b.name, m.name, v.name");
    $variants->execute([$pid]);

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['receive'])) {
        try {
            $stk = $pdo->prepare("INSERT INTO bike_stock (variant_id, chassis_no, motor_no, battery_serial, charger_serial, purchase_price, sale_price, status, purchase_id, created_at) VALUES (?,?,?,?,?,?,?,'in_stock',?,CURDATE())");
            $received = 0;
            if (!empty($_POST['variant_id'])) {
                foreach ($_POST['variant_id'] as $i => $vid) {
                    if (empty($vid)) continue;
                    $price = floatval($_POST['purchase_price'][$i] ?? 0);
                    $salePrice = floatval($_POST['sale_price'][$i] ?? 0);
                    $ch = $_POST['chassis_no'][$i] ?? '';
                    $motor = $_POST['motor_no'][$i] ?? '';
                    $battery = $_POST['battery_serial'][$i] ?? '';
                    $charger = $_POST['charger_serial'][$i] ?? '';
                    $stk->execute([$vid, $ch ?: null, $motor ?: null, $battery ?: null, $charger ?: null, $price, $salePrice, $pid]);
                    $received++;
                }
            }
            if ($received > 0) {
                // Update purchase status
                $totalOrdered = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM purchase_items WHERE purchase_id=?");
                $totalOrdered->execute([$pid]);
                $totalOrdered = $totalOrdered->fetchColumn();
                $totalReceived = $pdo->prepare("SELECT COUNT(*) FROM bike_stock WHERE purchase_id=?");
                $totalReceived->execute([$pid]);
                $totalReceived = $totalReceived->fetchColumn();
                $newStatus = $totalReceived >= $totalOrdered ? 'completed' : 'partial';
                $pdo->prepare("UPDATE purchases SET status=? WHERE id=?")->execute([$newStatus, $pid]);

                logActivity($pdo, 'Receive Stock', "Purchase #$pid, Received: $received bikes");
                header("Location: receive_stock.php?receive=$pid&msg=1"); exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $dupErr = 'Duplicate entry! Chassis, Motor, Battery, and Charger numbers must be unique.';
            } else {
                throw $e;
            }
        }
    }

    require_once '../includes/header.php';
    require_once '../includes/sidebar.php';
    ?>
    <div class="content">
        <div class="topbar">
            <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Receive Stock</span></div>
            <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name']; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
        </div>
        <div class="main-content">
            <?php if (!empty($dupErr)): ?>
                <div class="alert alert-danger"><?php echo e($dupErr); ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success">Stock received successfully!</div>
            <?php endif; ?>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-truck me-2"></i>Purchase: <?php echo e($purchase['invoice_no']); ?> — <?php echo e($purchase['sname'] ?? '-'); ?></span>
                    <a href="receive_stock.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><strong>Date:</strong> <?php echo formatDate($purchase['purchase_date']); ?></div>
                        <div class="col-md-3"><strong>Total Amount:</strong> <?php echo formatMoney($purchase['total_amount']); ?></div>
                        <div class="col-md-3"><strong>Status:</strong> <span class="badge bg-<?php echo $purchase['status']=='completed'?'success':($purchase['status']=='partial'?'warning text-dark':'secondary'); ?>"><?php echo ucfirst($purchase['status']); ?></span></div>
                    </div>

                    <h6 class="fw-semibold mb-2">Ordered Items</h6>
                    <table class="table table-sm table-bordered mb-4">
                        <thead class="table-light">
                            <tr><th>Variant</th><th>Qty Ordered</th><th>Qty Received</th><th>Pending</th></tr>
                        </thead>
                        <tbody>
                            <?php $allComplete = true; foreach ($orderedItems as $item):
                                $recStmt = $pdo->prepare("SELECT COUNT(*) FROM bike_stock WHERE purchase_id=? AND variant_id=?");
                                $recStmt->execute([$pid, $item['variant_id']]);
                                $received = $recStmt->fetchColumn();
                                $pending = $item['qty'] - $received;
                                if ($pending > 0) $allComplete = false;
                            ?>
                            <tr>
                                <td><?php echo e($item['bname'] . ' ' . $item['mname'] . ' - ' . $item['vname']); ?></td>
                                <td><?php echo $item['qty']; ?></td>
                                <td><?php echo $received; ?></td>
                                <td><span class="badge bg-<?php echo $pending > 0 ? 'warning text-dark' : 'success'; ?>"><?php echo $pending; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if (!$allComplete): ?>
                    <h6 class="fw-semibold mb-2"><i class="bi bi-box-seam me-1"></i>Receive Bikes</h6>
                    <div class="text-muted small mb-2">One row per bike — enter serial numbers as they arrive.</div>
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <div class="table-responsive mb-2">
                            <table class="table table-bordered table-hover" id="receiveTable">
                                <thead class="table-success">
                                    <tr>
                                        <th>#</th>
                                        <th style="min-width:140px;">Variant</th>
                                        <th>Chassis No</th>
                                        <th>Motor No</th>
                                        <th>Battery Serial</th>
                                        <th>Charger Serial</th>
                                        <th style="width:100px;">Purchase Price</th>
                                        <th style="width:100px;">Sale Price</th>
                                        <th style="width:50px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="row-num text-muted fw-semibold">1</td>
                                        <td>
                                            <select name="variant_id[]" class="form-select form-select-sm" required>
                                                <option value="">Select Variant</option>
                                                <?php $variants->execute(); while ($v = $variants->fetch(PDO::FETCH_ASSOC)): ?>
                                                    <option value="<?php echo $v['id']; ?>" data-purchase="<?php echo $v['purchase_price']; ?>" data-sale="<?php echo $v['sale_price']; ?>"><?php echo e($v['bname'] . ' ' . $v['mname'] . ' - ' . $v['name']); ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </td>
                                        <td><input type="text" name="chassis_no[]" class="form-control form-control-sm" placeholder="Chassis"></td>
                                        <td><input type="text" name="motor_no[]" class="form-control form-control-sm" placeholder="Motor"></td>
                                        <td><input type="text" name="battery_serial[]" class="form-control form-control-sm" placeholder="Battery"></td>
                                        <td><input type="text" name="charger_serial[]" class="form-control form-control-sm" placeholder="Charger"></td>
                                        <td><input type="number" step="0.01" name="purchase_price[]" class="form-control form-control-sm" min="0" required placeholder="Cost"></td>
                                        <td><input type="number" step="0.01" name="sale_price[]" class="form-control form-control-sm" min="0" required placeholder="Sale"></td>
                                        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-x"></i></button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex gap-2 mb-3">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()"><i class="bi bi-plus-lg"></i> Add Row</button>
                            <button type="submit" name="receive" class="btn btn-success"><i class="bi bi-check-lg"></i> Receive Stock</button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-success mb-0"><i class="bi bi-check-circle me-1"></i>All items fully received.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <script>
    document.querySelector('#receiveTable tbody').addEventListener('change', function(e) {
        if (e.target.tagName === 'SELECT') {
            var opt = e.target.options[e.target.selectedIndex];
            var row = e.target.closest('tr');
            row.querySelector('input[name*="purchase_price"]').value = opt.getAttribute('data-purchase') || 0;
            row.querySelector('input[name*="sale_price"]').value = opt.getAttribute('data-sale') || 0;
        }
    });
    function addRow() {
        var tbody = document.querySelector('#receiveTable tbody');
        var row = tbody.querySelector('tr').cloneNode(true);
        row.querySelectorAll('input').forEach(function(e) { e.value = ''; });
        tbody.appendChild(row);
        renumber();
    }
    function removeRow(el) {
        if (document.querySelectorAll('#receiveTable tbody tr').length > 1) { el.closest('tr').remove(); renumber(); }
    }
    function renumber() {
        document.querySelectorAll('#receiveTable tbody tr .row-num').forEach(function(el, i) { el.textContent = i + 1; });
    }
    </script>
    <?php require_once '../includes/footer.php'; ?>
    <?php exit;
}

// List purchases with pending stock
$result = $pdo->query("SELECT p.*, s.name as sname,
    (SELECT COALESCE(SUM(pi.qty),0) FROM purchase_items pi WHERE pi.purchase_id=p.id) as ordered_qty,
    (SELECT COUNT(*) FROM bike_stock bs WHERE bs.purchase_id=p.id) as received_qty
    FROM purchases p LEFT JOIN suppliers s ON p.supplier_id=s.id
    ORDER BY p.id DESC");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Receive Stock</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name']; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
    </div>
    <div class="main-content">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-box-seam me-2"></i>Receive Stock Against Purchase Orders</span>
                <a href="purchases.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> New Purchase Order</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <table class="table table-hover" id="receiveTable">
                        <thead>
                            <tr><th>Invoice</th><th>Supplier</th><th>Date</th><th>Ordered</th><th>Received</th><th>Pending</th><th>Status</th><th>Actions</th></tr>
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
                                <td><span class="badge bg-<?php echo $pending > 0 ? 'warning text-dark' : 'success'; ?>"><?php echo $pending; ?></span></td>
                                <td><span class="badge bg-<?php echo $r['status']=='completed'?'success':($r['status']=='partial'?'warning text-dark':'secondary'); ?>"><?php echo ucfirst($r['status']); ?></span></td>
                                <td>
                                    <a href="receive_stock.php?receive=<?php echo $r['id']; ?>" class="btn btn-sm btn-success <?php echo $pending <= 0 ? 'disabled' : ''; ?>"><i class="bi bi-box-seam"></i> Receive</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php require_once '../includes/footer.php'; ?>
