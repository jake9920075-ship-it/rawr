
<?php
require_once __DIR__ . '/config/database.php';

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$token = $_GET['token'] ?? '';

if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(403);
    exit('Invalid or missing tenant link.');
}

$tokenHash = hash('sha256', $token);

$sql = "SELECT
            t.tenant_id,
            t.tenant_name,
            u.unit_name,
            u.floors,
            u.room_count,
            u.monthly_rent
        FROM tenant_portal_tokens pt
        JOIN tenants t ON t.tenant_id = pt.tenant_id
        JOIN units u ON u.unit_id = t.unit_id
        WHERE pt.token_hash = ?
          AND pt.revoked_at IS NULL
          AND (pt.expires_at IS NULL OR pt.expires_at > NOW())
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $tokenHash);
$stmt->execute();
$tenant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tenant) {
    http_response_code(403);
    exit('This tenant link is invalid, expired, or revoked.');
}

$tenantId = (int) $tenant['tenant_id'];

$stmt = $conn->prepare(
    "SELECT payment_id, due_date, amount, payment_date, status
     FROM payments
     WHERE tenant_id = ?
     ORDER BY due_date DESC, payment_id DESC"
);

$stmt->bind_param("i", $tenantId);
$stmt->execute();
$result = $stmt->get_result();

$payments = [];
$outstanding = 0;
$nextDueDate = null;

while ($payment = $result->fetch_assoc()) {
    $payments[] = $payment;

    if (strcasecmp($payment['status'], 'Paid') !== 0) {
        $outstanding += (float) $payment['amount'];

        if (
            $payment['due_date'] >= date('Y-m-d') &&
            (
                $nextDueDate === null ||
                $payment['due_date'] < $nextDueDate
            )
        ) {
            $nextDueDate = $payment['due_date'];
        }
    }
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Tenant Portal</title>
    <style>
        :root {
            --accent: #f5c400;
            --dark: #202124;
            --background: #f5f6f8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--background);
            color: #252525;
        }

        header {
            background: var(--dark);
            color: white;
            padding: 22px;
            border-bottom: 4px solid var(--accent);
        }

        main {
            max-width: 1000px;
            margin: 25px auto;
            padding: 0 16px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 18px;
            box-shadow: 0 2px 8px #0000000d;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }

        .label {
            color: #666;
            font-size: 14px;
        }

        .value {
            font-size: 23px;
            font-weight: bold;
            margin-top: 8px;
            overflow-wrap: anywhere;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th, td {
            text-align: left;
            padding: 13px 10px;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #fafafa;
        }

        .status {
            font-weight: bold;
        }

        .paid {
            color: #16803c;
        }

        .unpaid {
            color: #b45309;
        }

        footer {
            text-align: center;
            padding: 20px;
            color: #666;
        }
    </style>
</head>
<body>

<header>
    <h2>Apartment Tenant Portal</h2>
    <p>Your unit, rent due dates, and payment records</p>
</header>

<main>
    <section class="card">
        <h2>Welcome, <?= e($tenant['tenant_name']) ?>!</h2>
        <p>Here you can check your rental information and payments.</p>
    </section>

    <section class="grid">
        <div class="card">
            <div class="label">Your Unit</div>
            <div class="value"><?= e($tenant['unit_name']) ?></div>
        </div>

        <div class="card">
            <div class="label">Monthly Rent</div>
            <div class="value">
                ₱<?= number_format((float) $tenant['monthly_rent'], 2) ?>
            </div>
        </div>

        <div class="card">
            <div class="label">Outstanding Balance</div>
            <div class="value">
                ₱<?= number_format($outstanding, 2) ?>
            </div>
        </div>

        <div class="card">
            <div class="label">Next Unpaid Due Date</div>
            <div class="value">
                <?= $nextDueDate ? e(date('M d, Y', strtotime($nextDueDate))) : 'None' ?>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>Unit Information</h2>
        <p><strong>Unit:</strong> <?= e($tenant['unit_name']) ?></p>
        <p><strong>Number of floors:</strong> <?= e($tenant['floors']) ?></p>
        <p><strong>Number of rooms:</strong> <?= e($tenant['room_count']) ?></p>
    </section>

    <section class="card">
        <h2>Payment History and Due Dates</h2>

        <?php if (count($payments) > 0): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Due Date</th>
                            <th>Amount</th>
                            <th>Payment Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <?php
                        $status = ucfirst(strtolower($payment['status']));
                        $isPaid = strcasecmp($payment['status'], 'Paid') === 0;
                        $isOverdue = !$isPaid &&
                            $payment['due_date'] < date('Y-m-d');
                        ?>
                        <tr>
                            <td><?= e(date('M d, Y', strtotime($payment['due_date']))) ?></td>
                            <td>₱<?= number_format((float) $payment['amount'], 2) ?></td>
                            <td>
                                <?= $payment['payment_date']
                                    ? e(date('M d, Y', strtotime($payment['payment_date'])))
                                    : '—' ?>
                            </td>
                            <td class="status <?= $isPaid ? 'paid' : 'unpaid' ?>">
                                <?= $isOverdue ? 'Overdue' : e($status) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>No payment records are available yet.</p>
        <?php endif; ?>
    </section>

    <p>For corrections or questions about your balance, contact the apartment administrator.</p>
</main>

<footer>
    Apartment Management System
</footer>

</body>
</html>
