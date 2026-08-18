<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

if (isset($_GET['delete_variant'])) {
    $pdo->prepare("DELETE FROM bike_variants WHERE id=?")->execute([$_GET['delete_variant']]);
    header('Location: bike_stock.php'); exit;
}

// Print view
if (isset($_GET['print'])) {
    require_once __DIR__ . '/../includes/company.php';
    $summaryP = $pdo->query("
        SELECT 
            b.id as brand_id, b.name as brand_name,
            m.id as model_id, m.name as model_name,
            v.id as variant_id, v.name as variant_name, v.color, v.purchase_price, v.sale_price,
            SUM(CASE WHEN s.status='ordered' THEN 1 ELSE 0 END) as ordered,
            SUM(CASE WHEN s.status='in_stock' THEN 1 ELSE 0 END) as in_stock,
            SUM(CASE WHEN s.status='sold' THEN 1 ELSE 0 END) as sold,
            SUM(CASE WHEN s.status='booked' THEN 1 ELSE 0 END) as booked,
            SUM(CASE WHEN s.status='damaged' THEN 1 ELSE 0 END) as damaged,
            COUNT(s.id) as total
        FROM bike_variants v
        JOIN bike_models m ON v.model_id=m.id
        JOIN bike_brands b ON m.brand_id=b.id
        LEFT JOIN bike_stock s ON s.variant_id=v.id
        GROUP BY v.id ORDER BY b.name, m.name, v.name
    ");
    $pBrands = [];
    while ($r = $summaryP->fetch(PDO::FETCH_ASSOC)) {
        $pBrands[$r['brand_id']]['name'] = $r['brand_name'];
        $pBrands[$r['brand_id']]['models'][$r['model_id']]['name'] = $r['model_name'];
        $pBrands[$r['brand_id']]['models'][$r['model_id']]['variants'][] = $r;
    }
    ?>
    <!DOCTYPE html><html lang="en"><head><title>Stock Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { font-family:'Poppins',sans-serif; margin:0; padding:0; box-sizing:border-box; }
        body { background:#fff; }
        .print-box { width:100%; }
        .header { text-align:center; border-bottom:2px solid #095D3B; padding-bottom:12px; margin-bottom:12px; }
        .header h1 { color:#095D3B; font-size:20px; margin:0; }
        .header .meta-line { margin:2px 0; font-size:11px; color:#666; }
        .header .meta-line i { color:#095D3B; margin-right:4px; width:12px; text-align:center; }
        .info { text-align:right; margin-bottom:10px; font-size:11px; color:#555; }
        table { width:100%; border-collapse:collapse; font-size:10px; margin-bottom:12px; }
        th, td { padding:4px 6px; text-align:left; border-bottom:1px solid #ddd; }
        th { background:#095D3B; color:#fff; font-weight:600; font-size:9px; text-transform:uppercase; }
        .text-center { text-align:center; }
        .fw-bold { font-weight:700; }
        .brand-row td { background:#e8f3ee; font-weight:700; font-size:11px; color:#095D3B; }
        .model-row td { background:#f5f5f5; font-weight:600; font-size:10px; }
        .total-row td { background:#f0f0f0; font-weight:600; }
        .grand-row td { background:#095D3B; color:#fff; font-weight:700; font-size:11px; }
        .footer { text-align:center; margin-top:15px; color:#888; font-size:10px; border-top:1px solid #eee; padding-top:10px; }
        .no-print { text-align:center; margin-top:15px; }
        .no-print button { display:inline-block; padding:8px 20px; margin:0 5px; border-radius:4px; font-size:13px; cursor:pointer; border:none; }
        .btn-primary { background:#095D3B; color:#fff; }
        .btn-secondary { background:#6c757d; color:#fff; }
        @media print { body { padding:0; background:#fff; } .print-box { box-shadow:none; } .no-print { display:none; } }
    </style></head><body>
    <div class="print-box">
        <?php $pt = 'Stock Report'; include '../includes/print_header.php'; ?>
        <?php foreach ($pBrands as $brandId => $brand): ?>
            <table>
                <tr class="brand-row"><td colspan="8"><?php echo e($brand['name']); ?></td></tr>
                <tr><th>Model</th><th>Variant</th><th>Color</th><th class="text-center">Ordered</th><th class="text-center">In Stock</th><th class="text-center">Sold</th><th class="text-center">Booked</th><th class="text-center">Damaged</th></tr>
                <?php foreach ($brand['models'] as $modelId => $model): ?>
                    <?php foreach ($model['variants'] as $i => $v): ?>
                    <tr>
                        <td><?php echo $i === 0 ? e($model['name']) : ''; ?></td>
                        <td><?php echo e($v['variant_name']); ?></td>
                        <td><?php echo e($v['color'] ?: '-'); ?></td>
                        <td class="text-center"><?php echo $v['ordered'] ?: '-'; ?></td>
                        <td class="text-center"><?php echo $v['in_stock']; ?></td>
                        <td class="text-center"><?php echo $v['sold'] ?: '-'; ?></td>
                        <td class="text-center"><?php echo $v['booked'] ?: '-'; ?></td>
                        <td class="text-center"><?php echo $v['damaged'] ?: '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <?php
                    $bOrd = array_sum(array_map(function($m){return array_sum(array_column($m['variants'],'ordered'));},$brand['models']));
                    $bIn = array_sum(array_map(function($m){return array_sum(array_column($m['variants'],'in_stock'));},$brand['models']));
                    $bSold = array_sum(array_map(function($m){return array_sum(array_column($m['variants'],'sold'));},$brand['models']));
                    $bBook = array_sum(array_map(function($m){return array_sum(array_column($m['variants'],'booked'));},$brand['models']));
                    $bDmg = array_sum(array_map(function($m){return array_sum(array_column($m['variants'],'damaged'));},$brand['models']));
                    $bTotal = $bOrd + $bIn + $bSold + $bBook + $bDmg;
                ?>
                <tr class="total-row"><td colspan="3"><?php echo e($brand['name']); ?> Total</td><td class="text-center"><?php echo $bOrd ?: '-'; ?></td><td class="text-center"><?php echo $bIn; ?></td><td class="text-center"><?php echo $bSold ?: '-'; ?></td><td class="text-center"><?php echo $bBook ?: '-'; ?></td><td class="text-center"><?php echo $bDmg ?: '-'; ?></td></tr>
            </table>
        <?php endforeach; ?>
        <div class="footer">Generated on <?php echo date('d-m-Y H:i'); ?></div>
        <div class="no-print"><button onclick="window.print()" class="btn-primary"><i class="bi bi-printer"></i> Print</button> <button onclick="window.close()" class="btn-secondary">Close</button></div>
    </div>
    </body></html>
    <?php exit;
}

// Stock grouped by brand > model > variant
$summary = $pdo->query("
    SELECT 
        b.id as brand_id, b.name as brand_name,
        m.id as model_id, m.name as model_name,
        v.id as variant_id, v.name as variant_name, v.color, v.purchase_price, v.sale_price,
        SUM(CASE WHEN s.status='ordered' THEN 1 ELSE 0 END) as ordered,
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

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Bike Stock</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
    </div>
    <div class="main-content">

        <!-- Stock Summary Grouped by Brand / Model -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="bi bi-bar-chart-steps me-2"></i>Stock Summary</span>
                <div class="d-flex align-items-center gap-3">
                    <a href="?print=1" class="btn btn-outline-dark btn-sm" target="_blank"><i class="bi bi-printer me-1"></i>Print</a>
                    <input type="text" id="stockSearch" class="form-control form-control-sm" placeholder="Search brand / model / variant..." style="width:260px;" onkeyup="filterStock(event)">
                    <span class="badge bg-secondary fs-6">Total: <?php echo $pdo->query("SELECT COUNT(*) FROM bike_stock")->fetchColumn(); ?> units</span>
                </div>
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
                                    <th class="text-center text-secondary">Ordered</th>
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
                                        <td colspan="8" class="fw-semibold py-2"><?php echo e($model['name']); ?></td>
                                    </tr>
                                    <?php foreach ($model['variants'] as $v): ?>
                                        <?php $modelTotalInStock += $v['in_stock']; $modelTotal += $v['total']; ?>
                                        <tr>
                                            <td style="width:30px;"></td>
                                            <td><span class="fw-medium"><?php echo e($v['variant_name']); ?></span> <?php echo $v['color'] ? '<span class="badge bg-light text-muted border">' . e($v['color']) . '</span>' : ''; ?></td>
                                            <td class="text-center"><?php echo $v['ordered'] ? '<span class="badge bg-secondary">' . $v['ordered'] . '</span>' : '-'; ?></td>
                                            <td class="text-center"><span class="badge bg-success"><?php echo $v['in_stock']; ?></span></td>
                                            <td class="text-center"><?php echo $v['sold'] ? '<span class="badge bg-primary">' . $v['sold'] . '</span>' : '-'; ?></td>
                                            <td class="text-center"><?php echo $v['booked'] ? '<span class="badge bg-warning text-dark">' . $v['booked'] . '</span>' : '-'; ?></td>
                                            <td class="text-center"><?php echo $v['damaged'] ? '<span class="badge bg-danger">' . $v['damaged'] . '</span>' : '-'; ?></td>
                                            <td class="text-center fw-semibold"><?php echo $v['total']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-light">
                                        <td colspan="2" class="text-end pe-3 fw-semibold text-muted small"><?php echo e($model['name']); ?> Total</td>
                                        <td class="text-center text-secondary"><?php echo array_sum(array_column($model['variants'], 'ordered')) ?: '-'; ?></td>
                                        <td class="text-center fw-bold text-success"><?php echo $modelTotalInStock; ?></td>
                                        <td class="text-center text-primary"><?php echo array_sum(array_column($model['variants'], 'sold')) ?: '-'; ?></td>
                                        <td></td>
                                        <td></td>
                                        <td class="text-center fw-bold"><?php echo $modelTotal; ?></td>
                                    </tr>
                                    <?php $brandTotalInStock += $modelTotalInStock; $brandTotal += $modelTotal; ?>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <?php
                                $bOrdered = array_sum(array_map(function($m){return array_sum(array_column($m['variants'],'ordered'));},$brand['models']));
                                $bSold = array_sum(array_map(function($m){return array_sum(array_column($m['variants'],'sold'));},$brand['models']));
                                $bBooked = array_sum(array_map(function($m){return array_sum(array_column($m['variants'],'booked'));},$brand['models']));
                                $bDamaged = array_sum(array_map(function($m){return array_sum(array_column($m['variants'],'damaged'));},$brand['models']));
                                ?>
                                <tr class="fw-bold">
                                    <td colspan="2" class="text-primary"><?php echo e($brand['name']); ?> Total</td>
                                    <td class="text-center text-secondary"><?php echo $bOrdered ?: '-'; ?></td>
                                    <td class="text-center text-success"><?php echo $brandTotalInStock; ?></td>
                                    <td class="text-center text-primary"><?php echo $bSold ?: '-'; ?></td>
                                    <td class="text-center text-warning"><?php echo $bBooked ?: '-'; ?></td>
                                    <td class="text-center text-danger"><?php echo $bDamaged ?: '-'; ?></td>
                                    <td class="text-center"><?php echo $brandTotal; ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
<script>
function filterStock(e) {
    const q = e.target.value.toLowerCase().trim();
    document.querySelectorAll('.table[data-skip-dt="true"]').forEach(tbl => {
        let visible = false;
        tbl.querySelectorAll('tbody > tr').forEach(tr => {
            if (tr.classList.contains('table-secondary')) {
                // model header row
                const match = !q || tr.textContent.toLowerCase().includes(q);
                tr.style.display = match ? '' : 'none';
                tr._matched = match;
            } else if (tr.classList.contains('table-light')) {
                // model total row
                tr.style.display = tr._prevSibling?._matched ? '' : 'none';
            } else {
                // variant row
                const match = !q || tr.textContent.toLowerCase().includes(q);
                tr.style.display = match ? '' : 'none';
                tr._matched = match;
                // update prev reference
                let prev = tr.previousElementSibling;
                while (prev && (prev.classList.contains('table-light') || prev.classList.contains('table-secondary'))) {
                    prev = prev.previousElementSibling;
                }
                if (prev) prev._matched = match;
                // also propagate to model header
                let modelRow = tr.previousElementSibling;
                while (modelRow && !modelRow.classList.contains('table-secondary')) {
                    modelRow = modelRow.previousElementSibling;
                }
                if (modelRow && match) modelRow._matched = true;
            }
            if (tr.style.display !== 'none') visible = true;
        });
        tbl.style.display = visible ? '' : 'none';
    });
}
</script>
<?php require_once '../includes/footer.php'; ?>
