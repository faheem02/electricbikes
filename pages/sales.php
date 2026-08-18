<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$customers = $pdo->query("SELECT * FROM customers ORDER BY name");
$stockItems = $pdo->query("SELECT s.id, s.chassis_no, s.motor_no, s.battery_serial, s.sale_price, s.purchase_price, v.name as vname, v.sale_price as variant_sale, v.purchase_price as variant_purchase, m.name as mname, b.name as bname FROM bike_stock s JOIN bike_variants v ON s.variant_id=v.id JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id WHERE s.status='in_stock' ORDER BY b.name, m.name");

$invPrefix = getSetting($pdo, 'invoice_prefix') ?: 'INV-';
$invNo = nextInvoiceNo($pdo, $invPrefix);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save'])) {
    $cid = $_POST['customer_id'];
    $inv = trim($_POST['invoice_no'] ?? '');
    if ($inv === '') $inv = nextInvoiceNo($pdo, getSetting($pdo, 'invoice_prefix') ?: 'INV-');
    $date = $_POST['sale_date'];
    $type = $_POST['sale_type'];
    $discount = floatval($_POST['discount']);
    $downPay = floatval($_POST['down_payment']);
    $totalAmt = floatval($_POST['grand_total']);
    $remaining = max(0, $totalAmt - $discount - $downPay);
    if ($type === 'cash' && $downPay == 0 && $remaining > 0) {
        $downPay = $totalAmt - $discount;
        $remaining = 0;
    }
    $payStatus = ($remaining <= 0) ? 'paid' : ($downPay > 0 ? 'partial' : 'unpaid');
    $paymentMethod = $_POST['payment_method'] ?? 'cash';

    $pdo->prepare("INSERT INTO sales (invoice_no, customer_id, sale_date, sale_type, total_amount, discount, down_payment, remaining_amount, payment_status, payment_method, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,CURDATE())")->execute([$inv, $cid, $date, $type, $totalAmt, $discount, $downPay, $remaining, $payStatus, $paymentMethod]);
    $sid = $pdo->lastInsertId();

    $stockStatus = $type === 'booking' ? 'booked' : 'sold';
    $bikeNames = [];
    if (!empty($_POST['stock_id'])) {
        $saleItemStmt = $pdo->prepare("INSERT INTO sale_items (sale_id, stock_id, sale_price) VALUES (?,?,?)");
        $updateStk = $pdo->prepare("UPDATE bike_stock SET status=?, sale_id=? WHERE id=?");
        $bikeNameStmt = $pdo->prepare("SELECT CONCAT(b.name, ' ', m.name, ' ', v.name) as bike_name, s.chassis_no FROM bike_stock s JOIN bike_variants v ON s.variant_id=v.id JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id WHERE s.id=?");
        foreach ($_POST['stock_id'] as $i => $stkId) {
            if (empty($stkId)) continue;
            $price = floatval($_POST['sale_price'][$i] ?? 0);
            $saleItemStmt->execute([$sid, $stkId, $price]);
            $updateStk->execute([$stockStatus, $sid, $stkId]);
            $bikeNameStmt->execute([$stkId]);
            $b = $bikeNameStmt->fetch(PDO::FETCH_ASSOC);
            if ($b) $bikeNames[] = $b['bike_name'] . ' (' . $b['chassis_no'] . ')';
        }
    }
    $bikeDetail = !empty($bikeNames) ? ' - ' . implode(', ', $bikeNames) : '';

    $pdo->prepare("INSERT INTO customer_ledger (customer_id, date, description, debit, credit, balance) VALUES (?,?,'Sale INV $inv$bikeDetail',?,0,0)")->execute([$cid, $date, $totalAmt]);
    if ($downPay > 0) {
        $pdo->prepare("INSERT INTO customer_ledger (customer_id, date, description, debit, credit, balance) VALUES (?,?,'Payment INV $inv$bikeDetail',0,?,0)")->execute([$cid, $date, $downPay]);
    }
    $paidAmount = $downPay > 0 ? $downPay : ($remaining <= 0 ? $totalAmt - $discount : 0);
    if ($paidAmount > 0 && $paymentMethod === 'cash') {
        $pdo->prepare("INSERT INTO cash_book (date, description, type, amount, balance) VALUES (?,?,'in',?,0)")->execute([$date, "Sale Payment INV $inv$bikeDetail", $paidAmount]);
    } elseif ($paidAmount > 0 && $paymentMethod === 'bank') {
        $pdo->prepare("INSERT INTO bank_book (date, description, type, amount, balance) VALUES (?,?,'in',?,0)")->execute([$date, "Sale Payment INV $inv$bikeDetail", $paidAmount]);
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
    require_once __DIR__ . '/../includes/company.php';
    $sale = $pdo->prepare("SELECT s.*, c.name as cname, c.father_name, c.cnic, c.mobile, c.address, c.city FROM sales s JOIN customers c ON s.customer_id=c.id WHERE s.id=?");
    $sale->execute([$sid]);
    $sale = $sale->fetch(PDO::FETCH_ASSOC);
    if ($sale) {
        $items = $pdo->prepare("SELECT si.*, s.chassis_no, s.motor_no, s.battery_serial, s.charger_serial, YEAR(s.created_at) as bike_year, v.name as vname, v.color, m.name as mname, b.name as bname FROM sale_items si JOIN bike_stock s ON si.stock_id=s.id JOIN bike_variants v ON s.variant_id=v.id JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id WHERE si.sale_id=?");
        $items->execute([$sid]);
        $netAmount = $sale['total_amount'] - $sale['discount'];
        $warranty = getSetting($pdo, 'invoice_warranty');
        $terms = getSetting($pdo, 'invoice_terms');
        ?>
        <!DOCTYPE html><html><head><title>Invoice <?php echo $sale['invoice_no']; ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            * { font-family: 'Poppins', sans-serif; margin:0; padding:0; box-sizing:border-box; }
            body { background:#f0f0f0; padding:25px; }
            .inv { max-width:640px; margin:auto; background:#fff; box-shadow:0 2px 12px rgba(0,0,0,.12); }
            .inv-inner { padding:28px 32px; }
            .brand { text-align:center; }
            .brand h1 { color:#095D3B; font-size:30px; font-weight:700; letter-spacing:1px; margin:0; }
            .brand .tagline { color:#095D3B; font-weight:600; font-size:11px; letter-spacing:3px; text-transform:uppercase; margin-top:3px; }
            .brand .meta { color:#666; font-size:11px; margin-top:7px; line-height:1.6; }
            .brand .meta .meta-line { margin:2px 0; }
            .brand .meta .meta-line i { color:#095D3B; margin-right:5px; width:14px; text-align:center; }
            .title-row { border-bottom:3px solid #095D3B; margin-top:14px; padding-bottom:7px; text-align:center; }
            .title-row h2 { color:#095D3B; font-size:19px; font-weight:700; letter-spacing:5px; margin:0; text-transform:uppercase; }
            .inv-meta { display:flex; justify-content:space-between; font-size:11px; margin-top:10px; color:#333; }
            .inv-meta b { font-weight:600; color:#095D3B; }
            .sec { margin-top:15px; }
            .sec-bar { background:#095D3B; color:#fff; padding:5px 10px; font-size:10.5px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; }
            .sec-body { border:1px solid #e4e4e4; border-top:none; padding:10px; }
            .grid2 { display:grid; grid-template-columns:1fr 1fr; gap:7px 18px; }
            .grid2 .f span { color:#888; font-size:9px; text-transform:uppercase; letter-spacing:.5px; display:block; }
            .grid2 .f { color:#222; font-size:12px; }
            .full { grid-column:1 / -1; }
            table.items { width:100%; border-collapse:collapse; }
            table.items th, table.items td { padding:6px 7px; font-size:10.5px; text-align:left; border-bottom:1px solid #eee; }
            table.items th { background:#095D3B; color:#fff; font-weight:600; font-size:10px; text-transform:uppercase; letter-spacing:.4px; }
            .bike-name { font-weight:600; color:#095D3B; }
            .sum .row { display:flex; justify-content:space-between; align-items:center; padding:4px 0; font-size:12px; color:#333; }
            .sum .row span:first-child { color:#666; }
            .sum .net { border-top:2px solid #095D3B; font-weight:700; font-size:14px; margin-top:3px; padding-top:7px; }
            .sum .net span:first-child { color:#095D3B; }
            .sum .paid { color:#1d8a4e; font-weight:700; }
            .sum .due { color:#d62839; font-weight:700; }
            .pay-badge { display:inline-block; background:#e8f3ee; color:#095D3B; padding:2px 10px; border-radius:10px; font-size:10px; font-weight:600; text-transform:uppercase; }
            .notes { font-size:10.5px; color:#444; line-height:1.7; white-space:pre-line; }
            .notes b { color:#095D3B; }
            .foot { background:#095D3B; color:#fff; text-align:center; padding:11px; font-size:11px; font-weight:600; letter-spacing:.5px; }
            .no-print { text-align:center; margin-top:18px; }
            .no-print button, .no-print a { display:inline-block; padding:10px 24px; margin:0 5px; border-radius:4px; text-decoration:none; font-size:14px; cursor:pointer; border:none; }
            .btn-primary { background:#095D3B; color:#fff; }
            .btn-secondary { background:#6c757d; color:#fff; }
            @media print { body { padding:0; background:#fff; } .inv { box-shadow:none; } .no-print { display:none; } }
        </style>
        </head><body>
        <div class="inv">
            <div class="inv-inner">
                <div class="brand">
                    <img src="../pic/alhafiz.jpeg" alt="Logo" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin-bottom:10px;border:2px solid #095D3B;">
                    <h1><?php echo COMPANY_NAME; ?></h1>
                    <div class="tagline">EV Scooties &amp; Electric Motorcycles</div>
                    <div class="meta">
                        <div class="meta-line"><i class="fas fa-map-marker-alt"></i> <?php echo COMPANY_ADDRESS; ?></div>
                        <div class="meta-line"><?php echo COMPANY_TAGLINE; ?> | <?php echo COMPANY_LINE3; ?></div>
                        <div class="meta-line"><i class="fab fa-whatsapp"></i> <?php echo COMPANY_PHONES; ?></div>
                        <div class="meta-line"><i class="fas fa-envelope"></i> <?php echo COMPANY_EMAIL; ?></div>
                    </div>
                </div>
                <div class="title-row"><h2>Sales Invoice</h2></div>
                <div class="inv-meta">
                    <div><b>Invoice No:</b> <?php echo e($sale['invoice_no']); ?></div>
                    <div><b>Date:</b> <?php echo formatDate($sale['sale_date']); ?></div>
                </div>

                <div class="sec">
                    <div class="sec-bar">Customer Details</div>
                    <div class="sec-body">
                        <div class="grid2">
                            <div class="f"><span>Customer Name</span><?php echo e($sale['cname']); ?></div>
                            <div class="f"><span>Father / Husband Name</span><?php echo e($sale['father_name'] ?: '-'); ?></div>
                            <div class="f"><span>CNIC No</span><?php echo e($sale['cnic'] ?: '-'); ?></div>
                            <div class="f"><span>Contact No</span><?php echo e($sale['mobile'] ?: '-'); ?></div>
                            <div class="f full"><span>Address</span><?php echo e(trim(($sale['address'] ?: '') . ($sale['city'] ? ', ' . $sale['city'] : ''), ', ') ?: '-'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="sec">
                    <div class="sec-bar">Vehicle / Bike Details</div>
                    <div class="sec-body" style="padding:0;">
                        <table class="items">
                            <tr>
                                <th>#</th><th>Bike</th><th>Chassis No</th><th>Motor No</th><th>Battery No</th><th>Charger No</th><th>Year</th><th class="text-end" style="text-align:right;">Price</th>
                            </tr>
                            <?php $hasItems = false; $i = 1; while ($it = $items->fetch(PDO::FETCH_ASSOC)): $hasItems = true; ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td class="bike-name"><?php echo e(trim($it['bname'] . ' ' . $it['mname'] . ' ' . $it['vname'] . ($it['color'] ? ' [' . $it['color'] . ']' : ''))); ?></td>
                                <td><?php echo e($it['chassis_no'] ?: '-'); ?></td>
                                <td><?php echo e($it['motor_no'] ?: '-'); ?></td>
                                <td><?php echo e($it['battery_serial'] ?: '-'); ?></td>
                                <td><?php echo e($it['charger_serial'] ?: '-'); ?></td>
                                <td><?php echo e($it['bike_year'] ?: '-'); ?></td>
                                <td class="text-end" style="text-align:right;"><?php echo formatMoney($it['sale_price']); ?></td>
                            </tr>
                            <?php endwhile; if (!$hasItems): ?>
                            <tr><td colspan="8" style="text-align:center;color:#888;">No items</td></tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <div class="sec">
                    <div class="sec-bar">Sale Details</div>
                    <div class="sec-body">
                        <div class="sum">
                            <div class="row"><span>Subtotal</span><span><?php echo formatMoney($sale['total_amount']); ?></span></div>
                            <?php if ($sale['discount'] > 0): ?>
                            <div class="row"><span>Discount</span><span>-<?php echo formatMoney($sale['discount']); ?></span></div>
                            <?php endif; ?>
                            <div class="row net"><span>Net Amount</span><span><?php echo formatMoney($netAmount); ?></span></div>
                            <div class="row"><span>Amount Paid</span><span class="paid"><?php echo formatMoney($sale['down_payment']); ?></span></div>
                            <div class="row"><span>Balance Due</span><span class="<?php echo $sale['remaining_amount'] > 0 ? 'due' : 'paid'; ?>"><?php echo $sale['remaining_amount'] > 0 ? formatMoney($sale['remaining_amount']) : '0.00'; ?></span></div>
                            <div class="row"><span>Payment Method</span><span><?php echo e(ucfirst($sale['payment_method'] ?: 'cash')); ?></span></div>
                            <div class="row"><span>Payment Status</span><span class="pay-badge"><?php echo ucfirst($sale['payment_status']); ?></span></div>
                        </div>
                    </div>
                </div>

                <div class="sec">
                    <div class="sec-bar">Terms &amp; Notes</div>
                    <div class="sec-body">
                        <div class="notes">
                            <?php if ($terms): ?><?php echo e($terms); ?><?php endif; ?>
                            <?php if (!empty($sale['notes'])): ?><?php if ($terms): echo "\n\n"; endif; ?><b>Notes:</b> <?php echo e($sale['notes']); ?><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="foot">Thank you for shopping with <?php echo COMPANY_NAME; ?></div>
        </div>
        <div class="no-print">
            <button onclick="window.print()" class="btn-primary"><i class="bi bi-printer"></i> Print</button>
            <a href="sale_list.php" class="btn-secondary">Close</a>
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
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
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
                                <div class="small text-muted text-uppercase fw-semibold mb-1">Amount Paid</div>
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
                    <div class="row g-3 mt-1">
                        <div class="col-md-3">
                            <div class="p-2 rounded-3" style="background:#f8f9fa;">
                                <div class="small text-muted text-uppercase fw-semibold mb-1">Payment Method</div>
                                <select name="payment_method" class="form-select form-select-sm" style="border:none;background:transparent;padding-left:0;">
                                    <option value="cash">Cash</option>
                                    <option value="bank">Bank</option>
                                    <option value="online">Online</option>
                                    <option value="other">Other</option>
                                </select>
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
    document.querySelectorAll('.salePrice').forEach(function(el) { total += parseFloat(el.value) || 0; });
    document.getElementById('grand_total').value = total.toFixed(2);
    document.getElementById('displayTotal').textContent = total.toFixed(0);
    calcSummary();
}
function calcSummary() {
    var total = parseFloat(document.getElementById('grand_total').value) || 0;
    var discount = parseFloat(document.querySelector('input[name="discount"]').value) || 0;
    var downPay = parseFloat(document.querySelector('input[name="down_payment"]').value) || 0;
    var remaining = Math.max(0, total - discount - downPay);
    document.getElementById('displayRemaining').textContent = remaining.toFixed(0);
    var status = remaining <= 0 ? 'PAID' : (downPay > 0 ? 'PARTIAL' : 'UNPAID');
    document.getElementById('displayStatus').textContent = status;
    var badge = document.getElementById('statusBadge');
    if (status === 'PAID') { badge.style.background = '#d4edda'; badge.style.color = '#155724'; }
    else if (status === 'PARTIAL') { badge.style.background = '#fff3cd'; badge.style.color = '#856404'; }
    else { badge.style.background = '#f8d7da'; badge.style.color = '#721c24'; }
}
document.getElementById('itemsTable').addEventListener('change', function(e) {
    if (e.target.matches('select[name="stock_id[]"]')) {
        var opt = e.target.options[e.target.selectedIndex];
        var price = parseFloat(opt.getAttribute('data-price')) || 0;
        e.target.closest('tr').querySelector('.salePrice').value = price;
        calcTotal();
    }
});
function toggleInstallment() {
    var type = document.getElementById('saleType').value;
    if (type === 'cash') {
        var total = parseFloat(document.getElementById('grand_total').value) || 0;
        document.querySelector('input[name="down_payment"]').value = total;
    } else {
        document.querySelector('input[name="down_payment"]').value = 0;
    }
    calcSummary();
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
