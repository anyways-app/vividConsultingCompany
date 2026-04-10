<?php
/**
 * vividConsulting.info — Central Configuration
 *
 * Fill in every placeholder before deploying.
 * For QA set FORCE_HTTPS = false; for production set it to true.
 */

return [

    // ── Environment ───────────────────────────────────────────
    'FORCE_HTTPS' => false,   // true in production (controls cookie Secure flag + redirect URIs)
    'BASE_URL'    => 'http://www.vividconsulting.info/qa',  // no trailing slash

    // ── Google OAuth 2.0 ──────────────────────────────────────
    'GOOGLE_CLIENT_ID'     => 'REPLACE_WITH_GOOGLE_CLIENT_ID',
    'GOOGLE_CLIENT_SECRET' => 'REPLACE_WITH_GOOGLE_CLIENT_SECRET',
    'GOOGLE_REDIRECT_URI'  => 'http://www.vividconsulting.info/qa/callback.php',

    // ── Stripe ────────────────────────────────────────────────
    'STRIPE_PUBLISHABLE_KEY'    => 'REPLACE_WITH_STRIPE_PUBLISHABLE_KEY',
    'STRIPE_SECRET_KEY'         => 'REPLACE_WITH_STRIPE_SECRET_KEY',
    'STRIPE_WEBHOOK_SECRET'     => 'REPLACE_WITH_STRIPE_WEBHOOK_SIGNING_SECRET',
    'STRIPE_PRICE_MONTHLY'      => 'REPLACE_WITH_STRIPE_PRICE_ID_MONTHLY',   // $199/mo
    'STRIPE_PRICE_ANNUAL'       => 'REPLACE_WITH_STRIPE_PRICE_ID_ANNUAL',    // $1,433/yr

    // ── PostgreSQL ────────────────────────────────────────────
    'DB_HOST'     => 'localhost',
    'DB_PORT'     => '5432',
    'DB_NAME'     => 'REPLACE_WITH_DB_NAME',
    'DB_USER'     => 'REPLACE_WITH_DB_USER',
    'DB_PASSWORD' => 'REPLACE_WITH_DB_PASSWORD',

    // ── Session ───────────────────────────────────────────────
    'SESSION_COOKIE_NAME'     => 'vc_session',
    'SESSION_LIFETIME_HOURS'  => 72,          // 3 days
    'SESSION_COOKIE_SECURE'   => false,       // set true in production (requires HTTPS)
    'SESSION_COOKIE_HTTPONLY'  => true,
    'SESSION_COOKIE_SAMESITE' => 'Lax',
    'SESSION_COOKIE_PATH'     => '/qa/',      // set to '/' in production
];
