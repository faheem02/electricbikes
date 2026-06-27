<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id=r.id WHERE u.username = ? AND u.status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $password === $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['role_id'] = $user['role_id'];

            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            logActivity($pdo, 'Login', 'User logged in');

            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password!';
        }
    } else {
        $error = 'Please enter username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Electric Bikes ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --primary: #A04657; --primary-dark: #7f3544; }
        * { font-family: 'Poppins', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #A04657 0%, #7f3544 50%, #5a2530 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .login-card .brand { text-align: center; margin-bottom: 30px; }
        .login-card .brand i { font-size: 48px; color: var(--primary); }
        .login-card .brand h2 { font-weight: 700; color: #333; margin-top: 10px; font-size: 22px; }
        .login-card .brand p { color: #888; font-size: 13px; margin: 0; }
        .form-control { border-radius: 8px; padding: 12px 15px; border: 1px solid #ddd; font-size: 14px; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 0.2rem rgba(160,70,87,0.15); }
        .btn-login {
            background: var(--primary); border: none; border-radius: 8px; padding: 12px;
            font-weight: 600; font-size: 16px; color: #fff; width: 100%; cursor: pointer; transition: 0.3s;
        }
        .btn-login:hover { background: var(--primary-dark); }
        .alert { border-radius: 8px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand">
            <i class="bi bi-bicycle"></i>
            <h2>Electric Bikes ERP</h2>
            <p>Sale, Purchase & Service Management</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" placeholder="Enter username" required>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter password" required>
            </div>
            <button type="submit" class="btn-login"><i class="bi bi-box-arrow-in-right me-2"></i>Login</button>
            <div class="text-center mt-3 text-muted small">
                Default: admin / admin@123
            </div>
        </form>
    </div>
</body>
</html>
