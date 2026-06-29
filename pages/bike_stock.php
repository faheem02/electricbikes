<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

if (isset($_GET['delete_variant'])) {
    $pdo->prepare("DELETE FROM bike_variants WHERE id=?")->execute([$_GET['delete_variant']]);
    header('Location: bike_stock.php'); exit;
}

// Summary counts
$totalInStock = $pdo->query("SELECT COUNT(*) FROM bike_stock WHERE status='in_stock'")->fetchColumn();
$totalSold = $pdo->query("SELECT COUNT(*) FROM bike_stock WHERE status='sold'")->fetchColumn();
$totalBooked = $pdo->query("SELECT COUNT(*) FROM bike_stock WHERE status='booked'")->fetchColumn();
$totalDamaged = $pdo->query("SELECT COUNT(*) FROM bike_stock WHERE status='damaged'")->fetchColumn();
$totalAll = $totalInStock + $totalSold + $totalBooked + $totalDamaged;

// Stock grouped by brand > model > variant
$summary = $pdo->query("
    SELECT 
        b.id as brand_id, b.name as brand_name,
        m.id as model_id, m.name as model_name,
        v.id as variant_id, v.name as variant_name, v.color, v.purchase_price, v.sale_price,
        SUM(CASE WHEN s.status='in_stock' THEN 1 ELSE 0 END) as in_stock,
        SUM(CASE WHEN s.status='sold' THEN 1 ELSE 0 END) as sold,
        SUM(CASE WHEN s.status='booked' THEN 1 ELSE 0 END) as booked,
        SUM(CASE WHEN s.status='damaged' THEN 1 ELSE 0 END) as damaged,
        COUNT(s.id) as total
    FROM bike_variants v
    JOIN bike_models m ON v.model_id=m.id
    JOIN bike_brands b ON m.brand_id=b.id
    LEFT JOIN bike_stock s ON s.variant_id=v.id
    GROUP BY v.id
    ORDER BY b.name, m.name, v.name
");

// Group by brand > model in PHP
$brands = [];
while ($r = $summary->fetch(PDO::FETCH_ASSOC)) {
    $brands[$r['brand_id']]['name'] = $r['brand_name'];
    $brands[$r['brand_id']]['models'][$r['model_id']]['name'] = $r['model_name'];
    $brands[$r['brand_id']]['models'][$r['model_id']]['variants'][] = $r;
}

// Stock units
$st = $pdo->query("SELECT s.*, v.name as vname, m.name as mname, b.name as bname FROM bike_stock s JOIN bike_variants v ON s.variant_id=v.id JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id ORDER BY s.id DESC LIMIT 500");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Bike Stock</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
    </div>
    <div class="main-content">

        <!-- Summary Cards -->
        <div class="row g-3 mb-3">
            <div class="col-md-3 col-6">
                <div class="stat-card" style="background: linear-gradient(135deg, #1cc88a, #13855c);">
                    <div class="d-flex justify-content-between">
                        <div><div class="number"><?php echo $totalInStock; ?></div><div class="label">In Stock</div></div>
                        <i class="bi bi-box-seam"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card" style="background: linear-gradient(135deg, #4e73df, #224abe);">
                    <div class="d-flex justify-content-between">
                        <div><div class="number"><?php echo $totalSold; ?></div><div class="label">Sold</div></div>
                        <i class="bi bi-cart-check"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card" style="background: linear-gradient(135deg, #f6c23e, #dda20a);">
                    <div class="d-flex justify-content-between">
                        <div><div class="number"><?php echo $totalBooked; ?></div><div class="label">Booked</div></div>
                        <i class="bi bi-calendar-check"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card" style="background: linear-gradient(135deg, #e74a3b, #be2617);">
                    <div class="d-flex justify-content-between">
                        <div><div class="number"><?php echo $totalDamaged; ?></div><div class="label">Damaged</div></div>
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock Summary Grouped by Brand / Model -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-bar-chart-steps me-2"></i>Stock Summary</span>
                <span class="badge bg-secondary fs-6">Total: <?php echo $totalAll; ?> units</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($brands)): ?>
                    <div class="text-center text-muted py-4">No stock data available.</div>
                <?php else: ?>
                    <?php foreach ($brands as $brandId => $brand): ?>
                        <?php $brandTotalInStock = 0; $brandTotal = 0; ?>
                        <table class="table table-hover mb-0 border-top" data-skip-dt="true">
                            <thead class="table-light">
                                <tr>
                                    <th colspan="2" class="fw-bold fs-6 text-primary py-3"><?php echo e($brand['name']); ?></th>
                                    <th class="text-center text-success">In Stock</th>
                                    <th class="text-center text-secondary">Sold</th>
                                    <th class="text-center text-warning">Booked</th>
                                    <th class="text-center text-danger">Damaged</th>
                                    <th class="text-center">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($brand['models'] as $modelId => $model): ?>
                                    <?php $modelTotalInStock = 0; $modelTotal = 0; ?>
                                    <tr class="table-secondary">
                                        <td colspan="7" class="fw-semibold py-2"><?php echo e($model['name']); ?></td>
                                    </tr>
                                    <?php foreach ($model['variants'] as $v): ?>
                                        <?php $modelTotalInStock += $v['in_stock']; $modelTotal += $v['total']; ?>
                                        <tr>
                                            <td style="width:30px;"></td>
                                            <td><span class="fw-medium"><?php echo e($v['variant_name']); ?></span> <?php echo $v['color'] ? '<span class="badge bg-light text-muted border">' . e($v['color']) . '</span>' : ''; ?></td>
                                            <td class="text-center"><span class="badge bg-success"><?php echo $v['in_stock']; ?></span></td>
                                            <td class="text-center"><?php echo $v['sold'] ? '<span class="badge bg-secondary">' . $v['sold'] . '</span>' : '-'; ?></td>
                                            <td class="text-center"><?php echo $v['booked'] ? '<span class="badge bg-warning text-dark">' . $v['booked'] . '</span>' : '-'; ?></td>
                                            <td class="text-center"><?php echo $v['damaged'] ? '<span class="badge bg-danger">' . $v['damaged'] . '</span>' : '-'; ?></td>
                                            <td class="text-center fw-semibold"><?php echo $v['total']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-light">
                                        <td colspan="2" class="text-end pe-3 fw-semibold text-muted small"><?php echo e($model['name']); ?> Total</td>
                                        <td class="text-center fw-bold text-success"><?php echo $modelTotalInStock; ?></td>
                                        <td class="text-center text-secondary"><?php echo $modelTotal - $modelTotalInStock > 0 ? $modelTotal - $modelTotalInStock : '-'; ?></td>
                                        <td></td>
                                        <td></td>
                                        <td class="text-center fw-bold"><?php echo $modelTotal; ?></td>
                                    </tr>
                                    <?php $brandTotalInStock += $modelTotalInStock; $brandTotal += $modelTotal; ?>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td colspan="2" class="text-primary"><?php echo e($brand['name']); ?> Total</td>
                                    <td class="text-center text-success"><?php echo $brandTotalInStock; ?></td>
                                    <td class="text-center text-secondary"><?php echo $brandTotal - $brandTotalInStock > 0 ? $brandTotal - $brandTotalInStock : '-'; ?></td>
                                    <td></td>
                                    <td></td>
                                    <td class="text-center"><?php echo $brandTotal; ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stock Units -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="bi bi-box-seam me-2"></i>Stock Units</span>
                <a href="stock_entry.php" class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i> Add New</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead><tr><th>Chassis</th><th>Motor</th><th>Battery</th><th>Charger</th><th>Variant</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php while ($r = $st->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo e($r['chassis_no']); ?></td>
                                <td><?php echo e($r['motor_no']); ?></td>
                                <td><?php echo e($r['battery_serial']); ?></td>
                                <td><?php echo e($r['charger_serial'] ?: '-'); ?></td>
                                <td><?php echo e($r['bname'] . ' ' . $r['mname'] . ' ' . $r['vname']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $r['status']=='in_stock'?'success':($r['status']=='sold'?'secondary':($r['status']=='booked'?'warning text-dark':'danger')); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $r['status'])); ?>
                                    </span>
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
