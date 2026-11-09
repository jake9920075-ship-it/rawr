
<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    require_once __DIR__ . '/config/database.php';

    $username = 'admin';
    $password = 'Admin@12345';
    $fullName = 'System Administrator';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO admins (username, password, full_name)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
         password = VALUES(password),
         full_name = VALUES(full_name)"
    );

    $stmt->bind_param("sss", $username, $hashedPassword, $fullName);
    $stmt->execute();

    echo "SUCCESS: Admin account created or updated.";
    echo "<br>Username: admin";
    echo "<br>Password: Admin@12345";

} catch (Throwable $e) {
    echo "ERROR: " . htmlspecialchars($e->getMessage());
}
?>
