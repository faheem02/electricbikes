<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product) { header('Location: products.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $pdo->prepare("UPDATE products SET name=?, model=?, brand=?, price=?, stock=? WHERE id=?")->execute([$_POST['name'], $_POST['model'], $_POST['brand'], $_POST['price'], $_POST['stock'], $id]);
    header('Location: products.php'); exit;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Edit Product</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name']; ?></div>
    </div>
    <div class="main-content">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>Edit: <?php echo e($product['name']); ?></span>
                <a href="products.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <?php echo csrfField(); ?>
                    <div class="col-md-6"><label class="form-label">Product Name *</label><input type="text" name="name" class="form-control" value="<?php echo e($product['name']); ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Model</label><input type="text" name="model" class="form-control" value="<?php echo e($product['model']); ?>"></div>
                    <div class="col-md-3"><label class="form-label">Brand</label><input type="text" name="brand" class="form-control" value="<?php echo e($product['brand']); ?>"></div>
                    <div class="col-md-3"><label class="form-label">Price</label><input type="number" step="0.01" name="price" class="form-control" value="<?php echo $product['price']; ?>"></div>
                    <div class="col-md-3"><label class="form-label">Stock</label><input type="number" name="stock" class="form-control" value="<?php echo $product['stock']; ?>"><div class="form-text">Adjust manually if needed.</div></div>
                    <div class="col-md-3 d-flex align-items-end"><button type="submit" name="update" class="btn btn-primary w-100"><i class="bi bi-save"></i> Update</button></div>
                    <div class="col-md-3 d-flex align-items-end"><a href="product_view.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-info w-100"><i class="bi bi-eye"></i> View</a></div>
                </form>
            </div>
        </div>
    </div>
<?php require_once '../includes/footer.php'; ?>
