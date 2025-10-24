<?php
// src/auth/logout.php
require_once __DIR__ . '/bootstrap.php'; // loads session_start(), db, etc.

// Destroy all session data securely
$_SESSION = [];

// Delete session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Finally destroy the session
session_destroy();

// Redirect to homepage or login page
header("Location: ../index.php");
exit;
