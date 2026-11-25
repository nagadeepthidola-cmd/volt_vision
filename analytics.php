
<?php
session_start();
require_once __DIR__ . "/config/db.php";

// LOGIN CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// SUMMARY STATISTICS
$summary_sql = "SELECT
    COUNT(*) AS total_predictions,
    COALESCE(SUM(predicted_bill), 0) AS total_amount,
    COALESCE(AVG(predicted_bill), 0) AS average_bill,
    COALESCE(MAX(predicted_bill), 0) AS highest_bill
    FROM predictions
    WHERE user_id = ?";

$stmt = $conn->prepare($summary_sql);

$summary = [
    'total_predictions' => 0,
    'total_amount' => 0,
    'average_bill' => 0,
    'highest_bill' => 0
];

$error = "";

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $summary = $result->fetch_assoc() ?: $summary;

    $stmt->close();
} else {
    $error = "Unable to load summary statistics.";
}

// MONTHLY ANALYTICS
$monthly_sql = "SELECT
    DATE_FORMAT(created_at, '%Y-%m') AS month_key,
    DATE_FORMAT(created_at, '%b %Y') AS month_label,
    COUNT(*) AS prediction_count,
    COALESCE(SUM(predicted_bill), 0) AS total_bill,
    COALESCE(AVG(predicted_bill), 0) AS average_bill
    FROM predictions
    WHERE user_id = ?
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month_key ASC";

$stmt = $conn->prepare($monthly_sql);

$labels = [];
$amounts = [];
$counts = [];

$highest_month = "N/A";
$highest_amount = 0;

if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $labels[] = $row['month_label'];

        $amounts[] = round((float)$row['total_bill'], 2);

        $counts[] = (int)$row['prediction_count'];

        if ((float)$row['total_bill'] > $highest_amount) {
            $highest_amount = (float)$row['total_bill'];
            $highest_month = $row['month_label'];
        }
    }

    $stmt->close();
} else {
    $error = "Unable to load monthly analytics.";
}

// SAFE JSON FOR JAVASCRIPT
$labels_json = json_encode(
    $labels,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);

$amounts_json = json_encode($amounts);
$counts_json = json_encode($counts);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Analytics | VoltVision</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

/* MAIN CONTAINER */

.container {
    max-width: 1450px;
    margin: 38px auto;
    padding: 0 28px;
}

/* PAGE BANNER */

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

/* ERROR MESSAGE */

.error {
    padding: 15px 19px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 13px;
    background: rgba(255,65,85,.10);
    color: #ff9ba5;
    border: 1px solid rgba(255,65,85,.25);
}

/* SUMMARY CARDS */

.cards {
    display: grid;
    grid-template-columns: repeat(4,1fr);
    gap: 20px;
    margin-bottom: 28px;
}

.card {
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

.card::after {
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

.card:hover {
    transform: translateY(-6px);
    border-color: rgba(0,230,154,.4);
    box-shadow: 0 15px 35px rgba(0,0,0,.25);
}

.card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.card-icon {
    width: 49px;
    height: 49px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 23px;
    border-radius: 14px;
}

.green {
    background: rgba(0,230,154,.13);
}

.blue {
    background: rgba(37,217,239,.13);
}

.orange {
    background: rgba(255,166,77,.13);
}

.purple {
    background: rgba(174,130,255,.14);
}

.card-label {
    color: var(--muted);
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 10px;
}

.card .value {
    color: white;
    font-size: clamp(21px,2.2vw,29px);
    font-weight: 800;
    overflow-wrap: anywhere;
}

.card-note {
    color: #718ba0;
    font-size: 11px;
    margin-top: 10px;
}

/* CHART GRID */

.chart-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 22px;
    margin-bottom: 25px;
}

/* CHART BOX */

.chart-box {
    min-width: 0;
    background: linear-gradient(145deg,#102239,#0b1929);
    padding: 27px;
    border-radius: 19px;
    border: 1px solid var(--border);
    box-shadow: 0 10px 30px rgba(0,0,0,.14);
    transition: .3s;
}

.chart-box:hover {
    border-color: rgba(37,217,239,.22);
}

.chart-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}

.chart-heading h2 {
    color: #edf7ff;
    font-size: 18px;
    font-weight: 750;
}

.chart-heading p {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.6;
    margin-top: 7px;
}

.chart-badge {
    background: rgba(0,230,154,.10);
    color: var(--green);
    border: 1px solid rgba(0,230,154,.18);
    padding: 8px 13px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .5px;
}

.chart-badge.blue-badge {
    background: rgba(37,217,239,.10);
    color: var(--cyan);
    border-color: rgba(37,217,239,.18);
}

.chart-container {
    position: relative;
    width: 100%;
    height: 330px;
}

.empty {
    color: #8da4b8;
    padding: 55px 15px;
    text-align: center;
    background: rgba(255,255,255,.02);
    border: 1px dashed #30495e;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.8;
}

.empty-icon {
    font-size: 42px;
    margin-bottom: 14px;
}

/* HIGHEST MONTH */

.highlight-card {
    position: relative;
    overflow: hidden;

    background:
        radial-gradient(circle at 90% 10%, rgba(0,230,154,.2), transparent 30%),
        linear-gradient(115deg,#102b40,#073b37);

    color: white;
    padding: 30px;
    border-radius: 19px;
    margin-bottom: 25px;

    border: 1px solid rgba(0,230,154,.22);
    box-shadow: 0 12px 30px rgba(0,0,0,.17);
}

.highlight-card h3 {
    color: #9bcbb8;
    font-size: 11px;
    letter-spacing: 1.5px;
    margin-bottom: 16px;
}

.highlight-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.highlight-month {
    font-size: 29px;
    font-weight: 800;
    margin-bottom: 7px;
}

.highlight-amount {
    color: var(--green);
    font-size: 28px;
    font-weight: 800;
}

.highlight-icon {
    font-size: 65px;
    opacity: .8;
    filter: drop-shadow(0 0 15px rgba(0,230,154,.3));
}

/* BUTTONS */

.buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 13px;
    margin-top: 25px;
}

.buttons a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
    padding: 13px 19px;
    background: linear-gradient(120deg,#00e69a,#22d9bd);
    color: #041b18;
    border: 1px solid transparent;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 800;
    transition: .3s;
}

.buttons a:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 22px rgba(0,230,154,.2);
}

.buttons a.secondary {
    background: #142a40;
    color: #c8d9e8;
    border-color: var(--border);
}

.buttons a.secondary:hover {
    color: var(--green);
    border-color: rgba(0,230,154,.4);
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
.card,
.chart-box,
.highlight-card {
    animation: fadeUp .6s ease both;
}

.card:nth-child(2) {
    animation-delay: .08s;
}

.card:nth-child(3) {
    animation-delay: .16s;
}

.card:nth-child(4) {
    animation-delay: .24s;
}

/* RESPONSIVE */

@media(max-width:1100px) {
    .cards {
        grid-template-columns: repeat(2,1fr);
    }

    .chart-grid {
        grid-template-columns: 1fr;
    }
}

@media(max-width:750px) {
    .navbar {
        padding: 17px 20px;
    }

    .nav-links {
        gap: 14px;
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

    .chart-box {
        padding: 20px;
    }

    .chart-container {
        height: 280px;
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

    .cards {
        grid-template-columns: 1fr;
    }

    .card {
        padding: 22px;
    }

    .page-banner h1 {
        font-size: 25px;
    }

    .page-banner p {
        font-size: 12px;
    }

    .chart-container {
        height: 250px;
    }

    .buttons {
        flex-direction: column;
    }

    .buttons a {
        width: 100%;
    }

    .highlight-month,
    .highlight-amount {
        font-size: 23px;
    }

    .banner-icon {
        opacity: .07;
    }
}

</style>

</head>

<body>

<!-- NAVBAR -->

<nav class="navbar">

    <div class="logo">
        ⚡ Volt<span>Vision</span>
    </div>

    <div class="nav-links">

        <a href="dashboard.php">Dashboard</a>

        <a href="bills/upload_bill.php">Upload Bill</a>

        <a href="bills/my_bills.php">My Bills</a>

        <a href="predict_bill.php">Predict Bill</a>

        <a href="my_predictions.php">My Predictions</a>

        <a href="analytics.php" class="active">Analytics</a>

        <a href="logout.php" class="logout">Logout ↗</a>

    </div>

</nav>


<main class="container">

    <!-- PAGE HEADER -->

    <section class="page-banner">

        <span class="banner-tag">
            SMART ENERGY ANALYTICS
        </span>

        <h1>Analytics Dashboard 📊</h1>

        <p>
            Analyze your electricity bill predictions, monitor monthly
            trends and understand your estimated energy expenses
            through interactive visual reports.
        </p>

        <div class="banner-icon">📈</div>

    </section>


    <!-- ERROR MESSAGE -->

    <?php if ($error): ?>

        <div class="error">
            ⚠️ <?= h($error) ?>
        </div>

    <?php endif; ?>


    <!-- SUMMARY CARDS -->

    <section class="cards">

        <div class="card">

            <div class="card-top">
                <div class="card-icon green">🤖</div>
            </div>

            <p class="card-label">Total Predictions</p>

            <p class="value">
                <?= (int)$summary['total_predictions'] ?>
            </p>

            <p class="card-note">
                Total generated predictions
            </p>

        </div>


        <div class="card">

            <div class="card-top">
                <div class="card-icon blue">💰</div>
            </div>

            <p class="card-label">Total Predicted Amount</p>

            <p class="value">
                ₹<?= number_format((float)$summary['total_amount'], 2) ?>
            </p>

            <p class="card-note">
                Combined predicted bill amount
            </p>

        </div>


        <div class="card">

            <div class="card-top">
                <div class="card-icon orange">📊</div>
            </div>

            <p class="card-label">Average Predicted Bill</p>

            <p class="value">
                ₹<?= number_format((float)$summary['average_bill'], 2) ?>
            </p>

            <p class="card-note">
                Average amount per prediction
            </p>

        </div>


        <div class="card">

            <div class="card-top">
                <div class="card-icon purple">⚡</div>
            </div>

            <p class="card-label">Highest Predicted Bill</p>

            <p class="value">
                ₹<?= number_format((float)$summary['highest_bill'], 2) ?>
            </p>

            <p class="card-note">
                Highest individual prediction
            </p>

        </div>

    </section>


    <!-- CHARTS -->

    <div class="chart-grid">

        <!-- MONTHLY BILL CHART -->

        <section class="chart-box">

            <div class="chart-heading">

                <div>
                    <h2>📊 Monthly Predicted Bill</h2>

                    <p>
                        Track your total predicted electricity
                        bill amount month by month.
                    </p>
                </div>

                <span class="chart-badge">
                    BAR CHART
                </span>

            </div>

            <?php if (count($labels) > 0): ?>

                <div class="chart-container">
                    <canvas id="billChart"></canvas>
                </div>

            <?php else: ?>

                <div class="empty">
                    <div class="empty-icon">📊</div>
                    No prediction data available yet.
                    Generate your first prediction to view this chart.
                </div>

            <?php endif; ?>

        </section>


        <!-- MONTHLY COUNT CHART -->

        <section class="chart-box">

            <div class="chart-heading">

                <div>
                    <h2>📈 Monthly Prediction Count</h2>

                    <p>
                        Understand how many bill predictions
                        you generated each month.
                    </p>
                </div>

                <span class="chart-badge blue-badge">
                    LINE CHART
                </span>

            </div>

            <?php if (count($labels) > 0): ?>

                <div class="chart-container">
                    <canvas id="countChart"></canvas>
                </div>

            <?php else: ?>

                <div class="empty">
                    <div class="empty-icon">📈</div>
                    Generate a prediction to view monthly
                    prediction statistics.
                </div>

            <?php endif; ?>

        </section>

    </div>


    <!-- HIGHEST MONTH -->

    <section class="highlight-card">

        <h3>
            🏆 MONTH WITH HIGHEST TOTAL PREDICTED AMOUNT
        </h3>

        <div class="highlight-content">

            <div>

                <div class="highlight-month">
                    <?= h($highest_month) ?>
                </div>

                <div class="highlight-amount">
                    ₹<?= number_format($highest_amount, 2) ?>
                </div>

            </div>

            <div class="highlight-icon">
                ⚡
            </div>

        </div>

    </section>


    <!-- NAVIGATION BUTTONS -->

    <div class="buttons">

        <a href="dashboard.php" class="secondary">
            ← Dashboard
        </a>

        <a href="predict_bill.php">
            + New Prediction
        </a>

        <a href="my_predictions.php" class="secondary">
            📋 My Predictions
        </a>

    </div>

</main>


<!-- FOOTER -->

<footer class="footer">

    © 2026 VoltVision | Smart Energy. Smart Savings. Better Tomorrow.

</footer>


<!-- CHART.JS -->

<?php if (count($labels) > 0): ?>

<script>

const labels = <?= $labels_json ?>;
const amounts = <?= $amounts_json ?>;
const counts = <?= $counts_json ?>;

// CHART DEFAULT SETTINGS

Chart.defaults.color = "#8da4b8";

Chart.defaults.font.family = "'Segoe UI', Arial, sans-serif";

Chart.defaults.font.size = 11;


// CHART ERROR HANDLING

function showChartError(message) {

    document.querySelectorAll(".chart-container").forEach(box => {

        box.innerHTML =
            '<div class="empty">' + message + '</div>';

    });

}


// CREATE CHARTS

function createCharts() {

    if (typeof Chart === "undefined") {

        showChartError(
            "Chart.js could not load. Check your internet connection and refresh the page."
        );

        return;
    }


    // MONTHLY PREDICTED BILL - BAR CHART

    const billCanvas = document.getElementById("billChart");

    if (billCanvas) {

        new Chart(billCanvas, {

            type: "bar",

            data: {

                labels: labels,

                datasets: [{

                    label: "Predicted Bill (₹)",

                    data: amounts,

                    backgroundColor: "rgba(0,230,154,0.65)",

                    borderColor: "#00e69a",

                    borderWidth: 2,

                    borderRadius: 8,

                    hoverBackgroundColor: "#00e69a",

                    maxBarThickness: 65

                }]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                interaction: {
                    intersect: false,
                    mode: "index"
                },

                plugins: {

                    legend: {

                        display: true,

                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }

                    },

                    tooltip: {

                        backgroundColor: "#10243a",

                        titleColor: "#00e69a",

                        bodyColor: "#edf7ff",

                        borderColor: "rgba(0,230,154,.3)",

                        borderWidth: 1,

                        padding: 12,

                        callbacks: {

                            label: function(context) {

                                return " ₹" +
                                    Number(context.raw).toLocaleString(
                                        "en-IN",
                                        {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        }
                                    );

                            }

                        }

                    }

                },

                scales: {

                    y: {

                        beginAtZero: true,

                        grid: {
                            color: "rgba(148,180,205,.10)"
                        },

                        ticks: {
                            color: "#8da4b8"
                        },

                        title: {
                            display: true,
                            text: "Amount (₹)",
                            color: "#25d9ef"
                        }

                    },

                    x: {

                        grid: {
                            display: false
                        },

                        ticks: {
                            color: "#8da4b8"
                        },

                        title: {
                            display: true,
                            text: "Month",
                            color: "#25d9ef"
                        }

                    }

                }

            }

        });

    }


    // MONTHLY PREDICTION COUNT - LINE CHART

    const countCanvas = document.getElementById("countChart");

    if (countCanvas) {

        new Chart(countCanvas, {

            type: "line",

            data: {

                labels: labels,

                datasets: [{

                    label: "Number of Predictions",

                    data: counts,

                    borderColor: "#25d9ef",

                    backgroundColor: "rgba(37,217,239,.10)",

                    fill: true,

                    tension: .35,

                    pointRadius: 5,

                    pointHoverRadius: 8,

                    pointBackgroundColor: "#00e69a",

                    pointBorderColor: "#ffffff",

                    pointBorderWidth: 2,

                    borderWidth: 3

                }]

            },

            options: {

                responsive: true,

                maintainAspectRatio: false,

                interaction: {
                    intersect: false,
                    mode: "index"
                },

                plugins: {

                    legend: {

                        display: true,

                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }

                    },

                    tooltip: {

                        backgroundColor: "#10243a",

                        titleColor: "#25d9ef",

                        bodyColor: "#edf7ff",

                        borderColor: "rgba(37,217,239,.3)",

                        borderWidth: 1,

                        padding: 12

                    }

                },

                scales: {

                    y: {

                        beginAtZero: true,

                        ticks: {
                            precision: 0,
                            color: "#8da4b8"
                        },

                        grid: {
                            color: "rgba(148,180,205,.10)"
                        },

                        title: {
                            display: true,
                            text: "Prediction Count",
                            color: "#25d9ef"
                        }

                    },

                    x: {

                        grid: {
                            display: false
                        },

                        ticks: {
                            color: "#8da4b8"
                        },

                        title: {
                            display: true,
                            text: "Month",
                            color: "#25d9ef"
                        }

                    }

                }

            }

        });

    }

}


// INITIALIZE

createCharts();

</script>

<?php endif; ?>

</body>
</html>
