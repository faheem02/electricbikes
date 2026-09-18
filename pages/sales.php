<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$customers = $pdo->query("SELECT * FROM customers ORDER BY name");
$variants = $pdo->query("SELECT v.id as vid, v.name as vname, v.color as vcolor, v.sale_price as variant_sale, v.purchase_price as variant_purchase, m.name as mname, b.name as bname, COUNT(s.id) as bike_count FROM bike_variants v JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id JOIN bike_stock s ON s.variant_id=v.id WHERE s.status='in_stock' GROUP BY v.id ORDER BY b.name, m.name, v.name");

$invPrefix = getSetting($pdo, 'invoice_prefix') ?: 'INV-';
$invNo = nextInvoiceNo($pdo, $invPrefix);

// Load sale for editing
$editSale = null;
$editItems = [];
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $st = $pdo->prepare("SELECT * FROM sales WHERE id=?");
    $st->execute([$eid]);
    $editSale = $st->fetch(PDO::FETCH_ASSOC);
    if (!$editSale) { header('Location: sale_list.php'); exit; }
    $it = $pdo->prepare("SELECT si.*, st.variant_id, st.chassis_no, st.motor_no, st.battery_serial, st.charger_serial FROM sale_items si LEFT JOIN bike_stock st ON si.stock_id=st.id WHERE si.sale_id=? ORDER BY si.id");
    $it->execute([$eid]);
    $editItems = $it->fetchAll(PDO::FETCH_ASSOC);
}

// All variants (including ones with zero available bikes) for edit mode
$variantsEditRows = [];
if ($editSale) {
    $vq = $pdo->query("SELECT v.id as vid, v.name as vname, v.color as vcolor, v.sale_price as variant_sale, v.purchase_price as variant_purchase, m.name as mname, b.name as bname,
        (SELECT COUNT(*) FROM bike_stock s2 WHERE s2.variant_id=v.id AND s2.status='in_stock') as bike_count
        FROM bike_variants v JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id ORDER BY b.name, m.name, v.name");
    $variantsEditRows = $vq->fetchAll(PDO::FETCH_ASSOC);
}

// Update existing sale (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_id'])) {
    $sid = intval($_POST['update_id']);
    $oldStmt = $pdo->prepare("SELECT * FROM sales WHERE id=?");
    $oldStmt->execute([$sid]);
    $oldSale = $oldStmt->fetch(PDO::FETCH_ASSOC);
    if (!$oldSale) { header('Location: sale_list.php'); exit; }

    $cid = $_POST['customer_id'];
    $inv = trim($_POST['invoice_no'] ?? '');
    if ($inv === '') $inv = $oldSale['invoice_no'];
    $date = $_POST['sale_date'];
    $type = $_POST['sale_type'];
    $discount = floatval($_POST['discount']);
    $downPay = floatval($_POST['down_payment']);
    $totalAmt = floatval($_POST['grand_total']);

    // Manual payments collected after the sale (linked by invoice no in description)
    $collStmt = $pdo->prepare("SELECT COALESCE(SUM(credit-debit),0) FROM customer_ledger WHERE customer_id=? AND description LIKE ?");
    $collStmt->execute([$oldSale['customer_id'], "%(INV: {$oldSale['invoice_no']})%"]);
    $collected = floatval($collStmt->fetchColumn());

    $remaining = max(0, $totalAmt - $discount - $downPay - $collected);
    if ($type === 'cash' && $downPay == 0 && $remaining > 0) {
        $downPay = $totalAmt - $discount - $collected;
        $remaining = 0;
    }
    $payStatus = ($remaining <= 0) ? 'paid' : (($downPay > 0 || $collected > 0) ? 'partial' : 'unpaid');
    $paymentMethod = $_POST['payment_method'] ?? 'cash';

    try {
        $pdo->beginTransaction();

        // Revert previously sold/booked stock back to available
        $pdo->prepare("UPDATE bike_stock SET status='in_stock', sale_id=NULL WHERE sale_id=? AND status IN ('sold','booked')")->execute([$sid]);

        // Remove old ledger / cash-book / bank-book entries linked to this sale
        $delLedger = $pdo->prepare("DELETE FROM customer_ledger WHERE sale_id=?");
        $delLedger->execute([$sid]);
        if ($delLedger->rowCount() == 0) {
            $d = $oldSale['invoice_no'];
            $pdo->prepare("DELETE FROM customer_ledger WHERE customer_id=? AND (description=? OR description LIKE ? OR description=? OR description LIKE ?)")
                ->execute([$oldSale['customer_id'], "Sale INV $d", "Sale INV $d %", "Payment INV $d", "Payment INV $d %"]);
        }
        foreach (['cash_book', 'bank_book'] as $tbl) {
            $delBook = $pdo->prepare("DELETE FROM `$tbl` WHERE sale_id=?");
            $delBook->execute([$sid]);
            if ($delBook->rowCount() == 0) {
                $d = $oldSale['invoice_no'];
                $pdo->prepare("DELETE FROM `$tbl` WHERE description=? OR description LIKE ?")
                    ->execute(["Sale Payment INV $d", "Sale Payment INV $d %"]);
            }
        }

        // Update the sale row
        $pdo->prepare("UPDATE sales SET invoice_no=?, customer_id=?, sale_date=?, sale_type=?, total_amount=?, discount=?, down_payment=?, remaining_amount=?, payment_status=?, payment_method=? WHERE id=?")
            ->execute([$inv, $cid, $date, $type, $totalAmt, $discount, $downPay, $remaining, $payStatus, $paymentMethod, $sid]);

        // Replace sale items and mark new stock
        $pdo->prepare("DELETE FROM sale_items WHERE sale_id=?")->execute([$sid]);
        $stockStatus = $type === 'booking' ? 'booked' : 'sold';
        $bikeNames = [];
        if (!empty($_POST['stock_id'])) {
            $saleItemStmt = $pdo->prepare("INSERT INTO sale_items (sale_id, stock_id, sale_price) VALUES (?,?,?)");
            $updateStk = $pdo->prepare("UPDATE bike_stock SET status=?, sale_id=? WHERE id=? AND status='in_stock'");
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

        // Re-post ledger entries
        $pdo->prepare("INSERT INTO customer_ledger (customer_id, sale_id, date, description, debit, credit, balance) VALUES (?,?,?,?,?,0,0)")
            ->execute([$cid, $sid, $date, "Sale INV $inv$bikeDetail", $totalAmt]);
        if ($downPay > 0) {
            $pdo->prepare("INSERT INTO customer_ledger (customer_id, sale_id, date, description, debit, credit, balance) VALUES (?,?,?,?,0,?,0)")
                ->execute([$cid, $sid, $date, "Payment INV $inv$bikeDetail", $downPay]);
        }
        $paidAmount = $downPay > 0 ? $downPay : ($remaining <= 0 ? $totalAmt - $discount : 0);
        if ($paidAmount > 0 && $paymentMethod === 'cash') {
            $pdo->prepare("INSERT INTO cash_book (date, description, type, amount, balance, sale_id) VALUES (?,?,'in',?,0,?)")->execute([$date, "Sale Payment INV $inv$bikeDetail", $paidAmount, $sid]);
        } elseif ($paidAmount > 0 && $paymentMethod === 'bank') {
            $pdo->prepare("INSERT INTO bank_book (date, description, type, amount, balance, sale_id) VALUES (?,?,'in',?,0,?)")->execute([$date, "Sale Payment INV $inv$bikeDetail", $paidAmount, $sid]);
        }

        $pdo->commit();
        logActivity($pdo, 'Sale Updated', "Invoice: $inv, Type: $type, Amount: $totalAmt");
        header("Location: sales.php?print=$sid"); exit;
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
}

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

    $pdo->prepare("INSERT INTO customer_ledger (customer_id, sale_id, date, description, debit, credit, balance) VALUES (?,?,?,?,?,0,0)")->execute([$cid, $sid, $date, "Sale INV $inv$bikeDetail", $totalAmt]);
    if ($downPay > 0) {
        $pdo->prepare("INSERT INTO customer_ledger (customer_id, sale_id, date, description, debit, credit, balance) VALUES (?,?,?,?,0,?,0)")->execute([$cid, $sid, $date, "Payment INV $inv$bikeDetail", $downPay]);
    }
    $paidAmount = $downPay > 0 ? $downPay : ($remaining <= 0 ? $totalAmt - $discount : 0);
    if ($paidAmount > 0 && $paymentMethod === 'cash') {
        $pdo->prepare("INSERT INTO cash_book (date, description, type, amount, balance, sale_id) VALUES (?,?,'in',?,0,?)")->execute([$date, "Sale Payment INV $inv$bikeDetail", $paidAmount, $sid]);
    } elseif ($paidAmount > 0 && $paymentMethod === 'bank') {
        $pdo->prepare("INSERT INTO bank_book (date, description, type, amount, balance, sale_id) VALUES (?,?,'in',?,0,?)")->execute([$date, "Sale Payment INV $inv$bikeDetail", $paidAmount, $sid]);
    }

    logActivity($pdo, 'Sale', "Invoice: $inv, Type: $type, Amount: $totalAmt");
    // Redirect with print param
    header("Location: sales.php?print=$sid"); exit;
}

// Edit a single sale item (swap bike / change price)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_item'])) {
    $itemId = intval($_POST['item_id']);
    $sid = intval($_POST['sale_id']);
    $newStock = intval($_POST['stock_id']);
    $newPrice = floatval($_POST['sale_price']);

    $it = $pdo->prepare("SELECT si.*, s.status as stock_status FROM sale_items si LEFT JOIN bike_stock s ON si.stock_id=s.id WHERE si.id=? AND si.sale_id=?");
    $it->execute([$itemId, $sid]);
    $item = $it->fetch(PDO::FETCH_ASSOC);
    $saleStmt = $pdo->prepare("SELECT * FROM sales WHERE id=?");
    $saleStmt->execute([$sid]);
    $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);

    if ($item && $sale) {
        $newStatus = $sale['sale_type'] === 'booking' ? 'booked' : 'sold';
        if ($item['stock_id'] != $newStock) {
            $pdo->prepare("UPDATE bike_stock SET status='in_stock', sale_id=NULL WHERE id=? AND sale_id=?")->execute([$item['stock_id'], $sid]);
            $pdo->prepare("UPDATE bike_stock SET status=?, sale_id=? WHERE id=? AND status='in_stock'")->execute([$newStatus, $sid, $newStock]);
            $pdo->prepare("UPDATE sale_items SET stock_id=?, sale_price=? WHERE id=?")->execute([$newStock, $newPrice, $itemId]);
        } else {
            $pdo->prepare("UPDATE sale_items SET sale_price=? WHERE id=?")->execute([$newPrice, $itemId]);
        }
        rebuildSaleLedger($pdo, $sid);
        logActivity($pdo, 'Sale Item Updated', "Invoice: {$sale['invoice_no']}, Item #$itemId");
    }
    header('Location: ' . ($_POST['back'] ?? 'sale_list.php')); exit;
}

// Delete a single sale item (bike returned to stock)
if (isset($_GET['delete_item'])) {
    $itemId = intval($_GET['delete_item']);
    $it = $pdo->prepare("SELECT * FROM sale_items WHERE id=?");
    $it->execute([$itemId]);
    $item = $it->fetch(PDO::FETCH_ASSOC);
    if ($item) {
        $sid = $item['sale_id'];
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM sale_items WHERE sale_id=?");
        $cnt->execute([$sid]);
        if ($cnt->fetchColumn() > 1) {
            $pdo->prepare("UPDATE bike_stock SET status='in_stock', sale_id=NULL WHERE id=? AND sale_id=?")->execute([$item['stock_id'], $sid]);
            $pdo->prepare("DELETE FROM sale_items WHERE id=?")->execute([$itemId]);
            rebuildSaleLedger($pdo, $sid);
            $invStmt = $pdo->prepare("SELECT invoice_no FROM sales WHERE id=?");
            $invStmt->execute([$sid]);
            logActivity($pdo, 'Sale Item Deleted', "Invoice: {$invStmt->fetchColumn()}, Item #$itemId");
        }
    }
    header('Location: ' . ($_GET['back'] ?? 'sale_list.php')); exit;
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $oldStmt = $pdo->prepare("SELECT * FROM sales WHERE id=?");
    $oldStmt->execute([$id]);
    $oldSale = $oldStmt->fetch(PDO::FETCH_ASSOC);
    $pdo->prepare("UPDATE bike_stock SET status='in_stock', sale_id=NULL WHERE sale_id=?")->execute([$id]);
    if ($oldSale) {
        $delLedger = $pdo->prepare("DELETE FROM customer_ledger WHERE sale_id=?");
        $delLedger->execute([$id]);
        if ($delLedger->rowCount() == 0) {
            $d = $oldSale['invoice_no'];
            $pdo->prepare("DELETE FROM customer_ledger WHERE customer_id=? AND (description=? OR description LIKE ? OR description=? OR description LIKE ?)")
                ->execute([$oldSale['customer_id'], "Sale INV $d", "Sale INV $d %", "Payment INV $d", "Payment INV $d %"]);
        }
        foreach (['cash_book', 'bank_book'] as $tbl) {
            $delBook = $pdo->prepare("DELETE FROM `$tbl` WHERE sale_id=?");
            $delBook->execute([$id]);
            if ($delBook->rowCount() == 0) {
                $d = $oldSale['invoice_no'];
                $pdo->prepare("DELETE FROM `$tbl` WHERE description=? OR description LIKE ?")
                    ->execute(["Sale Payment INV $d", "Sale Payment INV $d %"]);
            }
        }
    }
    $pdo->prepare("DELETE FROM sales WHERE id=?")->execute([$id]);
    logActivity($pdo, 'Sale Deleted', "Invoice: " . ($oldSale['invoice_no'] ?? $id));
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
            body { background:#f0f0f0; padding:15px; }
            @page { size: A4; margin: 0; }
            .inv { max-width:700px; margin:auto; background:#fff; box-shadow:0 2px 12px rgba(0,0,0,.12); }
            .inv-inner { padding:15px 20px; }
            .title-row { border-bottom:2px solid #00AEEF; margin-top:4px; padding-bottom:4px; text-align:center; }
            .title-row h2 { color:#102E68; font-size:14px; font-weight:700; letter-spacing:3px; margin:0; text-transform:uppercase; }
            .inv-meta { display:flex; justify-content:space-between; font-size:10px; margin-top:6px; color:#333; }
            .inv-meta b { font-weight:600; color:#102E68; }
            .sec { margin-top:8px; }
            .sec-bar { background:#102E68; color:#fff; padding:3px 8px; font-size:9px; font-weight:600; letter-spacing:1px; text-transform:uppercase; }
            .sec-body { border:1px solid #e4e4e4; border-top:none; padding:6px 8px; }
            .grid2 { display:grid; grid-template-columns:1fr 1fr; gap:3px 14px; }
            .grid2 .f span { color:#888; font-size:8px; text-transform:uppercase; letter-spacing:.4px; display:block; }
            .grid2 .f { color:#222; font-size:10px; }
            .full { grid-column:1 / -1; }
            table.items { width:100%; border-collapse:collapse; }
            table.items th, table.items td { padding:3px 5px; font-size:9px; text-align:left; border-bottom:1px solid #eee; }
            table.items th { background:#102E68; color:#fff; font-weight:600; font-size:8px; text-transform:uppercase; letter-spacing:.3px; }
            .bike-name { font-weight:600; color:#102E68; }
            .sum .row { display:flex; justify-content:space-between; align-items:center; padding:2px 0; font-size:10px; color:#333; }
            .sum .row span:first-child { color:#666; }
            .sum .net { border-top:2px solid #00AEEF; font-weight:700; font-size:11px; margin-top:2px; padding-top:4px; }
            .sum .net span:first-child { color:#102E68; }
            .sum .paid { color:#1d8a4e; font-weight:700; }
            .sum .due { color:#d62839; font-weight:700; }
            .pay-badge { display:inline-block; background:#e8f3ee; color:#102E68; padding:1px 6px; border-radius:8px; font-size:8px; font-weight:600; text-transform:uppercase; }
            .notes { font-size:8px; color:#444; line-height:1.4; white-space:pre-line; }
            .notes b { color:#102E68; }
            .foot { background:#102E68; color:#fff; text-align:center; padding:6px; font-size:9px; font-weight:600; letter-spacing:.5px; }
            .no-print { text-align:center; margin-top:12px; }
            .no-print button, .no-print a { display:inline-block; padding:8px 20px; margin:0 5px; border-radius:4px; text-decoration:none; font-size:13px; cursor:pointer; border:none; }
            .btn-primary { background:#102E68; color:#fff; }
            .btn-secondary { background:#6c757d; color:#fff; }
            @media print { @page { size: A4; margin: 0; } body { padding:0; background:#fff; margin:0; } .inv { box-shadow:none; max-width:100%; } .no-print { display:none; } }
        </style>
        </head><body>
        <div class="inv">
            <div class="inv-inner">
                <?php $pt = ''; $showTaxInfo = true; include '../includes/print_header.php'; ?>
                <div class="title-row"><h2>Sales Invoice</h2></div>
                <div class="inv-meta">
                    <div><b>Invoice No:</b> <?php echo e($sale['invoice_no']); ?></div>
                    <div><b>STRN:</b> <?php echo COMPANY_STRN; ?> &nbsp;|&nbsp; <b>NTN:</b> <?php echo COMPANY_NTN; ?></div>
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
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title"><?php echo $editSale ? 'Edit Sale' : 'New Sale'; ?></span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
    </div>
    <div class="main-content">
        <form method="POST" id="saleForm">
            <?php echo csrfField(); ?>
            <?php if ($editSale): ?><input type="hidden" name="update_id" value="<?php echo $editSale['id']; ?>"><?php endif; ?>

            <!-- Customer & Invoice Section -->
            <div class="card mb-3" style="border: none; box-shadow: 0 2px 8px rgba(0,0,0,.08);">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Customer</label>
                            <select name="customer_id" class="form-select form-select-lg" required>
                                <option value="">Select Customer</option>
                                <?php $customers->execute(); while ($c = $customers->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php if ($editSale && $editSale['customer_id'] == $c['id']) echo 'selected'; ?>><?php echo e($c['name']); ?> - <?php echo e($c['mobile']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Invoice No</label>
                            <input type="text" name="invoice_no" class="form-control form-control-lg" value="<?php echo $editSale ? e($editSale['invoice_no']) : $invNo; ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Date</label>
                            <input type="date" name="sale_date" class="form-control form-control-lg" value="<?php echo $editSale ? $editSale['sale_date'] : date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Sale Type</label>
                            <select name="sale_type" class="form-select form-select-lg" id="saleType" onchange="toggleInstallment()">
                                <option value="cash" <?php if ($editSale && $editSale['sale_type'] == 'cash') echo 'selected'; ?>>Cash Sale</option>
                                <option value="booking" <?php if ($editSale && $editSale['sale_type'] == 'booking') echo 'selected'; ?>>Booking</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Discount</label>
                            <input type="number" step="0.01" name="discount" class="form-control form-control-lg" placeholder="0" value="<?php echo $editSale ? $editSale['discount'] + 0 : ''; ?>" oninput="calcSummary()">
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
                                    <th class="ps-4">Variant</th>
                                    <th>Select Bike</th>
                                    <th style="width:180px;">Sale Price</th>
                                    <th style="width:40px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($editSale): foreach ($editItems as $it):
                                    $curVariant = $it['variant_id'];
                                    $bikeOpts = [];
                                    if ($curVariant) {
                                        $bsq = $pdo->prepare("SELECT * FROM bike_stock WHERE variant_id=? AND (status='in_stock' OR id=?) ORDER BY (id=?) DESC, id");
                                        $bsq->execute([$curVariant, $it['stock_id'], $it['stock_id']]);
                                        $bikeOpts = $bsq->fetchAll(PDO::FETCH_ASSOC);
                                    }
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <select name="variant_id[]" class="form-select variant-select" required>
                                            <option value="">Select Variant</option>
                                            <?php foreach ($variantsEditRows as $v): ?>
                                                <option value="<?php echo $v['vid']; ?>" data-price="<?php echo $v['variant_sale'] ?: $v['variant_purchase']; ?>" <?php if ($v['vid'] == $curVariant) echo 'selected'; ?>><?php echo e($v['bname'] . ' ' . $v['mname'] . ' - ' . $v['vname'] . ($v['vcolor'] ? ' (' . $v['vcolor'] . ')' : '') . ' (' . $v['bike_count'] . ')'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="stock_id[]" class="form-select bike-select" required <?php if (!$bikeOpts) echo 'disabled'; ?>>
                                            <?php if (!$bikeOpts): ?>
                                                <option value="">-- Select variant first --</option>
                                            <?php else: foreach ($bikeOpts as $bo): ?>
                                                <option value="<?php echo $bo['id']; ?>"
                                                    data-price="<?php echo $bo['sale_price'] ?: 0; ?>"
                                                    data-chassis="<?php echo e($bo['chassis_no'] ?: '-'); ?>"
                                                    data-motor="<?php echo e($bo['motor_no'] ?: '-'); ?>"
                                                    data-battery="<?php echo e($bo['battery_serial'] ?: '-'); ?>"
                                                    data-charger="<?php echo e($bo['charger_serial'] ?: '-'); ?>"
                                                    <?php if ($bo['id'] == $it['stock_id']) echo 'selected'; ?>>Chassis: <?php echo e($bo['chassis_no'] ?: 'No Chassis'); ?><?php if ($bo['id'] == $it['stock_id']) echo ' (current)'; ?></option>
                                            <?php endforeach; endif; ?>
                                        </select>
                                        <div class="bike-info mt-2" style="<?php if ($it['stock_id']) echo 'display:block;'; ?> font-size:0.82rem; background:#f8f9fa; border-radius:6px; padding:7px 10px; border:1px solid #dee2e6;">
                                            <div class="row g-1">
                                                <div class="col-6"><span class="text-muted">Chassis:</span> <strong class="info-chassis"><?php echo e($it['chassis_no'] ?: '-'); ?></strong></div>
                                                <div class="col-6"><span class="text-muted">Motor:</span> <strong class="info-motor"><?php echo e($it['motor_no'] ?: '-'); ?></strong></div>
                                                <div class="col-6"><span class="text-muted">Battery:</span> <strong class="info-battery"><?php echo e($it['battery_serial'] ?: '-'); ?></strong></div>
                                                <div class="col-6"><span class="text-muted">Charger:</span> <strong class="info-charger"><?php echo e($it['charger_serial'] ?: '-'); ?></strong></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><input type="number" step="0.01" name="sale_price[]" class="form-control salePrice" placeholder="Enter price" oninput="calcTotal()" required value="<?php echo $it['sale_price'] + 0; ?>"></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td class="ps-4">
                                        <select name="variant_id[]" class="form-select variant-select" required>
                                            <option value="">Select Variant</option>
                                            <?php $variants->execute(); while ($v = $variants->fetch(PDO::FETCH_ASSOC)): ?>
                                                <option value="<?php echo $v['vid']; ?>" data-price="<?php echo $v['variant_sale'] ?: $v['variant_purchase']; ?>"><?php echo e($v['bname'] . ' ' . $v['mname'] . ' - ' . $v['vname'] . ($v['vcolor'] ? ' (' . $v['vcolor'] . ')' : '') . ' (' . $v['bike_count'] . ')'); ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="stock_id[]" class="form-select bike-select" required disabled>
                                            <option value="">-- Select variant first --</option>
                                        </select>
                                        <div class="bike-info mt-2" style="display:none; font-size:0.82rem; background:#f8f9fa; border-radius:6px; padding:7px 10px; border:1px solid #dee2e6;">
                                            <div class="row g-1">
                                                <div class="col-6"><span class="text-muted">Chassis:</span> <strong class="info-chassis">-</strong></div>
                                                <div class="col-6"><span class="text-muted">Motor:</span> <strong class="info-motor">-</strong></div>
                                                <div class="col-6"><span class="text-muted">Battery:</span> <strong class="info-battery">-</strong></div>
                                                <div class="col-6"><span class="text-muted">Charger:</span> <strong class="info-charger">-</strong></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><input type="number" step="0.01" name="sale_price[]" class="form-control salePrice" placeholder="Enter price" oninput="calcTotal()" required></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
                                </tr>
                                <?php endif; ?>
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
                                <input type="number" step="0.01" name="down_payment" class="form-control form-control-lg" placeholder="Enter amount" value="<?php echo $editSale ? $editSale['down_payment'] + 0 : ''; ?>" oninput="calcSummary()" style="font-size:1.2rem;font-weight:700;border:none;background:transparent;padding-left:0;">
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
                                    <option value="cash" <?php if ($editSale && ($editSale['payment_method'] ?: 'cash') == 'cash') echo 'selected'; ?>>Cash</option>
                                    <option value="bank" <?php if ($editSale && $editSale['payment_method'] == 'bank') echo 'selected'; ?>>Bank</option>
                                    <option value="online" <?php if ($editSale && $editSale['payment_method'] == 'online') echo 'selected'; ?>>Online</option>
                                    <option value="other" <?php if ($editSale && $editSale['payment_method'] == 'other') echo 'selected'; ?>>Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                            <button type="submit" name="save" class="btn btn-primary btn-lg w-100 py-3"><i class="bi bi-check-circle me-2"></i> <?php echo $editSale ? 'Update Sale' : 'Complete Sale'; ?></button>
                    </div>
                </div>
            </div>
        </form>
        <div class="text-center mb-4">
            <a href="sale_list.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left me-1"></i> View Sales History</a>
        </div>
    </div>

<style>
.bike-select { min-width: 100% !important; }
.variant-select option { white-space: nowrap; }
</style>
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
    if (e.target.classList.contains('variant-select')) {
        var row = e.target.closest('tr');
        var bikeSel = row.querySelector('.bike-select');
        var bikeInfo = row.querySelector('.bike-info');
        var variantId = e.target.value;
        if (!variantId) {
            bikeSel.innerHTML = '<option value="">-- Select variant first --</option>';
            bikeSel.disabled = true;
            bikeInfo.style.display = 'none';
            return;
        }
        bikeSel.innerHTML = '<option value="">Loading...</option>';
        bikeSel.disabled = true;
        bikeInfo.style.display = 'none';
        fetch('stock_get.php?variant_id=' + variantId)
            .then(function(r) { return r.json(); })
            .then(function(bikes) {
                var html = '<option value="">-- Select bike (' + bikes.length + ' available) --</option>';
                bikes.forEach(function(b) {
                    var label = 'Chassis: ' + (b.chassis_no || 'No Chassis');
                    html += '<option value="' + b.id + '"'
                        + ' data-price="' + (b.sale_price || 0) + '"'
                        + ' data-chassis="' + (b.chassis_no || '-') + '"'
                        + ' data-motor="' + (b.motor_no || '-') + '"'
                        + ' data-battery="' + (b.battery_serial || '-') + '"'
                        + ' data-charger="' + (b.charger_serial || '-') + '"'
                        + '>' + label + '</option>';
                });
                bikeSel.innerHTML = html;
                bikeSel.disabled = false;
            })
            .catch(function() {
                bikeSel.innerHTML = '<option value="">-- Failed to load bikes --</option>';
                bikeSel.disabled = false;
            });
    }
    if (e.target.classList.contains('bike-select')) {
        var row = e.target.closest('tr');
        var opt = e.target.options[e.target.selectedIndex];
        var bikeInfo = row.querySelector('.bike-info');
        var price = parseFloat(opt.getAttribute('data-price')) || 0;
        if (price > 0 && (!row.querySelector('.salePrice').value || row.querySelector('.salePrice').value == 0)) {
            row.querySelector('.salePrice').value = price;
        }
        // Update info card
        if (e.target.value) {
            row.querySelector('.info-chassis').textContent  = opt.getAttribute('data-chassis')  || '-';
            row.querySelector('.info-motor').textContent    = opt.getAttribute('data-motor')    || '-';
            row.querySelector('.info-battery').textContent  = opt.getAttribute('data-battery')  || '-';
            row.querySelector('.info-charger').textContent  = opt.getAttribute('data-charger')  || '-';
            bikeInfo.style.display = 'block';
        } else {
            bikeInfo.style.display = 'none';
        }
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
    row.querySelectorAll('input').forEach(function(e) { e.value = ''; });
    row.querySelector('.bike-select').innerHTML = '<option value="">-- Select variant first --</option>';
    row.querySelector('.bike-select').disabled = true;
    row.querySelector('.bike-info').style.display = 'none';
    var variantSel = row.querySelector('.variant-select');
    variantSel.selectedIndex = 0;
    tbody.appendChild(row);
    calcTotal();
}
function removeRow(el) {
    if (document.querySelectorAll('#itemsTable tbody tr').length > 1) { el.closest('tr').remove(); calcTotal(); }
}
calcTotal();
toggleInstallment();
<?php if ($editSale): ?>
document.querySelector('input[name="discount"]').value = '<?php echo $editSale['discount'] + 0; ?>';
document.querySelector('input[name="down_payment"]').value = '<?php echo $editSale['down_payment'] + 0; ?>';
calcSummary();
<?php endif; ?>
</script>
<?php require_once '../includes/footer.php'; ?>
