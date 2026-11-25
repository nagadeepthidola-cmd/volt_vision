
<?php
session_start();
require_once __DIR__ . "/config/db.php";

// LOGIN CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// CSRF PROTECTION
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$months = [
    1 => "January", 2 => "February",
    3 => "March", 4 => "April",
    5 => "May", 6 => "June",
    7 => "July", 8 => "August",
    9 => "September", 10 => "October",
    11 => "November", 12 => "December"
];

$error = "";
$success = "";

// DELETE PREDICTION
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'delete'
) {
    $posted_token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrf_token, $posted_token)) {
        $error = "Invalid request. Please refresh the page.";
    } else {
        $delete_id = filter_var(
            $_POST['prediction_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        if (!$delete_id || $delete_id < 1) {
            $error = "Invalid prediction ID.";
        } else {
            $delete_stmt = $conn->prepare(
                "DELETE FROM predictions WHERE id = ? AND user_id = ?"
            );

            if ($delete_stmt) {
                $delete_stmt->bind_param("ii", $delete_id, $user_id);
                $delete_stmt->execute();

                if ($delete_stmt->affected_rows > 0) {
                    $delete_stmt->close();
                    header("Location: my_predictions.php?deleted=1");
                    exit();
                } else {
                    $error = "Prediction not found.";
                }

                $delete_stmt->close();
            } else {
                $error = "Unable to delete prediction.";
            }
        }
    }
}

if (isset($_GET['deleted'])) {
    $success = "Prediction deleted successfully!";
}

// SEARCH FILTERS
$monthFilter = isset($_GET['month'])
    ? (int)$_GET['month'] : 0;

if ($monthFilter < 0 || $monthFilter > 12) {
    $monthFilter = 0;
}

$cityFilter = trim($_GET['city'] ?? '');
$cityFilter = substr($cityFilter, 0, 100);

// FETCH PREDICTIONS
$sql = "SELECT
            id, fan, refrigerator, air_conditioner,
            television, monitor, motor_pump,
            fan_daily_hours, refrigerator_daily_hours,
            air_conditioner_daily_hours, television_daily_hours,
            monitor_daily_hours, motor_pump_daily_hours,
            estimated_units, month, city, company,
            monthly_hours, tariff_rate, predicted_bill, created_at
        FROM predictions
        WHERE user_id = ?
        AND (? = 0 OR month = ?)
        AND (? = '' OR city LIKE CONCAT('%', ?, '%'))
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);

$predictions = [];
$totalPredictions = 0;
$totalAmount = 0;
$averageAmount = 0;
$totalUnits = 0;

if ($stmt) {
    $stmt->bind_param(
        "iiiss",
        $user_id,
        $monthFilter,
        $monthFilter,
        $cityFilter,
        $cityFilter
    );

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $predictions[] = $row;
    }

    $stmt->close();

    $totalPredictions = count($predictions);

    foreach ($predictions as $item) {
        $totalAmount += (float)$item['predicted_bill'];
        $totalUnits += (float)$item['estimated_units'];
    }

    if ($totalPredictions > 0) {
        $averageAmount = $totalAmount / $totalPredictions;
    }
} else {
    $error = "Unable to fetch prediction history: " . $conn->error;
}

// CSV EXPORT
if (isset($_GET['export']) && $_GET['export'] === 'csv') {

    if (!empty($predictions)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=VoltVision_predictions.csv');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, [
            'Date', 'Month', 'City', 'Company',
            'Fans', 'Fan Daily Hours',
            'Refrigerators', 'Refrigerator Daily Hours',
            'AC', 'AC Daily Hours',
            'Televisions', 'TV Daily Hours',
            'Monitors', 'Monitor Daily Hours',
            'Motor Pumps', 'Pump Daily Hours',
            'Estimated Units (kWh)',
            'Tariff Rate', 'Predicted Bill'
        ]);

        foreach ($predictions as $item) {
            fputcsv($output, [
                date('d-m-Y', strtotime($item['created_at'])),
                $months[(int)$item['month']] ?? 'Unknown',
                $item['city'],
                $item['company'],
                $item['fan'],
                $item['fan_daily_hours'],
                $item['refrigerator'],
                $item['refrigerator_daily_hours'],
                $item['air_conditioner'],
                $item['air_conditioner_daily_hours'],
                $item['television'],
                $item['television_daily_hours'],
                $item['monitor'],
                $item['monitor_daily_hours'],
                $item['motor_pump'],
                $item['motor_pump_daily_hours'],
                $item['estimated_units'],
                $item['tariff_rate'],
                $item['predicted_bill']
            ]);
        }

        fclose($output);
        exit();
    } else {
        $error = "No records available to export.";
    }
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>My Predictions | VoltVision</title>

<style>
:root {
    --bg: #06111f;
    --panel: #0d1b2b;
    --panel-light: #12243a;
    --border: rgba(148,180,205,.13);
    --green: #00e69a;
    --cyan: #25d9ef;
    --text: #edf7ff;
    --muted: #8da4b8;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Arial, sans-serif;
}

body {
    background:
        radial-gradient(ellipse at 10% 0%, rgba(0,230,154,.09), transparent 35%),
        radial-gradient(ellipse at 90% 15%, rgba(37,217,239,.07), transparent 30%),
        var(--bg);
    color: var(--text);
    min-height: 100vh;
}

/* NAVBAR */

.navbar {
    background: rgba(8,23,39,.94);
    backdrop-filter: blur(18px);
    border-bottom: 1px solid var(--border);
    padding: 19px 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 18px;
    position: sticky;
    top: 0;
    z-index: 100;
}

.logo {
    font-size: 27px;
    font-weight: 900;
    letter-spacing: .5px;
    color: white;
}

.logo span {
    color: var(--green);
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 22px;
    flex-wrap: wrap;
}

.nav-links a {
    color: #a9bdcf;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    transition: .3s;
}

.nav-links a:hover,
.nav-links a.active {
    color: var(--green);
}

.nav-links .logout {
    background: rgba(255,75,95,.10);
    border: 1px solid rgba(255,75,95,.2);
    color: #ff8795;
    padding: 10px 15px;
    border-radius: 9px;
}

/* CONTAINER */

.container {
    max-width: 1450px;
    margin: 38px auto;
    padding: 0 28px;
}

/* HERO */

.page-banner {
    position: relative;
    overflow: hidden;
    padding: 45px 42px;
    border-radius: 22px;
    margin-bottom: 30px;
    background:
        radial-gradient(circle at 85% 20%, rgba(0,230,154,.23), transparent 28%),
        radial-gradient(circle at 65% 100%, rgba(37,217,239,.13), transparent 35%),
        linear-gradient(120deg,#102b40,#0a2633 55%,#073b37);
    border: 1px solid rgba(0,230,154,.2);
    box-shadow: 0 18px 45px rgba(0,0,0,.2);
}

.banner-tag {
    display: inline-block;
    padding: 8px 14px;
    background: rgba(0,230,154,.10);
    color: var(--green);
    border: 1px solid rgba(0,230,154,.25);
    border-radius: 30px;
    font-size: 10px;
    letter-spacing: 2px;
    font-weight: 800;
    margin-bottom: 17px;
}

.page-banner h1 {
    font-size: clamp(27px,4vw,38px);
    font-weight: 800;
    margin-bottom: 12px;
}

.page-banner p {
    color: #b0c8d8;
    font-size: 14px;
    line-height: 1.8;
    max-width: 650px;
}

.banner-icon {
    position: absolute;
    right: 65px;
    top: 15px;
    font-size: 125px;
    opacity: .13;
    filter: drop-shadow(0 0 20px var(--green));
}

/* ALERTS */

.error,
.success {
    padding: 16px 19px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 14px;
    overflow-wrap: anywhere;
}

.error {
    background: rgba(255,65,85,.10);
    color: #ff9ba5;
    border: 1px solid rgba(255,65,85,.25);
}

.success {
    background: rgba(0,230,154,.09);
    color: #65f5bd;
    border: 1px solid rgba(0,230,154,.25);
}

/* SUMMARY CARDS */

.summary {
    display: grid;
    grid-template-columns: repeat(4,1fr);
    gap: 20px;
    margin-bottom: 28px;
}

.summary-card {
    position: relative;
    overflow: hidden;
    min-width: 0;
    padding: 25px;
    background: linear-gradient(145deg,#11243a,#0b1a2a);
    border: 1px solid var(--border);
    border-radius: 18px;
    box-shadow: 0 10px 30px rgba(0,0,0,.15);
    transition: .3s ease;
}

.summary-card::after {
    content: "";
    position: absolute;
    width: 100px;
    height: 100px;
    right: -40px;
    bottom: -50px;
    background: rgba(0,230,154,.08);
    border-radius: 50%;
    filter: blur(15px);
}

.summary-card:hover {
    transform: translateY(-6px);
    border-color: rgba(0,230,154,.4);
    box-shadow: 0 15px 35px rgba(0,230,154,.08);
}

.card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.card-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 23px;
    border: 1px solid rgba(255,255,255,.07);
}

.green {
    background: rgba(0,230,154,.13);
}

.blue {
    background: rgba(37,217,239,.13);
}

.orange {
    background: rgba(255,169,75,.13);
}

.purple {
    background: rgba(171,125,255,.13);
}

.card-label {
    color: var(--muted);
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 10px;
}

.summary-card h2 {
    color: var(--green);
    font-size: clamp(22px,2.5vw,29px);
    font-weight: 800;
    overflow-wrap: anywhere;
}

.card-note {
    color: #71889d;
    font-size: 11px;
    margin-top: 9px;
}

/* ACTION BUTTONS */

.actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 13px 19px;
    border-radius: 10px;
    text-decoration: none;
    color: #041b17;
    background: linear-gradient(135deg,#00e69a,#00bd87);
    font-size: 12px;
    font-weight: 800;
    border: 1px solid transparent;
    cursor: pointer;
    transition: .3s;
}

.action-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 22px rgba(0,230,154,.2);
}

.action-btn.secondary {
    color: #d8e9f5;
    background: #14283b;
    border-color: var(--border);
}

.action-btn.secondary:hover {
    border-color: var(--green);
    color: var(--green);
}

.action-btn.export {
    color: #dffaff;
    background: linear-gradient(135deg,#176da8,#15548d);
}

.action-btn.export:hover {
    box-shadow: 0 8px 22px rgba(37,217,239,.15);
}

/* FILTER CARD AND TABLE CARD */

.filter-card,
.table-card {
    background: linear-gradient(145deg,#102136,#0c1b2c);
    padding: 27px;
    border-radius: 19px;
    border: 1px solid var(--border);
    box-shadow: 0 10px 30px rgba(0,0,0,.14);
    margin-bottom: 25px;
}

.section-heading {
    margin-bottom: 23px;
}

.section-heading h2 {
    color: #e8f6ff;
    font-size: 19px;
    font-weight: 750;
}

.section-heading p {
    color: var(--muted);
    font-size: 12px;
    margin-top: 7px;
}

/* FILTER FORM */

.filter-form {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: end;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 9px;
    flex: 1;
    min-width: 190px;
}

.form-group label {
    font-size: 12px;
    color: #a9c3d5;
    font-weight: 700;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid #294158;
    border-radius: 10px;
    font-size: 13px;
    background: #081727;
    color: #e9f5ff;
    outline: none;
    transition: .3s;
}

.form-group input::placeholder {
    color: #70869b;
}

.form-group input:focus,
.form-group select:focus {
    border-color: var(--green);
    box-shadow: 0 0 0 3px rgba(0,230,154,.10);
}

.form-group select option {
    background: #102136;
    color: white;
}

.filter-btn {
    padding: 14px 22px;
    border: none;
    border-radius: 10px;
    background: linear-gradient(135deg,#00e69a,#00bd87);
    color: #041b17;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    transition: .3s;
}

.filter-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 7px 20px rgba(0,230,154,.18);
}

.reset-link {
    padding: 14px 12px;
    color: var(--cyan);
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
}

.reset-link:hover {
    color: var(--green);
}

/* TABLE */

.table-wrapper {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid var(--border);
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1800px;
}

th, td {
    padding: 16px 13px;
    text-align: center;
    border-bottom: 1px solid rgba(148,180,205,.10);
    font-size: 12px;
    white-space: nowrap;
}

th {
    background: #142b40;
    color: var(--green);
    font-weight: 800;
    position: sticky;
    top: 0;
    z-index: 2;
    letter-spacing: .3px;
}

td {
    color: #afc1d0;
}

tbody tr {
    background: rgba(255,255,255,.01);
    transition: .2s;
}

tbody tr:nth-child(even) {
    background: rgba(255,255,255,.025);
}

tbody tr:hover {
    background: rgba(0,230,154,.055);
}

.amount {
    color: var(--green);
    font-weight: 800;
}

.units {
    color: var(--cyan);
    font-weight: 800;
}

.appliance-details {
    text-align: left;
    line-height: 2;
    white-space: normal;
    min-width: 185px;
}

.appliance-details span {
    color: var(--green);
    font-weight: 700;
}

/* DELETE BUTTON */

.delete-btn {
    background: rgba(255,65,85,.10);
    color: #ff8795;
    border: 1px solid rgba(255,65,85,.25);
    padding: 10px 15px;
    border-radius: 9px;
    font-size: 11px;
    font-weight: 800;
    cursor: pointer;
    transition: .3s;
}

.delete-btn:hover {
    background: #d52e45;
    color: white;
    border-color: #d52e45;
    transform: translateY(-2px);
}

/* EMPTY STATE */

.empty {
    text-align: center;
    padding: 65px 20px;
    color: var(--muted);
    background: rgba(255,255,255,.02);
    border: 1px dashed #30495e;
    border-radius: 15px;
}

.empty-icon {
    font-size: 52px;
    margin-bottom: 18px;
    opacity: .8;
}

.empty h3 {
    color: #e6f4ff;
    margin-bottom: 10px;
    font-size: 21px;
}

.empty p {
    font-size: 13px;
    line-height: 1.7;
    margin-bottom: 22px;
}

.empty a {
    display: inline-block;
    padding: 13px 20px;
    background: linear-gradient(135deg,#00e69a,#00bd87);
    color: #041b17;
    text-decoration: none;
    border-radius: 9px;
    font-size: 12px;
    font-weight: 800;
}

/* NOTE */

.note {
    color: #8da4b8;
    font-size: 12px;
    line-height: 1.8;
    margin-top: 20px;
    background: rgba(37,217,239,.045);
    border: 1px solid rgba(37,217,239,.13);
    border-radius: 11px;
    padding: 16px;
}

.note strong {
    color: var(--cyan);
}

/* FOOTER */

.footer {
    text-align: center;
    padding: 28px 15px;
    color: #70869b;
    font-size: 12px;
    border-top: 1px solid var(--border);
    margin-top: 45px;
}

/* SCROLLBAR */

.table-wrapper::-webkit-scrollbar {
    height: 9px;
}

.table-wrapper::-webkit-scrollbar-track {
    background: #091727;
    border-radius: 10px;
}

.table-wrapper::-webkit-scrollbar-thumb {
    background: #28506a;
    border-radius: 10px;
}

.table-wrapper::-webkit-scrollbar-thumb:hover {
    background: var(--green);
}

/* ANIMATIONS */

@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.page-banner,
.summary-card,
.filter-card,
.table-card {
    animation: fadeUp .6s ease both;
}

.summary-card:nth-child(2) {
    animation-delay: .08s;
}

.summary-card:nth-child(3) {
    animation-delay: .16s;
}

.summary-card:nth-child(4) {
    animation-delay: .24s;
}

/* RESPONSIVE */

@media(max-width:1100px) {
    .summary {
        grid-template-columns: repeat(2,1fr);
    }

    .nav-links {
        gap: 14px;
    }
}

@media(max-width:750px) {
    .navbar {
        padding: 17px 20px;
    }

    .container {
        padding: 0 16px;
        margin: 25px auto;
    }

    .page-banner {
        padding: 32px 23px;
    }

    .banner-icon {
        right: 12px;
        top: 25px;
        font-size: 65px;
    }

    .page-banner p {
        max-width: 90%;
    }

    .filter-card,
    .table-card {
        padding: 20px;
    }

    .filter-form {
        align-items: stretch;
    }
}

@media(max-width:550px) {
    .navbar {
        align-items: flex-start;
    }

    .nav-links {
        gap: 13px;
    }

    .nav-links a {
        font-size: 11px;
    }

    .summary {
        grid-template-columns: 1fr;
    }

    .summary-card {
        padding: 22px;
    }

    .page-banner h1 {
        font-size: 25px;
    }

    .page-banner p {
        font-size: 12px;
    }

    .filter-form {
        flex-direction: column;
    }

    .form-group {
        min-width: 100%;
    }

    .actions {
        flex-direction: column;
    }

    .action-btn {
        width: 100%;
    }

    .banner-icon {
        opacity: .08;
    }
}
</style>
</head>

<body>

<!-- NAVIGATION -->
<nav class="navbar">

    <div class="logo">
        ⚡ Watt<span>Wise</span>
    </div>

    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="bills/upload_bill.php">Upload Bill</a>
        <a href="bills/my_bills.php">My Bills</a>
        <a href="predict_bill.php">Predict Bill</a>
        <a href="my_predictions.php" class="active">My Predictions</a>
        <a href="analytics.php">Analytics</a>
        <a href="logout.php" class="logout">Logout ↗</a>
    </div>

</nav>

<main class="container">

    <!-- HERO -->
    <section class="page-banner">

        <span class="banner-tag">SMART ENERGY ANALYTICS</span>

        <h1>My Predictions ⚡</h1>

        <p>
            Track your electricity bill predictions, review estimated
            consumption, filter your prediction history and export
            your records whenever you need them.
        </p>

        <div class="banner-icon">📊</div>

    </section>

    <!-- ALERTS -->
    <?php if ($error): ?>
        <div class="error">
            ⚠️ <?= h($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success">
            ✓ <?= h($success) ?>
        </div>
    <?php endif; ?>

    <!-- SUMMARY CARDS -->
    <section class="summary">

        <div class="summary-card">
            <div class="card-top">
                <div class="card-icon green">🤖</div>
            </div>
            <p class="card-label">Total Predictions</p>
            <h2><?= $totalPredictions ?></h2>
            <p class="card-note">Your generated predictions</p>
        </div>

        <div class="summary-card">
            <div class="card-top">
                <div class="card-icon blue">💰</div>
            </div>
            <p class="card-label">Total Predicted Amount</p>
            <h2>₹<?= number_format($totalAmount, 2) ?></h2>
            <p class="card-note">Combined predicted bill amount</p>
        </div>

        <div class="summary-card">
            <div class="card-top">
                <div class="card-icon orange">📈</div>
            </div>
            <p class="card-label">Average Predicted Bill</p>
            <h2>₹<?= number_format($averageAmount, 2) ?></h2>
            <p class="card-note">Average amount per prediction</p>
        </div>

        <div class="summary-card">
            <div class="card-top">
                <div class="card-icon purple">⚡</div>
            </div>
            <p class="card-label">Total Estimated Units</p>
            <h2><?= number_format($totalUnits, 2) ?></h2>
            <p class="card-note">Total estimated consumption in kWh</p>
        </div>

    </section>

    <!-- ACTION BUTTONS -->
    <div class="actions">

        <a href="predict_bill.php" class="action-btn">
            + New Prediction
        </a>

        <a href="dashboard.php" class="action-btn secondary">
            ← Dashboard
        </a>

        <a href="analytics.php" class="action-btn secondary">
            📊 Analytics
        </a>

        <a
            class="action-btn export"
            href="?<?= h(http_build_query([
                'month' => $monthFilter,
                'city' => $cityFilter,
                'export' => 'csv'
            ])) ?>"
        >
            ↓ Export CSV
        </a>

    </div>

    <!-- FILTER SECTION -->
    <section class="filter-card">

        <div class="section-heading">
            <h2>🔍 Search & Filter Predictions</h2>
            <p>Find your predictions by month or city.</p>
        </div>

        <form method="GET" class="filter-form">

            <div class="form-group">
                <label for="month">Select Month</label>

                <select name="month" id="month">
                    <option value="0">All Months</option>

                    <?php foreach ($months as $number => $name): ?>
                        <option
                            value="<?= $number ?>"
                            <?= $monthFilter === $number ? 'selected' : '' ?>
                        >
                            <?= h($name) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div class="form-group">
                <label for="city">Search City</label>

                <input
                    type="text"
                    name="city"
                    id="city"
                    maxlength="100"
                    placeholder="Enter city name"
                    value="<?= h($cityFilter) ?>"
                >
            </div>

            <button type="submit" class="filter-btn">
                Search Predictions
            </button>

            <a href="my_predictions.php" class="reset-link">
                Reset Filters ↻
            </a>

        </form>

    </section>

    <!-- PREDICTION HISTORY -->
    <section class="table-card">

        <div class="section-heading">
            <h2>📋 Prediction History</h2>
            <p>
                Showing <?= $totalPredictions ?> prediction record(s)
                matching your filters.
            </p>
        </div>

        <?php if (count($predictions) > 0): ?>

            <div class="table-wrapper">

                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Month</th>
                            <th>City</th>
                            <th>Company</th>
                            <th>Appliance Quantities</th>
                            <th>Daily Usage Hours</th>
                            <th>Estimated Units</th>
                            <th>Tariff Rate</th>
                            <th>Predicted Bill</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($predictions as $index => $item): ?>

                        <tr>

                            <td><?= $index + 1 ?></td>

                            <td>
                                <?= h(date(
                                    "d-m-Y",
                                    strtotime($item['created_at'])
                                )) ?>
                            </td>

                            <td>
                                <?= h($months[(int)$item['month']] ?? "Unknown") ?>
                            </td>

                            <td><?= h($item['city']) ?></td>

                            <td><?= h($item['company']) ?></td>

                            <td class="appliance-details">
                                Fan: <span><?= (int)$item['fan'] ?></span><br>
                                Refrigerator:
                                <span><?= (int)$item['refrigerator'] ?></span><br>
                                AC:
                                <span><?= (int)$item['air_conditioner'] ?></span><br>
                                TV:
                                <span><?= (int)$item['television'] ?></span><br>
                                Monitor:
                                <span><?= (int)$item['monitor'] ?></span><br>
                                Motor Pump:
                                <span><?= (int)$item['motor_pump'] ?></span>
                            </td>

                            <td class="appliance-details">
                                Fan:
                                <span><?= number_format((float)$item['fan_daily_hours'], 2) ?></span> hrs<br>

                                Refrigerator:
                                <span><?= number_format((float)$item['refrigerator_daily_hours'], 2) ?></span> hrs<br>

                                AC:
                                <span><?= number_format((float)$item['air_conditioner_daily_hours'], 2) ?></span> hrs<br>

                                TV:
                                <span><?= number_format((float)$item['television_daily_hours'], 2) ?></span> hrs<br>

                                Monitor:
                                <span><?= number_format((float)$item['monitor_daily_hours'], 2) ?></span> hrs<br>

                                Motor Pump:
                                <span><?= number_format((float)$item['motor_pump_daily_hours'], 2) ?></span> hrs
                            </td>

                            <td class="units">
                                <?= number_format(
                                    (float)$item['estimated_units'],
                                    2
                                ) ?> kWh
                            </td>

                            <td>
                                ₹<?= number_format(
                                    (float)$item['tariff_rate'],
                                    2
                                ) ?>
                            </td>

                            <td class="amount">
                                ₹<?= number_format(
                                    (float)$item['predicted_bill'],
                                    2
                                ) ?>
                            </td>

                            <td>
                                <form
                                    method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this prediction?');"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="prediction_id"
                                        value="<?= (int)$item['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= h($csrf_token) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="delete-btn"
                                    >
                                        🗑 Delete
                                    </button>

                                </form>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>
                </table>

            </div>

            <div class="note">
                <strong>Note:</strong>
                Appliance wattages are illustrative assumptions.
                Estimated units are calculated from appliance usage inputs.
                Predictions are project demonstrations using synthetic
                training data and are not guaranteed actual electricity bills.
            </div>

        <?php else: ?>

            <div class="empty">

                <div class="empty-icon">📊</div>

                <h3>No Predictions Found</h3>

                <p>
                    No records match your selected filters.
                    Generate a new electricity bill prediction to get started.
                </p>

                <a href="predict_bill.php">
                    + Predict Your Bill
                </a>

            </div>

        <?php endif; ?>

    </section>

</main>

<footer class="footer">
    © 2026 VoltVision | Smart Energy. Smart Savings. Better Tomorrow.
</footer>

</body>
</html>
