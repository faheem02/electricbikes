<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save'])) {
    $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_person, phone, address, opening_balance, created_at) VALUES (?,?,?,?,?, CURDATE())");
    $stmt->execute([$_POST['name'], $_POST['contact_person'], $_POST['phone'], $_POST['address'], $_POST['opening_balance'] ?? 0]);
    logActivity($pdo, 'Create Supplier', 'Created: ' . $_POST['name']);
    header('Location: suppliers.php'); exit;
}
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM suppliers WHERE id=?")->execute([$_GET['delete']]);
    header('Location: suppliers.php'); exit;
}
$edit = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM suppliers WHERE id=?");
    $s->execute([$_GET['edit']]);
    $edit = $s->fetch(PDO::FETCH_ASSOC);
}
$result = $pdo->query("SELECT * FROM suppliers ORDER BY name");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Suppliers</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name']; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
    </div>
    <div class="main-content">
        <div class="card mb-3">
            <div class="card-header"><?php echo $edit ? 'Edit Supplier' : 'Add Supplier'; ?></div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="id" value="<?php echo $edit['id'] ?? ''; ?>">
                    <div class="col-md-4"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" value="<?php echo e($edit['name'] ?? ''); ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Contact Person</label><input type="text" name="contact_person" class="form-control" value="<?php echo e($edit['contact_person'] ?? ''); ?>"></div>
                    <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?php echo e($edit['phone'] ?? ''); ?>"></div>
                    <div class="col-md-6"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?php echo e($edit['address'] ?? ''); ?></textarea></div>
                    <div class="col-md-3"><label class="form-label">Opening Balance</label><input type="number" step="0.01" name="opening_balance" class="form-control" value="<?php echo $edit['opening_balance'] ?? '0'; ?>"></div>
                    <div class="col-md-3 d-flex align-items-end"><button type="submit" name="save" class="btn btn-primary w-100"><?php echo $edit ? 'Update' : 'Save'; ?></button></div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header">All Suppliers</div>
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <table class="table table-hover">
                        <thead><tr><th>#</th><th>Name</th><th>Contact</th><th>Phone</th><th>Balance</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php $i=1; while ($r = $result->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo e($r['name']); ?></td>
                                <td><?php echo e($r['contact_person']); ?></td>
                                <td><?php echo e($r['phone']); ?></td>
                                <td><?php echo formatMoney($r['opening_balance']); ?></td>
                                <td>
                                    <a href="?edit=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="supplier_ledger.php?supplier_id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-journal-text"></i></a>
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
