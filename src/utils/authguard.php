<?php
// src/utils/auth.php
require_once __DIR__ . '/bootstrap.php';

/**
 * Guard the page and return the authenticated user id.
 * Redirects to /auth.php if not logged in.
 */
function requireAuth(): int {
    if (empty($_SESSION['user_id'])) {
        $next = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        redirect("/auth.php?login_required=1&next={$next}");
    }
    return (int) $_SESSION['user_id'];
}
