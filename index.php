<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';
requireLogin();
$showSidebar = true;
$base_path = '';

// Stats
$todaySales = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE sale_date = CURDATE()")->fetchColumn();
$monthSales = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE MONTH(sale_date) = MONTH(CURDATE()) AND YEAR(sale_date) = YEAR(CURDATE())")->fetchColumn();
$bikesInStock = $pdo->query("SELECT COUNT(*) FROM bike_stock WHERE status = 'in_stock'")->fetchColumn();
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$totalSuppliers = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();

// Chart data - last 12 months sales
$chartLabels = []; $chartData = [];
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $chartLabels[] = date('M Y', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?");
    $stmt->execute([$m]);
    $chartData[] = $stmt->fetchColumn();
}
$chartLabelsJson = json_encode($chartLabels);
$chartDataJson = json_encode($chartData);

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div>
            <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
            <span class="page-title">Dashboard</span>
        </div>
        <div class="user-info">
            <i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?>
            <span class="badge bg-primary"><?php echo $_SESSION['role_name'] ?? ''; ?></span>
            <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()" title="Toggle Theme"><i class="bi bi-moon-fill"></i></button>
        </div>
    </div>
    <div class="main-content">
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card" style="background: linear-gradient(135deg, #A04657, #7f3544);">
                    <div class="d-flex justify-content-between">
                        <div><div class="number"><?php echo formatMoney($todaySales); ?></div><div class="label">Today's Sales</div></div>
                        <i class="bi bi-cart-check"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="background: linear-gradient(135deg, #4e73df, #224abe);">
                    <div class="d-flex justify-content-between">
                        <div><div class="number"><?php echo formatMoney($monthSales); ?></div><div class="label">Monthly Sales</div></div>
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="background: linear-gradient(135deg, #1cc88a, #13855c);">
                    <div class="d-flex justify-content-between">
                        <div><div class="number"><?php echo $bikesInStock; ?></div><div class="label">Bikes in Stock</div></div>
                        <i class="bi bi-bicycle"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="background: linear-gradient(135deg, #36b9cc, #258391);">
                    <div class="d-flex justify-content-between">
                        <div><div class="number"><?php echo $totalCustomers; ?></div><div class="label">Total Customers</div></div>
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card" style="background: linear-gradient(135deg, #6f42c1, #553098);">
                    <div class="d-flex justify-content-between">
                        <div><div class="number"><?php echo $totalSuppliers; ?></div><div class="label">Suppliers</div></div>
                        <i class="bi bi-truck"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><i class="bi bi-bar-chart-line me-2"></i>Monthly Sales Trend</div>
                    <div class="card-body">
                        <canvas id="salesChart" height="280"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header"><i class="bi bi-pie-chart me-2"></i>Sales by Type</div>
                    <div class="card-body">
                        <canvas id="typeChart" height="240"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-2">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Recent Sales</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Invoice</th><th>Customer</th><th>Date</th><th>Type</th><th>Amount</th></tr></thead>
                                <tbody>
                                <?php
                                $rs = $pdo->query("SELECT s.*, c.name as cname FROM sales s LEFT JOIN customers c ON s.customer_id=c.id ORDER BY s.id DESC LIMIT 5");
                                while ($r = $rs->fetch(PDO::FETCH_ASSOC)):
                                ?>
                                    <tr>
                                        <td><?php echo e($r['invoice_no']); ?></td>
                                        <td><?php echo e($r['cname']); ?></td>
                                        <td><?php echo formatDate($r['sale_date']); ?></td>
                                        <td><span class="badge bg-<?php echo $r['sale_type']=='cash'?'success':'info'; ?>"><?php echo ucfirst($r['sale_type']); ?></span></td>
                                        <td><?php echo formatMoney($r['total_amount']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

<script>
const ctx1 = document.getElementById('salesChart').getContext('2d');
new Chart(ctx1, {
    type: 'bar',
    data: {
        labels: <?php echo $chartLabelsJson; ?>,
        datasets: [{
            label: 'Sales',
            data: <?php echo $chartDataJson; ?>,
            backgroundColor: 'rgba(160,70,87,0.6)',
            borderColor: '#A04657',
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
            x: { grid: { display: false } }
        }
    }
});

<?php
$cashSales = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE sale_type='cash'")->fetchColumn();
$bookSales = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE sale_type='booking'")->fetchColumn();
?>
const ctx2 = document.getElementById('typeChart').getContext('2d');
new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: ['Cash', 'Booking'],
        datasets: [{
            data: [<?php echo "$cashSales, $bookSales"; ?>],
            backgroundColor: ['#1cc88a', '#f6c23e', '#36b9cc'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>
<?php require_once 'includes/footer.php'; ?>
