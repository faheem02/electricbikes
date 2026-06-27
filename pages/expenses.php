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

$result = $pdo->prepare("SELECT * FROM expenses WHERE date BETWEEN ? AND ? ORDER BY date DESC");
$result->execute([$from, $to]);

$totalExpenses = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE date BETWEEN ? AND ?");
$totalExpenses->execute([$from, $to]);
$totalExpenses = $totalExpenses->fetchColumn();

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Expenses</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name']; ?></div>
    </div>
    <div class="main-content">
        <div class="card mb-3">
            <div class="card-header">Add Expense</div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <?php echo csrfField(); ?>
                    <div class="col-md-2">
                        <select name="category" class="form-select" required>
                            <option value="">Category</option>
                            <option>Salary</option><option>Electricity</option><option>Rent</option><option>Transport</option><option>Miscellaneous</option>
                        </select>
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
                        <?php while ($r = $result->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo formatDate($r['date']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo e($r['category']); ?></span></td>
                                <td><?php echo e($r['description']); ?></td>
                                <td class="text-danger fw-semibold"><?php echo formatMoney($r['amount']); ?></td>
                                <td><?php echo e($r['paid_by']); ?></td>
                                <td><a href="?delete=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php require_once '../includes/footer.php'; ?>
