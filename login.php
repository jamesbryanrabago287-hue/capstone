<?php
require_once __DIR__ . "/includes/auth.php";

if (isset($_SESSION["user"])) {
    redirect("dashboard.php");
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf();

    $username = mb_substr(trim((string)($_POST["username"] ?? "")), 0, 50);
    $password = is_string($_POST["password"] ?? null) ? $_POST["password"] : "";

    if (too_many_failed_logins($username)) {
        $error = "Too many failed attempts. Please wait 15 minutes and try again.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ? AND status = 'Active' LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password"])) {
            login_user($user);
            record_activity($user["id"], $user["username"], "login", "Signed in to the system");
            redirect("dashboard.php");
        }

        record_activity(0, $username !== "" ? $username : "unknown", "login_failed", "Failed sign-in attempt");
        $error = "Invalid username or password.";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login - Campus Clinic | ISAT U</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
    <div class="login-shell">
        <div class="login-hero">
            <img src="assets/isatu-logo.png" alt="ISAT U" class="hero-logo" onerror="this.style.display='none'">
            <div class="hero-badge">ISAT U · Campus Health</div>
            <h1>Campus Clinic</h1>
            <p>Medicine Inventory and Decision Support System</p>
            <ul class="hero-points">
                <li>Track stock levels</li>
                <li>Monitor expirations</li>
                <li>Support clinic staff decisions</li>
            </ul>
        </div>

        <div class="login-card">
            <div class="card-header">
                <span class="eyebrow">Welcome back</span>
                <h2>Clinic staff sign in</h2>
            </div>

            <?php render_flash(); ?>
            <?php if ($error): ?><div class="alert danger"><?= h($error) ?></div><?php endif; ?>

            <form method="post" class="login-form">
                <?= csrf_field() ?>
                <label>
                    <span>Username</span>
                    <input name="username" maxlength="50" placeholder="Enter your username" autocomplete="username" required autofocus>
                </label>
                <label>
                    <span>Password</span>
                    <input type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                </label>
                <button class="btn primary" type="submit">Log In</button>
            </form>

            <?php if (APP_DEBUG): // demo credentials are only shown while developing ?>
            <div class="demo-box">
                <strong>Demo accounts (debug mode only)</strong>
                <div>Admin: admin / admin123</div>
                <div>Staff: staff / staff123</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php include __DIR__.'/includes/chatbot.php'; ?>
</body>
</html>