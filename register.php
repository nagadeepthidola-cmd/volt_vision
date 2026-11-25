
<?php
session_start();

require_once __DIR__ . '/config/db.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    }
    elseif (strlen($password) < 8) {
        $error = "Password must contain at least 8 characters.";
    }
    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    }
    else {

        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");

        if (!$check) {
            die("Database error: " . $conn->error);
        }

        $check->bind_param("s", $email);
        $check->execute();

        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error = "Email already registered. Please login.";
        }
        else {

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)"
            );

            if (!$stmt) {
                die("Database error: " . $conn->error);
            }

            $stmt->bind_param("sss", $name, $email, $hashedPassword);

            if ($stmt->execute()) {
                $success = "Registration successful! You can now login.";
            }
            else {
                $error = "Registration failed: " . $stmt->error;
            }

            $stmt->close();
        }

        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Register | VoltVision</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: #07111f;
    color: #e9f4f5;
    min-height: 100vh;
    overflow-x: hidden;
}

.background-glow {
    position: fixed;
    width: 400px;
    height: 400px;
    border-radius: 50%;
    filter: blur(110px);
    opacity: 0.17;
    pointer-events: none;
}

.glow-one {
    background: #00f59b;
    top: -150px;
    left: -120px;
}

.glow-two {
    background: #00d9ff;
    bottom: -150px;
    right: -100px;
}

.main-container {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    position: relative;
    z-index: 1;
}

/* LEFT SECTION */

.welcome-section {
    padding: 45px 8%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    background:
        radial-gradient(circle at 15% 85%, rgba(0,245,155,0.10), transparent 35%),
        linear-gradient(145deg, #0b1a2b, #0b1726);
    border-right: 1px solid #26394d;
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 27px;
    font-weight: 800;
    color: #00f59b;
}

.brand-icon {
    font-size: 32px;
}

.welcome-content {
    max-width: 570px;
    margin: 40px 0;
}

.tagline {
    display: inline-block;
    color: #00d9ff;
    border: 1px solid rgba(0,217,255,0.3);
    background: rgba(0,217,255,0.07);
    padding: 9px 15px;
    border-radius: 30px;
    font-size: 11px;
    letter-spacing: 2px;
    font-weight: 800;
    margin-bottom: 25px;
}

.welcome-content h1 {
    font-size: clamp(35px, 4vw, 53px);
    line-height: 1.2;
    margin-bottom: 22px;
    letter-spacing: -1px;
}

.welcome-content h1 span {
    color: #00f59b;
}

.welcome-content > p {
    color: #9aafbf;
    font-size: 15px;
    line-height: 1.8;
    max-width: 500px;
}

.features {
    display: grid;
    gap: 20px;
    margin-top: 35px;
}

.feature {
    display: flex;
    align-items: center;
    gap: 16px;
}

.feature > span {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    font-size: 22px;
    border-radius: 13px;
    background: #142b3d;
    border: 1px solid #29475a;
}

.feature h3 {
    font-size: 15px;
    margin-bottom: 5px;
}

.feature p {
    color: #8da3b5;
    font-size: 13px;
}

.footer-text {
    color: #71889a;
    font-size: 12px;
    letter-spacing: 0.5px;
}

/* RIGHT SECTION */

.login-section {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 30px;
}

.login-card {
    width: 100%;
    max-width: 460px;
    padding: 32px 40px;
    background: rgba(16,29,45,0.96);
    border: 1px solid #2a4054;
    border-radius: 22px;
    box-shadow: 0 25px 70px rgba(0,0,0,0.3);
}

.mobile-brand {
    display: none;
    color: #00f59b;
    font-weight: 800;
    font-size: 23px;
    margin-bottom: 23px;
}

.login-icon {
    width: 58px;
    height: 58px;
    display: grid;
    place-items: center;
    font-size: 28px;
    border-radius: 16px;
    color: #07111f;
    background: linear-gradient(135deg, #00f59b, #00d9ff);
    margin-bottom: 18px;
    box-shadow: 0 8px 25px rgba(0,245,155,0.15);
}

.welcome-label {
    color: #00d9ff;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 2px;
}

.login-card h2 {
    font-size: 27px;
    margin: 10px 0;
}

.subtitle {
    color: #8da3b5;
    font-size: 13px;
    line-height: 1.7;
    margin-bottom: 18px;
}

.message {
    padding: 12px;
    margin-bottom: 16px;
    border-radius: 9px;
    font-size: 13px;
    line-height: 1.5;
}

.error {
    background: rgba(255,70,70,0.12);
    border: 1px solid rgba(255,100,100,0.35);
    color: #ffbaba;
}

.success {
    background: rgba(0,245,155,0.10);
    border: 1px solid rgba(0,245,155,0.35);
    color: #9bffd4;
}

.success a {
    display: inline-block;
    margin-top: 8px;
    color: #00f59b;
    text-decoration: none;
    font-weight: 800;
}

form label {
    display: block;
    color: #d7e5ed;
    font-size: 13px;
    font-weight: 600;
    margin: 14px 0 8px;
}

.input-wrapper {
    position: relative;
}

form input {
    width: 100%;
    height: 46px;
    background: #0a1827;
    border: 1px solid #2c4256;
    border-radius: 9px;
    outline: none;
    padding: 0 14px;
    color: #ffffff;
    font-size: 14px;
    margin-bottom: 2px;
    transition: 0.25s;
}

form input::placeholder {
    color: #71889a;
}

form input:focus {
    border-color: #00d9ff;
    box-shadow: 0 0 0 3px rgba(0,217,255,0.09);
}

.input-wrapper input {
    padding-right: 65px;
}

.toggle-password {
    position: absolute;
    right: 12px;
    top: 12px;
    background: transparent;
    border: none;
    color: #00d9ff;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
}

.password-hint {
    color: #71889a;
    font-size: 11px;
    margin-top: 6px;
}

.submit-btn {
    width: 100%;
    height: 49px;
    margin-top: 23px;
    border: none;
    border-radius: 10px;
    background: linear-gradient(90deg, #00f59b, #00d9ff);
    color: #07111f;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    transition: 0.25s;
}

.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(0,245,155,0.2);
}

.submit-btn span {
    margin-left: 8px;
    font-size: 18px;
}

.register-link {
    text-align: center;
    margin-top: 22px;
    color: #8da3b5;
    font-size: 13px;
}

.register-link a {
    color: #00f59b;
    text-decoration: none;
    font-weight: 700;
    margin-left: 4px;
}

.register-link a:hover {
    color: #00d9ff;
    text-decoration: underline;
}

.secure-text {
    text-align: center;
    margin-top: 22px;
    padding-top: 18px;
    border-top: 1px solid #26394d;
    color: #8299aa;
    font-size: 11px;
}

@media(max-width: 900px) {
    .main-container {
        grid-template-columns: 1fr;
    }

    .welcome-section {
        display: none;
    }

    .login-section {
        min-height: 100vh;
        padding: 25px 18px;
    }

    .login-card {
        max-width: 460px;
    }

    .mobile-brand {
        display: block;
    }
}

@media(max-width: 480px) {
    .login-card {
        padding: 27px 22px;
        border-radius: 17px;
    }

    .login-card h2 {
        font-size: 24px;
    }
}
</style>
</head>

<body>

<div class="background-glow glow-one"></div>
<div class="background-glow glow-two"></div>

<div class="main-container">

    <!-- LEFT SECTION -->

    <section class="welcome-section">

        <div class="brand">
            <span class="brand-icon">⚡</span>
            <span>VoltVision</span>
        </div>

        <div class="welcome-content">

            <span class="tagline">JOIN THE SMART ENERGY REVOLUTION</span>

            <h1>
                Start Your<br>
                <span>Energy Journey.</span>
            </h1>

            <p>
                Create your VoltVision account and discover a smarter
                way to monitor electricity usage, predict monthly bills
                and understand your energy consumption.
            </p>

            <div class="features">

                <div class="feature">
                    <span>⚡</span>
                    <div>
                        <h3>Smart Electricity Prediction</h3>
                        <p>Get estimated monthly electricity bills.</p>
                    </div>
                </div>

                <div class="feature">
                    <span>📈</span>
                    <div>
                        <h3>Track Your Consumption</h3>
                        <p>View and analyze your electricity history.</p>
                    </div>
                </div>

                <div class="feature">
                    <span>🌿</span>
                    <div>
                        <h3>Build Better Energy Habits</h3>
                        <p>Make informed energy-saving decisions.</p>
                    </div>
                </div>

            </div>

        </div>

        <div class="footer-text">
            Smart Energy. Smart Savings. Better Tomorrow.
        </div>

    </section>

    <!-- RIGHT REGISTER SECTION -->

    <section class="login-section">

        <div class="login-card register-card">

            <div class="mobile-brand">⚡ VoltVision</div>

            <div class="login-icon">⚡</div>

            <span class="welcome-label">CREATE YOUR ACCOUNT</span>

            <h2>Join VoltVision</h2>

            <p class="subtitle">
                Enter your details to get started with smart energy management.
            </p>

            <?php if (!empty($error)): ?>
                <div class="message error">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="message success">
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                    <br>
                    <a href="login.php">Login Now →</a>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php">

                <label for="name">Full Name</label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="Enter your full name"
                    value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="name"
                    required
                >

                <label for="email">Email Address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="email"
                    required
                >

                <label for="password">Password</label>

                <div class="input-wrapper">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Minimum 8 characters"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                    <button type="button" class="toggle-password" data-target="password">
                        Show
                    </button>
                </div>

                <div class="password-hint">
                    Password must contain at least 8 characters.
                </div>

                <label for="confirm_password">Confirm Password</label>

                <div class="input-wrapper">
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Re-enter your password"
                        autocomplete="new-password"
                        required
                    >

                    <button type="button" class="toggle-password" data-target="confirm_password">
                        Show
                    </button>
                </div>

                <button type="submit" class="submit-btn">
                    Create Account <span>→</span>
                </button>

            </form>

            <div class="register-link">
                Already have an account?
                <a href="login.php">Login</a>
            </div>

            <div class="secure-text">
                🔒 Your account information is protected.
            </div>

        </div>

    </section>

</div>

<script>
document.querySelectorAll(".toggle-password").forEach(function(button) {

    button.addEventListener("click", function() {

        const input = document.getElementById(button.dataset.target);

        if (input.type === "password") {
            input.type = "text";
            button.textContent = "Hide";
        } else {
            input.type = "password";
            button.textContent = "Show";
        }

    });

});
</script>

</body>
</html>
