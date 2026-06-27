<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save'])) {
    $pdo->prepare("INSERT INTO products (name, model, brand, price, stock, created_at) VALUES (?,?,?,?,?,CURDATE())")->execute([$_POST['name'], $_POST['model'], $_POST['brand'], $_POST['price'], $_POST['stock']]);
    logActivity($pdo, 'Add Product', $_POST['name']);
    header('Location: products.php'); exit;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Add Product</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name']; ?></div>
    </div>
    <div class="main-content">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>New Product Entry</span>
                <a href="products.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <?php echo csrfField(); ?>
                    <div class="col-md-6"><label class="form-label">Product Name *</label><input type="text" name="name" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Model</label><input type="text" name="model" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label">Brand</label><input type="text" name="brand" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">Price</label><input type="number" step="0.01" name="price" class="form-control" value="0"></div>
                    <div class="col-md-4"><label class="form-label">Opening Stock</label><input type="number" name="stock" class="form-control" value="0" min="0"><div class="form-text">Initial stock quantity.</div></div>
                    <div class="col-md-4 d-flex align-items-end"><button type="submit" name="save" class="btn btn-primary w-100"><i class="bi bi-save"></i> Save Product</button></div>
                </form>
            </div>
        </div>
    </div>
<?php require_once '../includes/footer.php'; ?>
