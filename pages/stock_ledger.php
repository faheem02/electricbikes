<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$search = $_GET['search'] ?? '';

$query = "SELECT s.id, s.chassis_no, s.motor_no, s.battery_serial, s.charger_serial, s.status, s.created_at,
          v.name as vname, m.name as mname, b.name as bname,
          p.invoice_no as purchase_invoice, p.purchase_date,
          sl.invoice_no as sale_invoice, sl.sale_date
          FROM bike_stock s
          JOIN bike_variants v ON s.variant_id=v.id
          JOIN bike_models m ON v.model_id=m.id
          JOIN bike_brands b ON m.brand_id=b.id
          LEFT JOIN purchases p ON s.purchase_id=p.id
          LEFT JOIN sales sl ON s.sale_id=sl.id
          WHERE 1=1";
$params = [];
if ($search) {
    $query .= " AND (s.chassis_no LIKE ? OR s.motor_no LIKE ? OR s.battery_serial LIKE ? OR s.charger_serial LIKE ? OR v.name LIKE ? OR m.name LIKE ? OR b.name LIKE ?)";
    $params = array_fill(0, 7, "%$search%");
}
$query .= " ORDER BY s.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Stock Ledger</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name']; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
    </div>
    <div class="main-content">

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="bi bi-journal-text me-2"></i>Stock Ledger</span>
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search chassis/motor/battery/variant..." value="<?php echo e($search); ?>" style="width:260px;">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
                    <?php if ($search): ?>
                        <a href="stock_ledger.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Chassis</th>
                                <th>Motor</th>
                                <th>Battery</th>
                                <th>Charger</th>
                                <th>Variant</th>
                                <th>Status</th>
                                <th>Source</th>
                                <th>Sale Ref</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $i = 1; while ($r = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td class="text-muted"><?php echo $i++; ?></td>
                                <td class="fw-semibold"><?php echo e($r['chassis_no']); ?></td>
                                <td><?php echo e($r['motor_no'] ?: '-'); ?></td>
                                <td><?php echo e($r['battery_serial'] ?: '-'); ?></td>
                                <td><?php echo e($r['charger_serial'] ?: '-'); ?></td>
                                <td><?php echo e($r['bname'] . ' ' . $r['mname'] . ' ' . $r['vname']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $r['status']=='in_stock'?'success':($r['status']=='sold'?'secondary':($r['status']=='booked'?'warning text-dark':'danger')); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $r['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($r['purchase_invoice']): ?>
                                        <span class="text-info small"><i class="bi bi-cart-plus"></i> <?php echo e($r['purchase_invoice']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">Direct</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($r['sale_invoice']): ?>
                                        <span class="text-secondary small"><i class="bi bi-cart-check"></i> <?php echo e($r['sale_invoice']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?php echo formatDate($r['created_at']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
<?php require_once '../includes/footer.php'; ?>
