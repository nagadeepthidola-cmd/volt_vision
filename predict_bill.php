
<?php
session_start();
require_once __DIR__ . "/config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Check config/db.php");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

$months = [
    1 => "January", 2 => "February", 3 => "March",
    4 => "April", 5 => "May", 6 => "June",
    7 => "July", 8 => "August", 9 => "September",
    10 => "October", 11 => "November", 12 => "December"
];

$appliances = [
    "fan" => [
        "label" => "Fans",
        "model" => "Fan",
        "hours_key" => "fan_daily_hours",
        "model_hours" => "FanDailyHours",
        "watts" => 75,
        "icon" => "🌀"
    ],
    "refrigerator" => [
        "label" => "Refrigerators",
        "model" => "Refrigerator",
        "hours_key" => "refrigerator_daily_hours",
        "model_hours" => "RefrigeratorDailyHours",
        "watts" => 150,
        "icon" => "🧊"
    ],
    "air_conditioner" => [
        "label" => "Air Conditioners",
        "model" => "AirConditioner",
        "hours_key" => "air_conditioner_daily_hours",
        "model_hours" => "AirConditionerDailyHours",
        "watts" => 1500,
        "icon" => "❄️"
    ],
    "television" => [
        "label" => "Televisions",
        "model" => "Television",
        "hours_key" => "television_daily_hours",
        "model_hours" => "TelevisionDailyHours",
        "watts" => 100,
        "icon" => "📺"
    ],
    "monitor" => [
        "label" => "Monitors",
        "model" => "Monitor",
        "hours_key" => "monitor_daily_hours",
        "model_hours" => "MonitorDailyHours",
        "watts" => 50,
        "icon" => "🖥️"
    ],
    "motor_pump" => [
        "label" => "Motor Pumps",
        "model" => "MotorPump",
        "hours_key" => "motor_pump_daily_hours",
        "model_hours" => "MotorPumpDailyHours",
        "watts" => 750,
        "icon" => "💧"
    ]
];

$values = [
    "fan" => "0",
    "refrigerator" => "0",
    "air_conditioner" => "0",
    "television" => "0",
    "monitor" => "0",
    "motor_pump" => "0",

    "fan_daily_hours" => "8",
    "refrigerator_daily_hours" => "8",
    "air_conditioner_daily_hours" => "0",
    "television_daily_hours" => "4",
    "monitor_daily_hours" => "5",
    "motor_pump_daily_hours" => "1",

    "month" => (string) date("n"),
    "city" => "",
    "company" => "",
    "tariff_rate" => "8.4"
];

$prediction = null;
$estimated_units = null;
$monthlyHours = null;
$error = "";
$save_success = false;
$prediction_id = null;

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function validNumber($value) {
    return is_scalar($value) &&
        $value !== "" &&
        is_numeric($value) &&
        is_finite((float)$value);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Preserve submitted values safely.
    foreach ($values as $key => $default) {
        if (isset($_POST[$key]) && is_scalar($_POST[$key])) {
            $values[$key] = trim((string)$_POST[$key]);
        }
    }

    // CSRF validation.
    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !hash_equals($csrf_token, $submittedToken)
    ) {
        $error = "Invalid form request. Please refresh the page and try again.";
    }

    // Appliance validation.
    if (!$error) {
        foreach ($appliances as $key => $appliance) {

            $quantity = $values[$key];
            $hoursKey = $appliance["hours_key"];
            $hours = $values[$hoursKey];

            if (
                !validNumber($quantity) ||
                (float)$quantity < 0 ||
                (float)$quantity > 50 ||
                floor((float)$quantity) != (float)$quantity
            ) {
                $error = $appliance["label"] .
                    " quantity must be a whole number between 0 and 50.";
                break;
            }

            if (
                !validNumber($hours) ||
                (float)$hours < 0 ||
                (float)$hours > 24
            ) {
                $error = $appliance["label"] .
                    " daily usage must be between 0 and 24 hours.";
                break;
            }
        }
    }

    // Month validation.
    if (!$error) {
        if (
            !validNumber($values["month"]) ||
            floor((float)$values["month"]) != (float)$values["month"] ||
            (int)$values["month"] < 1 ||
            (int)$values["month"] > 12
        ) {
            $error = "Please select a valid month.";
        }
    }

    // Tariff validation.
    if (!$error) {
        if (
            !validNumber($values["tariff_rate"]) ||
            (float)$values["tariff_rate"] <= 0 ||
            (float)$values["tariff_rate"] > 100
        ) {
            $error = "Tariff rate must be greater than 0 and not more than 100.";
        }
    }

    // City and company validation.
    if (!$error) {
        $city = trim($values["city"]);
        $company = trim($values["company"]);

        if ($city === "" || $company === "") {
            $error = "Please enter your city and electricity company.";
        } elseif (
            mb_strlen($city) > 100 ||
            mb_strlen($company) > 100
        ) {
            $error = "City and electricity company names must not exceed 100 characters.";
        } elseif (
            preg_match('/[\x00-\x1F\x7F]/', $city) ||
            preg_match('/[\x00-\x1F\x7F]/', $company)
        ) {
            $error = "City and company names contain invalid characters.";
        }
    }

    if (!$error) {

        $month = (int)$values["month"];
        $year = (int)date("Y");

        $daysInMonth = cal_days_in_month(
            CAL_GREGORIAN,
            $month,
            $year
        );

        $city = trim($values["city"]);
        $company = trim($values["company"]);
        $tariff = (float)$values["tariff_rate"];

        $inputData = [
            "Month" => $month,
            "DaysInMonth" => $daysInMonth,
            "City" => $city,
            "Company" => $company,
            "TariffRate" => $tariff
        ];

        $monthlyHours = 0.0;
        $estimated_units = 0.0;
        $totalQuantity = 0;

        $quantities = [];
        $dailyHoursValues = [];

        foreach ($appliances as $key => $appliance) {

            $quantity = (int)$values[$key];
            $hours = (float)$values[$appliance["hours_key"]];
            $watts = (float)$appliance["watts"];

            $quantities[$key] = $quantity;
            $dailyHoursValues[$key] = $hours;

            $inputData[$appliance["model"]] = $quantity;
            $inputData[$appliance["model_hours"]] = $hours;

            $totalQuantity += $quantity;

            // Monthly operating hours across appliance types.
            $monthlyHours += $hours * $daysInMonth;

            // Monthly energy consumption in kWh.
            $estimated_units += (
                $quantity *
                $watts *
                $hours *
                $daysInMonth
            ) / 1000;
        }

        if ($totalQuantity <= 0) {
            $error = "Please enter at least one appliance quantity.";
        }

        if (!$error && $estimated_units <= 0) {
            $error = "Your estimated electricity usage is zero. Please enter valid daily usage hours.";
        }

        if (!$error && !is_finite($estimated_units)) {
            $error = "Unable to calculate electricity consumption. Please check your inputs.";
        }

        if (!$error) {

            // Calculate the final amount using the entered flat tariff.
            // Do not use the ML model's predicted_bill as the payable estimate.
            $prediction = round($estimated_units * $tariff, 2);

            if (!is_finite($prediction) || $prediction < 0) {
                $error = "Unable to calculate the estimated bill amount. Please check your tariff rate.";
                $prediction = null;
            } else {

                // Save the calculated amount and usage in prediction history.
                try {

                    $stmt = $conn->prepare(
                        "INSERT INTO predictions (
                            user_id,
                            fan,
                            refrigerator,
                            air_conditioner,
                            television,
                            monitor,
                            motor_pump,
                            month,
                            city,
                            company,
                            monthly_hours,
                            tariff_rate,
                            predicted_bill,
                            fan_daily_hours,
                            refrigerator_daily_hours,
                            air_conditioner_daily_hours,
                            television_daily_hours,
                            monitor_daily_hours,
                            motor_pump_daily_hours,
                            estimated_units
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );

                    if (!$stmt) {
                        throw new Exception("Database prepare failed: " . $conn->error);
                    }

                    $fan = $quantities["fan"];
                    $refrigerator = $quantities["refrigerator"];
                    $ac = $quantities["air_conditioner"];
                    $tv = $quantities["television"];
                    $monitor = $quantities["monitor"];
                    $pump = $quantities["motor_pump"];

                    $fh = $dailyHoursValues["fan"];
                    $rh = $dailyHoursValues["refrigerator"];
                    $ach = $dailyHoursValues["air_conditioner"];
                    $tvh = $dailyHoursValues["television"];
                    $mh = $dailyHoursValues["monitor"];
                    $ph = $dailyHoursValues["motor_pump"];

                    $stmt->bind_param(
                        "iiiiiiiissdddddddddd",
                        $user_id,
                        $fan,
                        $refrigerator,
                        $ac,
                        $tv,
                        $monitor,
                        $pump,
                        $month,
                        $city,
                        $company,
                        $monthlyHours,
                        $tariff,
                        $prediction,
                        $fh,
                        $rh,
                        $ach,
                        $tvh,
                        $mh,
                        $ph,
                        $estimated_units
                    );

                    if (!$stmt->execute()) {
                        throw new Exception("Database insert failed: " . $stmt->error);
                    }

                    $prediction_id = $stmt->insert_id;
                    $stmt->close();

                    $save_success = true;

                } catch (Throwable $e) {

                    error_log("VoltVision prediction save error: " . $e->getMessage());

                    $error = "Bill amount was calculated, but saving it to prediction history failed. Please check your predictions table structure.";
                    $prediction = null;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Predict Electricity Bill | VoltVision</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Arial, sans-serif;
}

:root {
    --bg: #07111f;
    --panel: #101d2d;
    --green: #00f59b;
    --cyan: #00d9ff;
    --text: #e9f4f5;
    --muted: #8da3b5;
    --border: #26394d;
}

body {
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
}

.navbar {
    background: #0b1726;
    border-bottom: 1px solid #203448;
    color: white;
    padding: 17px 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 18px;
    box-shadow: 0 5px 25px rgba(0,0,0,.25);
}

.logo {
    font-size: 26px;
    font-weight: 800;
}

.logo span {
    color: var(--green);
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.nav-links a {
    color: #b6c7d6;
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
    background: #1b3044;
    padding: 9px 15px;
    border-radius: 8px;
    color: #ff8585;
}

.container {
    max-width: 1150px;
    margin: 35px auto;
    padding: 0 22px;
}

.hero {
    background:
        radial-gradient(circle at 85% 15%, rgba(0,245,155,.18), transparent 30%),
        radial-gradient(circle at 10% 100%, rgba(0,217,255,.12), transparent 35%),
        linear-gradient(115deg, #101f32, #102d3b);
    color: white;
    padding: 38px;
    border: 1px solid #244356;
    border-radius: 22px;
    margin-bottom: 27px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 12px 35px rgba(0,0,0,.22);
}

.hero-tag {
    display: inline-block;
    background: rgba(0,245,155,.10);
    color: var(--green);
    border: 1px solid rgba(0,245,155,.3);
    border-radius: 30px;
    padding: 7px 13px;
    font-size: 10px;
    letter-spacing: 2px;
    font-weight: 700;
    margin-bottom: 14px;
}

.hero h1 {
    font-size: 31px;
    margin-bottom: 10px;
}

.hero h1 span {
    color: var(--green);
}

.hero p {
    color: #a8bfce;
    font-size: 14px;
    line-height: 1.8;
    max-width: 650px;
}

.hero-icon {
    position: absolute;
    right: 55px;
    top: 20px;
    font-size: 105px;
    opacity: .15;
}

.form-panel {
    background: var(--panel);
    padding: 30px;
    border-radius: 20px;
    border: 1px solid var(--border);
    box-shadow: 0 10px 35px rgba(0,0,0,.20);
}

.section-heading {
    display: flex;
    align-items: center;
    gap: 13px;
    margin-bottom: 22px;
}

.section-icon {
    width: 46px;
    height: 46px;
    background: rgba(0,245,155,.10);
    border: 1px solid rgba(0,245,155,.2);
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.section-heading h2 {
    font-size: 19px;
    color: #efffff;
}

.section-heading p {
    color: var(--muted);
    font-size: 12px;
    margin-top: 4px;
}

.section-divider {
    height: 1px;
    background: #26394d;
    margin: 28px 0;
}

.appliance-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px;
}

.appliance-card {
    border: 1px solid #293e52;
    background: linear-gradient(145deg, #142438, #101d2d);
    padding: 19px;
    border-radius: 15px;
    transition: .3s;
}

.appliance-card:hover {
    border-color: rgba(0,245,155,.65);
    transform: translateY(-3px);
    box-shadow: 0 9px 25px rgba(0,245,155,.06);
}

.appliance-header {
    display: flex;
    align-items: center;
    gap: 11px;
    margin-bottom: 17px;
}

.appliance-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: rgba(0,217,255,.10);
    border: 1px solid rgba(0,217,255,.16);
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 21px;
}

.appliance-header h3 {
    font-size: 15px;
    color: #eafff8;
}

.appliance-header p {
    color: var(--green);
    font-size: 11px;
    margin-top: 3px;
}

.input-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.form-group label {
    display: block;
    font-size: 12px;
    color: #b6c8d5;
    font-weight: 700;
    margin-bottom: 8px;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px;
    border: 1px solid #30465a;
    border-radius: 9px;
    font-size: 14px;
    color: #eaf7f7;
    background: #0b1726;
    outline: none;
    transition: .3s;
}

.form-group input:focus,
.form-group select:focus {
    border-color: var(--green);
    box-shadow: 0 0 0 3px rgba(0,245,155,.09);
}

input::placeholder {
    color: #71869a;
}

.hint {
    font-size: 11px;
    color: #8298aa;
    margin-top: 7px;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.detail-card {
    background: #142438;
    border: 1px solid #293e52;
    border-radius: 13px;
    padding: 17px;
    transition: .3s;
}

.detail-card:hover {
    border-color: rgba(0,217,255,.45);
}

.detail-card label {
    font-size: 12px;
    font-weight: 700;
    color: #b6c8d5;
    display: block;
    margin-bottom: 9px;
}

.detail-card input,
.detail-card select {
    width: 100%;
    padding: 12px;
    border: 1px solid #30465a;
    border-radius: 9px;
    background: #0b1726;
    color: #eaf7f7;
    font-size: 14px;
    outline: none;
}

.detail-card input:focus,
.detail-card select:focus {
    border-color: var(--cyan);
    box-shadow: 0 0 0 3px rgba(0,217,255,.09);
}

select option {
    background: #101d2d;
    color: white;
}

.predict-btn {
    width: 100%;
    padding: 16px;
    margin-top: 28px;
    border: none;
    border-radius: 11px;
    background: linear-gradient(110deg, #00d98b, #00bde8);
    color: #061622;
    font-size: 15px;
    font-weight: 900;
    cursor: pointer;
    transition: .3s;
    box-shadow: 0 7px 22px rgba(0,217,155,.15);
}

.predict-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 11px 28px rgba(0,217,155,.25);
}

.result {
    margin-top: 25px;
    padding: 30px;
    text-align: center;
    background:
        radial-gradient(circle at top right, rgba(0,245,155,.14), transparent 35%),
        linear-gradient(135deg, #142c32, #102438);
    border: 1px solid rgba(0,245,155,.4);
    border-radius: 17px;
    box-shadow: 0 8px 25px rgba(0,245,155,.05);
}

.result-label {
    color: var(--green);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1.5px;
}

.result h2 {
    color: #eafff8;
    font-size: 21px;
    margin: 12px 0;
}

.amount {
    font-size: 42px;
    font-weight: 900;
    color: var(--green);
    margin: 10px 0;
    text-shadow: 0 0 20px rgba(0,245,155,.18);
}

.units-box {
    display: inline-block;
    margin-top: 9px;
    background: #0b1b29;
    border: 1px solid #285044;
    padding: 12px 19px;
    border-radius: 10px;
    color: #c0e8d8;
    font-size: 13px;
}

.units-box strong {
    color: var(--cyan);
}

.result-note {
    color: #9bb9aa;
    font-size: 12px;
    margin-top: 15px;
    line-height: 1.7;
}

.error {
    margin-top: 22px;
    padding: 16px;
    background: rgba(255,70,90,.09);
    border: 1px solid rgba(255,100,110,.35);
    color: #ff9b9b;
    border-radius: 11px;
    font-size: 13px;
    line-height: 1.7;
    overflow-wrap: anywhere;
}

.info-note {
    margin-top: 23px;
    background: #142438;
    border-left: 4px solid var(--cyan);
    border-top: 1px solid #293e52;
    border-right: 1px solid #293e52;
    border-bottom: 1px solid #293e52;
    padding: 16px 18px;
    border-radius: 8px;
    color: #9eb2c2;
    font-size: 11px;
    line-height: 1.9;
}

.info-note strong {
    color: var(--cyan);
}

.links {
    display: flex;
    justify-content: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-top: 25px;
}

.links a {
    text-decoration: none;
    color: var(--green);
    background: #142b30;
    border: 1px solid #285044;
    padding: 11px 17px;
    border-radius: 9px;
    font-size: 12px;
    font-weight: 700;
    transition: .3s;
}

.links a:hover {
    background: #1b3d3c;
    border-color: var(--green);
    transform: translateY(-2px);
}

.footer {
    text-align: center;
    padding: 25px;
    color: #71869a;
    font-size: 12px;
    border-top: 1px solid #1c3043;
    margin-top: 35px;
}

@media(max-width: 850px) {
    .nav-links {
        gap: 13px;
    }

    .hero-icon {
        right: 20px;
        font-size: 75px;
    }
}

@media(max-width: 650px) {
    .navbar {
        padding: 17px 20px;
    }

    .nav-links {
        gap: 12px;
    }

    .nav-links a {
        font-size: 11px;
    }

    .container {
        padding: 0 13px;
        margin: 22px auto;
    }

    .hero {
        padding: 27px 22px;
    }

    .hero h1 {
        font-size: 24px;
    }

    .hero-icon {
        font-size: 55px;
        right: 12px;
        top: 15px;
    }

    .form-panel {
        padding: 20px 15px;
    }

    .appliance-grid,
    .details-grid {
        grid-template-columns: 1fr;
    }
}

@media(max-width: 400px) {
    .input-row {
        grid-template-columns: 1fr;
    }

    .hero h1 {
        font-size: 21px;
    }

    .amount {
        font-size: 32px;
    }
}
</style>
</head>

<body>

<nav class="navbar">
    <div class="logo">⚡ Watt<span>Wise</span></div>

    <div class="nav-links">
        <a href="dashboard.php">Dashboard</a>
        <a href="bills/upload_bill.php">Upload Bill</a>
        <a href="bills/my_bills.php">My Bills</a>
        <a href="predict_bill.php" class="active">Predict Bill</a>
        <a href="my_predictions.php">My Predictions</a>
        <a href="analytics.php">Analytics</a>
        <a href="logout.php" class="logout">Logout ↗</a>
    </div>
</nav>

<main class="container">

    <section class="hero">
        <span class="hero-tag">AI POWERED ENERGY ANALYSIS</span>

        <h1>Predict Your <span>Electricity Bill</span> ⚡</h1>

        <p>
            Enter your household appliance details and electricity tariff.
            VoltVision calculates your estimated monthly consumption and
            bill amount using the appliance wattages and tariff rate you provide.
        </p>

        <div class="hero-icon">⚡</div>
    </section>

    <section class="form-panel">

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= h($csrf_token) ?>"
            >

            <div class="section-heading">
                <div class="section-icon">🏠</div>
                <div>
                    <h2>Appliance Details</h2>
                    <p>Enter the quantity and average daily usage of each appliance.</p>
                </div>
            </div>

            <div class="appliance-grid">

                <?php foreach ($appliances as $key => $appliance): ?>

                    <div class="appliance-card">

                        <div class="appliance-header">
                            <div class="appliance-icon">
                                <?= h($appliance["icon"]) ?>
                            </div>

                            <div>
                                <h3><?= h($appliance["label"]) ?></h3>
                                <p>Power: <?= (int)$appliance["watts"] ?> Watts</p>
                            </div>
                        </div>

                        <div class="input-row">

                            <div class="form-group">
                                <label>Quantity</label>

                                <input
                                    type="number"
                                    name="<?= h($key) ?>"
                                    min="0"
                                    max="50"
                                    step="1"
                                    required
                                    value="<?= h($values[$key]) ?>"
                                >
                            </div>

                            <div class="form-group">
                                <label>Daily Hours</label>

                                <input
                                    type="number"
                                    name="<?= h($appliance["hours_key"]) ?>"
                                    min="0"
                                    max="24"
                                    step="0.25"
                                    required
                                    value="<?= h($values[$appliance["hours_key"]]) ?>"
                                >
                            </div>

                        </div>

                        <p class="hint">
                            Average power consumption per appliance
                        </p>

                    </div>

                <?php endforeach; ?>

            </div>

            <div class="section-divider"></div>

            <div class="section-heading">
                <div class="section-icon">🔌</div>
                <div>
                    <h2>Electricity Details</h2>
                    <p>Provide your location and electricity tariff information.</p>
                </div>
            </div>

            <div class="details-grid">

                <div class="detail-card">
                    <label>📅 Select Month</label>

                    <select name="month" required>
                        <?php foreach ($months as $n => $name): ?>
                            <option
                                value="<?= $n ?>"
                                <?= ((int)$values["month"] === $n) ? "selected" : "" ?>
                            >
                                <?= h($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="detail-card">
                    <label>📍 City</label>

                    <input
                        type="text"
                        name="city"
                        maxlength="100"
                        required
                        placeholder="Enter your city"
                        value="<?= h($values["city"]) ?>"
                    >
                </div>

                <div class="detail-card">
                    <label>🏢 Electricity Company</label>

                    <input
                        type="text"
                        name="company"
                        maxlength="100"
                        required
                        placeholder="Enter electricity company"
                        value="<?= h($values["company"]) ?>"
                    >
                </div>

                <div class="detail-card">
                    <label>💰 Tariff Rate (₹ per unit)</label>

                    <input
                        type="number"
                        name="tariff_rate"
                        min="0.01"
                        max="100"
                        step="0.01"
                        required
                        value="<?= h($values["tariff_rate"]) ?>"
                    >
                </div>

            </div>

            <button type="submit" class="predict-btn">
                ⚡ Predict My Electricity Bill
            </button>

        </form>

        <?php if ($prediction !== null && $save_success && !$error): ?>

            <div class="result">

                <p class="result-label">YOUR PREDICTION IS READY</p>

                <h2>Estimated Monthly Electricity Bill</h2>

                <div class="amount">
                    ₹<?= number_format($prediction, 2) ?>
                </div>

                <div class="units-box">
                    ⚡ Estimated Consumption:
                    <strong>
                        <?= number_format($estimated_units, 2) ?> kWh
                    </strong>
                </div>

                <p class="result-note">
                    ✓ Prediction generated successfully.<br>
                    ✓ Prediction saved to your history.
                    <?php if ($prediction_id !== null): ?>
                        <br>Prediction ID: #<?= (int)$prediction_id ?>
                    <?php endif; ?>
                </p>

            </div>

        <?php endif; ?>

        <?php if ($error): ?>

            <div class="error">
                <strong>⚠ Error:</strong><br>
                <?= nl2br(h($error)) ?>
            </div>

        <?php endif; ?>

        <div class="info-note">
            <strong>ℹ Important Note:</strong>
            The estimated amount is calculated as estimated units multiplied
            by the flat tariff rate you enter. This estimate does not include
            fixed charges, taxes, subsidies, or slab-based tariff changes.
            Appliance wattages are illustrative assumptions, so actual usage
            and your official electricity bill may differ.
        </div>

        <div class="links">
            <a href="dashboard.php">← Back to Dashboard</a>
            <a href="my_predictions.php">View My Predictions →</a>
            <a href="analytics.php">View Analytics ↗</a>
        </div>

    </section>

</main>

<footer class="footer">
    © 2026 VoltVision | Smart Energy. Smart Savings. Better Tomorrow.
</footer>

</body>
</html>
