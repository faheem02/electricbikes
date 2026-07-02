<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save'])) {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE suppliers SET name=?, contact_person=?, phone=?, address=?, opening_balance=? WHERE id=?");
        $stmt->execute([$_POST['name'], $_POST['contact_person'], $_POST['phone'], $_POST['address'], $_POST['opening_balance'] ?? 0, $id]);
        logActivity($pdo, 'Update Supplier', 'Updated: ' . $_POST['name']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO suppliers (name, contact_person, phone, address, opening_balance, created_at) VALUES (?,?,?,?,?, CURDATE())");
        $stmt->execute([$_POST['name'], $_POST['contact_person'], $_POST['phone'], $_POST['address'], $_POST['opening_balance'] ?? 0]);
        logActivity($pdo, 'Create Supplier', 'Created: ' . $_POST['name']);
    }
    header('Location: suppliers.php'); exit;
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM suppliers WHERE id=?")->execute([$_GET['delete']]);
    header('Location: suppliers.php'); exit;
}

$editData = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM suppliers WHERE id=?");
    $s->execute([$_GET['edit']]);
    $editData = $s->fetch(PDO::FETCH_ASSOC);
}

$result = $pdo->query("SELECT s.*, (s.opening_balance + COALESCE((SELECT SUM(credit - debit) FROM supplier_ledger WHERE supplier_id = s.id), 0)) as computed_balance FROM suppliers s ORDER BY s.name");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Suppliers</span></div>
        <span class="user-info">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#supplierModal"><i class="bi bi-plus-lg me-1"></i>Add Supplier</button>
            <i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button>
        </span>
    </div>
    <div class="main-content">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-truck me-2"></i>All Suppliers</span>
            </div>
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
                                <td class="fw-semibold <?php echo $r['computed_balance'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatMoney($r['computed_balance']); ?></td>
                                <td>
                                    <a href="?edit=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary edit-btn" data-id="<?php echo $r['id']; ?>"><i class="bi bi-pencil"></i></a>
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

<!-- Add / Edit Supplier Modal -->
<div class="modal fade" id="supplierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-truck me-2"></i><span id="modalTitle">Add Supplier</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="id" id="supplierId" value="0">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Name *</label>
                            <input type="text" name="name" id="supplierName" class="form-control" placeholder="Supplier name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Contact Person</label>
                            <input type="text" name="contact_person" id="supplierContact" class="form-control" placeholder="Contact person name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Phone</label>
                            <input type="text" name="phone" id="supplierPhone" class="form-control" placeholder="Phone number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Opening Balance</label>
                            <input type="number" step="0.01" name="opening_balance" id="supplierBalance" class="form-control" placeholder="0.00">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Address</label>
                            <textarea name="address" id="supplierAddress" class="form-control" rows="2" placeholder="Address"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($editData): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('modalTitle').textContent = 'Edit Supplier';
    document.getElementById('supplierId').value = <?php echo $editData['id']; ?>;
    document.getElementById('supplierName').value = <?php echo json_encode($editData['name']); ?>;
    document.getElementById('supplierContact').value = <?php echo json_encode($editData['contact_person'] ?? ''); ?>;
    document.getElementById('supplierPhone').value = <?php echo json_encode($editData['phone'] ?? ''); ?>;
    document.getElementById('supplierBalance').value = <?php echo $editData['opening_balance'] ?? 0; ?>;
    document.getElementById('supplierAddress').value = <?php echo json_encode($editData['address'] ?? ''); ?>;
    var modal = new bootstrap.Modal(document.getElementById('supplierModal'));
    modal.show();
});
</script>
<?php endif; ?>

<script>
document.querySelectorAll('.edit-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var id = this.getAttribute('data-id');
        fetch('suppliers_get.php?id=' + id)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('modalTitle').textContent = 'Edit Supplier';
                document.getElementById('supplierId').value = data.id;
                document.getElementById('supplierName').value = data.name;
                document.getElementById('supplierContact').value = data.contact_person || '';
                document.getElementById('supplierPhone').value = data.phone || '';
                document.getElementById('supplierBalance').value = data.opening_balance || 0;
                document.getElementById('supplierAddress').value = data.address || '';
                var modal = new bootstrap.Modal(document.getElementById('supplierModal'));
                modal.show();
            });
    });
});

document.getElementById('supplierModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('modalTitle').textContent = 'Add Supplier';
    document.getElementById('supplierId').value = 0;
    document.getElementById('supplierName').value = '';
    document.getElementById('supplierContact').value = '';
    document.getElementById('supplierPhone').value = '';
    document.getElementById('supplierBalance').value = '';
    document.getElementById('supplierAddress').value = '';
});
</script>
<?php require_once '../includes/footer.php'; ?>
