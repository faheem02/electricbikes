<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$cid = intval($_GET['customer_id'] ?? 0);
$from = $_GET['from_date'] ?? '';
$to = $_GET['to_date'] ?? '';
$customers = $pdo->query("SELECT * FROM customers ORDER BY name");

$customer = null; $ledger = []; $balance = 0;
if ($cid) {
    $s = $pdo->prepare("SELECT * FROM customers WHERE id=?");
    $s->execute([$cid]);
    $customer = $s->fetch(PDO::FETCH_ASSOC);
    if ($customer) {
        $params = [$cid];
        $where = "cl.customer_id=?";
        if ($from) { $where .= " AND cl.date>=?"; $params[] = $from; }
        if ($to) { $where .= " AND cl.date<=?"; $params[] = $to; }
        $stmt = $pdo->prepare("SELECT cl.*, GROUP_CONCAT(DISTINCT CONCAT(b.name, ' ', m.name, ' ', v.name) ORDER BY b.name SEPARATOR ', ') as products FROM customer_ledger cl LEFT JOIN sales s ON cl.description LIKE CONCAT('%', s.invoice_no, '%') LEFT JOIN sale_items si ON si.sale_id = s.id LEFT JOIN bike_stock st ON si.stock_id = st.id LEFT JOIN bike_variants v ON st.variant_id = v.id LEFT JOIN bike_models m ON v.model_id = m.id LEFT JOIN bike_brands b ON m.brand_id = b.id WHERE $where GROUP BY cl.id ORDER BY cl.date, cl.id");
        $stmt->execute($params);
        $ledger = $stmt;

        $balance = $customer['opening_balance'];
        $all = $pdo->prepare("SELECT * FROM customer_ledger WHERE customer_id=? ORDER BY date, id");
        $all->execute([$cid]);
        while ($r = $all->fetch(PDO::FETCH_ASSOC)) $balance += $r['debit'] - $r['credit'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_entry'])) {
    $amount = floatval($_POST['amount']);
    $type = $_POST['entry_type'];
    $method = $_POST['payment_method'];
    $desc = $_POST['description'];
    $entryDate = $_POST['date'];

    if ($type === 'pay_us') {
        $ledgerDebit = 0;
        $ledgerCredit = $amount;
        $bookType = 'in';
    } else {
        $ledgerDebit = $amount;
        $ledgerCredit = 0;
        $bookType = 'out';
    }

    $pdo->prepare("INSERT INTO customer_ledger (customer_id, date, description, debit, credit, balance) VALUES (?,?,?,?,?,0)")->execute([$_POST['customer_id'], $entryDate, $desc, $ledgerDebit, $ledgerCredit]);

    if ($method === 'cash') {
        $pdo->prepare("INSERT INTO cash_book (date, description, type, amount, balance) VALUES (?,?,?,?,0)")->execute([$entryDate, $desc, $bookType, $amount]);
    } elseif ($method === 'bank') {
        $pdo->prepare("INSERT INTO bank_book (date, description, type, amount, balance) VALUES (?,?,?,?,0)")->execute([$entryDate, $desc, $bookType, $amount]);
    }

    header("Location: customer_ledger.php?customer_id={$_POST['customer_id']}"); exit;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<style>
.cust-ledger-filter { background:var(--bg-card); padding:15px 22px; border-bottom:1px solid var(--border-color); }
.cust-ledger-filter .form-select, .cust-ledger-filter .form-control { font-size:13px; }
.cust-header { background:var(--bg-card); padding:14px 22px; border-bottom:1px solid var(--border-color); }
.cust-header .name { font-size:16px; font-weight:600; }
.cust-header .detail { font-size:13px; color:var(--text-muted); }
.cust-header .balance-label { font-size:12px; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; }
.cust-header .balance-value { font-size:22px; font-weight:700; }
.table th { padding:10px 22px !important; font-size:12px; text-transform:uppercase; letter-spacing:0.3px; }
.table td { padding:10px 22px !important; }
</style>
<div class="content" style="min-height:100vh;">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Customer Ledger</span></div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($customer): ?>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEntryModal"><i class="bi bi-plus-lg me-1"></i>Add Entry</button>
            <?php endif; ?>
            <span class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?></span>
        </div>
    </div>
    <div class="main-content p-0" style="flex:1;display:flex;flex-direction:column;">
        <div class="cust-ledger-filter">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <select name="customer_id" class="form-select" required>
                        <option value="">Select Customer</option>
                        <?php $customers->execute(); while ($c = $customers->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $cid == $c['id'] ? 'selected' : ''; ?>><?php echo e($c['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3"><input type="date" name="from_date" class="form-control" value="<?php echo $from; ?>"></div>
                <div class="col-md-3"><input type="date" name="to_date" class="form-control" value="<?php echo $to; ?>"></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">View</button></div>
            </form>
        </div>
        <?php if ($customer): ?>
        <div class="cust-header d-flex justify-content-between align-items-center">
            <div>
                <div class="name"><?php echo e($customer['name']); ?></div>
                <div class="detail">
                    <?php if ($customer['mobile']): ?><span class="me-3"><i class="bi bi-telephone me-1"></i><?php echo e($customer['mobile']); ?></span><?php endif; ?>
                    <?php if ($customer['city']): ?><span class="me-3"><i class="bi bi-geo-alt me-1"></i><?php echo e($customer['city']); ?></span><?php endif; ?>
                    <?php if ($customer['cnic']): ?><span><i class="bi bi-card-text me-1"></i><?php echo e($customer['cnic']); ?></span><?php endif; ?>
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
                        <td><?php echo $customer['created_at']; ?></td>
                        <td>-</td>
                        <td>Opening Balance</td>
                        <td class="text-end">-</td>
                        <td class="text-end">-</td>
                        <td class="text-end fw-bold"><?php echo formatMoney($customer['opening_balance']); ?></td>
                    </tr>
                    <?php $run = $customer['opening_balance']; while ($r = $ledger->fetch(PDO::FETCH_ASSOC)): $run += $r['debit'] - $r['credit']; ?>
                    <tr>
                        <td class="text-nowrap"><?php echo $r['date']; ?></td>
                        <td><span class="fw-medium" style="font-size:12px;"><?php echo $r['products'] ? e($r['products']) : '-'; ?></span></td>
                        <td style="font-size:12px;color:var(--text-muted);"><?php echo e($r['description']); ?></td>
                        <td class="text-end"><?php echo $r['debit'] ? formatMoney($r['debit']) : '-'; ?></td>
                        <td class="text-end"><?php echo $r['credit'] ? formatMoney($r['credit']) : '-'; ?></td>
                        <td class="text-end fw-semibold <?php echo $run >= 0 ? '' : 'text-danger'; ?>"><?php echo formatMoney($run); ?></td>
                    </tr>
                    <?php endwhile; ?>
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
                    <input type="hidden" name="customer_id" value="<?php echo $cid; ?>">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Type</label>
                        <select name="entry_type" class="form-select" required>
                            <option value="pay_us">Customer pays us</option>
                            <option value="pay_customer">We pay customer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Amount</label>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="Enter amount" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="e.g. Payment received" required>
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
<?php require_once '../includes/footer.php'; ?>
