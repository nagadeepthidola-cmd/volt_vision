
<?php
session_start();

require_once __DIR__ . "/config/db.php";

$error = "";
$email = "";

// Login form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {

        $error = "Please enter your email and password.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        $stmt = $conn->prepare(
            "SELECT id, name, email, password_hash
             FROM users
             WHERE email = ?"
        );

        if ($stmt) {

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $stmt->bind_result(
                $userId,
                $userName,
                $userEmail,
                $passwordHash
            );

            if ($stmt->fetch()) {

                if (password_verify($password, $passwordHash)) {

                    session_regenerate_id(true);

                    $_SESSION["user_id"] = $userId;
                    $_SESSION["user_name"] = $userName;
                    $_SESSION["user_email"] = $userEmail;

                    $stmt->close();

                    header("Location: dashboard.php");
                    exit();

                } else {

                    $error = "Incorrect password. Please try again.";

                }

            } else {

                $error = "Account not found. Please register first.";

            }

            $stmt->close();

        } else {

            $error = "Something went wrong. Please try again.";

        }
    }
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | VoltVision</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background: #07111f;
            color: #e9f4f5;
            overflow-x: hidden;
        }

        /* BACKGROUND */

        .background-glow {
            position: fixed;
            width: 350px;
            height: 350px;
            border-radius: 50%;
            filter: blur(110px);
            opacity: 0.13;
            pointer-events: none;
        }

        .glow-one {
            background: #00f59b;
            top: -100px;
            left: -100px;
        }

        .glow-two {
            background: #00d9ff;
            bottom: -100px;
            right: -100px;
        }

        /* MAIN CONTAINER */

        .main-container {
            width: 100%;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            position: relative;
            z-index: 1;
        }

        /* LEFT SECTION */

        .welcome-section {
            padding: 45px 8%;
            background:
                linear-gradient(
                    145deg,
                    rgba(0, 245, 155, 0.07),
                    rgba(0, 217, 255, 0.03)
                ),
                #0c1929;

            display: flex;
            flex-direction: column;
            justify-content: space-between;

            border-right: 1px solid #26394d;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 28px;
            font-weight: 800;
            color: #00f59b;
        }

        .brand-icon {
            font-size: 35px;
            color: #ff9b38;
        }

        .welcome-content {
            margin-top: 45px;
            max-width: 600px;
        }

        .tagline {
            display: inline-block;
            padding: 10px 16px;
            border: 1px solid rgba(0, 217, 255, 0.4);
            border-radius: 30px;
            background: rgba(0, 217, 255, 0.06);

            color: #00d9ff;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 2px;

            margin-bottom: 30px;
        }

        .welcome-content h1 {
            font-size: clamp(35px, 4vw, 52px);
            line-height: 1.25;
            color: #e9f4f5;
            margin-bottom: 22px;
        }

        .welcome-content h1 span {
            color: #00f59b;
        }

        .welcome-description {
            color: #a0b4c5;
            font-size: 14px;
            line-height: 1.9;
            max-width: 500px;
            margin-bottom: 38px;
        }

        /* FEATURES */

        .features {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            flex-shrink: 0;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #142a3a;
            border: 1px solid #29465a;
            border-radius: 13px;

            font-size: 21px;
        }

        .feature h3 {
            color: #ffffff;
            font-size: 14px;
            margin-bottom: 7px;
        }

        .feature p {
            color: #8da3b5;
            font-size: 12px;
            line-height: 1.5;
        }

        .footer-text {
            color: #71879b;
            font-size: 11px;
            letter-spacing: 1px;
            margin-top: 35px;
        }

        /* RIGHT SECTION */

        .login-section {
            padding: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #07111f;
        }

        .login-card {
            width: 100%;
            max-width: 440px;

            padding: 42px;

            background: #101d2d;
            border: 1px solid #26394d;
            border-radius: 23px;

            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
        }

        .mobile-brand {
            display: none;
            color: #00f59b;
            font-size: 25px;
            font-weight: 800;
            margin-bottom: 30px;
        }

        .login-icon {
            width: 62px;
            height: 62px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: linear-gradient(135deg, #00f59b, #00d9ff);
            border-radius: 17px;

            font-size: 28px;
            margin-bottom: 30px;

            box-shadow: 0 10px 25px rgba(0, 245, 155, 0.15);
        }

        .welcome-label {
            color: #00d9ff;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 2px;
        }

        .login-card h2 {
            color: #e9f4f5;
            font-size: 27px;
            margin-top: 15px;
            margin-bottom: 12px;
        }

        .subtitle {
            color: #8da3b5;
            font-size: 13px;
            line-height: 1.7;
            margin-bottom: 30px;
        }

        /* FORM */

        .login-form label {
            display: block;
            color: #dce9ee;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .login-form input {
            width: 100%;
            height: 50px;

            padding: 0 15px;

            background: #0b1726;
            border: 1px solid #30475b;
            border-radius: 10px;

            outline: none;
            color: #ffffff;
            font-size: 13px;

            transition: 0.3s;
        }

        .login-form input::placeholder {
            color: #71879b;
        }

        .login-form input:focus {
            border-color: #00f59b;
            box-shadow: 0 0 0 3px rgba(0, 245, 155, 0.08);
        }

        .form-group {
            margin-bottom: 23px;
        }

        /* PASSWORD FIELD - CORRECT SHOW/HIDE ALIGNMENT */

        .password-wrap {
            position: relative !important;
            display: block !important;
            width: 100% !important;
        }

        .password-wrap input {
            display: block;
            width: 100% !important;
            height: 50px !important;

            padding: 0 75px 0 15px !important;

            background: #0b1726 !important;
            border: 1px solid #30475b !important;
            border-radius: 10px !important;

            color: #ffffff !important;
            font-size: 13px !important;

            box-shadow: none;
            outline: none;
        }

        .password-wrap input:focus {
            border-color: #00f59b !important;
            box-shadow: 0 0 0 3px rgba(0, 245, 155, 0.08) !important;
        }

        .password-wrap .show-btn {
            position: absolute !important;

            right: 12px !important;
            top: 50% !important;
            bottom: auto !important;
            left: auto !important;

            transform: translateY(-50%) !important;

            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;

            width: auto !important;
            min-width: 42px !important;
            height: 30px !important;

            margin: 0 !important;
            padding: 0 4px !important;

            background: transparent !important;
            background-image: none !important;

            border: none !important;
            border-radius: 0 !important;

            box-shadow: none !important;

            color: #00d9ff !important;
            font-size: 12px !important;
            font-weight: 700 !important;

            cursor: pointer !important;
            z-index: 5;
        }

        .password-wrap .show-btn:hover {
            transform: translateY(-50%) !important;
            color: #00f59b !important;
            text-decoration: underline;
        }

        /* FORGOT PASSWORD */

        .forgot-password {
            display: flex;
            justify-content: flex-end;
            margin-top: -7px;
            margin-bottom: 25px;
        }

        .forgot-password a {
            color: #00d9ff;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
        }

        .forgot-password a:hover {
            color: #00f59b;
        }

        /* ERROR MESSAGE */

        .error-message {
            padding: 13px 15px;
            margin-bottom: 22px;

            background: rgba(255, 70, 70, 0.12);
            border: 1px solid rgba(255, 100, 100, 0.35);
            border-radius: 9px;

            color: #ffbaba;
            font-size: 13px;
            line-height: 1.6;
        }

        /* LOGIN BUTTON */

        .login-btn {
            width: 100%;
            height: 51px;

            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;

            background: linear-gradient(135deg, #00f59b, #00d9ff);
            border: none;
            border-radius: 11px;

            color: #04251d;
            font-size: 14px;
            font-weight: 800;

            cursor: pointer;
            transition: 0.3s;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 245, 155, 0.2);
        }

        .login-btn span {
            font-size: 19px;
        }

        /* REGISTER */

        .register-link {
            text-align: center;
            margin-top: 28px;
            color: #8da3b5;
            font-size: 12px;
        }

        .register-link a {
            color: #00f59b;
            font-weight: 700;
            text-decoration: none;
            margin-left: 4px;
        }

        .register-link a:hover {
            color: #00d9ff;
        }

        /* SECURITY */

        .secure-text {
            text-align: center;
            border-top: 1px solid #26394d;
            padding-top: 20px;
            margin-top: 28px;

            color: #71879b;
            font-size: 11px;
        }

        /* RESPONSIVE */

        @media (max-width: 950px) {

            .main-container {
                grid-template-columns: 1fr 1fr;
            }

            .welcome-section {
                padding: 35px 7%;
            }

            .login-section {
                padding: 25px;
            }

            .login-card {
                padding: 30px;
            }

            .welcome-content h1 {
                font-size: 36px;
            }
        }

        @media (max-width: 750px) {

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
                padding: 35px 25px;
            }

            .mobile-brand {
                display: block;
            }
        }

        @media (max-width: 400px) {

            .login-card {
                padding: 28px 20px;
            }

            .login-card h2 {
                font-size: 23px;
            }

        }

    </style>

</head>

<body>

<div class="background-glow glow-one"></div>
<div class="background-glow glow-two"></div>

<div class="main-container">

    <!-- LEFT SIDE -->

    <section class="welcome-section">

        <div class="brand">
            <span class="brand-icon">ϟ</span>
            <span>VoltVision</span>
        </div>

        <div class="welcome-content">

            <span class="tagline">
                SMART ENERGY MANAGEMENT
            </span>

            <h1>
                Power Your Home.<br>
                <span>Save Your Energy.</span>
            </h1>

            <p class="welcome-description">
                Monitor electricity consumption, predict your monthly
                electricity bills and make smarter energy decisions
                with VoltVision.
            </p>

            <div class="features">

                <div class="feature">

                    <div class="feature-icon">⚡</div>

                    <div>
                        <h3>Smart Bill Prediction</h3>
                        <p>Estimate your electricity expenses.</p>
                    </div>

                </div>

                <div class="feature">

                    <div class="feature-icon">📊</div>

                    <div>
                        <h3>Energy Analytics</h3>
                        <p>Understand your monthly consumption.</p>
                    </div>

                </div>

                <div class="feature">

                    <div class="feature-icon">🌱</div>

                    <div>
                        <h3>Energy Awareness</h3>
                        <p>Make smarter electricity choices.</p>
                    </div>

                </div>

            </div>

        </div>

        <div class="footer-text">
            Smart Energy. Smart Savings. Better Tomorrow.
        </div>

    </section>

    <!-- RIGHT SIDE -->

    <section class="login-section">

        <div class="login-card">

            <div class="mobile-brand">
                ⚡ VoltVision
            </div>

            <div class="login-icon">
                ⚡
            </div>

            <span class="welcome-label">
                WELCOME BACK
            </span>

            <h2>Login to your account</h2>

            <p class="subtitle">
                Enter your details to access your energy dashboard.
            </p>

            <?php if (!empty($error)): ?>

                <div class="error-message">
                    <?= e($error) ?>
                </div>

            <?php endif; ?>

            <form method="POST" action="" class="login-form">

                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your email"
                        value="<?= e($email) ?>"
                        autocomplete="email"
                        required
                    >

                </div>

                <!-- PASSWORD -->

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <div class="password-wrap">

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="show-btn"
                            onclick="togglePassword('password', this)"
                        >
                            Show
                        </button>

                    </div>

                </div>

                <!-- FORGOT PASSWORD -->

                <div class="forgot-password">

                    <a href="forgot_password.php">
                        Forgot password?
                    </a>

                </div>

                <!-- LOGIN BUTTON -->

                <button type="submit" class="login-btn">

                    Login to VoltVision

                    <span>→</span>

                </button>

            </form>

            <!-- REGISTER LINK -->

            <div class="register-link">

                Don't have an account?

                <a href="register.php">
                    Create Account
                </a>

            </div>

            <!-- SECURITY -->

            <div class="secure-text">
                🔒 Your energy data is safe with us.
            </div>

        </div>

    </section>

</div>

<script>

function togglePassword(id, btn) {

    const input = document.getElementById(id);

    if (input.type === "password") {

        input.type = "text";
        btn.textContent = "Hide";

    } else {

        input.type = "password";
        btn.textContent = "Show";

    }
}

</script>

</body>
</html>
