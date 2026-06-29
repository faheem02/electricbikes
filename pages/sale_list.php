<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

// Complete delivery for booking sales
if (isset($_GET['deliver'])) {
    $sid = $_GET['deliver'];
    $pdo->prepare("UPDATE bike_stock SET status='sold' WHERE sale_id=? AND status='booked'")->execute([$sid]);
    logActivity($pdo, 'Complete Delivery', "Sale #$sid delivery completed");
    header('Location: sale_list.php'); exit;
}

$result = $pdo->query("SELECT s.*, c.name as cname,
    GROUP_CONCAT(DISTINCT m.name SEPARATOR '<br>') as bikes,
    CASE WHEN s.sale_type='cash' THEN s.total_amount ELSE s.down_payment END as paid_amount
    FROM sales s
    LEFT JOIN customers c ON s.customer_id=c.id
    LEFT JOIN sale_items si ON si.sale_id=s.id
    LEFT JOIN bike_stock st ON si.stock_id=st.id
    LEFT JOIN bike_variants v ON st.variant_id=v.id
    LEFT JOIN bike_models m ON v.model_id=m.id
    LEFT JOIN bike_brands b ON m.brand_id=b.id
    GROUP BY s.id ORDER BY s.id DESC");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Sale List</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
    </div>
    <div class="main-content">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-cart-check me-2"></i>Sales History</span>
                <a href="sales.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> New Sale</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <table class="table table-hover">
                        <thead><tr><th>Invoice</th><th>Customer</th><th>Bikes</th><th>Date</th><th>Type</th><th>Amount</th><th>Paid</th><th>Remaining</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php while ($r = $result->fetch(PDO::FETCH_ASSOC)):
                            $hasBooked = $pdo->prepare("SELECT COUNT(*) FROM bike_stock WHERE sale_id=? AND status='booked'");
                            $hasBooked->execute([$r['id']]);
                            $hasBooked = $hasBooked->fetchColumn() > 0;
                        ?>
                            <tr>
                                <td><?php echo e($r['invoice_no']); ?></td>
                                <td><?php echo e($r['cname']); ?></td>
                                <td style="font-size:0.85rem;"><?php echo $r['bikes'] ?: '-'; ?></td>
                                <td><?php echo formatDate($r['sale_date']); ?></td>
                                <td><span class="badge bg-<?php echo $r['sale_type']=='cash'?'success':($r['sale_type']=='installment'?'warning text-dark':'info'); ?>"><?php echo ucfirst($r['sale_type']); ?></span></td>
                                <td><?php echo formatMoney($r['total_amount']); ?></td>
                                <td><?php echo formatMoney($r['paid_amount']); ?></td>
                                <td><?php echo $r['remaining_amount'] > 0 ? formatMoney($r['remaining_amount']) : '-'; ?></td>
                                <td><span class="badge bg-<?php echo $r['payment_status']=='paid'?'success':($r['payment_status']=='partial'?'warning text-dark':'danger'); ?>"><?php echo ucfirst($r['payment_status']); ?></span></td>
                                <td>
                                    <a href="sales.php?print=<?php echo $r['id']; ?>" target="_blank" class="btn btn-sm btn-outline-info" title="Print"><i class="bi bi-printer"></i></a>
                                    <?php if ($hasBooked): ?>
                                        <a href="sale_list.php?deliver=<?php echo $r['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Mark this booking as delivered?')" title="Complete Delivery"><i class="bi bi-check-circle"></i></a>
                                    <?php endif; ?>
                                    <a href="sales.php?delete=<?php echo $r['id']; ?>&redirect=list" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete sale?')" title="Delete"><i class="bi bi-trash"></i></a>
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
