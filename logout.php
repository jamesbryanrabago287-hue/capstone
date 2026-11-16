<?php
require_once __DIR__ . "/includes/auth.php";

// Not signed in (or someone typed the address): nothing to log out of
if (!isset($_SESSION["user"]) || $_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect(isset($_SESSION["user"]) ? "dashboard.php" : "login.php");
}

verify_csrf();

// Log first, while we still know who is signing out
record_activity($_SESSION["user"]["id"], $_SESSION["user"]["username"], "logout", "Signed out of the system");

// Empty the session, delete the cookie, then destroy it on the server
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), "", [
        "expires"  => time() - 42000,
        "path"     => $p["path"],
        "domain"   => $p["domain"],
        "secure"   => $p["secure"],
        "httponly" => $p["httponly"],
        "samesite" => $p["samesite"] ?: "Lax",
    ]);
}
session_destroy();

redirect("login.php");