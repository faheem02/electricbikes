<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$from = $_GET['from_date'] ?? date('Y-m-01');
$to = $_GET['to_date'] ?? date('Y-m-t');
$type = $_GET['type'] ?? 'daily_sales';

$result = null;
$title = '';

switch ($type) {
    case 'daily_sales':
        $title = 'Daily Sales Report';
        
        // 1. Overall summary metrics for date range
        $salesSumStmt = $pdo->prepare("SELECT 
            COUNT(id) as total_invoices,
            COALESCE(SUM(total_amount), 0) as gross_sales,
            COALESCE(SUM(discount), 0) as total_discounts,
            COALESCE(SUM(total_amount - discount), 0) as net_sales,
            COALESCE(SUM(down_payment), 0) as total_paid,
            COALESCE(SUM(remaining_amount), 0) as total_remaining,
            COUNT(DISTINCT sale_date) as active_days
        FROM sales WHERE sale_date BETWEEN ? AND ?");
        $salesSumStmt->execute([$from, $to]);
        $salesSum = $salesSumStmt->fetch(PDO::FETCH_ASSOC);

        $totalInvoices = intval($salesSum['total_invoices'] ?? 0);
        $grossSales = floatval($salesSum['gross_sales'] ?? 0);
        $totalDiscounts = floatval($salesSum['total_discounts'] ?? 0);
        $netSales = floatval($salesSum['net_sales'] ?? 0);
        $totalPaid = floatval($salesSum['total_paid'] ?? 0);
        $totalRemaining = floatval($salesSum['total_remaining'] ?? 0);
        $activeDays = max(1, intval($salesSum['active_days'] ?? 1));
        $avgDailySales = $netSales / $activeDays;

        // Total bikes count sold
        $bikeCountStmt = $pdo->prepare("SELECT COUNT(si.id) FROM sale_items si JOIN sales s ON si.sale_id=s.id WHERE s.sale_date BETWEEN ? AND ?");
        $bikeCountStmt->execute([$from, $to]);
        $totalBikesSold = intval($bikeCountStmt->fetchColumn());

        // 2. Day-by-Day breakdown
        $dayStmt = $pdo->prepare("SELECT 
            s.sale_date,
            COUNT(s.id) as invoices_count,
            (SELECT COUNT(si.id) FROM sale_items si JOIN sales s2 ON si.sale_id=s2.id WHERE s2.sale_date = s.sale_date) as bikes_count,
            COALESCE(SUM(s.total_amount), 0) as gross_total,
            COALESCE(SUM(s.discount), 0) as discount_total,
            COALESCE(SUM(s.total_amount - s.discount), 0) as net_total,
            COALESCE(SUM(s.down_payment), 0) as paid_total,
            COALESCE(SUM(s.remaining_amount), 0) as remaining_total
        FROM sales s
        WHERE s.sale_date BETWEEN ? AND ?
        GROUP BY s.sale_date
        ORDER BY s.sale_date DESC");
        $dayStmt->execute([$from, $to]);
        $dailyDays = $dayStmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Invoices detail list for this period
        $invStmt = $pdo->prepare("SELECT 
            s.id, s.invoice_no, s.sale_date, s.sale_type, s.total_amount, s.discount, (s.total_amount - s.discount) as net_amount,
            s.down_payment, s.remaining_amount, s.payment_status, s.payment_method,
            c.name as customer_name, c.mobile as customer_mobile,
            (SELECT GROUP_CONCAT(CONCAT(b.name,' ',m.name,' ',v.name, IF(st.chassis_no IS NOT NULL AND st.chassis_no != '', CONCAT(' [',st.chassis_no,']'), '')) SEPARATOR '<br>')
             FROM sale_items si 
             JOIN bike_stock st ON si.stock_id=st.id 
             JOIN bike_variants v ON st.variant_id=v.id 
             JOIN bike_models m ON v.model_id=m.id 
             JOIN bike_brands b ON m.brand_id=b.id 
             WHERE si.sale_id=s.id) as bike_details
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.sale_date BETWEEN ? AND ?
        ORDER BY s.sale_date DESC, s.id DESC");
        $invStmt->execute([$from, $to]);
        $invoicesList = $invStmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'purchase':
        $title = 'Purchase Report';
        $result = $pdo->prepare("SELECT p.purchase_date, p.invoice_no, s.name as supplier, p.total_amount, p.expenses, p.paid_amount, p.payment_status FROM purchases p LEFT JOIN suppliers s ON p.supplier_id=s.id WHERE p.purchase_date BETWEEN ? AND ? ORDER BY p.purchase_date DESC");
        $result->execute([$from, $to]);
        break;

    case 'stock':
        $title = 'Stock Report';
        $result = $pdo->query("SELECT v.name as variant, m.name as model, b.name as brand, v.color, v.purchase_price, v.sale_price, (SELECT COUNT(*) FROM bike_stock WHERE variant_id=v.id AND status='in_stock') as in_stock, (SELECT COUNT(*) FROM bike_stock WHERE variant_id=v.id AND status='sold') as sold FROM bike_variants v JOIN bike_models m ON v.model_id=m.id JOIN bike_brands b ON m.brand_id=b.id ORDER BY b.name, m.name, v.name");
        break;

    case 'expense':
        $title = 'Expense Report';
        $result = $pdo->prepare("SELECT category, SUM(amount) as total, COUNT(*) as count FROM expenses WHERE date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC");
        $result->execute([$from, $to]);
        break;

    case 'profit':
        $title = 'Profit & Loss Report';
        
        // 1. Sales & Discount summary (uses exact sales and discounts)
        $saleSummaryStmt = $pdo->prepare("SELECT 
            COUNT(*) as total_invoices,
            COALESCE(SUM(total_amount), 0) as gross_sales,
            COALESCE(SUM(discount), 0) as total_discounts,
            COALESCE(SUM(total_amount - discount), 0) as net_sales
            FROM sales WHERE sale_date BETWEEN ? AND ?");
        $saleSummaryStmt->execute([$from, $to]);
        $saleSummary = $saleSummaryStmt->fetch(PDO::FETCH_ASSOC);

        $totalInvoices = intval($saleSummary['total_invoices'] ?? 0);
        $grossSales = floatval($saleSummary['gross_sales'] ?? 0);
        $totalDiscounts = floatval($saleSummary['total_discounts'] ?? 0);
        $netSales = floatval($saleSummary['net_sales'] ?? 0);

        // 2. Cost of Goods Sold (COGS) - exact purchase cost of bikes sold in this date range
        $cogsStmt = $pdo->prepare("SELECT 
            COUNT(si.id) as total_bikes_sold,
            COALESCE(SUM(COALESCE(NULLIF(st.purchase_price, 0), NULLIF(v.purchase_price, 0), 0)), 0) as total_cogs,
            COALESCE(SUM(si.sale_price), 0) as total_items_sale
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            JOIN bike_stock st ON si.stock_id = st.id
            LEFT JOIN bike_variants v ON st.variant_id = v.id
            WHERE s.sale_date BETWEEN ? AND ?");
        $cogsStmt->execute([$from, $to]);
        $cogsData = $cogsStmt->fetch(PDO::FETCH_ASSOC);

        $totalBikesSold = intval($cogsData['total_bikes_sold'] ?? 0);
        $totalCogs = floatval($cogsData['total_cogs'] ?? 0);

        // 3. Gross Profit = Net Sales - COGS
        $grossProfit = $netSales - $totalCogs;

        // 4. Purchase Expenses (Freight, Bilty, Carriage, Unloading on purchases)
        $purExpStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE (category LIKE '%Purchase%' OR category LIKE '%Freight%' OR category LIKE '%Carriage%' OR category LIKE '%Bilty%' OR description LIKE '%Purchase%') AND date BETWEEN ? AND ?");
        $purExpStmt->execute([$from, $to]);
        $purExpFromTable = floatval($purExpStmt->fetchColumn());

        $purDirectExpStmt = $pdo->prepare("SELECT COALESCE(SUM(expenses), 0) FROM purchases WHERE purchase_date BETWEEN ? AND ?");
        $purDirectExpStmt->execute([$from, $to]);
        $purExpFromPurchases = floatval($purDirectExpStmt->fetchColumn());

        $purchaseExpenses = max($purExpFromTable, $purExpFromPurchases);

        // 5. Operating / Shop Expenses (Rent, utilities, staff salaries, tea, maintenance)
        $allExpStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE date BETWEEN ? AND ?");
        $allExpStmt->execute([$from, $to]);
        $allExpensesSum = floatval($allExpStmt->fetchColumn());
        $operatingExpenses = max(0, $allExpensesSum - $purExpFromTable);

        // Total Combined Expenses
        $totalCombinedExpenses = $purchaseExpenses + $operatingExpenses;

        // 6. Net Profit = Gross Profit - Purchase Expenses - Operating Expenses
        $netProfit = $grossProfit - $totalCombinedExpenses;

        $grossMargin = $netSales > 0 ? ($grossProfit / $netSales) * 100 : 0;
        $netMargin = $netSales > 0 ? ($netProfit / $netSales) * 100 : 0;

        // 7. Detailed itemized sold bikes query with purchase rate & entered sale price
        $soldBikesStmt = $pdo->prepare("SELECT 
            s.id as sale_id,
            s.invoice_no,
            s.sale_date,
            s.sale_type,
            s.discount as invoice_discount,
            s.total_amount as invoice_total,
            s.payment_status,
            c.name as customer_name,
            c.mobile as customer_mobile,
            b.name as brand_name,
            m.name as model_name,
            v.name as variant_name,
            v.color as variant_color,
            st.chassis_no,
            st.motor_no,
            si.sale_price,
            COALESCE(NULLIF(st.purchase_price, 0), NULLIF(v.purchase_price, 0), 0) as purchase_price,
            (si.sale_price - COALESCE(NULLIF(st.purchase_price, 0), NULLIF(v.purchase_price, 0), 0)) as item_profit
        FROM sales s
        JOIN sale_items si ON s.id = si.sale_id
        JOIN bike_stock st ON si.stock_id = st.id
        LEFT JOIN bike_variants v ON st.variant_id = v.id
        LEFT JOIN bike_models m ON v.model_id = m.id
        LEFT JOIN bike_brands b ON m.brand_id = b.id
        LEFT JOIN customers c ON s.customer_id = c.id
        WHERE s.sale_date BETWEEN ? AND ?
        ORDER BY s.sale_date DESC, s.id DESC, si.id ASC");
        $soldBikesStmt->execute([$from, $to]);
        $soldBikes = $soldBikesStmt->fetchAll(PDO::FETCH_ASSOC);
        break;
}

// Print view
if (isset($_GET['print'])) {
    require_once __DIR__ . '/../includes/company.php';
    ?>
    <!DOCTYPE html><html lang="en"><head><title><?php echo e($title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        * { font-family:'Poppins',sans-serif; margin:0; padding:0; box-sizing:border-box; }
        body { background:#fff; color:#222; }
        .print-box { width:100%; }
        .date-range { text-align:center; font-size:10.5px; color:#555; margin-bottom:10px; }
        table { width:100%; border-collapse:collapse; font-size:9.5px; margin-bottom:12px; }
        th, td { padding:5px 6px; text-align:left; border-bottom:1px solid #e0e0e0; }
        th { background:#102E68; color:#fff; font-weight:600; font-size:9px; text-transform:uppercase; }
        .text-end { text-align:right; }
        .text-center { text-align:center; }
        .fw-bold { font-weight:700; }
        .fw-semibold { font-weight:600; }
        .text-success { color:#095D3B !important; }
        .text-danger { color:#c0392b !important; }
        .text-warning { color:#d35400 !important; }
        .text-muted { color:#666 !important; }
        
        .kpi-grid { display:grid; grid-template-columns:repeat(6, 1fr); gap:6px; margin-bottom:12px; }
        .kpi-grid.col-5 { grid-template-columns:repeat(5, 1fr); }
        .kpi-card { padding:7px 5px; border:1px solid #ddd; border-radius:5px; text-align:center; background:#fafafa; }
        .kpi-card .amount { font-size:11px; font-weight:700; }
        .kpi-card .label { font-size:8px; color:#666; text-transform:uppercase; margin-top:2px; }
        .kpi-card .sub { font-size:7.5px; color:#888; margin-top:1px; }

        .summary-box { width:65%; margin-left:auto; border:1px solid #ccc; border-radius:4px; padding:8px; margin-top:10px; font-size:10px; }
        .summary-row { display:flex; justify-content:space-between; padding:3px 0; border-bottom:1px dashed #eee; }
        .summary-row.total { border-top:2px solid #102E68; border-bottom:none; font-weight:700; font-size:11px; padding-top:5px; margin-top:4px; }

        .footer { text-align:center; margin-top:15px; color:#888; font-size:9px; border-top:1px solid #eee; padding-top:8px; }
        .no-print { text-align:center; margin-top:15px; }
        .no-print button { display:inline-block; padding:7px 18px; margin:0 4px; border-radius:4px; font-size:12px; cursor:pointer; border:none; }
        .btn-primary { background:#102E68; color:#fff; }
        .btn-secondary { background:#6c757d; color:#fff; }
        @media print { body { padding:0; background:#fff; } .print-box { box-shadow:none; } .no-print { display:none; } }
    </style></head><body>
    <div class="print-box">
        <?php $pt = $title; include '../includes/print_header.php'; ?>
        <div class="date-range">Report Period: <strong><?php echo formatDate($from); ?></strong> to <strong><?php echo formatDate($to); ?></strong></div>
        
        <?php if ($type == 'daily_sales'): ?>
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="amount"><?php echo formatMoney($grossSales); ?></div>
                    <div class="label">Gross Sales</div>
                    <div class="sub"><?php echo $totalInvoices; ?> Invoices</div>
                </div>
                <div class="kpi-card">
                    <div class="amount text-danger"><?php echo formatMoney($totalDiscounts); ?></div>
                    <div class="label">Discounts</div>
                    <div class="sub">Total Reductions</div>
                </div>
                <div class="kpi-card">
                    <div class="amount text-success"><?php echo formatMoney($netSales); ?></div>
                    <div class="label">Net Sales</div>
                    <div class="sub"><?php echo $totalBikesSold; ?> Bikes Sold</div>
                </div>
                <div class="kpi-card">
                    <div class="amount text-success"><?php echo formatMoney($totalPaid); ?></div>
                    <div class="label">Cash Collected</div>
                    <div class="sub">Paid / Down Payment</div>
                </div>
                <div class="kpi-card">
                    <div class="amount text-danger"><?php echo formatMoney($totalRemaining); ?></div>
                    <div class="label">Remaining Due</div>
                    <div class="sub">Receivables</div>
                </div>
                <div class="kpi-card">
                    <div class="amount" style="color:#2b5876;"><?php echo formatMoney($avgDailySales); ?></div>
                    <div class="label">Daily Average</div>
                    <div class="sub"><?php echo $activeDays; ?> Active Days</div>
                </div>
            </div>

            <div style="font-weight:700; font-size:11px; color:#095D3B; margin:8px 0 4px;">Day-by-Day Sales Summary</div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="text-center">Invoices</th>
                        <th class="text-center">Bikes</th>
                        <th class="text-end">Gross Total</th>
                        <th class="text-end">Discount</th>
                        <th class="text-end">Net Sales</th>
                        <th class="text-end">Cash / Paid</th>
                        <th class="text-end">Remaining Due</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($dailyDays)): foreach ($dailyDays as $d): ?>
                    <tr>
                        <td><strong><?php echo date('d M Y (D)', strtotime($d['sale_date'])); ?></strong></td>
                        <td class="text-center"><?php echo $d['invoices_count']; ?></td>
                        <td class="text-center"><?php echo $d['bikes_count']; ?></td>
                        <td class="text-end"><?php echo formatMoney($d['gross_total']); ?></td>
                        <td class="text-end text-danger"><?php echo $d['discount_total'] > 0 ? '- '.formatMoney($d['discount_total']) : '-'; ?></td>
                        <td class="text-end fw-bold text-success"><?php echo formatMoney($d['net_total']); ?></td>
                        <td class="text-end"><?php echo formatMoney($d['paid_total']); ?></td>
                        <td class="text-end <?php echo $d['remaining_total'] > 0 ? 'text-danger fw-semibold' : ''; ?>"><?php echo formatMoney($d['remaining_total']); ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="8" class="text-center text-muted" style="padding:15px;">No sales found in this date range.</td></tr>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($dailyDays)): ?>
                <tfoot>
                    <tr style="font-weight:700; background:#f0f7f3; border-top:2px solid #095D3B;">
                        <td>Total Summary:</td>
                        <td class="text-center"><?php echo $totalInvoices; ?></td>
                        <td class="text-center"><?php echo $totalBikesSold; ?></td>
                        <td class="text-end"><?php echo formatMoney($grossSales); ?></td>
                        <td class="text-end text-danger"><?php echo formatMoney($totalDiscounts); ?></td>
                        <td class="text-end text-success"><?php echo formatMoney($netSales); ?></td>
                        <td class="text-end"><?php echo formatMoney($totalPaid); ?></td>
                        <td class="text-end text-danger"><?php echo formatMoney($totalRemaining); ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>

            <div style="font-weight:700; font-size:11px; color:#095D3B; margin:12px 0 4px;">Invoices Breakdown</div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Inv #</th>
                        <th>Customer</th>
                        <th>Bikes & Chassis</th>
                        <th>Type</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Due</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($invoicesList)): foreach ($invoicesList as $inv): ?>
                    <tr>
                        <td><?php echo formatDate($inv['sale_date']); ?></td>
                        <td><strong><?php echo e($inv['invoice_no']); ?></strong></td>
                        <td><?php echo e($inv['customer_name'] ?: 'Cash Customer'); ?></td>
                        <td><?php echo $inv['bike_details'] ?: '-'; ?></td>
                        <td><?php echo ucfirst($inv['sale_type']); ?></td>
                        <td class="text-end fw-bold"><?php echo formatMoney($inv['net_amount']); ?></td>
                        <td class="text-end text-success"><?php echo formatMoney($inv['down_payment']); ?></td>
                        <td class="text-end <?php echo $inv['remaining_amount'] > 0 ? 'text-danger' : ''; ?>"><?php echo formatMoney($inv['remaining_amount']); ?></td>
                        <td class="text-center"><?php echo strtoupper($inv['payment_status']); ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="9" class="text-center text-muted" style="padding:10px;">No invoices.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php elseif ($type == 'profit'): ?>
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="amount text-success"><?php echo formatMoney($netSales); ?></div>
                    <div class="label">Net Sales Revenue</div>
                    <div class="sub"><?php echo $totalBikesSold; ?> Bikes Sold</div>
                </div>
                <div class="kpi-card">
                    <div class="amount" style="color:#2b5876;"><?php echo formatMoney($totalCogs); ?></div>
                    <div class="label">Purchase Cost (COGS)</div>
                    <div class="sub">Cost of Sold Units</div>
                </div>
                <div class="kpi-card">
                    <div class="amount text-success"><?php echo formatMoney($grossProfit); ?></div>
                    <div class="label">Gross Profit</div>
                    <div class="sub"><?php echo number_format($grossMargin, 1); ?>% Margin</div>
                </div>
                <div class="kpi-card">
                    <div class="amount text-warning"><?php echo formatMoney($purchaseExpenses); ?></div>
                    <div class="label">Purchase Expenses</div>
                    <div class="sub">Freight / Carriage</div>
                </div>
                <div class="kpi-card">
                    <div class="amount text-danger"><?php echo formatMoney($operatingExpenses); ?></div>
                    <div class="label">Shop Expenses</div>
                    <div class="sub">Rent / Bills / Salaries</div>
                </div>
                <div class="kpi-card">
                    <div class="amount <?php echo $netProfit >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatMoney($netProfit); ?></div>
                    <div class="label">Net Profit</div>
                    <div class="sub"><?php echo number_format($netMargin, 1); ?>% Net Margin</div>
                </div>
            </div>

            <div style="font-weight:700; font-size:11px; color:#095D3B; margin:8px 0 4px;">Itemized Sold Bikes & Profit Breakdown</div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Inv #</th>
                        <th>Customer</th>
                        <th>Bike Model / Variant</th>
                        <th>Chassis #</th>
                        <th class="text-end">Purchase Rate</th>
                        <th class="text-end">Sale Price</th>
                        <th class="text-end">Profit</th>
                        <th class="text-end">Margin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sumCost = 0; $sumSale = 0; $sumProfit = 0;
                    if (!empty($soldBikes)): 
                        foreach ($soldBikes as $b): 
                            $cost = floatval($b['purchase_price']);
                            $sale = floatval($b['sale_price']);
                            $pft = floatval($b['item_profit']);
                            $sumCost += $cost;
                            $sumSale += $sale;
                            $sumProfit += $pft;
                            $margin = $sale > 0 ? ($pft / $sale) * 100 : 0;
                    ?>
                    <tr>
                        <td><?php echo formatDate($b['sale_date']); ?></td>
                        <td><strong><?php echo e($b['invoice_no']); ?></strong></td>
                        <td><?php echo e($b['customer_name'] ?: 'Cash Customer'); ?></td>
                        <td><?php echo e(trim(($b['brand_name'] ?? '') . ' ' . ($b['model_name'] ?? '') . ' ' . ($b['variant_name'] ?? ''))); ?> <?php if(!empty($b['variant_color'])) echo '('.e($b['variant_color']).')'; ?></td>
                        <td><code><?php echo e($b['chassis_no'] ?: '-'); ?></code></td>
                        <td class="text-end"><?php echo formatMoney($cost); ?></td>
                        <td class="text-end fw-semibold"><?php echo formatMoney($sale); ?></td>
                        <td class="text-end fw-bold <?php echo $pft >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatMoney($pft); ?></td>
                        <td class="text-end"><?php echo number_format($margin, 1); ?>%</td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="9" class="text-center text-muted" style="padding:15px;">No bike sales recorded in this date range.</td></tr>
                    <?php endif; ?>
                </tbody>
                <?php if (!empty($soldBikes)): ?>
                <tfoot>
                    <tr style="font-weight:700; background:#f0f7f3; border-top:2px solid #095D3B;">
                        <td colspan="5" class="text-end">Totals (<?php echo count($soldBikes); ?> Bikes):</td>
                        <td class="text-end"><?php echo formatMoney($sumCost); ?></td>
                        <td class="text-end"><?php echo formatMoney($sumSale); ?></td>
                        <td class="text-end text-success"><?php echo formatMoney($sumProfit); ?></td>
                        <td class="text-end"><?php echo $sumSale > 0 ? number_format(($sumProfit / $sumSale) * 100, 1) : '0.0'; ?>%</td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>

            <div class="summary-box">
                <div class="summary-row"><span>Gross Sales Revenue (<?php echo $totalInvoices; ?> Invoices):</span> <strong><?php echo formatMoney($grossSales); ?></strong></div>
                <?php if ($totalDiscounts > 0): ?>
                <div class="summary-row"><span>Less: Sales Discounts:</span> <strong class="text-danger">- <?php echo formatMoney($totalDiscounts); ?></strong></div>
                <?php endif; ?>
                <div class="summary-row"><span>Net Sales Revenue:</span> <strong><?php echo formatMoney($netSales); ?></strong></div>
                <div class="summary-row"><span>Less: Cost of Sold Bikes (COGS):</span> <strong class="text-danger">- <?php echo formatMoney($totalCogs); ?></strong></div>
                <div class="summary-row" style="font-weight:600; color:#095D3B;"><span>Gross Profit:</span> <strong><?php echo formatMoney($grossProfit); ?> (<?php echo number_format($grossMargin, 1); ?>%)</strong></div>
                <?php if ($purchaseExpenses > 0): ?>
                <div class="summary-row"><span>Less: Purchase Expenses (Freight/Carriage):</span> <strong class="text-warning">- <?php echo formatMoney($purchaseExpenses); ?></strong></div>
                <?php endif; ?>
                <div class="summary-row"><span>Less: Shop Operating Expenses:</span> <strong class="text-danger">- <?php echo formatMoney($operatingExpenses); ?></strong></div>
                <div class="summary-row total"><span class="<?php echo $netProfit >= 0 ? 'text-success' : 'text-danger'; ?>">NET PROFIT:</span> <span class="<?php echo $netProfit >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatMoney($netProfit); ?> (<?php echo number_format($netMargin, 1); ?>%)</span></div>
            </div>

        <?php elseif ($type == 'purchase' && $result): ?>
            <table>
                <thead><tr><th>Date</th><th>Invoice</th><th>Supplier</th><th class="text-end">Total Amount</th><th class="text-end">Expenses</th><th class="text-end">Paid Amount</th><th class="text-center">Status</th></tr></thead>
                <tbody><?php while ($r = $result->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr><td><?php echo formatDate($r['purchase_date']); ?></td><td><strong><?php echo e($r['invoice_no']); ?></strong></td><td><?php echo e($r['supplier']); ?></td><td class="text-end fw-semibold"><?php echo formatMoney($r['total_amount']); ?></td><td class="text-end text-warning"><?php echo formatMoney($r['expenses']); ?></td><td class="text-end text-success"><?php echo formatMoney($r['paid_amount']); ?></td><td class="text-center"><?php echo strtoupper($r['payment_status']); ?></td></tr>
                <?php endwhile; ?></tbody>
            </table>
        <?php elseif ($type == 'stock' && $result): ?>
            <table>
                <thead><tr><th>Brand</th><th>Model</th><th>Variant</th><th>Color</th><th class="text-end">Purchase</th><th class="text-end">Sale</th><th class="text-center">In Stock</th><th class="text-center">Sold</th></tr></thead>
                <tbody><?php while ($r = $result->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr><td><?php echo e($r['brand']); ?></td><td><?php echo e($r['model']); ?></td><td><?php echo e($r['variant']); ?></td><td><?php echo e($r['color']); ?></td><td class="text-end"><?php echo formatMoney($r['purchase_price']); ?></td><td class="text-end"><?php echo formatMoney($r['sale_price']); ?></td><td class="text-center"><?php echo $r['in_stock']; ?></td><td class="text-center"><?php echo $r['sold']; ?></td></tr>
                <?php endwhile; ?></tbody>
            </table>
        <?php elseif ($type == 'expense' && $result): ?>
            <table>
                <thead><tr><th>Category</th><th>Count</th><th class="text-end">Total</th></tr></thead>
                <tbody><?php while ($r = $result->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr><td><?php echo e($r['category']); ?></td><td><?php echo $r['count']; ?></td><td class="text-end fw-semibold"><?php echo formatMoney($r['total']); ?></td></tr>
                <?php endwhile; ?></tbody>
            </table>
        <?php endif; ?>
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
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Reports</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?></div>
    </div>
    <div class="main-content">
        <div class="card mb-3 shadow-sm border-0">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Report Type</label>
                        <select name="type" class="form-select">
                            <option value="daily_sales" <?php echo $type=='daily_sales'?'selected':''; ?>>Daily Sales Report</option>
                            <option value="profit" <?php echo $type=='profit'?'selected':''; ?>>Profit & Loss (COGS & Expense Based)</option>
                            <option value="purchase" <?php echo $type=='purchase'?'selected':''; ?>>Purchase Report</option>
                            <option value="stock" <?php echo $type=='stock'?'selected':''; ?>>Stock Inventory Report</option>
                            <option value="expense" <?php echo $type=='expense'?'selected':''; ?>>Expense Report</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">From Date</label>
                        <input type="date" name="from_date" class="form-control" value="<?php echo $from; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">To Date</label>
                        <input type="date" name="to_date" class="form-control" value="<?php echo $to; ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel me-1"></i> Generate Report</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($type == 'daily_sales'): ?>
            <!-- Daily Sales KPI Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #095D3B !important;">
                        <div class="card-body p-3">
                            <div class="text-muted small fw-semibold text-uppercase">Net Sales</div>
                            <div class="fs-5 fw-bold text-success mt-1"><?php echo formatMoney($netSales); ?></div>
                            <div class="small text-muted mt-1"><?php echo $totalInvoices; ?> Invoices (<?php echo $totalBikesSold; ?> Bikes)</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #36b9cc !important;">
                        <div class="card-body p-3">
                            <div class="text-muted small fw-semibold text-uppercase">Gross Sales</div>
                            <div class="fs-5 fw-bold mt-1" style="color:#258391;"><?php echo formatMoney($grossSales); ?></div>
                            <div class="small text-muted mt-1">Before Discount</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #e74a3b !important;">
                        <div class="card-body p-3">
                            <div class="text-muted small fw-semibold text-uppercase">Discounts</div>
                            <div class="fs-5 fw-bold mt-1 text-danger"><?php echo formatMoney($totalDiscounts); ?></div>
                            <div class="small text-muted mt-1">Total Concessions</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #1cc88a !important;">
                        <div class="card-body p-3">
                            <div class="text-muted small fw-semibold text-uppercase">Cash Collected</div>
                            <div class="fs-5 fw-bold mt-1 text-success"><?php echo formatMoney($totalPaid); ?></div>
                            <div class="small text-muted mt-1">Paid / Down Pay</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #f6c23e !important;">
                        <div class="card-body p-3">
                            <div class="text-muted small fw-semibold text-uppercase">Remaining Due</div>
                            <div class="fs-5 fw-bold mt-1 text-warning"><?php echo formatMoney($totalRemaining); ?></div>
                            <div class="small text-muted mt-1">Receivables</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #4e73df !important;">
                        <div class="card-body p-3">
                            <div class="text-muted small fw-semibold text-uppercase">Daily Average</div>
                            <div class="fs-5 fw-bold mt-1" style="color:#2e59d9;"><?php echo formatMoney($avgDailySales); ?></div>
                            <div class="small text-muted mt-1"><?php echo $activeDays; ?> Active Days</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Day by Day Sales Table -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-bold fs-6"><i class="bi bi-calendar-date me-2 text-primary"></i>Day-by-Day Sales Summary</span>
                        <span class="badge bg-primary ms-2"><?php echo count($dailyDays); ?> Days</span>
                    </div>
                    <a href="?print=1&type=<?php echo urlencode($type); ?>&from_date=<?php echo urlencode($from); ?>&to_date=<?php echo urlencode($to); ?>" class="btn btn-outline-dark btn-sm" target="_blank"><i class="bi bi-printer me-1"></i>Print Report</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th class="text-center">Invoices</th>
                                    <th class="text-center">Bikes Sold</th>
                                    <th class="text-end">Gross Sales</th>
                                    <th class="text-end">Discount</th>
                                    <th class="text-end">Net Sales</th>
                                    <th class="text-end">Cash / Paid</th>
                                    <th class="text-end">Remaining Due</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($dailyDays)): foreach ($dailyDays as $d): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo date('d M Y', strtotime($d['sale_date'])); ?></div>
                                        <div class="small text-muted"><?php echo date('l', strtotime($d['sale_date'])); ?></div>
                                    </td>
                                    <td class="text-center"><span class="badge bg-secondary-subtle text-dark border"><?php echo $d['invoices_count']; ?></span></td>
                                    <td class="text-center"><span class="badge bg-primary-subtle text-primary border"><?php echo $d['bikes_count']; ?></span></td>
                                    <td class="text-end"><?php echo formatMoney($d['gross_total']); ?></td>
                                    <td class="text-end text-danger"><?php echo $d['discount_total'] > 0 ? '- ' . formatMoney($d['discount_total']) : '-'; ?></td>
                                    <td class="text-end fw-bold text-success fs-6"><?php echo formatMoney($d['net_total']); ?></td>
                                    <td class="text-end text-success fw-semibold"><?php echo formatMoney($d['paid_total']); ?></td>
                                    <td class="text-end <?php echo $d['remaining_total'] > 0 ? 'text-danger fw-bold' : 'text-muted'; ?>"><?php echo formatMoney($d['remaining_total']); ?></td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No sales recorded in this date range.</td></tr>
                                <?php endif; ?>
                            </tbody>
                            <?php if (!empty($dailyDays)): ?>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td>Total:</td>
                                    <td class="text-center"><?php echo $totalInvoices; ?></td>
                                    <td class="text-center"><?php echo $totalBikesSold; ?></td>
                                    <td class="text-end"><?php echo formatMoney($grossSales); ?></td>
                                    <td class="text-end text-danger"><?php echo formatMoney($totalDiscounts); ?></td>
                                    <td class="text-end text-success fs-6"><?php echo formatMoney($netSales); ?></td>
                                    <td class="text-end text-success"><?php echo formatMoney($totalPaid); ?></td>
                                    <td class="text-end text-danger"><?php echo formatMoney($totalRemaining); ?></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Invoices Detailed List -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <span class="fw-bold fs-6"><i class="bi bi-receipt me-2 text-primary"></i>Invoices Detail List</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Invoice #</th>
                                    <th>Customer</th>
                                    <th>Bikes & Chassis</th>
                                    <th>Type</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Paid</th>
                                    <th class="text-end">Due</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($invoicesList)): foreach ($invoicesList as $idx => $inv): ?>
                                <tr>
                                    <td><?php echo $idx + 1; ?></td>
                                    <td><?php echo formatDate($inv['sale_date']); ?></td>
                                    <td><a href="sales.php?print=<?php echo $inv['id']; ?>" target="_blank" class="fw-bold text-decoration-none"><?php echo e($inv['invoice_no']); ?></a></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo e($inv['customer_name'] ?: 'Cash Customer'); ?></div>
                                        <?php if (!empty($inv['customer_mobile'])): ?><div class="small text-muted"><?php echo e($inv['customer_mobile']); ?></div><?php endif; ?>
                                    </td>
                                    <td class="small"><?php echo $inv['bike_details'] ?: '-'; ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo ucfirst($inv['sale_type']); ?></span></td>
                                    <td class="text-end fw-bold text-dark"><?php echo formatMoney($inv['net_amount']); ?></td>
                                    <td class="text-end text-success fw-semibold"><?php echo formatMoney($inv['down_payment']); ?></td>
                                    <td class="text-end <?php echo $inv['remaining_amount'] > 0 ? 'text-danger fw-bold' : 'text-muted'; ?>"><?php echo formatMoney($inv['remaining_amount']); ?></td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $inv['payment_status']=='paid'?'bg-success':($inv['payment_status']=='partial'?'bg-warning text-dark':'bg-danger'); ?>">
                                            <?php echo strtoupper($inv['payment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="10" class="text-center text-muted py-4">No invoices found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($type == 'profit'): ?>
            <!-- Profit & Loss KPI Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #095D3B !important;">
                        <div class="card-body p-3">
                            <div class="text-muted small fw-semibold text-uppercase">Net Sales Revenue</div>
                            <div class="fs-5 fw-bold text-success mt-1"><?php echo formatMoney($netSales); ?></div>
                            <div class="small text-muted mt-1"><?php echo $totalBikesSold; ?> Bikes Sold</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #4e73df !important;">
                        <div class="card-body p-3">
                            <div class="text-muted small fw-semibold text-uppercase">Purchase Cost (COGS)</div>
                            <div class="fs-5 fw-bold mt-1" style="color:#2e59d9;"><?php echo formatMoney($totalCogs); ?></div>
                            <div class="small text-muted mt-1">Cost of Sold Bikes</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #1cc88a !important;">
                        <div class="card-body p-3">
                            <div class="text-muted small fw-semibold text-uppercase">Gross Profit</div>
                            <div class="fs-5 fw-bold mt-1 text-success"><?php echo formatMoney($grossProfit); ?></div>
                            <div class="small text-muted mt-1"><span class="badge bg-success-subtle text-success border border-success-subtle"><?php echo number_format($grossMargin, 1); ?>% Margin</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #fd7e14 !important;">
                        <div class="card-body p-3">
                            <div class="text-muted small fw-semibold text-uppercase">Purchase Expenses</div>
                            <div class="fs-5 fw-bold mt-1 text-warning"><?php echo formatMoney($purchaseExpenses); ?></div>
                            <div class="small text-muted mt-1">Freight / Carriage</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #e74a3b !important;">
                        <div class="card-body p-3">
                            <div class="text-muted small fw-semibold text-uppercase">Shop Expenses</div>
                            <div class="fs-5 fw-bold mt-1 text-danger"><?php echo formatMoney($operatingExpenses); ?></div>
                            <div class="small text-muted mt-1">Rent / Bills / Staff</div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-2 col-md-4 col-6">
                    <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid <?php echo $netProfit >= 0 ? '#095D3B' : '#e74a3b'; ?> !important;">
                        <div class="card-body p-3">
                            <div class="text-muted small fw-semibold text-uppercase">Net Profit</div>
                            <div class="fs-5 fw-bold mt-1 <?php echo $netProfit >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo formatMoney($netProfit); ?></div>
                            <div class="small mt-1"><span class="badge <?php echo $netProfit >= 0 ? 'bg-success text-white' : 'bg-danger text-white'; ?>"><?php echo number_format($netMargin, 1); ?>% Net Margin</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Itemized Sold Bikes Table -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-bold fs-6"><i class="bi bi-bicycle me-2 text-primary"></i>Sold Bikes & Profit Details</span>
                        <span class="badge bg-primary ms-2"><?php echo count($soldBikes); ?> Bikes Sold</span>
                    </div>
                    <a href="?print=1&type=<?php echo urlencode($type); ?>&from_date=<?php echo urlencode($from); ?>&to_date=<?php echo urlencode($to); ?>" class="btn btn-outline-dark btn-sm" target="_blank"><i class="bi bi-printer me-1"></i>Print Report</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Invoice #</th>
                                    <th>Customer</th>
                                    <th>Bike Model / Variant</th>
                                    <th>Chassis #</th>
                                    <th class="text-end">Purchase Rate</th>
                                    <th class="text-end">Sale Price</th>
                                    <th class="text-end">Profit</th>
                                    <th class="text-end">Margin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $tblCost = 0; $tblSale = 0; $tblProfit = 0;
                                if (!empty($soldBikes)): 
                                    foreach ($soldBikes as $idx => $b): 
                                        $cost = floatval($b['purchase_price']);
                                        $sale = floatval($b['sale_price']);
                                        $pft = floatval($b['item_profit']);
                                        $tblCost += $cost;
                                        $tblSale += $sale;
                                        $tblProfit += $pft;
                                        $margin = $sale > 0 ? ($pft / $sale) * 100 : 0;
                                ?>
                                <tr>
                                    <td><?php echo $idx + 1; ?></td>
                                    <td><?php echo formatDate($b['sale_date']); ?></td>
                                    <td><a href="sales.php?print=<?php echo $b['sale_id']; ?>" target="_blank" class="fw-bold text-decoration-none"><?php echo e($b['invoice_no']); ?></a></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo e($b['customer_name'] ?: 'Cash Customer'); ?></div>
                                        <?php if (!empty($b['customer_mobile'])): ?><div class="small text-muted"><?php echo e($b['customer_mobile']); ?></div><?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo e(trim(($b['brand_name'] ?? '') . ' ' . ($b['model_name'] ?? '') . ' ' . ($b['variant_name'] ?? ''))); ?></strong>
                                        <?php if (!empty($b['variant_color'])): ?><span class="badge bg-light text-dark border ms-1"><?php echo e($b['variant_color']); ?></span><?php endif; ?>
                                    </td>
                                    <td><code><?php echo e($b['chassis_no'] ?: '-'); ?></code></td>
                                    <td class="text-end text-secondary fw-semibold"><?php echo formatMoney($cost); ?></td>
                                    <td class="text-end fw-bold text-dark"><?php echo formatMoney($sale); ?></td>
                                    <td class="text-end fw-bold <?php echo $pft >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo formatMoney($pft); ?>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge <?php echo $margin >= 15 ? 'bg-success-subtle text-success' : ($margin >= 0 ? 'bg-warning-subtle text-warning' : 'bg-danger-subtle text-danger'); ?> border">
                                            <?php echo number_format($margin, 1); ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="10" class="text-center text-muted py-4">No bike sales found in selected date range.</td></tr>
                                <?php endif; ?>
                            </tbody>
                            <?php if (!empty($soldBikes)): ?>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td colspan="6" class="text-end">Total (<?php echo count($soldBikes); ?> Bikes):</td>
                                    <td class="text-end text-secondary"><?php echo formatMoney($tblCost); ?></td>
                                    <td class="text-end text-dark"><?php echo formatMoney($tblSale); ?></td>
                                    <td class="text-end text-success"><?php echo formatMoney($tblProfit); ?></td>
                                    <td class="text-end"><?php echo $tblSale > 0 ? number_format(($tblProfit / $tblSale) * 100, 1) : '0.0'; ?>%</td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Financial Summary Box -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <span class="fw-bold fs-6"><i class="bi bi-file-earmark-spreadsheet me-2 text-primary"></i>Financial Profit & Loss Statement</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-7 mx-auto">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="d-flex justify-content-between py-2 border-bottom"><span>Gross Sales Revenue (<?php echo $totalInvoices; ?> Invoices):</span> <strong><?php echo formatMoney($grossSales); ?></strong></div>
                                <?php if ($totalDiscounts > 0): ?>
                                <div class="d-flex justify-content-between py-2 border-bottom"><span>Less: Sales Discounts:</span> <strong class="text-danger">- <?php echo formatMoney($totalDiscounts); ?></strong></div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between py-2 border-bottom fw-bold text-dark"><span>Net Sales Revenue:</span> <strong><?php echo formatMoney($netSales); ?></strong></div>
                                <div class="d-flex justify-content-between py-2 border-bottom"><span>Less: Cost of Sold Bikes (COGS):</span> <strong class="text-danger">- <?php echo formatMoney($totalCogs); ?></strong></div>
                                <div class="d-flex justify-content-between py-2 border-bottom fw-bold text-success fs-6"><span>Gross Profit:</span> <span><?php echo formatMoney($grossProfit); ?> (<?php echo number_format($grossMargin, 1); ?>%)</span></div>
                                <?php if ($purchaseExpenses > 0): ?>
                                <div class="d-flex justify-content-between py-2 border-bottom"><span>Less: Purchase Expenses (Freight/Carriage):</span> <strong class="text-warning">- <?php echo formatMoney($purchaseExpenses); ?></strong></div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between py-2 border-bottom"><span>Less: Shop Operating Expenses:</span> <strong class="text-danger">- <?php echo formatMoney($operatingExpenses); ?></strong></div>
                                <div class="d-flex justify-content-between pt-3 border-top border-2 border-success fw-bold fs-5 <?php echo $netProfit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <span>NET PROFIT:</span>
                                    <span><?php echo formatMoney($netProfit); ?> (<?php echo number_format($netMargin, 1); ?>%)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold fs-6"><?php echo $title; ?></span>
                    <a href="?print=1&type=<?php echo urlencode($type); ?>&from_date=<?php echo urlencode($from); ?>&to_date=<?php echo urlencode($to); ?>" class="btn btn-outline-dark btn-sm" target="_blank"><i class="bi bi-printer me-1"></i>Print</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive p-3">
                        <?php if ($type == 'purchase' && $result): ?>
                            <table class="table table-hover align-middle">
                                <thead class="table-light"><tr><th>Date</th><th>Invoice</th><th>Supplier</th><th class="text-end">Total Amount</th><th class="text-end">Expenses</th><th class="text-end">Paid Amount</th><th class="text-center">Status</th></tr></thead>
                                <tbody>
                                <?php while ($r = $result->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr><td><?php echo formatDate($r['purchase_date']); ?></td><td><strong><?php echo e($r['invoice_no']); ?></strong></td><td><?php echo e($r['supplier']); ?></td><td class="text-end fw-semibold"><?php echo formatMoney($r['total_amount']); ?></td><td class="text-end text-warning"><?php echo formatMoney($r['expenses']); ?></td><td class="text-end text-success"><?php echo formatMoney($r['paid_amount']); ?></td><td class="text-center"><span class="badge <?php echo $r['payment_status']=='paid'?'bg-success':($r['payment_status']=='partial'?'bg-warning text-dark':'bg-danger'); ?>"><?php echo strtoupper($r['payment_status']); ?></span></td></tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php elseif ($type == 'stock' && $result): ?>
                            <table class="table table-hover align-middle">
                                <thead class="table-light"><tr><th>Brand</th><th>Model</th><th>Variant</th><th>Color</th><th>Purchase Price</th><th>Sale Price</th><th>In Stock</th><th>Sold</th></tr></thead>
                                <tbody>
                                <?php while ($r = $result->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr><td><?php echo e($r['brand']); ?></td><td><?php echo e($r['model']); ?></td><td><?php echo e($r['variant']); ?></td><td><?php echo e($r['color']); ?></td><td><?php echo formatMoney($r['purchase_price']); ?></td><td><?php echo formatMoney($r['sale_price']); ?></td><td><span class="badge bg-success"><?php echo $r['in_stock']; ?></span></td><td><span class="badge bg-secondary"><?php echo $r['sold']; ?></span></td></tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php elseif ($type == 'expense' && $result): ?>
                            <table class="table table-hover align-middle">
                                <thead class="table-light"><tr><th>Category</th><th>Count</th><th>Total</th></tr></thead>
                                <tbody>
                                <?php while ($r = $result->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr><td><span class="badge bg-secondary"><?php echo e($r['category']); ?></span></td><td><?php echo $r['count']; ?></td><td class="text-danger fw-semibold"><?php echo formatMoney($r['total']); ?></td></tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php require_once '../includes/footer.php'; ?>
