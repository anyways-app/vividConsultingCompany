<?php
/**
 * vividConsulting.info — OAuth Callback Endpoint
 *
 * Google redirects here after the user authorizes.
 * Validates state, exchanges code, finds/creates user, creates session,
 * then redirects to the dashboard.
 */

session_start();

require_once __DIR__ . '/auth.php';

$cfg = require __DIR__ . '/config.php';

try {
    // ── Validate inputs ───────────────────────────────────────
    $code  = $_GET['code']  ?? '';
    $state = $_GET['state'] ?? '';
    $error = $_GET['error'] ?? '';

    if ($error) {
        throw new RuntimeException('Google returned error: ' . htmlspecialchars($error));
    }
    if (!$code || !$state) {
        throw new RuntimeException('Missing authorization code or state parameter');
    }

    // ── Validate CSRF state ───────────────────────────────────
    auth_validate_state($state);

    // ── Exchange code for ID token claims ─────────────────────
    $claims = auth_google_exchange_code($code);

    // ── Find or create user ───────────────────────────────────
    $user = auth_find_or_create_user('google', $claims);

    // ── Create session ────────────────────────────────────────
    auth_create_session($user['id']);

    // ── Redirect to dashboard ─────────────────────────────────
    header('Location: ' . $cfg['BASE_URL'] . '/dashboard.html');
    exit;

} catch (Throwable $e) {
    // Log the error (to server error log)
    error_log('[vividConsulting auth] Callback error: ' . $e->getMessage());

    // Redirect to login with error flag
    header('Location: ' . $cfg['BASE_URL'] . '/login.html?error=auth_failed');
    exit;
}
