
<?php
session_start();

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

require_once __DIR__ . '/config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare(
        "SELECT admin_id, username, password, full_name
         FROM admins
         WHERE username = ?
         LIMIT 1"
    );

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();

    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);

        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_name'] = $admin['full_name'];

        header("Location: dashboard.php");
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Apartment Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container min-vh-100 d-flex justify-content-center align-items-center">
        <div class="card shadow p-4 w-100" style="max-width: 420px;">
            <h2 class="text-center mb-4">Apartment Management</h2>
            <p class="text-center text-muted">Administrator Login</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" name="username" id="username"
                           class="form-control" required autocomplete="username">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" name="password" id="password"
                           class="form-control" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn-warning w-100">
                    Log In
                </button>
            </form>
        </div>
    </div>
</body>
</html>
