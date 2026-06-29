<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save'])) {
    foreach ($_POST['setting'] as $key => $value) {
        $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
        $stmt->execute([$key, $value, $value]);
    }
    logActivity($pdo, 'Settings Updated');
    $success = 'Settings saved successfully!';
}

$settings = $pdo->query("SELECT * FROM settings");
$settingsMap = [];
while ($s = $settings->fetch(PDO::FETCH_ASSOC)) {
    $settingsMap[$s['key']] = $s['value'];
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Settings</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?></div>
    </div>
    <div class="main-content">
        <div class="card">
            <div class="card-header">Company Settings</div>
            <div class="card-body">
                <?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="setting[company_name]" class="form-control" value="<?php echo e($settingsMap['company_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="setting[company_phone]" class="form-control" value="<?php echo e($settingsMap['company_phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Currency</label>
                            <input type="text" name="setting[currency]" class="form-control" value="<?php echo e($settingsMap['currency'] ?? 'PKR'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Address</label>
                            <textarea name="setting[company_address]" class="form-control" rows="2"><?php echo e($settingsMap['company_address'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Invoice Prefix</label>
                            <input type="text" name="setting[invoice_prefix]" class="form-control" value="<?php echo e($settingsMap['invoice_prefix'] ?? 'INV-'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tax Rate (%)</label>
                            <input type="number" step="0.01" name="setting[tax_rate]" class="form-control" value="<?php echo e($settingsMap['tax_rate'] ?? '0'); ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" name="save" class="btn btn-primary"><i class="bi bi-save"></i> Save Settings</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php require_once '../includes/footer.php'; ?>
