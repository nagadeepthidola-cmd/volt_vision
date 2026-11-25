
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/config/db.php";

date_default_timezone_set("Asia/Kolkata");

$userId = (int)$_SESSION["user_id"];
$userName = $_SESSION["user_name"] ?? "User";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// BILL SUMMARY
$sql = "SELECT
            COUNT(*) AS total_bills,
            COALESCE(SUM(units_kwh), 0) AS total_units,
            COALESCE(SUM(amount), 0) AS total_amount
        FROM bills
        WHERE user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalBills = (int)$summary["total_bills"];
$totalUnits = (float)$summary["total_units"];
$totalAmount = (float)$summary["total_amount"];

// PREDICTION SUMMARY
$sql = "SELECT
            COUNT(*) AS total_predictions,
            COALESCE(SUM(predicted_bill), 0) AS total_predicted_amount
        FROM predictions
        WHERE user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$predictionSummary = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalPredictions = (int)$predictionSummary["total_predictions"];
$totalPredictedAmount = (float)$predictionSummary["total_predicted_amount"];

// MONTHLY BILL DATA - LAST 6 MONTHS
$monthlyData = [];

for ($i = 5; $i >= 0; $i--) {
    $date = new DateTime("first day of this month");
    $date->modify("-" . $i . " months");

    $key = $date->format("Y-m");

    $monthlyData[$key] = [
        "label" => $date->format("M Y"),
        "units" => 0,
        "amount" => 0
    ];
}

$sql = "SELECT
            DATE_FORMAT(bill_date, '%Y-%m') AS month_key,
            SUM(units_kwh) AS units,
            SUM(amount) AS amount
        FROM bills
        WHERE user_id = ?
          AND bill_date >= DATE_SUB(
              DATE_FORMAT(CURDATE(), '%Y-%m-01'),
              INTERVAL 5 MONTH
          )
          AND bill_date < DATE_ADD(
              DATE_FORMAT(CURDATE(), '%Y-%m-01'),
              INTERVAL 1 MONTH
          )
        GROUP BY DATE_FORMAT(bill_date, '%Y-%m')
        ORDER BY month_key ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $key = $row["month_key"];

    if (isset($monthlyData[$key])) {
        $monthlyData[$key]["units"] = (float)$row["units"];
        $monthlyData[$key]["amount"] = (float)$row["amount"];
    }
}

$stmt->close();

$monthlyData = array_values($monthlyData);

// MONTHLY COMPARISON
$comparisonSql = "SELECT
                    DATE_FORMAT(bill_date, '%Y-%m') AS month_key,
                    SUM(units_kwh) AS units,
                    SUM(amount) AS amount
                  FROM bills
                  WHERE user_id = ?
                    AND bill_date IS NOT NULL
                  GROUP BY DATE_FORMAT(bill_date, '%Y-%m')
                  ORDER BY month_key DESC
                  LIMIT 2";

$stmt = $conn->prepare($comparisonSql);
$stmt->bind_param("i", $userId);
$stmt->execute();

$comparisonResult = $stmt->get_result();
$comparisonRows = [];

while ($row = $comparisonResult->fetch_assoc()) {
    $comparisonRows[] = $row;
}

$stmt->close();

$currentMonthData = $comparisonRows[0] ?? null;
$previousMonthData = $comparisonRows[1] ?? null;

$billDifference = null;
$unitDifference = null;
$amountChangePercent = null;
$unitChangePercent = null;

if ($currentMonthData && $previousMonthData) {

    $billDifference =
        (float)$currentMonthData["amount"] -
        (float)$previousMonthData["amount"];

    $unitDifference =
        (float)$currentMonthData["units"] -
        (float)$previousMonthData["units"];

    $amountChangePercent = (float)$previousMonthData["amount"] > 0
        ? ($billDifference / (float)$previousMonthData["amount"]) * 100
        : null;

    $unitChangePercent = (float)$previousMonthData["units"] > 0
        ? ($unitDifference / (float)$previousMonthData["units"]) * 100
        : null;
}

// DUE DATE REMINDERS
$sql = "SELECT id, due_date, bill_date, amount, units_kwh
        FROM bills
        WHERE user_id = ?
          AND due_date IS NOT NULL
          AND due_date != '0000-00-00'
        ORDER BY due_date ASC
        LIMIT 20";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();

$dueResult = $stmt->get_result();
$dueBills = [];

$today = new DateTime("today");

while ($row = $dueResult->fetch_assoc()) {

    try {
        $dueDate = new DateTime($row["due_date"]);
    } catch (Exception $e) {
        continue;
    }

    $daysRemaining = (int)$today->diff($dueDate)->format("%r%a");

    $row["days_remaining"] = $daysRemaining;
    $row["due_timestamp"] = $dueDate->getTimestamp();

    $dueBills[] = $row;
}

$stmt->close();

// FIND UPCOMING OR OVERDUE BILLS
$activeDueBills = array_filter(
    $dueBills,
    function ($bill) {
        return $bill["days_remaining"] <= 3;
    }
);

// GRAPH MAXIMUM
$maxUnits = 0;

foreach ($monthlyData as $month) {
    $maxUnits = max($maxUnits, $month["units"]);
}

$maxUnits = $maxUnits > 0 ? $maxUnits : 1;

// GENERAL SAVING SUGGESTIONS
$savingTips = [
    [
        "icon" => "❄️",
        "title" => "Use AC efficiently",
        "description" => "Set your AC around 24–26°C and clean its filters regularly."
    ],
    [
        "icon" => "💡",
        "title" => "Switch to LED lighting",
        "description" => "Use energy-efficient LED bulbs and switch off unused lights."
    ],
    [
        "icon" => "🔌",
        "title" => "Avoid standby power",
        "description" => "Switch off and unplug appliances when they are not being used."
    ],
    [
        "icon" => "🌀",
        "title" => "Use fans wisely",
        "description" => "Switch off fans when leaving a room and use natural ventilation when possible."
    ]
];

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>VoltVision | Smart Energy Dashboard</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>

:root {
    --bg: #020b1c;
    --panel: #071b35;
    --panel2: #0b2542;
    --green: #00f5a0;
    --blue: #00bfff;
    --text: #f4faff;
    --muted: #a7bdd3;
    --border: rgba(0,210,255,.25);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', sans-serif;
}

html {
    scroll-behavior: smooth;
}

body {
    background:
        radial-gradient(circle at 15% 15%, rgba(0,190,255,.10), transparent 30%),
        radial-gradient(circle at 90% 75%, rgba(0,245,160,.08), transparent 30%),
        var(--bg);
    color: var(--text);
    min-height: 100vh;
    overflow-x: hidden;
}

/* HEADER */

.header {
    height: 82px;
    padding: 0 4%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(2,13,31,.92);
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    z-index: 100;
    backdrop-filter: blur(15px);
}

.logo {
    font-size: 30px;
    font-weight: 900;
    letter-spacing: -1.5px;
}

.logo .bolt {
    color: var(--green);
    margin-right: 8px;
}

.logo span {
    color: var(--green);
}

.menu-btn {
    width: 57px;
    height: 52px;
    border: 1px solid var(--green);
    background: rgba(0,245,160,.10);
    color: var(--green);
    border-radius: 15px;
    font-size: 28px;
    cursor: pointer;
    transition: .3s;
}

.menu-btn:hover {
    background: var(--green);
    color: #021526;
}

/* SIDEBAR */

.overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.65);
    z-index: 199;
    opacity: 0;
    visibility: hidden;
    transition: .3s;
}

.overlay.active {
    opacity: 1;
    visibility: visible;
}

.sidebar {
    position: fixed;
    top: 0;
    right: -320px;
    width: 300px;
    height: 100vh;
    padding: 28px 22px;
    background: #06172e;
    border-left: 1px solid rgba(0,245,160,.4);
    z-index: 200;
    transition: right .35s ease;
}

.sidebar.active {
    right: 0;
}

.sidebar-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 35px;
}

.sidebar-top h2 {
    color: var(--green);
    font-size: 20px;
}

.close-btn {
    background: transparent;
    border: none;
    color: white;
    font-size: 28px;
    cursor: pointer;
}

.sidebar a {
    display: block;
    color: #dceafa;
    text-decoration: none;
    padding: 15px;
    border-radius: 11px;
    margin-bottom: 9px;
    font-size: 14px;
    font-weight: 600;
    transition: .25s;
}

.sidebar a:hover {
    color: var(--green);
    background: rgba(0,245,160,.09);
}

.sidebar .logout {
    color: #ff8585;
    margin-top: 25px;
}

/* PAGE */

.container {
    max-width: 1500px;
    padding: 24px 3%;
    margin: auto;
}

/* HERO */

.hero {
    min-height: 390px;
    padding: 42px 45px;
    display: grid;
    grid-template-columns: 1.1fr .9fr;
    align-items: center;
    gap: 20px;
    position: relative;
    overflow: hidden;
    border-radius: 27px;
    border: 1px solid rgba(0,205,255,.55);
    background:
        radial-gradient(circle at 75% 50%, rgba(0,150,255,.20), transparent 35%),
        radial-gradient(circle at 10% 100%, rgba(0,245,160,.12), transparent 35%),
        linear-gradient(115deg,#03152e,#062b43 65%,#032b2b);
    box-shadow: 0 0 30px rgba(0,180,255,.12);
}

.hero-content {
    position: relative;
    z-index: 2;
}

.tag {
    display: inline-block;
    color: var(--green);
    background: rgba(0,245,160,.10);
    border: 1px solid rgba(0,245,160,.35);
    padding: 10px 17px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.5px;
    margin-bottom: 25px;
}

.hero h1 {
    font-size: clamp(34px,4vw,57px);
    font-weight: 900;
    line-height: 1.13;
    letter-spacing: -2px;
    margin-bottom: 20px;
}

.hero h1 span {
    color: var(--green);
}

.hello {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 12px;
}

.hello span {
    color: var(--green);
}

.hero-description {
    color: #c1d4e7;
    line-height: 1.8;
    font-size: 14px;
    max-width: 520px;
}

/* HERO STATS */

.hero-stats {
    display: grid;
    grid-template-columns: repeat(4,minmax(0,1fr));
    gap: 10px;
    margin-top: 28px;
}

.stat {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 13px 10px;
    background: rgba(0,20,40,.58);
    border: 1px solid rgba(0,210,255,.32);
    border-radius: 16px;
    min-width: 0;
}

.stat-icon {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    border: 1px solid var(--green);
    color: var(--green);
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 20px;
}

.stat strong {
    display: block;
    font-size: 16px;
    overflow-wrap: anywhere;
}

.stat small {
    display: block;
    font-size: 9px;
    color: #b7c9da;
    margin-top: 5px;
}

/* ROBOT */

.robot-area {
    position: relative;
    z-index: 2;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 280px;
}

.robot-glow {
    position: absolute;
    width: 260px;
    height: 260px;
    border-radius: 50%;
    background: radial-gradient(circle,rgba(0,190,255,.25),transparent 70%);
}

.robot {
    position: relative;
    width: 190px;
    height: 220px;
    animation: float 3s ease-in-out infinite;
}

.antenna {
    position: absolute;
    width: 5px;
    height: 25px;
    background: #8beaff;
    top: -17px;
    left: 92px;
    border-radius: 5px;
}

.antenna::before {
    content: "";
    position: absolute;
    width: 15px;
    height: 15px;
    background: var(--blue);
    border-radius: 50%;
    top: -10px;
    left: -5px;
    box-shadow: 0 0 15px var(--blue);
}

.robot-head {
    position: absolute;
    top: 10px;
    left: 15px;
    width: 160px;
    height: 115px;
    border-radius: 43px;
    background: linear-gradient(145deg,#ffffff,#a6c9e5 45%,#477cae);
    border: 4px solid #9beaff;
    box-shadow: 0 0 25px rgba(0,190,255,.55);
    display: flex;
    justify-content: center;
    align-items: center;
}

.robot-face {
    width: 125px;
    height: 82px;
    border-radius: 30px;
    background: linear-gradient(135deg,#00142d,#061f42);
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 23px;
    position: relative;
}

.eye {
    width: 17px;
    height: 25px;
    border-radius: 50%;
    background: #00edff;
    box-shadow: 0 0 15px #00edff;
    animation: blink 4s infinite;
}

.smile {
    position: absolute;
    bottom: 12px;
    width: 24px;
    height: 12px;
    border-bottom: 4px solid #00edff;
    border-radius: 50%;
}

.robot-body {
    position: absolute;
    top: 125px;
    left: 48px;
    width: 95px;
    height: 85px;
    border-radius: 28px 28px 35px 35px;
    background: linear-gradient(145deg,#ffffff,#9dc9e9 55%,#3975a8);
    border: 3px solid #a0eaff;
    display: grid;
    place-items: center;
}

.robot-core {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #06274a;
    border: 2px solid var(--green);
    display: grid;
    place-items: center;
    color: var(--green);
    font-size: 24px;
}

.robot-arm {
    position: absolute;
    top: 135px;
    width: 23px;
    height: 65px;
    border-radius: 20px;
    background: linear-gradient(90deg,#ffffff,#79b6df);
}

.robot-arm.left {
    left: 22px;
    transform: rotate(25deg);
}

.robot-arm.right {
    right: 15px;
    transform: rotate(-28deg);
}

.robot-message {
    position: absolute;
    right: 0;
    top: 8px;
    padding: 13px 17px;
    background: rgba(0,30,55,.9);
    border: 1px solid var(--blue);
    border-radius: 15px;
    color: white;
    font-size: 12px;
    font-weight: 700;
}

/* SECTION */

.section {
    margin-top: 32px;
}

.section-title {
    font-size: 25px;
    font-weight: 800;
    margin-bottom: 20px;
}

.section-title span {
    color: var(--green);
}

.panel {
    padding: 25px;
    background: linear-gradient(145deg,rgba(5,35,67,.96),rgba(3,19,42,.98));
    border: 1px solid var(--border);
    border-radius: 20px;
    box-shadow: inset 0 0 22px rgba(0,190,255,.04);
}

/* FEATURE CARDS */

.feature-grid {
    display: grid;
    grid-template-columns: repeat(4,1fr);
    gap: 20px;
}

.feature-card {
    min-height: 250px;
    padding: 23px 18px;
    text-align: center;
    text-decoration: none;
    color: white;
    border-radius: 23px;
    background: linear-gradient(145deg,rgba(5,35,67,.96),rgba(3,19,42,.98));
    border: 1px solid rgba(0,190,255,.45);
    transition: .35s;
}

.feature-card:hover {
    transform: translateY(-7px);
    border-color: var(--green);
    box-shadow: 0 0 25px rgba(0,190,255,.2);
}

.feature-icon {
    font-size: 48px;
    display: block;
    margin: 7px 0 17px;
}

.feature-card h3 {
    font-size: 16px;
    margin-bottom: 11px;
}

.feature-card p {
    color: #b7c9da;
    font-size: 12px;
    line-height: 1.7;
}

.explore {
    display: inline-block;
    margin-top: 15px;
    padding: 9px 20px;
    border: 1px solid var(--green);
    border-radius: 30px;
    color: var(--green);
    font-size: 12px;
    font-weight: 800;
}

/* DUE DATE */

.due-list {
    display: grid;
    gap: 13px;
}

.due-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding: 17px;
    border: 1px solid rgba(0,190,255,.2);
    border-radius: 13px;
    background: rgba(0,190,255,.05);
}

.due-item.overdue {
    border-color: rgba(255,90,90,.5);
    background: rgba(255,70,70,.07);
}

.due-item.today {
    border-color: rgba(255,190,50,.5);
    background: rgba(255,190,50,.06);
}

.due-info strong {
    display: block;
    font-size: 14px;
    margin-bottom: 7px;
}

.due-info small {
    color: var(--muted);
    font-size: 12px;
}

.due-status {
    padding: 8px 13px;
    border-radius: 30px;
    background: rgba(0,245,160,.12);
    color: var(--green);
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.due-status.overdue {
    background: rgba(255,70,70,.13);
    color: #ff9292;
}

.due-status.today {
    background: rgba(255,190,50,.13);
    color: #ffd36b;
}

.empty-message {
    color: var(--muted);
    text-align: center;
    padding: 25px 10px;
    line-height: 1.7;
    font-size: 13px;
}

.action-link {
    display: inline-block;
    margin-top: 16px;
    color: var(--blue);
    font-size: 12px;
    text-decoration: none;
    font-weight: 700;
}

/* COMPARISON */

.comparison-grid {
    display: grid;
    grid-template-columns: repeat(2,1fr);
    gap: 16px;
}

.comparison-card {
    background: rgba(0,190,255,.05);
    border: 1px solid rgba(0,190,255,.22);
    border-radius: 15px;
    padding: 20px;
}

.comparison-card small {
    color: var(--muted);
    font-size: 12px;
}

.comparison-card strong {
    display: block;
    margin-top: 12px;
    font-size: 25px;
}

.comparison-card p {
    margin-top: 10px;
    font-size: 12px;
    color: var(--muted);
}

.change-up {
    color: #ff9292 !important;
}

.change-down {
    color: var(--green) !important;
}

/* USAGE HISTORY */

.chart-list {
    display: grid;
    gap: 17px;
}

.chart-row {
    display: grid;
    grid-template-columns: 85px 1fr 90px;
    align-items: center;
    gap: 13px;
}

.chart-label {
    color: var(--muted);
    font-size: 12px;
}

.chart-track {
    height: 17px;
    border-radius: 20px;
    background: #102d49;
    overflow: hidden;
}

.chart-fill {
    height: 100%;
    border-radius: 20px;
    background: linear-gradient(90deg,var(--blue),var(--green));
    min-width: 0;
    transition: width .5s;
}

.chart-value {
    text-align: right;
    font-size: 12px;
    color: #e9f4f5;
    font-weight: 700;
}

.chart-legend {
    color: var(--muted);
    font-size: 11px;
    margin-top: 20px;
}

/* SAVING TIPS */

.tips-grid {
    display: grid;
    grid-template-columns: repeat(2,1fr);
    gap: 15px;
}

.tip-card {
    padding: 18px;
    background: rgba(0,245,160,.045);
    border: 1px solid rgba(0,245,160,.18);
    border-radius: 14px;
}

.tip-card .tip-icon {
    font-size: 28px;
    margin-bottom: 10px;
}

.tip-card h3 {
    color: var(--green);
    font-size: 14px;
    margin-bottom: 8px;
}

.tip-card p {
    color: #c1d4e7;
    font-size: 12px;
    line-height: 1.7;
}

/* BANNER */

.tips-banner {
    margin-top: 28px;
    padding: 22px 25px;
    border: 1px solid rgba(0,245,160,.4);
    border-radius: 18px;
    background: linear-gradient(100deg,rgba(0,245,160,.08),rgba(0,150,255,.08));
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.tips-banner h3 {
    color: var(--green);
    font-size: 16px;
    margin-bottom: 6px;
}

.tips-banner p {
    color: #c5d6e6;
    font-size: 12px;
    line-height: 1.6;
}

.tips-btn {
    text-decoration: none;
    color: var(--green);
    border: 1px solid var(--green);
    border-radius: 30px;
    padding: 11px 20px;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

/* FOOTER */

.footer {
    text-align: center;
    padding: 27px 15px;
    color: #8299b2;
    font-size: 11px;
}

/* ANIMATIONS */

@keyframes float {
    0%,100% { transform: translateY(0); }
    50% { transform: translateY(-13px); }
}

@keyframes blink {
    0%,45%,49%,100% { transform: scaleY(1); }
    47% { transform: scaleY(.1); }
}

/* RESPONSIVE */

@media(max-width:1050px) {
    .feature-grid {
        grid-template-columns: repeat(2,1fr);
    }

    .hero-stats {
        grid-template-columns: repeat(2,1fr);
    }

    .hero {
        grid-template-columns: 1fr .8fr;
    }
}

@media(max-width:750px) {
    .header {
        height: 72px;
        padding: 0 20px;
    }

    .logo {
        font-size: 25px;
    }

    .hero {
        grid-template-columns: 1fr;
        padding: 30px 23px;
    }

    .robot-area {
        min-height: 250px;
    }

    .comparison-grid,
    .tips-grid {
        grid-template-columns: 1fr;
    }

    .tips-banner {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media(max-width:480px) {
    .container {
        padding: 17px 13px;
    }

    .feature-grid {
        grid-template-columns: 1fr;
    }

    .hero h1 {
        font-size: 36px;
    }

    .hero-stats {
        grid-template-columns: 1fr 1fr;
    }

    .stat {
        padding: 10px 7px;
    }

    .stat-icon {
        width: 32px;
        height: 32px;
        font-size: 16px;
    }

    .stat strong {
        font-size: 13px;
    }

    .chart-row {
        grid-template-columns: 65px 1fr 70px;
        gap: 8px;
    }

    .chart-label,
    .chart-value {
        font-size: 10px;
    }

    .panel {
        padding: 17px;
    }

    .due-item {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media(prefers-reduced-motion:reduce) {
    *, *::before, *::after {
        animation: none !important;
        transition: none !important;
    }
}

</style>

</head>

<body>

<!-- HEADER -->

<header class="header">

    <div class="logo">
        <span class="bolt">⚡</span>Volt<span>Vision</span>
    </div>

    <button class="menu-btn" id="menuBtn" aria-label="Open menu">
        ☰
    </button>

</header>

<!-- MENU -->

<div class="overlay" id="overlay"></div>

<aside class="sidebar" id="sidebar">

    <div class="sidebar-top">
        <h2>⚡ VoltVision Menu</h2>
        <button class="close-btn" id="closeBtn">×</button>
    </div>

    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="bills/upload_bill.php">📄 Upload Bill</a>
    <a href="bills/my_bills.php">🧾 My Bills</a>
    <a href="predict_bill.php">⚡ Predict Bill</a>
    <a href="my_predictions.php">🤖 My Predictions</a>
    <a href="analytics.php">📊 Analytics</a>
    <a href="analytics/monthly_consumption.php">
        <span>📊</span>
        Monthly Consumption Graph
    </a>
    <a href="analytics/energy_recommendations.php">
    ⚡ Energy Saving Recommendations
</a>
<a href="analytics/future_forecast.php">
    🔮 Future Consumption Forecast
</a>
    <a href="logout.php" class="logout">↗ Logout</a>
</aside>

<main class="container">

    <!-- HERO -->

    <section class="hero">

        <div class="hero-content">

            <div class="tag">⚡ SMART ENERGY MANAGEMENT</div>

            <h1>
                Welcome to<br>
                Volt<span>Vision!</span> ⚡
            </h1>

            <div class="hello">
                Hello, <span><?= h($userName) ?>!</span> 👋
            </div>

            <p class="hero-description">
                Your smart energy assistant is ready.
                Explore electricity usage, predict your bills
                and discover smarter ways to save energy.
            </p>

            <div class="hero-stats">

                <div class="stat">
                    <div class="stat-icon">⚡</div>
                    <div>
                        <strong><?= $totalBills ?></strong>
                        <small>Total Bills</small>
                    </div>
                </div>

                <div class="stat">
                    <div class="stat-icon">🔋</div>
                    <div>
                        <strong><?= number_format($totalUnits, 2) ?></strong>
                        <small>Total Units (kWh)</small>
                    </div>
                </div>

                <div class="stat">
                    <div class="stat-icon">₹</div>
                    <div>
                        <strong>₹<?= number_format($totalAmount, 2) ?></strong>
                        <small>Total Amount</small>
                    </div>
                </div>

                <div class="stat">
                    <div class="stat-icon">📈</div>
                    <div>
                        <strong><?= $totalPredictions ?></strong>
                        <small>Predictions</small>
                    </div>
                </div>

            </div>

        </div>

        

        <!-- ROBOT -->

        <div class="robot-area">

            <div class="robot-glow"></div>

            <div class="robot-message">
                Let's save<br>energy together! ⚡
            </div>

            <div class="robot">

                <div class="antenna"></div>

                <div class="robot-head">
                    <div class="robot-face">
                        <div class="eye"></div>
                        <div class="eye"></div>
                        <div class="smile"></div>
                    </div>
                </div>

                <div class="robot-arm left"></div>
                <div class="robot-arm right"></div>

                <div class="robot-body">
                    <div class="robot-core">⚡</div>
                </div>

            </div>

        </div>

    </section>

    <!-- FEATURE LINKS -->

    <section class="section">

        <h2 class="section-title">
            What can you do with <span>VoltVision</span>?
        </h2>

        <div class="feature-grid">

            <a href="bills/upload_bill.php" class="feature-card">
                <span class="feature-icon">🧾</span>
                <h3>Upload Your Electricity Bill</h3>
                <p>Upload and manage electricity bills easily.</p>
                <span class="explore">Explore →</span>
            </a>

            <a href="predict_bill.php" class="feature-card">
                <span class="feature-icon">⚡</span>
                <h3>Predict Your Electricity Bill</h3>
                <p>Estimate future energy usage and costs.</p>
                <span class="explore">Explore →</span>
            </a>

            <a href="my_predictions.php" class="feature-card">
                <span class="feature-icon">📊</span>
                <h3>Track Your Energy Usage</h3>
                <p>View your prediction history and usage.</p>
                <span class="explore">Explore →</span>
            </a>

            <a href="analytics.php" class="feature-card">
                <span class="feature-icon">🌱</span>
                <h3>Explore Analytics</h3>
                <p>Understand your energy patterns and insights.</p>
                <span class="explore">Explore →</span>
            </a>

            <div class="feature-card">
    <div class="feature-icon">📊</div>
    <h3>Monthly Consumption</h3>
    <p>View your month-wise electricity usage graph.</p>
    <a href="analytics/monthly_consumption.php">
        <span class="explore">Explore →</span>
    </a>
</div>
    <div class="feature-card">
    <div class="feature-icon">⚡</div>
    <h3>Energy Saving Recommendations</h3>
    <p>Get smart suggestions based on your electricity consumption.</p>
    <a href="analytics/energy_recommendations.php">
        <span class="explore">Explore →</span>
    </a>
</div>


        </div>

    </section>

    <!-- 1. DUE DATE REMINDER -->

    <section class="section" id="reminders">

        <h2 class="section-title">
            🔔 Bill <span>Due Date Reminders</span>
        </h2>

        <div class="panel">

            <?php if (empty($activeDueBills)): ?>

                <div class="empty-message">
                    <div style="font-size:35px;margin-bottom:10px;">✅</div>
                    No bills are due within the next 3 days.
                    <br>
                    Upload your latest electricity bill to track its due date.
                </div>

            <?php else: ?>

                <div class="due-list">

                    <?php foreach ($activeDueBills as $bill): ?>

                        <?php
                        $days = $bill["days_remaining"];

                        if ($days < 0) {
                            $status = "Overdue by " . abs($days) . " day(s)";
                            $statusClass = "overdue";
                            $itemClass = "overdue";
                        } elseif ($days === 0) {
                            $status = "Due Today!";
                            $statusClass = "today";
                            $itemClass = "today";
                        } else {
                            $status = "Due in " . $days . " day(s)";
                            $statusClass = "";
                            $itemClass = "";
                        }
                        ?>

                        <div class="due-item <?= $itemClass ?>">

                            <div class="due-info">

                                <strong>
                                    Electricity Bill #<?= (int)$bill["id"] ?>
                                </strong>

                                <small>
                                    Due Date:
                                    <?= h(date("d M Y", $bill["due_timestamp"])) ?>
                                    &nbsp; | &nbsp;
                                    Amount: ₹<?= number_format((float)$bill["amount"], 2) ?>
                                </small>

                            </div>

                            <span class="due-status <?= $statusClass ?>">
                                <?= h($status) ?>
                            </span>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

            <a href="bills/my_bills.php" class="action-link">
                View All Bills →
            </a>

        </div>

    </section>

    <!-- 2. MONTHLY BILL COMPARISON -->

    <section class="section">

        <h2 class="section-title">
            📊 Monthly <span>Bill Comparison</span>
        </h2>

        <div class="panel">

            <?php if (!$currentMonthData || !$previousMonthData): ?>

                <div class="empty-message">
                    Upload bills for at least two different months
                    to view your monthly comparison.
                </div>

            <?php else: ?>

                <p style="color:var(--muted);font-size:12px;margin-bottom:20px;">
                    Comparing
                    <?= h(date("M Y", strtotime($currentMonthData["month_key"] . "-01"))) ?>
                    with
                    <?= h(date("M Y", strtotime($previousMonthData["month_key"] . "-01"))) ?>
                </p>

                <div class="comparison-grid">

                    <div class="comparison-card">

                        <small>Current Month Bill</small>

                        <strong>
                            ₹<?= number_format((float)$currentMonthData["amount"], 2) ?>
                        </strong>

                        <p>
                            <?= number_format((float)$currentMonthData["units"], 2) ?>
                            kWh consumed
                        </p>

                    </div>

                    <div class="comparison-card">

                        <small>Previous Month Bill</small>

                        <strong>
                            ₹<?= number_format((float)$previousMonthData["amount"], 2) ?>
                        </strong>

                        <p>
                            <?= number_format((float)$previousMonthData["units"], 2) ?>
                            kWh consumed
                        </p>

                    </div>

                    <div class="comparison-card">

                        <small>Bill Amount Difference</small>

                        <strong class="<?= $billDifference > 0 ? 'change-up' : 'change-down' ?>">
                            <?= $billDifference > 0 ? '+' : ($billDifference < 0 ? '-' : '') ?>
                            ₹<?= number_format(abs($billDifference), 2) ?>
                        </strong>

                        <p>
                            <?php if ($amountChangePercent !== null): ?>
                                <?= number_format(abs($amountChangePercent), 1) ?>%
                                <?= $billDifference > 0 ? 'increase' : ($billDifference < 0 ? 'decrease' : 'no change') ?>
                            <?php else: ?>
                                Previous bill amount was zero; percentage unavailable.
                            <?php endif; ?>
                        </p>

                    </div>

                    <div class="comparison-card">

                        <small>Units Difference</small>

                        <strong class="<?= $unitDifference > 0 ? 'change-up' : 'change-down' ?>">
                            <?= $unitDifference > 0 ? '+' : ($unitDifference < 0 ? '-' : '') ?>
                            <?= number_format(abs($unitDifference), 2) ?> kWh
                        </strong>

                        <p>
                            <?php if ($unitChangePercent !== null): ?>
                                <?= number_format(abs($unitChangePercent), 1) ?>%
                                <?= $unitDifference > 0 ? 'increase' : ($unitDifference < 0 ? 'decrease' : 'no change') ?>
                            <?php else: ?>
                                Previous units were zero; percentage unavailable.
                            <?php endif; ?>
                        </p>

                    </div>

                </div>

            <?php endif; ?>

            <a href="analytics.php" class="action-link">
                View Detailed Analytics →
            </a>

        </div>

    </section>

    <!-- 4. USAGE HISTORY -->

    <section class="section">

        <h2 class="section-title">
            📈 Six-Month <span>Electricity Usage History</span>
        </h2>

        <div class="panel">

            <div class="chart-list">

                <?php foreach ($monthlyData as $month): ?>

                    <?php
                    $width = ($month["units"] / $maxUnits) * 100;
                    ?>

                    <div class="chart-row">

                        <div class="chart-label">
                            <?= h($month["label"]) ?>
                        </div>

                        <div class="chart-track">

                            <div
                                class="chart-fill"
                                style="width:<?= number_format($width, 2, '.', '') ?>%;"
                            ></div>

                        </div>

                        <div class="chart-value">
                            <?= number_format($month["units"], 2) ?> kWh
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <div class="chart-legend">
                <span style="color:var(--green);">●</span>
                Monthly electricity consumption based on uploaded bills.
            </div>

            <a href="analytics.php" class="action-link">
                Explore Full Analytics →
            </a>

        </div>

    </section>

    <!-- 3. SAVING SUGGESTIONS -->

    <section class="section" id="saving-tips">

        <h2 class="section-title">
            🌱 Electricity <span>Saving Suggestions</span>
        </h2>

        <div class="panel">

            <div class="tips-grid">

                <?php foreach ($savingTips as $tip): ?>

                    <div class="tip-card">

                        <div class="tip-icon">
                            <?= $tip["icon"] ?>
                        </div>

                        <h3>
                            <?= h($tip["title"]) ?>
                        </h3>

                        <p>
                            <?= h($tip["description"]) ?>
                        </p>

                    </div>

                <?php endforeach; ?>

            </div>

            <a href="predict_bill.php" class="action-link">
                Predict Your Electricity Usage →
            </a>

        </div>

    </section>

    <!-- BANNER -->

    <section class="tips-banner">

        <div>
            <h3>⚡ Small Steps, Big Impact!</h3>

            <p>
                Save energy today for a greener tomorrow.
                Together, let's build a sustainable future.
            </p>
        </div>

        <a href="#saving-tips" class="tips-btn">
            🌿 View Saving Tips →
        </a>

    </section>

</main>

<footer class="footer">
    © 2026 VoltVision | Smart Energy. Smart Savings. Better Tomorrow.
</footer>

<script>

const menuBtn = document.getElementById("menuBtn");
const closeBtn = document.getElementById("closeBtn");
const sidebar = document.getElementById("sidebar");
const overlay = document.getElementById("overlay");

function openMenu() {
    sidebar.classList.add("active");
    overlay.classList.add("active");
    menuBtn.setAttribute("aria-expanded", "true");
}

function closeMenu() {
    sidebar.classList.remove("active");
    overlay.classList.remove("active");
    menuBtn.setAttribute("aria-expanded", "false");
}

menuBtn.addEventListener("click", openMenu);
closeBtn.addEventListener("click", closeMenu);
overlay.addEventListener("click", closeMenu);

document.addEventListener("keydown", function(event) {
    if (event.key === "Escape") {
        closeMenu();
    }
});

</script>

</body>
</html>
