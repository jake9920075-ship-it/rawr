
<?php
require_once "includes/auth.php";
require_once "config/database.php";

function getCount(mysqli $conn, string $sql): int {
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }

    return (int) $result->fetch_row()[0];
}

function getAmount(mysqli $conn, string $sql): float {
    $result = $conn->query($sql);
    if (!$result) {
        return 0.0;
    }

    return (float) $result->fetch_row()[0];
}

$totalUnits = getCount($conn, "SELECT COUNT(*) FROM units");
$occupiedUnits = getCount($conn, "SELECT COUNT(*) FROM units WHERE status = 'Occupied'");
$vacantUnits = getCount($conn, "SELECT COUNT(*) FROM units WHERE status = 'Vacant'");
$maintenanceUnits = getCount($conn, "SELECT COUNT(*) FROM units WHERE status = 'Maintenance'");

$activeTenants = getCount($conn, "SELECT COUNT(*) FROM tenants WHERE status = 'Active'");

$totalPaid = getAmount(
    $conn,
    "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'Paid'"
);

$totalPending = getAmount(
    $conn,
    "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'Pending'"
);

$totalOverdue = getAmount(
    $conn,
    "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'Overdue'"
);

$recentPayments = $conn->query(
    "SELECT p.payment_date, p.payment_for, p.amount,
            p.payment_method, p.status, p.reference_number,
            t.full_name, u.unit_number
     FROM payments p
     INNER JOIN tenants t ON p.tenant_id = t.tenant_id
     LEFT JOIN units u ON t.unit_id = u.unit_id
     ORDER BY p.payment_date DESC, p.payment_id DESC
     LIMIT 20"
);

$unitReport = $conn->query(
    "SELECT u.unit_number, u.number_of_stories, u.number_of_rooms,
            u.monthly_rent, u.status, t.full_name
     FROM units u
     LEFT JOIN tenants t
       ON t.unit_id = u.unit_id AND t.status = 'Active'
     ORDER BY u.unit_number"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports | Apartment Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<div class="d-flex min-vh-100">
    <aside class="sidebar">
        <h4 class="fw-bold mb-4">APARTMENT</h4>
        <p class="sidebar-label">MAIN MENU</p>

        <a href="dashboard.php" class="nav-link">Dashboard</a>
        <a href="units.php" class="nav-link">Manage Units</a>
        <a href="tenants.php" class="nav-link">Manage Tenants</a>
        <a href="payments.php" class="nav-link">Payments</a>
        <a href="reports.php" class="nav-link active">Reports</a>

        <div class="sidebar-bottom">
            <p class="small mb-1">Logged in as</p>
            <strong><?= htmlspecialchars($_SESSION["admin_username"] ?? "Admin") ?></strong>
            <a href="logout.php" class="btn btn-outline-warning w-100 mt-3">Log Out</a>
        </div>
    </aside>

    <main class="main-content flex-grow-1">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <h2 class="fw-bold mb-1">Reports</h2>
                <p class="text-secondary mb-0">Apartment occupancy and payment summary.</p>
            </div>
            <button onclick="window.print()" class="btn btn-warning">Print Report</button>
        </div>

        <h5 class="fw-bold mb-3">Unit Summary</h5>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="content-card h-100">
                    <p class="text-secondary mb-1">Total Units</p>
                    <h3 class="fw-bold"><?= $totalUnits ?></h3>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="content-card h-100">
                    <p class="text-secondary mb-1">Occupied Units</p>
                    <h3 class="fw-bold"><?= $occupiedUnits ?></h3>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="content-card h-100">
                    <p class="text-secondary mb-1">Vacant Units</p>
                    <h3 class="fw-bold"><?= $vacantUnits ?></h3>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="content-card h-100">
                    <p class="text-secondary mb-1">Maintenance</p>
                    <h3 class="fw-bold"><?= $maintenanceUnits ?></h3>
                </div>
            </div>
        </div>

        <div class="content-card mb-4">
            <h5 class="fw-bold mb-3">Payment Summary</h5>

            <div class="row g-3">
                <div class="col-md-3">
                    <p class="text-secondary mb-1">Active Tenants</p>
                    <h4 class="fw-bold"><?= $activeTenants ?></h4>
                </div>

                <div class="col-md-3">
                    <p class="text-secondary mb-1">Total Payments Received</p>
                    <h4 class="fw-bold text-success">₱<?= number_format($totalPaid, 2) ?></h4>
                </div>

                <div class="col-md-3">
                    <p class="text-secondary mb-1">Pending Payments</p>
                    <h4 class="fw-bold">₱<?= number_format($totalPending, 2) ?></h4>
                </div>

                <div class="col-md-3">
                    <p class="text-secondary mb-1">Overdue Payments</p>
                    <h4 class="fw-bold text-danger">₱<?= number_format($totalOverdue, 2) ?></h4>
                </div>
            </div>

            <p class="text-secondary small mt-3 mb-0">
                Payment totals are calculated from existing payment records.
                Pending and overdue amounts do not include rent that has not been entered as a payment record.
            </p>
        </div>

        <div class="content-card mb-4">
            <h5 class="fw-bold mb-3">Unit Details Report</h5>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th>Stories</th>
                            <th>Rooms</th>
                            <th>Monthly Rent</th>
                            <th>Tenant</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($unitReport && $unitReport->num_rows > 0): ?>
                            <?php while ($unit = $unitReport->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($unit["unit_number"]) ?></td>
                                    <td><?= (int) $unit["number_of_stories"] ?></td>
                                    <td><?= (int) $unit["number_of_rooms"] ?></td>
                                    <td>₱<?= number_format((float) $unit["monthly_rent"], 2) ?></td>
                                    <td><?= htmlspecialchars($unit["full_name"] ?? "—") ?></td>
                                    <td><?= htmlspecialchars($unit["status"]) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-secondary py-4">
                                    No units found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="content-card">
            <h5 class="fw-bold mb-3">Recent Payment Records</h5>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Tenant</th>
                            <th>Unit</th>
                            <th>Payment For</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentPayments && $recentPayments->num_rows > 0): ?>
                            <?php while ($payment = $recentPayments->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($payment["payment_date"]) ?></td>
                                    <td><?= htmlspecialchars($payment["full_name"]) ?></td>
                                    <td><?= htmlspecialchars($payment["unit_number"] ?? "—") ?></td>
                                    <td><?= htmlspecialchars($payment["payment_for"]) ?></td>
                                    <td><?= htmlspecialchars($payment["payment_method"] ?? "—") ?></td>
                                    <td>₱<?= number_format((float) $payment["amount"], 2) ?></td>
                                    <td><?= htmlspecialchars($payment["status"]) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-secondary py-4">
                                    No payment records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<style>
@media print {
    .sidebar, button {
        display: none !important;
    }

    .main-content {
        width: 100% !important;
        padding: 0 !important;
    }

    .content-card {
        break-inside: avoid;
        box-shadow: none !important;
    }
}
</style>

</body>
</html>
