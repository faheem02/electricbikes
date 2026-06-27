<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
requireRole(['super_admin', 'admin']);
$showSidebar = true; $base_path = '../';

$result = $pdo->query("SELECT a.*, u.full_name FROM activity_logs a LEFT JOIN users u ON a.user_id=u.id ORDER BY a.id DESC LIMIT 500");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Activity Logs</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name']; ?></div>
    </div>
    <div class="main-content">
        <div class="card">
            <div class="card-header">Activity Logs</div>
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <table class="table table-hover">
                        <thead><tr><th>Date/Time</th><th>User</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
                        <tbody>
                        <?php while ($r = $result->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo date('d-m-Y H:i', strtotime($r['created_at'])); ?></td>
                                <td><?php echo e($r['full_name'] ?? 'System'); ?></td>
                                <td><span class="badge bg-secondary"><?php echo e($r['action']); ?></span></td>
                                <td><?php echo e($r['description']); ?></td>
                                <td><?php echo e($r['ip_address']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php require_once '../includes/footer.php'; ?>
