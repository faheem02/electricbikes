<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$sid = $_GET['supplier_id'] ?? 0;
$from = $_GET['from_date'] ?? '';
$to = $_GET['to_date'] ?? '';
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name");

$supplier = null; $ledger = []; $balance = 0;
if ($sid) {
    $s = $pdo->prepare("SELECT * FROM suppliers WHERE id=?");
    $s->execute([$sid]);
    $supplier = $s->fetch(PDO::FETCH_ASSOC);
    if ($supplier) {
        $where = "supplier_id=$sid";
        if ($from) $where .= " AND date>='$from'";
        if ($to) $where .= " AND date<='$to'";
        $ledger = $pdo->query("SELECT * FROM supplier_ledger WHERE $where ORDER BY date, id");
        $balance = $supplier['opening_balance'];
        $all = $pdo->query("SELECT * FROM supplier_ledger WHERE supplier_id=$sid ORDER BY date, id");
        while ($r = $all->fetch(PDO::FETCH_ASSOC)) $balance += $r['debit'] - $r['credit'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_entry'])) {
    $pdo->prepare("INSERT INTO supplier_ledger (supplier_id, date, description, debit, credit, balance) VALUES (?,?,?,?,?,0)")->execute([$_POST['supplier_id'], $_POST['date'], $_POST['description'], $_POST['debit'] ?? 0, $_POST['credit'] ?? 0]);
    header("Location: supplier_ledger.php?supplier_id={$_POST['supplier_id']}"); exit;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Supplier Ledger</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name']; ?></div>
    </div>
    <div class="main-content">
        <div class="card mb-3">
            <div class="card-header">Select Supplier</div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <select name="supplier_id" class="form-select" required>
                            <option value="">Select Supplier</option>
                            <?php while ($c = $suppliers->fetch(PDO::FETCH_ASSOC)): ?>
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
        <div class="card mb-3">
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
        <div class="card">
            <div class="card-header">Add Manual Entry</div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="supplier_id" value="<?php echo $sid; ?>">
                    <div class="col-md-3"><input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="col-md-3"><input type="text" name="description" class="form-control" placeholder="Description" required></div>
                    <div class="col-md-2"><input type="number" step="0.01" name="debit" class="form-control" placeholder="Debit"></div>
                    <div class="col-md-2"><input type="number" step="0.01" name="credit" class="form-control" placeholder="Credit"></div>
                    <div class="col-md-2"><button type="submit" name="add_entry" class="btn btn-primary w-100">Add</button></div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php require_once '../includes/footer.php'; ?>
