
<?php
session_start();

require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    exit('Access denied. Admin login required.');
}

$message = '';
$tenantLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenantId = filter_input(INPUT_POST, 'tenant_id', FILTER_VALIDATE_INT);

    if (!$tenantId || $tenantId < 1) {
        $message = 'Please enter a valid tenant ID.';
    } else {
        $check = $conn->prepare(
            "SELECT tenant_id FROM tenants WHERE tenant_id = ?"
        );
        $check->bind_param("i", $tenantId);
        $check->execute();
        $tenant = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$tenant) {
            $message = 'Tenant not found.';
        } else {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            $insert = $conn->prepare(
                "INSERT INTO tenant_portal_tokens
                 (tenant_id, token_hash, expires_at)
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 180 DAY))"
            );

            $insert->bind_param("is", $tenantId, $tokenHash);

            if ($insert->execute()) {
                $baseUrl = 'http://localhost/ApartmentManagementSystem';
                $tenantLink = $baseUrl . '/tenant_portal.php?token='
                    . urlencode($token);
                $message = 'Tenant link generated successfully.';
            } else {
                $message = 'Unable to generate the tenant link.';
            }

            $insert->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Tenant Link</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f6f8;
            padding: 30px;
        }
        .container {
            max-width: 600px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
        }
        input, button {
            width: 100%;
            padding: 12px;
            margin-top: 10px;
            box-sizing: border-box;
        }
        button {
            background: #f5c400;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }
        .link {
            overflow-wrap: anywhere;
            margin-top: 15px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Generate Tenant Link</h2>

    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>

    <form method="POST">
        <label for="tenant_id">Tenant ID</label>
        <input type="number" id="tenant_id" name="tenant_id"
               min="1" required>
        <button type="submit">Generate Private Link</button>
    </form>

    <?php if ($tenantLink !== ''): ?>
        <div class="link">
            <p>Copy and send this link to the tenant. It is shown only now.</p>
            <input id="tenantLink"
                   value="<?= htmlspecialchars($tenantLink, ENT_QUOTES, 'UTF-8') ?>"
                   readonly>
            <button type="button"
                onclick="navigator.clipboard.writeText(
                    document.getElementById('tenantLink').value
                )">
                Copy Link
            </button>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
