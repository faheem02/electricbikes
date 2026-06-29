<?php
require_once '../includes/database.php';
require_once '../includes/auth.php';
requireLogin();
$showSidebar = true; $base_path = '../';

$brand_filter = $_GET['brand'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save'])) {
    $pdo->prepare("INSERT INTO bike_models (brand_id, name) VALUES (?,?)")->execute([$_POST['brand_id'], $_POST['name']]);
    header('Location: bike_models.php' . ($_POST['brand_id'] ? '?brand='.$_POST['brand_id'] : '')); exit;
}
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM bike_models WHERE id=?")->execute([$_GET['delete']]);
    $ref = $brand_filter ? "?brand=$brand_filter" : '';
    header("Location: bike_models.php$ref"); exit;
}
$brands = $pdo->query("SELECT * FROM bike_brands ORDER BY name");
$models = $brand_filter
    ? $pdo->prepare("SELECT m.*, b.name as bname FROM bike_models m JOIN bike_brands b ON m.brand_id=b.id WHERE m.brand_id=? ORDER BY m.name")
    : $pdo->query("SELECT m.*, b.name as bname FROM bike_models m JOIN bike_brands b ON m.brand_id=b.id ORDER BY b.name, m.name");
if ($brand_filter) $models->execute([$brand_filter]);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>
<div class="content">
    <div class="topbar">
        <div><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button><span class="page-title">Bike Models</span></div>
        <div class="user-info"><i class="bi bi-person-circle"></i> <?php echo $_SESSION['full_name'] ?? ''; ?> <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()"><i class="bi bi-moon-fill"></i></button></div>
    </div>
    <div class="main-content">
        <div class="card mb-3">
            <div class="card-header">Add Model</div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <?php echo csrfField(); ?>
                    <div class="col-md-4">
                        <select name="brand_id" class="form-select" required>
                            <option value="">Select Brand</option>
                            <?php $brands->execute(); while ($b = $brands->fetch(PDO::FETCH_ASSOC)): ?>
                                <option value="<?php echo $b['id']; ?>" <?php echo $brand_filter == $b['id'] ? 'selected' : ''; ?>><?php echo e($b['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><input type="text" name="name" class="form-control" placeholder="Model Name" required></div>
                    <div class="col-md-2"><button type="submit" name="save" class="btn btn-primary">Save</button></div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>Models</span>
                <form method="GET" class="d-flex gap-2">
                    <select name="brand" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                        <option value="">All Brands</option>
                        <?php $brands->execute(); while ($b = $brands->fetch(PDO::FETCH_ASSOC)): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo $brand_filter == $b['id'] ? 'selected' : ''; ?>><?php echo e($b['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive p-3">
                    <table class="table table-hover">
                        <thead><tr><th>#</th><th>Brand</th><th>Model</th><th>Variants</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php $i=1; while ($r = $models->fetch(PDO::FETCH_ASSOC)):
                            $vc = $pdo->prepare("SELECT COUNT(*) FROM bike_variants WHERE model_id=?");
                            $vc->execute([$r['id']]);
                        ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo e($r['bname']); ?></td>
                                <td><?php echo e($r['name']); ?></td>
                                <td><a href="bike_stock.php?model=<?php echo $r['id']; ?>"><?php echo $vc->fetchColumn(); ?> Variants</a></td>
                                <td>
                                    <a href="bike_stock.php?model=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-info"><i class="bi bi-box"></i></a>
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
