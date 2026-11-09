
<?php
require_once "includes/auth.php";
require_once "config/database.php";

$message = "";
$error = "";
$generatedLink = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["full_name"])) {
    $fullName = trim($_POST["full_name"] ?? "");
    $unitId = ($_POST["unit_id"] ?? "") === ""
        ? null
        : (int) $_POST["unit_id"];
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $moveInDate = $_POST["move_in_date"] ?? "";

    if ($fullName === "") {
        $error = "Please enter the tenant's full name.";
    } elseif ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($moveInDate === "") {
        $error = "Please select a move-in date.";
    } else {
        try {
            $conn->begin_transaction();

            if ($unitId !== null) {
                $check = $conn->prepare(
                    "SELECT status FROM units WHERE unit_id = ? FOR UPDATE"
                );
                $check->bind_param("i", $unitId);
                $check->execute();
                $unit = $check->get_result()->fetch_assoc();
                $check->close();

                if (!$unit) {
                    throw new RuntimeException("The selected unit does not exist.");
                }

                if ($unit["status"] !== "Vacant") {
                    throw new RuntimeException("Please select a vacant unit.");
                }
            }

            $stmt = $conn->prepare(
                "INSERT INTO tenants
                (unit_id, full_name, phone, email, move_in_date, status)
                VALUES (?, ?, ?, ?, ?, 'Active')"
            );

            $stmt->bind_param(
                "issss",
                $unitId,
                $fullName,
                $phone,
                $email,
                $moveInDate
            );

            $stmt->execute();
            $stmt->close();

            if ($unitId !== null) {
                $update = $conn->prepare(
                    "UPDATE units SET status = 'Occupied' WHERE unit_id = ?"
                );
                $update->bind_param("i", $unitId);
                $update->execute();
                $update->close();
            }

            $conn->commit();
            $message = "Tenant added successfully!";
        } catch (Throwable $e) {
            $conn->rollback();

            if ($e instanceof mysqli_sql_exception) {
                error_log("Tenant insertion error: " . $e->getMessage());
                $error = "Unable to save the tenant. Please check the database.";
            } else {
                $error = $e->getMessage();
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["generate_tenant_link"])) {
    $tenantId = filter_input(INPUT_POST, "tenant_id", FILTER_VALIDATE_INT);

    if (!$tenantId || $tenantId < 1) {
        $error = "Invalid tenant selected.";
    } else {
        try {
            $stmt = $conn->prepare(
                "SELECT tenant_id, full_name, status, portal_token
                 FROM tenants
                 WHERE tenant_id = ?
                 LIMIT 1"
            );

            $stmt->bind_param("i", $tenantId);
            $stmt->execute();
            $tenant = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$tenant || $tenant["status"] !== "Active") {
                $error = "The tenant does not exist or is inactive.";
            } else {
                $token = $tenant["portal_token"];

                if (empty($token)) {
                    $token = bin2hex(random_bytes(32));

                    $update = $conn->prepare(
                        "UPDATE tenants
                         SET portal_token = ?
                         WHERE tenant_id = ? AND portal_token IS NULL"
                    );

                    $update->bind_param("si", $token, $tenantId);
                    $update->execute();
                    $update->close();

                    $stmt = $conn->prepare(
                        "SELECT portal_token FROM tenants WHERE tenant_id = ?"
                    );
                    $stmt->bind_param("i", $tenantId);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    $token = $result["portal_token"] ?? $token;
                }

                $baseUrl = (
                    (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
                        ? "https://"
                        : "http://"
                ) . $_SERVER["HTTP_HOST"]
                  . rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/\\");

                $generatedLink = $baseUrl
                    . "/tenantPortal.php?token="
                    . urlencode($token);

                $message = "Tenant portal link generated successfully.";
            }
        } catch (Throwable $e) {
            error_log("Tenant link error: " . $e->getMessage());
            $error = "Unable to generate the tenant link. Check that the portal_token column exists.";
        }
    }
}

$units = $conn->query(
    "SELECT unit_id, unit_number, monthly_rent
     FROM units
     WHERE status = 'Vacant'
     ORDER BY unit_number"
);

$tenants = $conn->query(
    "SELECT t.*, u.unit_number
     FROM tenants t
     LEFT JOIN units u ON t.unit_id = u.unit_id
     ORDER BY t.tenant_id DESC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Tenants | Apartment Management</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">

    <style>
        .btn-tenant-link {
            background: #f5c400;
            color: #202124;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            white-space: nowrap;
        }

        .btn-tenant-link:hover {
            background: #dfb200;
        }

        .portal-link-box {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
        }
    </style>
</head>
<body>

<div class="d-flex min-vh-100">
    <aside class="sidebar">
        <h4 class="fw-bold mb-4">APARTMENT</h4>
        <p class="sidebar-label">MAIN MENU</p>

        <a href="dashboard.php" class="nav-link">Dashboard</a>
        <a href="units.php" class="nav-link">Manage Units</a>
        <a href="tenants.php" class="nav-link active">Manage Tenants</a>
        <a href="payments.php" class="nav-link">Payments</a>
        <a href="reports.php" class="nav-link">Reports</a>

        <div class="sidebar-bottom">
            <p class="small mb-1">Logged in as</p>
            <strong><?= htmlspecialchars($_SESSION["admin_username"] ?? "Admin") ?></strong>
            <a href="logout.php" class="btn btn-outline-warning w-100 mt-3">Log Out</a>
        </div>
    </aside>

    <main class="main-content flex-grow-1">
        <h2 class="fw-bold mb-1">Manage Tenants</h2>
        <p class="text-secondary mb-4">
            Register tenants, assign vacant units, and share private tenant portal links.
        </p>

        <?php if ($message !== ""): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($generatedLink !== ""): ?>
            <div class="portal-link-box mb-4">
                <h5 class="fw-bold">Tenant Portal Link</h5>
                <p class="text-secondary">
                    Copy and share this private link with the tenant.
                </p>

                <div class="input-group">
                    <input
                        type="text"
                        id="tenantPortalLink"
                        class="form-control"
                        value="<?= htmlspecialchars($generatedLink, ENT_QUOTES, "UTF-8") ?>"
                        readonly
                    >
                    <button type="button" class="btn btn-warning" onclick="copyTenantLink()">
                        Copy Link
                    </button>
                    <a
                        href="<?= htmlspecialchars($generatedLink, ENT_QUOTES, "UTF-8") ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="btn btn-outline-dark"
                    >
                        Open Portal
                    </a>
                </div>

                <p id="copyMessage" class="small mt-2 mb-0"></p>
            </div>
        <?php endif; ?>

        <div class="content-card mb-4">
            <h5 class="fw-bold mb-3">Add New Tenant</h5>

            <form method="POST" action="tenants.php">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" id="full_name" name="full_name"
                               class="form-control" maxlength="100" required>
                    </div>

                    <div class="col-md-6">
                        <label for="unit_id" class="form-label">Assign Unit</label>
                        <select id="unit_id" name="unit_id" class="form-select">
                            <option value="">No unit assigned</option>
                            <?php if ($units): ?>
                                <?php while ($unit = $units->fetch_assoc()): ?>
                                    <option value="<?= (int) $unit["unit_id"] ?>">
                                        <?= htmlspecialchars($unit["unit_number"]) ?>
                                        - ₱<?= number_format((float) $unit["monthly_rent"], 2) ?>/month
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" id="phone" name="phone"
                               class="form-control" maxlength="20">
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email"
                               class="form-control" maxlength="100">
                    </div>

                    <div class="col-md-6">
                        <label for="move_in_date" class="form-label">Move-in Date</label>
                        <input type="date" id="move_in_date" name="move_in_date"
                               class="form-control" required>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-warning">
                            Add Tenant
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="content-card">
            <h5 class="fw-bold mb-3">Registered Tenants</h5>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Unit</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Move-in Date</th>
                            <th>Status</th>
                            <th>Tenant Portal</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if ($tenants && $tenants->num_rows > 0): ?>
                        <?php while ($tenant = $tenants->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($tenant["full_name"]) ?></td>

                                <td>
                                    <?= htmlspecialchars($tenant["unit_number"] ?? "Not assigned") ?>
                                </td>

                                <td><?= htmlspecialchars($tenant["phone"] ?? "") ?></td>

                                <td><?= htmlspecialchars($tenant["email"] ?? "") ?></td>

                                <td><?= htmlspecialchars($tenant["move_in_date"] ?? "") ?></td>

                                <td>
                                    <span class="badge text-bg-<?= $tenant["status"] === "Active" ? "success" : "secondary" ?>">
                                        <?= htmlspecialchars($tenant["status"]) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($tenant["status"] === "Active"): ?>
                                        <form action="tenants.php" method="POST">
                                            <input type="hidden" name="tenant_id"
                                                   value="<?= (int) $tenant["tenant_id"] ?>">

                                            <button
                                                type="submit"
                                                name="generate_tenant_link"
                                                value="1"
                                                class="btn-tenant-link"
                                            >
                                                Generate Tenant Link
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-secondary">Inactive tenant</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-4">
                                No tenants registered yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
async function copyTenantLink() {
    const field = document.getElementById("tenantPortalLink");
    const message = document.getElementById("copyMessage");

    try {
        await navigator.clipboard.writeText(field.value);
        message.textContent = "Tenant link copied successfully!";
    } catch (error) {
        field.select();
        const copied = document.execCommand("copy");
        message.textContent = copied
            ? "Tenant link copied successfully!"
            : "Please copy the link manually.";
    }
}
</script>

</body>
</html>
