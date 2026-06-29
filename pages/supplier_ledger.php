<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$sid = intval($_GET['supplier_id'] ?? 0);
$from = $_GET['from_date'] ?? '';
$to = $_GET['to_date'] ?? '';
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name");

$supplier = null; $ledger = []; $balance = 0;
if ($sid) {
    $s = $pdo->prepare("SELECT * FROM suppliers WHERE id=?");
    $s->execute([$sid]);
    $supplier = $s->fetch(PDO::FETCH_ASSOC);
    if ($supplier) {
        $params = [$sid];
        $where = "supplier_id=?";
        if ($from) { $where .= " AND date>=?"; $params[] = $from; }
        if ($to) { $where .= " AND date<=?"; $params[] = $to; }
        $stmt = $pdo->prepare("SELECT * FROM supplier_ledger WHERE $where ORDER BY date, id");
        $stmt->execute($params);
        $ledger = $stmt;

        $balance = $supplier['opening_balance'];
        $all = $pdo->prepare("SELECT * FROM supplier_ledger WHERE supplier_id=? ORDER BY date, id");
        $all->execute([$sid]);
        while ($r = $all->fetch(PDO::FETCH_ASSOC)) $balance += $r['debit'] - $r['credit'];
    }
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
        $ledgerDebit = 0;
        $ledgerCredit = $amount;
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
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Supplier Ledger</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?></div>
    </div>
    <div class="main-content">
        <div class="card mb-3">
            <div class="card-header">Select Supplier</div>
            <div class="card-body">
                <form method="GET" class="row g-3">
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
        </div>
        <?php if ($supplier): ?>
        <div class="row g-3 mb-3">
            <div class="col-md-8">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between">
                        <span>Ledger: <strong><?php echo e($supplier['name']); ?></strong></span>
                        <span>Balance: <strong class="<?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatMoney($balance); ?></strong></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive p-3">
                            <table class="table table-hover">
                                <thead><tr><th>Date</th><th>Description</th><th>Debit</th><th>Credit</th><th>Balance</th></tr></thead>
                                <tbody>
                                    <tr><td><?php echo $supplier['created_at']; ?></td><td>Opening Balance</td><td>-</td><td>-</td><td class="fw-semibold"><?php echo formatMoney($supplier['opening_balance']); ?></td></tr>
                                    <?php $run = $supplier['opening_balance']; while ($r = $ledger->fetch(PDO::FETCH_ASSOC)): $run += $r['debit'] - $r['credit']; ?>
                                    <tr><td><?php echo $r['date']; ?></td><td><?php echo e($r['description']); ?></td><td><?php echo $r['debit'] ? formatMoney($r['debit']) : '-'; ?></td><td><?php echo $r['credit'] ? formatMoney($r['credit']) : '-'; ?></td><td class="fw-semibold"><?php echo formatMoney($run); ?></td></tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><i class="bi bi-pencil-square me-1"></i>Add Entry</div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="supplier_id" value="<?php echo $sid; ?>">
                            <div class="mb-2">
                                <label class="form-label small">Type</label>
                                <select name="entry_type" class="form-select" required>
                                    <option value="pay_supplier">We pay supplier</option>
                                    <option value="supplier_pays">Supplier pays us</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Amount</label>
                                <input type="number" step="0.01" name="amount" class="form-control" placeholder="Enter amount" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Description</label>
                                <input type="text" name="description" class="form-control" placeholder="Description" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Payment Method</label>
                                <select name="payment_method" class="form-select">
                                    <option value="">None</option>
                                    <option value="cash">Cash</option>
                                    <option value="bank">Bank</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small">Date</label>
                                <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <button type="submit" name="add_entry" class="btn btn-primary w-100">Add Entry</button>
                        </form>
                        <hr>
                        <div class="small text-muted">
                            <p class="mb-1"><strong>We pay supplier</strong> &mdash; debit entry (reduces what we owe). Select Cash or Bank to update the respective book.</p>
                            <p class="mb-0"><strong>Supplier pays us</strong> &mdash; credit entry (refund).</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php require_once '../includes/footer.php'; ?>
