<?php
/**
 * vividConsulting.info — API: Initiate Google Login
 *
 * GET /qa/api/login.php
 * Generates the Google OAuth URL and redirects the user.
 */

require_once __DIR__ . '/../auth.php';

$url = auth_google_authorize_url();
header('Location: ' . $url);
exit;
