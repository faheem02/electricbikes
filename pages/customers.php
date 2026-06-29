<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save'])) {
    $stmt = $pdo->prepare("INSERT INTO customers (name, father_name, cnic, mobile, address, city, reference, notes, opening_balance, created_at) VALUES (?,?,?,?,?,?,?,?,?, CURDATE())");
    $stmt->execute([$_POST['name'], $_POST['father_name'], $_POST['cnic'], $_POST['mobile'], $_POST['address'], $_POST['city'], $_POST['reference'], $_POST['notes'], $_POST['opening_balance'] ?? 0]);
    logActivity($pdo, 'Create Customer', 'Created: ' . $_POST['name']);
    header('Location: customers.php'); exit;
}
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM customers WHERE id=?")->execute([$_GET['delete']]);
    header('Location: customers.php'); exit;
}
$edit = null;
if (isset($_GET['edit'])) {
    $edit = $pdo->prepare("SELECT * FROM customers WHERE id=?");
    $edit->execute([$_GET['edit']]);
    $edit = $edit->fetch(PDO::FETCH_ASSOC);
}
$result = $pdo->query("SELECT * FROM customers ORDER BY name");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Customers</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
    </div>
    <div class="main-content">
        <div class="card mb-3">
            <div class="card-header"><?php echo $edit ? 'Edit Customer' : 'Add Customer'; ?></div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="id" value="<?php echo $edit['id'] ?? ''; ?>">
                    <div class="col-md-4"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" value="<?php echo e($edit['name'] ?? ''); ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Father Name</label><input type="text" name="father_name" class="form-control" value="<?php echo e($edit['father_name'] ?? ''); ?>"></div>
                    <div class="col-md-4"><label class="form-label">CNIC</label><input type="text" name="cnic" class="form-control" value="<?php echo e($edit['cnic'] ?? ''); ?>"></div>
                    <div class="col-md-3"><label class="form-label">Mobile *</label><input type="text" name="mobile" class="form-control" value="<?php echo e($edit['mobile'] ?? ''); ?>" required></div>
                    <div class="col-md-3"><label class="form-label">City</label><input type="text" name="city" class="form-control" value="<?php echo e($edit['city'] ?? ''); ?>"></div>
                    <div class="col-md-3"><label class="form-label">Reference</label><input type="text" name="reference" class="form-control" value="<?php echo e($edit['reference'] ?? ''); ?>"></div>
                    <div class="col-md-3"><label class="form-label">Opening Balance</label><input type="number" step="0.01" name="opening_balance" class="form-control" value="<?php echo $edit['opening_balance'] ?? '0'; ?>"></div>
                    <div class="col-md-6"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?php echo e($edit['address'] ?? ''); ?></textarea></div>
                    <div class="col-md-6"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?php echo e($edit['notes'] ?? ''); ?></textarea></div>
                    <div class="col-12"><button type="submit" name="save" class="btn btn-primary"><?php echo $edit ? 'Update' : 'Save'; ?></button></div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header">All Customers</div>
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <table class="table table-hover">
                        <thead><tr><th>#</th><th>Name</th><th>Mobile</th><th>Address</th><th>City</th><th>CNIC</th><th>Balance</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php $i=1; while ($r = $result->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><a href="customer_ledger.php?customer_id=<?php echo $r['id']; ?>" class="text-decoration-none"><?php echo e($r['name']); ?></a></td>
                                <td><?php echo e($r['mobile']); ?></td>
                                <td><?php echo e(StrLimit($r['address'], 40)); ?></td>
                                <td><?php echo e($r['city']); ?></td>
                                <td><?php echo e($r['cnic']); ?></td>
                                <td><?php echo formatMoney($r['opening_balance']); ?></td>
                                <td>
                                    <a href="?edit=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="customer_ledger.php?customer_id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-journal-text"></i></a>
                                    <a href="?delete=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php require_once '../includes/footer.php'; ?>
