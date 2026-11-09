
<?php
require_once "includes/auth.php";
require_once "config/database.php";

$message = "";
$error = "";

$allowedStatuses = ["Vacant", "Occupied", "Maintenance"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $unitNumber = trim($_POST["unit_number"] ?? "");
    $stories = (int) ($_POST["number_of_stories"] ?? 1);
    $rooms = (int) ($_POST["number_of_rooms"] ?? 1);
    $hasSink = isset($_POST["has_sink"]) ? 1 : 0;
    $hasBathroom = isset($_POST["has_bathroom"]) ? 1 : 0;
    $hasLaundry = isset($_POST["has_laundry_area"]) ? 1 : 0;
    $rent = (float) ($_POST["monthly_rent"] ?? 0);
    $status = $_POST["status"] ?? "Vacant";
    $description = trim($_POST["description"] ?? "");

    if ($unitNumber === "" || $stories < 1 || $rooms < 1 || $rent < 0) {
        $error = "Please enter valid unit information.";
    } elseif (!in_array($status, $allowedStatuses, true)) {
        $error = "Invalid unit status.";
    } else {
        try {
            $stmt = $conn->prepare(
                "INSERT INTO units (
                    unit_number,
                    number_of_stories,
                    number_of_rooms,
                    has_sink,
                    has_bathroom,
                    has_laundry_area,
                    monthly_rent,
                    status,
                    description
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "siiiiidss",
                $unitNumber,
                $stories,
                $rooms,
                $hasSink,
                $hasBathroom,
                $hasLaundry,
                $rent,
                $status,
                $description
            );

            $stmt->execute();
            $stmt->close();

            $message = "Unit added successfully!";
        } catch (mysqli_sql_exception $e) {
            if (isset($stmt) && $stmt instanceof mysqli_stmt) {
                $stmt->close();
            }

            if ($e->getCode() === 1062) {
                $error = "This unit number already exists. Please use another.";
            } else {
                error_log("Unit insertion error: " . $e->getMessage());
                $error = "Unable to add the unit. Please check your database settings and table.";
            }
        }
    }
}

$units = $conn->query("SELECT * FROM units ORDER BY unit_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Units | Apartment Management</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<div class="d-flex min-vh-100">
    <aside class="sidebar">
        <h4 class="fw-bold mb-4">APARTMENT</h4>
        <p class="sidebar-label">MAIN MENU</p>

        <a href="dashboard.php" class="nav-link">Dashboard</a>
        <a href="units.php" class="nav-link active">Manage Units</a>
        <a href="tenants.php" class="nav-link">Manage Tenants</a>
        <a href="payments.php" class="nav-link">Payments</a>
        <a href="reports.php" class="nav-link">Reports</a>

        <div class="sidebar-bottom">
            <p class="small mb-1">Logged in as</p>
            <strong><?= htmlspecialchars($_SESSION["admin_username"] ?? "Admin") ?></strong>
            <a href="logout.php" class="btn btn-outline-warning w-100 mt-3">Log Out</a>
        </div>
    </aside>

    <main class="main-content flex-grow-1">
        <h2 class="fw-bold mb-1">Manage Units</h2>
        <p class="text-secondary mb-4">Add and view apartment units.</p>

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

        <div class="content-card mb-4">
            <h5 class="fw-bold mb-3">Add New Unit</h5>

            <form method="POST" action="units.php">
                <div class="row g-3">

                    <div class="col-md-4">
                        <label for="unit_number" class="form-label">Unit Number</label>
                        <input
                            type="text"
                            id="unit_number"
                            name="unit_number"
                            class="form-control"
                            maxlength="20"
                            required
                        >
                    </div>

                    <div class="col-md-4">
                        <label for="number_of_stories" class="form-label">Number of Stories</label>
                        <input
                            type="number"
                            id="number_of_stories"
                            name="number_of_stories"
                            class="form-control"
                            min="1"
                            value="1"
                            required
                        >
                    </div>

                    <div class="col-md-4">
                        <label for="number_of_rooms" class="form-label">Number of Rooms</label>
                        <input
                            type="number"
                            id="number_of_rooms"
                            name="number_of_rooms"
                            class="form-control"
                            min="1"
                            value="1"
                            required
                        >
                    </div>

                    <div class="col-md-4">
                        <label for="monthly_rent" class="form-label">Monthly Rent (₱)</label>
                        <input
                            type="number"
                            id="monthly_rent"
                            name="monthly_rent"
                            class="form-control"
                            min="0"
                            step="0.01"
                            required
                        >
                    </div>

                    <div class="col-md-4">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select" required>
                            <option value="Vacant">Vacant</option>
                            <option value="Occupied">Occupied</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                    </div>

                    <div class="col-md-4 d-flex align-items-end flex-wrap gap-3">
                        <div class="form-check">
                            <input type="checkbox" name="has_sink" id="sink" class="form-check-input">
                            <label for="sink" class="form-check-label">Sink</label>
                        </div>

                        <div class="form-check">
                            <input type="checkbox" name="has_bathroom" id="bathroom" class="form-check-input">
                            <label for="bathroom" class="form-check-label">Bathroom</label>
                        </div>

                        <div class="form-check">
                            <input type="checkbox" name="has_laundry_area" id="laundry" class="form-check-input">
                            <label for="laundry" class="form-check-label">Laundry Area</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea
                            id="description"
                            name="description"
                            class="form-control"
                            rows="3"
                            placeholder="Describe the unit, its facilities, and other details."
                        ></textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-warning">Add Unit</button>
                    </div>

                </div>
            </form>
        </div>

        <div class="content-card">
            <h5 class="fw-bold mb-3">Registered Units</h5>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th>Stories</th>
                            <th>Rooms</th>
                            <th>Sink</th>
                            <th>Bathroom</th>
                            <th>Laundry</th>
                            <th>Monthly Rent</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($units && $units->num_rows > 0): ?>
                            <?php while ($unit = $units->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($unit["unit_number"]) ?></td>
                                    <td><?= (int) $unit["number_of_stories"] ?></td>
                                    <td><?= (int) $unit["number_of_rooms"] ?></td>
                                    <td><?= $unit["has_sink"] ? "Yes" : "No" ?></td>
                                    <td><?= $unit["has_bathroom"] ? "Yes" : "No" ?></td>
                                    <td><?= $unit["has_laundry_area"] ? "Yes" : "No" ?></td>
                                    <td>₱<?= number_format((float) $unit["monthly_rent"], 2) ?></td>
                                    <td>
                                        <span class="badge text-bg-<?=
                                            $unit["status"] === "Vacant" ? "success" :
                                            ($unit["status"] === "Occupied" ? "primary" : "warning")
                                        ?>">
                                            <?= htmlspecialchars($unit["status"]) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-secondary py-4">
                                    No units have been added yet.
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
