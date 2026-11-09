
<?php
require_once "includes/auth.php";
require_once "config/database.php";

function getCount($conn, $sql) {
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    return (int) ($row["total"] ?? 0);
}

function getAmount($conn, $sql) {
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    return (float) ($row["total"] ?? 0);
}

$totalUnits = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM units"
);

$occupiedUnits = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM units WHERE status = 'Occupied'"
);

$vacantUnits = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM units WHERE status = 'Vacant'"
);

$totalTenants = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM tenants WHERE status = 'Active'"
);

$totalPaid = getAmount(
    $conn,
    "SELECT COALESCE(SUM(amount), 0) AS total
     FROM payments
     WHERE status = 'Paid'"
);

$totalOutstanding = getAmount(
    $conn,
    "SELECT COALESCE(SUM(amount), 0) AS total
     FROM payments
     WHERE status IN ('Pending', 'Overdue')"
);

$recentPayments = $conn->query(
    "SELECT
        p.payment_date,
        t.full_name AS tenant_name,
        p.amount AS amount_paid,
        p.status AS payment_status
     FROM payments p
     INNER JOIN tenants t ON p.tenant_id = t.tenant_id
     ORDER BY p.payment_date DESC, p.payment_id DESC
     LIMIT 5"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | AMCK Compound</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<div class="d-flex min-vh-100">
    <aside class="sidebar">
        <h4 class="fw-bold mb-4">AMCK Compound
</h4>

        <p class="sidebar-label">MAIN MENU</p>
        <a href="dashboard.php" class="nav-link active">Dashboard</a>
        <a href="units.php" class="nav-link">Manage Units</a>
        <a href="tenants.php" class="nav-link">Manage Tenants</a>
        <a href="payments.php" class="nav-link">Payments</a>
        <a href="reports.php" class="nav-link">Reports</a>

        <div class="sidebar-bottom">
            <p class="small mb-1">Logged in as</p>
            <strong><?= htmlspecialchars($_SESSION["admin_username"] ?? "Admin") ?></strong>

            <a href="logout.php" class="btn btn-outline-warning w-100 mt-3">
                Log Out
            </a>
        </div>
    </aside>

    <main class="main-content">
    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold">Dashboard</h2>
                <p class="text-secondary mb-0">
                    Overview of your apartment property
                </p>
            </div>

            <span class="admin-badge">Administrator</span>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <p>Total Units</p>
                    <h2><?= $totalUnits ?></h2>
                    <span>All registered units</span>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <p>Occupied Units</p>
                    <h2><?= $occupiedUnits ?></h2>
                    <span>Currently occupied</span>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <p>Vacant Units</p>
                    <h2><?= $vacantUnits ?></h2>
                    <span>Available for tenants</span>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <p>Active Tenants</p>
                    <h2><?= $totalTenants ?></h2>
                    <span>Currently registered tenants</span>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="stat-card">
                    <p>Total Payments Received</p>
                    <h2>₱<?= number_format($totalPaid, 2) ?></h2>
                    <span>Payments marked as paid</span>
                </div>
            </div>

            <div class="col-md-6">
                <div class="stat-card">
                    <p>Outstanding Payments</p>
                    <h2>₱<?= number_format($totalOutstanding, 2) ?></h2>
                    <span>Pending and overdue payment records</span>
                </div>
            </div>
        </div>

        <div class="content-card">
            <h5 class="fw-bold mb-3">Recent Payments</h5>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Tenant</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if ($recentPayments && $recentPayments->num_rows > 0): ?>
                        <?php while ($payment = $recentPayments->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($payment["payment_date"]) ?></td>
                                <td><?= htmlspecialchars($payment["tenant_name"]) ?></td>
                                <td>₱<?= number_format((float) $payment["amount_paid"], 2) ?></td>
                                <td><?= htmlspecialchars($payment["payment_status"]) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-secondary py-4">
                                No payment records found.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>
</div>

</body>
</html>
