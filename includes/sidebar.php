<?php
$current = basename($_SERVER['PHP_SELF']);
$cp = function($name) use ($current) { return $current === $name ? 'active' : ''; };
$cg = function($names) use ($current) { return in_array($current, (array)$names) ? 'active' : ''; };
$cs = function($names) use ($current) { return in_array($current, (array)$names) ? 'show' : ''; };
?>
<div class="sidebar" id="sidebar">
    <div class="brand">
        <i class="bi bi-bicycle"></i> Electric Bikes
    </div>
    <nav class="nav flex-column mt-1">
        <a class="nav-link <?php echo $cp('index.php'); ?>" href="<?php echo $base_path; ?>index.php">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a class="nav-link <?php echo $cg(['customers.php','customer_ledger.php']); ?>" href="#customersSub" data-bs-toggle="collapse">
            <i class="bi bi-people"></i> Customers <i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <div class="collapse sub-menu <?php echo $cs(['customers.php','customer_ledger.php']); ?>" id="customersSub">
            <a class="nav-link <?php echo $cp('customers.php'); ?>" href="<?php echo $base_path; ?>pages/customers.php">Manage Customers</a>
            <a class="nav-link <?php echo $cp('customer_ledger.php'); ?>" href="<?php echo $base_path; ?>pages/customer_ledger.php">Customer Ledger</a>
        </div>

        <a class="nav-link <?php echo $cg(['suppliers.php','supplier_ledger.php']); ?>" href="#suppliersSub" data-bs-toggle="collapse">
            <i class="bi bi-truck"></i> Suppliers <i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <div class="collapse sub-menu <?php echo $cs(['suppliers.php','supplier_ledger.php']); ?>" id="suppliersSub">
            <a class="nav-link <?php echo $cp('suppliers.php'); ?>" href="<?php echo $base_path; ?>pages/suppliers.php">Manage Suppliers</a>
            <a class="nav-link <?php echo $cp('supplier_ledger.php'); ?>" href="<?php echo $base_path; ?>pages/supplier_ledger.php">Supplier Ledger</a>
        </div>

        <a class="nav-link <?php echo $cg(['bike_brands.php','bike_models.php']); ?>" href="#bikesSub" data-bs-toggle="collapse">
            <i class="bi bi-bicycle"></i> Bikes <i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <div class="collapse sub-menu <?php echo $cs(['bike_brands.php','bike_models.php']); ?>" id="bikesSub">
            <a class="nav-link <?php echo $cp('bike_brands.php'); ?>" href="<?php echo $base_path; ?>pages/bike_brands.php">Brands</a>
            <a class="nav-link <?php echo $cp('bike_models.php'); ?>" href="<?php echo $base_path; ?>pages/bike_models.php">Models</a>
        </div>

        <a class="nav-link <?php echo $cg(['bike_stock.php','stock_entry.php','stock_ledger.php']); ?>" href="#stockSub" data-bs-toggle="collapse">
            <i class="bi bi-box-seam"></i> Stock <i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <div class="collapse sub-menu <?php echo $cs(['bike_stock.php','stock_entry.php','stock_ledger.php']); ?>" id="stockSub">
            <a class="nav-link <?php echo $cp('bike_stock.php'); ?>" href="<?php echo $base_path; ?>pages/bike_stock.php">Stock View</a>
            <a class="nav-link <?php echo $cp('stock_entry.php'); ?>" href="<?php echo $base_path; ?>pages/stock_entry.php">Stock Entry</a>
            <a class="nav-link <?php echo $cp('stock_ledger.php'); ?>" href="<?php echo $base_path; ?>pages/stock_ledger.php">Stock Ledger</a>
        </div>

        <a class="nav-link <?php echo $cg(['purchases.php','purchase_view.php']); ?>" href="#purchasesSub" data-bs-toggle="collapse">
            <i class="bi bi-cart-plus"></i> Purchases <i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <div class="collapse sub-menu <?php echo $cs(['purchases.php','purchase_view.php']); ?>" id="purchasesSub">
            <a class="nav-link <?php echo $cp('purchases.php'); ?>" href="<?php echo $base_path; ?>pages/purchases.php">New Purchase</a>
            <a class="nav-link <?php echo $cp('purchase_view.php'); ?>" href="<?php echo $base_path; ?>pages/purchase_view.php">Purchase View</a>
        </div>

        <a class="nav-link <?php echo $cg(['sales.php','sale_list.php']); ?>" href="#salesSub" data-bs-toggle="collapse">
            <i class="bi bi-cart-check"></i> Sales <i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <div class="collapse sub-menu <?php echo $cs(['sales.php','sale_list.php']); ?>" id="salesSub">
            <a class="nav-link <?php echo $cp('sales.php'); ?>" href="<?php echo $base_path; ?>pages/sales.php">New Sale</a>
            <a class="nav-link <?php echo $cp('sale_list.php'); ?>" href="<?php echo $base_path; ?>pages/sale_list.php">Sale List</a>
        </div>

        <a class="nav-link <?php echo $cp('expenses.php'); ?>" href="<?php echo $base_path; ?>pages/expenses.php">
            <i class="bi bi-receipt"></i> Expenses
        </a>

        <a class="nav-link <?php echo $cp('cash_book.php'); ?>" href="<?php echo $base_path; ?>pages/cash_book.php">
            <i class="bi bi-cash"></i> Cash Book
        </a>

        <a class="nav-link <?php echo $cp('bank_book.php'); ?>" href="<?php echo $base_path; ?>pages/bank_book.php">
            <i class="bi bi-bank"></i> Bank Book
        </a>

        <a class="nav-link <?php echo $cp('reports.php'); ?>" href="<?php echo $base_path; ?>pages/reports.php">
            <i class="bi bi-file-earmark-bar-graph"></i> Reports
        </a>

        <hr class="m-0 text-white opacity-25">
        <a class="nav-link" href="<?php echo $base_path; ?>logout.php">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </nav>
</div>
