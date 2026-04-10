<?php
/**
 * vividConsulting.info — API: Current User Profile
 *
 * GET /qa/api/user.php
 * Returns JSON with the authenticated user's profile.
 * Returns 401 if not authenticated.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../auth.php';

$user = auth_current_user();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Return safe subset of user data (exclude internal fields)
echo json_encode([
    'id'                => $user['id'],
    'email'             => $user['email'],
    'display_name'      => $user['display_name'],
    'given_name'        => $user['given_name'],
    'family_name'       => $user['family_name'],
    'avatar_url'        => $user['avatar_url'],
    'subscription_tier' => $user['subscription_tier'],
    'created_at'        => $user['created_at'],
]);
