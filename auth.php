<?php
/**
 * vividConsulting.info — Authentication Logic
 *
 * Google OAuth 2.0 flow + session management + find-or-create user.
 * Designed for multi-provider linking (Google now, Microsoft/Apple later).
 */

require_once __DIR__ . '/db.php';

/**
 * Generate a CSRF-safe state token, store it in $_SESSION, and return
 * the full Google authorization URL to redirect the user to.
 */
function auth_google_authorize_url(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $cfg   = require __DIR__ . '/config.php';
    $state = bin2hex(random_bytes(32));
    $_SESSION['oauth_state'] = $state;

    $params = http_build_query([
        'client_id'     => $cfg['GOOGLE_CLIENT_ID'],
        'redirect_uri'  => $cfg['GOOGLE_REDIRECT_URI'],
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'access_type'   => 'online',
        'state'         => $state,
        'prompt'        => 'select_account',
    ]);

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
}

/**
 * Exchange the authorization code for tokens, validate the ID token,
 * and return the decoded claims array.
 *
 * @return array{sub:string, email:string, email_verified:bool, name:string, given_name:string, family_name:string, picture:string, locale:string}
 * @throws RuntimeException on any validation failure
 */
function auth_google_exchange_code(string $code): array
{
    $cfg = require __DIR__ . '/config.php';

    // Exchange code for tokens
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'code'          => $code,
            'client_id'     => $cfg['GOOGLE_CLIENT_ID'],
            'client_secret' => $cfg['GOOGLE_CLIENT_SECRET'],
            'redirect_uri'  => $cfg['GOOGLE_REDIRECT_URI'],
            'grant_type'    => 'authorization_code',
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new RuntimeException('Google token exchange failed: ' . $response);
    }

    $tokens = json_decode($response, true);
    if (empty($tokens['id_token'])) {
        throw new RuntimeException('No id_token in Google response');
    }

    // Decode and validate ID token (using Google's tokeninfo endpoint for simplicity;
    // in production you may verify the JWT signature locally with Google's public keys)
    $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($tokens['id_token']));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $idResponse = curl_exec($ch);
    $idHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($idHttpCode !== 200) {
        throw new RuntimeException('Google ID token validation failed');
    }

    $claims = json_decode($idResponse, true);

    // Verify audience matches our client ID
    if (($claims['aud'] ?? '') !== $cfg['GOOGLE_CLIENT_ID']) {
        throw new RuntimeException('ID token audience mismatch');
    }

    return $claims;
}

/**
 * Validate the OAuth state parameter against the session.
 *
 * @throws RuntimeException on mismatch
 */
function auth_validate_state(string $state): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $expected = $_SESSION['oauth_state'] ?? '';
    unset($_SESSION['oauth_state']);

    if (!hash_equals($expected, $state)) {
        throw new RuntimeException('OAuth state mismatch — possible CSRF');
    }
}

/**
 * Find or create a user from provider claims.
 *
 * Logic:
 * 1. Look up auth_identities by (provider, provider_user_id).
 *    → If found, update last_login_at and provider_data, return user.
 * 2. If not found, check if a verified-email user exists with that email.
 *    → If found, create a new auth_identity linking this provider, return user.
 * 3. If still not found, create a brand new user + auth_identity.
 *
 * @param string $provider  'google', 'microsoft', or 'apple'
 * @param array  $claims    Decoded ID token claims
 * @return array  The user row from the users table
 */
function auth_find_or_create_user(string $provider, array $claims): array
{
    $pdo = vc_db();

    $providerUserId = $claims['sub'] ?? ($claims['oid'] ?? '');
    $providerEmail  = $claims['email'] ?? null;
    $emailVerified  = !empty($claims['email_verified']) && $claims['email_verified'] !== 'false';
    $displayName    = $claims['name']        ?? null;
    $givenName      = $claims['given_name']  ?? null;
    $familyName     = $claims['family_name'] ?? null;
    $avatarUrl      = $claims['picture']     ?? null;
    $locale         = $claims['locale']      ?? null;

    // 1. Check existing auth identity
    $stmt = $pdo->prepare('
        SELECT u.* FROM auth_identities ai
        JOIN users u ON u.id = ai.user_id
        WHERE ai.provider = :provider AND ai.provider_user_id = :pid
    ');
    $stmt->execute(['provider' => $provider, 'pid' => $providerUserId]);
    $user = $stmt->fetch();

    if ($user) {
        // Update last_login_at and provider_data
        $pdo->prepare('UPDATE users SET last_login_at = now() WHERE id = :id')
            ->execute(['id' => $user['id']]);
        $pdo->prepare('UPDATE auth_identities SET provider_data = :data WHERE provider = :prov AND provider_user_id = :pid')
            ->execute(['data' => json_encode($claims), 'prov' => $provider, 'pid' => $providerUserId]);
        $user['last_login_at'] = date('c');
        return $user;
    }

    // 2. Check if a user with this verified email already exists (account linking)
    if ($providerEmail && $emailVerified) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email AND email_verified = true');
        $stmt->execute(['email' => $providerEmail]);
        $user = $stmt->fetch();

        if ($user) {
            // Link new provider to existing user
            $pdo->prepare('
                INSERT INTO auth_identities (user_id, provider, provider_user_id, provider_email, provider_data)
                VALUES (:uid, :prov, :pid, :pemail, :data)
            ')->execute([
                'uid'    => $user['id'],
                'prov'   => $provider,
                'pid'    => $providerUserId,
                'pemail' => $providerEmail,
                'data'   => json_encode($claims),
            ]);

            $pdo->prepare('UPDATE users SET last_login_at = now() WHERE id = :id')
                ->execute(['id' => $user['id']]);
            $user['last_login_at'] = date('c');
            return $user;
        }
    }

    // 3. Create new user + auth identity
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('
            INSERT INTO users (email, email_verified, display_name, given_name, family_name, avatar_url, locale, last_login_at)
            VALUES (:email, :ev, :dn, :gn, :fn, :av, :loc, now())
            RETURNING *
        ');
        $stmt->execute([
            'email' => $providerEmail ?? ($providerUserId . '@' . $provider . '.placeholder'),
            'ev'    => $emailVerified ? 'true' : 'false',
            'dn'    => $displayName,
            'gn'    => $givenName,
            'fn'    => $familyName,
            'av'    => $avatarUrl,
            'loc'   => $locale,
        ]);
        $user = $stmt->fetch();

        $pdo->prepare('
            INSERT INTO auth_identities (user_id, provider, provider_user_id, provider_email, provider_data)
            VALUES (:uid, :prov, :pid, :pemail, :data)
        ')->execute([
            'uid'    => $user['id'],
            'prov'   => $provider,
            'pid'    => $providerUserId,
            'pemail' => $providerEmail,
            'data'   => json_encode($claims),
        ]);

        $pdo->commit();
        return $user;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Create a new session for the given user, set the cookie, return session ID.
 */
function auth_create_session(string $userId): string
{
    $cfg = require __DIR__ . '/config.php';
    $pdo = vc_db();

    $lifetimeSeconds = $cfg['SESSION_LIFETIME_HOURS'] * 3600;
    $expiresAt = date('c', time() + $lifetimeSeconds);

    $stmt = $pdo->prepare('
        INSERT INTO sessions (user_id, expires_at)
        VALUES (:uid, :exp)
        RETURNING id
    ');
    $stmt->execute(['uid' => $userId, 'exp' => $expiresAt]);
    $sessionId = $stmt->fetchColumn();

    setcookie($cfg['SESSION_COOKIE_NAME'], $sessionId, [
        'expires'  => time() + $lifetimeSeconds,
        'path'     => $cfg['SESSION_COOKIE_PATH'],
        'secure'   => $cfg['SESSION_COOKIE_SECURE'],
        'httponly'  => $cfg['SESSION_COOKIE_HTTPONLY'],
        'samesite' => $cfg['SESSION_COOKIE_SAMESITE'],
    ]);

    return $sessionId;
}

/**
 * Get the currently authenticated user from the session cookie.
 * Returns null if not authenticated or session expired.
 *
 * @return array|null  User row with subscription_tier
 */
function auth_current_user(): ?array
{
    $cfg = require __DIR__ . '/config.php';

    $sessionId = $_COOKIE[$cfg['SESSION_COOKIE_NAME']] ?? null;
    if (!$sessionId) {
        return null;
    }

    $pdo  = vc_db();
    $stmt = $pdo->prepare('
        SELECT u.* FROM sessions s
        JOIN users u ON u.id = s.user_id
        WHERE s.id = :sid AND s.expires_at > now()
    ');
    $stmt->execute(['sid' => $sessionId]);
    return $stmt->fetch() ?: null;
}

/**
 * Destroy the current session: delete from DB and clear the cookie.
 */
function auth_logout(): void
{
    $cfg = require __DIR__ . '/config.php';

    $sessionId = $_COOKIE[$cfg['SESSION_COOKIE_NAME']] ?? null;
    if ($sessionId) {
        $pdo = vc_db();
        $pdo->prepare('DELETE FROM sessions WHERE id = :sid')->execute(['sid' => $sessionId]);
    }

    // Clear cookie
    setcookie($cfg['SESSION_COOKIE_NAME'], '', [
        'expires'  => time() - 3600,
        'path'     => $cfg['SESSION_COOKIE_PATH'],
        'secure'   => $cfg['SESSION_COOKIE_SECURE'],
        'httponly'  => $cfg['SESSION_COOKIE_HTTPONLY'],
        'samesite' => $cfg['SESSION_COOKIE_SAMESITE'],
    ]);
}

/**
 * Generate a CSRF token for forms, stored in PHP session.
 */
function auth_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token.
 *
 * @throws RuntimeException on mismatch
 */
function auth_csrf_validate(string $token): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        throw new RuntimeException('CSRF token mismatch');
    }
}
