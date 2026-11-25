
<?php
session_start();

require_once __DIR__ . "/config/db.php";

$message = "";
$demoLink = "";
$emailValue = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $emailValue = $email;

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {

            $token = bin2hex(random_bytes(32));
            $tokenHash = password_hash($token, PASSWORD_DEFAULT);
            $expires = date("Y-m-d H:i:s", time() + 1800);

            $upd = $pdo->prepare(
                "UPDATE users
                 SET reset_token_hash = ?, reset_expires_at = ?
                 WHERE id = ?"
            );

            $upd->execute([
                $tokenHash,
                $expires,
                $user["id"]
            ]);

            $demoLink = "reset_password.php?token=" .
                urlencode($token) . "&email=" . urlencode($email);
        }
    }

    // Same message whether email exists or not
    $message = "If that email is registered, a password reset option is ready.";
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

<title>Forgot Password | VoltVision</title>

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
    margin: 50px 0;
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
    margin-top: 38px;
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
    max-width: 450px;
    padding: 40px;
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
    margin-bottom: 25px;
}

.login-icon {
    width: 62px;
    height: 62px;
    display: grid;
    place-items: center;
    font-size: 29px;
    border-radius: 17px;
    color: #07111f;
    background: linear-gradient(135deg, #00f59b, #00d9ff);
    margin-bottom: 25px;
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
    margin-bottom: 25px;
}

.message {
    background: rgba(0,245,155,0.09);
    color: #9bffd4;
    border: 1px solid rgba(0,245,155,0.3);
    padding: 13px;
    border-radius: 9px;
    font-size: 13px;
    line-height: 1.6;
    margin-bottom: 20px;
}

form label {
    display: block;
    color: #d7e5ed;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 9px;
}

form input {
    width: 100%;
    height: 49px;
    background: #0a1827;
    border: 1px solid #2c4256;
    border-radius: 9px;
    outline: none;
    padding: 0 14px;
    color: #ffffff;
    font-size: 14px;
    margin-bottom: 20px;
    transition: 0.25s;
}

form input::placeholder {
    color: #71889a;
}

form input:focus {
    border-color: #00d9ff;
    box-shadow: 0 0 0 3px rgba(0,217,255,0.09);
}

form button {
    width: 100%;
    height: 50px;
    border: none;
    border-radius: 10px;
    background: linear-gradient(90deg, #00f59b, #00d9ff);
    color: #07111f;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    transition: 0.25s;
}

form button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(0,245,155,0.2);
}

form button span {
    margin-left: 8px;
    font-size: 18px;
}

/* DEMO RESET LINK */

.demo-link {
    margin-top: 22px;
    padding: 18px;
    background: #0b1928;
    border: 1px solid #285064;
    border-radius: 12px;
}

.demo-link h3 {
    color: #00f59b;
    font-size: 15px;
    margin-bottom: 8px;
}

.demo-link p {
    color: #9aafbf;
    font-size: 12px;
    line-height: 1.6;
    margin-bottom: 14px;
}

.reset-action {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 12px 15px;
    border-radius: 9px;
    background: linear-gradient(90deg, #00f59b, #00d9ff);
    color: #07111f;
    text-decoration: none;
    font-size: 13px;
    font-weight: 800;
    transition: 0.2s;
}

.reset-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,245,155,0.2);
}

.demo-link small {
    display: block;
    color: #71889a;
    font-size: 11px;
    line-height: 1.6;
    margin-top: 13px;
}

.register-link {
    text-align: center;
    margin-top: 25px;
}

.register-link a {
    color: #00d9ff;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
}

.register-link a:hover {
    color: #00f59b;
}

.secure-text {
    text-align: center;
    margin-top: 25px;
    padding-top: 20px;
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
        padding: 28px 22px;
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

            <span class="tagline">ACCOUNT RECOVERY</span>

            <h1>
                Let's Get You<br>
                <span>Back in Control.</span>
            </h1>

            <p>
                Forgot your password? Don't worry.
                Recover your VoltVision account and continue
                monitoring your electricity consumption.
            </p>

            <div class="features">

                <div class="feature">
                    <span>🔐</span>
                    <div>
                        <h3>Secure Recovery</h3>
                        <p>Reset your password securely.</p>
                    </div>
                </div>

                <div class="feature">
                    <span>⚡</span>
                    <div>
                        <h3>Continue with VoltVision</h3>
                        <p>Access your energy dashboard again.</p>
                    </div>
                </div>

                <div class="feature">
                    <span>📊</span>
                    <div>
                        <h3>Your Data, Your Control</h3>
                        <p>Continue tracking your energy usage.</p>
                    </div>
                </div>

            </div>

        </div>

        <div class="footer-text">
            Smart Energy. Smart Savings. Better Tomorrow.
        </div>

    </section>

    <!-- RIGHT SECTION -->

    <section class="login-section">

        <div class="login-card">

            <div class="mobile-brand">⚡ VoltVision</div>

            <div class="login-icon">🔐</div>

            <span class="welcome-label">ACCOUNT RECOVERY</span>

            <h2>Forgot Password?</h2>

            <p class="subtitle">
                Enter your registered email address.
                We will prepare a password reset option for you.
            </p>

            <?php if (!empty($message)): ?>
                <div class="message">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="forgot_password.php">

                <label for="email">Email Address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your registered email"
                    value="<?= e($emailValue) ?>"
                    autocomplete="email"
                    required
                >

                <button type="submit">
                    Generate Reset Link <span>→</span>
                </button>

            </form>

            <?php if (!empty($demoLink)): ?>

                <div class="demo-link">

                    <h3>⚡ Local Demo Reset Link</h3>

                    <p>
                        Click below to open the password reset page.
                    </p>

                    <a href="<?= e($demoLink) ?>" class="reset-action">
                        <span>Reset My Password</span>
                        <span>→</span>
                    </a>

                    <small>
                        College project demonstration only.
                        In production, this link should be sent through email.
                        This reset link expires in 30 minutes.
                    </small>

                </div>

            <?php endif; ?>

            <div class="register-link">
                <a href="login.php">← Back to Login</a>
            </div>

            <div class="secure-text">
                🔒 Your account information is protected.
            </div>

        </div>

    </section>

</div>

</body>
</html>
