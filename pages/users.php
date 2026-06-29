<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
requireRole(['super_admin', 'admin']);
$showSidebar = true; $base_path = '../';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save'])) {
    $pdo->prepare("INSERT INTO users (username, email, password, full_name, phone, role_id, status, created_at) VALUES (?,?,?,?,?,?,?,CURDATE())")->execute([$_POST['username'], $_POST['email'], $_POST['password'], $_POST['full_name'], $_POST['phone'], $_POST['role_id'], $_POST['status'] ?? 'active']);
    logActivity($pdo, 'Create User', $_POST['username']);
    header('Location: users.php'); exit;
}
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$_GET['delete']]);
    header('Location: users.php'); exit;
}

$roles = $pdo->query("SELECT * FROM roles");
$result = $pdo->query("SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id=r.id ORDER BY u.id");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Users</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?></div>
    </div>
    <div class="main-content">
        <div class="card mb-3">
            <div class="card-header">Add User</div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <?php echo csrfField(); ?>
                    <div class="col-md-4"><input type="text" name="full_name" class="form-control" placeholder="Full Name" required></div>
                    <div class="col-md-4"><input type="text" name="username" class="form-control" placeholder="Username" required></div>
                    <div class="col-md-4"><input type="email" name="email" class="form-control" placeholder="Email"></div>
                    <div class="col-md-3"><input type="password" name="password" class="form-control" placeholder="Password" required></div>
                    <div class="col-md-3"><input type="text" name="phone" class="form-control" placeholder="Phone"></div>
                    <div class="col-md-3">
                        <select name="role_id" class="form-select" required>
                            <option value="">Select Role</option>
                            <?php while ($r = $roles->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $r['id']; ?>"><?php echo ucfirst(str_replace('_', ' ', $r['role_name'])); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-12"><button type="submit" name="save" class="btn btn-primary">Create User</button></div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header">All Users</div>
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <table class="table table-hover">
                        <thead><tr><th>#</th><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php $i=1; while ($r = $result->fetch(PDO::FETCH_ASSOC)): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo e($r['full_name']); ?></td>
                                <td><?php echo e($r['username']); ?></td>
                                <td><span class="badge bg-info text-dark"><?php echo ucfirst(str_replace('_', ' ', $r['role_name'] ?? '')); ?></span></td>
                                <td><span class="badge bg-<?php echo $r['status']=='active'?'success':'danger'; ?>"><?php echo ucfirst($r['status']); ?></span></td>
                                <td><?php echo $r['last_login'] ? date('d-m-Y H:i', strtotime($r['last_login'])) : '-'; ?></td>
                                <td>
                                    <a href="?delete=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete user?')"><i class="bi bi-trash"></i></a>
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
