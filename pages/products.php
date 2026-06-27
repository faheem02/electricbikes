<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$_GET['delete']]);
    header('Location: products.php'); exit;
}
$result = $pdo->query("SELECT * FROM products ORDER BY name");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Products</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name']; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
    </div>
    <div class="main-content">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>All Products</span>
                <a href="product_add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Product</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <table class="table table-hover">
                        <thead><tr><th>#</th><th>Name</th><th>Model</th><th>Brand</th><th>Price</th><th>Stock</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php $i=1; while ($row = $result->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><a href="product_view.php?id=<?php echo $row['id']; ?>" class="text-decoration-none fw-medium"><?php echo e($row['name']); ?></a></td>
                                <td><?php echo e($row['model']); ?></td>
                                <td><?php echo e($row['brand']); ?></td>
                                <td><?php echo formatMoney($row['price']); ?></td>
                                <td><span class="badge <?php echo $row['stock'] > 0 ? 'bg-success' : 'bg-secondary'; ?>"><?php echo $row['stock']; ?></span></td>
                                <td>
                                    <a href="product_view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-eye"></i></a>
                                    <a href="product_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
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
