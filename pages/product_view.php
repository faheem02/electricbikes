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

$total_purchased = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM purchase_items WHERE product_id=?");
$total_purchased->execute([$id]);
$total_purchased = $total_purchased->fetchColumn();

$total_sold = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM sale_items WHERE product_id=?");
$total_sold->execute([$id]);
$total_sold = $total_sold->fetchColumn();

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Product Details</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?></div>
    </div>
    <div class="main-content">
        <div class="row g-3 mb-3">
            <div class="col-md-8">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between">
                        <span><?php echo e($product['name']); ?></span>
                        <div><a href="product_edit.php?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a> <a href="products.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a></div>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered mb-0">
                            <tr><th class="text-muted">Model</th><td><?php echo e($product['model'] ?: '-'); ?></td><th class="text-muted">Brand</th><td><?php echo e($product['brand'] ?: '-'); ?></td></tr>
                            <tr><th class="text-muted">Price</th><td class="fw-semibold"><?php echo formatMoney($product['price']); ?></td><th class="text-muted">Stock</th><td><span class="badge fs-6 bg-<?php echo $product['stock'] > 10 ? 'success' : ($product['stock'] > 0 ? 'warning text-dark' : 'danger'); ?>"><?php echo $product['stock']; ?> units</span></td></tr>
                            <tr><th class="text-muted">Created</th><td><?php echo formatDate($product['created_at']); ?></td><th></th><td></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="row g-2">
                    <div class="col-6"><div class="card text-center p-3 border-success"><div class="text-success fw-bold fs-3">+<?php echo $total_purchased; ?></div><div class="text-muted small">Purchased</div></div></div>
                    <div class="col-6"><div class="card text-center p-3 border-danger"><div class="text-danger fw-bold fs-3">-<?php echo $total_sold; ?></div><div class="text-muted small">Sold</div></div></div>
                    <div class="col-12"><div class="card text-center p-3 border-primary"><div class="text-primary fw-bold fs-3"><?php echo $product['stock']; ?></div><div class="text-muted small">Current Stock</div></div></div>
                </div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card"><div class="card-header">Purchase History</div>
                    <div class="card-body p-0"><div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Date</th><th>Invoice</th><th>Supplier</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead>
                            <tbody>
                            <?php
                            $purchases = $pdo->prepare("SELECT pi.*, p.purchase_date, p.invoice_no, s.name as supplier_name FROM purchase_items pi JOIN purchases p ON pi.purchase_id=p.id LEFT JOIN suppliers s ON p.supplier_id=s.id WHERE pi.product_id=? ORDER BY p.purchase_date DESC");
                            $purchases->execute([$id]);
                            if ($purchases->rowCount() > 0):
                                while ($row = $purchases->fetch(PDO::FETCH_ASSOC)):
                            ?>
                                <tr><td><?php echo formatDate($row['purchase_date']); ?></td><td><?php echo e($row['invoice_no']); ?></td><td><?php echo e($row['supplier_name']); ?></td><td class="text-success fw-semibold">+<?php echo $row['qty']; ?></td><td><?php echo formatMoney($row['rate']); ?></td><td><?php echo formatMoney($row['amount']); ?></td></tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">No records.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card"><div class="card-header">Sale History</div>
                    <div class="card-body p-0"><div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Date</th><th>Invoice</th><th>Customer</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead>
                            <tbody>
                            <?php
                            $sales = $pdo->prepare("SELECT si.*, s.sale_date, s.invoice_no, c.name as customer_name FROM sale_items si JOIN sales s ON si.sale_id=s.id LEFT JOIN customers c ON s.customer_id=c.id WHERE si.product_id=? ORDER BY s.sale_date DESC");
                            $sales->execute([$id]);
                            if ($sales->rowCount() > 0):
                                while ($row = $sales->fetch(PDO::FETCH_ASSOC)):
                            ?>
                                <tr><td><?php echo formatDate($row['sale_date']); ?></td><td><?php echo e($row['invoice_no']); ?></td><td><?php echo e($row['customer_name']); ?></td><td class="text-danger fw-semibold">-<?php echo $row['qty']; ?></td><td><?php echo formatMoney($row['rate']); ?></td><td><?php echo formatMoney($row['amount']); ?></td></tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="6" class="text-center text-muted py-3">No records.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div></div>
                </div>
            </div>
        </div>
    </div>
<?php require_once '../includes/footer.php'; ?>
