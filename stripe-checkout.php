<?php
/**
 * vividConsulting.info — Stripe Checkout Session Creator
 *
 * Accepts GET parameter: ?plan=monthly|annual
 * Creates a Stripe Checkout Session and redirects the user.
 * Requires the user to be authenticated.
 */

session_start();

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/vendor/autoload.php';

$cfg  = require __DIR__ . '/config.php';
$user = auth_current_user();

if (!$user) {
    header('Location: ' . $cfg['BASE_URL'] . '/login.html?redirect=upgrade');
    exit;
}

// ── CSRF check (token passed as query param from dashboard) ──
$csrfToken = $_GET['csrf'] ?? '';
try {
    auth_csrf_validate($csrfToken);
} catch (RuntimeException $e) {
    http_response_code(403);
    echo 'Invalid CSRF token. Please go back and try again.';
    exit;
}

// ── Determine price ID ───────────────────────────────────────
$plan = $_GET['plan'] ?? 'monthly';
$priceId = ($plan === 'annual')
    ? $cfg['STRIPE_PRICE_ANNUAL']
    : $cfg['STRIPE_PRICE_MONTHLY'];

// ── Create Stripe Checkout Session ───────────────────────────
\Stripe\Stripe::setApiKey($cfg['STRIPE_SECRET_KEY']);

try {
    $checkoutParams = [
        'mode'                => 'subscription',
        'customer_email'      => $user['email'],
        'line_items'          => [[
            'price'    => $priceId,
            'quantity' => 1,
        ]],
        'metadata'            => [
            'vc_user_id' => $user['id'],
        ],
        'subscription_data'   => [
            'metadata' => [
                'vc_user_id' => $user['id'],
            ],
        ],
        'success_url'         => $cfg['BASE_URL'] . '/dashboard.html?subscription=success',
        'cancel_url'          => $cfg['BASE_URL'] . '/dashboard.html?subscription=cancelled',
    ];

    // If user already has a Stripe customer ID, use it
    if (!empty($user['stripe_customer_id'])) {
        unset($checkoutParams['customer_email']);
        $checkoutParams['customer'] = $user['stripe_customer_id'];
    }

    $session = \Stripe\Checkout\Session::create($checkoutParams);

    header('Location: ' . $session->url);
    exit;

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log('[vividConsulting stripe] Checkout error: ' . $e->getMessage());
    header('Location: ' . $cfg['BASE_URL'] . '/dashboard.html?subscription=error');
    exit;
}
