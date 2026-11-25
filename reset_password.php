
<?php
session_start();

require_once __DIR__ . "/config/db.php";

$email = trim($_GET["email"] ?? $_POST["email"] ?? "");
$token = $_GET["token"] ?? $_POST["token"] ?? "";

$error = "";
$success = false;
$user = null;

// Fetch user using email
if ($email !== "" && $token !== "") {
    $stmt = $pdo->prepare(
        "SELECT id, reset_token_hash, reset_expires_at
         FROM users
         WHERE email = ?"
    );

    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Validate reset token and expiry
$valid = $user
    && !empty($user["reset_token_hash"])
    && !empty($user["reset_expires_at"])
    && strtotime($user["reset_expires_at"]) > time()
    && password_verify($token, $user["reset_token_hash"]);

// Handle password update
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $newPassword = $_POST["password"] ?? "";
    $confirm = $_POST["confirm_password"] ?? "";

    if (!$valid) {

        $error = "Reset link invalid or expired. Please generate a new link.";

    } elseif (strlen($newPassword) < 8) {

        $error = "Password must contain at least 8 characters.";

    } elseif ($newPassword !== $confirm) {

        $error = "Passwords do not match.";

    } else {

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        $upd = $pdo->prepare(
            "UPDATE users
             SET password_hash = ?,
                 reset_token_hash = NULL,
                 reset_expires_at = NULL
             WHERE id = ?"
        );

        $upd->execute([$hash, $user["id"]]);

        $success = true;
        $valid = false;
    }
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reset Password | VoltVision</title>

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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 25px;
            overflow-x: hidden;
        }

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

        .main-container {
            width: 100%;
            max-width: 1100px;
            min-height: 650px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #101d2d;
            border: 1px solid #26394d;
            border-radius: 24px;
            overflow: hidden;
            position: relative;
            z-index: 1;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.35);
        }

        /* LEFT SECTION */

        .welcome-section {
            padding: 55px 45px;
            background:
                linear-gradient(
                    145deg,
                    rgba(0, 245, 155, 0.08),
                    rgba(0, 217, 255, 0.04)
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
            font-size: 27px;
            font-weight: 800;
            color: #ffffff;
        }

        .brand-icon {
            color: #00f59b;
            font-size: 35px;
        }

        .welcome-content {
            margin-top: 40px;
        }

        .tagline {
            display: inline-block;
            color: #00f59b;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 2px;
            margin-bottom: 20px;
        }

        .welcome-content h1 {
            font-size: 42px;
            line-height: 1.2;
            margin-bottom: 22px;
            color: #ffffff;
        }

        .welcome-content h1 span {
            color: #00f59b;
        }

        .welcome-content > p {
            color: #8da3b5;
            font-size: 14px;
            line-height: 1.8;
            max-width: 400px;
            margin-bottom: 35px;
        }

        .features {
            display: flex;
            flex-direction: column;
            gap: 23px;
        }

        .feature {
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }

        .feature > span {
            width: 43px;
            height: 43px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 245, 155, 0.09);
            border: 1px solid rgba(0, 245, 155, 0.2);
            border-radius: 12px;
            font-size: 20px;
        }

        .feature h3 {
            font-size: 14px;
            color: #e9f4f5;
            margin-bottom: 6px;
        }

        .feature p {
            color: #8da3b5;
            font-size: 12px;
            line-height: 1.6;
        }

        .footer-text {
            color: #71879b;
            font-size: 11px;
            letter-spacing: 1px;
            margin-top: 35px;
        }

        /* RIGHT SECTION */

        .login-section {
            padding: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: 100%;
            max-width: 410px;
        }

        .mobile-brand {
            display: none;
        }

        .login-icon {
            width: 62px;
            height: 62px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            background: rgba(0, 245, 155, 0.09);
            border: 1px solid rgba(0, 245, 155, 0.25);
            border-radius: 18px;
            margin-bottom: 22px;
        }

        .welcome-label {
            color: #00f59b;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 2px;
        }

        .login-card h2 {
            font-size: 29px;
            color: #ffffff;
            margin-top: 12px;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #8da3b5;
            font-size: 13px;
            line-height: 1.7;
            margin-bottom: 28px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #dce9ee;
            margin-bottom: 9px;
            margin-top: 20px;
        }

        .password-wrap {
            position: relative;
            width: 100%;
        }

        .password-wrap input {
            width: 100%;
            padding: 15px 70px 15px 15px;
            background: #0b1726;
            border: 1px solid #26394d;
            border-radius: 10px;
            outline: none;
            color: #ffffff;
            font-size: 13px;
            transition: 0.3s;
        }

        .password-wrap input:focus {
            border-color: #00f59b;
            box-shadow: 0 0 0 3px rgba(0, 245, 155, 0.08);
        }

        input::placeholder {
            color: #60758a;
        }

        .show-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: auto;
            margin: 0;
            padding: 6px 9px;
            background: transparent;
            color: #00f59b;
            border: none;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .show-btn:hover {
            text-decoration: underline;
        }

        .submit-btn {
            width: 100%;
            padding: 15px;
            margin-top: 28px;
            border: none;
            border-radius: 11px;
            background: linear-gradient(135deg, #00f59b, #00d9ff);
            color: #04251d;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 245, 155, 0.2);
        }

        .reset-error {
            padding: 13px;
            border-radius: 9px;
            background: rgba(255, 70, 70, 0.12);
            border: 1px solid rgba(255, 100, 100, 0.35);
            color: #ffbaba;
            font-size: 13px;
            margin-bottom: 18px;
            line-height: 1.6;
        }

        .reset-success {
            padding: 20px;
            border-radius: 12px;
            background: rgba(0, 245, 155, 0.09);
            border: 1px solid rgba(0, 245, 155, 0.3);
            color: #a6ffda;
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 22px;
        }

        .reset-success-icon {
            font-size: 35px;
            color: #00f59b;
            margin-bottom: 10px;
        }

        .reset-link-button {
            display: block;
            text-align: center;
            padding: 15px;
            border-radius: 11px;
            background: linear-gradient(135deg, #00f59b, #00d9ff);
            color: #04251d;
            font-weight: 800;
            font-size: 14px;
            text-decoration: none;
            transition: 0.3s;
        }

        .reset-link-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 245, 155, 0.2);
        }

        .register-link {
            text-align: center;
            margin-top: 25px;
        }

        .register-link a {
            color: #00d9ff;
            font-size: 13px;
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            color: #00f59b;
        }

        .secure-text {
            margin-top: 30px;
            padding-top: 18px;
            border-top: 1px solid #26394d;
            text-align: center;
            color: #71879b;
            font-size: 11px;
        }

        @media (max-width: 850px) {
            .main-container {
                max-width: 550px;
                grid-template-columns: 1fr;
            }

            .welcome-section {
                display: none;
            }

            .login-section {
                padding: 40px 30px;
            }

            .mobile-brand {
                display: block;
                color: #ffffff;
                font-size: 23px;
                font-weight: 800;
                margin-bottom: 35px;
            }

            .mobile-brand span {
                color: #00f59b;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 12px;
            }

            .main-container {
                border-radius: 17px;
            }

            .login-section {
                padding: 30px 20px;
            }

            .login-card h2 {
                font-size: 25px;
            }

            .subtitle {
                font-size: 12px;
            }
        }
    </style>
</head>

<body>

<div class="background-glow glow-one"></div>
<div class="background-glow glow-two"></div>

<div class="main-container">

    <!-- LEFT SECTION -->

    <div class="welcome-section">

        <div class="brand">
            <span class="brand-icon">⚡</span>
            <span>VoltVision</span>
        </div>

        <div class="welcome-content">

            <span class="tagline">SECURE YOUR ACCOUNT</span>

            <h1>
                A Fresh Start.<br>
                <span>A Safer Account.</span>
            </h1>

            <p>
                Create a strong new password and continue
                your smart electricity management journey
                with VoltVision.
            </p>

            <div class="features">

                <div class="feature">
                    <span>🔐</span>
                    <div>
                        <h3>Secure Password</h3>
                        <p>Protect your account with a strong password.</p>
                    </div>
                </div>

                <div class="feature">
                    <span>⚡</span>
                    <div>
                        <h3>Stay Connected</h3>
                        <p>Continue accessing your energy dashboard.</p>
                    </div>
                </div>

                <div class="feature">
                    <span>🌱</span>
                    <div>
                        <h3>Smart Energy Journey</h3>
                        <p>Keep tracking your electricity usage.</p>
                    </div>
                </div>

            </div>

        </div>

        <div class="footer-text">
            Smart Energy. Smart Savings. Better Tomorrow.
        </div>

    </div>

    <!-- RIGHT SECTION -->

    <div class="login-section">

        <div class="login-card">

            <div class="mobile-brand">
                <span>⚡</span> VoltVision
            </div>

            <div class="login-icon">🔐</div>

            <span class="welcome-label">RESET PASSWORD</span>

            <h2>
                <?= $success ? "Password Updated!" : "Create New Password" ?>
            </h2>

            <p class="subtitle">
                <?= $success
                    ? "Your VoltVision account password has been successfully changed."
                    : "Enter your new password below to secure your account."
                ?>
            </p>

            <?php if ($success): ?>

                <div class="reset-success">

                    <div class="reset-success-icon">✓</div>

                    Your password has been successfully updated.
                    You can now login using your new password.

                </div>

                <a href="login.php" class="reset-link-button">
                    Go to Login →
                </a>

            <?php elseif (!$valid): ?>

                <?php if ($error): ?>
                    <div class="reset-error">
                        <?= e($error) ?>
                    </div>
                <?php else: ?>
                    <div class="reset-error">
                        Reset link is invalid or expired.
                        Please generate a new reset link.
                    </div>
                <?php endif; ?>

                <a href="forgot_password.php" class="reset-link-button">
                    Generate New Reset Link →
                </a>

                <div class="register-link">
                    <a href="login.php">← Back to Login</a>
                </div>

            <?php else: ?>

                <?php if ($error): ?>
                    <div class="reset-error">
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">

                    <input
                        type="hidden"
                        name="email"
                        value="<?= e($email) ?>"
                    >

                    <input
                        type="hidden"
                        name="token"
                        value="<?= e($token) ?>"
                    >

                    <label for="password">New Password</label>

                    <div class="password-wrap">

                        <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Minimum 8 characters"
                            required
                        >

                        <button
                            class="show-btn"
                            type="button"
                            onclick="togglePassword('password', this)"
                        >
                            Show
                        </button>

                    </div>

                    <label for="confirm_password">
                        Confirm New Password
                    </label>

                    <div class="password-wrap">

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            minlength="8"
                            autocomplete="new-password"
                            placeholder="Re-enter your password"
                            required
                        >

                        <button
                            class="show-btn"
                            type="button"
                            onclick="togglePassword('confirm_password', this)"
                        >
                            Show
                        </button>

                    </div>

                    <button type="submit" class="submit-btn">
                        Update Password →
                    </button>

                </form>

                <div class="register-link">
                    <a href="login.php">← Back to Login</a>
                </div>

            <?php endif; ?>

            <div class="secure-text">
                🔒 Your account information is protected.
            </div>

        </div>

    </div>

</div>

<script>
function togglePassword(id, btn) {

    const input = document.getElementById(id);

    const showing = input.type === "password";

    input.type = showing ? "text" : "password";

    btn.textContent = showing ? "Hide" : "Show";
}
</script>

</body>
</html>
