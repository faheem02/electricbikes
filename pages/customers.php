<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save'])) {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE customers SET name=?, father_name=?, cnic=?, mobile=?, address=?, city=?, reference=?, notes=?, opening_balance=? WHERE id=?");
        $stmt->execute([$_POST['name'], $_POST['father_name'], $_POST['cnic'], $_POST['mobile'], $_POST['address'], $_POST['city'], $_POST['reference'], $_POST['notes'], $_POST['opening_balance'] ?? 0, $id]);
        logActivity($pdo, 'Update Customer', 'Updated: ' . $_POST['name']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO customers (name, father_name, cnic, mobile, address, city, reference, notes, opening_balance, created_at) VALUES (?,?,?,?,?,?,?,?,?, CURDATE())");
        $stmt->execute([$_POST['name'], $_POST['father_name'], $_POST['cnic'], $_POST['mobile'], $_POST['address'], $_POST['city'], $_POST['reference'], $_POST['notes'], $_POST['opening_balance'] ?? 0]);
        logActivity($pdo, 'Create Customer', 'Created: ' . $_POST['name']);
    }
    header('Location: customers.php'); exit;
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM customers WHERE id=?")->execute([$_GET['delete']]);
    header('Location: customers.php'); exit;
}

$editData = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM customers WHERE id=?");
    $s->execute([$_GET['edit']]);
    $editData = $s->fetch(PDO::FETCH_ASSOC);
}

$result = $pdo->query("SELECT c.*, (c.opening_balance + COALESCE((SELECT SUM(debit - credit) FROM customer_ledger WHERE customer_id = c.id), 0)) as computed_balance FROM customers c ORDER BY c.name");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Customers</span></div>
        <span class="user-info">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#customerModal"><i class="bi bi-plus-lg me-1"></i>Add Customer</button>
            <i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button>
        </span>
    </div>
    <div class="main-content">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-people me-2"></i>All Customers</span>
            </div>
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
                                <td class="fw-semibold <?php echo $r['computed_balance'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatMoney($r['computed_balance']); ?></td>
                                <td>
                                    <a href="?edit=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary edit-btn" data-id="<?php echo $r['id']; ?>"><i class="bi bi-pencil"></i></a>
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

<!-- Add / Edit Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-people me-2"></i><span id="modalTitle">Add Customer</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="id" id="customerId" value="0">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Name *</label>
                            <input type="text" name="name" id="customerName" class="form-control" placeholder="Customer name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">Father Name</label>
                            <input type="text" name="father_name" id="customerFather" class="form-control" placeholder="Father name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium">CNIC</label>
                            <input type="text" name="cnic" id="customerCnic" class="form-control" placeholder="CNIC number">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Mobile *</label>
                            <input type="text" name="mobile" id="customerMobile" class="form-control" placeholder="Mobile number" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">City</label>
                            <input type="text" name="city" id="customerCity" class="form-control" placeholder="City">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Reference</label>
                            <input type="text" name="reference" id="customerReference" class="form-control" placeholder="Reference">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-medium">Opening Balance</label>
                            <input type="number" step="0.01" name="opening_balance" id="customerBalance" class="form-control" placeholder="0.00">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Address</label>
                            <textarea name="address" id="customerAddress" class="form-control" rows="2" placeholder="Address"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium">Notes</label>
                            <textarea name="notes" id="customerNotes" class="form-control" rows="2" placeholder="Notes"></textarea>
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
    document.getElementById('modalTitle').textContent = 'Edit Customer';
    document.getElementById('customerId').value = <?php echo $editData['id']; ?>;
    document.getElementById('customerName').value = <?php echo json_encode($editData['name']); ?>;
    document.getElementById('customerFather').value = <?php echo json_encode($editData['father_name'] ?? ''); ?>;
    document.getElementById('customerCnic').value = <?php echo json_encode($editData['cnic'] ?? ''); ?>;
    document.getElementById('customerMobile').value = <?php echo json_encode($editData['mobile']); ?>;
    document.getElementById('customerCity').value = <?php echo json_encode($editData['city'] ?? ''); ?>;
    document.getElementById('customerReference').value = <?php echo json_encode($editData['reference'] ?? ''); ?>;
    document.getElementById('customerBalance').value = <?php echo $editData['opening_balance'] ?? 0; ?>;
    document.getElementById('customerAddress').value = <?php echo json_encode($editData['address'] ?? ''); ?>;
    document.getElementById('customerNotes').value = <?php echo json_encode($editData['notes'] ?? ''); ?>;
    var modal = new bootstrap.Modal(document.getElementById('customerModal'));
    modal.show();
});
</script>
<?php endif; ?>

<script>
document.querySelectorAll('.edit-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var id = this.getAttribute('data-id');
        fetch('customers_get.php?id=' + id)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('modalTitle').textContent = 'Edit Customer';
                document.getElementById('customerId').value = data.id;
                document.getElementById('customerName').value = data.name;
                document.getElementById('customerFather').value = data.father_name || '';
                document.getElementById('customerCnic').value = data.cnic || '';
                document.getElementById('customerMobile').value = data.mobile;
                document.getElementById('customerCity').value = data.city || '';
                document.getElementById('customerReference').value = data.reference || '';
                document.getElementById('customerBalance').value = data.opening_balance || 0;
                document.getElementById('customerAddress').value = data.address || '';
                document.getElementById('customerNotes').value = data.notes || '';
                var modal = new bootstrap.Modal(document.getElementById('customerModal'));
                modal.show();
            });
    });
});

document.getElementById('customerModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('modalTitle').textContent = 'Add Customer';
    document.getElementById('customerId').value = 0;
    document.getElementById('customerName').value = '';
    document.getElementById('customerFather').value = '';
    document.getElementById('customerCnic').value = '';
    document.getElementById('customerMobile').value = '';
    document.getElementById('customerCity').value = '';
    document.getElementById('customerReference').value = '';
    document.getElementById('customerBalance').value = '';
    document.getElementById('customerAddress').value = '';
    document.getElementById('customerNotes').value = '';
});
</script>
<?php require_once '../includes/footer.php'; ?>
