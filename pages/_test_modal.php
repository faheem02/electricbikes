<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
$_SESSION['user_id'] = 1;
$_SESSION['role_name'] = 'super_admin';
$_SESSION['full_name'] = 'Admin';
$showSidebar = true; $base_path = '../';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
$id = $_GET['id'] ?? 0;
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Test Modal</span></div>
        <span class="user-info"><i class="bi bi-person-circle"></i> Admin</span>
    </div>
    <div class="main-content">
        <div class="modal fade show d-block" id="detailsModal" tabindex="-1">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Purchase Details</h5>
                        <button type="button" class="btn-close"></button>
                    </div>
                    <div class="modal-body" id="detailsBody">
                        <?php include 'purchase_details.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
