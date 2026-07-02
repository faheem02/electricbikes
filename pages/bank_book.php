<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$from = $_GET['from_date'] ?? date('Y-m-01');
$to = $_GET['to_date'] ?? date('Y-m-t');

$stmt = $pdo->prepare("SELECT * FROM bank_book WHERE date BETWEEN ? AND ? ORDER BY date, id");
$stmt->execute([$from, $to]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$balance = 0;
$all = $pdo->query("SELECT * FROM bank_book ORDER BY date, id");
while ($r = $all->fetch(PDO::FETCH_ASSOC)) $balance += $r['type'] === 'in' ? $r['amount'] : -$r['amount'];

$openingBalance = 0;
$before = $pdo->prepare("SELECT * FROM bank_book WHERE date < ? ORDER BY date, id");
$before->execute([$from]);
while ($r = $before->fetch(PDO::FETCH_ASSOC)) $openingBalance += $r['type'] === 'in' ? $r['amount'] : -$r['amount'];

// Print view
if (isset($_GET['print'])) {
    ?><!DOCTYPE html><html lang="en"><head><title>Bank Book</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family:'Poppins',sans-serif; margin:0; padding:0; box-sizing:border-box; }
        body { padding:40px; background:#f5f5f5; }
        .print-box { max-width:900px; margin:auto; background:#fff; border-radius:8px; padding:40px; box-shadow:0 2px 10px rgba(0,0,0,.1); }
        .header { text-align:center; border-bottom:2px solid #A04657; padding-bottom:20px; margin-bottom:20px; }
        .header h1 { color:#A04657; font-size:24px; margin:0; }
        .header p { color:#888; margin:5px 0 0; font-size:13px; }
        .info { display:flex; justify-content:space-between; margin-bottom:15px; font-size:13px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { padding:7px 10px; text-align:left; border-bottom:1px solid #ddd; }
        th { background:#A04657; color:#fff; font-weight:600; font-size:11px; text-transform:uppercase; }
        .text-end { text-align:right; }
        .fw-bold { font-weight:700; }
        .text-muted { color:#888; }
        .text-success { color:#28a745; }
        .opening-row td { background:#f8f9fa; font-weight:600; }
        .footer { text-align:center; margin-top:30px; color:#888; font-size:13px; border-top:1px solid #eee; padding-top:20px; }
        .no-print { text-align:center; margin-top:20px; }
        .no-print button { display:inline-block; padding:10px 24px; margin:0 5px; border-radius:4px; font-size:14px; cursor:pointer; border:none; }
        .btn-primary { background:#A04657; color:#fff; }
        .btn-secondary { background:#6c757d; color:#fff; }
        @media print { body { padding:20px; background:#fff; } .print-box { box-shadow:none; padding:20px; } .no-print { display:none; } }
    </style></head><body>
    <div class="print-box">
        <div class="header"><h1><?php echo e(getSetting($pdo, 'company_name') ?: 'Electric Bikes Showroom'); ?></h1><p>Bank Book</p></div>
        <div class="info">
            <span>Period: <?php echo $from; ?> — <?php echo $to; ?></span>
            <span class="fw-bold">Closing Balance: <?php echo formatMoney($balance); ?></span>
        </div>
        <table>
            <tr><th>Date</th><th>Description</th><th class="text-end">Bank In</th><th class="text-end">Bank Out</th><th class="text-end">Balance</th></tr>
            <tr class="opening-row"><td colspan="4" class="text-muted">Opening Balance</td><td class="text-end fw-bold"><?php echo formatMoney($openingBalance); ?></td></tr>
            <?php $run = $openingBalance; foreach ($rows as $r): $run += $r['type'] === 'in' ? $r['amount'] : -$r['amount']; ?>
            <tr>
                <td><?php echo $r['date']; ?></td>
                <td><?php echo e($r['description']); ?></td>
                <td class="text-end text-success"><?php echo $r['type'] === 'in' ? formatMoney($r['amount']) : '-'; ?></td>
                <td class="text-end"><?php echo $r['type'] === 'out' ? formatMoney($r['amount']) : '-'; ?></td>
                <td class="text-end fw-bold"><?php echo formatMoney($run); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <div class="footer">Generated on <?php echo date('d-m-Y H:i'); ?></div>
        <div class="no-print"><button onclick="window.print()" class="btn-primary"><i class="bi bi-printer"></i> Print</button> <button onclick="window.close()" class="btn-secondary">Close</button></div>
    </div>
    </body></html>
    <?php exit;
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Bank Book</span></div>
        <div class="d-flex align-items-center gap-2">
            <a href="?print=1&from_date=<?php echo urlencode($from); ?>&to_date=<?php echo urlencode($to); ?>" class="btn btn-outline-dark btn-sm" target="_blank"><i class="bi bi-printer me-1"></i>Print</a>
            <span class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?></span>
        </div>
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
                            <?php $run = $openingBalance; foreach ($rows as $r): $run += $r['type'] === 'in' ? $r['amount'] : -$r['amount']; ?>
                            <tr>
                                <td><?php echo $r['date']; ?></td>
                                <td><?php echo e($r['description']); ?></td>
                                <td><?php echo $r['type'] === 'in' ? formatMoney($r['amount']) : '-'; ?></td>
                                <td><?php echo $r['type'] === 'out' ? formatMoney($r['amount']) : '-'; ?></td>
                                <td class="fw-semibold"><?php echo formatMoney($run); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php require_once '../includes/footer.php'; ?>
