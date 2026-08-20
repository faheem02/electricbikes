<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$err = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_variant'])) {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $color = trim($_POST['color']);
    $purchase = floatval($_POST['purchase_price']);
    $sale = floatval($_POST['sale_price']);
    if ($name !== '' && $id > 0) {
        $pdo->prepare("UPDATE bike_variants SET name=?, color=?, purchase_price=?, sale_price=? WHERE id=?")->execute([$name, $color ?: null, $purchase, $sale, $id]);
        logActivity($pdo, 'Product Updated', "Variant #$id: $name");
        $success = 'Product updated successfully!';
    } else {
        $err = 'Product name is required.';
    }
}

if (isset($_GET['delete_variant'])) {
    $id = intval($_GET['delete_variant']);
    $pdo->prepare("DELETE FROM bike_variants WHERE id=?")->execute([$id]);
    logActivity($pdo, 'Product Deleted', "Variant #$id");
    header('Location: products.php'); exit;
}

if (isset($_GET['delete_stock'])) {
    $stockId = intval($_GET['delete_stock']);
    $variantId = intval($_GET['variant_id'] ?? 0);
    $row = $pdo->prepare("SELECT chassis_no, status FROM bike_stock WHERE id=?");
    $row->execute([$stockId]);
    $unit = $row->fetch(PDO::FETCH_ASSOC);
    if ($unit && in_array($unit['status'], ['sold', 'booked'])) {
        // Cannot delete sold/booked units — return JSON error for AJAX
        echo json_encode(['error' => 'Cannot delete a unit that is sold or booked.']);
        exit;
    }
    $pdo->prepare("DELETE FROM bike_stock WHERE id=?")->execute([$stockId]);
    logActivity($pdo, 'Stock Unit Deleted', "bike_stock #$stockId chassis: " . ($unit['chassis_no'] ?: 'N/A'));
    echo json_encode(['success' => true]);
    exit;
}

$products = $pdo->query("
    SELECT v.id as variant_id, b.name as brand_name, m.name as model_name, v.name as variant_name, v.color,
        v.purchase_price, v.sale_price,
        (SELECT COUNT(*) FROM bike_stock s WHERE s.variant_id=v.id AND s.status='in_stock') as in_stock,
        (SELECT COUNT(*) FROM bike_stock s WHERE s.variant_id=v.id AND s.status='booked') as booked,
        (SELECT COUNT(*) FROM bike_stock s WHERE s.variant_id=v.id AND s.status='sold') as sold
    FROM bike_variants v
    JOIN bike_models m ON v.model_id=m.id
    JOIN bike_brands b ON m.brand_id=b.id
    ORDER BY b.name, m.name, v.name
")->fetchAll(PDO::FETCH_ASSOC);

$totalVariants = count($products);
$totalUnits = $pdo->query("SELECT COUNT(*) FROM bike_stock")->fetchColumn();

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Products</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
    </div>
    <div class="main-content">
        <?php if ($err): ?><div class="alert alert-danger"><?php echo e($err); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="bi bi-bicycle me-2"></i>All Products</span>
                <div class="d-flex gap-2">
                    <span class="badge bg-success fs-6"><?php echo $totalUnits; ?> units</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Brand</th>
                                <th>Model</th>
                                <th>Product</th>
                                <th>Color</th>
                                <th class="text-end">Purchase Price</th>
                                <th class="text-end">Sale Price</th>
                                <th class="text-center">In Stock</th>
                                <th class="text-center">Booked</th>
                                <th class="text-center">Sold</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $i = 1; foreach ($products as $p): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo e($p['brand_name']); ?></td>
                                <td><?php echo e($p['model_name']); ?></td>
                                <td class="fw-semibold"><?php echo e($p['variant_name']); ?></td>
                                <td><?php echo $p['color'] ? '<span class="badge bg-light text-muted border">' . e($p['color']) . '</span>' : '-'; ?></td>
                                <td class="text-end"><?php echo formatMoney($p['purchase_price']); ?></td>
                                <td class="text-end"><?php echo formatMoney($p['sale_price']); ?></td>
                                <td class="text-center"><span class="badge bg-success"><?php echo $p['in_stock']; ?></span></td>
                                <td class="text-center"><?php echo $p['booked'] ? '<span class="badge bg-warning text-dark">' . $p['booked'] . '</span>' : '-'; ?></td>
                                <td class="text-center"><?php echo $p['sold'] ? '<span class="badge bg-primary">' . $p['sold'] . '</span>' : '-'; ?></td>
                                <td class="text-nowrap text-center">
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="viewProduct(<?php echo $p['variant_id']; ?>)" title="View"><i class="bi bi-eye"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editProduct(<?php echo $p['variant_id']; ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
                                    <a href="?delete_variant=<?php echo $p['variant_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this product? All related bike stock and sale records will be deleted.')" title="Delete"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($products)): ?>
                            <tr><td colspan="11" class="text-center text-muted py-4">No products found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>Product Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewBody">
                    <div class="text-center text-muted py-3">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="id" id="editId">
                        <div class="mb-3">
                            <label class="form-label fw-medium">Product Name</label>
                            <input type="text" name="name" id="editName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Color</label>
                            <input type="text" name="color" id="editColor" class="form-control" placeholder="e.g. Red">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Purchase Price</label>
                            <input type="number" step="0.01" name="purchase_price" id="editPurchase" class="form-control" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-medium">Sale Price</label>
                            <input type="number" step="0.01" name="sale_price" id="editSale" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_variant" class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<script>
function viewProduct(id) {
    var body = document.getElementById('viewBody');
    body.innerHTML = '<div class="text-center text-muted py-3">Loading...</div>';
    new bootstrap.Modal(document.getElementById('viewModal')).show();
    fetch('product_details.php?variant_id=' + id)
        .then(function(r) { return r.text(); })
        .then(function(html) { body.innerHTML = html; })
        .catch(function() { body.innerHTML = '<div class="alert alert-danger">Failed to load details.</div>'; });
}
var productsData = <?php echo json_encode($products); ?>;
function editProduct(id) {
    var p = productsData.find(function(x) { return x.variant_id == id; });
    if (!p) return;
    document.getElementById('editId').value = p.variant_id;
    document.getElementById('editName').value = p.variant_name;
    document.getElementById('editColor').value = p.color || '';
    document.getElementById('editPurchase').value = p.purchase_price;
    document.getElementById('editSale').value = p.sale_price;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php require_once '../includes/footer.php'; ?>
