<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save'])) {
    $pdo->prepare("INSERT INTO expenses (category, amount, description, date, paid_by, created_at) VALUES (?,?,?,?,?,CURDATE())")->execute([$_POST['category'], $_POST['amount'], $_POST['description'], $_POST['date'], $_POST['paid_by']]);
    logActivity($pdo, 'Expense Added', "{$_POST['category']}: {$_POST['amount']}");
    header('Location: expenses.php'); exit;
}
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM expenses WHERE id=?")->execute([$_GET['delete']]);
    header('Location: expenses.php'); exit;
}

$from = $_GET['from_date'] ?? date('Y-m-01');
$to = $_GET['to_date'] ?? date('Y-m-t');

$stmt = $pdo->prepare("SELECT * FROM expenses WHERE date BETWEEN ? AND ? ORDER BY date DESC");
$stmt->execute([$from, $to]);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalExpenses = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE date BETWEEN ? AND ?");
$totalExpenses->execute([$from, $to]);
$totalExpenses = $totalExpenses->fetchColumn();

// Print view
if (isset($_GET['print'])) {
    ?><!DOCTYPE html><html lang="en"><head><title>Expenses</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family:'Poppins',sans-serif; margin:0; padding:0; box-sizing:border-box; }
        body { padding:40px; background:#f5f5f5; }
        .print-box { max-width:900px; margin:auto; background:#fff; border-radius:8px; padding:40px; box-shadow:0 2px 10px rgba(0,0,0,.1); }
        .header { text-align:center; border-bottom:2px solid #A04657; padding-bottom:20px; margin-bottom:20px; }
        .header h1 { color:#A04657; font-size:24px; margin:0; }
        .header p { color:#888; margin:5px 0 0; font-size:13px; }
        .info { display:flex; justify-content:space-between; margin-bottom:15px; font-size:13px; }
        .info .label { color:#888; font-size:12px; text-transform:uppercase; letter-spacing:0.3px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { padding:7px 10px; text-align:left; border-bottom:1px solid #ddd; }
        th { background:#A04657; color:#fff; font-weight:600; font-size:11px; text-transform:uppercase; }
        .text-end { text-align:right; }
        .fw-bold { font-weight:700; }
        .text-muted { color:#888; }
        .text-danger { color:#dc3545; }
        .badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; background:#6c757d; color:#fff; }
        .footer { text-align:center; margin-top:30px; color:#888; font-size:13px; border-top:1px solid #eee; padding-top:20px; }
        .no-print { text-align:center; margin-top:20px; }
        .no-print button { display:inline-block; padding:10px 24px; margin:0 5px; border-radius:4px; font-size:14px; cursor:pointer; border:none; }
        .btn-primary { background:#A04657; color:#fff; }
        .btn-secondary { background:#6c757d; color:#fff; }
        @media print { body { padding:20px; background:#fff; } .print-box { box-shadow:none; padding:20px; } .no-print { display:none; } }
    </style></head><body>
    <div class="print-box">
        <div class="header"><h1><?php echo e(getSetting($pdo, 'company_name') ?: 'Electric Bikes Showroom'); ?></h1><p>Expenses</p></div>
        <div class="info">
            <span>Period: <?php echo $from; ?> — <?php echo $to; ?></span>
            <span class="fw-bold text-danger">Total: <?php echo formatMoney($totalExpenses); ?></span>
        </div>
        <table>
            <tr><th>Date</th><th>Category</th><th>Description</th><th class="text-end">Amount</th><th>Paid By</th></tr>
            <?php foreach ($expenses as $r): ?>
            <tr>
                <td><?php echo formatDate($r['date']); ?></td>
                <td><span class="badge"><?php echo e($r['category']); ?></span></td>
                <td><?php echo e($r['description']); ?></td>
                <td class="text-end text-danger fw-bold"><?php echo formatMoney($r['amount']); ?></td>
                <td><?php echo e($r['paid_by']); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <div class="footer">Generated on <?php echo date('d-m-Y H:i'); ?></div>
        <div class="no-print"><button onclick="window.print()" class="btn-primary"><i class="bi bi-printer"></i> Print</button> <button onclick="window.close()" class="btn-secondary">Close</button></div>
    </div>
    </body></html>
    <?php exit;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Expenses</span></div>
        <div class="d-flex align-items-center gap-2">
            <a href="?print=1&from_date=<?php echo urlencode($from); ?>&to_date=<?php echo urlencode($to); ?>" class="btn btn-outline-dark btn-sm" target="_blank"><i class="bi bi-printer me-1"></i>Print</a>
            <span class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?></span>
        </div>
    </div>
    <div class="main-content">
        <div class="card mb-3">
            <div class="card-header">Add Expense</div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <?php echo csrfField(); ?>
                    <div class="col-md-2">
                        <input type="text" name="category" class="form-control" placeholder="Category" list="catList" required>
                        <datalist id="catList">
                            <option value="Salary"><option value="Electricity"><option value="Rent"><option value="Transport"><option value="Miscellaneous">
                        </datalist>
                    </div>
                    <div class="col-md-2"><input type="number" step="0.01" name="amount" class="form-control" placeholder="Amount" required></div>
                    <div class="col-md-3"><input type="text" name="description" class="form-control" placeholder="Description"></div>
                    <div class="col-md-2"><input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>"></div>
                    <div class="col-md-2"><input type="text" name="paid_by" class="form-control" placeholder="Paid By"></div>
                    <div class="col-md-1"><button type="submit" name="save" class="btn btn-primary w-100">Add</button></div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Expenses (Total: <?php echo formatMoney($totalExpenses); ?>)</span>
                <form method="GET" class="d-flex gap-2">
                    <input type="date" name="from_date" class="form-control form-control-sm" value="<?php echo $from; ?>">
                    <input type="date" name="to_date" class="form-control form-control-sm" value="<?php echo $to; ?>">
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <table class="table table-hover">
                        <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Amount</th><th>Paid By</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($expenses as $r): ?>
                            <tr>
                                <td><?php echo formatDate($r['date']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo e($r['category']); ?></span></td>
                                <td><?php echo e($r['description']); ?></td>
                                <td class="text-danger fw-semibold"><?php echo formatMoney($r['amount']); ?></td>
                                <td><?php echo e($r['paid_by']); ?></td>
                                <td><a href="?delete=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php require_once '../includes/footer.php'; ?>
