<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$customers = $pdo->query("SELECT * FROM customers ORDER BY name");
$stockItems = $pdo->query("SELECT s.id, s.chassis_no, s.motor_no, s.battery_serial, s.sale_price, s.purchase_price, v.name as vname, v.sale_price as variant_sale, v.purchase_price as variant_purchase, m.name as mname, b.name as bname FROM bike_stock s JOIN bike_variants v ON s.variant_id=v.id JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id WHERE s.status='in_stock' ORDER BY b.name, m.name");

$invNo = 'INV-' . date('Ymd') . '-' . str_pad($pdo->query("SELECT COUNT(*)+1 FROM sales")->fetchColumn(), 3, '0', STR_PAD_LEFT);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save'])) {
    $cid = $_POST['customer_id'];
    $inv = $_POST['invoice_no'];
    $date = $_POST['sale_date'];
    $type = $_POST['sale_type'];
    $discount = floatval($_POST['discount']);
    $downPay = floatval($_POST['down_payment']);
    $totalAmt = floatval($_POST['grand_total']);
    $remaining = max(0, $totalAmt - $discount - $downPay);
    $payStatus = ($remaining <= 0) ? 'paid' : ($downPay > 0 ? 'partial' : 'unpaid');

    $pdo->prepare("INSERT INTO sales (invoice_no, customer_id, sale_date, sale_type, total_amount, discount, down_payment, remaining_amount, payment_status, created_at) VALUES (?,?,?,?,?,?,?,?,?,CURDATE())")->execute([$inv, $cid, $date, $type, $totalAmt, $discount, $downPay, $remaining, $payStatus]);
    $sid = $pdo->lastInsertId();

    $stockStatus = $type === 'booking' ? 'booked' : 'sold';
    if (!empty($_POST['stock_id'])) {
        $saleItemStmt = $pdo->prepare("INSERT INTO sale_items (sale_id, stock_id, sale_price) VALUES (?,?,?)");
        $updateStk = $pdo->prepare("UPDATE bike_stock SET status=?, sale_id=? WHERE id=?");
        foreach ($_POST['stock_id'] as $i => $stkId) {
            if (empty($stkId)) continue;
            $price = floatval($_POST['sale_price'][$i] ?? 0);
            $saleItemStmt->execute([$sid, $stkId, $price]);
            $updateStk->execute([$stockStatus, $sid, $stkId]);
        }
    }

    $pdo->prepare("INSERT INTO customer_ledger (customer_id, date, description, debit, credit, balance) VALUES (?,?,'Sale - INV $inv',0,?,?)")->execute([$cid, $date, $totalAmt, $totalAmt]);
    if ($downPay > 0) {
        $pdo->prepare("INSERT INTO customer_ledger (customer_id, date, description, debit, credit, balance) VALUES (?,?,'Down Payment - INV $inv',?,0,?)")->execute([$cid, $date, $downPay, $totalAmt - $downPay]);
    }

    logActivity($pdo, 'Sale', "Invoice: $inv, Type: $type, Amount: $totalAmt");
    // Redirect with print param
    header("Location: sales.php?print=$sid"); exit;
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("UPDATE bike_stock SET status='in_stock', sale_id=NULL WHERE sale_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM sales WHERE id=?")->execute([$id]);
    $loc = ($_GET['redirect'] ?? '') === 'list' ? 'sale_list.php' : 'sales.php';
    header("Location: $loc"); exit;
}

// Print invoice
if (isset($_GET['print'])) {
    $sid = $_GET['print'];
    $sale = $pdo->prepare("SELECT s.*, c.name as cname, c.mobile, c.address FROM sales s JOIN customers c ON s.customer_id=c.id WHERE s.id=?");
    $sale->execute([$sid]);
    $sale = $sale->fetch(PDO::FETCH_ASSOC);
    if ($sale) {
        $items = $pdo->prepare("SELECT si.*, s.chassis_no, v.name as vname, m.name as mname, b.name as bname FROM sale_items si JOIN bike_stock s ON si.stock_id=s.id JOIN bike_variants v ON s.variant_id=v.id JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id WHERE si.sale_id=?");
        $items->execute([$sid]);
        $netAmount = $sale['total_amount'] - $sale['discount'];
        ?>
        <!DOCTYPE html><html><head><title>Invoice <?php echo $sale['invoice_no']; ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
        <style>
            * { font-family: 'Poppins', sans-serif; margin: 0; padding: 0; box-sizing:border-box; }
            body { padding: 40px; background:#f5f5f5; }
            .invoice-box { max-width: 800px; margin: auto; background:#fff; border-radius:8px; padding: 40px; box-shadow:0 2px 10px rgba(0,0,0,.1); }
            .header { text-align: center; border-bottom: 2px solid #A04657; padding-bottom: 20px; margin-bottom: 20px; }
            .header h1 { color: #A04657; font-size: 26px; margin:0; }
            .header p { color:#888; margin:5px 0 0; font-size:14px; }
            .details { display: flex; justify-content: space-between; margin-bottom: 20px; font-size:14px; }
            .details div { line-height:1.8; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size:14px; }
            th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background: #A04657; color: #fff; font-weight:600; }
            .summary-table th { background: transparent; color:#333; border-bottom:1px solid #ddd; font-weight:500; }
            .summary-table td { text-align:right; font-weight:600; }
            .summary-table .total-row td { font-size:16px; font-weight:700; border-top:2px solid #A04657; }
            .footer { text-align: center; margin-top: 30px; color: #888; font-size: 13px; border-top:1px solid #eee; padding-top:20px; }
            .no-print { text-align:center; margin-top:20px; }
            .no-print button, .no-print a { display:inline-block; padding:10px 24px; margin:0 5px; border-radius:4px; text-decoration:none; font-size:14px; cursor:pointer; border:none; }
            .btn-primary { background:#A04657; color:#fff; }
            .btn-secondary { background:#6c757d; color:#fff; }
            @media print { body { padding:20px; background:#fff; } .invoice-box { box-shadow:none; padding:20px; } .no-print { display:none; } }
            .text-end { text-align:right; }
            .text-green { color:#28a745; font-weight:700; }
            .text-red { color:#dc3545; font-weight:700; }
            .text-muted { color:#888; }
        </style>
        </head><body>
        <div class="invoice-box">
            <div class="header">
                <h1>Electric Bikes Showroom</h1>
                <p>Sale & Purchase Management</p>
            </div>
            <div class="details">
                <div>
                    <strong>Customer:</strong> <?php echo e($sale['cname']); ?><br>
                    <strong>Mobile:</strong> <?php echo e($sale['mobile']); ?><br>
                    <?php if ($sale['address']): ?><strong>Address:</strong> <?php echo e($sale['address']); ?><?php endif; ?>
                </div>
                <div style="text-align:right">
                    <strong>Invoice:</strong> <?php echo $sale['invoice_no']; ?><br>
                    <strong>Date:</strong> <?php echo formatDate($sale['sale_date']); ?><br>
                    <strong>Type:</strong> <?php echo ucfirst($sale['sale_type']); ?>
                </div>
            </div>
            <table>
                <tr><th>#</th><th>Bike</th><th>Chassis</th><th>Price</th></tr>
                <?php $hasItems = false; $i=1; while ($it = $items->fetch(PDO::FETCH_ASSOC)): $hasItems = true; ?>
                <tr><td><?php echo $i++; ?></td><td><?php echo e($it['bname'] . ' ' . $it['mname'] . ' ' . $it['vname']); ?></td><td><?php echo e($it['chassis_no']); ?></td><td><?php echo formatMoney($it['sale_price']); ?></td></tr>
                <?php endwhile; if (!$hasItems): ?>
                <tr><td colspan="4" class="text-muted" style="text-align:center;">No items</td></tr>
                <?php endif; ?>
            </table>
            <table class="summary-table">
                <tr><th style="width:75%;">Total Amount</th><td><?php echo formatMoney($sale['total_amount']); ?></td></tr>
                <?php if ($sale['discount'] > 0): ?>
                <tr><th>Discount</th><td>-<?php echo formatMoney($sale['discount']); ?></td></tr>
                <?php endif; ?>
                <tr class="total-row"><th>Net Amount</th><td><?php echo formatMoney($netAmount); ?></td></tr>
                <tr><th>Amount Paid</th><td class="text-green"><?php echo formatMoney($sale['down_payment']); ?></td></tr>
                <tr><th>Remaining</th><td class="<?php echo $sale['remaining_amount'] > 0 ? 'text-red' : 'text-green'; ?>"><?php echo $sale['remaining_amount'] > 0 ? formatMoney($sale['remaining_amount']) : '0.00'; ?></td></tr>
                <tr><th>Status</th><td><?php echo ucfirst($sale['payment_status']); ?></td></tr>
            </table>
            <div class="footer">Thank you for your business!</div>
            <div class="no-print">
                <button onclick="window.print()" class="btn-primary">Print</button>
                <a href="sale_list.php" class="btn-secondary">Close</a>
            </div>
        </div>
        </body></html>
        <?php exit;
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">New Sale</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name']; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
    </div>
    <div class="main-content">
        <form method="POST" id="saleForm">
            <?php echo csrfField(); ?>

            <!-- Customer & Invoice Section -->
            <div class="card mb-3" style="border: none; box-shadow: 0 2px 8px rgba(0,0,0,.08);">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Customer</label>
                            <select name="customer_id" class="form-select form-select-lg" required>
                                <option value="">Select Customer</option>
                                <?php $customers->execute(); while ($c = $customers->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo e($c['name']); ?> - <?php echo e($c['mobile']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Invoice No</label>
                            <input type="text" name="invoice_no" class="form-control form-control-lg" value="<?php echo $invNo; ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Date</label>
                            <input type="date" name="sale_date" class="form-control form-control-lg" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Sale Type</label>
                            <select name="sale_type" class="form-select form-select-lg" id="saleType" onchange="toggleInstallment()">
                                <option value="cash">Cash Sale</option>
                                <option value="booking">Booking</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Discount</label>
                            <input type="number" step="0.01" name="discount" class="form-control form-control-lg" placeholder="0" oninput="calcSummary()">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bikes Section -->
            <div class="card mb-3" style="border: none; box-shadow: 0 2px 8px rgba(0,0,0,.08);">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <span class="fw-bold"><i class="bi bi-bicycle me-2 text-primary"></i>Bikes</span>
                    <button type="button" class="btn btn-primary btn-sm" onclick="addRow()"><i class="bi bi-plus-lg me-1"></i>Add Bike</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0" id="itemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:50%;" class="ps-4">Select Bike</th>
                                    <th style="width:25%;">Sale Price</th>
                                    <th style="width:10%;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="ps-4">
                                        <select name="stock_id[]" class="form-select" required>
                                            <option value="">Select Bike</option>
                                            <?php $stockItems->execute(); while ($s = $stockItems->fetch(PDO::FETCH_ASSOC)): ?>
                                                <option value="<?php echo $s['id']; ?>" data-price="<?php echo $s['sale_price'] ?: ($s['variant_sale'] ?: $s['variant_purchase']); ?>"><?php echo e($s['bname'] . ' ' . $s['mname'] . ' ' . $s['vname'] . ' [' . $s['chassis_no'] . ']'); ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.01" name="sale_price[]" class="form-control salePrice" placeholder="Enter price" oninput="calcTotal()" required></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Payment Summary Section -->
            <div class="card mb-3" style="border: none; box-shadow: 0 2px 8px rgba(0,0,0,.08);">
                <div class="card-header bg-white py-3">
                    <span class="fw-bold"><i class="bi bi-calculator me-2 text-primary"></i>Payment Summary</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="p-3 rounded-3" style="background:#f8f9fa;">
                                <div class="small text-muted text-uppercase fw-semibold mb-1">Total Amount</div>
                                <div class="fs-4 fw-bold" id="displayTotal">0</div>
                                <input type="hidden" name="grand_total" id="grand_total" value="0">
                            </div>
                        </div>
                        <div class="col-md-3" id="downPayDiv">
                            <div class="p-3 rounded-3" style="background:#f8f9fa;">
                                <div class="small text-muted text-uppercase fw-semibold mb-1">Down Payment</div>
                                <input type="number" step="0.01" name="down_payment" class="form-control form-control-lg" placeholder="Enter amount" oninput="calcSummary()" style="font-size:1.2rem;font-weight:700;border:none;background:transparent;padding-left:0;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 rounded-3" style="background:#f8f9fa;">
                                <div class="small text-muted text-uppercase fw-semibold mb-1">Remaining</div>
                                <div class="fs-4 fw-bold" id="displayRemaining">0</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3 rounded-3" id="statusBadge" style="background:#d4edda;">
                                <div class="small text-uppercase fw-semibold mb-1">Status</div>
                                <div class="fs-4 fw-bold" id="displayStatus">PAID</div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                            <button type="submit" name="save" class="btn btn-primary btn-lg w-100 py-3"><i class="bi bi-check-circle me-2"></i> Complete Sale</button>
                    </div>
                </div>
            </div>
        </form>
        <div class="text-center mb-4">
            <a href="sale_list.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left me-1"></i> View Sales History</a>
        </div>
    </div>
<script>
function calcTotal() {
    var total = 0;
    $('.salePrice').each(function() { total += parseFloat($(this).val()) || 0; });
    $('#grand_total').val(total.toFixed(2));
    $('#displayTotal').text(total.toFixed(0));
    calcSummary();
}
function calcSummary() {
    var total = parseFloat($('#grand_total').val()) || 0;
    var discount = parseFloat($('input[name="discount"]').val()) || 0;
    var downPay = parseFloat($('input[name="down_payment"]').val()) || 0;
    var remaining = Math.max(0, total - discount - downPay);
    $('#displayRemaining').text(remaining.toFixed(0));
    var status = remaining <= 0 ? 'PAID' : (downPay > 0 ? 'PARTIAL' : 'UNPAID');
    $('#displayStatus').text(status);
    var badge = $('#statusBadge');
    if (status === 'PAID') { badge.css('background', '#d4edda').css('color', '#155724'); }
    else if (status === 'PARTIAL') { badge.css('background', '#fff3cd').css('color', '#856404'); }
    else { badge.css('background', '#f8d7da').css('color', '#721c24'); }
}
$(document).on('change', 'select[name="stock_id[]"]', function() {
    var price = $(this).find(':selected').data('price') || 0;
    $(this).closest('tr').find('.salePrice').val(price);
    calcTotal();
});
function toggleInstallment() {
    var type = $('#saleType').val();
    if (type === 'cash') { $('#downPayDiv').hide(); $('input[name="down_payment"]').val(0); calcSummary(); } else { $('#downPayDiv').show(); }
}
function addRow() {
    var tbody = document.querySelector('#itemsTable tbody');
    var row = tbody.querySelector('tr').cloneNode(true);
    row.querySelectorAll('input, select').forEach(function(e) { if (e.tagName !== 'SELECT') e.value = ''; });
    tbody.appendChild(row);
    calcTotal();
}
function removeRow(el) {
    if (document.querySelectorAll('#itemsTable tbody tr').length > 1) { el.closest('tr').remove(); calcTotal(); }
}
calcTotal();
toggleInstallment();
</script>
<?php require_once '../includes/footer.php'; ?>
