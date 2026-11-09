
<?php
require_once "includes/auth.php";
require_once "config/database.php";

$message = "";
$error = "";

$allowedStatuses = ["Paid", "Pending", "Overdue"];
$allowedMethods = ["Cash", "Bank Transfer", "GCash", "Maya", "Other"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $tenantId = (int) ($_POST["tenant_id"] ?? 0);
    $amount = (float) ($_POST["amount"] ?? 0);
    $paymentDate = trim($_POST["payment_date"] ?? "");
    $paymentFor = trim($_POST["payment_for"] ?? "Monthly Rent");
    $paymentMethod = $_POST["payment_method"] ?? "Cash";
    $status = $_POST["status"] ?? "Pending";
    $referenceNumber = trim($_POST["reference_number"] ?? "");
    $notes = trim($_POST["notes"] ?? "");

    if ($tenantId < 1 || $amount <= 0) {
        $error = "Please select a tenant and enter an amount greater than zero.";
    } elseif (
        $paymentDate === "" ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate) ||
        !checkdate(
            (int) substr($paymentDate, 5, 2),
            (int) substr($paymentDate, 8, 2),
            (int) substr($paymentDate, 0, 4)
        )
    ) {
        $error = "Please enter a valid payment date.";
    } elseif ($paymentFor === "") {
        $error = "Please enter what the payment is for.";
    } elseif (!in_array($paymentMethod, $allowedMethods, true)) {
        $error = "Invalid payment method.";
    } elseif (!in_array($status, $allowedStatuses, true)) {
        $error = "Invalid payment status.";
    } else {
        try {
            $check = $conn->prepare(
                "SELECT tenant_id FROM tenants
                 WHERE tenant_id = ? AND status = 'Active'"
            );
            $check->bind_param("i", $tenantId);
            $check->execute();
            $tenantExists = $check->get_result()->num_rows > 0;
            $check->close();

            if (!$tenantExists) {
                throw new RuntimeException(
                    "Please select an active tenant."
                );
            }

            $stmt = $conn->prepare(
                "INSERT INTO payments (
                    tenant_id,
                    amount,
                    payment_date,
                    payment_for,
                    payment_method,
                    status,
                    reference_number,
                    notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "idssssss",
                $tenantId,
                $amount,
                $paymentDate,
                $paymentFor,
                $paymentMethod,
                $status,
                $referenceNumber,
                $notes
            );

            $stmt->execute();
            $stmt->close();

            $message = "Payment recorded successfully!";
        } catch (Throwable $e) {
            if (isset($stmt) && $stmt instanceof mysqli_stmt) {
                $stmt->close();
            }

            if ($e instanceof mysqli_sql_exception) {
                error_log("Payment insertion error: " . $e->getMessage());
                $error = "Unable to save the payment. Please check your database.";
            } else {
                $error = $e->getMessage();
            }
        }
    }
}

$tenants = $conn->query(
    "SELECT t.tenant_id, t.full_name, u.unit_number
     FROM tenants t
     LEFT JOIN units u ON t.unit_id = u.unit_id
     WHERE t.status = 'Active'
     ORDER BY t.full_name"
);

$payments = $conn->query(
    "SELECT p.payment_id, p.amount, p.payment_date, p.payment_for,
            p.payment_method, p.status, p.reference_number,
            t.full_name, u.unit_number
     FROM payments p
     INNER JOIN tenants t ON p.tenant_id = t.tenant_id
     LEFT JOIN units u ON t.unit_id = u.unit_id
     ORDER BY p.payment_id DESC"
);

$totalPaidResult = $conn->query(
    "SELECT COALESCE(SUM(amount), 0) AS total
     FROM payments WHERE status = 'Paid'"
);
$totalPaid = (float) $totalPaidResult->fetch_assoc()["total"];

$totalPendingResult = $conn->query(
    "SELECT COALESCE(SUM(amount), 0) AS total
     FROM payments WHERE status = 'Pending'"
);
$totalPending = (float) $totalPendingResult->fetch_assoc()["total"];

$totalOverdueResult = $conn->query(
    "SELECT COALESCE(SUM(amount), 0) AS total
     FROM payments WHERE status = 'Overdue'"
);
$totalOverdue = (float) $totalOverdueResult->fetch_assoc()["total"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payments | Apartment Management</title>

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
        <a href="payments.php" class="nav-link active">Payments</a>
        <a href="reports.php" class="nav-link">Reports</a>

        <div class="sidebar-bottom">
            <p class="small mb-1">Logged in as</p>
            <strong><?= htmlspecialchars($_SESSION["admin_username"] ?? "Admin") ?></strong>
            <a href="logout.php" class="btn btn-outline-warning w-100 mt-3">Log Out</a>
        </div>
    </aside>

    <main class="main-content flex-grow-1">
        <h2 class="fw-bold mb-1">Payments</h2>
        <p class="text-secondary mb-4">Record and monitor tenant payments.</p>

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

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="content-card h-100">
                    <p class="text-secondary mb-1">Payments Received</p>
                    <h4 class="fw-bold text-success">
                        ₱<?= number_format($totalPaid, 2) ?>
                    </h4>
                </div>
            </div>

            <div class="col-md-4">
                <div class="content-card h-100">
                    <p class="text-secondary mb-1">Pending Payments</p>
                    <h4 class="fw-bold">
                        ₱<?= number_format($totalPending, 2) ?>
                    </h4>
                </div>
            </div>

            <div class="col-md-4">
                <div class="content-card h-100">
                    <p class="text-secondary mb-1">Overdue Payments</p>
                    <h4 class="fw-bold text-danger">
                        ₱<?= number_format($totalOverdue, 2) ?>
                    </h4>
                </div>
            </div>
        </div>

        <div class="content-card mb-4">
            <h5 class="fw-bold mb-3">Record New Payment</h5>

            <form method="POST" action="payments.php">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label for="tenant_id" class="form-label">Tenant</label>
                        <select id="tenant_id" name="tenant_id" class="form-select" required>
                            <option value="">Select a tenant</option>
                            <?php while ($tenant = $tenants->fetch_assoc()): ?>
                                <option value="<?= (int) $tenant["tenant_id"] ?>">
                                    <?= htmlspecialchars($tenant["full_name"]) ?>
                                    (Unit <?= htmlspecialchars($tenant["unit_number"] ?? "Unassigned") ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="amount" class="form-label">Amount (₱)</label>
                        <input type="number" id="amount" name="amount"
                               class="form-control" min="0.01" step="0.01" required>
                    </div>

                    <div class="col-md-6">
                        <label for="payment_date" class="form-label">Payment Date</label>
                        <input type="date" id="payment_date" name="payment_date"
                               class="form-control"
                               value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="payment_for" class="form-label">Payment For</label>
                        <input type="text" id="payment_for" name="payment_for"
                               class="form-control" value="Monthly Rent"
                               maxlength="100" required>
                    </div>

                    <div class="col-md-6">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select id="payment_method" name="payment_method" class="form-select" required>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="GCash">GCash</option>
                            <option value="Maya">Maya</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="status" class="form-label">Payment Status</label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="Paid">Paid</option>
                            <option value="Pending">Pending</option>
                            <option value="Overdue">Overdue</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="reference_number" class="form-label">
                            Reference Number (Optional)
                        </label>
                        <input type="text" id="reference_number" name="reference_number"
                               class="form-control" maxlength="100">
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes (Optional)</label>
                        <textarea id="notes" name="notes" class="form-control"
                                  rows="2"></textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-warning">
                            Record Payment
                        </button>
                    </div>

                </div>
            </form>
        </div>

        <div class="content-card">
            <h5 class="fw-bold mb-3">Payment History</h5>

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
                            <th>Reference</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($payments && $payments->num_rows > 0): ?>
                            <?php while ($payment = $payments->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($payment["payment_date"]) ?></td>
                                    <td><?= htmlspecialchars($payment["full_name"]) ?></td>
                                    <td><?= htmlspecialchars($payment["unit_number"] ?? "—") ?></td>
                                    <td><?= htmlspecialchars($payment["payment_for"]) ?></td>
                                    <td><?= htmlspecialchars($payment["payment_method"] ?? "—") ?></td>
                                    <td>₱<?= number_format((float) $payment["amount"], 2) ?></td>
                                    <td>
                                        <?php
                                        $badge = match ($payment["status"]) {
                                            "Paid" => "success",
                                            "Pending" => "warning",
                                            "Overdue" => "danger",
                                            default => "secondary"
                                        };
                                        ?>
                                        <span class="badge text-bg-<?= $badge ?>">
                                            <?= htmlspecialchars($payment["status"]) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($payment["reference_number"] ?? "—") ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-secondary py-4">
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

</body>
</html>
