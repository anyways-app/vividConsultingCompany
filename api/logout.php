<?php
/**
 * vividConsulting.info — API: Logout
 *
 * POST /qa/api/logout.php
 * Destroys the session and clears the cookie.
 * Redirects to the home page.
 */

require_once __DIR__ . '/../auth.php';

auth_logout();

$cfg = require __DIR__ . '/../config.php';
header('Location: ' . $cfg['BASE_URL'] . '/index.html');
exit;
