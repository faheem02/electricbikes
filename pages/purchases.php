<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save'])) {
    $sid = $_POST['supplier_id'];
    $inv = $_POST['invoice_no'];
    $date = $_POST['purchase_date'];
    $expenses = floatval($_POST['expenses']);
    $paid = floatval($_POST['paid_amount']);

    $total = 0;
    $items = [];
    if (!empty($_POST['variant_id'])) {
        foreach ($_POST['variant_id'] as $i => $vid) {
            if (empty($vid)) continue;
            $chassis = $_POST['chassis_no'][$i] ?? '';
            $motor = $_POST['motor_no'][$i] ?? '';
            $battery = $_POST['battery_serial'][$i] ?? '';
            $charger = $_POST['charger_serial'][$i] ?? '';
            $pPrice = floatval($_POST['purchase_price'][$i] ?? 0);
            $sPrice = floatval($_POST['sale_price'][$i] ?? 0);
            if ($pPrice <= 0) continue;
            $items[] = [$vid, $chassis, $motor, $battery, $charger, $pPrice, $sPrice];
            $total += $pPrice;
        }
    }

    if (empty($items)) {
        $err = 'Add at least one bike with valid purchase price.';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO purchases (supplier_id, invoice_no, purchase_date, total_amount, expenses, paid_amount, payment_status, status, created_at) VALUES (?,?,?,?,?,?,'unpaid','ordered',CURDATE())")->execute([$sid, $inv, $date, $total, $expenses, $paid]);
            $pid = $pdo->lastInsertId();

            $insItem = $pdo->prepare("INSERT INTO purchase_items (purchase_id, variant_id, qty, cost_price, total) VALUES (?,?,1,?,?)");
            $insStock = $pdo->prepare("INSERT INTO bike_stock (variant_id, chassis_no, motor_no, battery_serial, charger_serial, purchase_price, sale_price, status, purchase_id, created_at) VALUES (?,?,?,?,?,?,?,'ordered',?,CURDATE())");
            foreach ($items as $it) {
                $insItem->execute([$pid, $it[0], $it[5], $it[5]]);
                $insStock->execute([$it[0], $it[1] ?: null, $it[2] ?: null, $it[3] ?: null, $it[4] ?: null, $it[5], $it[6], $pid]);
            }

            $pdo->prepare("INSERT INTO supplier_ledger (supplier_id, date, description, debit, credit, balance) VALUES (?,?,'Purchase Order - INV $inv',0,?,?)")->execute([$sid, $date, $total, $total]);

        if ($paid > 0) {
            $method = $_POST['payment_method'] ?? '';
            $payDesc = "Payment against Purchase INV $inv";
            $stmt = $pdo->prepare("INSERT INTO supplier_ledger (supplier_id, date, description, debit, credit, balance) VALUES (?,?,?,?,?,0)");
            $stmt->execute([$sid, $date, "Payment - Purchase INV $inv", $paid, 0]);
            if ($method === 'cash') {
                $stmt2 = $pdo->prepare("INSERT INTO cash_book (date, description, type, amount, balance) VALUES (?,?,'out',?,0)");
                $stmt2->execute([$date, $payDesc, $paid]);
            } elseif ($method === 'bank') {
                $stmt2 = $pdo->prepare("INSERT INTO bank_book (date, description, type, amount, balance) VALUES (?,?,'out',?,0)");
                $stmt2->execute([$date, $payDesc, $paid]);
            }
        }

        if ($expenses > 0) {
            $expMethod = $_POST['payment_method'] ?? '';
            $expDesc = "Purchase Expense - INV $inv";
            $pdo->prepare("INSERT INTO expenses (category, amount, description, date, paid_by) VALUES (?,?,?,?,?)")->execute(["Purchase Expense", $expenses, $expDesc, $date, $_SESSION['full_name'] ?? '']);
            if ($expMethod === 'cash') {
                $pdo->prepare("INSERT INTO cash_book (date, description, type, amount, balance) VALUES (?,'Purchase Expense INV $inv','out',?,0)")->execute([$date, $expenses]);
            } elseif ($expMethod === 'bank') {
                $pdo->prepare("INSERT INTO bank_book (date, description, type, amount, balance) VALUES (?,'Purchase Expense INV $inv','out',?,0)")->execute([$date, $expenses]);
            }
        }

        $pdo->commit();
        logActivity($pdo, 'Purchase Order', "Invoice: $inv, Amount: $total, Items: " . count($items));
        header('Location: purchase_view.php'); exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) {
            $err = 'Duplicate entry! Chassis, Motor, Battery, and Charger numbers must be unique.';
        } else {
            throw $e;
        }
    }
    }
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM bike_stock WHERE purchase_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM purchases WHERE id=?")->execute([$id]);
    $loc = ($_GET['redirect'] ?? '') === 'view' ? 'purchase_view.php' : 'purchases.php';
    header("Location: $loc"); exit;
}

$variants = $pdo->query("SELECT v.id, v.name, v.purchase_price, v.sale_price, m.name as mname, b.name as bname FROM bike_variants v JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id ORDER BY b.name, m.name, v.name");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">New Purchase Order</span></div>
        <span class="user-info">
            <i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button>
        </span>
    </div>
    <div class="main-content">
        <?php if (!empty($err)): ?>
            <div class="alert alert-danger"><?php echo e($err); ?></div>
        <?php endif; ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-truck me-2"></i>Purchase Order — Enter Bike Details</span>
                <span class="text-muted small">Serials will auto-create bike stock in ordered status</span>
            </div>
            <div class="card-body">
                <form method="POST" id="orderForm">
                    <?php echo csrfField(); ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <select name="supplier_id" class="form-select" required>
                                <option value="">Select Supplier</option>
                                <?php while ($s = $suppliers->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo e($s['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3"><input type="text" name="invoice_no" class="form-control" placeholder="Invoice No" required></div>
                        <div class="col-md-3"><input type="date" name="purchase_date" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
                        <div class="col-md-3"><input type="number" step="0.01" name="expenses" class="form-control" placeholder="Expenses"></div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <div class="input-group">
                                <span class="input-group-text">Paid Amount</span>
                                <input type="number" step="0.01" name="paid_amount" class="form-control" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="payment_method" class="form-select">
                                <option value="">Payment Method</option>
                                <option value="cash">Cash</option>
                                <option value="bank">Bank</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-hover mb-2" id="itemsTable">
                            <thead class="table-primary">
                                <tr>
                                    <th>#</th>
                                    <th style="min-width:160px;">Variant</th>
                                    <th style="width:120px;">Chassis No</th>
                                    <th style="width:110px;">Motor No</th>
                                    <th style="width:110px;">Battery Serial</th>
                                    <th style="width:110px;">Charger Serial</th>
                                    <th style="width:110px;">Purchase Price</th>
                                    <th style="width:100px;">Sale Price</th>
                                    <th style="width:40px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="row-num text-muted fw-semibold">1</td>
                                    <td>
                                        <select name="variant_id[]" class="form-select form-select-sm variant-select" required>
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
                                    <td><input type="number" step="0.01" name="purchase_price[]" class="form-control form-control-sm purchase-input" min="0" step="0.01" required placeholder="0"></td>
                                    <td><input type="number" step="0.01" name="sale_price[]" class="form-control form-control-sm" step="0.01" placeholder="0"></td>
                                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-x"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()"><i class="bi bi-plus-lg"></i> Add Bike</button>
                        <button type="submit" name="save" class="btn btn-primary"><i class="bi bi-save"></i> Save Purchase Order</button>
                    </div>
                    <div class="text-end">
                        <strong>Total Purchase: <span id="orderTotal">0.00</span></strong>
                    </div>
                </form>
            </div>
        </div>
        <div class="mt-3">
            <a href="purchase_view.php" class="btn btn-outline-secondary"><i class="bi bi-clock-history"></i> View Purchase History</a>
        </div>
    </div>
<script>
function calcTotal() {
    var total = 0;
    document.querySelectorAll('#itemsTable tbody .purchase-input').forEach(function(el) {
        total += parseFloat(el.value) || 0;
    });
    document.getElementById('orderTotal').textContent = total.toFixed(2);
}
document.querySelector('#itemsTable tbody').addEventListener('input', function(e) {
    if (e.target.classList.contains('purchase-input')) calcTotal();
});
document.querySelector('#itemsTable tbody').addEventListener('change', function(e) {
    if (e.target.classList.contains('variant-select')) {
        var opt = e.target.options[e.target.selectedIndex];
        var row = e.target.closest('tr');
        row.querySelector('.purchase-input').value = opt.getAttribute('data-purchase') || 0;
        row.querySelector('input[name="sale_price[]"]').value = opt.getAttribute('data-sale') || 0;
        calcTotal();
    }
});
function addRow() {
    var tbody = document.querySelector('#itemsTable tbody');
    var row = tbody.querySelector('tr').cloneNode(true);
    row.querySelectorAll('input').forEach(function(e) { e.value = ''; });
    tbody.appendChild(row);
    renumber();
}
function removeRow(el) {
    if (document.querySelectorAll('#itemsTable tbody tr').length > 1) { el.closest('tr').remove(); renumber(); calcTotal(); }
}
function renumber() {
    document.querySelectorAll('#itemsTable tbody tr .row-num').forEach(function(el, i) { el.textContent = i + 1; });
}
calcTotal();
</script>
<?php require_once '../includes/footer.php'; ?>
