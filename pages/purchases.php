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
    $payStatus = $_POST['payment_status'];

    $total = 0;
    $items = [];
    if (!empty($_POST['variant_id'])) {
        foreach ($_POST['variant_id'] as $i => $vid) {
            if (empty($vid)) continue;
            $qty = intval($_POST['qty'][$i] ?? 1);
            $cost = floatval($_POST['cost_price'][$i] ?? 0);
            if ($qty < 1 || $cost <= 0) continue;
            $items[] = [$vid, $qty, $cost, $qty * $cost];
            $total += $qty * $cost;
        }
    }

    if (empty($items)) {
        $err = 'Add at least one item with valid qty and cost price.';
    } else {
        $pdo->prepare("INSERT INTO purchases (supplier_id, invoice_no, purchase_date, total_amount, expenses, paid_amount, payment_status, status, created_at) VALUES (?,?,?,?,?,?,?,'ordered',CURDATE())")->execute([$sid, $inv, $date, $total, $expenses, $paid, $payStatus]);
        $pid = $pdo->lastInsertId();

        $ins = $pdo->prepare("INSERT INTO purchase_items (purchase_id, variant_id, qty, cost_price, total) VALUES (?,?,?,?,?)");
        foreach ($items as $it) {
            $ins->execute([$pid, $it[0], $it[1], $it[2], $it[3]]);
        }

        $pdo->prepare("INSERT INTO supplier_ledger (supplier_id, date, description, debit, credit, balance) VALUES (?,?,'Purchase Order - INV $inv',0,?,?)")->execute([$sid, $date, $total, $total]);
        logActivity($pdo, 'Purchase Order', "Invoice: $inv, Amount: $total");
        header('Location: purchase_view.php'); exit;
    }
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stk = $pdo->prepare("UPDATE bike_stock SET status='in_stock', sale_id=NULL WHERE purchase_id=?");
    $stk->execute([$id]);
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
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name']; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
    </div>
    <div class="main-content">
        <?php if (!empty($err)): ?>
            <div class="alert alert-danger"><?php echo e($err); ?></div>
        <?php endif; ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-truck me-2"></i>Purchase Order</span>
                <span class="text-muted small">Order bikes from supplier — stock will be received later</span>
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
                        <div class="col-md-2"><input type="text" name="invoice_no" class="form-control" placeholder="Invoice No" required></div>
                        <div class="col-md-2"><input type="date" name="purchase_date" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
                        <div class="col-md-2"><input type="number" step="0.01" name="expenses" class="form-control" placeholder="Expenses"></div>
                        <div class="col-md-2"><input type="number" step="0.01" name="paid_amount" class="form-control" placeholder="Paid Amount"></div>
                        <div class="col-md-1">
                            <select name="payment_status" class="form-select">
                                <option value="unpaid">Unpaid</option>
                                <option value="partial">Partial</option>
                                <option value="paid">Paid</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-hover mb-2" id="itemsTable">
                            <thead class="table-primary">
                <tr>
                    <th>#</th>
                    <th style="min-width:160px;">Variant</th>
                    <th style="width:100px;">Qty</th>
                    <th style="width:130px;">Cost Price</th>
                    <th style="width:130px;">Total</th>
                    <th style="width:50px;"></th>
                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="row-num text-muted fw-semibold">1</td>
                                    <td>
                                        <select name="variant_id[]" class="form-select form-select-sm variant-select" required>
                                            <option value="">Select Variant</option>
                                            <?php $variants->execute(); while ($v = $variants->fetch(PDO::FETCH_ASSOC)): ?>
                                                <option value="<?php echo $v['id']; ?>" data-price="<?php echo $v['purchase_price']; ?>"><?php echo e($v['bname'] . ' ' . $v['mname'] . ' - ' . $v['name']); ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </td>
                                    <td><input type="number" name="qty[]" class="form-control form-control-sm qty-input" min="1" value="1" required></td>
                                    <td><input type="number" step="0.01" name="cost_price[]" class="form-control form-control-sm cost-input" min="0" required placeholder="Cost"></td>
                                    <td class="row-total fw-semibold">0.00</td>
                                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-x"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRow()"><i class="bi bi-plus-lg"></i> Add Row</button>
                        <button type="submit" name="save" class="btn btn-primary"><i class="bi bi-save"></i> Save Purchase Order</button>
                    </div>
                    <div class="text-end">
                        <strong>Order Total: <span id="orderTotal">0.00</span></strong>
                    </div>
                </form>
            </div>
        </div>
        <div class="mt-3">
            <a href="purchase_view.php" class="btn btn-outline-secondary"><i class="bi bi-clock-history"></i> View Purchase History</a>
            <a href="receive_stock.php" class="btn btn-outline-success"><i class="bi bi-box-seam"></i> Receive Stock</a>
        </div>
    </div>
<script>
function calcRow(row) {
    var qty = parseFloat(row.querySelector('.qty-input').value) || 0;
    var cost = parseFloat(row.querySelector('.cost-input').value) || 0;
    var total = qty * cost;
    row.querySelector('.row-total').textContent = total.toFixed(2);
    calcTotal();
}
function calcTotal() {
    var total = 0;
    document.querySelectorAll('#itemsTable tbody tr').forEach(function(r) {
        total += parseFloat(r.querySelector('.row-total').textContent) || 0;
    });
    document.getElementById('orderTotal').textContent = total.toFixed(2);
}
document.querySelector('#itemsTable tbody').addEventListener('input', function(e) {
    if (e.target.classList.contains('qty-input') || e.target.classList.contains('cost-input')) {
        calcRow(e.target.closest('tr'));
    }
});
document.querySelector('#itemsTable tbody').addEventListener('change', function(e) {
    if (e.target.classList.contains('variant-select')) {
        var price = e.target.options[e.target.selectedIndex].getAttribute('data-price') || 0;
        var row = e.target.closest('tr');
        row.querySelector('.cost-input').value = price;
        calcRow(row);
    }
});
function addRow() {
    var tbody = document.querySelector('#itemsTable tbody');
    var row = tbody.querySelector('tr').cloneNode(true);
    row.querySelectorAll('input, select').forEach(function(e) { if (e.tagName !== 'SELECT') e.value = ''; });
    row.querySelector('.row-total').textContent = '0.00';
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
