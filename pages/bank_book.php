<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$from = $_GET['from_date'] ?? date('Y-m-01');
$to = $_GET['to_date'] ?? date('Y-m-t');

$rows = $pdo->prepare("SELECT * FROM bank_book WHERE date BETWEEN ? AND ? ORDER BY date, id");
$rows->execute([$from, $to]);

$balance = 0;
$all = $pdo->query("SELECT * FROM bank_book ORDER BY date, id");
while ($r = $all->fetch(PDO::FETCH_ASSOC)) $balance += $r['type'] === 'in' ? $r['amount'] : -$r['amount'];

$openingBalance = 0;
$before = $pdo->prepare("SELECT * FROM bank_book WHERE date < ? ORDER BY date, id");
$before->execute([$from]);
while ($r = $before->fetch(PDO::FETCH_ASSOC)) $openingBalance += $r['type'] === 'in' ? $r['amount'] : -$r['amount'];

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Bank Book</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?></div>
    </div>
    <div class="main-content">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <span>Bank Book</span>
                <span class="fw-bold">Closing Balance: <?php echo formatMoney($balance); ?></span>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 mb-3">
                    <div class="col-md-4"><input type="date" name="from_date" class="form-control" value="<?php echo $from; ?>"></div>
                    <div class="col-md-4"><input type="date" name="to_date" class="form-control" value="<?php echo $to; ?>"></div>
                    <div class="col-md-4"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover" data-skip-dt>
                        <thead><tr><th>Date</th><th>Description</th><th>Bank In</th><th>Bank Out</th><th>Balance</th></tr></thead>
                        <tbody>
                            <tr><td colspan="4" class="text-muted">Opening Balance</td><td class="fw-semibold"><?php echo formatMoney($openingBalance); ?></td></tr>
                            <?php $run = $openingBalance; while ($r = $rows->fetch(PDO::FETCH_ASSOC)): $run += $r['type'] === 'in' ? $r['amount'] : -$r['amount']; ?>
                            <tr>
                                <td><?php echo $r['date']; ?></td>
                                <td><?php echo e($r['description']); ?></td>
                                <td><?php echo $r['type'] === 'in' ? formatMoney($r['amount']) : '-'; ?></td>
                                <td><?php echo $r['type'] === 'out' ? formatMoney($r['amount']) : '-'; ?></td>
                                <td class="fw-semibold"><?php echo formatMoney($run); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php require_once '../includes/footer.php'; ?>
