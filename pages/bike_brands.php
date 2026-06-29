<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save'])) {
    $pdo->prepare("INSERT INTO bike_brands (name) VALUES (?)")->execute([$_POST['name']]);
    header('Location: bike_brands.php'); exit;
}
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM bike_brands WHERE id=?")->execute([$_GET['delete']]);
    header('Location: bike_brands.php'); exit;
}
$result = $pdo->query("SELECT * FROM bike_brands ORDER BY name");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Bike Brands</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
    </div>
    <div class="main-content">
        <div class="card mb-3">
            <div class="card-header">Add Brand</div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <?php echo csrfField(); ?>
                    <div class="col-md-6"><input type="text" name="name" class="form-control" placeholder="Brand Name" required></div>
                    <div class="col-md-3"><button type="submit" name="save" class="btn btn-primary">Save</button></div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header">All Brands</div>
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <table class="table table-hover">
                        <thead><tr><th>#</th><th>Name</th><th>Models</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php $i=1; while ($r = $result->fetch(PDO::FETCH_ASSOC)):
                            $mc = $pdo->prepare("SELECT COUNT(*) FROM bike_models WHERE brand_id=?");
                            $mc->execute([$r['id']]);
                        ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo e($r['name']); ?></td>
                                <td><a href="bike_models.php?brand=<?php echo $r['id']; ?>"><?php echo $mc->fetchColumn(); ?> Models</a></td>
                                <td>
                                    <a href="?delete=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')"><i class="bi bi-trash"></i></a>
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
