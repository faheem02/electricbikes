<?php
$host = 'localhost';
$dbname = 'electricbikes';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$tables = [
    "CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_name VARCHAR(50) NOT NULL UNIQUE,
        permissions LONGTEXT
    )",

    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100),
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        role_id INT,
        status ENUM('active','inactive') DEFAULT 'active',
        last_login DATETIME,
        created_at DATE,
        FOREIGN KEY (role_id) REFERENCES roles(id)
    )",

    "CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        father_name VARCHAR(100),
        cnic VARCHAR(15),
        mobile VARCHAR(20),
        address TEXT,
        city VARCHAR(50),
        reference VARCHAR(100),
        notes TEXT,
        opening_balance DECIMAL(12,2) DEFAULT 0.00,
        created_at DATE
    )",

    "CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        contact_person VARCHAR(100),
        phone VARCHAR(20),
        address TEXT,
        opening_balance DECIMAL(12,2) DEFAULT 0.00,
        created_at DATE
    )",

    "CREATE TABLE IF NOT EXISTS bike_brands (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL
    )",

    "CREATE TABLE IF NOT EXISTS bike_models (
        id INT AUTO_INCREMENT PRIMARY KEY,
        brand_id INT,
        name VARCHAR(100) NOT NULL,
        FOREIGN KEY (brand_id) REFERENCES bike_brands(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS bike_variants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        model_id INT,
        name VARCHAR(100) NOT NULL,
        color VARCHAR(50),
        purchase_price DECIMAL(12,2) DEFAULT 0.00,
        sale_price DECIMAL(12,2) DEFAULT 0.00,
        FOREIGN KEY (model_id) REFERENCES bike_models(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS bike_stock (
        id INT AUTO_INCREMENT PRIMARY KEY,
        variant_id INT,
        chassis_no VARCHAR(50) UNIQUE,
        motor_no VARCHAR(50) UNIQUE,
        battery_serial VARCHAR(50) UNIQUE,
        charger_serial VARCHAR(50) UNIQUE,
        status ENUM('in_stock','sold','booked','damaged') DEFAULT 'in_stock',
        purchase_id INT,
        sale_id INT,
        created_at DATE,
        FOREIGN KEY (variant_id) REFERENCES bike_variants(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS purchases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT,
        invoice_no VARCHAR(50),
        purchase_date DATE,
        total_amount DECIMAL(12,2) DEFAULT 0.00,
        expenses DECIMAL(12,2) DEFAULT 0.00,
        paid_amount DECIMAL(12,2) DEFAULT 0.00,
        payment_status ENUM('paid','partial','unpaid') DEFAULT 'unpaid',
        status ENUM('ordered','partial','completed') DEFAULT 'ordered',
        notes TEXT,
        created_at DATE,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS purchase_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        purchase_id INT,
        variant_id INT,
        qty INT DEFAULT 0,
        cost_price DECIMAL(12,2) DEFAULT 0.00,
        total DECIMAL(12,2) DEFAULT 0.00,
        FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
        FOREIGN KEY (variant_id) REFERENCES bike_variants(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_no VARCHAR(50) NOT NULL,
        customer_id INT,
        sale_date DATE,
        sale_type ENUM('cash','installment','booking') DEFAULT 'cash',
        total_amount DECIMAL(12,2) DEFAULT 0.00,
        discount DECIMAL(12,2) DEFAULT 0.00,
        down_payment DECIMAL(12,2) DEFAULT 0.00,
        remaining_amount DECIMAL(12,2) DEFAULT 0.00,
        payment_status ENUM('paid','partial','unpaid') DEFAULT 'unpaid',
        notes TEXT,
        created_at DATE,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS sale_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT,
        stock_id INT,
        sale_price DECIMAL(12,2) DEFAULT 0.00,
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
        FOREIGN KEY (stock_id) REFERENCES bike_stock(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS installments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT,
        total_amount DECIMAL(12,2) DEFAULT 0.00,
        down_payment DECIMAL(12,2) DEFAULT 0.00,
        monthly_amount DECIMAL(12,2) DEFAULT 0.00,
        duration INT DEFAULT 0,
        start_date DATE,
        status ENUM('active','completed','defaulted') DEFAULT 'active',
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS installment_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        installment_id INT,
        customer_id INT,
        amount DECIMAL(12,2) DEFAULT 0.00,
        payment_date DATE,
        due_date DATE,
        penalty DECIMAL(12,2) DEFAULT 0.00,
        notes TEXT,
        status ENUM('paid','pending','overdue') DEFAULT 'pending',
        FOREIGN KEY (installment_id) REFERENCES installments(id) ON DELETE CASCADE,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(50),
        amount DECIMAL(12,2) DEFAULT 0.00,
        description TEXT,
        date DATE,
        paid_by VARCHAR(100),
        created_at DATE
    )",

    "CREATE TABLE IF NOT EXISTS cash_book (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE,
        description VARCHAR(255),
        type ENUM('in','out') DEFAULT 'in',
        amount DECIMAL(12,2) DEFAULT 0.00,
        balance DECIMAL(12,2) DEFAULT 0.00
    )",

    "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100),
        description TEXT,
        ip_address VARCHAR(45),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        model VARCHAR(100),
        brand VARCHAR(100),
        price DECIMAL(12,2) DEFAULT 0.00,
        stock INT DEFAULT 0,
        created_at DATE
    )",

    "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        `key` VARCHAR(100) NOT NULL UNIQUE,
        `value` TEXT
    )",

    "CREATE TABLE IF NOT EXISTS customer_ledger (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT,
        date DATE,
        description VARCHAR(255),
        debit DECIMAL(12,2) DEFAULT 0.00,
        credit DECIMAL(12,2) DEFAULT 0.00,
        balance DECIMAL(12,2) DEFAULT 0.00,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS supplier_ledger (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT,
        date DATE,
        description VARCHAR(255),
        debit DECIMAL(12,2) DEFAULT 0.00,
        credit DECIMAL(12,2) DEFAULT 0.00,
        balance DECIMAL(12,2) DEFAULT 0.00,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
    )"
];

foreach ($tables as $sql) {
    $pdo->exec($sql);
}

// Migrations: add missing columns to existing tables (for DBs created before these columns existed)
// Note: Use VARCHAR for ENUM in ALTER to avoid "column already exists" with different ENUM members
$colCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
$colAdd = function($table, $column, $definition) use ($pdo, $colCheck, $dbname) {
    $colCheck->execute([$dbname, $table, $column]);
    if ($colCheck->fetchColumn() == 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
};
$colAdd('users', 'email', 'VARCHAR(100) AFTER username');
$colAdd('users', 'phone', 'VARCHAR(20) AFTER full_name');
$colAdd('users', 'role_id', 'INT AFTER phone');
$colAdd('users', 'status', "VARCHAR(20) DEFAULT 'active' AFTER role_id");
$colAdd('users', 'last_login', 'DATETIME AFTER status');
$colAdd('customers', 'father_name', 'VARCHAR(100) AFTER name');
$colAdd('customers', 'cnic', 'VARCHAR(15) AFTER father_name');
$colAdd('customers', 'mobile', 'VARCHAR(20) AFTER cnic');
$colAdd('customers', 'city', 'VARCHAR(50) AFTER address');
$colAdd('customers', 'reference', 'VARCHAR(100) AFTER city');
$colAdd('customers', 'notes', 'TEXT AFTER reference');
$colAdd('suppliers', 'contact_person', 'VARCHAR(100) AFTER name');
$colAdd('bike_stock', 'charger_serial', 'VARCHAR(100) DEFAULT NULL');
$colAdd('bike_stock', 'purchase_price', 'DECIMAL(12,2) DEFAULT 0.00');
$colAdd('bike_stock', 'sale_price', 'DECIMAL(12,2) DEFAULT 0.00');
    $colAdd('purchases', 'created_at', 'DATE');
    $colAdd('purchases', 'expenses', 'DECIMAL(12,2) DEFAULT 0.00');
$colAdd('purchases', 'paid_amount', 'DECIMAL(12,2) DEFAULT 0.00');
$colAdd('purchases', 'payment_status', "VARCHAR(20) DEFAULT 'unpaid'");
$colAdd('purchases', 'notes', 'TEXT');
$colAdd('purchases', 'status', "VARCHAR(20) DEFAULT 'ordered'");
    $colAdd('sale_items', 'stock_id', 'INT');
    $colAdd('sale_items', 'sale_price', 'DECIMAL(12,2) DEFAULT 0.00');
    $colAdd('sales', 'created_at', 'DATE');
    $colAdd('sales', 'sale_type', "VARCHAR(20) DEFAULT 'cash'");
$colAdd('sales', 'discount', 'DECIMAL(12,2) DEFAULT 0.00');
$colAdd('sales', 'down_payment', 'DECIMAL(12,2) DEFAULT 0.00');
$colAdd('sales', 'remaining_amount', 'DECIMAL(12,2) DEFAULT 0.00');
$colAdd('sales', 'payment_status', "VARCHAR(20) DEFAULT 'unpaid'");
$colAdd('sales', 'notes', 'TEXT');
$colAdd('installments', 'monthly_amount', 'DECIMAL(12,2) DEFAULT 0.00');
$colAdd('installments', 'duration', 'INT DEFAULT 0');
$colAdd('installments', 'start_date', 'DATE');
$colAdd('installments', 'status', "VARCHAR(20) DEFAULT 'active'");
$colAdd('installments', 'down_payment', 'DECIMAL(12,2) DEFAULT 0.00');
$colAdd('installment_payments', 'penalty', 'DECIMAL(12,2) DEFAULT 0.00');
$colAdd('installment_payments', 'notes', 'TEXT');
$colAdd('installment_payments', 'status', "VARCHAR(20) DEFAULT 'pending'");
$colAdd('expenses', 'paid_by', 'VARCHAR(100) AFTER date');

// Add unique indexes (skip if duplicates exist)
$idxAdd = function($table, $index, $column) use ($pdo, $dbname) {
    $chk = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?");
    $chk->execute([$dbname, $table, $index]);
    if ($chk->fetchColumn() == 0) {
        try { $pdo->exec("ALTER TABLE `$table` ADD UNIQUE INDEX `$index` (`$column`)"); } catch (PDOException $e) {}
    }
};
$idxAdd('bike_stock', 'idx_motor_no', 'motor_no');
$idxAdd('bike_stock', 'idx_battery_serial', 'battery_serial');
$idxAdd('bike_stock', 'idx_charger_serial', 'charger_serial');

// Default roles
$stmt = $pdo->query("SELECT COUNT(*) FROM roles");
if ($stmt->fetchColumn() == 0) {
    $pdo->exec("INSERT INTO roles (role_name) VALUES ('super_admin'), ('admin'), ('salesman'), ('cashier'), ('service_manager')");
}

// Default admin user (password: admin@123)
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
if ($stmt->fetchColumn() == 0) {
    $pdo->exec("INSERT INTO users (username, password, full_name, role_id, status, created_at) 
                VALUES ('admin', 'admin@123', 'Super Admin', 1, 'active', CURDATE())");
} else {
    $pdo->exec("UPDATE users SET role_id = 1 WHERE username = 'admin' AND (role_id IS NULL OR role_id = 0)");
}

// Default settings
$defaults = [
    ['company_name', 'Electric Bikes Showroom'],
    ['company_phone', '0312-1234567'],
    ['company_address', 'Main Road, City'],
    ['currency', 'PKR'],
    ['invoice_prefix', 'INV-'],
    ['tax_rate', '0'],
];
$stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE `key` = ?");
$ins = $pdo->prepare("INSERT IGNORE INTO settings (`key`, `value`) VALUES (?, ?)");
foreach ($defaults as $d) {
    $stmt->execute([$d[0]]);
    if ($stmt->fetchColumn() == 0) {
        $ins->execute($d);
    }
}
?>
