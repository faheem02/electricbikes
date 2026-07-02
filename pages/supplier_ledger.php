<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$sid = intval($_GET['supplier_id'] ?? 0);
$from = $_GET['from_date'] ?? '';
$to = $_GET['to_date'] ?? '';
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name");

$supplier = null; $ledgerRows = []; $balance = 0;
if ($sid) {
    $s = $pdo->prepare("SELECT * FROM suppliers WHERE id=?");
    $s->execute([$sid]);
    $supplier = $s->fetch(PDO::FETCH_ASSOC);
    if ($supplier) {
        $params = [$sid];
        $where = "sl.supplier_id=?";
        if ($from) { $where .= " AND sl.date>=?"; $params[] = $from; }
        if ($to) { $where .= " AND sl.date<=?"; $params[] = $to; }
        $stmt = $pdo->prepare("SELECT sl.*, GROUP_CONCAT(DISTINCT CONCAT(b.name, ' ', m.name, ' ', v.name) ORDER BY b.name SEPARATOR ', ') as products FROM supplier_ledger sl LEFT JOIN purchases p ON sl.description LIKE CONCAT('%', p.invoice_no, '%') LEFT JOIN purchase_items pi ON pi.purchase_id = p.id LEFT JOIN bike_variants v ON pi.variant_id = v.id LEFT JOIN bike_models m ON v.model_id = m.id LEFT JOIN bike_brands b ON m.brand_id = b.id WHERE $where GROUP BY sl.id ORDER BY sl.date, sl.id");
        $stmt->execute($params);
        $ledgerRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $balance = $supplier['opening_balance'];
        $all = $pdo->prepare("SELECT * FROM supplier_ledger WHERE supplier_id=? ORDER BY date, id");
        $all->execute([$sid]);
        while ($r = $all->fetch(PDO::FETCH_ASSOC)) $balance += $r['credit'] - $r['debit'];
    }
}

// Print view
if (isset($_GET['print']) && $supplier) {
    ?><!DOCTYPE html><html lang="en"><head><title>Supplier Ledger - <?php echo e($supplier['name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Poppins', sans-serif; margin:0; padding:0; box-sizing:border-box; }
        body { padding:40px; background:#f5f5f5; }
        .print-box { max-width:1000px; margin:auto; background:#fff; border-radius:8px; padding:40px; box-shadow:0 2px 10px rgba(0,0,0,.1); }
        .header { text-align:center; border-bottom:2px solid #A04657; padding-bottom:20px; margin-bottom:20px; }
        .header h1 { color:#A04657; font-size:24px; margin:0; }
        .header p { color:#888; margin:5px 0 0; font-size:13px; }
        .info { display:flex; justify-content:space-between; margin-bottom:20px; font-size:14px; }
        .info div { line-height:1.8; }
        .info .label { color:#888; font-size:12px; text-transform:uppercase; letter-spacing:0.3px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { padding:8px 12px; text-align:left; border-bottom:1px solid #ddd; }
        th { background:#A04657; color:#fff; font-weight:600; font-size:12px; text-transform:uppercase; }
        .text-end { text-align:right; }
        .fw-bold { font-weight:700; }
        .text-success { color:#28a745; }
        .text-danger { color:#dc3545; }
        .text-muted { color:#888; }
        .opening-row td { background:#f8f9fa; font-weight:600; }
        .footer { text-align:center; margin-top:30px; color:#888; font-size:13px; border-top:1px solid #eee; padding-top:20px; }
        .no-print { text-align:center; margin-top:20px; }
        .no-print button { display:inline-block; padding:10px 24px; margin:0 5px; border-radius:4px; font-size:14px; cursor:pointer; border:none; }
        .btn-primary { background:#A04657; color:#fff; }
        .btn-secondary { background:#6c757d; color:#fff; }
        @media print { body { padding:20px; background:#fff; } .print-box { box-shadow:none; padding:20px; } .no-print { display:none; } }
    </style></head><body>
    <div class="print-box">
        <div class="header"><h1><?php echo e(getSetting($pdo, 'company_name') ?: 'Electric Bikes Showroom'); ?></h1><p>Supplier Ledger</p></div>
        <div class="info">
            <div>
                <strong><?php echo e($supplier['name']); ?></strong><br>
                <?php if ($supplier['phone']): ?><?php echo e($supplier['phone']); ?><br><?php endif; ?>
                <?php if ($supplier['address']): ?><?php echo e($supplier['address']); ?><?php endif; ?>
            </div>
            <div style="text-align:right">
                <div class="label">Period</div>
                <?php echo $from ?: 'All'; ?> — <?php echo $to ?: 'All'; ?><br>
                <div class="label" style="margin-top:5px;">Balance</div>
                <span class="fw-bold <?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatMoney($balance); ?></span>
            </div>
        </div>
        <table>
            <tr><th>Date</th><th>Product</th><th>Description</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr>
            <tr class="opening-row">
                <td><?php echo $supplier['created_at']; ?></td><td>-</td><td>Opening Balance</td>
                <td class="text-end">-</td><td class="text-end">-</td><td class="text-end fw-bold"><?php echo formatMoney($supplier['opening_balance']); ?></td>
            </tr>
            <?php $run = $supplier['opening_balance']; foreach ($ledgerRows as $r): $run += $r['credit'] - $r['debit']; ?>
            <tr>
                <td><?php echo $r['date']; ?></td>
                <td><?php echo $r['products'] ? e($r['products']) : '-'; ?></td>
                <td style="color:#666;font-size:12px;"><?php echo e($r['description']); ?></td>
                <td class="text-end"><?php echo $r['debit'] ? formatMoney($r['debit']) : '-'; ?></td>
                <td class="text-end"><?php echo $r['credit'] ? formatMoney($r['credit']) : '-'; ?></td>
                <td class="text-end fw-bold <?php echo $run >= 0 ? '' : 'text-danger'; ?>"><?php echo formatMoney($run); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <div class="footer">Generated on <?php echo date('d-m-Y H:i'); ?></div>
        <div class="no-print"><button onclick="window.print()" class="btn-primary"><i class="bi bi-printer"></i> Print</button> <button onclick="window.close()" class="btn-secondary">Close</button></div>
    </div>
    </body></html>
    <?php exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_entry'])) {
    $amount = floatval($_POST['amount']);
    $type = $_POST['entry_type'];
    $method = $_POST['payment_method'];
    $desc = $_POST['description'];
    $entryDate = $_POST['date'];

    if ($type === 'pay_supplier') {
        $ledgerDebit = $amount;
        $ledgerCredit = 0;
        $bookType = 'out';
    } else {
        $ledgerDebit = $amount;
        $ledgerCredit = 0;
        $bookType = 'in';
    }

    $pdo->prepare("INSERT INTO supplier_ledger (supplier_id, date, description, debit, credit, balance) VALUES (?,?,?,?,?,0)")->execute([$_POST['supplier_id'], $entryDate, $desc, $ledgerDebit, $ledgerCredit]);

    if ($method === 'cash') {
        $pdo->prepare("INSERT INTO cash_book (date, description, type, amount, balance) VALUES (?,?,?,?,0)")->execute([$entryDate, $desc, $bookType, $amount]);
    } elseif ($method === 'bank') {
        $pdo->prepare("INSERT INTO bank_book (date, description, type, amount, balance) VALUES (?,?,?,?,0)")->execute([$entryDate, $desc, $bookType, $amount]);
    }

    header("Location: supplier_ledger.php?supplier_id={$_POST['supplier_id']}"); exit;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<style>
.supp-ledger-filter { background:var(--bg-card); padding:15px 22px; border-bottom:1px solid var(--border-color); }
.supp-ledger-filter .form-select, .supp-ledger-filter .form-control { font-size:13px; }
.supp-header { background:var(--bg-card); padding:14px 22px; border-bottom:1px solid var(--border-color); }
.supp-header .name { font-size:16px; font-weight:600; }
.supp-header .detail { font-size:13px; color:var(--text-muted); }
.supp-header .balance-label { font-size:12px; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; }
.supp-header .balance-value { font-size:22px; font-weight:700; }
.table th { padding:10px 22px !important; font-size:12px; text-transform:uppercase; letter-spacing:0.3px; }
.table td { padding:10px 22px !important; }
</style>
<div class="content" style="min-height:100vh;">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Supplier Ledger</span></div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($supplier): ?>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEntryModal"><i class="bi bi-plus-lg me-1"></i>Add Entry</button>
                <a href="?print=1&supplier_id=<?php echo $sid; ?>&from_date=<?php echo urlencode($from); ?>&to_date=<?php echo urlencode($to); ?>" class="btn btn-outline-dark btn-sm" target="_blank"><i class="bi bi-printer me-1"></i>Print</a>
            <?php endif; ?>
            <span class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?></span>
        </div>
    </div>
    <div class="main-content" style="flex:1;display:flex;flex-direction:column;">
        <div class="supp-ledger-filter">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <select name="supplier_id" class="form-select" required>
                        <option value="">Select Supplier</option>
                        <?php $suppliers->execute(); while ($c = $suppliers->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $sid == $c['id'] ? 'selected' : ''; ?>><?php echo e($c['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3"><input type="date" name="from_date" class="form-control" value="<?php echo $from; ?>"></div>
                <div class="col-md-3"><input type="date" name="to_date" class="form-control" value="<?php echo $to; ?>"></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">View</button></div>
            </form>
        </div>
        <?php if ($supplier): ?>
        <div class="supp-header d-flex justify-content-between align-items-center">
            <div>
                <div class="name"><?php echo e($supplier['name']); ?></div>
                <div class="detail">
                    <?php if ($supplier['phone']): ?><span class="me-3"><i class="bi bi-telephone me-1"></i><?php echo e($supplier['phone']); ?></span><?php endif; ?>
                    <?php if ($supplier['address']): ?><span><i class="bi bi-geo-alt me-1"></i><?php echo e($supplier['address']); ?></span><?php endif; ?>
                </div>
            </div>
            <div class="text-end">
                <div class="balance-label">Balance</div>
                <div class="balance-value <?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatMoney($balance); ?></div>
            </div>
        </div>
        <div class="table-responsive" style="min-height:200px;">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:100px;">Date</th>
                        <th style="width:180px;">Product</th>
                        <th>Description</th>
                        <th class="text-end" style="width:130px;">Debit</th>
                        <th class="text-end" style="width:130px;">Credit</th>
                        <th class="text-end" style="width:130px;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-secondary">
                        <td><?php echo $supplier['created_at']; ?></td>
                        <td>-</td>
                        <td>Opening Balance</td>
                        <td class="text-end">-</td>
                        <td class="text-end">-</td>
                        <td class="text-end fw-bold"><?php echo formatMoney($supplier['opening_balance']); ?></td>
                    </tr>
                    <?php $run = $supplier['opening_balance']; foreach ($ledgerRows as $r): $run += $r['credit'] - $r['debit']; ?>
                    <tr>
                        <td class="text-nowrap"><?php echo $r['date']; ?></td>
                        <td><span class="fw-medium" style="font-size:12px;"><?php echo $r['products'] ? e($r['products']) : '-'; ?></span></td>
                        <td style="font-size:12px;color:var(--text-muted);"><?php echo e($r['description']); ?></td>
                        <td class="text-end"><?php echo $r['debit'] ? formatMoney($r['debit']) : '-'; ?></td>
                        <td class="text-end"><?php echo $r['credit'] ? formatMoney($r['credit']) : '-'; ?></td>
                        <td class="text-end fw-semibold <?php echo $run >= 0 ? '' : 'text-danger'; ?>"><?php echo formatMoney($run); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <div style="flex:1;"></div>

<!-- Add Entry Modal -->
<div class="modal fade" id="addEntryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Add Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="supplier_id" value="<?php echo $sid; ?>">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Type</label>
                        <select name="entry_type" class="form-select" required>
                                        <option value="pay_supplier">We pay supplier</option>
                                        <option value="supplier_pays">Supplier refund / credit note</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Amount</label>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="Enter amount" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="e.g. Payment made" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="">None</option>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-medium">Date</label>
                        <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_entry" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Add Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>
</div> <!-- close .content -->
<style>.footer{padding-left:25px;padding-right:25px}</style>
<?php require_once '../includes/footer.php'; ?>
